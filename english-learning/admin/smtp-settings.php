<?php
// admin/smtp-settings.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . EL_BASE_URL . "admin/login.php");
    exit();
}

// Create table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS smtp_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
        smtp_port INT NOT NULL DEFAULT 587,
        smtp_user VARCHAR(255) NOT NULL,
        smtp_pass VARCHAR(255) NOT NULL,
        from_email VARCHAR(255) NOT NULL,
        from_name VARCHAR(255) NOT NULL DEFAULT 'English Learning App',
        welcome_subject VARCHAR(255) NOT NULL DEFAULT 'Welcome to English Stories & Learning!',
        welcome_body TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Insert default row if table is empty
$stmt = $pdo->query("SELECT COUNT(*) FROM smtp_settings");
if ($stmt->fetchColumn() == 0) {
    $default_body = "<h3>Welcome to English Stories & Learning!</h3><p>We are so glad to have you here. Start exploring our rich collection of stories, idioms, and vocabulary to improve your English today!</p><p>Happy Learning!</p>";
    $pdo->exec("INSERT INTO smtp_settings (smtp_user, smtp_pass, from_email, welcome_body) VALUES ('your_email@gmail.com', 'your_app_password', 'your_email@gmail.com', '$default_body')");
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_settings'])) {
        $host = $_POST['smtp_host'];
        $port = (int)$_POST['smtp_port'];
        $user = $_POST['smtp_user'];
        $pass = $_POST['smtp_pass'];
        $from_email = $_POST['from_email'];
        $from_name = $_POST['from_name'];
        $subject = $_POST['welcome_subject'];
        $body = $_POST['welcome_body'];

        $stmt = $pdo->prepare("UPDATE smtp_settings SET smtp_host=?, smtp_port=?, smtp_user=?, smtp_pass=?, from_email=?, from_name=?, welcome_subject=?, welcome_body=? WHERE id=1");
        if ($stmt->execute([$host, $port, $user, $pass, $from_email, $from_name, $subject, $body])) {
            $msg = "SMTP settings updated successfully.";
        } else {
            $error = "Failed to update settings.";
        }
    } elseif (isset($_POST['send_test'])) {
        $test_email = $_POST['test_email'];
        
        $stmt = $pdo->query("SELECT * FROM smtp_settings LIMIT 1");
        $smtp = $stmt->fetch();
        
        try {
            require_once __DIR__ . '/../includes/PHPMailer/Exception.php';
            require_once __DIR__ . '/../includes/PHPMailer/PHPMailer.php';
            require_once __DIR__ . '/../includes/PHPMailer/SMTP.php';

            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $smtp['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp['smtp_user'];
            $mail->Password   = $smtp['smtp_pass'];
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtp['smtp_port'];

            $mail->setFrom($smtp['from_email'], $smtp['from_name']);
            $mail->addAddress($test_email, 'Admin Test');

            $mail->isHTML(true);
            $mail->Subject = "[TEST] " . $smtp['welcome_subject'];
            
            // Replace placeholders for test
            $body = str_replace(['{name}', '{email}'], ['Admin Test', $test_email], $smtp['welcome_body']);
            $mail->Body = $body;

            $mail->send();
            $msg = "Test email sent successfully to {$test_email}!";
        } catch (Exception $e) {
            $error = "Failed to send test email. Error: " . $mail->ErrorInfo;
        }
    }
}

// Fetch current settings
$stmt = $pdo->query("SELECT * FROM smtp_settings LIMIT 1");
$settings = $stmt->fetch();

$page_title = 'SMTP Settings';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">SMTP & Email Configuration</h1>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0">
                <h5 class="fw-bold"><i class="fas fa-server text-primary me-2"></i> Mail Server Settings</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($settings['smtp_port']) ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMTP Username</label>
                            <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($settings['smtp_user']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SMTP App Password</label>
                            <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars($settings['smtp_pass']) ?>" required>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">From Email</label>
                            <input type="email" name="from_email" class="form-control" value="<?= htmlspecialchars($settings['from_email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">From Name</label>
                            <input type="text" name="from_name" class="form-control" value="<?= htmlspecialchars($settings['from_name']) ?>" required>
                        </div>
                    </div>

                    <hr class="mb-4">
                    <h5 class="fw-bold mb-3"><i class="fas fa-envelope-open-text text-success me-2"></i> Auto Welcome Email Template</h5>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject Line</label>
                        <input type="text" name="welcome_subject" class="form-control" value="<?= htmlspecialchars($settings['welcome_subject']) ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Email Body (HTML supported)</label>
                        <textarea name="welcome_body" class="form-control rich-editor" rows="5"><?= htmlspecialchars($settings['welcome_body']) ?></textarea>
                        <div class="form-text text-muted">You can use HTML tags to design the welcome email beautifully.</div>
                    </div>

                    <button type="submit" name="save_settings" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i> Save SMTP Settings</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mt-4 mt-lg-0">
        <div class="card bg-light border-0 mb-4">
            <div class="card-body">
                <h6 class="fw-bold"><i class="fas fa-info-circle text-info me-2"></i> How to use Gmail SMTP?</h6>
                <p class="small text-muted mb-2">1. Turn on 2-Step Verification for your Google Account.</p>
                <p class="small text-muted mb-2">2. Go to Google Account Security -> App Passwords.</p>
                <p class="small text-muted mb-2">3. Create a new App Password for "Mail".</p>
                <p class="small text-muted mb-0">4. Use that 16-character password in the SMTP App Password field above.</p>
            </div>
        </div>
        
        <div class="card shadow-sm border-0 border-top border-warning border-3">
            <div class="card-header bg-white pt-3 pb-2 border-0">
                <h6 class="fw-bold"><i class="fas fa-paper-plane text-warning me-2"></i> Send Test Email</h6>
            </div>
            <div class="card-body p-4 pt-2">
                <p class="small text-muted mb-3">Send a test email using your currently saved SMTP settings to verify they are working.</p>
                <form action="" method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Test Email Address</label>
                        <input type="email" name="test_email" class="form-control" placeholder="admin@example.com" required>
                    </div>
                    <button type="submit" name="send_test" class="btn btn-warning w-100 fw-bold"><i class="fas fa-vial me-2"></i> Send Test</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
