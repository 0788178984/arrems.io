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

    // Get analytics data
    $stats = [
        'total_properties' => $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn(),
        'active_properties' => $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available'")->fetchColumn(),
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_agents' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'agent'")->fetchColumn(),
        'total_tours' => $pdo->query("SELECT COUNT(*) FROM property_media WHERE media_type = '3d_model'")->fetchColumn(),
        'total_views' => $pdo->query("SELECT COUNT(*) FROM property_analytics")->fetchColumn() ?? 0
    ];

    // Get recent activities for the activity chart
    $activities = $pdo->query("SELECT DATE(created_at) as date, COUNT(*) as count 
                              FROM activity_logs 
                              GROUP BY DATE(created_at) 
                              ORDER BY date DESC 
                              LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);

    // Get property types distribution
    $property_types = $pdo->query("SELECT type as property_type, COUNT(*) as count 
                                  FROM properties 
                                  GROUP BY type")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - ARREMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="nav-link" href="dashboard.php">
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
                            <a class="nav-link active" href="analytics.php">
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
                    <h1 class="h2">Analytics Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="fas fa-calendar me-1"></i> This Month
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Properties</h5>
                                <h2><?php echo $stats['total_properties']; ?></h2>
                                <p class="mb-0"><?php echo $stats['active_properties']; ?> active listings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Users & Agents</h5>
                                <h2><?php echo $stats['total_users']; ?></h2>
                                <p class="mb-0"><?php echo $stats['total_agents']; ?> active agents</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card card bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Virtual Tours</h5>
                                <h2><?php echo $stats['total_tours']; ?></h2>
                                <p class="mb-0"><?php echo number_format($stats['total_views']); ?> total views</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card card bg-warning text-white">
                            <div class="card-body">
                                <h5 class="card-title">Engagement Rate</h5>
                                <h2><?php echo $stats['total_tours'] > 0 ? round(($stats['total_views'] / $stats['total_tours']), 1) : 0; ?></h2>
                                <p class="mb-0">Views per tour</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Activity Overview</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Property Types</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="propertyTypesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activity Chart
        const activityData = <?php echo json_encode(array_reverse($activities)); ?>;
        new Chart(document.getElementById('activityChart'), {
            type: 'line',
            data: {
                labels: activityData.map(item => item.date),
                datasets: [{
                    label: 'Daily Activities',
                    data: activityData.map(item => item.count),
                    borderColor: '#4e73df',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Property Types Chart
        const propertyTypesData = <?php echo json_encode($property_types); ?>;
        new Chart(document.getElementById('propertyTypesChart'), {
            type: 'doughnut',
            data: {
                labels: propertyTypesData.map(item => item.property_type),
                datasets: [{
                    data: propertyTypesData.map(item => item.count),
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e',
                        '#e74a3b'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 