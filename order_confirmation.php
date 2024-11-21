<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_config.php'; // Include your database connection file

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch order ID from the session (you might set this during the checkout process)
$order_id = isset($_SESSION['order_id']) ? $_SESSION['order_id'] : null;

if (!$order_id) {
    echo "<p>No order found. Please try again.</p>";
    exit();
}

// Fetch order details from the database
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<p>Order not found. Please check your order ID.</p>";
    exit();
}

// Fetch order items
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.price_in_ksh FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total price
$totalPrice = 0;
foreach ($order_items as $item) {
    $totalPrice += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h2 {
            color: #007bff;
        }
        .order-summary {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        .button {
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="order-summary">
        <h2>Order Confirmation</h2>
        <p>Thank you for your order, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</p>
        <p>Your order ID is: <strong><?php echo htmlspecialchars($order['order_id']); ?></strong></p>
        <p>Order Date: <strong><?php echo htmlspecialchars($order['order_date']); ?></strong></p>
        <p>Status: <strong><?php echo htmlspecialchars($order['status']); ?></strong></p>

        <h3>Order Details:</h3>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Price (Ksh)</th>
                    <th>Quantity</th>
                    <th>Subtotal (Ksh)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td><?php echo htmlspecialchars($item['price']); ?></td>
                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($item['price'] * $item['quantity']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Total Price: Ksh <?php echo htmlspecialchars($totalPrice); ?></h3>
        <a href="index.php" class="button">Continue Shopping</a>
    </div>
</body>

</html>