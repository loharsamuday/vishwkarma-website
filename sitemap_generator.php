<?php
// sitemap_generator.php - Script to generate sitemap.xml dynamically

require_once 'config/config.php';
// require_once 'includes/db.php'; // DB Connection

// Normally we would query the database for all active profiles and dynamic pages
// e.g., SELECT slug, updated_at FROM ent_matrimony_profiles WHERE status = 'active'
// e.g., SELECT url_slug, created_at FROM ent_seo_dynamic_pages WHERE is_active = 1

$base_url = BASE_URL;
$date_today = date('Y-m-d');

header("Content-Type: text/xml;charset=utf-8");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Add static important pages
$static_pages = [
    '',
    'about.php',
    'contact.php',
    'matrimony-register.php'
];

foreach ($static_pages as $page) {
    echo "  <url>\n";
    echo "    <loc>{$base_url}{$page}</loc>\n";
    echo "    <lastmod>{$date_today}</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>1.0</priority>\n";
    echo "  </url>\n";
}

// Add Dynamic Landing Pages Mockups
$dynamic_pages = [
    'brides/bihar',
    'grooms/uttar-pradesh',
    'software-engineer-brides',
    'government-job-grooms'
];

foreach ($dynamic_pages as $page) {
    echo "  <url>\n";
    echo "    <loc>{$base_url}{$page}</loc>\n";
    echo "    <lastmod>{$date_today}</lastmod>\n";
    echo "    <changefreq>daily</changefreq>\n";
    echo "    <priority>0.9</priority>\n";
    echo "  </url>\n";
}

// Add Individual Profiles Mockups
$profiles = [
    'brides/bihar/patna/priya-vishwakarma-789',
    'grooms/bihar/patna/rahul-vishwakarma-123'
];

foreach ($profiles as $profile) {
    echo "  <url>\n";
    echo "    <loc>{$base_url}{$profile}</loc>\n";
    echo "    <lastmod>{$date_today}</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>';
?>
