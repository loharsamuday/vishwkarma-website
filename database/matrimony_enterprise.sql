-- ==========================================================
-- VISHWAKARMA SAMAJ MATRIMONY - ENTERPRISE DB SCHEMA
-- ==========================================================
-- This schema extends the existing system and adds the
-- Enterprise-level 40+ tables with SEO & Performance Indexing.

-- 1. EXTENDED MASTER TABLES
CREATE TABLE IF NOT EXISTS `master_countries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(5),
    `status` ENUM('active', 'inactive') DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS `master_educations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `degree` VARCHAR(100) NOT NULL,
    `qualification` ENUM('Undergraduate', 'Graduate', 'Post Graduate', 'Doctorate', 'Diploma', 'High School', 'Other'),
    `status` ENUM('active', 'inactive') DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS `master_occupations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `category` ENUM('Private', 'Government', 'Business', 'Self Employed', 'Not Working'),
    `status` ENUM('active', 'inactive') DEFAULT 'active'
);

CREATE TABLE IF NOT EXISTS `master_rashis` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL
);

CREATE TABLE IF NOT EXISTS `master_nakshatras` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL
);

-- 2. ENHANCED MATRIMONY PROFILES
-- (Modifying/Replacing existing matrimony_profiles conceptually)
CREATE TABLE IF NOT EXISTS `ent_matrimony_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `profile_for` ENUM('Self', 'Son', 'Daughter', 'Brother', 'Sister', 'Relative') NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL, -- SEO SLUG: rahul-vishwakarma-patna-1234
    
    -- Basic
    `gender` ENUM('Male', 'Female') NOT NULL,
    `dob` DATE NOT NULL,
    `marital_status` ENUM('Never Married', 'Divorced', 'Widowed', 'Awaiting Divorce', 'Annulled') NOT NULL,
    `height_cm` INT, -- Storing in cm for easier filtering
    `weight_kg` DECIMAL(5,2),
    `blood_group` VARCHAR(10),
    `mother_tongue` VARCHAR(50) DEFAULT 'Hindi',
    
    -- Location
    `country_id` INT DEFAULT 1,
    `state_id` INT,
    `district_id` INT,
    `city_id` INT,
    
    -- Community
    `religion_id` INT,
    `caste_id` INT,
    `sub_caste_id` INT,
    `gotra_id` INT,
    
    -- Astrology
    `manglik` ENUM('Yes', 'No', 'Don\'t Know', 'Anshik') DEFAULT 'Don\'t Know',
    `rashi_id` INT,
    `nakshatra_id` INT,
    `birth_time` TIME,
    `birth_place` VARCHAR(100),
    
    -- Education & Career (Denormalized for quick search, detailed in related tables)
    `education_id` INT,
    `occupation_id` INT,
    `annual_income_lakhs` DECIMAL(10,2),
    
    -- Lifestyle
    `diet` ENUM('Vegetarian', 'Non-Vegetarian', 'Eggetarian', 'Vegan') DEFAULT 'Vegetarian',
    `smoking` ENUM('No', 'Yes', 'Occasionally') DEFAULT 'No',
    `drinking` ENUM('No', 'Yes', 'Occasionally') DEFAULT 'No',
    
    -- Status & SEO
    `about_me` TEXT,
    `profile_completeness` INT DEFAULT 0, -- out of 100
    `is_verified` BOOLEAN DEFAULT FALSE,
    `is_premium` BOOLEAN DEFAULT FALSE,
    `status` ENUM('active', 'inactive', 'hidden', 'deleted') DEFAULT 'active',
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- INDEXING FOR FAST SEARCH
    INDEX `idx_search_basic` (`gender`),
    INDEX `idx_search_location` (`state_id`, `district_id`, `city_id`),
    INDEX `idx_search_community` (`caste_id`, `sub_caste_id`),
    INDEX `idx_search_career` (`occupation_id`, `annual_income_lakhs`),
    INDEX `idx_search_status` (`is_verified`, `is_premium`, `created_at`)
);

