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

/* Institutional about-page polish */
.about-hero { min-height: 620px; text-align: left; isolation: isolate; }
.about-hero::before { background: linear-gradient(100deg, rgba(10,27,42,.94) 0%, rgba(15,37,54,.76) 48%, rgba(9,25,39,.28) 100%); z-index: -1; }
.about-hero-content { text-align: left; max-width: 760px; margin: 0; }
.about-kicker { display: inline-flex; align-items: center; gap: .55rem; padding: .5rem .9rem; border: 1px solid rgba(255,255,255,.18); border-radius: 99px; background: rgba(255,255,255,.1); color: #ffda83; font-size: .74rem; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
.about-hero h1 { letter-spacing: -.055em; line-height: 1.05; }
.about-lead { color: rgba(255,255,255,.82); max-width: 630px; line-height: 1.75; }
.about-hero-points { display: flex; flex-wrap: wrap; gap: 1.1rem; color: rgba(255,255,255,.86); font-size: .83rem; font-weight: 600; margin-top: 1.6rem; }
.about-hero-points i { color: #ffc857; }
.about-section { padding: 6rem 0; }
.about-eyebrow { color: #bf6c07; font-size: .72rem; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
.about-heading { color: #1c3042; letter-spacing: -.04em; }
.story-image-wrapper img { height: 470px; }
.story-note { position: absolute; bottom: 1.6rem; left: -1rem; z-index: 2; max-width: 250px; padding: 1rem 1.15rem; border-radius: 12px; background: #fff; box-shadow: 0 16px 35px rgba(8,29,44,.2); }
.story-note strong { color: #172e43; }
.focus-card { height: 100%; padding: 1.6rem; border: 1px solid #e5ebf0; border-radius: 15px; background: #fff; box-shadow: 0 10px 25px rgba(16,39,59,.045); transition: transform .25s ease, box-shadow .25s ease; }
.focus-card:hover { transform: translateY(-6px); box-shadow: 0 18px 34px rgba(16,39,59,.1); }
.focus-icon { width: 52px; height: 52px; display: grid; place-items: center; border-radius: 14px; background: #fff2d7; color: #bd7007; font-size: 1.25rem; margin-bottom: 1.1rem; }
.v-m-card { border-radius: 16px; border-color: #e3eaf0; }
.v-m-card:hover { transform: translateY(-6px); }
.value-card { border: 1px solid #e7edf2; border-radius: 16px; }
.value-card:hover { border-color: rgba(231,155,23,.4); }
.journey-panel { background: radial-gradient(circle at 85% 15%, rgba(255,205,103,.3), transparent 25%), linear-gradient(125deg, #102d43, #0b2033); color: #fff; border-radius: 20px; overflow: hidden; }
.journey-panel p { color: rgba(255,255,255,.76); }
@media (max-width: 767.98px) { .about-hero { min-height: 570px; text-align: center; } .about-hero-content { text-align: center; margin: auto; } .about-hero h1 { font-size: 2.55rem; } .about-hero-points { justify-content: center; gap: .7rem; } .about-section { padding: 4rem 0; } .story-image-wrapper { padding: 10px; } .story-image-wrapper img { height: 320px; } .story-note { position: static; margin-top: -1.8rem; margin-left: auto; margin-right: auto; width: calc(100% - 1rem); } .v-m-card { border-radius: 14px; } }
</style>

<!-- Premium Hero Section -->
<?php 
$hero_bg = function_exists('getUiImage') ? getUiImage('about_hero', 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=1920&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=1920&auto=format&fit=crop';
?>
<section class="about-hero" style="background-image: url('<?= htmlspecialchars($hero_bg) ?>');">
    <div class="container about-hero-content" data-aos="fade-up">
        <div class="about-kicker mb-4"><i class="fa-solid fa-gem"></i> Rooted in craft. Connected by purpose.</div>
        <h1 class="display-3 fw-bold text-white drop-shadow mb-3">Honouring our <span class="text-warning">heritage.</span><br>Building what comes next.</h1>
        <p class="about-lead lead mb-0">Vishwakarma Samaj brings people, opportunity and shared purpose together—so our collective legacy can continue to grow in a modern world.</p>
        <div class="about-hero-points"><span><i class="fa-solid fa-circle-check me-2"></i>Community connection</span><span><i class="fa-solid fa-circle-check me-2"></i>Shared opportunity</span><span><i class="fa-solid fa-circle-check me-2"></i>Respect for tradition</span></div>
    </div>
</section>

<!-- The "Our Story" Section (2-Column) -->
<section class="about-section bg-white">
    <div class="container my-5">
        <div class="row g-5 align-items-center">
            <!-- Left Side: Premium Image -->
            <div class="col-lg-5" data-aos="fade-right">
                <div class="story-image-wrapper">
                    <?php $img_community = function_exists('getUiImage') ? getUiImage('about_community', 'https://images.unsplash.com/photo-1529156069898-49953eb1b5b4?q=80&w=800&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1529156069898-49953eb1b5b4?q=80&w=800&auto=format&fit=crop'; ?>
                    <img src="<?= htmlspecialchars($img_community) ?>" alt="Our Community" class="img-fluid">
                    <div class="story-note"><div class="about-eyebrow mb-1">Our shared purpose</div><strong>Unity that makes progress possible.</strong></div>
                </div>
            </div>
            
            <!-- Right Side: CMS Content -->
            <div class="col-lg-7 px-lg-5" data-aos="fade-left">
                <div class="about-eyebrow mb-2">Our story</div>
                <h2 class="fw-bold about-heading mb-4 display-6">A legacy of skill.<br><span class="text-warning">A future of possibility.</span></h2>
                <div class="rounded mb-4" style="width: 60px; height: 4px; background: #ffc107;"></div>
                
                <div class="cms-content-wrapper">
                    <p>Welcome to the Vishwakarma Samaj platform. We are dedicated to connecting our people globally, preserving our rich heritage, and empowering our members through various initiatives like education support, business directories, and matrimony services.</p>
                    <p>Our ancestors were great architects and craftsmen. Today, we carry forward that legacy of creation and excellence into the modern world. Together, there is nothing we cannot achieve.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Community Focus Areas -->
<section class="about-section" style="background: #f6f8fa;">
    <div class="container">
        <div class="row align-items-end mb-5">
            <div class="col-lg-7" data-aos="fade-right">
                <div class="about-eyebrow mb-2">What we are here to strengthen</div>
                <h2 class="fw-bold about-heading display-6 mb-2">Practical support for every <span class="text-warning">stage of life.</span></h2>
                <p class="text-muted mb-0">Our platform is designed around the everyday ways a connected community can make a difference.</p>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3" data-aos="fade-up"><div class="focus-card"><div class="focus-icon"><i class="fa-solid fa-people-group"></i></div><h5 class="fw-bold">Meaningful connections</h5><p class="text-muted small mb-0">Help families and members discover one another through a shared community network.</p></div></div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100"><div class="focus-card"><div class="focus-icon"><i class="fa-solid fa-graduation-cap"></i></div><h5 class="fw-bold">Education & guidance</h5><p class="text-muted small mb-0">Make space for learning, scholarships, mentorship and future-ready opportunities.</p></div></div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200"><div class="focus-card"><div class="focus-icon"><i class="fa-solid fa-store"></i></div><h5 class="fw-bold">Business growth</h5><p class="text-muted small mb-0">Celebrate enterprise and help community-owned businesses become easier to discover.</p></div></div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300"><div class="focus-card"><div class="focus-icon"><i class="fa-solid fa-hand-holding-heart"></i></div><h5 class="fw-bold">Care in moments that matter</h5><p class="text-muted small mb-0">Bring people together for support, information and help during important moments.</p></div></div>
        </div>
    </div>
</section>

<!-- Vision & Mission Section -->
<section class="about-section" style="background-color: #f8f9fa;">
    <div class="container my-5">
        <div class="row g-4">
            <!-- Vision -->
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                <div class="v-m-card p-0">
                    <?php $img_vision = function_exists('getUiImage') ? getUiImage('about_vision', 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?q=80&w=800&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?q=80&w=800&auto=format&fit=crop'; ?>
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
                    <?php $img_mission = function_exists('getUiImage') ? getUiImage('about_mission', 'https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=800&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=800&auto=format&fit=crop'; ?>
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
<section class="about-section bg-white border-top border-bottom border-warning border-4">
    <div class="container my-5 text-center">
        <div class="about-eyebrow mb-2">Our pillars</div>
        <h2 class="fw-bold about-heading mb-5 display-6">The values that guide <span class="text-warning">every connection.</span></h2>
        
        <div class="row g-4">
            <!-- Pillar 1 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="100">
                <div class="value-card">
                    <?php $img_unity = function_exists('getUiImage') ? getUiImage('about_core_unity', 'https://images.unsplash.com/photo-1582213782179-e0d53f98f2ca?q=80&w=200&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1582213782179-e0d53f98f2ca?q=80&w=200&auto=format&fit=crop'; ?>
                    <img src="<?= htmlspecialchars($img_unity) ?>" alt="Unity" class="value-img">
                    <h4 class="fw-bold mb-2">Unity</h4>
                    <p class="text-muted small mb-0">Fostering strong bonds and togetherness across families and borders.</p>
                </div>
            </div>
            <!-- Pillar 2 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="200">
                <div class="value-card">
                    <?php $img_edu = function_exists('getUiImage') ? getUiImage('about_core_education', 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=200&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=200&auto=format&fit=crop'; ?>
                    <img src="<?= htmlspecialchars($img_edu) ?>" alt="Education" class="value-img">
                    <h4 class="fw-bold mb-2">Education</h4>
                    <p class="text-muted small mb-0">Empowering the next generation with knowledge, guidance, and support.</p>
                </div>
            </div>
            <!-- Pillar 3 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="300">
                <div class="value-card">
                    <?php $img_her = function_exists('getUiImage') ? getUiImage('about_core_heritage', 'https://images.unsplash.com/photo-1561016444-14f7474f4d45?q=80&w=200&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1561016444-14f7474f4d45?q=80&w=200&auto=format&fit=crop'; ?>
                    <img src="<?= htmlspecialchars($img_her) ?>" alt="Heritage" class="value-img">
                    <h4 class="fw-bold mb-2">Heritage</h4>
                    <p class="text-muted small mb-0">Preserving and honoring our rich history of craftsmanship and engineering.</p>
                </div>
            </div>
            <!-- Pillar 4 -->
            <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="400">
                <div class="value-card">
                    <?php $img_sup = function_exists('getUiImage') ? getUiImage('about_core_support', 'https://images.unsplash.com/photo-1526948531399-320e7e40f0ca?q=80&w=200&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1526948531399-320e7e40f0ca?q=80&w=200&auto=format&fit=crop'; ?>
                    <img src="<?= htmlspecialchars($img_sup) ?>" alt="Support" class="value-img">
                    <h4 class="fw-bold mb-2">Support</h4>
                    <p class="text-muted small mb-0">Standing by each other through business networks and emergencies.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Join the Journey -->
<section class="about-section" style="background: #f6f8fa;">
    <div class="container">
        <div class="journey-panel p-4 p-md-5" data-aos="fade-up">
            <div class="row align-items-center g-4">
                <div class="col-lg-8"><div class="about-eyebrow text-warning mb-2">Be part of what comes next</div><h2 class="fw-bold mb-3">Every member adds strength to the story.</h2><p class="mb-0">Whether you are looking to connect with families, grow a business, explore opportunities or give back, there is a place for you in this shared journey.</p></div>
                <div class="col-lg-4 text-lg-end"><a href="<?= BASE_URL ?><?= isLoggedIn() ? 'dashboard.php' : 'register.php' ?>" class="btn btn-warning btn-lg fw-bold rounded-pill px-4"><?= isLoggedIn() ? 'Visit Dashboard' : 'Join the Community' ?> <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
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
