<?php
// actions/add_member_action.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        setFlashMessage('error', 'Security check failed. Please try again.');
        header('Location: ../admin/add_member.php');
        exit;
    }

    $member_no   = trim($_POST['member_no'] ?? '');
    $full_name   = trim($_POST['full_name'] ?? '');
    $gender      = $_POST['gender'] ?? 'Other';
    $contact     = trim($_POST['contact'] ?? '');
    $page_number = trim($_POST['page_number'] ?? '');
    $table_no    = trim($_POST['table_no'] ?? '');
    $file_number = trim($_POST['file_number'] ?? '');
    $status      = $_POST['status'] ?? '';
    $address     = trim($_POST['address'] ?? '');

    // Validate
    $errors = [];
    if (empty($member_no))            $errors[] = 'Member number is required.';
    if (strlen($member_no) > 50)      $errors[] = 'Member number must be under 50 characters.';
    if (empty($full_name))            $errors[] = 'Full name is required.';
    if (strlen($full_name) > 100)     $errors[] = 'Full name must be under 100 characters.';
    if (!empty($contact) && strlen($contact) > 50) $errors[] = 'Contact must be under 50 characters.';
    if (!in_array($status, ['active', 'inactive'])) $errors[] = 'Invalid status value.';

    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        header('Location: ../admin/add_member.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO members (member_no, full_name, gender, contact, page_number, table_no, file_number, status, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$member_no, $full_name, $gender, $contact, $page_number, $table_no, $file_number, $status, $address]);
        setFlashMessage('success', 'Member added successfully!');
        header('Location: ../admin/members.php');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            setFlashMessage('error', 'Member number already exists.');
        } else {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
        header('Location: ../admin/add_member.php');
    }
    exit;
}

header('Location: ../admin/add_member.php');
exit;
