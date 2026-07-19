<?php
$page_title = "Search Matches";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
/* Modern Utilities & Animations */
@keyframes ripple {
  0% { transform: scale(0.8); opacity: 1; }
  100% { transform: scale(2.4); opacity: 0; }
}
.online-blink {
  position: relative;
}
.online-blink::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: #198754;
  border-radius: 50%;
  z-index: -1;
  animation: ripple 1.5s infinite ease-out;
}

@keyframes pulseChat {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
  70% { transform: scale(1.05); box-shadow: 0 0 0 8px rgba(255, 193, 7, 0); }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
}
.btn-chat-animate {
  animation: pulseChat 2s infinite;
}

/* Modern Profile Card */
.profile-card {
  border: none;
  border-radius: 15px;
  overflow: hidden;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0,0,0,0.05);
  background: #fff;
}
.profile-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.profile-card .card-banner {
  height: 80px;
  background: linear-gradient(135deg, #f39c12, #f1c40f);
}
.profile-card .profile-img-wrap {
  margin-top: -50px;
  text-align: center;
  position: relative;
}
.profile-card .profile-img-wrap img {
  width: 100px;
  height: 100px;
  object-fit: cover;
  border: 4px solid #fff;
  border-radius: 50%;
  background: #fff;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.profile-info-list li {
  padding: 6px 0;
  border-bottom: 1px dashed #eee;
  font-size: 0.9rem;
  color: #555;
}
.profile-info-list li:last-child {
  border-bottom: none;
}
.profile-info-list i {
  width: 20px;
  color: #f39c12;
  text-align: center;
  margin-right: 8px;
}
</style>

<?php
// Build the query dynamically
$query = "
    SELECT m.id, m.user_id, m.gender, m.dob, m.education, m.profession, u.first_name, u.last_name, u.last_active, p.profile_pic, r.name as religion
    FROM matrimony_profiles m 
    JOIN users u ON m.user_id = u.id
    LEFT JOIN member_profiles p ON u.id = p.user_id
    LEFT JOIN religions r ON m.religion_id = r.id
    WHERE 1=1
";
$params = [];

if (isset($_GET['gender']) && !empty($_GET['gender'])) {
    $query .= " AND m.gender = ?";
    $params[] = $_GET['gender'];
}

if (isset($_GET['age_from']) && !empty($_GET['age_from'])) {
    $query .= " AND TIMESTAMPDIFF(YEAR, m.dob, CURDATE()) >= ?";
    $params[] = $_GET['age_from'];
}

if (isset($_GET['age_to']) && !empty($_GET['age_to'])) {
    $query .= " AND TIMESTAMPDIFF(YEAR, m.dob, CURDATE()) <= ?";
    $params[] = $_GET['age_to'];
}

if (isset($_GET['religion']) && !empty($_GET['religion'])) {
    if(is_numeric($_GET['religion'])) {
        $query .= " AND m.religion_id = ?";
        $params[] = $_GET['religion'];
    } else {
        $query .= " AND r.name = ?";
        $params[] = $_GET['religion'];
    }
}

$query .= " ORDER BY m.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Fetch Matrimony Settings to know if it's paid mode
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'is_matrimony_paid'");
$matrimony_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$is_paid_mode = isset($matrimony_settings['is_matrimony_paid']) && $matrimony_settings['is_matrimony_paid'] == '1';
?>

<div class="bg-dark text-white py-4 text-center">
    <div class="container">
        <h2 class="fw-bold text-warning">Search Results</h2>
        <p class="mb-0">Found <?= count($results) ?> matching profile(s).</p>
    </div>
</div>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <a href="matrimony.php" class="btn btn-outline-dark rounded-pill px-4"><i class="fa-solid fa-arrow-left me-2"></i>Back to Search</a>
        </div>
    </div>
    
    <div class="row g-4">
        <?php if (empty($results)): ?>
            <div class="col-12 text-center text-muted">
                <div class="card card-custom p-5 border-0 shadow-sm" style="border-radius: 15px;">
                    <i class="fa-solid fa-search-minus fa-3x mb-3 text-warning"></i>
                    <h4>No profiles found matching your criteria.</h4>
                    <p>Try adjusting your search filters.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($results as $index => $profile): 
                $age = date_diff(date_create($profile['dob']), date_create('today'))->y;
                $default_pic = $profile['gender'] == 'Male' ? 'https://placehold.co/150x150/2c3e50/white?text=Groom' : 'https://placehold.co/150x150/f39c12/white?text=Bride';
                $pic = $profile['profile_pic'] ? BASE_URL . "uploads/profile/" . $profile['profile_pic'] : $default_pic;
                
                $is_blurred = !isLoggedIn() && $index >= 3;
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 position-relative">
                <div class="profile-card h-100 d-flex flex-column" style="<?= $is_blurred ? 'filter: blur(5px) grayscale(40%); pointer-events: none; user-select: none;' : '' ?>">
                    <div class="card-banner"></div>
                    <div class="profile-img-wrap">
                        <img src="<?= htmlspecialchars($pic) ?>" alt="Profile">
                        <?php if(isUserOnline($profile['last_active'])): ?>
                            <span class="position-absolute p-2 bg-success border border-light rounded-circle online-blink" style="bottom: 10px; right: 25px; z-index: 2;" title="Online Now">
                                <span class="visually-hidden">Online</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3 text-center flex-grow-1 d-flex flex-column">
                        <h5 class="fw-bold mb-1 text-truncate" title="<?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?>">
                            <?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?>
                        </h5>
                        <p class="text-muted small mb-3">ID: VISH-<?= str_pad($profile['id'], 5, '0', STR_PAD_LEFT) ?></p>
                        
                        <ul class="list-unstyled text-start profile-info-list mb-3 flex-grow-1">
                            <li><i class="fa-solid fa-calendar-alt"></i> <?= $age ?> Years</li>
                            <li class="text-truncate" title="<?= htmlspecialchars($profile['religion'] ?? 'N/A') ?>"><i class="fa-solid fa-hands-praying"></i> <?= htmlspecialchars($profile['religion'] ?? 'N/A') ?></li>
                            <li class="text-truncate" title="<?= htmlspecialchars($profile['education'] ?? 'N/A') ?>"><i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($profile['education'] ?? 'N/A') ?></li>
                            <li class="text-truncate" title="<?= htmlspecialchars($profile['profession'] ?? 'N/A') ?>"><i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($profile['profession'] ?? 'N/A') ?></li>
                        </ul>
                        
                        <div class="row g-2 mt-auto">
                            <div class="col-6">
                                <a href="profile.php?id=<?= $profile['id'] ?>" class="btn btn-outline-dark btn-sm w-100 rounded-pill">Profile</a>
                            </div>
                            <div class="col-6">
                                <a href="discussion.php?user_id=<?= $profile['user_id'] ?>" class="btn btn-warning btn-sm w-100 rounded-pill fw-bold btn-chat-animate"><i class="fa-solid fa-comment-dots"></i> Chat</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if($is_blurred): ?>
                    <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-center" style="z-index: 10; background: rgba(255,255,255,0.4); border-radius: 15px;">
                        <div class="bg-white p-3 rounded-circle shadow mb-3">
                            <i class="fa-solid fa-lock fa-2x text-warning"></i>
                        </div>
                        <h6 class="fw-bold text-dark bg-white px-3 py-1 rounded shadow-sm">Hidden Profile</h6>
                        <a href="register.php" class="btn btn-warning fw-bold mt-2 shadow-sm rounded-pill px-4 stretched-link">
                            <?= (!$is_paid_mode) ? 'Register Free to View' : 'Join to View' ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
