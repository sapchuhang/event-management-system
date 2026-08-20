<?php
// actions/update_schema_user_tables.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Allow running from command line OR if logged in as admin
if (php_sapi_name() !== 'cli') {
    requireLogin();
}

try {
    // Check if table user_tables exists
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
          AND table_name = 'user_tables'
    ");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn() > 0;

    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE user_tables (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                table_no VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_table (user_id, table_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        echo "Table 'user_tables' created successfully.<br>\n";
    } else {
        echo "Table 'user_tables' already exists.<br>\n";
    }

    echo "<strong>Schema migration for user_tables completed successfully!</strong>";

} catch (Exception $e) {
    die("Migration failed: " . htmlspecialchars($e->getMessage()));
}
?>
