document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('mobileMenuToggle');
    const closeBtn = document.getElementById('mobileMenuClose');
    const overlay = document.getElementById('mobileNavOverlay');
    const drawer = document.getElementById('mobileNavDrawer');

    if (toggleBtn && closeBtn && overlay && drawer) {
        function openDrawer() {
            drawer.classList.add('open');
            overlay.classList.add('show');
            document.body.classList.add('mobile-menu-open');
            toggleBtn.setAttribute('aria-expanded', 'true');
        }

        function closeDrawer() {
            drawer.classList.remove('open');
            overlay.classList.remove('show');
            document.body.classList.remove('mobile-menu-open');
            toggleBtn.setAttribute('aria-expanded', 'false');
        }

        toggleBtn.addEventListener('click', function () {
            if (drawer.classList.contains('open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });

        closeBtn.addEventListener('click', closeDrawer);
        overlay.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        });
    }

    // ========================================
    // Phone Number Input Validation (10 digits only)
    // ========================================
    const phoneInputs = document.querySelectorAll('.phone-input');
    
    phoneInputs.forEach(function(input) {
        // Real-time input validation
        input.addEventListener('input', function(e) {
            // Remove all non-numeric characters
            let value = this.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (value.length > 10) {
                value = value.slice(0, 10);
            }
            
            // Update the input value
            this.value = value;
            
            // Add visual feedback
            if (value.length === 10) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (value.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        // On focus - clear invalid state
        input.addEventListener('focus', function() {
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
            }
        });
        
        // On blur - validate
        input.addEventListener('blur', function() {
            let value = this.value.trim();
            if (value.length > 0 && value.length !== 10) {
                this.classList.add('is-invalid');
            } else if (value.length === 10) {
                this.classList.add('is-valid');
            }
        });

        // Prevent non-numeric input
        input.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) {
                e.preventDefault();
            }
        });
    });
});

