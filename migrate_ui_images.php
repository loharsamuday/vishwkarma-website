<?php
require_once 'includes/db.php';

$new_images = [
    // Home Page
    ['home_hero', 'Home Page', 'Main Hero Slider/Banner Image', null, 'https://placehold.co/1920x800/2c3e50/f39c12?text=Welcome+to+Vishwakarma+Samaj'],
    ['home_about', 'Home Page', 'About Section Image', null, 'https://placehold.co/800x600/f8f9fa/ffc107?text=Vishwakarma+Samaj'],
    ['home_testimonial_1', 'Home Page', 'Testimonial Avatar 1', null, 'https://ui-avatars.com/api/?name=Rajesh+S&background=random'],
    ['home_testimonial_2', 'Home Page', 'Testimonial Avatar 2', null, 'https://ui-avatars.com/api/?name=Amit+V&background=random'],
    ['home_testimonial_3', 'Home Page', 'Testimonial Avatar 3', null, 'https://ui-avatars.com/api/?name=Sneha+P&background=random'],
    
    // Banners
    ['banner_community', 'Community', 'Community Directory Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Directory'],
    ['banner_contact', 'Contact Us', 'Contact Page Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Contact+Us'],
    ['banner_events', 'Events', 'Events Page Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Events'],
    ['banner_gallery', 'Gallery', 'Gallery Page Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Image+Gallery'],
    ['banner_web_services', 'Web Services', 'Web Services Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=IT+%26+Web+Services'],
    ['banner_blogs', 'Blogs', 'Blogs Page Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Community+Blogs'],
    ['banner_blood_bank', 'Blood Bank', 'Blood Bank Banner', null, 'https://placehold.co/1920x400/e74c3c/ffffff?text=Blood+Bank'],
    ['banner_business', 'Business', 'Business Directory Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Business+Directory'],
    ['banner_matrimony', 'Matrimony', 'Matrimony Banner', null, 'https://placehold.co/1920x400/e84393/ffffff?text=Matrimony'],
    ['banner_education', 'Education', 'Education Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Education+%26+Career'],
    ['banner_jobs', 'Jobs', 'Jobs Portal Banner', null, 'https://placehold.co/1920x400/2c3e50/f39c12?text=Jobs+Portal'],

    // Static images
    ['gallery_static_1', 'Gallery', 'Static Gallery Image 1', null, 'https://placehold.co/400x300/f39c12/white?text=Vishwakarma+Puja'],
    ['gallery_static_2', 'Gallery', 'Static Gallery Image 2', null, 'https://placehold.co/400x300/2c3e50/white?text=Devotion'],
    ['gallery_static_3', 'Gallery', 'Static Gallery Image 3', null, 'https://placehold.co/400x300/e67e22/white?text=Celebration'],
    ['web_services_placeholder', 'Web Services', 'Web Development Image', null, 'https://placehold.co/600x500/2c3e50/f39c12?text=Web+Development']
];

$insert_stmt = $pdo->prepare("INSERT IGNORE INTO ui_images (image_key, page_name, title, upload_path, external_url) VALUES (?, ?, ?, ?, ?)");

$added = 0;
foreach ($new_images as $img) {
    $insert_stmt->execute($img);
    if ($insert_stmt->rowCount() > 0) {
        $added++;
    }
}

echo "Added $added new UI images successfully.\n";
?>
