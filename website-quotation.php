<?php
$page_title = "Get a Website Quotation";
require_once 'includes/db.php';
require_once 'includes/session.php';

// Form Processing
$form_msg = '';
$form_msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quote'])) {
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $phone = trim(htmlspecialchars($_POST['phone'] ?? ''));
    $company = trim(htmlspecialchars($_POST['company'] ?? ''));
    $website_type = trim(htmlspecialchars($_POST['website_type'] ?? ''));
    $budget = trim(htmlspecialchars($_POST['budget'] ?? ''));
    $domain_hosting = trim(htmlspecialchars($_POST['domain_hosting'] ?? ''));
    $features = isset($_POST['features']) && is_array($_POST['features']) ? implode(', ', array_map('htmlspecialchars', $_POST['features'])) : 'None selected';
    $reference_urls = trim(htmlspecialchars($_POST['reference_urls'] ?? ''));
    $details = trim(htmlspecialchars($_POST['details'] ?? ''));
    $terms_agreed = isset($_POST['terms']) ? 'Yes' : 'No';

    if (empty($name) || empty($phone) || empty($website_type) || empty($budget)) {
        $form_msg = "Please fill in all the required fields.";
        $form_msg_type = "danger";
    } elseif ($terms_agreed === 'No') {
        $form_msg = "You must agree to the terms and conditions to proceed.";
        $form_msg_type = "warning";
    } else {
        // Save to Database
        try {
            $stmt = $pdo->prepare("INSERT INTO website_quotations (name, email, phone, company, website_type, budget, domain_hosting, features, reference_urls, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $company, $website_type, $budget, $domain_hosting, $features, $reference_urls, $details]);
        } catch (PDOException $e) {
            // Log error but continue to try sending email
            error_log("Failed to save website quotation to DB: " . $e->getMessage());
        }

        require_once "vendor/PHPMailer/src/Exception.php";
        require_once "vendor/PHPMailer/src/PHPMailer.php";
        require_once "vendor/PHPMailer/src/SMTP.php";

        $global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
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
                $mail->setFrom($global_settings["smtp_username"], "Website Quotation");
            } else {
                $mail->isMail();
                $mail->setFrom("noreply@".$_SERVER['HTTP_HOST'], "Website Quotation");
            }

            $mail->addAddress("loharsamuday@gmail.com"); // Sending to the admin

            // If user provided email, send them a confirmation too? Or just to admin. Let's send to admin.
            // if(!empty($email)) {
            //    $mail->addReplyTo($email, $name);
            // }

            $mail->isHTML(true);
            $mail->Subject = "New Website Quotation Request from $name";
            $mail->Body    = "
                <h3>New Website Quotation Request</h3>
                <p><strong>Client Details:</strong></p>
                <table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 800px; margin-bottom: 20px;'>
                    <tr><td width='30%'><strong>Name</strong></td><td>$name</td></tr>
                    <tr><td><strong>Email</strong></td><td>$email</td></tr>
                    <tr><td><strong>Phone</strong></td><td>$phone</td></tr>
                    <tr><td><strong>Company/Org</strong></td><td>$company</td></tr>
                </table>
                
                <p><strong>Project Requirements:</strong></p>
                <table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; max-width: 800px;'>
                    <tr><td width='30%'><strong>Website Type</strong></td><td>$website_type</td></tr>
                    <tr><td><strong>Budget Range</strong></td><td>$budget</td></tr>
                    <tr><td><strong>Domain & Hosting Needed?</strong></td><td>$domain_hosting</td></tr>
                    <tr><td><strong>Features Required</strong></td><td>$features</td></tr>
                    <tr><td><strong>Reference URLs</strong></td><td>" . nl2br($reference_urls) . "</td></tr>
                    <tr><td><strong>Project Details</strong></td><td>" . nl2br($details) . "</td></tr>
                    <tr><td><strong>Agreed to Terms?</strong></td><td>$terms_agreed</td></tr>
                </table>
            ";

            $mail->send();
            $form_msg = "Your quotation request has been submitted successfully. We will review your requirements and get back to you shortly.";
            $form_msg_type = "success";
            
            // Clear form data on success (optional, we'll just not pre-fill if it's success)
            $success = true;
        } catch (Exception $e) {
            $form_msg = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            $form_msg_type = "danger";
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<style>
    .quote-hero {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 60px 0;
    }
    .form-section {
        background: #f8f9fa;
        padding: 50px 0;
    }
    .quote-card {
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    .quote-card-header {
        background: #ffc107;
        color: #000;
        padding: 30px;
        text-align: center;
        border-bottom: 5px solid #e0a800;
    }
    .quote-card-body {
        padding: 40px;
        background: #fff;
    }
    .section-title-sm {
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #6c757d;
        font-weight: 700;
        margin-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 10px;
    }
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }
    .custom-control-label {
        cursor: pointer;
    }
</style>

<!-- Hero Section -->
<section class="quote-hero text-center text-white">
    <div class="container" data-aos="fade-up">
        <h1 class="display-4 fw-bold mb-3 drop-shadow">Get a <span class="text-warning">Website Quotation</span></h1>
        <p class="lead text-light opacity-75 mx-auto" style="max-width: 700px;">Tell us about your project requirements in detail, and we'll provide you with a comprehensive proposal and cost estimate.</p>
    </div>
</section>

<!-- Form Section -->
<section class="form-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <?php if(!empty($form_msg)): ?>
                    <div class="alert alert-<?= $form_msg_type ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                        <strong><?= $form_msg_type === 'success' ? 'Success!' : 'Error!' ?></strong> <?= $form_msg ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if(!isset($success)): ?>
                <div class="card quote-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="quote-card-header">
                        <h3 class="fw-bold mb-0"><i class="fa-solid fa-file-invoice me-2"></i> Project Requirement Form</h3>
                    </div>
                    <div class="quote-card-body">
                        <form action="website-quotation.php" method="POST">
                            
                            <!-- Personal Details -->
                            <h4 class="section-title-sm text-primary"><i class="fa-solid fa-user-tie me-2"></i> 1. Contact Information</h4>
                            <div class="row g-4 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control form-control-lg" required placeholder="Enter your name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span></label>
                                    <input type="tel" name="phone" class="form-control form-control-lg" required placeholder="Enter your phone number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email Address</label>
                                    <input type="email" name="email" class="form-control form-control-lg" placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Company / Organization Name</label>
                                    <input type="text" name="company" class="form-control form-control-lg" placeholder="Optional" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
                                </div>
                            </div>

                            <!-- Project Details -->
                            <h4 class="section-title-sm text-primary"><i class="fa-solid fa-laptop-code me-2"></i> 2. Project Requirements</h4>
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Type of Website <span class="text-danger">*</span></label>
                                    <select name="website_type" class="form-select form-select-lg" required>
                                        <option value="">Select website type</option>
                                        <option value="Business/Corporate">Business / Corporate Website</option>
                                        <option value="E-Commerce">E-Commerce / Online Store</option>
                                        <option value="Blog/News">Blog / News Portal</option>
                                        <option value="Portfolio">Personal Portfolio</option>
                                        <option value="Community/Directory">Community / Directory / Matrimony</option>
                                        <option value="Custom Web App">Custom Web Application</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Estimated Budget <span class="text-danger">*</span></label>
                                    <select name="budget" class="form-select form-select-lg" required>
                                        <option value="">Select your budget</option>
                                        <option value="Below ₹10,000">Below ₹10,000</option>
                                        <option value="₹10,000 - ₹25,000">₹10,000 - ₹25,000</option>
                                        <option value="₹25,000 - ₹50,000">₹25,000 - ₹50,000</option>
                                        <option value="₹50,000 - ₹1,00,000">₹50,000 - ₹1,00,000</option>
                                        <option value="Above ₹1,00,000">Above ₹1,00,000</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Do you need Domain Name & Hosting?</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="domain_hosting" id="dh_yes" value="Yes, I need both" checked>
                                            <label class="form-check-label" for="dh_yes">Yes, I need both</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="domain_hosting" id="dh_no" value="No, I already have them">
                                            <label class="form-check-label" for="dh_no">No, I already have them</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="domain_hosting" id="dh_unsure" value="Not sure / Need advice">
                                            <label class="form-check-label" for="dh_unsure">Not sure / Need advice</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Features -->
                            <div class="mb-4">
                                <label class="form-label fw-bold mb-3">Which features do you need? (Check all that apply)</label>
                                <div class="checkbox-grid">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="User Login/Registration" id="f1">
                                        <label class="form-check-label custom-control-label" for="f1">User Login / Registration</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="Payment Gateway" id="f2">
                                        <label class="form-check-label custom-control-label" for="f2">Payment Gateway (Online Payments)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="Admin Panel" id="f3">
                                        <label class="form-check-label custom-control-label" for="f3">Admin Control Panel</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="Contact Form" id="f4">
                                        <label class="form-check-label custom-control-label" for="f4">Contact / Enquiry Forms</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="Live Chat / WhatsApp" id="f5">
                                        <label class="form-check-label custom-control-label" for="f5">Live Chat / WhatsApp Int.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="Multi-Language" id="f6">
                                        <label class="form-check-label custom-control-label" for="f6">Multi-Language Support</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="Blog / News Section" id="f7">
                                        <label class="form-check-label custom-control-label" for="f7">Blog / News Section</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="features[]" value="SEO Optimization" id="f8">
                                        <label class="form-check-label custom-control-label" for="f8">SEO Optimization</label>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4 mb-5">
                                <div class="col-12">
                                    <label class="form-label fw-bold">Reference Websites (If any)</label>
                                    <textarea name="reference_urls" class="form-control form-control-lg" rows="2" placeholder="List links to websites you like or want us to refer to..."><?= htmlspecialchars($_POST['reference_urls'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Detailed Project Description <span class="text-danger">*</span></label>
                                    <textarea name="details" class="form-control form-control-lg" rows="6" required placeholder="Please describe your business, what the website is for, target audience, and any specific requirements..."><?= htmlspecialchars($_POST['details'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Terms & Submit -->
                            <h4 class="section-title-sm text-primary"><i class="fa-solid fa-clipboard-check me-2"></i> 3. Terms & Conditions</h4>
                            <div class="mb-4 p-4 bg-light border rounded">
                                <p class="small text-muted mb-3">By submitting this form, you acknowledge that:</p>
                                <ul class="small text-muted mb-3">
                                    <li>This is a request for quotation, not a binding contract.</li>
                                    <li>The estimated budget is for our reference to provide suitable solutions and is not the final price.</li>
                                    <li>We will contact you via phone or email to discuss the project further before providing a final quote.</li>
                                    <li>Your data will be kept confidential and used only for the purpose of communicating regarding this project.</li>
                                </ul>
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                                    <label class="form-check-label fw-bold text-dark" for="terms">
                                        I have read and agree to the Terms & Conditions <span class="text-danger">*</span>
                                    </label>
                                </div>
                            </div>

                            <div class="text-center mt-5">
                                <button type="submit" name="submit_quote" class="btn btn-warning btn-lg px-5 py-3 fw-bold rounded-pill shadow hover-scale w-100 w-md-auto">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Submit Quotation Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <a href="web-services.php" class="btn btn-outline-primary rounded-pill px-4"><i class="fa-solid fa-arrow-left me-2"></i> Back to Services</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
