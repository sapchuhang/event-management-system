<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
if (!validateCsrfToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$event_id = $data['event_id'] ?? null;
$member_id = $data['member_id'] ?? null;

if ($event_id && $member_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ? AND member_id = ?");
        $stmt->execute([$event_id, $member_id]);
        echo json_encode(['success' => true, 'message' => 'Attendance unmarked successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
}
exit;
