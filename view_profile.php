<?php
session_start();
require 'db_config.php'; // Database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user profile
$sql = "SELECT u.username, u.email AS user_email, p.name, p.address, p.profile_picture, p.email AS profile_email 
        FROM users u 
        LEFT JOIN profiles p ON u.user_id = p.user_id 
        WHERE u.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$profile = $stmt->fetch();

if (!$profile) {
    echo "Profile not found.";
    exit();
}
// Assign profile data to variables
$username = $profile['username'];
$name = $profile['name'];
$address = $profile['address'];
$profile_picture = $profile['profile_picture'];
$user_email = $profile['user_email'];
$profile_email = $profile['profile_email'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Roboto', sans-serif;
        background-color: #f4f4f4;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h1 {
        text-align: center;
        color: #333;
    }

    p {
        font-size: 18px;
        color: #555;
    }

    .profile-picture {
        text-align: center;
        margin-bottom: 20px;
    }

    .profile-picture img {
        border-radius: 50%;
        width: 150px;
        height: 150px;
        object-fit: cover;
    }

    .profile-info {
        margin-bottom: 20px;
    }

    .profile-info p {
        margin: 10px 0;
    }

    .error {
        color: red;
        text-align: center;
    }
    </style>
</head>

<body>
    <div class="container">
        <h1>Profile Information</h1>
        <div class="profile-picture">
            <?php if ($profile_picture): ?>
            <img src="<?php echo $profile_picture; ?>" alt="Profile Picture">
            <?php else: ?>
            <img src="default-profile.png" alt="Default Profile Picture">
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <p><strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($name); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?></p>
            <p><strong>Profile Email:</strong> <?php echo htmlspecialchars($profile_email); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($address); ?></p>
        </div>
    </div>
</body>

</html>