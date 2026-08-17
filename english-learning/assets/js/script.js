// assets/js/script.js
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips if using Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            html: true
        });
    });

    // Handle vocabulary highlight clicks in story
    const vocabHighlights = document.querySelectorAll('.vocab-word-highlight');
    vocabHighlights.forEach(el => {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            const wordId = this.getAttribute('data-vocab-id');
            const targetElement = document.getElementById('vocab-' + wordId);
            
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Briefly highlight the card
                targetElement.style.backgroundColor = '#e8f4f8';
                setTimeout(() => {
                    targetElement.style.backgroundColor = 'white';
                    targetElement.style.transition = 'background-color 1s';
                }, 1000);
            }
        });
    });

    // Handle newsletter subscription
    const subForm = document.getElementById('subscribeForm');
    if (subForm) {
        subForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const emailInput = document.getElementById('sub_email');
            const subBtn = document.getElementById('sub_btn');
            const subMsg = document.getElementById('sub_message');
            
            subBtn.disabled = true;
            subBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            const formData = new FormData();
            formData.append('email', emailInput.value);
            
            fetch('subscribe.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                subMsg.style.display = 'block';
                subMsg.className = 'small fw-bold mt-2 ' + (data.status === 'success' ? 'text-success' : 'text-danger');
                subMsg.innerText = data.message;
                
                if (data.status === 'success') {
                    emailInput.value = '';
                }
            })
            .catch(error => {
                subMsg.style.display = 'block';
                subMsg.className = 'small fw-bold mt-2 text-danger';
                subMsg.innerText = 'An error occurred. Please try again.';
            })
            .finally(() => {
                subBtn.disabled = false;
                subBtn.innerText = 'Subscribe';
                
                setTimeout(() => {
                    subMsg.style.display = 'none';
                }, 5000);
            });
        });
    }
});
