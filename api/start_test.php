<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_id'])) {
    $test_id = (int)$_POST['test_id'];
    $user_id = (int)$_SESSION['user_id'];
    
    // Check if test exists and is published
    $stmt = $pdo->prepare("SELECT * FROM mt_mock_tests WHERE id = ? AND status = 'published'");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch();
    
    if (!$test) {
        setFlashMessage('error', 'Test not found or unavailable.');
        header("Location: ../mock-tests.php");
        exit;
    }
    
    // Security check (Premium)
    $is_vip = false;
    $vip_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_vip_status WHERE user_id = ? AND status = 'active' AND expiry_date >= CURDATE()");
    $vip_stmt->execute([$user_id]);
    if ($vip_stmt->fetchColumn() > 0) {
        $is_vip = true;
    }
    
    $is_admin = false;
    $role_stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = ?");
    $role_stmt->execute([$user_id]);
    if ($role_stmt->fetchColumn() == 1) $is_admin = true;
    
    if ($test['is_premium'] && !$is_vip && !$is_admin) {
        setFlashMessage('error', 'Premium access required.');
        header("Location: ../mock-test-detail.php?slug=" . $test['slug']);
        exit;
    }
    
    // Check for existing active attempt
    $stmt_active = $pdo->prepare("SELECT id FROM mt_test_attempts WHERE user_id = ? AND mock_test_id = ? AND status = 'in_progress'");
    $stmt_active->execute([$user_id, $test_id]);
    $active_attempt = $stmt_active->fetch();
    
    if ($active_attempt) {
        // Redirect to resume
        header("Location: ../mock-test-interface.php?test_id=" . $test_id);
        exit;
    }
    
    // Check attempt limit
    $stmt_att = $pdo->prepare("SELECT COUNT(*) FROM mt_test_attempts WHERE user_id = ? AND mock_test_id = ? AND status = 'completed'");
    $stmt_att->execute([$user_id, $test_id]);
    $attempts_count = $stmt_att->fetchColumn();
    
    if ($attempts_count >= $test['attempt_limit']) {
        setFlashMessage('error', 'Attempt limit reached for this test.');
        header("Location: ../mock-test-detail.php?slug=" . $test['slug']);
        exit;
    }
    
    // Create new attempt
    $start_time = date('Y-m-d H:i:s');
    $stmt_insert = $pdo->prepare("INSERT INTO mt_test_attempts (user_id, mock_test_id, start_time, status) VALUES (?, ?, ?, 'in_progress')");
    $stmt_insert->execute([$user_id, $test_id, $start_time]);
    
    header("Location: ../mock-test-interface.php?test_id=" . $test_id);
    exit;
} else {
    header("Location: ../mock-tests.php");
    exit;
}
