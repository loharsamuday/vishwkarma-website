<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$page_title = 'My Mistake Book';
require_once '../includes/header.php';
?>
<div class="container py-5 text-center">
    <h2><i class="fas fa-book-dead text-danger mb-3"></i><br>My Mistake Book</h2>
    <p class="text-muted">Coming soon in Phase 8...</p>
</div>
<?php require_once '../includes/footer.php'; ?>
