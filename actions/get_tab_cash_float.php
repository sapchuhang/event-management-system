<?php
// actions/get_tab_cash_float.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn() || isViewer()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Viewer role does not access cash float balances']);
    exit;
}

$event_id = !empty($_GET['event_id']) ? (int)$_GET['event_id'] : null;
$table_no = trim($_GET['table_no'] ?? '');

if (!$event_id) {
    $stmtEvLatest = $pdo->query("SELECT id FROM events ORDER BY id DESC LIMIT 1");
    $event_id = (int)($stmtEvLatest->fetchColumn() ?: 0);
}

if (!$event_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Event not specified']);
    exit;
}

// 1. Get event information and allowance
$stmtEv = $pdo->prepare("SELECT title, allowance_amount FROM events WHERE id = ?");
$stmtEv->execute([$event_id]);
$ev = $stmtEv->fetch(PDO::FETCH_ASSOC);

$allowance = (float)($ev['allowance_amount'] ?? 0.00);
if ($ev && isAgmEvent($ev['title'])) {
    $allowance = 500.00;
}

$userId = (int)$_SESSION['admin_id'];

// 2. Check if current user has direct allocated cash float
$stmtUserCash = $pdo->prepare("SELECT COALESCE(allocated_amount, 0.00) FROM staff_event_cash WHERE event_id = ? AND user_id = ?");
$stmtUserCash->execute([$event_id, $userId]);
$userAllocated = (float)$stmtUserCash->fetchColumn();

$stmtUserPaid = $pdo->prepare("
    SELECT (
        (SELECT COALESCE(SUM(allowance_paid), 0.00) FROM attendance WHERE event_id = ? AND marked_by = ?) +
        (SELECT COALESCE(SUM(allowance_paid), 0.00) FROM guest_allowances WHERE event_id = ? AND marked_by = ?)
    )
");
$stmtUserPaid->execute([$event_id, $userId, $event_id, $userId]);
$userPaid = (float)$stmtUserPaid->fetchColumn();

// If current user has direct allocation and no specific different table requested
if ($userAllocated > 0 && $table_no === '') {
    $remaining = $userAllocated - $userPaid;
    echo json_encode([
        'success'    => true,
        'event_id'   => $event_id,
        'table_no'   => $table_no,
        'type'       => 'user',
        'label'      => 'Your Float',
        'allowance'  => $allowance,
        'allocated'  => $userAllocated,
        'paid'       => $userPaid,
        'remaining'  => $remaining
    ]);
    exit;
}

// 3. Check by Table No (either requested or user's assigned table)
$targetTable = $table_no;
if ($targetTable === '') {
    $assignedTables = getAssignedTables($userId);
    if (!empty($assignedTables)) {
        $targetTable = $assignedTables[0];
    }
}

if ($targetTable !== '') {
    $cleanTable = preg_replace('/^table\s*/i', '', trim($targetTable));
    $stmtTableCash = $pdo->prepare("
        SELECT COALESCE(SUM(sec.allocated_amount), 0.00) AS allocated,
               COALESCE(SUM(payouts.paid), 0.00) AS paid
        FROM admin_users u
        JOIN user_tables ut ON u.id = ut.user_id
        LEFT JOIN staff_event_cash sec ON u.id = sec.user_id AND sec.event_id = ?
        LEFT JOIN (
            SELECT marked_by, SUM(allowance_paid) as paid FROM (
                SELECT marked_by, allowance_paid FROM attendance WHERE event_id = ?
                UNION ALL
                SELECT marked_by, allowance_paid FROM guest_allowances WHERE event_id = ?
            ) p GROUP BY marked_by
        ) payouts ON u.id = payouts.marked_by
        WHERE (ut.table_no = ? OR ut.table_no = ?)
    ");
    $stmtTableCash->execute([$event_id, $event_id, $event_id, $cleanTable, 'Table ' . $cleanTable]);
    $tableRow = $stmtTableCash->fetch(PDO::FETCH_ASSOC);
    $tAllocated = (float)($tableRow['allocated'] ?? 0.00);
    $tPaid = (float)($tableRow['paid'] ?? 0.00);

    if ($tAllocated > 0 || $table_no !== '') {
        $remaining = $tAllocated - $tPaid;
        echo json_encode([
            'success'    => true,
            'event_id'   => $event_id,
            'table_no'   => $cleanTable,
            'type'       => 'table',
            'label'      => "Table $cleanTable Float",
            'allowance'  => $allowance,
            'allocated'  => $tAllocated,
            'paid'       => $tPaid,
            'remaining'  => $remaining
        ]);
        exit;
    }
}

// 4. Fallback: Event Total Cash Float
$stmtEvTotal = $pdo->prepare("
    SELECT COALESCE(SUM(sec.allocated_amount), 0.00) AS allocated,
           COALESCE(SUM(payouts.paid), 0.00) AS paid
    FROM staff_event_cash sec
    LEFT JOIN (
        SELECT marked_by, SUM(allowance_paid) as paid FROM (
            SELECT marked_by, allowance_paid FROM attendance WHERE event_id = ?
            UNION ALL
            SELECT marked_by, allowance_paid FROM guest_allowances WHERE event_id = ?
        ) p GROUP BY marked_by
    ) payouts ON sec.user_id = payouts.marked_by
    WHERE sec.event_id = ?
");
$stmtEvTotal->execute([$event_id, $event_id, $event_id]);
$evRow = $stmtEvTotal->fetch(PDO::FETCH_ASSOC);
$eAllocated = (float)($evRow['allocated'] ?? 0.00);
$ePaid = (float)($evRow['paid'] ?? 0.00);

echo json_encode([
    'success'    => true,
    'event_id'   => $event_id,
    'table_no'   => '',
    'type'       => 'event',
    'label'      => 'Event Float',
    'allowance'  => $allowance,
    'allocated'  => $eAllocated,
    'paid'       => $ePaid,
    'remaining'  => $eAllocated - $ePaid
]);
exit;
