<?php
function addToWishlist($pdo, $user_id, $product_id) {
    try {
        $check_query = "SELECT * FROM wishlist WHERE user_id = :user_id AND product_id = :product_id";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute(['user_id' => $user_id, 'product_id' => $product_id]);
        
        if ($check_stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Product already in wishlist'];
        }

        $insert_query = "INSERT INTO wishlist (user_id, product_id, added_at) VALUES (:user_id, :product_id, NOW())";
        $insert_stmt = $pdo->prepare($insert_query);
        $insert_stmt->execute(['user_id' => $user_id, 'product_id' => $product_id]);

        return ['success' => true, 'message' => 'Product added to wishlist'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getPersonalizedRecommendations($pdo, $user_id) {
    try {
        $category_query = "
            SELECT p.category, COUNT(*) as category_count 
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.user_id = :user_id
            GROUP BY p.category
            ORDER BY category_count DESC
            LIMIT 3
        ";
        $category_stmt = $pdo->prepare($category_query);
        $category_stmt->execute(['user_id' => $user_id]);
        $user_categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($user_categories)) {
            $random_query = "SELECT * FROM products ORDER BY RAND() LIMIT 5";
            $random_stmt = $pdo->query($random_query);
            return $random_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $recommendations_query = "
            SELECT * FROM products 
            WHERE category IN (" . 
            implode(',', array_map(function($cat) { 
                return "'" . $cat['category'] . "'"; 
            }, $user_categories)) . 
            ") AND product_id NOT IN (
                SELECT product_id FROM order_items WHERE user_id = :user_id
            )
            LIMIT 5
        ";
        $recommendations_stmt = $pdo->prepare($recommendations_query);
        $recommendations_stmt->execute(['user_id' => $user_id]);
        
        return $recommendations_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Recommendation Error: " . $e->getMessage());
        return [];
    }
}

function applyLoyaltyDiscount($pdo, $user_id) {
    try {
        $points_query = "SELECT loyalty_points FROM users WHERE user_id = :user_id";
        $points_stmt = $pdo->prepare($points_query);
        $points_stmt->execute(['user_id' => $user_id]);
        $current_points = $points_stmt->fetchColumn();

        $discount_percentage = min(20, floor($current_points / 100));
        
        return $discount_percentage;
    } catch (Exception $e) {
        error_log("Loyalty Discount Error: " . $e->getMessage());
        return 0;
    }
}

function trackUserBrowsing($pdo, $user_id, $product_id) {
    try {
        $insert_query = "
            INSERT INTO user_browsing_history 
            (user_id, product_id, viewed_at) 
            VALUES (:user_id, :product_id, NOW())
            ON DUPLICATE KEY UPDATE viewed_at = NOW()
        ";
        $stmt = $pdo->prepare($insert_query);
        $stmt->execute([
            'user_id' => $user_id,
            'product_id' => $product_id
        ]);
    } catch (Exception $e) {
        error_log("Browsing Track Error: " . $e->getMessage());
    }
}

function sendCartReminderNotification($pdo, $user_id) {
    try {
        $cart_check_query = "
            SELECT p.product_name, uc.created_at 
            FROM user_cart uc
            JOIN products p ON uc.product_id = p.product_id
            WHERE uc.user_id = :user_id 
            AND uc.created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ";
        $cart_stmt = $pdo->prepare($cart_check_query);
        $cart_stmt->execute(['user_id' => $user_id]);
        $cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($cart_items)) {
            $_SESSION['cart_reminder'] = "You have items in your cart from " . 
                count($cart_items) . " products waiting to be purchased!";
        }
    } catch (Exception $e) {
        error_log("Cart Reminder Error: " . $e->getMessage());
    }
}