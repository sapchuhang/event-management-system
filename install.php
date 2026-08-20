<?php
// install.php — SECURED
// This file should be deleted after first-time setup.
// Access is blocked unless the correct secret token is provided.

define('INSTALL_SECRET', 'CHANGE_THIS_SECRET_BEFORE_USE');

if (!isset($_GET['secret']) || $_GET['secret'] !== INSTALL_SECRET) {
    http_response_code(403);
    die('403 Forbidden. This installer is protected. Delete this file after installation.');
}

// Extract credentials from config/db.php securely without executing it (to avoid database-not-found die() failures)
$config_content = file_get_contents('config/db.php');
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'event_management';

if (preg_match('/\$host\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $host = $matches[1];
}
if (preg_match('/\$dbname\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $dbname = $matches[1];
}
if (preg_match('/\$username\s*=\s*[\'"]([^\'"]+)[\'"]/', $config_content, $matches)) {
    $username = $matches[1];
}
if (preg_match('/\$password\s*=\s*[\'"]([^\'"]*)[\'"]/', $config_content, $matches)) {
    $password = $matches[1];
}

try {
    // Connect to host only, without database selected
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");

    // Initialize tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_no VARCHAR(50) NOT NULL UNIQUE,
            full_name VARCHAR(100) NOT NULL,
            gender ENUM('Male', 'Female', 'Other') DEFAULT 'Other',
            contact VARCHAR(50),
            page_number VARCHAR(50),
            table_no VARCHAR(50),
            file_number VARCHAR(50),
            address VARCHAR(255),
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            event_date DATE NOT NULL,
            location VARCHAR(200),
            status ENUM('upcoming', 'ongoing', 'completed') DEFAULT 'upcoming',
            allowance_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            member_id INT NOT NULL,
            marked_by INT NULL,
            allowance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            attended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
            FOREIGN KEY (marked_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_attendance (event_id, member_id)
        );
        CREATE TABLE IF NOT EXISTS speakers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            title VARCHAR(150),
            bio TEXT,
            photo_path VARCHAR(255),
            email VARCHAR(100),
            website VARCHAR(150),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS agendas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_time TIME NULL,
            end_time TIME NULL,
            speaker_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (speaker_id) REFERENCES speakers(id) ON DELETE SET NULL
        );
        CREATE TABLE IF NOT EXISTS staff_event_cash (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            allocated_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_staff_event (event_id, user_id)
        );
        CREATE TABLE IF NOT EXISTS user_tables (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            table_no VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_table (user_id, table_no)
        );
    ");

    // Default admin: admin / password123
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO admin_users (username, password, role) VALUES ('admin', '$hash', 'admin')");
    }

    echo "Installation successful! <strong>Please delete this file immediately for security.</strong>";

} catch (PDOException $e) {
    die("Installation failed: " . htmlspecialchars($e->getMessage()));
}
?>
