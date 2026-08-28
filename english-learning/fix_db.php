<?php
// fix_db.php
// Run this file in your browser to fix the missing columns on the live server
require_once 'config/database.php';

echo "<h2>Database Fix Tool</h2>";

$queries = [
    "ALTER TABLE categories ADD COLUMN slug VARCHAR(100) NULL AFTER name" => "Adding 'slug' column to categories",
    "ALTER TABLE stories ADD COLUMN slug VARCHAR(255) NULL AFTER title" => "Adding 'slug' column to stories",
    "ALTER TABLE stories ADD UNIQUE KEY `slug` (`slug`)" => "Adding unique key to 'slug'",
    "ALTER TABLE stories ADD COLUMN featured_image VARCHAR(255) NULL AFTER reading_time" => "Adding 'featured_image' column",
    "ALTER TABLE stories ADD COLUMN seo_title VARCHAR(255) NULL AFTER status" => "Adding 'seo_title' column",
    "ALTER TABLE stories ADD COLUMN seo_description TEXT NULL AFTER seo_title" => "Adding 'seo_description' column",
    "ALTER TABLE stories ADD COLUMN hindi_meaning LONGTEXT NULL AFTER content" => "Adding 'hindi_meaning' column",
    "ALTER TABLE stories ADD COLUMN moral TEXT NULL AFTER hindi_meaning" => "Adding 'moral' column",
    "ALTER TABLE user_stories ADD COLUMN admin_note TEXT NULL AFTER status" => "Adding 'admin_note' column to user_stories",
    "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER password" => "Adding 'profile_photo' column to users",
    "ALTER TABLE admins ADD COLUMN role ENUM('super_admin', 'guest_admin') DEFAULT 'super_admin' AFTER password" => "Adding 'role' to admins",
    "ALTER TABLE admins ADD COLUMN permissions TEXT NULL AFTER role" => "Adding 'permissions' to admins",
    
    // Also fix guest columns just in case
    "ALTER TABLE study_routines MODIFY user_id INT NULL" => "Modifying study_routines user_id",
    "ALTER TABLE study_routines ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id" => "Adding guest_id to study_routines",
    "ALTER TABLE study_sessions MODIFY user_id INT NULL" => "Modifying study_sessions user_id",
    "ALTER TABLE study_sessions ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id" => "Adding guest_id to study_sessions",
    "ALTER TABLE daily_targets MODIFY user_id INT NULL" => "Modifying daily_targets user_id",
    "ALTER TABLE daily_targets ADD COLUMN guest_id VARCHAR(100) NULL AFTER user_id" => "Adding guest_id to daily_targets"
];

foreach ($queries as $sql => $description) {
    echo "<strong>$description:</strong> ";
    try {
        $pdo->exec($sql);
        echo "<span style='color:green;'>Success</span><br>";
    } catch (PDOException $e) {
        // 1060 is "Duplicate column name", 1061 is "Duplicate key name"
        if ($e->getCode() == '42S21' || $e->errorInfo[1] == 1060 || $e->errorInfo[1] == 1061) {
            echo "<span style='color:orange;'>Already exists (Skipped)</span><br>";
        } else {
            echo "<span style='color:red;'>Error: " . $e->getMessage() . "</span><br>";
        }
    }
}

echo "<h3>Done! You can now delete this file and try your import again.</h3>";
?>
