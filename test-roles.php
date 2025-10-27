<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

$pageTitle = 'Role Test';
include 'includes/header.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h4 class="mb-4"><i class="bi bi-shield-check me-2"></i>Role-Based Access Test</h4>
        
        <div class="alert alert-info">
            <h6><i class="bi bi-person-badge me-2"></i>Your Current Role: <strong><?= ucfirst($auth->getRole()) ?></strong></h6>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Access Permissions</h6>
                        <div class="small">
                            <?php
                            $roles = ['admin', 'manager', 'inventory_manager', 'cashier', 'waiter', 'rider'];
                            foreach ($roles as $role) {
                                $hasAccess = $auth->hasRole($role);
                                echo '<div class="mb-1">';
                                echo '<i class="bi bi-' . ($hasAccess ? 'check-circle text-success' : 'x-circle text-danger') . ' me-2"></i>';
                                echo ucfirst(str_replace('_', ' ', $role)) . ': ' . ($hasAccess ? 'Yes' : 'No');
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Menu Items You Should See</h6>
                        <div class="small">
                            <?php
                            $currentRole = $auth->getRole();
                            $menus = [];
                            
                            // Always visible
                            $menus[] = 'Dashboard';
                            
                            if ($currentRole === 'admin') {
                                $menus = array_merge($menus, [
                                    'All Operations', 'Management Section', 
                                    'Manage Tables', 'Manage Rooms', 'Users', 'Settings'
                                ]);
                            } elseif ($currentRole === 'manager') {
                                $menus = array_merge($menus, [
                                    'Retail POS', 'Restaurant', 'Rooms', 'Delivery', 
                                    'Products', 'Sales', 'Customers', 'Reports', 'Accounting'
                                ]);
                            } elseif ($currentRole === 'cashier') {
                                $menus = array_merge($menus, ['Retail POS', 'Customers']);
                            } elseif ($currentRole === 'waiter') {
                                $menus[] = 'Restaurant';
                            } elseif ($currentRole === 'rider') {
                                $menus[] = 'Delivery';
                            } elseif ($currentRole === 'inventory_manager') {
                                $menus[] = 'Products';
                            }
                            
                            foreach ($menus as $menu) {
                                echo '<div class="mb-1"><i class="bi bi-check-circle text-success me-2"></i>' . $menu . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <div class="alert alert-warning">
                <h6><i class="bi bi-info-circle me-2"></i>Instructions:</h6>
                <ol class="mb-0">
                    <li>Check if your sidebar menu matches the "Menu Items You Should See" list</li>
                    <li>If you're Admin, you should see "Management" section with Manage Tables/Rooms</li>
                    <li>If you're Manager, you should see Accounting and all operational modules</li>
                    <li>If you're Cashier, you should only see Retail POS and Customers</li>
                    <li>If you're Waiter, you should only see Restaurant</li>
                    <li>If you're Rider, you should only see Delivery</li>
                </ol>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
            <a href="users.php" class="btn btn-primary">
                <i class="bi bi-person-badge me-2"></i>Manage Users (Admin Only)
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-house me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
