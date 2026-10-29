<?php
// api/tg_quiz/questions.php
// Question Management API — Add / Edit / Delete / Upload CSV/XLSX

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF token invalid']);
        exit;
    }
}

function normalizeTimeLimit($value): ?int {
    if ($value === null || $value === '') return null;
    $str = trim((string)$value);
    if ($str === '') return null;
    if (preg_match('/^(\d+)\s*(s|sec|secs|second|seconds)?$/i', $str, $m)) {
        $seconds = (int)$m[1];
        return $seconds > 0 ? $seconds : null;
    }
    $number = (int)$str;
    return $number > 0 ? $number : null;
}

function sanitizeQuestion(array $q): array {
    $timeLimit = normalizeTimeLimit($q['time_limit'] ?? $q['question_time'] ?? $q['timer_seconds'] ?? null);
    return [
        'question_text'  => strip_tags(trim($q['question_text'] ?? $q['question'] ?? '')),
        'option_a'       => strip_tags(trim($q['option_a'] ?? '')),
        'option_b'       => strip_tags(trim($q['option_b'] ?? '')),
        'option_c'       => strip_tags(trim($q['option_c'] ?? '')),
        'option_d'       => strip_tags(trim($q['option_d'] ?? '')),
        'correct_answer' => strtoupper(trim($q['correct_answer'] ?? '')),
        'explanation'    => strip_tags(trim($q['explanation'] ?? '')),
        'time_limit'     => $timeLimit,
    ];
}

function validateQuestion(array $q, int $num): ?string {
    if (empty($q['question_text'])) return "Q{$num}: Question text is empty";
    if (empty($q['option_a']))     return "Q{$num}: Option A is empty";
    if (empty($q['option_b']))     return "Q{$num}: Option B is empty";
    if (!in_array($q['correct_answer'], ['A','B','C','D'])) return "Q{$num}: Correct answer must be A, B, C, or D (got: {$q['correct_answer']})";
    if (array_key_exists('time_limit', $q) && $q['time_limit'] !== null && $q['time_limit'] <= 0) {
        return "Q{$num}: time_limit must be a positive integer in seconds";
    }
    if ($q['correct_answer'] === 'C' && empty($q['option_c'])) return "Q{$num}: Correct answer is C but Option C is empty";
    if ($q['correct_answer'] === 'D' && empty($q['option_d'])) return "Q{$num}: Correct answer is D but Option D is empty";
    return null;
}

function extractCorrectLetter(string $rawAns, string $optA, string $optB, string $optC, string $optD): string {
    $raw = trim($rawAns);
    if ($raw === '') return 'A';

    // 1. Exact match with option text (Hindi or English)
    $normRaw = mb_strtolower($raw);
    if ($normRaw !== '') {
        if ($normRaw === mb_strtolower(trim($optA))) return 'A';
        if ($normRaw === mb_strtolower(trim($optB))) return 'B';
        if ($normRaw === mb_strtolower(trim($optC))) return 'C';
        if ($normRaw === mb_strtolower(trim($optD))) return 'D';
    }

    // 2. Numeric 1, 2, 3, 4
    if ($raw === '1') return 'A';
    if ($raw === '2') return 'B';
    if ($raw === '3') return 'C';
    if ($raw === '4') return 'D';

    // 3. Word boundary or surrounded by brackets e.g. "(B)", "[B]", "Option B", "b."
    if (preg_match('/\b([A-Da-d])\b/', $raw, $m)) {
        return strtoupper($m[1]);
    }
    if (preg_match('/[\(\[\{]([A-Da-d])[\)\]\}]/', $raw, $m)) {
        return strtoupper($m[1]);
    }
    if (preg_match('/^([A-Da-d])[\.\:\)\-]/', $raw, $m)) {
        return strtoupper($m[1]);
    }

    // 4. First character if A, B, C, D
    $firstChar = strtoupper(substr($raw, 0, 1));
    if (in_array($firstChar, ['A', 'B', 'C', 'D'])) {
        return $firstChar;
    }

    // 5. Check if answer text is contained inside one of the options
    if ($normRaw !== '' && strlen($normRaw) >= 3) {
        if (mb_strpos(mb_strtolower($optA), $normRaw) !== false) return 'A';
        if (mb_strpos(mb_strtolower($optB), $normRaw) !== false) return 'B';
        if (mb_strpos(mb_strtolower($optC), $normRaw) !== false) return 'C';
        if (mb_strpos(mb_strtolower($optD), $normRaw) !== false) return 'D';
    }

    return 'A';
}

