<?php 
$current_page = basename($_SERVER['PHP_SELF']); 
$global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
function isActiveNav($page, $current_page) {
    return ($page == $current_page) ? 'active text-warning fw-bold' : '';
}
function isServicesActive($current_page) {
    $services = ['community-directory.php', 'business-directory.php', 'jobs.php', 'education.php', 'blood-bank.php'];
    return in_array($current_page, $services) ? 'active text-warning fw-bold' : '';
}
?>
<!-- Fraud Alert Banner -->
<div class="bg-danger text-white py-0 w-100" style="font-size: 0.8rem; line-height: 1.2;">
    <div class="container-fluid px-0">
        <marquee behavior="scroll" direction="left" scrollamount="6" onmouseover="this.stop();" onmouseout="this.start();" class="m-0 py-1">
            <strong>⚠️ FRAUD ALERT:</strong> Please beware of fake profiles or individuals asking for money. The website owner/management is <strong>NOT RESPONSIBLE</strong> for any fraud, financial loss, or damages. <a href="<?= BASE_URL ?>fraud-alert.php" target="_blank" class="text-warning text-decoration-underline fw-bold ms-2">Click here to read our full disclaimer.</a>
        </marquee>
    </div>
</div>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand text-warning fw-bold me-lg-4 d-flex align-items-center" href="<?= BASE_URL ?>">
        <?php if(!empty($global_settings['logo_image'])): ?>
            <img src="<?= BASE_URL ?>uploads/banners/<?= htmlspecialchars($global_settings['logo_image']) ?>" alt="Logo" class="d-inline-block align-text-top me-2" style="max-height: 40px; border-radius: 5px;">
        <?php else: ?>
            <img src="https://placehold.co/40x40/orange/white?text=V" alt="Logo" class="d-inline-block align-text-top me-2" style="border-radius: 5px;">
            <?= SITE_NAME ?>
        <?php endif; ?>
    </a>
    <button class="navbar-toggler mobile-nav-toggle" type="button" id="mobileMenuToggle" aria-controls="mobileNavDrawer" aria-expanded="false" aria-label="Open navigation menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link <?= isActiveNav('index.php', $current_page) ?>" href="<?= BASE_URL ?>">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= isActiveNav('about.php', $current_page) ?>" href="<?= BASE_URL ?>about.php">About Samaj</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= ($current_page == 'matrimony.php') ? 'active text-danger fw-bold text-decoration-underline' : 'text-danger fw-semibold' ?>" href="<?= BASE_URL ?>matrimony.php">Matrimony <i class="fa-solid fa-heart"></i></a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= isServicesActive($current_page) ?>" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Services
          </a>
          <ul class="dropdown-menu" aria-labelledby="servicesDropdown">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>community-directory.php">Community Directory</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>business-directory.php">Business Directory</a></li>
            <li><a class="dropdown-item fw-bold text-primary" href="<?= BASE_URL ?>web-services.php">IT & Web Services <span class="badge bg-danger ms-1">New</span></a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>jobs.php">Jobs</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>education.php">Education & Scholarships</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>blood-bank.php">Blood Bank</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= isActiveNav('events.php', $current_page) ?>" href="<?= BASE_URL ?>events.php">Events</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= isActiveNav('blogs.php', $current_page) ?>" href="<?= BASE_URL ?>blogs.php">Blogs</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= isActiveNav('gallery.php', $current_page) ?>" href="<?= BASE_URL ?>gallery.php">Gallery</a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-nowrap <?= isActiveNav('contact.php', $current_page) ?>" href="<?= BASE_URL ?>contact.php">Contact Us</a>
        </li>
      </ul>
      <div class="d-flex ms-lg-3 mt-3 mt-lg-0 align-items-center">
          <?php 
          if (!isset($global_settings['enable_translation']) || $global_settings['enable_translation'] == '1'): 
          ?>
          <!-- Google Translate Widget -->
          <div id="google_translate_element" class="me-lg-3 mb-3 mb-lg-0"></div>
          <script type="text/javascript">
            function googleTranslateElementInit() {
              new google.translate.TranslateElement({
                  pageLanguage: 'en', 
                  includedLanguages: 'hi,en', 
                  layout: google.translate.TranslateElement.InlineLayout.SIMPLE
              }, 'google_translate_element');

              // Change default "Select Language" text to "English"
              setTimeout(function() {
                  var combo = document.querySelector('.goog-te-combo');
                  if(combo && combo.options[0]) {
                      combo.options[0].innerHTML = 'English';
                  }
              }, 500);
              setTimeout(function() {
                  var combo = document.querySelector('.goog-te-combo');
                  if(combo && combo.options[0] && combo.options[0].innerHTML !== 'English') {
                      combo.options[0].innerHTML = 'English';
                  }
              }, 1500);
            }
          </script>
          <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
          <?php endif; ?>

          <?php if(isset($_SESSION['admin_impersonating']) && $_SESSION['admin_impersonating'] === true): ?>
            <a href="<?= BASE_URL ?>admin/return.php" class="btn btn-sm btn-danger fw-bold me-3 shadow-sm" title="Return to Admin Panel"><i class="fa-solid fa-user-shield me-1"></i> Return Admin</a>
          <?php endif; ?>
          
          <?php if(isset($_SESSION['user_id'])): 
            // Fetch user profile picture for navbar
            $nav_pic_stmt = $pdo->prepare("SELECT profile_pic FROM member_profiles WHERE user_id = ?");
            $nav_pic_stmt->execute([$_SESSION['user_id']]);
            $nav_prof = $nav_pic_stmt->fetch();
            $nav_user_pic = ($nav_prof && $nav_prof['profile_pic']) ? BASE_URL . "uploads/profile/" . $nav_prof['profile_pic'] : "https://placehold.co/30x30/f39c12/white?text=" . strtoupper(substr($_SESSION['first_name'] ?? 'U', 0, 1));

            $notifications = function_exists('getUnreadNotifications') ? getUnreadNotifications($_SESSION['user_id']) : [];
            $total_unread = 0;
            foreach($notifications as $n) $total_unread += $n['unread_count'];
          ?>
            <!-- Notification Bell -->
            <div class="nav-item dropdown me-3">
                <a class="nav-link position-relative px-2" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell fs-5 text-dark"></i>
                    <?php if($total_unread > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                            <?= $total_unread ?>
                            <span class="visually-hidden">unread messages</span>
                        </span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="notificationDropdown" style="min-width: 300px; max-height: 400px; overflow-y: auto;">
                    <li><h6 class="dropdown-header text-warning fw-bold bg-dark text-white py-2"><i class="fa-solid fa-comments me-2"></i> Messages</h6></li>
                    <?php if(empty($notifications)): ?>
                        <li><span class="dropdown-item text-muted text-center py-3 small">No new messages.</span></li>
                    <?php else: ?>
                        <?php foreach($notifications as $notif): 
                            $pic = $notif['profile_pic'] ? BASE_URL . "uploads/profile/" . $notif['profile_pic'] : "https://placehold.co/40x40/f39c12/white?text=U";
                        ?>
                            <li>
                                <a class="dropdown-item py-2 border-bottom" href="<?= BASE_URL ?>discussion.php?user_id=<?= $notif['sender_id'] ?>">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= htmlspecialchars($pic) ?>" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                        <div>
                                            <strong class="d-block text-dark"><?= htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']) ?></strong>
                                            <small class="text-danger fw-bold"><?= $notif['unread_count'] ?> new message<?= $notif['unread_count'] > 1 ? 's' : '' ?></small>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <li><a class="dropdown-item text-center text-primary small py-2 fw-bold" href="<?= BASE_URL ?>discussion.php">View All Discussions</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle btn btn-outline-warning text-dark fw-bold px-3 d-flex align-items-center gap-1" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 20px;">
                    <img src="<?= htmlspecialchars($nav_user_pic) ?>" class="rounded-circle" style="width: 25px; height: 25px; object-fit: cover; border: 1px solid #f39c12;" alt="Profile">
                    <span><?= htmlspecialchars($_SESSION['first_name'] ?? 'User') ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>dashboard.php"><i class="fa-solid fa-gauge-high me-2 text-secondary"></i> Dashboard</a></li>
                    <?php 
                    $nav_role_stmt = $pdo->prepare("SELECT role_id FROM users WHERE id = ?");
                    $nav_role_stmt->execute([$_SESSION['user_id']]);
                    if($nav_role_stmt->fetchColumn() == 4): 
                    ?>
                    <li><a class="dropdown-item py-2 fw-bold text-primary" href="<?= BASE_URL ?>student-chat.php"><i class="fa-solid fa-users-rectangle me-2"></i> My Class Chat</a></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger fw-bold" href="<?= BASE_URL ?>logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                </ul>
            </div>
          <?php else: ?>
            <style>
            @keyframes heartbeat { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } } 
            .btn-vip { animation: heartbeat 1.5s infinite; background: linear-gradient(45deg, #ff0000, #ff7300); color: white; border: none; }
            .btn-vip:hover { color: white; background: linear-gradient(45deg, #ff7300, #ff0000); }
            </style>
            <a href="<?= BASE_URL ?>free-vip.php" class="btn btn-sm btn-vip fw-bold me-2 shadow-sm rounded-pill px-3 text-nowrap"><i class="fa-solid fa-gift me-1"></i> VIP Offer</a>
            <a href="<?= BASE_URL ?>login.php" class="btn btn-outline-warning me-2">Login</a>
            <a href="<?= BASE_URL ?>register.php" class="btn btn-warning text-white fw-bold">Register</a>
          <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div class="mobile-nav-overlay d-lg-none" id="mobileNavOverlay"></div>
<div class="mobile-nav-drawer d-lg-none" id="mobileNavDrawer" role="dialog" aria-label="Mobile navigation">
  <div class="drawer-header">
    <a class="navbar-brand text-warning fw-bold d-flex align-items-center" href="<?= BASE_URL ?>">
      <?php if(!empty($global_settings['logo_image'])): ?>
          <img src="<?= BASE_URL ?>uploads/banners/<?= htmlspecialchars($global_settings['logo_image']) ?>" alt="Logo" class="d-inline-block align-text-top me-2" style="max-height: 40px; border-radius: 5px;">
      <?php else: ?>
          <img src="https://placehold.co/40x40/orange/white?text=V" alt="Logo" class="d-inline-block align-text-top me-2" style="border-radius: 5px;">
          <?= SITE_NAME ?>
      <?php endif; ?>
    </a>
    <button class="btn-close" type="button" id="mobileMenuClose" aria-label="Close navigation menu"></button>
  </div>

  <div class="drawer-nav">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link <?= isActiveNav('index.php', $current_page) ?>" href="<?= BASE_URL ?>">Home</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActiveNav('about.php', $current_page) ?>" href="<?= BASE_URL ?>about.php">About Samaj</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($current_page == 'matrimony.php') ? 'active text-danger fw-bold text-decoration-underline' : 'text-danger fw-semibold' ?>" href="<?= BASE_URL ?>matrimony.php">Matrimony <i class="fa-solid fa-heart"></i></a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActiveNav('events.php', $current_page) ?>" href="<?= BASE_URL ?>events.php">Events</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActiveNav('blogs.php', $current_page) ?>" href="<?= BASE_URL ?>blogs.php">Blogs</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActiveNav('gallery.php', $current_page) ?>" href="<?= BASE_URL ?>gallery.php">Gallery</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isServicesActive($current_page) ?>" href="<?= BASE_URL ?>community-directory.php">Community Services</a>
        <a class="nav-link <?= isActiveNav('web-services.php', $current_page) ?> fw-bold text-primary" href="<?= BASE_URL ?>web-services.php">IT & Web Services <span class="badge bg-danger ms-1">New</span></a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= isActiveNav('contact.php', $current_page) ?>" href="<?= BASE_URL ?>contact.php">Contact Us</a>
      </li>
    </ul>

    <div class="drawer-actions">
      <?php if(isset($_SESSION['user_id'])): ?>
        <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-outline-warning mb-2">Dashboard</a>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-danger text-white">Logout</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>free-vip.php" class="btn btn-vip fw-bold w-100 mb-3 shadow-sm rounded-pill"><i class="fa-solid fa-gift me-1"></i> Claim Free VIP</a>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-outline-warning mb-2">Login</a>
        <a href="<?= BASE_URL ?>register.php" class="btn btn-warning text-white">Register</a>
      <?php endif; ?>
    </div>
  </div>
</div>
