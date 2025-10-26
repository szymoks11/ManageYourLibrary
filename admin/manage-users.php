<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';
requireRole(['admin']);

$success = '';
$error = '';

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isset($_POST['update_role'])) {
        try {
            $stmt = $db->prepare("CALL update_user_role(?, ?)");
            $stmt->execute([$_POST['user_id'], $_POST['role']]);
            $success = "User role updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        try {
            $query = "UPDATE users SET is_active = NOT is_active WHERE user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$_POST['user_id']]);
            $success = "User status updated successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['add_user'])) {
        try {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password_hash, full_name, role, phone, address) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_POST['username'],
                $_POST['email'],
                $password_hash,
                $_POST['full_name'],
                $_POST['role'],
                $_POST['phone'],
                $_POST['address']
            ]);
            $success = "User added successfully!";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get all users with statistics
$users = fetchAll("
    SELECT u.*,
           COUNT(DISTINCT l.loan_id) as total_loans,
           COUNT(DISTINCT CASE WHEN l.status IN ('active', 'overdue') THEN l.loan_id END) as active_loans,
           COALESCE(SUM(CASE WHEN f.is_paid = 0 THEN f.fine_amount ELSE 0 END), 0) as unpaid_fines
    FROM users u
    LEFT JOIN loans l ON u.user_id = l.client_id
    LEFT JOIN fines f ON u.user_id = f.client_id
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");

// Get statistics
$stats = fetchOne("
    SELECT 
        COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
        COUNT(CASE WHEN role = 'worker' THEN 1 END) as workers,
        COUNT(CASE WHEN role = 'client' THEN 1 END) as clients,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_users
    FROM users
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people"></i> Manage Users</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus"></i> Add New User
            </button>
        </div>
        
        <!-- Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?= $stats['admins'] ?></h3>
                        <small>Admins</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?= $stats['workers'] ?></h3>
                        <small>Workers</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?= $stats['clients'] ?></h3>
                        <small>Clients</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <h3 class="text-success"><?= $stats['active_users'] ?></h3>
                        <small>Active Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <h3 class="text-danger"><?= $stats['inactive_users'] ?></h3>
                        <small>Inactive Users</small>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Loans</th>
                                <th>Active Loans</th>
                                <th>Fines</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= $user['user_id'] ?></td>
                                    <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                    <td><small><?= htmlspecialchars($user['email']) ?></small></td>
                                    <td><small><?= htmlspecialchars($user['phone']) ?></small></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $user['role'] == 'admin' ? 'danger' : 
                                            ($user['role'] == 'worker' ? 'info' : 'primary') 
                                        ?>">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= $user['total_loans'] ?></td>
                                    <td><?= $user['active_loans'] ?></td>
                                    <td>
                                        <?php if ($user['unpaid_fines'] > 0): ?>
                                            <span class="badge bg-warning">$<?= number_format($user['unpaid_fines'], 2) ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['is_active'] ? 'success' : 'secondary' ?>">
                                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td><small><?= date('M d, Y', strtotime($user['created_at'])) ?></small></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-info" onclick='editUser(<?= json_encode($user) ?>)'>
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <button type="submit" name="toggle_status" 
                                                        class="btn btn-<?= $user['is_active'] ? 'warning' : 'success' ?>"
                                                        onclick="return confirm('Toggle user status?')">
                                                    <i class="bi bi-<?= $user['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="client">Client</option>
                                <option value="worker">Worker</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="bi bi-save"></i> Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><strong>User:</strong> <span id="edit_user_name"></span></p>
                        <p><strong>Email:</strong> <span id="edit_user_email"></span></p>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Change Role *</label>
                            <select class="form-select" name="role" id="edit_user_role" required>
                                <option value="client">Client</option>
                                <option value="worker">Worker</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Changing a user's role will immediately affect their permissions.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_role" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']]
            });
        });
        
        function editUser(user) {
            $('#edit_user_id').val(user.user_id);
            $('#edit_user_name').text(user.full_name);
            $('#edit_user_email').text(user.email);
            $('#edit_user_role').val(user.role);
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
    </script>
    
</body>
</html>