// Parse CSV file
function detectCsvDelimiter(string $filepath): string {
    $delimiters = [',', ';', "\t", '|'];
    $handle = @fopen($filepath, 'r');
    if (!$handle) return ',';
    $firstLine = fgets($handle);
    fclose($handle);
    if (!$firstLine) return ',';
    $counts = [];
    foreach ($delimiters as $d) {
        $counts[$d] = count(str_getcsv($firstLine, $d));
    }
    arsort($counts);
    $best = key($counts);
    return ($counts[$best] > 1) ? $best : ',';
}

function normalizeCsvHeaders(array $rawHeaders): array {
    $normalized = [];
    foreach ($rawHeaders as $idx => $raw) {
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', trim((string)$raw));
        $clean = strtolower($clean);
        $clean = str_replace(['.', ':', '-', '_', '(', ')', '[', ']', '/', '\\', '#'], ' ', $clean);
        $clean = preg_replace('/\s+/', '_', trim($clean));

        // Question
        if (in_array($clean, ['question', 'question_text', 'questiontext', 'questions', 'prompt', 'prashna', 'prashn', 'sawal', 'q'])
            || in_array($raw, ['प्रश्न', 'सवाल', 'प्रश्‍न', 'प्रशन'])
            || (strpos($clean, 'question') !== false && strpos($clean, 'no') === false && strpos($clean, 'num') === false && strpos($clean, 'id') === false)
            || strpos($raw, 'प्रश्न') !== false) {
            $normalized[$idx] = 'question_text';
        }
        // Option A
        elseif (in_array($clean, ['option_a', 'optiona', 'opt_a', 'opta', 'a', 'option_1', 'option1', 'opt1', 'choice_a', 'choice1', 'vikalp_a', 'vikalp_1'])
            || in_array($raw, ['विकल्प A', 'विकल्प 1', 'विकल्प क', 'उत्तर A', 'उत्तर 1', 'विकल्प_A', 'विकल्प_1'])
            || (strpos($clean, 'option') !== false && (strpos($clean, 'a') !== false || strpos($clean, '1') !== false))) {
            $normalized[$idx] = 'option_a';
        }
        // Option B
        elseif (in_array($clean, ['option_b', 'optionb', 'opt_b', 'optb', 'b', 'option_2', 'option2', 'opt2', 'choice_b', 'choice2', 'vikalp_b', 'vikalp_2'])
            || in_array($raw, ['विकल्प B', 'विकल्प 2', 'विकल्प ख', 'उत्तर B', 'उत्तर 2', 'विकल्प_B', 'विकल्प_2'])
            || (strpos($clean, 'option') !== false && (strpos($clean, 'b') !== false || strpos($clean, '2') !== false))) {
            $normalized[$idx] = 'option_b';
        }
        // Option C
        elseif (in_array($clean, ['option_c', 'optionc', 'opt_c', 'optc', 'c', 'option_3', 'option3', 'opt3', 'choice_c', 'choice3', 'vikalp_c', 'vikalp_3'])
            || in_array($raw, ['विकल्प C', 'विकल्प 3', 'विकल्प ग', 'उत्तर C', 'उत्तर 3', 'विकल्प_C', 'विकल्प_3'])
            || (strpos($clean, 'option') !== false && (strpos($clean, 'c') !== false || strpos($clean, '3') !== false))) {
            $normalized[$idx] = 'option_c';
        }
        // Option D
        elseif (in_array($clean, ['option_d', 'optiond', 'opt_d', 'optd', 'd', 'option_4', 'option4', 'opt4', 'choice_d', 'choice4', 'vikalp_d', 'vikalp_4'])
            || in_array($raw, ['विकल्प D', 'विकल्प 4', 'विकल्प घ', 'उत्तर D', 'उत्तर 4', 'विकल्प_D', 'विकल्प_4'])
            || (strpos($clean, 'option') !== false && (strpos($clean, 'd') !== false || strpos($clean, '4') !== false))) {
            $normalized[$idx] = 'option_d';
        }
        // Correct Answer
        elseif (in_array($clean, ['correct_answer', 'correctanswer', 'correct', 'answer', 'ans', 'right_answer', 'right_ans', 'correct_option', 'correct_opt', 'key', 'ans_key', 'uttar', 'sahi_uttar'])
            || in_array($raw, ['सही उत्तर', 'उत्तर', 'जवाब', 'सही विकल्प', 'सही_उत्तर', 'सही_विकल्प'])
            || strpos($clean, 'correct') !== false
            || strpos($clean, 'answer') !== false
            || strpos($raw, 'उत्तर') !== false) {
            $normalized[$idx] = 'correct_answer';
        }
        // Explanation
        elseif (in_array($clean, ['explanation', 'expl', 'details', 'solution', 'hint', 'vyakhya', 'vivaran', 'spashtikaran', 'notes', 'description'])
            || in_array($raw, ['व्याख्या', 'विवरण', 'स्पष्टीकरण'])
            || strpos($clean, 'expl') !== false
            || strpos($clean, 'solut') !== false
            || strpos($raw, 'व्याख्या') !== false) {
            $normalized[$idx] = 'explanation';
        }
        // Time limit
        elseif (in_array($clean, ['time_limit', 'question_time', 'time', 'question_time_limit', 'timer', 'timer_seconds', 'seconds'])
            || in_array($raw, ['समय', 'टाइम', 'time_limit', 'question_time'])
            || strpos($clean, 'time') !== false || strpos($clean, 'timer') !== false) {
            $normalized[$idx] = 'time_limit';
        }
        // Question Number
        elseif (in_array($clean, ['question_number', 'questionnumber', 'q_no', 'qno', 'q_num', 'num', 'no', 's_no', 'sno', 'sr_no', 'srno', 'sl_no', 'slno', 'serial_no', 'kram', 'id'])
            || in_array($raw, ['क्र.सं.', 'क्रमांक', 'संख्या', 'क्र_सं'])) {
            $normalized[$idx] = 'question_number';
        } else {
            $normalized[$idx] = $clean;
        }
    }
    return $normalized;
}

