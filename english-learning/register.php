<?php
// register.php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: profile.php");
    exit();
}

$error = '';
$success = '';

if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Registration successful! You can now login.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $target_exam = $_POST['target_exam'] ?? '';
    $custom_exam = trim($_POST['custom_exam'] ?? '');
    
    // Handle custom target exam
    if ($target_exam === 'other' && !empty($custom_exam)) {
        $stmtCheck = $pdo->prepare("SELECT id FROM target_exams WHERE name = ?");
        $stmtCheck->execute([$custom_exam]);
        $existingExam = $stmtCheck->fetch();
        
        if ($existingExam) {
            $target_exam = $existingExam['id'];
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO target_exams (name, status) VALUES (?, 'active')");
            $stmtInsert->execute([$custom_exam]);
            $target_exam = $pdo->lastInsertId();
        }
    } elseif ($target_exam === 'other') {
        $target_exam = null; // If they selected other but didn't type anything
    }
    
    if (empty($name) || empty($email) || empty($mobile) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error = "Mobile number must be exactly 10 digits.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email is already registered.";
        } else {
            // Register user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $mobile, $hashed_password]);
                $new_user_id = $pdo->lastInsertId();
                
                if (!empty($target_exam)) {
                    $stmt_pref = $pdo->prepare("INSERT INTO student_preferences (user_id, target_exam_id) VALUES (?, ?)");
                    $stmt_pref->execute([$new_user_id, $target_exam]);
                }
                $pdo->commit();
                $success = "Registration successful! You can now login.";

                // --- SEND WELCOME EMAIL ---
                try {
                    // Fallback standard PHP mail()
                    $to = $email;
                    $subject = "Welcome to English Learning - Let's Start Your Journey!";
                    $message = "Hello $name,\n\n";
                    $message .= "Congratulations and welcome to the English Learning community! We are absolutely thrilled to have you onboard. Your account has been successfully created.\n\n";
                    $message .= "Our platform is designed to help you master the English language through consistent practice and engaging content. Here are a few things you can do right now to get started:\n\n";
                    $message .= "1. Update Your Profile: Add a profile picture and select your target exam so we can personalize your experience.\n";
                    $message .= "2. Write Your First Story: Practice your grammar and vocabulary by writing short stories. Our admins will review them and provide helpful feedback!\n";
                    $message .= "3. Read & Learn: Browse through stories published by others and discover new vocabulary, idioms, and meanings.\n\n";
                    $message .= "We believe that with daily practice, you will achieve your English learning goals very soon.\n\n";
                    $message .= "Happy Learning!\n\n";
                    $message .= "Warm Regards,\n";
                    $message .= "The English Learning Team";
                    
                    $headers = "From: noreply@" . $_SERVER['SERVER_NAME'];
                    
                    // Check if SMTP is configured, else use mail()
                    $stmt_smtp = $pdo->query("SELECT * FROM smtp_settings LIMIT 1");
                    $smtp = $stmt_smtp->fetch();
                    if ($smtp && !empty($smtp['smtp_user'])) {
                        require_once 'includes/PHPMailer/Exception.php';
                        require_once 'includes/PHPMailer/PHPMailer.php';
                        require_once 'includes/PHPMailer/SMTP.php';

                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host       = $smtp['smtp_host'];
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $smtp['smtp_user'];
                        $mail->Password   = $smtp['smtp_pass'];
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = $smtp['smtp_port'];
                        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
                        $mail->addAddress($email, $name);
                        $mail->isHTML(true);
                        $mail->Subject = $smtp['welcome_subject'] ? $smtp['welcome_subject'] : $subject;
                        $body = str_replace(['{name}', '{email}'], [$name, $email], $smtp['welcome_body']);
                        $mail->Body = $body ? $body : nl2br($message);
                        $mail->send();
                    } else {
                        // Use default mail() if no SMTP settings found
                        @mail($to, $subject, $message, $headers);
                    }
                } catch (Exception $e) {
                    // Fail silently so user still gets registered successfully, but try default mail() just in case table doesn't exist
                    $fallback_msg = "Hello $name,\n\nCongratulations and welcome to the English Learning community! Your account has been successfully created.\n\nOur platform is designed to help you master the English language through consistent practice. You can now update your profile, read stories, and write your own stories to get feedback from our admins.\n\nHappy Learning!\n\nThe English Learning Team";
                    @mail($email, "Welcome to English Learning - Let's Start Your Journey!", $fallback_msg, "From: noreply@" . $_SERVER['SERVER_NAME']);
                    error_log("Welcome Email Error: " . $e->getMessage());
                }
                // ------------------------------------
                
                // Redirect to avoid form resubmission on refresh
                header("Location: register.php?registered=1");
                exit();
            } catch(PDOException $e) {
                $pdo->rollBack();
                $error = "Registration failed. Please try again later.";
            }
        }
    }
}

