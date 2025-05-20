<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
$db_host = 'localhost';
$db_name = 'arrems_realestate_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get dashboard statistics
    $stats = [
        'properties' => $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn(),
        'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'agents' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'agent'")->fetchColumn(),
        'tours' => $pdo->query("SELECT COUNT(*) FROM property_media WHERE media_type = '3d_model'")->fetchColumn()
    ];

    // Get recent activities
    $activities = $pdo->query("SELECT al.*, u.first_name, u.last_name 
                              FROM activity_logs al 
                              JOIN users u ON al.user_id = u.id 
                              ORDER BY al.created_at DESC 
                              LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARREMS Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
        }
        .sidebar .nav-link:hover {
            color: rgba(255,255,255,1);
        }
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,.1);
        }
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="mb-4 px-3">
                        <h5>ARREMS Admin</h5>
                        <p class="text-muted small">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-home me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="properties.php">
                                <i class="fas fa-building me-2"></i> Properties
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="virtual-tours.php">
                                <i class="fas fa-vr-cardboard me-2"></i> Virtual Tours
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="fas fa-chart-bar me-2"></i> Analytics
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-primary me-2">
                            <i class="fas fa-download"></i> Export Report
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Properties</h5>
                                <h2><?php echo $stats['properties']; ?></h2>
                                <p class="mb-0"><i class="fas fa-building"></i> Total listings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Users</h5>
                                <h2><?php echo $stats['users']; ?></h2>
                                <p class="mb-0"><i class="fas fa-users"></i> Registered users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Agents</h5>
                                <h2><?php echo $stats['agents']; ?></h2>
                                <p class="mb-0"><i class="fas fa-user-tie"></i> Active agents</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Virtual Tours</h5>
                                <h2><?php echo $stats['tours']; ?></h2>
                                <p class="mb-0"><i class="fas fa-vr-cardboard"></i> Active tours</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['action_type']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 