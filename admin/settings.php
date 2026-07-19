<?php
$page_title = "Site Settings";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$existing_settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Image/Banner Deletion
    if (isset($_POST['delete_image'])) {
        $image_key = $_POST['delete_image'];
        $allowed_keys = [
            'logo_image', 'banner_home', 'banner_matrimony', 'banner_business', 
            'banner_blood', 'banner_education', 'banner_jobs', 'banner_about',
            'banner_events', 'banner_blogs', 'banner_gallery', 'banner_contact',
            'banner_community', 'banner_web_services', 'image_home_about', 'payment_qr_code'
        ];
        
        if (in_array($image_key, $allowed_keys)) {
            $old_img = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $old_img->execute([$image_key]);
            $old_img_val = $old_img->fetchColumn();
            
            if ($old_img_val && file_exists('../uploads/banners/' . $old_img_val)) {
                unlink('../uploads/banners/' . $old_img_val);
            }
            $pdo->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = ?")->execute([$image_key]);
            
            logActivity("Deleted image: $image_key", 'admin', null, $_SESSION['admin_id'] ?? null);
            setFlashMessage('success', 'Image permanently deleted!');
            header("Location: settings.php");
            exit;
        }
    }

    $settings = [
        'social_facebook' => trim($_POST['social_facebook'] ?? ''),
        'social_twitter' => trim($_POST['social_twitter'] ?? ''),
        'social_instagram' => trim($_POST['social_instagram'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'whatsapp_number' => trim($_POST['whatsapp_number'] ?? ''),
        'contact_address' => trim($_POST['contact_address'] ?? ''),
        'forgot_password_text' => trim($_POST['forgot_password_text'] ?? ''),
        'smtp_host' => trim($_POST['smtp_host'] ?? ''),
        'smtp_username' => trim($_POST['smtp_username'] ?? ''),
        'smtp_password' => trim($_POST['smtp_password'] ?? ''),
        'smtp_port' => trim($_POST['smtp_port'] ?? ''),
        'is_matrimony_paid' => isset($_POST['is_matrimony_paid']) ? '1' : '0',
        'matrimony_free_promo_message' => trim($_POST['matrimony_free_promo_message'] ?? ''),
        'promo_mode' => trim($_POST['promo_mode'] ?? 'disabled'),
        'popup_title' => trim($_POST['popup_title'] ?? ''),
        'popup_body' => trim($_POST['popup_body'] ?? ''),
        'popup_highlight_title' => trim($_POST['popup_highlight_title'] ?? ''),
        'popup_highlight_sub' => trim($_POST['popup_highlight_sub'] ?? ''),
        'enable_translation' => isset($_POST['enable_translation']) ? '1' : '0',
        'enable_email_verification' => isset($_POST['enable_email_verification']) ? '1' : '0',
        'payment_price' => trim($_POST['payment_price'] ?? ''),
        'payment_upi_id' => trim($_POST['payment_upi_id'] ?? ''),
        'payment_mode' => trim($_POST['payment_mode'] ?? 'manual'),
        'razorpay_key_id' => trim($_POST['razorpay_key_id'] ?? ''),
        'razorpay_key_secret' => trim($_POST['razorpay_key_secret'] ?? '') !== '' ? trim($_POST['razorpay_key_secret'] ?? '') : ($existing_settings['razorpay_key_secret'] ?? ''),
        'razorpay_currency' => trim($_POST['razorpay_currency'] ?? 'INR'),
        'razorpay_description' => trim($_POST['razorpay_description'] ?? 'Premium Membership Upgrade'),
        'promo_original_price' => trim($_POST['promo_original_price'] ?? ''),
        'promo_discounted_price' => trim($_POST['promo_discounted_price'] ?? ''),
        'promo_delay_seconds' => trim($_POST['promo_delay_seconds'] ?? ''),
        'promo_validity_date' => trim($_POST['promo_validity_date'] ?? '')
    ];

    // Handle File Uploads (Banners/Images)
    $upload_dir = '../uploads/banners/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $banner_fields = [
        'logo_image', 'banner_home', 'banner_matrimony', 'banner_business', 
        'banner_blood', 'banner_education', 'banner_jobs', 'banner_about',
        'banner_events', 'banner_blogs', 'banner_gallery', 'banner_contact',
        'banner_community', 'banner_web_services', 'image_home_about', 'payment_qr_code'
    ];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    
    foreach ($banner_fields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);
            
            if (in_array($file_type, $allowed_types)) {
                if ($file_type === 'image/webp' || $file_type === 'image/avif') {
                    // Direct upload without conversion
                    $ext = ($file_type === 'image/webp') ? 'webp' : 'avif';
                    $new_filename = $field . '_' . time() . '.' . $ext;
                    $destination = $upload_dir . $new_filename;
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        if (!empty($existing_settings[$field]) && file_exists($upload_dir . $existing_settings[$field])) {
                            unlink($upload_dir . $existing_settings[$field]);
                        }
                        $settings[$field] = $new_filename;
                    }
                } else {
                    // Convert JPG/PNG to WEBP
                    $ext = 'webp';
                    $new_filename = $field . '_' . time() . '.' . $ext;
                    $destination = $upload_dir . $new_filename;
                    
                    $image = null;
                    switch ($file_type) {
                        case 'image/jpeg': $image = @imagecreatefromjpeg($file['tmp_name']); break;
                        case 'image/png': 
                            $image = @imagecreatefrompng($file['tmp_name']); 
                            if ($image) {
                                imagepalettetotruecolor($image);
                                imagealphablending($image, true);
                                imagesavealpha($image, true);
                            }
                            break;
                    }
                    
                    if ($image !== null) {
                        imagewebp($image, $destination, 80);
                        imagedestroy($image);
                        if (!empty($existing_settings[$field]) && file_exists($upload_dir . $existing_settings[$field])) {
                            unlink($upload_dir . $existing_settings[$field]);
                        }
                        $settings[$field] = $new_filename;
                    }
                }
            }
        }
    }

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    
    logActivity('Updated Site Settings', 'admin', null, $_SESSION['admin_id'] ?? null);
    
    setFlashMessage('success', 'Site settings updated successfully!');
    header("Location: settings.php");
    exit;
}

