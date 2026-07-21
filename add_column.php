<?php
require 'includes/db.php';
$pdo->exec("ALTER TABLE users ADD COLUMN account_type ENUM('general', 'matrimony') DEFAULT 'general'");
echo 'Column added successfully';
