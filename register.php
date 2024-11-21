<?php
session_start();
$host = 'localhost';
$username = 'root';
$password = 'Tariq926';
$online_store_db = 'online_store';

// Create connection to online_store database
$conn = new mysqli($host, $username, $password, $online_store_db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Capture all form inputs
    $full_name = trim($_POST['full_name']); // New full name field
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    
    // Validation Errors Array
    $errors = [];

    // Full Name Validation
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    } elseif (strlen($full_name) < 3) {
        $errors[] = "Full name must be at least 3 characters";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $full_name)) {
        $errors[] = "Full name can only contain letters and spaces";
    }

    // Username Validation
    if (empty($user)) {
        $errors[] = "Username is required";
    } elseif (strlen($user) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }

    // Email Validation
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Password Validation
    if (empty($pass)) {
        $errors[] = "Password is required";
    } elseif (strlen($pass) < 8) {
        $errors[] = "Password must be at least 8 characters";
    } elseif (!preg_match("/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/", $pass)) {
        $errors[] = "Password must include letters, numbers, and special characters";
    }

    // Confirm Password Validation
    if ($pass !== $confirm_pass) {
        $errors[] = "Passwords do not match";
    }

    // If there are validation errors, store them in session and redirect
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        header("Location: register.php");
        exit();
    }

    // Check if the username or email already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $user, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['registration_errors'] = ["Username or email already exists"];
        header("Location: register.php");
        exit();
    }

    try {
        // Hash the password before storing it
        $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

        // Insert new user into the database with full name
        $insertQuery = "INSERT INTO users (full_name, username, password, email, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmtInsert = $conn->prepare($insertQuery);
        $stmtInsert->bind_param("ssss", $full_name, $user, $hashed_password, $email);

        if ($stmtInsert->execute()) {
            // Get the newly inserted user ID
            $user_id = $stmtInsert->insert_id;

            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $user;
            $_SESSION['full_name'] = $full_name;

            $_SESSION['message'] = "Registration successful! Welcome, $full_name!";
            header("Location: index.php"); // Redirect to dashboard or home page
            exit();
        } else {
            throw new Exception("Registration failed: " . $stmtInsert->error);
        }
    } catch (Exception $e) {
        $_SESSION['registration_errors'] = [$e->getMessage()];
        header("Location: register.php");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f4f4;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }

    .container {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(15px);
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        width: 300px;
        text-align: center;
    }

    h2 {
        margin-bottom: 20px;
        cursor: default;
    }

    .form-group {
        margin-bottom: 15px;
        text-align: left;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
    }

    .form-group input {
        width: 90%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    button {
        background-color: teal;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        width: 100%;
    }

    button:hover {
        background-color: darkcyan;
    }

    p {
        margin-top: 20px;
    }

    a {
        color: teal;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }
    .password-container {
        position: relative;
    }
    
    .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        user-select: none;
    }

    /* Success Popup Styles */
    #successPopup {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: #4CAF50;
        color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
        text-align: center;
    }

    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        display: none;
    }
    </style>
</head>

<body>
    <div class="container">
        <h2>Register</h2>
        
        <!-- Error Messages Display -->
        <?php
        if (isset($_SESSION['registration_errors'])) {
            echo "<div class='error-messages'>";
            foreach ($_SESSION['registration_errors'] as $error) {
                echo "<p style='color: red;'>" . htmlspecialchars($error) . "</p>";
            }
            echo "</div>";
            unset($_SESSION['registration_errors']);
        }
        ?>

        <form method="post" action="register.php" id="registrationForm">
            <!-- Full Name Input -->
            <div class="form-group">
                <label for="full_name">Full Name:</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                       required>
            </div>

            <!-- Username Input -->
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       required>
            </div>

            <!-- Email Input -->
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       required>
            </div>

            <!-- Password Input with Visibility Toggle -->
            <div class="form-group password-container">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <span class="toggle-password" onclick="togglePasswordVisibility('password')">👁️</span>
            </div>

            <!-- Confirm Password Input with Visibility Toggle -->
            <div class="form-group password-container">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">👁️</span>
            </div>

            <button type="submit" name="register">Register</button>
        </form>

        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>

    <!-- Success Popup -->
    <div id="successOverlay" class="overlay"></div>
    <div id="successPopup">
        <h2>Account Created Successfully!</h2>
        <p>Welcome to our platform!</p>
        <button onclick="closeSuccessPopup()">Continue</button>
    </div>

    <script>
    // Password Visibility Toggle
    function togglePasswordVisibility(inputId) {
        const passwordInput = document.getElementById(inputId);
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
        } else {
            passwordInput.type = 'password';
        }
    }

    // Success Popup Handling
    <?php if(isset($_SESSION['account_created']) && $_SESSION['account_created']): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showSuccessPopup();
        });
    <?php 
        unset($_SESSION['account_created']); 
    endif; ?>

    function showSuccessPopup() {
        document.getElementById('successOverlay').style.display = 'block';
        document.getElementById('successPopup').style.display = 'block';
    }

    function closeSuccessPopup() {
        document.getElementById('successOverlay').style.display = 'none';
        document.getElementById('successPopup').style.display = 'none';
        window.location.href = 'index.php'; // Redirect to dashboard
    }

    // Optional: Form Validation
    document.getElementById('registrationForm').addEventListener('submit', function(event) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        // Password Strength Check
        const passwordPattern = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/;
        
        if (!passwordPattern.test(password.value)) {
            alert('Password must be at least 8 characters long and contain letters, numbers, and special characters');
            event.preventDefault();
            return;
        }

        // Password Match Check
        if (password.value !== confirmPassword.value) {
            alert('Passwords do not match');
            event.preventDefault();
        }
    });
    </script>
</body>
</html>