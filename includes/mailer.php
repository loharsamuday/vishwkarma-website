<?php
// includes/mailer.php
// Robust PHPMailer loader that checks both vendor/PHPMailer and root PHPMailer

if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    $base_dir = dirname(__DIR__);
    
    if (file_exists($base_dir . '/vendor/PHPMailer/src/PHPMailer.php')) {
        require_once $base_dir . '/vendor/PHPMailer/src/Exception.php';
        require_once $base_dir . '/vendor/PHPMailer/src/PHPMailer.php';
        require_once $base_dir . '/vendor/PHPMailer/src/SMTP.php';
    } elseif (file_exists($base_dir . '/PHPMailer/src/PHPMailer.php')) {
        require_once $base_dir . '/PHPMailer/src/Exception.php';
        require_once $base_dir . '/PHPMailer/src/PHPMailer.php';
        require_once $base_dir . '/PHPMailer/src/SMTP.php';
    }
}
