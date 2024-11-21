<?php
session_start();
header('Content-Type: application/json');

// Include necessary files
require_once 'database.php';
require_once 'cart_manager.php';

// Initialize response array
$response = [
    'can_proceed' => false,
    'message' => 'Unknown error'
];

try {
    // Receive JSON payload
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (!isset($_SESSION['user_id']) || empty($input)) {
        $response['message'] = 'Invalid request';
        echo json_encode($response);
        exit();
    }

    // Initialize database and cart manager
    $database = new Database();
    $cartManager = new CartManager($database);

    // Fetch user ID
    $userId = $_SESSION['user_id'];

    // Validate cart
    $cartItems = $cartManager->getCartItems($userId);
    if (empty($cartItems)) {
        $response['message'] = 'Your cart is empty.';
        echo json_encode($response);
        exit();
    }

    // Perform stock validation
    $stockValidation = $cartManager->validateCartStock($userId);
    
    if (!empty($stockValidation['errors'])) {
        $response['errors'] = $stockValidation['errors'];
        echo json_encode($response);
        exit();
    }

    // If all validations pass
    $response['can_proceed'] = true;
    $response['message'] = 'Validation successful';
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);
?>