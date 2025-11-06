<?php
require "includes/db.php";
require "includes/header.php";

// Fetch data for charts
$conversionsQuery = $conn->query("
    SELECT
        funnel_name,
        step_type,
        SUM(conversion_count) as conversions,
        SUM(revenue) as revenue
    FROM conversions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY funnel_name, step_type
    ORDER BY revenue DESC
");
$conversionsData = [];
while ($row = $conversionsQuery->fetch_assoc()) {
    $conversionsData[$row["funnel_name"]][$row["step_type"]] = [
        "conversions" => $row["conversions"],
        "revenue" => $row["revenue"],
    ];
}

$trafficQuery = $conn->query("
    SELECT
        funnel_name,
        page_type,
        COUNT(DISTINCT visitor_id) as visitors
    FROM traffic
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY funnel_name, page_type
    ORDER BY visitors DESC
");
$trafficData = [];
while ($row = $trafficQuery->fetch_assoc()) {
    $trafficData[$row["funnel_name"]][$row["page_type"]] = $row["visitors"];
}

$leadsQuery = $conn->query("
    SELECT
        funnel_name,
        lead_source,
        COUNT(*) as leads
    FROM leads
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY funnel_name, lead_source
    ORDER BY leads DESC
");
$leadsData = [];
while ($row = $leadsQuery->fetch_assoc()) {
    $leadsData[$row["funnel_name"]][$row["lead_source"]] = $row["leads"];
}

$utmQuery = $conn->query("
    SELECT
        utm_source,
        COUNT(*) as count
    FROM leads
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY utm_source
    ORDER BY count DESC
    LIMIT 5
");
$utmData = [];
while ($row = $utmQuery->fetch_assoc()) {
    $utmData[$row["utm_source"]] = $row["count"];
}
?>

<div class="container mt-4">
    <h1 class="text-left mb-4">Dashboard</h1>

    <!-- Key Metrics Cards -->
    <div class="row mb-1">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Total Conversions (30d)</h5>
                    <p class="card-text fs-3">
                        <?php
                        $totalConversions = $conn
                            ->query(
                                "SELECT SUM(conversion_count) as total FROM conversions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                            )
                            ->fetch_assoc()["total"];
                        echo $totalConversions ?? 0;
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue (30d)</h5>
                    <p class="card-text fs-3">
                        $<?php
                        $totalRevenue = $conn
                            ->query(
                                "SELECT SUM(revenue) as total FROM conversions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                            )
                            ->fetch_assoc()["total"];
                        echo number_format($totalRevenue ?? 0, 2);
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Total Traffic (30d)</h5>
                    <p class="card-text fs-3">
                        <?php
                        $totalTraffic = $conn
                            ->query(
                                "SELECT COUNT(DISTINCT visitor_id) as total FROM traffic WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                            )
                            ->fetch_assoc()["total"];
                        echo $totalTraffic ?? 0;
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title">Total Leads (30d)</h5>
                    <p class="card-text fs-3">
                        <?php
                        $totalLeads = $conn
                            ->query(
                                "SELECT COUNT(*) as total FROM leads WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                            )
                            ->fetch_assoc()["total"];
                        echo $totalLeads ?? 0;
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Tables -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Detailed Conversion Data</h5>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Funnel</th>
                                <th>Step</th>
                                <th>Conversions</th>
                                <th>Revenue</th>
                                <th>Conversion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (
                                $conversionsData
                                as $funnel => $steps
                            ): ?>
                                <?php foreach ($steps as $step => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(
                                            $funnel,
                                        ); ?></td>
                                        <td><?php echo htmlspecialchars(
                                            $step,
                                        ); ?></td>
                                        <td><?php echo $data[
                                            "conversions"
                                        ]; ?></td>
                                        <td>$<?php echo number_format(
                                            $data["revenue"],
                                            2,
                                        ); ?></td>
                                        <td>
                                            <?php
                                            $visitors =
                                                $trafficData[$funnel][$step] ??
                                                1;
                                            $rate =
                                                ($data["conversions"] /
                                                    $visitors) *
                                                100;
                                            echo number_format($rate, 2) . "%";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-1">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Conversions by Funnel</h5>
                    <canvas id="conversionsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Traffic by Page Type</h5>
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-1">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Leads by Source</h5>
                    <canvas id="leadsChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Top UTM Sources</h5>
                    <canvas id="utmChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Conversions by Funnel Chart
    const conversionsCtx = document.getElementById('conversionsChart').getContext('2d');
    const conversionsChart = new Chart(conversionsCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($conversionsData)); ?>,
            datasets: [
                /*{
                    label: 'Lander',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($conversionsData, "lander"),
                            "conversions",
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)'
                },
                {
                    label: 'Checkout',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($conversionsData, "checkout"),
                            "conversions",
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)'
                    },*/
                {
                    label: 'Upsell1',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($conversionsData, "upsell1"),
                            "conversions",
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.5)'
                },
                {
                    label: 'Thankyou',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($conversionsData, "thankyou"),
                            "conversions",
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)'
                }
            ]
        }
    });

    // Traffic by Page Type Chart
    const trafficCtx = document.getElementById('trafficChart').getContext('2d');
    const trafficChart = new Chart(trafficCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_keys($trafficData)); ?>,
            datasets: [
                {
                    label: 'Lander',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($trafficData, "lander"),
                            null,
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)'
                },
                {
                    label: 'Checkout',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($trafficData, "checkout"),
                            null,
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.5)'
                },
                {
                    label: 'Upsell 1',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($trafficData, "upsell1"),
                            null,
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.5)'
                },
                {
                    label: 'Upsell 2',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($trafficData, "upsell2"),
                            null,
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.5)'
                },
                {
                    label: 'Upsell 3',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($trafficData, "upsell3"),
                            null,
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.5)'
                },
                {
                    label: 'Thankyou',
                    data: <?php echo json_encode(
                        array_column(
                            array_column($trafficData, "thankyou"),
                            null,
                        ),
                    ); ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.5)'
                }
            ]
        }
    });

    // Leads by Source Chart
    const leadsCtx = document.getElementById('leadsChart').getContext('2d');
    const leadsChart = new Chart(leadsCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(
                array_keys($leadsData[array_key_first($leadsData)] ?? []),
            ); ?>,
            datasets: [{
                data: <?php echo json_encode(
                    array_values($leadsData[array_key_first($leadsData)] ?? []),
                ); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)'
                ]
            }]
        }
    });

    // UTM Sources Chart
    const utmCtx = document.getElementById('utmChart').getContext('2d');
    const utmChart = new Chart(utmCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_keys($utmData)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_values($utmData)); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)'
                ]
            }]
        }
    });
</script>

<?php require "includes/footer.php"; ?>
