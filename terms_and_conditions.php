<?php
// Start the session
session_start();// Optional: Include your header file here
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions</title>
    <link rel="stylesheet" href="styles.css"> <!-- Include your CSS file -->
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
        <h1>Terms and Conditions</h1>
    </header>
    <main>
        <h2>1. Introduction</h2>
        <p>Welcome to our website! By accessing or using our services, you agree to comply with and be bound by the
            following terms and conditions.</p>

        <h2>2. Acceptance of Terms</h2>
        <p>By using our website, you confirm that you accept these terms and conditions and that you agree to comply
            with them. If you do not agree to these terms, you must not use our services.</p>

        <h2>3. Changes to Terms</h2>
        <p>We reserve the right to modify these terms at any time. Any changes will be effective immediately upon
            posting on this page. It is your responsibility to review these terms periodically.</p>

        <h2>4. User Responsibilities</h2>
        <p>You agree to use our services only for lawful purposes and in a manner that does not infringe the rights of
            others.</p>

        <h2>5. Limitation of Liability</h2>
        <p>We will not be liable for any direct, indirect, incidental, or consequential damages arising from your use of
            our services.</p>

        <h2>6. Governing Law</h2>
        <p>These terms and conditions are governed by the laws of [Your Country/State].</p>

        <h2>7. Contact Us</h2>
        <p>If you have any questions about these terms, please contact us at [Your Contact Information].</p>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Your Company Name. All rights reserved.</p>
    </footer>
</body>

</html>