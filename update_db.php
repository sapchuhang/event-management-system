<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
requireLogin();
try {
    $pdo->exec("ALTER TABLE agms MODIFY COLUMN agm_date VARCHAR(20)");
    echo "Column type updated to VARCHAR.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
