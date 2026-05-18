<?php

/**
 * Admin Dashboard
 */

$pageTitle = 'Dashboard';
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['admin', 'committee']);

$error = '';
$success = '';
$currentAdminId = getCurrentUserId();
$currentAdminRole = getCurrentUserRole();
$isFullAdmin = $currentAdminRole === 'admin';
$dashboardTitle = $isFullAdmin ? 'Admin Dashboard' : 'Committee Dashboard';
$pageTitle = $dashboardTitle;

function fetchOne($conn, $query, $types = '', ...$params)
{
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database query failed: ' . $conn->error);
    }
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function fetchAll($conn, $query, $types = '', ...$params)
{
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database query failed: ' . $conn->error);
    }
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function tableColumnExists($conn, $tableName, $columnName)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $tableName, $columnName);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return (int)($result['count'] ?? 0) > 0;
}

$hasPropertyReviewStatus = tableColumnExists($conn, 'reviews', 'status');
$hasPlatformReviewStatus = tableColumnExists($conn, 'platform_reviews', 'status');

if (!$hasPropertyReviewStatus || !$hasPlatformReviewStatus) {
    $error = 'Review moderation database update is missing. Run migrations/moderate_reviews.sql on the hosted database, then reload this page.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'property_status') {
            $propertyId = (int)($_POST['property_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if (!$propertyId || !in_array($status, ['pending', 'verified', 'rejected'], true)) {
                throw new Exception('Invalid property status request.');
            }

            $verifiedBy = $status === 'verified' ? $currentAdminId : null;
            $stmt = $conn->prepare("UPDATE properties SET verification_status = ?, verified_by = ? WHERE id = ?");
            $stmt->bind_param("sii", $status, $verifiedBy, $propertyId);
            $stmt->execute();

            $requestStatus = $status === 'verified' ? 'approved' : ($status === 'rejected' ? 'rejected' : 'pending');
            $notes = trim($_POST['reviewer_notes'] ?? '');
            $stmt = $conn->prepare("
                UPDATE verification_requests
                SET status = ?, reviewer_notes = ?, reviewed_by = ?, review_date = NOW()
                WHERE property_id = ? AND request_type = 'property'
            ");
            $stmt->bind_param("ssii", $requestStatus, $notes, $currentAdminId, $propertyId);
            $stmt->execute();

            $success = 'Property status updated.';
        } elseif ($action === 'verify_user') {
            if (!$isFullAdmin) {
                throw new Exception('Only admins can verify users directly.');
            }

            $userId = (int)($_POST['user_id'] ?? 0);
            if (!$userId) {
                throw new Exception('Invalid user.');
            }

            $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_date = NOW(), verified_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $currentAdminId, $userId);
            $stmt->execute();

            $success = 'User verified.';
        } elseif ($action === 'review_user_request') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $notes = trim($_POST['reviewer_notes'] ?? '');

            if (!$userId || !in_array($status, ['approved', 'rejected'], true)) {
                throw new Exception('Invalid user verification review.');
            }

            if ($status === 'approved') {
                $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_date = NOW(), verified_by = ? WHERE id = ?");
                $stmt->bind_param("ii", $currentAdminId, $userId);
                $stmt->execute();
            }

            $stmt = $conn->prepare(
                "UPDATE verification_requests SET status = ?, reviewer_notes = ?, reviewed_by = ?, review_date = NOW() WHERE user_id = ? AND request_type = 'user'"
            );
            $stmt->bind_param("ssii", $status, $notes, $currentAdminId, $userId);
            $stmt->execute();

            $success = $status === 'approved' ? 'Landlord verification approved.' : 'Landlord verification rejected.';
        } elseif ($action === 'user_role') {
            if (!$isFullAdmin) {
                throw new Exception('Only admins can change user roles.');
            }

            $userId = (int)($_POST['user_id'] ?? 0);
            $role = $_POST['role'] ?? '';

            if (!$userId || !in_array($role, ['student', 'landlord', 'admin', 'committee'], true)) {
                throw new Exception('Invalid role update.');
            }

            if ($userId === $currentAdminId) {
                throw new Exception('You cannot change your own admin role from this screen.');
            }

            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $role, $userId);
            $stmt->execute();

            $success = 'User role updated.';
        } elseif ($action === 'booking_status') {
            if (!$isFullAdmin) {
                throw new Exception('Only admins can update booking statuses.');
            }

            $bookingId = (int)($_POST['booking_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if (!$bookingId || !in_array($status, ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
                throw new Exception('Invalid booking status request.');
            }

            $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $bookingId);
            $stmt->execute();

            $success = 'Booking status updated.';
        } elseif ($action === 'platform_review_status') {
            $reviewId = (int)($_POST['review_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if (!$reviewId || !in_array($status, ['approved', 'rejected'], true)) {
                throw new Exception('Invalid platform review request.');
            }

            if (!$hasPlatformReviewStatus) {
                throw new Exception('Run the review moderation database update before approving platform reviews.');
            }

            $stmt = $conn->prepare("UPDATE platform_reviews SET status = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Database query failed: ' . $conn->error);
            }
            $stmt->bind_param("si", $status, $reviewId);
            $stmt->execute();

            $success = $status === 'approved' ? 'Review approved' : 'Review rejected';
        } elseif ($action === 'property_review_status') {
            $reviewId = (int)($_POST['review_id'] ?? 0);
            $status = $_POST['status'] ?? '';

            if (!$reviewId || !in_array($status, ['approved', 'rejected'], true)) {
                throw new Exception('Invalid property review request.');
            }

            if (!$hasPropertyReviewStatus) {
                throw new Exception('Run the review moderation database update before approving property reviews.');
            }

            $stmt = $conn->prepare("SELECT property_id FROM reviews WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Database query failed: ' . $conn->error);
            }
            $stmt->bind_param("i", $reviewId);
            $stmt->execute();
            $review = $stmt->get_result()->fetch_assoc();

            if (!$review) {
                throw new Exception('Review not found.');
            }

            $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception('Database query failed: ' . $conn->error);
            }
            $stmt->bind_param("si", $status, $reviewId);
            $stmt->execute();

            $stmt = $conn->prepare("
                UPDATE properties
                SET rating = COALESCE((SELECT AVG(rating) FROM reviews WHERE property_id = ? AND status = 'approved'), 0),
                    review_count = (SELECT COUNT(*) FROM reviews WHERE property_id = ? AND status = 'approved')
                WHERE id = ?
            ");
            if (!$stmt) {
                throw new Exception('Database query failed: ' . $conn->error);
            }
            $propertyId = (int)$review['property_id'];
            $stmt->bind_param("iii", $propertyId, $propertyId, $propertyId);
            $stmt->execute();

            $success = $status === 'approved' ? 'Review approved' : 'Review rejected';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stats = [
    'users' => 0,
    'landlords' => 0,
    'students' => 0,
    'properties' => 0,
    'pending_properties' => 0,
    'pending_user_verifications' => 0,
    'pending_platform_reviews' => 0,
    'pending_user_reviews' => 0,
    'bookings' => 0,
    'pending_bookings' => 0,
    'completed_payments' => 0,
];
$pendingProperties = [];
$pendingUserRequests = [];
$pendingPlatformReviews = [];
$pendingUserReviews = [];
$allProperties = [];
$users = [];
$bookings = [];
$recentReviews = [];

try {
    $stats = [
        'users' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM users")['count'],
        'landlords' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM users WHERE role = 'landlord'")['count'],
        'students' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM users WHERE role = 'student'")['count'],
        'properties' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM properties")['count'],
        'pending_properties' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM properties WHERE verification_status = 'pending'")['count'],
        'pending_user_verifications' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM verification_requests WHERE request_type = 'user' AND status = 'pending'")['count'],
        'pending_platform_reviews' => $hasPlatformReviewStatus ? (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM platform_reviews WHERE status = 'pending'")['count'] : 0,
        'pending_user_reviews' => $hasPropertyReviewStatus ? (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM reviews WHERE status = 'pending'")['count'] : 0,
        'bookings' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM bookings")['count'],
        'pending_bookings' => (int)fetchOne($conn, "SELECT COUNT(*) AS count FROM bookings WHERE status = 'pending'")['count'],
        'completed_payments' => (float)(fetchOne($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = 'completed'")['total'] ?? 0),
    ];

    $pendingProperties = fetchAll($conn, "
        SELECT p.id, p.name, p.address, p.city, p.price_per_month, p.image_urls, p.created_at,
               u.first_name, u.last_name, u.email
        FROM properties p
        LEFT JOIN users u ON p.landlord_id = u.id
        WHERE p.verification_status = 'pending'
        ORDER BY p.created_at DESC
        LIMIT 12
    ");

    $pendingUserRequests = fetchAll($conn, "
        SELECT vr.id, vr.user_id, vr.document_urls, vr.created_at, u.first_name, u.last_name, u.email, u.role
        FROM verification_requests vr
        LEFT JOIN users u ON vr.user_id = u.id
        WHERE vr.request_type = 'user' AND vr.status = 'pending'
        ORDER BY vr.created_at DESC
        LIMIT 12
    ");

    $pendingPlatformReviews = $hasPlatformReviewStatus ? fetchAll($conn, "
        SELECT pr.id, pr.rating, pr.title, pr.comment, pr.user_role, pr.created_at,
               u.first_name, u.last_name, u.email
        FROM platform_reviews pr
        LEFT JOIN users u ON pr.user_id = u.id
        WHERE pr.status = 'pending'
        ORDER BY pr.created_at ASC
        LIMIT 12
    ") : [];

    $pendingUserReviews = $hasPropertyReviewStatus ? fetchAll($conn, "
        SELECT r.id, r.rating, r.title, r.comment, r.created_at,
               p.name AS property_name,
               u.first_name, u.last_name, u.email
        FROM reviews r
        LEFT JOIN properties p ON r.property_id = p.id
        LEFT JOIN users u ON r.reviewer_id = u.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC
        LIMIT 12
    ") : [];

    $allProperties = fetchAll($conn, "
        SELECT p.id, p.name, p.city, p.price_per_month, p.verification_status, p.created_at,
               u.first_name, u.last_name
        FROM properties p
        LEFT JOIN users u ON p.landlord_id = u.id
        ORDER BY p.created_at DESC
        LIMIT 25
    ");

    $users = fetchAll($conn, "
        SELECT id, email, first_name, last_name, role, is_verified, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 30
    ");

    $bookings = fetchAll($conn, "
        SELECT b.id, b.status, b.total_price, b.check_in_date, b.check_out_date, b.created_at,
               p.name AS property_name,
               u.first_name, u.last_name, u.email
        FROM bookings b
        LEFT JOIN properties p ON b.property_id = p.id
        LEFT JOIN users u ON b.student_id = u.id
        ORDER BY b.created_at DESC
        LIMIT 25
    ");

    $recentReviews = fetchAll($conn, "
        SELECT r.rating, r.title, " . ($hasPropertyReviewStatus ? "r.status" : "'approved' AS status") . ", r.created_at, p.name AS property_name, u.first_name, u.last_name
        FROM reviews r
        LEFT JOIN properties p ON r.property_id = p.id
        LEFT JOIN users u ON r.reviewer_id = u.id
        ORDER BY r.created_at DESC
        LIMIT 8
    ");
} catch (Exception $e) {
    $error = 'Admin dashboard database error: ' . $e->getMessage();
}

require_once '../templates/header.php';
?>

<style>
    .admin-page {
        display: grid;
        gap: 1.5rem;
    }

    .admin-hero,
    .admin-panel,
    .metric-card {
        background: #ffffff;
        border: 1px solid #e5edf2;
        border-radius: 8px;
        box-shadow: 0 6px 18px rgba(44, 62, 80, 0.07);
    }

    .admin-hero {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }

    .admin-hero h1 {
        margin-bottom: 0.35rem;
    }

    .admin-hero p {
        margin: 0;
        color: #657786;
    }

    .metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
    }

    .metric-card {
        padding: 1.15rem;
    }

    .metric-value {
        font-size: 2rem;
        line-height: 1;
        font-weight: 800;
        color: #2c3e50;
        margin-bottom: 0.45rem;
    }

    .metric-label {
        color: #6b7c86;
        font-size: 0.92rem;
    }

    .admin-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(320px, 0.8fr);
        gap: 1.5rem;
        align-items: start;
    }

    .admin-stack {
        display: grid;
        gap: 1.5rem;
    }

    .admin-panel {
        padding: 1.25rem;
    }

    .panel-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .panel-head h2,
    .panel-head h3 {
        margin: 0;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        box-shadow: none;
        border-radius: 0;
    }

    .admin-table th,
    .admin-table td {
        padding: 0.85rem;
        border-bottom: 1px solid #edf2f5;
        vertical-align: middle;
    }

    .admin-table th {
        background: #f6f9fb;
        color: #2c3e50;
        font-size: 0.88rem;
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 0.28rem 0.65rem;
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: capitalize;
        background: #eef5f6;
        color: #1f6f78;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-verified,
    .status-confirmed,
    .status-completed {
        background: #e9f7ef;
        color: #1e8449;
    }

    .status-rejected,
    .status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }

    .inline-form,
    .action-row {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .inline-form select,
    .inline-form input {
        padding: 0.5rem;
        border: 1px solid #d9e2e8;
        border-radius: 6px;
        min-height: 36px;
    }

    .btn-mini {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        border-radius: 6px;
    }

    .property-review-card {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 1rem;
        border: 1px solid #edf2f5;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #fbfcfd;
    }

    .property-review-thumb {
        width: 120px;
        height: 90px;
        border-radius: 8px;
        background: #e8eef2;
        display: grid;
        place-items: center;
        overflow: hidden;
        color: #7f8c8d;
        font-size: 1.5rem;
    }

    .property-review-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .property-review-card h3 {
        margin-bottom: 0.25rem;
    }

    .muted {
        color: #657786;
    }

    .review-item {
        border-bottom: 1px solid #edf2f5;
        padding: 0.85rem 0;
    }

    .review-item:last-child {
        border-bottom: 0;
    }

    @media (max-width: 980px) {
        .admin-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {

        .admin-hero,
        .property-review-card {
            grid-template-columns: 1fr;
        }

        .admin-hero {
            flex-direction: column;
            align-items: flex-start;
        }

        .property-review-thumb {
            width: 100%;
            height: 180px;
        }
    }
</style>

<div class="admin-page">
    <section class="admin-hero">
        <div>
            <h1><?php echo htmlspecialchars($dashboardTitle); ?></h1>
            <p><?php echo $isFullAdmin ? 'Review listings, manage users, monitor bookings, and keep CampusNest trustworthy.' : 'Review listings, verify landlord requests, and moderate community reviews.'; ?></p>
        </div>
        <a href="<?php echo pageUrl('properties.php'); ?>" class="btn">View Public Listings</a>
    </section>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <section class="metric-grid">
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['users']; ?></div>
            <div class="metric-label">Total Users</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['students']; ?></div>
            <div class="metric-label">Students</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['landlords']; ?></div>
            <div class="metric-label">Landlords</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['properties']; ?></div>
            <div class="metric-label">Properties</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['pending_properties']; ?></div>
            <div class="metric-label">Pending Property Reviews</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['pending_user_verifications']; ?></div>
            <div class="metric-label">Pending Landlord Verifications</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['pending_platform_reviews']; ?></div>
            <div class="metric-label">Pending Platform Reviews</div>
        </div>
        <div class="metric-card">
            <div class="metric-value"><?php echo $stats['pending_user_reviews']; ?></div>
            <div class="metric-label">Pending User Reviews</div>
        </div>
        <?php if ($isFullAdmin): ?>
            <div class="metric-card">
                <div class="metric-value"><?php echo $stats['bookings']; ?></div>
                <div class="metric-label">Bookings</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo $stats['pending_bookings']; ?></div>
                <div class="metric-label">Pending Bookings</div>
            </div>
            <div class="metric-card">
                <div class="metric-value"><?php echo formatCurrency($stats['completed_payments']); ?></div>
                <div class="metric-label">Completed Payments</div>
            </div>
        <?php endif; ?>
    </section>

    <div class="admin-layout">
        <div class="admin-stack">
            <section class="admin-panel">
                <div class="panel-head">
                    <h2>Pending Property Reviews</h2>
                    <span class="status-pill status-pending"><?php echo count($pendingProperties); ?> waiting</span>
                </div>

                <?php if (empty($pendingProperties)): ?>
                    <p class="muted">No property listings are waiting for review.</p>
                <?php else: ?>
                    <?php foreach ($pendingProperties as $prop): ?>
                        <?php
                        $images = json_decode($prop['image_urls'] ?? '[]', true);
                        $thumbnail = is_array($images) && !empty($images[0]) ? $images[0] : '';
                        ?>
                        <div class="property-review-card">
                            <div class="property-review-thumb">
                                <?php if ($thumbnail): ?>
                                    <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($prop['name']); ?>">
                                <?php else: ?>
                                    Home
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars($prop['name']); ?></h3>
                                <p class="muted"><?php echo htmlspecialchars($prop['address'] . ', ' . $prop['city']); ?></p>
                                <p>
                                    <strong><?php echo formatCurrency($prop['price_per_month']); ?>/month</strong>
                                    by <?php echo htmlspecialchars(trim(($prop['first_name'] ?? '') . ' ' . ($prop['last_name'] ?? '')) ?: $prop['email']); ?>
                                </p>
                                <div class="action-row">
                                    <a href="<?php echo pageUrl('property-details.php?id=' . $prop['id']); ?>" class="btn btn-mini">Review</a>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="property_status">
                                        <input type="hidden" name="property_id" value="<?php echo $prop['id']; ?>">
                                        <input type="hidden" name="status" value="verified">
                                        <button type="submit" class="btn btn-success btn-mini">Approve</button>
                                    </form>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="property_status">
                                        <input type="hidden" name="property_id" value="<?php echo $prop['id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <input type="text" name="reviewer_notes" placeholder="Reason" aria-label="Rejection reason">
                                        <button type="submit" class="btn btn-danger btn-mini">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="admin-panel">
                <div class="panel-head">
                    <h2>Pending Platform Reviews</h2>
                    <span class="status-pill status-pending"><?php echo count($pendingPlatformReviews); ?> waiting</span>
                </div>

                <?php if (empty($pendingPlatformReviews)): ?>
                    <p class="muted">No platform reviews are waiting for approval.</p>
                <?php else: ?>
                    <?php foreach ($pendingPlatformReviews as $review): ?>
                        <div class="review-item">
                            <strong><?php echo htmlspecialchars($review['title']); ?></strong>
                            <div><?php echo (int)$review['rating']; ?>/5 <span class="muted"><?php echo htmlspecialchars(ucfirst($review['user_role'])); ?></span></div>
                            <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <span class="muted">by <?php echo htmlspecialchars(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')) ?: $review['email']); ?> - <?php echo formatDate($review['created_at']); ?></span>
                            <div class="action-row" style="margin-top: 0.75rem;">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="platform_review_status">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-success btn-mini">Approve</button>
                                </form>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="platform_review_status">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-danger btn-mini">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="admin-panel">
                <div class="panel-head">
                    <h2>Pending User Reviews</h2>
                    <span class="status-pill status-pending"><?php echo count($pendingUserReviews); ?> waiting</span>
                </div>

                <?php if (empty($pendingUserReviews)): ?>
                    <p class="muted">No property reviews are waiting for approval.</p>
                <?php else: ?>
                    <?php foreach ($pendingUserReviews as $review): ?>
                        <div class="review-item">
                            <strong><?php echo htmlspecialchars($review['property_name'] ?? 'Unknown property'); ?></strong>
                            <div><?php echo (int)$review['rating']; ?>/5 <span class="muted"><?php echo htmlspecialchars($review['title'] ?? 'Review'); ?></span></div>
                            <p><?php echo nl2br(htmlspecialchars($review['comment'] ?? '')); ?></p>
                            <span class="muted">by <?php echo htmlspecialchars(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? '')) ?: $review['email']); ?> - <?php echo formatDate($review['created_at']); ?></span>
                            <div class="action-row" style="margin-top: 0.75rem;">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="property_review_status">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <input type="hidden" name="status" value="approved">
                                    <button type="submit" class="btn btn-success btn-mini">Approve</button>
                                </form>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="action" value="property_review_status">
                                    <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="btn btn-danger btn-mini">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <section class="admin-panel">
                <div class="panel-head">
                    <h2>Pending Landlord Verifications</h2>
                    <span class="status-pill status-pending"><?php echo count($pendingUserRequests); ?> waiting</span>
                </div>

                <?php if (empty($pendingUserRequests)): ?>
                    <p class="muted">No landlord verification requests are waiting.</p>
                <?php else: ?>
                    <?php foreach ($pendingUserRequests as $request): ?>
                        <?php $docs = json_decode($request['document_urls'] ?? '{}', true); ?>
                        <div class="property-review-card">
                            <div class="property-review-thumb">
                                <strong><?php echo htmlspecialchars(strtoupper(substr($request['first_name'] ?? 'L', 0, 1) . substr($request['last_name'] ?? 'A', 0, 1))); ?></strong>
                            </div>
                            <div>
                                <h3><?php echo htmlspecialchars(trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? '')) ?: $request['email']); ?></h3>
                                <p class="muted"><?php echo htmlspecialchars($request['email']); ?></p>
                                <p><strong><?php echo htmlspecialchars(ucfirst($request['role'] ?? 'landlord')); ?></strong></p>
                                <p class="muted">Submitted <?php echo formatDate($request['created_at']); ?></p>
                                <?php if (!empty($docs) && is_array($docs)): ?>
                                    <div style="margin: 1rem 0;">
                                        <?php foreach (['government_id' => 'Government ID', 'property_documents' => 'Property Documents', 'proof_of_residence' => 'Proof of Residence'] as $key => $label): ?>
                                            <?php if (!empty($docs[$key])): ?>
                                                <div style="margin-bottom: 0.4rem;"><strong><?php echo $label; ?>:</strong></div>
                                                <ul style="margin: 0 0 0.75rem 1rem; padding: 0; list-style: disc; color: #34495e;">
                                                    <?php foreach ((array)$docs[$key] as $url): ?>
                                                        <li><a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(basename($url)); ?></a></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="action-row">
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="review_user_request">
                                        <input type="hidden" name="user_id" value="<?php echo $request['user_id']; ?>">
                                        <input type="hidden" name="status" value="approved">
                                        <button type="submit" class="btn btn-success btn-mini">Approve</button>
                                    </form>
                                    <form method="post" class="inline-form">
                                        <input type="hidden" name="action" value="review_user_request">
                                        <input type="hidden" name="user_id" value="<?php echo $request['user_id']; ?>">
                                        <input type="hidden" name="status" value="rejected">
                                        <input type="text" name="reviewer_notes" placeholder="Rejection reason" aria-label="Rejection reason">
                                        <button type="submit" class="btn btn-danger btn-mini">Reject</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <?php if ($isFullAdmin): ?>
                <section class="admin-panel">
                    <div class="panel-head">
                        <h2>User Management</h2>
                    </div>

                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></strong><br>
                                            <span class="muted"><?php echo htmlspecialchars($user['email']); ?></span>
                                        </td>
                                        <td>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="action" value="user_role">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <select name="role" <?php echo $user['id'] == $currentAdminId ? 'disabled' : ''; ?>>
                                                    <?php foreach (['student', 'landlord', 'committee', 'admin'] as $roleOption): ?>
                                                        <option value="<?php echo $roleOption; ?>" <?php echo $user['role'] === $roleOption ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst($roleOption); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if ($user['id'] != $currentAdminId): ?>
                                                    <button type="submit" class="btn btn-mini">Save</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        <td>
                                            <span class="status-pill <?php echo $user['is_verified'] ? 'status-verified' : 'status-pending'; ?>">
                                                <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($user['created_at']); ?></td>
                                        <td>
                                            <?php if (!$user['is_verified']): ?>
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="action" value="verify_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-mini">Verify</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="muted">No action</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="admin-stack">
            <?php if ($isFullAdmin): ?>
                <section class="admin-panel">
                    <div class="panel-head">
                        <h3>Recent Bookings</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Booking</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($booking['property_name'] ?? 'Unknown property'); ?></strong><br>
                                            <span class="muted"><?php echo htmlspecialchars(trim($booking['first_name'] . ' ' . $booking['last_name'])); ?></span><br>
                                            <span class="muted"><?php echo formatCurrency($booking['total_price']); ?></span>
                                        </td>
                                        <td>
                                            <form method="post" class="inline-form">
                                                <input type="hidden" name="action" value="booking_status">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <select name="status">
                                                    <?php foreach (['pending', 'confirmed', 'cancelled', 'completed'] as $statusOption): ?>
                                                        <option value="<?php echo $statusOption; ?>" <?php echo $booking['status'] === $statusOption ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst($statusOption); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-mini">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <section class="admin-panel">
                <div class="panel-head">
                    <h3>Property Status</h3>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allProperties as $prop): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($prop['name']); ?></strong><br>
                                        <span class="muted"><?php echo htmlspecialchars($prop['city']); ?> - <?php echo formatCurrency($prop['price_per_month']); ?></span>
                                    </td>
                                    <td>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="action" value="property_status">
                                            <input type="hidden" name="property_id" value="<?php echo $prop['id']; ?>">
                                            <select name="status">
                                                <?php foreach (['pending', 'verified', 'rejected'] as $statusOption): ?>
                                                    <option value="<?php echo $statusOption; ?>" <?php echo $prop['verification_status'] === $statusOption ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst($statusOption); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-mini">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-panel">
                <div class="panel-head">
                    <h3>Recent Reviews</h3>
                </div>
                <?php if (empty($recentReviews)): ?>
                    <p class="muted">No reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($recentReviews as $review): ?>
                        <div class="review-item">
                            <strong><?php echo htmlspecialchars($review['property_name'] ?? 'Unknown property'); ?></strong>
                            <div><?php echo (int)$review['rating']; ?>/5 <span class="muted"><?php echo htmlspecialchars($review['title'] ?? 'Review'); ?></span></div>
                            <span class="muted">by <?php echo htmlspecialchars(trim(($review['first_name'] ?? '') . ' ' . ($review['last_name'] ?? ''))); ?> - <?php echo formatDate($review['created_at']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>