function parseCsv(string $filepath, ?int $defaultTimeLimit = null): array {
    $questions = [];
    $errors    = [];
    $row_num   = 0;

    // Detect line endings and encoding
    @ini_set('auto_detect_line_endings', '1');

    // Read initial raw bytes to detect BOM and UTF-16
    $raw = @file_get_contents($filepath, false, null, 0, 4);
    if ($raw !== false) {
        if (substr($raw, 0, 2) === "\xFF\xFE") {
            $allContent = file_get_contents($filepath);
            $converted = mb_convert_encoding(substr($allContent, 2), 'UTF-8', 'UTF-16LE');
            file_put_contents($filepath, $converted);
        } elseif (substr($raw, 0, 2) === "\xFE\xFF") {
            $allContent = file_get_contents($filepath);
            $converted = mb_convert_encoding(substr($allContent, 2), 'UTF-8', 'UTF-16BE');
            file_put_contents($filepath, $converted);
        }
    }

    $delimiter = detectCsvDelimiter($filepath);

    if (($handle = fopen($filepath, 'r')) !== false) {
        $header = null;
        while (($row = fgetcsv($handle, 8000, $delimiter)) !== false) {
            $row_num++;
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$row[0]);
            }
            $nonEmpty = array_filter($row, function($v) { return trim((string)$v) !== ''; });
            if (empty($nonEmpty)) continue;

            if ($header === null) {
                $norm = normalizeCsvHeaders($row);
                $hasRequiredHeader = in_array('question_text', $norm) && in_array('option_a', $norm) && in_array('option_b', $norm) && in_array('correct_answer', $norm);
                if ($hasRequiredHeader) {
                    $header = $norm;
                    continue;
                }
            }

            if ($header !== null) {
                $data = [];
                foreach ($header as $colIdx => $colName) {
                    $data[$colName] = $row[$colIdx] ?? '';
                }
            } else {
                $c0 = trim((string)($row[0] ?? ''));
                $hasNumberCol = is_numeric($c0) || preg_match('/^q?\d+[\.\)]?$/i', $c0);
                if ($hasNumberCol && count($row) >= 4) {
                    $data = [
                        'question_number' => $c0 ?: $row_num,
                        'question_text'   => $row[1] ?? '',
                        'option_a'        => $row[2] ?? '',
                        'option_b'        => $row[3] ?? '',
                        'option_c'        => $row[4] ?? '',
                        'option_d'        => $row[5] ?? '',
                        'correct_answer'  => $row[6] ?? '',
                        'explanation'     => $row[7] ?? '',
                        'time_limit'      => $row[8] ?? $defaultTimeLimit,
                    ];
                } else {
                    $data = [
                        'question_number' => $row_num,
                        'question_text'   => $row[0] ?? '',
                        'option_a'        => $row[1] ?? '',
                        'option_b'        => $row[2] ?? '',
                        'option_c'        => $row[3] ?? '',
                        'option_d'        => $row[4] ?? '',
                        'correct_answer'  => $row[5] ?? '',
                        'explanation'     => $row[6] ?? '',
                        'time_limit'      => $row[7] ?? $defaultTimeLimit,
                    ];
                }
            }

            $qualified = array_values($data);
            if ($header !== null && !isset($data['question_text'], $data['option_a'], $data['option_b'], $data['correct_answer'])) {
                $errors[] = "Row {$row_num}: CSV headers are invalid or missing required columns (question, option_a, option_b, correct_answer).";
                continue;
            }

            $optA = trim((string)($data['option_a'] ?? ''));
            $optB = trim((string)($data['option_b'] ?? ''));
            $optC = trim((string)($data['option_c'] ?? ''));
            $optD = trim((string)($data['option_d'] ?? ''));
            $rawAns = trim((string)($data['correct_answer'] ?? ''));
            if (($header !== null || count($qualified) >= 4) && trim((string)($data['question_text'] ?? '')) === '') {
                $errors[] = "Row {$row_num}: Question text is empty.";
                continue;
            }

            $data['correct_answer'] = extractCorrectLetter($rawAns, $optA, $optB, $optC, $optD);
            $data['time_limit'] = $data['time_limit'] ?? $defaultTimeLimit;

            $q = sanitizeQuestion($data);
            $error = validateQuestion($q, $row_num);
            if ($error) {
                $errors[] = $error;
                if (count($errors) >= 30) break;
            } else {
                $questions[] = $q;
            }
        }
        fclose($handle);
    }
    return ['questions' => $questions, 'errors' => $errors];
}

