<?php
// actions/delete_guest_allowance.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

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

if (isViewer()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Viewer accounts cannot modify records.']);
    exit;
}

header('Content-Type: application/json');

$inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($inputData['csrf_token'] ?? '');
if (!validateCsrfToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
    exit;
}

$id = !empty($inputData['id']) ? (int)$inputData['id'] : 0;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing record ID.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM guest_allowances WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    // Only Admin or the staff member who entered it can delete/undo it
    if (!isAdmin() && (int)$record['marked_by'] !== (int)$_SESSION['admin_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only void records created by your own account.']);
        exit;
    }

    $stmtDel = $pdo->prepare("DELETE FROM guest_allowances WHERE id = ?");
    $stmtDel->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Allowance for ' . $record['full_name'] . ' (' . $record['token_no'] . ') was reverted and refunded to the table float.',
        'reverted_amount' => (float)$record['allowance_paid']
    ]);
} catch (PDOException $e) {
    error_log("Database error deleting guest allowance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
