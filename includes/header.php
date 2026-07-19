<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?><?= SITE_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
    
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- AOS Animation CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <style>
        /* Modern Utilities */
        .hover-scale { transition: transform 0.3s ease; }
        .hover-scale:hover { transform: scale(1.05); }
        .tracking-wide { letter-spacing: 2px; }
        .drop-shadow { text-shadow: 2px 2px 4px rgba(0,0,0,0.5); }
        
        /* Hide Google Translate Top Bar & Branding */
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }
        #google_translate_element .goog-te-gadget {
            color: transparent !important;
            font-size: 0px !important;
        }
        #google_translate_element .goog-te-gadget .goog-te-combo {
            color: #212529 !important;
            border: 1px solid #ffc107;
            border-radius: 20px;
            padding: 4px 8px;
            font-size: 13px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            background-color: #fff;
            outline: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            max-width: 100px;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            text-align: center;
        }
        #google_translate_element .goog-te-gadget .goog-te-combo:hover {
            border-color: #fd7e14;
        }
        .goog-logo-link { display: none !important; }
    </style>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<?php
if (function_exists('getCmsContent')) {
    $header_cms = getCmsContent('header');
    if ($header_cms && !empty(trim(strip_tags($header_cms['content'])))) {
        echo '<div class="bg-warning text-dark text-center py-2 small fw-bold">' . $header_cms['content'] . '</div>';
    }
}
?>
