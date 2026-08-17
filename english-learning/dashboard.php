<?php
// dashboard.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Allow guests to view the dashboard preview

$page_title = 'My Smart Dashboard';
include 'includes/header.php';

include 'includes/student_dashboard.php';

include 'includes/footer.php';
?>
