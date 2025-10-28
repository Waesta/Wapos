<?php
require_once "includes/bootstrap.php";
if (!$auth->isLoggedIn()) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>WAPOS Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>🎯 WAPOS Dashboard</h1>
        <div class="alert alert-success">
            <h4>✅ System is Working!</h4>
            <p>Basic functionality has been restored.</p>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>POS System</h5>
                        <a href="pos.php" class="btn btn-primary">Open POS</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>Restaurant</h5>
                        <a href="restaurant.php" class="btn btn-success">Restaurant</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>Products</h5>
                        <a href="products.php" class="btn btn-info">Products</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5>Settings</h5>
                        <a href="settings.php" class="btn btn-secondary">Settings</a>
                    </div>
                </div>
            </div>
        </div>
        <hr>
        <p><a href="logout.php" class="btn btn-outline-danger">Logout</a></p>
    </div>
</body>
</html>