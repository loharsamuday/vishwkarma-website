<?php
// admin/send-updates.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: " . EL_BASE_URL . "admin/login.php");
    exit();
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_update'])) {
    $audience = $_POST['audience'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    
    $recipients = [];

    if ($audience === 'all_users') {
        $stmt = $pdo->query("SELECT name, email FROM users WHERE status != 'blocked'");
        $recipients = $stmt->fetchAll();
    } elseif ($audience === 'all_subscribers') {
        $stmt = $pdo->query("SELECT 'Subscriber' as name, email FROM subscribers WHERE status = 'active'");
        $recipients = $stmt->fetchAll();
    } elseif ($audience === 'selective') {
        if (isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
            $inQuery = implode(',', array_fill(0, count($_POST['selected_users']), '?'));
            $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id IN ($inQuery)");
            $stmt->execute($_POST['selected_users']);
            $recipients = $stmt->fetchAll();
        }
    }

    if (empty($recipients)) {
        $error = "No recipients found for the selected audience.";
    } else {
        // Load SMTP Settings
        $stmt_smtp = $pdo->query("SELECT * FROM smtp_settings LIMIT 1");
        $smtp = $stmt_smtp->fetch();

        if ($smtp && !empty($smtp['smtp_user'])) {
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
                $mail->isHTML(true);
                $mail->Subject = $subject;

                $successCount = 0;
                foreach ($recipients as $r) {
                    try {
                        $mail->clearAddresses();
                        $mail->addAddress($r['email'], $r['name']);
                        
                        // Personalize
                        $personalized_body = str_replace(['{name}', '{email}'], [$r['name'], $r['email']], $body);
                        $mail->Body = $personalized_body;
                        
                        $mail->send();
                        $successCount++;
                    } catch (Exception $e) {
                        // Skip failed email and continue
                    }
                }
                
                if ($successCount > 0) {
                    $msg = "Successfully sent update to $successCount recipient(s)!";
                } else {
                    $error = "Failed to send emails. Please check your SMTP settings.";
                }
                
            } catch (Exception $e) {
                $error = "Mailer Error: " . $mail->ErrorInfo;
            }
        } else {
            $error = "SMTP Settings not configured. Please configure them first.";
        }
    }
}

// Fetch all users for selective dropdown
$stmt = $pdo->query("SELECT id, name, email FROM users WHERE status != 'blocked' ORDER BY name ASC");
$all_users = $stmt->fetchAll();

$page_title = 'Send Email Updates';
include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Send Email Updates</h1>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pt-3 pb-2 border-0">
                <h5 class="fw-bold"><i class="fas fa-paper-plane text-primary me-2"></i> Compose Email Update</h5>
            </div>
            <div class="card-body p-4">
                <form action="" method="POST" id="broadcastForm">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select Audience</label>
                        <select name="audience" id="audienceSelect" class="form-select form-select-lg bg-light" onchange="toggleSelective()" required>
                            <option value="">-- Choose Recipients --</option>
                            <option value="all_users">All Registered Users</option>
                            <option value="all_subscribers">All Newsletter Subscribers</option>
                            <option value="selective">Specific Users (Selective)</option>
                        </select>
                    </div>

                    <div class="mb-4 d-none" id="selectiveUsersDiv">
                        <label class="form-label fw-bold">Select Users</label>
                        <select name="selected_users[]" class="form-select bg-light" multiple size="6">
                            <?php foreach ($all_users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= escape($u['name']) ?> (<?= escape($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Hold CTRL (or CMD on Mac) to select multiple users.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Subject</label>
                        <input type="text" name="subject" class="form-control bg-light" required placeholder="Important Update: New English Stories Added!">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Email Body</label>
                        <textarea name="body" class="form-control rich-editor" rows="10" placeholder="Type your email content here..."></textarea>
                        <div class="form-text text-muted">Use <strong>{name}</strong> to automatically insert the user's name.</div>
                    </div>

                    <button type="submit" name="send_update" class="btn btn-primary btn-lg px-5 shadow-sm" onclick="return confirm('Are you sure you want to send this email to the selected audience?');">
                        <i class="fas fa-paper-plane me-2"></i> Send Email
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4 mt-4 mt-lg-0">
        <div class="card bg-light border-0 shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb text-warning me-2"></i> Broadcast Tips</h6>
                <div class="mb-3">
                    <strong class="d-block text-dark small mb-1">Personalization</strong>
                    <p class="small text-muted mb-0">Include {name} in the body to make your emails friendly (e.g., "Hi {name}, we have new content for you!").</p>
                </div>
                <div class="mb-3">
                    <strong class="d-block text-dark small mb-1">Avoid Spam Filters</strong>
                    <p class="small text-muted mb-0">Don't use excessive capitalization or exclamation marks in the subject line.</p>
                </div>
                <div>
                    <strong class="d-block text-dark small mb-1">Testing</strong>
                    <p class="small text-muted mb-0">Before sending to "All Users", try sending it to just your own account using the "Specific Users" option to see how it looks!</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSelective() {
    const audience = document.getElementById('audienceSelect').value;
    const selectiveDiv = document.getElementById('selectiveUsersDiv');
    const selectBox = selectiveDiv.querySelector('select');
    
    if (audience === 'selective') {
        selectiveDiv.classList.remove('d-none');
        selectBox.setAttribute('required', 'required');
    } else {
        selectiveDiv.classList.add('d-none');
        selectBox.removeAttribute('required');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
