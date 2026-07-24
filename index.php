<?php
require_once 'includes/auth.php';
if (isLoggedIn()) {
    header('Location: admin/dashboard.php');
} else {
    header('Location: login.php');
}
exit;
