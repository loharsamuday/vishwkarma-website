<?php
$page_title = "Matrimony Registration - 4 Step Profile Setup";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';

// Check if already created
$stmt = $pdo->prepare("SELECT id FROM matrimony_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    setFlashMessage('warning', 'You have already created a matrimony profile.');
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Step 1: Basic Info
        $profile_for = $_POST['profile_for'];
        $gender = $_POST['gender'];
        $dob = $_POST['dob'];
        $height = $_POST['height'];
        $weight = $_POST['weight'] ?? null;
        $blood_group = $_POST['blood_group'] ?? null;
        $marital_status = $_POST['marital_status'];
        $mother_tongue = $_POST['mother_tongue'] ?? null;
        $religion_id = $_POST['religion_id'] ?: null;
        $caste_id = $_POST['caste_id'] ?: null;
        $manglik = $_POST['manglik'] ?? 'Don\'t Know';
        
        $diet = $_POST['diet'] ?? null;
        $smoking = $_POST['smoking'] ?? null;
        $drinking = $_POST['drinking'] ?? null;
        $disability = $_POST['disability'] ?? null;
        
        $rashi = $_POST['rashi'] ?? null;
        $nakshatra = $_POST['nakshatra'] ?? null;
        $birth_time = $_POST['birth_time'] ?: null;
        $birth_place = $_POST['birth_place'] ?? null;

        // Step 2: Edu, Career, Address
        $highest_qualification = $_POST['highest_qualification'] ?? null;
        $college_university = $_POST['college_university'] ?? null;
        $additional_qualification = $_POST['additional_qualification'] ?? null;
        $profession = $_POST['profession'] ?? null;
        $company_name = $_POST['company_name'] ?? null;
        $designation = $_POST['designation'] ?? null;
        $work_type = $_POST['work_type'] ?? null;
        $annual_income = $_POST['annual_income'] ?? null;
        $work_location = $_POST['work_location'] ?? null;

        $country = $_POST['country'] ?? 'India';
        $state = $_POST['state'] ?? null;
        $district = $_POST['district'] ?? null;
        $pin_code = $_POST['pin_code'] ?? null;
        $full_address = $_POST['full_address'] ?? null;

        // Step 3: Family & Preferences
        $father_name = $_POST['father_name'] ?? null;
        $father_occupation = $_POST['father_occupation'] ?? null;
        $mother_name = $_POST['mother_name'] ?? null;
        $mother_occupation = $_POST['mother_occupation'] ?? null;
        $brothers = (int)($_POST['brothers'] ?? 0);
        $married_brothers = (int)($_POST['married_brothers'] ?? 0);
        $sisters = (int)($_POST['sisters'] ?? 0);
        $married_sisters = (int)($_POST['married_sisters'] ?? 0);
        $family_type = $_POST['family_type'] ?? null;
        $family_status = $_POST['family_status'] ?? null;
        $family_values = $_POST['family_values'] ?? null;

        // Preferences (partner_preferences)
        $pref_age_min = $_POST['pref_age_min'] ?? null;
        $pref_age_max = $_POST['pref_age_max'] ?? null;
        $pref_height_min = $_POST['pref_height_min'] ?? null;
        $pref_height_max = $_POST['pref_height_max'] ?? null;
        $pref_marital = $_POST['pref_marital'] ?? null;
        $pref_education = $_POST['pref_education'] ?? null;
        $pref_profession = $_POST['pref_profession'] ?? null;
        $pref_income = $_POST['pref_income'] ?? null;
        $pref_religion = $_POST['pref_religion'] ?? null;
        $pref_caste = $_POST['pref_caste'] ?? null;
        $pref_state = $_POST['pref_state'] ?? null;
        $pref_manglik = $_POST['pref_manglik'] ?? null;
        $other_expectations = $_POST['other_expectations'] ?? null;

        // Step 4: Contact, Privacy, About Me, Photos
        $whatsapp_number = $_POST['whatsapp_number'] ?? null;
        $preferred_contact_time = $_POST['preferred_contact_time'] ?? null;
        $about_me = $_POST['about_me'] ?? null;
        
        $privacy_show_mobile = isset($_POST['privacy_show_mobile']) ? 1 : 0;
        $privacy_show_email = isset($_POST['privacy_show_email']) ? 1 : 0;
        $privacy_hide_contact = isset($_POST['privacy_hide_contact']) ? 1 : 0;
        $privacy_only_verified_views = isset($_POST['privacy_only_verified_views']) ? 1 : 0;

        // Profile Photo Upload
        $profile_pic = '';
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $filename = $_FILES['profile_pic']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $new_filename = uniqid('profile_') . '.' . $ext;
                $upload_path = 'uploads/profile/' . $new_filename;
                if (!is_dir('uploads/profile')) mkdir('uploads/profile', 0755, true);
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                    $profile_pic = $new_filename;
                }
            }
        }

        // Full Photo Upload (Auto Convert to WebP / compress)
        $full_photo = null;
        if (isset($_FILES['full_photo']) && $_FILES['full_photo']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['full_photo']['tmp_name'];
            $mime = mime_content_type($tmp_name);
            $img = null;
            if ($mime == 'image/jpeg') $img = imagecreatefromjpeg($tmp_name);
            elseif ($mime == 'image/png') { $img = imagecreatefrompng($tmp_name); imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true); }
            elseif ($mime == 'image/webp') $img = imagecreatefromwebp($tmp_name);
            
            if ($img) {
                // Auto resize if width > 1200 to save space while keeping it 'full'
                $width = imagesx($img);
                $height = imagesy($img);
                if ($width > 1200) {
                    $new_width = 1200;
                    $new_height = floor($height * ($new_width / $width));
                    $tmp_img = imagecreatetruecolor($new_width, $new_height);
                    if ($mime == 'image/png') {
                        imagealphablending($tmp_img, false);
                        imagesavealpha($tmp_img, true);
                        $transparent = imagecolorallocatealpha($tmp_img, 255, 255, 255, 127);
                        imagefilledrectangle($tmp_img, 0, 0, $new_width, $new_height, $transparent);
                    }
                    imagecopyresampled($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                    imagedestroy($img);
                    $img = $tmp_img;
                }

                $new_filename = uniqid('full_') . '.webp';
                $upload_path = 'uploads/profile/' . $new_filename;
                if (!is_dir('uploads/profile')) mkdir('uploads/profile', 0755, true);
                if (imagewebp($img, $upload_path, 80)) {
                    $full_photo = $new_filename;
                }
                imagedestroy($img);
            }
        }

        // 1. Insert into matrimony_profiles
        $sql_profile = "INSERT INTO matrimony_profiles (
            user_id, profile_for, gender, dob, height, weight, blood_group, marital_status, mother_tongue, 
            religion_id, caste_id, manglik, diet, smoking, drinking, disability, rashi, nakshatra, birth_time, birth_place,
            education, highest_qualification, college_university, additional_qualification, profession, company_name, designation, work_type, annual_income, work_location,
            country, state, district, pin_code, full_address,
            father_name, father_occupation, mother_name, mother_occupation, brothers, married_brothers, sisters, married_sisters, family_type, family_status, family_values,
            whatsapp_number, preferred_contact_time, about_me, privacy_show_mobile, privacy_show_email, privacy_hide_contact, privacy_only_verified_views, full_photo, verification_status
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, 'Pending'
        )";

        $stmt = $pdo->prepare($sql_profile);
        $stmt->execute([
            $user_id, $profile_for, $gender, $dob, $height, $weight, $blood_group, $marital_status, $mother_tongue,
            $religion_id, $caste_id, $manglik, $diet, $smoking, $drinking, $disability, $rashi, $nakshatra, $birth_time, $birth_place,
            $highest_qualification, $highest_qualification, $college_university, $additional_qualification, $profession, $company_name, $designation, $work_type, $annual_income, $work_location,
            $country, $state, $district, $pin_code, $full_address,
            $father_name, $father_occupation, $mother_name, $mother_occupation, $brothers, $married_brothers, $sisters, $married_sisters, $family_type, $family_status, $family_values,
            $whatsapp_number, $preferred_contact_time, $about_me, $privacy_show_mobile, $privacy_show_email, $privacy_hide_contact, $privacy_only_verified_views, $full_photo
        ]);
        
        $matrimony_profile_id = $pdo->lastInsertId();

        // 2. Insert into partner_preferences
        $sql_pref = "INSERT INTO partner_preferences (
            matrimony_profile_id, age_min, age_max, height_min, height_max, marital_status, religion_id, caste_id, education, profession, preferred_income, state_id, preferred_manglik, other_expectations
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        // state_id is technically an int in db, but we capture string usually, let's keep it null if we capture string in frontend or just save 0.
        // If state_id must be int, we pass null.
        $stmt_pref = $pdo->prepare($sql_pref);
        $stmt_pref->execute([
            $matrimony_profile_id, $pref_age_min, $pref_age_max, $pref_height_min, $pref_height_max, $pref_marital, $pref_religion, $pref_caste, $pref_education, $pref_profession, $pref_income, null, $pref_manglik, $other_expectations
        ]);

        // 3. Insert or Update Member Profile (for picture)
        if ($profile_pic) {
            $stmt = $pdo->prepare("INSERT INTO member_profiles (user_id, profile_pic) VALUES (?, ?) ON DUPLICATE KEY UPDATE profile_pic = ?");
            $stmt->execute([$user_id, $profile_pic, $profile_pic]);
        }

        $pdo->commit();
        setFlashMessage('success', 'Matrimony Profile created successfully and sent for admin approval!');
        header("Location: dashboard.php");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving profile: " . $e->getMessage();
    }
}

