<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Handle Remove Single Item
if (isset($_POST['remove_item'])) {
    try {
        $cart_id = $_POST['cart_id'];
        $delete_query = "DELETE FROM user_cart WHERE id = :cart_id AND user_id = :user_id";
        $stmt = $pdo->prepare($delete_query);
        $stmt->execute([
            'cart_id' => $cart_id,
            'user_id' => $_SESSION['user_id']
        ]);

        $_SESSION['cart_message'] = "Item removed from cart successfully!";
    } catch (Exception $e) {
        $_SESSION['cart_error'] = $e->getMessage();
    }
    header("Location: cart.php");
    exit();
}

// Handle Clear Entire Cart
if (isset($_POST['clear_cart'])) {
    try {
        $delete_query = "DELETE FROM user_cart WHERE user_id = :user_id";
        $stmt = $pdo->prepare($delete_query);
        $stmt->execute(['user_id' => $_SESSION['user_id']]);

        $_SESSION['cart_message'] = "Cart cleared successfully!";
    } catch (Exception $e) {
        $_SESSION['cart_error'] = $e->getMessage();
    }
    header("Location: cart.php");
    exit();
}

// Handle Add to Cart / Update Cart
if (isset($_POST['add_to_cart']) || isset($_POST['update_cart'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'] ?? 1;
        $selected_size = $_POST['selected_size'];

        // Check if product exists and size is valid
        $product_query = "SELECT size, price_ksh FROM products WHERE product_id = :product_id";
        $product_stmt = $pdo->prepare($product_query);
        $product_stmt->execute(['product_id' => $product_id]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Product not found");
        }

        // Validate size
        $sizes = json_decode($product['size'], true);
        $size_exists = false;
        foreach ($sizes as $size) {
            if ($size['size'] == $selected_size) {
                $size_exists = true;
                break;
            }
        }

        if (!$size_exists) {
            throw new Exception("Selected size is not available");
        }

        // Check if item already exists in cart
        $check_query = "SELECT id FROM user_cart 
                        WHERE user_id = :user_id 
                        AND product_id = :product_id 
                        AND selected_size = :selected_size";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([
            'user_id' => $user_id,
            'product_id' => $product_id,
            'selected_size' => $selected_size
        ]);

        if ($check_stmt->rowCount() > 0) {
            // Update existing cart item
            $update_query = "UPDATE user_cart 
                             SET quantity = :quantity 
                             WHERE user_id = :user_id 
                             AND product_id = :product_id 
                             AND selected_size = :selected_size";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                'quantity' => $quantity,
                'user_id' => $user_id,
                'product_id' => $product_id,
                'selected_size' => $selected_size
            ]);
        } else {
            // Insert new cart item
            $insert_query = "INSERT INTO user_cart 
                             (user_id, product_id, quantity, selected_size) 
                             VALUES (:user_id, :product_id, :quantity, :selected_size)";
            $insert_stmt = $pdo->prepare($insert_query);
            $insert_stmt->execute([
                'user_id' => $user_id,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'selected_size' => $selected_size
            ]);
        }

        $_SESSION['cart_message'] = "Cart updated successfully!";
    } catch (Exception $e) {
        $_SESSION['cart_error'] = $e->getMessage();
    }
    header("Location: cart.php");
    exit();
}

// Fetch Cart Items
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

// Calculate Total Price
$total_query = "
    SELECT SUM(uc.quantity * p.price_ksh) AS total_price
    FROM user_cart uc
    JOIN products p ON uc.product_id = p.product_id
    WHERE uc.user_id = :user_id
";
$total_stmt = $pdo->prepare($total_query);
$total_stmt->execute(['user_id' => $_SESSION['user_id']]);
$total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
$total_price = $total_result['total_price'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f4f4;
            padding: 20px;
        }

        .cart-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .success-message {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .error-message {
            background-color: #f44336;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            padding: 10px;
            border -radius: 4px;
            background-color: #fafafa;
        }

        .item-details {
            flex-grow: 1;
            margin-left: 15px;
        }

        .item-details h3 {
            margin-bottom: 10px;
        }

        .cart-summary {
            margin-top: 20px;
            text-align: right;
        }

        .cart-summary h2 {
            margin-bottom: 10px;
        }

        button {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        input[type="number"] {
            width: 60px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="cart-container">
        <?php 
        // Display any session messages
        if (isset($_SESSION['cart_message'])) {
            echo "<div class='success-message'>" . $_SESSION['cart_message'] . "</div>";
            unset($_SESSION['cart_message']);
        }
        if (isset($_SESSION['cart_error'])) {
            echo "<div class='error-message'>" . $_SESSION['cart_error'] . "</div>";
            unset($_SESSION['cart_error']);
        }
        ?>

        <?php if (empty($cart_items)): ?>
            <p>Your cart is empty</p>
        <?php else: ?>
            <div class="cart-items">
                <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 100px; height: auto;">
                        
                        <div class="item-details">
                            <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                            
                            <!-- Update Cart Form -->
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                <input type="hidden" name="selected_size" value="<?php echo htmlspecialchars($item['selected_size']); ?>">
                                <label for="quantity">Quantity:</label>
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1">
                                <button type="submit" name="update_cart">Update</button>
                                <button type="submit" name="remove_item">Remove</button>
                            </form>
                            <p>Price: Ksh <?php echo htmlspecialchars($item['price_ksh']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <h2>Total Price: Ksh <?php echo $total_price; ?></h2>
                <form method="POST" action="cart.php">
                    <button type="submit" name="clear_cart">Clear Cart</button>
                </form>
                <form method="POST" action="checkout.php">
                    <button type="submit">Proceed to Checkout</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>