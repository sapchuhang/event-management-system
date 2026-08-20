<?php
// actions/edit_user_action.php
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

    $id       = $_POST['id'] ?? null;
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    if (!$id || !is_numeric($id)) {
        setFlashMessage('error', 'Invalid user ID.');
        header('Location: ../admin/users.php');
        exit;
    }

    $id = (int)$id;
    $isSelf = ($id === (int)$_SESSION['admin_id']);

    // Fetch existing user data
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        setFlashMessage('error', 'User not found.');
        header('Location: ../admin/users.php');
        exit;
    }

    // Force role to 'admin' if editing self to prevent locking out self
    if ($isSelf) {
        $role = 'admin';
    }

    // Validation
    $errors = [];
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Username must be under 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $errors[] = 'Username can only contain alphanumeric characters, hyphens, and underscores.';
    }

    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if (!in_array($role, ['admin', 'staff'])) {
        $errors[] = 'Invalid role selected.';
    }

    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        header('Location: ../admin/edit_user.php?id=' . $id);
        exit;
    }

    try {
        if (!empty($password)) {
            // Update with password change
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $password_hash, $role, $id]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE admin_users SET username = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $role, $id]);
        }

        // If editing self, update active session username just in case
        if ($isSelf) {
            $_SESSION['admin_username'] = $username;
        }

        // Update Seating Table Assignments
        $stmtDelete = $pdo->prepare("DELETE FROM user_tables WHERE user_id = ?");
        $stmtDelete->execute([$id]);

        if (!empty($_POST['assigned_tables']) && is_array($_POST['assigned_tables'])) {
            $stmtTable = $pdo->prepare("INSERT INTO user_tables (user_id, table_no) VALUES (?, ?)");
            foreach ($_POST['assigned_tables'] as $table_no) {
                $table_no = trim($table_no);
                if ($table_no !== '') {
                    $stmtTable->execute([$id, $table_no]);
                }
            }
        }

        setFlashMessage('success', 'User account updated successfully!');
        header('Location: ../admin/users.php');
    } catch (PDOException $e) {
        error_log("Database error editing user: " . $e->getMessage());
        
        if ($e->getCode() == 23000) {
            setFlashMessage('error', 'Username already exists.');
        } else {
            setFlashMessage('error', 'A database error occurred. Please try again.');
        }
        header('Location: ../admin/edit_user.php?id=' . $id);
    }
    exit;
}

header('Location: ../admin/users.php');
exit;
