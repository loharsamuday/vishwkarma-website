<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query("SELECT id, bot_id, bot_username, is_active, last_update_id FROM tg_bots");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
