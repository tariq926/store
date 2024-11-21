<?php
session_start();
require_once 'db_config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Function to validate cart items
function validateCartItems($cart_items, $pdo) {
    $errors = [];

    foreach ($cart_items as $item) {
        // Check product existence and availability
        $product_query = "SELECT quantity_in_stock, size FROM products WHERE product_id = :product_id";
        $stmt = $pdo->prepare($product_query);
        $stmt->execute(['product_id' => $item['product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errors[] = "Product {$item['product_id']} not found";
            continue;
        }

        // Validate size availability
        $sizes = json_decode($product['size'], true);
        $size_available = false;
        foreach ($sizes as $size_info) {
            if ($size_info['size'] == $item['selected_size']) {
                if ($size_info['quantity'] < $item['quantity']) {
                    $errors[] = "Insufficient stock for {$item['product_name']} in size {$item['selected_size']}";
                }
                $size_available = true;
                break;
            }
        }

        if (!$size_available) {
            $errors[] = "Size {$item['selected_size']} not available for {$item['product_name']}";
        }
    }

    return $errors;
}

try {
    // Database connection
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch Cart Items for the Current User
    $cart_query = "
        SELECT
            uc.id AS cart_id,
            uc.quantity,
            uc.selected_size,
            p.product_id,
            p.product_name,
            p.price_ksh,
            p.image_url
        FROM
            user_cart uc
        JOIN
            products p ON uc.product_id = p.product_id
        WHERE
            uc.user_id = :user_id
    ";
    $cart_stmt = $pdo->prepare($cart_query);
    $cart_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Total Cart Value
    if (empty($cart_items)) {
        $_SESSION['error'] = "Your cart is empty. Please add items before checkout.";
        header("Location: cart.php");
        exit();
    }

    $total_cart_value = array_reduce($cart_items, function($carry, $item) {
        return $carry + ($item['price_ksh'] * $item['quantity']);
    }, 0);

    // Fetch User Details
    $user_query = "SELECT * FROM users WHERE user_id = :user_id";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // Generate CSRF Token if not already generated
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

} catch (PDOException $e) {
    error_log("Checkout Error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred. Please try again.";
    header("Location: cart.php");
    exit();
}

// M-Pesa Configuration
define('MPESA_CONSUMER_KEY', 'MTHTek6hFZ4KWagn8797nmzxTPFQ61oMGEmLmF84mNPAIMzR'); // Replace with your actual consumer key
define('MPESA_CONSUMER_SECRET', 'ysFUhAaDwxSvWitKKG8vxnVHGbKHYuJGs9rm2ndG4nA58S8URFZfg0uNWdsZg2RX'); // Replace with your actual consumer secret
define('MPESA_SHORTCODE', '3060922'); // Replace with your actual shortcode
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); // Replace with your actual passkey
define('MPESA_ENVIRONMENT', 'sandbox'); // or 'production'

// Function to generate M-Pesa access token
function generateMpesaAccessToken() {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET)
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return null;
    }
    
    $tokenData = json_decode($response, true);
    return $tokenData['access_token'] ?? null;
}

// Function to initiate STK Push
function initiateSTKPush($phoneNumber, $amount) {
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
    $accessToken = generateMpesaAccessToken();
    
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Failed to generate access token'];
    }
    
    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerBuyGoodsOnline', // Changed to Buy Goods
        'Amount' => round($amount),
        'PartyA' => $phoneNumber,
        'PartyB' => MPESA_SHORTCODE,
        'PhoneNumber' => $phoneNumber,
        'CallBackURL' => 'https://yourdomain.com/mpesa_callback.php',
        'AccountReference' => 'Order Payment',
        'TransactionDesc' => 'Payment for Order'
    ];
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return ['success' => false, 'message' => 'Curl Error: ' . $err];
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['ResponseCode']) && $responseData['ResponseCode'] == '0') {
        return [
            'success' => true, 
            'message' => 'STK Push Initiated',
            'checkout_request_id' => $responseData['CheckoutRequestID']
        ];
    }
    
    return ['success' => false, 'message' => $responseData['errorMessage'] ?? 'Unknown error'];
}