-- 3. PROFILE DETAILS (NORMALIZED)
CREATE TABLE IF NOT EXISTS `ent_education_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT NOT NULL,
    `education_id` INT NOT NULL,
    `degree_name` VARCHAR(100),
    `college_name` VARCHAR(150),
    `passing_year` INT,
    FOREIGN KEY (`profile_id`) REFERENCES `ent_matrimony_profiles`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `ent_career_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT NOT NULL,
    `occupation_id` INT NOT NULL,
    `company_name` VARCHAR(150),
    `designation` VARCHAR(100),
    `work_location` VARCHAR(100),
    FOREIGN KEY (`profile_id`) REFERENCES `ent_matrimony_profiles`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `ent_family_details` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT NOT NULL,
    `father_name` VARCHAR(100),
    `father_occupation` VARCHAR(100),
    `mother_name` VARCHAR(100),
    `mother_occupation` VARCHAR(100),
    `brothers` INT DEFAULT 0,
    `sisters` INT DEFAULT 0,
    `married_brothers` INT DEFAULT 0,
    `married_sisters` INT DEFAULT 0,
    `family_type` ENUM('Joint', 'Nuclear') DEFAULT 'Nuclear',
    `family_status` ENUM('Middle Class', 'Upper Middle Class', 'Rich', 'Affluent'),
    `native_place` VARCHAR(150),
    FOREIGN KEY (`profile_id`) REFERENCES `ent_matrimony_profiles`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `ent_photos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL, -- WebP format
    `is_primary` BOOLEAN DEFAULT FALSE,
    `is_approved` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`profile_id`) REFERENCES `ent_matrimony_profiles`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `ent_documents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT NOT NULL,
    `doc_type` ENUM('ID Proof', 'Address Proof', 'Education', 'Salary'),
    `file_name` VARCHAR(255) NOT NULL,
    `is_verified` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`profile_id`) REFERENCES `ent_matrimony_profiles`(`id`) ON DELETE CASCADE
);

-- 4. PARTNER PREFERENCES
CREATE TABLE IF NOT EXISTS `ent_partner_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT NOT NULL UNIQUE,
    `age_min` INT,
    `age_max` INT,
    `height_min_cm` INT,
    `height_max_cm` INT,
    `marital_status` VARCHAR(255), -- JSON array string
    `manglik` VARCHAR(100), -- JSON array string
    `diet` VARCHAR(100), -- JSON array string
    `state_ids` VARCHAR(255), -- JSON array string
    `city_ids` VARCHAR(255), -- JSON array string
    `education_ids` VARCHAR(255), -- JSON array string
    `occupation_ids` VARCHAR(255), -- JSON array string
    `income_min_lakhs` DECIMAL(10,2),
    FOREIGN KEY (`profile_id`) REFERENCES `ent_matrimony_profiles`(`id`) ON DELETE CASCADE
);

-- 5. ENGAGEMENT & FEATURES
CREATE TABLE IF NOT EXISTS `ent_profile_views` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `viewer_id` INT NOT NULL,
    `viewed_profile_id` INT NOT NULL,
    `view_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_views` (`viewed_profile_id`, `viewer_id`)
);

CREATE TABLE IF NOT EXISTS `ent_ai_match_scores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `profile_id` INT NOT NULL,
    `matched_profile_id` INT NOT NULL,
    `compatibility_score` INT NOT NULL, -- percentage
    `last_calculated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_match` (`profile_id`, `matched_profile_id`),
    INDEX `idx_score` (`profile_id`, `compatibility_score` DESC)
);

CREATE TABLE IF NOT EXISTS `ent_search_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT, -- Null for guests
    `search_params` JSON,
    `search_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `ent_saved_searches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100),
    `search_params` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. SEO & OPERATIONS
CREATE TABLE IF NOT EXISTS `ent_seo_dynamic_pages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `url_slug` VARCHAR(255) UNIQUE NOT NULL, -- e.g., brides-in-bihar, software-engineer-grooms
    `page_type` ENUM('Location', 'Profession', 'Community', 'Education', 'Custom'),
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `h1_tag` VARCHAR(255),
    `content_top` TEXT,
    `content_bottom` TEXT,
    `filter_json` JSON, -- Defines what profiles show on this page
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `ent_sitemap_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `url` VARCHAR(255) NOT NULL,
    `last_mod` DATETIME,
    `change_freq` VARCHAR(20) DEFAULT 'daily',
    `priority` DECIMAL(3,2) DEFAULT 0.8,
    `is_processed` BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS `ent_email_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `to_email` VARCHAR(150),
    `subject` VARCHAR(255),
    `body` TEXT,
    `status` ENUM('Pending', 'Sent', 'Failed') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add basic log tables and monetization
CREATE TABLE IF NOT EXISTS `ent_admin_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT,
    `action` VARCHAR(255),
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `ent_memberships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plan_name` VARCHAR(100),
    `price` DECIMAL(10,2),
    `duration_months` INT,
    `contact_views_allowed` INT,
    `is_active` BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS `ent_user_memberships` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `membership_id` INT,
    `start_date` DATE,
    `end_date` DATE,
    `contacts_remaining` INT,
    `status` ENUM('Active', 'Expired', 'Cancelled')
);