// Fetch existing settings
$db_settings = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<?php require_once 'includes/header.php'; ?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-cog text-secondary me-2"></i> Site Settings</h3>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="card border-0 shadow-sm p-4">
        <form method="POST" enctype="multipart/form-data">
            <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold text-dark" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General Settings</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="banners-tab" data-bs-toggle="tab" data-bs-target="#banners" type="button" role="tab">Images & Banners</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="matrimony-tab" data-bs-toggle="tab" data-bs-target="#matrimony" type="button" role="tab">Matrimony</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="popup-tab" data-bs-toggle="tab" data-bs-target="#popup" type="button" role="tab">Promo Popup</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="auth-tab" data-bs-toggle="tab" data-bs-target="#auth" type="button" role="tab">Authentication</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button" role="tab">Email (SMTP)</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold text-dark" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button" role="tab">Payment Settings</button>
                </li>
            </ul>

            <div class="tab-content" id="settingsTabsContent">
                <!-- General Tab -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <h5 class="pb-2 mb-4 text-warning">Website Features</h5>
                    <div class="mb-4 form-check form-switch border p-3 rounded">
                        <input class="form-check-input" type="checkbox" role="switch" id="enable_translation" name="enable_translation" <?= (!isset($db_settings['enable_translation']) || $db_settings['enable_translation'] == '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="enable_translation">Enable Google Translate</label>
                        <div class="text-muted small">Show the English/Hindi translation button on the website menu.</div>
                    </div>

                    <h5 class="border-bottom pb-2 mb-4 text-warning">Social Media Links</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-brands fa-facebook text-primary me-2"></i> Facebook Link</label>
                        <input type="text" name="social_facebook" class="form-control" value="<?= htmlspecialchars($db_settings['social_facebook'] ?? '') ?>" placeholder="https://facebook.com/yourpage">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-brands fa-twitter text-info me-2"></i> Twitter / X Link</label>
                        <input type="text" name="social_twitter" class="form-control" value="<?= htmlspecialchars($db_settings['social_twitter'] ?? '') ?>" placeholder="https://twitter.com/yourhandle">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-brands fa-instagram text-danger me-2"></i> Instagram Link</label>
                        <input type="text" name="social_instagram" class="form-control" value="<?= htmlspecialchars($db_settings['social_instagram'] ?? '') ?>" placeholder="https://instagram.com/yourprofile">
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-4 mt-5 text-warning">Contact Information (Footer)</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Email Address</label>
                        <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($db_settings['contact_email'] ?? 'support@vishwakarmasamaj.com') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Phone Number</label>
                        <input type="text" name="contact_phone" class="form-control phone-input" value="<?= htmlspecialchars($db_settings['contact_phone'] ?? '+91 9876543210') ?>">
                        <small class="text-muted">Enter phone number with country code if needed.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold"><i class="fa-brands fa-whatsapp text-success me-1"></i> WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" class="form-control phone-input" placeholder="+919876543210" value="<?= htmlspecialchars($db_settings['whatsapp_number'] ?? '') ?>">
                        <small class="text-muted">For the floating chat icon (Include country code, e.g., +91).</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Office Address</label>
                        <input type="text" name="contact_address" class="form-control" value="<?= htmlspecialchars($db_settings['contact_address'] ?? 'New Delhi, India') ?>">
                    </div>
                </div>

                <!-- Banners Tab -->
                <div class="tab-pane fade" id="banners" role="tabpanel">
                    <h5 class="pb-2 mb-4 text-warning">Dynamic Images & Banners</h5>
                    <p class="text-muted small mb-4">Upload JPG or PNG images. They will be automatically converted to highly compressed WEBP format to make the website load faster.</p>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Website Logo</label>
                        <?php if(!empty($db_settings['logo_image'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['logo_image'] ?>" height="50" class="rounded border d-block mb-2 bg-light p-1">
                                <button type="submit" name="delete_image" value="logo_image" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete the logo?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Logo
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="logo_image" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i>Replaces the text logo in the navigation bar. <strong>Recommended height: 40px to 60px.</strong></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Home Page: Main Welcome Banner</label>
                        <?php if(!empty($db_settings['banner_home'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['banner_home'] ?>" height="100" class="rounded border d-block mb-2">
                                <button type="submit" name="delete_image" value="banner_home" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Image
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_home" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i>Replaces the 'Welcome to Vishwakarma Samaj' text on the Home page hero section. <strong>Recommended width: 800px.</strong></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Home Page: About Us Image</label>
                        <?php if(!empty($db_settings['image_home_about'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['image_home_about'] ?>" height="100" class="rounded border d-block mb-2">
                                <button type="submit" name="delete_image" value="image_home_about" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Image
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image_home_about" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i>Used on the Home page in the About Samaj section. <strong>Recommended dimension: 600x400 pixels.</strong></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Matrimony Header Banner</label>
                        <?php if(!empty($db_settings['banner_matrimony'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['banner_matrimony'] ?>" height="100" class="rounded border d-block mb-2">
                                <button type="submit" name="delete_image" value="banner_matrimony" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Image
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_matrimony" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i><strong>Recommended dimension: 1920x400 pixels.</strong></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Business Directory Banner</label>
                        <?php if(!empty($db_settings['banner_business'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['banner_business'] ?>" height="100" class="rounded border d-block mb-2">
                                <button type="submit" name="delete_image" value="banner_business" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Image
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_business" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i><strong>Recommended dimension: 1920x400 pixels.</strong></small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Blood Bank Banner</label>
                        <?php if(!empty($db_settings['banner_blood'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['banner_blood'] ?>" height="100" class="rounded border d-block mb-2">
                                <button type="submit" name="delete_image" value="banner_blood" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Image
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_blood" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i><strong>Recommended dimension: 1920x400 pixels.</strong></small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Education Banner</label>
                        <?php if(!empty($db_settings['banner_education'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['banner_education'] ?>" height="100" class="rounded border d-block mb-2">
                                <button type="submit" name="delete_image" value="banner_education" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Image
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_education" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i><strong>Recommended dimension: 1920x400 pixels.</strong></small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">Jobs Portal Banner</label>
                        <?php if(!empty($db_settings['banner_jobs'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['banner_jobs'] ?>" height="100" class="rounded border d-block mb-2">
                                <button type="submit" name="delete_image" value="banner_jobs" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                    <i class="fa-solid fa-trash me-1"></i> Delete Image
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="banner_jobs" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i><strong>Recommended dimension: 1920x400 pixels.</strong></small>
                    </div>

                    <h5 class="border-bottom pb-2 mt-5 mb-4 text-warning">Other Pages Banners</h5>

                    <div class="row">
                        <?php
                        $other_banners = [
                            'banner_about' => 'About Us Banner',
                            'banner_events' => 'Events Banner',
                            'banner_blogs' => 'Blogs Banner',
                            'banner_gallery' => 'Gallery Banner',
                            'banner_contact' => 'Contact Us Banner',
                            'banner_community' => 'Community Directory Banner',
                            'banner_web_services' => 'IT & Web Services Banner'
                        ];
                        foreach ($other_banners as $key => $label):
                        ?>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-bold"><?= $label ?></label>
                            <?php if(!empty($db_settings[$key])): ?>
                                <div class="mb-2">
                                    <img src="../uploads/banners/<?= $db_settings[$key] ?>" height="80" class="rounded border d-block mb-2 w-100" style="object-fit: cover;">
                                    <button type="submit" name="delete_image" value="<?= $key ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete this image?');">
                                        <i class="fa-solid fa-trash me-1"></i> Delete Image
                                    </button>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="<?= $key ?>" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Matrimony Tab -->
                <div class="tab-pane fade" id="matrimony" role="tabpanel">
                    <h5 class="pb-2 mb-4 text-warning">Matrimony Settings</h5>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_matrimony_paid" name="is_matrimony_paid" <?= (isset($db_settings['is_matrimony_paid']) && $db_settings['is_matrimony_paid'] == '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="is_matrimony_paid">Enable Premium Matrimony (Paid Mode)</label>
                        <div class="text-muted small">If turned OFF, matrimony will be completely free for everyone and the promo message will be shown.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Free Promo Message (Ads-like)</label>
                        <textarea name="matrimony_free_promo_message" class="form-control" rows="2" placeholder="e.g. Hurry! Register now for free before we switch to premium!"><?= htmlspecialchars($db_settings['matrimony_free_promo_message'] ?? 'Hurry! Matrimony registration is completely FREE for a limited time. Register now to connect with your life partner!') ?></textarea>
                        <small class="text-muted">This message is only shown when the "Paid Mode" is turned OFF.</small>
                    </div>
                </div>

                <!-- Promo Popup Tab -->
                <div class="tab-pane fade" id="popup" role="tabpanel">
                    <h5 class="pb-2 mb-4 text-warning">Promo Popup Settings</h5>
                    <div class="mb-4 p-3 border border-warning rounded bg-light">
                        <label class="form-label fw-bold text-dark">Promo Display Mode</label>
                        <select name="promo_mode" class="form-select border-warning shadow-sm">
                            <option value="disabled" <?= (isset($db_settings['promo_mode']) && $db_settings['promo_mode'] == 'disabled') ? 'selected' : '' ?>>Disable Promo Popup</option>
                            <option value="free" <?= (isset($db_settings['promo_mode']) && $db_settings['promo_mode'] == 'free') ? 'selected' : '' ?>>Free Promo (Shows Text & Highlights Only)</option>
                            <option value="paid" <?= (isset($db_settings['promo_mode']) && $db_settings['promo_mode'] == 'paid') ? 'selected' : '' ?>>Paid Offer Promo (Shows Prices, Discount & Countdown)</option>
                        </select>
                        <small class="text-muted mt-1 d-block">Choose whether to show a Free registration message or a Paid special offer message.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Popup Title</label>
                        <input type="text" name="popup_title" class="form-control" value="<?= htmlspecialchars($db_settings['popup_title'] ?? "Wait! Don't Miss Out") ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Popup Main Text</label>
                        <textarea name="popup_body" class="form-control" rows="2"><?= htmlspecialchars($db_settings['popup_body'] ?? "Join 1000+ Vishwakarma families today. Find your perfect match, grow your business, and stay connected with your roots!") ?></textarea>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Highlight Title (Blinking)</label>
                            <input type="text" name="popup_highlight_title" class="form-control" value="<?= htmlspecialchars($db_settings['popup_highlight_title'] ?? "100% FREE Registration") ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Highlight Subtext</label>
                            <input type="text" name="popup_highlight_sub" class="form-control" value="<?= htmlspecialchars($db_settings['popup_highlight_sub'] ?? "For a limited time only!") ?>">
                        </div>
                    </div>
                    
                    <h6 class="mt-4 border-bottom pb-2 text-warning">Pricing & Advanced Offer Settings</h6>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Original Price (₹)</label>
                            <input type="number" name="promo_original_price" class="form-control" value="<?= htmlspecialchars($db_settings['promo_original_price'] ?? '1000') ?>" placeholder="1000">
                            <small class="text-muted">This will be shown with a cut mark.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Discounted Price (₹)</label>
                            <input type="number" name="promo_discounted_price" class="form-control" value="<?= htmlspecialchars($db_settings['promo_discounted_price'] ?? '500') ?>" placeholder="500">
                            <small class="text-muted">Discount % is auto-calculated.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Display Delay (Seconds)</label>
                            <input type="number" name="promo_delay_seconds" class="form-control" value="<?= htmlspecialchars($db_settings['promo_delay_seconds'] ?? '5') ?>" placeholder="5">
                            <small class="text-muted">Time before popup appears.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Validity End Date & Time</label>
                            <input type="datetime-local" name="promo_validity_date" class="form-control" value="<?= htmlspecialchars($db_settings['promo_validity_date'] ?? '') ?>">
                            <small class="text-muted">Leave empty for no expiry.</small>
                        </div>
                    </div>
                </div>

                <!-- Authentication Tab -->
                <div class="tab-pane fade" id="auth" role="tabpanel">
                    <h5 class="pb-2 mb-4 text-warning">Authentication Settings</h5>
                    
                    <div class="mb-4 form-check form-switch border p-3 rounded">
                        <input class="form-check-input" type="checkbox" role="switch" id="enable_email_verification" name="enable_email_verification" <?= (!isset($db_settings['enable_email_verification']) || $db_settings['enable_email_verification'] == '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="enable_email_verification">Enable Email OTP Verification during Registration</label>
                        <div class="text-muted small">If turned OFF, new users will be able to register instantly without verifying their email via OTP. Turn this OFF during testing to prevent SMTP limits.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Forgot Password Instructions</label>
                        <textarea name="forgot_password_text" class="form-control" rows="2" placeholder="e.g. Please contact Admin at 9876543210 to reset your password."><?= htmlspecialchars($db_settings['forgot_password_text'] ?? 'Please contact the Administrator to reset your password.') ?></textarea>
                        <small class="text-muted">This message will be shown to users who click 'Forgot Password'.</small>
                    </div>
                </div>

                <!-- Email Tab -->
                <div class="tab-pane fade" id="smtp" role="tabpanel">
                    <h5 class="pb-2 mb-4 text-warning">Email (SMTP) Settings</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">SMTP Host</label>
                            <input type="text" name="smtp_host" id="smtp_host" class="form-control" value="<?= htmlspecialchars($db_settings['smtp_host'] ?? '') ?>" placeholder="e.g. smtp.gmail.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">SMTP Port</label>
                            <input type="number" name="smtp_port" id="smtp_port" class="form-control" value="<?= htmlspecialchars($db_settings['smtp_port'] ?? '587') ?>" placeholder="587">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">SMTP Username (Email)</label>
                            <input type="email" name="smtp_username" id="smtp_username" class="form-control" value="<?= htmlspecialchars($db_settings['smtp_username'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">SMTP Password (App Password)</label>
                            <div class="input-group">
                                <input type="password" name="smtp_password" id="smtp_password" class="form-control" value="<?= htmlspecialchars($db_settings['smtp_password'] ?? '') ?>">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3 border border-secondary p-3 rounded text-dark" style="background-color: rgba(255,255,255,0.05);">
                        <h6 class="fw-bold mb-2 text-info">Test SMTP Connection</h6>
                        <p class="small text-muted mb-2">Fill the form above (no need to save first) and enter an email address to test the SMTP settings.</p>
                        <div class="input-group">
                            <input type="email" id="test_email" class="form-control" placeholder="Recipient email address for test" value="<?= htmlspecialchars($db_settings['smtp_username'] ?? '') ?>">
                            <button type="button" class="btn btn-info fw-bold" id="btnTestSmtp">Test Email</button>
                            <button type="button" class="btn btn-primary fw-bold" id="btnTestVerification" title="See how the verification OTP looks"><i class="fa-solid fa-envelope-open-text me-1"></i> Test OTP Template</button>
                        </div>
                        <div id="smtpTestResult" class="mt-2" style="display:none;"></div>
                    </div>
                </div>
                
                <!-- Payment Settings Tab -->
                <div class="tab-pane fade" id="payment" role="tabpanel">
                    <h5 class="pb-2 mb-4 text-warning">Payment Gateway Settings</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Premium Subscription Price (₹)</label>
                        <input type="number" name="payment_price" class="form-control" value="<?= htmlspecialchars($db_settings['payment_price'] ?? '999') ?>" placeholder="999">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Payment Mode</label>
                        <select name="payment_mode" class="form-select">
                            <option value="manual" <?= (($db_settings['payment_mode'] ?? 'manual') === 'manual') ? 'selected' : '' ?>>UPI / Manual Verification (Legacy)</option>
                            <option value="razorpay" <?= (($db_settings['payment_mode'] ?? 'manual') === 'razorpay') ? 'selected' : '' ?>>Razorpay Only</option>
                            <option value="both" <?= (($db_settings['payment_mode'] ?? 'manual') === 'both') ? 'selected' : '' ?>>Both (Razorpay + Manual UPI)</option>
                        </select>
                        <small class="text-muted">Legacy UPI remains available when you choose manual or both.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">UPI ID</label>
                        <input type="text" name="payment_upi_id" class="form-control" value="<?= htmlspecialchars($db_settings['payment_upi_id'] ?? 'vishwakarma@upi') ?>" placeholder="e.g. yourname@ybl">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Razorpay Key ID</label>
                            <input type="text" name="razorpay_key_id" class="form-control" value="<?= htmlspecialchars($db_settings['razorpay_key_id'] ?? '') ?>" placeholder="rzp_test_xxxxxxxxxx">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Razorpay Key Secret</label>
                            <div class="input-group">
                                <input type="password" name="razorpay_key_secret" class="form-control" placeholder="Leave blank to keep current secret">
                                <button class="btn btn-outline-secondary toggle-password" type="button" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Razorpay Currency</label>
                            <input type="text" name="razorpay_currency" class="form-control" value="<?= htmlspecialchars($db_settings['razorpay_currency'] ?? 'INR') ?>" placeholder="INR">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Razorpay Description</label>
                            <input type="text" name="razorpay_description" class="form-control" value="<?= htmlspecialchars($db_settings['razorpay_description'] ?? 'Premium Membership Upgrade') ?>" placeholder="Premium Membership Upgrade">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">QR Code Image</label>
                        <?php if(!empty($db_settings['payment_qr_code'])): ?>
                            <div class="mb-2">
                                <img src="../uploads/banners/<?= $db_settings['payment_qr_code'] ?>" height="150" class="rounded border mb-2 d-block">
                                <button type="submit" name="delete_image" value="payment_qr_code" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to permanently delete the QR Code? This action cannot be undone.');">
                                    <i class="fa-solid fa-trash me-1"></i> Hard Delete QR Code
                                </button>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="payment_qr_code" class="form-control" accept="image/jpeg, image/png, image/webp, image/avif">
                        <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i>Upload the scanner QR code image for payments.</small>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-warning fw-bold btn-lg w-100 mt-4 shadow-sm">Save All Settings</button>
        </form>
    </div>
<?php require_once 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnTestSmtp = document.getElementById('btnTestSmtp');
    const btnTestVerification = document.getElementById('btnTestVerification');
    
    function runTest(btn, testType) {
        const resultDiv = document.getElementById('smtpTestResult');
        const smtpHost = document.getElementById('smtp_host').value;
        const smtpPort = document.getElementById('smtp_port').value;
        const smtpUser = document.getElementById('smtp_username').value;
        const smtpPass = document.getElementById('smtp_password').value;
        const testEmail = document.getElementById('test_email').value;

        if (!smtpHost || !smtpPort || !smtpUser || !smtpPass) {
            resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0">Please fill all SMTP fields first.</div>';
            resultDiv.style.display = 'block';
            return;
        }

        if (!testEmail) {
            resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0">Please enter a test email address.</div>';
            resultDiv.style.display = 'block';
            return;
        }

        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Testing...';
        btn.disabled = true;
        resultDiv.style.display = 'none';

        const formData = new FormData();
        formData.append('smtp_host', smtpHost);
        formData.append('smtp_port', smtpPort);
        formData.append('smtp_username', smtpUser);
        formData.append('smtp_password', smtpPass);
        formData.append('test_email', testEmail);
        formData.append('test_type', testType);

        fetch('ajax_test_smtp.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            resultDiv.style.display = 'block';
            
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success py-2 mb-0"><i class="fa-solid fa-check-circle me-2"></i>' + data.message + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fa-solid fa-circle-exclamation me-2"></i>' + data.message + '</div>';
            }
        })
        .catch(error => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i>An error occurred during the test.</div>';
            console.error('Error:', error);
        });
    }

    if (btnTestSmtp) {
        btnTestSmtp.addEventListener('click', function() {
            runTest(this, 'smtp');
        });
    }
    
    if (btnTestVerification) {
        btnTestVerification.addEventListener('click', function() {
            runTest(this, 'verification');
        });
    }

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
});
</script>
