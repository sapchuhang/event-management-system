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

$member_id = !empty($_GET['member_id']) ? (int)$_GET['member_id'] : null;
$member_input = trim($_GET['member_input'] ?? '');
$event_id = !empty($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if (!$member_id && $member_input === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing member search input']);
    exit;
}

// If no event_id provided, default to latest event
if (!$event_id) {
    $stmtLatest = $pdo->query("SELECT id FROM events ORDER BY id DESC LIMIT 1");
    $event_id = $stmtLatest->fetchColumn() ?: 0;
}

$baseSelect = "
    SELECT m.id, m.sn, m.member_no, m.full_name, m.gender, m.contact, m.page_number, m.table_no, m.file_number, m.status, 
           a.attended_at, a.allowance_paid, e.title AS event_title, e.id AS event_id, e.allowance_amount
    FROM members m 
    LEFT JOIN attendance a ON m.id = a.member_id AND a.event_id = ?
    LEFT JOIN events e ON e.id = ?
";

$member = null;

// 1. If member_id is provided directly
if ($member_id) {
    $stmt = $pdo->prepare($baseSelect . " WHERE m.id = ? LIMIT 1");
    $stmt->execute([$event_id, $event_id, $member_id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 2. Flexible extraction of member_no or clean search term
if (!$member && $member_input !== '') {
    $rawInput = $member_input;
    $candidate = $rawInput;

    if (preg_match('/^([^\-]+)\s*-\s*(.+)$/u', $rawInput, $matches)) {
        // Format: "M001 - Full Name" or "SN 12 | M001 - Full Name"
        $candidate = trim($matches[1]);
        if (strpos($candidate, '|') !== false) {
            $pipeParts = explode('|', $candidate);
            $candidate = trim(end($pipeParts));
        }
    } elseif (preg_match('/\(No\.?\s*([^\)]+)\)/iu', $rawInput, $matches)) {
        $candidate = trim($matches[1]);
    } elseif (preg_match('/\(#?([^\)]+)\)/u', $rawInput, $matches)) {
        $candidate = trim($matches[1]);
    }

    $cleanSn = preg_replace('/^(sn|s\.n\.|#|no\.?)\s*[-:]?\s*/iu', '', $rawInput);
    $cleanCandidateSn = preg_replace('/^(sn|s\.n\.|#|no\.?)\s*[-:]?\s*/iu', '', $candidate);
    $numSn = is_numeric($cleanSn) ? (int)$cleanSn : (is_numeric($rawInput) ? (int)$rawInput : -999999);

    // A. Match member_no case-insensitively
    $stmt = $pdo->prepare($baseSelect . " WHERE LOWER(m.member_no) = LOWER(?) LIMIT 1");
    $stmt->execute([$event_id, $event_id, $candidate]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    // B. Try raw input as member_no case-insensitively
    if (!$member && $candidate !== $rawInput) {
        $stmt = $pdo->prepare($baseSelect . " WHERE LOWER(m.member_no) = LOWER(?) LIMIT 1");
        $stmt->execute([$event_id, $event_id, $rawInput]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // C. Try matching Serial No (sn) case-insensitively or numerically
    if (!$member) {
        $snAttempts = array_unique(array_filter([$cleanCandidateSn, $cleanSn, $candidate, $rawInput]));
        foreach ($snAttempts as $snVal) {
            if ($snVal !== '') {
                $stmt = $pdo->prepare($baseSelect . " WHERE LOWER(m.sn) = LOWER(?) OR (m.sn REGEXP '^[0-9]+$' AND CAST(m.sn AS UNSIGNED) = ?) LIMIT 1");
                $nVal = is_numeric($snVal) ? (int)$snVal : -999999;
                $stmt->execute([$event_id, $event_id, $snVal, $nVal]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($member) break;
            }
        }
    }

    // D. Try matching exact full_name case-insensitively
    if (!$member) {
        $stmt = $pdo->prepare($baseSelect . " WHERE LOWER(m.full_name) = LOWER(?) LIMIT 1");
        $stmt->execute([$event_id, $event_id, $rawInput]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // E. Try matching phone/contact
    if (!$member) {
        $stmt = $pdo->prepare($baseSelect . " WHERE m.contact = ? LIMIT 1");
        $stmt->execute([$event_id, $event_id, $rawInput]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // F. Fallback LIKE match on full_name, member_no, or sn (case-insensitive)
    if (!$member) {
        $stmt = $pdo->prepare("
            $baseSelect 
            WHERE LOWER(m.full_name) LIKE LOWER(?) 
               OR LOWER(m.member_no) LIKE LOWER(?) 
               OR LOWER(m.sn) LIKE LOWER(?) 
            ORDER BY 
               CASE 
                   WHEN LOWER(m.sn) = LOWER(?) OR (m.sn REGEXP '^[0-9]+$' AND CAST(m.sn AS UNSIGNED) = ?) THEN 1
                   WHEN LOWER(m.member_no) = LOWER(?) THEN 2 
                   WHEN LOWER(m.full_name) LIKE LOWER(?) THEN 3 
                   ELSE 4 
               END 
            LIMIT 1
        ");
        $like = "%" . $rawInput . "%";
        $stmt->execute([$event_id, $event_id, $like, $like, $like, $cleanSn, $numSn, $candidate, $rawInput . '%']);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if ($member) {
    // Check table restriction
    $restrictedTables = getRestrictedTables();
    if ($restrictedTables !== null && !in_array($member['table_no'], $restrictedTables)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access Denied: Table restriction active.']);
        exit;
    }

    $allowanceAmount = (float)($member['allowance_amount'] ?? 0.00);
    if (!empty($member['event_title']) && isAgmEvent($member['event_title'])) {
        $allowanceAmount = 500.00;
    }
    $member['allowance_amount'] = $allowanceAmount;
    $member['allowance_paid'] = ($member['allowance_paid'] !== null) ? (float)$member['allowance_paid'] : null;

    echo json_encode([
        'success' => true,
        'member' => $member
    ]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}
exit;
