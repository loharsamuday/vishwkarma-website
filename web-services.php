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
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_web_services', 'https://placehold.co/1920x400/2c3e50/f39c12?text=IT+%26+Web+Services') : 'https://placehold.co/1920x400/2c3e50/f39c12?text=IT+%26+Web+Services'; ?>
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
</style>

<!-- Hero Section -->
<section class="web-hero text-center text-white">
    <div class="container web-hero-content" data-aos="zoom-in">
        <span class="badge bg-warning text-dark fs-6 px-4 py-2 rounded-pill mb-4"><i class="fa-solid fa-code me-2"></i> Professional IT & Web Services</span>
        <h1 class="display-3 fw-bold mb-4 drop-shadow">Need a <span class="text-warning">Website</span> for Your Business?</h1>
        <p class="lead mb-5 fs-4 text-light opacity-75" style="max-width: 800px; margin: 0 auto;">We design and develop high-quality, fast, and secure websites tailored to your specific needs. From e-commerce to custom portals, we build digital experiences that drive growth.</p>
        
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?= $whatsapp_link ?>" <?= !empty($whatsapp_clean) ? 'target="_blank"' : '' ?> class="btn btn-warning btn-lg px-4 py-3 fw-bold rounded-pill shadow-lg hover-scale">
                <i class="fa-brands fa-whatsapp fs-4 me-2 align-middle"></i> Chat With Developer
            </a>
            <a href="website-quotation.php" class="btn btn-outline-light btn-lg px-4 py-3 fw-bold rounded-pill shadow-lg hover-scale">
                <i class="fa-solid fa-file-invoice-dollar fs-4 me-2 align-middle"></i> Get a Free Quote
            </a>
        </div>
        <p class="mt-4 text-light opacity-50 small"><i class="fa-solid fa-shield-halved me-1"></i> Trusted by the Community &middot; 100% Satisfaction Guaranteed</p>
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
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Business Websites</h4>
                    <p class="text-muted">Professional portfolio and corporate websites to showcase your services, build trust, and attract new clients.</p>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Community Portals</h4>
                    <p class="text-muted">Custom platforms like Matrimony, Job portals, and Directories tailored for your community.</p>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="card service-card-web bg-white p-4 h-100">
                    <div class="feature-icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-map-location-dot"></i>
                    </div>
                    <h4 class="fw-bold mb-3">Google Map Listing</h4>
                    <p class="text-muted">Get your business verified on Google My Business. Rank higher on local searches and attract more customers.</p>
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
                <img src="<?= function_exists('getUiImage') ? getUiImage('web_services_placeholder', 'https://placehold.co/600x500/2c3e50/f39c12?text=Web+Development') : 'https://placehold.co/600x500/2c3e50/f39c12?text=Web+Development' ?>" class="img-fluid rounded-4 shadow-lg" alt="Web Development">
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

<!-- Appointment Form Section -->
<section class="py-5 bg-light" id="appointment">
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="card shadow-lg border-0 rounded-4">
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
