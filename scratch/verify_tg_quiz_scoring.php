<?php
restore_error_handler();
restore_exception_handler();
require_once __DIR__ . '/../api/tg_quiz/tg_helper.php';
restore_error_handler();
restore_exception_handler();

echo "=== VERIFYING TELEGRAM QUIZ SCORING HELPER ===\n";

// Case 1: User's reported problem
// 50 total questions, 41 correct (+1), 8 wrong (-0.25), 1 skipped (0)
// Expected score: 41 - 2 = 39.00
$r1 = calculateCandidateQuizScore(41, 8, 50, 1.0, 0.25, 0.0);
echo "Case 1 (41 Correct, 8 Wrong, 1 Skipped out of 50):\n";
echo "Attempted: {$r1['attempted']}, Skipped: {$r1['skipped']}, Score: {$r1['final_score']} (Expected: 39.00)\n";
$p1 = ($r1['attempted'] === 49 && $r1['skipped'] === 1 && $r1['final_score'] == 39.0);
echo "Status: " . ($p1 ? "PASS" : "FAIL") . "\n\n";

// Case 2: 50 answered, 0 skipped
$r2 = calculateCandidateQuizScore(50, 0, 50, 1.0, 0.25, 0.0);
echo "Case 2 (50 Correct, 0 Wrong, 0 Skipped out of 50):\n";
echo "Attempted: {$r2['attempted']}, Skipped: {$r2['skipped']}, Score: {$r2['final_score']} (Expected: 50.00)\n";
$p2 = ($r2['attempted'] === 50 && $r2['skipped'] === 0 && $r2['final_score'] == 50.0);
echo "Status: " . ($p2 ? "PASS" : "FAIL") . "\n\n";

// Case 3: 20 correct, 5 wrong, 25 skipped
$r3 = calculateCandidateQuizScore(20, 5, 50, 1.0, 0.25, 0.0);
echo "Case 3 (20 Correct, 5 Wrong, 25 Skipped out of 50):\n";
echo "Attempted: {$r3['attempted']}, Skipped: {$r3['skipped']}, Score: {$r3['final_score']} (Expected: 18.75)\n";
$p3 = ($r3['attempted'] === 25 && $r3['skipped'] === 25 && $r3['final_score'] == 18.75);
echo "Status: " . ($p3 ? "PASS" : "FAIL") . "\n\n";

if ($p1 && $p2 && $p3) {
    echo ">> ALL TELEGRAM QUIZ SCORING CASES PASSED!\n";
} else {
    echo ">> SOME TELEGRAM QUIZ SCORING CASES FAILED!\n";
}
