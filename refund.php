<?php
$page_title = "Cancellation & Refund Policy";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container my-5 py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 bg-white p-5 rounded-4 shadow-sm border-top border-4 border-warning">
            <h1 class="fw-bold mb-4 text-center">Cancellation & Refund Policy</h1>
            <p class="text-muted text-center mb-5">Last updated: <?= date('F d, Y') ?></p>
            
            <div class="policy-content" style="line-height: 1.8;">
                <h4 class="fw-bold text-dark mt-4">1. Cancellation Policy</h4>
                <p class="text-muted">Users can cancel their premium membership or services at any time. However, cancellation does not guarantee a refund. The cancellation will simply prevent the auto-renewal of your subscription if applicable.</p>
                
                <h4 class="fw-bold text-dark mt-4">2. Refund Eligibility</h4>
                <p class="text-muted">Refunds are only processed under the following circumstances:</p>
                <ul class="text-muted">
                    <li>If a duplicate payment was accidentally made due to a technical glitch.</li>
                    <li>If the premium services were not activated on your account within 48 hours of successful payment.</li>
                </ul>

                <h4 class="fw-bold text-dark mt-4">3. Non-Refundable Scenarios</h4>
                <p class="text-muted">Payments are strictly non-refundable once the service (e.g., viewing contact details in Matrimony) has been utilized or accessed. Change of mind after purchasing a membership is not considered a valid ground for a refund.</p>

                <h4 class="fw-bold text-dark mt-4">4. Refund Processing</h4>
                <p class="text-muted">If your refund request is approved, the amount will be credited back to the original method of payment within 5-7 business days.</p>
                
                <h4 class="fw-bold text-dark mt-4">5. Contact for Refunds</h4>
                <p class="text-muted">For any payment-related disputes or refund requests, please email us at <?= ADMIN_EMAIL ?> with your transaction ID.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
