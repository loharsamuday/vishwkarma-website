<?php
$files = ['dashboard.php', 'users.php', 'matrimony.php', 'settings.php', 'cms.php', 'cms-edit.php'];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace header part
    $pattern_header = '/<!DOCTYPE html>.*?<div class="main-content">/s';
    $replacement_header = "<?php require_once 'includes/header.php'; ?>\n<div class=\"main-content\">";
    $content = preg_replace($pattern_header, $replacement_header, $content);
    
    // Replace footer part
    $pattern_footer = '/<\/div>\s*<\/body>\s*<\/html>/s';
    $replacement_footer = "<?php require_once 'includes/footer.php'; ?>";
    $content = preg_replace($pattern_footer, $replacement_footer, $content);

    // Also inject the hamburger menu if there's a d-flex heading (like in dashboard and others)
    // we look for `<div class="d-flex justify-content-between` and add the button inside the first heading or so.
    // Actually we can just do a regex replace to insert the button at the beginning of the header row.
    $pattern_dflex = '/<div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">/';
    $replacement_dflex = '<div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">' . "\n" . '        <div class="d-flex align-items-center">' . "\n" . '            <button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>';
    
    // Wait, the div we need to close is the new `<div class="d-flex align-items-center">`. We need to put it before the closing of the left part.
    // Or we can just insert the button before the <h3>
    $pattern_h3 = '/<h3 class="mb-0/';
    $replacement_h3 = '<button class="btn btn-dark d-md-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>' . "\n" . '        <h3 class="mb-0';
    $content = preg_replace($pattern_h3, $replacement_h3, $content);

    file_put_contents($file, $content);
    echo "Refactored $file\n";
}
?>
