<?php
require_once 'includes/db.php';
require_once 'includes/session.php';

if (isset($_SESSION['admin_impersonating']) && $_SESSION['admin_impersonating'] === true) {
    header("Location: admin/return.php");
    exit;
}

session_unset();
session_destroy();
session_start();
setFlashMessage('success', 'You have been logged out successfully.');
header("Location: login.php");
exit;
?>
