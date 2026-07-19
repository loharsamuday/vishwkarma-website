<?php
$page_title = "Business Directory";
require_once 'includes/db.php';
require_once 'includes/session.php';

// Dynamic Search Logic
$query = "SELECT * FROM business_directory WHERE 1=1";
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $query .= " AND business_name LIKE ?";
    $params[] = "%" . $_GET['search'] . "%";
}
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $query .= " AND category = ?";
    $params[] = $_GET['category'];
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$businesses = $stmt->fetchAll();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- Header Banner -->
<?php 
$banner_business = function_exists('getUiImage') ? getUiImage('banner_business', 'https://placehold.co/1920x400/2c3e50/f39c12?text=Business+Directory') : "https://placehold.co/1920x400/2c3e50/f39c12?text=Business+Directory";
?>
<div class="bg-dark text-white py-5 text-center" style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('<?= htmlspecialchars($banner_business) ?>') center/cover;">
    <div class="container">
        <h1 class="display-4 fw-bold text-warning">Community Business Directory</h1>
        <p class="lead">Support and grow with businesses owned by the Vishwakarma Samaj.</p>
        <?php if(!isLoggedIn()): ?>
            <a href="login.php" class="btn btn-warning btn-lg mt-3 fw-bold">Login to Register Business</a>
        <?php else: ?>
            <a href="business-register.php" class="btn btn-warning btn-lg mt-3 fw-bold">Register Your Business</a>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card card-custom p-4 shadow-sm border-0 bg-white">
                <form action="business-directory.php" method="GET">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control form-control-lg" placeholder="Search by Business Name..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="category" class="form-select form-select-lg">
                                <option value="">All Categories</option>
                                <?php
                                $categories = ['Goldsmith', 'Blacksmith', 'Carpenter', 'Hardware', 'Workshop', 'Other'];
                                $sel_cat = $_GET['category'] ?? '';
                                foreach($categories as $cat) {
                                    $selected = ($sel_cat == $cat) ? 'selected' : '';
                                    echo "<option value='$cat' $selected>$cat</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php if(empty($businesses)): ?>
            <div class="col-12 text-center text-muted">
                <div class="card card-custom p-5 border-0 shadow-sm">
                    <i class="fa-solid fa-briefcase fa-3x mb-3 text-warning"></i>
                    <h4>No businesses found.</h4>
                    <p>Be the first to <a href="business-register.php" class="text-warning">register your business</a>!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach($businesses as $b): 
                $logo = $b['logo'] ? BASE_URL . "uploads/documents/" . $b['logo'] : "https://placehold.co/400x200/f39c12/white?text=".urlencode($b['business_name']);
            ?>
            <div class="col-md-4 col-sm-6">
                <div class="card card-custom h-100 p-3 border-0 shadow-sm hover-lift">
                    <img src="<?= htmlspecialchars($logo) ?>" class="card-img-top rounded mb-3" alt="Business Logo" style="height: 180px; object-fit: cover;">
                    <div class="card-body p-0">
                        <span class="badge bg-warning mb-2"><?= htmlspecialchars($b['category']) ?></span>
                        <h5 class="fw-bold"><?= htmlspecialchars($b['business_name']) ?></h5>
                        <p class="text-muted small mb-1"><i class="fa-solid fa-location-dot me-2 text-warning"></i><?= htmlspecialchars($b['address']) ?></p>
                        <p class="text-muted small"><i class="fa-solid fa-phone me-2 text-warning"></i><?= htmlspecialchars($b['phone']) ?></p>
                        <p class="small text-muted mb-3"><?= htmlspecialchars(substr($b['description'], 0, 80)) ?>...</p>
                        <a href="#" class="btn btn-outline-warning w-100">View Details</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
