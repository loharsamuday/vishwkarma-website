<?php
$page_title = "About Us";
require_once 'includes/db.php';
require_once 'includes/session.php';
$page_data = getCmsContent('about');
if ($page_data && !empty($page_data['title'])) {
    $page_title = $page_data['title'] . " - " . SITE_NAME;
}
require_once 'includes/header.php';
require_once 'includes/navbar.php';
$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
?>

<style>
/* About Page Premium Styling */
.about-hero {
    min-height: 50vh;
    background-size: cover;
    background-position: center;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}
.about-hero::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.6) 100%);
    z-index: 1;
}
.about-hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
}

/* CMS Content Typography */
.cms-content-wrapper {
    font-size: 1.1rem;
    line-height: 1.8;
    color: #4b5563;
}
.cms-content-wrapper p {
    margin-bottom: 1.5rem;
}
.cms-content-wrapper h1, .cms-content-wrapper h2, .cms-content-wrapper h3 {
    color: #1f2937;
    font-weight: 700;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

/* Image with Pattern */
.story-image-wrapper {
    position: relative;
    padding: 20px;
}
.story-image-wrapper::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 60%; height: 60%;
    background-image: radial-gradient(#ffc107 2px, transparent 2px);
    background-size: 20px 20px;
    z-index: 0;
    opacity: 0.5;
    border-radius: 10px;
}
.story-image-wrapper img {
    position: relative;
    z-index: 1;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    width: 100%;
    object-fit: cover;
    height: 500px;
}

/* Vision & Mission Cards */
.v-m-card {
    background: #fff;
    border-radius: 20px;
    padding: 40px;
    height: 100%;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05);
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    position: relative;
    overflow: hidden;
}
.v-m-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}
.v-m-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 193, 7, 0.15);
    color: #e6a800;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
    font-size: 2rem;
    margin-bottom: 25px;
    transition: all 0.3s ease;
}
.v-m-card:hover .v-m-icon {
    background: #ffc107;
    color: #fff;
    transform: scale(1.1) rotate(5deg);
}

/* Values Grid */
.value-card {
    text-align: center;
    padding: 30px 20px;
    background: #f8f9fa;
    border-radius: 20px;
    transition: all 0.3s ease;
    height: 100%;
}
.value-card:hover {
    background: #fff;
    box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    transform: translateY(-5px);
}
.value-img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 50%;
    margin: 0 auto 20px;
    border: 4px solid #fff;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
</style>

<!-- Premium Hero Section -->
<?php 
$hero_bg = function_exists('getUiImage') ? getUiImage('about_hero', 'https://placehold.co/1920x600/1a1a1a/ffc107?text=Vishwakarma+Samaj') : 'https://placehold.co/1920x600/1a1a1a/ffc107?text=Vishwakarma+Samaj'; 
?>
<section class="about-hero" style="background-image: url('<?= htmlspecialchars($hero_bg) ?>');">
    <div class="container about-hero-content" data-aos="zoom-in">
        <h6 class="text-warning fw-bold text-uppercase tracking-wide mb-3">Learn More About Us</h6>
        <h1 class="display-3 fw-bold text-white drop-shadow mb-0">Our <span class="text-warning">Heritage</span></h1>
    </div>
</section>

<!-- The "Our Story" Section (2-Column) -->
<section class="py-5 bg-white">
    <div class="container my-5">
        <div class="row g-5 align-items-center">
            <!-- Left Side: Premium Image -->
            <div class="col-lg-5" data-aos="fade-right">
                <div class="story-image-wrapper">
                    <?php $img_community = function_exists('getUiImage') ? getUiImage('about_community', 'https://placehold.co/800x800/f8f9fa/ffc107?text=Our+Community') : 'https://placehold.co/800x800/f8f9fa/ffc107?text=Our+Community'; ?>
                    <img src="<?= htmlspecialchars($img_community) ?>" alt="Our Community" class="img-fluid">
                    <div class="position-absolute bottom-0 start-0 bg-dark text-white p-4 rounded-4 shadow-lg ms-n2 mb-3 d-none d-md-block border-start border-4 border-warning">
                        <h4 class="fw-bold text-warning mb-0">Unity & Progress</h4>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: CMS Content -->
            <div class="col-lg-7 px-lg-5" data-aos="fade-left">
                <h6 class="text-warning fw-bold text-uppercase tracking-wide mb-2">Our Story</h6>
                <h2 class="fw-bold mb-4 display-6">Building a Stronger <br>Global Community</h2>
                <div class="rounded mb-4" style="width: 60px; height: 4px; background: #ffc107;"></div>
                
                <div class="cms-content-wrapper">
                    <p>Welcome to the Vishwakarma Samaj platform. We are dedicated to connecting our people globally, preserving our rich heritage, and empowering our members through various initiatives like education support, business directories, and matrimony services.</p>
                    <p>Our ancestors were great architects and craftsmen. Today, we carry forward that legacy of creation and excellence into the modern world. Together, there is nothing we cannot achieve.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Vision & Mission Section -->
