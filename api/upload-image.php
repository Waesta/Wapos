<?php
/**
 * WAPOS - Image Upload API
 * Handles image uploads for products, menu items, logos, etc.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

// Require authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Configuration
$config = [
    'max_size' => 5 * 1024 * 1024, // 5MB
    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'upload_base' => __DIR__ . '/../storage/uploads/',
    'url_base' => '/wapos/storage/uploads/'
];

// Determine upload type/folder
$type = $_POST['type'] ?? 'general';
$validTypes = ['products', 'menu', 'logos', 'categories', 'users', 'general'];
if (!in_array($type, $validTypes)) {
    $type = 'general';
}

// Check if file was uploaded
if (empty($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
    ];
    $error = $errorMessages[$file['error']] ?? 'Unknown upload error';
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// Check file size
if ($file['size'] > $config['max_size']) {
    echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $config['allowed_types'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP, SVG']);
    exit;
}

// Validate extension
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $config['allowed_extensions'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid file extension']);
    exit;
}

// Create upload directory
$uploadDir = $config['upload_base'] . $type . '/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit;
    }
}

// Generate unique filename
$filename = uniqid($type . '_') . '_' . time() . '.' . $extension;
$filepath = $uploadDir . $filename;
$urlPath = $config['url_base'] . $type . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Optional: Create thumbnail for products/menu
$thumbnailUrl = null;
if (in_array($type, ['products', 'menu']) && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    $thumbnailUrl = createThumbnail($filepath, $uploadDir, $filename, 200);
}

// Optional: Update database record if ID provided
$recordId = $_POST['record_id'] ?? null;
$recordType = $_POST['record_type'] ?? null;

if ($recordId && $recordType) {
    try {
        $db = Database::getInstance();
        
        switch ($recordType) {
            case 'product':
                $db->query("UPDATE products SET image = ? WHERE id = ?", [$urlPath, $recordId]);
                break;
            case 'category':
                $db->query("UPDATE categories SET image = ? WHERE id = ?", [$urlPath, $recordId]);
                break;
            case 'menu_item':
                $db->query("UPDATE menu_items SET image = ? WHERE id = ?", [$urlPath, $recordId]);
                break;
        }
    } catch (Exception $e) {
        // Log error but don't fail - file was uploaded successfully
        error_log('Failed to update database record: ' . $e->getMessage());
    }
}

// Return success response
echo json_encode([
    'success' => true,
    'url' => $urlPath,
    'thumbnail' => $thumbnailUrl,
    'filename' => $filename,
    'size' => $file['size'],
    'type' => $mimeType
]);

/**
 * Create a thumbnail image
 */
function createThumbnail($sourcePath, $destDir, $filename, $maxSize = 200) {
    $thumbDir = $destDir . 'thumbnails/';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
    
    $thumbPath = $thumbDir . 'thumb_' . $filename;
    
    // Get image info
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return null;
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    // Calculate new dimensions
    if ($width > $height) {
        $newWidth = $maxSize;
        $newHeight = intval($height * ($maxSize / $width));
    } else {
        $newHeight = $maxSize;
        $newWidth = intval($width * ($maxSize / $height));
    }
    
    // Create source image
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            return null;
    }
    
    if (!$source) {
        return null;
    }
    
    // Create thumbnail
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preserve transparency for PNG/GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    // Resize
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save thumbnail
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($thumb, $thumbPath, 85);
            break;
        case 'png':
            imagepng($thumb, $thumbPath, 8);
            break;
        case 'gif':
            imagegif($thumb, $thumbPath);
            break;
        case 'webp':
            imagewebp($thumb, $thumbPath, 85);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($thumb);
    
    // Return URL path
    $urlBase = '/wapos/storage/uploads/' . basename($destDir) . '/thumbnails/';
    return $urlBase . 'thumb_' . $filename;
}
