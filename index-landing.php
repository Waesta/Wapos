<?php
/**
 * WAPOS - Professional Landing Page
 * Welcome page with login access
 */

require_once 'includes/bootstrap.php';

// If already logged in, redirect to dashboard
if ($auth->isLoggedIn()) {
    $role = $auth->getUser()['role'];
    
    switch ($role) {
        case 'admin':
            redirect('dashboards/admin.php');
            break;
        case 'manager':
            redirect('dashboards/manager.php');
            break;
        case 'accountant':
            redirect('dashboards/accountant.php');
            break;
        case 'cashier':
            redirect('dashboards/cashier.php');
            break;
        case 'waiter':
            redirect('dashboards/waiter.php');
            break;
        default:
            redirect('pos.php');
    }
}

$pageTitle = 'Welcome to WAPOS';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .landing-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .hero-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .hero-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 60px 40px;
            text-align: center;
        }
        
        .hero-header h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .hero-header p {
            font-size: 1.3rem;
            opacity: 0.95;
            margin-bottom: 0;
        }
        
        .hero-body {
            padding: 50px 40px;
        }
        
        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid #f0f0f0;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: white;
        }
        
        .feature-card h3 {
            color: var(--secondary-color);
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .feature-card p {
            color: #666;
            margin-bottom: 0;
        }
        
        .cta-section {
            text-align: center;
            margin-top: 40px;
            padding-top: 40px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 18px 50px;
            font-size: 1.2rem;
            font-weight: 600;
            border: none;
            border-radius: 50px;
            box-shadow: 0 10px 30px rgba(37, 99, 235, 0.3);
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(37, 99, 235, 0.4);
            color: white;
        }
        
        .stats-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        @media (max-width: 768px) {
            .hero-header h1 {
                font-size: 2.5rem;
            }
            
            .hero-header p {
                font-size: 1.1rem;
            }
            
            .hero-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="hero-card">
            <!-- Hero Header -->
            <div class="hero-header">
                <h1><i class="bi bi-shop"></i> WAPOS</h1>
                <p>Web-Based Point of Sale & Business Management System</p>
            </div>
            
            <!-- Hero Body -->
            <div class="hero-body">
                <!-- Features Grid -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                            <h3>Point of Sale</h3>
                            <p>Fast and efficient POS system for retail and restaurant operations</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <h3>Financial Reports</h3>
                            <p>Comprehensive accounting and financial management tools</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-box-seam"></i>
                            </div>
                            <h3>Inventory Control</h3>
                            <p>Real-time inventory tracking and stock management</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <h3>User Management</h3>
                            <p>Role-based access control with personalized dashboards</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-globe"></i>
                            </div>
                            <h3>Currency Neutral</h3>
                            <p>Works globally with any currency and regional settings</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="bi bi-phone"></i>
                            </div>
                            <h3>Mobile Ready</h3>
                            <p>Responsive design works on desktop, tablet, and mobile</p>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Section -->
                <div class="stats-section">
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3 mb-md-0">
                            <div class="stat-item">
                                <span class="stat-number"><i class="bi bi-check-circle-fill"></i></span>
                                <span class="stat-label">Reliable</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3 mb-md-0">
                            <div class="stat-item">
                                <span class="stat-number"><i class="bi bi-shield-check"></i></span>
                                <span class="stat-label">Secure</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <span class="stat-number"><i class="bi bi-lightning-fill"></i></span>
                                <span class="stat-label">Fast</span>
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="stat-item">
                                <span class="stat-number"><i class="bi bi-heart-fill"></i></span>
                                <span class="stat-label">Easy to Use</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- CTA Section -->
                <div class="cta-section">
                    <h2 class="mb-4">Ready to Get Started?</h2>
                    <a href="login.php" class="btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login to Your Account
                    </a>
                    <p class="mt-4 text-muted">
                        <small>Secure access with role-based permissions</small>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-4">
            <p class="text-white mb-0">
                <small>&copy; <?= date('Y') ?> WAPOS. Professional Business Management System.</small>
            </p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
