<?php
session_start();
include 'db_config.php';

// Check if the user is logged in as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); // Redirect to login page if not an admin
    exit();
}

// Fetch the profile picture URL for the logged-in user
$user_id = $_SESSION['user_id']; // Get the logged-in user's ID
$stmt = $pdo->prepare("SELECT profile_picture_url FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$profile_picture_url = $user ? $user['profile_picture_url'] : 'https://live.staticflickr.com/65535/53312109285_9243397fae_z.jpg'; // Fallback image

// Handle profile picture update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
        $fileName = $_FILES['profile_picture']['name'];
        $fileSize = $_FILES['profile_picture']['size'];
        $fileType = $_FILES['profile_picture']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Specify the directory where you want to save the uploaded file
        $uploadFileDir = './uploaded_profile_pictures/';
        $newFileName = uniqid() . '.' . $fileExtension; // Create a unique name for the file
        $dest_path = $uploadFileDir . $newFileName;

        // Move the file to the upload directory
        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            // Update the users table with the new profile picture URL
            $profile_picture_url = $dest_path; // Store the path to the uploaded file
            $stmt = $pdo->prepare("UPDATE users SET profile_picture_url = ? WHERE user_id = ?");
            $stmt->execute([$profile_picture_url, $user_id]); // Use the logged-in user's ID
        } else {
            echo "There was an error moving the uploaded file.";
        }
    }
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $product_name = $_POST['product_name'];
    $price = $_POST['price'];
    $image_url = $_POST['image_url'];
    $quantity_in_stock = $_POST['quantity_in_stock'];

    // Insert new product into the database
    $stmt = $pdo->prepare("INSERT INTO products (product_name, price_ksh, image_url, quantity_in_stock) VALUES (?, ?, ?, ?)");
    $stmt->execute([$product_name, $price, $image_url, $quantity_in_stock]); // Ensure all variables are included
}

// Handle product deletion
if (isset($_GET['delete'])) {
    $product_id = $_GET['delete'];

    // Delete the product from the database
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);
}

// Fetch products from the database
$stmt = $pdo->prepare("SELECT * FROM products");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 20px;
    }

    h1,
    h2 {
        color: #333;
    }

    .welcome-message {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .welcome-message p {
        margin: 0;
    }

    .button {
        padding: 10px 20px;
        background-color: #007BFF;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        margin-right: 10px;
        /* Add some margin between buttons */
    }

    .button:hover {
        background-color: #0056b3;
    }

    form {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: #fff;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    th,
    td {
        border: 1px solid #ddd;
        padding: 12px;
        text-align: left;
    }

    th {
        background-color: #f4f4f4;
    }

    td img {
        width: 50px;
        border-radius: 4px;
    }

    footer {
        text-align: center;
        margin-top: 20px;
        color: #777;
    }

    .profile-picture {
        width: 50px;
        /* Adjust size as needed */
        height: 50px;
        /* Adjust size as needed */
        border-radius: 50%;
        /* Make it circular */
        object-fit: cover;
        /* Maintain aspect ratio */
    }

    .profile-container {
        position: absolute;
        /* Positioning for the upper right corner */
        top: 20px;
        /* Adjust as needed */
        right: 20px;
        /* Adjust as needed */
    }
    </style>
</head>

<body>
    <h1>Admin Dashboard</h1>

    <div class="welcome-message">
        <p>Hello, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        <div class="profile-container">
            <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" alt="Profile Picture"
                class="profile-picture">
        </div>


        <a href="view_profile.php" class="button">Your Profile</a>
    </div>

    <!-- Navigation Buttons -->

    <h2>Update Profile Picture</h2>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label for="profile_picture">Upload Profile Picture:</label>
            <input type="file" name="profile_picture" accept="image/*" required>
        </div>
        <button type="submit" name="update_picture" class="button">Update Picture</button>
    </form>
    <div class="navigation-buttons">
        <a href="order_management.php" class="button">Orders Management</a>
        <a href="product_management.php" class="button">Products Management</a>
        <a href="user_management.php" class="button">User Management</a>
        <a href="analytics.php" class="button">Analytics</a>
        <a href="profile.php" class="button">Profile Update</a>
        <a href="logout.php" class="button">Logout</a>
        <a href="backup.php" class="button">Back up</a>
    </div>

    <h2>Add New Product</h2>
    <form method="POST" action="">
        <div class="form-group">
            <label for="product_name">Product Name:</label>
            <input type="text" name="product_name" required>
        </div>
        <div class="form-group">
            <label for="price_ksh">Price:</label>
            <input type="number" name="price" required>
        </div>
        <div class="form-group">
            <label for="image_url">Image URL:</label>
            <input type="text" name="image_url" required>
        </div>
        <div class="form-group">
            <label for="quantity_in_stock">Quantity:</label>
            <input type="text" name="quantity_in_stock" required>
        </div>
        <button type="submit" name="add_product" class="button">Add Product</button>
    </form>

    <h2>Existing Products</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Product Name</th>
                <th>Price</th>
                <th>Image</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr>
                <td><?php echo $product['product_id']; ?></td>
                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                <td><?php echo htmlspecialchars($product['price_ksh']); ?></td>
                <td><img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                        alt="<?php echo htmlspecialchars($product['product_name']); ?>"></td>
                <td>
                    <a href="?delete=<?php echo $product['product_id']; ?>" class="button"
                        onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <footer>
        <p>© 2023 Tariq's Store Admin</p>
    </footer>
</body>

</html>