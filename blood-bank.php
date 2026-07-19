<?php
$page_title = "Blood Bank";
require_once 'includes/db.php';
require_once 'includes/session.php';

$results = null;

if (isset($_GET['blood_group']) && !empty($_GET['blood_group'])) {
    // Only logged in users should ideally see full details, but for demo, we'll show name and location. 
    // Phone numbers can be masked if not logged in.
    $bg = $_GET['blood_group'];
    $city = $_GET['city'] ?? '';
    
    $query = "
        SELECT u.first_name, u.last_name, u.phone, b.blood_group, b.last_donated, p.address, c.name as city_name 
        FROM blood_donors b 
        JOIN users u ON b.user_id = u.id 
        LEFT JOIN member_profiles p ON u.id = p.user_id 
        LEFT JOIN cities c ON p.city_id = c.id
        WHERE b.blood_group = ? AND b.is_available = 1
    ";
    $params = [$bg];
    
    // We do a simple LIKE on address since city might not be linked strictly.
    if (!empty($city)) {
        $query .= " AND (p.address LIKE ? OR c.name LIKE ?)";
        $params[] = "%$city%";
        $params[] = "%$city%";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_blood_bank', 'https://placehold.co/1920x400/e74c3c/ffffff?text=Blood+Bank') : 'https://placehold.co/1920x400/e74c3c/ffffff?text=Blood+Bank'; ?>
<div class="page-banner mb-4">
    <img src="<?= htmlspecialchars($banner_img) ?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">
</div>


<!-- Header Banner -->
<?php 
$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
$banner_blood = (!empty($global_settings['banner_blood'])) ? BASE_URL . "uploads/banners/" . $global_settings['banner_blood'] : "https://placehold.co/1920x400/8b0000/ff4d4d?text=Blood+Bank";
?>
<div class="bg-dark text-white py-5 text-center" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= htmlspecialchars($banner_blood) ?>') center/cover;">
    <div class="container">
        <h1 class="display-4 fw-bold text-white drop-shadow">Community Blood Bank</h1>
        <p class="lead drop-shadow">Donate blood, save lives. Find donors in your area during emergencies.</p>
        <?php if(!isLoggedIn()): ?>
            <a href="login.php" class="btn btn-danger btn-lg mt-3 fw-bold border-white border-2">Login to Register as Donor</a>
        <?php else: ?>
            <a href="blood-register.php" class="btn btn-danger btn-lg mt-3 fw-bold border-white border-2">Register as Donor</a>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5 text-center">
    <h2 class="fw-bold mb-4">Search Blood Donors</h2>
    <form action="blood-bank.php" method="GET" class="row justify-content-center g-3 mb-5">
        <div class="col-md-3">
            <select name="blood_group" class="form-select form-select-lg" required>
                <option value="">Blood Group *</option>
                <?php
                $bgroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
                $sel = $_GET['blood_group'] ?? '';
                foreach($bgroups as $g) {
                    echo "<option value='$g' " . ($sel==$g ? 'selected':'') . ">$g</option>";
                }
                ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="city" class="form-control form-control-lg" placeholder="City or Area (Optional)" value="<?= htmlspecialchars($_GET['city'] ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold border-white border-2">Search</button>
        </div>
    </form>
    
    <div class="alert alert-info d-inline-block mb-5">
        <i class="fa-solid fa-info-circle me-2"></i> Only active donors are displayed in search results.
    </div>
    
    <?php if ($results !== null): ?>
        <h4 class="mb-4 text-start fw-bold border-bottom pb-2">Search Results: <?= htmlspecialchars($_GET['blood_group']) ?> Donors</h4>
        <div class="row g-4 text-start">
            <?php if (empty($results)): ?>
                <div class="col-12">
                    <div class="card card-custom p-4 border-0 shadow-sm text-center">
                        <h5 class="text-muted">No donors found matching your criteria.</h5>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($results as $donor): 
                    // Mask phone if not logged in
                    $phone = isLoggedIn() ? $donor['phone'] : substr($donor['phone'], 0, 2) . '******' . substr($donor['phone'], -2) . ' <small><a href="login.php" class="text-danger">(Login to view)</a></small>';
                ?>
                <div class="col-md-4">
                    <div class="card card-custom p-3 border-top border-4 border-danger shadow-sm">
                        <h5 class="fw-bold text-danger mb-1"><?= htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']) ?></h5>
                        <p class="text-muted small mb-2"><i class="fa-solid fa-droplet text-danger me-1"></i> <?= htmlspecialchars($donor['blood_group']) ?></p>
                        <p class="mb-1"><i class="fa-solid fa-phone text-secondary me-2"></i> <?= $phone ?></p>
                        <p class="mb-0 text-muted small"><i class="fa-solid fa-location-dot text-secondary me-2"></i> <?= htmlspecialchars($donor['address'] ?? 'Location not provided') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
