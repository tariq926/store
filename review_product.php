<?php
session_start();
require 'db_config.php'; // Include the database connection script

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Fetch product data
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo "<div class='error'>Product not found.</div>";
    exit();
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    // Validate inputs
    if ($rating < 1 || $rating > 5) {
        echo "<div class='error'>Rating must be between 1 and 5.</div>";
    } elseif (empty($comment)) {
        echo "<div class='error'>Comment cannot be empty.</div>";
    } else {
        // Insert review into the database
        $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$product_id, $_SESSION['user_id'], $rating, $comment]);
        echo "<div class='success'>Review submitted successfully!</div>";
    }
}

// Fetch existing reviews for the product
$stmt = $pdo->prepare("SELECT r.rating, r.comment, r.created_at, u.username FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Product - <?= htmlspecialchars($product['product_name']) ?></title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        color: #333;
        margin: 0;
        padding: 0;
    }

    header {
        background: #007BFF;
        color: white;
        padding: 10px 0;
        text-align: center;
    }

    main {
        max-width: 800px;
        margin: 20px auto;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    h1,
    h2 {
        color: #007BFF;
    }

    form {
        margin-bottom: 20px;
    }

    label {
        display: block;
        margin: 10px 0 5px;
    }

    input[type="number"],
    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
    }

    button {
        background: #007BFF;
        color: white;
        border: none;
        padding: 10px 15px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    button:hover {
        background: #0056b3;
    }

    .success {
        color: green;
        margin: 10px 0;
    }

    .error {
        color: red;
        margin: 10px 0;
    }

    .review {
        border-bottom: 1px solid #ccc;
        padding: 10px 0;
    }

    .review h3 {
        margin: 0;
        color: #007BFF;
    }

    .review p {
        margin: 5px 0;
    }

    footer {
        text-align: center;
        padding: 10px 0;
        background: #f4f4f4;
        margin-top: 20px;
        border-top: 1px solid #ccc;
    }
    </style>
</head>

<body>
    <header>
        <h1>Review Product: <?= htmlspecialchars($product['name']) ?></h1>
    </header>
    <main>
        <section>
            <h2>Submit Your Review</h2>
            <form method="POST" action="">
                <label for="rating">Rating (1-5):</label>
                <input type="number" name="rating" min="1" max="5" required>

                <label for="comment">Comment:</label>
                <textarea name="comment" rows="4" required></textarea>

                <button type="submit" name="submit_review">Submit Review</button>
            </form>
        </section>

        <section>
            <h2>Existing Reviews</h2>
            <?php if (count($reviews) > 0): ?>
            <?php foreach ($reviews as $review): ?>
            <div class="review">
                <h3><?= htmlspecialchars($review['username']) ?> (Rating: <?= htmlspecialchars($review['rating']) ?>)
                </h3>
                <p><?= htmlspecialchars($review['comment']) ?></p>
                <p><small>Reviewed on <?= date('F j, Y, g:i a', strtotime($review['created_at'])) ?></small></p>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <p>No reviews yet. Be the first to review this product!</p>
            <?php endif; ?>
        </section>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Your Company Name. All rights reserved.</p>
    </footer>
</body>

</html>