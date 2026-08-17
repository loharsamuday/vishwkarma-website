<?php
// admin/download_sample_csv.php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Define the header and sample data
$data = [
    ['Word', 'Part of Speech', 'Hindi Meaning', 'English Meaning', 'Synonym', 'Antonym', 'Example Sentence'],
    ['Abundant', 'Adjective', 'प्रचुर / भरपूर', 'Available in large quantities', 'Plentiful', 'Scarce', 'The region has abundant natural resources.'],
    ['Reluctant', 'Adjective', 'अनिच्छुक', 'Unwilling or hesitant', 'Unwilling', 'Willing', 'He was reluctant to accept the offer.']
];

// Set headers to force download as CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sample_vocabulary.csv');

// Output to memory
$output = fopen('php://output', 'w');

// Important for Excel to recognize UTF-8 characters properly (like Hindi text)
fputs($output, "\xEF\xBB\xBF"); 

foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
