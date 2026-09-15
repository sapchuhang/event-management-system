<?php
require_once '../config/db.php';
require_once '../includes/auth.php';

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Check login (return JSON if not logged in)
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isViewer()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Viewer accounts are read-only and cannot mark attendance.']);
    exit;
}

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Validate CSRF
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
if (!validateCsrfToken($csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$event_id = !empty($data['event_id']) ? (int)$data['event_id'] : null;
$member_id = !empty($data['member_id']) && is_numeric($data['member_id']) ? (int)$data['member_id'] : null;
$member_input = trim($data['member_input'] ?? $data['member_no'] ?? '');

if (!$event_id || (!$member_id && $member_input === '')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$member = null;

// 1. Direct lookup by member_id if provided
if ($member_id) {
    $stmt = $pdo->prepare("SELECT id, sn, member_no, full_name, contact, page_number, table_no, file_number FROM members WHERE id = ?");
    $stmt->execute([$member_id]);
    $member = $stmt->fetch();
}

// 2. Lookup by member_input (case-insensitive, supporting Serial No, Member No, Full Name)
if (!$member && $member_input !== '') {
    $rawInput = $member_input;
    
    // Check if input is formatted like "M001 - Name" or "SN 12 | M001 - Name"
    $candidate = $rawInput;
    if (preg_match('/^([^\-]+)\s*-\s*(.+)$/u', $rawInput, $matches)) {
        $candidate = trim($matches[1]);
        if (strpos($candidate, '|') !== false) {
            $pipeParts = explode('|', $candidate);
            $candidate = trim(end($pipeParts));
        }
    }

    $cleanSn = preg_replace('/^(sn|s\.n\.|#|no\.?)\s*[-:]?\s*/iu', '', $rawInput);
    $cleanCandidateSn = preg_replace('/^(sn|s\.n\.|#|no\.?)\s*[-:]?\s*/iu', '', $candidate);
    $numSn = is_numeric($cleanSn) ? (int)$cleanSn : (is_numeric($rawInput) ? (int)$rawInput : -999999);

    // A. Match member_no case-insensitively
    $stmt = $pdo->prepare("SELECT id, sn, member_no, full_name, contact, page_number, table_no, file_number FROM members WHERE LOWER(member_no) = LOWER(?) LIMIT 1");
    $stmt->execute([$candidate]);
    $member = $stmt->fetch();

    // B. Match raw input as member_no case-insensitively
    if (!$member && $candidate !== $rawInput) {
        $stmt = $pdo->prepare("SELECT id, sn, member_no, full_name, contact, page_number, table_no, file_number FROM members WHERE LOWER(member_no) = LOWER(?) LIMIT 1");
        $stmt->execute([$rawInput]);
        $member = $stmt->fetch();
    }

    // C. Match S.N. (Serial No) case-insensitively or numerically
    if (!$member) {
        $snAttempts = array_unique(array_filter([$cleanCandidateSn, $cleanSn, $candidate, $rawInput]));
        foreach ($snAttempts as $snVal) {
            if ($snVal !== '') {
                $stmt = $pdo->prepare("SELECT id, sn, member_no, full_name, contact, page_number, table_no, file_number FROM members WHERE LOWER(sn) = LOWER(?) OR (sn REGEXP '^[0-9]+$' AND CAST(sn AS UNSIGNED) = ?) LIMIT 1");
                $nVal = is_numeric($snVal) ? (int)$snVal : -999999;
                $stmt->execute([$snVal, $nVal]);
                $member = $stmt->fetch();
                if ($member) break;
            }
        }
    }

    // D. Match exact full_name case-insensitively
    if (!$member) {
        $stmt = $pdo->prepare("SELECT id, sn, member_no, full_name, contact, page_number, table_no, file_number FROM members WHERE LOWER(full_name) = LOWER(?) LIMIT 1");
        $stmt->execute([$rawInput]);
        $member = $stmt->fetch();
    }

    // E. Match contact / phone number
    if (!$member) {
        $stmt = $pdo->prepare("SELECT id, sn, member_no, full_name, contact, page_number, table_no, file_number FROM members WHERE contact = ? LIMIT 1");
        $stmt->execute([$rawInput]);
        $member = $stmt->fetch();
    }

    // F. Fallback LIKE match on member_no, full_name, or sn (case-insensitive)
    if (!$member) {
        $stmt = $pdo->prepare("
            SELECT id, sn, member_no, full_name, contact, page_number, table_no, file_number 
            FROM members 
            WHERE LOWER(member_no) LIKE LOWER(?) 
               OR LOWER(full_name) LIKE LOWER(?) 
               OR LOWER(sn) LIKE LOWER(?) 
            ORDER BY 
               CASE 
                   WHEN LOWER(sn) = LOWER(?) OR (sn REGEXP '^[0-9]+$' AND CAST(sn AS UNSIGNED) = ?) THEN 1
                   WHEN LOWER(member_no) = LOWER(?) THEN 2
                   WHEN LOWER(full_name) LIKE LOWER(?) THEN 3
                   ELSE 4
               END 
            LIMIT 1
        ");
        $like = "%" . $rawInput . "%";
        $stmt->execute([$like, $like, $like, $cleanSn, $numSn, $candidate, $rawInput . '%']);
        $member = $stmt->fetch();
    }
}

if ($member) {
    // Check table restriction
    $restrictedTables = getRestrictedTables();
    if ($restrictedTables !== null && !in_array($member['table_no'], $restrictedTables)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not assigned to manage members of Table: ' . ($member['table_no'] ?: 'N/A')]);
        exit;
    }

    // Check event allowance configs
    $stmtEvent = $pdo->prepare("SELECT title, allowance_amount FROM events WHERE id = ?");
    $stmtEvent->execute([$event_id]);
    $eventObj = $stmtEvent->fetch();
    
    if (!$eventObj) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    $allowance = (float)$eventObj['allowance_amount'];
    if (isAgmEvent($eventObj['title'])) {
        $allowance = 500.00;
    }
    $marked_by = $_SESSION['admin_id'];
    $allowance_paid = 0.00;
    $remaining = 0.00;

    if ($allowance > 0) {
        // Fetch allocated amount for user
        $stmtCash = $pdo->prepare("SELECT COALESCE(allocated_amount, 0.00) FROM staff_event_cash WHERE event_id = ? AND user_id = ?");
        $stmtCash->execute([$event_id, $marked_by]);
        $allocated = (float)$stmtCash->fetchColumn();

        // Calculate paid out amount by this user
        $stmtPaid = $pdo->prepare("SELECT COALESCE(SUM(allowance_paid), 0.00) FROM attendance WHERE event_id = ? AND marked_by = ?");
        $stmtPaid->execute([$event_id, $marked_by]);
        $paid = (float)$stmtPaid->fetchColumn();

        $remaining = $allocated - $paid;

        if ($remaining < $allowance) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'Insufficient cash float. You have NPR ' . number_format($remaining, 2) . ' remaining, but NPR ' . number_format($allowance, 2) . ' is required.'
            ]);
            exit;
        }
        
        $allowance_paid = $allowance;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id, member_id, marked_by, allowance_paid) VALUES (?, ?, ?, ?)");
        $stmt->execute([$event_id, $member['id'], $marked_by, $allowance_paid]);
        
        $new_remaining = ($allowance > 0) ? ($remaining - $allowance) : 0.00;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance marked for ' . $member['full_name'] . ($allowance > 0 ? '. Paid allowance: NPR ' . number_format($allowance, 2) : ''),
            'allowance_paid' => $allowance_paid,
            'remaining_cash' => $new_remaining,
            'member' => [
                'id' => $member['id'],
                'sn' => $member['sn'] ?? '—',
                'member_no' => $member['member_no'],
                'full_name' => $member['full_name'],
                'contact' => $member['contact'],
                'page_number' => $member['page_number'],
                'table_no' => $member['table_no'],
                'file_number' => $member['file_number'],
                'attended_at' => date('h:i A')
            ]
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Already marked present']);
        } else {
            error_log("Database error marking attendance: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    }
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}
exit;
