<?php
$page_title = "Web Development Services";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Get WhatsApp Number for CTA
if (!isset($global_settings)) {
    $global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
}
$whatsapp_number = $global_settings['whatsapp_number'] ?? ($global_settings['contact_phone'] ?? '');
$whatsapp_clean = preg_replace('/[^0-9]/', '', $whatsapp_number);
$whatsapp_link = !empty($whatsapp_clean) ? "https://wa.me/" . $whatsapp_clean . "?text=" . urlencode("Hello, I am interested in getting a website developed.") : "contact.php";

// Appointment Form Processing
$form_msg = '';
$form_msg_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $name = trim(htmlspecialchars($_POST['name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $phone = trim(htmlspecialchars($_POST['phone']));
    $date = trim(htmlspecialchars($_POST['date']));
    $details = trim(htmlspecialchars($_POST['details']));

    if (empty($name) || empty($phone)) {
        $form_msg = "Please fill in all required fields (Name and Phone).";
        $form_msg_type = "danger";
    } else {
        require_once "vendor/PHPMailer/src/Exception.php";
        require_once "vendor/PHPMailer/src/PHPMailer.php";
        require_once "vendor/PHPMailer/src/SMTP.php";

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            if (!empty($global_settings["smtp_host"]) && !empty($global_settings["smtp_username"])) {
                $mail->isSMTP();
                $mail->Host       = $global_settings["smtp_host"];
                $mail->SMTPAuth   = true;
                $mail->Username   = $global_settings["smtp_username"];
                $mail->Password   = $global_settings["smtp_password"];
                $mail->SMTPSecure = (!empty($global_settings["smtp_secure"]) && strtolower($global_settings["smtp_secure"]) === "ssl") ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $global_settings["smtp_port"];
                $mail->setFrom($global_settings["smtp_username"], "Website Appointment");
            } else {
                // Fallback to mail() if SMTP is not properly configured
                $mail->isMail();
                $mail->setFrom("noreply@".$_SERVER['HTTP_HOST'], "Website Appointment");
            }

            $mail->addAddress("loharsamuday@gmail.com");

            $mail->isHTML(true);
            $mail->Subject = "New Appointment Booking from $name";
            $mail->Body    = "
                <h3>New Appointment Booking Request</h3>
                <table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 600px;'>
                    <tr><td width='30%'><strong>Name</strong></td><td>$name</td></tr>
                    <tr><td><strong>Email</strong></td><td>$email</td></tr>
                    <tr><td><strong>Phone</strong></td><td>$phone</td></tr>
                    <tr><td><strong>Preferred Date</strong></td><td>$date</td></tr>
                    <tr><td><strong>Details</strong></td><td>" . nl2br($details) . "</td></tr>
                </table>
            ";

            $mail->send();
            $form_msg = "Your appointment request has been sent successfully. We will contact you soon.";
            $form_msg_type = "success";
        } catch (Exception $e) {
            $form_msg = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            $form_msg_type = "danger";
        }
    }
}
?>
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_web_services', 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?q=80&w=1920&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?q=80&w=1920&auto=format&fit=crop'; ?>
<div class="page-banner mb-4">
    <img src="<?= htmlspecialchars($banner_img) ?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">
</div>

<style>
    .web-hero {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 100px 0;
        position: relative;
        overflow: hidden;
    }
    .web-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(243,156,18,0.1) 0%, rgba(0,0,0,0) 60%);
        z-index: 1;
        animation: pulse-bg 10s infinite alternate;
    }
    @keyframes pulse-bg {
        0% { transform: scale(1); }
        100% { transform: scale(1.2); }
    }
    .web-hero-content {
        position: relative;
        z-index: 2;
    }
    .service-card-web {
        transition: all 0.3s ease;
        border-radius: 15px;
        border: 1px solid rgba(0,0,0,0.05);
    }
    .service-card-web:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        border-color: #ffc107;
    }
    .feature-icon-box {
        width: 70px;
        height: 70px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin-bottom: 20px;
    }
    .web-hero { padding: 7.5rem 0 6.5rem; background: radial-gradient(circle at 82% 20%, rgba(243,156,18,.25), transparent 26%), linear-gradient(125deg, #071b2c 0%, #102f47 52%, #0d2133 100%); }
    .web-hero::after { content: ''; position: absolute; inset: auto 0 0; height: 120px; background: linear-gradient(transparent, rgba(7,27,44,.32)); z-index: 1; }
    .web-hero-content { max-width: 920px; }
    .web-hero h1 { letter-spacing: -.055em; line-height: 1.05; }
    .web-kicker { display: inline-flex; gap: .55rem; align-items: center; padding: .55rem .95rem; border: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.08); color: #ffd379; border-radius: 99px; font-size: .77rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
    .hero-proof { display: grid; grid-template-columns: repeat(4, 1fr); max-width: 780px; margin: 4rem auto 0; border: 1px solid rgba(255,255,255,.13); border-radius: 16px; background: rgba(255,255,255,.07); backdrop-filter: blur(8px); overflow: hidden; }
    .hero-proof-item { padding: 1.05rem; border-right: 1px solid rgba(255,255,255,.13); }
    .hero-proof-item:last-child { border-right: 0; }
    .hero-proof strong { display: block; color: #ffd379; font-size: 1.2rem; }
    .hero-proof span { color: rgba(255,255,255,.74); font-size: .73rem; font-weight: 600; }
    .service-card-web { border: 1px solid #e7edf2; border-radius: 16px; box-shadow: 0 10px 28px rgba(16,39,59,.045); }
    .service-card-web:hover { border-color: rgba(231,155,23,.55); box-shadow: 0 20px 38px rgba(16,39,59,.12); }
    .service-card-web p { font-size: .91rem; line-height: 1.7; }
    .service-card-web .service-link { color: #bd7007; font-size: .8rem; font-weight: 800; text-decoration: none; letter-spacing: .03em; }
    .service-card-web .service-link:hover { color: #8f5000; }
    .service-card-web .service-link i { transition: transform .2s ease; }
    .service-card-web:hover .service-link i { transform: translateX(4px); }
    .standards-section { background: #f4f7fa; }
    .standard-card { background: #fff; height: 100%; padding: 1.45rem; border-radius: 14px; border: 1px solid #e6edf3; }
    .standard-card .number { color: #d98a12; font-size: .77rem; letter-spacing: .1em; font-weight: 800; }
    .process-step { position: relative; padding: 1.55rem; background: #fff; border: 1px solid #e6edf3; border-radius: 14px; height: 100%; }
    .process-step .step-number { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 50%; background: #fff2d8; color: #c57504; font-weight: 800; margin-bottom: 1rem; }
    .faq-wrap .accordion-item { border: 1px solid #e3eaf0; border-radius: 12px !important; overflow: hidden; margin-bottom: .85rem; }
    .faq-wrap .accordion-button { box-shadow: none; font-weight: 700; color: #183248; }
    .faq-wrap .accordion-button:not(.collapsed) { background: #fff8ea; color: #a95e00; }
    .appointment-card { border: 1px solid #e5edf3 !important; box-shadow: 0 20px 45px rgba(16,39,59,.1) !important; }
    .form-control, .form-select { border-color: #dbe4eb; border-radius: 10px; padding: .75rem .9rem; }
    .form-control:focus, .form-select:focus { border-color: #e6a22d; box-shadow: 0 0 0 .2rem rgba(230,162,45,.15); }
    @media (max-width: 767.98px) { .web-hero { padding: 5.5rem 0 4.5rem; } .web-hero h1 { font-size: 2.55rem; } .hero-proof { grid-template-columns: repeat(2, 1fr); margin-top: 2.5rem; } .hero-proof-item:nth-child(2) { border-right: 0; } .hero-proof-item { border-bottom: 1px solid rgba(255,255,255,.13); } .hero-proof-item:nth-child(n+3) { border-bottom: 0; } }
</style>

<!-- Hero Section -->
<section class="web-hero text-center text-white">
    <div class="container web-hero-content" data-aos="zoom-in">
        <div class="web-kicker mb-4"><i class="fa-solid fa-wand-magic-sparkles"></i> Strategy, design & development</div>
        <h1 class="display-3 fw-bold mb-4 drop-shadow">Build a digital presence<br>that <span class="text-warning">moves business forward.</span></h1>
        <p class="lead mb-5 fs-5 text-light opacity-75" style="max-width: 760px; margin: 0 auto;">From your first business website to a custom member portal, we turn practical ideas into fast, polished and easy-to-manage digital products.</p>
        
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?= $whatsapp_link ?>" <?= !empty($whatsapp_clean) ? 'target="_blank"' : '' ?> class="btn btn-warning btn-lg px-4 py-3 fw-bold rounded-pill shadow-lg hover-scale">
                <i class="fa-brands fa-whatsapp fs-4 me-2 align-middle"></i> Chat With Developer
            </a>
            <a href="website-quotation.php" class="btn btn-outline-light btn-lg px-4 py-3 fw-bold rounded-pill shadow-lg hover-scale">
                <i class="fa-solid fa-file-invoice-dollar fs-4 me-2 align-middle"></i> Get a Free Quote
            </a>
        </div>
        <div class="hero-proof">
            <div class="hero-proof-item"><strong>Mobile-first</strong><span>Built for every screen</span></div>
            <div class="hero-proof-item"><strong>SEO-ready</strong><span>Structured for discovery</span></div>
            <div class="hero-proof-item"><strong>Secure</strong><span>Careful, reliable builds</span></div>
            <div class="hero-proof-item"><strong>Clear support</strong><span>Human communication</span></div>
        </div>
    </div>
</section>

<!-- Services Offered -->
<section class="py-5 bg-light">
    <div class="container my-5">
        <div class="text-center mb-5" data-aos="fade-up">
            <h6 class="text-warning fw-bold text-uppercase tracking-wide">Our Expertise</h6>
            <h2 class="fw-bold section-title">What We <span class="text-warning">Build</span></h2>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <h4 class="fw-bold mb-3">E-Commerce Stores</h4>
                    <p class="text-muted">Start selling online with fully functional, secure, and easy-to-manage e-commerce websites with payment gateway integration.</p>
                    <a href="website-quotation.php" class="service-link mt-auto">PLAN YOUR STORE <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Business Websites</h4>
                    <p class="text-muted">Professional portfolio and corporate websites to showcase your services, build trust, and attract new clients.</p>
                    <a href="website-quotation.php" class="service-link mt-auto">GROW YOUR BRAND <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Community Portals</h4>
                    <p class="text-muted">Custom platforms like Matrimony, Job portals, and Directories tailored for your community.</p>
                    <a href="website-quotation.php" class="service-link mt-auto">BUILD A PORTAL <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Google Map Listing</h4>
                    <p class="text-muted">Get your business verified on Google My Business. Rank higher on local searches and attract more customers.</p>
                    <a href="website-quotation.php" class="service-link mt-auto">IMPROVE VISIBILITY <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-info bg-opacity-10 text-info"><i class="fa-solid fa-laptop-code"></i></div>
                    <h4 class="fw-bold mb-3">Custom Web Apps</h4>
                    <p class="text-muted">Streamline work with dashboards, booking tools, member areas and tailored internal systems.</p>
                    <a href="website-quotation.php" class="service-link mt-auto">EXPLORE POSSIBILITIES <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-secondary bg-opacity-10 text-secondary"><i class="fa-solid fa-pen-ruler"></i></div>
                    <h4 class="fw-bold mb-3">UI/UX Design</h4>
                    <p class="text-muted">Clear page structure, compelling visual identity and conversion-focused user journeys.</p>
                    <a href="website-quotation.php" class="service-link mt-auto">DESIGN YOUR EXPERIENCE <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-success bg-opacity-10 text-success"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                    <h4 class="fw-bold mb-3">SEO Foundations</h4>
                    <p class="text-muted">Technical essentials, search-friendly content structure and analytics setup from day one.</p>
                    <a href="website-quotation.php" class="service-link mt-auto">GET FOUND ONLINE <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                    <h4 class="fw-bold mb-3">Care & Maintenance</h4>
                    <p class="text-muted">Keep your website healthy with updates, backups, performance checks and dependable help.</p>
                    <a href="#appointment" class="service-link mt-auto">KEEP IT RUNNING <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="py-5 bg-white">
    <div class="container my-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-5 mb-lg-0" data-aos="fade-right">
                <img src="<?= function_exists('getUiImage') ? getUiImage('web_services_placeholder', 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=600&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=600&auto=format&fit=crop' ?>" class="img-fluid rounded-4 shadow-lg" alt="Web Development">
            </div>
            <div class="col-lg-6 px-lg-5" data-aos="fade-left">
                <h6 class="text-warning fw-bold text-uppercase tracking-wide">Why Choose Us?</h6>
                <h2 class="fw-bold mb-4">Quality Code, Affordable Price</h2>
                
                <div class="d-flex mb-4">
                    <div class="text-warning fs-3 me-3"><i class="fa-solid fa-rocket"></i></div>
                    <div>
                        <h5 class="fw-bold">Lightning Fast Performance</h5>
                        <p class="text-muted">We build highly optimized websites that load quickly and rank better on Google (SEO Friendly).</p>
                    </div>
                </div>
                
                <div class="d-flex mb-4">
                    <div class="text-success fs-3 me-3"><i class="fa-solid fa-mobile-screen-button"></i></div>
                    <div>
                        <h5 class="fw-bold">100% Mobile Responsive</h5>
                        <p class="text-muted">Your website will look perfect and function flawlessly on smartphones, tablets, and desktops.</p>
                    </div>
                </div>
                
                <div class="d-flex mb-4">
                    <div class="text-primary fs-3 me-3"><i class="fa-solid fa-handshake-angle"></i></div>
                    <div>
                        <h5 class="fw-bold">Reliable Support</h5>
                        <p class="text-muted">Being from the community, we ensure transparent communication, affordable pricing, and long-term support.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Delivery Standards -->
<section class="py-5 standards-section">
    <div class="container my-4">
        <div class="row align-items-end mb-4">
            <div class="col-lg-7" data-aos="fade-right">
                <div class="eyebrow mb-2">No unnecessary complexity</div>
                <h2 class="fw-bold section-heading mb-2">Built with the details that <span class="text-warning">matter.</span></h2>
                <p class="text-muted mb-0">A good website is more than a good-looking homepage. Every project is shaped around clarity, performance and an easy path for your visitors to take action.</p>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6 col-lg-3" data-aos="fade-up"><div class="standard-card"><div class="number mb-2">01 / PERFORMANCE</div><h5 class="fw-bold">Fast by design</h5><p class="text-muted small mb-0">Optimised pages and practical code for a smooth first impression.</p></div></div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100"><div class="standard-card"><div class="number mb-2">02 / CLARITY</div><h5 class="fw-bold">Easy to use</h5><p class="text-muted small mb-0">Thoughtful layouts that help visitors understand and act quickly.</p></div></div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200"><div class="standard-card"><div class="number mb-2">03 / TRUST</div><h5 class="fw-bold">Ready to grow</h5><p class="text-muted small mb-0">Secure essentials and scalable structure for your next stage.</p></div></div>
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300"><div class="standard-card"><div class="number mb-2">04 / HANDOVER</div><h5 class="fw-bold">No black box</h5><p class="text-muted small mb-0">Straightforward guidance so you know what has been built for you.</p></div></div>
        </div>
    </div>
</section>

<!-- Project Process -->
<section class="py-5 bg-white">
    <div class="container my-4">
        <div class="text-center mb-5" data-aos="fade-up">
            <div class="eyebrow mb-2">A simple, transparent process</div>
            <h2 class="fw-bold section-heading">From first conversation to <span class="text-warning">confident launch.</span></h2>
        </div>
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3" data-aos="fade-up"><div class="process-step"><div class="step-number">01</div><h5 class="fw-bold">Discover</h5><p class="text-muted small mb-0">We understand your audience, goals, scope and the outcome you need.</p></div></div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100"><div class="process-step"><div class="step-number">02</div><h5 class="fw-bold">Plan & Design</h5><p class="text-muted small mb-0">We shape the pages, content flow and visual direction before development begins.</p></div></div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200"><div class="process-step"><div class="step-number">03</div><h5 class="fw-bold">Build & Review</h5><p class="text-muted small mb-0">Your product is developed carefully, tested across screens and reviewed with you.</p></div></div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300"><div class="process-step"><div class="step-number">04</div><h5 class="fw-bold">Launch & Support</h5><p class="text-muted small mb-0">We help you go live with confidence and stay available for the next steps.</p></div></div>
        </div>
    </div>
</section>

<!-- Appointment Form Section -->
<section class="py-5 bg-light" id="appointment">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="card appointment-card shadow-lg border-0 rounded-4">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h6 class="text-warning fw-bold text-uppercase tracking-wide">Book an Appointment</h6>
                            <h2 class="fw-bold">Discuss Your Project</h2>
                            <p class="text-muted">Fill out the form below and we'll get back to you as soon as possible.</p>
                        </div>
                        
                        <?php if(!empty($form_msg)): ?>
                            <div class="alert alert-<?= $form_msg_type ?> alert-dismissible fade show" role="alert">
                                <?= $form_msg ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="web-services.php#appointment" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Your Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control form-control-lg" required placeholder="John Doe">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="phone" class="form-control form-control-lg" required placeholder="+91 xxxxx xxxxx">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email Address</label>
                                    <input type="email" name="email" class="form-control form-control-lg" placeholder="john@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Preferred Date</label>
                                    <input type="date" name="date" class="form-control form-control-lg">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Project Details / Message</label>
                                    <textarea name="details" class="form-control form-control-lg" rows="4" placeholder="Briefly describe what you need..."></textarea>
                                </div>
                                <div class="col-12 text-center mt-4">
                                    <button type="submit" name="book_appointment" class="btn btn-primary btn-lg px-5 py-3 fw-bold rounded-pill shadow-lg hover-scale w-100 w-md-auto">
                                        <i class="fa-solid fa-calendar-check me-2"></i> Submit Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Frequently Asked Questions -->
<section class="py-5 bg-white">
    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="text-center mb-5" data-aos="fade-up">
                    <div class="eyebrow mb-2">Helpful answers</div>
                    <h2 class="fw-bold section-heading">Questions before you <span class="text-warning">start?</span></h2>
                </div>
                <div class="accordion faq-wrap" id="webServicesFaq" data-aos="fade-up">
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOne">How much does a website project cost?</button></h3><div id="faqOne" class="accordion-collapse collapse show" data-bs-parent="#webServicesFaq"><div class="accordion-body text-muted">Every project is scoped around its features, content and timeline. Share your idea and we will provide a clear, tailored quotation before work starts.</div></div></div>
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwo">Can you redesign an existing website?</button></h3><div id="faqTwo" class="accordion-collapse collapse" data-bs-parent="#webServicesFaq"><div class="accordion-body text-muted">Yes. We can assess the current site, improve its design and user experience, or rebuild it on a more reliable foundation where needed.</div></div></div>
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqThree">Will my website work well on mobile?</button></h3><div id="faqThree" class="accordion-collapse collapse" data-bs-parent="#webServicesFaq"><div class="accordion-body text-muted">Absolutely. Mobile experience is considered from the beginning, so your website remains clear, fast and usable on phones, tablets and desktops.</div></div></div>
                    <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFour">Can you support the website after launch?</button></h3><div id="faqFour" class="accordion-collapse collapse" data-bs-parent="#webServicesFaq"><div class="accordion-body text-muted">Yes. Ongoing care can include updates, backups, improvements and technical guidance depending on your requirements.</div></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5 bg-warning text-dark text-center">
    <div class="container my-4" data-aos="zoom-in">
        <h2 class="fw-bold mb-3">Ready to Bring Your Idea to Life?</h2>
        <p class="lead mb-4 fw-semibold opacity-75">Let's discuss your project and get a free quotation today.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?= $whatsapp_link ?>" <?= !empty($whatsapp_clean) ? 'target="_blank"' : '' ?> class="btn btn-dark btn-lg px-4 py-3 fw-bold rounded-pill shadow-lg hover-scale">
                <i class="fa-brands fa-whatsapp fs-4 me-2 align-middle text-success"></i> Message on WhatsApp
            </a>
            <a href="website-quotation.php" class="btn btn-outline-dark btn-lg px-4 py-3 fw-bold rounded-pill shadow-lg hover-scale">
                <i class="fa-solid fa-file-invoice-dollar fs-4 me-2 align-middle"></i> Request Formal Quote
            </a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
