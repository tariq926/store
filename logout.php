<?php
session_start();

// Unset all session variables
$_SESSION = [];

// If you want to delete the session cookie, set its expiration time to the past
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear any additional cookies related to the website (if set)
if (isset($_COOKIE['username'])) {
    setcookie('username', '', time() - 3600, '/'); // Adjust name and path as needed
}
if (isset($_COOKIE['user_id'])) {
    setcookie('user_id', '', time() - 3600, '/'); // Adjust name and path as needed
}

// Redirect to the login page
header("Location: login.php");
exit();
?>