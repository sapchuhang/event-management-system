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

if (isViewer()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Viewer accounts are read-only.']);
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
        // Check table restriction
        $stmtMember = $pdo->prepare("SELECT table_no FROM members WHERE id = ?");
        $stmtMember->execute([$member_id]);
        $member = $stmtMember->fetch();
        if ($member) {
            $restrictedTables = getRestrictedTables();
            if ($restrictedTables !== null && !in_array($member['table_no'], $restrictedTables)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'You are not assigned to manage members of Table: ' . ($member['table_no'] ?: 'N/A')]);
                exit;
            }
        }

        $stmt = $pdo->prepare("DELETE FROM attendance WHERE event_id = ? AND member_id = ?");
        $stmt->execute([$event_id, $member_id]);

        // Calculate updated remaining cash for the operator
        $marked_by = (int)$_SESSION['admin_id'];
        $stmtCash = $pdo->prepare("SELECT COALESCE(allocated_amount, 0.00) FROM staff_event_cash WHERE event_id = ? AND user_id = ?");
        $stmtCash->execute([$event_id, $marked_by]);
        $allocated = (float)$stmtCash->fetchColumn();

        $stmtPaid = $pdo->prepare("
            SELECT (
                (SELECT COALESCE(SUM(allowance_paid), 0.00) FROM attendance WHERE event_id = ? AND marked_by = ?) +
                (SELECT COALESCE(SUM(allowance_paid), 0.00) FROM guest_allowances WHERE event_id = ? AND marked_by = ?)
            )
        ");
        $stmtPaid->execute([$event_id, $marked_by, $event_id, $marked_by]);
        $paid = (float)$stmtPaid->fetchColumn();
        $new_remaining = $allocated - $paid;

        echo json_encode([
            'success'        => true, 
            'message'        => 'Attendance unmarked successfully.',
            'remaining_cash' => $new_remaining
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
}
exit;