<section class="py-5" style="background-color: #f8f9fa;">
    <div class="container my-5">
        <div class="row g-4">
            <!-- Vision -->
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="v-m-card p-0">
                    <?php $img_vision = function_exists('getUiImage') ? getUiImage('about_vision', 'https://placehold.co/800x400/fff/ffc107?text=Our+Vision') : 'https://placehold.co/800x400/fff/ffc107?text=Our+Vision'; ?>
                    <img src="<?= htmlspecialchars($img_vision) ?>" alt="Our Vision" class="w-100" style="height: 250px; object-fit: cover;">
                    <div class="p-4 p-md-5 text-center text-md-start">
                        <h3 class="fw-bold text-dark mb-3"><i class="fa-solid fa-eye text-warning me-2"></i> Our Vision</h3>
                        <p class="text-muted fs-5 mb-0" style="line-height: 1.7;">To create a globally connected, prosperous, and self-reliant Vishwakarma community where every individual is empowered to achieve their highest potential while staying deeply rooted in our cultural heritage.</p>
                    </div>
                </div>
            </div>
            <!-- Mission -->
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
                <div class="v-m-card p-0">
                    <?php $img_mission = function_exists('getUiImage') ? getUiImage('about_mission', 'https://placehold.co/800x400/fff/ffc107?text=Our+Mission') : 'https://placehold.co/800x400/fff/ffc107?text=Our+Mission'; ?>
                    <img src="<?= htmlspecialchars($img_mission) ?>" alt="Our Mission" class="w-100" style="height: 250px; object-fit: cover;">
                    <div class="p-4 p-md-5 text-center text-md-start">
                        <h3 class="fw-bold text-dark mb-3"><i class="fa-solid fa-bullseye text-warning me-2"></i> Our Mission</h3>
                        <p class="text-muted fs-5 mb-0" style="line-height: 1.7;">To provide a secure, digital ecosystem that supports our members through dedicated matrimonial services, business networking, educational scholarships, and emergency blood donation networks.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Core Values Section -->
<section class="py-5 bg-white border-top border-bottom border-warning border-4">
    <div class="container my-5 text-center">
        <h6 class="text-warning fw-bold text-uppercase tracking-wide mb-2">Our Pillars</h6>
        <h2 class="fw-bold mb-5 display-6">Core Values</h2>
        
        <div class="row g-4">
            <!-- Pillar 1 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="value-card">
                    <?php $img_unity = function_exists('getUiImage') ? getUiImage('about_core_unity', 'https://placehold.co/200x200/ffc107/fff?text=Unity') : 'https://placehold.co/200x200/ffc107/fff?text=Unity'; ?>
                    <img src="<?= htmlspecialchars($img_unity) ?>" alt="Unity" class="value-img">
                    <h4 class="fw-bold mb-2">Unity</h4>
                    <p class="text-muted small mb-0">Fostering strong bonds and togetherness across families and borders.</p>
                </div>
            </div>
            <!-- Pillar 2 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="value-card">
                    <?php $img_edu = function_exists('getUiImage') ? getUiImage('about_core_education', 'https://placehold.co/200x200/ffc107/fff?text=Education') : 'https://placehold.co/200x200/ffc107/fff?text=Education'; ?>
                    <img src="<?= htmlspecialchars($img_edu) ?>" alt="Education" class="value-img">
                    <h4 class="fw-bold mb-2">Education</h4>
                    <p class="text-muted small mb-0">Empowering the next generation with knowledge, guidance, and support.</p>
                </div>
            </div>
            <!-- Pillar 3 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="value-card">
                    <?php $img_her = function_exists('getUiImage') ? getUiImage('about_core_heritage', 'https://placehold.co/200x200/ffc107/fff?text=Heritage') : 'https://placehold.co/200x200/ffc107/fff?text=Heritage'; ?>
                    <img src="<?= htmlspecialchars($img_her) ?>" alt="Heritage" class="value-img">
                    <h4 class="fw-bold mb-2">Heritage</h4>
                    <p class="text-muted small mb-0">Preserving and honoring our rich history of craftsmanship and engineering.</p>
                </div>
            </div>
            <!-- Pillar 4 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="400">
                <div class="value-card">
                    <?php $img_sup = function_exists('getUiImage') ? getUiImage('about_core_support', 'https://placehold.co/200x200/ffc107/fff?text=Support') : 'https://placehold.co/200x200/ffc107/fff?text=Support'; ?>
                    <img src="<?= htmlspecialchars($img_sup) ?>" alt="Support" class="value-img">
                    <h4 class="fw-bold mb-2">Support</h4>
                    <p class="text-muted small mb-0">Standing by each other through business networks and emergencies.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CMS Full Page Content -->
<?php if(!empty($page_data['content'])): ?>
    <section class="cms-dynamic-section bg-white border-top">
        <?= $page_data['content'] ?>
    </section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
