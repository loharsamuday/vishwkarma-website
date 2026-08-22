<?php
// admin/includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$requestUri = $_SERVER['REQUEST_URI'];
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-primary-custom admin-sidebar collapse text-white">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'index.php' && strpos($requestUri, '/admin/index.php') !== false) ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/index.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'stories.php' || $currentPage == 'add-story.php' || $currentPage == 'edit-story.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/stories.php">
                    <i class="fas fa-book me-2"></i> Stories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'vocabulary.php' || $currentPage == 'add-vocabulary.php' || $currentPage == 'edit-vocabulary.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/vocabulary.php">
                    <i class="fas fa-language me-2"></i> Vocabulary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'categories.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/categories.php">
                    <i class="fas fa-tags me-2"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'user-stories.php' || $currentPage == 'view-user-story.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/user-stories.php">
                    <i class="fas fa-file-signature me-2"></i> User Stories
                </a>
            </li>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted text-uppercase">
                    <span>Idioms & Phrasal Verbs</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($requestUri, '/admin/idioms') !== false) ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/idioms/">
                    <i class="fas fa-comment-dots me-2"></i> Idioms
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($requestUri, '/admin/phrasal-verbs') !== false) ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/phrasal-verbs/">
                    <i class="fas fa-layer-group me-2"></i> Phrasal Verbs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($requestUri, '/admin/practice') !== false) ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/practice/">
                    <i class="fas fa-question-circle me-2"></i> Practice Questions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($requestUri, '/admin/exam-categories') !== false) ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/exam-categories/">
                    <i class="fas fa-list-alt me-2"></i> Exam Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= (strpos($requestUri, '/admin/import') !== false) ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/import/">
                    <i class="fas fa-file-import me-2"></i> Bulk Import
                </a>
            </li>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted text-uppercase">
                    <span>Users & Audience</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'users.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/users.php">
                    <i class="fas fa-users me-2"></i> Registered Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'subscribers.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/subscribers.php">
                    <i class="fas fa-envelope-open-text me-2"></i> Subscribers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'guest-activity.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/guest-activity.php">
                    <i class="fas fa-user-secret me-2"></i> Guest Activity
                </a>
            </li>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted text-uppercase">
                    <span>System</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'settings.php') ? 'active' : '' ?>" href="<?= EL_BASE_URL ?>admin/settings.php">
                    <i class="fas fa-cogs me-2"></i> Site Settings
                </a>
            </li>
        </ul>
    </div>
</nav>
