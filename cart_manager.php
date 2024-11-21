

<?php
// Ensure Database class is included
require_once 'database.php';

class CartManager {
    private $database;
    private $pdo;

    public function __construct(Database $database) {
        $this->database = $database;
        $this->pdo = $database->getPdo(); // Use getPdo() to get PDO connection
    }

// Get cart items for a specific user with full product details
public function getCartItems($userId) {
    try {
        $query = "
            SELECT 
                uc.id AS cart_id,
                uc.product_id, 
                p.product_name,
                p.image_url,
                p.price_ksh,
                uc.quantity, 
                (uc.quantity * p.price_ksh) AS total_price
            FROM 
                user_cart uc
            JOIN 
                products p ON uc.product_id = p.product_id
            WHERE 
                uc.user_id = :user_id
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        
        // Fetch all cart items and calculate the total price
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate the total price for each item based on quantity
        foreach ($cartItems as &$item) {
            $item['price'] = $item['quantity'] * $item['price_ksh']; // Calculate total price for the item
        }

        return $cartItems;
    } catch (PDOException $e) {
        error_log("Get Cart Items Error: " . $e->getMessage());
        throw new Exception("Failed to retrieve cart items: " . $e->getMessage());
    }
}

    // Calculate total cart value
    public function getCartTotal($userId) {
        try {
            $query = "
                SELECT 
                    COALESCE(SUM(uc.quantity * p.price_ksh), 0) AS cart_total
                FROM 
                    user_cart uc
                JOIN 
                    products p ON uc.product_id = p.product_id
                WHERE 
                    uc.user_id = :user_id
            ";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':user_id' => $userId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['cart_total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Cart Total Calculation Error: " . $e->getMessage());
            throw new Exception("Failed to calculate cart total: " . $e->getMessage());
        }
    }

    // Add item to cart
    public function addToCart($userId, $productId, $quantity = 1) {
        try {
            // Begin transaction
            $this->pdo->beginTransaction();

            // Check if product already exists in cart
            $query = "
                INSERT INTO user_cart (user_id, product_id, quantity) 
                VALUES (:user_id, :product_id, :quantity)
                ON DUPLICATE KEY UPDATE 
                quantity = quantity + :quantity
            ";

            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute([
                ':user_id' => $userId,
                ':product_id' => $productId,
                ':quantity' => $quantity
            ]);

            // Commit transaction
            $this->pdo->commit();

            return $result;
        } catch (PDOException $e) {
            // Rollback transaction in case of error
            $this->pdo->rollBack();
            error_log("Add to Cart Error: " . $e->getMessage());
            throw new Exception("Failed to add product to cart: " . $e->getMessage());
        }
    }

    // Update cart item quantity
    public function updateCartItemQuantity($userId, $cartId, $quantity) {
        try {
            $query = "
                UPDATE user_cart 
                SET quantity = :quantity 
                WHERE id = :cart_id AND user_id = :user_id
            ";

            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute([
                ':quantity' => $quantity,
                ':cart_id' => $cartId,
                ':user_id' => $userId
            ]);

            return $result;
        } catch (PDOException $e) {
            error_log("Update Cart Quantity Error: " . $e->getMessage());
            throw new Exception("Failed to update cart item quantity: " . $e->getMessage());
        }
    }

    // Remove item from cart
    public function removeFromCart($userId, $cartId) {
        try {
            $query = "
                DELETE FROM user_cart 
                WHERE id = :cart_id AND user_id = :user_id
            ";

            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute([
                ':cart_id' => $cartId,
                ':user_id' => $userId
            ]);

            return $result;
        } catch (PDOException $e) {
            error_log("Remove from Cart Error: " . $e->getMessage());
            throw new Exception("Failed to remove item from cart: " . $e->getMessage());
        }
    }

    // Clear entire cart
    public function clearCart($userId) {
        try {
            $query = "
                DELETE FROM user_cart 
                WHERE user_id = :user_id
            ";

            $stmt = $this->pdo->prepare($query);
            $result = $stmt->execute([':user_id' => $userId]);

            return $result;
        } catch (PDOException $e) {
            error_log("Clear Cart Error: " . $e->getMessage());
            throw new Exception("Failed to clear cart: " . $e->getMessage());
        }
    }

    // Get cart item count
    public function getCartItemCount($userId) {
        try {
            $query = "
                SELECT 
                    COALESCE(SUM(quantity), 0) AS total_items
                FROM 
                    user_cart
                WHERE 
                    user_id = :user_id
            ";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute([':user_id' => $userId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total_items'] ?? 0;
        } catch (PDOException $e) {
            error_log("Cart Item Count Error: " . $e->getMessage());
            throw new Exception("Failed to retrieve cart item count: " . $e->getMessage());
        }
    }

    public function validateCartStock($userId) {
        $errors = [];
        $cartItems = $this->getCartItems($userId); // Fetch cart items

        foreach ($cartItems as $item) {
            // Check stock for each item
            $product = $this->getProductDetails($item['product_id']); // Get product details

            // Check if product exists and if stock is sufficient
            if ($product && $product['quantity_in_stock'] < $item['quantity']) { 
                $errors[] = "Insufficient stock for {$product['product_name']}. Available: {$product['quantity_in_stock']}, Requested: {$item['quantity']}";
            }
        }

        return ['errors' => $errors];
    }

    // Helper method to fetch product details
    private function getProductDetails($productId) {
        $query = "SELECT * FROM products WHERE product_id = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Check if product is already in cart
    public function isProductInCart($userId, $productId) {
        try {
            $query = "
                SELECT COUNT(*) as count 
                FROM user_cart 
                WHERE user_id = :user_id AND product_id = :product_id
            ";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                ':user_id' => $userId,
                ':product_id' => $productId
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Check Product in Cart Error: " . $e->getMessage());
            throw new Exception("Failed to check product in cart: " . $e->getMessage());
        }
    }
}

// Example usage
try {
    // Create database connection
    $database = new Database();

    // Create cart manager
    $cartManager = new CartManager($database);

    // Assuming you have user authentication and user ID
    $userId = $_SESSION['user_id']; // Make sure this is set

    // Get cart items
    $cartItems = $cartManager->getCartItems($userId);

    // Get cart total
    $cartTotal = $cartManager->getCartTotal($userId);

    // Get cart item count
    $cartItemCount = $cartManager->getCartItemCount($userId);

} catch (Exception $e) {
    // Handle any errors
    error_log("Cart Manager Error: " . $e->getMessage());
    // Display user-friendly error message
}
