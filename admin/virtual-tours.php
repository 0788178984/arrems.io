<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get virtual tours with related information
$query = "SELECT vt.*, 
          p.title as property_title,
          p.type as property_type,
          u.first_name as agent_first_name,
          u.last_name as agent_last_name,
          COUNT(DISTINCT tm.media_id) as media_count,
          pa.views_count
          FROM virtual_tours vt
          LEFT JOIN properties p ON vt.property_id = p.id
          LEFT JOIN users u ON p.agent_id = u.id
          LEFT JOIN tour_media tm ON vt.tour_id = tm.tour_id
          LEFT JOIN property_analytics pa ON p.id = pa.property_id
          GROUP BY vt.tour_id
          ORDER BY vt.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Tours Management - Admin Panel</title>
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
        .tour-preview {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }
        .action-buttons .btn { margin: 0 2px; }
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
                        <a class="nav-link active" href="virtual-tours.php">
                            <i class="fas fa-vr-cardboard me-2"></i>Virtual Tours
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="analytics.php">
                            <i class="fas fa-chart-bar me-2"></i>Analytics
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
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
                <h2>Virtual Tours Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTourModal">
                    <i class="fas fa-plus me-2"></i>Create New Tour
                </button>
            </div>

            <!-- Tour Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <select class="form-select" id="propertyFilter">
                                <option value="">All Properties</option>
                                <option value="house">Houses</option>
                                <option value="apartment">Apartments</option>
                                <option value="commercial">Commercial</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="agentFilter">
                                <option value="">All Agents</option>
                                <?php
                                $agents = $conn->query("SELECT id, first_name, last_name FROM users WHERE role = 'agent'");
                                while($agent = $agents->fetch_assoc()) {
                                    echo "<option value='{$agent['id']}'>{$agent['first_name']} {$agent['last_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search virtual tours...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-secondary w-100" id="resetFilters">Reset Filters</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Virtual Tours Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Preview</th>
                                    <th>Property</th>
                                    <th>Type</th>
                                    <th>Agent</th>
                                    <th>Media Count</th>
                                    <th>Views</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo $row['thumbnail_url'] ?? 'assets/img/default-tour.jpg'; ?>" 
                                             alt="Tour Preview" 
                                             class="tour-preview">
                                    </td>
                                    <td><?php echo htmlspecialchars($row['property_title']); ?></td>
                                    <td><?php echo ucfirst($row['property_type']); ?></td>
                                    <td>
                                        <?php 
                                        echo $row['agent_first_name'] ? 
                                            htmlspecialchars($row['agent_first_name'] . ' ' . $row['agent_last_name']) : 
                                            'No Agent';
                                        ?>
                                    </td>
                                    <td><?php echo $row['media_count']; ?></td>
                                    <td><?php echo $row['views_count'] ?? 0; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewTour(<?php echo $row['tour_id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="editTour(<?php echo $row['tour_id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="publishTour(<?php echo $row['tour_id']; ?>)">
                                            <i class="fas fa-globe"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteTour(<?php echo $row['tour_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Tour Modal -->
<div class="modal fade" id="addTourModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Virtual Tour</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addTourForm">
                    <div class="mb-3">
                        <label class="form-label">Select Property</label>
                        <select class="form-select" name="property_id" required>
                            <option value="">Choose a property...</option>
                            <?php
                            $properties = $conn->query("SELECT id, title FROM properties WHERE status = 'available'");
                            while($property = $properties->fetch_assoc()) {
                                echo "<option value='{$property['id']}'>{$property['title']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Tour Media</label>
                        <input type="file" class="form-control" name="tour_media[]" multiple accept="image/*,video/*">
                        <small class="text-muted">You can select multiple files. Supported formats: JPG, PNG, MP4</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tour Settings</label>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="autoplay" id="autoplay">
                            <label class="form-check-label" for="autoplay">Enable Autoplay</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" name="show_controls" id="show_controls" checked>
                            <label class="form-check-label" for="show_controls">Show Navigation Controls</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="enable_vr" id="enable_vr">
                            <label class="form-check-label" for="enable_vr">Enable VR Mode</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTour()">Create Tour</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tour Management Functions
function viewTour(id) {
    window.open(`view-tour.php?id=${id}`, '_blank');
}

function editTour(id) {
    window.location.href = `edit-tour.php?id=${id}`;
}

function publishTour(id) {
    if (confirm('Are you sure you want to publish this tour?')) {
        fetch(`publish-tour.php?id=${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Tour published successfully!');
                location.reload();
            } else {
                alert('Error publishing tour: ' + data.message);
            }
        });
    }
}

function deleteTour(id) {
    if (confirm('Are you sure you want to delete this tour?')) {
        fetch(`delete-tour.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting tour');
            }
        });
    }
}

function saveTour() {
    const form = document.getElementById('addTourForm');
    const formData = new FormData(form);

    fetch('save-tour.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error creating tour: ' + data.message);
        }
    });
}

// Filter Functions
document.getElementById('propertyFilter').addEventListener('change', applyFilters);
document.getElementById('agentFilter').addEventListener('change', applyFilters);
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('resetFilters').addEventListener('click', resetFilters);

function applyFilters() {
    const propertyType = document.getElementById('propertyFilter').value.toLowerCase();
    const agentId = document.getElementById('agentFilter').value;
    const search = document.getElementById('searchInput').value.toLowerCase();

    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const rowType = row.children[2].textContent.toLowerCase();
        const rowAgent = row.children[3].textContent;
        const rowText = row.textContent.toLowerCase();

        const typeMatch = !propertyType || rowType === propertyType;
        const agentMatch = !agentId || rowAgent.includes(agentId);
        const searchMatch = !search || rowText.includes(search);

        row.style.display = typeMatch && agentMatch && searchMatch ? '' : 'none';
    });
}

function resetFilters() {
    document.getElementById('propertyFilter').value = '';
    document.getElementById('agentFilter').value = '';
    document.getElementById('searchInput').value = '';
    applyFilters();
}
</script>

</body>
</html> 