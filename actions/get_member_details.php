<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$member_input = trim($_GET['member_input'] ?? '');
$event_id = $_GET['event_id'] ?? null;

if (!$member_input || !$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Extract member_no from "M001 - Name" format if it contains " - "
$parts = explode(' - ', $member_input);
$member_no = trim($parts[0]);

$stmt = $pdo->prepare("
    SELECT m.id, m.sn, m.member_no, m.full_name, m.contact, m.page_number, m.table_no, m.file_number, m.status, a.attended_at 
    FROM members m 
    LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
    WHERE m.member_no = ?
");
$stmt->execute([$event_id, $member_no]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if ($member) {
    // Check table restriction
    $restrictedTables = getRestrictedTables();
    if ($restrictedTables !== null && !in_array($member['table_no'], $restrictedTables)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access Denied: Table restriction active.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'member' => $member
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}
exit;
