<?php
// admin/download_story_csv.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$type = $_GET['type'] ?? 'story';

if ($type === 'story') {
    $filename = 'sample_story_import.csv';
} else {
    $filename = 'sample_vocabulary_import.csv';
}

// Clean any output buffer before sending headers
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// Add BOM for UTF-8 (Hindi support in Excel)
fputs($output, "\xEF\xBB\xBF"); 

if ($type === 'story') {
    // Headers
    fputcsv($output, ['story_title', 'story_level', 'story_category', 'story_content', 'hindi_meaning', 'moral']);
    
    // Sample Data
    fputcsv($output, [
        'The Clever Fox', 
        'Beginner', 
        'Animal Story', 
        'Once there was a clever fox wandering in the forest...', 
        'एक चालाक लोमड़ी थी...', 
        'Intelligence is better than strength.'
    ]);
    fputcsv($output, [
        'The Thirsty Crow', 
        'Beginner', 
        'Moral Story', 
        'A thirsty crow found a pitcher with little water...', 
        'एक प्यासे कौवे को थोड़ा पानी मिला...', 
        'Where there is a will, there is a way.'
    ]);
} else {
    // Headers
    fputcsv($output, ['story_id', 'word', 'meaning_hindi', 'meaning_english', 'part_of_speech', 'example_sentence', 'synonym', 'antonym']);
    
    // Sample Data
    fputcsv($output, [
        '101', 
        'clever', 
        'चालाक', 
        'quick to understand', 
        'Adjective', 
        'The clever fox escaped.', 
        'smart', 
        'foolish'
    ]);
    fputcsv($output, [
        '101', 
        'forest', 
        'जंगल', 
        'a large area covered with trees', 
        'Noun', 
        'The fox lived in the forest.', 
        'woodland', 
        ''
    ]);
}

fclose($output);
exit();
