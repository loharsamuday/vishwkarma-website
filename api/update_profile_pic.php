<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

$upload_dir = '../uploads/profile/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Ensure user has a member_profiles row
$stmt = $pdo->prepare("SELECT id, profile_pic FROM member_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) {
    $pdo->prepare("INSERT INTO member_profiles (user_id) VALUES (?)")->execute([$user_id]);
    $profile = ['profile_pic' => null];
}

if ($action === 'delete') {
    if (!empty($profile['profile_pic'])) {
        $old_file = $upload_dir . $profile['profile_pic'];
        if (file_exists($old_file)) {
            unlink($old_file);
        }
        $pdo->prepare("UPDATE member_profiles SET profile_pic = NULL WHERE user_id = ?")->execute([$user_id]);
        setFlashMessage('success', 'Profile picture removed successfully.');
    }
    header("Location: ../dashboard.php");
    exit;
}

if ($action === 'upload' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_pic'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($file_info, $file['tmp_name']);
    finfo_close($file_info);
    
    if (in_array($file_type, $allowed_types)) {
        $ext = 'webp';
        $new_filename = uniqid('profile_') . '_' . $user_id . '.' . $ext;
        $destination = $upload_dir . $new_filename;
        
        $image = null;
        switch ($file_type) {
            case 'image/jpeg': $image = imagecreatefromjpeg($file['tmp_name']); break;
            case 'image/png': 
                $image = imagecreatefrompng($file['tmp_name']); 
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'image/webp': $image = imagecreatefromwebp($file['tmp_name']); break;
            case 'image/avif': 
                if(function_exists('imagecreatefromavif')) {
                    $image = imagecreatefromavif($file['tmp_name']); 
                }
                break;
        }
        
        if ($image !== null) {
            // Delete old file if exists
            if (!empty($profile['profile_pic'])) {
                $old_file = $upload_dir . $profile['profile_pic'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            // Convert and save
            imagewebp($image, $destination, 80);
            imagedestroy($image);
            
            // Update database
            $pdo->prepare("UPDATE member_profiles SET profile_pic = ? WHERE user_id = ?")->execute([$new_filename, $user_id]);
            setFlashMessage('success', 'Profile picture updated successfully.');
        } else {
            setFlashMessage('danger', 'Error processing the image.');
        }
    } else {
        setFlashMessage('danger', 'Invalid file type. Only JPG, PNG, WEBP, and AVIF are allowed.');
    }
} else {
    setFlashMessage('danger', 'Please select a valid image to upload.');
}

header("Location: ../dashboard.php");
exit;
?>
