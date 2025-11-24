<?php
require_once '../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$payload = $_POST;

if (empty($payload) && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $payload = $json;
    }
}

$rating = isset($payload['rating']) && $payload['rating'] !== '' ? (int)$payload['rating'] : null;
$comments = trim((string)($payload['comments'] ?? ''));
$contact = trim((string)($payload['contact'] ?? ''));
$contextPage = trim((string)($payload['context_page'] ?? ($_SERVER['HTTP_REFERER'] ?? 'unknown')));
$metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : null;

if ($comments === '') {
    echo json_encode(['success' => false, 'message' => 'Comments are required']);
    exit;
}

try {
    $db = Database::getInstance();
    $insertData = [
        'user_id' => $auth->isLoggedIn() ? $auth->getUserId() : null,
        'context_page' => $contextPage !== '' ? $contextPage : null,
        'rating' => $rating,
        'contact' => $contact !== '' ? $contact : null,
        'comments' => $comments,
        'metadata' => $metadata ? json_encode($metadata) : null,
    ];

    $db->insert('demo_feedback', $insertData);

    echo json_encode(['success' => true, 'message' => 'Thanks for your feedback!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save feedback.']);
}
