<?php
$page_title = "Home";
require_once 'includes/db.php';
require_once 'includes/session.php';

// Fetch stats for the counter
$stats = [
    'members' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() + 1000,
    'brides' => $pdo->query("SELECT COUNT(*) FROM matrimony_profiles WHERE gender='Female'")->fetchColumn() + 500,
    'grooms' => $pdo->query("SELECT COUNT(*) FROM matrimony_profiles WHERE gender='Male'")->fetchColumn() + 500,
    'businesses' => $pdo->query("SELECT COUNT(*) FROM business_directory")->fetchColumn() + 200,
];

// Fetch latest gallery images
try {
    $latest_gallery = $pdo->query("
        SELECT ug.*, u.first_name, u.last_name 
        FROM user_gallery ug 
        JOIN users u ON ug.user_id = u.id 
        WHERE ug.status = 'approved' 
        ORDER BY ug.created_at DESC 
        LIMIT 4
    ")->fetchAll();
} catch (PDOException $e) {
    $latest_gallery = [];
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
/* Modern Utilities for Homepage */
.hero-section {
    min-height: 80vh;
    background-size: cover;
    background-position: center;
    position: relative;
}
.hero-section::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(135deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 100%);
    z-index: 1;
}
.hero-content {
    position: relative;
    z-index: 2;
}
.hero-btn {
    border-radius: 30px;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.hero-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(255, 193, 7, 0.4);
}

/* Services Grid */
.service-card {
    border: none;
    border-radius: 20px;
    background: #fff;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
.service-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
}
.service-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 193, 7, 0.1);
    border-radius: 50%;
    transition: all 0.3s ease;
}
.service-card:hover .service-icon {
    background: #ffc107;
    color: #fff !important;
    transform: scale(1.1);
}

/* Stats Section */
.stats-section {
    background: linear-gradient(135deg, #1a1a1a 0%, #333 100%);
    color: #fff;
}
.glass-stat-box {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    transition: transform 0.3s ease;
}
.glass-stat-box:hover {
    transform: translateY(-5px);
    background: rgba(255, 255, 255, 0.15);
}

/* Gallery Hover */
.gallery-card {
    border-radius: 15px;
    overflow: hidden;
    position: relative;
}
.gallery-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: all 0.3s ease;
}
.gallery-card:hover .gallery-overlay {
    opacity: 1;
}
.gallery-card img {
    transition: transform 0.5s ease;
}
.gallery-card:hover img {
    transform: scale(1.1);
}

/* Testimonials */
.testimonial-card {
    border-radius: 20px;
    background: #fff;
    position: relative;
    padding: 40px;
}
.testimonial-card::before {
    content: '\201C';
    font-size: 100px;
    color: rgba(255, 193, 7, 0.2);
    position: absolute;
    top: 10px;
    left: 20px;
    font-family: serif;
    line-height: 1;
}
</style>

<!-- Hero Section -->
<?php 
$hero_images = [];
if (function_exists('getUiImage')) {
    $img1 = getUiImage('home_hero', 'https://placehold.co/1920x800/1a1a1a/ffc107?text=Welcome+to+Vishwakarma+Samaj');
    $img2 = getUiImage('home_hero_2', '');
    $img3 = getUiImage('home_hero_3', '');
    
    $hero_images[] = $img1;
    if (!empty($img2)) $hero_images[] = $img2;
    if (!empty($img3)) $hero_images[] = $img3;
} else {
    $hero_images[] = "https://placehold.co/1920x800/1a1a1a/ffc107?text=Welcome+to+Vishwakarma+Samaj";
}
?>
<section class="hero-section d-flex align-items-center justify-content-center text-center overflow-hidden position-relative">
    
    <!-- Background Carousel -->
    <div id="heroCarousel" class="carousel slide carousel-fade position-absolute w-100 h-100" data-bs-ride="carousel" data-bs-pause="false" style="top: 0; left: 0; z-index: 0;">
        <div class="carousel-inner w-100 h-100">
            <?php foreach ($hero_images as $index => $img): ?>
                <div class="carousel-item w-100 h-100 <?= $index === 0 ? 'active' : '' ?>" style="background-image: url('<?= htmlspecialchars($img) ?>'); background-size: cover; background-position: center;">
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Overlay Content -->
    <div class="container hero-content" data-aos="zoom-in">
        <h1 class="display-3 fw-bold mb-3 text-white drop-shadow">Welcome to <span class="text-warning">Vishwakarma Samaj</span></h1>
        <p class="lead mb-5 text-light fs-4 drop-shadow mx-auto" style="max-width: 800px;">Connecting our community globally. Empowering lives through unity, education, and mutual support.</p>
        
        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 hero-actions">
            <a href="register.php" class="btn btn-warning btn-lg px-5 py-3 fw-bold hero-btn shadow-lg">
                <i class="fa-solid fa-users me-2"></i> Join Community
            </a>
            <a href="matrimony.php" class="btn btn-outline-light btn-lg px-5 py-3 fw-bold hero-btn">
                <i class="fa-solid fa-heart me-2 text-warning"></i> Find a Match
            </a>
        </div>
    </div>
