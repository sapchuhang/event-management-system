<?php
// actions/add_user_action.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin(); // Restrict to administrators

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        setFlashMessage('error', 'Security check failed. Please try again.');
        header('Location: ../admin/users.php');
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'staff';

    // Validation
    $errors = [];
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Username must be under 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $errors[] = 'Username can only contain alphanumeric characters, hyphens, and underscores.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if (!in_array($role, ['admin', 'staff'])) {
        $errors[] = 'Invalid role selected.';
    }

    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        header('Location: ../admin/add_user.php');
        exit;
    }

    try {
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password_hash, $role]);
        $userId = $pdo->lastInsertId();

        // Save Seating Table Assignments
        if (!empty($_POST['assigned_tables']) && is_array($_POST['assigned_tables'])) {
            $stmtTable = $pdo->prepare("INSERT INTO user_tables (user_id, table_no) VALUES (?, ?)");
            foreach ($_POST['assigned_tables'] as $table_no) {
                $table_no = trim($table_no);
                if ($table_no !== '') {
                    $stmtTable->execute([$userId, $table_no]);
                }
            }
        }

        setFlashMessage('success', 'User account created successfully!');
        header('Location: ../admin/users.php');
    } catch (PDOException $e) {
        // Log the exact error securely and show a user-friendly message
        error_log("Database error creating user: " . $e->getMessage());
        
        if ($e->getCode() == 23000) {
            setFlashMessage('error', 'Username already exists.');
            header('Location: ../admin/add_user.php');
        } else {
            setFlashMessage('error', 'A database error occurred. Please try again.');
            header('Location: ../admin/add_user.php');
        }
    }
    exit;
}

header('Location: ../admin/users.php');
exit;
