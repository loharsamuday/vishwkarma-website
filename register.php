<?php
$page_title = "Register";
require_once 'includes/db.php';
require_once 'includes/session.php';

require 'vendor/PHPMailer/src/Exception.php';
require 'vendor/PHPMailer/src/PHPMailer.php';
require 'vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']);
    $account_type = $_POST['account_type'] ?? 'general';
    $class_name = $_POST['class_name'] ?? '';
    
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must contain exactly 10 digits.";
    } elseif (!$terms) {
        $error = "You must agree to the Terms & Conditions.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email or phone exists
        $enable_email_verification = !isset($global_settings['enable_email_verification']) || $global_settings['enable_email_verification'] == '1';
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            $error = "Email or phone already registered.";
        } elseif ($enable_email_verification && (!isset($_SESSION['reg_email_verified']) || $_SESSION['reg_email_verified'] !== true || $_SESSION['reg_email'] !== $email)) {
            $error = "Please verify your email address before registering.";
        } else {
            try {
                $pdo->beginTransaction();

                $declaration_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $declaration_datetime = date('Y-m-d H:i:s');
                $declaration_accepted = 1;

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role_id = ($account_type === 'student') ? 4 : 2;
                
                // Insert user with is_verified = 1 since email is already verified via AJAX
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role_id, is_verified, declaration_accepted, declaration_datetime, declaration_ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, $role_id, 1, $declaration_accepted, $declaration_datetime, $declaration_ip]);
                $new_user_id = $pdo->lastInsertId();

                if ($account_type === 'student' && !empty($class_name)) {
                    $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, class_name) VALUES (?, ?)");
                    $stmt->execute([$new_user_id, $class_name]);
                }

                $pdo->commit();

                // Clear session verification data
                unset($_SESSION['reg_otp']);
                unset($_SESSION['reg_email']);
                unset($_SESSION['reg_email_verified']);

                logActivity('Registered new account', 'user', $new_user_id);
                setFlashMessage('success', 'Registration successful! You may now login.');
                header("Location: login.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h3 class="text-center mb-4 text-warning">Create an Account</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" action="register.php" id="registerForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <?php $enable_email_verification = !isset($global_settings['enable_email_verification']) || $global_settings['enable_email_verification'] == '1'; ?>
                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="email" name="email" id="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <?php if ($enable_email_verification): ?>
                                <button type="button" class="btn btn-secondary" id="btnSendOtp">Send OTP</button>
                            <?php endif; ?>
                        </div>
                        <div id="emailMessage" class="small mt-1"></div>
                    </div>
                    
                    <div class="mb-3" id="otpSection" style="display:none;">
                        <label class="form-label">Enter Email OTP <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="otp" id="otp" class="form-control" maxlength="6">
                            <button type="button" class="btn btn-warning fw-bold" id="btnVerifyOtp">Verify</button>
                        </div>
                        <div id="otpMessage" class="small mt-1"></div>
                        <input type="hidden" id="isEmailVerified" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control phone-input" pattern="\d{10}" title="Enter exactly 10 digits" inputmode="numeric" maxlength="10" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <small class="text-muted">Enter only 10 digits, no spaces or symbols.</small>
                    </div>

                    <div class="mb-3 border border-warning p-3 rounded bg-light">
                        <label class="form-label fw-bold text-dark mb-2">Register As *</label>
                        <select name="account_type" class="form-select border-warning" required onchange="toggleStudentSection(this.value)">
                            <option value="general">General Community Member</option>
                            <option value="matrimony">Matrimony User (Looking for Match)</option>
                            <option value="student">Student</option>
                        </select>
                        <small class="text-muted mt-1 d-block">Select "Matrimony User" to be directed to profile setup after login.</small>
                    </div>
                    
                    <div class="mb-3 border border-info p-3 rounded bg-light" id="studentSection" style="display:none;">
                        <label class="form-label fw-bold text-dark mb-2">Select Your Class/Course <span class="text-danger">*</span></label>
                        <select name="class_name" id="class_name" class="form-select border-info">
                            <option value="">Select Class</option>
                            <?php for($i=1; $i<=12; $i++): ?>
                                <option value="Class <?= $i ?>">Class <?= $i ?></option>
                            <?php endfor; ?>
                            <option value="ITI">ITI</option>
                            <option value="Diploma">Diploma</option>
                            <option value="Graduation (B.A/B.Sc/B.Com)">Graduation (B.A/B.Sc/B.Com)</option>
                            <option value="Graduation (B.Tech/BE)">Graduation (B.Tech/BE)</option>
                            <option value="Post-Graduation">Post-Graduation</option>
                            <option value="PhD">PhD</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" class="form-control" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-check mb-3 text-start">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label small text-muted" for="terms">
                            I agree to the <a href="terms.php" target="_blank" class="text-warning text-decoration-none fw-bold">Terms & Conditions</a>
                        </label>
                    </div>
                    
                    <button type="button" class="btn btn-warning w-100 fw-bold mt-2" id="triggerRegisterBtn">Register</button>
                    <button type="submit" class="d-none" id="realSubmitBtn"></button>
                    
                    <p class="text-center mt-3">Already have an account? <a href="login.php" class="text-warning text-decoration-none">Login here</a></p>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Declaration Modal -->
<div class="modal fade" id="declarationModal" tabindex="-1" aria-labelledby="declarationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning">
        <h5 class="modal-title text-dark" id="declarationModalLabel">User Declaration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">I hereby declare that all the information provided by me in this registration form is true, complete and correct to the best of my knowledge and belief. I understand that in the event of any information being found false or incorrect at any stage, my registration shall be liable to be cancelled.</p>
        <div class="form-check mt-3">
            <input class="form-check-input border-warning" type="checkbox" id="declarationCheck" required>
            <label class="form-check-label fw-bold" for="declarationCheck">
                I agree and accept the declaration
            </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-warning fw-bold" onclick="submitRegistration()">Submit Registration</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const triggerBtn = document.getElementById('triggerRegisterBtn');
    const realSubmitBtn = document.getElementById('realSubmitBtn');
    const form = document.getElementById('registerForm');
    
    // OTP Elements
    const btnSendOtp = document.getElementById('btnSendOtp');
    const btnVerifyOtp = document.getElementById('btnVerifyOtp');
    const emailInput = document.getElementById('email');
    const otpInput = document.getElementById('otp');
    const otpSection = document.getElementById('otpSection');
    const emailMessage = document.getElementById('emailMessage');
    const otpMessage = document.getElementById('otpMessage');
    const isEmailVerified = document.getElementById('isEmailVerified');

    let declarationModal;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        declarationModal = new bootstrap.Modal(document.getElementById('declarationModal'));
    }

    // Send OTP
    if (btnSendOtp) {
        btnSendOtp.addEventListener('click', function() {
            const email = emailInput.value.trim();
        const firstName = document.querySelector('input[name="first_name"]').value.trim();
        const lastName = document.querySelector('input[name="last_name"]').value.trim();

        if (!email) {
            emailMessage.innerHTML = '<span class="text-danger">Please enter an email address first.</span>';
            return;
        }

        btnSendOtp.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btnSendOtp.disabled = true;
        emailMessage.innerHTML = '';

        const formData = new FormData();
        formData.append('email', email);
        formData.append('first_name', firstName);
        formData.append('last_name', lastName);

        fetch('ajax_send_reg_otp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btnSendOtp.innerHTML = 'Send OTP';
            btnSendOtp.disabled = false;
            
            if (data.success) {
                emailMessage.innerHTML = '<span class="text-success"><i class="fa-solid fa-check"></i> ' + data.message + '</span>';
                otpSection.style.display = 'block';
                emailInput.readOnly = true; // Prevent changing email after OTP sent
            } else {
                emailMessage.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-exclamation"></i> ' + data.message + '</span>';
            }
        })
        .catch(error => {
            btnSendOtp.innerHTML = 'Send OTP';
            btnSendOtp.disabled = false;
            emailMessage.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> An error occurred.</span>';
        });
    });
    }

    // Verify OTP
    btnVerifyOtp.addEventListener('click', function() {
        const email = emailInput.value.trim();
        const otp = otpInput.value.trim();

        if (!otp) {
            otpMessage.innerHTML = '<span class="text-danger">Please enter the OTP.</span>';
            return;
        }

        btnVerifyOtp.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btnVerifyOtp.disabled = true;

        const formData = new FormData();
        formData.append('email', email);
        formData.append('otp', otp);

        fetch('ajax_verify_reg_otp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                otpMessage.innerHTML = '<span class="text-success fw-bold"><i class="fa-solid fa-check-circle"></i> ' + data.message + '</span>';
                btnVerifyOtp.innerHTML = 'Verified';
                btnVerifyOtp.classList.replace('btn-warning', 'btn-success');
                otpInput.readOnly = true;
                isEmailVerified.value = '1';
                if (btnSendOtp) btnSendOtp.style.display = 'none';
            } else {
                btnVerifyOtp.innerHTML = 'Verify';
                btnVerifyOtp.disabled = false;
                otpMessage.innerHTML = '<span class="text-danger"><i class="fa-solid fa-circle-exclamation"></i> ' + data.message + '</span>';
            }
        })
        .catch(error => {
            btnVerifyOtp.innerHTML = 'Verify';
            btnVerifyOtp.disabled = false;
            otpMessage.innerHTML = '<span class="text-danger"><i class="fa-solid fa-triangle-exclamation"></i> An error occurred.</span>';
        });
    });

    triggerBtn.addEventListener('click', function() {
        if (form.checkValidity()) {
            if (isEmailVerified.value !== '1' && <?= $enable_email_verification ? 'true' : 'false' ?>) {
                alert("Please verify your email address before registering.");
                return;
            }
            if (declarationModal) {
                document.getElementById('declarationCheck').checked = false; // reset checkbox
                declarationModal.show();
            } else {
                if (confirm("I declare that all the information provided by me is true and correct. Do you accept?")) {
                    form.submit();
                }
            }
        } else {
            realSubmitBtn.click();
        }
    });

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
});

function submitRegistration() {
    const check = document.getElementById('declarationCheck');
    if (check.checked) {
        document.getElementById('registerForm').submit();
    } else {
        alert("Please click the checkbox to accept the declaration and proceed.");
    }
}

function toggleStudentSection(val) {
    const section = document.getElementById('studentSection');
    const classSelect = document.getElementById('class_name');
    if (val === 'student') {
        section.style.display = 'block';
        classSelect.setAttribute('required', 'required');
    } else {
        section.style.display = 'none';
        classSelect.removeAttribute('required');
        classSelect.value = '';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
