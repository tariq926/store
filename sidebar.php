<?php
// Start the session if it hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'db_config.php'; // Include your database configuration file

// Initialize the cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    if (isset($_COOKIE['cart'])) {
        $_SESSION['cart'] = json_decode($_COOKIE['cart'], true);
    } else {
        $_SESSION['cart'] = []; // Initialize an empty cart
    }
}
// Calculate the total items in the cart (Assuming you have the function defined)
$totalItemsInCart = countItemsInCart($_SESSION['cart']);

?>
<div class="sidebar" id="sidebar">
    <h3>....</h3>
    <ul>
        <li><a href="#sneakers">Sneakers</a></li>
        <li><a href="#mens-shoes">Men's Shoes</a></li>
        <li><a href="#womens-shoes">Women's Shoes</a></li>
        <li><a href="#tshirts">T-Shirts</a></li>
        <li><a href="#shirts">Shirts</a></li>
        <li><a href="#tops">Tops</a></li>
        <li><a href="index.php">Home</a></li>
        <li><a href="#products">Products</a></li>
        <li><a href="cart.php">Cart (<?php echo $totalItemsInCart; ?>)</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#contact">Contact</a></li>
        <li><a href="login.php">Login</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<button class="toggle-btn" onclick="toggleSidebar()">☰</button>
<button id="darkModeToggle" class="toggle-btn right" onclick="toggleDarkMode()">🌙 Dark Mode</button>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    transition: background-color 0.3s, color 0.3s;
    /* smooth transition */
    cursor: default;
}

/* Sidebar styles */
body.dark-mode {
    background-color: #121212;
    /* Dark background */
    color: #fff;
    /* White text */
}

.sidebar {
    height: calc(100%);
    width: 250px;
    position: fixed;
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

.toggle-btn.right {
    right: 10px;
    /* Position dark mode toggle on the right */
    left: auto;
    /* Remove left positioning */
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content'); // Get the main content element
    sidebar.classList.toggle('active'); // Toggle the active class to show/hide the sidebar
    if (sidebar.classList.contains('active')) {
        mainContent.style.marginLeft = '250px'; // Adjust main content when sidebar is active
    } else {
        mainContent.style.marginLeft = '0'; // Reset margin when sidebar is hidden
    }
}

function toggleDarkMode() {
    const body = document.body;
    body.classList.toggle('dark-mode'); // Toggle dark mode class on body
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('dark-mode'); // Toggle dark mode class on sidebar

    // Save the dark mode preference in localStorage
    if (body.classList.contains('dark-mode')) {
        localStorage.setItem('darkMode', 'enabled');
    } else {
        localStorage.setItem('darkMode', 'disabled');
    }
}

// Check localStorage for dark mode preference on page load
window.onload = function() {
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        document.querySelector('.sidebar').classList.add('dark-mode');
    }
};
</script>