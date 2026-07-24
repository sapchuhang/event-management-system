<?php
// config/db.php
$host = 'localhost';
$dbname = 'event_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log the error securely in a real app, don't expose to user
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please contact the administrator.");
}
?>
