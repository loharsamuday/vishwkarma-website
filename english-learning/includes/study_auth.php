<?php
// includes/study_auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = null;
$guest_id = null;
$is_guest = false;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} else {
    $is_guest = true;
    if (isset($_COOKIE['study_guest_id'])) {
        $guest_id = $_COOKIE['study_guest_id'];
    } else {
        $guest_id = 'guest_' . time() . '_' . rand(1000, 9999);
        setcookie('study_guest_id', $guest_id, time() + (86400 * 365), "/"); // 1 year
    }
}

function get_study_user_condition() {
    global $user_id, $guest_id, $is_guest;
    if ($is_guest) {
        return "guest_id = ?";
    } else {
        return "user_id = ?";
    }
}

function get_study_user_param() {
    global $user_id, $guest_id, $is_guest;
    if ($is_guest) {
        return $guest_id;
    } else {
        return $user_id;
    }
}
?>
