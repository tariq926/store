<?php
session_start();
include 'db_config.php';

// Initialize the cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    if (isset($_COOKIE['cart'])) {
        $_SESSION['cart'] = json_decode($_COOKIE['cart'], true);
    } else {
        $_SESSION['cart'] = [];
    }
}
// Function declaration OUTSIDE the conditional block 
function countItemsInCart($cart) {
    $totalItems = 0;
    if (isset($cart)) {
        foreach ($cart as $product) {
            $totalItems += $product['quantity'];
        }
    }
    return $totalItems;
}

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$totalItemsInCart = countItemsInCart($_SESSION['cart']); 
// Handle adding to cart
if (isset($_POST['add_to_cart'])) {
    $productId = $_POST['product_id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Add product to cart
        $_SESSION['cart'][$productId] = [
            'name' => $product['product_name'],
            'price' => $product['price_ksh'],
            'image' => $product['image_url'],
            'quantity' => 1 // Default quantity
        ];
    }
}
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ... (Rest of the code) ...

$totalItemsInCart = countItemsInCart($_SESSION['cart']); 

// Optionally set a cookie to store cart data
setcookie('cart', json_encode($_SESSION['cart']), time() + (86400 * 30), "/"); // 30 days

// Fetch products from the database
$stmt = $pdo->prepare("SELECT * FROM products");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle search functionality
$searchResults = [];
$noResultsMessage = '';
if (isset($_GET['search'])) {
    $searchTerm = $_GET['search'];
    
    // Prepare the SQL statement to search for products
    $stmt = $pdo->prepare("SELECT * FROM products WHERE product_name LIKE ?");
    $stmt->execute(["%$searchTerm%"]);
    $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if any products were found
    if (empty($searchResults)) {
        $noResultsMessage = "No products found matching your search.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fashion Store</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        transition: background-color 0.3s, color 0.3s;
        background-color: #f4f4f4;
        /* Light gray background */
        color: #333;
        cursor: default;
    }

    /* dark mode style */
    body.dark-mode {
        background-color: #121212;
        /* dark background*/
        color: #fff;
        /* white text */
    }

    /* Sidebar styles */
    .sidebar {
        height: calc(100%);
        /* set height to 95% of the viewport height*/
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
        /* dark sidebar */
    }

    .sidebar li {
        padding: 10px;
        border-radius: 5px;
        transition: background-color 0.3s, transform 0.3s;
    }

    .sidebar li:hover {
        background-color: #f7f7f7;
        /* Light gray background */
        transform: translateY(-5px);
        /* Move up slightly */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        /* Add a subtle shadow */
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
        /* Position for menu toggle button */
    }

    .toggle-btn.dark-mode {
        right: 10px;
        /* Position for dark mode toggle button */
        left: auto;
        /* Prevent overriding left property */
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

    .product-list {
        display: flex;
        /* Use flexbox to align items */
        flex-wrap: wrap;
        /* Allow items to wrap to the next line if necessary */
        justify-content: flex-start;
        /* Align items at the start */
    }

    .product {
        margin: 5px;
        /* Add some space between products */
        text-align: center;
        /* Center text under images */
        width: 200px;
        /* Set fixed width for products */
    }

    .product img {
        max-width: 100%;
        /* Ensure the images are responsive */
        height: 150px;
        /* set fixed height for images */
        object-fit: cover;
        /* maintain aspect ratio while covering the area */
        transition: transform 0.3s;
        /*smooth transition*/
    }

    .product img:hover {
        transform: scale(1.1);
        /* Increase the size of the image on hover */
        transition: transform 0.3s;
        /* Smooth transition */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        /* Add a shadow effect */
        cursor: pointer;
        animation: hover-effect 0.5s;
        /* hover effect animation */
    }

    @keyframes hover-effect {
        0% {
            transform: scale(1) rotate(0deg);
            opacity: 1;
        }

        50% {
            transform: scale(1.2) rotate(10deg);
            opacity: 0.8;
        }

        100% {
            transform: scale(1.1) rotate(0deg);
            opacity: 1;
        }
    }

    footer {
        text-align: center;
        /* center footer text */
        padding: 10px;
        background-color: #f4f4f4;
        position: relative;
        bottom: 0;
        width: 100%;
    }

    footer.dark-mode {
        background-color: #1e1e1e;
        /* Dark mode background color */
    }

    .toggle-btn.menu {
        left: 10px;
        /* Position for menu toggle button */
    }

    .toggle-btn.dark-mode {
        right: 10px;
        /* Position for dark mode toggle button */
        left: auto;
        /* Prevent overriding left property */
    }

    footer.dark-mode {
        background-color: #1e1e1e;
    }

    /* Additional styles for the search bar */
    .search-bar {
        display: none;
        margin-top: 10px;
    }

    .search-bar input {
        padding: 5px;
        width: 200px;
    }

    .search-bar button {
        padding: 5px;
    }

    .navbar {
        display: flex;
        /* Use flexbox for layout */
        justify-content: space-around;
        /* Space items evenly */
        padding: 10px;
        /* Padding for the navbar */
        background-color: #f4f4f4;
        /* Background color of navbar */
        box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.1);
        /* Shadow effect */
        width: 100%;
        bottom: 0;
    }

    .navbar.dark-mode {
        background: #1e1e1e;
    }

    .nav-item {
        text-align: center;
        /* Center align text and icons */
    }

    .nav-icon {
        width: 30px;
        /* Set width for icons */
        height: 30px;
        /* Set height for icons */
        margin-bottom: 5px;
        /* Space between icon and text */
    }

    button {
        cursor: pointer;
    }

    .typewriter {
        overflow: hidden;
        /* Ensures the content is not revealed until the animation */
        border-right: .15em solid orange;
        /* The typewriter cursor */
        white-space: nowrap;
        /* Keeps the content on a single line */
        margin: 0 auto;
        /* Centers the text */
        letter-spacing: .01em;
        /* Adjust as needed */
        animation: typing 4s steps(30, end) forwards;
    }

    /* The typing effect */
    @keyframes typing {
        from {
            width: 0
        }

        to {
            width: 50%
        }
    }

    .admin-panel {
        display: none;
    }

    .btn {
        width: 9em;
        height: 3em;
        border-radius: 30em;
        font-size: 15px;
        font-family: inherit;
        border: none;
        overflow: hidden;
        position: relative;
        z-index: 1;
        box-shadow: 6px 6px 12px #c5c5c5, -6px -6px -12px #ffffff
    }

    .btn::before {
        content: '';
        width: 0;
        height: 3em;
        border-radius: 30em;
        position: absolute;
        top: 0;
        left: 0;
        background-image: linear-gradient(to right, #0fd505 0%, #f9f047 100%);
        transition: .5 ease;
        display: block;
        z-index: 1
    }

    .btn-hover::before {
        width: 9em;
    }
    </style>
</head>

<body>
    <div class=" home" id="home">
        <button class="toggle-btn menu" onclick="toggleSidebar()">☰ Menu</button>
        <button id="darkModeToggle" class="toggle-btn dark-mode" onclick="toggleDarkMode()">🌙 dark mode</button>

        <div class="sidebar" id="sidebar">
            <h3>....</h3>
            <ul>
                <li><button class="btn"><a href="profile.php">Update Profile</a></button></li>
                <li><button class="btn"><a href="order_confirmation.php">Order confirmation</a></button> </a></li>
                <li><button class="btn"><a href="order_history.php">Order History</a></button></li>
                <li><button class="btn"><a href="cart.php">Cart (<?php echo $totalItemsInCart; ?>)</a></button></li>
                <li><button class="btn"><a href="view_profile.php">View Profile</a></button></li>
                <li><button class="btn"><a href="contact.php">Contact</a></button></li>
                <li><button class="btn"><a href="login.php">Login</a></button></li>
                <li><button class="btn"><a href="logout.php">Logout</a></button></li>
                <li><button class="btn"><a href="request_password_reset.php">Password Reset</a></button></li>
                <li><button class="btn"><a href="privacy_policy.php">Policy</a></button></li>
                <li><button class="btn"><a href="terms_and_conditions.php">Terms & Condition</a></button></li>
            </ul>
        </div>

        <main class="main-content" id="main-content">
            <h1 class="typewriter"><em><u>Welcome to Tariq's Store</u></em></h1>


            <!-- Search functionality -->
            <div class="search-bar" id="search-bar">
                <form method="GET" action="index.php">
                    <input type="text" name="search" placeholder="Search for products..." required>
                    <button type="submit">Search</button>
                </form>
            </div>

            <!-- Display search results if any -->
            <?php if (!empty($noResultsMessage)): ?>
            <h2><?php echo $noResultsMessage; ?></h2>
            <?php elseif (!empty($searchResults)): ?>
            <h2>Search Results</h2>
            <div class="product-list">
                <?php foreach ($searchResults as $product): ?>
                <div class="product">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                        alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <p>Price: Ksh. <?php echo htmlspecialchars($product['price_ksh']); ?></p>
                    <form method="POST" action="">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        <button type="submit" name="add_to_cart" class="btn">Add to Cart</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Display username with greeting based on time of day -->
            <?php if (isset($_SESSION['user_id'])): ?>
            <?php
            // Get the current hour
            $currentHour = date('H'); // 24-hour format (00 to 23)
            
            // Determine the appropriate greeting based on the hour
            if ($currentHour >= 5 && $currentHour < 12) {
                $greeting = "Good morning";
            } elseif ($currentHour >= 12 && $currentHour < 17) {
                $greeting = "Good afternoon";
            } elseif ($currentHour >= 17 && $currentHour < 21) {
                $greeting = "Good evening";
            } else {
                $greeting = "Good night";
            }
            ?>
            <div class="welcome-message">
                <p>Hello, <?php echo htmlspecialchars($greeting . ' ' . $_SESSION['username']); ?>!</p>
            </div>
            <?php else: ?>
            <p>You are not logged in. Please check the menu tab to log in or register to an account for you to make
                orders and purchases with us.</p>
            <?php endif; ?>

            <div class="product-list">
                <?php foreach ($products as $product): ?>
                <div class="product">
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                        alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <p>Price: ksh.<?php echo htmlspecialchars($product['price_ksh']); ?></p>
                    <form method="POST" action="">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        <button type="submit" name="add_to_cart" class="btn" onclick="addToCartNotification()">Add
                            to
                            Cart</button>
                    </form><br>
                    <button class=" btn" type="hidden">
                        <a href="product_detail.php?product_id=<?php echo $product['product_id']; ?>">
                            View
                            Details</a>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <section id="about">
                <h2>About Us</h2>
                <p>We are a leading fashion store offering a wide range of products to meet your style needs.</p>
            </section>
            <footer>
                <p>&copy; 2023 Tariq's Fashion Store</p>
            </footer>
            <div class=" navbar" id="navbar">
                <div class="nav-item">
                    <img src="https://th.bing.com/th/id/R.a6449e2bf30f1df9c22006aa0a9c4e0d?rik=B3nTPtUb9CXZbw&pid=ImgRaw&r=0"
                        alt="Cart" class="nav-icon">
                    <span><a href="cart.php">Cart (<?php echo $totalItemsInCart; ?>)</a></span>
                </div>
                <div class="nav-item" onclick="toggleSearchBar()">
                    <img src="https://th.bing.com/th/id/OIP.2fkWEE_aDk22i7x0Ie02aQHaHa?w=512&h=512&rs=1&pid=ImgDetMain"
                        alt="Search" class="nav-icon">
                    <span>Search</sp>
                </div>
                <div class="nav-item">
                    <img src="https://cdn2.iconfinder.com/data/icons/online-shopping-224/24/icon-dual_tone-home-512.png"
                        alt="Home" class="nav-icon">
                    <span><a href="#home">Home</a> </span>
                </div>
                <div class="nav-item">
                    <img src="https://th.bing.com/th/id/OIP.uJa5IiHq7eF3EnIb5eCv4AHaGH?rs=1&pid=ImgDetMain"
                        alt="Support" class="nav-icon">
                    <span>Support</span>
                </div>
            </div>
    </div>
    </main>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        sidebar.classList.toggle('active');
        mainContent.classList.toggle('shifted');
    }

    function
    toggleDarkMode() {
        const body = document.body;
        const sidebar = document.getElementById('sidebar');
        const footer = document.querySelector('footer');
        const darkModeToggle = document.getElementById('darkModeToggle');
        const navbar = document.getElementById('navbar');

        body.classList.toggle('dark-mode');
        sidebar.classList.toggle('dark-mode');
        footer.classList.toggle('dark-mode');
        navbar.classList.toggle('dark-mode');
        // Save the dark mode preference in localStorage
        if (body.classList.contains('dark-mode')) {
            localStorage.setItem('darkMode', 'enabled');
        } else {
            localStorage.setItem('darkMode', 'disabled');
        }
    }
    // Add the item to the cart
    function addToCart() {
        // Get the product ID from the form data
        var productId = document.getElementById('product_id').value;

        // Send an AJAX request to add the item to the cart
        $.ajax({
            type: 'POST',
            url: 'cart.php', // Assuming you have a separate PHP file to handle the cart addition
            data: {
                product_id: productId
            },
            success: function(response) {
                if (response == 'success') {
                    // Display a success notification
                    showSuccessMessage();
                } else {
                    // Display a failure notification
                    alert('Failed to add item to cart. Please try again.');
                }
            }
        });
    }

    // Function to display success message
    function showSuccessMessage() {
        alert("Item successfully added to cart!");
    }
    // Check localStorage for dark mode preference on page load
    window.onload = function() {
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            document.querySelector('.sidebar').classList.add('dark-mode');
        }

        // Change button text based on dark mode status if
        if (body.classList.contains('dark-mode')) {
            darkModeToggle.textContent = '☀️ Light Mode'; //Change text to Light Mode
        } else {
            darkModeToggle.textContent = '🌙 Dark Mode'; // Change text back to Dark Mode
        }
    }

    function addToCartNotification() {
        // Get the form data
        var formData = new FormData(this.form);

        // Send an AJAX request to add the item to the cart
        $.ajax({
            type: 'POST',
            url: 'add_to_cart.php', // Assuming you have a separate PHP file to handle the cart addition
            data: formData,
            success: function(response) {
                if (response == 'success') {
                    // Display a success notification
                    alert('Item successfully added to cart!');
                } else {
                    // Display a failure notification
                    alert('Failed to add item to cart. Please try again.');
                }
            }
        });
    }


    // Function to show/hide the search bar function
    function toggleSearchBar() {
        const searchBar = document.getElementById('search-bar');
        searchBar.style.display = searchBar.style.display === 'block' ? 'none' : 'block';
    }
    const
        loginForm = document.getElementById('login-form');
    loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        // TO DO: Add authentication logic here
        // For now, just log the username and password to the console
        console.log(`Username: ${username}, Password: ${password}`);
        // TO DO: Check if the credentials are for the admin
        // If they are, show the admin panel and product management
        // Else, redirect to the home page
        if (username === 'admin' && password === 'password') {
            document.getElementById('admin-panel').style.display = 'block';
        } else {
            window.location.href = 'index.php';
        }
    });
    </script>
</body>

</html>