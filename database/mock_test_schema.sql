-- Mock Test System Schema Integration for Vishwkarma Database

USE `Vishwkarma`;

-- 1. Exam Categories (e.g., Banking, SSC, Railway)
CREATE TABLE IF NOT EXISTS `mt_exam_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) UNIQUE NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Exams (e.g., SBI PO, SSC CGL)
CREATE TABLE IF NOT EXISTS `mt_exams` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) UNIQUE NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `mt_exam_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Test Series (Optional grouping)
CREATE TABLE IF NOT EXISTS `mt_test_series` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `exam_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) UNIQUE NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `mt_exams`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Test Types (e.g., Full Mock, Sectional, Previous Year)
CREATE TABLE IF NOT EXISTS `mt_test_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default test types
INSERT IGNORE INTO `mt_test_types` (`id`, `name`, `status`) VALUES 
(1, 'Full Length Mock Test', 'active'),
(2, 'Prelims Mock', 'active'),
(3, 'Mains Mock', 'active'),
(4, 'Sectional Test', 'active'),
(5, 'Previous Year Paper', 'active');

-- 5. Subjects & Topics (e.g., Reasoning, Quant)
CREATE TABLE IF NOT EXISTS `mt_subjects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mt_topics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `subject_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (`subject_id`) REFERENCES `mt_subjects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Mock Tests (The main test record)
CREATE TABLE IF NOT EXISTS `mt_mock_tests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `exam_id` INT NOT NULL,
    `test_series_id` INT NULL,
    `test_type_id` INT NOT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 60,
    `total_marks` DECIMAL(8,2) NOT NULL DEFAULT 100,
    `total_questions` INT NOT NULL DEFAULT 100,
    `negative_marking` DECIMAL(4,2) NOT NULL DEFAULT 0.25,
    `language` VARCHAR(50) DEFAULT 'English, Hindi',
    `instructions` TEXT,
    `start_date` DATETIME NULL,
    `end_date` DATETIME NULL,
    `is_premium` BOOLEAN DEFAULT FALSE,
    `attempt_limit` INT DEFAULT 1,
    `result_visibility` ENUM('immediate', 'after_end_date', 'manual') DEFAULT 'immediate',
    `status` ENUM('draft', 'published', 'inactive') DEFAULT 'draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`exam_id`) REFERENCES `mt_exams`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`test_series_id`) REFERENCES `mt_test_series`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`test_type_id`) REFERENCES `mt_test_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Question Bank
CREATE TABLE IF NOT EXISTS `mt_questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `question_type` ENUM('single_mcq', 'multi_mcq', 'true_false', 'numerical') DEFAULT 'single_mcq',
    `question_text` TEXT NOT NULL,
    `option_a` TEXT NULL,
    `option_b` TEXT NULL,
    `option_c` TEXT NULL,
    `option_d` TEXT NULL,
    `option_e` TEXT NULL,
    `correct_option` VARCHAR(255) NOT NULL,
    `explanation` TEXT NULL,
    `short_trick` TEXT NULL,
    `subject_id` INT NOT NULL,
    `topic_id` INT NULL,
    `difficulty_level` ENUM('Easy', 'Moderate', 'Difficult') DEFAULT 'Moderate',
    `marks` DECIMAL(6,2) NOT NULL DEFAULT 1.00,
    `negative_marks` DECIMAL(6,2) NOT NULL DEFAULT 0.25,
    `language` VARCHAR(50) DEFAULT 'English',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`subject_id`) REFERENCES `mt_subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`topic_id`) REFERENCES `mt_topics`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Test Questions Mapping (Linking Mock Tests to Questions)
CREATE TABLE IF NOT EXISTS `mt_test_questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mock_test_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `section_name` VARCHAR(150) NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`mock_test_id`) REFERENCES `mt_mock_tests`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `mt_questions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_test_question` (`mock_test_id`, `question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Test Attempts (Student records)
CREATE TABLE IF NOT EXISTS `mt_test_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mock_test_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NULL,
    `status` ENUM('in_progress', 'submitted', 'time_up') DEFAULT 'in_progress',
    `total_marks` DECIMAL(8,2) DEFAULT 0,
    `score` DECIMAL(8,2) DEFAULT 0,
    `correct_answers` INT DEFAULT 0,
    `wrong_answers` INT DEFAULT 0,
    `unattempted` INT DEFAULT 0,
    `accuracy` DECIMAL(5,2) DEFAULT 0,
    `time_taken_seconds` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`mock_test_id`) REFERENCES `mt_mock_tests`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Attempt Answers (Question-level student answers)
CREATE TABLE IF NOT EXISTS `mt_attempt_answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `attempt_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `user_answer` VARCHAR(255) NULL,
    `is_correct` BOOLEAN NULL,
    `time_spent_seconds` INT DEFAULT 0,
    `status` ENUM('answered', 'not_answered', 'marked_review', 'answered_marked_review', 'not_visited') DEFAULT 'not_visited',
    `marks_awarded` DECIMAL(6,2) DEFAULT 0,
    FOREIGN KEY (`attempt_id`) REFERENCES `mt_test_attempts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `mt_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Bookmarked Questions
CREATE TABLE IF NOT EXISTS `mt_question_bookmarks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `mt_questions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_bookmark` (`user_id`, `question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Mock Test Reviews
CREATE TABLE IF NOT EXISTS `mt_mock_test_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `mock_test_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` INT NOT NULL CHECK(rating >= 1 AND rating <= 5),
    `review_text` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`mock_test_id`) REFERENCES `mt_mock_tests`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
