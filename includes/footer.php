    <style>
        .footer-custom {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            border-top: 4px solid #f39c12;
            position: relative;
            overflow: hidden;
        }
        .footer-link {
            color: #d1d5db;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            margin-bottom: 8px;
        }
        .footer-link:hover {
            color: #f39c12;
            transform: translateX(8px);
        }
        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-size: 18px;
        }
        .social-icon:hover {
            transform: translateY(-5px) scale(1.15);
            background: #f39c12;
            color: #111827 !important;
            box-shadow: 0 10px 15px -3px rgba(243, 156, 18, 0.4);
        }
        .footer-contact-icon {
            width: 25px;
            text-align: center;
            color: #f39c12;
        }
    </style>
    <!-- Footer -->
    <footer class="footer-custom text-white pt-5 pb-3 mt-5">
        <div class="container text-center text-md-left">
            <div class="row text-center text-md-left">
                <div class="col-md-4 col-lg-4 col-xl-4 mx-auto mt-3 mb-4 text-center text-md-start" data-aos="fade-up" data-aos-delay="100">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-warning fw-bold"><i class="fa-solid fa-users-rays me-2"></i><?= SITE_NAME ?></h5>
                    <p class="text-light" style="line-height: 1.8;">Connecting the Vishwakarma Samaj globally. Explore matrimony, business directory, and community services all in one place.</p>
                </div>

                <div class="col-md-3 col-lg-2 col-xl-2 mx-auto mt-3 mb-4 text-center text-md-start" data-aos="fade-up" data-aos-delay="200">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-warning fw-bold">Quick Links</h5>
                    <div class="d-flex flex-column align-items-center align-items-md-start">
                        <a href="<?= BASE_URL ?>about.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>About Us</a>
                        <a href="<?= BASE_URL ?>matrimony.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>Matrimony</a>
                        <a href="<?= BASE_URL ?>business-directory.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>Business Directory</a>
                        <a href="<?= BASE_URL ?>web-services.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>Web Services</a>
                        <a href="<?= BASE_URL ?>contact.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>Contact</a>
                        <a href="<?= BASE_URL ?>terms.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>Terms & Conditions</a>
                        <a href="<?= BASE_URL ?>privacy.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>Privacy Policy</a>
                        <a href="<?= BASE_URL ?>refund.php" class="footer-link"><i class="fa-solid fa-angle-right me-2 text-warning small"></i>Refund Policy</a>
                    </div>
                </div>

                <?php
                if (!isset($global_settings)) {
                    $global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
                }
                $contact_address = $global_settings['contact_address'] ?? 'New Delhi, India';
                $contact_phone = $global_settings['contact_phone'] ?? '+91 9876543210';
                $contact_email = $global_settings['contact_email'] ?? ADMIN_EMAIL;
                $facebook = $global_settings['social_facebook'] ?? '#';
                $twitter = $global_settings['social_twitter'] ?? '#';
                $instagram = $global_settings['social_instagram'] ?? '#';
                ?>
                <div class="col-md-4 col-lg-3 col-xl-3 mx-auto mt-3 mb-4 text-center text-md-start" data-aos="fade-up" data-aos-delay="300">
                    <h5 class="text-uppercase mb-4 font-weight-bold text-warning fw-bold">Contact Us</h5>
                    <div class="d-flex flex-column align-items-center align-items-md-start">
                        <p class="mb-3 d-flex align-items-center"><i class="fas fa-home footer-contact-icon"></i> <span class="ms-2"><?= htmlspecialchars($contact_address) ?></span></p>
                        <p class="mb-3 d-flex align-items-center"><i class="fas fa-envelope footer-contact-icon"></i> <span class="ms-2"><?= htmlspecialchars($contact_email) ?></span></p>
                        <p class="mb-3 d-flex align-items-center"><i class="fas fa-phone footer-contact-icon"></i> <span class="ms-2"><?= htmlspecialchars($contact_phone) ?></span></p>
                    </div>
                </div>
            </div>

            <hr class="mb-4">
            
            <div class="row align-items-center text-center text-md-start">
                <div class="col-md-7 col-lg-8 mb-3 mb-md-0">
                    <?php
                    $footer_cms = null;
                    if (function_exists('getCmsContent')) {
                        $footer_cms = getCmsContent('footer');
                    }
                    if ($footer_cms && !empty(trim(strip_tags($footer_cms['content'])))):
                    ?>
                        <?= $footer_cms['content'] ?>
                    <?php else: ?>
                        <p class="mb-0">© <?= date('Y') ?> Copyright:
                            <a href="<?= BASE_URL ?>" style="text-decoration: none;">
                                <strong class="text-warning"><?= SITE_NAME ?></strong>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-5 col-lg-4 text-center text-md-end mt-3 mt-md-0">
                    <ul class="list-unstyled list-inline mb-0">
                        <?php if($facebook !== '#'): ?>
                        <li class="list-inline-item me-2">
                            <a href="<?= htmlspecialchars($facebook) ?>" class="text-white social-icon" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        </li>
                        <?php endif; ?>
                        <?php if($twitter !== '#'): ?>
                        <li class="list-inline-item me-2">
                            <a href="<?= htmlspecialchars($twitter) ?>" class="text-white social-icon" target="_blank"><i class="fab fa-twitter"></i></a>
                        </li>
                        <?php endif; ?>
                        <?php if($instagram !== '#'): ?>
                        <li class="list-inline-item">
                            <a href="<?= htmlspecialchars($instagram) ?>" class="text-white social-icon" target="_blank"><i class="fab fa-instagram"></i></a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });
    </script>
    <!-- Custom JS -->
    <script src="<?= BASE_URL ?>assets/js/app.js"></script>
    
    <?php 
    $promo_mode = $global_settings['promo_mode'] ?? 'disabled';
    if ($promo_mode !== 'disabled' && !isset($_SESSION['user_id'])): 
        $p_title = $global_settings['popup_title'] ?? "Wait! Don't Miss Out";
        $p_body = $global_settings['popup_body'] ?? "Join 1000+ Vishwakarma families today. Find your perfect match, grow your business, and stay connected with your roots!";
        $p_htitle = $global_settings['popup_highlight_title'] ?? "100% FREE Registration";
        $p_hsub = $global_settings['popup_highlight_sub'] ?? "For a limited time only!";
        
        $p_orig_price = floatval($global_settings['promo_original_price'] ?? 0);
        $p_disc_price = floatval($global_settings['promo_discounted_price'] ?? 0);
        $p_delay = intval($global_settings['promo_delay_seconds'] ?? 5) * 1000; // in milliseconds
        $p_validity = $global_settings['promo_validity_date'] ?? '';
        
        $discount_pct = 0;
        if($p_orig_price > 0 && $p_orig_price > $p_disc_price) {
            $discount_pct = round((($p_orig_price - $p_disc_price) / $p_orig_price) * 100);
        }
        
        $show_promo = true;
        if (!empty($p_validity)) {
            $valid_timestamp = strtotime($p_validity);
            if (time() > $valid_timestamp) {
                $show_promo = false; // Offer expired
            }
        }
        
        if ($show_promo):
    ?>
    <!-- Registration Promo Modal -->
    <div class="modal fade" id="promoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg" style="background: linear-gradient(135deg, #1a252f, #2c3e50); border: 2px solid #f39c12 !important; color: white;">
                <div class="modal-header border-0 pb-0 justify-content-end">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center pt-0 px-4 pb-5">
                    <div class="mb-3">
                        <i class="fa-solid fa-gift fa-4x text-warning fa-bounce"></i>
                    </div>
                    <h2 class="fw-bold text-warning mb-3"><?= htmlspecialchars($p_title) ?></h2>
                    <p class="fs-5 mb-4"><?= htmlspecialchars($p_body) ?></p>
                    <div class="bg-white text-dark p-3 rounded-3 mb-4 mx-auto shadow-sm" style="max-width: 90%;">
                        <h5 class="fw-bold text-danger mb-1 fa-fade"><?= htmlspecialchars($p_htitle) ?></h5>
                        <small class="text-muted fw-bold d-block mb-3"><?= htmlspecialchars($p_hsub) ?></small>
                        
                        <?php if ($promo_mode === 'paid' && ($p_orig_price > 0 || $p_disc_price > 0)): ?>
                        <div class="d-flex justify-content-center align-items-center gap-3 mb-2 p-2 bg-light rounded border border-warning">
                            <?php if ($p_orig_price > 0): ?>
                                <span class="text-muted fs-5"><del>₹<?= number_format($p_orig_price) ?></del></span>
                            <?php endif; ?>
                            
                            <span class="text-success fs-2 fw-bold">₹<?= number_format($p_disc_price) ?></span>
                            
                            <?php if ($discount_pct > 0): ?>
                                <span class="badge bg-danger rounded-pill fs-6"><?= $discount_pct ?>% OFF</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($promo_mode === 'paid' && !empty($p_validity)): ?>
                            <div class="text-danger fw-bold mt-2 small" id="promoCountdown">
                                <i class="fa-regular fa-clock me-1"></i> Offer ends: <?= date('d M Y, h:i A', $valid_timestamp) ?>
                                <br><span id="timerText" class="text-dark"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a href="<?= BASE_URL ?>register.php" class="btn btn-warning btn-lg fw-bold w-100 rounded-pill shadow hover-scale text-dark fs-5">Register Now <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    <p class="mt-4 mb-0 small"><a href="<?= BASE_URL ?>login.php" class="text-light text-decoration-none border-bottom border-warning pb-1">Already a member? Login here</a></p>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        if (!sessionStorage.getItem('promoShown')) {
            setTimeout(function() {
                var promoModalEl = document.getElementById('promoModal');
                if (promoModalEl) {
                    var promoModal = new bootstrap.Modal(promoModalEl);
                    promoModal.show();
                    sessionStorage.setItem('promoShown', '1');
                }
            }, <?= $p_delay ?>); 
        }
        
        <?php if ($promo_mode === 'paid' && !empty($p_validity)): ?>
        // Simple countdown logic
        var countDownDate = new Date("<?= date('Y-m-d\TH:i:s', $valid_timestamp) ?>").getTime();
        var timerInterval = setInterval(function() {
            var now = new Date().getTime();
            var distance = countDownDate - now;
            
            if (distance < 0) {
                clearInterval(timerInterval);
                document.getElementById("timerText").innerHTML = "EXPIRED";
                document.getElementById("timerText").className = "text-danger text-uppercase fw-bold";
            } else {
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                var timeStr = "";
                if(days > 0) timeStr += days + "d ";
                timeStr += hours + "h " + minutes + "m " + seconds + "s left";
                document.getElementById("timerText").innerHTML = timeStr;
            }
        }, 1000);
        <?php endif; ?>
    });
    </script>
    <?php 
        endif; // end $show_promo
    endif; // end $promo_mode !== 'disabled'
    ?>
    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

    <!-- Unified Support Floating Action Menu -->
    <?php
    if (!isset($global_settings)) {
        $global_settings = function_exists('getGlobalSettings') ? getGlobalSettings() : [];
    }
    $whatsapp_number = $global_settings['whatsapp_number'] ?? ($global_settings['contact_phone'] ?? '');
    $whatsapp_clean = preg_replace('/[^0-9]/', '', $whatsapp_number);
    ?>
    <div class="dropup position-fixed" style="bottom: 30px; right: 30px; z-index: 1050;">
        <button type="button" class="feedback-fab border-0" data-bs-toggle="dropdown" aria-expanded="false" title="Support & Feedback">
            <i class="fa-solid fa-headset"></i>
        </button>
        <ul class="dropdown-menu mb-2 shadow border-0 rounded-4 p-2" style="min-width: 200px;">
            <?php if (!empty($whatsapp_clean)): ?>
            <li>
                <a class="dropdown-item py-2 fw-bold rounded" href="https://wa.me/<?= $whatsapp_clean ?>" target="_blank">
                    <i class="fa-brands fa-whatsapp text-success me-2 fs-5 align-middle"></i> WhatsApp Support
                </a>
            </li>
            <li><hr class="dropdown-divider opacity-25"></li>
            <?php endif; ?>
            <li>
                <a class="dropdown-item py-2 fw-bold rounded" href="#" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                    <i class="fa-solid fa-comment-dots text-warning me-2 fs-5 align-middle"></i> Share Feedback
                </a>
            </li>
        </ul>
    </div>

    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning border-0 text-dark">
                    <h5 class="modal-title fw-bold" id="feedbackModalLabel"><i class="fa-solid fa-star me-2"></i> Your Feedback Matters</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="feedbackResponse" class="mb-3"></div>
                    <form id="feedbackForm">
                        <div class="mb-3 text-center">
                            <label class="form-label fw-bold d-block mb-1">How would you rate your experience?</label>
                            <div class="star-rating fs-2">
                                <input type="radio" id="star5" name="rating" value="5" checked /><label for="star5" title="5 stars"><i class="fa-solid fa-star"></i></label>
                                <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars"><i class="fa-solid fa-star"></i></label>
                                <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars"><i class="fa-solid fa-star"></i></label>
                                <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars"><i class="fa-solid fa-star"></i></label>
                                <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star"><i class="fa-solid fa-star"></i></label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Feedback Type</label>
                            <select name="feedback_type" class="form-select border-warning" required>
                                <option value="Suggestion">Suggestion (Idea to improve)</option>
                                <option value="Bug">Bug / Issue (Something is broken)</option>
                                <option value="Compliment">Compliment (You liked something)</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Message</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="Tell us more about your experience..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold rounded-pill" id="btnSubmitFeedback">Submit Feedback <i class="fa-solid fa-paper-plane ms-1"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback AJAX Script -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const feedbackForm = document.getElementById("feedbackForm");
        if (feedbackForm) {
            feedbackForm.addEventListener("submit", function(e) {
                e.preventDefault();
                const btn = document.getElementById("btnSubmitFeedback");
                const resDiv = document.getElementById("feedbackResponse");
                const formData = new FormData(feedbackForm);
                
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
                resDiv.innerHTML = "";

                fetch("<?= BASE_URL ?>ajax_submit_feedback.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resDiv.innerHTML = `<div class="alert alert-success fw-bold p-2"><i class="fa-solid fa-check-circle me-1"></i> ${data.message}</div>`;
                        feedbackForm.reset();
                        setTimeout(() => {
                            let modalEl = document.getElementById("feedbackModal");
                            let modalInstance = bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) {
                                modalInstance.hide();
                            }
                            resDiv.innerHTML = "";
                        }, 2500);
                    } else {
                        resDiv.innerHTML = `<div class="alert alert-danger fw-bold p-2"><i class="fa-solid fa-circle-exclamation me-1"></i> ${data.message}</div>`;
                    }
                })
                .catch(error => {
                    resDiv.innerHTML = `<div class="alert alert-danger fw-bold p-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> Connection error. Please try again.</div>`;
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = 'Submit Feedback <i class="fa-solid fa-paper-plane ms-1"></i>';
                });
            });
        }
    });
    </script>
</body>
</html>
