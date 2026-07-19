<?php
// includes/session.php

// 1. Anti-Hacking Security Headers
// Prevent Clickjacking (loading site in iframe)
header("X-Frame-Options: SAMEORIGIN");
// Prevent XSS reflection
header("X-XSS-Protection: 1; mode=block");
// Prevent MIME-sniffing
header("X-Content-Type-Options: nosniff");
// Prevent Referrer leakage to external domains
header("Referrer-Policy: strict-origin-when-cross-origin");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log Site Visitor (Daily unique IP)
if (isset($pdo) && !isset($_SESSION['site_visited_today'])) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $date = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO site_visitors (ip_address, visit_date) VALUES (?, ?)");
        $stmt->execute([$ip, $date]);
        $_SESSION['site_visited_today'] = true;
    } catch (PDOException $e) {
        // Ignore if tracking fails
    }
}

// Live User Tracking Ping
if (isset($_SESSION['user_id']) && isset($pdo)) {
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $current_time = time();
    $last_active = $_SESSION['last_active_time'] ?? 0;
    $last_url = $_SESSION['last_tracked_url'] ?? '';

    // Update DB if URL changed or 1 minute has passed since last ping
    if ($current_url !== $last_url || ($current_time - $last_active) > 60) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET last_active = NOW(), last_page_url = ? WHERE id = ?");
            $stmt->execute([$current_url, $_SESSION['user_id']]);
            
            $_SESSION['last_active_time'] = $current_time;
            $_SESSION['last_tracked_url'] = $current_url;
        } catch (PDOException $e) {
            // Ignore if error occurs
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if a user is currently online (active in the last 5 mins)
function isUserOnline($last_active_timestamp) {
    if (!$last_active_timestamp) return false;
    $last_active = strtotime($last_active_timestamp);
    $current_time = time();
    // 5 minutes = 300 seconds
    return ($current_time - $last_active) <= 300;
}

// Fetch unread messages count and details for the current user
function getUnreadNotifications($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT u.id as sender_id, u.first_name, u.last_name, p.profile_pic, COUNT(m.id) as unread_count 
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN member_profiles p ON u.id = p.user_id
            WHERE m.receiver_id = ? AND m.is_read = 0
            GROUP BY u.id
            ORDER BY MAX(m.created_at) DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        
        $alertClass = 'alert-info';
        if ($type === 'success') $alertClass = 'alert-success';
        if ($type === 'error') $alertClass = 'alert-danger';
        if ($type === 'warning') $alertClass = 'alert-warning';
        
        echo "<div class='alert {$alertClass} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
              
        unset($_SESSION['flash']);
    }
}

// Helper function to fetch CMS page content
function getCmsContent($slug) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT title, content FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

// Helper function to fetch Global Settings
function getGlobalSettings() {
    global $pdo;
    try {
        return $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        return [];
    }
}

// Helper function to log user and admin activities
function logActivity($action, $role = 'user', $user_id = null, $admin_id = null) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, admin_id, role, action, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $admin_id, $role, $action, $ip]);
    } catch (PDOException $e) {
        // Ignore log failure to prevent breaking app flow
    }
}
?>
