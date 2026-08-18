<?php
$html = file_get_contents("https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css");
echo strpos($html, "offcanvas-lg") !== false ? "YES" : "NO";
?>
