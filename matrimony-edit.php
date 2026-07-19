<?php
$page_title = "Edit Matrimony Profile";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';

// Check profile and premium status
$stmt = $pdo->prepare("SELECT * FROM matrimony_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    header("Location: matrimony-register.php");
    exit;
}

if (!$profile['is_premium']) {
    setFlashMessage('error', 'You must be a Premium Member to edit your profile. This prevents spam and ensures only serious profiles can be updated.');
    header("Location: upgrade.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $height = $_POST['height'];
    $weight = $_POST['weight'];
    $marital_status = $_POST['marital_status'];
    $education = $_POST['education'];
    $profession = $_POST['profession'];
    $annual_income = $_POST['annual_income'];
    $manglik = $_POST['manglik'];
    $state = trim($_POST['state']);
    $district = trim($_POST['district']);
    $block = trim($_POST['block']);
    $full_address = trim($_POST['full_address']);
    
    // Full Photo Upload (Auto Convert to WebP / compress)
    $full_photo_update = "";
    $params = [$height, $weight, $marital_status, $education, $profession, $annual_income, $manglik, $full_address, $state, $district, $block];
    
    if (isset($_FILES['full_photo']) && $_FILES['full_photo']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['full_photo']['tmp_name'];
        $mime = mime_content_type($tmp_name);
        $img = null;
        if ($mime == 'image/jpeg') $img = imagecreatefromjpeg($tmp_name);
        elseif ($mime == 'image/png') { $img = imagecreatefrompng($tmp_name); imagepalettetotruecolor($img); imagealphablending($img, true); imagesavealpha($img, true); }
        elseif ($mime == 'image/webp') $img = imagecreatefromwebp($tmp_name);
        
        if ($img) {
            $width = imagesx($img);
            $height_img = imagesy($img);
            if ($width > 1200) {
                $new_width = 1200;
                $new_height = floor($height_img * ($new_width / $width));
                $tmp_img = imagecreatetruecolor($new_width, $new_height);
                if ($mime == 'image/png') {
                    imagealphablending($tmp_img, false);
                    imagesavealpha($tmp_img, true);
                    $transparent = imagecolorallocatealpha($tmp_img, 255, 255, 255, 127);
                    imagefilledrectangle($tmp_img, 0, 0, $new_width, $new_height, $transparent);
                }
                imagecopyresampled($tmp_img, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height_img);
                imagedestroy($img);
                $img = $tmp_img;
            }

            $new_filename = uniqid('full_') . '.webp';
            $upload_path = 'uploads/profile/' . $new_filename;
            if (!is_dir('uploads/profile')) mkdir('uploads/profile', 0755, true);
            if (imagewebp($img, $upload_path, 80)) {
                $full_photo_update = ", full_photo = ?";
                $params[] = $new_filename;
            }
            imagedestroy($img);
        }
    }
    
    $params[] = $user_id;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE matrimony_profiles 
            SET height = ?, weight = ?, marital_status = ?, education = ?, profession = ?, annual_income = ?, manglik = ?, full_address = ?, state = ?, district = ?, block = ? {$full_photo_update}
            WHERE user_id = ?
        ");
        $stmt->execute($params);
        
        // Refresh the profile array with updated data just in case it's used further down
        $profile['height'] = $height;
        $profile['weight'] = $weight;
        $profile['marital_status'] = $marital_status;
        $profile['education'] = $education;
        $profile['profession'] = $profession;
        $profile['annual_income'] = $annual_income;
        $profile['manglik'] = $manglik;

        logActivity('Updated Matrimony Profile', 'user', $user_id);

        setFlashMessage('success', 'Your Matrimony Profile has been successfully updated!');
        header("Location: dashboard.php");
        exit;
    } catch (PDOException $e) {
        $error = "System error while updating profile. Please try again.";
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-custom p-4 shadow-sm border-top border-4 border-warning">
                <h3 class="fw-bold text-center mb-4"><i class="fa-solid fa-user-edit text-warning me-2"></i> Edit Matrimony Profile</h3>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="alert alert-info py-2 small">
                        <i class="fa-solid fa-circle-info me-1"></i> <strong>Note:</strong> Please fill in correct information. Authentic details help in finding the best match for you.
                    </div>
                    <h5 class="border-bottom pb-2 mb-3 mt-3 text-warning">Basic Details (Non-editable)</h5>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="text-muted small">Profile For</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($profile['profile_for']) ?>" readonly disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Gender</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($profile['gender']) ?>" readonly disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Date of Birth</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($profile['dob']) ?>" readonly disabled>
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 text-warning">Physical Attributes</h5>
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Height (e.g. 5.11) *</label>
                            <input type="number" step="0.01" name="height" class="form-control" value="<?= htmlspecialchars($profile['height']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" class="form-control" value="<?= htmlspecialchars($profile['weight']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marital Status *</label>
                            <select name="marital_status" class="form-select" required>
                                <?php
                                $statuses = ['Never Married', 'Divorced', 'Widowed', 'Awaiting Divorce'];
                                foreach ($statuses as $status) {
                                    $selected = ($profile['marital_status'] == $status) ? 'selected' : '';
                                    echo "<option value=\"$status\" $selected>$status</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manglik Status</label>
                            <select name="manglik" class="form-select">
                                <?php
                                $m_statuses = ['No', 'Yes', 'Don\'t Know'];
                                foreach ($m_statuses as $status) {
                                    $selected = ($profile['manglik'] == $status) ? 'selected' : '';
                                    echo "<option value=\"$status\" $selected>$status</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 text-warning">Photos</h5>
                    <div class="row mb-4">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Full Photo (Optional, Recommended)</label>
                            <?php if (!empty($profile['full_photo'])): ?>
                                <div class="mb-2">
                                    <img src="<?= htmlspecialchars('uploads/profile/'.$profile['full_photo']) ?>" alt="Full Photo" class="img-thumbnail" style="max-height: 150px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="full_photo" class="form-control" accept="image/*">
                            <small class="text-info"><i class="fa-solid fa-camera"></i> Take a live photo or upload from your gallery. Size is automatically adjusted.</small>
                        </div>
                    </div>

                    <h5 class="border-bottom pb-2 mb-3 text-warning">Education & Career</h5>
                    <div class="row mb-4">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Highest Education *</label>
                            <input type="text" name="education" class="form-control" value="<?= htmlspecialchars($profile['education']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Profession *</label>
                            <input type="text" name="profession" class="form-control" value="<?= htmlspecialchars($profile['profession']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Annual Income</label>
                            <input type="text" name="annual_income" class="form-control" value="<?= htmlspecialchars($profile['annual_income']) ?>" placeholder="e.g. 5-10 Lakhs">
                        </div>
                    </div>
                    
                    <h5 class="border-bottom pb-2 mb-3 text-warning">Location & Address</h5>
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State *</label>
                            <select name="state" id="stateSelect" class="form-select" required>
                                <option value="">Select State</option>
                                <?php
                                $indian_states = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Delhi'];
                                foreach ($indian_states as $s) {
                                    $sel = ($profile['state'] == $s) ? 'selected' : '';
                                    echo "<option value=\"$s\" $sel>$s</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">District *</label>
                            <select name="district" id="districtSelect" class="form-select" required>
                                <option value="">Select State First</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Block / Tehsil / City *</label>
                            <input type="text" name="block" class="form-control" value="<?= htmlspecialchars($profile['block'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Full Address (Local Area) *</label>
                            <textarea name="full_address" class="form-control" rows="2" required><?= htmlspecialchars($profile['full_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 fw-bold btn-lg">Update Profile</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const stateDistricts = {
        "Uttar Pradesh": ["Agra", "Aligarh", "Allahabad", "Ambedkar Nagar", "Amethi", "Amroha", "Auraiya", "Azamgarh", "Baghpat", "Bahraich", "Ballia", "Balrampur", "Banda", "Barabanki", "Bareilly", "Basti", "Bhadohi", "Bijnor", "Budaun", "Bulandshahr", "Chandauli", "Chitrakoot", "Deoria", "Etah", "Etawah", "Faizabad", "Farrukhabad", "Fatehpur", "Firozabad", "Gautam Buddha Nagar", "Ghaziabad", "Ghazipur", "Gonda", "Gorakhpur", "Hamirpur", "Hapur", "Hardoi", "Hathras", "Jalaun", "Jaunpur", "Jhansi", "Kannauj", "Kanpur Dehat", "Kanpur Nagar", "Kasganj", "Kaushambi", "Kheri", "Kushinagar", "Lalitpur", "Lucknow", "Maharajganj", "Mahoba", "Mainpuri", "Mathura", "Mau", "Meerut", "Mirzapur", "Moradabad", "Muzaffarnagar", "Pilibhit", "Pratapgarh", "Raebareli", "Rampur", "Saharanpur", "Sambhal", "Sant Kabir Nagar", "Shahjahanpur", "Shamli", "Shravasti", "Siddharthnagar", "Sitapur", "Sonbhadra", "Sultanpur", "Unnao", "Varanasi"],
        "Bihar": ["Araria", "Arwal", "Aurangabad", "Banka", "Begusarai", "Bhagalpur", "Bhojpur", "Buxar", "Darbhanga", "East Champaran", "Gaya", "Gopalganj", "Jamui", "Jehanabad", "Kaimur", "Katihar", "Khagaria", "Kishanganj", "Lakhisarai", "Madhepura", "Madhubani", "Munger", "Muzaffarpur", "Nalanda", "Nawada", "Patna", "Purnia", "Rohtas", "Saharsa", "Samastipur", "Saran", "Sheikhpura", "Sheohar", "Sitamarhi", "Siwan", "Supaul", "Vaishali", "West Champaran"],
        "Jharkhand": ["Bokaro", "Chatra", "Deoghar", "Dhanbad", "Dumka", "East Singhbhum", "Garhwa", "Giridih", "Godda", "Gumla", "Hazaribagh", "Jamtara", "Khunti", "Koderma", "Latehar", "Lohardaga", "Pakur", "Palamu", "Ramgarh", "Ranchi", "Sahibganj", "Seraikela Kharsawan", "Simdega", "West Singhbhum"],
        "Madhya Pradesh": ["Agar Malwa", "Alirajpur", "Anuppur", "Ashoknagar", "Balaghat", "Barwani", "Betul", "Bhind", "Bhopal", "Burhanpur", "Chhatarpur", "Chhindwara", "Damoh", "Datia", "Dewas", "Dhar", "Dindori", "Guna", "Gwalior", "Harda", "Hoshangabad", "Indore", "Jabalpur", "Jhabua", "Katni", "Khandwa", "Khargone", "Mandla", "Mandsaur", "Morena", "Narsinghpur", "Neemuch", "Panna", "Raisen", "Rajgarh", "Ratlam", "Rewa", "Sagar", "Satna", "Sehore", "Seoni", "Shahdol", "Shajapur", "Sheopur", "Shivpuri", "Sidhi", "Singrauli", "Tikamgarh", "Ujjain", "Umaria", "Vidisha"],
        "Rajasthan": ["Ajmer", "Alwar", "Banswara", "Baran", "Barmer", "Bharatpur", "Bhilwara", "Bikaner", "Bundi", "Chittorgarh", "Churu", "Dausa", "Dholpur", "Dungarpur", "Hanumangarh", "Jaipur", "Jaisalmer", "Jalore", "Jhalawar", "Jhunjhunu", "Jodhpur", "Karauli", "Kota", "Nagaur", "Pali", "Pratapgarh", "Rajsamand", "Sawai Madhopur", "Sikar", "Sirohi", "Sri Ganganagar", "Tonk", "Udaipur"],
        "Delhi": ["Central Delhi", "East Delhi", "New Delhi", "North Delhi", "North East Delhi", "North West Delhi", "Shahdara", "South Delhi", "South East Delhi", "South West Delhi", "West Delhi"],
        "Maharashtra": ["Ahmednagar", "Akola", "Amravati", "Aurangabad", "Beed", "Bhandara", "Buldhana", "Chandrapur", "Dhule", "Gadchiroli", "Gondia", "Hingoli", "Jalgaon", "Jalna", "Kolhapur", "Latur", "Mumbai City", "Mumbai Suburban", "Nagpur", "Nanded", "Nandurbar", "Nashik", "Osmanabad", "Palghar", "Parbhani", "Pune", "Raigad", "Ratnagiri", "Sangli", "Satara", "Sindhudurg", "Solapur", "Thane", "Wardha", "Washim", "Yavatmal"],
        "Gujarat": ["Ahmedabad", "Amreli", "Anand", "Aravalli", "Banaskantha", "Bharuch", "Bhavnagar", "Botad", "Chhota Udaipur", "Dahod", "Dang", "Devbhoomi Dwarka", "Gandhinagar", "Gir Somnath", "Jamnagar", "Junagadh", "Kheda", "Kutch", "Mahisagar", "Mehsana", "Morbi", "Narmada", "Navsari", "Panchmahal", "Patan", "Porbandar", "Rajkot", "Sabarkantha", "Surat", "Surendranagar", "Tapi", "Vadodara", "Valsad"],
        "Haryana": ["Ambala", "Bhiwani", "Charkhi Dadri", "Faridabad", "Fatehabad", "Gurugram", "Hisar", "Jhajjar", "Jind", "Kaithal", "Karnal", "Kurukshetra", "Mahendragarh", "Nuh", "Palwal", "Panchkula", "Panipat", "Rewari", "Rohtak", "Sirsa", "Sonipat", "Yamunanagar"]
    };

    const stateSelect = document.getElementById("stateSelect");
    const districtSelect = document.getElementById("districtSelect");
    
    // Fallback original value if editing
    const currentDistrict = "<?= isset($profile['district']) ? htmlspecialchars($profile['district']) : '' ?>";

    function populateDistricts(selectedState, preselectDistrict = '') {
        districtSelect.innerHTML = '<option value="">Select District</option>';
        if (stateDistricts[selectedState]) {
            stateDistricts[selectedState].forEach(function(district) {
                const option = document.createElement("option");
                option.value = district;
                option.textContent = district;
                if(district === preselectDistrict) option.selected = true;
                districtSelect.appendChild(option);
            });
        } else if(selectedState) {
            // Fallback for unlisted states
            districtSelect.innerHTML = '<option value="Other">Other (Please specify in address)</option>';
        } else {
            districtSelect.innerHTML = '<option value="">Select State First</option>';
        }
    }

    stateSelect.addEventListener("change", function() {
        populateDistricts(this.value);
    });

    // Initial load
    if (stateSelect.value) {
        populateDistricts(stateSelect.value, currentDistrict);
    }
});
</script>
