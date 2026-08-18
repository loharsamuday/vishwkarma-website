<?php
// config/database.php

// Check if running on localhost
$is_localhost = ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1');

if ($is_localhost) {
    // -----------------------------------------
    // LOCALHOST CONFIGURATION (XAMPP)
    // -----------------------------------------
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $host = 'localhost';
    $db_name = 'english_learning';
    $username = 'root';
    $password = '';
} else {
    // -----------------------------------------
    // LIVE SERVER CONFIGURATION (InfinityFree)
    // -----------------------------------------
    // Temporarily keeping errors ON for debugging your current issue. 
    // Once it works, change to error_reporting(0) and ini_set('display_errors', 0)
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    $host = 'sql112.infinityfree.com';
    $db_name = 'if0_42277227_vishwkarma';
    $username = 'if0_42277227';
    $password = 'LiAc40aALrDAS';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ==========================================
    // AUTO CREATE TABLES (If they don't exist)
    // ==========================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            mobile VARCHAR(20) NULL,
            status ENUM('active', 'blocked') DEFAULT 'active',
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS stories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author_id INT NOT NULL,
            category_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            short_description TEXT,
            content TEXT NOT NULL,
            difficulty ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL,
            reading_time INT DEFAULT 5,
            status ENUM('Draft', 'Published') DEFAULT 'Published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS vocabulary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NOT NULL,
            word VARCHAR(100) NOT NULL,
            hindi_meaning VARCHAR(255) NOT NULL,
            synonyms VARCHAR(255),
            antonyms VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS site_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ==========================================
    // AUTO INSERT DEFAULT DATA (If empty)
    // ==========================================
    try {
        // Create default user if empty
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt && $stmt->fetchColumn() == 0) {
            $user_pass = password_hash('password123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO users (name, email, password) VALUES ('Test User', 'user@example.com', '$user_pass')");
            
            // Create default categories
            $pdo->exec("INSERT INTO categories (name) VALUES ('Moral Stories'), ('Fairy Tales'), ('Adventure'), ('Funny Stories')");
            
            // Create default site settings
            $pdo->exec("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES 
                ('contact_email', 'support@englishstories.com'),
                ('contact_phone', '+91 98765 43210'),
                ('contact_whatsapp', '919876543210'),
                ('social_facebook', '#'),
                ('social_twitter', '#'),
                ('social_instagram', '#'),
                ('social_youtube', '#'),
                ('smtp_host', 'smtp.gmail.com'),
                ('smtp_port', '587'),
                ('smtp_user', ''),
                ('smtp_pass', '')");
        }
        
        // Create default admin if empty
        $stmt_admin = $pdo->query("SELECT COUNT(*) FROM admins");
        if ($stmt_admin && $stmt_admin->fetchColumn() == 0) {
            $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO admins (username, password) VALUES ('admin', '$admin_pass')");
        }
    } catch(PDOException $e) {
        // Ignore errors during initial check
    }
    
} catch(PDOException $e) {
    if ($is_localhost) {
        die("Database Connection failed: " . $e->getMessage());
    } else {
        // Show error on live server temporarily for debugging
        die("Live Database Connection failed: " . $e->getMessage() . "<br><br>Please check your InfinityFree database credentials.");
    }
}
?>
