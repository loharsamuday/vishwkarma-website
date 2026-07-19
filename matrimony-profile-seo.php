<?php
// matrimony-profile-seo.php - Individual SEO Profile Page

require_once 'config/config.php';
require_once 'includes/seo_helper.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    // 404
    header("HTTP/1.0 404 Not Found");
    exit("Profile Not Found");
}

// In real app, fetch profile by slug from `ent_matrimony_profiles`
// e.g. SELECT * FROM ent_matrimony_profiles p JOIN ent_career_details c ... WHERE p.slug = ?

// Mockup Data
$profile = [
    'first_name' => 'Rahul',
    'last_name' => 'Vishwakarma',
    'gender' => 'Male',
    'age' => 28,
    'city_name' => 'Patna',
    'state_name' => 'Bihar',
    'occupation_name' => 'Software Engineer',
    'income' => '10 Lakhs+',
    'education' => 'B.Tech',
    'height' => '5\'8"',
    'marital_status' => 'Never Married',
    'diet' => 'Vegetarian',
    'manglik' => 'No',
    'about' => 'I am a software engineer working in a reputed MNC. I belong to a middle-class nuclear family.',
    'score' => 98
];

// Generate SEO Meta
$meta = get_profile_meta($profile);
$json_ld = generate_json_ld_profile($profile, BASE_URL . ltrim($_GET['url'] ?? '', '/'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($meta['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta['description']) ?>">
    <link rel="canonical" href="<?= BASE_URL . ltrim($_GET['url'] ?? '', '/') ?>" />
    
    <!-- Schema.org JSON-LD -->
    <?= $json_ld ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-header { background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); padding: 40px 0; border-bottom: 1px solid #ddd; }
        .profile-img { width: 200px; height: 200px; object-fit: cover; border-radius: 50%; border: 5px solid white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .section-title { border-bottom: 2px solid #dc3545; display: inline-block; padding-bottom: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>">Vishwakarma Samaj</a>
        </div>
    </nav>
    
    <div class="profile-header">
        <div class="container text-center">
            <img src="<?= BASE_URL ?>assets/images/default-avatar.png" alt="<?= $profile['first_name'] ?> Vishwakarma Groom Patna" class="profile-img mb-3">
            <h1 class="h2"><?= htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']) ?></h1>
            <p class="lead text-muted">
                <?= $profile['age'] ?> Yrs, <?= $profile['height'] ?> | <?= $profile['city_name'] ?>, <?= $profile['state_name'] ?> | <?= $profile['occupation_name'] ?>
            </p>
            <div class="d-flex justify-content-center gap-3">
                <span class="badge bg-success fs-6"><i class="bi bi-check-circle"></i> Verified Profile</span>
                <span class="badge bg-info text-dark fs-6">Profile Complete: <?= $profile['score'] ?>%</span>
            </div>
        </div>
    </div>
    
    <div class="container my-5">
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h4 class="section-title">About Me</h4>
                        <p><?= htmlspecialchars($profile['about']) ?></p>
                    </div>
                </div>
                
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h4 class="section-title">Basic Details</h4>
                        <div class="row">
                            <div class="col-sm-6 mb-2"><strong>Marital Status:</strong> <?= $profile['marital_status'] ?></div>
                            <div class="col-sm-6 mb-2"><strong>Height:</strong> <?= $profile['height'] ?></div>
                            <div class="col-sm-6 mb-2"><strong>Diet:</strong> <?= $profile['diet'] ?></div>
                            <div class="col-sm-6 mb-2"><strong>Manglik:</strong> <?= $profile['manglik'] ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <h4 class="section-title">Education & Career</h4>
                        <div class="row">
                            <div class="col-sm-6 mb-2"><strong>Education:</strong> <?= $profile['education'] ?></div>
                            <div class="col-sm-6 mb-2"><strong>Occupation:</strong> <?= $profile['occupation_name'] ?></div>
                            <div class="col-sm-6 mb-2"><strong>Income:</strong> <?= $profile['income'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- AI Match Score -->
                <div class="card border-primary mb-4 text-center">
                    <div class="card-body">
                        <h5 class="text-primary">Compatibility Score</h5>
                        <h2 class="display-4 fw-bold text-success">96%</h2>
                        <p class="text-muted small">Based on your partner preferences</p>
                        <button class="btn btn-primary w-100">Send Interest</button>
                    </div>
                </div>
                
                <!-- Internal Linking Widget -->
                <div class="card mb-4">
                    <div class="card-header bg-light fw-bold">Similar Profiles</div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><a href="#" class="text-decoration-none">Brides in <?= $profile['city_name'] ?></a></li>
                        <li class="list-group-item"><a href="#" class="text-decoration-none"><?= $profile['occupation_name'] ?> Brides</a></li>
                        <li class="list-group-item"><a href="#" class="text-decoration-none">B.Tech Vishwakarma Brides</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
</body>
</html>
