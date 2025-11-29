<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config.php';

// Handle AJAX requests for user operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $pdo = getDBConnection();
        $response = ['success' => false, 'message' => ''];

        switch ($_POST['action']) {
            case 'delete':
                $userId = intval($_POST['user_id']);

                // Prevent admin from deleting themselves
                if ($userId === $_SESSION['user_id']) {
                    $response['message'] = 'You cannot delete your own account';
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);

                $response['success'] = true;
                $response['message'] = 'User deleted successfully';
                break;

            case 'update_role':
                $userId = intval($_POST['user_id']);
                $newRole = $_POST['role'];

                // Validate role
                if (!in_array($newRole, ['user', 'admin'])) {
                    $response['message'] = 'Invalid role';
                    break;
                }

                // Prevent admin from demoting themselves
                if ($userId === $_SESSION['user_id']) {
                    $response['message'] = 'You cannot change your own role';
                    break;
                }

                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$newRole, $userId]);

                $response['success'] = true;
                $response['message'] = 'User role updated successfully';
                break;

            case 'add':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];

                // Validation
                if (empty($username) || empty($email) || empty($password)) {
                    $response['message'] = 'All fields are required';
                    break;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response['message'] = 'Invalid email format';
                    break;
                }

                if (!in_array($role, ['user', 'admin'])) {
                    $response['message'] = 'Invalid role';
                    break;
                }

                // Check if username exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $response['message'] = 'Username already exists';
                    break;
                }

                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $response['message'] = 'Email already exists';
                    break;
                }

                // Hash password and insert user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $role]);

                $response['success'] = true;
                $response['message'] = 'User added successfully';
                break;

            default:
                $response['message'] = 'Invalid action';
        }

        echo json_encode($response);
        exit();

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

// Fetch all users for display
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error fetching users: " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include "inc/head.inc.php"; ?>
<body>
    <header class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="dashboard-title">Manage Users</h1>
                    <p class="dashboard-subtitle">User Account Management</p>
                </div>
                <?php include "inc/navbar.inc.php"; ?>
            </div>
        </div>
    </header>

    <main class="container dashboard-container">
        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Add User Section -->
        <section class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <h2 class="chart-title mb-3">Add New User</h2>
                    <form id="addUserForm" class="row g-3">
                        <div class="col-md-3">
                            <label for="newUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="newUsername" name="username" required>
                        </div>
                        <div class="col-md-3">
                            <label for="newEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="newEmail" name="email" required>
                        </div>
                        <div class="col-md-2">
                            <label for="newPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="newPassword" name="password" required>
                        </div>
                        <div class="col-md-2">
                            <label for="newRole" class="form-label">Role</label>
                            <select class="form-select" id="newRole" name="role" required>
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Users Table -->
        <section class="section">
            <div class="card chart-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="chart-title mb-0">All Users</h2>
                        <div>
                            <input type="text" id="searchUsers" class="form-control" placeholder="Search users...">
                        </div>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover" id="usersTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr data-user-id="<?php echo $user['id']; ?>">
                                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <select class="form-select form-select-sm role-select"
                                                        data-user-id="<?php echo $user['id']; ?>"
                                                        <?php echo $user['id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                                                    <button class="btn btn-sm btn-danger delete-user"
                                                            data-user-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                        Delete
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Current User</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <?php include "inc/footer.inc.php"; ?>

    <script>
        // Show alert message
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Add new user
        document.getElementById('addUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add');

            try {
                const response = await fetch('manage_users.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(result.message, 'success');
                    this.reset();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message, 'danger');
                }
            } catch (error) {
                showAlert('Error adding user: ' + error.message, 'danger');
            }
        });

        // Update user role
        document.querySelectorAll('.role-select').forEach(select => {
            select.addEventListener('change', async function() {
                const userId = this.dataset.userId;
                const newRole = this.value;

                const formData = new FormData();
                formData.append('action', 'update_role');
                formData.append('user_id', userId);
                formData.append('role', newRole);

                try {
                    const response = await fetch('manage_users.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        showAlert(result.message, 'success');
                    } else {
                        showAlert(result.message, 'danger');
                        location.reload();
                    }
                } catch (error) {
                    showAlert('Error updating role: ' + error.message, 'danger');
                    location.reload();
                }
            });
        });

        // Delete user
        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', async function() {
                const userId = this.dataset.userId;
                const username = this.dataset.username;

                if (!confirm(`Are you sure you want to delete user "${username}"?`)) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('user_id', userId);

                try {
                    const response = await fetch('manage_users.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        showAlert(result.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(result.message, 'danger');
                    }
                } catch (error) {
                    showAlert('Error deleting user: ' + error.message, 'danger');
                }
            });
        });

        // Search users
        document.getElementById('searchUsers').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>
