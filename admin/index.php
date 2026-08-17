<?php
$page_title = "Admin Login";
require_once '../includes/db.php';
require_once '../includes/session.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        
        logActivity('Admin Logged In', 'admin', null, $admin['id']);
        
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid admin credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Vishwakarma Samaj</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Body setup */
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #0f2027; /* Fallback */
        }

        /* Animated Full-Area Background */
        .bg-area {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            width: 100%;
            height: 100vh;
            position: absolute;
            top: 0;
            left: 0;
            z-index: -1;
            overflow: hidden;
        }

        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        .floating-shapes li {
            position: absolute;
            display: block;
            list-style: none;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.03);
            animation: animateShape 25s linear infinite;
            bottom: -150px;
            border-radius: 50%;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Various shapes configuration */
        .floating-shapes li:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; background: rgba(243, 156, 18, 0.05); }
        .floating-shapes li:nth-child(2) { left: 10%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 12s; }
        .floating-shapes li:nth-child(3) { left: 70%; width: 30px; height: 30px; animation-delay: 4s; background: rgba(52, 152, 219, 0.05); }
        .floating-shapes li:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .floating-shapes li:nth-child(5) { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .floating-shapes li:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; background: rgba(243, 156, 18, 0.05); }
        .floating-shapes li:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .floating-shapes li:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .floating-shapes li:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; background: rgba(52, 152, 219, 0.08); }
        .floating-shapes li:nth-child(10) { left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }

        @keyframes animateShape {
            0% {
                transform: translateY(0) rotate(0deg) scale(1);
                opacity: 1;
                border-radius: 30%;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg) scale(1.5);
                opacity: 0;
                border-radius: 50%;
            }
        }

        /* Glassmorphism Card */
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            color: #fff;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 50px rgba(0, 0, 0, 0.3);
        }

        /* Branding */
        .brand-logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #f39c12, #e67e22);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            font-size: 28px;
            color: white;
            box-shadow: 0 10px 20px rgba(243, 156, 18, 0.3);
        }

        .login-title {
            font-weight: 700;
            font-size: 24px;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .login-subtitle {
            font-weight: 300;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
        }

        /* Custom Input Styling */
        .input-wrapper {
            position: relative;
            margin-bottom: 25px;
        }

        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            font-size: 18px;
            transition: color 0.3s ease, transform 0.3s ease;
            pointer-events: none;
        }

        .custom-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.25);
            border: 2px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            border-radius: 14px;
            padding: 16px 20px 16px 55px; /* left padding for icon */
            font-size: 15px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            box-sizing: border-box;
        }

        .custom-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 400;
        }

        .custom-input:focus {
            background: rgba(0, 0, 0, 0.45);
            border-color: #f39c12;
            box-shadow: 0 0 20px rgba(243, 156, 18, 0.25);
        }

        /* Animate Icon on Focus */
        .custom-input:focus ~ .input-icon,
        .custom-input:not(:placeholder-shown) ~ .input-icon {
            color: #f39c12;
            transform: translateY(-50%) scale(1.1);
        }

        /* Password Toggle */
        .toggle-password {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            font-size: 18px;
            transition: color 0.2s ease;
            z-index: 10;
        }

        .toggle-password:hover {
            color: #f39c12;
        }

        /* Login Button */
        .btn-login {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 700;
            padding: 15px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(230, 126, 34, 0.3);
            margin-top: 10px;
        }

        .btn-login:hover {
            background: linear-gradient(45deg, #e67e22, #d35400);
            box-shadow: 0 15px 30px rgba(230, 126, 34, 0.4);
            transform: translateY(-3px);
        }

        /* Forgot Password */
        .forgot-pass {
            color: rgba(255, 255, 255, 0.6);
            font-size: 13px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        .forgot-pass:hover {
            color: #f39c12;
            text-decoration: underline;
        }

        /* Error Alert */
        .alert-custom {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.4);
            color: #ffb3b8;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Mobile Responsive adjustments */
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 25px;
                border-radius: 20px;
                border-left: none;
                border-right: none;
                background: rgba(255, 255, 255, 0.04);
            }
        }
    </style>
</head>
<body>

    <!-- Full-Area Animated Background -->
    <div class="bg-area">
        <ul class="floating-shapes">
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
        </ul>
    </div>

    <div class="container d-flex justify-content-center px-3">
        <div class="login-card">
            
            <div class="text-center">
                <div class="brand-logo">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="login-title">Admin Portal</h3>
                <p class="login-subtitle">Vishwakarma Samaj Management</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-custom py-2 px-3 text-center mb-4">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                
                <div class="input-wrapper">
                    <input type="text" class="custom-input" name="username" placeholder="Username or Email" required value="admin" autocomplete="off">
                    <i class="fa-regular fa-user input-icon"></i>
                </div>
                
                <div class="input-wrapper mb-2">
                    <input type="password" class="custom-input" id="passwordInput" name="password" placeholder="Password" required value="password">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <i class="fa-solid fa-eye toggle-password" id="togglePasswordBtn"></i>
                </div>
                
                <div class="text-end mb-4 mt-2">
                    <a href="forgot-password.php" class="forgot-pass">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    Secure Login <i class="fa-solid fa-arrow-right-to-bracket ms-2"></i>
                </button>
                
            </form>
            
        </div>
    </div>

    <script>
    // Password Toggle Script
    document.getElementById('togglePasswordBtn').addEventListener('click', function() {
        const input = document.getElementById('passwordInput');
        
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
            this.style.color = '#f39c12';
        } else {
            input.type = 'password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
            this.style.color = '';
        }
    });
    </script>
</body>
</html>
