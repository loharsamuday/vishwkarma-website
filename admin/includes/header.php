<?php
// admin/includes/header.php
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Admin Dashboard' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .sidebar { height: 100vh; background: #2c3e50; color: white; position: fixed; width: 250px; overflow-y: auto; z-index: 1040; transition: transform 0.3s ease; }
        .sidebar a.nav-item { color: #bdc3c7; text-decoration: none; display: block; padding: 15px 20px; transition: 0.3s; }
        .sidebar a.nav-item:hover, .sidebar a.nav-item.active { background: #34495e; color: #f39c12; border-left: 4px solid #f39c12; }
        .sidebar-category { text-decoration: none; transition: 0.3s; }
        .sidebar-category:hover { color: #f39c12 !important; }
        .main-content { margin-left: 250px; padding: 20px; background: #f4f7f6; min-height: 100vh; transition: margin-left 0.3s ease; }
        
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1030; }
        .sidebar-overlay.show { display: block; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="sidebar" id="sidebar">
    <div class="text-center py-4 border-bottom border-secondary position-relative">
        <h4 class="text-warning fw-bold mb-0">Admin Panel</h4>
        <small>Vishwakarma Samaj</small>
        <button class="btn btn-sm btn-outline-light d-md-none position-absolute top-0 end-0 m-2" id="sidebarCloseBtn"><i class="fa-solid fa-times"></i></button>
    </div>
    <div class="accordion accordion-flush" id="sidebarAccordion">
        <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fa-solid fa-gauge me-2"></i> Dashboard</a>
        
        <a class="sidebar-category text-uppercase text-secondary fw-bold ps-3 pt-3 pb-2 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuUsers" role="button" aria-expanded="<?= in_array($current_page, ['users.php', 'online_users.php', 'contact_messages.php', 'website_quotations.php', 'feedbacks.php']) ? 'true' : 'false' ?>" style="font-size: 0.75rem; letter-spacing: 0.05em;">
            <span>Users & Members</span> <i class="fa-solid fa-chevron-down pe-3"></i>
        </a>
        <div class="collapse <?= in_array($current_page, ['users.php', 'online_users.php', 'contact_messages.php', 'website_quotations.php', 'feedbacks.php']) ? 'show' : '' ?>" id="menuUsers" data-bs-parent="#sidebarAccordion">
            <a href="users.php" class="nav-item <?= $current_page == 'users.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-users me-2"></i> Members</a>
            <a href="online_users.php" class="nav-item <?= $current_page == 'online_users.php' ? 'active text-success' : 'text-success' ?> fw-bold ps-4"><i class="fa-solid fa-satellite-dish me-2 fa-fade"></i> Live Users</a>
            <a href="contact_messages.php" class="nav-item <?= $current_page == 'contact_messages.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-envelope me-2"></i> Enquiries</a>
            <a href="website_quotations.php" class="nav-item <?= $current_page == 'website_quotations.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Quotations</a>
            <a href="feedbacks.php" class="nav-item <?= $current_page == 'feedbacks.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-comments me-2"></i> Feedbacks</a>
        </div>
        
        <a class="sidebar-category text-uppercase text-secondary fw-bold ps-3 pt-3 pb-2 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuModules" role="button" aria-expanded="<?= in_array($current_page, ['matrimony.php']) ? 'true' : 'false' ?>" style="font-size: 0.75rem; letter-spacing: 0.05em;">
            <span>Community Modules</span> <i class="fa-solid fa-chevron-down pe-3"></i>
        </a>
        <div class="collapse <?= in_array($current_page, ['matrimony.php']) ? 'show' : '' ?>" id="menuModules" data-bs-parent="#sidebarAccordion">
            <a href="matrimony.php" class="nav-item <?= $current_page == 'matrimony.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-heart me-2"></i> Matrimony</a>
            <a href="#" class="nav-item ps-4"><i class="fa-solid fa-briefcase me-2"></i> Businesses</a>
            <a href="#" class="nav-item ps-4"><i class="fa-solid fa-calendar-days me-2"></i> Events</a>
        </div>
        
        <a class="sidebar-category text-uppercase text-secondary fw-bold ps-3 pt-3 pb-2 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuMockTests" role="button" aria-expanded="<?= in_array($current_page, ['mt_categories.php', 'mt_exams.php', 'mt_subjects.php', 'mt_questions.php', 'mt_mock_tests.php', 'mt_results.php', 'mt_settings.php']) ? 'true' : 'false' ?>" style="font-size: 0.75rem; letter-spacing: 0.05em;">
            <span>Mock Tests</span> <i class="fa-solid fa-chevron-down pe-3"></i>
        </a>
        <div class="collapse <?= in_array($current_page, ['mt_categories.php', 'mt_exams.php', 'mt_subjects.php', 'mt_questions.php', 'mt_mock_tests.php', 'mt_results.php', 'mt_settings.php']) ? 'show' : '' ?>" id="menuMockTests" data-bs-parent="#sidebarAccordion">
            <a href="mt_categories.php" class="nav-item <?= $current_page == 'mt_categories.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-list me-2"></i> Categories & Exams</a>
            <a href="mt_subjects.php" class="nav-item <?= $current_page == 'mt_subjects.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-book me-2"></i> Subjects & Topics</a>
            <a href="mt_questions.php" class="nav-item <?= $current_page == 'mt_questions.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-clipboard-question me-2"></i> Question Bank</a>
            <a href="mt_mock_tests.php" class="nav-item <?= $current_page == 'mt_mock_tests.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-file-signature me-2"></i> Mock Tests</a>
            <a href="mt_results.php" class="nav-item <?= $current_page == 'mt_results.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-chart-bar me-2"></i> Results & Reports</a>
        </div>
        
        <a class="sidebar-category text-uppercase text-secondary fw-bold ps-3 pt-3 pb-2 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuFinance" role="button" aria-expanded="<?= in_array($current_page, ['payments.php']) ? 'true' : 'false' ?>" style="font-size: 0.75rem; letter-spacing: 0.05em;">
            <span>Finance & Revenue</span> <i class="fa-solid fa-chevron-down pe-3"></i>
        </a>
        <div class="collapse <?= in_array($current_page, ['payments.php']) ? 'show' : '' ?>" id="menuFinance" data-bs-parent="#sidebarAccordion">
            <a href="payments.php" class="nav-item <?= $current_page == 'payments.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-indian-rupee-sign me-2"></i> Payments</a>
        </div>
        
        <a class="sidebar-category text-uppercase text-secondary fw-bold ps-3 pt-3 pb-2 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuContent" role="button" aria-expanded="<?= in_array($current_page, ['cms.php', 'cms-edit.php', 'blogs.php', 'gallery.php', 'ui_images.php']) ? 'true' : 'false' ?>" style="font-size: 0.75rem; letter-spacing: 0.05em;">
            <span>Content Management</span> <i class="fa-solid fa-chevron-down pe-3"></i>
        </a>
        <div class="collapse <?= in_array($current_page, ['cms.php', 'cms-edit.php', 'blogs.php', 'gallery.php', 'ui_images.php']) ? 'show' : '' ?>" id="menuContent" data-bs-parent="#sidebarAccordion">
            <a href="cms.php" class="nav-item <?= $current_page == 'cms.php' || $current_page == 'cms-edit.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-file-alt me-2"></i> CMS / Pages</a>
            <a href="ui_images.php" class="nav-item <?= $current_page == 'ui_images.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-image text-warning me-2"></i> UI Images</a>
            <a href="blogs.php" class="nav-item <?= $current_page == 'blogs.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-blog me-2"></i> Manage Blogs</a>
            <a href="gallery.php" class="nav-item <?= $current_page == 'gallery.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-images me-2"></i> Manage Gallery</a>
        </div>
        
        <a class="sidebar-category text-uppercase text-secondary fw-bold ps-3 pt-3 pb-2 d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#menuSystem" role="button" aria-expanded="<?= in_array($current_page, ['activity_logs.php', 'tools.php', 'settings.php', 'admin_users.php', 'git_manager.php']) ? 'true' : 'false' ?>" style="font-size: 0.75rem; letter-spacing: 0.05em;">
            <span>System & Config</span> <i class="fa-solid fa-chevron-down pe-3"></i>
        </a>
        <div class="collapse <?= in_array($current_page, ['activity_logs.php', 'tools.php', 'settings.php', 'admin_users.php', 'git_manager.php']) ? 'show' : '' ?>" id="menuSystem" data-bs-parent="#sidebarAccordion">
            <a href="git_manager.php" class="nav-item <?= $current_page == 'git_manager.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-cloud-arrow-up text-info me-2"></i> Live Deploy</a>
            <a href="admin_users.php" class="nav-item <?= $current_page == 'admin_users.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-user-shield me-2"></i> Admin Staff</a>
            <a href="activity_logs.php" class="nav-item <?= $current_page == 'activity_logs.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-clipboard-list me-2"></i> Activity Logs</a>
            <a href="tools.php" class="nav-item <?= $current_page == 'tools.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-screwdriver-wrench me-2"></i> Advanced Tools</a>
            <a href="settings.php" class="nav-item <?= $current_page == 'settings.php' ? 'active' : '' ?> ps-4"><i class="fa-solid fa-cog me-2"></i> Site Settings</a>
        </div>
        
        <div class="mt-4 border-top border-secondary opacity-50"></div>
        <a href="profile.php" class="nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?> mb-2"><i class="fa-solid fa-user-circle me-2"></i> My Profile</a>
        <a href="logout.php" class="nav-item text-danger mb-4"><i class="fa-solid fa-sign-out-alt me-2"></i> Logout</a>
    </div>
</div>

