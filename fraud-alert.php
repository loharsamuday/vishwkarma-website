<?php
$page_title = "Fraud Alert & Disclaimer";
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-danger shadow">
                <div class="card-header bg-danger text-white">
                    <h3 class="mb-0"><i class="fa-solid fa-triangle-exclamation me-2"></i> Fraud Alert & Disclaimer</h3>
                </div>
                <div class="card-body p-4 text-dark">
                    <h5 class="text-danger fw-bold">Beware of Fraudulent Activities</h5>
                    <p>Dear Members & Visitors,</p>
                    <p>We kindly request you to be vigilant and exercise extreme caution while interacting with other members, responding to job postings, business directory listings, or matrimony profiles on this platform.</p>
                    
                    <h6 class="fw-bold mt-4">1. No Financial Transactions</h6>
                    <p>The website management or owners will NEVER ask you for your bank details, OTP, UPI PIN, or money to facilitate a job, matrimony match, or any other service. Do not transfer money to any individual without verifying their authenticity.</p>

                    <h6 class="fw-bold mt-4">2. Verify Before Trusting</h6>
                    <p>Profiles and listings (Jobs, Matrimony, Business) are created by users. We do not independently verify the background, financial status, or character of every registered user. Please do your own background checks before making any commitments or payments.</p>

                    <h6 class="fw-bold mt-4">3. Website Owner Liability</h6>
                    <p class="fw-bold text-danger" style="font-size: 1.1rem; border-left: 4px solid #dc3545; padding-left: 15px; background: #fff3f3; padding-top: 10px; padding-bottom: 10px;">The website owner, management team, and administrators are STRICTLY NOT RESPONSIBLE for any kind of fraud, cheating, financial loss, emotional distress, or damages resulting from the use of this website.</p>
                    <p>By using this website, you explicitly agree that any interaction you have with other users is solely at your own risk.</p>

                    <hr>
                    <p class="mb-0 small text-muted">If you notice any suspicious activity or a fake profile, please report it to the administrator immediately via the contact page.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
