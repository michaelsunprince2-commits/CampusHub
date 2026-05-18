<?php

/**
 * Platform Review Form Page
 * Allows students and landlords to submit reviews about their CampusNest experience
 */

$pageTitle = 'Share Your Experience';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['student', 'landlord']);

require_once '../models/PlatformReview.php';

$error = '';
$success = '';
$reviewModel = new PlatformReview($conn);

// Check if user already has a review
$existingReview = $reviewModel->getUserReview(getCurrentUserId());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = sanitizeInt($_POST['rating'] ?? 0);
    $title = sanitizeString($_POST['title'] ?? '');
    $comment = sanitizeInput($_POST['comment'] ?? '');

    // Validation
    if (empty($title)) {
        $error = 'Review title is required';
    } elseif (empty($comment)) {
        $error = 'Your experience is required';
    } elseif ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5 stars';
    } elseif (strlen($comment) < 20) {
        $error = 'Your experience must be at least 20 characters';
    } elseif (strlen($title) > 100) {
        $error = 'Title must not exceed 100 characters';
    } else {
        // If user already has a review, update it
        if ($existingReview) {
            $result = $reviewModel->update($existingReview['id'], getCurrentUserId(), $rating, $title, $comment);
        } else {
            $result = $reviewModel->create(
                getCurrentUserId(),
                getCurrentUserRole(),
                $rating,
                $title,
                $comment
            );
        }

        if ($result['success']) {
            $success = $existingReview
                ? 'Your review has been updated and is awaiting admin approval.'
                : 'Thank you for your review! It is awaiting admin approval before it appears publicly.';
            $existingReview = $reviewModel->getUserReview(getCurrentUserId());
        } else {
            $error = $result['message'];
        }
    }
}

require_once '../templates/header.php';
?>

