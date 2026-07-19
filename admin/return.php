<?php
require_once '../includes/session.php';

if (isset($_SESSION['admin_impersonating']) && $_SESSION['admin_impersonating'] === true) {
    // End the impersonation securely
    unset($_SESSION['user_id']);
    unset($_SESSION['admin_impersonating']);
    unset($_SESSION['original_admin_id']);
    
    setFlashMessage('success', 'Returned to Admin Panel securely.');
}

header("Location: dashboard.php");
exit;
