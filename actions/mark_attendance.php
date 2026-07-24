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

$event_id = $data['event_id'] ?? null;
$member_input = trim($data['member_input'] ?? $data['member_no'] ?? '');

if (!$event_id || !$member_input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Extract member_no from "M001 - Full Name" format if it contains " - "
$parts = explode(' - ', $member_input);
$member_no = trim($parts[0]);

// Find member
$stmt = $pdo->prepare("SELECT id, full_name, contact, page_number, table_no, file_number FROM members WHERE member_no = ?");
$stmt->execute([$member_no]);
$member = $stmt->fetch();

if ($member) {
    try {
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id, member_id) VALUES (?, ?)");
        $stmt->execute([$event_id, $member['id']]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Attendance marked for ' . $member['full_name'],
            'member' => [
                'id' => $member['id'],
                'member_no' => $member_no,
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
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    }
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Member not found']);
}
exit;
