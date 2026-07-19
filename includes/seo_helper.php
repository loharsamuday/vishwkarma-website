<?php
// includes/seo_helper.php

/**
 * Generate an SEO friendly slug from a string
 */
function generate_slug($string) {
    $slug = strtolower(trim($string));
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    // Remove multiple consecutive hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    // Remove leading and trailing hyphens
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Create a unique profile slug: name-community-location-id
 */
function create_profile_slug($first_name, $last_name, $city_name, $profile_id) {
    $base = $first_name . ' ' . $last_name . ' vishwakarma ' . $city_name . ' ' . $profile_id;
    return generate_slug($base);
}

/**
 * Generate SEO Meta Tags
 */
function get_profile_meta($profile) {
    $title = "{$profile['first_name']} {$profile['last_name']} | {$profile['age']} Years | {$profile['occupation_name']} | {$profile['city_name']} - Vishwakarma Matrimony";
    
    $desc = "{$profile['age']}-year-old Vishwakarma " . strtolower($profile['gender'] == 'Male' ? 'groom' : 'bride') . " from {$profile['city_name']}. {$profile['occupation_name']}. Looking for an educated Vishwakarma partner. Check full profile and contact today.";
    
    return [
        'title' => $title,
        'description' => $desc
    ];
}

/**
 * Generate JSON-LD Schema for a Person/Profile
 */
function generate_json_ld_profile($profile, $url) {
    $schema = [
        "@context" => "https://schema.org",
        "@type" => "Person",
        "name" => $profile['first_name'] . ' ' . $profile['last_name'],
        "gender" => $profile['gender'],
        "jobTitle" => $profile['occupation_name'],
        "address" => [
            "@type" => "PostalAddress",
            "addressLocality" => $profile['city_name'],
            "addressRegion" => $profile['state_name'],
            "addressCountry" => "IN"
        ],
        "url" => $url
    ];

    return '<script type="application/ld+json">' . json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</script>';
}
?>
