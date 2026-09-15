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
$cleanSn = preg_replace('/^(sn|s\.n\.|#|no\.?)\s*[-:]?\s*/iu', '', $query);
$numSn = is_numeric($cleanSn) ? (int)$cleanSn : (is_numeric($query) ? (int)$query : -999999);
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
    $cleanTerm = "%" . $cleanSn . "%";
    
    // Case-insensitive search matching full_name, member_no, contact, and sn (both string and numeric)
    $whereClauses[] = "(
        LOWER(m.full_name) LIKE LOWER(?) OR 
        LOWER(m.member_no) LIKE LOWER(?) OR 
        m.contact LIKE ? OR 
        LOWER(m.sn) LIKE LOWER(?) OR 
        LOWER(m.sn) LIKE LOWER(?) OR 
        LOWER(m.sn) = LOWER(?) OR 
        LOWER(m.sn) = LOWER(?) OR 
        (m.sn REGEXP '^[0-9]+$' AND CAST(m.sn AS UNSIGNED) = ?)
    )";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $cleanTerm;
    $params[] = $query;
    $params[] = $cleanSn;
    $params[] = $numSn;
} else {
    // If empty query, return recent active members (first 25)
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

// Prioritize exact S.N. matches, exact member_no matches, exact name, then prefix matches
// All comparisons are case-insensitive
$orderBy = "
    CASE 
        WHEN (m.sn REGEXP '^[0-9]+$' AND CAST(m.sn AS UNSIGNED) = ?) OR LOWER(m.sn) = LOWER(?) OR LOWER(m.sn) = LOWER(?) THEN 1
        WHEN LOWER(m.member_no) = LOWER(?) OR LOWER(m.member_no) = LOWER(?) THEN 2
        WHEN LOWER(m.full_name) = LOWER(?) THEN 3
        WHEN LOWER(m.member_no) LIKE LOWER(?) THEN 4
        WHEN LOWER(m.full_name) LIKE LOWER(?) THEN 5
        WHEN LOWER(m.sn) LIKE LOWER(?) THEN 6
        ELSE 7 
    END, 
    CAST(m.sn AS UNSIGNED) ASC,
    m.full_name ASC
";
$orderParams = [
    $numSn,
    $query,
    $cleanSn,
    $query,
    $cleanSn,
    $query,
    $query . '%',
    $query . '%',
    $cleanSn . '%'
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
