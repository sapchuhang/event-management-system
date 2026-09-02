<?php
// actions/edit_member_action.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        setFlashMessage('error', 'Security check failed. Please try again.');
        header('Location: ../admin/members.php');
        exit;
    }

    $id          = $_POST['id'] ?? null;
    $sn          = isset($_POST['sn']) && $_POST['sn'] !== '' ? (int)$_POST['sn'] : null;
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
    if (!$id || !is_numeric($id))          $errors[] = 'Invalid member ID.';
    if (empty($member_no))                 $errors[] = 'Member number is required.';
    if (strlen($member_no) > 50)           $errors[] = 'Member number must be under 50 characters.';
    if (empty($full_name))                 $errors[] = 'Full name is required.';
    if (strlen($full_name) > 100)          $errors[] = 'Full name must be under 100 characters.';
    if (!empty($contact) && strlen($contact) > 50) $errors[] = 'Contact must be under 50 characters.';
    if (!in_array($status, ['active', 'inactive'])) $errors[] = 'Invalid status value.';

    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        header('Location: ../admin/edit_member.php?id=' . (int)$id);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE members SET sn = ?, member_no = ?, full_name = ?, gender = ?, contact = ?, page_number = ?, table_no = ?, file_number = ?, status = ?, address = ? WHERE id = ?");
        $stmt->execute([$sn, $member_no, $full_name, $gender, $contact, $page_number, $table_no, $file_number, $status, $address, $id]);
        setFlashMessage('success', 'Member updated successfully!');
        header('Location: ../admin/members.php');
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            if (strpos($e->getMessage(), 'unique_sn') !== false || strpos($e->getMessage(), "'sn'") !== false) {
                setFlashMessage('error', 'S.N. ' . $sn . ' is already assigned to another member. Please use a different serial number.');
            } else {
                setFlashMessage('error', 'Member number already exists.');
            }
        } else {
            setFlashMessage('error', 'Database error: ' . $e->getMessage());
        }
        header('Location: ../admin/edit_member.php?id=' . (int)$id);
    }
    exit;
}

header('Location: ../admin/members.php');
exit;
