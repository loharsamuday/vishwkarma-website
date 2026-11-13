<?php
require_once __DIR__ . '/../includes/db.php';
$stmt = $pdo->query("SELECT * FROM tg_shared_quizzes WHERE id = 11");
echo json_encode($stmt->fetch(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
