<?php
require_once 'includes/bootstrap.php';
$auth->requireLogin();

use App\Services\RoomBookingService;
use Dompdf\Dompdf;
use Dompdf\Options;

$bookingId = filter_input(INPUT_GET, 'booking_id', FILTER_VALIDATE_INT) ?? 0;
if ($bookingId <= 0) {
    die('Invalid booking reference');
}

if (!class_exists(Dompdf::class)) {
    die('PDF generator not available. Please run composer install to set up dompdf/dompdf.');
}

$database = Database::getInstance();
$pdo = $database->getConnection();
$bookingService = new RoomBookingService($pdo);

$invoiceData = $bookingService->getInvoiceData($bookingId);
$booking = $invoiceData['booking'];
$folioEntries = $invoiceData['folio'] ?? [];
$totals = $invoiceData['totals'] ?? ['total_charges' => 0, 'total_payments' => 0, 'balance_due' => 0];
$mode = $invoiceData['mode'] ?? 'legacy';

if (!$booking) {
    die('Booking not found');
}

$settingsRaw = $database->fetchAll("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsRaw as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

$currencySymbol = $settings['currency_symbol'] ?? ($settings['currency'] ?? CURRENCY_SYMBOL ?? '$');
$businessName = $settings['business_name'] ?? APP_NAME;
$logoPath = $settings['business_logo'] ?? null;

$formatCurrency = function ($value) use ($currencySymbol) {
    return sprintf('%s %s', $currencySymbol, number_format((float)$value, 2));
};

$totalCharges = (float)($totals['total_charges'] ?? 0);
$totalPayments = (float)($totals['total_payments'] ?? 0);
$balanceDue = (float)($totals['balance_due'] ?? 0);

$folioRows = '';
if (!empty($folioEntries)) {
    foreach ($folioEntries as $entry) {
        $amount = (float)($entry['amount'] ?? 0);
        $prefix = $amount < 0 ? '-' : '';
        $type = htmlspecialchars(ucwords(str_replace('_', ' ', $entry['item_type'] ?? 'Entry')));
        $description = htmlspecialchars($entry['description'] ?? $type);
        $date = !empty($entry['date_charged']) ? formatDate($entry['date_charged'], 'd M Y') : '';
        $folioRows .= sprintf(
            "<tr><td>%s</td><td>%s</td><td>%s</td><td style='text-align:right'>%s%s</td></tr>",
            htmlspecialchars($date),
            $type,
            $description,
            $prefix,
            $formatCurrency(abs($amount))
        );
    }
} else {
    $folioRows = "<tr><td colspan='4' style='text-align:center;padding:16px;font-style:italic;'>No folio entries recorded yet.</td></tr>";
}

$checkInDate = !empty($booking['check_in_date']) ? formatDate($booking['check_in_date'], 'd M Y') : '';
$checkOutDate = !empty($booking['check_out_date']) ? formatDate($booking['check_out_date'], 'd M Y') : '';
$checkInActual = !empty($booking['actual_check_in']) ? formatDate($booking['actual_check_in'], 'd M Y H:i') : (!empty($booking['check_in_time']) ? formatDate($booking['check_in_time'], 'd M Y H:i') : '');
$checkOutActual = !empty($booking['actual_check_out']) ? formatDate($booking['actual_check_out'], 'd M Y H:i') : (!empty($booking['check_out_time']) ? formatDate($booking['check_out_time'], 'd M Y H:i') : '');

$paymentStatus = strtolower($booking['payment_status'] ?? 'pending');
$badgeClass = $paymentStatus === 'paid' ? 'badge badge-paid' : 'badge badge-pending';
$badgeLabel = strtoupper($paymentStatus === 'paid' ? 'Paid in Full' : 'Pending Balance');

$logoHtml = '';
if (!empty($logoPath)) {
    $logoHtml = "<img src='" . htmlspecialchars($logoPath) . "' alt='Logo' style='max-height:80px;margin-bottom:10px;'>";
}

$businessAddress = !empty($settings['business_address']) ? nl2br(htmlspecialchars($settings['business_address'])) : '';
$businessContactParts = [];
if (!empty($settings['business_phone'])) {
    $businessContactParts[] = htmlspecialchars($settings['business_phone']);
}
if (!empty($settings['business_email'])) {
    $businessContactParts[] = htmlspecialchars($settings['business_email']);
}
$businessContactLine = implode(' | ', $businessContactParts);

$guestSummaryParts = [];
if (!empty($booking['guest_name'])) {
    $guestSummaryParts[] = htmlspecialchars($booking['guest_name']);
}
if (!empty($booking['guest_phone'])) {
    $guestSummaryParts[] = htmlspecialchars($booking['guest_phone']);
}
if (!empty($booking['guest_email'])) {
    $guestSummaryParts[] = htmlspecialchars($booking['guest_email']);
}
$guestSummary = implode(' • ', $guestSummaryParts);
$guestIdNumber = htmlspecialchars($booking['guest_id_number'] ?? 'N/A');
$roomNumber = htmlspecialchars($booking['room_number'] ?? '');
$roomType = htmlspecialchars($booking['room_type_name'] ?? '');
$bookingNumber = htmlspecialchars($booking['booking_number'] ?? '');

$adultCount = (int)($booking['adults'] ?? 1);
$childCount = (int)($booking['children'] ?? 0);
$guestCountLabel = $adultCount . ' adult' . ($adultCount !== 1 ? 's' : '');
if ($childCount > 0) {
    $guestCountLabel .= ', ' . $childCount . ' child' . ($childCount !== 1 ? 'ren' : '');
}

$specialRequests = !empty($booking['special_requests'])
    ? '<div class="notes"><strong>Special Requests:</strong><br>' . nl2br(htmlspecialchars($booking['special_requests'])) . '</div>'
    : '';

$legacyNotice = $mode === 'legacy'
    ? '<div class="legacy-alert">Legacy folio detected: new folio ledger will adopt these charges after migration completes.</div>'
    : '';

$roomChargesFormatted = $formatCurrency($totalCharges);
$paymentsFormatted = $formatCurrency($totalPayments);
$balanceFormatted = $formatCurrency($balanceDue);
$generatedAt = date('d/m/Y H:i');
$download = filter_input(INPUT_GET, 'download', FILTER_VALIDATE_BOOLEAN) ?? false;
$appNameEsc = htmlspecialchars(APP_NAME);

$css = <<<CSS
body {
    font-family: 'Helvetica Neue', Arial, sans-serif;
    color: #222;
    font-size: 12px;
    line-height: 1.6;
}
.header {
    text-align: center;
    border-bottom: 3px solid #4c6ef5;
    padding-bottom: 20px;
    margin-bottom: 25px;
}
.header h1 {
    margin: 10px 0 0;
    font-size: 26px;
    letter-spacing: 1px;
}
.business-meta {
    margin-top: 6px;
    font-size: 12px;
    color: #555;
}
.invoice-title {
    background: #f1f3f5;
    padding: 14px 20px;
    border-left: 5px solid #4c6ef5;
    margin-bottom: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.invoice-title h2 {
    margin: 0;
    font-size: 20px;
    text-transform: uppercase;
}
.badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.badge-paid {
    background: #d3f9d8;
    color: #2b8a3e;
}
.badge-pending {
    background: #fff3bf;
    color: #e67700;
}
.info-grid {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 24px;
}
.card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 4px rgba(15, 23, 42, 0.08);
    flex: 1;
}
.card h3 {
    margin-top: 0;
    font-size: 15px;
    text-transform: uppercase;
    color: #495057;
    letter-spacing: 0.6px;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}
th, td {
    padding: 10px 12px;
    border-bottom: 1px solid #dee2e6;
}
th {
    background: #edf2ff;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 11px;
    color: #495057;
}
.totals {
    margin-top: 26px;
    width: 45%;
    margin-left: auto;
}
.totals td {
    padding: 8px 10px;
}
.totals tr.balance {
    font-weight: 700;
    font-size: 15px;
    color: #d9480f;
}
.notes {
    margin-top: 24px;
    padding: 18px;
    background: #f8f0fc;
    border-left: 4px solid #7048e8;
    border-radius: 6px;
    font-size: 12px;
    color: #5f3dc4;
}
.footer {
    margin-top: 40px;
    text-align: center;
    font-size: 11px;
    color: #868e96;
}
.legacy-alert {
    margin-bottom: 16px;
    padding: 12px 14px;
    background: #fff4e6;
    border-left: 4px solid #f08c00;
    border-radius: 6px;
    color: #995c00;
    font-size: 11px;
}
CSS;

$checkMeta = [];
if ($checkInActual) {
    $checkMeta[] = '<div><strong>Checked In:</strong> ' . htmlspecialchars($checkInActual) . '</div>';
}
if ($checkOutActual) {
    $checkMeta[] = '<div><strong>Checked Out:</strong> ' . htmlspecialchars($checkOutActual) . '</div>';
}
$checkMetaHtml = implode('', $checkMeta);

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>{$css}</style>
</head>
<body>
    <div class="header">
        {$logoHtml}
        <h1>{$businessName}</h1>
        <div class="business-meta">{$businessAddress}</div>
        <div class="business-meta">{$businessContactLine}</div>
    </div>

    <div class="invoice-title">
        <div>
            <h2>Room Booking Invoice</h2>
            <div style="font-size:12px;color:#6c757d;">Booking #{$bookingNumber}</div>
        </div>
        <div><span class="{$badgeClass}">{$badgeLabel}</span></div>
    </div>

    {$legacyNotice}

    <div class="info-grid">
        <div class="card">
            <h3>Guest</h3>
            <div>{$guestSummary}</div>
            <div><strong>ID:</strong> {$guestIdNumber}</div>
        </div>
        <div class="card">
            <h3>Stay Details</h3>
            <div><strong>Room:</strong> {$roomNumber} ({$roomType})</div>
            <div><strong>Check-in:</strong> {$checkInDate}</div>
            <div><strong>Check-out:</strong> {$checkOutDate}</div>
            <div><strong>Guests:</strong> {$guestCountLabel}</div>
        </div>
    </div>

    <h3 style="margin-top:30px;">Folio Summary</h3>
    <table>
        <thead>
            <tr>
                <th style="width:20%;">Date</th>
                <th style="width:20%;">Type</th>
                <th>Description</th>
                <th style="width:20%; text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            {$folioRows}
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Room Charges</td>
            <td style="text-align:right;">{$roomChargesFormatted}</td>
        </tr>
        <tr>
            <td>Payments & Deposits</td>
            <td style="text-align:right;">{$paymentsFormatted}</td>
        </tr>
        <tr class="balance">
            <td>Balance Due</td>
            <td style="text-align:right;">{$balanceFormatted}</td>
        </tr>
    </table>

    <div style="margin-top:18px;">{$checkMetaHtml}</div>
    {$specialRequests}

    <div class="footer">
        <div>Generated on {$generatedAt}</div>
        <div>Powered by {$appNameEsc}</div>
    </div>
</body>
</html>
HTML;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$bookingSlug = $bookingNumber !== '' ? preg_replace('/[^A-Za-z0-9_-]+/', '-', $bookingNumber) : (string)$bookingId;
$pdfName = sprintf('room-invoice-%s.pdf', strtolower($bookingSlug ?: 'booking'));

$dompdf->stream($pdfName, ['Attachment' => $download]);

exit;
