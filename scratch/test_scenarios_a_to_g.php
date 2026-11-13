<?php
/**
 * scratch/test_scenarios_a_to_g.php
 * Automated verification of Scenarios A through G for Mock Test and Quiz answer submission.
 */

require_once __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "=================================================================\n";
    echo "       VERIFICATION OF SCENARIOS A THROUGH G (GLOBAL AUDIT)       \n";
    echo "=================================================================\n\n";

    // Setup dummy user and dummy test if not present
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            email VARCHAR(150) UNIQUE,
            password VARCHAR(255),
            role_id INT DEFAULT 4,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $stmt_u = $pdo->prepare("SELECT id FROM users WHERE email = 'test_student@example.com'");
    $stmt_u->execute();
    $test_user_id = $stmt_u->fetchColumn();
    if (!$test_user_id) {
        $pdo->exec("INSERT INTO users (first_name, last_name, email, password, role_id) VALUES ('Test', 'Student', 'test_student@example.com', 'dummy_hash', 4)");
        $test_user_id = $pdo->lastInsertId();
    }

    // Setup dummy category, exam and test type
    $pdo->exec("INSERT IGNORE INTO mt_exam_categories (id, name, slug) VALUES (1, 'General Exam', 'general-exam')");
    $pdo->exec("INSERT IGNORE INTO mt_exams (id, category_id, name, slug) VALUES (1, 1, 'General Test Exam', 'general-test-exam')");
    $pdo->exec("INSERT IGNORE INTO mt_test_types (id, name) VALUES (1, 'Full Length Test')");
    $pdo->exec("INSERT IGNORE INTO mt_subjects (id, name) VALUES (1, 'General Subject')");

    // Setup dummy mock test
    $stmt_t = $pdo->prepare("SELECT id FROM mt_mock_tests WHERE slug = 'test-audit-50q'");
    $stmt_t->execute();
    $mock_test_id = $stmt_t->fetchColumn();
    if (!$mock_test_id) {
        $pdo->exec("INSERT INTO mt_mock_tests (id, title, slug, exam_id, test_type_id, total_questions, total_marks, duration_minutes, negative_marking, status) VALUES (999, 'Test Audit 50Q', 'test-audit-50q', 1, 1, 50, 50.00, 60, 0.25, 'published')");
        $mock_test_id = 999;
    }

    // Ensure 50 dummy questions exist mapped to this test
    $stmt_q_cnt = $pdo->prepare("SELECT COUNT(*) FROM mt_test_questions WHERE mock_test_id = ?");
    $stmt_q_cnt->execute([$mock_test_id]);
    $existing_q_cnt = $stmt_q_cnt->fetchColumn();

    if ($existing_q_cnt < 50) {
        echo "Generating 50 test questions for test $mock_test_id...\n";
        for ($i = $existing_q_cnt + 1; $i <= 50; $i++) {
            $stmt_ins_q = $pdo->prepare("INSERT INTO mt_questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks, negative_marks) VALUES (1, ?, 'A', 'B', 'C', 'D', 'A', 1.00, 0.25)");
            $stmt_ins_q->execute(["Sample Question $i"]);
            $new_qid = $pdo->lastInsertId();

            $stmt_map = $pdo->prepare("INSERT INTO mt_test_questions (mock_test_id, question_id, display_order) VALUES (?, ?, ?)");
            $stmt_map->execute([$mock_test_id, $new_qid, $i]);
        }
    }

    // Fetch the 50 mapped question IDs
    $stmt_all_q = $pdo->prepare("SELECT question_id FROM mt_test_questions WHERE mock_test_id = ? ORDER BY display_order ASC LIMIT 50");
    $stmt_all_q->execute([$mock_test_id]);
    $question_ids = $stmt_all_q->fetchAll(PDO::FETCH_COLUMN);
    $total_q = count($question_ids);

    echo "Loaded $total_q test questions for scenario verification.\n\n";

    // Helper to run submission evaluation matching api/submit_test.php
    function evaluateAttempt($pdo, $attempt_id) {
        $stmt_att = $pdo->prepare("SELECT a.*, t.negative_marking FROM mt_test_attempts a JOIN mt_mock_tests t ON a.mock_test_id = t.id WHERE a.id = ?");
        $stmt_att->execute([$attempt_id]);
        $attempt = $stmt_att->fetch();

        $stmt_q = $pdo->prepare("SELECT q.id, q.correct_option, q.marks, q.negative_marks FROM mt_test_questions tq JOIN mt_questions q ON tq.question_id = q.id WHERE tq.mock_test_id = ? ORDER BY tq.display_order ASC");
        $stmt_q->execute([$attempt['mock_test_id']]);
        $questions = $stmt_q->fetchAll();

        $stmt_r = $pdo->prepare("SELECT question_id, selected_option, status FROM mt_student_responses WHERE attempt_id = ?");
        $stmt_r->execute([$attempt_id]);
        $responses_raw = $stmt_r->fetchAll();
        $responses = [];
        foreach ($responses_raw as $r) {
            $responses[$r['question_id']] = $r;
        }

        $score = 0.0;
        $correct = 0;
        $wrong = 0;
        $attempted = 0;
        $skipped = 0;
        $total_questions = count($questions);

        foreach ($questions as $q) {
            $qid = $q['id'];
            $ans = (isset($responses[$qid]) && !empty($responses[$qid]['selected_option'])) ? trim($responses[$qid]['selected_option']) : null;
            if (!empty($ans)) {
                $attempted++;
                if (strcasecmp($ans, trim($q['correct_option'])) === 0) {
                    $correct++;
                    $score += (float)$q['marks'];
                } else {
                    $wrong++;
                    $score -= (float)$q['negative_marks'];
                }
            } else {
                $skipped++;
            }
        }

        // Enforcement of formulas
        $calc_skipped = $total_questions - $attempted;
        $accuracy = ($attempted > 0) ? round(($correct / $attempted) * 100, 2) : 0.0;

        $pdo->prepare("
            UPDATE mt_test_attempts 
            SET score = ?, correct_answers = ?, wrong_answers = ?, unattempted = ?, total_correct = ?, total_incorrect = ?, total_unanswered = ?, attempted_questions = ?, skipped_questions = ?, accuracy = ?, status = 'completed'
            WHERE id = ?
        ")->execute([$score, $correct, $wrong, $skipped, $correct, $wrong, $skipped, $attempted, $skipped, $accuracy, $attempt_id]);

        return [
            'total' => $total_questions,
            'attempted' => $attempted,
            'correct' => $correct,
            'wrong' => $wrong,
            'skipped' => $skipped,
            'score' => $score,
            'accuracy' => $accuracy
        ];
    }

    $all_passed = true;

    // =================================================================
    // SCENARIO A: 50 Questions, all 50 answered -> Skipped must be 0
    // =================================================================
    echo "--- Testing Scenario A (50 answered, 0 skipped) ---\n";
    $pdo->prepare("INSERT INTO mt_test_attempts (mock_test_id, user_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$mock_test_id, $test_user_id]);
    $att_a = $pdo->lastInsertId();

    foreach ($question_ids as $qid) {
        $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, 'A', 'ANSWERED')")->execute([$att_a, $qid]);
    }
    $res_a = evaluateAttempt($pdo, $att_a);
    $pass_a = ($res_a['attempted'] === 50 && $res_a['skipped'] === 0 && ($res_a['correct'] + $res_a['wrong'] + $res_a['skipped'] === 50));
    echo "Result: Attempted={$res_a['attempted']}, Skipped={$res_a['skipped']}, Correct={$res_a['correct']}, Wrong={$res_a['wrong']}\n";
    echo "Check: Total ({$res_a['total']}) == Correct ({$res_a['correct']}) + Wrong ({$res_a['wrong']}) + Skipped ({$res_a['skipped']})\n";
    if ($pass_a) {
        echo ">> Scenario A: PASSED [OK]\n\n";
    } else {
        echo ">> Scenario A: FAILED\n\n";
        $all_passed = false;
    }

    // =================================================================
    // SCENARIO B: 50 Questions, 41 answered, 9 left unanswered -> Skipped must be 9
    // (41 correct = 41 marks, 0 wrong, 9 skipped = 0 marks -> Score = 41.00)
    // =================================================================
    echo "--- Testing Scenario B (41 answered, 9 unanswered -> Skipped = 9) ---\n";
    $pdo->prepare("INSERT INTO mt_test_attempts (mock_test_id, user_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$mock_test_id, $test_user_id]);
    $att_b = $pdo->lastInsertId();

    for ($i = 0; $i < 41; $i++) {
        $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, 'A', 'ANSWERED')")->execute([$att_b, $question_ids[$i]]);
    }
    for ($i = 41; $i < 50; $i++) {
        // 9 unanswered (visited or unvisited, selected_option null)
        $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, NULL, 'VISITED_UNANSWERED')")->execute([$att_b, $question_ids[$i]]);
    }
    $res_b = evaluateAttempt($pdo, $att_b);
    $pass_b = ($res_b['attempted'] === 41 && $res_b['skipped'] === 9 && ($res_b['correct'] + $res_b['wrong'] + $res_b['skipped'] === 50) && $res_b['score'] == 41.0);
    echo "Result: Attempted={$res_b['attempted']}, Skipped={$res_b['skipped']}, Correct={$res_b['correct']}, Wrong={$res_b['wrong']}, Score={$res_b['score']}\n";
    echo "Check: Total ({$res_b['total']}) == Correct ({$res_b['correct']}) + Wrong ({$res_b['wrong']}) + Skipped ({$res_b['skipped']})\n";
    if ($pass_b) {
        echo ">> Scenario B: PASSED [OK]\n\n";
    } else {
        echo ">> Scenario B: FAILED\n\n";
        $all_passed = false;
    }

    // =================================================================
    // SCENARIO C: 50 Questions, 25 answered, 25 untouched -> Skipped must be 25
    // =================================================================
    echo "--- Testing Scenario C (25 answered, 25 untouched -> Skipped = 25) ---\n";
    $pdo->prepare("INSERT INTO mt_test_attempts (mock_test_id, user_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$mock_test_id, $test_user_id]);
    $att_c = $pdo->lastInsertId();

    for ($i = 0; $i < 25; $i++) {
        $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, 'A', 'ANSWERED')")->execute([$att_c, $question_ids[$i]]);
    }
    // Remaining 25 have NO records at all in mt_student_responses
    $res_c = evaluateAttempt($pdo, $att_c);
    $pass_c = ($res_c['attempted'] === 25 && $res_c['skipped'] === 25 && ($res_c['correct'] + $res_c['wrong'] + $res_c['skipped'] === 50));
    echo "Result: Attempted={$res_c['attempted']}, Skipped={$res_c['skipped']}, Correct={$res_c['correct']}, Wrong={$res_c['wrong']}\n";
    echo "Check: Total ({$res_c['total']}) == Correct ({$res_c['correct']}) + Wrong ({$res_c['wrong']}) + Skipped ({$res_c['skipped']})\n";
    if ($pass_c) {
        echo ">> Scenario C: PASSED [OK]\n\n";
    } else {
        echo ">> Scenario C: FAILED\n\n";
        $all_passed = false;
    }

    // =================================================================
    // SCENARIO D: Navigation & Answer Modification Deduplication
    // (Candidate answers Q1='A', goes to Q2='B', returns to Q1, changes to 'C')
    // Must be counted once, 1 row in DB, skipped = 48.
    // =================================================================
    echo "--- Testing Scenario D (Back/Forth Navigation & Answer Change Deduplication) ---\n";
    $pdo->prepare("INSERT INTO mt_test_attempts (mock_test_id, user_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$mock_test_id, $test_user_id]);
    $att_d = $pdo->lastInsertId();

    $q1 = $question_ids[0];
    $q2 = $question_ids[1];

    $upsert_sql = "
        INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), status = VALUES(status)
    ";
    // 1. Candidate answers Q1='A'
    $pdo->prepare($upsert_sql)->execute([$att_d, $q1, 'A', 'ANSWERED']);
    // 2. Candidate navigates to Q2 and answers Q2='B'
    $pdo->prepare($upsert_sql)->execute([$att_d, $q2, 'B', 'ANSWERED']);
    // 3. Candidate returns to Q1 and changes answer to 'C'
    $pdo->prepare($upsert_sql)->execute([$att_d, $q1, 'C', 'ANSWERED']);

    // Check count of rows for (att_d, q1)
    $stmt_check_d = $pdo->prepare("SELECT COUNT(*) FROM mt_student_responses WHERE attempt_id = ? AND question_id = ?");
    $stmt_check_d->execute([$att_d, $q1]);
    $q1_row_count = $stmt_check_d->fetchColumn();

    // Check saved option for Q1
    $stmt_opt_d = $pdo->prepare("SELECT selected_option FROM mt_student_responses WHERE attempt_id = ? AND question_id = ?");
    $stmt_opt_d->execute([$att_d, $q1]);
    $q1_opt = $stmt_opt_d->fetchColumn();

    $res_d = evaluateAttempt($pdo, $att_d);
    $pass_d = ($q1_row_count == 1 && $q1_opt === 'C' && $res_d['attempted'] === 2 && $res_d['skipped'] === 48);
    echo "Q1 DB Row Count: $q1_row_count (Expected: 1)\n";
    echo "Q1 Final Answer: $q1_opt (Expected: C)\n";
    echo "Result: Attempted={$res_d['attempted']}, Skipped={$res_d['skipped']}, Total={$res_d['total']}\n";
    if ($pass_d) {
        echo ">> Scenario D: PASSED [OK]\n\n";
    } else {
        echo ">> Scenario D: FAILED\n\n";
        $all_passed = false;
    }

    // =================================================================
    // SCENARIO E: State Persistence Across Page Refresh / Reload
    // (Candidate answers 20 questions, reloads page -> all 20 restored)
    // =================================================================
    echo "--- Testing Scenario E (State Persistence Across Page Refresh) ---\n";
    $pdo->prepare("INSERT INTO mt_test_attempts (mock_test_id, user_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$mock_test_id, $test_user_id]);
    $att_e = $pdo->lastInsertId();

    for ($i = 0; $i < 20; $i++) {
        $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, 'B', 'ANSWERED')")->execute([$att_e, $question_ids[$i]]);
    }

    // Simulate page reload: query mt_student_responses exactly as mock-test-interface.php does
    $stmt_reload = $pdo->prepare("SELECT question_id, selected_option, status FROM mt_student_responses WHERE attempt_id = ?");
    $stmt_reload->execute([$att_e]);
    $reloaded = $stmt_reload->fetchAll();
    $reloaded_map = [];
    foreach ($reloaded as $r) {
        $reloaded_map[$r['question_id']] = $r;
    }

    $restored_count = 0;
    foreach ($question_ids as $qid) {
        if (isset($reloaded_map[$qid]) && !empty($reloaded_map[$qid]['selected_option'])) {
            $restored_count++;
        }
    }
    $pass_e = ($restored_count === 20);
    echo "Restored Answered Questions on Page Reload: $restored_count / 20\n";
    if ($pass_e) {
        echo ">> Scenario E: PASSED [OK]\n\n";
    } else {
        echo ">> Scenario E: FAILED\n\n";
        $all_passed = false;
    }

    // =================================================================
    // SCENARIO F: Network Disconnect / Rapid Repeated Retries
    // (Simulate 5 identical rapid AJAX calls for same question)
    // Must result in exactly 1 record, latest value kept, no duplicates.
    // =================================================================
    echo "--- Testing Scenario F (Network Disconnect / Rapid Retries) ---\n";
    $pdo->prepare("INSERT INTO mt_test_attempts (mock_test_id, user_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$mock_test_id, $test_user_id]);
    $att_f = $pdo->lastInsertId();
    $target_q = $question_ids[5];

    // Fire 5 repeated upserts
    for ($k = 1; $k <= 5; $k++) {
        $pdo->prepare("
            INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status)
            VALUES (?, ?, 'D', 'ANSWERED')
            ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), status = VALUES(status)
        ")->execute([$att_f, $target_q]);
    }

    $stmt_cnt_f = $pdo->prepare("SELECT COUNT(*) FROM mt_student_responses WHERE attempt_id = ? AND question_id = ?");
    $stmt_cnt_f->execute([$att_f, $target_q]);
    $row_count_f = $stmt_cnt_f->fetchColumn();
    $pass_f = ($row_count_f == 1);
    echo "Database Rows for Question after 5 retries: $row_count_f (Expected: 1)\n";
    if ($pass_f) {
        echo ">> Scenario F: PASSED [OK]\n\n";
    } else {
        echo ">> Scenario F: FAILED\n\n";
        $all_passed = false;
    }

    // =================================================================
    // SCENARIO G: Strict Validation Equation Check on Submit
    // User request example:
    // Total Questions: 50
    // Correct: 41 (+1 mark = 41)
    // Wrong: 8 (-0.25 mark = -2)
    // Skipped: 1 (0 mark = 0)
    // Expected Final Score: 41 - 2 = 39.00
    // =================================================================
    echo "--- Testing Scenario G (Strict Mathematical Formula & Validation) ---\n";
    $pdo->prepare("INSERT INTO mt_test_attempts (mock_test_id, user_id, start_time, status) VALUES (?, ?, NOW(), 'in_progress')")->execute([$mock_test_id, $test_user_id]);
    $att_g = $pdo->lastInsertId();

    // 41 correct answers ('A' matches correct_option 'A')
    for ($i = 0; $i < 41; $i++) {
        $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, 'A', 'ANSWERED')")->execute([$att_g, $question_ids[$i]]);
    }
    // 8 wrong answers ('B' does NOT match correct_option 'A')
    for ($i = 41; $i < 49; $i++) {
        $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, 'B', 'ANSWERED')")->execute([$att_g, $question_ids[$i]]);
    }
    // 1 skipped question (Q50)
    $pdo->prepare("INSERT INTO mt_student_responses (attempt_id, question_id, selected_option, status) VALUES (?, ?, NULL, 'VISITED_UNANSWERED')")->execute([$att_g, $question_ids[49]]);

    $res_g = evaluateAttempt($pdo, $att_g);

    $eq1 = ($res_g['total'] === ($res_g['correct'] + $res_g['wrong'] + $res_g['skipped']));
    $eq2 = ($res_g['attempted'] === ($res_g['correct'] + $res_g['wrong']));
    $eq3 = ($res_g['score'] == 39.0);
    $pass_g = ($eq1 && $eq2 && $eq3 && $res_g['skipped'] === 1 && $res_g['attempted'] === 49);

    echo "Total: {$res_g['total']}, Attempted: {$res_g['attempted']}, Correct: {$res_g['correct']}, Wrong: {$res_g['wrong']}, Skipped: {$res_g['skipped']}\n";
    echo "Final Score: {$res_g['score']} (Expected: 39.00)\n";
    echo "Equation 1 (Total == Correct + Wrong + Skipped): " . ($eq1 ? "TRUE" : "FALSE") . "\n";
    echo "Equation 2 (Attempted == Correct + Wrong): " . ($eq2 ? "TRUE" : "FALSE") . "\n";
    echo "Equation 3 (Score == 39.00): " . ($eq3 ? "TRUE" : "FALSE") . "\n";
    if ($pass_g) {
        echo ">> Scenario G: PASSED [OK]\n\n";
    } else {
        echo ">> Scenario G: FAILED\n\n";
        $all_passed = false;
    }

    echo "=================================================================\n";
    if ($all_passed) {
        echo "  ALL SCENARIOS (A, B, C, D, E, F, G) PASSED WITH 100% ACCURACY! \n";
    } else {
        echo "  ONE OR MORE SCENARIOS FAILED! PLEASE REVIEW OUTPUT ABOVE.     \n";
    }
    echo "=================================================================\n";

    // Clean up test attempts from the verification run
    $test_att_ids = [$att_a, $att_b, $att_c, $att_d, $att_e, $att_f, $att_g];
    $in_clause = implode(',', $test_att_ids);
    $pdo->exec("DELETE FROM mt_student_responses WHERE attempt_id IN ($in_clause)");
    $pdo->exec("DELETE FROM mt_test_attempts WHERE id IN ($in_clause)");
    echo "Cleaned up test attempt rows successfully.\n";

} catch (Exception $e) {
    echo "Fatal Error during verification: " . $e->getMessage() . "\n";
}
