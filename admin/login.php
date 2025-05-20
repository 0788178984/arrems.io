<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Database connection
$db_host = 'localhost';
$db_name = 'arrems_realestate_db';
$db_user = 'root';
$db_pass = '';

// Initialize error message
$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Get user with admin role
        $stmt = $pdo->prepare("SELECT id, email, password, first_name, last_name FROM users WHERE email = ? AND role = 'admin' AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['admin_email'] = $user['email'];

            // Log activity
            $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action_type, description) VALUES (?, 'login', 'Admin login successful')");
            $logStmt->execute([$user['id']]);

            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARREMS Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 150px;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <h2>ARREMS Admin</h2>
                <p class="text-muted">Augmented Reality Real Estate Management System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 