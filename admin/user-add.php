<?php
$page_title = "Add New User";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must contain exactly 10 digits.";
    } else {
        // Check if email or phone exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        if ($stmt->fetch()) {
            $error = "Email or phone already registered.";
        } else {
            try {
                $declaration_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $declaration_datetime = date('Y-m-d H:i:s');
                $declaration_accepted = 1;

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, phone, password, is_verified, declaration_accepted, declaration_datetime, declaration_ip, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$first_name, $last_name, $email, $phone, $hashed_password, 1, $declaration_accepted, $declaration_datetime, $declaration_ip]);
                $new_user_id = $pdo->lastInsertId();

                setFlashMessage('success', 'User has been registered successfully without payment.');
                header("Location: users.php");
                exit;
            } catch (Exception $e) {
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div class="d-flex align-items-center">
            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3 class="mb-0 text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i> Add New User</h3>
        </div>
        <a href="users.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Users</a>
    </div>
    
    <div class="card border-0 shadow-sm p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="user-add.php">
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
            
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control phone-input" pattern="\d{10}" title="Enter exactly 10 digits" inputmode="numeric" maxlength="10" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="text" name="password" class="form-control" required>
                <small class="text-muted">Password will be set for the user. They can change it later.</small>
            </div>
            
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Register User</button>
        </form>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
