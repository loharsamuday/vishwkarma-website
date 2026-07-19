<?php
$page_title = "Upgrade to Premium";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';

// Check current status
$stmt = $pdo->prepare("SELECT id, is_premium FROM matrimony_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    setFlashMessage('warning', 'Please create your Matrimony Profile first before upgrading.');
    header("Location: matrimony-register.php");
    exit;
}

if ($profile['is_premium']) {
    setFlashMessage('info', 'You are already a Premium Member!');
    header("Location: dashboard.php");
    exit;
}

// Fetch Payment Settings
$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
$payment_price = $global_settings['payment_price'] ?? '999';
$payment_upi_id = $global_settings['payment_upi_id'] ?? 'vishwakarma@upi';
$payment_qr_code = !empty($global_settings['payment_qr_code']) ? BASE_URL . "uploads/banners/" . $global_settings['payment_qr_code'] : "https://placehold.co/200x200/000000/ffffff?text=Scan+QR+Code";
$payment_mode = $global_settings['payment_mode'] ?? 'manual';
$razorpay_enabled = in_array($payment_mode, ['razorpay', 'both'], true);
$manual_enabled = in_array($payment_mode, ['manual', 'both'], true);
$razorpay_key_id = $global_settings['razorpay_key_id'] ?? '';
$razorpay_description = $global_settings['razorpay_description'] ?? 'Premium Membership Upgrade';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id = trim($_POST['transaction_id']);
    
    if (empty($transaction_id)) {
        $error = "Transaction ID/UTR Number is required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert into payments with 'Pending' status
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, amount, payment_type, transaction_id, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $payment_price, 'Subscription', $transaction_id, 'Pending']);
            
            $pdo->commit();
            setFlashMessage('success', 'Payment submitted successfully! Please wait for Admin Verification.');
            header("Location: dashboard.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() == 23000) {
                $error = "This Transaction ID has already been submitted.";
            } else {
                $error = "System error. Please try again.";
            }
        }
    }
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-4 border-top border-4 border-warning shadow-sm text-center">
                <h3 class="fw-bold mb-3 text-warning"><i class="fa-solid fa-crown me-2"></i>Upgrade to Premium</h3>
                <p class="text-muted mb-4">Unlock "Discussion" access and connect directly with your matches.</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger text-start"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div class="bg-light p-4 rounded mb-4 border">
                    <h5 class="fw-bold mb-3 text-dark">Pay Amount: ₹<?= htmlspecialchars($payment_price) ?>/-</h5>
                    <img src="<?= htmlspecialchars($payment_qr_code) ?>" alt="QR Code" class="img-fluid rounded mb-3 shadow-sm" style="max-width: 200px;">
                    <p class="small text-muted mb-0">UPI ID: <?= htmlspecialchars($payment_upi_id) ?></p>
                </div>

                <?php if ($razorpay_enabled): ?>
                    <div class="border rounded p-3 mb-4 bg-white">
                        <h6 class="fw-bold text-dark mb-2">Pay Securely with Razorpay</h6>
                        <p class="small text-muted mb-3">Card, UPI, Wallets and Netbanking support available.</p>
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-success bg-opacity-10 text-success border border-success w-100 py-2"><i class="fa-solid fa-shield-halved me-1"></i> 100% Secure Payment (PCI-DSS Compliant)</span>
                        </div>
                        <?php if (!empty($razorpay_key_id)): ?>
                            <button type="button" id="razorpay-pay-btn" class="btn btn-primary btn-lg w-100 fw-bold"><i class="fa-solid fa-credit-card me-2"></i>Pay ₹<?= htmlspecialchars($payment_price) ?> with Razorpay</button>
                        <?php else: ?>
                            <div class="alert alert-warning small mb-0">Razorpay is enabled in admin settings but API credentials are not configured yet.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($manual_enabled): ?>
                    <form method="POST" class="text-start">
                        <div class="mb-4">
                            <label class="fw-bold">Enter Transaction ID / UTR Number *</label>
                            <input type="text" name="transaction_id" class="form-control form-control-lg mt-2" placeholder="e.g. 123456789012" required>
                            <small class="text-muted">You will find this 12-digit number on your payment receipt.</small>
                        </div>
                        
                        <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold">Submit Payment Details</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="alert alert-info mt-4 small">
                <i class="fa-solid fa-shield-halved me-2"></i><strong>Secure Transaction:</strong> Your payments are verified securely by our team. Once verified, your premium features will be activated automatically.
            </div>
        </div>
    </div>
</div>

<?php if ($razorpay_enabled && !empty($razorpay_key_id)): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const payButton = document.getElementById('razorpay-pay-btn');
    if (!payButton) return;

    payButton.addEventListener('click', function () {
        payButton.disabled = true;
        payButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparing payment...';

        fetch('<?= BASE_URL ?>api/create_razorpay_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                amount: <?= (int) round((float) $payment_price * 100) ?>,
                description: <?= json_encode($razorpay_description) ?>
            })
        })
        .then(async function (response) {
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to create payment order.');
            }
            return data;
        })
        .then(function (data) {
            const options = {
                key: '<?= htmlspecialchars($razorpay_key_id) ?>',
                amount: data.amount,
                currency: data.currency,
                name: '<?= htmlspecialchars(SITE_NAME) ?>',
                description: data.description,
                order_id: data.order_id,
                handler: function (response) {
                    fetch('<?= BASE_URL ?>api/verify_razorpay_payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_signature: response.razorpay_signature,
                            amount: data.amount
                        })
                    })
                    .then(async function (verifyResponse) {
                        const verifyData = await verifyResponse.json();
                        if (!verifyResponse.ok || !verifyData.success) {
                            throw new Error(verifyData.message || 'Payment verification failed.');
                        }
                        window.location.href = verifyData.redirect_url || '<?= BASE_URL ?>dashboard.php';
                    })
                    .catch(function (error) {
                        alert(error.message || 'Payment verification failed.');
                        payButton.disabled = false;
                        payButton.innerHTML = 'Pay ₹<?= htmlspecialchars($payment_price) ?> with Razorpay';
                    });
                },
                prefill: {
                    name: '<?= htmlspecialchars($_SESSION['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? '') ?>',
                    email: '<?= htmlspecialchars($_SESSION['email'] ?? '') ?>'
                },
                theme: { color: '#f59e0b' }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                alert(response.error && response.error.description ? response.error.description : 'Payment failed. Please try again.');
                payButton.disabled = false;
                payButton.innerHTML = 'Pay ₹<?= htmlspecialchars($payment_price) ?> with Razorpay';
            });
            rzp.open();
        })
        .catch(function (error) {
            alert(error.message || 'Unable to initialize payment.');
            payButton.disabled = false;
            payButton.innerHTML = 'Pay ₹<?= htmlspecialchars($payment_price) ?> with Razorpay';
        });
    });
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
