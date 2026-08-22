<?php
// admin/import/download_sample.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Columns expected by admin/import/index.php
$data = [
    [
        'idiom_or_phrasal_verb',
        'slug',
        'english_meaning',
        'hindi_meaning',
        'explanation',
        'example_sentence',
        'memory_trick',
        'synonyms',
        'antonyms',
        'difficulty',
        'exam_type',
        'meta_title',
        'meta_description',
        'status'
    ],
    [
        'A blessing in disguise',
        'a-blessing-in-disguise',
        'A good thing that seemed bad at first',
        'दुख के भेष में सुख',
        'Used when something seems bad initially but results in something good.',
        'Losing my job was a blessing in disguise because it pushed me to start my own business.',
        'Think of someone wearing a scary disguise but giving you a gift.',
        'hidden blessing, silver lining',
        'curse, misfortune',
        'Easy',
        'SSC CGL, IBPS',
        'A Blessing in Disguise - Meaning and Examples',
        'Learn the meaning, hindi translation, and examples of the idiom a blessing in disguise.',
        'Published'
    ],
    [
        'Break down',
        'break-down',
        'To stop functioning or to lose control emotionally',
        'खराब होना / रो पड़ना',
        'Can refer to a machine failing or a person crying.',
        'My car broke down on the way to work.',
        'When a machine breaks, it goes down.',
        'fail, stop working',
        'work, function',
        'Moderate',
        'Bank PO',
        'Break down - Phrasal Verb Meaning',
        'Learn how to use the phrasal verb break down in sentences.',
        'Published'
    ]
];

// Clean any output buffer before sending headers
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sample_idioms_phrasal_verbs.csv"');

$output = fopen('php://output', 'w');

// Add BOM for UTF-8 (Hindi support in Excel)
fputs($output, "\xEF\xBB\xBF"); 

foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
