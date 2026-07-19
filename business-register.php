<?php
$page_title = "Register Business";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = trim($_POST['business_name']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $website = trim($_POST['website']);
    
    // Validate phone number - must be exactly 10 digits
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must contain exactly 10 digits.";
    }
    
    // Logo upload
    $logo = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['logo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid('biz_') . '.' . $ext;
            $upload_path = 'uploads/documents/' . $new_filename; // using documents folder
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_path)) {
                $logo = $new_filename;
            } else {
                $error = "Failed to upload logo.";
            }
        } else {
            $error = "Invalid logo format. Only JPG and PNG allowed.";
        }
    }
    
    if (!$error) {
        $stmt = $pdo->prepare("INSERT INTO business_directory (user_id, business_name, category, description, address, phone, website, logo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $business_name, $category, $description, $address, $phone, $website, $logo])) {
            setFlashMessage('success', 'Business registered successfully!');
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Database error. Please try again.";
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-custom p-4 border-top border-4 border-info shadow-sm">
                <h3 class="fw-bold mb-4 text-info">Register Your Business</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label>Business Name</label>
                        <input type="text" name="business_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Category</label>
                        <select name="category" class="form-select" required>
                            <option value="">Select Category</option>
                            <option value="Goldsmith">Goldsmith</option>
                            <option value="Blacksmith">Blacksmith</option>
                            <option value="Carpenter">Carpenter</option>
                            <option value="Hardware">Hardware</option>
                            <option value="Workshop">Workshop</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control phone-input" pattern="\d{10}" title="Enter exactly 10 digits" inputmode="numeric" maxlength="10" required>
                            <small class="text-muted">Enter only 10 digits, no spaces or symbols.</small>
                        </div>
                        <div class="col-md-6">
                            <label>Website (optional)</label>
                            <input type="url" name="website" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Address (City/Location)</label>
                        <input type="text" name="address" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label>Business Logo</label>
                        <input type="file" name="logo" class="form-control" accept="image/jpeg, image/png">
                        <small class="text-muted">Upload a business logo (JPG/PNG).</small>
                    </div>
                    
                    <button type="submit" class="btn btn-info w-100 fw-bold text-white">Register Business</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
