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
?>
