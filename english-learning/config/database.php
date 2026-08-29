<?php
// config/database.php

// Check if running on localhost or CLI
$is_localhost = (php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')));

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
    if (!defined('EL_BASE_URL')) {
        define('EL_BASE_URL', '/vishwkarma/english-learning/');
    }
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
    if (!defined('EL_BASE_URL')) {
        define('EL_BASE_URL', '/english-learning/');
    }
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
            profile_photo VARCHAR(255) NULL,
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
            hindi_meaning LONGTEXT NULL,
            moral TEXT NULL,
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
            role ENUM('super_admin', 'guest_admin') DEFAULT 'super_admin',
            permissions TEXT NULL,
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
            admin_note TEXT DEFAULT NULL,
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

        CREATE TABLE IF NOT EXISTS exam_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS idioms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            idiom VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            english_meaning TEXT,
            hindi_meaning TEXT,
            explanation TEXT,
            example_sentence TEXT,
            memory_trick TEXT,
            synonyms TEXT,
            antonyms TEXT,
            category_id INT NULL,
            difficulty ENUM('Easy', 'Moderate', 'Hard', 'Very Important') DEFAULT 'Moderate',
            exam_type VARCHAR(255) NULL,
            related_content TEXT NULL,
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            meta_keywords TEXT NULL,
            canonical_url VARCHAR(255) NULL,
            featured TINYINT(1) DEFAULT 0,
            status ENUM('Draft', 'Published') DEFAULT 'Draft',
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (slug),
            INDEX (status),
            INDEX (category_id),
            INDEX (featured),
            FOREIGN KEY (category_id) REFERENCES exam_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS phrasal_verbs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phrasal_verb VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            english_meaning TEXT,
            hindi_meaning TEXT,
            explanation TEXT,
            example_sentence TEXT,
            memory_trick TEXT,
            synonyms TEXT,
            antonyms TEXT,
            category_id INT NULL,
            difficulty ENUM('Easy', 'Moderate', 'Hard', 'Very Important') DEFAULT 'Moderate',
            exam_type VARCHAR(255) NULL,
            related_content TEXT NULL,
            meta_title VARCHAR(255) NULL,
            meta_description TEXT NULL,
            meta_keywords TEXT NULL,
            canonical_url VARCHAR(255) NULL,
            featured TINYINT(1) DEFAULT 0,
            status ENUM('Draft', 'Published') DEFAULT 'Draft',
            views INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (slug),
            INDEX (status),
            INDEX (category_id),
            INDEX (featured),
            FOREIGN KEY (category_id) REFERENCES exam_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS practice_questions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content_type ENUM('idiom', 'phrasal_verb', 'general') DEFAULT 'general',
            content_id INT NULL,
            question TEXT NOT NULL,
            option_a VARCHAR(255) NOT NULL,
            option_b VARCHAR(255) NOT NULL,
            option_c VARCHAR(255) NOT NULL,
            option_d VARCHAR(255) NOT NULL,
            correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
            explanation TEXT,
            hindi_explanation TEXT,
            difficulty ENUM('Easy', 'Moderate', 'Hard', 'Very Important') DEFAULT 'Moderate',
            exam_type VARCHAR(255) NULL,
            status ENUM('Draft', 'Published') DEFAULT 'Published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS target_exams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_preferences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            target_exam_id INT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (target_exam_id) REFERENCES target_exams(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_memory (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_type ENUM('idiom', 'phrasal_verb', 'vocabulary') NOT NULL,
            item_id INT NOT NULL,
            status ENUM('learning', 'need_revision', 'mastered') DEFAULT 'learning',
            mastery_score INT DEFAULT 0,
            next_revision_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY user_item (user_id, item_type, item_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS mistake_book (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            question_id INT NOT NULL,
            wrong_answer VARCHAR(255) NULL,
            status ENUM('active', 'mastered') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            activity_type VARCHAR(100) NOT NULL,
            points_earned INT DEFAULT 0,
            metadata TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_streaks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            current_streak INT DEFAULT 0,
            longest_streak INT DEFAULT 0,
            last_activity_date DATE NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS battle_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            score INT DEFAULT 0,
            correct INT DEFAULT 0,
            wrong INT DEFAULT 0,
            accuracy DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            level_name VARCHAR(100) NOT NULL,
            min_points INT DEFAULT 0,
            min_mastered INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT NULL,
            icon_class VARCHAR(100) NULL,
            type VARCHAR(50) NULL,
            requirement_value INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS student_badges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            badge_id INT NOT NULL,
            earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS confusion_sets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            status ENUM('Draft', 'Published') DEFAULT 'Draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS confusion_set_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            set_id INT NOT NULL,
            item_type ENUM('idiom', 'phrasal_verb', 'vocabulary') NOT NULL,
            item_id INT NOT NULL,
            FOREIGN KEY (set_id) REFERENCES confusion_sets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
        CREATE TABLE IF NOT EXISTS study_routines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            guest_id VARCHAR(100) NULL,
            routine_date DATE NOT NULL,
            task_title VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            start_time TIME,
            end_time TIME,
            priority ENUM('High', 'Medium', 'Low') DEFAULT 'Medium',
            status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS study_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            guest_id VARCHAR(100) NULL,
            study_date DATE NOT NULL,
            subject VARCHAR(255) NOT NULL,
            start_time TIME,
            end_time TIME,
            duration_minutes INT DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

        CREATE TABLE IF NOT EXISTS daily_targets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            guest_id VARCHAR(100) NULL,
            target_date DATE NOT NULL,
            target_type VARCHAR(100) NOT NULL,
            target_description VARCHAR(255),
            target_value INT NOT NULL DEFAULT 0,
            completed_value INT NOT NULL DEFAULT 0,
            status ENUM('Pending', 'In Progress', 'Completed') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ==========================================
    // AUTO FIX SCHEMA (Add missing columns to existing tables)
    // ==========================================
    try {
        // Fix categories table
        $cat_columns = $pdo->query("SHOW COLUMNS FROM categories")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('slug', $cat_columns)) {
            $pdo->exec("ALTER TABLE categories ADD COLUMN slug VARCHAR(100) NULL AFTER name");
        }
        
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
        
        // Fix study tables for guests
        try {
            $study_routines_cols = $pdo->query("SHOW COLUMNS FROM study_routines")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('guest_id', $study_routines_cols)) {
                $pdo->exec("ALTER TABLE study_routines MODIFY user_id INT NULL");
                $pdo->exec("ALTER TABLE study_routines ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id");
            }
        } catch(PDOException $e) {}
        
        try {
            $study_sessions_cols = $pdo->query("SHOW COLUMNS FROM study_sessions")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('guest_id', $study_sessions_cols)) {
                $pdo->exec("ALTER TABLE study_sessions MODIFY user_id INT NULL");
                $pdo->exec("ALTER TABLE study_sessions ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id");
            }
        } catch(PDOException $e) {}
        
        try {
            $daily_targets_cols = $pdo->query("SHOW COLUMNS FROM daily_targets")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('guest_id', $daily_targets_cols)) {
                $pdo->exec("ALTER TABLE daily_targets MODIFY user_id INT NULL");
                $pdo->exec("ALTER TABLE daily_targets ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id");
            }
        } catch(PDOException $e) {}
        if (!in_array('hindi_meaning', $columns)) {
            $pdo->exec("ALTER TABLE stories ADD COLUMN hindi_meaning LONGTEXT NULL AFTER content");
        }
        if (!in_array('moral', $columns)) {
            $pdo->exec("ALTER TABLE stories ADD COLUMN moral TEXT NULL AFTER hindi_meaning");
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

        // Create default target exams
        $stmt_exams = $pdo->query("SELECT COUNT(*) FROM target_exams");
        if ($stmt_exams && $stmt_exams->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO target_exams (name) VALUES 
                ('SBI PO'), ('IBPS PO'), ('SBI Clerk'), ('IBPS Clerk'), 
                ('RBI Grade B'), ('NABARD'), ('SSC CGL'), ('SSC CHSL'), 
                ('Railway NTPC'), ('UPSC EPFO'), ('Other')");
        }

        // Create default levels
        $stmt_levels = $pdo->query("SELECT COUNT(*) FROM levels");
        if ($stmt_levels && $stmt_levels->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO levels (level_name, min_points, min_mastered) VALUES 
                ('Beginner', 0, 0),
                ('Learner', 100, 50),
                ('Smart Learner', 500, 150),
                ('Exam Ready', 1500, 300),
                ('Vocabulary Master', 3000, 500)");
        }

        // Create default badges
        $stmt_badges = $pdo->query("SELECT COUNT(*) FROM badges");
        if ($stmt_badges && $stmt_badges->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO badges (name, description, icon_class, type, requirement_value) VALUES 
                ('First Step', 'Started your learning journey', 'fas fa-shoe-prints', 'points', 10),
                ('50 Words', 'Mastered 50 new words/phrases', 'fas fa-book', 'mastery', 50),
                ('Memory Master', 'Saved 100 items to your memory', 'fas fa-brain', 'saved', 100),
                ('7 Day Streak', 'Maintained a 7-day learning streak', 'fas fa-fire', 'streak', 7),
                ('Daily Challenge Champion', 'Completed 10 daily challenges', 'fas fa-trophy', 'challenge', 10),
                ('Battle Master', 'Won 50 Battles', 'fas fa-khanda', 'battle', 50)");
        }
        
        // ==========================================
        // AUTOMATIC DATABASE MANAGE SYSTEM
        // ==========================================
        // This checks if the latest columns exist. If not, it automatically fixes the database.
        $stmt_check = $pdo->query("SHOW COLUMNS FROM user_stories LIKE 'admin_note'");
        if ($stmt_check && $stmt_check->rowCount() == 0) {
            // The column is missing! This means the live database is outdated.
            $alter_queries = [
                "ALTER TABLE categories ADD COLUMN slug VARCHAR(100) NULL AFTER name",
                "ALTER TABLE stories ADD COLUMN slug VARCHAR(255) NULL AFTER title",
                "ALTER TABLE stories ADD UNIQUE KEY `slug` (`slug`)",
                "ALTER TABLE stories ADD COLUMN featured_image VARCHAR(255) NULL AFTER reading_time",
                "ALTER TABLE stories ADD COLUMN seo_title VARCHAR(255) NULL AFTER status",
                "ALTER TABLE stories ADD COLUMN seo_description TEXT NULL AFTER seo_title",
                "ALTER TABLE stories ADD COLUMN hindi_meaning LONGTEXT NULL AFTER content",
                "ALTER TABLE stories ADD COLUMN moral TEXT NULL AFTER hindi_meaning",
                "ALTER TABLE user_stories ADD COLUMN admin_note TEXT NULL AFTER status",
                "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER password",
                "ALTER TABLE admins ADD COLUMN role ENUM('super_admin', 'guest_admin') DEFAULT 'super_admin' AFTER password",
                "ALTER TABLE admins ADD COLUMN permissions TEXT NULL AFTER role",
                "ALTER TABLE study_routines MODIFY user_id INT NULL",
                "ALTER TABLE study_routines ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id",
                "ALTER TABLE study_sessions MODIFY user_id INT NULL",
                "ALTER TABLE study_sessions ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id",
                "ALTER TABLE daily_targets MODIFY user_id INT NULL",
                "ALTER TABLE daily_targets ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id"
            ];
            
            foreach ($alter_queries as $q) {
                try {
                    $pdo->exec($q);
                } catch (PDOException $e) {
                    // Ignore duplicate column errors (1060, 1061)
                }
            }
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
