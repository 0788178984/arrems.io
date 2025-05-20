<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get current admin info
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Get system settings
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
$stmt->execute();
$result = $stmt->get_result();
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .nav-link {
            color: rgba(255,255,255,.8);
        }
        .nav-link:hover {
            color: white;
        }
        .nav-link.active {
            background: rgba(255,255,255,.1);
        }
        .settings-card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 px-0 sidebar">
            <div class="p-3">
                <h5>Admin Panel</h5>
                <hr>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="properties.php">
                            <i class="fas fa-building me-2"></i>Properties
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="virtual-tours.php">
                            <i class="fas fa-vr-cardboard me-2"></i>Virtual Tours
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="fas fa-chart-bar me-2"></i>Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Settings</h2>
            </div>

            <!-- Settings Sections -->
            <div class="row">
                <!-- Profile Settings -->
                <div class="col-md-6 mb-4">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>">
                                </div>
                                <button type="button" class="btn btn-primary" onclick="updateProfile()">Update Profile</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Password Settings -->
                <div class="col-md-6 mb-4">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">Change Password</h5>
                        </div>
                        <div class="card-body">
                            <form id="passwordForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="updatePassword()">Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div class="col-md-12">
                    <div class="card settings-card">
                        <div class="card-header">
                            <h5 class="mb-0">System Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="systemSettingsForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Site Title</label>
                                        <input type="text" class="form-control" name="site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? 'ARREMS'); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Contact Email</label>
                                        <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? 'contact@arrems.com'); ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Maintenance Mode</label>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="maintenance_mode" id="maintenance_mode" <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary" onclick="updateSystemSettings()">Save Settings</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateProfile() {
    const form = document.getElementById('profileForm');
    const formData = new FormData(form);

    fetch('update-profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Profile updated successfully!');
            location.reload(); // Reload to update displayed information
        } else {
            alert('Error updating profile: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function updatePassword() {
    const form = document.getElementById('passwordForm');
    const formData = new FormData(form);

    // Validate passwords match
    if (formData.get('new_password') !== formData.get('confirm_password')) {
        alert('New passwords do not match!');
        return;
    }

    fetch('update-password.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password updated successfully!');
            form.reset();
        } else {
            alert('Error updating password: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function updateSystemSettings() {
    const form = document.getElementById('systemSettingsForm');
    const formData = new FormData(form);

    fetch('update-system-settings.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('System settings updated successfully!');
            location.reload(); // Reload to update displayed information
        } else {
            alert('Error updating system settings: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>

</body>
</html> 