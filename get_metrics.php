<?php
require 'includes/db.php';

// Return JSON data for charts
header('Content-Type: application/json');

// Example: Fetch LTV trend from database
$ltvQuery = $conn->query("
    SELECT date, value
    FROM metrics
    WHERE name = 'ltv_30day'
    ORDER BY date DESC
    LIMIT 30
");
$ltvData = [];
while ($row = $ltvQuery->fetch_assoc()) {
    $ltvData[] = $row;
}

echo json_encode([
    'ltvTrend' => [
        'labels' => array_column($ltvData, 'date'),
        'values' => array_column($ltvData, 'value')
    ],
    // Add other metrics here
]);
?>
