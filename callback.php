<?php
require 'db_config.php'; // Include the database connection script

// Get the JSON data from M-Pesa
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log the callback data for debugging
file_put_contents('mpesa_callback.log', print_r($data, true), FILE_APPEND);

// Check if the callback contains the necessary data
if (isset($data['Body']['stkCallback'])) {
    $callback = $data['Body']['stkCallback'];
    $transaction_id = $callback['CheckoutRequestID'];
    $result_code = $callback['ResultCode'];
    $result_desc = $callback['ResultDesc'];

    // Log the result code and description
    file_put_contents('mpesa_callback.log', "Transaction ID: $transaction_id, Result Code: $result_code, Result Description: $result_desc\n", FILE_APPEND);

    // Check for successful payment
    if ($result_code == 0) {
        // Payment was successful
        $amount = $callback['CallbackMetadata']['Item'][0]['Value'];
        $phone_number = $callback['CallbackMetadata']['Item'][1]['Value'];

        // Update the order status in the database
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Paid', amount = ?, phone_number = ? WHERE transaction_id = ?");
        $stmt->execute([$amount, $phone_number, $transaction_id]);

        // Optionally, you can send a confirmation email or notification to the user here
    } else {
        // Payment failed
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Failed' WHERE transaction_id = ?");
        $stmt->execute([$transaction_id]);
    }
} else {
    // Log an error if the callback structure is not as expected
    file_put_contents('mpesa_callback.log', "Invalid callback structure: " . print_r($data, true) . "\n", FILE_APPEND);
}
?>