// Fetch active exams for dropdown
$exams = $pdo->query("SELECT id, name FROM target_exams WHERE status = 'active' ORDER BY id")->fetchAll();


$page_title = 'Register';
include 'includes/header.php';
?>

<style>
/* Ultra Professional Login Styles */
.login-wrapper {
    min-height: calc(100vh - 76px);
    display: flex;
    align-items: center;
    background: #f8f9fa;
    padding: 2rem 0;
}
.login-card {
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
    background: #fff;
}
.login-left {
    background: linear-gradient(135deg, var(--bs-primary) 0%, #1a237e 100%);
    color: white;
    padding: 3rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.login-left::after {
    content: '';
    position: absolute;
    bottom: -50px;
    right: -50px;
    width: 250px;
    height: 250px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}
.login-left::before {
    content: '';
    position: absolute;
    top: -50px;
    left: -50px;
    width: 150px;
    height: 150px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
}
.login-right {
    padding: 3.5rem;
}
.custom-input {
    border: 2px solid #eef2f5;
    border-radius: 12px;
    padding: 0.8rem 2.5rem 0.8rem 2.8rem;
    transition: all 0.2s ease;
    background-color: #fcfcfc;
    font-size: 1.05rem;
}
.custom-select {
    padding-right: 1rem !important; /* Selects don't have right icons */
}
.custom-input:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.15);
    background-color: #fff;
}
.login-btn {
    border-radius: 12px;
    padding: 0.8rem 1.5rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
}
.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(var(--bs-primary-rgb), 0.3);
}
/* Hide native Edge/IE password reveal eye */
input::-ms-reveal,
input::-ms-clear {
    display: none;
}
.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #6c757d;
    z-index: 5;
    padding: 10px;
}
.toggle-password:hover {
    color: var(--bs-primary);
}
.fade-up {
    animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 767.98px) {
    .login-left { display: none !important; }
    .login-right { padding: 2rem 1.5rem; }
}
</style>

