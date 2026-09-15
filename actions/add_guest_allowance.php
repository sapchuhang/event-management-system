<?php
// actions/add_guest_allowance.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Authentication check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isViewer()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Viewer accounts are read-only and cannot dispense allowance.']);
    exit;
}

header('Content-Type: application/json');

// Parse JSON body or form POST
$inputData = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// CSRF validation
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($inputData['csrf_token'] ?? '');
if (!validateCsrfToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF security token. Please refresh and try again.']);
    exit;
}

$event_id = !empty($inputData['event_id']) ? (int)$inputData['event_id'] : 0;
$full_name = trim($inputData['full_name'] ?? '');
$contact = trim($inputData['contact'] ?? '');
$organization = trim($inputData['organization'] ?? '');
$category = trim($inputData['category'] ?? 'Media / Reporter');
$table_no = trim($inputData['table_no'] ?? '');
$reason_remarks = trim($inputData['reason_remarks'] ?? '');
$custom_allowance = isset($inputData['allowance_paid']) && is_numeric($inputData['allowance_paid']) ? (float)$inputData['allowance_paid'] : null;

if (!$event_id || empty($full_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event and Full Name are required.']);
    exit;
}

// If table_no is empty, fallback to the staff user's first assigned table or 'General'
$marked_by = (int)$_SESSION['admin_id'];
if (empty($table_no)) {
    $assignedTables = getAssignedTables($marked_by);
    $table_no = !empty($assignedTables) ? $assignedTables[0] : 'General';
}

// Fetch event details
$stmtEvent = $pdo->prepare("SELECT id, title, allowance_amount FROM events WHERE id = ?");
$stmtEvent->execute([$event_id]);
$eventObj = $stmtEvent->fetch();

if (!$eventObj) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event not found.']);
    exit;
}

// Determine default allowance amount
$eventAllowance = (float)$eventObj['allowance_amount'];
if (isAgmEvent($eventObj['title'])) {
    $eventAllowance = 500.00;
}

$allowance_paid = ($custom_allowance !== null && $custom_allowance >= 0) ? $custom_allowance : $eventAllowance;
$remaining_cash = 0.00;

// Verify cash float if allowance is being paid
if ($allowance_paid > 0) {
    // 1. Get allocated float for current user/table
    $stmtCash = $pdo->prepare("SELECT COALESCE(allocated_amount, 0.00) FROM staff_event_cash WHERE event_id = ? AND user_id = ?");
    $stmtCash->execute([$event_id, $marked_by]);
    $allocated = (float)$stmtCash->fetchColumn();

    // 2. Sum member attendance payouts
    $stmtMemPaid = $pdo->prepare("SELECT COALESCE(SUM(allowance_paid), 0.00) FROM attendance WHERE event_id = ? AND marked_by = ?");
    $stmtMemPaid->execute([$event_id, $marked_by]);
    $memberPaid = (float)$stmtMemPaid->fetchColumn();

    // 3. Sum guest/reporter allowance payouts
    $stmtGuestPaid = $pdo->prepare("SELECT COALESCE(SUM(allowance_paid), 0.00) FROM guest_allowances WHERE event_id = ? AND marked_by = ?");
    $stmtGuestPaid->execute([$event_id, $marked_by]);
    $guestPaid = (float)$stmtGuestPaid->fetchColumn();

    $totalPaid = $memberPaid + $guestPaid;
    $remaining_cash = $allocated - $totalPaid;

    if ($remaining_cash < $allowance_paid) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient cash float for your table. You have NPR ' . number_format($remaining_cash, 2) . ' remaining, but NPR ' . number_format($allowance_paid, 2) . ' is required. Please request a cash top-up.'
        ]);
        exit;
    }
}

try {
    // Generate sequential token number for this event: G-001, G-002, etc.
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM guest_allowances WHERE event_id = ?");
    $stmtCount->execute([$event_id]);
    $guestIndex = (int)$stmtCount->fetchColumn() + 1;
    $token_no = 'G-' . str_pad((string)$guestIndex, 3, '0', STR_PAD_LEFT);

    // Insert record
    $stmtInsert = $pdo->prepare("
        INSERT INTO guest_allowances (event_id, token_no, full_name, contact, organization, category, table_no, reason_remarks, allowance_paid, marked_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $event_id,
        $token_no,
        $full_name,
        $contact ?: null,
        $organization ?: null,
        $category,
        $table_no ?: null,
        $reason_remarks ?: null,
        $allowance_paid,
        $marked_by
    ]);

    $newGuestId = (int)$pdo->lastInsertId();
    $newRemaining = ($allowance_paid > 0) ? ($remaining_cash - $allowance_paid) : $remaining_cash;

    echo json_encode([
        'success' => true,
        'message' => 'Allowance of NPR ' . number_format($allowance_paid, 2) . ' dispensed to ' . $full_name . ' (' . $token_no . ') and subtracted from Table ' . $table_no . ' float.',
        'remaining_cash' => $newRemaining,
        'guest' => [
            'id' => $newGuestId,
            'token_no' => $token_no,
            'full_name' => $full_name,
            'contact' => $contact ?: '—',
            'organization' => $organization ?: '—',
            'category' => $category,
            'table_no' => $table_no ?: 'General',
            'reason_remarks' => $reason_remarks ?: '—',
            'allowance_paid' => $allowance_paid,
            'event_title' => $eventObj['title'],
            'dispensed_by' => $_SESSION['admin_username'] ?? 'Staff',
            'dispensed_time' => date('h:i A'),
            'dispensed_date' => date('M d, Y')
        ]
    ]);
} catch (PDOException $e) {
    error_log("Database error saving guest allowance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error while saving guest record.']);
}
