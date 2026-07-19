<?php
$page_title = "Register as Blood Donor";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';

// Check if already registered
$stmt = $pdo->prepare("SELECT id, blood_group, is_available, last_donated FROM blood_donors WHERE user_id = ?");
$stmt->execute([$user_id]);
$donor = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blood_group = $_POST['blood_group'];
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    $last_donated = !empty($_POST['last_donated']) ? $_POST['last_donated'] : null;
    
    if (empty($blood_group)) {
        $error = "Blood group is required.";
    } else {
        if ($donor) {
            $stmt = $pdo->prepare("UPDATE blood_donors SET blood_group = ?, is_available = ?, last_donated = ? WHERE user_id = ?");
            $stmt->execute([$blood_group, $is_available, $last_donated, $user_id]);
            setFlashMessage('success', 'Blood donor profile updated!');
        } else {
            $stmt = $pdo->prepare("INSERT INTO blood_donors (user_id, blood_group, is_available, last_donated) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $blood_group, $is_available, $last_donated]);
            setFlashMessage('success', 'Successfully registered as a blood donor!');
        }
        header("Location: dashboard.php");
        exit;
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-4 border-top border-4 border-danger shadow-sm">
                <h3 class="fw-bold mb-4 text-danger"><i class="fa-solid fa-droplet me-2"></i>Blood Donor Registration</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label>Blood Group *</label>
                        <select name="blood_group" class="form-select" required>
                            <option value="">Select</option>
                            <?php
                            $bgroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                            $current = $donor['blood_group'] ?? '';
                            foreach($bgroups as $bg) {
                                $sel = ($current == $bg) ? 'selected' : '';
                                echo "<option value='$bg' $sel>$bg</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label>Last Donated Date (Optional)</label>
                        <input type="date" name="last_donated" class="form-control" value="<?= htmlspecialchars($donor['last_donated'] ?? '') ?>">
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="is_available" id="is_available" <?= (!$donor || $donor['is_available'] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_available">
                            I am currently available to donate blood.
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold border-white">Save Details</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
