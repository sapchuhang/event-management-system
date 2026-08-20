<?php
// includes/auth.php

if (!defined('BASE_URL')) {
    define('BASE_URL', '/event-management/');
}

// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

function isLoggedIn() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }

    // Session timeout: 2 hours of inactivity
    $timeout = 7200;
    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > $timeout) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . 'login.php?timeout=1');
        exit;
    }
    $_SESSION['last_active'] = time();
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['admin_role'] ?? '') === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Forbidden – Access Denied</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Inter', sans-serif; background-color: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; }
                .card { border: none; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); max-width: 450px; text-align: center; padding: 2.5rem; background: #fff; }
                .icon { font-size: 4rem; color: #dc2626; margin-bottom: 1.5rem; }
                .btn-home { background-color: #1e4644; color: #fff; border-radius: 8px; padding: 0.6rem 1.5rem; font-weight: 500; transition: background 0.2s; border: none; }
                .btn-home:hover { background-color: #0d2423; color: #fff; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                <h3 class="fw-bold text-dark mb-2">Access Denied</h3>
                <p class="text-muted mb-4">You do not have the required administrator privileges to access this page.</p>
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="btn btn-home">Return to Dashboard</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// --- Flash Messages ---
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

function getFlashMessages() {
    if (isset($_SESSION['flash_messages'])) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    return [];
}

// --- CSRF Protection ---
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Generate it on load just to be sure it's available
generateCsrfToken();

function getAssignedTables($userId) {
    global $pdo;
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->prepare("SELECT table_no FROM user_tables WHERE user_id = ? ORDER BY table_no ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return [];
    }
}

function getRestrictedTables() {
    if (isAdmin()) {
        return null; // Admins are never restricted
    }
    
    $userId = $_SESSION['admin_id'] ?? null;
    if (!$userId) {
        return null;
    }
    
    $tables = getAssignedTables($userId);
    if (empty($tables)) {
        return null; // No tables assigned means no restriction (general access)
    }
    
    return $tables;
}
?>
