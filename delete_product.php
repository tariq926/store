<?php
session_start();
include 'db_config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['is_admin']) {
    header("Location: index.php"); // Redirect if not admin
    exit();
}

// Handle product deletion
if (isset($_GET['id'])) {
    $productId = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId);

    if ($stmt->execute()) {
        header("Location: admin.php"); // Redirect to admin panel after deletion
        exit();
    } else {
        $delete_error = "Failed to delete product. Please try again.";
    }
} else {
    header("Location: admin.php"); // Redirect if no ID provided
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Product</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to your CSS file -->
</head>

<body>
    <h1>Delete Product</h1>

    <?php if (isset($delete_error)): ?>
    <div style="color: red;"><?php echo $delete_error; ?></div>
    <?php else: ?>
    <p>Product has been successfully deleted.</p>
    <?php endif; ?>

    <footer>
        <p>&copy; 2023 Fashion Store. All rights reserved.</p>
    </footer>
</body>

</html>