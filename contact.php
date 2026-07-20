<?php
$page_title = "Contact Us";
require_once 'includes/db.php';
require_once 'includes/session.php';

require_once 'vendor/PHPMailer/src/Exception.php';
require_once 'vendor/PHPMailer/src/PHPMailer.php';
require_once 'vendor/PHPMailer/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, name, email, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $name, $email, $subject, $message]);

            $global_settings = function_exists("getGlobalSettings") ? getGlobalSettings() : [];
            if (!empty($global_settings["smtp_host"]) && !empty($global_settings["smtp_username"]) && !empty($global_settings["smtp_password"])) {
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $global_settings['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $global_settings['smtp_username'];
                    $mail->Password   = $global_settings['smtp_password'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $global_settings['smtp_port'] ?? 587;

                    $mail->setFrom($global_settings['smtp_username'], SITE_NAME . ' Contact Form');
                    $mail->addAddress('loharsamuday@gmail.com');
                    $mail->addReplyTo($email, $name);

                    $mail->isHTML(true);
                    $mail->Subject = 'New Contact Message: ' . $subject;
                    $mail->Body    = "
                        <h3>New Contact Message</h3>
                        <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
                        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                        <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                        <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
                    ";
                    $mail->AltBody = "New Contact Message\nName: $name\nEmail: $email\nSubject: $subject\nMessage:\n$message";

                    $mail->send();
                } catch (Exception $e) {
                    // Ignore mailer error, message is still saved in DB
                }
            }

            setFlashMessage('success', 'Your message has been sent successfully. We will get back to you shortly!');
            header("Location: contact.php");
            exit;
        } catch (PDOException $e) {
            $error = "System error. Please try again later.";
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<?php $banner_img = function_exists('getUiImage') ? getUiImage('banner_contact', 'https://images.unsplash.com/photo-1516387938699-a93567ec168e?q=80&w=1920&auto=format&fit=crop') : 'https://images.unsplash.com/photo-1516387938699-a93567ec168e?q=80&w=1920&auto=format&fit=crop'; ?>
<div class="page-banner mb-4">
    <img src="<?= htmlspecialchars($banner_img) ?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">
</div>

<style>
    .contact-card-anim {
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
    }
    .contact-card-anim:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: 0 1rem 3rem rgba(0,0,0,.175)!important;
    }
    .contact-card-anim i {
        transition: transform 0.4s ease;
    }
    .contact-card-anim:hover i {
        transform: scale(1.2) rotate(10deg);
    }
    .form-anim {
        transition: all 0.3s ease;
    }
    .form-anim:focus {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
</style>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-custom p-4 p-md-5 border-top border-4 border-warning shadow-sm" data-aos="zoom-in" data-aos-duration="1000">
                <h2 class="fw-bold text-center mb-4 text-warning" data-aos="fade-down" data-aos-delay="100">Contact Us</h2>
                <p class="text-center text-muted mb-5" data-aos="fade-up" data-aos-delay="200">Have any questions, suggestions, or concerns? We'd love to hear from you. Please fill out the form below and our team will respond as soon as possible.</p>
                
                <?php displayFlashMessage(); ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="contact.php">
                    <div class="row g-3">
                        <div class="col-md-6" data-aos="fade-right" data-aos-delay="300">
                            <label class="form-label fw-bold">Full Name *</label>
                            <input type="text" name="name" class="form-control form-control-lg form-anim" required value="<?= isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) : '' ?>">
                        </div>
                        <div class="col-md-6" data-aos="fade-left" data-aos-delay="400">
                            <label class="form-label fw-bold">Email Address *</label>
                            <input type="email" name="email" class="form-control form-control-lg form-anim" required value="<?= isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : '' ?>">
                        </div>
                        <div class="col-12" data-aos="fade-up" data-aos-delay="500">
                            <label class="form-label fw-bold">Subject *</label>
                            <input type="text" name="subject" class="form-control form-control-lg form-anim" placeholder="What is this regarding?" required>
                        </div>
                        <div class="col-12" data-aos="fade-up" data-aos-delay="600">
                            <label class="form-label fw-bold">Message *</label>
                            <textarea name="message" class="form-control form-anim" rows="6" placeholder="Write your message here..." required></textarea>
                        </div>
                        <div class="col-12 mt-4 text-center" data-aos="zoom-in" data-aos-delay="700">
                            <button type="submit" class="btn btn-warning btn-lg px-5 fw-bold shadow-sm rounded-pill"><i class="fa-solid fa-paper-plane me-2"></i> Send Message</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="row mt-5 text-center g-4">
                <?php $global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : []; ?>
                <div class="col-md-4" data-aos="fade-right" data-aos-delay="200" data-aos-duration="800">
                    <div class="p-4 bg-light rounded shadow-sm h-100 border contact-card-anim">
                        <i class="fa-solid fa-envelope fa-2x text-warning mb-3"></i>
                        <h5 class="fw-bold">Email Us</h5>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($global_settings['contact_email'] ?? (defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'support@vishwakarmasamaj.com')) ?></p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="400" data-aos-duration="800">
                    <div class="p-4 bg-light rounded shadow-sm h-100 border contact-card-anim">
                        <i class="fa-solid fa-phone fa-2x text-warning mb-3"></i>
                        <h5 class="fw-bold">Call Us</h5>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($global_settings['contact_phone'] ?? '+91 9876543210') ?></p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-left" data-aos-delay="600" data-aos-duration="800">
                    <div class="p-4 bg-light rounded shadow-sm h-100 border contact-card-anim">
                        <i class="fa-solid fa-location-dot fa-2x text-warning mb-3"></i>
                        <h5 class="fw-bold">Visit Us</h5>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($global_settings['contact_address'] ?? 'Vishwakarma Bhawan, India') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
