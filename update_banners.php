<?php
$files = [
    'community-directory.php' => ['key' => 'banner_community', 'text' => 'Community+Directory'],
    'contact.php' => ['key' => 'banner_contact', 'text' => 'Contact+Us'],
    'events.php' => ['key' => 'banner_events', 'text' => 'Community+Events'],
    'gallery.php' => ['key' => 'banner_gallery', 'text' => 'Image+Gallery'],
    'web-services.php' => ['key' => 'banner_web_services', 'text' => 'IT+%26+Web+Services'],
    'blogs.php' => ['key' => 'banner_blogs', 'text' => 'Community+Blogs'],
    'blood-bank.php' => ['key' => 'banner_blood_bank', 'text' => 'Blood+Bank', 'color' => 'e74c3c/ffffff'],
    'business-directory.php' => ['key' => 'banner_business', 'text' => 'Business+Directory'],
    'matrimony.php' => ['key' => 'banner_matrimony', 'text' => 'Matrimony', 'color' => 'e84393/ffffff'],
    'education.php' => ['key' => 'banner_education', 'text' => 'Education+%26+Career'],
    'jobs.php' => ['key' => 'banner_jobs', 'text' => 'Jobs+Portal'],
];

foreach ($files as $filename => $info) {
    if (!file_exists($filename)) continue;
    $content = file_get_contents($filename);
    
    $color = isset($info['color']) ? $info['color'] : '2c3e50/f39c12';
    $key = $info['key'];
    $text = $info['text'];
    
    $replacement = "<?php \$banner_img = function_exists('getUiImage') ? getUiImage('$key', 'https://placehold.co/1920x400/$color?text=$text') : 'https://placehold.co/1920x400/$color?text=$text'; ?>\n" .
                   "<div class=\"page-banner mb-4\">\n" .
                   "    <img src=\"<?= htmlspecialchars(\$banner_img) ?>\" class=\"img-fluid w-100 shadow-sm\" style=\"max-height: 400px; object-fit: cover;\">\n" .
                   "</div>";
    
    // Pattern to replace
    $pattern = '/<\?php \$global_settings = function_exists\(\'getGlobalSettings\'\) \? getGlobalSettings\(\) : \[\]; \?>\s*<\?php if\(\!empty\(\$global_settings\[\'.*?\'\]\)\): \?>\s*<div class="page-banner mb-4">\s*<img src="<\?= BASE_URL \?>uploads\/banners\/<\?= htmlspecialchars\(\$global_settings\[\'.*?\'\]\) \?>" class="img-fluid w-100 shadow-sm" style="max-height: 400px; object-fit: cover;">\s*<\/div>\s*<\?php endif; \?>/s';
    
    $new_content = preg_replace($pattern, $replacement, $content);
    
    if ($new_content !== $content) {
        file_put_contents($filename, $new_content);
        echo "Updated $filename\n";
    } else {
        echo "No changes needed for $filename or pattern not matched.\n";
    }
}
?>