// Parse XLSX file using ZipArchive (no external library needed for basic XLSX)
function parseXlsx(string $filepath, ?int $defaultTimeLimit = null): array {
    // Try using PhpSpreadsheet if available via Composer
    $composer_path = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($composer_path)) {
        require_once $composer_path;
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows  = $sheet->toArray(null, true, true, false);
                // Convert to CSV-like structure
                $tmpFile = tempnam(sys_get_temp_dir(), 'tgquiz_');
                $fp = fopen($tmpFile, 'w');
                foreach ($rows as $row) {
                    fputcsv($fp, $row);
                }
                fclose($fp);
                $result = parseCsv($tmpFile, $defaultTimeLimit);
                unlink($tmpFile);
                return $result;
            } catch (\Exception $e) {
                // Fall through to ZIP method
            }
        }
    }

    // Fallback: Parse XLSX as ZIP (basic, works for simple sheets)
    $questions = [];
    $errors    = [];

    if (!class_exists('ZipArchive')) {
        return [
            'questions' => [],
            'errors' => [
                'Server par PHP Zip extension enable nahi hai jisse XLSX directly read ho sake.',
                '💡 SAMADHAN: Kripya apni Excel file ko "Save As" karke "CSV (Comma delimited) (*.csv)" format mein save karein aur upload karein. CSV bina kisi issue ke 100% fast import ho jayega!'
            ]
        ];
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        return ['questions' => [], 'errors' => ['Could not open XLSX file']];
    }

    $xml_data = $zip->getFromName('xl/worksheets/sheet1.xml');
    $shared    = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();

    if (!$xml_data) {
        return ['questions' => [], 'errors' => ['Could not read XLSX worksheet']];
    }

    // Parse shared strings
    $strings = [];
    if ($shared) {
        $sharedXml = simplexml_load_string($shared);
        if ($sharedXml) {
            foreach ($sharedXml->si as $si) {
                $strings[] = (string)$si->t ?? implode('', (array)$si->r->t ?? []);
            }
        }
    }

    // Parse cells
    $sheetXml = simplexml_load_string($xml_data);
    $rows_data = [];
    if ($sheetXml) {
        foreach ($sheetXml->sheetData->row as $row) {
            $row_vals = [];
            foreach ($row->c as $cell) {
                $cell_type = (string)$cell['t'];
                $val = (string)$cell->v;
                if ($cell_type === 's') {
                    $val = $strings[(int)$val] ?? '';
                }
                $col_letter = preg_replace('/\d/', '', (string)$cell['r']);
                $col_idx    = ord($col_letter) - ord('A');
                $row_vals[$col_idx] = $val;
            }
            ksort($row_vals);
            $rows_data[] = array_values($row_vals);
        }
    }

    // Convert rows to questions (skip header row)
    $header_skipped = false;
    $q_num = 0;
    foreach ($rows_data as $row) {
        if (!$header_skipped) {
            $first = strtolower(trim($row[0] ?? ''));
            if ($first === 'question_number' || $first === 'no' || $first === 'question' || $first === 'sl') {
                $header_skipped = true;
                continue;
            }
        }
        $q_num++;
        // Positional: 0=num, 1=question, 2=A, 3=B, 4=C, 5=D, 6=correct, 7=explanation
        $data = [
            'question_text'  => $row[1] ?? $row[0] ?? '',
            'option_a'       => $row[2] ?? '',
            'option_b'       => $row[3] ?? '',
            'option_c'       => $row[4] ?? '',
            'option_d'       => $row[5] ?? '',
            'correct_answer' => strtoupper(trim($row[6] ?? '')),
            'explanation'    => $row[7] ?? '',
            'time_limit'     => $row[8] ?? $defaultTimeLimit,
        ];
        $q = sanitizeQuestion($data);
        $error = validateQuestion($q, $q_num);
        if ($error) {
            $errors[] = $error;
            if (count($errors) >= 10) break;
        } else {
            $questions[] = $q;
        }
    }

    return ['questions' => $questions, 'errors' => $errors];
}

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['admin_id'];

