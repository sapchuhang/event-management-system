<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$event_id = $_GET['event_id'] ?? null;
if (!$event_id) {
    die("Event ID is required");
}

$stmt = $pdo->prepare("SELECT title FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    die("Event not found");
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$pageFilter = $_GET['page_number'] ?? '';

$queryParts = [];
$params = [$event_id];

if ($search !== '') {
    $queryParts[] = "(m.full_name LIKE ? OR m.member_no LIKE ? OR m.contact LIKE ? OR m.page_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    if ($statusFilter === 'present') {
        $queryParts[] = "a.attended_at IS NOT NULL";
    } elseif ($statusFilter === 'absent') {
        $queryParts[] = "a.attended_at IS NULL";
    }
}

if ($pageFilter !== '') {
    $queryParts[] = "m.page_number = ?";
    $params[] = $pageFilter;
}

$restrictedTables = getRestrictedTables();
if ($restrictedTables !== null) {
    if (empty($restrictedTables)) {
        $queryParts[] = "1=0";
    } else {
        $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
        $queryParts[] = "m.table_no IN ($inClause)";
        foreach ($restrictedTables as $tbl) {
            $params[] = $tbl;
        }
    }
}

$whereClause = "";
if (!empty($queryParts)) {
    $whereClause = "AND " . implode(" AND ", $queryParts);
}

$sql = "
    SELECT m.member_no, m.full_name, m.gender, m.contact, m.page_number, m.table_no, m.file_number, a.attended_at 
    FROM members m 
    LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
    WHERE 1=1 {$whereClause}
    ORDER BY CAST(m.page_number AS UNSIGNED) ASC, m.page_number ASC, m.full_name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $event['title']) . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Member No', 'Full Name', 'Gender', 'Contact', 'Page No', 'Table No', 'File No', 'Status', 'Time']);

foreach ($members as $member) {
    $status = $member['attended_at'] ? 'Present' : 'Absent';
    $time = $member['attended_at'] ? date('h:i A', strtotime($member['attended_at'])) : '-';
    fputcsv($output, [
        $member['member_no'],
        $member['full_name'],
        $member['gender'] ?? '',
        $member['contact'],
        $member['page_number'],
        $member['table_no'],
        $member['file_number'],
        $status,
        $time
    ]);
}
fclose($output);
exit;
