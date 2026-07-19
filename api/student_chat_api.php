<?php
// api/student_chat_api.php
require_once '../includes/db.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Check if user is a student and get their class
$stmt = $pdo->prepare("SELECT class_name FROM student_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'You do not have a student profile.']);
    exit;
}

$class_name = $student['class_name'];

if ($action === 'send') {
    $message = trim($_POST['message'] ?? '');
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message is empty.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO student_group_chats (sender_id, class_name, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $class_name, $message]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
    exit;

} elseif ($action === 'fetch') {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    try {
        $sql = "SELECT c.id, c.message, c.created_at, u.first_name, u.last_name, c.sender_id, 
                COALESCE(mp.profile_pic, '') as profile_pic
                FROM student_group_chats c
                JOIN users u ON c.sender_id = u.id
                LEFT JOIN member_profiles mp ON u.id = mp.user_id
                WHERE c.class_name = ? ";
        
        $params = [$class_name];
        
        if ($last_id > 0) {
            $sql .= " AND c.id > ? ";
            $params[] = $last_id;
        }
        
        $sql .= " ORDER BY c.id ASC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formatted_messages = [];
        foreach($messages as $m) {
            $pic = empty($m['profile_pic']) ? "https://placehold.co/40x40/007bff/white?text=" . strtoupper(substr($m['first_name'], 0, 1)) : "../uploads/profile/" . $m['profile_pic'];
            $is_mine = ($m['sender_id'] == $user_id);
            $formatted_messages[] = [
                'id' => $m['id'],
                'text' => htmlspecialchars($m['message']),
                'time' => date('h:i A', strtotime($m['created_at'])),
                'name' => htmlspecialchars($m['first_name'] . ' ' . $m['last_name']),
                'pic' => $pic,
                'is_mine' => $is_mine
            ];
        }
        
        echo json_encode(['success' => true, 'messages' => $formatted_messages]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action.']);
}
?>