</section>

<!-- Quick Services -->
<section class="py-5" style="background-color: #f8f9fa;">
    <div class="container my-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h6 class="text-warning fw-bold text-uppercase tracking-wide">What We Offer</h6>
            <h2 class="fw-bold display-6">Our <span class="text-warning">Services</span></h2>
            <div class="mx-auto mt-3 rounded" style="width: 60px; height: 4px; background: #ffc107;"></div>
        </div>
        
        <!-- Changed to a 3-column grid (col-xl-4 col-md-6 col-12) so 9 items fit perfectly -->
        <div class="row g-4">
            <!-- Service 1 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="100">
                <a href="matrimony.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4">
                            <i class="fa-solid fa-heart fa-2x text-warning"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Matrimony</h4>
                        <p class="text-muted mb-0">Find your perfect life partner within the trusted community network.</p>
                    </div>
                </a>
            </div>
            <!-- Service 2 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="200">
                <a href="business-directory.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4">
                            <i class="fa-solid fa-briefcase fa-2x text-warning"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Business Directory</h4>
                        <p class="text-muted mb-0">Discover and support local businesses owned by our community members.</p>
                    </div>
                </a>
            </div>
            <!-- Service 3 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="300">
                <a href="jobs.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4">
                            <i class="fa-solid fa-user-tie fa-2x text-warning"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Jobs Portal</h4>
                        <p class="text-muted mb-0">Explore career opportunities or hire skilled professionals easily.</p>
                    </div>
                </a>
            </div>
            <!-- Service 4 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="100">
                <a href="education.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4">
                            <i class="fa-solid fa-graduation-cap fa-2x text-warning"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Education Support</h4>
                        <p class="text-muted mb-0">Access scholarships, mentorship, and career guidance programs.</p>
                    </div>
                </a>
            </div>
            <!-- Service 5 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="200">
                <a href="blood-bank.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4" style="background: rgba(220, 53, 69, 0.1);">
                            <i class="fa-solid fa-droplet fa-2x text-danger"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Blood Bank</h4>
                        <p class="text-muted mb-0">A life-saving network to donate or request blood during emergencies.</p>
                    </div>
                </a>
            </div>
            <!-- Service 6 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="300">
                <a href="community-directory.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4">
                            <i class="fa-solid fa-users fa-2x text-warning"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Member Directory</h4>
                        <p class="text-muted mb-0">Connect with families, relatives, and prominent members globally.</p>
                    </div>
                </a>
            </div>
            <!-- Service 7 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="100">
                <a href="events.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4">
                            <i class="fa-regular fa-calendar-days fa-2x text-warning"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Community Events</h4>
                        <p class="text-muted mb-0">Stay updated on cultural gatherings, meetings, and celebrations.</p>
                    </div>
                </a>
            </div>
            <!-- Service 8 -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="200">
                <a href="gallery.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100">
                        <div class="service-icon mb-4">
                            <i class="fa-solid fa-images fa-2x text-warning"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">Media Gallery</h4>
                        <p class="text-muted mb-0">Relive the memories through our curated photos and videos collection.</p>
                    </div>
                </a>
            </div>
            <!-- Service 9: Web Services -->
            <div class="col-xl-4 col-md-6 col-12" data-aos="fade-up" data-aos-delay="300">
                <a href="web-services.php" class="text-decoration-none">
                    <div class="card service-card text-center p-4 h-100 position-relative border border-2 border-warning">
                        <span class="position-absolute top-0 start-50 translate-middle badge rounded-pill bg-danger shadow-sm px-3 py-2">NEW</span>
                        <div class="service-icon mb-4">
                            <i class="fa-solid fa-code fa-2x text-dark"></i>
                        </div>
                        <h4 class="text-dark fw-bold mb-3">IT & Web Services</h4>
                        <p class="text-muted mb-0">Get premium websites, apps, and software built for your business.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Statistics Section (Glassmorphism) -->
