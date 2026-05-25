<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');

require 'connection/dbconn.php';
require 'partials/helpers.php';
require 'partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

$listingId = (int) ($_GET['listing_id'] ?? 0);
$ownerView = ($_GET['owner_view'] ?? '') === '1';
if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid listing id.',
    ]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT l.listing_id, l.user_id, l.media, l.moderation_status, l.admin_purged_at
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     WHERE l.listing_id = ?
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load listing gallery.',
    ]);
    exit;
}

$stmt->bind_param('i', $listingId);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$listing) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Listing not found.',
    ]);
    exit;
}

if (trim((string) ($listing['admin_purged_at'] ?? '')) !== '') {
    echo json_encode([
        'success' => true,
        'display_blurred' => true,
        'listing_id' => $listingId,
        'main_media' => '',
        'images' => [],
    ]);
    exit;
}

$isHidden = strtolower((string) ($listing['moderation_status'] ?? 'visible')) === 'hidden';
$canViewHiddenListing = false;
if ($isHidden && $ownerView) {
    $adminUser = jomu_current_admin($conn);
    if ($adminUser) {
        $canViewHiddenListing = true;
    } elseif (!empty($_SESSION['emailormobilenumber'])) {
        $ownerCheckStmt = $conn->prepare('SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1');
        if ($ownerCheckStmt) {
            $ownerCheckStmt->bind_param('s', $_SESSION['emailormobilenumber']);
            $ownerCheckStmt->execute();
            $ownerRow = $ownerCheckStmt->get_result()->fetch_assoc();
            $ownerCheckStmt->close();
            $canViewHiddenListing = (int) ($ownerRow['id'] ?? 0) === (int) ($listing['user_id'] ?? 0);
        }
    }
}

if ($isHidden && !$canViewHiddenListing) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Listing not found.',
    ]);
    exit;
}

$mainMedia = trim((string) ($listing['media'] ?? ''));
$galleryImages = getListingGalleryImages($conn, $listingId);

echo json_encode([
    'success' => true,
    'listing_id' => $listingId,
    'main_media' => $mainMedia,
    'images' => $galleryImages,
]);
