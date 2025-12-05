<?php
/**
 * WAPOS - Guest Request Tracking
 * Public page for guests to track their maintenance request status
 * 
 * @copyright Waesta Enterprises U Ltd. All rights reserved.
 */

require_once 'includes/bootstrap.php';

// Get settings
$settings = new SettingsStore($db);
$businessName = $settings->get('business_name', 'Our Property');

$trackingCode = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$request = null;
$error = '';

if ($trackingCode) {
    try {
        require_once 'app/Services/MaintenanceService.php';
        $maintenanceService = new \App\Services\MaintenanceService($db->getConnection());
        
        $result = $maintenanceService->getRequestByTrackingCode($trackingCode);
        if ($result) {
            $request = $result;
        } else {
            $error = 'No request found with that tracking code. Please check and try again.';
        }
    } catch (Exception $e) {
        error_log("Guest tracking error: " . $e->getMessage());
        $error = 'Unable to look up your request. Please try again later.';
    }
}

$pageTitle = 'Track Your Request - ' . $businessName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="theme-color" content="#667eea">
    
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --success: #48bb78;
            --warning: #ed8936;
            --danger: #f56565;
            --info: #4299e1;
            --surface: #ffffff;
            --text: #2d3748;
            --text-muted: #718096;
            --border: #e2e8f0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 16px;
        }
        
        .container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            padding: 24px 16px;
        }
        
        .header h1 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            margin-bottom: 8px;
        }
        
        .card {
            background: var(--surface);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .card-body {
            padding: clamp(20px, 5vw, 32px);
        }
        
        .form-control {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-align: center;
            font-weight: 600;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        
        .btn-track {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 14px 28px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
        }
        
        .btn-track:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .status-card {
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        
        .status-open, .status-pending { background: #fef3c7; color: #92400e; }
        .status-assigned { background: #dbeafe; color: #1e40af; }
        .status-in_progress, .status-in-progress { background: #e0e7ff; color: #3730a3; }
        .status-resolved, .status-completed { background: #d1fae5; color: #065f46; }
        .status-closed { background: #f3f4f6; color: #374151; }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .detail-value {
            font-weight: 600;
            text-align: right;
        }
        
        .timeline {
            margin-top: 20px;
            padding-left: 20px;
            border-left: 3px solid var(--border);
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
            padding-left: 20px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -11px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
        }
        
        .timeline-item.completed::before {
            background: var(--success);
        }
        
        .timeline-item.pending::before {
            background: var(--border);
        }
        
        .alert {
            border-radius: 8px;
            padding: 14px 16px;
        }
        
        .back-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            margin-top: 20px;
            opacity: 0.9;
        }
        
        .back-link:hover {
            opacity: 1;
            color: white;
        }
        
        @media (max-width: 480px) {
            .detail-row {
                flex-direction: column;
                gap: 4px;
            }
            .detail-value {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="bi bi-search me-2"></i>Track Your Request</h1>
            <p>Enter your tracking code to check the status</p>
        </header>
        
        <div class="card">
            <div class="card-body">
                <form method="GET">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tracking Code</label>
                        <input type="text" name="code" class="form-control" 
                               placeholder="XXXXXXXX" maxlength="10"
                               value="<?= htmlspecialchars($trackingCode) ?>" required>
                    </div>
                    <button type="submit" class="btn-track">
                        <i class="bi bi-search me-2"></i>Track Request
                    </button>
                </form>
                
                <?php if ($error): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($request): ?>
                    <div class="status-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($request['title'] ?? 'Maintenance Request') ?></h5>
                                <small class="text-muted">#<?= htmlspecialchars($request['tracking_code']) ?></small>
                            </div>
                            <span class="status-badge status-<?= htmlspecialchars($request['status']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $request['status'])) ?>
                            </span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Submitted</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($request['created_at'])) ?></span>
                        </div>
                        
                        <?php if (!empty($request['priority'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Priority</span>
                            <span class="detail-value"><?= ucfirst($request['priority']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['assigned_to_name'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Assigned To</span>
                            <span class="detail-value"><?= htmlspecialchars($request['assigned_to_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['started_at'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Work Started</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($request['started_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['completed_at'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Completed</span>
                            <span class="detail-value"><?= date('M j, Y g:i A', strtotime($request['completed_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Status Timeline -->
                        <div class="timeline mt-4">
                            <div class="timeline-item completed">
                                <strong>Request Submitted</strong>
                                <div class="text-muted small"><?= date('M j, Y', strtotime($request['created_at'])) ?></div>
                            </div>
                            
                            <div class="timeline-item <?= in_array($request['status'], ['assigned', 'in_progress', 'resolved', 'closed']) ? 'completed' : 'pending' ?>">
                                <strong>Assigned to Technician</strong>
                            </div>
                            
                            <div class="timeline-item <?= in_array($request['status'], ['in_progress', 'resolved', 'closed']) ? 'completed' : 'pending' ?>">
                                <strong>Work in Progress</strong>
                            </div>
                            
                            <div class="timeline-item <?= in_array($request['status'], ['resolved', 'closed']) ? 'completed' : 'pending' ?>">
                                <strong>Resolved</strong>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center">
            <a href="guest-request.php" class="back-link">
                <i class="bi bi-plus-circle me-1"></i> Submit a New Request
            </a>
        </div>
    </div>
</body>
</html>
