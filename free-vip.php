<?php
$page_title = "Exclusive VIP Offer";
require_once 'includes/db.php';
require_once 'includes/session.php';

// If already logged in, redirect to index
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Vishwakarma Samaj</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <!-- AOS CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            color: #ffffff;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .golden-text {
            background: linear-gradient(to right, #bf953f, #fcf6ba, #b38728, #fbf5b7, #aa771c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
        }
        .timer-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 215, 0, 0.3);
            border-radius: 12px;
            padding: 15px 25px;
            display: inline-block;
            margin: 20px 0;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .btn-golden {
            background: linear-gradient(to bottom, #fcf6ba, #bf953f);
            color: #000;
            font-weight: 800;
            text-transform: uppercase;
            padding: 18px 40px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 10px 20px rgba(191, 149, 63, 0.4);
            transition: all 0.3s ease;
            font-size: 1.2rem;
            animation: pulse-glow 2s infinite;
        }
        .btn-golden:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 30px rgba(191, 149, 63, 0.6);
            color: #000;
        }
        @keyframes pulse-glow {
            0% { box-shadow: 0 0 0 0 rgba(252, 246, 186, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(252, 246, 186, 0); }
            100% { box-shadow: 0 0 0 0 rgba(252, 246, 186, 0); }
        }
        /* Notification Toast Styling */
        #live-notification {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            color: #000;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 5px solid #bf953f;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            gap: 15px;
            transform: translateX(-150%);
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
            max-width: 300px;
        }
        #live-notification.show {
            transform: translateX(0);
        }
        .feature-icon {
            font-size: 2.5rem;
            color: #fcf6ba;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="container d-flex flex-column justify-content-center align-items-center text-center py-5 min-vh-100">
    <div data-aos="fade-down">
        <i class="fa-solid fa-gem fa-4x golden-text mb-3"></i>
        <h1 class="display-3 golden-text mb-2">EXCLUSIVE VIP INVITATION</h1>
        <h3 class="text-light fw-light mb-4">You have been selected for 100% Free Lifetime VIP Access</h3>
    </div>

    <div class="timer-box" data-aos="zoom-in" data-aos-delay="200">
        <h5 class="text-warning mb-2 text-uppercase tracking-wide"><i class="fa-solid fa-stopwatch fa-spin me-2"></i> This private link expires in:</h5>
        <div class="display-1 fw-bold text-white font-monospace" id="countdown">05:00</div>
    </div>

    <p class="lead text-light opacity-75 my-4 mx-auto" style="max-width: 600px;" data-aos="fade-up" data-aos-delay="400">
        Register now to instantly unlock hidden Matrimony profiles, view direct contact numbers, and message anyone completely free. 
        <strong class="text-warning">Don't miss out!</strong>
    </p>

    <div class="row g-4 my-4 w-100 justify-content-center" data-aos="fade-up" data-aos-delay="600">
        <div class="col-md-3 col-sm-6 text-center">
            <i class="fa-solid fa-lock-open feature-icon"></i>
            <h5 class="fw-bold">Unlock Profiles</h5>
        </div>
        <div class="col-md-3 col-sm-6 text-center">
            <i class="fa-solid fa-phone feature-icon"></i>
            <h5 class="fw-bold">View Contacts</h5>
        </div>
        <div class="col-md-3 col-sm-6 text-center">
            <i class="fa-solid fa-comment-dots feature-icon"></i>
            <h5 class="fw-bold">Direct Messaging</h5>
        </div>
    </div>

    <div class="mt-4" data-aos="zoom-in" data-aos-delay="800">
        <a href="register.php?promo=VIP" class="btn btn-golden text-decoration-none">
            CLAIM MY FREE VIP ACCOUNT <i class="fa-solid fa-arrow-right ms-2"></i>
        </a>
        <div class="mt-3 text-muted small">
            <i class="fa-solid fa-shield-halved text-success me-1"></i> 100% Secure & Verified
        </div>
    </div>
</div>

<!-- Fake Live Notification -->
<div id="live-notification">
    <img src="https://placehold.co/50x50/bf953f/white?text=VIP" class="rounded-circle" alt="User">
    <div>
        <strong class="d-block" id="toast-name">Rahul V.</strong>
        <span class="small text-muted" id="toast-action">just claimed a VIP account!</span>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.css"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ duration: 1000, once: true });

    // Countdown Timer Logic (5 Minutes)
    let time = 300; // 5 minutes in seconds
    const countdownEl = document.getElementById('countdown');
    
    const interval = setInterval(() => {
        let minutes = Math.floor(time / 60);
        let seconds = time % 60;
        
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        countdownEl.innerHTML = `${minutes}:${seconds}`;
        
        if(time <= 0) {
            clearInterval(interval);
            countdownEl.innerHTML = "EXPIRED";
            countdownEl.classList.add('text-danger');
            countdownEl.classList.remove('text-white');
        } else {
            time--;
        }
    }, 1000);

    // Fake Live Notification Logic
    const names = [
        "Rahul Sharma", "Priya Verma", "Amit Vishwakarma", 
        "Neha Singh", "Vikash V.", "Anjali K.", "Rohan M.", "Suresh T."
    ];
    const actions = [
        "just claimed a VIP account!", 
        "unlocked premium features!", 
        "just registered for free!", 
        "is now a Verified Member!"
    ];
    const toast = document.getElementById('live-notification');
    const toastName = document.getElementById('toast-name');
    const toastAction = document.getElementById('toast-action');

    function showRandomNotification() {
        // Only show if there's no hover intent (don't distract too much)
        const randomName = names[Math.floor(Math.random() * names.length)];
        const randomAction = actions[Math.floor(Math.random() * actions.length)];
        
        toastName.innerText = randomName;
        toastAction.innerText = randomAction;
        
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000); // Hide after 4 seconds
    }

    // Initial delay then trigger randomly between 8 to 15 seconds
    setTimeout(() => {
        showRandomNotification();
        setInterval(showRandomNotification, Math.floor(Math.random() * (15000 - 8000 + 1)) + 8000);
    }, 3000);
</script>
</body>
</html>
