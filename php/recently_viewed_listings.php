<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';
require __DIR__ . '/partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

$rawIds = trim((string) ($_GET['ids'] ?? ''));
if ($rawIds === '') {
    echo json_encode(['ok' => true, 'listings' => []]);
    exit;
}

$ids = array_values(array_unique(array_filter(array_map(
    static fn(string $value): int => (int) trim($value),
    explode(',', $rawIds)
), static fn(int $value): bool => $value > 0)));

if ($ids === []) {
    echo json_encode(['ok' => true, 'listings' => []]);
    exit;
}

$ids = array_slice($ids, 0, 20);

$currentUserId = 0;
if (!empty($_SESSION['emailormobilenumber'])) {
    $userStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param('s', $_SESSION['emailormobilenumber']);
        $userStmt->execute();
        $userRow = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
        $currentUserId = (int) ($userRow['id'] ?? 0);
    }
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));
$sql = "
    SELECT l.*, u.businessname AS seller_businessname, u.profilepic AS seller_profilepic
    FROM listings l
    INNER JOIN users u ON u.id = l.user_id
    WHERE l.listing_id IN ({$placeholders})
      AND COALESCE(l.moderation_status, 'visible') <> 'hidden'
      AND l.admin_purged_at IS NULL
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to load listings.']);
    exit;
}

$stmt->bind_param($types, ...$ids);
$stmt->execute();
$result = $stmt->get_result();

$listings = [];
while ($row = $result->fetch_assoc()) {
    $listingType = strtolower((string) ($row['listing_type'] ?? ''));
    if ($listingType !== 'product' && $listingType !== 'service') {
        $categoryText = strtolower((string) ($row['category'] ?? ''));
        $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
    }

    $priceFrom = trim((string) ($row['price_from'] ?? ''));
    $priceTo = trim((string) ($row['price_to'] ?? ''));
    $productPriceLabel = '';
    if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
        $productPriceLabel = formatProductPriceRange($priceFrom, $priceTo);
    } elseif ($listingType === 'product') {
        $productPriceLabel = trim((string) ($row['price'] ?? ''));
    }

    $isOwnListing = $currentUserId > 0 && (int) ($row['user_id'] ?? 0) === $currentUserId;
    $actionButtonLabel = $isOwnListing
        ? 'See Listing'
        : ($listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale');

    $purchaseParams = http_build_query([
        'image' => getMediaPath($row['media'] ?? '', 'php/'),
        'title' => $row['stockname'] ?? '',
        'price' => $productPriceLabel !== '' ? $productPriceLabel : ($row['price'] ?? ''),
        'raw_price' => $row['price'] ?? '',
        'price_from' => $priceFrom,
        'price_to' => $priceTo,
        'description' => $row['description'] ?? '',
        'category' => $row['category'] ?? '',
        'seller_businessname' => $row['seller_businessname'] ?? '',
        'seller_profilepic' => $row['seller_profilepic'] ?? '',
        'seller_id' => $row['user_id'] ?? '',
        'listing_id' => $row['listing_id'] ?? '',
        'listing_type' => $listingType,
        'owner_view' => $isOwnListing ? '1' : '0',
    ]);

    $purchaseUrl = 'purchasewholesale.html?' . $purchaseParams;
    $actionUrl = (!$isOwnListing && $currentUserId <= 0)
        ? '/?error=Not+Signed+In!'
        : $purchaseUrl;

    $savedHashtags = trim((string) ($row['hashtags'] ?? ''));
    if ($savedHashtags !== '') {
        $savedHashtags = preg_replace('/\s+/', ' ', $savedHashtags);
    }

    $tagSources = [
        (string) ($row['listing_type'] ?? ''),
        (string) ($row['category'] ?? ''),
    ];
    $hashtags = [];
    foreach ($tagSources as $sourceTag) {
        $cleanedTag = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $sourceTag);
        $parts = preg_split('/\s+/', trim((string) $cleanedTag));
        $joined = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $joined .= ucfirst(strtolower($part));
        }
        if ($joined !== '') {
            $hashTag = '#' . $joined;
            if (!in_array($hashTag, $hashtags, true)) {
                $hashtags[] = $hashTag;
            }
        }
    }
    if (count($hashtags) === 0) {
        $hashtags[] = '#JoMu';
    }

    $listings[] = [
        'listing_id' => (int) ($row['listing_id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'media_type' => getMediaType((string) ($row['media'] ?? '')),
        'media_src' => getMediaPath((string) ($row['media'] ?? ''), 'php/'),
        'title' => trim((string) ($row['stockname'] ?? '')),
        'description' => trim((string) ($row['description'] ?? '')),
        'category' => trim((string) ($row['category'] ?? '')),
        'price' => trim((string) ($productPriceLabel !== '' ? $productPriceLabel : ($row['price'] ?? ''))),
        'action_label' => $actionButtonLabel,
        'action_url' => $actionUrl,
        'purchase_url' => $purchaseUrl,
        'seller_name' => trim((string) ($row['seller_businessname'] ?? '')) ?: 'Business',
        'seller_profilepic' => trim((string) ($row['seller_profilepic'] ?? '')),
        'is_own_listing' => $isOwnListing,
        'hashtags' => $savedHashtags !== '' ? $savedHashtags : implode(' ', $hashtags),
    ];
}
$stmt->close();

echo json_encode([
    'ok' => true,
    'listings' => $listings,
]);
