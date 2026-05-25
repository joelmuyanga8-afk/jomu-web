<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/listing_preview_dataset.php';

header('Content-Type: text/html; charset=UTF-8');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function regexEscapeTerm(string $value): string
{
    return preg_quote(strtolower($value), '/');
}


function getCurrentUserId(mysqli $conn): int
{
    if (empty($_SESSION['emailormobilenumber'])) {
        return 0;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if (!$stmt) {
        return 0;
    }

    $emailOrMobile = (string) $_SESSION['emailormobilenumber'];
    $stmt->bind_param('s', $emailOrMobile);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['id'] ?? 0);
}

function buildPriceLabel(array $listing): string
{
    $listingType = strtolower(trim((string) ($listing['listing_type'] ?? '')));
    if ($listingType !== 'product' && $listingType !== 'service') {
        $categoryText = strtolower((string) ($listing['category'] ?? ''));
        $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
    }

    if ($listingType !== 'product') {
        return formatPriceText(trim((string) ($listing['price'] ?? '')));
    }

    $priceFrom = trim((string) ($listing['price_from'] ?? ''));
    $priceTo = trim((string) ($listing['price_to'] ?? ''));

    if ($priceFrom !== '' && $priceTo !== '') {
        return formatProductPriceRange($priceFrom, $priceTo);
    }

    return formatPriceText(trim((string) ($listing['price'] ?? '')));
}

function buildPurchaseUrl(array $listing, string $listingType, bool $isOwnListing): string
{
    $priceFrom = trim((string) ($listing['price_from'] ?? ''));
    $priceTo = trim((string) ($listing['price_to'] ?? ''));
    $priceLabel = buildPriceLabel($listing);

    return 'purchasewholesale.html?' . http_build_query([
        'image' => getMediaPath((string) ($listing['media'] ?? ''), 'php/'),
        'title' => $listing['stockname'] ?? '',
        'price' => $priceLabel,
        'raw_price' => $listing['price'] ?? '',
        'price_from' => $priceFrom,
        'price_to' => $priceTo,
        'description' => $listing['description'] ?? '',
        'category' => $listing['category'] ?? '',
        'seller_businessname' => $listing['seller_businessname'] ?? '',
        'seller_profilepic' => $listing['seller_profilepic'] ?? '',
        'seller_id' => $listing['user_id'] ?? '',
        'listing_id' => $listing['listing_id'] ?? '',
        'listing_type' => $listingType,
        'owner_view' => $isOwnListing ? '1' : '0',
    ]);
}

function renderListingCard(array $listing, int $currentUserId): string
{
    $listingType = strtolower(trim((string) ($listing['listing_type'] ?? '')));
    if ($listingType !== 'product' && $listingType !== 'service') {
        $categoryText = strtolower((string) ($listing['category'] ?? ''));
        $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
    }

    $title = trim((string) ($listing['stockname'] ?? ''));
    $description = trim((string) ($listing['description'] ?? ''));
    $imagePath = getMediaPath((string) ($listing['media'] ?? ''), 'php/');
    $priceLabel = buildPriceLabel($listing);
    $isOwnListing = $currentUserId > 0 && (int) ($listing['user_id'] ?? 0) === $currentUserId;
    $purchaseUrl = buildPurchaseUrl($listing, $listingType, $isOwnListing);
    $actionButtonLabel = $isOwnListing
        ? 'See Listing'
        : ($listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale');
    $actionUrl = (!$isOwnListing && $currentUserId <= 0)
        ? '/?error=Not+Signed+In!'
        : $purchaseUrl;

    if ($title === '') {
        $title = 'Listing';
    }

    $priceMarkup = '';
    $previewDatasetAttrs = jomu_listing_preview_dataset_attr_html($listing);

    if ($priceLabel !== '') {
        $priceText = $priceLabel;
        $unitText = '';
        if ($listingType === 'product' && substr($priceLabel, -7) === ' / unit') {
            $priceText = substr($priceLabel, 0, -7);
            $unitText = ' / unit';
        }

        $priceMarkup = '<p class="card-text mb-0 product-price-range">' . h($priceText);
        if ($unitText !== '') {
            $priceMarkup .= '<span class="price-unit">' . h($unitText) . '</span>';
        }
        $priceMarkup .= '</p>';
    }

    return '
        <div class="col-6 col-md-4 col-lg-3 listing-card-item">
            <div class="card h-100">
                <img src="' . h($imagePath) . '" class="card-img-top img-fluid media-preview-item media-preview-source" alt="' . h($title) . '" data-preview-type="image" data-preview-src="' . h($imagePath) . '" data-preview-title="' . h($title) . '" data-preview-description="' . h($description) . '" data-preview-price="' . h($priceLabel) . '" data-preview-listing-id="' . (int) ($listing['listing_id'] ?? 0) . '"' . $previewDatasetAttrs . '>
                <div class="card-body p-2 d-flex flex-column jomu-card-typography" style="gap: 1px;">
                    <h5 class="card-title mb-0 listing-name-top">' . h($title) . '</h5>
                    <p class="card-text mb-0 listing-description"><a href="' . h($purchaseUrl) . '" class="listing-description-link">' . h($description) . '</a></p>
                    ' . $priceMarkup . '
                    <a href="' . h($actionUrl) . '" class="btn card-img-button py-1 mt-0 listing-action-btn w-100 d-flex align-items-center justify-content-center text-center">' . h($actionButtonLabel) . '</a>
                </div>
            </div>
        </div>';
}

$categoryName = 'Construction & Building Materials';
$params = [$categoryName];
$types = 's';
$outOfStockFilter = listingTableHasColumn($conn, 'out_of_stock') ? 'COALESCE(l.out_of_stock, 0) = 0 AND ' : '';
$moderationFilter = jomu_listing_public_visibility_filters($conn, 'l');

$sql = "
    SELECT l.*, u.businessname AS seller_businessname, u.profilepic AS seller_profilepic
    FROM listings l
    INNER JOIN users u ON u.id = l.user_id
    WHERE {$outOfStockFilter}{$moderationFilter} LOWER(TRIM(COALESCE(l.listing_type, 'product'))) = 'product'
      AND LOWER(TRIM(COALESCE(l.category, ''))) = LOWER(TRIM(?))
    ORDER BY l.listing_id DESC
    LIMIT 120
";

$listings = [];
$currentUserId = getCurrentUserId($conn);
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $media = trim((string) ($row['media'] ?? ''));
                if ($media === '' || getMediaType($media) === 'video') {
                    continue;
                }
                $listings[] = $row;
            }
            $result->free();
        }
    }
    $stmt->close();
}

if ($listings === []) {
    echo '<div class="col-12"><div class="bg-white rounded p-3 text-center">No construction or building-materials-related listings are available right now.</div></div>';
    exit;
}

foreach ($listings as $listing) {
    echo renderListingCard($listing, $currentUserId);
}
