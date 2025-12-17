<?php
/**
 * Reset Rider Password - Admin Tool
 * Allows admin to reset any rider's password
 */

require_once 'includes/bootstrap.php';

// Require admin access
$auth->requireLogin();
$currentUser = $auth->getUser();

if (!in_array($currentUser['role'], ['super_admin', 'admin', 'developer'])) {
    die("❌ Access denied. Admin role required.");
}

$db = Database::getInstance();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($username) || empty($newPassword)) {
        $error = "Username and password are required";
    } else {
        try {
            // Check if user exists
            $user = $db->fetchOne("SELECT id, role FROM users WHERE username = ?", [$username]);
            
            if (!$user) {
                $error = "User '{$username}' not found";
            } else {
                // Hash the new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password
                $db->query("UPDATE users SET password = ? WHERE username = ?", [$hashedPassword, $username]);
                
                $success = "✅ Password updated successfully for '{$username}'";
                $showCredentials = true;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get all riders
$riders = $db->fetchAll("
    SELECT 
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.is_active,
        r.id as rider_id,
        r.phone,
        r.vehicle_type,
        r.vehicle_number,
        r.status
    FROM users u
    LEFT JOIN riders r ON u.id = r.user_id
    WHERE u.role = 'rider'
    ORDER BY u.full_name
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Rider Password - WAPOS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="bi bi-key me-2"></i>Reset Rider Password
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            </div>
                            <?php if (isset($showCredentials)): ?>
                                <div class="alert alert-info">
                                    <h6>Share these credentials with the rider:</h6>
                                    <p class="mb-1"><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
                                    <p class="mb-1"><strong>Password:</strong> <?= htmlspecialchars($newPassword) ?></p>
                                    <p class="mb-0"><strong>Login URL:</strong> <a href="rider-login.php" target="_blank"><?= APP_URL ?>/rider-login.php</a></p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Select Rider</label>
                                <select class="form-select" id="username" name="username" required>
                                    <option value="">-- Select Rider --</option>
                                    <?php foreach ($riders as $rider): ?>
                                        <option value="<?= htmlspecialchars($rider['username']) ?>" 
                                                <?= (isset($_POST['username']) && $_POST['username'] === $rider['username']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($rider['full_name']) ?> 
                                            (<?= htmlspecialchars($rider['username']) ?>)
                                            <?php if (!$rider['is_active']): ?>
                                                - <span class="text-danger">Inactive</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="text" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Enter new password" required minlength="6">
                                <small class="text-muted">Minimum 6 characters. Use a secure password.</small>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-key me-1"></i>Reset Password
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="generatePassword()">
                                    <i class="bi bi-shuffle me-1"></i>Generate Random
                                </button>
                                <a href="delivery.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-1"></i>Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Riders List -->
                <div class="card shadow mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>All Riders
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($riders)): ?>
                            <div class="alert alert-info m-3">
                                <i class="bi bi-info-circle me-2"></i>No riders found. 
                                <a href="create-rider-account.php">Create a rider account</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Phone</th>
                                            <th>Vehicle</th>
                                            <th>Status</th>
                                            <th>Active</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($riders as $rider): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($rider['full_name']) ?></td>
                                                <td><code><?= htmlspecialchars($rider['username']) ?></code></td>
                                                <td><?= htmlspecialchars($rider['phone'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php if ($rider['vehicle_type']): ?>
                                                        <?= htmlspecialchars(ucfirst($rider['vehicle_type'])) ?>
                                                        <?php if ($rider['vehicle_number']): ?>
                                                            <small class="text-muted">(<?= htmlspecialchars($rider['vehicle_number']) ?>)</small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusColors = [
                                                        'available' => 'success',
                                                        'busy' => 'warning',
                                                        'offline' => 'secondary'
                                                    ];
                                                    $statusColor = $statusColors[$rider['status'] ?? 'offline'] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $statusColor ?>">
                                                        <?= htmlspecialchars(ucfirst($rider['status'] ?? 'offline')) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($rider['is_active']): ?>
                                                        <span class="text-success"><i class="bi bi-check-circle"></i> Yes</span>
                                                    <?php else: ?>
                                                        <span class="text-danger"><i class="bi bi-x-circle"></i> No</span>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function generatePassword() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%";
            let password = "";
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            document.getElementById('new_password').value = password;
        }
    </script>
</body>
</html>