// Fetch Master Data
$religions = $pdo->query("SELECT * FROM religions")->fetchAll();
$castes = $pdo->query("SELECT * FROM castes")->fetchAll();

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .step-progress { display: flex; justify-content: space-between; margin-bottom: 30px; position: relative; }
    .step-progress::before { content: ''; position: absolute; top: 50%; left: 0; width: 100%; height: 4px; background: #e9ecef; z-index: 1; transform: translateY(-50%); }
    .step-progress-bar { position: absolute; top: 50%; left: 0; height: 4px; background: #ffc107; z-index: 1; transform: translateY(-50%); transition: width 0.3s; width: 0%; }
    .step-item { position: relative; z-index: 2; background: white; border: 4px solid #e9ecef; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #6c757d; transition: all 0.3s; }
    .step-item.active { border-color: #ffc107; background: #ffc107; color: #000; }
    .step-item.completed { border-color: #28a745; background: #28a745; color: white; }
    .form-step { display: none; animation: fadeIn 0.5s; }
    .form-step.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .section-title { font-size: 1.1rem; font-weight: bold; border-bottom: 2px solid #ffc107; padding-bottom: 5px; margin-bottom: 20px; margin-top: 30px; color: #333; }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card card-custom p-4 border-top border-4 border-warning shadow-lg">
                <h3 class="fw-bold text-center text-dark mb-2">Create Professional Matrimony Profile</h3>
                <p class="text-center text-muted mb-4">Complete your profile in 4 simple steps to find your perfect match.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="step-progress">
                    <div class="step-progress-bar" id="progressBar"></div>
                    <div class="step-item active" id="indicator-1">1</div>
                    <div class="step-item" id="indicator-2">2</div>
                    <div class="step-item" id="indicator-3">3</div>
                    <div class="step-item" id="indicator-4">4</div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="multiStepForm">
                    
                    <!-- STEP 1: Basic Info -->
                    <div class="form-step active" id="step-1">
                        <h4 class="text-warning mb-3"><i class="fa-solid fa-user me-2"></i>Step 1: Basic & Lifestyle Information</h4>
                        
                        <div class="section-title">Personal Details</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label>Profile Created For *</label>
                                <select name="profile_for" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="Self">Self</option>
                                    <option value="Son">Son</option>
                                    <option value="Daughter">Daughter</option>
                                    <option value="Brother">Brother</option>
                                    <option value="Sister">Sister</option>
                                    <option value="Relative">Relative</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Gender *</label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Date of Birth *</label>
                                <input type="date" name="dob" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label>Marital Status *</label>
                                <select name="marital_status" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="Never Married">Never Married</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Height (in feet) *</label>
                                <input type="number" step="0.01" name="height" class="form-control" placeholder="e.g. 5.6" required>
                            </div>
                            <div class="col-md-4">
                                <label>Weight (kg)</label>
                                <input type="number" name="weight" class="form-control" placeholder="e.g. 65">
                            </div>
                            <div class="col-md-4">
                                <label>Blood Group</label>
                                <select name="blood_group" class="form-select">
                                    <option value="">Select</option>
                                    <option value="A+">A+</option><option value="A-">A-</option>
                                    <option value="B+">B+</option><option value="B-">B-</option>
                                    <option value="O+">O+</option><option value="O-">O-</option>
                                    <option value="AB+">AB+</option><option value="AB-">AB-</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Mother Tongue</label>
                                <input type="text" name="mother_tongue" class="form-control" placeholder="e.g. Hindi, Bhojpuri">
                            </div>
                        </div>

                        <div class="section-title">Religious & Astrological Details</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label>Religion *</label>
                                <select name="religion_id" class="form-select" required>
                                    <option value="">Select Religion</option>
                                    <?php foreach($religions as $r): ?>
                                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Caste *</label>
                                <select name="caste_id" class="form-select" required>
                                    <option value="">Select Caste</option>
                                    <?php foreach($castes as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Manglik Status</label>
                                <select name="manglik" class="form-select">
                                    <option value="Don't Know">Don't Know</option>
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Rashi</label>
                                <input type="text" name="rashi" class="form-control" placeholder="e.g. Leo">
                            </div>
                            <div class="col-md-3">
                                <label>Nakshatra</label>
                                <input type="text" name="nakshatra" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label>Birth Time</label>
                                <input type="time" name="birth_time" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label>Birth Place</label>
                                <input type="text" name="birth_place" class="form-control" placeholder="City, State">
                            </div>
                        </div>

                        <div class="section-title">Lifestyle</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label>Diet</label>
                                <select name="diet" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Vegetarian">Vegetarian</option>
                                    <option value="Non-Vegetarian">Non-Vegetarian</option>
                                    <option value="Eggetarian">Eggetarian</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Smoking</label>
                                <select name="smoking" class="form-select">
                                    <option value="">Select</option>
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                    <option value="Occasionally">Occasionally</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Drinking</label>
                                <select name="drinking" class="form-select">
                                    <option value="">Select</option>
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                    <option value="Occasionally">Occasionally</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Disability</label>
                                <select name="disability" class="form-select">
                                    <option value="None">None</option>
                                    <option value="Physical">Physical Disability</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-warning px-4 fw-bold" onclick="nextStep(2)">Next <i class="fa-solid fa-arrow-right ms-2"></i></button>
                        </div>
                    </div>

                    <!-- STEP 2: Education, Career & Address -->
                    <div class="form-step" id="step-2">
                        <h4 class="text-warning mb-3"><i class="fa-solid fa-graduation-cap me-2"></i>Step 2: Education & Career</h4>
                        
                        <div class="section-title">Education Details</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label>Highest Qualification *</label>
                                <input type="text" name="highest_qualification" class="form-control" required placeholder="e.g. B.Tech, MBA, PhD">
                            </div>
                            <div class="col-md-6">
                                <label>College/University</label>
                                <input type="text" name="college_university" class="form-control" placeholder="e.g. Delhi University">
                            </div>
                            <div class="col-md-12">
                                <label>Additional Qualification</label>
                                <input type="text" name="additional_qualification" class="form-control" placeholder="Any diplomas or certifications">
                            </div>
                        </div>

                        <div class="section-title">Career Details</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label>Occupation/Profession *</label>
                                <input type="text" name="profession" class="form-control" required placeholder="e.g. Software Engineer">
                            </div>
                            <div class="col-md-4">
                                <label>Work Type</label>
                                <select name="work_type" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Private Sector">Private Sector</option>
                                    <option value="Government Sector">Government Sector</option>
                                    <option value="Business/Self Employed">Business/Self Employed</option>
                                    <option value="Not Working">Not Working</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Annual Income *</label>
                                <select name="annual_income" class="form-select" required>
                                    <option value="">Select</option>
                                    <option value="Under 3 Lakhs">Under 3 Lakhs</option>
                                    <option value="3 - 6 Lakhs">3 - 6 Lakhs</option>
                                    <option value="6 - 10 Lakhs">6 - 10 Lakhs</option>
                                    <option value="10 - 20 Lakhs">10 - 20 Lakhs</option>
                                    <option value="20 Lakhs+">20 Lakhs+</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Company Name</label>
                                <input type="text" name="company_name" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label>Designation</label>
                                <input type="text" name="designation" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label>Work Location</label>
                                <input type="text" name="work_location" class="form-control" placeholder="City, State, Country">
                            </div>
                        </div>

                        <div class="section-title">Current Address</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label>Country *</label>
                                <input type="text" name="country" class="form-control" value="India" required>
                            </div>
                            <div class="col-md-4">
                                <label>State *</label>
                                <input type="text" name="state" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label>District/City *</label>
                                <input type="text" name="district" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label>PIN Code *</label>
                                <input type="text" name="pin_code" class="form-control" required>
                            </div>
                            <div class="col-md-8">
                                <label>Full Address</label>
                                <input type="text" name="full_address" class="form-control">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary px-4" onclick="prevStep(1)"><i class="fa-solid fa-arrow-left me-2"></i> Back</button>
                            <button type="button" class="btn btn-warning px-4 fw-bold" onclick="nextStep(3)">Next <i class="fa-solid fa-arrow-right ms-2"></i></button>
                        </div>
                    </div>

                    <!-- STEP 3: Family & Preferences -->
                    <div class="form-step" id="step-3">
                        <h4 class="text-warning mb-3"><i class="fa-solid fa-users me-2"></i>Step 3: Family & Preferences</h4>
                        
                        <div class="section-title">Family Details</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label>Father's Name</label>
                                <input type="text" name="father_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label>Father's Occupation</label>
                                <input type="text" name="father_occupation" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label>Mother's Name</label>
                                <input type="text" name="mother_name" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label>Mother's Occupation</label>
                                <input type="text" name="mother_occupation" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label>Total Brothers</label>
                                <input type="number" name="brothers" class="form-control" value="0">
                            </div>
                            <div class="col-md-3">
                                <label>Married Brothers</label>
                                <input type="number" name="married_brothers" class="form-control" value="0">
                            </div>
                            <div class="col-md-3">
                                <label>Total Sisters</label>
                                <input type="number" name="sisters" class="form-control" value="0">
                            </div>
                            <div class="col-md-3">
                                <label>Married Sisters</label>
                                <input type="number" name="married_sisters" class="form-control" value="0">
                            </div>
                            <div class="col-md-4">
                                <label>Family Type</label>
                                <select name="family_type" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Nuclear">Nuclear</option>
                                    <option value="Joint">Joint</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Family Status</label>
                                <select name="family_status" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Middle Class">Middle Class</option>
                                    <option value="Upper Middle Class">Upper Middle Class</option>
                                    <option value="Rich/Affluent">Rich/Affluent</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Family Values</label>
                                <select name="family_values" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Orthodox">Orthodox</option>
                                    <option value="Traditional">Traditional</option>
                                    <option value="Moderate">Moderate</option>
                                    <option value="Liberal">Liberal</option>
                                </select>
                            </div>
                        </div>

                        <div class="section-title">Partner Preferences</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label>Age Min</label>
                                <input type="number" name="pref_age_min" class="form-control" placeholder="e.g. 21">
                            </div>
                            <div class="col-md-3">
                                <label>Age Max</label>
                                <input type="number" name="pref_age_max" class="form-control" placeholder="e.g. 28">
                            </div>
                            <div class="col-md-3">
                                <label>Height Min</label>
                                <input type="number" step="0.01" name="pref_height_min" class="form-control" placeholder="e.g. 5.0">
                            </div>
                            <div class="col-md-3">
                                <label>Height Max</label>
                                <input type="number" step="0.01" name="pref_height_max" class="form-control" placeholder="e.g. 6.0">
                            </div>
                            <div class="col-md-4">
                                <label>Preferred Marital Status</label>
                                <select name="pref_marital" class="form-select">
                                    <option value="">Doesn't Matter</option>
                                    <option value="Never Married">Never Married Only</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label>Preferred Education</label>
                                <input type="text" name="pref_education" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label>Preferred Income</label>
                                <input type="text" name="pref_income" class="form-control">
                            </div>
                            <div class="col-md-12">
                                <label>Other Expectations</label>
                                <textarea name="other_expectations" class="form-control" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary px-4" onclick="prevStep(2)"><i class="fa-solid fa-arrow-left me-2"></i> Back</button>
                            <button type="button" class="btn btn-warning px-4 fw-bold" onclick="nextStep(4)">Next <i class="fa-solid fa-arrow-right ms-2"></i></button>
                        </div>
                    </div>

                    <!-- STEP 4: Photos & Privacy -->
                    <div class="form-step" id="step-4">
                        <h4 class="text-warning mb-3"><i class="fa-solid fa-camera me-2"></i>Step 4: Profile Setup & Privacy</h4>
                        
                        <div class="section-title">About Me</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-12">
                                <label>Describe yourself and your family background *</label>
                                <textarea name="about_me" class="form-control" rows="4" required placeholder="I am a software engineer working in Bangalore. I believe in family values..."></textarea>
                            </div>
                        </div>

                        <div class="section-title">Contact & Photos</div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label>WhatsApp Number</label>
                                <input type="text" name="whatsapp_number" class="form-control" pattern="\d{10}">
                            </div>
                            <div class="col-md-6">
                                <label>Preferred Contact Time</label>
                                <input type="text" name="preferred_contact_time" class="form-control" placeholder="e.g. 6 PM to 9 PM">
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="fw-bold">Profile Photo *</label>
                                <input type="file" name="profile_pic" class="form-control" accept="image/jpeg, image/png, image/webp" required>
                                <small class="text-muted">A good quality photo significantly increases your chances of finding a match.</small>
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="fw-bold">Full Photo (Optional, Recommended)</label>
                                <input type="file" name="full_photo" class="form-control" accept="image/*">
                                <small class="text-info"><i class="fa-solid fa-camera"></i> Take a live photo or upload from your gallery. Size is automatically adjusted.</small>
                            </div>
                        </div>

                        <div class="section-title">Privacy Options</div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <div class="form-check form-switch mb-2">
                                  <input class="form-check-input" type="checkbox" name="privacy_show_mobile" id="privacy_show_mobile" checked>
                                  <label class="form-check-label" for="privacy_show_mobile">Show Mobile Number to Premium Members</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                  <input class="form-check-input" type="checkbox" name="privacy_show_email" id="privacy_show_email" checked>
                                  <label class="form-check-label" for="privacy_show_email">Show Email to Premium Members</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                  <input class="form-check-input" type="checkbox" name="privacy_only_verified_views" id="privacy_only_verified_views">
                                  <label class="form-check-label" for="privacy_only_verified_views">Only Verified Accounts can view my full profile</label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning mb-4 small">
                            <i class="fa-solid fa-shield-halved me-2"></i> All profiles undergo manual Admin Verification. Your profile will be visible publicly only after approval.
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary px-4" onclick="prevStep(3)"><i class="fa-solid fa-arrow-left me-2"></i> Back</button>
                            <button type="submit" class="btn btn-success px-5 fw-bold btn-lg">Submit Profile <i class="fa-solid fa-check ms-2"></i></button>
                        </div>
                    </div>
                    
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function validateStep(step) {
        let isValid = true;
        const currentStepDiv = document.getElementById('step-' + step);
        const inputs = currentStepDiv.querySelectorAll('input[required], select[required], textarea[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if(!isValid) {
            alert('Please fill all required fields in this step before proceeding.');
        }
        return isValid;
    }

    function nextStep(step) {
        if (!validateStep(step - 1)) return;

        // Hide all steps
        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        // Show current step
        document.getElementById('step-' + step).classList.add('active');
        
        // Update Progress Bar
        const progress = ((step - 1) / 3) * 100;
        document.getElementById('progressBar').style.width = progress + '%';
        
        // Update Indicators
        for(let i=1; i<=4; i++) {
            const indicator = document.getElementById('indicator-' + i);
            if(i < step) {
                indicator.classList.add('completed');
                indicator.classList.remove('active');
            } else if (i === step) {
                indicator.classList.add('active');
                indicator.classList.remove('completed');
            } else {
                indicator.classList.remove('active', 'completed');
            }
        }
        window.scrollTo(0, 0);
    }

    function prevStep(step) {
        // Hide all steps
        document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
        // Show current step
        document.getElementById('step-' + step).classList.add('active');
        
        // Update Progress Bar
        const progress = ((step - 1) / 3) * 100;
        document.getElementById('progressBar').style.width = progress + '%';
        
        // Update Indicators
        for(let i=1; i<=4; i++) {
            const indicator = document.getElementById('indicator-' + i);
            if(i < step) {
                indicator.classList.add('completed');
                indicator.classList.remove('active');
            } else if (i === step) {
                indicator.classList.add('active');
                indicator.classList.remove('completed');
            } else {
                indicator.classList.remove('active', 'completed');
            }
        }
        window.scrollTo(0, 0);
    }

    // Remove is-invalid on input change
    document.querySelectorAll('input, select, textarea').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
