<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_user_id = $_SESSION['user_id'];
    $target_user_id = $_POST['target_user_id'] ?? null;
    $action = $_POST['action'] ?? null;

    if (!$target_user_id || !$action || $current_user_id == $target_user_id) {
        $_SESSION['error'] = "Invalid request.";
        header("Location: ../matrimony.php");
        exit;
    }

    if ($action === 'block') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->execute([$current_user_id, $target_user_id]);
        $_SESSION['success'] = "User has been blocked successfully.";
    } elseif ($action === 'unblock') {
        $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$current_user_id, $target_user_id]);
        $_SESSION['success'] = "User has been unblocked successfully.";
    }

    // Redirect back to the user's profile
    $stmt = $pdo->prepare("SELECT id FROM matrimony_profiles WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $matrimony_profile = $stmt->fetch();

    if ($matrimony_profile) {
        header("Location: ../profile.php?id=" . $matrimony_profile['id']);
    } else {
        header("Location: ../matrimony.php");
    }
    exit;
} else {
    header("Location: ../matrimony.php");
    exit;
}
