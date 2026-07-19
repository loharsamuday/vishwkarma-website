<?php
// router.php - The central front controller for SEO friendly URLs

require_once 'config/config.php';
// require_once 'includes/db.php'; // Ensure DB is included if needed

// Get the requested URL
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';

if (empty($url)) {
    // Should not reach here if .htaccess is correct, but just in case
    require 'index.php';
    exit;
}

// Split the URL into segments
$segments = explode('/', $url);
$base_segment = strtolower($segments[0]);

// 1. Matrimony Individual Profiles: /brides/bihar/patna/priya-vishwakarma-1254
// Or /grooms/uttar-pradesh/varanasi/rahul-vishwakarma-5489
if ($base_segment === 'brides' || $base_segment === 'grooms') {
    
    // Check if it's an individual profile (usually has 4 segments: brides/state/city/slug)
    // or just a dynamic search page (brides/state or brides/state/city)
    
    $num_segments = count($segments);
    
    if ($num_segments >= 4) {
        // Individual Profile
        $slug = $segments[$num_segments - 1]; // The last segment is the slug
        $_GET['slug'] = $slug;
        require 'matrimony-profile-seo.php';
        exit;
    } else {
        // Dynamic Landing Page or Search Page (e.g., /brides/bihar)
        $_GET['gender'] = ($base_segment === 'brides') ? 'Female' : 'Male';
        if (isset($segments[1])) $_GET['state_slug'] = $segments[1];
        if (isset($segments[2])) $_GET['city_slug'] = $segments[2];
        
        require 'matrimony-dynamic-search.php';
        exit;
    }
}

// 2. Generic Dynamic Landing Pages: /software-engineer-brides
// These might be mapped in the ent_seo_dynamic_pages table
// We can check if the url matches a known SEO slug
// (For this mockup, we'll route it to a handler if it contains specific keywords)
if (strpos($url, '-brides') !== false || strpos($url, '-grooms') !== false) {
    $_GET['seo_slug'] = $url;
    require 'matrimony-dynamic-search.php';
    exit;
}

// 3. Fallback: Check if it's a valid PHP file
if (file_exists($url . '.php')) {
    require $url . '.php';
    exit;
}

// 4. 404 Not Found
header("HTTP/1.0 404 Not Found");
echo "<h1>404 Not Found</h1>";
echo "<p>The page you requested could not be found.</p>";
exit;
?>
