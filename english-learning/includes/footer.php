<?php
// includes/footer.php
$footer_settings = [];
try {
    $stmt_footer = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    while ($row = $stmt_footer->fetch()) {
        $footer_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Fail silently if table doesn't exist yet
}
$c_email = $footer_settings['contact_email'] ?? 'support@englishstories.com';
$c_phone = $footer_settings['contact_phone'] ?? '+91 98765 43210';
$c_wa    = $footer_settings['contact_whatsapp'] ?? '919876543210';
$s_fb    = $footer_settings['social_facebook'] ?? '#';
$s_tw    = $footer_settings['social_twitter'] ?? '#';
$s_ig    = $footer_settings['social_instagram'] ?? '#';
$s_yt    = $footer_settings['social_youtube'] ?? '#';
?>
</main>

<footer class="py-5 mt-auto border-top border-5 border-primary" style="background-color: #0f172a; color: #f8fafc;">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-3 col-md-6">
                <h4 class="text-white fw-bold"><i class="fas fa-book-reader me-2 text-primary-custom"></i>EnglishStories</h4>
                <p class="text-white-50 mt-3 mb-0">Improve your English reading, vocabulary, and writing skills with our interactive story platform.</p>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <h5 class="text-white mb-3">Quick Links</h5>
                <ul class="list-unstyled mb-0 lh-lg">
                    <li><a href="index.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Home</a></li>
                    <li><a href="dashboard.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-warning fw-bold text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Smart Dashboard</a></li>
                    <li><a href="stories.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Stories</a></li>
                    <li><a href="vocabulary.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Vocabulary</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <h5 class="text-white mb-3">Account</h5>
                <ul class="list-unstyled mb-0 lh-lg">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li><a href="profile.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">My Account</a></li>
                        <li><a href="write-story.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Write a Story</a></li>
                        <li><a href="logout.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Log Out</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Log In</a></li>
                        <li><a href="register.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <h5 class="text-white mb-3">Contact Support</h5>
                <ul class="list-unstyled mb-0 lh-lg">
                    <li class="text-white-50 mb-2"><i class="fas fa-envelope me-2 text-warning"></i> <?= escape($c_email) ?></li>
                    <li class="text-white-50 mb-2"><i class="fas fa-phone-alt me-2 text-warning"></i> <?= escape($c_phone) ?></li>
                    <li class="mt-3">
                        <a href="https://wa.me/<?= escape($c_wa) ?>" target="_blank" class="btn btn-sm btn-outline-success border-2 rounded-pill px-3">
                            <i class="fab fa-whatsapp fa-lg me-1"></i> WhatsApp Support
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <hr class="border-secondary opacity-25 mt-5 mb-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start text-white-50 small">
                &copy; <?= date('Y') ?> English Learning Story Platform. All rights reserved.
            </div>
            <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
                <a href="<?= escape($s_fb) ?>" class="text-white-50 me-3 text-decoration-none fs-5 transition" onmouseover="this.className='text-white me-3 text-decoration-none fs-5 transition'" onmouseout="this.className='text-white-50 me-3 text-decoration-none fs-5 transition'"><i class="fab fa-facebook"></i></a>
                <a href="<?= escape($s_tw) ?>" class="text-white-50 me-3 text-decoration-none fs-5 transition" onmouseover="this.className='text-white me-3 text-decoration-none fs-5 transition'" onmouseout="this.className='text-white-50 me-3 text-decoration-none fs-5 transition'"><i class="fab fa-twitter"></i></a>
                <a href="<?= escape($s_ig) ?>" class="text-white-50 me-3 text-decoration-none fs-5 transition" onmouseover="this.className='text-white me-3 text-decoration-none fs-5 transition'" onmouseout="this.className='text-white-50 me-3 text-decoration-none fs-5 transition'"><i class="fab fa-instagram"></i></a>
                <a href="<?= escape($s_yt) ?>" class="text-white-50 text-decoration-none fs-5 transition" onmouseover="this.className='text-white text-decoration-none fs-5 transition'" onmouseout="this.className='text-white-50 text-decoration-none fs-5 transition'"><i class="fab fa-youtube"></i></a>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/script.js"></script>
</body>
</html>