switch ($action) {

    // --------------------------------------------------------
    // GET list — questions for a quiz set
    // --------------------------------------------------------
    case 'list':
        $quiz_set_id = (int)($_GET['quiz_set_id'] ?? 0);
        if (!$quiz_set_id) { echo json_encode(['success'=>false,'message'=>'quiz_set_id required']); break; }

        // Verify this quiz set belongs to this admin
        $check = $pdo->prepare("SELECT id FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$quiz_set_id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Not found']); break; }

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');

        if ($search) {
            $stmt = $pdo->prepare("SELECT * FROM tg_quiz_questions WHERE quiz_set_id=? AND question_text LIKE ? ORDER BY question_number LIMIT {$limit} OFFSET {$offset}");
            $stmt->execute([$quiz_set_id, "%{$search}%"]);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=? AND question_text LIKE ?");
            $countStmt->execute([$quiz_set_id, "%{$search}%"]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tg_quiz_questions WHERE quiz_set_id=? ORDER BY question_number LIMIT {$limit} OFFSET {$offset}");
            $stmt->execute([$quiz_set_id]);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=?");
            $countStmt->execute([$quiz_set_id]);
        }
        $total = (int)$countStmt->fetchColumn();
        echo json_encode(['success'=>true, 'questions'=>$stmt->fetchAll(), 'total'=>$total, 'page'=>$page, 'limit'=>$limit]);
        break;

    // --------------------------------------------------------
    // POST add — add a single question
    // --------------------------------------------------------
    case 'add':
        validateCsrfToken();
        $quiz_set_id = (int)($_POST['quiz_set_id'] ?? 0);
        if (!$quiz_set_id) { echo json_encode(['success'=>false,'message'=>'quiz_set_id required']); break; }

        $check = $pdo->prepare("SELECT id FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$quiz_set_id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Quiz set not found']); break; }

        $q = sanitizeQuestion($_POST);
        $error = validateQuestion($q, 1);
        if ($error) { echo json_encode(['success'=>false,'message'=>$error]); break; }

        $dupCheck = $pdo->prepare("SELECT id FROM tg_quiz_questions WHERE quiz_set_id=? AND question_text=? AND option_a=? AND option_b=? LIMIT 1");
        $dupCheck->execute([$quiz_set_id, $q['question_text'], $q['option_a'], $q['option_b']]);
        if ($dupCheck->fetchColumn()) {
            echo json_encode(['success'=>false,'message'=>'Duplicate question detected in this quiz set.']);
            break;
        }

        $maxStmt = $pdo->prepare("SELECT MAX(question_number) FROM tg_quiz_questions WHERE quiz_set_id=?");
        $maxStmt->execute([$quiz_set_id]);
        $nextNum = ((int)$maxStmt->fetchColumn()) + 1;

        $ins = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, time_limit) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([$quiz_set_id, $nextNum, $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation'], $q['time_limit']]);

        // Update total_questions count
        $pdo->prepare("UPDATE tg_quiz_sets SET total_questions = (SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=?) WHERE id=?")->execute([$quiz_set_id, $quiz_set_id]);

        echo json_encode(['success'=>true, 'message'=>'Question added', 'id'=>$pdo->lastInsertId(), 'question_number'=>$nextNum]);
        break;

    // --------------------------------------------------------
    // POST update — edit a question
    // --------------------------------------------------------
    case 'update':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $quiz_set_id = (int)($_POST['quiz_set_id'] ?? 0);
        // Verify ownership
        $check = $pdo->prepare("SELECT qq.id FROM tg_quiz_questions qq JOIN tg_quiz_sets qs ON qs.id=qq.quiz_set_id WHERE qq.id=? AND qs.admin_id=?");
        $check->execute([$id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Question not found']); break; }

        $q = sanitizeQuestion($_POST);
        $error = validateQuestion($q, $id);
        if ($error) { echo json_encode(['success'=>false,'message'=>$error]); break; }

        $upd = $pdo->prepare("UPDATE tg_quiz_questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, explanation=?, time_limit=? WHERE id=?");
        $upd->execute([$q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation'], $q['time_limit'], $id]);
        echo json_encode(['success'=>true, 'message'=>'Question updated']);
        break;

    // --------------------------------------------------------
    // POST delete — remove a question
    // --------------------------------------------------------
    case 'delete':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare("SELECT qq.id, qq.quiz_set_id FROM tg_quiz_questions qq JOIN tg_quiz_sets qs ON qs.id=qq.quiz_set_id WHERE qq.id=? AND qs.admin_id=?");
        $check->execute([$id, $admin_id]);
        $row = $check->fetch();
        if (!$row) { echo json_encode(['success'=>false,'message'=>'Question not found']); break; }

        $pdo->prepare("DELETE FROM tg_quiz_questions WHERE id=?")->execute([$id]);
        // Renumber remaining questions
        $pdo->prepare("SET @rank := 0")->execute();
        $pdo->prepare("UPDATE tg_quiz_questions SET question_number = (@rank := @rank + 1) WHERE quiz_set_id=? ORDER BY question_number")->execute([$row['quiz_set_id']]);
        // Update count
        $pdo->prepare("UPDATE tg_quiz_sets SET total_questions=(SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=?) WHERE id=?")->execute([$row['quiz_set_id'], $row['quiz_set_id']]);
        echo json_encode(['success'=>true, 'message'=>'Question deleted']);
        break;

    // --------------------------------------------------------
    // POST duplicate — copy a question
    // --------------------------------------------------------
    case 'duplicate':
        validateCsrfToken();
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare("SELECT qq.*, qq.quiz_set_id FROM tg_quiz_questions qq JOIN tg_quiz_sets qs ON qs.id=qq.quiz_set_id WHERE qq.id=? AND qs.admin_id=?");
        $check->execute([$id, $admin_id]);
        $orig = $check->fetch();
        if (!$orig) { echo json_encode(['success'=>false,'message'=>'Question not found']); break; }

        $maxStmt = $pdo->prepare("SELECT MAX(question_number) FROM tg_quiz_questions WHERE quiz_set_id=?");
        $maxStmt->execute([$orig['quiz_set_id']]);
        $nextNum = ((int)$maxStmt->fetchColumn()) + 1;

        $ins = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, time_limit) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $ins->execute([$orig['quiz_set_id'], $nextNum, $orig['question_text'].' (Copy)', $orig['option_a'], $orig['option_b'], $orig['option_c'], $orig['option_d'], $orig['correct_answer'], $orig['explanation'], $orig['time_limit']]);
        $pdo->prepare("UPDATE tg_quiz_sets SET total_questions=(SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=?) WHERE id=?")->execute([$orig['quiz_set_id'], $orig['quiz_set_id']]);
        echo json_encode(['success'=>true, 'message'=>'Question duplicated', 'new_number'=>$nextNum]);
        break;

    // --------------------------------------------------------
    // POST reorder — move question up/down
    // --------------------------------------------------------
    case 'reorder':
        validateCsrfToken();
        $id        = (int)($_POST['id'] ?? 0);
        $direction = $_POST['direction'] ?? 'up'; // 'up' or 'down'

        $check = $pdo->prepare("SELECT qq.* FROM tg_quiz_questions qq JOIN tg_quiz_sets qs ON qs.id=qq.quiz_set_id WHERE qq.id=? AND qs.admin_id=?");
        $check->execute([$id, $admin_id]);
        $current = $check->fetch();
        if (!$current) { echo json_encode(['success'=>false,'message'=>'Question not found']); break; }

        $target_num = $direction === 'up' ? $current['question_number'] - 1 : $current['question_number'] + 1;
        $swapStmt = $pdo->prepare("SELECT id FROM tg_quiz_questions WHERE quiz_set_id=? AND question_number=?");
        $swapStmt->execute([$current['quiz_set_id'], $target_num]);
        $swap_id = $swapStmt->fetchColumn();

        if ($swap_id) {
            $pdo->prepare("UPDATE tg_quiz_questions SET question_number=? WHERE id=?")->execute([$target_num, $id]);
            $pdo->prepare("UPDATE tg_quiz_questions SET question_number=? WHERE id=?")->execute([$current['question_number'], $swap_id]);
        }
        echo json_encode(['success'=>true, 'message'=>'Reordered']);
        break;

    // --------------------------------------------------------
    // POST upload — CSV or XLSX file upload
    // --------------------------------------------------------
    case 'upload':
        validateCsrfToken();
        $quiz_set_id = (int)($_POST['quiz_set_id'] ?? 0);
        if (!$quiz_set_id) { echo json_encode(['success'=>false,'message'=>'quiz_set_id required']); break; }

        $check = $pdo->prepare("SELECT id FROM tg_quiz_sets WHERE id=? AND admin_id=?");
        $check->execute([$quiz_set_id, $admin_id]);
        if (!$check->fetchColumn()) { echo json_encode(['success'=>false,'message'=>'Quiz set not found']); break; }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $errCode = $_FILES['file']['error'] ?? 'missing';
            $errMsg = 'File upload failed (Code: ' . $errCode . ')';
            if ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE) $errMsg = 'File size exceeds upload limit';
            elseif ($errCode === UPLOAD_ERR_NO_FILE) $errMsg = 'No file was uploaded. Please select a CSV file.';
            echo json_encode(['success'=>false,'message'=>$errMsg]);
            break;
        }

        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'txt'])) {
            echo json_encode(['success'=>false,'message'=>'Only CSV and XLSX files are allowed (got: ' . htmlspecialchars($ext) . ')']);
            break;
        }

        $upload_dir = __DIR__ . '/../../uploads/tg_quiz/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
        $filename   = 'import_' . $admin_id . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $targetFile = $upload_dir . $filename;
        $filepath   = null;
        if (@move_uploaded_file($file['tmp_name'], $targetFile)) {
            $filepath = $targetFile;
        } elseif (@copy($file['tmp_name'], $targetFile)) {
            $filepath = $targetFile;
        } elseif (is_readable($file['tmp_name'])) {
            $filepath = $file['tmp_name'];
        }

        if (!$filepath) {
            echo json_encode(['success'=>false,'message'=>'Could not read uploaded file. Check server write permissions for uploads/ folder.']);
            break;
        }

        $defaultTimeLimit = normalizeTimeLimit($_POST['default_time_limit'] ?? null);
        $parsed = ($ext === 'xlsx') ? parseXlsx($filepath, $defaultTimeLimit) : parseCsv($filepath, $defaultTimeLimit);
        if ($filepath === $targetFile) {
            @unlink($filepath);
        }

        if (!empty($parsed['errors']) && empty($parsed['questions'])) {
            echo json_encode([
                'success' => false,
                'message' => 'File parsing failed. Please check the CSV headers and required columns.',
                'errors'  => $parsed['errors']
            ]);
            break;
        }

        if (empty($parsed['questions'])) {
            echo json_encode(['success'=>false,'message'=>'No valid questions found in file. Make sure columns match: question, option_a, option_b, correct_answer.']);
            break;
        }

        $upload_mode = strtolower(trim($_POST['uploadMode'] ?? $_POST['mode'] ?? 'append'));

        $pdo->beginTransaction();
        try {
            if ($upload_mode === 'replace') {
                $pdo->prepare("DELETE FROM tg_quiz_questions WHERE quiz_set_id=?")->execute([$quiz_set_id]);
                $startNum = 1;
            } else {
                $maxStmt = $pdo->prepare("SELECT MAX(question_number) FROM tg_quiz_questions WHERE quiz_set_id=?");
                $maxStmt->execute([$quiz_set_id]);
                $startNum = ((int)$maxStmt->fetchColumn()) + 1;
            }

            $ins = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation, time_limit) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $imported = 0;
            $failedRows = [];
            foreach ($parsed['questions'] as $i => $q) {
                $dupCheck = $pdo->prepare("SELECT id FROM tg_quiz_questions WHERE quiz_set_id=? AND question_text=? AND option_a=? AND option_b=? LIMIT 1");
                $dupCheck->execute([$quiz_set_id, $q['question_text'], $q['option_a'], $q['option_b']]);
                if ($dupCheck->fetchColumn()) {
                    $failedRows[] = 'Row ' . ($i + 2) . ': Duplicate question found in this quiz set.';
                    continue;
                }
                $ins->execute([$quiz_set_id, $startNum + $imported, $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation'], $q['time_limit']]);
                $imported++;
            }

            $pdo->prepare("UPDATE tg_quiz_sets SET total_questions=(SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=?) WHERE id=?")->execute([$quiz_set_id, $quiz_set_id]);
            $pdo->commit();
            logActivity("Imported {$imported} questions to quiz set #{$quiz_set_id}", 'admin', null, $admin_id);
            echo json_encode([
                'success'  => true,
                'message'  => "✅ {$imported} questions imported successfully.",
                'imported' => $imported,
                'failed'   => count($failedRows),
                'errors'   => array_merge($parsed['errors'] ?? [], $failedRows),
            ]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success'=>false,'message'=>'Database error during import: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
