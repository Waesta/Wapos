<?php
/**
 * PWA Icon Generator
 * Generates all required PWA icon sizes from existing WAPOS logo SVG
 */

// Configuration
$sourceLogoSVG = __DIR__ . '/assets/images/system/wapos-logo.svg';
$outputDir = __DIR__ . '/assets/images/system/';

// Required icon sizes
$iconSizes = [
    72, 96, 128, 144, 152, 192, 384, 512
];

$maskableSizes = [
    192, 512
];

// Check if source exists
if (!file_exists($sourceLogoSVG)) {
    die("Error: Source logo not found at $sourceLogoSVG\n");
}

// Check if output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

echo "WAPOS PWA Icon Generator\n";
echo "========================\n\n";

// Check if Imagick extension is available
if (!extension_loaded('imagick')) {
    echo "❌ PHP Imagick extension not installed.\n\n";
    echo "ALTERNATIVE METHODS:\n\n";
    
    echo "METHOD 1: Online Tool (Recommended)\n";
    echo "1. Go to: https://www.pwabuilder.com/imageGenerator\n";
    echo "2. Upload: $sourceLogoSVG\n";
    echo "3. Generate all sizes\n";
    echo "4. Download and extract to: $outputDir\n";
    echo "5. Rename files to match:\n";
    foreach ($iconSizes as $size) {
        echo "   - wapos-icon-{$size}.png\n";
    }
    foreach ($maskableSizes as $size) {
        echo "   - wapos-icon-maskable-{$size}.png\n";
    }
    
    echo "\nMETHOD 2: Using ImageMagick Command Line\n";
    echo "If you have ImageMagick installed, run these commands:\n\n";
    
    foreach ($iconSizes as $size) {
        echo "magick convert \"$sourceLogoSVG\" -resize {$size}x{$size} -background transparent -gravity center -extent {$size}x{$size} \"{$outputDir}wapos-icon-{$size}.png\"\n";
    }
    
    echo "\n# Maskable icons with purple background\n";
    foreach ($maskableSizes as $size) {
        $logoSize = (int)($size * 0.8); // 80% of canvas for safe zone
        echo "magick convert \"$sourceLogoSVG\" -resize {$logoSize}x{$logoSize} -background \"#667eea\" -gravity center -extent {$size}x{$size} \"{$outputDir}wapos-icon-maskable-{$size}.png\"\n";
    }
    
    exit(1);
}

// Generate icons using Imagick
echo "✓ PHP Imagick extension found\n";
echo "Generating PWA icons...\n\n";

$successCount = 0;
$errorCount = 0;

// Generate standard icons
foreach ($iconSizes as $size) {
    try {
        $imagick = new Imagick();
        $imagick->setBackgroundColor(new ImagickPixel('transparent'));
        $imagick->readImage($sourceLogoSVG);
        $imagick->setImageFormat('png');
        $imagick->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
        
        $outputFile = $outputDir . "wapos-icon-{$size}.png";
        $imagick->writeImage($outputFile);
        $imagick->clear();
        $imagick->destroy();
        
        echo "✓ Generated: wapos-icon-{$size}.png\n";
        $successCount++;
    } catch (Exception $e) {
        echo "✗ Failed: wapos-icon-{$size}.png - " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

// Generate maskable icons with purple background
foreach ($maskableSizes as $size) {
    try {
        $imagick = new Imagick();
        $imagick->setBackgroundColor(new ImagickPixel('#667eea'));
        $imagick->readImage($sourceLogoSVG);
        $imagick->setImageFormat('png');
        
        // Resize logo to 80% of canvas size (20% safe zone)
        $logoSize = (int)($size * 0.8);
        $imagick->resizeImage($logoSize, $logoSize, Imagick::FILTER_LANCZOS, 1);
        
        // Extend canvas to full size with purple background
        $imagick->extentImage($size, $size, -($size - $logoSize) / 2, -($size - $logoSize) / 2);
        
        $outputFile = $outputDir . "wapos-icon-maskable-{$size}.png";
        $imagick->writeImage($outputFile);
        $imagick->clear();
        $imagick->destroy();
        
        echo "✓ Generated: wapos-icon-maskable-{$size}.png\n";
        $successCount++;
    } catch (Exception $e) {
        echo "✗ Failed: wapos-icon-maskable-{$size}.png - " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n========================\n";
echo "Summary:\n";
echo "✓ Success: $successCount icons\n";
echo "✗ Failed: $errorCount icons\n";

if ($successCount > 0) {
    echo "\n✅ PWA icons generated successfully!\n";
    echo "Icons saved to: $outputDir\n";
    echo "\nNext steps:\n";
    echo "1. Upload manifest.json to production\n";
    echo "2. Upload all generated PNG files to production\n";
    echo "3. Test PWA installation on mobile device\n";
} else {
    echo "\n❌ No icons were generated. Please use alternative methods.\n";
}
?>
