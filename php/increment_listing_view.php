<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

header('Content-Type: application/json; charset=UTF-8');

$listingId = isset($_GET['listing_id']) ? (int) $_GET['listing_id'] : 0;
if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid listing id']);
    exit;
}

if (empty($_SESSION['emailormobilenumber'])) {
    echo json_encode(['ok' => true, 'tracked' => false]);
    exit;
}

$currentUserStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
if (!$currentUserStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to resolve user']);
    exit;
}

$emailOrMobile = (string) $_SESSION['emailormobilenumber'];
$currentUserStmt->bind_param('s', $emailOrMobile);
$currentUserStmt->execute();
$currentUserRow = $currentUserStmt->get_result()->fetch_assoc();
$currentUserStmt->close();

$currentUserId = (int) ($currentUserRow['id'] ?? 0);
if ($currentUserId <= 0) {
    echo json_encode(['ok' => true, 'tracked' => false]);
    exit;
}

$listingOwnerStmt = $conn->prepare(
    "SELECT l.user_id
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     WHERE l.listing_id = ?
       AND COALESCE(l.moderation_status, 'visible') <> 'hidden'
       AND l.admin_purged_at IS NULL
     LIMIT 1"
);
if (!$listingOwnerStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to resolve listing owner']);
    exit;
}

$listingOwnerStmt->bind_param('i', $listingId);
$listingOwnerStmt->execute();
$listingOwnerRow = $listingOwnerStmt->get_result()->fetch_assoc();
$listingOwnerStmt->close();

$listingOwnerId = (int) ($listingOwnerRow['user_id'] ?? 0);
if ($listingOwnerId <= 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Listing not found']);
    exit;
}

$isOwnerView = $listingOwnerId === $currentUserId;

$conn->query(
    "CREATE TABLE IF NOT EXISTS listing_view_stats (
        user_id INT NOT NULL,
        listing_id INT NOT NULL,
        view_count INT NOT NULL DEFAULT 1,
        last_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, listing_id),
        KEY idx_listing_view_stats_listing_id (listing_id),
        KEY idx_listing_view_stats_last_viewed (last_viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$viewsColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'views'");
if (!$viewsColumnCheck || $viewsColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN views INT NOT NULL DEFAULT 0");
}

$ownerHasPriorCountedView = false;
if ($isOwnerView) {
    $ownerViewCheckStmt = $conn->prepare(
        "SELECT 1
         FROM listing_view_stats
         WHERE user_id = ? AND listing_id = ?
         LIMIT 1"
    );
    if ($ownerViewCheckStmt) {
        $ownerViewCheckStmt->bind_param('ii', $currentUserId, $listingId);
        $ownerViewCheckStmt->execute();
        $ownerHasPriorCountedView = (bool) $ownerViewCheckStmt->get_result()->fetch_row();
        $ownerViewCheckStmt->close();
    }
}

$trackViewStmt = $conn->prepare(
    "INSERT INTO listing_view_stats (user_id, listing_id, view_count, last_viewed_at)
     VALUES (?, ?, 1, NOW())
     ON DUPLICATE KEY UPDATE
        view_count = " . ($isOwnerView ? "view_count" : "view_count + 1") . ",
        last_viewed_at = NOW()"
);

if (!$trackViewStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to track view']);
    exit;
}

$trackViewStmt->bind_param('ii', $currentUserId, $listingId);
$trackViewStmt->execute();
$trackViewStmt->close();

$shouldIncrementVisibleViews = !$isOwnerView || !$ownerHasPriorCountedView;

if ($shouldIncrementVisibleViews) {
    $incrementListingViewsStmt = $conn->prepare(
        "UPDATE listings
         SET views = views + 1
         WHERE listing_id = ?"
    );

    if ($incrementListingViewsStmt) {
        $incrementListingViewsStmt->bind_param('i', $listingId);
        $incrementListingViewsStmt->execute();
        $incrementListingViewsStmt->close();
    }
}

$totalViews = 0;
$totalViewsStmt = $conn->prepare(
    "SELECT views
     FROM listings
     WHERE listing_id = ?
     LIMIT 1"
);

if ($totalViewsStmt) {
    $totalViewsStmt->bind_param('i', $listingId);
    $totalViewsStmt->execute();
    $totalViewsRow = $totalViewsStmt->get_result()->fetch_assoc();
    $totalViews = (int) ($totalViewsRow['views'] ?? 0);
    $totalViewsStmt->close();
}

echo json_encode([
    'ok' => true,
    'tracked' => true,
    'owner_view' => $isOwnerView,
    'counted' => $shouldIncrementVisibleViews,
    'total_views' => $totalViews
]);
