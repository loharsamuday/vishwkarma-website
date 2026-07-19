CREATE DATABASE IF NOT EXISTS `Vishwkarma` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `Vishwkarma`;

-- 1. Master Data Tables
CREATE TABLE `states` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active'
);

CREATE TABLE `districts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `state_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`state_id`) REFERENCES `states`(`id`) ON DELETE CASCADE
);

CREATE TABLE `cities` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `district_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`) ON DELETE CASCADE
);

CREATE TABLE `religions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL
);

CREATE TABLE `castes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `religion_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`religion_id`) REFERENCES `religions`(`id`) ON DELETE CASCADE
);

CREATE TABLE `sub_castes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `caste_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    FOREIGN KEY (`caste_id`) REFERENCES `castes`(`id`) ON DELETE CASCADE
);

CREATE TABLE `gotra` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL
);

-- 2. User & Auth Tables
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL, -- e.g., Admin, Member, Business
    `permissions` TEXT
);

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT DEFAULT 2, -- Default to Member
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `phone` VARCHAR(20) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `is_verified` BOOLEAN DEFAULT FALSE,
    `id_document` VARCHAR(255) NULL,
    `id_status` ENUM('pending', 'approved', 'rejected') NULL DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'banned') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
);

CREATE TABLE `email_verifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `otp` VARCHAR(10) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `role` VARCHAR(50) DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `login_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `login_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 3. Profiles & Matrimony
CREATE TABLE `member_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `address` TEXT,
    `city_id` INT,
    `district_id` INT,
    `state_id` INT,
    `pincode` VARCHAR(10),
    `about_me` TEXT,
    `profile_pic` VARCHAR(255),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`city_id`) REFERENCES `cities`(`id`),
    FOREIGN KEY (`district_id`) REFERENCES `districts`(`id`),
    FOREIGN KEY (`state_id`) REFERENCES `states`(`id`)
);

CREATE TABLE `matrimony_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `profile_for` ENUM('Self', 'Son', 'Daughter', 'Brother', 'Sister', 'Relative') NOT NULL,
    `gender` ENUM('Male', 'Female') NOT NULL,
    `dob` DATE NOT NULL,
    `marital_status` ENUM('Never Married', 'Divorced', 'Widowed', 'Awaiting Divorce') NOT NULL,
    `height` DECIMAL(4,2), -- e.g., 5.11
    `weight` DECIMAL(5,2),
    `religion_id` INT,
    `caste_id` INT,
    `sub_caste_id` INT,
    `gotra_id` INT,
    `manglik` ENUM('Yes', 'No', 'Don\'t Know') DEFAULT 'Don\'t Know',
    `horoscope` VARCHAR(255),
    `education` VARCHAR(255),
    `profession` VARCHAR(255),
    `annual_income` VARCHAR(100),
    `is_premium` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`religion_id`) REFERENCES `religions`(`id`),
    FOREIGN KEY (`caste_id`) REFERENCES `castes`(`id`),
    FOREIGN KEY (`sub_caste_id`) REFERENCES `sub_castes`(`id`),
    FOREIGN KEY (`gotra_id`) REFERENCES `gotra`(`id`)
);

CREATE TABLE `partner_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `matrimony_profile_id` INT NOT NULL UNIQUE,
    `age_min` INT,
    `age_max` INT,
    `height_min` DECIMAL(4,2),
    `height_max` DECIMAL(4,2),
    `marital_status` VARCHAR(255),
    `religion_id` INT,
    `caste_id` INT,
    `education` VARCHAR(255),
    `profession` VARCHAR(255),
    `state_id` INT,
    FOREIGN KEY (`matrimony_profile_id`) REFERENCES `matrimony_profiles`(`id`) ON DELETE CASCADE
);

CREATE TABLE `interests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `status` ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id` INT NOT NULL,
    `receiver_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 4. Community Modules
CREATE TABLE `business_directory` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `business_name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(100) NOT NULL, -- e.g., Goldsmith, Blacksmith, Carpenter
    `description` TEXT,
    `address` TEXT,
    `city_id` INT,
    `district_id` INT,
    `state_id` INT,
    `phone` VARCHAR(20),
    `email` VARCHAR(150),
    `website` VARCHAR(255),
    `logo` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `company_name` VARCHAR(255),
    `job_type` ENUM('Private', 'Government', 'Apprenticeship', 'Skill Development'),
    `location` VARCHAR(255),
    `description` TEXT,
    `salary_range` VARCHAR(100),
    `apply_link` VARCHAR(255),
    `status` ENUM('open', 'closed') DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `education` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `category` ENUM('Colleges', 'Books', 'Study Materials', 'Competitive Exams'),
    `description` TEXT,
    `link` VARCHAR(255),
    `file_path` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `scholarships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `provider` VARCHAR(255),
    `description` TEXT,
    `amount` VARCHAR(100),
    `deadline` DATE,
    `apply_link` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `category` ENUM('Marriage', 'Meeting', 'Seminar', 'Festival', 'Community Gathering'),
    `description` TEXT,
    `event_date` DATETIME NOT NULL,
    `location` VARCHAR(255),
    `image` VARCHAR(255),
    `organizer` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `gallery` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `gallery_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gallery_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    FOREIGN KEY (`gallery_id`) REFERENCES `gallery`(`id`) ON DELETE CASCADE
);

CREATE TABLE `news` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `category` ENUM('News', 'Announcements'),
    `image` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `blood_donors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `blood_group` VARCHAR(10) NOT NULL,
    `last_donated` DATE,
    `is_available` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 5. System, Payments & Others
CREATE TABLE `donations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT, -- Nullable for anonymous
    `donor_name` VARCHAR(150),
    `amount` DECIMAL(10,2) NOT NULL,
    `transaction_id` VARCHAR(100),
    `payment_status` ENUM('Pending', 'Completed', 'Failed') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `plan_name` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('Active', 'Expired', 'Cancelled') DEFAULT 'Active',
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_type` ENUM('Donation', 'Subscription', 'Event'),
    `transaction_id` VARCHAR(100) UNIQUE,
    `status` ENUM('Pending', 'Success', 'Failed'),
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'manual',
    `gateway_order_id` VARCHAR(100) NULL,
    `gateway_payment_id` VARCHAR(100) NULL,
    `gateway_signature` VARCHAR(255) NULL,
    `gateway_payload` TEXT NULL,
    `gateway_status` VARCHAR(50) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE `contact_queries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(255),
    `message` TEXT NOT NULL,
    `status` ENUM('New', 'Read', 'Resolved') DEFAULT 'New',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT
);

CREATE TABLE `success_stories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `bride_name` VARCHAR(100),
    `groom_name` VARCHAR(100),
    `marriage_date` DATE,
    `story` TEXT,
    `image` VARCHAR(255),
    `status` ENUM('Pending', 'Approved') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Insert Default Admin
INSERT INTO `admin_users` (`username`, `password`, `email`) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@vishwkarma.local'); -- password is 'password'

-- 6. User Blocks
CREATE TABLE `user_blocks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `blocker_id` INT NOT NULL,
    `blocked_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_block` (`blocker_id`, `blocked_id`),
    FOREIGN KEY (`blocker_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`blocked_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 7. Student Features
INSERT IGNORE INTO roles (id, name) VALUES (4, 'Student');

CREATE TABLE IF NOT EXISTS student_profiles (
    user_id INT PRIMARY KEY,
    class_name VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS student_group_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    class_name VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

