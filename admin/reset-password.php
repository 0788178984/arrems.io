<?php
// Database connection
$db_host = 'localhost';
$db_name = 'arrems_realestate_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set the new password
    $newPassword = 'admin123'; // You can change this to your desired password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update admin user password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = 'admin@example.com' AND role = 'admin'");
    $stmt->execute([$hashedPassword]);

    if ($stmt->rowCount() > 0) {
        echo "Password updated successfully!<br>";
        echo "Email: admin@example.com<br>";
        echo "Password: " . $newPassword;
    } else {
        echo "No admin user found to update.";
    }

} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?> 