<?php
// actions/add_event_action.php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        setFlashMessage('error', 'Security check failed. Please try again.');
        header('Location: ../admin/events.php');
        exit;
    }

    $title            = trim($_POST['title'] ?? '');
    $event_date       = trim($_POST['event_date'] ?? '');
    $location         = trim($_POST['location'] ?? '');
    $allowance_amount = floatval($_POST['allowance_amount'] ?? 0.00);

    // Validate
    $errors = [];
    if (empty($title))    $errors[] = 'Event title is required.';
    if (strlen($title) > 200) $errors[] = 'Event title must be under 200 characters.';
    if (empty($event_date)) $errors[] = 'Event date is required.';
    if (empty($location)) $errors[] = 'Event location is required.';
    if (strlen($location) > 200) $errors[] = 'Location must be under 200 characters.';
    if ($allowance_amount < 0) $errors[] = 'Transportation allowance cannot be negative.';

    if (!empty($errors)) {
        setFlashMessage('error', implode(' ', $errors));
        header('Location: ../admin/events.php');
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO events (title, event_date, location, status, allowance_amount) VALUES (?, ?, ?, 'upcoming', ?)");
    $stmt->execute([$title, $event_date, $location, $allowance_amount]);

    setFlashMessage('success', 'Event scheduled successfully!');
    header('Location: ../admin/events.php');
    exit;
}

header('Location: ../admin/events.php');
exit;
