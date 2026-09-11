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
$event_id = !empty($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if (!$member_input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing member search input']);
    exit;
}

// If no event_id provided, default to latest event
if (!$event_id) {
    $stmtLatest = $pdo->query("SELECT id FROM events ORDER BY id DESC LIMIT 1");
    $event_id = $stmtLatest->fetchColumn() ?: 0;
}

// Flexible extraction of member_no or clean search term
$member_no_candidate = $member_input;
if (preg_match('/^([^\-]+)\s*-\s*(.+)$/u', $member_input, $matches)) {
    // Format: "M001 - Full Name"
    $member_no_candidate = trim($matches[1]);
} elseif (preg_match('/\(No\.?\s*([^\)]+)\)/iu', $member_input, $matches)) {
    // Format: "Full Name (No. M001)"
    $member_no_candidate = trim($matches[1]);
} elseif (preg_match('/\(#?([^\)]+)\)/u', $member_input, $matches)) {
    // Format: "Full Name (#M001)"
    $member_no_candidate = trim($matches[1]);
}

$baseSelect = "
    SELECT m.id, m.sn, m.member_no, m.full_name, m.gender, m.contact, m.page_number, m.table_no, m.file_number, m.status, 
           a.attended_at, e.title AS event_title, e.id AS event_id
    FROM members m 
    LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
    LEFT JOIN events e ON e.id = ?
";

// 1. Try matching member_no exactly
$stmt = $pdo->prepare($baseSelect . " WHERE m.member_no = ? LIMIT 1");
$stmt->execute([$event_id, $event_id, $member_no_candidate]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. If not found, try original input as member_no
if (!$member && $member_no_candidate !== $member_input) {
    $stmt = $pdo->prepare($baseSelect . " WHERE m.member_no = ? LIMIT 1");
    $stmt->execute([$event_id, $event_id, $member_input]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 3. If not found and input is numeric, try matching S.N.
if (!$member && is_numeric($member_input)) {
    $stmt = $pdo->prepare($baseSelect . " WHERE m.sn = ? LIMIT 1");
    $stmt->execute([$event_id, $event_id, (int)$member_input]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4. If not found, try matching exact full_name
if (!$member) {
    $stmt = $pdo->prepare($baseSelect . " WHERE m.full_name = ? LIMIT 1");
    $stmt->execute([$event_id, $event_id, $member_input]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 5. If not found, try matching phone/contact
if (!$member) {
    $stmt = $pdo->prepare($baseSelect . " WHERE m.contact = ? LIMIT 1");
    $stmt->execute([$event_id, $event_id, $member_input]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 6. If still not found, try LIKE match on full_name or member_no
if (!$member) {
    $stmt = $pdo->prepare($baseSelect . " WHERE m.full_name LIKE ? OR m.member_no LIKE ? ORDER BY CASE WHEN m.full_name LIKE ? THEN 1 ELSE 2 END LIMIT 1");
    $stmt->execute([$event_id, $event_id, "%$member_input%", "%$member_input%", "$member_input%"]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

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
