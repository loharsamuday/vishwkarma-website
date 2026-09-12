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

function sanitizeQuestion(array $q): array {
    return [
        'question_text'  => strip_tags(trim($q['question_text'] ?? $q['question'] ?? '')),
        'option_a'       => strip_tags(trim($q['option_a'] ?? '')),
        'option_b'       => strip_tags(trim($q['option_b'] ?? '')),
        'option_c'       => strip_tags(trim($q['option_c'] ?? '')),
        'option_d'       => strip_tags(trim($q['option_d'] ?? '')),
        'correct_answer' => strtoupper(trim($q['correct_answer'] ?? '')),
        'explanation'    => strip_tags(trim($q['explanation'] ?? '')),
    ];
}

function validateQuestion(array $q, int $num): ?string {
    if (empty($q['question_text'])) return "Q{$num}: Question text is empty";
    if (empty($q['option_a']))     return "Q{$num}: Option A is empty";
    if (empty($q['option_b']))     return "Q{$num}: Option B is empty";
    if (!in_array($q['correct_answer'], ['A','B','C','D'])) return "Q{$num}: Correct answer must be A, B, C, or D (got: {$q['correct_answer']})";
    // If correct is C or D, option_c/option_d must exist
    if ($q['correct_answer'] === 'C' && empty($q['option_c'])) return "Q{$num}: Correct answer is C but Option C is empty";
    if ($q['correct_answer'] === 'D' && empty($q['option_d'])) return "Q{$num}: Correct answer is D but Option D is empty";
    return null;
}

// Parse CSV file
function parseCsv(string $filepath): array {
    $questions = [];
    $errors    = [];
    $row_num   = 0;

    if (($handle = fopen($filepath, 'r')) !== false) {
        $header = null;
        while (($row = fgetcsv($handle, 2000, ',')) !== false) {
            $row_num++;
            // Skip empty rows
            if (count(array_filter($row)) === 0) continue;
            // Detect header row
            if ($header === null) {
                $header_lower = array_map('strtolower', array_map('trim', $row));
                if (in_array('question', $header_lower) || in_array('question_text', $header_lower)) {
                    $header = $header_lower;
                    continue;
                }
                // No header — treat as data with positional columns
                // Columns: question_number, question, option_a, option_b, option_c, option_d, correct_answer, explanation
            }

            if ($header !== null) {
                $data = array_combine($header, array_pad($row, count($header), ''));
                // Map alternate column names
                $data['question_text']  = $data['question'] ?? $data['question_text'] ?? '';
                $data['correct_answer'] = strtoupper(trim($data['correct_answer'] ?? $data['answer'] ?? ''));
            } else {
                // Positional: 0=num, 1=question, 2=A, 3=B, 4=C, 5=D, 6=correct, 7=explanation
                $data = [
                    'question_number' => $row[0] ?? $row_num,
                    'question_text'   => $row[1] ?? '',
                    'option_a'        => $row[2] ?? '',
                    'option_b'        => $row[3] ?? '',
                    'option_c'        => $row[4] ?? '',
                    'option_d'        => $row[5] ?? '',
                    'correct_answer'  => strtoupper(trim($row[6] ?? '')),
                    'explanation'     => $row[7] ?? '',
                ];
            }

            $q = sanitizeQuestion($data);
            $error = validateQuestion($q, $row_num);
            if ($error) {
                $errors[] = $error;
                if (count($errors) >= 10) break; // Stop at 10 errors
            } else {
                $questions[] = $q;
            }
        }
        fclose($handle);
    }
    return ['questions' => $questions, 'errors' => $errors];
}

