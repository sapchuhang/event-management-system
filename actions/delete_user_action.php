<?php
// actions/delete_user_action.php
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

    $id = $_POST['id'] ?? null;

    if (!$id || !is_numeric($id)) {
        setFlashMessage('error', 'Invalid user ID.');
        header('Location: ../admin/users.php');
        exit;
    }

    $id = (int)$id;

    // 1. Prevent self-deletion
    if ($id === (int)$_SESSION['admin_id']) {
        setFlashMessage('error', 'You cannot delete your own account.');
        header('Location: ../admin/users.php');
        exit;
    }

    try {
        // Fetch user info to check their role
        $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            setFlashMessage('error', 'User not found.');
            header('Location: ../admin/users.php');
            exit;
        }

        // 2. Prevent deleting the last administrator
        if ($user['role'] === 'admin') {
            $adminCount = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'admin'")->fetchColumn();
            if ($adminCount <= 1) {
                setFlashMessage('error', 'Cannot delete the last remaining administrator account.');
                header('Location: ../admin/users.php');
                exit;
            }
        }

        // Delete user
        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$id]);

        setFlashMessage('success', 'User account deleted successfully!');
    } catch (PDOException $e) {
        error_log("Database error deleting user: " . $e->getMessage());
        setFlashMessage('error', 'A database error occurred. Please try again.');
    }
    header('Location: ../admin/users.php');
    exit;
}

header('Location: ../admin/users.php');
exit;
