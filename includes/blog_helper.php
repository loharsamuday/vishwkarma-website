<?php
// includes/blog_helper.php

/**
 * Calculates the blogger's rank based on the total sum of ratings
 * their approved blogs have received.
 * 
 * @param int $user_id
 * @param PDO $pdo
 * @return array ['title' => 'Rank Name', 'class' => 'badge bg-...', 'icon' => 'fa-...']
 */
function getBloggerRank($user_id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT SUM(br.rating) as total_points 
        FROM blog_ratings br
        JOIN blogs b ON br.blog_id = b.id
        WHERE b.user_id = ? AND b.status = 'approved'
    ");
    $stmt->execute([$user_id]);
    $total_points = (int)$stmt->fetchColumn();

    if ($total_points >= 151) {
        return [
            'title' => 'Expert Blogger',
            'class' => 'badge bg-danger',
            'icon' => 'fa-solid fa-crown',
            'points' => $total_points
        ];
    } elseif ($total_points >= 51) {
        return [
            'title' => 'Top Blogger',
            'class' => 'badge bg-primary',
            'icon' => 'fa-solid fa-medal',
            'points' => $total_points
        ];
    } elseif ($total_points >= 11) {
        return [
            'title' => 'Rising Blogger',
            'class' => 'badge bg-info text-dark',
            'icon' => 'fa-solid fa-arrow-trend-up',
            'points' => $total_points
        ];
    } else {
        return [
            'title' => 'Beginner Blogger',
            'class' => 'badge bg-secondary',
            'icon' => 'fa-solid fa-seedling',
            'points' => $total_points
        ];
    }
}
?>
