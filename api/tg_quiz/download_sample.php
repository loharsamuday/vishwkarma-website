<?php
// api/tg_quiz/download_sample.php
// Download sample CSV or XLSX template for quiz questions
// Admin-only

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$format = strtolower(trim($_GET['format'] ?? 'csv'));
if (!in_array($format, ['csv', 'xlsx'])) $format = 'csv';

// Sample questions data (Hindi + English)
$sample_rows = [
    // Header
    ['question_number', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer', 'explanation'],
    // Sample rows
    [1, 'भारत की राजधानी क्या है?', 'मुंबई', 'नई दिल्ली', 'पटना', 'जयपुर', 'B', 'भारत की राजधानी नई दिल्ली है।'],
    [2, 'RBI की स्थापना कब हुई?', '1930', '1935', '1940', '1947', 'B', 'RBI की स्थापना 1 अप्रैल 1935 को हुई।'],
    [3, 'भारत का राष्ट्रीय खेल कौन सा है?', 'क्रिकेट', 'हॉकी', 'कबड्डी', 'फुटबॉल', 'B', 'भारत का राष्ट्रीय खेल हॉकी है।'],
    [4, 'Who is known as Father of Computer?', 'Alan Turing', 'Charles Babbage', 'Bill Gates', 'Steve Jobs', 'B', 'Charles Babbage is known as the Father of Computer.'],
    [5, 'Constitution of India came into effect on?', '15 Aug 1947', '26 Jan 1950', '26 Nov 1949', '2 Oct 1950', 'B', 'The Constitution of India came into effect on 26 January 1950.'],
];

// ─── CSV Format ───────────────────────────────────────────────────────────────
if ($format === 'csv') {
    $filename = 'quiz_sample_format.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility (especially for Hindi text)
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    foreach ($sample_rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ─── XLSX Format ─────────────────────────────────────────────────────────────
if ($format === 'xlsx') {
    $filename = 'quiz_sample_format.xlsx';

    // Try PhpSpreadsheet first
    $composer_path = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($composer_path)) {
        require_once $composer_path;
        if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            generateXlsxWithPhpSpreadsheet($sample_rows, $filename);
            exit;
        }
    }

    // Fallback: Generate XLSX manually using OpenXML ZIP structure
    generateXlsxManual($sample_rows, $filename);
    exit;
}

// ─── XLSX via PhpSpreadsheet ──────────────────────────────────────────────────
function generateXlsxWithPhpSpreadsheet(array $rows, string $filename) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Quiz Questions');

    foreach ($rows as $rowIndex => $row) {
        foreach ($row as $colIndex => $value) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
        }
    }

    // Style header row
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2196F3']],
    ];
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

    // Auto-width columns
    foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}

// ─── XLSX Manual (no library needed) ─────────────────────────────────────────
function generateXlsxManual(array $rows, string $filename) {
    // Build sheet XML
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheetXml .= '<sheetData>';

    $sharedStrings = [];
    $cellData = [];

    // Build shared strings index
    foreach ($rows as $rowIdx => $row) {
        $cellData[$rowIdx] = [];
        foreach ($row as $colIdx => $val) {
            $valStr = (string)$val;
            if (!isset($sharedStrings[$valStr])) {
                $sharedStrings[$valStr] = count($sharedStrings);
            }
            $cellData[$rowIdx][$colIdx] = $sharedStrings[$valStr];
        }
    }

    // Build sheet rows
    foreach ($cellData as $rowIdx => $cells) {
        $sheetXml .= '<row r="' . ($rowIdx + 1) . '">';
        foreach ($cells as $colIdx => $strIdx) {
            $colLetter = chr(ord('A') + $colIdx);
            $cellRef = $colLetter . ($rowIdx + 1);
            $sheetXml .= '<c r="' . $cellRef . '" t="s"><v>' . $strIdx . '</v></c>';
        }
        $sheetXml .= '</row>';
    }
    $sheetXml .= '</sheetData></worksheet>';

    // Build shared strings XML
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">';
    foreach ($sharedStrings as $str => $idx) {
        $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($str, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $ssXml .= '</sst>';

    // Standard XLSX files (ZIP-based)
    $tmpFile = tempnam(sys_get_temp_dir(), 'tgquiz_xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // [Content_Types].xml
    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
        '</Types>'
    );

    // _rels/.rels
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>'
    );

    // xl/workbook.xml
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="Quiz Questions" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>'
    );

    // xl/_rels/workbook.xml.rels
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
        '</Relationships>'
    );

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);
    $zip->close();

    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($tmpFile);
    unlink($tmpFile);
}
