<?php
session_start();

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include necessary files
require_once 'database.php';
require_once 'cart_manager.php';

// Initialize variables
$cartItems = [];
$cartTotal = 0;
$errorMessage = '';

try {
    // Initialize database and cart manager
    $database = new Database();
    $cartManager = new CartManager($database);

    // Get cart items for the logged-in user
    $cartItems = $cartManager->getCartItems($_SESSION['user_id']);
    
    // Calculate total cart value
    $cartTotal = $cartManager->getCartTotal($_SESSION['user_id']);

    // Handle cart actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_quantity':
                    if (isset($_POST['cart_id']) && isset($_POST['quantity'])) {
                        try {
                            $result = $cartManager->updateCartItemQuantity(
                                $_SESSION['user_id'], 
                                $_POST['cart_id'], 
                                $_POST['quantity']
                            );
                            
                            if ($result) {
                                $_SESSION['success_message'] = "Quantity updated successfully.";
                            } else {
                                $_SESSION['error_message'] = "Failed to update quantity.";
                            }
                            
                            header("Location: cart.php");
                            exit();
                        } catch (Exception $e) {
                            $_SESSION['error_message'] = $e->getMessage();
                            header("Location: cart.php");
                            exit();
                        }
                    }
                    break;

                case 'remove_item':
                    if (isset($_POST['cart_id'])) {
                        try {
                            $result = $cartManager->removeFromCart(
                                $_SESSION['user_id'], 
                                $_POST['cart_id']
                            );
                            
                            if ($result) {
                                $_SESSION['success_message'] = "Item removed from cart.";
                            } else {
                                $_SESSION['error_message'] = "Failed to remove item.";
                            }
                            
                            header("Location: cart.php");
                            exit();
                        } catch (Exception $e) {
                            $_SESSION['error_message'] = $e->getMessage();
                            header("Location: cart.php");
                            exit();
                        }
                    }
                    break;

                case 'clear_cart':
                    try {
                        $result = $cartManager->clearCart($_SESSION['user_id']);
                        
                        if ($result) {
                            $_SESSION['success_message'] = "Cart cleared successfully.";
                        } else {
                            $_SESSION['error_message'] = "Failed to clear cart.";
                        }
                        
                        header("Location: cart.php");
                        exit();
                    } catch (Exception $e) {
                        $_SESSION['error_message'] = $e->getMessage();
                        header("Location: cart.php");
                        exit();
                    }
                    break;

                    case 'proceed_to_checkout':
                        // Debug logging
                        error_log("Proceed to Checkout Triggered");
                        error_log("Cart Items: " . json_encode($cartItems));
                        error_log("Cart Total: " . $cartTotal);
    
                        // Validate cart before proceeding
                        if (empty($cartItems)) {
                            $_SESSION['error_message'] = "Your cart is empty.";
                            header("Location: cart.php");
                            exit();
                        }
    
                        // Perform stock validation
                        try {
                            $stockValidation = $cartManager->validateCartStock($_SESSION['user_id']);
                            
                            if (!empty($stockValidation['errors'])) {
                                // Store validation errors in session
                                $_SESSION['stock_errors'] = $stockValidation['errors'];
                                header("Location: cart.php");
                                exit();
                            }
    
                            // Set a session flag to indicate checkout attempt
                            $_SESSION['checkout_attempt'] = true;
    
                            // Multiple redirection methods
                            header("Location: checkout.php");
                            echo "<script>window.location.href = 'checkout.php';</script>";
                            exit();
                        } catch (Exception $e) {
                            $_SESSION['error_message'] = "Checkout validation failed: " . $e->getMessage();
                            header("Location: cart.php");
                            exit();
                        }
                        break;
                }
            }
        }
} catch (Exception $e) {
    // Handle any unexpected errors
    $errorMessage = $e->getMessage();
    $_SESSION['error_message'] = $errorMessage;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Shopping Cart</title>
    <style>
    {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Arial', sans-serif;
        background-color: #f4f4f4;
        line-height: 1.6;
        color: #333;
    }

    .cart-container {
        max-width: 900px;
        margin: 2rem auto;
        background-color: white;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
    }

    .cart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 1rem;
    }

    .cart-header h1 {
        color: #2c3e50;
    }

    .cart-table {
        width: 100%;
        border-collapse: collapse;
    }

    .cart-table th,
    .cart-table td {
        border: 1px solid #e0e0e0;
        padding: 0.75rem;
        text-align: left;
    }

    .cart-table th {
        background-color: #f8f9fa;
        font-weight: bold;
    }

    .cart-item-image {
        width: 80px;
        height: 80px;
        object-fit: cover;
        margin-right: 1rem;
    }

    .quantity-input {
        width: 60px;
        padding: 0.5rem;
        text-align: center;
    }

    .btn {
        display: inline-block;
        padding: 0.5rem 1rem;
        margin: 0.25rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .btn-update {
        background-color: #3498db;
        color: white;
    }

    .btn-remove {
        background-color: #e74c3c;
        color: white;
    }

    .btn-clear {
        background-color: #95a5a6;
        color: white;
    }

    .cart-summary {
        display: flex;
        justify-content: space-between;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 2px solid #e0e0e0;
    }

    .cart-total {
        font-size: 1.25rem;
        font-weight: bold;
    }

    .btn-checkout {
        background-color: #2ecc71;
        color: white;
        padding: 0.75rem 1.5rem;
        text-decoration: none;
        border-radius: 5px;
    }

    .error-message {
        background-color: #f8d7da;
        color: #721c24;
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 5px;
    }

    .empty-cart {
        text-align: center;
        padding: 2rem;
        color: #7f8c8d;
    }

    .cart-actions {
        display: flex;
        gap: 0.5rem;
    }
    </style>
</head>

<body>
    <div class="cart-container">
        <div class="cart-header">
            <h1>Your Shopping Cart</h1>
        </div>
<?php
// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include necessary files
require_once 'database.php';
require_once 'cart_manager.php';

// Initialize variables
$cartItems = [];
$cartTotal = 0;
$errorMessage = '';

try {
    // Initialize database and cart manager
    $database = new Database();
    $cartManager = new CartManager($database);

    // Get cart items for the logged-in user
    $cartItems = $cartManager->getCartItems($_SESSION['user_id']);
    
    // Calculate total cart value
    $cartTotal = $cartManager->getCartTotal($_SESSION['user_id']);

    // Handle cart actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_quantity':
                    if (isset($_POST['cart_id']) && isset($_POST['quantity'])) {
                        try {
                            $result = $cartManager->updateCartItemQuantity(
                                $_SESSION['user_id'], 
                                $_POST['cart_id'], 
                                $_POST['quantity']
                            );
                            
                            if ($result) {
                                $_SESSION['success_message'] = "Quantity updated successfully.";
                            } else {
                                $_SESSION['error_message'] = "Failed to update quantity.";
                            }
                            
                            header("Location: cart.php");
                            exit();
                        } catch (Exception $e) {
                            $_SESSION['error_message'] = $e->getMessage();
                            header("Location: cart.php");
                            exit();
                        }
                    }
                    break;

                case 'remove_item':
                    if (isset($_POST['cart_id'])) {
                        try {
                            $result = $cartManager->removeFromCart(
                                $_SESSION['user_id'], 
                                $_POST['cart_id']
                            );
                            
                            if ($result) {
                                $_SESSION['success_message'] = "Item removed from cart.";
                            } else {
                                $_SESSION['error_message'] = "Failed to remove item.";
                            }
                            
                            header("Location: cart.php");
                            exit();
                        } catch (Exception $e) {
                            $_SESSION['error_message'] = $e->getMessage();
                            header("Location: cart.php");
                            exit();
                        }
                    }
                    break;

                case 'clear_cart':
                    try {
                        $result = $cartManager->clearCart($_SESSION['user_id']);
                        
                        if ($result) {
                            $_SESSION['success_message'] = "Cart cleared successfully.";
                        } else {
                            $_SESSION['error_message'] = "Failed to clear cart.";
                        }
                        
                        header("Location: cart.php");
                        exit();
                    } catch (Exception $e) {
                        $_SESSION['error_message'] = $e->getMessage();
                        header("Location: cart.php");
                        exit();
                    }
                    break;

                case 'proceed_to_checkout':
                    // Validate cart before proceeding
                    if (empty($cartItems)) {
                        $_SESSION['error_message'] = "Your cart is empty.";
                        header("Location: cart.php");
                        exit();
                    }

                    // Perform stock validation
                    try {
                        $stockValidation = $cartManager->validateCartStock($_SESSION['user_id']);
                        
                        if (!empty($stockValidation['errors'])) {
                            // Store validation errors in session
                            $_SESSION['stock_errors'] = $stockValidation['errors'];
                            header("Location: cart.php");
                            exit();
                        }

                        // If all validations pass, proceed to checkout
                        header("Location: checkout.php");
                        exit();
                    } catch (Exception $e) {
                        $_SESSION['error_message'] = "Checkout validation failed: " . $e->getMessage();
                        header("Location: cart.php");
                        exit();
                    }
                    break;
            }
        }
    }
} catch (Exception $e) {
    // Handle any unexpected errors
    $errorMessage = $e->getMessage();
    $_SESSION['error_message'] = $errorMessage;
}
?>

        <!-- Error and Success Messages -->
        <?php
        if (isset($_SESSION['error_message'])) {
            echo "<div class='alert alert-danger'>" . 
                 htmlspecialchars($_SESSION['error_message']) . 
                 "</div>";
            unset($_SESSION['error_message']);
        }

        if (isset($_SESSION['success_message'])) {
            echo "<div class='alert alert-success'>" . 
                 htmlspecialchars($_SESSION['success_message']) . 
                 "</div>";
            unset($_SESSION['success_message']);
        }

        // Display stock validation errors
        if (isset($_SESSION['stock_errors'])) {
            echo "<div class='alert alert-warning'>";
            echo "<h3>Stock Availability Issues:</h3>";
            foreach ($_SESSION['stock_errors'] as $error) {
                echo "<p>" . htmlspecialchars($error) . "</p>";
            }
            echo "</div>";
            unset($_SESSION['stock_errors']);
        }
        ?>

        <?php if (isset($errorMessage)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <?php endif; ?>

        <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
            <p>Your cart is empty. Start shopping!</p>
        </div>
        <?php else: ?>
        <table class="cart-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cartItems as $item): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center;">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>"
                                alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="cart-item-image">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </div>
                    </td>
                    <td>Ksh <?php echo number_format($item['price_ksh'], 2); ?></td>
                    <td>
                        <form method="post" class="cart-actions">
                            <input type="hidden" name="action" value="update_quantity">
                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1"
                                class="quantity-input">
                            <button type="submit" class="btn btn-update">Update</button>
                        </form>
                    </td>
                    <td>Ksh <?php echo number_format($item['price_ksh'], 2); ?></td>
                    <td>
                        <form method="post" class="cart-actions">
                            <input type="hidden" name="action" value="remove_item">
                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                            <button type="submit" class="btn btn-remove">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="cart-summary">
            <span class="cart-total">Total: Ksh <?php echo number_format($cartTotal, 2); ?></span>
            <div class="cart-actions">
                <form method="post">
                    <input type="hidden" name="action" value="clear_cart">
                    <button type="submit" class="btn btn-clear">Clear Cart</button>
                </form>
                <form method="post" id="checkoutForm">
                    <input type="hidden" name="action" value="proceed_to_checkout">
                    <input type="hidden" name="cart_total" value="<?php echo $cartTotal; ?>">
                    <button type="submit" class="btn btn-checkout">Proceed to Checkout</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const checkoutForm = document.getElementById('checkoutForm');
        
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default form submission

                // Log to console for debugging
                console.log('Checkout form submitted');

                // Use AJAX to validate checkout
                fetch('validate_checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: <?php echo $_SESSION['user_id']; ?>,
                        cart_total: <?php echo $cartTotal; ?>
                    })
                })
                .then(response => {
                    console.log('Response Status:', response.status); // Log the response status
                    return response.json();
                })
                .then(data => {
                    console.log('Response Data:', data); // Log the response data
                    if (data.can_proceed) {
                        // Redirect to checkout
                        window.location.href = 'checkout.php';
                    } else {
                        // Handle errors
                        if (data.errors) {
                            let errorString = 'Stock availability issues:\n';
                            data.errors.forEach(error => {
                                errorString += `- Product: ${error.product}, Requested: ${error.requested}, Available: ${error.available}\n`;
                            });
                            alert(errorString);
                        } else {
                            alert(data.message || 'Cannot proceed to checkout');
                        }
                    }
                })
                .catch(error => {
                    console.error('Checkout Validation Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        }
    });
    </script>
</body>
</html>