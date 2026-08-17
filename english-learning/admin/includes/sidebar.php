<?php
// admin/includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-primary-custom admin-sidebar collapse text-white">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'index.php') ? 'active' : '' ?>" href="index.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'stories.php' || $currentPage == 'add-story.php' || $currentPage == 'edit-story.php') ? 'active' : '' ?>" href="stories.php">
                    <i class="fas fa-book me-2"></i> Stories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'vocabulary.php' || $currentPage == 'add-vocabulary.php' || $currentPage == 'edit-vocabulary.php') ? 'active' : '' ?>" href="vocabulary.php">
                    <i class="fas fa-language me-2"></i> Vocabulary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'categories.php') ? 'active' : '' ?>" href="categories.php">
                    <i class="fas fa-tags me-2"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'user-stories.php' || $currentPage == 'view-user-story.php') ? 'active' : '' ?>" href="user-stories.php">
                    <i class="fas fa-file-signature me-2"></i> User Stories
                </a>
            </li>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted text-uppercase">
                    <span>Users & Audience</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'users.php') ? 'active' : '' ?>" href="users.php">
                    <i class="fas fa-users me-2"></i> Registered Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'subscribers.php') ? 'active' : '' ?>" href="subscribers.php">
                    <i class="fas fa-envelope-open-text me-2"></i> Subscribers
                </a>
            </li>
            <li class="nav-item mt-3">
                <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted text-uppercase">
                    <span>System</span>
                </h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($currentPage == 'settings.php') ? 'active' : '' ?>" href="settings.php">
                    <i class="fas fa-cogs me-2"></i> Site Settings
                </a>
            </li>
        </ul>
    </div>
</nav>
