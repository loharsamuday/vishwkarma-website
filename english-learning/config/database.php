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

    // TEMPORARY FIX: If URL has ?reset_db=1, drop all tables to fix schema mismatch
    if (isset($_GET['reset_db']) && $_GET['reset_db'] == '1') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $tables = ['users', 'categories', 'stories', 'vocabulary', 'site_settings', 'admins', 'user_stories', 'subscribers', 'student_tasks', 'student_routines', 'student_goals', 'student_focus_sessions', 'student_daily_stats'];
        foreach($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
        die("Database Reset Successful! All old tables dropped. Now remove ?reset_db=1 from URL and refresh to auto-create them.");
    }

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
            slug VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS stories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id INT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            short_description TEXT,
            content LONGTEXT NOT NULL,
            difficulty ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL DEFAULT 'Beginner',
            reading_time INT DEFAULT 5,
            featured_image VARCHAR(255) NULL,
            status ENUM('Draft', 'Published') DEFAULT 'Draft',
            seo_title VARCHAR(255) NULL,
            seo_description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS vocabulary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            story_id INT NULL,
            word VARCHAR(100) NOT NULL,
            part_of_speech VARCHAR(50) NULL,
            hindi_meaning VARCHAR(255) NULL,
            english_meaning TEXT NULL,
            synonym VARCHAR(255) NULL,
            antonym VARCHAR(255) NULL,
            example_sentence TEXT NULL,
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
        
        CREATE TABLE IF NOT EXISTS user_stories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            author_name VARCHAR(100) NOT NULL,
            email VARCHAR(100),
            title VARCHAR(255) NOT NULL,
            category_id INT,
            difficulty ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL DEFAULT 'Beginner',
            content TEXT NOT NULL,
            status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(255),
            subject VARCHAR(100),
            category VARCHAR(50),
            priority ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
            estimated_minutes INT DEFAULT 30,
            goal_id INT NULL,
            status ENUM('Pending', 'Completed') DEFAULT 'Pending',
            task_date DATE,
            completed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_routines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            title VARCHAR(100),
            category VARCHAR(50),
            start_time TIME,
            end_time TIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            goal_name VARCHAR(255),
            current_value INT DEFAULT 0,
            target_value INT,
            target_date DATE,
            status ENUM('Active', 'Completed') DEFAULT 'Active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_focus_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            task_id INT NULL,
            duration_minutes INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_daily_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            current_streak INT DEFAULT 0,
            longest_streak INT DEFAULT 0,
            last_activity_date DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_daily_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            review_date DATE NOT NULL,
            study_minutes INT DEFAULT 0,
            tasks_completed INT DEFAULT 0,
            tasks_total INT DEFAULT 0,
            productivity_score INT DEFAULT 0,
            learning_note TEXT,
            tomorrow_priority TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ==========================================
    // AUTO FIX SCHEMA (Add missing columns to existing tables)
    // ==========================================
    try {
        // Fix stories table
        $columns = $pdo->query("SHOW COLUMNS FROM stories")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('slug', $columns)) {
            $pdo->exec("ALTER TABLE stories ADD COLUMN slug VARCHAR(255) NULL AFTER title");
            $pdo->exec("ALTER TABLE stories ADD UNIQUE KEY `slug` (`slug`)");
        }
        if (!in_array('featured_image', $columns)) {
            $pdo->exec("ALTER TABLE stories ADD COLUMN featured_image VARCHAR(255) NULL AFTER reading_time");
        }
        if (!in_array('seo_title', $columns)) {
            $pdo->exec("ALTER TABLE stories ADD COLUMN seo_title VARCHAR(255) NULL AFTER status");
        }
        if (!in_array('seo_description', $columns)) {
            $pdo->exec("ALTER TABLE stories ADD COLUMN seo_description TEXT NULL AFTER seo_title");
        }
        if (in_array('author_id', $columns)) {
            try { $pdo->exec("ALTER TABLE stories DROP FOREIGN KEY stories_ibfk_1"); } catch(PDOException $e) {}
            try { $pdo->exec("ALTER TABLE stories DROP COLUMN author_id"); } catch(PDOException $e) {}
        }

        // Fix vocabulary table
        $vocab_cols = $pdo->query("SHOW COLUMNS FROM vocabulary")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('part_of_speech', $vocab_cols)) {
            $pdo->exec("ALTER TABLE vocabulary ADD COLUMN part_of_speech VARCHAR(50) NULL AFTER word");
        }
        if (!in_array('english_meaning', $vocab_cols)) {
            $pdo->exec("ALTER TABLE vocabulary ADD COLUMN english_meaning TEXT NULL AFTER hindi_meaning");
        }
        if (!in_array('example_sentence', $vocab_cols)) {
            $pdo->exec("ALTER TABLE vocabulary ADD COLUMN example_sentence TEXT NULL");
        }
        
        // Rename synonyms to synonym
        if (in_array('synonyms', $vocab_cols) && !in_array('synonym', $vocab_cols)) {
            $pdo->exec("ALTER TABLE vocabulary CHANGE synonyms synonym VARCHAR(255) NULL");
        } elseif (!in_array('synonyms', $vocab_cols) && !in_array('synonym', $vocab_cols)) {
            $pdo->exec("ALTER TABLE vocabulary ADD COLUMN synonym VARCHAR(255) NULL");
        }

        // Rename antonyms to antonym
        if (in_array('antonyms', $vocab_cols) && !in_array('antonym', $vocab_cols)) {
            $pdo->exec("ALTER TABLE vocabulary CHANGE antonyms antonym VARCHAR(255) NULL");
        } elseif (!in_array('antonyms', $vocab_cols) && !in_array('antonym', $vocab_cols)) {
            $pdo->exec("ALTER TABLE vocabulary ADD COLUMN antonym VARCHAR(255) NULL");
        }

        // Make story_id and hindi_meaning nullable
        try { $pdo->exec("ALTER TABLE vocabulary MODIFY story_id INT NULL"); } catch(PDOException $e) {}
        try { $pdo->exec("ALTER TABLE vocabulary MODIFY hindi_meaning VARCHAR(255) NULL"); } catch(PDOException $e) {}
        
    } catch(PDOException $e) {
        // Ignore column check errors
    }

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