<style>
    .review-container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 2rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .review-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .review-header h1 {
        color: #2c3e50;
        margin-bottom: 0.5rem;
    }

    .review-header p {
        color: #7f8c8d;
        font-size: 1.1rem;
    }

    .review-form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.75rem;
        font-size: 1.05rem;
    }

    .form-group input,
    .form-group textarea {
        padding: 0.75rem;
        border: 2px solid #ecf0f1;
        border-radius: 4px;
        font-size: 1rem;
        font-family: inherit;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 8px rgba(102, 126, 234, 0.2);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 150px;
    }

    .rating-group {
        display: flex;
        flex-direction: column;
    }

    .rating-group label {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 0.75rem;
        font-size: 1.05rem;
    }

    .rating-stars {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .star-input {
        display: none;
    }

    .star-label {
        font-size: 2.5rem;
        cursor: pointer;
        transition: transform 0.2s, filter 0.2s;
        filter: grayscale(100%);
        opacity: 0.6;
    }

    .star-input:checked~.star-label,
    .star-label:hover,
    .star-input:checked~.star-label:hover {
        filter: grayscale(0%);
        opacity: 1;
        transform: scale(1.2);
    }

    .rating-container {
        display: flex;
        flex-direction: row-reverse;
        gap: 0.5rem;
    }

    .rating-value {
        display: inline-block;
        min-width: 60px;
        padding: 0.5rem 1rem;
        background: #ecf0f1;
        border-radius: 4px;
        text-align: center;
        font-weight: 600;
        color: #2c3e50;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .form-actions button,
    .form-actions a {
        flex: 1;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 4px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        text-align: center;
    }

    .form-actions button[type="submit"] {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .form-actions button[type="submit"]:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .form-actions a {
        background: #ecf0f1;
        color: #2c3e50;
    }

    .form-actions a:hover {
        background: #d5dbdb;
    }

    .alert {
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1.5rem;
        border-left: 4px solid;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border-left-color: #f5c6cb;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border-left-color: #c3e6cb;
    }

    .review-info {
        background: #f0f4f8;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #667eea;
    }

    .review-info p {
        margin: 0.5rem 0;
        color: #2c3e50;
    }

    .review-info strong {
        color: #764ba2;
    }

    .char-count {
        font-size: 0.9rem;
        color: #7f8c8d;
        margin-top: 0.25rem;
    }

    .edit-review-badge {
        display: inline-block;
        background: #f39c12;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-size: 0.85rem;
        margin-left: 0.5rem;
    }
</style>

<div class="review-container">
    <div class="review-header">
        <h1>Share Your Experience</h1>
        <p>Help other students and landlords discover how great CampusNest is!</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($existingReview && !$success): ?>
        <div class="review-info">
            <p><strong>✓ You already have a review on file</strong></p>
            <p>Your current rating:
                <strong>
                    <?php
                    echo str_repeat('⭐', $existingReview['rating']) . ' (' . $existingReview['rating'] . ' stars)';
                    ?>
                </strong>
            </p>
            <p>You can update your review below to reflect your latest experience.</p>
        </div>
    <?php endif; ?>

    <form method="post" class="review-form">
        <!-- Rating -->
        <div class="rating-group">
            <label>Your Rating <span style="color: #e74c3c;">*</span></label>
            <div class="rating-container">
                <div class="rating-value" id="ratingValue">Select Rating</div>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input
                        type="radio"
                        id="rating<?php echo $i; ?>"
                        name="rating"
                        value="<?php echo $i; ?>"
                        <?php echo ($existingReview && $existingReview['rating'] == $i) ? 'checked' : ''; ?>
                        class="star-input">
                    <label for="rating<?php echo $i; ?>" class="star-label">⭐</label>
                <?php endfor; ?>
            </div>
            <small style="color: #7f8c8d; margin-top: 0.5rem;">Click on the stars to rate your experience</small>
        </div>

        <!-- Title -->
        <div class="form-group">
            <label for="title">Review Title <span style="color: #e74c3c;">*</span></label>
            <input
                type="text"
                id="title"
                name="title"
                placeholder="Summarize your experience in a few words"
                maxlength="100"
                required
                value="<?php echo htmlspecialchars($existingReview['title'] ?? ''); ?>">
            <small class="char-count"><span id="titleCount">0</span>/100 characters</small>
        </div>

        <!-- Comment -->
        <div class="form-group">
            <label for="comment">Your Experience <span style="color: #e74c3c;">*</span></label>
            <textarea
                id="comment"
                name="comment"
                placeholder="Share your experience with CampusNest. What did you like? What could we improve?"
                required><?php echo htmlspecialchars($existingReview['comment'] ?? ''); ?></textarea>
            <small class="char-count"><span id="commentCount">0</span> characters (minimum 20)</small>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit">
                <?php echo $existingReview ? '📝 Update Review' : '✓ Submit Review'; ?>
            </button>
            <a href="<?php echo pageUrl('index.php'); ?>">← Back</a>
        </div>
    </form>
</div>

<script>
    // Rating selection
    const ratingInputs = document.querySelectorAll('.star-input');
    const ratingValue = document.getElementById('ratingValue');

    ratingInputs.forEach(input => {
        input.addEventListener('change', function() {
            const rating = this.value;
            ratingValue.textContent = rating + ' Star' + (rating > 1 ? 's' : '');
        });
    });

    // Initialize rating display
    const checkedRating = document.querySelector('.star-input:checked');
    if (checkedRating) {
        ratingValue.textContent = checkedRating.value + ' Star' + (checkedRating.value > 1 ? 's' : '');
    }

    // Character counter for title
    const titleInput = document.getElementById('title');
    const titleCount = document.getElementById('titleCount');
    titleInput.addEventListener('input', function() {
        titleCount.textContent = this.value.length;
    });
    titleCount.textContent = titleInput.value.length;

    // Character counter for comment
    const commentInput = document.getElementById('comment');
    const commentCount = document.getElementById('commentCount');
    commentInput.addEventListener('input', function() {
        commentCount.textContent = this.value.length;
    });
    commentCount.textContent = commentInput.value.length;
</script>

<?php require_once '../templates/footer.php'; ?>