<section class="py-5 stats-section text-center position-relative" data-aos="fade-up">
    <div class="container my-5">
        <h6 class="text-warning fw-bold text-uppercase tracking-wide mb-2">Our Impact</h6>
        <h2 class="mb-5 fw-bold text-white drop-shadow-sm display-6">Samaj at a Glance</h2>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="glass-stat-box p-4 h-100 shadow-lg">
                    <h2 class="display-4 fw-bold text-warning mb-0 counter" data-target="<?= $stats['members'] ?>">0</h2>
                    <p class="text-light fw-semibold mb-0 mt-2 fs-5">Registered Members</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="glass-stat-box p-4 h-100 shadow-lg">
                    <h2 class="display-4 fw-bold text-warning mb-0 counter" data-target="<?= $stats['brides'] ?>">0</h2>
                    <p class="text-light fw-semibold mb-0 mt-2 fs-5">Brides</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="glass-stat-box p-4 h-100 shadow-lg">
                    <h2 class="display-4 fw-bold text-warning mb-0 counter" data-target="<?= $stats['grooms'] ?>">0</h2>
                    <p class="text-light fw-semibold mb-0 mt-2 fs-5">Grooms</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="glass-stat-box p-4 h-100 shadow-lg">
                    <h2 class="display-4 fw-bold text-warning mb-0 counter" data-target="<?= $stats['businesses'] ?>">0</h2>
                    <p class="text-light fw-semibold mb-0 mt-2 fs-5">Business Owners</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- About Section -->
<section class="py-5 bg-white">
    <div class="container my-5">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="position-relative">
                    <?php $about_img = function_exists('getUiImage') ? getUiImage('home_about', 'https://placehold.co/800x600/f8f9fa/ffc107?text=Vishwakarma+Samaj') : "https://placehold.co/800x600/f8f9fa/ffc107?text=Vishwakarma+Samaj"; ?>
                    <img src="<?= htmlspecialchars($about_img) ?>" class="img-fluid rounded-4 shadow-lg w-100" alt="Vishwakarma Samaj" style="object-fit: cover; height: 450px;">
                    <div class="position-absolute bottom-0 end-0 bg-warning text-dark p-4 rounded-4 shadow-lg me-n3 mb-n3 d-none d-md-block">
                        <h3 class="fw-bold mb-0 text-center">100+</h3>
                        <p class="mb-0 fw-bold small text-uppercase">Years of Legacy</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 px-md-4" data-aos="fade-left">
                <h6 class="text-warning fw-bold text-uppercase tracking-wide">About Us</h6>
                <h2 class="fw-bold mb-4 display-6">Preserving Tradition,<br>Embracing Progress</h2>
                <div class="rounded mb-4" style="width: 60px; height: 4px; background: #ffc107;"></div>
                <p class="text-muted mb-4 fs-5" style="line-height: 1.8;">
                    The Vishwakarma Samaj has a rich history of craftsmanship, engineering, and architectural brilliance. Our community is dedicated to uplifting its members through mutual support, educational initiatives, and creating a strong global network.
                </p>
                <p class="text-muted mb-5 fs-5" style="line-height: 1.8;">
                    Join us in building a vibrant future while staying deeply rooted in our cultural heritage. Together, we can achieve greatness and support those in need.
                </p>
                <a href="about.php" class="btn btn-dark btn-lg rounded-pill px-5 fw-bold shadow">Discover Our History <i class="fa-solid fa-arrow-right ms-2"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- Latest Gallery Section -->
