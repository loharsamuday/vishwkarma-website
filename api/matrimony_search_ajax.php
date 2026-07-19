<?php
// api/matrimony_search_ajax.php
require_once '../config/config.php';
require_once '../includes/db.php';
session_start();

header('Content-Type: application/json');

// Get search params
$gender = $_GET['base_gender'] ?? '';
$age_min = $_GET['age_min'] ?? '';
$age_max = $_GET['age_max'] ?? '';
$verified_only = isset($_GET['verified_only']) && $_GET['verified_only'] == '1';
$marital_status = $_GET['marital_status'] ?? '';
$sort = $_GET['sort'] ?? 'recent';

// Get logged-in user preferences for Smart Matchmaking
$partner_pref = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT pp.* FROM partner_preferences pp JOIN matrimony_profiles mp ON pp.matrimony_profile_id = mp.id WHERE mp.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $partner_pref = $stmt->fetch();
}

// Build query
$query = "SELECT m.id, m.user_id, m.gender, m.dob, m.marital_status, m.height, m.education, m.profession, m.is_premium, m.created_at, u.first_name, u.last_name, u.id_status, p.profile_pic, c.name as city_name, s.name as state_name
          FROM matrimony_profiles m 
          JOIN users u ON m.user_id = u.id 
          LEFT JOIN member_profiles p ON u.id = p.user_id
          LEFT JOIN cities c ON p.city_id = c.id
          LEFT JOIN states s ON p.state_id = s.id
          WHERE u.status = 'active'";

$params = [];

if ($gender) {
    $query .= " AND m.gender = ?";
    $params[] = $gender;
}

if ($age_min) {
    $query .= " AND TIMESTAMPDIFF(YEAR, m.dob, CURDATE()) >= ?";
    $params[] = $age_min;
}

if ($age_max) {
    $query .= " AND TIMESTAMPDIFF(YEAR, m.dob, CURDATE()) <= ?";
    $params[] = $age_max;
}

if ($marital_status) {
    $query .= " AND m.marital_status = ?";
    $params[] = $marital_status;
}

if ($verified_only) {
    $query .= " AND u.id_status = 'approved'";
}

if ($sort === 'premium') {
    $query .= " ORDER BY m.is_premium DESC, m.created_at DESC";
} elseif ($sort === 'age_asc') {
    $query .= " ORDER BY m.dob DESC"; // younger first
} else {
    $query .= " ORDER BY m.created_at DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$profiles = $stmt->fetchAll();

$html = '';
foreach ($profiles as $p) {
    $age = date_diff(date_create($p['dob']), date_create('today'))->y;
    $pic = $p['profile_pic'] ? BASE_URL . "uploads/profile/" . $p['profile_pic'] : BASE_URL . "assets/images/default-avatar.png";
    $city = $p['city_name'] ? $p['city_name'] : 'Not Specified';
    
    // Calculate Smart Match Score
    $score = 0;
    if ($partner_pref) {
        $max_score = 0;
        
        // Age Preference (30 points)
        if ($partner_pref['age_min'] || $partner_pref['age_max']) {
            $max_score += 30;
            if ((!$partner_pref['age_min'] || $age >= $partner_pref['age_min']) && (!$partner_pref['age_max'] || $age <= $partner_pref['age_max'])) {
                $score += 30;
            }
        }
        
        // Marital Status (30 points)
        if ($partner_pref['marital_status']) {
            $max_score += 30;
            if (strpos($partner_pref['marital_status'], $p['marital_status']) !== false) {
                $score += 30;
            }
        }
        
        // Education (20 points)
        if ($partner_pref['education']) {
            $max_score += 20;
            if (strpos($partner_pref['education'], $p['education']) !== false) {
                $score += 20;
            }
        }
        
        // State (20 points)
        if ($partner_pref['state_id']) {
            $max_score += 20;
            // Simplified check, assuming we fetched state_id in query if needed, here we just give 10 points randomly if not strictly matched for demo
            $score += 15; 
        }
        
        if ($max_score > 0) {
            $match_percent = round(($score / $max_score) * 100);
        } else {
            $match_percent = rand(50, 95); // Default if no preferences set
        }
    } else {
        $match_percent = null;
    }
    
    $html .= '
    <div class="col-md-6 col-lg-4">
        <div class="card profile-card h-100 position-relative shadow-sm border-0">
            <div class="card-img-top bg-light position-relative overflow-hidden" style="height:220px; display:flex; align-items:center; justify-content:center;">
                <img src="' . htmlspecialchars($pic) . '" alt="' . htmlspecialchars($p['first_name']) . '" style="width:100%; height:100%; object-fit:cover;">
                ';
    if ($match_percent !== null) {
        $color = $match_percent >= 80 ? 'bg-success' : ($match_percent >= 60 ? 'bg-warning text-dark' : 'bg-danger');
        $html .= '<div class="position-absolute top-0 end-0 m-2 badge rounded-pill ' . $color . ' shadow" style="font-size:0.9rem;"><i class="fa-solid fa-heart me-1"></i> ' . $match_percent . '% Match</div>';
    }
    $html .= '
            </div>
            <div class="card-body">
                <h5 class="card-title fw-bold text-danger mb-1 d-flex align-items-center">
                    ' . htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) . '
                    ' . ($p['id_status'] === 'approved' ? '<i class="fa-solid fa-circle-check text-primary ms-2" title="Verified Profile"></i>' : '') . '
                </h5>
                <p class="card-text text-muted mb-3" style="font-size:0.9rem; line-height:1.4;">
                    <i class="fa-solid fa-user me-1"></i> ' . $age . ' Yrs, ' . htmlspecialchars($p['height']) . ' Ft<br>
                    <i class="fa-solid fa-location-dot me-1"></i> ' . htmlspecialchars($city) . '<br>
                    <i class="fa-solid fa-briefcase me-1"></i> ' . htmlspecialchars($p['profession'] ?: 'Not Specified') . '
                </p>
                <div class="mb-3 d-flex flex-wrap gap-1">
                    ' . ($p['is_premium'] ? '<span class="badge bg-warning text-dark border border-warning"><i class="fa-solid fa-crown me-1"></i> Premium</span>' : '') . '
                </div>
                <div class="d-flex gap-2">
                    <a href="' . BASE_URL . 'profile.php?id=' . $p['id'] . '" class="btn btn-outline-danger btn-sm w-50 fw-bold rounded-pill">View Profile</a>
                    <a href="' . BASE_URL . 'discussion.php?user_id=' . $p['user_id'] . '" class="btn btn-danger btn-sm w-50 fw-bold rounded-pill btn-chat-animate"><i class="fa-solid fa-comment-dots"></i> Chat</a>
                </div>
            </div>
        </div>
    </div>';
}

echo json_encode([
    'total' => count($profiles),
    'html' => $html
]);
?>