<div class="login-wrapper">
    <div class="container fade-up py-4">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">
                <div class="login-card row g-0">
                    
                    <!-- Left Branding Side -->
                    <div class="col-md-5 login-left d-none d-md-flex">
                        <div class="position-relative z-1">
                            <h2 class="fw-bold mb-4 display-6">Join Our<br>Community</h2>
                            <p class="lead opacity-75 mb-4" style="font-size: 1.1rem;">Create an account to start your English learning journey, track daily routines, and write your own stories.</p>
                            
                            <ul class="list-unstyled mt-4 mb-5">
                                <li class="mb-3 d-flex align-items-center"><i class="fas fa-check-circle text-success me-3 bg-white rounded-circle"></i> <span>Daily Routine Tracker</span></li>
                                <li class="mb-3 d-flex align-items-center"><i class="fas fa-check-circle text-success me-3 bg-white rounded-circle"></i> <span>Write & Publish Stories</span></li>
                                <li class="mb-3 d-flex align-items-center"><i class="fas fa-check-circle text-success me-3 bg-white rounded-circle"></i> <span>Learn Idioms & Vocabulary</span></li>
                                <li class="mb-3 d-flex align-items-center"><i class="fas fa-check-circle text-success me-3 bg-white rounded-circle"></i> <span>Target specific exams</span></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Right Form Side -->
                    <div class="col-md-7 login-right bg-white">
                        <div class="mb-4">
                            <h3 class="fw-bold text-dark mb-1">Create an Account</h3>
                            <p class="text-muted">Fill in the details below to get started.</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 shadow-sm rounded-3"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success border-0 shadow-sm rounded-3 text-center py-4">
                                <i class="fas fa-check-circle fa-3x mb-3 text-success"></i><br>
                                <h5 class="fw-bold"><?= escape($success) ?></h5>
                                <a href="login.php" class="btn btn-primary mt-3 px-4 shadow-sm border-0 bg-primary-custom rounded-pill">Proceed to Login</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <i class="fas fa-user position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                            <input type="text" class="form-control form-control-lg custom-input" id="name" name="name" placeholder="Full Name" required value="<?= isset($_POST['name']) ? escape($_POST['name']) : '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3 position-relative">
                                            <i class="fas fa-phone position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                            <input type="text" inputmode="numeric" class="form-control form-control-lg custom-input" id="mobile" name="mobile" placeholder="10-digit Mobile No." required pattern="[0-9]{10}" maxlength="10" minlength="10" title="Please enter exactly 10 digits" oninput="this.value = this.value.replace(/[^0-9]/g, '');" value="<?= isset($_POST['mobile']) ? escape($_POST['mobile']) : '' ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3 position-relative">
                                    <i class="fas fa-envelope position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                    <input type="email" class="form-control form-control-lg custom-input" id="email" name="email" placeholder="Email Address" required value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>">
                                </div>

                                <div class="mb-4 position-relative">
                                    <i class="fas fa-bullseye position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                    <select class="form-select form-select-lg custom-input custom-select" id="target_exam" name="target_exam" onchange="toggleCustomExam()">
                                        <option value="">Select Target Exam (Optional)...</option>
                                        <?php foreach($exams as $ex): ?>
                                            <option value="<?= $ex['id'] ?>" <?= (isset($_POST['target_exam']) && $_POST['target_exam'] == $ex['id']) ? 'selected' : '' ?>><?= escape($ex['name']) ?></option>
                                        <?php endforeach; ?>
                                        <option value="other" <?= (isset($_POST['target_exam']) && $_POST['target_exam'] == 'other') ? 'selected' : '' ?>>Other (Please Specify)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4 position-relative" id="custom_exam_div" style="display: <?= (isset($_POST['target_exam']) && $_POST['target_exam'] == 'other') ? 'block' : 'none' ?>;">
                                    <i class="fas fa-edit position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                    <input type="text" class="form-control form-control-lg custom-input" id="custom_exam" name="custom_exam" placeholder="Type your exam name" value="<?= isset($_POST['custom_exam']) ? escape($_POST['custom_exam']) : '' ?>">
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-4 position-relative">
                                            <i class="fas fa-lock position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                            <input type="password" class="form-control form-control-lg custom-input" id="password" name="password" placeholder="Password" required>
                                            <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-4 position-relative">
                                            <i class="fas fa-lock position-absolute text-muted" style="left: 18px; top: 50%; transform: translateY(-50%); z-index: 10;"></i>
                                            <input type="password" class="form-control form-control-lg custom-input" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                            <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-grid mb-4 mt-2">
                                    <button type="submit" class="btn btn-primary bg-primary-custom login-btn border-0 shadow-sm">
                                        Create Account <i class="fas fa-user-plus ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-4 pt-2 border-top">
                            <p class="text-muted mb-0">Already have an account? <a href="login.php" class="text-primary fw-bold text-decoration-none ms-1">Sign in here</a></p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function toggleCustomExam() {
    const select = document.getElementById('target_exam');
    const customDiv = document.getElementById('custom_exam_div');
    const customInput = document.getElementById('custom_exam');
    
    if (select.value === 'other') {
        customDiv.style.display = 'block';
        customInput.setAttribute('required', 'required');
    } else {
        customDiv.style.display = 'none';
        customInput.removeAttribute('required');
        customInput.value = '';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
