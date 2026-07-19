<?php
$page_title = "Matrimony Profile Details";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$current_user_id = $_SESSION['user_id'];
$profile_id = $_GET['id'] ?? null;

if (!$profile_id) {
    header("Location: matrimony.php");
    exit;
}

// Fetch Matrimony Settings
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'is_matrimony_paid'");
$matrimony_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$is_paid_mode = isset($matrimony_settings['is_matrimony_paid']) && $matrimony_settings['is_matrimony_paid'] == '1';

// Get the viewer's premium status
$stmt = $pdo->prepare("SELECT is_premium FROM matrimony_profiles WHERE user_id = ?");
$stmt->execute([$current_user_id]);
$viewer_profile = $stmt->fetch();
$is_actual_premium = $viewer_profile ? $viewer_profile['is_premium'] : 0;

// Get the target profile details
$query = "
    SELECT m.*, u.first_name, u.last_name, u.email, u.phone, p.profile_pic, p.city_id, c.name as city_name, r.name as religion, cs.name as caste 
    FROM matrimony_profiles m 
    JOIN users u ON m.user_id = u.id
    LEFT JOIN member_profiles p ON u.id = p.user_id
    LEFT JOIN cities c ON p.city_id = c.id
    LEFT JOIN religions r ON m.religion_id = r.id
    LEFT JOIN castes cs ON m.caste_id = cs.id
    WHERE m.id = ?
";
$stmt = $pdo->prepare($query);
$stmt->execute([$profile_id]);
$profile = $stmt->fetch();

if (!$profile) {
    die("Profile not found.");
}

// Logic for features
$can_chat = $is_actual_premium || !$is_paid_mode;
$can_view_contact = $is_actual_premium || ($current_user_id == $profile_id);

// Check block status
$has_blocked = false;
$is_blocked = false;

