<?php
$page_title = "Privacy Policy";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container my-5 py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 bg-white p-5 rounded-4 shadow-sm border-top border-4 border-warning">
            <h1 class="fw-bold mb-4 text-center">Privacy Policy</h1>
            <p class="text-muted text-center mb-5">Last updated: <?= date('F d, Y') ?></p>
            
            <div class="policy-content" style="line-height: 1.8;">
                <h4 class="fw-bold text-dark mt-4">1. Information We Collect</h4>
                <p class="text-muted">We collect information you provide directly to us, such as when you create or modify your account, request on-demand services, contact customer support, or otherwise communicate with us. This information may include: name, email, phone number, postal address, profile picture, and other information you choose to provide.</p>
                
                <h4 class="fw-bold text-dark mt-4">2. How We Use Your Information</h4>
                <p class="text-muted">We may use the information we collect about you to:</p>
                <ul class="text-muted">
                    <li>Provide, maintain, and improve our Services.</li>
                    <li>Perform internal operations, including, for example, to prevent fraud and abuse of our Services.</li>
                    <li>Send you communications we think will be of interest to you, including information about products, services, promotions, news, and events.</li>
                </ul>

                <h4 class="fw-bold text-dark mt-4">3. Security</h4>
                <p class="text-muted">We use industry-standard security measures (such as 256-bit SSL encryption) to protect the loss, misuse, and alteration of the information under our control. However, please note that no data transmission over the Internet can be guaranteed to be 100% secure.</p>

                <h4 class="fw-bold text-dark mt-4">4. Contact Us</h4>
                <p class="text-muted">If you have any questions about this Privacy Policy, please contact us at <?= ADMIN_EMAIL ?>.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
