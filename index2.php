<?php
require 'includes/protected.php';
require 'includes/db.php';
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}
?>
<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <h1 class="text-center mb-4">Stats Center</h1>

    <!-- Key Metrics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">LTV (30-Day)</h5>
                    <p class="card-text fs-3">
                        <?php
                        // Example: Fetch LTV from database
                        $ltvQuery = $conn->query("SELECT value FROM metrics WHERE name = 'ltv_30day' ORDER BY date DESC LIMIT 1");
                        $ltv = $ltvQuery->fetch_assoc()['value'] ?? 0;
                        echo '$' . number_format($ltv, 2);
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Conversion Rate</h5>
                    <p class="card-text fs-3">
                        <?php
                        $convQuery = $conn->query("SELECT value FROM metrics WHERE name = 'conversion_rate' ORDER BY date DESC LIMIT 1");
                        echo number_format($convQuery->fetch_assoc()['value'] ?? 0, 1) . '%';
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title">Leads (Weekly)</h5>
                    <p class="card-text fs-3">
                        <?php
                        $leadsQuery = $conn->query("SELECT value FROM metrics WHERE name = 'leads_weekly' ORDER BY date DESC LIMIT 1");
                        echo number_format($leadsQuery->fetch_assoc()['value'] ?? 0);
                        ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title">Churn Rate</h5>
                    <p class="card-text fs-3">
                        <?php
                        $churnQuery = $conn->query("SELECT value FROM metrics WHERE name = 'churn_rate' ORDER BY date DESC LIMIT 1");
                        echo number_format($churnQuery->fetch_assoc()['value'] ?? 0, 1) . '%';
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">LTV Trend (90 Days)</h5>
                    <canvas id="ltvChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Conversions by Channel</h5>
                    <canvas id="conversionsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Lead Quality Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Lead Quality by Source</h5>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th>Leads</th>
                                <th>Conversion Rate</th>
                                <th>Avg. LTV</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sourcesQuery = $conn->query("
                                SELECT
                                    source,
                                    COUNT(*) as leads,
                                    AVG(conversion_rate) as conv_rate,
                                    AVG(ltv) as avg_ltv
                                FROM lead_sources
                                GROUP BY source
                                ORDER BY avg_ltv DESC
                            ");
                            while ($row = $sourcesQuery->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['source']); ?></td>
                                <td><?php echo $row['leads']; ?></td>
                                <td><?php echo number_format($row['conv_rate'], 1) . '%'; ?></td>
                                <td>$<?php echo number_format($row['avg_ltv'], 2); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js for visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // LTV Trend Chart
    const ltvCtx = document.getElementById('ltvChart').getContext('2d');
    const ltvChart = new Chart(ltvCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], // Replace with dynamic data
            datasets: [{
                label: 'LTV ($)',
                data: [420, 450, 470, 460, 480, 500], // Replace with PHP data
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        }
    });

    // Conversions by Channel Chart
    const convCtx = document.getElementById('conversionsChart').getContext('2d');
    const conversionsChart = new Chart(convCtx, {
        type: 'bar',
        data: {
            labels: ['Email', 'Paid Ads', 'Organic', 'Referral'], // Replace with dynamic data
            datasets: [{
                label: 'Conversions',
                data: [120, 85, 150, 60], // Replace with PHP data
                backgroundColor: 'rgb(54, 162, 235)'
            }]
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