if ($current_user_id != $profile['user_id']) {
    $block_stmt = $pdo->prepare("SELECT blocker_id, blocked_id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
    $block_stmt->execute([$current_user_id, $profile['user_id'], $profile['user_id'], $current_user_id]);
    $blocks = $block_stmt->fetchAll();
    
    foreach ($blocks as $block) {
        if ($block['blocker_id'] == $current_user_id) {
            $has_blocked = true;
        }
        if ($block['blocker_id'] == $profile['user_id']) {
            $is_blocked = true;
        }
    }
}

if ($is_blocked || $has_blocked) {
    $can_chat = false;
}
if ($is_blocked) {
    $can_view_contact = false;
}

$age = date_diff(date_create($profile['dob']), date_create('today'))->y;
$default_pic = $profile['gender'] == 'Male' ? 'https://placehold.co/150x150/2c3e50/white?text=Groom' : 'https://placehold.co/150x150/f39c12/white?text=Bride';
$pic = $profile['profile_pic'] ? BASE_URL . "uploads/profile/" . $profile['profile_pic'] : $default_pic;

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-4 text-center mb-4">
            <div class="card card-custom p-4 shadow-sm border-top border-4 border-warning">
                <img src="<?= htmlspecialchars($pic) ?>" class="rounded-circle mx-auto border border-4 border-warning mb-3" alt="Profile" style="width: 200px; height: 200px; object-fit: cover;">
                <h3 class="fw-bold mb-1"><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h3>
                <p class="text-muted mb-2">VISH-<?= str_pad($profile['id'], 5, '0', STR_PAD_LEFT) ?></p>
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <span class="badge bg-light text-dark border"><?= $age ?> Yrs, <?= htmlspecialchars($profile['height']) ?> ft</span>
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($profile['religion']) ?></span>
                </div>
                <?php if ($profile['is_premium']): ?>
                <div class="mb-3">
                    <span class="badge bg-success px-3 py-2 text-uppercase shadow-sm">
                        <i class="fa-solid fa-crown me-1 text-warning"></i> Premium Member
                    </span>
                    <div class="text-success mt-1 small fw-bold">Validity: Lifetime</div>
                </div>
                <?php else: ?>
                <div class="mb-3">
                    <span class="badge bg-secondary px-3 py-2 text-uppercase shadow-sm">
                        Free Member
                    </span>
                    <div class="text-muted mt-1 small">Validity: Basic Plan</div>
                </div>
                <?php endif; ?>
                

                <?php if($current_user_id == $profile['user_id']): ?>
                    <button class="btn btn-secondary w-100 disabled">This is your profile</button>
                <?php else: ?>
                    <?php if ($can_chat): ?>
                        <form action="discussion.php" method="GET" class="mb-2">
                            <input type="hidden" name="user_id" value="<?= $profile['user_id'] ?>">
                            <button type="submit" class="btn btn-warning w-100 fw-bold"><i class="fa-solid fa-comments me-2"></i> Start Discussion</button>
                        </form>
                    <?php else: ?>
                        <?php if ($is_blocked): ?>
                            <button class="btn btn-danger w-100 disabled mb-2"><i class="fa-solid fa-ban me-2"></i> User Unavailable</button>
                        <?php elseif ($has_blocked): ?>
                            <button class="btn btn-secondary w-100 disabled mb-2"><i class="fa-solid fa-ban me-2"></i> You Blocked This User</button>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100 disabled mb-2"><i class="fa-solid fa-lock me-2"></i> Discussion Locked</button>
                            <a href="upgrade.php" class="btn btn-warning w-100 fw-bold text-dark small mb-2"><i class="fa-solid fa-crown me-1"></i> Upgrade to Chat</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <form action="api/block_user.php" method="POST">
                        <input type="hidden" name="target_user_id" value="<?= $profile['user_id'] ?>">
                        <?php if ($has_blocked): ?>
                            <input type="hidden" name="action" value="unblock">
                            <button type="submit" class="btn btn-outline-success w-100 fw-bold"><i class="fa-solid fa-unlock me-2"></i> Unblock User</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="block">
                            <button type="submit" class="btn btn-outline-danger w-100 fw-bold" onclick="return confirm('Are you sure you want to block this user? They will not be able to contact you.');"><i class="fa-solid fa-ban me-2"></i> Block User</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-md-8">
            <?php if (!empty($profile['full_photo'])): ?>
            <div class="card card-custom p-4 shadow-sm mb-4 text-center">
                <h4 class="fw-bold mb-4 border-bottom pb-2">Full Photo</h4>
                <img src="<?= BASE_URL . 'uploads/profile/' . htmlspecialchars($profile['full_photo']) ?>" alt="Full Photo" class="img-fluid rounded" style="max-height: 500px; object-fit: contain;">
            </div>
            <?php endif; ?>

            <div class="card card-custom p-4 shadow-sm mb-4">
                <h4 class="fw-bold mb-4 border-bottom pb-2">Personal Details</h4>
                <div class="row g-3">
                    <div class="col-md-6"><p class="mb-1 text-muted small">Profile Created For</p><h6 class="fw-bold"><?= htmlspecialchars($profile['profile_for']) ?></h6></div>
                    <div class="col-md-6"><p class="mb-1 text-muted small">Marital Status</p><h6 class="fw-bold"><?= htmlspecialchars($profile['marital_status']) ?></h6></div>
                    <div class="col-md-6"><p class="mb-1 text-muted small">Religion</p><h6 class="fw-bold"><?= htmlspecialchars($profile['religion']) ?></h6></div>
                    <div class="col-md-6"><p class="mb-1 text-muted small">Caste</p><h6 class="fw-bold"><?= htmlspecialchars($profile['caste']) ?></h6></div>
                    <?php if(!empty($profile['block'])): ?>
                    <div class="col-md-6"><p class="mb-1 text-muted small">Block/City</p><h6 class="fw-bold"><?= htmlspecialchars($profile['block']) ?></h6></div>
                    <?php endif; ?>
                    <?php if(!empty($profile['district'])): ?>
                    <div class="col-md-6"><p class="mb-1 text-muted small">District</p><h6 class="fw-bold"><?= htmlspecialchars($profile['district']) ?></h6></div>
                    <?php endif; ?>
                    <?php if(!empty($profile['state'])): ?>
                    <div class="col-md-6"><p class="mb-1 text-muted small">State</p><h6 class="fw-bold"><?= htmlspecialchars($profile['state']) ?></h6></div>
                    <?php endif; ?>
                    <?php if(!empty($profile['full_address'])): ?>
                    <div class="col-md-12 mt-3"><p class="mb-1 text-muted small">Full Address</p><h6 class="fw-bold"><?= nl2br(htmlspecialchars($profile['full_address'])) ?></h6></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card card-custom p-4 shadow-sm">
                <h4 class="fw-bold mb-4 border-bottom pb-2">Education & Profession</h4>
                <div class="row g-3">
                    <div class="col-md-6"><p class="mb-1 text-muted small">Highest Education</p><h6 class="fw-bold"><?= htmlspecialchars($profile['education']) ?></h6></div>
                    <div class="col-md-6"><p class="mb-1 text-muted small">Profession</p><h6 class="fw-bold"><?= htmlspecialchars($profile['profession']) ?></h6></div>
                </div>
            </div>

            <div class="card card-custom p-4 shadow-sm mb-4">
                <h4 class="fw-bold mb-4 border-bottom pb-2">Contact Details</h4>
                <?php if ($can_view_contact || $current_user_id == $profile['user_id']): ?>
                    <div class="row g-3">
                        <div class="col-md-6"><p class="mb-1 text-muted small"><i class="fa-solid fa-phone me-1"></i> Phone Number</p><h6 class="fw-bold"><?= htmlspecialchars($profile['phone'] ?? 'Not Provided') ?></h6></div>
                        <div class="col-md-6"><p class="mb-1 text-muted small"><i class="fa-solid fa-envelope me-1"></i> Email</p><h6 class="fw-bold"><?= htmlspecialchars($profile['email'] ?? 'Not Provided') ?></h6></div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary text-center mb-0 border border-secondary border-opacity-50">
                        <i class="fa-solid fa-lock fa-2x text-warning mb-2"></i>
                        <?php if ($is_blocked): ?>
                            <p class="mb-0 text-danger fw-bold">Contact details are hidden due to privacy restrictions.</p>
                        <?php else: ?>
                            <p class="mb-1 fw-bold text-dark">Contact Details Locked</p>
                            <p class="small text-muted mb-3 px-md-3">To view the contact details of any user, please upgrade to Premium Membership. After payment, your access will be enabled within 24 hours.</p>
                            <a href="upgrade.php" class="btn btn-warning btn-sm fw-bold px-4 rounded-pill shadow-sm"><i class="fa-solid fa-crown me-1"></i> Pay Now to Unlock</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
