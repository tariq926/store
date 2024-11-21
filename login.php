<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require 'db_config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $user = trim($_POST['username']); // Trim to remove extra spaces
    $pass = $_POST['password'];

    // Prepare and execute the query to prevent SQL injection
    $stmt = $pdo->prepare("SELECT user_id, username, password, role FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Verify the password
        if (password_verify($pass, $row['password'])) {
            // Successful login
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role']; // Store the role

            // Redirect based on user role
            if ($row['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: index.php"); // Redirect to user dashboard
            }
            exit(); // Always exit after a redirect
        } else {
            $_SESSION['message'] = "Invalid password.";
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['message'] = "User not found. Enter valid user credentials. THANK YOU!";
        header("Location: login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
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

    .container {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(15px);
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        width: 300px;
        text-align: center;
    }

    h2 {
        margin-bottom: 20px;
        cursor: default;
    }

    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input {
        width: 90%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    button {
        background-color: teal;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
    }

    button:hover {
        background-color: darkcyan;
    }

    p {
        margin-top: 20px;
    }

    a {
        color: teal;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
    </style>
</head>

<body
    style="background-image: url('https://www.royalcitytours.com/wp-content/uploads/2020/07/new-york-4725115-scaled.jpg'); background-size: 100% 100%;">
    <div class="container">
        <h2>Login</ h2>
            <form method="post" action="login.php">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" name="login">Login</button>
            </form>
            <p>Not a member? <a href="register.php">Register</a></p>
            <div class="out-put message">
                <?php
            if (isset($_SESSION['message'])) {
                echo htmlspecialchars($_SESSION['message']);
                unset($_SESSION['message']);
            }
            ?>
            </div>
    </div>
</body>

</html>