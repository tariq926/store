<?php
session_start(); 

// Include database configuration
require 'db_config.php'; 

// Enhanced Security Measures
// Set secure session parameters
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page instead of dying
    exit();
}

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Get user details from session
$userId = $_SESSION['user_id'];

// Validate User Cart
function validateUserCart($userId, $pdo) {
    try {
        // Check if cart is not empty
        $cartQuery = "SELECT COUNT(*) as cart_count FROM user_cart WHERE user_id = :user_id";
        $stmt = $pdo->prepare($cartQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $cartResult = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cartResult['cart_count'] == 0) {
            return false; // Cart is empty
        }

        // Additional validation: Check product availability
        $availabilityQuery = "
            SELECT p.product_id, p.product_name, p.stock_quantity, uc.quantity
            FROM user_cart uc
            JOIN products p ON uc.product_id = p.product_id
            WHERE uc.user_id = :user_id AND p.stock_quantity < uc.quantity
        ";
        $stmt = $pdo->prepare($availabilityQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $unavailableItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return empty($unavailableItems);
    } catch (PDOException $e) {
        // Log the error
        error_log("Cart Validation Error: " . $e->getMessage());
        return false;
    }
}

// Function to calculate the total amount based on the user's cart
function calculateTotalAmount($userId, $pdo) {
    try {
        $query = "
            SELECT SUM(p.price_ksh * uc.quantity) AS total
            FROM user_cart uc
            JOIN products p ON uc.product_id = p.product_id
            WHERE uc.user_id = :user_id
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'] ?? 0;
    } catch (PDOException $e) {
        // Log the error
        error_log("Total Amount Calculation Error: " . $e->getMessage());
        return 0;
    }
}

// Validate cart before proceeding
if (!validateUserCart($userId, $pdo)) {
    // Redirect with error message if cart is invalid
    header('Location: cart.php?error=invalid_cart');
    exit();
}

// Calculate the total amount based on the user's cart
$totalAmount = calculateTotalAmount($userId, $pdo);

// Initialize variables
$paymentMessage = "";
$errors = [];

// CSRF Token Generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process the checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token Validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid security token. Please try again.";
    }

    // Sanitize and validate input data
    $pickingAddress = filter_input(INPUT_POST, 'picking_address', FILTER_SANITIZE_STRING);
    $phoneNumber = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
    $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);

    // Validation checks
    if (empty($pickingAddress)) {
        $errors[] = "Picking address is required.";
    }

    if (empty($phoneNumber) || !preg_match('/^(?:254|\+254|0)?(7(?:(?:[129][0-9])|(?:0[0-8])|(4[0-1]))[0-9]{6})$/', $phoneNumber)) {
        $errors[] = "Invalid phone number. Please enter a valid Kenyan phone number.";
    }

    if (empty($paymentMethod)) {
        $errors[] = "Please select a payment method.";
    }

    // If no errors, proceed with payment
    if (empty($errors)) {
        // Normalize phone number
        $phoneNumber = preg_replace('/^(?:0|\+?254)/', '254', $phoneNumber);

        // Payment method processing
        switch ($paymentMethod) {
            case 'mpesa':
                try {
                    // M-Pesa Payment Integration (Enhanced Security)
                    $mpesaCredentials = [
                        'consumerKey' => getenv('MPESA_CONSUMER_KEY') ?: 'your_consumer_key',
                        'consumerSecret' => getenv('MPESA_CONSUMER_SECRET') ?: 'your_consumer_secret',
                        'businessShortcode' => getenv('MPESA_BUSINESS_SHORTCODE') ?: '174379',
                        'passkey' => getenv('MPESA_PASSKEY') ?: 'your_passkey'
                    ];

                    // Payment processing logic (similar to previous implementation)
                    // ... (M-Pesa integration code)

                    // If payment successful, create order
                    $orderQuery = "
                        INSERT INTO orders (
                            user_id,
                            total_amount,
                            payment_method,
                            picking_address,
                            phone_number,
                            status
                        ) VALUES (
                            :user_id, 
                            :total_amount, 
                            :payment_method, 
                            :picking_address, 
                            :phone_number, 
                            'Pending'
                        )
                    ";

                    $stmt = $pdo->prepare($orderQuery);
                    $stmt->execute([
                        ':user_id' => $userId,
                        ':total_amount' => $totalAmount,
                        ':payment_method' => $paymentMethod,
                        ':picking_address' => $pickingAddress,
                        ':phone_number' => $phoneNumber
                    ]);

                    // Clear user's cart after successful order
                    $clearCartQuery = "DELETE FROM user_cart WHERE user_id = :user_id";
                    $pdo->prepare($clearCartQuery)->execute([':user_id' => $userId]);

                    // Redirect to order confirmation
                    header('Location: order_confirmation.php');
                    exit();

                } catch (Exception $e) {
                    $errors[] = "Payment processing failed: " . $e->getMessage();
                }
                break;

            // Add other payment methods as needed
            default:
                $errors[] = "Selected payment method is not supported.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <!-- Your existing CSS styles -->
</head>

<body>
    <div class="container">
        <h1>Checkout</h1>

        <!-- Error Display -->
        <?php if (!empty($errors)): ?>
        <div class="error-container">
            <?php foreach ($errors as $error): ?>
            <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <label for="picking_address">Picking Address:</label>
            <input type="text" id="picking_address" name="picking_address" required>

            <label for="phone_number">Phone Number:</label>
            <input type="text" id="phone_number" name="phone_number" required>

            <label for="payment_method">Payment Method:</label>
            <select id="payment_method" name="payment_method" required>
                <option value="">Select Payment Method</option>
                <option value="credit_card">Credit Card</option>
                <option value="paypal">PayPal</option>
                <option value="mpesa">M-Pesa</option>
            </select>

            <button type="submit">Proceed to Payment</button>
        </form>
    </div>
</body>

</html>