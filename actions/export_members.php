<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$restrictedTables = getRestrictedTables();
$whereClause = "";
$params = [];
if ($restrictedTables !== null) {
    if (empty($restrictedTables)) {
        $whereClause = "WHERE 1=0";
    } else {
        $inClause = implode(',', array_fill(0, count($restrictedTables), '?'));
        $whereClause = "WHERE table_no IN ($inClause)";
        $params = $restrictedTables;
    }
}

$stmt = $pdo->prepare("
    SELECT member_no, full_name, gender, contact, page_number, table_no, file_number, status 
    FROM members 
    {$whereClause}
    ORDER BY full_name ASC
");
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="members_list_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Member No', 'Full Name', 'Gender', 'Contact', 'Page No', 'Table No', 'File No', 'Status']);

foreach ($members as $member) {
    fputcsv($output, [
        $member['member_no'],
        $member['full_name'],
        $member['gender'] ?? '',
        $member['contact'],
        $member['page_number'],
        $member['table_no'],
        $member['file_number'],
        ucfirst($member['status'])
    ]);
}
fclose($output);
exit;
