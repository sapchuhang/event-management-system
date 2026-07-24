<?php
// actions/delete_agenda_action.php
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

$id     = $_POST['id'] ?? null;
$event_id = $_POST['event_id'] ?? null;

if ($id && is_numeric($id) && $event_id && is_numeric($event_id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM agendas WHERE id = ?");
        $stmt->execute([$id]);
        setFlashMessage('success', 'Agenda item deleted successfully!');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error deleting agenda item: ' . $e->getMessage());
    }
    header('Location: ../admin/agenda.php?event_id=' . (int)$event_id);
} else {
    setFlashMessage('error', 'Invalid parameters.');
    header('Location: ../admin/events.php');
}
exit;
