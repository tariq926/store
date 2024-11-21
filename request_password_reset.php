<?php
session_start();
require 'db_config.php'; // Include the database connection script
require 'vendor/autoload.php'; // Include PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Rate limiting variables
$rate_limit = 5; // Maximum requests allowed in the time frame
$time_frame = 3600; // Time frame in seconds (1 hour)

// Check if the user is logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Check rate limit
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_log WHERE ip_address = ? AND timestamp > NOW() - INTERVAL 1 HOUR");
    $stmt->execute([$ip_address]);
    $request_count = $stmt->fetchColumn();

    if ($request_count >= $rate_limit) {
        echo "You have exceeded the maximum number of password reset requests. Please try again later.";
        exit();
    }

    // Check if the email exists in the users table
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate a unique token
        $token = bin2hex(random_bytes(50));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store the token in the database
        $stmt = $pdo->prepare("INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user['user_id'], $token, $expires_at]);

        // Send email with reset link using PHPMailer
        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Set the SMTP server to send through
            $mail->SMTPAuth = true;
            $mail->Username = 'ochiengphidel1@gmail.com'; // SMTP username
            $mail->Password = 'tariqghost926'; // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
            $mail->Port = 587; // TCP port to connect to

            // Recipients
            $mail->setFrom('your-email@example.com', 'Your Name');
            $mail->addAddress($email);

            // Content
            $reset_link = "http://yourwebsite.com/reset_password.php?token=$token";
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "To reset your password, click the following link: <a href='$reset_link'>$reset_link</a>";

            $mail->send();

            // Log the password reset request
            $stmt = $pdo->prepare("INSERT INTO password_reset_log (user_id, action) VALUES (?, ?)");
            $stmt->execute([$user['user_id'], 'Requested password reset']);

            echo "Password reset link has been sent to your email.";
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        echo "No user found with that email address.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Request Password Reset</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }

    h1 {
        color: #333;
    }

    form {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        width: 300px;
        text-align: center;
    }

    input[type="email"] {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    button {
        background-color: #4CAF50;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    button:hover {
        background-color: #45a049;
    }
    </style>
</head>

<body>
    <h1>Request Password Reset</h1>
    <form method="POST" action="">
        <input type="email" name="email" placeholder="Enter your email" required>
        <button type="submit">Request Reset</button>
    </form>
</body>

</html>