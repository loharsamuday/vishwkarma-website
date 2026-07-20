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

/* Matrimony trust-first visual system */
.match-hero { min-height: 760px !important; isolation: isolate; }
.match-hero::before { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(20,18,30,.94) 0%, rgba(33,23,39,.78) 48%, rgba(20,18,30,.28) 100%); z-index: -1; }
.match-hero .container { position: relative; z-index: 1; }
.match-kicker { display: inline-flex; align-items: center; gap: .55rem; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.1); padding: .55rem .9rem; border-radius: 99px; color: #ffda83; font-size: .75rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
.match-hero h1 { letter-spacing: -.055em; line-height: 1.05; }
.match-hero-copy { max-width: 690px; margin: auto; color: rgba(255,255,255,.83); line-height: 1.7; }
.hero-safety-points { display: flex; justify-content: center; gap: 1.2rem; flex-wrap: wrap; color: rgba(255,255,255,.82); font-size: .82rem; font-weight: 600; margin: 1.65rem 0 2.5rem; }
.hero-safety-points i { color: #ffc857; }
.glass-search { background: rgba(255,255,255,.95); border: 1px solid rgba(255,255,255,.6); border-radius: 18px; padding: 1.4rem; box-shadow: 0 20px 50px rgba(0,0,0,.25); text-align: left; }
.glass-search label { color: #46586a; text-shadow: none; font-size: .7rem; font-weight: 800; letter-spacing: .06em; }
.glass-search .form-select { border: 1px solid #e0e7ed !important; font-size: .93rem; padding: .75rem .85rem; }
.matrimony-trust-bar { background: #fff; border-bottom: 1px solid #e8edf1; }
.trust-item { padding: .65rem 1.1rem; height: 100%; border-right: 1px solid #e8edf1; }
.trust-item:last-child { border: 0; }
.trust-item .trust-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 12px; background: #fff4dd; color: #cf7b08; }
.match-section { padding: 6rem 0; }
.match-eyebrow { color: #bf6c07; font-weight: 800; font-size: .72rem; letter-spacing: .13em; text-transform: uppercase; }
.match-heading { color: #1d2b38; letter-spacing: -.04em; }
.process-card { border: 1px solid #e5ebf0; border-radius: 15px; padding: 1.5rem; height: 100%; background: #fff; box-shadow: 0 10px 25px rgba(16,39,59,.04); }
.process-number { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 50%; background: #fff0d3; color: #bd6d05; font-weight: 800; margin-bottom: 1.05rem; }
.privacy-panel { background: linear-gradient(135deg, #142d42, #0d2234); color: white; border-radius: 20px; overflow: hidden; }
.privacy-panel p { color: rgba(255,255,255,.7); }
.privacy-list li { margin-bottom: 1rem; color: rgba(255,255,255,.88); }
.privacy-list i { color: #ffc857; width: 22px; }
.profile-card { border: 1px solid #e6ebef; border-radius: 16px; box-shadow: 0 10px 28px rgba(16,39,59,.06); }
.profile-card:hover { transform: translateY(-7px); box-shadow: 0 18px 36px rgba(16,39,59,.12); border-color: rgba(231,155,23,.45); }
.profile-card .card-banner { height: 74px; background: linear-gradient(120deg, #18384f, #d98c14); }
.profile-card .profile-img-wrap img { width: 92px; height: 92px; }
.guest-profile-photo { filter: saturate(.8); }
.profile-info-list li { color: #536575; }
.profile-disclaimer { background: #fff8e9; border: 1px solid #f4d69c; border-radius: 12px; color: #765016; }
.bottom-match-cta { background: radial-gradient(circle at 82% 20%, rgba(255,215,128,.35), transparent 25%), linear-gradient(125deg, #142c40, #0b2033); color: white; }
.bottom-match-cta .lead { color: rgba(255,255,255,.77); }
@media (max-width: 767.98px) { .match-hero { min-height: 700px !important; } .match-hero h1 { font-size: 2.6rem; } .hero-safety-points { gap: .75rem; } .trust-item { border-right: 0; border-bottom: 1px solid #e8edf1; } .match-section { padding: 4rem 0; } }
</style>

<!-- Hero Section with Integrated Search -->
<?php 
$banner_matrimony = function_exists('getUiImage') ? getUiImage('banner_matrimony', 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?q=80&w=1920&auto=format&fit=crop') : "https://images.unsplash.com/photo-1511285560929-80b456fea0bc?q=80&w=1920&auto=format&fit=crop";
?>
<div class="position-relative d-flex align-items-center match-hero" style="background: url('<?= htmlspecialchars($banner_matrimony) ?>') center/cover no-repeat;">
    <div class="container py-5 mt-5">
        <div class="row justify-content-center text-center mb-5">
            <div class="col-lg-8" data-aos="zoom-in">
                <div class="match-kicker mb-4"><i class="fa-solid fa-heart-circle-check"></i> Meaningful introductions, thoughtfully made</div>
                <h1 class="display-3 fw-bold text-white mb-3">Find a connection built on <span class="text-warning">shared values.</span></h1>
                
                <?php if (!$is_paid_mode): ?>
                    <p class="match-hero-copy lead mb-0">Create your profile, explore compatible introductions and take the next step at your own pace. Registration is currently free.</p>
                <?php else: ?>
                    <p class="match-hero-copy lead mb-0">A thoughtful space to create your profile, explore introductions and begin meaningful conversations with care.</p>
                <?php endif; ?>

                <div class="hero-safety-points"><span><i class="fa-solid fa-shield-heart me-2"></i>Privacy-conscious profiles</span><span><i class="fa-solid fa-user-group me-2"></i>Community-led introductions</span><span><i class="fa-solid fa-lock me-2"></i>Share details at your comfort</span></div>

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

        <!-- Quick Search -->
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

<!-- Trust Principles -->
<div class="matrimony-trust-bar py-4">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="trust-item d-flex align-items-center text-start gap-3 justify-content-md-center"><div class="trust-icon"><i class="fa-solid fa-user-shield"></i></div><div><h6 class="fw-bold mb-1">Privacy comes first</h6><p class="text-muted small mb-0">Public views are designed to keep contact details private.</p></div></div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="trust-item d-flex align-items-center text-start gap-3 justify-content-md-center"><div class="trust-icon"><i class="fa-solid fa-address-card"></i></div><div><h6 class="fw-bold mb-1">Complete profiles matter</h6><p class="text-muted small mb-0">Helpful details make every introduction more meaningful.</p></div></div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="trust-item d-flex align-items-center text-start gap-3 justify-content-md-center"><div class="trust-icon"><i class="fa-solid fa-comments"></i></div><div><h6 class="fw-bold mb-1">Connect thoughtfully</h6><p class="text-muted small mb-0">Start with a conversation and proceed when it feels right.</p></div></div>
            </div>
        </div>
    </div>
</div>

<!-- How It Works + Safety -->
<section class="match-section bg-white">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <div class="match-eyebrow mb-2">A respectful way to begin</div>
            <h2 class="fw-bold match-heading display-6">Simple steps. <span class="text-warning">Better introductions.</span></h2>
            <p class="text-muted mx-auto mb-0" style="max-width: 670px;">Take your time, present yourself honestly and let shared values lead the conversation.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up"><div class="process-card"><div class="process-number">01</div><h5 class="fw-bold">Create your profile</h5><p class="text-muted small mb-0">Add the information that helps a compatible family understand you—your education, profession and preferences.</p></div></div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100"><div class="process-card"><div class="process-number">02</div><h5 class="fw-bold">Explore with intention</h5><p class="text-muted small mb-0">Use the search filters to discover profiles that align with what matters most to you.</p></div></div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200"><div class="process-card"><div class="process-number">03</div><h5 class="fw-bold">Start a respectful conversation</h5><p class="text-muted small mb-0">When there is mutual interest, connect thoughtfully and involve family at the pace you prefer.</p></div></div>
        </div>
        <div class="privacy-panel p-4 p-lg-5 mt-5" data-aos="fade-up">
            <div class="row align-items-center g-4">
                <div class="col-lg-7"><div class="match-eyebrow text-warning mb-2">Your safety matters</div><h3 class="fw-bold mb-3">A genuine journey starts with sensible boundaries.</h3><p class="mb-0">Avoid sending money, sharing sensitive personal documents, or moving too quickly. Get to know people carefully and verify important details independently.</p></div>
                <div class="col-lg-5"><ul class="list-unstyled privacy-list mb-0"><li><i class="fa-solid fa-check"></i>Keep financial information private</li><li><i class="fa-solid fa-check"></i>Verify details before committing</li><li><i class="fa-solid fa-check"></i>Report suspicious behaviour promptly</li></ul><a href="fraud-alert.php" class="btn btn-outline-light rounded-pill px-4">Read safety guidelines <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
            </div>
        </div>
    </div>
</section>

<!-- Profiles Section -->
<section class="match-section" style="background: #f6f8fa;">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2" data-aos="fade-right">
        <div><div class="match-eyebrow mb-1">New introductions</div><h3 class="fw-bold match-heading mb-0">Recently added matches</h3></div>
        <a href="search.php" class="btn btn-sm btn-outline-dark rounded-pill px-3">View All <i class="fa-solid fa-arrow-right ms-1"></i></a>
    </div>

    <div class="profile-disclaimer d-flex align-items-center gap-3 p-3 mb-4 small"><i class="fa-solid fa-circle-info fs-5"></i><span>For privacy, contact details are never displayed here. Create an account to view profiles responsibly and connect through the platform.</span></div>
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
                $profile_name = isLoggedIn()
                    ? trim($profile['first_name'] . ' ' . $profile['last_name'])
                    : trim($profile['first_name'] . ' ' . strtoupper(substr($profile['last_name'] ?? '', 0, 1)) . '.');
                
                // Show first 3 profiles as tease, blur the rest if not logged in
                $is_blurred = !isLoggedIn() && $index >= 3;
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 position-relative" data-aos="fade-up" data-aos-delay="<?= ($index % 4) * 100 ?>">
                <div class="profile-card h-100 d-flex flex-column" style="<?= $is_blurred ? 'filter: blur(5px) grayscale(40%); pointer-events: none; user-select: none;' : '' ?>">
                    <div class="card-banner"></div>
                    <div class="profile-img-wrap">
                        <img src="<?= htmlspecialchars($pic) ?>" alt="<?= htmlspecialchars($profile_name) ?>" class="<?= !isLoggedIn() ? 'guest-profile-photo' : '' ?>">
                        <?php if(isUserOnline($profile['last_active'])): ?>
                            <span class="position-absolute p-2 bg-success border border-light rounded-circle online-blink" style="bottom: 10px; right: 25px; z-index: 2;" title="Online Now">
                                <span class="visually-hidden">Online</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3 text-center flex-grow-1 d-flex flex-column">
                        <h5 class="fw-bold mb-1 text-truncate" title="<?= htmlspecialchars($profile_name) ?>">
                            <?= htmlspecialchars($profile_name) ?>
                        </h5>
                        <p class="text-muted small mb-3">ID: VISH-<?= str_pad($profile['id'], 5, '0', STR_PAD_LEFT) ?></p>
                        
                        <ul class="list-unstyled text-start profile-info-list mb-3 flex-grow-1">
                            <li><i class="fa-solid fa-calendar-alt"></i> <?= $age ?> Years</li>
                            <li class="text-truncate" title="<?= htmlspecialchars($profile['education'] ?? 'N/A') ?>"><i class="fa-solid fa-graduation-cap"></i> <?= htmlspecialchars($profile['education'] ?? 'N/A') ?></li>
                            <li class="text-truncate" title="<?= htmlspecialchars($profile['profession'] ?? 'N/A') ?>"><i class="fa-solid fa-briefcase"></i> <?= htmlspecialchars($profile['profession'] ?? 'N/A') ?></li>
                        </ul>
                        
                        <div class="row g-2 mt-auto">
                            <div class="col-6">
                                <a href="<?= isLoggedIn() ? 'profile.php?id=' . $profile['id'] : 'register.php' ?>" class="btn btn-outline-dark btn-sm w-100 rounded-pill"><?= isLoggedIn() ? 'Profile' : 'Details' ?></a>
                            </div>
                            <div class="col-6">
                                <a href="<?= isLoggedIn() ? 'discussion.php?user_id=' . $profile['user_id'] : 'register.php' ?>" class="btn btn-warning btn-sm w-100 rounded-pill fw-bold <?= isLoggedIn() ? 'btn-chat-animate' : '' ?>"><i class="fa-solid <?= isLoggedIn() ? 'fa-comment-dots' : 'fa-lock' ?>"></i> <?= isLoggedIn() ? 'Chat' : 'Join' ?></a>
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
</section>

<!-- Bottom CTA Section -->
<?php if(!isLoggedIn()): ?>
<div class="bottom-match-cta py-5 text-center">
    <div class="container" data-aos="zoom-in">
        <div class="match-eyebrow text-warning mb-2">Begin with confidence</div>
        <h2 class="fw-bold mb-3">Ready to create a profile that feels like you?</h2>
        <p class="lead mb-4">Join the community, share only what matters and discover introductions at your own pace. <?= (!$is_paid_mode) ? 'Registration is currently free.' : '' ?></p>
        <a href="register.php" class="btn btn-warning btn-lg px-5 py-3 rounded-pill fw-bold shadow hover-scale">
            Create Your Profile <i class="fa-solid fa-arrow-right ms-2"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
