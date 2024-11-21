<?php
session_start();
require 'db_config.php'; // Include the database connection script

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if the token is valid and not expired
    $stmt = $pdo->prepare("SELECT user_id FROM password_reset WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
        $new_password = $_POST['password'];

        // Password strength validation
        if (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            echo "Password must be at least 8 characters long and include at least one uppercase letter and one number.";
        } else {
            $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

            // Update the user's password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$new_password_hashed, $user['user_id']]);

            // Delete the token from the database
            $stmt = $pdo->prepare("DELETE FROM password_reset WHERE token = ?");
            $stmt->execute([$token]);

            // Log the password reset action
            $stmt = $pdo->prepare("INSERT INTO password_reset_log (user_id, action) VALUES (?, ?)");
            $stmt->execute([$user['user_id'], 'Reset password']);

            echo "Your password has been reset successfully.";
            exit();
        }
    }
} else {
    echo "Invalid token.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
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

    input[type="password"] {
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
    <h1>Reset Password</h1>
    <form method="POST" action="">
        <input type="password" name="password" placeholder="Enter new password" required>
        <button type="submit">Reset Password</button>
    </form>
</body>

</html>