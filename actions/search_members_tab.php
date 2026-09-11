<?php
// actions/search_members_tab.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$event_id = !empty($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if (!$event_id) {
    $stmtEvent = $pdo->query("SELECT id FROM events ORDER BY id DESC LIMIT 1");
    $event_id = (int)($stmtEvent->fetchColumn() ?: 0);
}

$restrictedTables = getRestrictedTables();
$whereClauses = [];
$params = [$event_id];

if ($query !== '') {
    $searchTerm = "%" . $query . "%";
    $whereClauses[] = "(m.full_name LIKE ? OR m.member_no LIKE ? OR m.contact LIKE ? OR m.sn LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
} else {
    // If empty query, return recent active members (first 20)
    $whereClauses[] = "1=1";
}

if ($restrictedTables !== null) {
    if (empty($restrictedTables)) {
        $whereClauses[] = "1=0";
    } else {
        $inPlaceholders = implode(',', array_fill(0, count($restrictedTables), '?'));
        $whereClauses[] = "m.table_no IN ($inPlaceholders)";
        foreach ($restrictedTables as $tbl) {
            $params[] = $tbl;
        }
    }
}

$whereSql = implode(' AND ', $whereClauses);

// Prioritize results that start with the query or match member_no/name closely
$orderBy = "
    CASE 
        WHEN m.member_no = ? THEN 1
        WHEN m.full_name = ? THEN 2
        WHEN m.full_name LIKE ? THEN 3
        WHEN m.member_no LIKE ? THEN 4
        ELSE 5 
    END, 
    m.full_name ASC
";
$orderParams = [
    $query,
    $query,
    $query . '%',
    $query . '%'
];

$sql = "
    SELECT 
        m.id, 
        m.sn, 
        m.member_no, 
        m.full_name, 
        m.gender, 
        m.contact, 
        m.page_number, 
        m.table_no, 
        m.file_number, 
        m.status,
        a.attended_at,
        e.title AS event_title
    FROM members m
    LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
    LEFT JOIN events e ON e.id = ?
    WHERE {$whereSql}
    ORDER BY {$orderBy}
    LIMIT 25
";

// Assembly parameters in correct order: event_id for attendance, event_id for event title, where params, order params
$finalParams = array_merge([$event_id, $event_id], array_slice($params, 1), $orderParams);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($finalParams);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format fields for frontend display
    $formatted = array_map(function ($item) {
        return [
            'id'           => (int)$item['id'],
            'sn'           => $item['sn'] ?? '—',
            'member_no'    => $item['member_no'],
            'full_name'    => $item['full_name'],
            'gender'       => $item['gender'] ?? 'Other',
            'contact'      => $item['contact'] ?: '—',
            'page_number'  => $item['page_number'] ?: '—',
            'table_no'     => $item['table_no'] ?: '—',
            'file_number'  => $item['file_number'] ?: '—',
            'status'       => $item['status'],
            'is_attended'  => !empty($item['attended_at']),
            'attended_at'  => $item['attended_at'] ? date('h:i A', strtotime($item['attended_at'])) : null,
            'attended_date'=> $item['attended_at'] ? date('M d, Y', strtotime($item['attended_at'])) : null,
            'event_title'  => $item['event_title'] ?? 'Current Event'
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'count'   => count($formatted),
        'members' => $formatted
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error during member search'
    ]);
}
