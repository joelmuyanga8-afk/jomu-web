<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';
require __DIR__ . '/partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('Africa/Kampala');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

function getUserCategorySignals(mysqli $conn, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT category
         FROM listings
         WHERE user_id = ?
           AND COALESCE(moderation_status, 'visible') <> 'hidden'
         ORDER BY listing_id DESC
         LIMIT 80"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $signals = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $category = strtolower(trim((string) ($row['category'] ?? '')));
            if ($category === '') {
                continue;
            }

            if (!isset($signals[$category])) {
                $signals[$category] = 0;
            }
            $signals[$category] += 3;

            $tokens = preg_split('/[^a-z0-9]+/', $category) ?: [];
            foreach ($tokens as $token) {
                if ($token === '' || strlen($token) < 3) {
                    continue;
                }
                if (!isset($signals[$token])) {
                    $signals[$token] = 0;
                }
                $signals[$token] += 1;
            }
        }
        $result->free();
    }

    $stmt->close();
    arsort($signals);

    return array_slice($signals, 0, 40, true);
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

    return '/purchase-wholesale?' . http_build_query([
        'image' => getMediaPath((string) ($listing['media'] ?? ''), '/php/'),
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
    $imagePath = getMediaPath((string) ($listing['media'] ?? ''), '/php/');
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
                <img src="' . h($imagePath) . '" class="card-img-top img-fluid media-preview-item media-preview-source" alt="' . h($title) . '" data-preview-type="image" data-preview-src="' . h($imagePath) . '" data-preview-title="' . h($title) . '" data-preview-description="' . h($description) . '" data-preview-price="' . h($priceLabel) . '" data-preview-listing-id="' . (int) ($listing['listing_id'] ?? 0) . '">
                <div class="card-body p-2 d-flex flex-column jomu-card-typography" style="gap: 1px;">
                    <h5 class="card-title mb-0 listing-name-top">' . h($title) . '</h5>
                    <p class="card-text mb-0 listing-description"><a href="' . h($purchaseUrl) . '" class="listing-description-link">' . h($description) . '</a></p>
                    ' . $priceMarkup . '
                    <a href="' . h($actionUrl) . '" class="btn card-img-button py-1 mt-0 listing-action-btn w-100 d-flex align-items-center justify-content-center text-center">' . h($actionButtonLabel) . '</a>
                </div>
            </div>
        </div>';
}

function buildSeasonProfile(int $month): array
{
    $profiles = [
        1 => ['label' => 'Back-to-school and New Year season', 'keywords' => ['back to school', 'school', 'term', 'new year', 'stationery', 'uniform', 'books']],
        2 => ['label' => 'Rain preparation and Valentine season', 'keywords' => ['rain', 'planting', 'seed', 'fertilizer', 'valentine', 'gift']],
        3 => ['label' => 'Rainy planting and Easter season', 'keywords' => ['rain', 'planting', 'seed', 'garden', 'easter', 'lent']],
        4 => ['label' => 'Easter and harvest prep season', 'keywords' => ['easter', 'holiday', 'harvest', 'produce', 'market day']],
        5 => ['label' => 'Second term and harvest season', 'keywords' => ['school', 'term', 'harvest', 'maize', 'beans', 'produce']],
        6 => ['label' => 'Dry season and travel season', 'keywords' => ['dry season', 'travel', 'tour', 'sun', 'outdoor']],
        7 => ['label' => 'Dry season and mid-year demand', 'keywords' => ['dry season', 'outdoor', 'holiday', 'tourism']],
        8 => ['label' => 'Back-to-school preparation season', 'keywords' => ['back to school', 'stationery', 'books', 'uniform', 'school']],
        9 => ['label' => 'School term and planting season', 'keywords' => ['school', 'term', 'planting', 'rain', 'seed']],
        10 => ['label' => 'Harvest and festive preparation season', 'keywords' => ['harvest', 'produce', 'festive', 'celebration']],
        11 => ['label' => 'Festive preparation season', 'keywords' => ['festive', 'christmas', 'holiday', 'party', 'gift', 'black friday']],
        12 => ['label' => 'Festive season', 'keywords' => ['festive', 'christmas', 'holiday', 'new year', 'party', 'gift']],
    ];

    return $profiles[$month] ?? $profiles[date('n')];
}

function buildRegionalTrendProfile(int $month): array
{
    $profiles = [
        1 => ['label' => 'Back-to-school and New Year essentials', 'keywords' => ['exercise book', 'books', 'ream paper', 'pen', 'pencil', 'school bag', 'backpack', 'uniform', 'school shoes', 'lunch box', 'water bottle']],
        2 => ['label' => 'Rain and early planting inputs', 'keywords' => ['seed', 'seeds', 'fertilizer', 'manure', 'sprayer', 'gumboots', 'rain coat', 'umbrella', 'watering can', 'pesticide']],
        3 => ['label' => 'Rainy planting and easter demand', 'keywords' => ['maize seed', 'bean seed', 'garden tools', 'gumboots', 'rain coat', 'easter dress', 'gift basket', 'soft drinks', 'cooking oil']],
        4 => ['label' => 'Easter and first harvest movement', 'keywords' => ['eggs', 'chicken', 'goat meat', 'rice', 'sugar', 'juice', 'maize', 'beans', 'fresh produce']],
        5 => ['label' => 'Harvest and school term demand', 'keywords' => ['maize', 'beans', 'rice', 'posho flour', 'cassava flour', 'uniform', 'exercise books', 'stationery']],
        6 => ['label' => 'Dry season travel and construction demand', 'keywords' => ['cement', 'paint', 'roofing sheet', 'water tank', 'sun hat', 'travel bag', 'sunscreen', 'mineral water']],
        7 => ['label' => 'Dry season events and tourism demand', 'keywords' => ['event tent', 'chairs', 'sound system', 'travel bag', 'cool drink', 'snacks', 'sunglasses', 'outdoor wear']],
        8 => ['label' => 'Back-to-school preparation', 'keywords' => ['exercise book', 'stationery', 'school bag', 'uniform', 'school shoes', 'lunch box', 'water bottle']],
        9 => ['label' => 'School and planting transition', 'keywords' => ['stationery', 'uniform', 'seeds', 'fertilizer', 'pesticide', 'gumboots', 'umbrella']],
        10 => ['label' => 'Harvest and festive stock-up', 'keywords' => ['maize', 'beans', 'rice', 'sugar', 'cooking oil', 'wheat flour', 'soft drinks', 'juice', 'gift wrap']],
        11 => ['label' => 'Festive preparation', 'keywords' => ['rice', 'sugar', 'cooking oil', 'wheat flour', 'soft drinks', 'juice', 'meat', 'chicken', 'decor lights', 'gift items', 'party wear']],
        12 => ['label' => 'Festive peak demand', 'keywords' => ['rice', 'sugar', 'cooking oil', 'soft drinks', 'juice', 'meat', 'chicken', 'party wear', 'gift items', 'decor lights']],
    ];

    return $profiles[$month] ?? $profiles[date('n')];
}

function countKeywordHits(string $haystack, array $keywords): int
{
    $hits = 0;
    foreach ($keywords as $keyword) {
        $needle = strtolower(trim((string) $keyword));
        if ($needle !== '' && strpos($haystack, $needle) !== false) {
            $hits++;
        }
    }

    return $hits;
}

function scoreSeasonalListing(array $listing, array $currentSeason, array $nextSeason, array $previousSeason, array $regionalProfile, array $userCategorySignals): float
{
    $category = strtolower(trim((string) ($listing['category'] ?? '')));
    $description = strtolower(trim((string) ($listing['description'] ?? '')));
    $stockName = strtolower(trim((string) ($listing['stockname'] ?? '')));
    $haystack = $category . ' ' . $description . ' ' . $stockName;

    $hasSeasonWord = strpos($category, 'season') !== false || strpos($description, 'season') !== false;
    if (!$hasSeasonWord) {
        return -1.0;
    }

    $currentHits = countKeywordHits($haystack, (array) ($currentSeason['keywords'] ?? []));
    $nextHits = countKeywordHits($haystack, (array) ($nextSeason['keywords'] ?? []));
    $previousHits = countKeywordHits($haystack, (array) ($previousSeason['keywords'] ?? []));

    $genericSeasonWords = ['seasonal', 'harvest', 'festive', 'easter', 'eid', 'christmas', 'rain', 'dry season', 'school'];
    $genericHits = countKeywordHits($haystack, $genericSeasonWords);
    $regionalHits = countKeywordHits($haystack, (array) ($regionalProfile['keywords'] ?? []));
    $userCategoryHits = 0;
    $userCategoryBoost = 0.0;
    foreach ($userCategorySignals as $term => $weight) {
        if ($term !== '' && strpos($haystack, (string) $term) !== false) {
            $userCategoryHits++;
            $userCategoryBoost += min(3.0, ((float) $weight) * 0.45);
        }
    }
    $seasonSignal = ($currentHits * 1.0) + ($nextHits * 0.75) + ($previousHits * 0.55);

    if ($hasSeasonWord) {
        if ($seasonSignal <= 0.0) {
            return -1.0;
        }

        $score = 12.0;
        $score += $currentHits * 5.0;
        $score += $nextHits * 3.5;
        $score += $previousHits * 2.5;
        $score += min(4, $genericHits);
        $score += min(4, $regionalHits * 0.5);
        $score += min(8.0, $userCategoryBoost);
        $score += min(3, $userCategoryHits) * 0.8;

        return $score;
    }

    // Strict fallback path: allow non-"season" text only when strongly aligned
    // with active season signals + regional trend basket (high-confidence gate).
    if ($seasonSignal < 1.8 || $regionalHits < 2) {
        return -1.0;
    }

    $confidence = min(1.0, ($seasonSignal * 0.28) + ($regionalHits * 0.18) + ($genericHits * 0.08));
    if ($confidence < 0.90) {
        return -1.0;
    }

    $score = 11.0;
    $score += $currentHits * 5.0;
    $score += $nextHits * 3.5;
    $score += $previousHits * 2.5;
    $score += $regionalHits * 2.0;
    $score += min(4, $genericHits);
    $score += min(8.0, $userCategoryBoost);
    $score += min(3, $userCategoryHits) * 0.8;

    return $score;
}

$outOfStockFilter = listingTableHasColumn($conn, 'out_of_stock') ? 'COALESCE(l.out_of_stock, 0) = 0 AND ' : '';
$moderationFilter = jomu_listing_public_visibility_filters($conn, 'l');

$sql = "
    SELECT l.*, u.businessname AS seller_businessname, u.profilepic AS seller_profilepic
    FROM listings l
    INNER JOIN users u ON u.id = l.user_id
    WHERE {$outOfStockFilter}{$moderationFilter} LOWER(TRIM(COALESCE(l.listing_type, 'product'))) = 'product'
    ORDER BY l.listing_id DESC
    LIMIT 350
";

$currentMonth = (int) date('n');
$nextMonth = $currentMonth === 12 ? 1 : $currentMonth + 1;
$previousMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;
$currentSeason = buildSeasonProfile($currentMonth);
$nextSeason = buildSeasonProfile($nextMonth);
$previousSeason = buildSeasonProfile($previousMonth);
$regionalProfile = buildRegionalTrendProfile($currentMonth);

$currentUserId = getCurrentUserId($conn);
$userCategorySignals = getUserCategorySignals($conn, $currentUserId);
$rankedListings = [];

$result = $conn->query($sql);
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $media = trim((string) ($row['media'] ?? ''));
        if ($media === '' || getMediaType($media) === 'video') {
            continue;
        }

        $score = scoreSeasonalListing($row, $currentSeason, $nextSeason, $previousSeason, $regionalProfile, $userCategorySignals);
        if ($score < 0) {
            continue;
        }

        $rankedListings[] = [
            'score' => $score,
            'listing_id' => (int) ($row['listing_id'] ?? 0),
            'row' => $row,
        ];
    }
    $result->free();
}

usort($rankedListings, static function (array $a, array $b): int {
    if ($a['score'] === $b['score']) {
        return $b['listing_id'] <=> $a['listing_id'];
    }
    return $b['score'] <=> $a['score'];
});

$rankedListings = array_slice($rankedListings, 0, 120);

if ($rankedListings === []) {
    echo '<div class="col-12"><div class="bg-white rounded p-3 text-center">No listings matched the current season.</div></div>';
    exit;
}

foreach ($rankedListings as $ranked) {
    echo renderListingCard($ranked['row'], $currentUserId);
}
