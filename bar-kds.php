<?php
/**
 * Bar KDS - Bar Kitchen Display System
 * 
 * Real-time display for bartenders showing incoming BOTs
 * Features:
 * - Live order queue
 * - Station filtering
 * - One-touch status updates
 * - Priority indicators
 * - Timer tracking
 */

use App\Services\BarTabService;

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'bartender']);

$db = Database::getInstance();
$pdo = $db->getConnection();
$tabService = new BarTabService($pdo);

$currentStation = $_GET['station'] ?? null;
$stations = $tabService->getStations();

$pageTitle = 'Bar Display System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --kds-bg: #1a1a2e;
            --kds-card: #16213e;
            --kds-border: #0f3460;
            --kds-text: #e4e4e4;
            --kds-muted: #8b8b8b;
            --status-pending: #ffc107;
            --status-preparing: #17a2b8;
            --status-ready: #28a745;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background: var(--kds-bg);
            color: var(--kds-text);
            font-family: 'Segoe UI', system-ui, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .kds-header {
            background: var(--kds-card);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--kds-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .kds-title {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .kds-title i {
            color: #17a2b8;
        }
        
        .station-tabs {
            display: flex;
            gap: 0.5rem;
        }
        
        .station-tab {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            background: transparent;
            border: 2px solid var(--kds-border);
            color: var(--kds-text);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .station-tab:hover {
            border-color: #17a2b8;
        }
        
        .station-tab.active {
            background: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        
        .kds-stats {
            display: flex;
            gap: 1.5rem;
            font-size: 0.9rem;
        }
        
        .kds-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .kds-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .kds-stat.pending .kds-stat-value { color: var(--status-pending); }
        .kds-stat.preparing .kds-stat-value { color: var(--status-preparing); }
        .kds-stat.ready .kds-stat-value { color: var(--status-ready); }
        
        .kds-container {
            padding: 1.5rem;
        }
        
        .kds-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1rem;
            align-items: start;
        }
        
        .bot-card {
            background: var(--kds-card);
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid var(--kds-border);
            transition: all 0.3s;
        }
        
        .bot-card.priority-rush {
            border-color: #fd7e14;
            animation: pulse-orange 2s infinite;
        }
        
        .bot-card.priority-vip {
            border-color: #e83e8c;
            animation: pulse-pink 2s infinite;
        }
        
        @keyframes pulse-orange {
            0%, 100% { box-shadow: 0 0 0 0 rgba(253, 126, 20, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(253, 126, 20, 0); }
        }
        
        @keyframes pulse-pink {
            0%, 100% { box-shadow: 0 0 0 0 rgba(232, 62, 140, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(232, 62, 140, 0); }
        }
        
        .bot-header {
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bot-header.pending { background: var(--status-pending); color: #000; }
        .bot-header.acknowledged { background: #6c757d; }
        .bot-header.preparing { background: var(--status-preparing); }
        .bot-header.ready { background: var(--status-ready); }
        
        .bot-number {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .bot-timer {
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .bot-timer.warning { color: #fd7e14; }
        .bot-timer.danger { color: #dc3545; }
        
        .bot-meta {
            padding: 0.5rem 1rem;
            background: rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
        }
        
        .bot-items {
            padding: 0.75rem 1rem;
        }
        
        .bot-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--kds-border);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .bot-item:last-child {
            border-bottom: none;
        }
        
        .item-qty {
            font-weight: 700;
            font-size: 1.2rem;
            min-width: 40px;
            color: #17a2b8;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .item-portion {
            font-size: 0.8rem;
            color: var(--kds-muted);
        }
        
        .item-mods {
            font-size: 0.8rem;
            color: #ffc107;
            font-style: italic;
        }
        
        .bot-actions {
            padding: 0.75rem 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .bot-actions .btn {
            padding: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .btn-bump {
            grid-column: span 2;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--kds-muted);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }
        
        /* Sound indicator */
        .sound-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        /* Fullscreen button */
        .btn-fullscreen {
            background: transparent;
            border: 2px solid var(--kds-border);
            color: var(--kds-text);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
        }
        
        .btn-fullscreen:hover {
            border-color: #17a2b8;
            color: #17a2b8;
        }
    </style>
</head>
<body>
    <header class="kds-header">
        <div class="kds-title">
            <a href="bar-pos.php" class="btn-fullscreen me-2" title="Back to Bar POS">
                <i class="bi bi-arrow-left"></i>
            </a>
            <i class="bi bi-cup-straw"></i>
            <span>Bar Display</span>
            <div class="sound-indicator" id="soundIndicator" title="Sound enabled"></div>
        </div>
        
        <div class="station-tabs" id="stationTabs">
            <button class="station-tab <?= !$currentStation ? 'active' : '' ?>" data-station="">All Stations</button>
            <?php foreach ($stations as $station): ?>
                <button class="station-tab <?= $currentStation === $station['code'] ? 'active' : '' ?>" 
                        data-station="<?= htmlspecialchars($station['code']) ?>">
                    <?= htmlspecialchars($station['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <div class="kds-stats">
                <div class="kds-stat pending">
                    <span class="kds-stat-value" id="pendingCount">0</span>
                    <span>Pending</span>
                </div>
                <div class="kds-stat preparing">
                    <span class="kds-stat-value" id="preparingCount">0</span>
                    <span>Making</span>
                </div>
                <div class="kds-stat ready">
                    <span class="kds-stat-value" id="readyCount">0</span>
                    <span>Ready</span>
                </div>
            </div>
            <button class="btn-fullscreen" onclick="toggleFullscreen()">
                <i class="bi bi-fullscreen"></i>
            </button>
        </div>
    </header>
    
    <div class="kds-container">
        <div class="kds-grid" id="botsGrid">
            <div class="empty-state">
                <i class="bi bi-cup-straw"></i>
                <h4>No Orders</h4>
                <p>Waiting for bar orders...</p>
            </div>
        </div>
    </div>
    
    <!-- Audio for new orders -->
    <audio id="newOrderSound" preload="auto">
        <source src="assets/sounds/ding.mp3" type="audio/mpeg">
    </audio>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStation = '<?= htmlspecialchars($currentStation ?? '') ?>';
        let bots = [];
        let lastBotCount = 0;
        const REFRESH_INTERVAL = 3000; // 3 seconds
        
        // Station tab switching
        document.querySelectorAll('.station-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.station-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentStation = this.dataset.station;
                loadBots();
            });
        });
        
        async function loadBots() {
            try {
                const url = currentStation 
                    ? `api/bar-tabs.php?action=get_pending_bots&station=${encodeURIComponent(currentStation)}`
                    : 'api/bar-tabs.php?action=get_pending_bots';
                    
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success) {
                    bots = result.bots;
                    renderBots();
                    updateStats();
                    
                    // Play sound for new orders
                    if (bots.length > lastBotCount) {
                        playNewOrderSound();
                    }
                    lastBotCount = bots.length;
                }
            } catch (error) {
                console.error('Error loading BOTs:', error);
            }
        }
        
        function renderBots() {
            const grid = document.getElementById('botsGrid');
            
            if (bots.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="bi bi-cup-straw"></i>
                        <h4>No Orders</h4>
                        <p>Waiting for bar orders...</p>
                    </div>
                `;
                return;
            }
            
            grid.innerHTML = bots.map(bot => {
                const items = JSON.parse(bot.items || '[]');
                const createdAt = new Date(bot.created_at);
                const elapsed = Math.floor((Date.now() - createdAt.getTime()) / 1000 / 60);
                let timerClass = '';
                if (elapsed > 10) timerClass = 'danger';
                else if (elapsed > 5) timerClass = 'warning';
                
                return `
                    <div class="bot-card priority-${bot.priority}" data-bot-id="${bot.id}">
                        <div class="bot-header ${bot.status}">
                            <span class="bot-number">${bot.bot_number}</span>
                            <span class="bot-timer ${timerClass}">
                                <i class="bi bi-clock"></i> ${elapsed}m
                            </span>
                        </div>
                        <div class="bot-meta">
                            <span><i class="bi bi-geo-alt"></i> ${bot.bar_station}</span>
                            <span>${bot.source_tab ? 'Tab: ' + bot.source_tab : ''}</span>
                        </div>
                        <div class="bot-items">
                            ${items.map(item => `
                                <div class="bot-item">
                                    <span class="item-qty">x${item.quantity}</span>
                                    <div class="item-details">
                                        <div class="item-name">${item.item_name}</div>
                                        ${item.portion_name ? `<div class="item-portion">${item.portion_name}</div>` : ''}
                                        ${item.special_instructions ? `<div class="item-mods">${item.special_instructions}</div>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        <div class="bot-actions">
                            ${bot.status === 'pending' ? `
                                <button class="btn btn-warning" onclick="updateStatus(${bot.id}, 'acknowledged')">
                                    <i class="bi bi-check"></i> Acknowledge
                                </button>
                                <button class="btn btn-info" onclick="updateStatus(${bot.id}, 'preparing')">
                                    <i class="bi bi-cup-hot"></i> Start Making
                                </button>
                            ` : ''}
                            ${bot.status === 'acknowledged' ? `
                                <button class="btn btn-info btn-bump" onclick="updateStatus(${bot.id}, 'preparing')">
                                    <i class="bi bi-cup-hot"></i> Start Making
                                </button>
                            ` : ''}
                            ${bot.status === 'preparing' ? `
                                <button class="btn btn-success btn-bump" onclick="updateStatus(${bot.id}, 'ready')">
                                    <i class="bi bi-check-circle"></i> Ready for Pickup
                                </button>
                            ` : ''}
                            ${bot.status === 'ready' ? `
                                <button class="btn btn-secondary btn-bump" onclick="updateStatus(${bot.id}, 'picked_up')">
                                    <i class="bi bi-box-arrow-up"></i> Picked Up
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function updateStats() {
            const pending = bots.filter(b => b.status === 'pending').length;
            const preparing = bots.filter(b => ['acknowledged', 'preparing'].includes(b.status)).length;
            const ready = bots.filter(b => b.status === 'ready').length;
            
            document.getElementById('pendingCount').textContent = pending;
            document.getElementById('preparingCount').textContent = preparing;
            document.getElementById('readyCount').textContent = ready;
        }
        
        async function updateStatus(botId, status) {
            try {
                const response = await fetch('api/bar-tabs.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'update_bot_status',
                        bot_id: botId,
                        status: status,
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                });
                const result = await response.json();
                
                if (result.success) {
                    loadBots();
                }
            } catch (error) {
                console.error('Error updating status:', error);
            }
        }
        
        function playNewOrderSound() {
            const sound = document.getElementById('newOrderSound');
            if (sound) {
                sound.currentTime = 0;
                sound.play().catch(() => {});
            }
        }
        
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        }
        
        // Initial load and auto-refresh
        loadBots();
        setInterval(loadBots, REFRESH_INTERVAL);
        
        // Update timers every minute
        setInterval(() => {
            if (bots.length > 0) renderBots();
        }, 60000);
    </script>
</body>
</html>
