<?php
// Start session
session_start();

// Include database connection
require 'db_config.php'; // Ensure you have a file for database connection

// Check if the user is an admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php"); // Redirect if not an admin
    exit();
}

// Handle date range filter
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');

try {
    // Fetch total sales within date range
    $totalSalesQuery = "SELECT SUM(oi.quantity) AS total_quantity, SUM(oi.quantity * p.price_ksh) AS total_sales 
                        FROM orders AS o 
                        JOIN order_items AS oi ON o.order_id = oi.order_id 
                        JOIN products AS p ON oi.product_id = p.product_id 
                        WHERE o.order_date BETWEEN :start_date AND :end_date";

    $stmt = $pdo->prepare($totalSalesQuery);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $totalSalesData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalSales = $totalSalesData['total_sales'] ?? 0;
    $totalQuantity = $totalSalesData['total_quantity'] ?? 0;

    // Fetch total users
    $totalUsersQuery = "SELECT COUNT(*) AS total_users FROM users";
    $totalUsersResult = $pdo->query($totalUsersQuery);
    $totalUsers = $totalUsersResult->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Fetch popular products within date range
    $popularProductsQuery = "SELECT oi.product_id, SUM(oi.quantity) AS purchase_count 
                             FROM orders AS o 
                             JOIN order_items AS oi ON o.order_id = oi.order_id 
                             WHERE o.order_date BETWEEN :start_date AND :end_date 
                             GROUP BY oi.product_id 
                             ORDER BY purchase_count DESC LIMIT 5";
    $stmt = $pdo->prepare($popularProductsQuery);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    $popularProductsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for sales trend chart
    $salesTrendQuery = "SELECT DATE(o.order_date) AS order_date, SUM(oi.quantity * p.price_ksh) AS daily_sales 
                        FROM orders AS o 
                        JOIN order_items AS oi ON o.order_id = oi.order_id 
                        JOIN products AS p ON oi.product_id = p.product_id 
                        WHERE o.order_date BETWEEN :start_date AND :end_date 
                        GROUP BY order_date 
                        ORDER BY order_date";
    $stmt = $pdo->prepare($salesTrendQuery);
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);

    $labels = [];
    $salesData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $labels[] = $row['order_date'];
        $salesData[] = $row['daily_sales'];
    }
} catch (PDOException $e) {
    // Handle the error, e.g., log it or display a user-friendly message
    echo "Error: " . htmlspecialchars($e->getMessage());
    exit();
}

// Include header
// Include your site's header
?>

<div class="container">
    <h1>Analytics Dashboard</h1>
    
    <form method="POST" action="">
        <label for="start_date">Start Date:</label>
        <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" required>
        
        <label for="end_date">End Date:</label>
        <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" required>
        
        <button type="submit">Filter</button>
    </form>

    <div class="analytics-summary">
        <h2>Summary</h2>
        <p>Total Sales: ksh. <?php echo number_format($totalSales, 2); ?></p>
        <p>Total Quantity Sold: <?php echo $totalQuantity; ?></p>
        <p>Total Users: <?php echo $totalUsers; ?></p>
    </div>

    <div class="popular-products">
        <h2>Most Popular Products</h2>
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Purchase Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($popularProductsResult as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['product_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['purchase_count']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="charts">
        <h2>Sales Trend</h2>
        <canvas id="salesTrendChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    const salesTrendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Sales Trend',
                data: <?php echo json_encode($salesData); ?>,
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                fill: false
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>