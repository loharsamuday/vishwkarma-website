<?php
require "includes/db.php";

$queries = [
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS blood_group VARCHAR(10) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS mother_tongue VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS highest_qualification VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS college_university VARCHAR(255) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS additional_qualification VARCHAR(255) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS company_name VARCHAR(255) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS designation VARCHAR(255) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS work_type VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS work_location VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS father_name VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS father_occupation VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS mother_name VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS mother_occupation VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS brothers INT DEFAULT 0;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS married_brothers INT DEFAULT 0;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS sisters INT DEFAULT 0;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS married_sisters INT DEFAULT 0;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS family_type VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS family_status VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS family_values VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS pin_code VARCHAR(20) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS diet VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS smoking VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS drinking VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS disability VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS rashi VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS nakshatra VARCHAR(50) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS birth_time TIME NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS birth_place VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS preferred_contact_time VARCHAR(100) NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS about_me TEXT NULL;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS privacy_show_mobile TINYINT(1) DEFAULT 1;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS privacy_show_email TINYINT(1) DEFAULT 1;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS privacy_hide_contact TINYINT(1) DEFAULT 0;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS privacy_only_verified_views TINYINT(1) DEFAULT 0;",
    "ALTER TABLE matrimony_profiles ADD COLUMN IF NOT EXISTS verification_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending';",
    
    "ALTER TABLE partner_preferences ADD COLUMN IF NOT EXISTS preferred_income VARCHAR(100) NULL;",
    "ALTER TABLE partner_preferences ADD COLUMN IF NOT EXISTS preferred_district VARCHAR(100) NULL;",
    "ALTER TABLE partner_preferences ADD COLUMN IF NOT EXISTS preferred_sub_caste VARCHAR(100) NULL;",
    "ALTER TABLE partner_preferences ADD COLUMN IF NOT EXISTS preferred_manglik VARCHAR(50) NULL;",
    "ALTER TABLE partner_preferences ADD COLUMN IF NOT EXISTS other_expectations TEXT NULL;"
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "Executed successfully.\n";
    } catch (Exception $e) {
        echo "Error executing: " . $e->getMessage() . "\n";
    }
}
echo "Migration complete.\n";

