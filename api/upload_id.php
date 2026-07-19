<?php
// api/upload_id.php
require_once '../includes/db.php';
require_once '../includes/session.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['id_document'])) {
    $user_id = $_SESSION['user_id'];
    $file = $_FILES['id_document'];
    
    // Validate file
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlashMessage('error', 'Error uploading file. Please try again.');
        header('Location: ../dashboard.php');
        exit;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        setFlashMessage('error', 'Invalid file type. Only JPG, PNG, and PDF are allowed.');
        header('Location: ../dashboard.php');
        exit;
    }
    
    if ($file['size'] > $max_size) {
        setFlashMessage('error', 'File size exceeds 5MB limit.');
        header('Location: ../dashboard.php');
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/id_documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'id_' . $user_id . '_' . time() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $stmt = $pdo->prepare("UPDATE users SET id_document = ?, id_status = 'pending' WHERE id = ?");
        $stmt->execute([$filename, $user_id]);
        
        setFlashMessage('success', 'Your ID document has been submitted and is pending verification.');
    } else {
        setFlashMessage('error', 'Failed to save the uploaded file.');
    }
}

header('Location: ../dashboard.php');
exit;
?>
