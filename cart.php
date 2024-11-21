<?php
session_start();
require_once 'db_config.php';
require_once 'cart_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['checkout_all'])) {
    // Ensure there are items in the cart before redirecting
    $check_cart_query = "SELECT COUNT(*) FROM user_cart WHERE user_id = :user_id";
    $check_stmt = $pdo->prepare($check_cart_query);
    $check_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $cart_count = $check_stmt->fetchColumn();

    if ($cart_count > 0) {
        header("Location: checkout.php");
        exit();
    } else {
        $_SESSION['cart_error'] = "Your cart is empty. Cannot proceed to checkout.";
        header("Location: cart.php");
        exit();
    }
}

// Track browsing for recommendations
if (isset($_GET['product_id'])) {
    trackUserBrowsing($pdo, $_SESSION['user_id'], $_GET['product_id']);
}

// Handle Add to Wishlist
if (isset($_POST['add_to_wishlist'])) {
    $product_id = $_POST['product_id'];
    $result = addToWishlist($pdo, $_SESSION['user_id'], $product_id);
    $_SESSION['wishlist_message'] = $result['message'];
    header("Location: cart.php");
    exit();
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
        $cart_id = $_POST['cart_id'] ?? null;

        // Check product details and stock
        $product_query = "SELECT size, price_ksh, quantity_in_stock FROM products WHERE product_id = :product_id";
        $product_stmt = $pdo->prepare($product_query);
        $product_stmt->execute(['product_id' => $product_id]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Product not found");
        }

        // Validate size and stock
        $sizes = json_decode($product['size'], true);
        $size_stock = null;
        foreach ($sizes as $size) {
            if ($size['size'] == $selected_size) {
                $size_stock = $size['quantity'] ?? 0;
                break;
            }
        }

        if ($size_stock === null) {
            throw new Exception("Selected size is not available");
        }

        if ($quantity > $size_stock) {
            throw new Exception("Insufficient stock. Only {$size_stock} items available.");
        }

        // If cart_id is provided, update the specific cart item
        if ($cart_id) {
            // Update the existing cart item with new size and/or quantity
            $update_query = "UPDATE user_cart 
                             SET selected_size = :selected_size, 
                                 quantity = :quantity, 
                                 product_id = :product_id
                             WHERE id = :cart_id 
                             AND user_id = :user_id";
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                'selected_size' => $selected_size,
                'quantity' => $quantity,
                'product_id' => $product_id,
                'cart_id' => $cart_id,
                'user_id' => $user_id
            ]);
            $_SESSION['cart_message'] = "Cart item updated successfully!";
        } else {
            // Check if an item with the same product and size already exists
            $check_query = "SELECT id, quantity FROM user_cart 
                            WHERE user_id = :user_id 
                            AND product_id = :product_id 
                            AND selected_size = :selected_size";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute([
                'user_id' => $user_id,
                'product_id' => $product_id,
                'selected_size' => $selected_size
            ]);
            $existing_cart_item = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_cart_item) {
                // Update quantity of the existing cart item
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
                $_SESSION['cart_message'] = "Cart quantity updated successfully!";
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
                $_SESSION['cart_message'] = "Item added to cart successfully!";
            }
        }
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
        p.image_url,
        p.size AS available_sizes
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
$total_stmt = $pdo->prepare 
($total_query);
$total_stmt->execute(['user_id' => $_SESSION['user_id']]);
$total_price = $total_stmt->fetchColumn();
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .product-image {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }

        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        .message, .error {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
        }

        .message {
            background-color: #4CAF50;
            color: white;
        }

        .error {
            background-color: #f443
            36;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        table th {
            background-color: #f8f9fa;
            color: #333;
        }

        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table td form {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 5px;
        }

        input[type="number"] {
            width: 60px;
            padding: 5px;
            text-align: center;
        }

        button {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        button[name="remove_item"] {
            background-color: #dc3545;
        }

        button[name="remove_item"]:hover {
            background-color: #c82333;
        }

        h2 {
            text-align: right;
            margin-bottom: 20px;
            color: #333;
        }

        form[name="clear_cart"], 
        form[name="checkout_all"] {
            display: inline-block;
            margin-right: 10px;
        }

        .cart-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .size-select {
            padding: 5px;
            margin-right: 10px;
        }

        .recommendations {
            margin-top: 20px;
        }

        .recommendation-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .recommendation-item {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            width: calc(20% - 20px);
        }

        .recommendation-item img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <h1>Your Shopping Cart</h1>
    <?php if (isset($_SESSION['cart_message'])): ?>
        <div class="message"><?php echo $_SESSION['cart_message']; unset($_SESSION['cart_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['cart_error'])): ?>
        <div class="error"><?php echo $_SESSION['cart_error']; unset($_SESSION['cart_error']); ?></div>
    <?php endif; ?>

    <table>
        <tr>
            <th>Image</th>
            <th>Product</th>
            <th>Size</th>
            <th>Quantity</th>
            <th>Price</th>
            <th>Action</th>
        </tr>
        <?php foreach ($cart_items as $item): ?>
            <tr>
                <td>
                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="product-image">
                </td>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td>
                    <form method="post" action="cart.php">
                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                        <select name="selected_size" class="size-select">
                            <?php 
                            $available_sizes = json_decode($item['available_sizes'], true) ?? [];
                            foreach ($available_sizes as $size_info):
                                $size = $size_info['size'];
                                $stock = $size_info['quantity'] ?? 0;
                            ?>
                                <option value="<?php echo htmlspecialchars($size); ?>" 
                                    <?php echo ($size == $item['selected_size'] ? 'selected' : ''); ?>
                                    <?php echo ($stock <= 0 ? 'disabled' : ''); ?>>
                                    <?php echo htmlspecialchars($size); ?> 
                                    <?php echo ($stock <= 0 ? '(Out of Stock)' : ""); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                
                        <button type="submit" name="update_cart">Update Size</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="cart.php">
                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                        <input type="hidden" name="selected_size" value="<?php echo $item['selected_size']; ?>">
                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1">
                        <button type="submit" name="update_cart">Update</button>
                    </form>
                </td>
                <td><?php echo htmlspecialchars($item['price_ksh']); ?></td>
                <td>
                    <form method="post" action="cart.php">
                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                        <button type="submit" name="remove_item">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Total Price: <?php echo htmlspecialchars($total_price); ?></h2>

    <div class="cart-actions">
        <form method="post" action="cart.php">
            <button type="submit" name="clear_cart">Clear Cart</button>
        </form>
        <form method="post" action="cart.php">
            <button type="submit" name="checkout_all">Proceed to Checkout</button>
        </form>
    </div>
</body>
</html>