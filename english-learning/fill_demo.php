<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/study_auth.php';

$condition = get_study_user_condition();
$param = get_study_user_param();

// Clear old routines
$pdo->prepare("DELETE FROM study_routines WHERE $condition")->execute([$param]);

$now = time();
$today = date('Y-m-d');

$routines = [
    [
        'title' => 'Morning Reading',
        'start' => date('H:i:s', $now - 7200),
        'end' => date('H:i:s', $now - 3600),
        'status' => 'Completed',
        'updated_at' => date('Y-m-d H:i:s')
    ],
    [
        'title' => 'English Grammar Practice',
        'start' => date('H:i:s', $now - 1800), // Started 30 mins ago
        'end' => date('H:i:s', $now + 1800),   // Ends in 30 mins
        'status' => 'Pending',
        'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'title' => 'Vocabulary Flashcards',
        'start' => date('H:i:s', $now + 1800), // Starts in 30 mins
        'end' => date('H:i:s', $now + 5400),
        'status' => 'Pending',
        'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ]
];

$sql = $is_guest ? "INSERT INTO study_routines (guest_id, routine_date, task_title, category, start_time, end_time, status, updated_at) VALUES (?, ?, ?, 'Reading', ?, ?, ?, ?)" 
                 : "INSERT INTO study_routines (user_id, routine_date, task_title, category, start_time, end_time, status, updated_at) VALUES (?, ?, ?, 'Reading', ?, ?, ?, ?)";

foreach($routines as $r) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param, $today, $r['title'], $r['start'], $r['end'], $r['status'], $r['updated_at']]);
}

// Clear old sessions and targets
$pdo->prepare("DELETE FROM study_sessions WHERE $condition")->execute([$param]);
$pdo->prepare("DELETE FROM daily_targets WHERE $condition")->execute([$param]);

$sessions = [
    [date('Y-m-d', strtotime('-6 days')), 120], // 2 hours
    [date('Y-m-d', strtotime('-5 days')), 90],  // 1.5 hours
    [date('Y-m-d', strtotime('-4 days')), 180], // 3 hours
    [date('Y-m-d', strtotime('-3 days')), 45],  // 45 mins
    [date('Y-m-d', strtotime('-2 days')), 210], // 3.5 hours
    [date('Y-m-d', strtotime('-1 days')), 60],  // 1 hour
    [$today, 150]                               // 2.5 hours today
];

$sql_sess = $is_guest ? "INSERT INTO study_sessions (guest_id, study_date, subject, start_time, end_time, duration_minutes, notes) VALUES (?, ?, 'Demo Study', '10:00:00', '12:00:00', ?, 'Demo Data')" 
                      : "INSERT INTO study_sessions (user_id, study_date, subject, start_time, end_time, duration_minutes, notes) VALUES (?, ?, 'Demo Study', '10:00:00', '12:00:00', ?, 'Demo Data')";

foreach($sessions as $s) {
    $stmt = $pdo->prepare($sql_sess);
    $stmt->execute([$param, $s[0], $s[1]]);
}

// Add Target Data
$targets = [
    ['Pages to Read', 'Read Story Book', 10, 7, 'Pending'],       // 7/10 complete
    ['Vocabulary Words', 'Learn new words', 20, 20, 'Completed'], // 20/20 complete
    ['Mock Tests', 'Complete Practice Tests', 2, 0, 'Pending']    // 0/2 complete
];

$sql_target = $is_guest ? "INSERT INTO daily_targets (guest_id, target_date, target_type, target_description, target_value, completed_value, status) VALUES (?, ?, ?, ?, ?, ?, ?)" 
                        : "INSERT INTO daily_targets (user_id, target_date, target_type, target_description, target_value, completed_value, status) VALUES (?, ?, ?, ?, ?, ?, ?)";

foreach($targets as $t) {
    $stmt = $pdo->prepare($sql_target);
    $stmt->execute([$param, $today, $t[0], $t[1], $t[2], $t[3], $t[4]]);
}

header("Location: study-dashboard.php");
exit;
