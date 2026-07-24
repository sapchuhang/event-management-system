<?php
// actions/delete_event_action.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

// Must be a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('error', 'Invalid request method.');
    header('Location: ../admin/events.php');
    exit;
}

// Validate CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    setFlashMessage('error', 'Security check failed. Please try again.');
    header('Location: ../admin/events.php');
    exit;
}

$id = $_POST['id'] ?? null;
if ($id && is_numeric($id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Event deleted successfully!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error deleting Event: ' . $e->getMessage());
    }
} else {
    setFlashMessage('error', 'Invalid Event ID.');
}

header('Location: ../admin/events.php');
exit;
