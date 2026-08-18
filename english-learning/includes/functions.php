<?php
// includes/functions.php

/**
 * Sanitize output to prevent XSS
 */
function escape($string) {
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a URL-friendly slug
 */
function generateSlug($string) {
    $slug = preg_replace('/[^a-zA-Z0-9\-]/', '-', strtolower(trim($string)));
    return preg_replace('/-+/', '-', $slug);
}

/**
 * Calculate reading time (assuming ~200 words per minute)
 */
function calculateReadingTime($text) {
    $wordCount = str_word_count(strip_tags($text));
    $minutes = ceil($wordCount / 200);
    return max(1, $minutes);
}

/**
 * Format date
 */
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

/**
 * CSRF Token generation
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token verification
 */
function verifyCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }
}
?>
