<?php
$page_title = "Discussions";
require_once 'includes/db.php';
require_once 'includes/session.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Security Check: Must be a premium member
$stmt = $pdo->prepare("SELECT is_premium FROM matrimony_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_profile = $stmt->fetch();

// Fetch Matrimony Settings
$settings_stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'is_matrimony_paid'");
$is_paid_mode = $settings_stmt->fetchColumn() == '1';

if ($is_paid_mode && (!$my_profile || !$my_profile['is_premium'])) {
    setFlashMessage('error', 'Discussions are exclusively for Premium Members. Please upgrade first.');
    header("Location: upgrade.php");
    exit;
}

$target_user_id = $_GET['user_id'] ?? ($_POST['receiver_id'] ?? null);
$error = '';
$is_blocked = false;

if ($target_user_id) {
    // Check for block
    $block_stmt = $pdo->prepare("SELECT id FROM user_blocks WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
    $block_stmt->execute([$user_id, $target_user_id, $target_user_id, $user_id]);
    if ($block_stmt->fetch()) {
        $is_blocked = true;
    }
}

// Handle Message Send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'], $_POST['receiver_id'])) {
    $message = trim($_POST['message']);
    $receiver_id = $_POST['receiver_id'];
    
    if ($is_blocked) {
        setFlashMessage('error', 'You cannot send messages to this user.');
        header("Location: discussion.php?user_id=" . $receiver_id);
        exit;
    }
    
    if (!empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $receiver_id, $message]);
        
        header("Location: discussion.php?user_id=" . $receiver_id);
        exit;
    }
}

// Handle Message Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message_id'])) {
    $del_id = $_POST['delete_message_id'];
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND sender_id = ?");
    $stmt->execute([$del_id, $user_id]);
    header("Location: discussion.php?user_id=" . $target_user_id);
    exit;
}

// Fetch list of users you have chatted with
$chat_users_query = "
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.last_active, p.profile_pic 
    FROM users u
    LEFT JOIN member_profiles p ON u.id = p.user_id
    JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id)
    WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ?
";
$stmt = $pdo->prepare($chat_users_query);
$stmt->execute([$user_id, $user_id, $user_id]);
$chat_history_users = $stmt->fetchAll();

// If target user is set but not in history, fetch their info to show in the UI
if ($target_user_id) {
    $found = false;
    foreach ($chat_history_users as $cu) {
        if ($cu['id'] == $target_user_id) $found = true;
    }
    if (!$found) {
        $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.last_active, p.profile_pic FROM users u LEFT JOIN member_profiles p ON u.id = p.user_id WHERE u.id = ?");
        $stmt->execute([$target_user_id]);
        $new_user = $stmt->fetch();
        if ($new_user) {
            array_unshift($chat_history_users, $new_user); // Add to top of list
        }
    }
}

// Fetch messages if a target user is selected
$messages = [];
$target_user_info = null;
if ($target_user_id) {
    $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.last_active, p.profile_pic FROM users u LEFT JOIN member_profiles p ON u.id = p.user_id WHERE u.id = ?");
    $stmt->execute([$target_user_id]);
    $target_user_info = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$user_id, $target_user_id, $target_user_id, $user_id]);
    $messages = $stmt->fetchAll();
    
    // Mark as read
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?");
    $stmt->execute([$user_id, $target_user_id]);
}