<section class="py-5" style="background-color: #f8f9fa;">
    <div class="container my-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-5 gap-3" data-aos="fade-right">
            <div>
                <h6 class="text-warning fw-bold text-uppercase tracking-wide">Community Showcase</h6>
                <h2 class="fw-bold mb-0 display-6">Latest <span class="text-warning">Gallery</span></h2>
                <div class="mt-3 rounded" style="width: 60px; height: 4px; background: #ffc107;"></div>
            </div>
            <a href="gallery.php" class="btn btn-outline-dark btn-lg rounded-pill fw-bold px-4">View All Media <i class="fa-solid fa-images ms-2"></i></a>
        </div>
        
        <div class="row g-4">
            <?php if (!empty($latest_gallery)): ?>
                <?php foreach ($latest_gallery as $index => $img): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 col-12" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                        <div class="gallery-card shadow-sm h-100 bg-white">
                            <img src="uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" class="w-100" alt="Gallery Image" style="height: 250px; object-fit: cover;">
                            <div class="gallery-overlay">
                                <a href="uploads/gallery/<?= htmlspecialchars($img['image_path']) ?>" target="_blank" class="btn btn-warning rounded-circle" style="width: 50px; height: 50px; line-height: 38px;">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </a>
                            </div>
                            <div class="p-3 text-center border-top">
                                <small class="text-muted fw-bold"><i class="fa-solid fa-user text-warning me-2"></i> <?= htmlspecialchars($img['first_name'] . ' ' . $img['last_name']) ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 bg-white rounded-4 shadow-sm">
                    <i class="fa-regular fa-images fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted mb-3">No memories uploaded yet.</h5>
                    <a href="gallery.php" class="btn btn-warning fw-bold rounded-pill px-4">Be the first to upload!</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Trust Badges Section -->
<section class="py-5 bg-dark text-white border-top border-bottom border-warning border-4">
    <div class="container my-3 text-center">
        <div class="row g-5 justify-content-center">
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="100">
                <i class="fa-solid fa-shield-halved fa-3x text-warning mb-3"></i>
                <h5 class="fw-bold mb-1">100% Secure</h5>
                <small class="text-light opacity-75">256-Bit SSL Encryption</small>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="200">
                <i class="fa-solid fa-user-check fa-3x text-warning mb-3"></i>
                <h5 class="fw-bold mb-1">Verified Profiles</h5>
                <small class="text-light opacity-75">Strict Manual Verification</small>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="300">
                <i class="fa-solid fa-lock fa-3x text-warning mb-3"></i>
                <h5 class="fw-bold mb-1">Privacy First</h5>
                <small class="text-light opacity-75">Complete Data Control</small>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="400">
                <i class="fa-solid fa-headset fa-3x text-warning mb-3"></i>
                <h5 class="fw-bold mb-1">24/7 Support</h5>
                <small class="text-light opacity-75">Always Here to Help</small>
            </div>
        </div>
    </div>
</section>

