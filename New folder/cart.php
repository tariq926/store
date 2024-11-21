
<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'db_config.php'; // Include your database connection file

// Function to count total items in the cart
function countItemsInCart($cart) {
    $totalItems = 0;
    foreach ($cart as $product) {
        $totalItems += $product['quantity']; // Sum the quantities of each product
    }
    return $totalItems;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Load the username from the session
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User ';

// Function to save cart to database
function saveCartToDatabase($userId, $cart) {
    global $pdo; // Assuming $pdo is your PDO database connection
    foreach ($cart as $productId => $product) {
        $quantity = $product['quantity'];
        try {
            $stmt = $pdo->prepare("INSERT INTO user_cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
            $stmt->execute([$userId, $productId, $quantity, $quantity]);
        } catch (PDOException $e) {
            error_log("Error saving cart to database: " . $e->getMessage());
        }
    }
}

// Load cart from database
function loadCartFromDatabase($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM user_cart WHERE user_id = ?");
    $stmt->execute([$userId]);
    $cart = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Fetch product details for each item in the cart
    $fullCart = [];
    foreach ($cart as $productId => $quantity) {
        // Assuming you have a products table to fetch details
        $productStmt = $pdo->prepare("SELECT name, price_in_ksh, image FROM products WHERE product_id = ?");
        $productStmt->execute([$productId]);
        $productDetails = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($productDetails) {
            $fullCart[$productId] = [
                'name' => $productDetails['name'],
                'price' => $productDetails['price_in_ksh'],
                'image' => $productDetails['image'],
                'quantity' => $quantity
            ];
        } else {
            error_log("Product ID $productId not found in products table.");
        }
    }
    return $fullCart;
}

// Additional functionality to clear the cart
if (isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = [];
    // Clear the cart from the database as well
    $stmt = $pdo->prepare("DELETE FROM user_cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Load cart from database if not in session
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = loadCartFromDatabase($_SESSION['user_id']);
}

// Handle form submissions for updating/removing items
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $productId = $_POST['product_id'];
        $newQuantity = $_POST['quantity'];

        if ($newQuantity <= 0) {
            // Remove item if quantity is 0 or less
            unset($_SESSION['cart'][$productId]);
        } else {
            // Update quantity
            $_SESSION['cart'][$productId]['quantity'] = $newQuantity;
        }

        // Save cart to database
        saveCartToDatabase($_SESSION['user_id'], $_SESSION['cart']);
    }

    if (isset($_POST['remove_item'])) {
        $productId = $_POST['product_id'];
        unset($_SESSION['cart'][$productId]);

        // Save cart to database
        saveCartToDatabase($_SESSION['user_id'], $_SESSION['cart']);
    }
}

// After initializing the cart
$totalItemsInCart = countItemsInCart($_SESSION['cart']); // Get the total items count

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    echo "<p>Your cart is empty. Please add products to your cart.</p>";
    exit();
}

