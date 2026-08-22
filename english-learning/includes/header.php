<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$page_title = isset($page_title) ? $page_title : 'English Learning Story Platform';
$seo_title = isset($seo_title) ? $seo_title : $page_title;
$seo_desc = isset($seo_desc) ? $seo_desc : 'Improve your English reading, vocabulary and writing skills with our interactive stories.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($seo_title) ?></title>
    <meta name="description" content="<?= escape($seo_desc) ?>">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= EL_BASE_URL ?>assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary-custom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= EL_BASE_URL ?>index.php">
            <i class="fas fa-book-reader me-2"></i>EnglishStories
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="offcanvas-lg offcanvas-start bg-primary-custom text-white" tabindex="-1" id="mainNav" aria-labelledby="mainNavLabel">
            <div class="offcanvas-header border-bottom border-light border-opacity-10">
                <h5 class="offcanvas-title fw-bold" id="mainNavLabel"><i class="fas fa-book-reader me-2"></i>EnglishStories</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#mainNav" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= EL_BASE_URL ?>index.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle fw-bold text-warning" href="#" id="dashboardDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Smart Dashboard
                        </a>
                        <ul class="dropdown-menu shadow border-0" aria-labelledby="dashboardDropdown">
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>dashboard/"><i class="fas fa-home text-primary me-2"></i>Main Dashboard</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>login.php?msg=login_required" onclick="alert('Please log in or sign up to access the Main Dashboard.');"><i class="fas fa-home text-primary me-2"></i>Main Dashboard</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Study Tools</h6></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>study-dashboard.php"><i class="fas fa-chart-line text-primary me-2"></i>Study Dashboard</a></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>daily-routine.php"><i class="fas fa-calendar-day text-success me-2"></i>Daily Routine</a></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>study-time.php"><i class="fas fa-stopwatch text-warning me-2"></i>Study Time</a></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>daily-target.php"><i class="fas fa-bullseye text-danger me-2"></i>Daily Target</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= EL_BASE_URL ?>stories.php">Stories</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="vocabDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Learning
                        </a>
                        <ul class="dropdown-menu shadow border-0" aria-labelledby="vocabDropdown">
                            <li><h6 class="dropdown-header">Dictionary</h6></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/">Idioms & Phrasal Verbs</a></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>vocabulary.php">Story Vocabulary</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">Practice & Review</h6></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>my-memory/"><i class="fas fa-brain text-primary me-2"></i>My Memory</a></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>revision/"><i class="fas fa-sync text-warning me-2"></i>Smart Revision</a></li>
                            <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>idioms-phrasal-verbs/practice.php">Practice Questions</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= EL_BASE_URL ?>categories.php">Categories</a>
                    </li>
                </ul>
                <form class="d-flex me-lg-3 mt-3 mt-lg-0" action="<?= EL_BASE_URL ?>search.php" method="GET">
                    <input class="form-control me-2 border-0 bg-light" type="search" name="q" placeholder="Search..." aria-label="Search" required>
                    <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
                </form>
                
                <ul class="navbar-nav mb-2 mb-lg-0 me-lg-3 mt-3 mt-lg-0">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle fw-bold text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?= escape($_SESSION['user_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?= EL_BASE_URL ?>profile.php"><i class="fas fa-id-card me-2 text-muted"></i>My Account</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="<?= EL_BASE_URL ?>logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= EL_BASE_URL ?>login.php">Log In</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-sm btn-outline-light ms-lg-2 mt-2 mt-lg-0 px-3" href="<?= EL_BASE_URL ?>register.php" style="padding-top: 0.4rem;">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <a href="<?= EL_BASE_URL ?>write-story.php" class="btn btn-success shadow-sm mt-3 mt-lg-0"><i class="fas fa-pen me-2"></i>Write a Story</a>
            </div>
        </div>
    </div>
</nav>

<main class="min-vh-100 pb-5">
