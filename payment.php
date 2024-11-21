<?php
session_start();
require 'db_config.php'; // Include the database connection script

// M-Pesa API credentials
$consumer_key = 'MTHTek6hFZ4KWagn8797nmzxTPFQ61oMGEmLmF84mNPAIMzR';
$consumer_secret = 'ysFUhAaDwxSvWitKKG8vxnVHGbKHYuJGs9rm2ndG4nA58S8URFZfg0uNWdsZg2RX';
$BusinessShortcode = '174379';
$lipa_na_mpesa_online_url = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'; // Use the live URL in production

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Get the user's cart total amount
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT SUM(p.price_ksh * uc.quantity) AS total_amount
                    FROM user_cart uc
                    JOIN products p ON uc.product_id = p.product_id
                    WHERE uc.user_id = ?");
$stmt->execute([$user_id]);
$cart_total = $stmt->fetchColumn();

if ($cart_total <= 0) {
    echo "Your cart is empty. Please add items to your cart before proceeding to payment.";
    exit();
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $cart_total; // Total amount from the cart
    $phone_number = $_POST['phone_number']; // User's phone number

    // Generate the token for M-Pesa API
    $token_url = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $consumer_key . ':' . $consumer_secret);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $json_response = json_decode($response);
    $access_token = $json_response->access_token;

    // Prepare the payment request
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ];

    $payload = [
        'BusinessShortCode' => $BusinessShortcode,
        'Password' => base64_encode($BusinessShortcode . $lipa_na_mpesa_online_url . time()), // Correct Password generation
        'Timestamp' => date('YmdHis'),
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone_number, // User's phone number
        'PartyB' => $BusinessShortcode,
        'PhoneNumber' => $phone_number,
        'CallBackURL' => 'https://yourwebsite.com/callback.php', // Your callback URL
        'AccountReference' => 'Order123', // Your account reference
        'TransactionDesc' => 'Payment for Order'
    ];

    // Make the payment request
    $payment_url = $lipa_na_mpesa_online_url;
    $ch = curl_init($payment_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $payment_response = curl_exec($ch);
    curl_close($ch);

    $payment_response_data = json_decode($payment_response);

    // Check the response from M-Pesa
    if (isset($payment_response_data->ResponseCode) && $payment_response_data->ResponseCode == '0') {
        // Payment was successful
        // Update order status in the database
        // You should also save the transaction details for future reference
        echo "Payment successful! Transaction ID: " . $payment_response_data->CheckoutRequestID;
    } else {
        // Payment failed
        echo "Payment failed: " . $payment_response_data->ResponseDescription;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Payment</title>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }

    h1 {
        text-align: center;
        color: #333;
    }

    p {
        text-align: center;
        font-size: 1.2em;
        color: #555;
    }

    form {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        max-width: 400px;
        width: 100%;
        margin: 0 auto;
    }

    input[type="text"] {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 5px;
    }

    button {
        width: 100%;
        padding: 10px;
        border: none;
        border-radius: 5px;
        background-color: #007BFF;
        color: white;
        font-size: 1em;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    button:hover {
        background-color: #0056b3;
    }
    </style>
</head>

<body>
    <h1>Make Payment</h1>
    <p>Total Amount: Ksh <?php echo number_format($cart_total, 2); ?></p>
    <form method="POST" action="">
        <input type="text" name="phone_number" placeholder="Enter your phone number" required>
        <button type="submit">Pay with M-Pesa</button>
    </form>
</body>

</html>