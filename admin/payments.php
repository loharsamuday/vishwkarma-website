<?php
$page_title = "Manage Payments";
require_once '../includes/db.php';
require_once '../includes/session.php';

require_once '../vendor/PHPMailer/src/Exception.php';
require_once '../vendor/PHPMailer/src/PHPMailer.php';
require_once '../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

// Handle Action (Approve/Reject)
if (isset($_GET['action'], $_GET['id'])) {
    $action = $_GET['action'];
    $payment_id = $_GET['id'];
    
    $stmt = $pdo->prepare("SELECT p.*, u.first_name, u.last_name, u.email FROM payments p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if ($payment && $payment['status'] === 'Pending') {
        if ($action === 'approve') {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE payments SET status = 'Success' WHERE id = ?");
                $stmt->execute([$payment_id]);
                
                // Active premium matrimony if it was a subscription
                if ($payment['payment_type'] === 'Subscription') {
                    $stmt = $pdo->prepare("UPDATE matrimony_profiles SET is_premium = 1 WHERE user_id = ?");
                    $stmt->execute([$payment['user_id']]);
                }
                
                // Send Email with Invoice and Welcome Message
                if (!empty($global_settings['smtp_host']) && !empty($global_settings['smtp_username']) && !empty($global_settings['smtp_password']) && !empty($global_settings['smtp_port'])) {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = $global_settings['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $global_settings['smtp_username'];
                    $mail->Password   = $global_settings['smtp_password'];
                    $mail->SMTPSecure = !empty($global_settings['smtp_secure']) && strtolower($global_settings['smtp_secure']) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = $global_settings['smtp_port'];

                    $mail->setFrom($global_settings['smtp_username'], SITE_NAME);
                    $mail->addAddress($payment['email'], $payment['first_name'] . ' ' . $payment['last_name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'Payment Invoice & Welcome to Premium Services - ' . SITE_NAME;
                    
                    // Generate Invoice HTML
                    $invoice_html = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 8px;'>
                        <h2 style='color: #4CAF50; text-align: center;'>Welcome to Premium Services!</h2>
                        <p>Dear <strong>" . htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) . "</strong>,</p>
                        <p>Thank you for your payment. We are thrilled to welcome you! Your payment has been successfully approved by the administrator, and your requested services are now active.</p>
                        <hr style='border: 1px solid #eee; margin: 20px 0;'>
                        <h3 style='text-align: center; color: #333;'>INVOICE</h3>
                        <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f9f9f9; font-weight: bold;'>Transaction ID</td>
                                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($payment['transaction_id']) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f9f9f9; font-weight: bold;'>Payment Type</td>
                                <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($payment['payment_type']) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f9f9f9; font-weight: bold;'>Amount Paid</td>
                                <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; color: #d9534f;'>₹" . number_format($payment['amount'], 2) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f9f9f9; font-weight: bold;'>Date</td>
                                <td style='padding: 10px; border: 1px solid #ddd;'>" . date('d M, Y h:i A', strtotime($payment['created_at'])) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px; border: 1px solid #ddd; background: #f9f9f9; font-weight: bold;'>Status</td>
                                <td style='padding: 10px; border: 1px solid #ddd; color: #5cb85c; font-weight: bold;'>Success</td>
                            </tr>
                        </table>
                        <p style='margin-top: 20px; font-size: 14px; color: #555;'>If you have any questions, please contact our support team.</p>
                        <p style='margin-top: 10px; font-size: 14px;'>Best Regards,<br><strong>" . SITE_NAME . " Team</strong></p>
                    </div>";
                    
                    $mail->Body = $invoice_html;
                    $mail->AltBody = "Welcome to Premium Services!\n\nDear " . htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) . ",\nYour payment of ₹" . number_format($payment['amount'], 2) . " for " . htmlspecialchars($payment['payment_type']) . " (Txn ID: " . htmlspecialchars($payment['transaction_id']) . ") has been approved.\n\nThank you for your payment.\n" . SITE_NAME . " Team";
                    
                    $mail->send();
                }

                $pdo->commit();
                setFlashMessage('success', 'Payment Approved successfully. Invoice and welcome email sent.');
            } catch (\Exception $e) {
                $pdo->rollBack();
                setFlashMessage('error', 'Error approving payment: ' . $e->getMessage());
            }
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'Failed' WHERE id = ?");
            $stmt->execute([$payment_id]);
            setFlashMessage('success', 'Payment Rejected.');
        }
    }
    
    header("Location: payments.php");
    exit;
}

// Fetch Payments
$query = "SELECT p.*, u.first_name, u.last_name, u.email, u.phone 
          FROM payments p 
          JOIN users u ON p.user_id = u.id 
          ORDER BY p.created_at DESC";
$stmt = $pdo->query($query);
$payments = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3 class="mb-0 text-dark"><i class="fa-solid fa-indian-rupee-sign text-warning me-2"></i> Manage Payments</h3>
    </div>
    
    <?php displayFlashMessage(); ?>

    <div class="card border-0 shadow-sm p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Amount (₹)</th>
                        <th>Transaction ID (UTR)</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($payments)): ?>
                        <tr><td colspan="8" class="text-center py-4">No payments found.</td></tr>
                    <?php else: ?>
                        <?php foreach($payments as $p): ?>
                        <tr>
                            <td>#<?= $p['id'] ?></td>
                            <td>
                                <strong><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></strong><br>
                                <small class="text-muted"><?= htmlspecialchars($p['phone']) ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($p['payment_type']) ?></span></td>
                            <td>
                                <span class="badge bg-info text-dark"><?= htmlspecialchars($p['payment_method'] ?? 'manual') ?></span>
                                <?php if (!empty($p['gateway_payment_id'])): ?>
                                    <div class="small text-muted">#{<?= htmlspecialchars($p['gateway_payment_id']) ?>}</div>
                                <?php endif; ?>
                            </td>
                            <td>₹<?= number_format($p['amount'], 2) ?></td>
                            <td class="font-monospace"><?= htmlspecialchars($p['transaction_id']) ?></td>
                            <td><?= date('d M, Y h:i A', strtotime($p['created_at'])) ?></td>
                            <td>
                                <?php if($p['status'] === 'Pending'): ?>
                                    <span class="badge bg-warning text-dark">Pending Verification</span>
                                <?php elseif($p['status'] === 'Success'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($p['status'] === 'Pending'): ?>
                                    <a href="payments.php?action=approve&id=<?= $p['id'] ?>" class="btn btn-sm btn-success fw-bold" onclick="return confirm('Approve this payment and grant premium?');"><i class="fa-solid fa-check"></i> Approve</a>
                                    <a href="payments.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger fw-bold" onclick="return confirm('Reject this payment?');"><i class="fa-solid fa-xmark"></i> Reject</a>
                                <?php else: ?>
                                    <span class="text-muted small">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
