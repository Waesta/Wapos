<?php
/**
 * WAPOS - Branding Helper Functions
 * Functions to retrieve and display business branding elements
 */

/**
 * Get the business logo URL
 * @param string $variant 'default' or 'light'
 * @return string|null Logo URL or null if not set
 */
function getBusinessLogo($variant = 'default') {
    if ($variant === 'light') {
        $logo = SettingsStore::get('branding_logo_light');
        if ($logo) return $logo;
    }
    return SettingsStore::get('branding_logo');
}

/**
 * Get the business name
 * @return string Business name
 */
function getBusinessName() {
    return SettingsStore::get('branding_business_name') 
        ?? SettingsStore::get('business_name') 
        ?? 'WAPOS';
}

/**
 * Get the business tagline
 * @return string|null Tagline or null
 */
function getBusinessTagline() {
    return SettingsStore::get('branding_tagline');
}

/**
 * Get primary brand color
 * @return string Hex color code
 */
function getPrimaryColor() {
    return SettingsStore::get('branding_primary_color') ?? '#2563eb';
}

/**
 * Get secondary brand color
 * @return string Hex color code
 */
function getSecondaryColor() {
    return SettingsStore::get('branding_secondary_color') ?? '#1e293b';
}

/**
 * Render logo HTML for receipts
 * @param int $maxHeight Maximum height in pixels
 * @return string HTML string
 */
function renderReceiptLogo($maxHeight = 60) {
    $logo = getBusinessLogo();
    $name = getBusinessName();
    $tagline = getBusinessTagline();
    
    $html = '<div class="receipt-header text-center">';
    
    if ($logo) {
        $html .= '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($name) . '" style="max-height: ' . $maxHeight . 'px; max-width: 100%;">';
        $html .= '<div class="business-name fw-bold mt-1">' . htmlspecialchars($name) . '</div>';
    } else {
        $html .= '<div class="business-name fw-bold" style="font-size: 1.2em;">' . htmlspecialchars($name) . '</div>';
    }
    
    if ($tagline) {
        $html .= '<div class="tagline small text-muted">' . htmlspecialchars($tagline) . '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render logo HTML for invoices/reports (PDF compatible)
 * @param string $variant 'default' or 'light'
 * @return string HTML string
 */
function renderInvoiceLogo($variant = 'default') {
    $logo = getBusinessLogo($variant);
    $name = getBusinessName();
    $primaryColor = getPrimaryColor();
    
    if ($logo) {
        // Convert relative URL to absolute path for PDF generation
        $logoPath = $logo;
        if (strpos($logo, '/wapos/') === 0) {
            $logoPath = $_SERVER['DOCUMENT_ROOT'] . $logo;
        }
        
        return '<img src="' . htmlspecialchars($logoPath) . '" alt="' . htmlspecialchars($name) . '" style="max-height: 80px; max-width: 250px;">';
    }
    
    return '<h2 style="color: ' . htmlspecialchars($primaryColor) . '; margin: 0;">' . htmlspecialchars($name) . '</h2>';
}

/**
 * Get logo as base64 for PDF embedding
 * @param string $variant 'default' or 'light'
 * @return string|null Base64 encoded image or null
 */
function getLogoBase64($variant = 'default') {
    $logo = getBusinessLogo($variant);
    if (!$logo) return null;
    
    // Convert URL to file path
    $filePath = $_SERVER['DOCUMENT_ROOT'] . $logo;
    if (!file_exists($filePath)) {
        return null;
    }
    
    $imageData = file_get_contents($filePath);
    $mimeType = mime_content_type($filePath);
    
    return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
}

/**
 * Render product image or placeholder
 * @param string|null $imageUrl Product image URL
 * @param string $productName Product name for alt text
 * @param string $size 'small', 'medium', 'large'
 * @return string HTML string
 */
function renderProductImage($imageUrl, $productName = 'Product', $size = 'medium') {
    $sizes = [
        'small' => ['width' => 50, 'height' => 50],
        'medium' => ['width' => 100, 'height' => 100],
        'large' => ['width' => 200, 'height' => 200]
    ];
    
    $dim = $sizes[$size] ?? $sizes['medium'];
    
    if ($imageUrl) {
        return '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($productName) . '" 
                style="width: ' . $dim['width'] . 'px; height: ' . $dim['height'] . 'px; object-fit: cover; border-radius: 8px;"
                onerror="this.onerror=null; this.src=\'/wapos/assets/images/placeholder-product.png\';">';
    }
    
    // Return placeholder
    $bgColor = '#f1f5f9';
    $iconColor = '#94a3b8';
    
    return '<div style="width: ' . $dim['width'] . 'px; height: ' . $dim['height'] . 'px; 
            background: ' . $bgColor . '; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-image" style="font-size: ' . ($dim['width'] / 3) . 'px; color: ' . $iconColor . ';"></i>
        </div>';
}

/**
 * Get all branding settings as array
 * @return array Branding settings
 */
function getBrandingSettings() {
    return [
        'logo' => getBusinessLogo(),
        'logo_light' => getBusinessLogo('light'),
        'business_name' => getBusinessName(),
        'tagline' => getBusinessTagline(),
        'primary_color' => getPrimaryColor(),
        'secondary_color' => getSecondaryColor()
    ];
}
