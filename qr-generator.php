<?php
/**
 * Universal QR Code Generator
 * 
 * Generates QR codes for all modules:
 * - Restaurant tables (digital menu)
 * - Bar tabs
 * - Room service
 * - Delivery tracking
 * - Payment links
 * - Receipts
 * - Loyalty/membership
 * - WiFi credentials
 */

require_once 'includes/bootstrap.php';
$auth->requireRole(['admin', 'manager', 'frontdesk', 'waiter', 'bartender']);

$db = Database::getInstance();

// Get data for various QR types
$tables = $db->fetchAll("SELECT * FROM restaurant_tables WHERE is_active = 1 ORDER BY table_number");
$rooms = $db->fetchAll("SELECT * FROM rooms WHERE is_active = 1 ORDER BY room_number");
$locations = $db->fetchAll("SELECT * FROM locations WHERE is_active = 1 ORDER BY name");

// Get active bookings for check-in QR
$bookings = $db->fetchAll("
    SELECT rb.id, rb.booking_number, rb.guest_name, r.room_number, rb.check_in_date
    FROM room_bookings rb
    JOIN rooms r ON rb.room_id = r.id
    WHERE rb.status IN ('confirmed', 'checked_in')
    ORDER BY rb.check_in_date DESC
    LIMIT 100
");

$baseUrl = rtrim(APP_URL, '/');
$qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/';

$pageTitle = 'QR Code Generator';
include 'includes/header.php';
?>

<style>
    .qr-type-card {
        border: 2px solid var(--bs-border-color);
        border-radius: 1rem;
        padding: 1.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        height: 100%;
    }
    
    .qr-type-card:hover {
        border-color: var(--bs-primary);
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .qr-type-card.active {
        border-color: var(--bs-primary);
        background: rgba(var(--bs-primary-rgb), 0.1);
    }
    
    .qr-type-icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }
    
    .qr-type-title {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    
    .qr-type-desc {
        font-size: 0.8rem;
        color: var(--bs-secondary);
    }
    
    .qr-preview {
        background: white;
        border-radius: 1rem;
        padding: 2rem;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .qr-preview img {
        max-width: 250px;
        border-radius: 0.5rem;
    }
    
    .qr-url {
        font-size: 0.75rem;
        color: var(--bs-secondary);
        word-break: break-all;
        margin-top: 1rem;
        padding: 0.5rem;
        background: var(--bs-light);
        border-radius: 0.25rem;
    }
    
    .batch-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 1rem;
    }
    
    .batch-item {
        background: white;
        border-radius: 0.5rem;
        padding: 1rem;
        text-align: center;
        border: 1px solid var(--bs-border-color);
    }
    
    .batch-item img {
        max-width: 150px;
    }
    
    .batch-item .label {
        font-weight: 600;
        margin-top: 0.5rem;
    }
    
    @media print {
        .no-print { display: none !important; }
        .batch-item { page-break-inside: avoid; }
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4 no-print">
        <div class="col">
            <h4><i class="bi bi-qr-code me-2"></i>Universal QR Code Generator</h4>
            <p class="text-muted">Generate QR codes for tables, rooms, payments, and more</p>
        </div>
    </div>
    
    <!-- QR Type Selection -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-2 col-4">
            <div class="qr-type-card active" data-type="table">
                <div class="qr-type-icon">🍽️</div>
                <div class="qr-type-title">Table Menu</div>
                <div class="qr-type-desc">Digital menu for tables</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="qr-type-card" data-type="bar">
                <div class="qr-type-icon">🍸</div>
                <div class="qr-type-title">Bar Menu</div>
                <div class="qr-type-desc">Bar drinks menu</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="qr-type-card" data-type="room">
                <div class="qr-type-icon">🛏️</div>
                <div class="qr-type-title">Room Service</div>
                <div class="qr-type-desc">In-room ordering</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="qr-type-card" data-type="payment">
                <div class="qr-type-icon">💳</div>
                <div class="qr-type-title">Payment</div>
                <div class="qr-type-desc">Payment links</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="qr-type-card" data-type="feedback">
                <div class="qr-type-icon">⭐</div>
                <div class="qr-type-title">Feedback</div>
                <div class="qr-type-desc">Customer reviews</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="qr-type-card" data-type="wifi">
                <div class="qr-type-icon">📶</div>
                <div class="qr-type-title">WiFi</div>
                <div class="qr-type-desc">Auto-connect WiFi</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="qr-type-card" data-type="checkin">
                <div class="qr-type-icon">🔑</div>
                <div class="qr-type-title">Guest Check-in</div>
                <div class="qr-type-desc">Self-service check-in</div>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="qr-type-card" data-type="loyalty">
                <div class="qr-type-icon">🎁</div>
                <div class="qr-type-title">Loyalty Card</div>
                <div class="qr-type-desc">Member lookup</div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Configuration Panel -->
        <div class="col-md-4 no-print">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-gear me-2"></i>Configuration</h6>
                </div>
                <div class="card-body">
                    <!-- Table Selection (for table type) -->
                    <div id="tableConfig">
                        <div class="mb-3">
                            <label class="form-label">Select Tables</label>
                            <select class="form-select" id="tableSelect" multiple size="6">
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?= $table['id'] ?>" data-number="<?= htmlspecialchars($table['table_number']) ?>">
                                        Table <?= htmlspecialchars($table['table_number']) ?> (<?= $table['capacity'] ?> seats)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" onclick="selectAllTables()">Select All</button>
                    </div>
                    
                    <!-- Room Selection (for room type) -->
                    <div id="roomConfig" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Select Rooms</label>
                            <select class="form-select" id="roomSelect" multiple size="6">
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= $room['id'] ?>" data-number="<?= htmlspecialchars($room['room_number']) ?>">
                                        Room <?= htmlspecialchars($room['room_number']) ?> - <?= htmlspecialchars($room['room_type'] ?? 'Standard') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" onclick="selectAllRooms()">Select All</button>
                    </div>
                    
                    <!-- Payment Config -->
                    <div id="paymentConfig" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Payment Type</label>
                            <select class="form-select" id="paymentType">
                                <option value="mpesa">M-Pesa Till/Paybill</option>
                                <option value="link">Payment Link</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount (Optional)</label>
                            <input type="number" class="form-control" id="paymentAmount" placeholder="Leave empty for any amount">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference</label>
                            <input type="text" class="form-control" id="paymentRef" placeholder="Invoice/Order number">
                        </div>
                    </div>
                    
                    <!-- WiFi Config -->
                    <div id="wifiConfig" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Network Name (SSID)</label>
                            <input type="text" class="form-control" id="wifiSsid" placeholder="Your WiFi name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="text" class="form-control" id="wifiPassword" placeholder="WiFi password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Security Type</label>
                            <select class="form-select" id="wifiSecurity">
                                <option value="WPA">WPA/WPA2</option>
                                <option value="WEP">WEP</option>
                                <option value="nopass">Open (No Password)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Feedback Config -->
                    <div id="feedbackConfig" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Feedback Type</label>
                            <select class="form-select" id="feedbackType">
                                <option value="general">General Feedback</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="bar">Bar</option>
                                <option value="room">Room/Stay</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Check-in Config -->
                    <div id="checkinConfig" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Select Bookings</label>
                            <select class="form-select" id="bookingSelect" multiple size="6">
                                <?php foreach ($bookings as $booking): ?>
                                    <option value="<?= $booking['id'] ?>" 
                                            data-number="<?= htmlspecialchars($booking['booking_number']) ?>"
                                            data-guest="<?= htmlspecialchars($booking['guest_name']) ?>"
                                            data-room="<?= htmlspecialchars($booking['room_number']) ?>">
                                        <?= htmlspecialchars($booking['booking_number']) ?> - Room <?= htmlspecialchars($booking['room_number']) ?> (<?= htmlspecialchars($booking['guest_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            QR codes allow guests to self check-in/out via their phone
                        </div>
                    </div>
                    
                    <!-- Loyalty Config -->
                    <div id="loyaltyConfig" class="d-none">
                        <div class="mb-3">
                            <label class="form-label">Member ID or Phone</label>
                            <input type="text" class="form-control" id="loyaltyId" placeholder="Enter member ID or phone">
                        </div>
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle me-1"></i>
                            Generate QR for quick member lookup at POS
                        </div>
                    </div>
                    
                    <!-- Common Options -->
                    <hr>
                    <div class="mb-3">
                        <label class="form-label">QR Code Size</label>
                        <select class="form-select" id="qrSize">
                            <option value="150">Small (150px)</option>
                            <option value="200" selected>Medium (200px)</option>
                            <option value="300">Large (300px)</option>
                            <option value="400">Extra Large (400px)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Include Label</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="includeLabel" checked>
                            <label class="form-check-label" for="includeLabel">Show table/room number below QR</label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="generateQR()">
                            <i class="bi bi-qr-code me-2"></i>Generate QR Code
                        </button>
                        <button class="btn btn-outline-success" onclick="generateBatch()">
                            <i class="bi bi-grid me-2"></i>Generate Batch
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Preview Panel -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center no-print">
                    <h6 class="mb-0"><i class="bi bi-eye me-2"></i>Preview</h6>
                    <div>
                        <button class="btn btn-outline-secondary btn-sm me-2" onclick="downloadQR()">
                            <i class="bi bi-download me-1"></i>Download
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i>Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Single QR Preview -->
                    <div id="singlePreview" class="text-center">
                        <div class="qr-preview mx-auto" style="max-width: 350px;">
                            <img id="qrImage" src="<?= $qrApiUrl ?>?size=200x200&data=<?= urlencode($baseUrl) ?>" alt="QR Code">
                            <div id="qrLabel" class="mt-3">
                                <h5 class="mb-1">Scan for Menu</h5>
                                <p class="text-muted mb-0">Point your camera at the QR code</p>
                            </div>
                            <div class="qr-url" id="qrUrl"><?= $baseUrl ?></div>
                        </div>
                    </div>
                    
                    <!-- Batch Preview -->
                    <div id="batchPreview" class="d-none">
                        <div class="batch-grid" id="batchGrid">
                            <!-- Generated dynamically -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?= $baseUrl ?>';
const QR_API = '<?= $qrApiUrl ?>';
let currentType = 'table';

// Type selection
document.querySelectorAll('.qr-type-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.qr-type-card').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        currentType = this.dataset.type;
        
        // Show/hide config panels
        document.getElementById('tableConfig').classList.toggle('d-none', !['table', 'bar'].includes(currentType));
        document.getElementById('roomConfig').classList.toggle('d-none', currentType !== 'room');
        document.getElementById('paymentConfig').classList.toggle('d-none', currentType !== 'payment');
        document.getElementById('wifiConfig').classList.toggle('d-none', currentType !== 'wifi');
        document.getElementById('feedbackConfig').classList.toggle('d-none', currentType !== 'feedback');
        document.getElementById('checkinConfig').classList.toggle('d-none', currentType !== 'checkin');
        document.getElementById('loyaltyConfig').classList.toggle('d-none', currentType !== 'loyalty');
    });
});

function selectAllTables() {
    const select = document.getElementById('tableSelect');
    for (let option of select.options) {
        option.selected = true;
    }
}

function selectAllRooms() {
    const select = document.getElementById('roomSelect');
    for (let option of select.options) {
        option.selected = true;
    }
}

function getQRUrl(type, data) {
    switch (type) {
        case 'table':
            return `${BASE_URL}/digital-menu.php?table=${data.id}`;
        case 'bar':
            return `${BASE_URL}/digital-menu.php?table=${data.id}&type=bar`;
        case 'room':
            return `${BASE_URL}/room-service-menu.php?room=${data.id}`;
        case 'payment':
            const paymentType = document.getElementById('paymentType').value;
            const amount = document.getElementById('paymentAmount').value;
            const ref = document.getElementById('paymentRef').value;
            if (paymentType === 'mpesa') {
                // M-Pesa QR format
                return `${BASE_URL}/pay.php?amount=${amount}&ref=${encodeURIComponent(ref)}`;
            }
            return `${BASE_URL}/payment-link.php?amount=${amount}&ref=${encodeURIComponent(ref)}`;
        case 'feedback':
            const feedbackType = document.getElementById('feedbackType').value;
            return `${BASE_URL}/feedback.php?type=${feedbackType}`;
        case 'wifi':
            const ssid = document.getElementById('wifiSsid').value;
            const password = document.getElementById('wifiPassword').value;
            const security = document.getElementById('wifiSecurity').value;
            // WiFi QR format: WIFI:T:WPA;S:mynetwork;P:mypass;;
            return `WIFI:T:${security};S:${ssid};P:${password};;`;
        case 'checkin':
            // Generate check-in URL with secure token
            const bookingNum = data.bookingNumber;
            const token = data.token; // MD5 hash generated server-side
            return `${BASE_URL}/guest-checkin.php?token=${token}`;
        case 'loyalty':
            const loyaltyId = document.getElementById('loyaltyId').value;
            return `${BASE_URL}/pos.php?member=${encodeURIComponent(loyaltyId)}`;
        default:
            return BASE_URL;
    }
}

function generateQR() {
    const size = document.getElementById('qrSize').value;
    let url = '';
    let label = '';
    
    if (currentType === 'table' || currentType === 'bar') {
        const select = document.getElementById('tableSelect');
        const selected = select.selectedOptions[0];
        if (!selected) {
            alert('Please select a table');
            return;
        }
        url = getQRUrl(currentType, {id: selected.value});
        label = `Table ${selected.dataset.number}`;
    } else if (currentType === 'room') {
        const select = document.getElementById('roomSelect');
        const selected = select.selectedOptions[0];
        if (!selected) {
            alert('Please select a room');
            return;
        }
        url = getQRUrl(currentType, {id: selected.value});
        label = `Room ${selected.dataset.number}`;
    } else if (currentType === 'wifi') {
        const ssid = document.getElementById('wifiSsid').value;
        if (!ssid) {
            alert('Please enter WiFi network name');
            return;
        }
        url = getQRUrl(currentType, {});
        label = `WiFi: ${ssid}`;
    } else if (currentType === 'checkin') {
        const select = document.getElementById('bookingSelect');
        const selected = select.selectedOptions[0];
        if (!selected) {
            alert('Please select a booking');
            return;
        }
        // Generate token (simple MD5 simulation - in production use PHP)
        const bookingNumber = selected.dataset.number;
        const token = md5(bookingNumber + 'guest_checkin_secret');
        url = getQRUrl(currentType, {bookingNumber: bookingNumber, token: token});
        label = `${selected.dataset.number} - Room ${selected.dataset.room}`;
    } else if (currentType === 'loyalty') {
        const loyaltyId = document.getElementById('loyaltyId').value;
        if (!loyaltyId) {
            alert('Please enter member ID or phone');
            return;
        }
        url = getQRUrl(currentType, {});
        label = `Member: ${loyaltyId}`;
    } else {
        url = getQRUrl(currentType, {});
        label = currentType.charAt(0).toUpperCase() + currentType.slice(1);
    }
    
    const qrUrl = `${QR_API}?size=${size}x${size}&data=${encodeURIComponent(url)}`;
    document.getElementById('qrImage').src = qrUrl;
    document.getElementById('qrUrl').textContent = url;
    
    const labelEl = document.getElementById('qrLabel');
    if (document.getElementById('includeLabel').checked) {
        labelEl.innerHTML = `<h5 class="mb-1">${label}</h5><p class="text-muted mb-0">Scan to ${currentType === 'wifi' ? 'connect' : 'order'}</p>`;
        labelEl.classList.remove('d-none');
    } else {
        labelEl.classList.add('d-none');
    }
    
    document.getElementById('singlePreview').classList.remove('d-none');
    document.getElementById('batchPreview').classList.add('d-none');
}

function generateBatch() {
    const size = document.getElementById('qrSize').value;
    const includeLabel = document.getElementById('includeLabel').checked;
    let items = [];
    
    if (currentType === 'table' || currentType === 'bar') {
        const select = document.getElementById('tableSelect');
        for (let option of select.selectedOptions) {
            items.push({
                id: option.value,
                label: `Table ${option.dataset.number}`,
                url: getQRUrl(currentType, {id: option.value})
            });
        }
    } else if (currentType === 'room') {
        const select = document.getElementById('roomSelect');
        for (let option of select.selectedOptions) {
            items.push({
                id: option.value,
                label: `Room ${option.dataset.number}`,
                url: getQRUrl(currentType, {id: option.value})
            });
        }
    }
    
    if (items.length === 0) {
        alert('Please select items to generate');
        return;
    }
    
    const grid = document.getElementById('batchGrid');
    grid.innerHTML = items.map(item => `
        <div class="batch-item">
            <img src="${QR_API}?size=${size}x${size}&data=${encodeURIComponent(item.url)}" alt="${item.label}">
            ${includeLabel ? `<div class="label">${item.label}</div>` : ''}
        </div>
    `).join('');
    
    document.getElementById('singlePreview').classList.add('d-none');
    document.getElementById('batchPreview').classList.remove('d-none');
}

function downloadQR() {
    const img = document.getElementById('qrImage');
    const link = document.createElement('a');
    link.href = img.src;
    link.download = `qr-code-${currentType}-${Date.now()}.png`;
    link.click();
}

// Simple MD5 implementation for client-side token generation
function md5(string) {
    function rotateLeft(lValue, iShiftBits) {
        return (lValue << iShiftBits) | (lValue >>> (32 - iShiftBits));
    }
    function addUnsigned(lX, lY) {
        var lX8 = (lX & 0x80000000), lY8 = (lY & 0x80000000);
        var lX4 = (lX & 0x40000000), lY4 = (lY & 0x40000000);
        var lResult = (lX & 0x3FFFFFFF) + (lY & 0x3FFFFFFF);
        if (lX4 & lY4) return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
        if (lX4 | lY4) {
            if (lResult & 0x40000000) return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
            else return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
        } else return (lResult ^ lX8 ^ lY8);
    }
    function F(x, y, z) { return (x & y) | ((~x) & z); }
    function G(x, y, z) { return (x & z) | (y & (~z)); }
    function H(x, y, z) { return (x ^ y ^ z); }
    function I(x, y, z) { return (y ^ (x | (~z))); }
    function FF(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(F(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function GG(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(G(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function HH(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(H(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function II(a, b, c, d, x, s, ac) {
        a = addUnsigned(a, addUnsigned(addUnsigned(I(b, c, d), x), ac));
        return addUnsigned(rotateLeft(a, s), b);
    }
    function convertToWordArray(string) {
        var lWordCount, lMessageLength = string.length;
        var lNumberOfWords_temp1 = lMessageLength + 8;
        var lNumberOfWords_temp2 = (lNumberOfWords_temp1 - (lNumberOfWords_temp1 % 64)) / 64;
        var lNumberOfWords = (lNumberOfWords_temp2 + 1) * 16;
        var lWordArray = Array(lNumberOfWords - 1);
        var lBytePosition = 0, lByteCount = 0;
        while (lByteCount < lMessageLength) {
            lWordCount = (lByteCount - (lByteCount % 4)) / 4;
            lBytePosition = (lByteCount % 4) * 8;
            lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount) << lBytePosition));
            lByteCount++;
        }
        lWordCount = (lByteCount - (lByteCount % 4)) / 4;
        lBytePosition = (lByteCount % 4) * 8;
        lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80 << lBytePosition);
        lWordArray[lNumberOfWords - 2] = lMessageLength << 3;
        lWordArray[lNumberOfWords - 1] = lMessageLength >>> 29;
        return lWordArray;
    }
    function wordToHex(lValue) {
        var WordToHexValue = "", WordToHexValue_temp = "", lByte, lCount;
        for (lCount = 0; lCount <= 3; lCount++) {
            lByte = (lValue >>> (lCount * 8)) & 255;
            WordToHexValue_temp = "0" + lByte.toString(16);
            WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length - 2, 2);
        }
        return WordToHexValue;
    }
    var x = convertToWordArray(string);
    var a = 0x67452301, b = 0xEFCDAB89, c = 0x98BADCFE, d = 0x10325476;
    var S11 = 7, S12 = 12, S13 = 17, S14 = 22;
    var S21 = 5, S22 = 9, S23 = 14, S24 = 20;
    var S31 = 4, S32 = 11, S33 = 16, S34 = 23;
    var S41 = 6, S42 = 10, S43 = 15, S44 = 21;
    for (var k = 0; k < x.length; k += 16) {
        var AA = a, BB = b, CC = c, DD = d;
        a = FF(a, b, c, d, x[k + 0], S11, 0xD76AA478); d = FF(d, a, b, c, x[k + 1], S12, 0xE8C7B756);
        c = FF(c, d, a, b, x[k + 2], S13, 0x242070DB); b = FF(b, c, d, a, x[k + 3], S14, 0xC1BDCEEE);
        a = FF(a, b, c, d, x[k + 4], S11, 0xF57C0FAF); d = FF(d, a, b, c, x[k + 5], S12, 0x4787C62A);
        c = FF(c, d, a, b, x[k + 6], S13, 0xA8304613); b = FF(b, c, d, a, x[k + 7], S14, 0xFD469501);
        a = FF(a, b, c, d, x[k + 8], S11, 0x698098D8); d = FF(d, a, b, c, x[k + 9], S12, 0x8B44F7AF);
        c = FF(c, d, a, b, x[k + 10], S13, 0xFFFF5BB1); b = FF(b, c, d, a, x[k + 11], S14, 0x895CD7BE);
        a = FF(a, b, c, d, x[k + 12], S11, 0x6B901122); d = FF(d, a, b, c, x[k + 13], S12, 0xFD987193);
        c = FF(c, d, a, b, x[k + 14], S13, 0xA679438E); b = FF(b, c, d, a, x[k + 15], S14, 0x49B40821);
        a = GG(a, b, c, d, x[k + 1], S21, 0xF61E2562); d = GG(d, a, b, c, x[k + 6], S22, 0xC040B340);
        c = GG(c, d, a, b, x[k + 11], S23, 0x265E5A51); b = GG(b, c, d, a, x[k + 0], S24, 0xE9B6C7AA);
        a = GG(a, b, c, d, x[k + 5], S21, 0xD62F105D); d = GG(d, a, b, c, x[k + 10], S22, 0x2441453);
        c = GG(c, d, a, b, x[k + 15], S23, 0xD8A1E681); b = GG(b, c, d, a, x[k + 4], S24, 0xE7D3FBC8);
        a = GG(a, b, c, d, x[k + 9], S21, 0x21E1CDE6); d = GG(d, a, b, c, x[k + 14], S22, 0xC33707D6);
        c = GG(c, d, a, b, x[k + 3], S23, 0xF4D50D87); b = GG(b, c, d, a, x[k + 8], S24, 0x455A14ED);
        a = GG(a, b, c, d, x[k + 13], S21, 0xA9E3E905); d = GG(d, a, b, c, x[k + 2], S22, 0xFCEFA3F8);
        c = GG(c, d, a, b, x[k + 7], S23, 0x676F02D9); b = GG(b, c, d, a, x[k + 12], S24, 0x8D2A4C8A);
        a = HH(a, b, c, d, x[k + 5], S31, 0xFFFA3942); d = HH(d, a, b, c, x[k + 8], S32, 0x8771F681);
        c = HH(c, d, a, b, x[k + 11], S33, 0x6D9D6122); b = HH(b, c, d, a, x[k + 14], S34, 0xFDE5380C);
        a = HH(a, b, c, d, x[k + 1], S31, 0xA4BEEA44); d = HH(d, a, b, c, x[k + 4], S32, 0x4BDECFA9);
        c = HH(c, d, a, b, x[k + 7], S33, 0xF6BB4B60); b = HH(b, c, d, a, x[k + 10], S34, 0xBEBFBC70);
        a = HH(a, b, c, d, x[k + 13], S31, 0x289B7EC6); d = HH(d, a, b, c, x[k + 0], S32, 0xEAA127FA);
        c = HH(c, d, a, b, x[k + 3], S33, 0xD4EF3085); b = HH(b, c, d, a, x[k + 6], S34, 0x4881D05);
        a = HH(a, b, c, d, x[k + 9], S31, 0xD9D4D039); d = HH(d, a, b, c, x[k + 12], S32, 0xE6DB99E5);
        c = HH(c, d, a, b, x[k + 15], S33, 0x1FA27CF8); b = HH(b, c, d, a, x[k + 2], S34, 0xC4AC5665);
        a = II(a, b, c, d, x[k + 0], S41, 0xF4292244); d = II(d, a, b, c, x[k + 7], S42, 0x432AFF97);
        c = II(c, d, a, b, x[k + 14], S43, 0xAB9423A7); b = II(b, c, d, a, x[k + 5], S44, 0xFC93A039);
        a = II(a, b, c, d, x[k + 12], S41, 0x655B59C3); d = II(d, a, b, c, x[k + 3], S42, 0x8F0CCC92);
        c = II(c, d, a, b, x[k + 10], S43, 0xFFEFF47D); b = II(b, c, d, a, x[k + 1], S44, 0x85845DD1);
        a = II(a, b, c, d, x[k + 8], S41, 0x6FA87E4F); d = II(d, a, b, c, x[k + 15], S42, 0xFE2CE6E0);
        c = II(c, d, a, b, x[k + 6], S43, 0xA3014314); b = II(b, c, d, a, x[k + 13], S44, 0x4E0811A1);
        a = II(a, b, c, d, x[k + 4], S41, 0xF7537E82); d = II(d, a, b, c, x[k + 11], S42, 0xBD3AF235);
        c = II(c, d, a, b, x[k + 2], S43, 0x2AD7D2BB); b = II(b, c, d, a, x[k + 9], S44, 0xEB86D391);
        a = addUnsigned(a, AA); b = addUnsigned(b, BB); c = addUnsigned(c, CC); d = addUnsigned(d, DD);
    }
    return (wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d)).toLowerCase();
}
</script>

<?php include 'includes/footer.php'; ?>
