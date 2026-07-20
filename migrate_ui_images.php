<?php
require_once 'includes/db.php';

$new_images = [
    // Home Page
    ['home_hero', 'Home Page', 'Main Hero Slider/Banner Image', null, 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=1920&auto=format&fit=crop'],
    ['home_about', 'Home Page', 'About Section Image', null, 'https://images.unsplash.com/photo-1566804860762-23c31671f76e?q=80&w=800&auto=format&fit=crop'],
    ['home_testimonial_1', 'Home Page', 'Testimonial Avatar 1', null, 'https://ui-avatars.com/api/?name=Rajesh+S&background=random'],
    ['home_testimonial_2', 'Home Page', 'Testimonial Avatar 2', null, 'https://ui-avatars.com/api/?name=Amit+V&background=random'],
    ['home_testimonial_3', 'Home Page', 'Testimonial Avatar 3', null, 'https://ui-avatars.com/api/?name=Sneha+P&background=random'],
    
    // Banners
    ['banner_community', 'Community', 'Community Directory Banner', null, 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?q=80&w=1920&auto=format&fit=crop'],
    ['banner_contact', 'Contact Us', 'Contact Page Banner', null, 'https://images.unsplash.com/photo-1516387938699-a93567ec168e?q=80&w=1920&auto=format&fit=crop'],
    ['banner_events', 'Events', 'Events Page Banner', null, 'https://images.unsplash.com/photo-1511578314322-379afb476865?q=80&w=1920&auto=format&fit=crop'],
    ['banner_gallery', 'Gallery', 'Gallery Page Banner', null, 'https://images.unsplash.com/photo-1452587925148-ce544e77e70d?q=80&w=1920&auto=format&fit=crop'],
    ['banner_web_services', 'Web Services', 'Web Services Banner', null, 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?q=80&w=1920&auto=format&fit=crop'],
    ['banner_blogs', 'Blogs', 'Blogs Page Banner', null, 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?q=80&w=1920&auto=format&fit=crop'],
    ['banner_blood_bank', 'Blood Bank', 'Blood Bank Banner', null, 'https://images.unsplash.com/photo-1615461066841-6116e61058f4?q=80&w=1920&auto=format&fit=crop'],
    ['banner_business', 'Business', 'Business Directory Banner', null, 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=1920&auto=format&fit=crop'],
    ['banner_matrimony', 'Matrimony', 'Matrimony Banner', null, 'https://placehold.co/1920x400/e84393/ffffff?text=Matrimony'],
    ['banner_education', 'Education', 'Education Banner', null, 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=1920&auto=format&fit=crop'],
    ['banner_jobs', 'Jobs', 'Jobs Portal Banner', null, 'https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=1920&auto=format&fit=crop'],

    // Static images
    ['gallery_static_1', 'Gallery', 'Static Gallery Image 1', null, 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=400&auto=format&fit=crop'],
    ['gallery_static_2', 'Gallery', 'Static Gallery Image 2', null, 'https://images.unsplash.com/photo-1600010996160-c447bc981249?q=80&w=400&auto=format&fit=crop'],
    ['gallery_static_3', 'Gallery', 'Static Gallery Image 3', null, 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?q=80&w=400&auto=format&fit=crop'],
    ['web_services_placeholder', 'Web Services', 'Web Development Image', null, 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=600&auto=format&fit=crop']
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
