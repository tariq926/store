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

// Fetch product details for editing
if (isset($_GET['id'])) {
    $productId = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: admin.php"); // Redirect if product not found
        exit();
    }
} else {
    header("Location: admin.php"); // Redirect if no ID provided
    exit();
}

// Handle product update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $productName = $_POST['product_name'];
    $price = $_POST['price'];
    $imageUrl = $_POST['image_url'];

    $stmt = $pdo->prepare("UPDATE products SET product_name = :product_name, price = :price, image_url = :image_url WHERE id = :id");
    $stmt->bindParam(':product_name', $productName);
    $stmt->bindParam(':price', $price);
    $stmt->bindParam(':image_url', $imageUrl);
    $stmt->bindParam(':id', $productId);

    if ($stmt->execute()) {
        header("Location: admin.php"); // Redirect to admin panel after update
        exit();
    } else {
        $update_error = "Failed to update product. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link rel="stylesheet" href="styles.css"> <!-- Link to your CSS file -->
</head>

<body>
    <h1>Edit Product</h1>

    <?php if (isset($update_error)): ?>
    <div style="color: red;"><?php echo $update_error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="product_name" placeholder="Product Name"
            value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
        <input type="number" name="price" placeholder="Price" value="<?php echo htmlspecialchars($product['price']); ?>"
            required>
        <input type="text" name="image_url" placeholder="Image URL"
            value="<?php echo htmlspecialchars($product['image_url']); ?>" required>
        <button type="submit">Update Product</button>
    </form>

    <footer>
        <p>&copy; 2023 Fashion Store. All rights reserved.</p>
    </footer>
</body>

</html>