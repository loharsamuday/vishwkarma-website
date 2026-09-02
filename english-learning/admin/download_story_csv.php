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
    fputcsv($output, ['The Honest Woodcutter', 'Beginner', 'Moral Story', 'A woodcutter lost his axe in a river and chose honesty over greed.', 'एक लकड़हारे ने ईमानदारी को लालच से ऊपर चुना।', 'Honesty is always rewarded.']);
    fputcsv($output, ['The Lion and the Mouse', 'Beginner', 'Animal Story', 'A small mouse helped a lion escape from a hunter net.', 'एक छोटे चूहे ने शेर को जाल से बचाया।', 'Kindness is never wasted.']);
    fputcsv($output, ['The Golden Touch', 'Intermediate', 'Moral Story', 'A king learned that wealth cannot replace love and happiness.', 'एक राजा ने सीखा कि धन प्यार और खुशी की जगह नहीं ले सकता।', 'Be careful what you wish for.']);
    fputcsv($output, ['The Lost Map', 'Intermediate', 'Adventure', 'Two friends followed an old map through a forest to find their way home.', 'दो दोस्तों ने जंगल में घर लौटने के लिए पुराने नक्शे का उपयोग किया।', 'Teamwork makes difficult journeys easier.']);
    fputcsv($output, ['A Gift of Time', 'Intermediate', 'Inspirational', 'A busy teacher made time to listen to a student who needed support.', 'एक व्यस्त शिक्षक ने जरूरतमंद छात्र की बात सुनने के लिए समय निकाला।', 'Giving time can be the best gift.']);
    fputcsv($output, ['The Brave Little Seed', 'Beginner', 'Nature Story', 'A tiny seed kept growing despite wind, rain and stones.', 'एक छोटा बीज हवा, बारिश और पत्थरों के बावजूद बढ़ता रहा।', 'Persistence leads to growth.']);
    fputcsv($output, ['The Clever Farmer', 'Advanced', 'Moral Story', 'A farmer solved a village dispute by asking everyone to work together.', 'एक किसान ने सबको साथ काम करने के लिए कहकर गाँव का विवाद सुलझाया।', 'Wisdom solves problems peacefully.']);
    fputcsv($output, ['The Empty Chair', 'Advanced', 'Inspirational', 'A student discovered the value of gratitude after noticing an empty chair in class.', 'एक छात्र ने कक्षा की खाली कुर्सी देखकर कृतज्ञता का महत्व जाना।', 'Value people while they are with you.']);
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
