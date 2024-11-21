<?php
session_start();

// Include database configuration
require 'db_config.php';

// Set content type to JSON for AJAX responses
header('Content-Type: application/json');

// Logging function
function logError($message) {
    error_log($message);
}

// Response utility function
function sendResponse($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse('error', 'Invalid request method');
}

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    sendResponse('error', 'Please log in to continue');
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    sendResponse('error', 'Invalid security token');
}

// Sanitize and validate input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input));
}

try {
    // Begin database transaction
    $pdo->beginTransaction();

    // Extract order details
    $userId = $_SESSION['user_id'];
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phoneNumber = sanitizeInput($_POST['phone_number'] ?? '');
    $deliveryAddress = sanitizeInput($_POST['delivery_address'] ?? '');
    $paymentMethod = sanitizeInput($_POST['payment_method'] ?? '');
    $cartItems = $_POST['cart_items'] ?? [];

    // Validate inputs
    $errors = [];
    if (empty($fullName)) $errors[] = "Full name is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($phoneNumber)) $errors[] = "Phone number is required";
    if (empty($deliveryAddress)) $errors[] = "Delivery address is required";
    if (empty($paymentMethod)) $errors[] = "Payment method is required";
    if (empty($cartItems)) $errors[] = "Cart is empty";

    if (!empty($errors)) {
        sendResponse('error', 'Validation failed', ['errors' => $errors]);
    }

    // Validate and process cart items
    $processedOrders = [];
    $totalOrderAmount = 0;

    foreach ($cartItems as $item) {
        // Verify product availability
        $checkStockQuery = "
            SELECT product_id, product_name, quantity_in_stock, price_ksh 
            FROM products 
            WHERE product_id = :product_id AND quantity_in_stock >= :requested_quantity
        ";
        $checkStockStmt = $pdo->prepare($checkStockQuery);
        $checkStockStmt->execute([
            ':product_id' => $item['product_id'],
            ':requested_quantity' => $item['quantity']
        ]);
        $productDetails = $checkStockStmt->fetch(PDO::FETCH_ASSOC);

        // Check if product is available
        if (!$productDetails) {
            throw new Exception("Insufficient stock for product: " . ($item['product_name'] ?? 'Unknown Product'));
        }

        // Calculate item total
        $itemTotal = $item['quantity'] * $productDetails['price_ksh'];
        $totalOrderAmount += $itemTotal;

        // Insert order
        $orderQuery = "
            INSERT INTO orders (
                user_id, product_id, quantity, total_amount, 
                payment_status, status, payment_method, 
                delivery_address, phone_number, email, 
                tracking_status, estimated_delivery_date
            ) VALUES (
                :user_id, :product_id, :quantity, :total_amount, 
                'Pending', 'Processing', :payment_method, 
                :delivery_address, :phone_number, :email, 
                'Order Received', DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            )
        ";

        $orderStmt = $pdo->prepare($orderQuery);
        $orderStmt->execute([
            ':user_id' => $userId,
            ':product_id' => $item['product_id'],
            ':quantity' => $item['quantity'],
            ':total_amount' => $itemTotal,
            ':payment_method' => $paymentMethod,
            ':delivery_address' => $deliveryAddress,
            ':phone_number' => $phoneNumber,
            ':email' => $email
        ]);

        $orderId = $pdo->lastInsertId();

        // Update product inventory
        $updateInventoryQuery = "
            UPDATE products 
            SET quantity_in_stock = quantity_in_stock - :quantity 
            WHERE product_id = :product_id
        ";
        $updateInventoryStmt = $pdo->prepare($updateInventoryQuery);
        $updateInventoryStmt->execute([
            ':quantity' => $item['quantity'],
            ':product_id' => $item['product_id']
        ]);

        // Store processed order details
        $processedOrders[] = [
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'item_total' => $itemTotal
        ];
    }

    // Clear user's cart
    $clearCartQuery = "DELETE FROM user_cart WHERE user_id = :user_id";
    $pdo->prepare($clearCartQuery)->execute([':user_id' => $userId]);

    // Commit transaction
    $pdo->commit();

    // Prepare order confirmation
    $orderConfirmation = [
        'orders' => $processedOrders,
        'total_order_amount' => $totalOrderAmount,
        'payment_method' => $paymentMethod,
        'estimated_delivery' => date('Y-m-d', strtotime('+3 days'))
    ];

    // Send success response
    sendResponse('success', 'Order placed successfully', $orderConfirmation);

} catch (Exception $e) {
    // Rollback transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log and respond with error
    $errorMessage = $e->getMessage();
    error_log("Order Processing Error: " . $errorMessage);
    sendResponse('error', $errorMessage);
}

exit();