<?php
// includes/db.php
require_once __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Auto-seed default roles if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO roles (id, name) VALUES (1, 'Admin'), (2, 'Member'), (3, 'Business')");
    }
    
    // Auto-add address columns to matrimony_profiles if they don't exist
    try {
        $pdo->exec("ALTER TABLE matrimony_profiles ADD COLUMN full_address TEXT AFTER annual_income");
        $pdo->exec("ALTER TABLE matrimony_profiles ADD COLUMN state VARCHAR(100) AFTER full_address");
        $pdo->exec("ALTER TABLE matrimony_profiles ADD COLUMN district VARCHAR(100) AFTER state");
        $pdo->exec("ALTER TABLE matrimony_profiles ADD COLUMN block VARCHAR(100) AFTER district");
        $pdo->exec("ALTER TABLE matrimony_profiles ADD COLUMN full_photo VARCHAR(255) NULL AFTER privacy_only_verified_views");
    } catch (PDOException $e) {
        // Ignore error if columns already exist
    }

    // Add email verification flag for existing installations
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_verified BOOLEAN DEFAULT FALSE");
    } catch (PDOException $e) {
        // Ignore error if column already exists or users table missing during initial install
    }

    // Add declaration tracking to users table
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN declaration_accepted BOOLEAN DEFAULT FALSE");
        $pdo->exec("ALTER TABLE users ADD COLUMN declaration_datetime DATETIME NULL");
        $pdo->exec("ALTER TABLE users ADD COLUMN declaration_ip VARCHAR(45) NULL");
    } catch (PDOException $e) {
        // Ignore error if columns already exist
    }

    // Password Resets Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            token VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Payment gateway support for Razorpay and legacy manual payments
    try {
        $pdo->exec("ALTER TABLE payments ADD COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'manual'");
        $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_order_id VARCHAR(100) NULL");
        $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_payment_id VARCHAR(100) NULL");
        $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_signature VARCHAR(255) NULL");
        $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_payload TEXT NULL");
        $pdo->exec("ALTER TABLE payments ADD COLUMN gateway_status VARCHAR(50) NULL");
    } catch (PDOException $e) {
        // Ignore if columns already exist
    }

    // Email OTP Verification Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            otp VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // CMS Pages Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Seed default CMS pages
    $stmt = $pdo->query("SELECT COUNT(*) FROM pages");
    if ($stmt->fetchColumn() == 0) {
        $default_about = "<h2>Welcome to Vishwakarma Samaj</h2><p>We are dedicated to uniting the Vishwakarma community globally...</p>";
        $default_terms = "<h2>Terms & Conditions</h2><p>By using this portal, you agree to our community guidelines...</p>";
        $default_header = "<marquee>Welcome to the official digital portal of the Vishwakarma Samaj! Register now to connect with the community.</marquee>";
        $default_footer = "Copyright &copy; 2026 Vishwakarma Samaj. All rights reserved.";
        
        $stmt = $pdo->prepare("INSERT INTO pages (slug, title, content) VALUES (?, ?, ?), (?, ?, ?), (?, ?, ?), (?, ?, ?)");
        $stmt->execute([
            'about', 'About Us', $default_about,
            'terms', 'Terms & Conditions', $default_terms,
            'header', 'Header Announcement', $default_header,
            'footer', 'Footer Content', $default_footer
        ]);
    }

    // User Gallery Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            status ENUM('pending', 'approved') DEFAULT 'approved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Site Visitors Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_visitors (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            visit_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_visit (ip_address, visit_date)
        )
    ");

    // Blogs Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blogs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            status ENUM('pending', 'approved') DEFAULT 'approved',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Blog Comments Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blog_id INT NOT NULL,
            user_id INT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Blog Ratings Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS blog_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blog_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_blog (blog_id, user_id),
            FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Contact Messages Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('unread', 'read') DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");

    // Website Quotations Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS website_quotations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            company VARCHAR(150),
            website_type VARCHAR(100),
            budget VARCHAR(100),
            domain_hosting VARCHAR(100),
            features TEXT,
            reference_urls TEXT,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // UI Images Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ui_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_key VARCHAR(100) NOT NULL UNIQUE,
            page_name VARCHAR(100) NOT NULL,
            title VARCHAR(150) NOT NULL,
            upload_path VARCHAR(255) NULL,
            external_url TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Seed default UI images
    $stmt = $pdo->query("SELECT COUNT(*) FROM ui_images");
    if ($stmt->fetchColumn() == 0) {
        $default_ui_images = [
            ['about_vision', 'About Us', 'Our Vision Image', null, 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?q=80&w=800&auto=format&fit=crop'],
            ['about_mission', 'About Us', 'Our Mission Image', null, 'https://images.unsplash.com/photo-1552664730-d307ca884978?q=80&w=800&auto=format&fit=crop'],
            ['about_community', 'About Us', 'Our Community Image', null, 'https://images.unsplash.com/photo-1529156069898-49953eb1b5b4?q=80&w=800&auto=format&fit=crop'],
            ['about_core_unity', 'About Us', 'Core Value: Unity', null, 'https://images.unsplash.com/photo-1582213782179-e0d53f98f2ca?q=80&w=200&auto=format&fit=crop'],
            ['about_core_education', 'About Us', 'Core Value: Education', null, 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=200&auto=format&fit=crop'],
            ['about_core_heritage', 'About Us', 'Core Value: Heritage', null, 'https://images.unsplash.com/photo-1561016444-14f7474f4d45?q=80&w=200&auto=format&fit=crop'],
            ['about_core_support', 'About Us', 'Core Value: Support', null, 'https://images.unsplash.com/photo-1526948531399-320e7e40f0ca?q=80&w=200&auto=format&fit=crop'],
            
            // Home Page
            ['home_hero', 'Home Page', 'Main Hero Slider/Banner Image', null, 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=1920&auto=format&fit=crop'],
            ['home_hero_2', 'Home Page', 'Home Page - Hero Slide 2', null, 'https://images.unsplash.com/photo-1600010996160-c447bc981249?q=80&w=1920&auto=format&fit=crop'],
            ['home_hero_3', 'Home Page', 'Home Page - Hero Slide 3', null, 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?q=80&w=1920&auto=format&fit=crop'],
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
            ['banner_matrimony', 'Matrimony', 'Matrimony Banner', null, 'https://images.unsplash.com/photo-1511285560929-80b456fea0bc?q=80&w=1920&auto=format&fit=crop'],
            ['banner_education', 'Education', 'Education Banner', null, 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=1920&auto=format&fit=crop'],
            ['banner_jobs', 'Jobs', 'Jobs Portal Banner', null, 'https://images.unsplash.com/photo-1521791136064-7986c2920216?q=80&w=1920&auto=format&fit=crop'],
        
            // Static images
            ['gallery_static_1', 'Gallery', 'Static Gallery Image 1', null, 'https://images.unsplash.com/photo-1590050752117-238cb0fb12b1?q=80&w=400&auto=format&fit=crop'],
            ['gallery_static_2', 'Gallery', 'Static Gallery Image 2', null, 'https://images.unsplash.com/photo-1600010996160-c447bc981249?q=80&w=400&auto=format&fit=crop'],
            ['gallery_static_3', 'Gallery', 'Static Gallery Image 3', null, 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?q=80&w=400&auto=format&fit=crop'],
            ['web_services_placeholder', 'Web Services', 'Web Development Image', null, 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=600&auto=format&fit=crop']
        ];
        $insert_stmt = $pdo->prepare("INSERT INTO ui_images (image_key, page_name, title, upload_path, external_url) VALUES (?, ?, ?, ?, ?)");
        foreach ($default_ui_images as $img) {
            $insert_stmt->execute($img);
        }
    }

} catch (PDOException $e) {
    include_once __DIR__ . '/../maintenance.php';
    exit();
}

/**
 * Fetch a UI image path (either uploaded or external URL).
 */
function getUiImage($image_key, $default_url = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT upload_path, external_url FROM ui_images WHERE image_key = ?");
        $stmt->execute([$image_key]);
        $row = $stmt->fetch();
        if ($row) {
            if (!empty($row['upload_path'])) {
                return BASE_URL . 'uploads/ui/' . $row['upload_path'];
            } elseif (!empty($row['external_url'])) {
                return $row['external_url'];
            }
        }
    } catch (PDOException $e) {
        // Fallback silently
    }
    return $default_url;
}
?>