require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-4">
    <div class="row bg-white rounded shadow-sm border overflow-hidden" style="height: 75vh;">
        <!-- Users List Sidebar -->
        <div class="col-md-4 col-12 border-end p-0 d-flex flex-column <?= ($target_user_id) ? 'd-none d-md-flex' : '' ?>" style="height: 100%;">
            <div class="bg-light p-3 border-bottom text-center">
                <h5 class="fw-bold mb-0 text-warning"><i class="fa-solid fa-comments me-2"></i> Discussions</h5>
            </div>
            <div class="overflow-auto flex-grow-1">
                <?php if (empty($chat_history_users)): ?>
                    <div class="p-4 text-center text-muted small">No active discussions. Go to Matrimony and find a match!</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($chat_history_users as $cu): 
                            $pic = $cu['profile_pic'] ? BASE_URL . "uploads/profile/" . $cu['profile_pic'] : "https://ui-avatars.com/api/?name=User&background=random";
                            $active = ($target_user_id == $cu['id']) ? 'bg-warning text-dark bg-opacity-25' : '';
                        ?>
                            <a href="discussion.php?user_id=<?= $cu['id'] ?>" class="list-group-item list-group-item-action <?= $active ?> py-3 position-relative">
                                <div class="d-flex align-items-center">
                                    <div class="position-relative">
                                        <img src="<?= htmlspecialchars($pic) ?>" class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit: cover;">
                                        <?php if(isUserOnline($cu['last_active'] ?? null)): ?>
                                            <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-light rounded-circle translate-middle" style="margin-bottom: 5px; margin-right: 10px;">
                                                <span class="visually-hidden">Online</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($cu['first_name'] . ' ' . $cu['last_name']) ?></h6>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat Area -->
        <div class="col-md-8 col-12 p-0 d-flex flex-column <?= (!$target_user_id) ? 'd-none d-md-flex' : '' ?>" style="height: 100%;">
            <?php if ($target_user_id && $target_user_info): 
                $pic = $target_user_info['profile_pic'] ? BASE_URL . "uploads/profile/" . $target_user_info['profile_pic'] : "https://ui-avatars.com/api/?name=User&background=random";
            ?>
                <!-- Chat Header -->
                <div class="bg-white p-3 border-bottom d-flex align-items-center shadow-sm z-1">
                    <a href="discussion.php" class="d-md-none text-dark me-3"><i class="fa-solid fa-arrow-left"></i></a>
                    <img src="<?= htmlspecialchars($pic) ?>" class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit: cover;">
                    <div>
                        <h5 class="mb-0 fw-bold"><?= htmlspecialchars($target_user_info['first_name'] . ' ' . $target_user_info['last_name']) ?></h5>
                        <?php if(isUserOnline($target_user_info['last_active'] ?? null)): ?>
                            <small class="text-success fw-bold"><i class="fa-solid fa-circle fa-2xs me-1"></i> Online</small>
                        <?php else: ?>
                            <small class="text-muted">Offline</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Messages -->
                <div class="flex-grow-1 p-4 overflow-auto bg-light" id="chat-box">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-muted my-5">
                            <i class="fa-solid fa-hand-wave fa-3x mb-3 text-warning"></i>
                            <p>Send a message to start the discussion!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($messages as $msg): 
                            $is_me = ($msg['sender_id'] == $user_id);
                        ?>
                            <div class="d-flex flex-column mb-3 <?= $is_me ? 'align-items-end' : 'align-items-start' ?>">
                                <div class="d-flex align-items-center gap-2 <?= $is_me ? 'flex-row-reverse' : '' ?>">
                                    <div class="p-3 rounded shadow-sm <?= $is_me ? 'bg-warning text-dark' : 'bg-white text-dark border' ?>" style="max-width: 100%; border-radius: 15px;">
                                        <?= htmlspecialchars($msg['message']) ?>
                                    </div>
                                    <?php if ($is_me): ?>
                                        <form method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete this message?');">
                                            <input type="hidden" name="delete_message_id" value="<?= $msg['id'] ?>">
                                            <button type="submit" class="btn btn-sm text-danger border-0 bg-transparent p-1" title="Delete Message"><i class="fa-solid fa-trash-can"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted mt-1" style="font-size: 0.7rem;">
                                    <?= date('h:i A', strtotime($msg['created_at'])) ?>
                                    <?php if ($is_me): ?>
                                        <i class="fa-solid fa-check-double ms-1 <?= $msg['is_read'] ? 'text-primary' : 'text-muted' ?>"></i>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Chat Input Form -->
                <div class="bg-white p-3 border-top">
                    <?php if ($is_blocked): ?>
                        <div class="alert alert-secondary text-center mb-0 border border-secondary border-opacity-50">
                            <i class="fa-solid fa-ban fa-lg text-danger me-2"></i>
                            <strong>You cannot reply to this conversation. A block is active.</strong>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($target_user_id) ?>">
                            <input type="text" name="message" class="form-control form-control-lg rounded-pill px-4" placeholder="Type a message..." required autocomplete="off">
                            <button type="submit" class="btn btn-warning rounded-circle px-3"><i class="fa-solid fa-paper-plane"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <script>
                    // Auto scroll to bottom
                    var chatBox = document.getElementById('chat-box');
                    chatBox.scrollTop = chatBox.scrollHeight;
                </script>

            <?php else: ?>
                <!-- Placeholder when no user selected -->
                <div class="h-100 d-flex flex-column align-items-center justify-content-center bg-light text-muted">
                    <i class="fa-solid fa-comments fa-4x mb-3 text-secondary"></i>
                    <h4>Select a profile to start discussing</h4>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
