<?php
// matrimony-dynamic-search.php - SEO Landing & Advanced Search

require_once 'config/config.php';
require_once 'includes/seo_helper.php';

// Detect context
$gender = $_GET['gender'] ?? '';
$state_slug = $_GET['state_slug'] ?? '';
$city_slug = $_GET['city_slug'] ?? '';
$seo_slug = $_GET['seo_slug'] ?? '';

$page_title = "Vishwakarma Matrimony";
$h1_title = "Find Your Perfect Vishwakarma Match";
$meta_desc = "Vishwakarma Matrimony Search - Find brides and grooms.";

if ($seo_slug) {
    // We would look up $seo_slug in `ent_seo_dynamic_pages` table
    // For mockup:
    $display_title = ucwords(str_replace('-', ' ', $seo_slug));
    $page_title = "{$display_title} | Vishwakarma Matrimony";
    $h1_title = "{$display_title}";
    $meta_desc = "Search thousands of {$display_title} on Vishwakarma Matrimony.";
} else {
    // Generate dynamically based on state/city
    $g_text = ($gender === 'Female') ? 'Brides' : ($gender === 'Male' ? 'Grooms' : 'Brides & Grooms');
    $location_text = "";
    if ($city_slug) {
        $location_text = " in " . ucwords(str_replace('-', ' ', $city_slug));
    } elseif ($state_slug) {
        $location_text = " in " . ucwords(str_replace('-', ' ', $state_slug));
    }
    
    $h1_title = "Vishwakarma {$g_text}{$location_text}";
    $page_title = "{$h1_title} | Free Matrimony Profile Search";
    $meta_desc = "Looking for Vishwakarma {$g_text}{$location_text}? View verified profiles, photos, and connect for free.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($meta_desc) ?>">
    <link rel="canonical" href="<?= BASE_URL . ltrim($_GET['url'] ?? '', '/') ?>" />
    
    <!-- CSS Dependencies (Bootstrap for quick styling, custom styles) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .filter-sidebar { background: #f8f9fa; padding: 20px; border-radius: 8px; }
        .profile-card { transition: transform 0.2s; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .profile-card:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .skeleton { background: #eee; background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%); border-radius: 5px; background-size: 200% 100%; animation: 1.5s shine linear infinite; }
        @keyframes shine { to { background-position-x: -200%; } }
        
        @keyframes pulseChatDanger {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            70% { transform: scale(1.05); box-shadow: 0 0 0 8px rgba(220, 53, 69, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }
        .btn-chat-animate {
            animation: pulseChatDanger 2s infinite;
        }
    </style>
</head>
<body>
    
    <!-- Navbar placeholder -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>">Vishwakarma Samaj</a>
        </div>
    </nav>

    <div class="container my-5">
        <h1 class="mb-4"><?= htmlspecialchars($h1_title) ?></h1>
        
        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-md-3">
                <div class="filter-sidebar">
                    <h5>Advanced Search</h5>
                    <form id="ajaxSearchForm">
                        <input type="hidden" name="base_gender" value="<?= htmlspecialchars($gender) ?>">
                        <input type="hidden" name="base_state" value="<?= htmlspecialchars($state_slug) ?>">
                        <input type="hidden" name="base_city" value="<?= htmlspecialchars($city_slug) ?>">
                        
                        <div class="mb-3">
                            <label>Age</label>
                            <div class="d-flex gap-2">
                                <input type="number" name="age_min" class="form-control" placeholder="Min" min="18" max="70">
                                <input type="number" name="age_max" class="form-control" placeholder="Max" min="18" max="70">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Height</label>
                            <select name="height_min" class="form-select">
                                <option value="">Any</option>
                                <option value="152">5'0"</option>
                                <option value="165">5'5"</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label>Marital Status</label>
                            <select name="marital_status" class="form-select">
                                <option value="">Any</option>
                                <option value="Never Married">Never Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label>Education</label>
                            <select name="education" class="form-select">
                                <option value="">Any</option>
                                <option value="B.Tech">B.Tech</option>
                                <option value="MBA">MBA</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label>Occupation Category</label>
                            <select name="occupation" class="form-select">
                                <option value="">Any</option>
                                <option value="Government">Government Job</option>
                                <option value="Private">Private Job</option>
                            </select>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="verified_only" value="1" id="verifiedOnly">
                            <label class="form-check-label" for="verifiedOnly">Verified Profiles Only</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                    </form>
                </div>
            </div>
            
            <!-- Search Results -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span id="resultsCount">Loading...</span>
                    <select class="form-select w-auto" id="sortSelect">
                        <option value="recent">Recently Joined</option>
                        <option value="premium">Premium First</option>
                        <option value="age_asc">Age (Low to High)</option>
                    </select>
                </div>
                
                <div id="resultsContainer" class="row g-4">
                    <!-- AJAX results will be injected here -->
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-danger" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('ajaxSearchForm');
            const resultsContainer = document.getElementById('resultsContainer');
            const resultsCount = document.getElementById('resultsCount');
            const sortSelect = document.getElementById('sortSelect');
            
            function loadResults() {
                const formData = new FormData(form);
                formData.append('sort', sortSelect.value);
                
                const queryString = new URLSearchParams(formData).toString();
                
                // Show loader
                resultsContainer.innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-danger" role="status"></div></div>';
                
                fetch('api/matrimony_search_ajax.php?' + queryString)
                    .then(response => response.json())
                    .then(data => {
                        resultsCount.textContent = data.total + " Profiles Found";
                        if (data.html) {
                            resultsContainer.innerHTML = data.html;
                        } else {
                            resultsContainer.innerHTML = '<div class="col-12"><div class="alert alert-info">No profiles found matching your criteria.</div></div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching results:', error);
                        resultsContainer.innerHTML = '<div class="col-12 text-danger">Error loading profiles.</div>';
                    });
            }
            
            // Initial load
            loadResults();
            
            // Listen for form submit
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                loadResults();
            });
            
            // Listen for sort change
            sortSelect.addEventListener('change', loadResults);
        });
    </script>
</body>
</html>