// Example usage
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneNumber = $_POST['phone_number']; // User's phone number
    $paymentMethod = $_POST['payment_method']; // User selected payment method

    // Process payment based on chosen method
    if ($paymentMethod === 'mpesa') {
        // Validate phone number format before proceeding with payment
        $phoneNumber = preg_replace('/\s+/', '', $phoneNumber); // Remove spaces
        if (!preg_match('/^(?:254|\+254|0)?7\d{8}$/', $phoneNumber)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid Kenyan phone number.']);
            exit;
        }

        // Initiate STK Push
        $stkResponse = initiateSTKPush($phoneNumber, $total_cart_value);
        if ($stkResponse['success']) {
            // Inform the user to complete the payment process
            echo json_encode(['success' => true, 'message' => 'Payment initiated successfully. Follow the instructions on your phone.']);
            exit;
        } else {
            // Handle failure
            echo json_encode(['success' => false, 'message' => $stkResponse['message']]);
            exit;
        }
    } elseif ($paymentMethod === 'cash_on_delivery') {
        // Logic to place order directly with cash on delivery
        // Implement order processing and store details in database...

        echo json_encode(['success' => true, 'message' => 'Order placed successfully with Cash on Delivery.']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid payment method selected.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Arial', sans-serif;
        background-color: #f4f6f9;
        line-height: 1.6;
        color: #333;
    }

    .checkout-container {
        max-width: 800px;
        margin: 30px auto;
        background-color: #ffffff;
        padding: 40px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    h1 {
        text-align: center;
        color: #2c3e50;
        margin-bottom: 30px;
        font-size: 2.5em;
        border-bottom: 3px solid #3498db;
        padding-bottom: 10px;
    }

    /* Cart Items Container */
    .cart-items-container {
        margin-bottom: 30px;
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
    }

    .cart-items-container h2 {
        color: #2c3e50;
        margin-bottom: 20px;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }

    .cart-item {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
        padding: 15px;
        background-color: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease;
    }

    .cart-item:hover {
        transform: translateX(5px);
    }

    .cart-item img {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        margin-right: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .cart-item-details {
        flex-grow: 1;
    }

    .cart-item-details h3 {
        color: #2c3e50;
        margin-bottom: 10px;
    }

    .cart-item-details p {
        color: #7f8c8d;
        margin-bottom: 5px;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #2c3e50;
        font-weight: bold;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 16px;
        transition: border-color 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
    }

    .btn-submit {
        width: 100%;
        padding: 15px;
        background-color: #3498db;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 18px;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .btn-submit:hover {
        background-color: #2980b9;
        transform: translateY(-2px);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    /* Responsive Design */
    @media (max-width: 600px) {
        .checkout-container {
            width: 95%;
            padding: 20px;
        }

        .cart-item {
            flex-direction: column;
            text-align: center;
        }

        .cart-item img {
            margin-right: 0;
            margin-bottom: 15px;
        }
    }

    /* Error and Success Messages */
    .error-message {
        background-color: #e74c3c;
        color: white;
        padding: 10px;
        border-radius: 6px;
        text-align: center;
        margin-bottom: 20px;
    }

    .success-message {
        background-color: #2ecc71;
        color: white;
        padding: 10px;
        border-radius: 6px;
        text-align: center;
        margin-bottom: 20px;
    }

    .cart-items-container {
        margin-bottom: 20px;
    }

    .cart-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        padding: 10px;
        border: 1px solid #ddd;
    }

    .cart-item img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        margin-right: 15px;
    }

    .cart-item-details {
        flex-grow: 1;
    }
    </style>
</head>

<body>
    <div class="checkout-container">
        <h1>Checkout</h1>

        <!-- Cart Items Summary -->
        <div class="cart-items-container">
            <h2>Order Items</h2>
            <?php foreach ($cart_items as $item): ?>
            <div class="cart-item">
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                    alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                <div class="cart-item-details">
                    <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                    <p>Size: <?php echo htmlspecialchars($item['selected_size']); ?></p>
                    <p>Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p>
                    <p>Price: KSh <?php echo number_format($item['price_ksh'], 2); ?></p>
                    <p>Subtotal: KSh <?php echo number_format($item['price_ksh'] * $item['quantity'], 2); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Checkout Form -->
        <form id="checkoutForm" action="process_order.php" method="POST">
            <!-- Cart Items as Hidden Inputs -->
            <?php foreach ($cart_items as $index => $item): ?>
            <input type="hidden" name="cart_items[<?php echo $index; ?>][product_id]"
                value="<?php echo $item['product_id']; ?>">
            <input type="hidden" name="cart_items[<?php echo $index; ?>][quantity]"
                value="<?php echo $item['quantity']; ?>">
            <input type="hidden" name="cart_items[<?php echo $index; ?>][selected_size]"
                value="<?php echo htmlspecialchars($item['selected_size']); ?>">
            <input type="hidden" name="cart_items[<?php echo $index; ?>][price]"
                value="<?php echo $item['price_ksh']; ?>">
            <?php endforeach; ?>

            <!-- Personal Information -->
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name"
                    value="<?php echo htmlspecialchars($user_details['full_name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number"
                    value="<?php echo htmlspecialchars($user_details['phone_number'] ?? ''); ?>" required
                    placeholder="e.g., 0712345678">
            </div>

            <!-- Delivery Information -->
            <div class="form-group">
                <label for="delivery_address">Delivery Address</label>
                <input type="text" id="delivery_address" name="delivery_address" required
                    placeholder="Enter your full delivery address">
            </div>

            <!-- Payment Method -->
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" required>
                    <option value="">Select Payment Method</option>
                    <option value="mpesa">M-Pesa</option>
                    <option value="cash_on_delivery">Cash on Delivery</option>
                </select>
            </div>

            <!-- Total Amount Hidden Input -->
            <input type="hidden" name="total_amount" value="<?php echo $total_cart_value; ?>">

            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <!-- Submit Button -->
            <button type="submit" class="btn-submit">Place Order</button>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentMethodSelect = document.getElementById('payment_method');
        const phoneNumberInput = document.getElementById('phone_number');
        const checkoutForm = document.getElementById('checkoutForm');
        const totalAmount = <?php echo $total_cart_value; ?>;

        // M-Pesa Payment Handler
        function handleMpesaPayment(e) {
            e.preventDefault();

            // Validate phone number
            const phoneNumber = phoneNumberInput.value.replace(/[^\d]/g, '');
            if (!phoneNumber.match(/^(?:254|\+254|0)?7\d{8}$/)) {
                alert('Please enter a valid Kenyan phone number');
                return;
            }

            // Send AJAX request to initiate M-Pesa STK Push
            fetch('checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'initiate_mpesa',
                        phone_number: phoneNumber,
                        total_amount: totalAmount
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Store checkout request ID in hidden input
                        const checkoutRequestInput = document.createElement('input');
                        checkoutRequestInput.type = 'hidden';
                        checkoutRequestInput.name = 'mpesa_checkout_request_id';
                        checkoutRequestInput.value = data.checkout_request_id;
                        checkoutForm.appendChild(checkoutRequestInput);

                        // Allow form submission
                        checkoutForm.submit();
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('M-Pesa Initiation Error:', error);
                    alert('Failed to initiate M-Pesa payment');
                });
        }

        // Attach event listener to form submission
        checkoutForm.addEventListener('submit', function(e) {
            if (paymentMethodSelect.value === 'mpesa') {
                handleMpesaPayment(e);
            }
        });
    });
    </script>
</body>

</html>