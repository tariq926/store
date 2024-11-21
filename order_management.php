<?php
session_start();
require 'db_config.php'; // Include the database connection script

// Check if the user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login if not logged in or not admin
    exit();
}

// Handle search
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Number of orders per page
$offset = ($page - 1) * $limit;

// Prepare the SQL query with search
$sql = "SELECT o.*, u.username, p.product_name AS product_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.user_id 
        JOIN products p ON o.product_id = p.product_id 
        WHERE 1=1";

$params = [];

if ($search_query) {
    $sql .= " AND (o.order_id LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

// Add pagination
$sql .= " LIMIT $limit OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Get total orders count for pagination
    $countSql = "SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.user_id WHERE 1=1";
    if ($search_query) {
        $countSql .= " AND (o.order_id LIKE ? OR u.username LIKE ?)";
        $countParams = ["%$search_query%", "%$search_query%"];
    } else {
        $countParams = [];
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $total_orders = $countStmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);
} catch (PDOException $e) {
    echo "Database query failed: " . $e->getMessage();
    exit();
}

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];

    try {
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $updateStmt->execute([$new_status, $order_id]);
        header("Location: order_management.php"); // Redirect to the same page to avoid resubmission
        exit();
    } catch (PDOException $e) {
        echo "Failed to update order status: " . $e->getMessage();
    }
}

// Delete order
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];

    try {
        $deleteStmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ?");
        $deleteStmt->execute([$delete_id]);
        header("Location: order_management.php"); // Redirect to the same page
        exit();
    } catch (PDOException $e) {
        echo "Failed to delete order: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 20px;
    }

    h1 {
        text-align: center;
        color: #333;
    }

    form {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    input[type="text"] {
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        width: 300px;
        margin-right: 10px;
    }

    button {
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        background-color: #007BFF;
        color: white;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: #0056b3;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    th,
    td {
        border: 1px solid #ccc;
        padding: 10px;
        text-align: left;
    }

    th {
        background-color: #007BFF;
        color: white;
    }

    tr:nth-child(even) {
        background-color: #f2f2f2;
    }

    tr:hover {
        background-color: #e9e9e9;
    }

    select {
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    .pagination {
        display: flex;
        justify-content: center;
        margin-top: 20px;
    }

    .pagination a {
        margin: 0 5px;
        padding: 10px 15px;
        text-decoration: none;
        color: #007BFF;
        border: 1px solid #ccc;
        border-radius: 5px;
        transition: background-color 0.3s;
    }

    .pagination a.active {
        font-weight: bold;
        background-color: #007BFF;
        color: white;
    }

    .pagination a:hover {
        background-color: #0056b3;
        color: white;
    }
    </style>
</head>

<body>
    <h1>Order Management</h1>

    <!-- Search Form -->
    <form method="GET" action="">
        <input type="text" name="search" placeholder="Search by Order ID or Username"
            value="<?php echo htmlspecialchars($search_query); ?>">
        <button type="submit">Search</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Username</th>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Order Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($orders): ?>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                <td><?php echo htmlspecialchars($order['username']); ?></td>
                <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                <td>
                    <form method="POST" action="">
                        <select name="status">
                            <option value="Pending" <?php echo ($order['status'] === 'Pending') ? 'selected' : ''; ?>>
                                Pending</option>
                            <option value="Shipped" <?php echo ($order['status'] === 'Shipped') ? 'selected' : ''; ?>>
                                Shipped</option>
                            <option value="Completed"
                                <?php echo ($order['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Canceled" <?php echo ($order['status'] === 'Canceled') ? 'selected' : ''; ?>>
                                Canceled</option>
                        </select>
                        <input type="hidden" name="order_id"
                            value="<?php echo htmlspecialchars($order['order_id']); ?>">
                        <button type="submit" name="update_status">Update</button>
                    </form>
                </td>
                <td>
                    <a href="?delete=<?php echo htmlspecialchars($order['order_id']); ?>"
                        onclick="return confirm('Are you sure you want to delete this order?');">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td colspan="7">No orders found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>"
            class="<?php echo ($i === $page) ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
</body>

</html>