// Calculate total price
$totalPrice = 0;
foreach ($_SESSION['cart'] as $product) {
    $totalPrice += $product['price'] * $product['quantity'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Your Shopping Cart</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        transition: background-color 0.3s, color 0.3s;
        /* smooth transition */
        cursor: default;
    }

    /* dark mode style */
    body.dark-mode {
        background-color: #121212;
        /* dark background */
        color: #fff;
        /* white text */
    }

    /* Sidebar styles */
    .sidebar {
        height: calc(100%);
        /* set height to 95% of the viewport height */
        width: 250px;
        /* Width of the sidebar */
        position: fixed;
        /* Fixed position */
        left: -250px;
        /* Start hidden off-screen */
        transition: left 0.3s;
        /* Smooth transition */
        background-color: #f4f4f4;
        /* Background color */
        padding: 10px;
        /* Padding inside sidebar */
        box-shadow: 2px 0 5px rgba(0, 0, 0, 0.5);
        /* Shadow effect */
    }

    .sidebar.active {
        left: 0;
        /* Move into view */
    }

    .sidebar.dark-mode {
        background-color: #1e1e1e;
        /* Dark sidebar */
    }

    .sidebar li {
        padding: 10px;
        border-radius: 5px;
        transition: background-color 0.3s, transform 0.3s;
    }

    .sidebar li:hover::before {
        content: "";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 100%;
        height: 100%;
        background-color: #fff;
        /* White background */
        border-radius: 5px;
        z-index: -1;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        /* Add a subtle shadow */
    }

    .sidebar li:hover {
        background-color: #f7f7f7;
        /* Light gray background */
        transform: translateY(-5px);
        /* Move up slightly */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        /* Add a subtle shadow */
    }

    .cart-list {
        display: flex;
        flex-direction: column;
        gap: 1em;
    }

    .cart-item {
        display: flex;
        align-items: center;
        gap: 1em;
    }

    .cart-item img {
        width: 100px;
        height: 100px;
        object-fit: cover;
    }

    .button {
        padding: 0.5em 1em;
        background-color: #007bff;
        color: white;
        border: none;
        cursor: pointer;
    }

    .button:hover {
        background-color: #0056b3;
    }

    .main-content {
        margin-left: 20px;
        /* Default margin */
        padding: 20px;
        /* Main content padding */
        transition: margin-left 0.3s;
        /* Smooth transition for content */
    }

    .main-content.shifted {
        margin-left: 270px;
        /* Shifted margin when sidebar is active */
    }

    .toggle-btn {
        position: fixed;
        top: 10px;
        left: 10px;
        background-color: #007BFF;
        /* Button color */
        color: white;
        /* Text color */
        border: none;
        padding: 10px 15px;
        cursor: pointer;
        z-index: 1000;
        /* Ensure button is on top */
    }

    .toggle-btn.menu {
        left: 10px;
    }

    /* Position for menu toggle button */
    .toggle-btn.dark-mode {
        right: 10px;
        /* Position for dark mode toggle button */
        left: auto;
        /* Prevent overriding left property */
    }
    </style>
</head>

<body>
    <button class="toggle-btn menu" onclick="toggleSidebar()">☰ Menu</button>


    <?php include 'sidebar.php'; ?>

    <div class="main-content" id="main-content">
        <h2><?php echo $username; ?>'s Shopping Cart</h2>
        <div class="cart-list">
            <?php foreach ($_SESSION['cart'] as $productId => $product): ?>
            <div class="cart-item">
                <img src="<?php echo htmlspecialchars($product['image']); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p>Price: ksh. <?php echo htmlspecialchars($product['price']); ?></p>
                <form method="POST" action="">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    <input type="number" name="quantity" value="<?php echo $product['quantity']; ?>" min="0">
                    <button type="submit" name="update_quantity" class="button">Update Quantity</button>
                    <button type="submit" name="remove_item" class="button">Remove Item</button>
                </form>
                <p>Subtotal: ksh. <?php echo $product['price'] * $product['quantity']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="">
            <button type="submit" name="clear_cart" class="button">Clear Cart</button>
        </form>
        <h3>Total Price: ksh. <?php echo $totalPrice; ?></h3>
        <a href="checkout.php" class="button">Proceed to Checkout</a>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content'); // Get the main content element
        sidebar.classList.toggle('active'); // Toggle sidebar visibility
        mainContent.classList.toggle('shifted'); // Adjust main content margin
    }

    function toggleDarkMode() {
        const body = document.body;
        const sidebar = document.getElementById('sidebar');
        const darkModeToggle = document.getElementById('darkModeToggle');

        body.classList.toggle('dark-mode');
        sidebar.classList.toggle('dark-mode');

        // Change button text based on dark mode status
        if (body.classList.contains('dark-mode')) {
            darkModeToggle.textContent = '☀️ Light Mode'; // Change text to Light Mode
        } else {
            darkModeToggle.textContent = '🌙 Dark Mode'; // Change text back to Dark Mode
        }
    }
    </script>
</body>

</html>