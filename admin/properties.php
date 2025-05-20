<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get properties with related information
$query = "SELECT p.*, 
          u.first_name AS agent_first_name, 
          u.last_name AS agent_last_name,
          COUNT(DISTINCT pm.id) as media_count,
          COUNT(DISTINCT r.id) as review_count,
          pa.views_count
          FROM properties p
          LEFT JOIN users u ON p.agent_id = u.id
          LEFT JOIN property_media pm ON p.id = pm.property_id
          LEFT JOIN reviews r ON p.id = r.property_id
          LEFT JOIN property_analytics pa ON p.id = pa.property_id
          GROUP BY p.id
          ORDER BY p.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Management - Admin Panel</title>
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
        .status-badge {
            padding: 0.25em 0.6em;
            border-radius: 12px;
            font-size: 0.85em;
        }
        .status-available { background-color: #28a745; color: white; }
        .status-sold { background-color: #dc3545; color: white; }
        .status-pending { background-color: #ffc107; color: black; }
        .status-rented { background-color: #17a2b8; color: white; }
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
                        <a class="nav-link active" href="properties.php">
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
                <h2>Property Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPropertyModal">
                    <i class="fas fa-plus me-2"></i>Add New Property
                </button>
            </div>

            <!-- Property Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <select class="form-select" id="typeFilter">
                                <option value="">All Types</option>
                                <option value="house">House</option>
                                <option value="apartment">Apartment</option>
                                <option value="commercial">Commercial</option>
                                <option value="land">Land</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="available">Available</option>
                                <option value="sold">Sold</option>
                                <option value="pending">Pending</option>
                                <option value="rented">Rented</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search properties...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-secondary w-100" id="resetFilters">Reset Filters</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Properties Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Agent</th>
                                    <th>Media</th>
                                    <th>Views</th>
                                    <th>Reviews</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td><?php echo ucfirst($row['type']); ?></td>
                                    <td>$<?php echo number_format($row['price']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $row['status']; ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        echo $row['agent_first_name'] ? 
                                            htmlspecialchars($row['agent_first_name'] . ' ' . $row['agent_last_name']) : 
                                            'No Agent';
                                        ?>
                                    </td>
                                    <td><?php echo $row['media_count']; ?></td>
                                    <td><?php echo $row['views_count'] ?? 0; ?></td>
                                    <td><?php echo $row['review_count']; ?></td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewProperty(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="editProperty(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteProperty(<?php echo $row['id']; ?>)">
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

<!-- Add Property Modal -->
<div class="modal fade" id="addPropertyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Property</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addPropertyForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type" required>
                                <option value="house">House</option>
                                <option value="apartment">Apartment</option>
                                <option value="commercial">Commercial</option>
                                <option value="land">Land</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Price</label>
                            <input type="number" class="form-control" name="price" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="available">Available</option>
                                <option value="sold">Sold</option>
                                <option value="pending">Pending</option>
                                <option value="rented">Rented</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Bedrooms</label>
                            <input type="number" class="form-control" name="bedrooms">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bathrooms</label>
                            <input type="number" class="form-control" name="bathrooms">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Area Size (sq ft)</label>
                            <input type="number" class="form-control" name="area_size">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ZIP Code</label>
                            <input type="text" class="form-control" name="zip_code">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Latitude</label>
                            <input type="text" class="form-control" name="latitude">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Longitude</label>
                            <input type="text" class="form-control" name="longitude">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveProperty()">Save Property</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Property Management Functions
function viewProperty(id) {
    window.location.href = `view-property.php?id=${id}`;
}

function editProperty(id) {
    window.location.href = `edit-property.php?id=${id}`;
}

function deleteProperty(id) {
    if (confirm('Are you sure you want to delete this property?')) {
        fetch(`delete-property.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting property');
            }
        });
    }
}

function saveProperty() {
    const form = document.getElementById('addPropertyForm');
    const formData = new FormData(form);

    fetch('save-property.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error saving property');
        }
    });
}

// Filter Functions
document.getElementById('typeFilter').addEventListener('change', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('resetFilters').addEventListener('click', resetFilters);

function applyFilters() {
    const type = document.getElementById('typeFilter').value.toLowerCase();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const search = document.getElementById('searchInput').value.toLowerCase();

    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const rowType = row.children[2].textContent.toLowerCase();
        const rowStatus = row.children[4].textContent.toLowerCase();
        const rowText = row.textContent.toLowerCase();

        const typeMatch = !type || rowType === type;
        const statusMatch = !status || rowStatus.includes(status);
        const searchMatch = !search || rowText.includes(search);

        row.style.display = typeMatch && statusMatch && searchMatch ? '' : 'none';
    });
}

function resetFilters() {
    document.getElementById('typeFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('searchInput').value = '';
    applyFilters();
}
</script>

</body>
</html> 