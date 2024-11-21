<?php
session_start();
require 'db_config.php';

header('Content-Type: application/json');

// Validate user authentication
if (!isset($_SESSION['user_id'])) { echo json_encode(['status' => 'error', 'message' => 'User  not authenticated']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Validate order ID
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($orderId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit();
}

try {
    // Fetch order tracking information
    $trackingQuery = "
        SELECT 
            ot.tracking_id, 
            ot.status, 
            ot.location, 
            ot.timestamp, 
            ot.estimated_delivery_date 
        FROM 
            order_tracking ot 
        JOIN 
            orders o ON ot.order_id = o.order_id 
        WHERE 
            o.order_id = :order_id AND o.user_id = :user_id
    ";

    $stmt = $pdo->prepare($trackingQuery);
    $stmt->execute([':order_id' => $orderId, ':user_id' => $userId]);
    $trackingInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($trackingInfo) {
        echo json_encode(['status' => 'success', 'data' => $trackingInfo]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No tracking information found for this order']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'An error occurred while fetching tracking information']);
}
exit();