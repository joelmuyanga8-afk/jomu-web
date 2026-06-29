<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require "connection/dbconn.php";
require "partials/helpers.php";

$conn->query(
    "CREATE TABLE IF NOT EXISTS recent_listing_views (
        viewer_key VARCHAR(191) NOT NULL,
        listing_id INT UNSIGNED NOT NULL,
        viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (viewer_key, listing_id),
        KEY idx_recent_listing_views_viewed_at (viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$currentUserId = 0;
if (!empty($_SESSION['emailormobilenumber'])) {
    $viewerStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if ($viewerStmt) {
        $viewerStmt->bind_param("s", $_SESSION['emailormobilenumber']);
        $viewerStmt->execute();
        $viewerRow = $viewerStmt->get_result()->fetch_assoc();
        $viewerStmt->close();
        $currentUserId = (int) ($viewerRow['id'] ?? 0);
    }
}

$viewerKey = $currentUserId > 0 ? ('user:' . $currentUserId) : ('session:' . session_id());
$recentListings = [];

$recentStmt = $conn->prepare(
    "SELECT
        rv.listing_id,
        rv.viewed_at,
        l.user_id,
        l.stockname,
        l.description,
        l.price,
        l.price_from,
        l.price_to,
        l.media,
        l.category,
        l.listing_type
     FROM recent_listing_views rv
     INNER JOIN listings l ON l.listing_id = rv.listing_id
     WHERE rv.viewer_key = ?
     ORDER BY rv.viewed_at DESC
     LIMIT 20"
);

if ($recentStmt) {
    $recentStmt->bind_param("s", $viewerKey);
    $recentStmt->execute();
    $result = $recentStmt->get_result();

    while ($listing = $result->fetch_assoc()) {
        $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
        if ($listingType !== 'product' && $listingType !== 'service') {
            $categoryText = strtolower((string) ($listing['category'] ?? ''));
            $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
        }

        $priceFrom = trim((string) ($listing['price_from'] ?? ''));
        $priceTo = trim((string) ($listing['price_to'] ?? ''));
        $priceLabel = '';
        if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
            $priceLabel = 'USh ' . $priceFrom . ' - ' . $priceTo . ' / unit';
        } elseif ($listingType === 'product') {
            $priceLabel = trim((string) ($listing['price'] ?? ''));
        }

        $media = (string) ($listing['media'] ?? '');
        $recentListings[] = [
            'listing_id' => (int) ($listing['listing_id'] ?? 0),
            'viewed_at' => (string) ($listing['viewed_at'] ?? ''),
            'media_type' => getMediaType($media) === 'video' ? 'video' : 'image',
            'media_src' => getMediaPath($media, '/php/'),
            'title' => (string) ($listing['stockname'] ?? ''),
            'description' => (string) ($listing['description'] ?? ''),
            'price' => $priceLabel !== '' ? $priceLabel : (string) ($listing['price'] ?? ''),
            'listing_type' => $listingType,
            'action_label' => $listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale',
            'purchase_url' => '/purchase-wholesale?' . http_build_query([
                'image' => getMediaPath($media, '/php/'),
                'title' => $listing['stockname'] ?? '',
                'price' => $priceLabel !== '' ? $priceLabel : ($listing['price'] ?? ''),
                'raw_price' => $listing['price'] ?? '',
                'price_from' => $priceFrom,
                'price_to' => $priceTo,
                'description' => $listing['description'] ?? '',
                'category' => $listing['category'] ?? '',
                'seller_businessname' => '',
                'seller_profilepic' => '',
                'seller_id' => $listing['user_id'] ?? '',
                'listing_id' => $listing['listing_id'] ?? '',
                'listing_type' => $listingType,
                'owner_view' => $currentUserId > 0 && $currentUserId === (int) ($listing['user_id'] ?? 0) ? '1' : '0',
            ]),
        ];
    }

    $recentStmt->close();
}

echo json_encode([
    'success' => true,
    'items' => $recentListings,
]);
