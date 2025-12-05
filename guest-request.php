<?php
/**
 * WAPOS - Guest Portal Redirect
 * Redirects to secure guest portal
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

// Redirect to secure guest portal
header('Location: ' . APP_URL . '/guest-portal.php');
exit;

// Legacy code below - kept for reference but never executed
/*
// Get settings
$settings = new SettingsStore($db);
$businessName = $settings->get('business_name', 'Our Property');
$businessLogo = $settings->get('business_logo', '');
$guestPortalEnabled = $settings->get('guest_portal_enabled', '1') === '1';

// Check if guest portal is enabled
if (!$guestPortalEnabled) {
    header('HTTP/1.0 404 Not Found');
    exit('Guest portal is not available.');
}
*/

$message = '';
$messageType = '';
$submitted = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guestName = trim($_POST['guest_name'] ?? '');
    $roomNumber = trim($_POST['room_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $requestType = $_POST['request_type'] ?? 'maintenance';
    $category = $_POST['category'] ?? 'general';
    $priority = $_POST['priority'] ?? 'medium';
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($guestName)) $errors[] = 'Name is required';
    if (empty($roomNumber)) $errors[] = 'Room/Unit number is required';
    if (empty($description)) $errors[] = 'Description is required';
    if (strlen($description) < 10) $errors[] = 'Please provide more details (at least 10 characters)';
    
    if (empty($errors)) {
        try {
            // Generate tracking code for guest
            $trackingCode = strtoupper(substr(md5(uniqid()), 0, 8));
            
            if ($requestType === 'housekeeping') {
                // Insert housekeeping request
                $stmt = $db->query(
                    "INSERT INTO housekeeping_tasks (room_number, task_type, priority, notes, status, created_at) 
                     VALUES (?, ?, ?, ?, 'pending', NOW())",
                    [
                        $roomNumber,
                        $category,
                        $priority,
                        "Guest: {$guestName}\nContact: " . ($phone ?: $email) . "\n\n{$description}"
                    ]
                );
            } else {
                // Use MaintenanceService for proper handling
                require_once 'app/Services/MaintenanceService.php';
                $maintenanceService = new \App\Services\MaintenanceService($db->getConnection());
                
                $result = $maintenanceService->createRequest([
                    'title' => ucfirst($category) . ' Issue - Room ' . $roomNumber,
                    'description' => $description,
                    'priority' => $priority === 'high' ? 'high' : ($priority === 'low' ? 'low' : 'normal'),
                    'reporter_type' => 'guest',
                    'reporter_name' => $guestName,
                    'reporter_contact' => $phone ?: $email,
                    'tracking_code' => $trackingCode,
                    'notes' => "Room: {$roomNumber}\nCategory: {$category}"
                ], null);
                
                $trackingCode = $result['tracking_code'] ?? $trackingCode;
            }
            
            $submitted = true;
            $message = "Your request has been submitted successfully!<br><strong>Tracking Code: {$trackingCode}</strong><br>Our team will attend to it shortly.";
            $messageType = 'success';
            
            // Log the submission
            error_log("Guest request submitted: {$requestType} from {$guestName} in Room {$roomNumber} - Tracking: {$trackingCode}");
            
        } catch (Exception $e) {
            error_log("Guest request error: " . $e->getMessage());
            $message = 'Sorry, there was an error submitting your request. Please try again or contact the front desk.';
            $messageType = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}

$pageTitle = 'Guest Request Portal - ' . $businessName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/images/favicon.png">
    
    <!-- Bootstrap & Icons -->
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
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 16px;
            color: var(--text);
        }
        
        .guest-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .guest-header {
            text-align: center;
            color: white;
            padding: 24px 16px;
        }
        
        .guest-header h1 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .guest-header p {
            opacity: 0.9;
            font-size: clamp(0.9rem, 3vw, 1rem);
            margin: 0;
        }
        
        .guest-card {
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        
        .card-body {
            padding: clamp(20px, 5vw, 32px);
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text);
            margin-bottom: 6px;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 16px; /* Prevents zoom on iOS */
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .request-type-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .type-option {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            background: var(--surface);
        }
        
        .type-option:hover {
            border-color: var(--primary);
            background: var(--surface-muted);
        }
        
        .type-option.active {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.1);
        }
        
        .type-option i {
            font-size: 2rem;
            color: var(--primary);
            display: block;
            margin-bottom: 8px;
        }
        
        .type-option span {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .type-option input {
            display: none;
        }
        
        .priority-selector {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .priority-option {
            flex: 1;
            min-width: 80px;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .priority-option:hover {
            border-color: var(--text-muted);
        }
        
        .priority-option.active {
            color: white;
        }
        
        .priority-option.low.active {
            background: var(--success);
            border-color: var(--success);
        }
        
        .priority-option.medium.active {
            background: var(--warning);
            border-color: var(--warning);
        }
        
        .priority-option.high.active {
            background: var(--danger);
            border-color: var(--danger);
        }
        
        .priority-option input {
            display: none;
        }
        
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .success-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .success-message h2 {
            color: var(--success);
            margin-bottom: 12px;
        }
        
        .success-message p {
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        
        .btn-new-request {
            background: var(--surface-muted);
            border: 2px solid var(--border);
            color: var(--text);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-new-request:hover {
            background: var(--border);
        }
        
        .alert {
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 20px;
            border: none;
        }
        
        .contact-info {
            background: var(--surface-muted);
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            text-align: center;
        }
        
        .contact-info p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .contact-info a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        
        /* Mobile optimizations */
        @media (max-width: 480px) {
            body {
                padding: 12px;
            }
            
            .guest-header {
                padding: 16px 12px;
            }
            
            .card-body {
                padding: 20px 16px;
            }
            
            .request-type-selector {
                grid-template-columns: 1fr;
            }
            
            .type-option {
                display: flex;
                align-items: center;
                gap: 12px;
                text-align: left;
                padding: 14px;
            }
            
            .type-option i {
                font-size: 1.5rem;
                margin-bottom: 0;
            }
            
            .priority-selector {
                flex-direction: column;
            }
            
            .priority-option {
                min-width: 100%;
            }
        }
        
        /* Tablet */
        @media (min-width: 481px) and (max-width: 768px) {
            .guest-container {
                max-width: 500px;
            }
        }
        
        /* Touch-friendly */
        @media (hover: none) and (pointer: coarse) {
            .type-option, .priority-option, .btn-submit, .btn-new-request {
                min-height: 48px;
            }
        }
        
        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            :root {
                --surface: #1a202c;
                --surface-muted: #2d3748;
                --text: #f7fafc;
                --text-muted: #a0aec0;
                --border: #4a5568;
            }
            
            body {
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            }
        }
    </style>
</head>
<body>
    <div class="guest-container">
        <header class="guest-header">
            <?php if ($businessLogo): ?>
                <img src="<?= htmlspecialchars($businessLogo) ?>" alt="<?= htmlspecialchars($businessName) ?>" style="max-height: 60px; margin-bottom: 12px;">
            <?php endif; ?>
            <h1><i class="bi bi-headset me-2"></i><?= htmlspecialchars($businessName) ?></h1>
            <p>Guest Service Request Portal</p>
        </header>
        
        <div class="guest-card">
            <div class="card-body">
                <?php if ($submitted && $messageType === 'success'): ?>
                    <!-- Success State -->
                    <div class="success-message">
                        <div class="success-icon">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <h2>Request Submitted!</h2>
                        <p><?= $message ?></p>
                        <a href="guest-request.php" class="btn-new-request">
                            <i class="bi bi-plus-lg me-2"></i>Submit Another Request
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Form -->
                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?>">
                            <i class="bi bi-<?= $messageType === 'danger' ? 'exclamation-triangle' : 'info-circle' ?> me-2"></i>
                            <?= $message ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="guestRequestForm">
                        <!-- Request Type -->
                        <div class="mb-4">
                            <label class="form-label">What do you need help with?</label>
                            <div class="request-type-selector">
                                <label class="type-option active" data-type="maintenance">
                                    <input type="radio" name="request_type" value="maintenance" checked>
                                    <i class="bi bi-tools"></i>
                                    <span>Maintenance</span>
                                </label>
                                <label class="type-option" data-type="housekeeping">
                                    <input type="radio" name="request_type" value="housekeeping">
                                    <i class="bi bi-house-heart"></i>
                                    <span>Housekeeping</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Guest Info -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                <input type="text" name="guest_name" class="form-control" placeholder="John Doe" required
                                       value="<?= htmlspecialchars($_POST['guest_name'] ?? '') ?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Room/Unit Number <span class="text-danger">*</span></label>
                                <input type="text" name="room_number" class="form-control" placeholder="e.g., 101, A-12" required
                                       value="<?= htmlspecialchars($_POST['room_number'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control" placeholder="+254 7XX XXX XXX"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" placeholder="guest@email.com"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <!-- Category -->
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" id="categorySelect">
                                <!-- Maintenance categories -->
                                <optgroup label="Maintenance" class="maintenance-options">
                                    <option value="plumbing">Plumbing (Leaks, Drains, Toilet)</option>
                                    <option value="electrical">Electrical (Lights, Outlets, Switches)</option>
                                    <option value="hvac">HVAC (AC, Heating, Ventilation)</option>
                                    <option value="appliances">Appliances (TV, Fridge, etc.)</option>
                                    <option value="furniture">Furniture (Bed, Chair, Table)</option>
                                    <option value="doors_windows">Doors & Windows</option>
                                    <option value="general">General/Other</option>
                                </optgroup>
                                <!-- Housekeeping categories -->
                                <optgroup label="Housekeeping" class="housekeeping-options" style="display:none;">
                                    <option value="cleaning">Room Cleaning</option>
                                    <option value="towels">Fresh Towels</option>
                                    <option value="linens">Bed Linens Change</option>
                                    <option value="toiletries">Toiletries Refill</option>
                                    <option value="minibar">Minibar Restock</option>
                                    <option value="turndown">Turndown Service</option>
                                    <option value="other">Other Request</option>
                                </optgroup>
                            </select>
                        </div>
                        
                        <!-- Priority -->
                        <div class="mb-3">
                            <label class="form-label">How urgent is this?</label>
                            <div class="priority-selector">
                                <label class="priority-option low">
                                    <input type="radio" name="priority" value="low">
                                    <i class="bi bi-clock me-1"></i> Low
                                </label>
                                <label class="priority-option medium active">
                                    <input type="radio" name="priority" value="medium" checked>
                                    <i class="bi bi-exclamation-circle me-1"></i> Medium
                                </label>
                                <label class="priority-option high">
                                    <input type="radio" name="priority" value="high">
                                    <i class="bi bi-exclamation-triangle me-1"></i> Urgent
                                </label>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label class="form-label">Describe the issue <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" 
                                      placeholder="Please describe what you need help with. Include any relevant details like location within the room, when the issue started, etc."
                                      required minlength="10"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Submit -->
                        <button type="submit" class="btn-submit">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                    </form>
                    
                    <div class="contact-info">
                        <p><i class="bi bi-telephone me-1"></i> For emergencies, please call the front desk directly</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <footer style="text-align: center; color: rgba(255,255,255,0.7); padding: 20px; font-size: 0.85rem;">
            <p>Powered by <strong>WAPOS</strong></p>
        </footer>
    </div>
    
    <script>
        // Request type toggle
        document.querySelectorAll('.type-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.type-option').forEach(o => o.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
                
                // Toggle category options
                const type = this.dataset.type;
                const select = document.getElementById('categorySelect');
                
                if (type === 'housekeeping') {
                    select.querySelectorAll('.maintenance-options option').forEach(o => o.style.display = 'none');
                    select.querySelectorAll('.housekeeping-options').forEach(g => g.style.display = '');
                    select.querySelectorAll('.housekeeping-options option').forEach(o => o.style.display = '');
                    select.value = 'cleaning';
                } else {
                    select.querySelectorAll('.housekeeping-options option').forEach(o => o.style.display = 'none');
                    select.querySelectorAll('.maintenance-options').forEach(g => g.style.display = '');
                    select.querySelectorAll('.maintenance-options option').forEach(o => o.style.display = '');
                    select.value = 'general';
                }
            });
        });
        
        // Priority toggle
        document.querySelectorAll('.priority-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.priority-option').forEach(o => o.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('input').checked = true;
            });
        });
        
        // Form submission feedback
        document.getElementById('guestRequestForm')?.addEventListener('submit', function(e) {
            const btn = this.querySelector('.btn-submit');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        });
    </script>
</body>
</html>
