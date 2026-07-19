<?php
// maintenance.php
header('HTTP/1.1 503 Service Temporarily Unavailable');
header('Status: 503 Service Temporarily Unavailable');
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Under Maintenance</title>
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }
        .container {
            background-color: #ffffff;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-width: 500px;
            width: 90%;
        }
        h1 {
            color: #e11d48;
            font-size: 28px;
            margin-bottom: 16px;
            font-weight: 700;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
            color: #64748b;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #e11d48;
            color: white;
            padding: 10px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #be123c;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🛠️</div>
        <h1>Under Maintenance</h1>
        <p>Our website is currently experiencing server connection issues. We are working hard to fix the problem and get things back to normal.</p>
        <p>Please check back again in a few moments. Thank you for your patience!</p>
        <a href="/" class="btn">Try Again</a>
    </div>
</body>
</html>
