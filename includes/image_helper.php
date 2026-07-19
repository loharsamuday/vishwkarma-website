<?php
// includes/image_helper.php

/**
 * Handle image upload, convert to WebP, and set SEO friendly filename
 * @param array $file $_FILES['image']
 * @param string $first_name
 * @param string $last_name
 * @param string $city
 * @param string $upload_dir Path to upload directory (e.g., 'uploads/profiles/')
 * @return string|false Filename on success, false on failure
 */
function upload_and_convert_to_webp($file, $first_name, $last_name, $city, $upload_dir) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $tmp_name = $file['tmp_name'];
    $mime_type = mime_content_type($tmp_name);
    
    // Check if it's a valid image
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $allowed_mimes)) {
        return false;
    }

    // Create image resource based on mime type
    $image = null;
    switch ($mime_type) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($tmp_name);
            break;
        case 'image/png':
            $image = imagecreatefrompng($tmp_name);
            // Handle transparency for PNG
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($tmp_name);
            break;
    }

    if (!$image) {
        return false;
    }

    // Generate SEO friendly filename: rahul-vishwakarma-patna-[uniqid].webp
    $base_name = strtolower(trim($first_name . ' ' . $last_name . ' vishwakarma ' . $city));
    $base_name = preg_replace('/[^a-z0-9-]+/', '-', $base_name);
    $base_name = trim($base_name, '-');
    $uniq = substr(uniqid(), -5);
    $new_filename = $base_name . '-' . $uniq . '.webp';
    $destination = rtrim($upload_dir, '/') . '/' . $new_filename;

    // Convert and save as WebP
    // 80 is the quality (0-100)
    $success = imagewebp($image, $destination, 80);
    
    // Free up memory
    imagedestroy($image);

    if ($success) {
        return $new_filename;
    }
    
    return false;
}
?>