<!-- Success Stories / Testimonials -->
<section class="py-5 bg-white">
    <div class="container my-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h6 class="text-warning fw-bold text-uppercase tracking-wide">Success Stories</h6>
            <h2 class="fw-bold display-6 mb-3">What Our <span class="text-warning">Members Say</span></h2>
            <div class="mx-auto rounded" style="width: 60px; height: 4px; background: #ffc107;"></div>
        </div>
        
        <div id="testimonialCarousel" class="carousel slide pb-5" data-bs-ride="carousel" data-bs-interval="5000">
            <div class="carousel-indicators mb-0">
                <button type="button" data-bs-target="#testimonialCarousel" data-bs-slide-to="0" class="active bg-warning" aria-current="true" aria-label="Slide 1"></button>
                <button type="button" data-bs-target="#testimonialCarousel" data-bs-slide-to="1" class="bg-warning" aria-label="Slide 2"></button>
                <button type="button" data-bs-target="#testimonialCarousel" data-bs-slide-to="2" class="bg-warning" aria-label="Slide 3"></button>
            </div>
            
            <div class="carousel-inner px-3 py-4">
                <!-- Slide 1 -->
                <div class="carousel-item active">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="testimonial-card shadow-lg text-center mx-auto border-top border-4 border-warning">
                                <img src="<?= function_exists('getUiImage') ? getUiImage('home_testimonial_1', 'https://ui-avatars.com/api/?name=Rajesh+S&background=random') : 'https://ui-avatars.com/api/?name=Rajesh+S&background=random' ?>" class="rounded-circle mx-auto mb-4 border border-4 border-white shadow" width="90" height="90" alt="Avatar" style="margin-top: -85px;">
                                <div class="text-warning mb-3 fs-5">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                </div>
                                <p class="text-dark fst-italic fs-4 mb-4" style="line-height: 1.6;">"I found my life partner through the Matrimony section. The profiles are 100% genuine and the verification process is top-notch. Highly recommended for our community!"</p>
                                <h5 class="fw-bold mb-0">Rajesh Sharma</h5>
                                <small class="text-muted text-uppercase tracking-wide">Mumbai, India</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 2 -->
                <div class="carousel-item">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="testimonial-card shadow-lg text-center mx-auto border-top border-4 border-warning">
                                <img src="<?= function_exists('getUiImage') ? getUiImage('home_testimonial_2', 'https://ui-avatars.com/api/?name=Amit+V&background=random') : 'https://ui-avatars.com/api/?name=Amit+V&background=random' ?>" class="rounded-circle mx-auto mb-4 border border-4 border-white shadow" width="90" height="90" alt="Avatar" style="margin-top: -85px;">
                                <div class="text-warning mb-3 fs-5">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                                </div>
                                <p class="text-dark fst-italic fs-4 mb-4" style="line-height: 1.6;">"Listed my carpentry business in the directory and started getting calls from our community members within a week. This platform is truly helping us grow together."</p>
                                <h5 class="fw-bold mb-0">Amit Vishwakarma</h5>
                                <small class="text-muted text-uppercase tracking-wide">Delhi, India</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Slide 3 -->
                <div class="carousel-item">
                    <div class="row justify-content-center">
                        <div class="col-lg-8">
                            <div class="testimonial-card shadow-lg text-center mx-auto border-top border-4 border-warning">
                                <img src="<?= function_exists('getUiImage') ? getUiImage('home_testimonial_3', 'https://ui-avatars.com/api/?name=Sneha+P&background=random') : 'https://ui-avatars.com/api/?name=Sneha+P&background=random' ?>" class="rounded-circle mx-auto mb-4 border border-4 border-white shadow" width="90" height="90" alt="Avatar" style="margin-top: -85px;">
                                <div class="text-warning mb-3 fs-5">
                                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
                                </div>
                                <p class="text-dark fst-italic fs-4 mb-4" style="line-height: 1.6;">"The blood bank feature saved a relative's life during an emergency. The fact that the entire samaj is connected here gives a great sense of security and trust."</p>
                                <h5 class="fw-bold mb-0">Sneha Panchal</h5>
                                <small class="text-muted text-uppercase tracking-wide">Pune, India</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Hidden on mobile, visible on lg screens -->
            <button class="carousel-control-prev d-none d-lg-flex" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev" style="width: 5%;">
                <span class="carousel-control-prev-icon bg-warning rounded-circle p-3 shadow" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next d-none d-lg-flex" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next" style="width: 5%;">
                <span class="carousel-control-next-icon bg-warning rounded-circle p-3 shadow" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</section>

<script>
    // Counter Animation
    document.addEventListener("DOMContentLoaded", () => {
        const counters = document.querySelectorAll('.counter');
        const speed = 200; 

        counters.forEach(counter => {
            const updateCount = () => {
                const target = +counter.getAttribute('data-target');
                const count = +counter.innerText;
                const inc = target / speed;

                if (count < target) {
                    counter.innerText = Math.ceil(count + inc);
                    setTimeout(updateCount, 15);
                } else {
                    counter.innerText = target;
                }
            };
            
            // Simple intersection observer to trigger when in view
            const observer = new IntersectionObserver((entries) => {
                if(entries[0].isIntersecting) {
                    updateCount();
                    observer.disconnect();
                }
            });
            observer.observe(counter);
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
