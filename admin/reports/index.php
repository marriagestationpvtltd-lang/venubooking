<?php
$page_title = 'Reports';
require_once __DIR__ . '/../includes/header.php';
$db = getDB();

// Revenue by month
$stmt = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(grand_total) as revenue, COUNT(*) as bookings 
                    FROM bookings 
                    WHERE booking_status != 'cancelled' 
                    GROUP BY month 
                    ORDER BY month DESC 
                    LIMIT 12");
$monthly_data = $stmt->fetchAll();
?>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Monthly Revenue Report</h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-table"></i> Revenue Summary</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_data as $data): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($data['month'] . '-01')); ?></td>
                                <td><?php echo $data['bookings']; ?></td>
                                <td><?php echo formatCurrency($data['revenue']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
const ctx = document.getElementById("revenueChart").getContext("2d");
const revenueChart = new Chart(ctx, {
    type: "line",
    data: {
        labels: ' . json_encode(array_reverse(array_column($monthly_data, 'month'))) . ',
        datasets: [{
            label: "Revenue",
            data: ' . json_encode(array_reverse(array_column($monthly_data, 'revenue'))) . ',
            borderColor: "#4CAF50",
            backgroundColor: "rgba(76, 175, 80, 0.1)",
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true
            }
        }
    }
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
