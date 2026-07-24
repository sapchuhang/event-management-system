<?php
// actions/add_speaker_action.php
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

$name = trim($_POST['name'] ?? '');
$title = trim($_POST['title'] ?? '');
$bio = trim($_POST['bio'] ?? '');
$email = trim($_POST['email'] ?? '');
$website = trim($_POST['website'] ?? '');

if (empty($name)) {
    setFlashMessage('danger', 'Speaker name is required.');
    header('Location: ../admin/speakers.php');
    exit;
}

$photo_path = null;

// Handle Photo Upload
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['photo'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlashMessage('danger', 'An error occurred during file upload. Error code: ' . $file['error']);
        header('Location: ../admin/speakers.php');
        exit;
    }

    // Limit size to 2MB
    if ($file['size'] > 2 * 1024 * 1024) {
        setFlashMessage('danger', 'Photo size must be less than 2MB.');
        header('Location: ../admin/speakers.php');
        exit;
    }

    // Allowed extensions
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension'] ?? '');

    if (!in_array($extension, $allowed_extensions)) {
        setFlashMessage('danger', 'Only JPG, JPEG, PNG, and GIF image formats are allowed.');
        header('Location: ../admin/speakers.php');
        exit;
    }

    // Validate MIME-type
    $mime_type = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mime_type = mime_content_type($file['tmp_name']);
    }

    if ($mime_type) {
        $allowed_mimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif'];
        if (!in_array($mime_type, $allowed_mimes)) {
            setFlashMessage('danger', 'Invalid file type. Only JPG, JPEG, PNG, and GIF image formats are allowed.');
            header('Location: ../admin/speakers.php');
            exit;
        }
    }

    // Create target directory if not exists
    $upload_dir = '../uploads/speakers/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique name
    $new_filename = 'speaker_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $dest_path = $upload_dir . $new_filename;

    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        $photo_path = 'uploads/speakers/' . $new_filename;
    } else {
        setFlashMessage('danger', 'Failed to save uploaded photo.');
        header('Location: ../admin/speakers.php');
        exit;
    }
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO speakers (name, title, bio, photo_path, email, website) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $title, $bio, $photo_path, $email, $website]);
    
    setFlashMessage('success', 'Speaker profile added successfully!');
} catch (PDOException $e) {
    setFlashMessage('danger', 'Database error: ' . $e->getMessage());
}

header('Location: ../admin/speakers.php');
exit;
?>
