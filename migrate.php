<?php
require 'config/db.php'; // get old db name etc.

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $oldDb = 'agm_system';
    $newDb = 'event_management';

    echo "Starting migration...\n";

    // 1. Create new database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$newDb`");
    
    // 2. We will just use RENAME TABLE which moves tables between databases instantly
    $tables = ['admin_users', 'members', 'agms', 'attendance', 'agendas'];
    foreach ($tables as $tbl) {
        // Check if table exists in old db
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$oldDb' AND table_name = '$tbl'");
        if ($stmt->fetchColumn() > 0) {
            // Drop target table if exists
            $pdo->exec("DROP TABLE IF EXISTS `$newDb`.`$tbl`");
            $pdo->exec("RENAME TABLE `$oldDb`.`$tbl` TO `$newDb`.`$tbl`");
            echo "Moved table $tbl to $newDb\n";
        }
    }

    $pdo->exec("USE `$newDb`");

    // 3. Rename tables and columns in the new DB
    
    // Get attendance FKs
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$newDb' AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'agm_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $pdo->exec("ALTER TABLE attendance DROP FOREIGN KEY {$row['CONSTRAINT_NAME']}");
    }
    
    // Get agendas FKs
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$newDb' AND TABLE_NAME = 'agendas' AND COLUMN_NAME = 'agm_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
    while ($row = $stmt->fetch()) {
        $pdo->exec("ALTER TABLE agendas DROP FOREIGN KEY {$row['CONSTRAINT_NAME']}");
    }

    // Rename table agms -> events
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$newDb' AND table_name = 'agms'");
    if ($stmt->fetchColumn() > 0) {
        $pdo->exec("RENAME TABLE agms TO events");
        echo "Renamed table agms to events\n";
    }

    // Drop unique key first because it references agm_id
    $pdo->exec("ALTER TABLE attendance DROP INDEX unique_attendance");

    // Rename columns
    $pdo->exec("ALTER TABLE events CHANGE agm_date event_date DATE NOT NULL");
    $pdo->exec("ALTER TABLE attendance CHANGE agm_id event_id INT NOT NULL");
    $pdo->exec("ALTER TABLE agendas CHANGE agm_id event_id INT NOT NULL");

    // Re-add unique key
    $pdo->exec("ALTER TABLE attendance ADD UNIQUE KEY unique_attendance (event_id, member_id)");


    // Re-add foreign keys
    $pdo->exec("ALTER TABLE attendance ADD FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");
    $pdo->exec("ALTER TABLE agendas ADD FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE");

    // Add gender column if missing in members table
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = '$newDb' AND table_name = 'members' AND column_name = 'gender'");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE members ADD COLUMN gender ENUM('Male', 'Female', 'Other') DEFAULT 'Other' AFTER full_name");
        echo "Added missing column 'gender' to members table.\n";
    }

    echo "Successfully updated schema.\n";

} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
