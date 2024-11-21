<?php
// Replace 'yourPlainTextPassword' with the actual password you want to hash
$password = 'ochiengphidel1'; // Change this to your desired password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Hashed Password: " . $hashedPassword;
?>