<?php
// actions/delete_speaker_action.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlashMessage('danger', 'Method not allowed.');
    header('Location: ../admin/speakers.php');
    exit;
}

// Validate CSRF
$csrf_token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrf_token)) {
    setFlashMessage('danger', 'Security check failed. Please try again.');
    header('Location: ../admin/speakers.php');
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id || is_numeric($id) === false) {
    setFlashMessage('danger', 'Invalid speaker parameter.');
    header('Location: ../admin/speakers.php');
    exit;
}

try {
    // Fetch speaker details to delete photo file
    $stmt = $pdo->prepare("SELECT photo_path FROM speakers WHERE id = ?");
    $stmt->execute([$id]);
    $speaker = $stmt->fetch();

    if ($speaker) {
        $photo_path = $speaker['photo_path'];
        if ($photo_path && file_exists('../' . $photo_path)) {
            @unlink('../' . $photo_path);
        }

        $stmt = $pdo->prepare("DELETE FROM speakers WHERE id = ?");
        $stmt->execute([$id]);

        setFlashMessage('success', 'Speaker profile deleted successfully!');
    } else {
        setFlashMessage('danger', 'Speaker not found.');
    }
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

header('Location: ../admin/speakers.php');
exit;
?>
