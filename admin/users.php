<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Get users with their activity counts
$query = "SELECT u.*, 
          COUNT(DISTINCT p.id) as property_count,
          COUNT(DISTINCT al.log_id) as activity_count,
          COUNT(DISTINCT r.id) as review_count
          FROM users u 
          LEFT JOIN properties p ON u.id = p.agent_id
          LEFT JOIN activity_logs al ON u.id = al.user_id
          LEFT JOIN reviews r ON u.id = r.user_id
          GROUP BY u.id
          ORDER BY u.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Admin Panel</title>
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
        .role-badge {
            padding: 0.25em 0.6em;
            border-radius: 12px;
            font-size: 0.85em;
        }
        .status-badge {
            padding: 0.25em 0.6em;
            border-radius: 12px;
            font-size: 0.85em;
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
                        <a class="nav-link active" href="users.php">
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
                <h2>User Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Add New User
                </button>
            </div>

            <!-- User Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <select class="form-select" id="roleFilter">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="agent">Agent</option>
                                <option value="client">Client</option>
                                <option value="manager">Manager</option>
                                <option value="buyer">Buyer</option>
                                <option value="seller">Seller</option>
                                <option value="stakeholder">Stakeholder</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search users...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-secondary w-100" id="resetFilters">Reset Filters</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Properties</th>
                                    <th>Activities</th>
                                    <th>Reviews</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="role-badge bg-<?php 
                                            echo match($row['role']) {
                                                'admin' => 'danger',
                                                'agent' => 'primary',
                                                'manager' => 'info',
                                                'client' => 'success',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($row['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['property_count']; ?></td>
                                    <td><?php echo $row['activity_count']; ?></td>
                                    <td><?php echo $row['review_count']; ?></td>
                                    <td>
                                        <span class="status-badge bg-<?php 
                                            echo match($row['status']) {
                                                'active' => 'success',
                                                'inactive' => 'warning',
                                                'suspended' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <button class="btn btn-sm btn-info" onclick="viewUser(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="editUser(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($row['role'] !== 'admin'): ?>
                                        <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="agent">Agent</option>
                                <option value="client">Client</option>
                                <option value="manager">Manager</option>
                                <option value="buyer">Buyer</option>
                                <option value="seller">Seller</option>
                                <option value="stakeholder">Stakeholder</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveUser()">Save User</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// User Management Functions
function viewUser(id) {
    window.location.href = `view-user.php?id=${id}`;
}

function editUser(id) {
    window.location.href = `edit-user.php?id=${id}`;
}

function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user?')) {
        fetch(`delete-user.php?id=${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting user');
            }
        });
    }
}

function saveUser() {
    const form = document.getElementById('addUserForm');
    const formData = new FormData(form);

    // Validate passwords match
    if (formData.get('password') !== formData.get('confirm_password')) {
        alert('Passwords do not match!');
        return;
    }

    fetch('save-user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error saving user: ' + data.message);
        }
    });
}

// Filter Functions
document.getElementById('roleFilter').addEventListener('change', applyFilters);
document.getElementById('statusFilter').addEventListener('change', applyFilters);
document.getElementById('searchInput').addEventListener('input', applyFilters);
document.getElementById('resetFilters').addEventListener('click', resetFilters);

function applyFilters() {
    const role = document.getElementById('roleFilter').value.toLowerCase();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const search = document.getElementById('searchInput').value.toLowerCase();

    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const rowRole = row.children[4].textContent.toLowerCase();
        const rowStatus = row.children[8].textContent.toLowerCase();
        const rowText = row.textContent.toLowerCase();

        const roleMatch = !role || rowRole.includes(role);
        const statusMatch = !status || rowStatus.includes(status);
        const searchMatch = !search || rowText.includes(search);

        row.style.display = roleMatch && statusMatch && searchMatch ? '' : 'none';
    });
}

function resetFilters() {
    document.getElementById('roleFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('searchInput').value = '';
    applyFilters();
}
</script>

</body>
</html> 