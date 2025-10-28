<?php
/**
 * QR Code Generator for Receipts
 * Generates QR codes for digital receipts and customer feedback
 */

function generateReceiptQR($saleId, $saleNumber, $businessWebsite = '') {
    // Create receipt URL for digital access
    $receiptUrl = (!empty($businessWebsite) ? $businessWebsite : 'http://localhost/wapos') . '/digital-receipt.php?id=' . $saleId . '&token=' . md5($saleNumber . 'receipt_token');
    
    // Use Google Charts API for QR code generation (free and reliable)
    $qrCodeUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($receiptUrl) . '&choe=UTF-8';
    
    return $qrCodeUrl;
}

function generateFeedbackQR($saleId, $businessWebsite = '') {
    // Create feedback URL
    $feedbackUrl = (!empty($businessWebsite) ? $businessWebsite : 'http://localhost/wapos') . '/feedback.php?sale=' . $saleId;
    
    // Generate QR code for feedback
    $qrCodeUrl = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($feedbackUrl) . '&choe=UTF-8';
    
    return $qrCodeUrl;
}

function generatePromotionQR($promoCode, $businessWebsite = '') {
    // Create promotion URL
    $promoUrl = (!empty($businessWebsite) ? $businessWebsite : 'http://localhost/wapos') . '/promo.php?code=' . urlencode($promoCode);
    
    // Generate QR code for promotion
    $qrCodeUrl = 'https://chart.googleapis.com/chart?chs=120x120&cht=qr&chl=' . urlencode($promoUrl) . '&choe=UTF-8';
    
    return $qrCodeUrl;
}

function generateContactQR($businessInfo) {
    // Create vCard format for contact information
    $vcard = "BEGIN:VCARD\n";
    $vcard .= "VERSION:3.0\n";
    $vcard .= "FN:" . ($businessInfo['business_name'] ?? 'Business') . "\n";
    if (!empty($businessInfo['business_phone'])) {
        $vcard .= "TEL:" . $businessInfo['business_phone'] . "\n";
    }
    if (!empty($businessInfo['business_email'])) {
        $vcard .= "EMAIL:" . $businessInfo['business_email'] . "\n";
    }
    if (!empty($businessInfo['business_address'])) {
        $vcard .= "ADR:;;" . str_replace("\n", " ", $businessInfo['business_address']) . "\n";
    }
    if (!empty($businessInfo['business_website'])) {
        $vcard .= "URL:" . $businessInfo['business_website'] . "\n";
    }
    $vcard .= "END:VCARD";
    
    // Generate QR code for contact info
    $qrCodeUrl = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($vcard) . '&choe=UTF-8';
    
    return $qrCodeUrl;
}
?>
