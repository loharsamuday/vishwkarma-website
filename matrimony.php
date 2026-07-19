<?php
$page_title = "Matrimony";
require_once 'includes/db.php';
require_once 'includes/session.php';

// Fetch recent profiles
$stmt = $pdo->query("
    SELECT m.id, m.user_id, m.gender, m.dob, m.education, m.profession, u.first_name, u.last_name, u.last_active, p.profile_pic 
    FROM matrimony_profiles m 
    JOIN users u ON m.user_id = u.id
    LEFT JOIN member_profiles p ON u.id = p.user_id
    ORDER BY m.created_at DESC 
    LIMIT 9
");
$recent_profiles = $stmt->fetchAll();

// Fetch Matrimony Settings
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('is_matrimony_paid', 'matrimony_free_promo_message')");
$matrimony_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$is_paid_mode = isset($matrimony_settings['is_matrimony_paid']) && $matrimony_settings['is_matrimony_paid'] == '1';
$promo_message = $matrimony_settings['matrimony_free_promo_message'] ?? 'Hurry! Register now for free!';

// Check if logged in user is already registered in matrimony
$has_matrimony_profile = false;
if (isLoggedIn()) {
    $stmt_check = $pdo->prepare("SELECT id FROM matrimony_profiles WHERE user_id = ?");
    $stmt_check->execute([$_SESSION['user_id']]);
    if ($stmt_check->fetch()) {
        $has_matrimony_profile = true;
    }
}

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

/* Glassmorphism Search Bar */
.glass-search {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 15px;
  padding: 25px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}
.glass-search label {
  color: #fff;
  font-weight: 500;
  text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
}
.glass-search .form-select, .glass-search .btn {
  border-radius: 10px;
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

<!-- Hero Section with Integrated Search -->
<?php 
$banner_matrimony = function_exists('getUiImage') ? getUiImage('banner_matrimony', 'https://placehold.co/1920x600/e84393/ffffff?text=Matrimony') : "https://placehold.co/1920x600/e84393/ffffff?text=Matrimony";
?>
<div class="position-relative d-flex align-items-center" style="min-height: 80vh; background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.8)), url('<?= htmlspecialchars($banner_matrimony) ?>') center/cover no-repeat;">
    <div class="container py-5 mt-5">
        <div class="row justify-content-center text-center mb-5">
            <div class="col-lg-8" data-aos="zoom-in">
                <h1 class="display-3 fw-bold text-white mb-3" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Find Your Perfect Match</h1>
                
                <?php if (!$is_paid_mode): ?>
                    <h3 class="text-warning fw-bold mb-4 drop-shadow"><i class="fa-solid fa-gift me-2"></i> 100% Free Registration & Matchmaking!</h3>
                    <p class="lead text-light mb-4 text-shadow">Join our growing community. Thousands of verified profiles from all castes and religions.</p>
                <?php else: ?>
                    <p class="lead text-light mb-4 text-shadow">Premium matchmaking services with strictly verified profiles.</p>
                <?php endif; ?>

                <?php if(!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-warning btn-lg px-5 py-3 fw-bold rounded-pill shadow-lg hover-scale fs-5">
                        <i class="fa-solid fa-user-plus me-2"></i> Create Free Profile
                    </a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-warning btn-lg px-5 py-3 fw-bold rounded-pill shadow-lg hover-scale fs-5">
                        <i class="fa-solid fa-table-columns me-2"></i> My Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Glassmorphism Quick Search -->
        <div class="row justify-content-center" data-aos="fade-up" data-aos-delay="200">
            <div class="col-xl-10">
                <div class="glass-search">
                    <form action="search.php" method="GET">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label small text-uppercase tracking-wide">Looking for</label>
                                <select name="gender" class="form-select form-select-lg bg-light border-0">
                                    <option value="Female">Bride</option>
                                    <option value="Male">Groom</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label small text-uppercase tracking-wide">Age From</label>
                                <select name="age_from" class="form-select form-select-lg bg-light border-0">
                                    <?php for($i=18; $i<=60; $i++) echo "<option value='$i'>$i Yrs</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <label class="form-label small text-uppercase tracking-wide">Age To</label>
                                <select name="age_to" class="form-select form-select-lg bg-light border-0">
                                    <?php for($i=18; $i<=60; $i++) echo "<option value='$i' ".($i==30 ? "selected" : "").">$i Yrs</option>"; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <label class="form-label small text-uppercase tracking-wide">Religion</label>
                                <select name="religion" class="form-select form-select-lg bg-light border-0">
                                    <option value="">Any Religion</option>
                                    <option value="Hindu">Hindu</option>
                                    <option value="Muslim">Muslim</option>
                                    <option value="Sikh">Sikh</option>
                                    <option value="Christian">Christian</option>
                                    <option value="Jain">Jain</option>
                                    <option value="Buddhist">Buddhist</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-2 col-12 mt-4 mt-md-0">
                                <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow">Search <i class="fa-solid fa-search ms-1"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Trust Badges Section -->
<div class="bg-light py-4 border-bottom shadow-sm">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <i class="fa-solid fa-shield-halved fa-2x text-warning mb-2"></i>
                <h6 class="fw-bold mb-1">100% Privacy Guaranteed</h6>
                <p class="text-muted small mb-0">Your data is safe and secure with us.</p>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <i class="fa-solid fa-user-check fa-2x text-warning mb-2"></i>
                <h6 class="fw-bold mb-1">Verified Profiles</h6>
                <p class="text-muted small mb-0">Genuine profiles with strict verification.</p>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <i class="fa-solid fa-hand-holding-heart fa-2x text-warning mb-2"></i>
                <h6 class="fw-bold mb-1">Success Stories</h6>
                <p class="text-muted small mb-0">Thousands have found their life partner.</p>
            </div>
        </div>
    </div>
</div>

<!-- Profiles Section -->
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2" data-aos="fade-right">
        <h3 class="fw-bold text-dark mb-0"><i class="fa-solid fa-users text-warning me-2"></i> Recently Added Matches</h3>
        <a href="search.php" class="btn btn-sm btn-outline-dark rounded-pill px-3">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>

    <div class="row g-4">
        <?php if (empty($recent_profiles)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fa-solid fa-box-open fa-3x mb-3 text-light"></i>
                <h5>No profiles found yet.</h5>
                <p>Be the first to <a href="register.php" class="text-warning fw-bold">register</a>!</p>
            </div>
        <?php else: ?>
            <?php foreach ($recent_profiles as $index => $profile): 
                $age = date_diff(date_create($profile['dob']), date_create('today'))->y;
                $default_pic = $profile['gender'] == 'Male' ? 'https://placehold.co/150x150/2c3e50/white?text=Groom' : 'https://placehold.co/150x150/f39c12/white?text=Bride';
                $pic = $profile['profile_pic'] ? BASE_URL . "uploads/profile/" . $profile['profile_pic'] : $default_pic;
                
                // Show first 3 profiles as tease, blur the rest if not logged in
                $is_blurred = !isLoggedIn() && $index >= 3;
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 position-relative" data-aos="fade-up" data-aos-delay="<?= ($index % 4) * 100 ?>">
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

<!-- Bottom CTA Section -->
<?php if(!isLoggedIn()): ?>
<div class="bg-warning text-dark py-5 text-center mt-4">
    <div class="container" data-aos="zoom-in">
        <h2 class="fw-bold mb-3">Ready to find your soulmate?</h2>
        <p class="lead mb-4">Join thousands of happy members and start your journey today. <?= (!$is_paid_mode) ? 'It\'s absolutely FREE!' : '' ?></p>
        <a href="register.php" class="btn btn-dark btn-lg px-5 py-3 rounded-pill fw-bold shadow hover-scale">
            Get Started Now <i class="fa-solid fa-arrow-right ms-2"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
