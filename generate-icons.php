<?php
/**
 * PWA Icon Generator
 * Generates all required icon sizes for the PWA manifest
 * Run once: php generate-icons.php or visit in browser
 */

$sizes = [72, 96, 128, 144, 152, 192, 384, 512];
$outputDir = __DIR__ . '/assets/images/icons/';

// Ensure directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Create icons using GD library
foreach ($sizes as $size) {
    $image = imagecreatetruecolor($size, $size);
    
    // Enable alpha blending
    imagealphablending($image, false);
    imagesavealpha($image, true);
    
    // Colors
    $bgColor = imagecolorallocate($image, 37, 99, 235); // #2563eb
    $textColor = imagecolorallocate($image, 255, 255, 255);
    
    // Fill background
    imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);
    
    // Add rounded corners effect (simple)
    $cornerRadius = $size * 0.15;
    
    // Draw "W" text
    $fontSize = $size * 0.5;
    $text = "W";
    
    // Calculate text position (center)
    $bbox = imagettfbbox($fontSize, 0, __DIR__ . '/assets/fonts/arial.ttf', $text);
    
    // If font not available, use built-in font
    if ($bbox === false) {
        // Use built-in font
        $fontWidth = imagefontwidth(5) * strlen($text);
        $fontHeight = imagefontheight(5);
        $x = ($size - $fontWidth) / 2;
        $y = ($size - $fontHeight) / 2;
        
        // Scale up the built-in font by drawing multiple times
        $scale = max(1, floor($size / 20));
        for ($i = 0; $i < $scale; $i++) {
            for ($j = 0; $j < $scale; $j++) {
                imagestring($image, 5, $x + $i, $y + $j, $text, $textColor);
            }
        }
    } else {
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];
        $x = ($size - $textWidth) / 2;
        $y = ($size + $textHeight) / 2;
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, __DIR__ . '/assets/fonts/arial.ttf', $text);
    }
    
    // Save PNG
    $filename = $outputDir . "icon-{$size}.png";
    imagepng($image, $filename);
    imagedestroy($image);
    
    echo "Created: icon-{$size}.png\n";
}

// Create maskable icons (with padding)
foreach ([192, 512] as $size) {
    $image = imagecreatetruecolor($size, $size);
    
    imagealphablending($image, false);
    imagesavealpha($image, true);
    
    $bgColor = imagecolorallocate($image, 37, 99, 235);
    $textColor = imagecolorallocate($image, 255, 255, 255);
    
    // Fill with background
    imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);
    
    // Draw "W" text (smaller for maskable safe zone)
    $fontSize = $size * 0.35;
    $fontWidth = imagefontwidth(5);
    $fontHeight = imagefontheight(5);
    $x = ($size - $fontWidth) / 2;
    $y = ($size - $fontHeight) / 2;
    
    $scale = max(1, floor($size / 25));
    for ($i = 0; $i < $scale; $i++) {
        for ($j = 0; $j < $scale; $j++) {
            imagestring($image, 5, $x + $i, $y + $j, "W", $textColor);
        }
    }
    
    $filename = $outputDir . "icon-maskable-{$size}.png";
    imagepng($image, $filename);
    imagedestroy($image);
    
    echo "Created: icon-maskable-{$size}.png\n";
}

// Create shortcut icons
$shortcuts = ['pos', 'restaurant', 'sales', 'inventory'];
$shortcutColors = [
    'pos' => [34, 197, 94],      // Green
    'restaurant' => [249, 115, 22], // Orange
    'sales' => [59, 130, 246],   // Blue
    'inventory' => [168, 85, 247] // Purple
];
$shortcutLetters = [
    'pos' => 'P',
    'restaurant' => 'R',
    'sales' => 'S',
    'inventory' => 'I'
];

foreach ($shortcuts as $shortcut) {
    $size = 96;
    $image = imagecreatetruecolor($size, $size);
    
    imagealphablending($image, false);
    imagesavealpha($image, true);
    
    $rgb = $shortcutColors[$shortcut];
    $bgColor = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
    $textColor = imagecolorallocate($image, 255, 255, 255);
    
    imagefilledrectangle($image, 0, 0, $size, $size, $bgColor);
    
    $x = ($size - imagefontwidth(5)) / 2;
    $y = ($size - imagefontheight(5)) / 2;
    
    $scale = 4;
    for ($i = 0; $i < $scale; $i++) {
        for ($j = 0; $j < $scale; $j++) {
            imagestring($image, 5, $x + $i, $y + $j, $shortcutLetters[$shortcut], $textColor);
        }
    }
    
    $filename = $outputDir . "shortcut-{$shortcut}.png";
    imagepng($image, $filename);
    imagedestroy($image);
    
    echo "Created: shortcut-{$shortcut}.png\n";
}

echo "\n✅ All icons generated successfully!\n";
echo "Icons saved to: {$outputDir}\n";

// If running in browser, show HTML output
if (php_sapi_name() !== 'cli') {
    echo "<br><br><a href='/wapos/'>← Back to WAPOS</a>";
}