// Parse XLSX file using ZipArchive (no external library needed for basic XLSX)
function parseXlsx(string $filepath): array {
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
                $result = parseCsv($tmpFile);
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
            $stmt = $pdo->prepare("SELECT * FROM tg_quiz_questions WHERE quiz_set_id=? AND question_text LIKE ? ORDER BY question_number LIMIT ? OFFSET ?");
            $stmt->execute([$quiz_set_id, "%{$search}%", $limit, $offset]);
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=? AND question_text LIKE ?");
            $countStmt->execute([$quiz_set_id, "%{$search}%"]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tg_quiz_questions WHERE quiz_set_id=? ORDER BY question_number LIMIT ? OFFSET ?");
            $stmt->execute([$quiz_set_id, $limit, $offset]);
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

        // Get next question number
        $maxStmt = $pdo->prepare("SELECT MAX(question_number) FROM tg_quiz_questions WHERE quiz_set_id=?");
        $maxStmt->execute([$quiz_set_id]);
        $nextNum = ((int)$maxStmt->fetchColumn()) + 1;

        $ins = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute([$quiz_set_id, $nextNum, $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation']]);

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

        $upd = $pdo->prepare("UPDATE tg_quiz_questions SET question_text=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_answer=?, explanation=? WHERE id=?");
        $upd->execute([$q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation'], $id]);
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

        $ins = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute([$orig['quiz_set_id'], $nextNum, $orig['question_text'].' (Copy)', $orig['option_a'], $orig['option_b'], $orig['option_c'], $orig['option_d'], $orig['correct_answer'], $orig['explanation']]);
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
            echo json_encode(['success'=>false,'message'=>'File upload failed']);
            break;
        }

        $file = $_FILES['file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'])) {
            echo json_encode(['success'=>false,'message'=>'Only CSV and XLSX files are allowed']);
            break;
        }

        // Validate MIME
        $allowed_mime = ['text/csv','text/plain','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/octet-stream'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        // Note: CSV files may show as text/plain — that's fine

        $upload_dir = __DIR__ . '/../../uploads/tg_quiz/';
        $filename   = 'import_' . $admin_id . '_' . time() . '.' . $ext;
        $filepath   = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            echo json_encode(['success'=>false,'message'=>'Could not save uploaded file']);
            break;
        }

        $parsed = ($ext === 'xlsx') ? parseXlsx($filepath) : parseCsv($filepath);
        @unlink($filepath); // Clean up immediately

        if (!empty($parsed['errors']) && empty($parsed['questions'])) {
            echo json_encode(['success'=>false,'message'=>'File parsing failed','errors'=>$parsed['errors']]);
            break;
        }

        if (empty($parsed['questions'])) {
            echo json_encode(['success'=>false,'message'=>'No valid questions found in file']);
            break;
        }

        // Bulk insert
        $maxStmt = $pdo->prepare("SELECT MAX(question_number) FROM tg_quiz_questions WHERE quiz_set_id=?");
        $maxStmt->execute([$quiz_set_id]);
        $startNum = ((int)$maxStmt->fetchColumn()) + 1;

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare("INSERT INTO tg_quiz_questions (quiz_set_id, question_number, question_text, option_a, option_b, option_c, option_d, correct_answer, explanation) VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($parsed['questions'] as $i => $q) {
                $ins->execute([$quiz_set_id, $startNum + $i, $q['question_text'], $q['option_a'], $q['option_b'], $q['option_c'], $q['option_d'], $q['correct_answer'], $q['explanation']]);
            }
            $pdo->prepare("UPDATE tg_quiz_sets SET total_questions=(SELECT COUNT(*) FROM tg_quiz_questions WHERE quiz_set_id=?) WHERE id=?")->execute([$quiz_set_id, $quiz_set_id]);
            $pdo->commit();
            $imported = count($parsed['questions']);
            logActivity("Imported {$imported} questions to quiz set #{$quiz_set_id}", 'admin', null, $admin_id);
            echo json_encode([
                'success'  => true,
                'message'  => "✅ {$imported} questions imported successfully!",
                'imported' => $imported,
                'errors'   => $parsed['errors'],
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,'message'=>'Database error during import']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
