<?php
$files_to_add_banner = [
    'blood-bank.php' => ['key' => 'banner_blood_bank', 'text' => 'Blood+Bank', 'color' => 'e74c3c/ffffff'],
    'business-directory.php' => ['key' => 'banner_business', 'text' => 'Business+Directory'],
    'matrimony.php' => ['key' => 'banner_matrimony', 'text' => 'Matrimony', 'color' => 'e84393/ffffff'],
    'education.php' => ['key' => 'banner_education', 'text' => 'Education+%26+Career'],
    'jobs.php' => ['key' => 'banner_jobs', 'text' => 'Jobs+Portal'],
];

foreach ($files_to_add_banner as $filename => $info) {
    if (!file_exists($filename)) continue;
    $content = file_get_contents($filename);
    
    // Check if we already added it
    if (strpos($content, $info['key']) !== false) {
        echo "Already added to $filename\n";
        continue;
    }
    
    $color = isset($info['color']) ? $info['color'] : '2c3e50/f39c12';
    $key = $info['key'];
    $text = $info['text'];
    
    $banner_html = "<?php \$banner_img = function_exists('getUiImage') ? getUiImage('$key', 'https://placehold.co/1920x400/$color?text=$text') : 'https://placehold.co/1920x400/$color?text=$text'; ?>\n" .
                   "<div class=\"page-banner mb-4\">\n" .
                   "    <img src=\"<?= htmlspecialchars(\$banner_img) ?>\" class=\"img-fluid w-100 shadow-sm\" style=\"max-height: 400px; object-fit: cover;\">\n" .
                   "</div>\n";
    
    // Insert after require_once 'includes/navbar.php'; or require_once 'includes/header.php';
    if (strpos($content, "require_once 'includes/navbar.php';\n?>") !== false) {
        $content = str_replace("require_once 'includes/navbar.php';\n?>", "require_once 'includes/navbar.php';\n?>\n" . $banner_html, $content);
    } elseif (strpos($content, "require_once 'includes/navbar.php';") !== false) {
        $content = str_replace("require_once 'includes/navbar.php';", "require_once 'includes/navbar.php';\n?>\n" . $banner_html . "<?php\n", $content);
    } elseif (strpos($content, "require 'includes/navbar.php';\n?>") !== false) {
         $content = str_replace("require 'includes/navbar.php';\n?>", "require 'includes/navbar.php';\n?>\n" . $banner_html, $content);
    }
    
    file_put_contents($filename, $content);
    echo "Added banner to $filename\n";
}
?>
