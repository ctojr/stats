<?php
require 'includes/db.php';

// Configuration
$alertThreshold = 100; // Alert if traffic exceeds this number in the last hour
$adminEmail = '---';
$slackWebhook = 'https://hooks.slack.com/services/your/webhook'; // Optional

// Check for high-traffic funnels
$highTrafficQuery = $conn->query("
    SELECT
        funnel_name,
        page_type,
        COUNT(DISTINCT visitor_id) as visitors
    FROM traffic
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY funnel_name, page_type
    HAVING visitors > {$alertThreshold}
    ORDER BY visitors DESC
");

$alerts = [];
while ($row = $highTrafficQuery->fetch_assoc()) {
    $alerts[] = [
        'funnel' => $row['funnel_name'],
        'page_type' => $row['page_type'],
        'visitors' => $row['visitors'],
        'time' => date('Y-m-d H:i:s')
    ];
}

// Check for sudden conversion drops
$conversionDropQuery = $conn->query("
    SELECT
        funnel_name,
        step_type,
        SUM(conversion_count) as current_conversions,
        (SELECT SUM(conversion_count)
         FROM conversions
         WHERE funnel_name = c.funnel_name
         AND step_type = c.step_type
         AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 HOUR) AND DATE_SUB(NOW(), INTERVAL 1 HOUR)) as previous_conversions
    FROM conversions c
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    GROUP BY funnel_name, step_type
    HAVING previous_conversions > 0
    AND current_conversions < (previous_conversions * 0.5)  -- 50% drop
");

while ($row = $conversionDropQuery->fetch_assoc()) {
    $dropPercent = (1 - ($row['current_conversions'] / $row['previous_conversions'])) * 100;
    $alerts[] = [
        'type' => 'conversion_drop',
        'funnel' => $row['funnel_name'],
        'step_type' => $row['step_type'],
        'current' => $row['current_conversions'],
        'previous' => $row['previous_conversions'],
        'drop_percent' => round($dropPercent, 1),
        'time' => date('Y-m-d H:i:s')
    ];
}

// Send alerts via email
if (!empty($alerts)) {
    $subject = "Funnel Alert: " . count($alerts) . " issues detected";
    $message = "The following issues were detected in the last hour:\n\n";
    foreach ($alerts as $alert) {
        if (isset($alert['type']) && $alert['type'] === 'conversion_drop') {
            $message .= "- CONVERSION DROP in {$alert['funnel']} ({$alert['step_type']}): ";
            $message .= "Dropped by {$alert['drop_percent']}% (from {$alert['previous']} to {$alert['current']}) at {$alert['time']}\n";
        } else {
            $message .= "- HIGH TRAFFIC in {$alert['funnel']} ({$alert['page_type']}): ";
            $message .= "{$alert['visitors']} visitors in the last hour (threshold: {$alertThreshold}) at {$alert['time']}\n";
        }
    }

    // Send email
    mail($adminEmail, $subject, $message);

    // Send to Slack (if configured)
    if (!empty($slackWebhook)) {
        $slackMessage = [
            'text' => $subject,
            'attachments' => [
                ['text' => $message, 'color' => 'danger']
            ]
        ];
        $ch = curl_init($slackWebhook);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($slackMessage));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
    }

    // Log alerts to database
    foreach ($alerts as $alert) {
        $alertType = $alert['type'] ?? 'high_traffic';
        $details = json_encode($alert);
        $conn->query("
            INSERT INTO alerts (alert_type, funnel_name, details, created_at)
            VALUES ('{$alertType}', '{$alert['funnel']}', '{$details}', NOW())
        ");
    }
}

echo "Alert check completed at " . date('Y-m-d H:i:s') . ". " . count($alerts) . " alerts generated.\n";
?>
