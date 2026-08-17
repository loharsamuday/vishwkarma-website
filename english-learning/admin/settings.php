<?php
// admin/settings.php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Auth is handled by includes/header.php

$success = '';
$error = '';

if (isset($_SESSION['success_msg'])) {
    $success = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'contact_email' => trim($_POST['contact_email']),
        'contact_phone' => trim($_POST['contact_phone']),
        'contact_whatsapp' => trim($_POST['contact_whatsapp']),
        'social_facebook' => trim($_POST['social_facebook']),
        'social_twitter' => trim($_POST['social_twitter'] ?? ''),
        'social_instagram' => trim($_POST['social_instagram'] ?? ''),
        'social_youtube' => trim($_POST['social_youtube'] ?? ''),
        'smtp_host' => trim($_POST['smtp_host'] ?? ''),
        'smtp_port' => trim($_POST['smtp_port'] ?? ''),
        'smtp_user' => trim($_POST['smtp_user'] ?? ''),
        'smtp_pass' => trim($_POST['smtp_pass'] ?? '')
    ];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($settings as $key => $value) {
            $stmt->execute([$value, $key]);
        }
        $pdo->commit();
        $_SESSION['success_msg'] = "Site settings updated successfully.";
        header("Location: settings.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_msg'] = "Failed to update settings: " . $e->getMessage();
        header("Location: settings.php");
        exit();
    }
}

// Fetch current settings
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt->fetch()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $error = "Failed to load settings.";
}

$page_title = 'Site Settings';
include 'includes/header.php';
?>

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom">
                <h1 class="h2"><i class="fas fa-cogs me-2 text-secondary"></i>Site Settings</h1>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= escape($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= escape($error) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0">Update Footer Content</h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <h6 class="text-uppercase text-muted fw-bold mb-3"><i class="fas fa-address-book me-2"></i>Contact Information</h6>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Support Email ID</label>
                                <input type="email" name="contact_email" class="form-control" value="<?= escape($current_settings['contact_email'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Support Mobile Number</label>
                                <input type="text" name="contact_phone" class="form-control" value="<?= escape($current_settings['contact_phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">WhatsApp Number (with Country Code)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fab fa-whatsapp text-success"></i></span>
                                    <input type="text" name="contact_whatsapp" class="form-control" value="<?= escape($current_settings['contact_whatsapp'] ?? '') ?>" placeholder="e.g. 919876543210">
                                </div>
                                <small class="text-muted">Do not use '+' sign. E.g. 919876543210</small>
                            </div>
                        </div>

                        <hr class="mb-4">

                        <h6 class="text-uppercase text-muted fw-bold mb-3"><i class="fas fa-share-alt me-2"></i>Social Media Links</h6>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="fab fa-facebook text-primary me-2"></i>Facebook URL</label>
                                <input type="text" name="social_facebook" class="form-control" value="<?= escape($current_settings['social_facebook'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="fab fa-twitter text-info me-2"></i>Twitter URL</label>
                                <input type="text" name="social_twitter" class="form-control" value="<?= escape($current_settings['social_twitter'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="fab fa-instagram text-danger me-2"></i>Instagram URL</label>
                                <input type="text" name="social_instagram" class="form-control" value="<?= escape($current_settings['social_instagram'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold"><i class="fab fa-youtube text-danger me-2"></i>YouTube URL</label>
                                <input type="text" name="social_youtube" class="form-control" value="<?= escape($current_settings['social_youtube'] ?? '') ?>">
                            </div>
                        </div>

                        <hr class="mb-4">

                        <h6 class="text-uppercase text-muted fw-bold mb-3"><i class="fas fa-envelope-open-text me-2"></i>SMTP Email Settings</h6>
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" value="<?= escape($current_settings['smtp_host'] ?? '') ?>" placeholder="e.g. smtp.gmail.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Port</label>
                                <input type="text" name="smtp_port" class="form-control" value="<?= escape($current_settings['smtp_port'] ?? '') ?>" placeholder="e.g. 587">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Username (Email)</label>
                                <input type="text" name="smtp_user" class="form-control" value="<?= escape($current_settings['smtp_user'] ?? '') ?>" placeholder="e.g. your-email@gmail.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">SMTP Password</label>
                                <input type="password" name="smtp_pass" class="form-control" value="<?= escape($current_settings['smtp_pass'] ?? '') ?>" placeholder="App Password">
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary bg-primary-custom px-5 py-2 fw-bold">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>

<?php include 'includes/footer.php'; ?>
