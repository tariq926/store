<?php
require 'db_config.php'; // Include your database connection file

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if the token exists in the database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        // Update the user's verification status
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        if ($stmt->execute([$user['id']])) {
            echo "Email verified successfully! You can now log in.";
        } else {
            echo "Email verification failed!";
        }
    } else {
        echo "Invalid verification token!";
    }
} else {
    echo "No token provided!";
}
?>