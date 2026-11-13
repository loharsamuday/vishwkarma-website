<?php
require_once __DIR__ . '/../includes/db.php';
$pdo->query("UPDATE tg_group_quiz_sessions SET status = 'STOPPED' WHERE id = 6");
echo "Session 6 stopped.\n";
