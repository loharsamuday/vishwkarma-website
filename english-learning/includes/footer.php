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
                    <li><a href="<?= EL_BASE_URL ?>index.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Home</a></li>
                    <li><a href="<?= EL_BASE_URL ?>dashboard/" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-warning fw-bold text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Smart Dashboard</a></li>
                    <li><a href="<?= EL_BASE_URL ?>stories.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Stories</a></li>
                    <li><a href="<?= EL_BASE_URL ?>vocabulary.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Vocabulary</a></li>
                </ul>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <h5 class="text-white mb-3">Account</h5>
                <ul class="list-unstyled mb-0 lh-lg">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li><a href="<?= EL_BASE_URL ?>profile.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">My Account</a></li>
                        <li><a href="<?= EL_BASE_URL ?>write-story.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Write a Story</a></li>
                        <li><a href="<?= EL_BASE_URL ?>logout.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Log Out</a></li>
                    <?php else: ?>
                        <li><a href="<?= EL_BASE_URL ?>login.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Log In</a></li>
                        <li><a href="<?= EL_BASE_URL ?>register.php" class="text-white-50 text-decoration-none transition" onmouseover="this.className='text-white text-decoration-none transition'" onmouseout="this.className='text-white-50 text-decoration-none transition'">Sign Up</a></li>
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

<!-- Unified Help & Feedback Button -->
<button class="floating-feedback-btn" data-bs-toggle="modal" data-bs-target="#feedbackModal" title="Help & Feedback">
    <i class="fas fa-headset"></i>
</button>

<!-- Support & Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary-custom text-white border-0">
        <h5 class="modal-title fw-bold" id="feedbackModalLabel"><i class="fas fa-headset me-2"></i>Support & Feedback</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        
        <!-- WhatsApp Section -->
        <div class="bg-light p-3 rounded-3 mb-4 text-center border-start border-4 border-success">
            <h6 class="fw-bold mb-2 text-dark">Need immediate assistance?</h6>
            <p class="small text-muted mb-3">Chat with our support team directly on WhatsApp.</p>
            <a href="https://wa.me/<?= escape($c_wa) ?>" target="_blank" class="btn btn-success fw-bold rounded-pill shadow-sm px-4">
                <i class="fab fa-whatsapp fa-lg me-2"></i>Chat on WhatsApp
            </a>
        </div>

        <div class="position-relative mb-4 text-center">
            <hr class="text-muted opacity-25">
            <span class="position-absolute top-50 start-50 translate-middle bg-white px-3 small text-muted fw-bold">OR</span>
        </div>

        <!-- Feedback Form -->
        <h6 class="fw-bold text-center mb-3 text-dark">Leave us your feedback</h6>
        <form id="feedbackForm" onsubmit="event.preventDefault(); alert('Thank you for your valuable feedback! We appreciate it.'); bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide(); this.reset();">
            <div class="mb-3 text-center">
                <div class="text-warning fs-3 d-flex justify-content-center gap-2" id="starRating">
                    <i class="far fa-star" onclick="rateStar(1)" style="cursor:pointer;"></i>
                    <i class="far fa-star" onclick="rateStar(2)" style="cursor:pointer;"></i>
                    <i class="far fa-star" onclick="rateStar(3)" style="cursor:pointer;"></i>
                    <i class="far fa-star" onclick="rateStar(4)" style="cursor:pointer;"></i>
                    <i class="far fa-star" onclick="rateStar(5)" style="cursor:pointer;"></i>
                </div>
                <input type="hidden" id="ratingValue" required>
            </div>
            <div class="mb-3">
                <textarea class="form-control bg-light border-0 shadow-none" rows="3" placeholder="Tell us what you love or what we can do better..." required></textarea>
            </div>
            <div class="d-grid mt-3">
                <button type="submit" class="btn btn-outline-primary fw-bold rounded-pill">Submit Feedback</button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
function rateStar(stars) {
    document.getElementById('ratingValue').value = stars;
    let starElements = document.getElementById('starRating').children;
    for(let i=0; i<5; i++) {
        if(i < stars) {
            starElements[i].classList.remove('far');
            starElements[i].classList.add('fas');
        } else {
            starElements[i].classList.remove('fas');
            starElements[i].classList.add('far');
        }
    }
}
</script>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= EL_BASE_URL ?>assets/js/script.js"></script>
<script src="<?= EL_BASE_URL ?>assets/js/offline-sync.js"></script>
<script>
    // Service Worker Registration
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('<?= EL_BASE_URL ?>sw.js')
                .then(reg => console.log('ServiceWorker registered:', reg.scope))
                .catch(err => console.log('ServiceWorker registration failed:', err));
        });
    }
</script>
</body>
</html>
