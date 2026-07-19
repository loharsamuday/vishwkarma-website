<?php
require_once '../includes/session.php';
if(session_status() === PHP_SESSION_NONE) session_start();
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
header("Location: index.php");
exit;
?>
