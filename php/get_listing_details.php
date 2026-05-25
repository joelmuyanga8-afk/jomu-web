<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';
require __DIR__ . '/partials/admin_helpers.php';

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
    "SELECT l.listing_id, l.user_id, l.stockname, l.description, l.category, l.media,
            l.price, l.price_from, l.price_to, l.listing_type, l.moderation_status, l.admin_purged_at,
            u.businessname, u.profilepic
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     WHERE l.listing_id = ?
     LIMIT 1"
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load listing details.',
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
        'listing' => [
            'listing_id' => (int) ($listing['listing_id'] ?? 0),
            'display_blurred' => true,
            'stockname' => '',
            'description' => '',
        ],
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

echo json_encode([
    'success' => true,
    'listing' => [
        'listing_id' => (int) ($listing['listing_id'] ?? 0),
        'seller_id' => (int) ($listing['user_id'] ?? 0),
        'stockname' => trim((string) ($listing['stockname'] ?? '')),
        'description' => (string) ($listing['description'] ?? ''),
        'category' => (string) ($listing['category'] ?? ''),
        'media' => getMediaPath((string) ($listing['media'] ?? ''), 'php/'),
        'price' => (string) ($listing['price'] ?? ''),
        'price_from' => (string) ($listing['price_from'] ?? ''),
        'price_to' => (string) ($listing['price_to'] ?? ''),
        'listing_type' => (string) ($listing['listing_type'] ?? ''),
        'seller_businessname' => trim((string) ($listing['businessname'] ?? '')),
        'seller_profilepic' => (string) ($listing['profilepic'] ?? ''),
    ],
]);
