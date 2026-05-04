<?php

/**
 * Platform Reviews Display Page
 * Shows all approved platform reviews from students and landlords
 */

$pageTitle = 'Community Reviews';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_once '../models/PlatformReview.php';

$reviewModel = new PlatformReview($conn);
$page = sanitizeInt($_GET['page'] ?? 1);
$roleFilter = sanitizeString($_GET['role'] ?? '');
$limit = 10;
$offset = ($page - 1) * $limit;

// Validate role filter
if (!empty($roleFilter) && !in_array($roleFilter, ['student', 'landlord'], true)) {
    $roleFilter = '';
}

// Get reviews
$reviews = $reviewModel->getApprovedReviews($limit, $offset, $roleFilter ?: null);

// Get rating stats
$ratingStats = $reviewModel->getAverageRating($roleFilter ?: null);
$ratingDistribution = $reviewModel->getRatingDistribution($roleFilter ?: null);

require_once '../templates/header.php';
?>

<style>
    .reviews-container {
        max-width: 1000px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .reviews-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .reviews-header h1 {
        color: #2c3e50;
        margin-bottom: 0.5rem;
        font-size: 2.5rem;
    }

    .reviews-header p {
        color: #7f8c8d;
        font-size: 1.1rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        font-size: 0.95rem;
        opacity: 0.9;
    }

    .rating-distribution {
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 3rem;
    }

    .rating-distribution h3 {
        color: #2c3e50;
        margin-bottom: 1.5rem;
    }

    .rating-row {
        display: flex;
        align-items: center;
        margin-bottom: 1rem;
        gap: 1rem;
    }

    .rating-star {
        min-width: 60px;
        font-weight: 600;
        color: #f39c12;
    }

    .rating-bar-container {
        flex: 1;
        height: 25px;
        background: #ecf0f1;
        border-radius: 4px;
        overflow: hidden;
    }

    .rating-bar {
        height: 100%;
        background: linear-gradient(90deg, #f39c12 0%, #f1c40f 100%);
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 0.5rem;
        color: white;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .rating-count {
        min-width: 40px;
        text-align: right;
        color: #7f8c8d;
        font-weight: 600;
    }

    .filters {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        flex-wrap: wrap;
    }

    .filter-btn {
        padding: 0.5rem 1.5rem;
        border: 2px solid #ecf0f1;
        background: white;
        color: #2c3e50;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .filter-btn:hover,
    .filter-btn.active {
        border-color: #667eea;
        background: #667eea;
        color: white;
    }

    .reviews-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .review-card {
        background: white;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-left: 4px solid #667eea;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .review-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .review-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .reviewer-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .reviewer-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }

    .reviewer-info {
        flex: 1;
    }

    .reviewer-name {
        font-weight: 600;
        color: #2c3e50;
        font-size: 1.05rem;
    }

    .reviewer-role {
        font-size: 0.85rem;
        color: #7f8c8d;
        text-transform: capitalize;
    }

    .review-rating {
        display: flex;
        gap: 0.25rem;
        margin-bottom: 0.5rem;
    }

    .review-rating .star {
        color: #f39c12;
        font-size: 1.1rem;
    }

    .review-rating .empty-star {
        color: #ecf0f1;
    }

    .review-date {
        font-size: 0.85rem;
        color: #95a5a6;
    }

    .review-title {
        font-size: 1.2rem;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .review-comment {
        color: #34495e;
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    .review-helpful {
        display: flex;
        gap: 1rem;
        align-items: center;
        font-size: 0.9rem;
    }

    .helpful-btn {
        background: none;
        border: none;
        color: #95a5a6;
        cursor: pointer;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        transition: all 0.3s;
    }

    .helpful-btn:hover {
        background: #ecf0f1;
        color: #667eea;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination a,
    .pagination span {
        padding: 0.5rem 0.75rem;
        border: 1px solid #ecf0f1;
        border-radius: 4px;
        text-decoration: none;
        color: #2c3e50;
        font-weight: 600;
        transition: all 0.3s;
    }

    .pagination a:hover {
        background: #ecf0f1;
        border-color: #667eea;
    }

    .pagination .active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .no-reviews {
        text-align: center;
        padding: 3rem 1rem;
        color: #7f8c8d;
    }

    .no-reviews p {
        font-size: 1.1rem;
        margin-bottom: 1.5rem;
    }

    .cta-button {
        display: inline-block;
        padding: 0.75rem 1.5rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-weight: 600;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .cta-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
</style>

<div class="reviews-container">
    <!-- Header -->
    <div class="reviews-header">
        <h1>Community Reviews</h1>
        <p>See what students and landlords think about CampusNest</p>
    </div>

    <!-- Rating Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($ratingStats['avg_rating'] ?? 0, 1); ?></div>
            <div class="stat-label">Average Rating</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $ratingStats['total_reviews'] ?? 0; ?></div>
            <div class="stat-label">Total Reviews</div>
        </div>
        <?php if (isAuthenticated()): ?>
            <div class="stat-card">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">
                    <a href="<?php echo pageUrl('platform-review.php'); ?>" style="color: white; text-decoration: none;">✍️</a>
                </div>
                <div class="stat-label"><a href="<?php echo pageUrl('platform-review.php'); ?>" style="color: white; text-decoration: none;">Share Your Review</a></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Rating Distribution -->
    <div class="rating-distribution">
        <h3>Rating Breakdown</h3>
        <?php
        $distribution = array_fill(0, 5, 0);
        $maxCount = 1;

        foreach ($ratingDistribution as $row) {
            $distribution[5 - (int)$row['rating']] = (int)$row['count'];
            $maxCount = max($maxCount, (int)$row['count']);
        }

        for ($i = 5; $i >= 1; $i--):
            $count = $distribution[5 - $i] ?? 0;
            $percentage = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
        ?>
            <div class="rating-row">
                <div class="rating-star">⭐<?php echo $i; ?></div>
                <div class="rating-bar-container">
                    <div class="rating-bar" style="width: <?php echo $percentage; ?>%;">
                        <?php echo $count > 0 ? $count : ''; ?>
                    </div>
                </div>
                <div class="rating-count"><?php echo $count; ?></div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Filters -->
    <div class="filters">
        <a href="<?php echo pageUrl('reviews.php'); ?>" class="filter-btn <?php echo empty($roleFilter) ? 'active' : ''; ?>">All Reviews</a>
        <a href="<?php echo pageUrl('reviews.php?role=student'); ?>" class="filter-btn <?php echo $roleFilter === 'student' ? 'active' : ''; ?>">👨 Student Reviews</a>
        <a href="<?php echo pageUrl('reviews.php?role=landlord'); ?>" class="filter-btn <?php echo $roleFilter === 'landlord' ? 'active' : ''; ?>">🏠 Landlord Reviews</a>
    </div>

    <!-- Reviews List -->
    <?php if (empty($reviews)): ?>
        <div class="no-reviews">
            <p>No reviews yet. Be the first to share your experience!</p>
            <?php if (isAuthenticated()): ?>
                <a href="<?php echo pageUrl('platform-review.php'); ?>" class="cta-button">✍️ Write a Review</a>
            <?php else: ?>
                <a href="<?php echo pageUrl('login.php'); ?>" class="cta-button">Login to Write a Review</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-avatar">
                            <?php if (!empty($review['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($review['profile_picture']); ?>" alt="Profile">
                            <?php else: ?>
                                <?php echo $review['user_role'] === 'landlord' ? '🏠' : '👨'; ?>
                            <?php endif; ?>
                        </div>
                        <div class="reviewer-info">
                            <div class="reviewer-name"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                            <div class="reviewer-role"><?php echo ucfirst($review['user_role']); ?></div>
                            <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                        </div>
                    </div>

                    <div class="review-rating">
                        <?php for ($i = 0; $i < 5; $i++): ?>
                            <span class="star <?php echo $i < $review['rating'] ? '' : 'empty'; ?>">⭐</span>
                        <?php endfor; ?>
                    </div>

                    <div class="review-title"><?php echo htmlspecialchars($review['title']); ?></div>
                    <div class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>

                    <div class="review-helpful">
                        <span>Was this helpful?</span>
                        <button class="helpful-btn">👍 Helpful (<?php echo $review['helpful_count']; ?>)</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?php echo pageUrl('reviews.php?page=' . ($page - 1) . ($roleFilter ? '&role=' . $roleFilter : '')); ?>">← Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo pageUrl('reviews.php?page=' . $i . ($roleFilter ? '&role=' . $roleFilter : '')); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if (count($reviews) === $limit): ?>
                <a href="<?php echo pageUrl('reviews.php?page=' . ($page + 1) . ($roleFilter ? '&role=' . $roleFilter : '')); ?>">Next →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- CTA for non-authenticated users -->
    <?php if (!isAuthenticated()): ?>
        <div style="text-align: center; margin-top: 3rem; padding: 2rem; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); border-radius: 8px;">
            <h3 style="color: #2c3e50; margin-bottom: 1rem;">Want to Share Your Experience?</h3>
            <p style="color: #7f8c8d; margin-bottom: 1.5rem;">Join CampusNest today and help other students find their perfect home!</p>
            <a href="<?php echo pageUrl('register.php'); ?>" class="cta-button">Register Now</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../templates/footer.php'; ?>