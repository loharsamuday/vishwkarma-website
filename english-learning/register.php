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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($mobile) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
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
                $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $mobile, $hashed_password]);
                $success = "Registration successful! You can now login.";
            } catch(PDOException $e) {
                $error = "Registration failed. Please try again later.";
            }
        }
    }
}

$page_title = 'Register';
include 'includes/header.php';
?>

<div class="container my-5 pb-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-0 text-center pt-4 pb-0">
                    <h2 class="fw-bold text-primary-custom">Create an Account</h2>
                    <p class="text-muted">Join us to write stories and track your progress.</p>
                </div>
                <div class="card-body p-4 p-md-5 pt-3">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= escape($success) ?></div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label fw-bold">Full Name</label>
                                <input type="text" class="form-control form-control-lg bg-light" id="name" name="name" required value="<?= isset($_POST['name']) ? escape($_POST['name']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Email Address</label>
                                <input type="email" class="form-control form-control-lg bg-light" id="email" name="email" required value="<?= isset($_POST['email']) ? escape($_POST['email']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="mobile" class="form-label fw-bold">Mobile Number</label>
                                <input type="text" class="form-control form-control-lg bg-light" id="mobile" name="mobile" required value="<?= isset($_POST['mobile']) ? escape($_POST['mobile']) : '' ?>" placeholder="10-digit mobile number">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label fw-bold">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg bg-light border-end-0" id="password" name="password" required>
                                    <button class="btn btn-outline-secondary bg-light border border-start-0" type="button" onclick="togglePassword('password', this)">
                                        <i class="fas fa-eye text-muted"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-bold">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg bg-light border-end-0" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary bg-light border border-start-0" type="button" onclick="togglePassword('confirm_password', this)">
                                        <i class="fas fa-eye text-muted"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary bg-primary-custom btn-lg shadow-sm">Sign Up</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center py-3 border-0 rounded-bottom-4">
                    Already have an account? <a href="login.php" class="text-primary-custom fw-bold text-decoration-none">Log in here</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
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
</script>

<?php include 'includes/footer.php'; ?>
