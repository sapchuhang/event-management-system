<?php
// actions/get_guest_allowances.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$event_id = !empty($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing event_id parameter']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT g.id, g.token_no, g.full_name, g.contact, g.organization, g.category, 
               g.table_no, g.reason_remarks, g.allowance_paid, g.created_at, g.marked_by,
               u.username AS marked_by_name
        FROM guest_allowances g
        LEFT JOIN admin_users u ON g.marked_by = u.id
        WHERE g.event_id = ?
        ORDER BY g.id DESC
    ");
    $stmt->execute([$event_id]);
    $guests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted = array_map(function ($g) {
        return [
            'id' => (int)$g['id'],
            'token_no' => $g['token_no'],
            'full_name' => $g['full_name'],
            'contact' => $g['contact'] ?: '—',
            'organization' => $g['organization'] ?: '—',
            'category' => $g['category'],
            'table_no' => $g['table_no'] ?: 'General',
            'reason_remarks' => $g['reason_remarks'] ?: '—',
            'allowance_paid' => (float)$g['allowance_paid'],
            'marked_by' => (int)$g['marked_by'],
            'marked_by_name' => $g['marked_by_name'] ?: 'Staff',
            'created_time' => date('h:i A', strtotime($g['created_at'])),
            'created_date' => date('M d, Y', strtotime($g['created_at']))
        ];
    }, $guests);

    echo json_encode([
        'success' => true,
        'guests' => $formatted,
        'total_count' => count($formatted),
        'total_paid' => array_sum(array_column($formatted, 'allowance_paid'))
    ]);
} catch (PDOException $e) {
    error_log("Database error fetching guest allowances: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
