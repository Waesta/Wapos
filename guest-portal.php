<?php
/**
 * WAPOS - Secure Guest Portal
 * Authenticated access for registered guests only
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';
require_once 'app/Services/GuestAuthService.php';

use App\Services\GuestAuthService;

// Initialize guest auth service
$guestAuth = new GuestAuthService($db->getConnection());

// Get settings
$settings = new SettingsStore($db);
$businessName = $settings->get('business_name', 'Our Property');
$guestPortalEnabled = $settings->get('guest_portal_enabled', '1') === '1';

// Check if guest portal is enabled
if (!$guestPortalEnabled) {
    header('HTTP/1.0 404 Not Found');
    exit('Guest portal is not available.');
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$message = '';
$messageType = '';
$isAuthenticated = false;
$guestData = null;

// Session cookie name
$sessionCookieName = 'wapos_guest_session';

// Check for existing session
if (isset($_COOKIE[$sessionCookieName])) {
    $guestData = $guestAuth->validateSession($_COOKIE[$sessionCookieName], $ipAddress);
    if ($guestData) {
        $isAuthenticated = true;
    }
}

// Handle token-based authentication (direct link)
if (!$isAuthenticated && isset($_GET['token'])) {
    try {
        $result = $guestAuth->authenticateByToken($_GET['token'], $ipAddress);
        
        // Set secure session cookie
        setcookie($sessionCookieName, $result['session_token'], [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        $isAuthenticated = true;
        $guestData = [
            'guest_access_id' => $result['guest_access_id'],
            'guest_name' => $result['guest_name'],
            'room_number' => $result['room_number'],
            'expires_at' => $result['expires_at']
        ];
        
        // Redirect to remove token from URL
        header('Location: ' . APP_URL . '/guest-portal.php');
        exit;
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle login form submission
if (!$isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        $result = $guestAuth->authenticate($username, $password, $ipAddress);
        
        // Set secure session cookie
        setcookie($sessionCookieName, $result['session_token'], [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        $isAuthenticated = true;
        $guestData = [
            'guest_access_id' => $result['guest_access_id'],
            'guest_name' => $result['guest_name'],
            'room_number' => $result['room_number'],
            'expires_at' => $result['expires_at']
        ];
        
        // Redirect to prevent form resubmission
        header('Location: ' . APP_URL . '/guest-portal.php');
        exit;
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_COOKIE[$sessionCookieName])) {
        $guestAuth->logout($_COOKIE[$sessionCookieName]);
        setcookie($sessionCookieName, '', time() - 3600, '/');
    }
    header('Location: ' . APP_URL . '/guest-portal.php');
    exit;
}

// Handle request submission (authenticated users only)
if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {
    $requestType = $_POST['request_type'] ?? 'maintenance';
    $category = $_POST['category'] ?? 'general';
    $priority = $_POST['priority'] ?? 'medium';
    $description = trim($_POST['description'] ?? '');
    
    if (strlen($description) < 10) {
        $message = 'Please provide more details (at least 10 characters).';
        $messageType = 'danger';
    } else {
        try {
            $trackingCode = strtoupper(substr(md5(uniqid()), 0, 8));
            
            if ($requestType === 'housekeeping') {
                $stmt = $db->query(
                    "INSERT INTO housekeeping_tasks (room_number, task_type, priority, notes, status, created_at) 
                     VALUES (?, ?, ?, ?, 'pending', NOW())",
                    [
                        $guestData['room_number'],
                        $category,
                        $priority,
                        "Guest: {$guestData['guest_name']}\n\n{$description}"
                    ]
                );
            } else {
                require_once 'app/Services/MaintenanceService.php';
                $maintenanceService = new \App\Services\MaintenanceService($db->getConnection());
                
                $result = $maintenanceService->createRequest([
                    'title' => ucfirst($category) . ' Issue - Room ' . $guestData['room_number'],
                    'description' => $description,
                    'priority' => $priority === 'high' ? 'high' : ($priority === 'low' ? 'low' : 'normal'),
                    'reporter_type' => 'guest',
                    'reporter_name' => $guestData['guest_name'],
                    'tracking_code' => $trackingCode,
                    'notes' => "Room: {$guestData['room_number']}\nCategory: {$category}\nGuest Portal Submission"
                ], null);
                
                $trackingCode = $result['tracking_code'] ?? $trackingCode;
            }
            
            $message = "Request submitted successfully!<br><strong>Tracking Code: {$trackingCode}</strong>";
            $messageType = 'success';
            
        } catch (Exception $e) {
            error_log("Guest portal request error: " . $e->getMessage());
            $message = 'Error submitting request. Please try again.';
            $messageType = 'danger';
        }
    }
}

$pageTitle = 'Guest Portal - ' . $businessName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#667eea">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --surface: #ffffff;
            --surface-muted: #f7fafc;
            --text: #2d3748;
            --text-muted: #718096;
            --border: #e2e8f0;
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 16px;
            color: var(--text);
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            padding: 20px 16px;
        }
        
        .header h1 {
            font-size: clamp(1.4rem, 5vw, 1.8rem);
            margin-bottom: 4px;
        }
        
        .header p {
            opacity: 0.9;
            margin: 0;
        }
        
        .card {
            background: var(--surface);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .card-body {
            padding: clamp(24px, 5vw, 32px);
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin: -32px -32px 24px -32px;
        }
        
        .welcome-banner h2 {
            margin: 0 0 4px 0;
            font-size: 1.2rem;
        }
        
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .room-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 6px;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            outline: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 14px 28px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            color: white;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 10px;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-outline:hover {
            background: var(--surface-muted);
        }
        
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        
        .request-type-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .type-btn {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--surface);
        }
        
        .type-btn:hover {
            border-color: var(--primary);
        }
        
        .type-btn.active {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.1);
        }
        
        .type-btn i {
            font-size: 1.8rem;
            color: var(--primary);
            display: block;
            margin-bottom: 8px;
        }
        
        .type-btn span {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .type-btn input { display: none; }
        
        .priority-pills {
            display: flex;
            gap: 8px;
        }
        
        .priority-pill {
            flex: 1;
            padding: 10px;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .priority-pill:hover { border-color: var(--text-muted); }
        .priority-pill.active.low { background: var(--success); color: white; border-color: var(--success); }
        .priority-pill.active.medium { background: var(--warning); color: white; border-color: var(--warning); }
        .priority-pill.active.high { background: var(--danger); color: white; border-color: var(--danger); }
        .priority-pill input { display: none; }
        
        .alert {
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border: none;
        }
        
        .logout-link {
            display: block;
            text-align: center;
            color: white;
            text-decoration: none;
            margin-top: 16px;
            opacity: 0.9;
        }
        
        .logout-link:hover {
            opacity: 1;
            color: white;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        .login-footer p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }
        
        @media (max-width: 480px) {
            .card-body { padding: 20px 16px; }
            .welcome-banner { margin: -20px -16px 20px -16px; padding: 16px; }
            .request-type-grid { grid-template-columns: 1fr; }
            .priority-pills { flex-direction: column; }
        }
        
        @media (prefers-color-scheme: dark) {
            :root {
                --surface: #1a202c;
                --surface-muted: #2d3748;
                --text: #f7fafc;
                --text-muted: #a0aec0;
                --border: #4a5568;
            }
            body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); }
            .secure-badge { background: #064e3b; color: #d1fae5; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="bi bi-shield-lock me-2"></i><?= htmlspecialchars($businessName) ?></h1>
            <p>Secure Guest Portal</p>
        </header>
        
        <div class="card">
            <div class="card-body">
                <?php if (!$isAuthenticated): ?>
                    <!-- Login Form -->
                    <div class="secure-badge">
                        <i class="bi bi-lock-fill"></i>
                        <span>Secure encrypted connection</span>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <i class="bi bi-<?= $messageType === 'danger' ? 'exclamation-triangle' : 'check-circle' ?> me-2"></i>
                            <?= $message ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="text-center mb-4">Guest Login</h3>
                    <p class="text-muted text-center mb-4">Enter the credentials provided at check-in</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-3">
                            <label class="form-label">Guest ID</label>
                            <input type="text" name="username" class="form-control" 
                                   placeholder="e.g., G10112041A2B" required autocomplete="username">
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter your password" required autocomplete="current-password">
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>
                    
                    <div class="login-footer">
                        <p><i class="bi bi-info-circle me-1"></i>Credentials are provided at check-in.<br>Contact the front desk if you need assistance.</p>
                    </div>
                    
                <?php else: ?>
                    <!-- Authenticated Guest View -->
                    <div class="welcome-banner">
                        <h2>Welcome, <?= htmlspecialchars($guestData['guest_name']) ?>!</h2>
                        <p>How can we help you today?</p>
                        <span class="room-badge"><i class="bi bi-door-open me-1"></i>Room <?= htmlspecialchars($guestData['room_number']) ?></span>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="submit_request">
                        
                        <!-- Request Type -->
                        <div class="mb-3">
                            <label class="form-label">What do you need?</label>
                            <div class="request-type-grid">
                                <label class="type-btn active">
                                    <input type="radio" name="request_type" value="maintenance" checked>
                                    <i class="bi bi-tools"></i>
                                    <span>Maintenance</span>
                                </label>
                                <label class="type-btn">
                                    <input type="radio" name="request_type" value="housekeeping">
                                    <i class="bi bi-house-heart"></i>
                                    <span>Housekeeping</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Category -->
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" id="categorySelect">
                                <optgroup label="Maintenance" class="maintenance-opts">
                                    <option value="plumbing">Plumbing</option>
                                    <option value="electrical">Electrical</option>
                                    <option value="hvac">AC / Heating</option>
                                    <option value="appliances">Appliances</option>
                                    <option value="furniture">Furniture</option>
                                    <option value="general" selected>General</option>
                                </optgroup>
                                <optgroup label="Housekeeping" class="housekeeping-opts" style="display:none;">
                                    <option value="cleaning">Room Cleaning</option>
                                    <option value="towels">Fresh Towels</option>
                                    <option value="linens">Bed Linens</option>
                                    <option value="toiletries">Toiletries</option>
                                    <option value="minibar">Minibar</option>
                                    <option value="other">Other</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <!-- Priority -->
                        <div class="mb-3">
                            <label class="form-label">Urgency</label>
                            <div class="priority-pills">
                                <label class="priority-pill low">
                                    <input type="radio" name="priority" value="low">
                                    Low
                                </label>
                                <label class="priority-pill medium active">
                                    <input type="radio" name="priority" value="medium" checked>
                                    Medium
                                </label>
                                <label class="priority-pill high">
                                    <input type="radio" name="priority" value="high">
                                    Urgent
                                </label>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label class="form-label">Describe your request</label>
                            <textarea name="description" class="form-control" rows="4" 
                                      placeholder="Please describe what you need..." required minlength="10"></textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <a href="guest-track.php" class="btn-outline">
                            <i class="bi bi-search me-1"></i> Track Existing Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($isAuthenticated): ?>
            <a href="?logout=1" class="logout-link">
                <i class="bi bi-box-arrow-left me-1"></i> Sign Out
            </a>
        <?php endif; ?>
        
        <footer style="text-align: center; color: rgba(255,255,255,0.7); padding: 20px; font-size: 0.8rem;">
            <i class="bi bi-shield-check me-1"></i> Secured by WAPOS
        </footer>
    </div>
    
    <script>
        // Request type toggle
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const type = this.querySelector('input').value;
                const select = document.getElementById('categorySelect');
                
                if (type === 'housekeeping') {
                    select.querySelectorAll('.maintenance-opts option').forEach(o => o.style.display = 'none');
                    select.querySelectorAll('.housekeeping-opts').forEach(g => g.style.display = '');
                    select.querySelectorAll('.housekeeping-opts option').forEach(o => o.style.display = '');
                    select.value = 'cleaning';
                } else {
                    select.querySelectorAll('.housekeeping-opts option').forEach(o => o.style.display = 'none');
                    select.querySelectorAll('.maintenance-opts').forEach(g => g.style.display = '');
                    select.querySelectorAll('.maintenance-opts option').forEach(o => o.style.display = '');
                    select.value = 'general';
                }
            });
        });
        
        // Priority toggle
        document.querySelectorAll('.priority-pill').forEach(pill => {
            pill.addEventListener('click', function() {
                document.querySelectorAll('.priority-pill').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
            });
        });
    </script>
</body>
</html>
