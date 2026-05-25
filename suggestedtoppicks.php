<?php
session_start();

require 'php/connection/dbconn.php';
require 'php/partials/helpers.php';

$image_listings = [];
$video_listings = [];
$allRankedListings = [];
$currentUserId = 0;

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

$regionColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'region'");
if (!$regionColumnCheck || $regionColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN region VARCHAR(20) NULL AFTER category");
}

$cityTownColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'city_town'");
if (!$cityTownColumnCheck || $cityTownColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN city_town VARCHAR(120) NULL AFTER region");
}

$createdAtColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'created_at'");
if (!$createdAtColumnCheck || $createdAtColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}

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

$conn->query(
    "CREATE TABLE IF NOT EXISTS user_search_interest (
        user_id INT NOT NULL,
        search_term VARCHAR(190) NOT NULL,
        search_count INT NOT NULL DEFAULT 1,
        last_searched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, search_term),
        KEY idx_user_search_interest_last (last_searched_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

function normalizeInterestText(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);

    return $value ?? '';
}

function extractInterestTokens(string $value): array
{
    $value = normalizeInterestText($value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/[^a-z0-9]+/', $value) ?: [];
    $tokens = [];
    $stopWords = [
        'and', 'the', 'for', 'with', 'from', 'this', 'that', 'your', 'into', 'just',
        'more', 'than', 'have', 'has', 'had', 'are', 'was', 'were', 'you', 'our',
        'their', 'them', 'its', 'his', 'her', 'she', 'him', 'about', 'these', 'those',
        'bulk', 'unit'
    ];

    foreach ($parts as $part) {
        if ($part === '' || strlen($part) < 3 || in_array($part, $stopWords, true)) {
            continue;
        }
        $tokens[$part] = true;
    }

    return array_keys($tokens);
}

function addInterestWeights(array &$phraseWeights, array &$tokenWeights, string $text, float $phraseWeight, float $tokenWeight): void
{
    $normalized = normalizeInterestText($text);
    if ($normalized !== '') {
        if (!isset($phraseWeights[$normalized])) {
            $phraseWeights[$normalized] = 0.0;
        }
        $phraseWeights[$normalized] += $phraseWeight;
    }

    foreach (extractInterestTokens($text) as $token) {
        if (!isset($tokenWeights[$token])) {
            $tokenWeights[$token] = 0.0;
        }
        $tokenWeights[$token] += $tokenWeight;
    }
}

function collectUserInterestWeights(mysqli $conn, int $userId): array
{
    $phraseWeights = [];
    $tokenWeights = [];

    if ($userId <= 0) {
        return [$phraseWeights, $tokenWeights];
    }

    $ownListingsStmt = $conn->prepare(
        "SELECT category, listing_type, stockname, description
         FROM listings
         WHERE user_id = ?
         ORDER BY listing_id DESC
         LIMIT 80"
    );
    if ($ownListingsStmt) {
        $ownListingsStmt->bind_param('i', $userId);
        $ownListingsStmt->execute();
        $ownListingsRes = $ownListingsStmt->get_result();
        while ($row = $ownListingsRes->fetch_assoc()) {
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['category'] ?? ''), 8.0, 4.0);
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['listing_type'] ?? ''), 4.0, 2.0);
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['stockname'] ?? ''), 2.0, 1.5);
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['description'] ?? ''), 0.6, 0.4);
        }
        $ownListingsStmt->close();
    }

    $viewedListingsStmt = $conn->prepare(
        "SELECT l.category, l.listing_type, l.stockname, l.description, v.view_count
         FROM listing_view_stats v
         INNER JOIN listings l ON l.listing_id = v.listing_id
         WHERE v.user_id = ?
         ORDER BY v.view_count DESC, v.last_viewed_at DESC
         LIMIT 80"
    );
    if ($viewedListingsStmt) {
        $viewedListingsStmt->bind_param('i', $userId);
        $viewedListingsStmt->execute();
        $viewedListingsRes = $viewedListingsStmt->get_result();
        while ($row = $viewedListingsRes->fetch_assoc()) {
            $multiplier = max(1, min(5, (int) ($row['view_count'] ?? 1)));
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['category'] ?? ''), 4.0 * $multiplier, 2.2 * $multiplier);
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['listing_type'] ?? ''), 2.0 * $multiplier, 1.2 * $multiplier);
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['stockname'] ?? ''), 1.4 * $multiplier, 1.1 * $multiplier);
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['description'] ?? ''), 0.5 * $multiplier, 0.35 * $multiplier);
        }
        $viewedListingsStmt->close();
    }

    $searchInterestStmt = $conn->prepare(
        "SELECT search_term, search_count
         FROM user_search_interest
         WHERE user_id = ?
         ORDER BY search_count DESC, last_searched_at DESC
         LIMIT 50"
    );
    if ($searchInterestStmt) {
        $searchInterestStmt->bind_param('i', $userId);
        $searchInterestStmt->execute();
        $searchInterestRes = $searchInterestStmt->get_result();
        while ($row = $searchInterestRes->fetch_assoc()) {
            $multiplier = max(1, min(6, (int) ($row['search_count'] ?? 1)));
            addInterestWeights($phraseWeights, $tokenWeights, (string) ($row['search_term'] ?? ''), 5.0 * $multiplier, 3.0 * $multiplier);
        }
        $searchInterestStmt->close();
    }

    return [$phraseWeights, $tokenWeights];
}

function scoreRecommendedListing(array $listing, array $phraseWeights, array $tokenWeights, int $currentUserId): float
{
    $score = 0.0;
    $category = normalizeInterestText((string) ($listing['category'] ?? ''));
    $listingType = normalizeInterestText((string) ($listing['listing_type'] ?? ''));

    if ($category !== '' && isset($phraseWeights[$category])) {
        $score += $phraseWeights[$category] * 2.6;
    }
    if ($listingType !== '' && isset($phraseWeights[$listingType])) {
        $score += $phraseWeights[$listingType] * 1.8;
    }

    $combinedText = implode(' ', [
        (string) ($listing['stockname'] ?? ''),
        (string) ($listing['category'] ?? ''),
        (string) ($listing['description'] ?? ''),
        (string) ($listing['listing_type'] ?? '')
    ]);

    foreach (extractInterestTokens($combinedText) as $token) {
        if (isset($tokenWeights[$token])) {
            $score += $tokenWeights[$token];
        }
    }

    $createdAt = strtotime((string) ($listing['created_at'] ?? '')) ?: time();
    $ageInDays = max(0.0, (time() - $createdAt) / 86400);
    $score += max(0.0, 18.0 - min(18.0, $ageInDays)) * 0.22;

    if ($currentUserId > 0 && (int) ($listing['user_id'] ?? 0) === $currentUserId) {
        $score += 4.0;
    }

    return $score;
}

function listingMatchesUserInterest(array $listing, array $phraseWeights, array $tokenWeights): bool
{
    if ($phraseWeights === [] && $tokenWeights === []) {
        return false;
    }

    $category = normalizeInterestText((string) ($listing['category'] ?? ''));
    $listingType = normalizeInterestText((string) ($listing['listing_type'] ?? ''));

    if ($category !== '' && isset($phraseWeights[$category])) {
        return true;
    }

    if ($listingType !== '' && isset($phraseWeights[$listingType])) {
        return true;
    }

    $combinedText = implode(' ', [
        (string) ($listing['stockname'] ?? ''),
        (string) ($listing['category'] ?? ''),
        (string) ($listing['description'] ?? ''),
        (string) ($listing['listing_type'] ?? '')
    ]);

    foreach (extractInterestTokens($combinedText) as $token) {
        if (isset($tokenWeights[$token])) {
            return true;
        }
    }

    return false;
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
    return 'purchasewholesale.html?' . http_build_query([
        'image' => getMediaPath((string) ($listing['media'] ?? ''), 'php/'),
        'title' => $listing['stockname'] ?? '',
        'price' => buildPriceLabel($listing),
        'raw_price' => $listing['price'] ?? '',
        'price_from' => $listing['price_from'] ?? '',
        'price_to' => $listing['price_to'] ?? '',
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

if (!empty($_SESSION['emailormobilenumber'])) {
    $currentUserStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if ($currentUserStmt) {
        $currentUserStmt->bind_param("s", $_SESSION['emailormobilenumber']);
        $currentUserStmt->execute();
        $currentUserRow = $currentUserStmt->get_result()->fetch_assoc();
        $currentUserStmt->close();
        if (!empty($currentUserRow['id'])) {
            $currentUserId = (int) $currentUserRow['id'];
        }
    }
}

[$phraseWeights, $tokenWeights] = collectUserInterestWeights($conn, $currentUserId);

$topPicksStmt = $conn->prepare(
    "SELECT l.*, u.businessname AS seller_businessname, u.profilepic AS seller_profilepic
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     WHERE COALESCE(l.out_of_stock, 0) = 0
       AND COALESCE(l.media, '') <> ''
       AND COALESCE(l.moderation_status, 'visible') <> 'hidden'
       AND l.admin_purged_at IS NULL
     ORDER BY l.created_at DESC, l.listing_id DESC
     LIMIT 320"
);

if ($topPicksStmt) {
    $topPicksStmt->execute();
    $topPicksRes = $topPicksStmt->get_result();
    while ($row = $topPicksRes->fetch_assoc()) {
        if ($currentUserId > 0 && (int) ($row['user_id'] ?? 0) === $currentUserId) {
            continue;
        }

        if (!listingMatchesUserInterest($row, $phraseWeights, $tokenWeights)) {
            continue;
        }

        $row['recommendation_score'] = scoreRecommendedListing($row, $phraseWeights, $tokenWeights, $currentUserId);
        $allRankedListings[] = $row;
    }
    $topPicksStmt->close();
}

usort($allRankedListings, static function (array $left, array $right): int {
    $scoreComparison = ((float) ($right['recommendation_score'] ?? 0.0)) <=> ((float) ($left['recommendation_score'] ?? 0.0));
    if ($scoreComparison !== 0) {
        return $scoreComparison;
    }

    $createdAtComparison = strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    if ($createdAtComparison !== 0) {
        return $createdAtComparison;
    }

    return ((int) ($right['listing_id'] ?? 0)) <=> ((int) ($left['listing_id'] ?? 0));
});

foreach ($allRankedListings as $listing) {
    $media = trim((string) ($listing['media'] ?? ''));
    if ($media === '') {
        continue;
    }

    if (getMediaType($media) === 'video') {
        $video_listings[] = $listing;
        continue;
    }

    $image_listings[] = $listing;
}

$desktop_video_listings = $video_listings;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JoMu | Top Picks</title>
    <link rel="stylesheet" href="assets/bootstrap.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        html,
        body {
            overflow-x: hidden;
        }

        html {
            background-color: #161515;
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        main.main-one {
            flex: 1 0 auto;
        }

        .footer-feedback {
            margin-top: auto;
            flex-shrink: 0;
        }

        .footer-links {
            gap: 4px 8px;
        }

        .footer-feedback,
        .footer-feedback .footer-links a,
        /* .footer-feedback a:visited, */
        /* .footer-feedback a:hover, */
        .footer-feedback a:focus,
        .footer-feedback small {
            color: #fff;
        }

        img.media-preview-item,
        img.media-preview-source {
            cursor: default;
        }

        video.media-preview-item,
        video.media-preview-source {
            cursor: pointer;
        }

        #mediaPreviewOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 18px;
        }

        #mediaPreviewOverlay.active {
            display: flex;
        }

        .media-preview-panel {
            max-width: 96vw;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            z-index: 1;
        }

        .media-preview-content {
            max-width: 96vw;
            max-height: 72vh;
            width: auto;
            height: auto;
            object-fit: contain;
            background: #000;
            z-index: 1;
        }

        .media-preview-details {
            width: min(96vw, 620px);
            background: rgba(9, 9, 9, 0.82);
            color: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: left;
            z-index: 3;
        }

        .media-preview-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.3;
            color: rgb(241, 90, 36);
        }

        .media-preview-price,
        .media-preview-description {
            margin: 4px 0 0;
            font-size: 0.9rem;
            line-height: 1.35;
        }

        .media-preview-close {
            position: absolute;
            top: 14px;
            right: 16px;
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 34px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 8px;
        }

        .media-preview-watermark {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 140px;
            max-width: 28vw;
            opacity: 0.28;
            pointer-events: none;
            user-select: none;
            display: none;
            z-index: 2;
        }

        .product-price-range {
            font-size: 1.14rem;
            font-weight: 800;
        }

        .listing-name-top {
            order: 1;
        }

        .listing-description {
            order: 2;
        }

        .listing-description-link {
            color: inherit;
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-clamp: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            line-height: 1.25;
            min-height: 2.5em;
            cursor: pointer;
        }

        .listing-description-link:hover {
            text-decoration: none;
            color: inherit;
        }

        .product-price-range {
            order: 3;
        }

        .listing-action-btn {
            order: 4;
        }

        .jomu-card-typography {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .jomu-card-typography .card-title {
            font-weight: 600;
            letter-spacing: 0;
        }

        .jomu-card-typography .card-text {
            font-weight: 500;
            line-height: 1.25;
        }

        .jomu-card-typography .product-price-range {
            font-weight: 800;
            line-height: 1.2;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }

        .jomu-card-typography .product-price-range .price-unit {
            font-weight: 400;
        }

        .jomu-card-typography .card-img-button {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-weight: 600;
        }

        .cards-container .card-newarrivals .card-img-top {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            background: #fff;
            padding: 0 !important;
        }

        .cards-container .card-newarrivals .card-body {
            padding: 0.35rem !important;
            display: flex;
            flex-direction: column;
            gap: 1px;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .cards-container .card-newarrivals .card-title {
            margin: 0;
            color: rgb(241, 90, 36);
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.15;
        }

        .cards-container .card-newarrivals .card-text {
            margin: 0;
            font-weight: 500;
            line-height: 1.25;
            font-size: 1rem;
        }

        .cards-container .card-newarrivals .product-price-range {
            font-weight: 800 !important;
            font-size: 0.95rem;
            letter-spacing: -0.01em;
        }

        .cards-container .card-newarrivals .card-img-button {
            margin-top: auto !important;
            margin-bottom: 0 !important;
            padding: 0.2rem 0.1rem !important;
            line-height: 1.1;
            font-weight: 600;
            font-size: 0.8rem;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .listing-load-more-wrap {
            display: flex;
            justify-content: center;
            padding-top: 0.9rem;
        }

        .listing-load-more-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 116px;
            padding: 0.35rem 0.9rem;
            background-color: rgb(241, 90, 36);
            border-color: rgb(241, 90, 36);
            color: #fff;
            text-align: center;
        }

        .listing-load-more-btn:hover,
        .listing-load-more-btn:focus,
        .listing-load-more-btn:active {
            background-color: rgb(241, 90, 36) !important;
            border-color: rgb(241, 90, 36) !important;
            color: #fff !important;
        }

        @media (min-width: 768px) {
            .videos-images-container-newarrivals {
                padding-right: 1rem !important;
            }

            .videos-images-container-newarrivals .images-container-newarrivals {
                padding-right: 0.75rem;
                box-sizing: border-box;
            }
        }

        .video-card-body {
            display: flex;
            flex-direction: column;
            gap: 0.08rem;
        }

        .video-seller-row {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .video-seller-link {
            color: inherit;
            text-decoration: none;
        }

        .video-seller-link:hover {
            color: inherit;
            text-decoration: none;
        }

        .video-seller-dp {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            background: #e9ecef;
        }

        .video-seller-dp-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #fff;
            background: #6c757d;
        }

        .video-seller-name {
            margin: 0;
            font-size: 0.82rem;
            line-height: 1.02;
            font-weight: 600;
            color: #343a40;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .video-stock-title {
            margin: 0;
            font-size: 0.92rem;
            line-height: 1.05;
            font-weight: 700;
            color: rgb(241, 90, 36);
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            line-clamp: 1;
            overflow: hidden;
        }

        .video-description-brief {
            margin: 0;
            font-size: 0.8rem;
            color: #5f676f;
            line-height: 1.08;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-clamp: 2;
            overflow: hidden;
        }

        .video-hashtags {
            margin-top: auto;
            font-size: 0.74rem;
            font-weight: 600;
            color: #0d6efd;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (max-width: 767.98px) {
            .footer-feedback br {
                display: none;
            }

            .footer-feedback small {
                display: block;
                margin-top: 0;
            }

            .cards-container .card-newarrivals.h-100 {
                height: auto !important;
            }

            .cards-container .card-newarrivals .card-img-top {
                aspect-ratio: auto;
                height: clamp(100px, 30vw, 140px);
                object-fit: cover;
            }

            .cards-container .card-newarrivals .card-title {
                font-size: 0.82rem;
                line-height: 1.15;
            }

            .cards-container .listing-description-link {
                -webkit-line-clamp: 1;
                line-clamp: 1;
                min-height: 1.2em;
                font-size: 0.76rem;
            }

            .cards-container .card-newarrivals .card-text {
                font-size: 0.72rem;
                line-height: 1.15;
            }

            .cards-container .product-price-range {
                line-height: 1.2;
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
                font-size: 0.7rem;
            }

            .cards-container .card-newarrivals .card-img-button {
                font-size: 0.72rem;
                line-height: 1.1;
            }
        }
            /* Sticky footer fix */
        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        body > footer {
            margin-top: auto;
            flex-shrink: 0;
        }
          .recent-mobile-auth-menu {
            position: fixed;
            left: auto !important;
            right: 36px !important;
            top: 58px;
            z-index: 1200;
        }

        .recent-mobile-auth-menu::before {
            content: "";
            position: absolute;
            top: -7px;
            right: 12px;
            width: 14px;
            height: 14px;
            background: #fff;
            transform: rotate(45deg);
            border-radius: 2px;
            box-shadow: -2px -2px 8px rgba(17, 24, 39, 0.04);
            left: auto;
        }
    </style>
</head>
<body style="background-color: #f0ecec;">
    <nav class="navbar navbar-expand-lg navbar-light sticky-top navbarone navbar-help bg-dark" id="navbarone"
        style="z-index: 100; margin-top: -6px;">
        <div class="container">
            <a class="navbar-brand brand-logos" href="index.php">
                <img src="assets/images/JoMu black and white.png" class="img-fluid logo">
                <img src="assets/images/JoMu logo redesigned.png" class="img-fluid logo logo-hover">
            </a>
        </div>

          <!-- Auth actions for small and medium screens -->
        <button id="newarrivals-mobile-toggler" class="navbar-toggler d-lg-none signin-icon-bg" type="button"
            aria-expanded="false" aria-label="Open sign in menu">
            <span>
                <img src="assets/images/icons/Signin.png" class="signin-icon" alt="Sign in">
            </span>
        </button>
        <div id="newarrivals-mobile-auth-menu" class="dropdown-menu mobile-auth-menu recent-mobile-auth-menu newarrivals-mobile-auth-menu"
            aria-labelledby="newarrivals-mobile-toggler">
            <a id="newarrivals-signin-mobile" class="dropdown-item mobile-auth-item" href="signin.html"
                data-mobile-auth-link="signin.html">Sign In</a>
            <button id="newarrivals-createaccount-mobile" class="dropdown-item mobile-auth-item mobile-auth-create"
                type="button" data-mobile-auth-link="createaccount.html">Create account</button>
        </div>
            <!-- Large screens -->
        <div class="collapse navbar-collapse d-none d-lg-flex me-4 links-container" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item active signin">
                    <a class="nav-link link-text" href="signin.html">Sign In</a>
                </li>
            </ul>
            <button class="button button-createaccount" onclick="location.href='createaccount.html'">Create
                account</button>
        </div>
    </nav>

    <main class="main-one">
        <div class="container-fluid">
            <p class="py-2 px-2 mb-0" style="color: rgb(34, 32, 32); font-weight: 600; font-size: small;">
                <a href="index.php" style="text-decoration: none; color: rgb(34, 32, 32);">Home Page</a>
                <span style="color: rgb(0,0,255);">&#187;</span>
                Suggested
                <span style="color: rgb(0,0,255);">&#187;</span>
                Top Picks
            </p>
        </div>

        <div class="mb-3 mt-1 newarrivals-container-one">
            <h4 style="font-weight: 700; color: rgb(241, 90, 36);">Top Picks</h4>
            <hr>
            <p>Discover <b>recommended listings</b> tailored to your activity on JoMu. These picks reflect the
                categories you post in most, the listings you view most, and the things you search for most often.</p>
        </div>

        <div class="d-none d-md-block d-lg-block">
            <div class="videos-images-container-newarrivals gap-2 px-1">
                <div id="videoListingsDesktopContainer" class="container videos-container-newarrivals col-2">
                    <h4 class="mt-0 mb-0"> Featured Videos</h4>
                    <p>Discover what businesses just shared.</p>
                    <?php if ($desktop_video_listings !== []) { ?>
                        <?php foreach ($desktop_video_listings as $listing) { ?>
                            <?php
                                $sellerName = trim((string) ($listing['seller_businessname'] ?? 'Business'));
                                if ($sellerName === '') {
                                    $sellerName = 'Business';
                                }
                                $sellerProfile = trim((string) ($listing['seller_profilepic'] ?? ''));
                                $sellerInitial = strtoupper(substr($sellerName, 0, 1));
                                if ($sellerInitial === '') {
                                    $sellerInitial = 'B';
                                }
                                $sellerProfileUrl = ((int) ($listing['user_id'] ?? 0) === $currentUserId && $currentUserId > 0)
                                    ? 'php/profile.php'
                                    : ('php/visitor_profile.php?user_id=' . urlencode((string) ($listing['user_id'] ?? '')));
                                $savedHashtags = trim((string) ($listing['hashtags'] ?? ''));
                                if ($savedHashtags !== '') {
                                    $savedHashtags = preg_replace('/\s+/', ' ', $savedHashtags);
                                }
                                $tagSources = [(string) ($listing['listing_type'] ?? ''), (string) ($listing['category'] ?? '')];
                                $hashtags = [];
                                foreach ($tagSources as $sourceTag) {
                                    $cleanedTag = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $sourceTag);
                                    $cleanedTag = preg_replace('/\s+/', ' ', trim((string) $cleanedTag));
                                    if ($cleanedTag === '') {
                                        continue;
                                    }
                                    $words = preg_split('/\s+/', strtolower($cleanedTag)) ?: [];
                                    $compactTag = '';
                                    foreach ($words as $word) {
                                        if ($word === '') {
                                            continue;
                                        }
                                        $compactTag .= ucfirst($word);
                                    }
                                    if ($compactTag !== '') {
                                        $hashtags[] = '#' . $compactTag;
                                    }
                                }
                                if ($hashtags === []) {
                                    $hashtags[] = '#JoMu';
                                }
                                $hashtagDisplay = $savedHashtags !== '' ? $savedHashtags : implode(' ', $hashtags);
                                $videoListingType = strtolower((string) ($listing['listing_type'] ?? ''));
                                if ($videoListingType !== 'product' && $videoListingType !== 'service') {
                                    $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                    $videoListingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                }
                                $videoPriceFrom = trim((string) ($listing['price_from'] ?? ''));
                                $videoPriceTo = trim((string) ($listing['price_to'] ?? ''));
                                $videoPreviewPrice = '';
                                if ($videoListingType === 'product' && $videoPriceFrom !== '' && $videoPriceTo !== '') {
                                    $videoPreviewPrice = formatProductPriceRange($videoPriceFrom, $videoPriceTo);
                                } else {
                                    $videoPreviewPrice = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                }
                            ?>
                            <div class="mb-2">
                                <div class="card h-100">
                                    <div class="video-wrapper">
                                        <video class="video-content media-preview-item media-preview-source" controls muted
                                            data-preview-type="video"
                                            data-preview-src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>"
                                            data-preview-title="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?>"
                                            data-preview-description="<?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?>"
                                            data-preview-price="<?php echo htmlspecialchars($videoPreviewPrice); ?>"
                                            data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                            <source src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>" type="video/mp4">
                                        </video>
                                    </div>
                                    <div class="card-body p-2 video-card-body">
                                        <a href="<?php echo htmlspecialchars($sellerProfileUrl); ?>" class="video-seller-row video-seller-link">
                                            <?php if ($sellerProfile !== '') { ?>
                                                <img src="<?php echo htmlspecialchars(getMediaPath($sellerProfile, 'php/')); ?>" alt="<?php echo htmlspecialchars($sellerName); ?>" class="video-seller-dp">
                                            <?php } else { ?>
                                                <span class="video-seller-dp video-seller-dp-fallback"><?php echo htmlspecialchars($sellerInitial); ?></span>
                                            <?php } ?>
                                            <p class="video-seller-name"><?php echo htmlspecialchars($sellerName); ?></p>
                                        </a>
                                        <h6 class="video-stock-title"><?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?></h6>
                                        <p class="video-description-brief"><?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?></p>
                                        <p class="video-hashtags mb-0"><?php echo htmlspecialchars($hashtagDisplay); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="card">
                            <div class="card-body p-2">
                                <p class="mb-0">No recommended videos are available right now.</p>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="listing-load-more-wrap">
                        <button type="button" id="videoSeeMoreButtonDesktop" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                    </div>
                </div>

                <div class="my-2 cards-container images-container-newarrivals col-10">
                    <div class="row g-1" id="imageListingsRowDesktop">
                        <?php if ($image_listings !== []) { ?>
                            <?php foreach ($image_listings as $listing) { ?>
                                <?php
                                    $listingType = strtolower(trim((string) ($listing['listing_type'] ?? '')));
                                    if ($listingType !== 'product' && $listingType !== 'service') {
                                        $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                        $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                    }
                                    $isOwnListing = $currentUserId > 0 && (int) ($listing['user_id'] ?? 0) === $currentUserId;
                                    $actionButtonLabel = $isOwnListing
                                        ? 'See Listing'
                                        : ($listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale');
                                    $priceLabel = buildPriceLabel($listing);
                                    $purchaseUrl = buildPurchaseUrl($listing, $listingType, $isOwnListing);
                                    $actionUrl = (!$isOwnListing && $currentUserId <= 0)
                                        ? '/?error=Not+Signed+In!'
                                        : $purchaseUrl;
                                ?>
                                <div class="col-6 col-md-3 custom-lg-newarrivals">
                                    <div class="card h-100 card-newarrivals">
                                        <img src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>"
                                            class="card-img-top img-fluid media-preview-item media-preview-source"
                                            alt="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? 'Listing image')); ?>"
                                            data-preview-type="image"
                                            data-preview-src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>"
                                            data-preview-title="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?>"
                                            data-preview-description="<?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?>"
                                            data-preview-price="<?php echo htmlspecialchars($priceLabel !== '' ? $priceLabel : ((string) ($listing['price'] ?? ''))); ?>"
                                            data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                        <div class="card-body p-2 d-flex flex-column jomu-card-typography" style="gap: 1px;">
                                            <h5 class="card-title mb-0 listing-name-top"><?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?></h5>
                                            <p class="card-text mb-0 listing-description"><a href="<?php echo htmlspecialchars($purchaseUrl); ?>" class="listing-description-link"><?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?></a></p>
                                            <?php if ($priceLabel !== '') { ?>
                                                <?php
                                                    $priceText = $priceLabel;
                                                    $unitText = '';
                                                    if ($listingType === 'product' && substr($priceLabel, -7) === ' / unit') {
                                                        $priceText = substr($priceLabel, 0, -7);
                                                        $unitText = ' / unit';
                                                    }
                                                ?>
                                                <p class="card-text mb-0 product-price-range"><?php echo htmlspecialchars($priceText); ?><?php if ($unitText !== '') { ?><span class="price-unit"><?php echo htmlspecialchars($unitText); ?></span><?php } ?></p>
                                            <?php } ?>
                                            <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn card-img-button py-1 mt-0 listing-action-btn"><?php echo htmlspecialchars($actionButtonLabel); ?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <p class="mb-0">No recommended listings are available right now.</p>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="listing-load-more-wrap">
                        <button type="button" id="imageSeeMoreButtonDesktop" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                    </div>
                    <div class="container text-center mt-5 px-5 py-4 newarrivals-feedback">
                        <h6>We'd love to hear from you! Share your thoughts or suggestions.</h6>
                        <a href="feedback.html"><button class="btn btn-newarrivals">Share Feedback</button></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-block d-md-none d-lg-none">
            <div class="videos-images-container-newarrivals-small gap-2 px-1">
                <div class="my-2 cards-container images-container-newarrivals col-10 w-100">
                    <div class="row g-1" id="imageListingsRowMobile">
                        <?php if ($image_listings !== []) { ?>
                            <?php foreach ($image_listings as $listing) { ?>
                                <?php
                                    $listingType = strtolower(trim((string) ($listing['listing_type'] ?? '')));
                                    if ($listingType !== 'product' && $listingType !== 'service') {
                                        $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                        $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                    }
                                    $isOwnListing = $currentUserId > 0 && (int) ($listing['user_id'] ?? 0) === $currentUserId;
                                    $actionButtonLabel = $isOwnListing
                                        ? 'See Listing'
                                        : ($listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale');
                                    $priceLabel = buildPriceLabel($listing);
                                    $purchaseUrl = buildPurchaseUrl($listing, $listingType, $isOwnListing);
                                    $actionUrl = (!$isOwnListing && $currentUserId <= 0)
                                        ? '/?error=Not+Signed+In!'
                                        : $purchaseUrl;
                                ?>
                                <div class="col-6 col-md-3 custom-lg-newarrivals">
                                    <div class="card h-100 card-newarrivals">
                                        <img src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>"
                                            class="card-img-top img-fluid media-preview-item media-preview-source"
                                            alt="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? 'Listing image')); ?>"
                                            data-preview-type="image"
                                            data-preview-src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>"
                                            data-preview-title="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?>"
                                            data-preview-description="<?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?>"
                                            data-preview-price="<?php echo htmlspecialchars($priceLabel !== '' ? $priceLabel : ((string) ($listing['price'] ?? ''))); ?>"
                                            data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                        <div class="card-body p-2 d-flex flex-column jomu-card-typography" style="gap: 1px;">
                                            <h5 class="card-title mb-0 listing-name-top"><?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?></h5>
                                            <p class="card-text mb-0 listing-description"><a href="<?php echo htmlspecialchars($purchaseUrl); ?>" class="listing-description-link"><?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?></a></p>
                                            <?php if ($priceLabel !== '') { ?>
                                                <?php
                                                    $priceText = $priceLabel;
                                                    $unitText = '';
                                                    if ($listingType === 'product' && substr($priceLabel, -7) === ' / unit') {
                                                        $priceText = substr($priceLabel, 0, -7);
                                                        $unitText = ' / unit';
                                                    }
                                                ?>
                                                <p class="card-text mb-0 product-price-range"><?php echo htmlspecialchars($priceText); ?><?php if ($unitText !== '') { ?><span class="price-unit"><?php echo htmlspecialchars($unitText); ?></span><?php } ?></p>
                                            <?php } ?>
                                            <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn card-img-button py-1 mt-0 listing-action-btn"><?php echo htmlspecialchars($actionButtonLabel); ?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <p class="mb-0">No recommended listings are available right now.</p>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="listing-load-more-wrap">
                        <button type="button" id="imageSeeMoreButtonMobile" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                    </div>
                </div>

                <div class="container videos-container-newarrivals">
                    <h4 class="mt-0 mb-0"> Featured Videos</h4>
                    <p>Discover what businesses just shared.</p>
                    <?php if ($video_listings !== []) { ?>
                        <div class="row g-2" id="videoListingsRowMobile">
                            <?php foreach ($video_listings as $listing) { ?>
                                <?php
                                    $sellerName = trim((string) ($listing['seller_businessname'] ?? 'Business'));
                                    if ($sellerName === '') {
                                        $sellerName = 'Business';
                                    }
                                    $sellerProfile = trim((string) ($listing['seller_profilepic'] ?? ''));
                                    $sellerInitial = strtoupper(substr($sellerName, 0, 1));
                                    if ($sellerInitial === '') {
                                        $sellerInitial = 'B';
                                    }
                                    $sellerProfileUrl = ((int) ($listing['user_id'] ?? 0) === $currentUserId && $currentUserId > 0)
                                        ? 'php/profile.php'
                                        : ('php/visitor_profile.php?user_id=' . urlencode((string) ($listing['user_id'] ?? '')));
                                    $savedHashtags = trim((string) ($listing['hashtags'] ?? ''));
                                    if ($savedHashtags !== '') {
                                        $savedHashtags = preg_replace('/\s+/', ' ', $savedHashtags);
                                    }
                                    $tagSources = [(string) ($listing['listing_type'] ?? ''), (string) ($listing['category'] ?? '')];
                                    $hashtags = [];
                                    foreach ($tagSources as $sourceTag) {
                                        $cleanedTag = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $sourceTag);
                                        $cleanedTag = preg_replace('/\s+/', ' ', trim((string) $cleanedTag));
                                        if ($cleanedTag === '') {
                                            continue;
                                        }
                                        $words = preg_split('/\s+/', strtolower($cleanedTag)) ?: [];
                                        $compactTag = '';
                                        foreach ($words as $word) {
                                            if ($word === '') {
                                                continue;
                                            }
                                            $compactTag .= ucfirst($word);
                                        }
                                        if ($compactTag !== '') {
                                            $hashtags[] = '#' . $compactTag;
                                        }
                                    }
                                    if ($hashtags === []) {
                                        $hashtags[] = '#JoMu';
                                    }
                                    $hashtagDisplay = $savedHashtags !== '' ? $savedHashtags : implode(' ', $hashtags);
                                    $videoListingType = strtolower((string) ($listing['listing_type'] ?? ''));
                                    if ($videoListingType !== 'product' && $videoListingType !== 'service') {
                                        $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                        $videoListingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                    }
                                    $videoPriceFrom = trim((string) ($listing['price_from'] ?? ''));
                                    $videoPriceTo = trim((string) ($listing['price_to'] ?? ''));
                                    $videoPreviewPrice = '';
                                    if ($videoListingType === 'product' && $videoPriceFrom !== '' && $videoPriceTo !== '') {
                                        $videoPreviewPrice = formatProductPriceRange($videoPriceFrom, $videoPriceTo);
                                    } else {
                                        $videoPreviewPrice = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                    }
                                ?>
                                <div class="col-6">
                                    <div class="card h-100">
                                        <div class="video-wrapper">
                                            <video class="video-content media-preview-item media-preview-source" controls muted
                                                data-preview-type="video"
                                                data-preview-src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>"
                                                data-preview-title="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?>"
                                                data-preview-description="<?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?>"
                                                data-preview-price="<?php echo htmlspecialchars($videoPreviewPrice); ?>"
                                                data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                                <source src="<?php echo htmlspecialchars(getMediaPath((string) ($listing['media'] ?? ''), 'php/')); ?>" type="video/mp4">
                                            </video>
                                        </div>
                                        <div class="card-body p-2 video-card-body">
                                            <a href="<?php echo htmlspecialchars($sellerProfileUrl); ?>" class="video-seller-row video-seller-link">
                                                <?php if ($sellerProfile !== '') { ?>
                                                    <img src="<?php echo htmlspecialchars(getMediaPath($sellerProfile, 'php/')); ?>" alt="<?php echo htmlspecialchars($sellerName); ?>" class="video-seller-dp">
                                                <?php } else { ?>
                                                    <span class="video-seller-dp video-seller-dp-fallback"><?php echo htmlspecialchars($sellerInitial); ?></span>
                                                <?php } ?>
                                                <p class="video-seller-name"><?php echo htmlspecialchars($sellerName); ?></p>
                                            </a>
                                            <h6 class="video-stock-title"><?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?></h6>
                                            <p class="video-description-brief"><?php echo htmlspecialchars((string) ($listing['description'] ?? '')); ?></p>
                                            <p class="video-hashtags mb-0"><?php echo htmlspecialchars($hashtagDisplay); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="card">
                            <div class="card-body p-2">
                                <p class="mb-0">No recommended videos are available right now.</p>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="listing-load-more-wrap">
                        <button type="button" id="videoSeeMoreButtonMobile" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                    </div>
                </div>

                <div class="container text-center mb-4 mt-5 px-3.5 py-4 newarrivals-feedback w-100">
                    <h6>We'd love to hear from you! Share your thoughts or suggestions.</h6>
                    <a href="feedback.html"><button class="btn btn-newarrivals">Share Feedback</button></a>
                </div>
            </div>
        </div>
    </main>
    <div id="mediaPreviewOverlay" aria-hidden="true">
        <button type="button" class="media-preview-close" id="mediaPreviewClose" aria-label="Close preview">&times;</button>
        <div class="media-preview-panel">
            <img id="mediaPreviewImage" class="media-preview-content" alt="Listing preview" style="display:none;">
            <video id="mediaPreviewVideo" class="media-preview-content" controls style="display:none;"></video>
            <div id="mediaPreviewDetails" class="media-preview-details" style="display:none;">
                <p id="mediaPreviewTitle" class="media-preview-title"></p>
                <p id="mediaPreviewPrice" class="media-preview-price"></p>
                <p id="mediaPreviewDescription" class="media-preview-description"></p>
            </div>
        </div>
        <img id="mediaPreviewWatermark" class="media-preview-watermark" src="assets/images/JoMu logo redesigned.png" alt="JoMu watermark">
    </div>

    <footer class="footer-feedback py-2 text-center">
        <div class="footer-links">
            <a href="termsandconditions.html">Terms of Use</a>
            <a href="privacypolicy.html">Privacy Policy</a>
            <a href="help.html">Help</a>
            <a href="support.html">Support</a>
            <a href="feedback.html">Give Feedback</a>
            <a href="about.html">About JoMu</a>
        </div>
        <br>
        <small>&copy; 2026 JoMu. All rights reserved.</small>
    </footer>

    <script src="assets/listing-preview-modal.js"></script>
    <script src="assets/listing-preview-gallery.js"></script>
    <script src="assets/bootstrap.bundle.min.js"></script>
        <script>
        async function syncAuthCtasForSignedInUser() {
            try {
                const response = await fetch('/php/auth_status.php', { credentials: 'same-origin' });
                if (!response.ok) return;

                const data = await response.json();
                if (!data || data.signed_in !== true) return;

                const authSelectors = [
                    "a[href='signin.html']",
                    "a[href='./signin.html']",
                    "a[href='/signin.html']",
                    "a[href='createaccount.html']",
                    "a[href='./createaccount.html']",
                    "a[href='/createaccount.html']",
                    "button[onclick*='createaccount.html']"
                ];

                document.querySelectorAll(authSelectors.join(',')).forEach((node) => {
                    const li = node.closest('li');
                    if (li) {
                        li.remove();
                    } else {
                        node.remove();
                    }
                });

                document.querySelectorAll('.offcanvas').forEach((panel) => {
                    const hasActions = panel.querySelector("a[href], button:not(.btn-close)");
                    if (hasActions) return;

                    const panelId = panel.getAttribute('id');
                    if (panelId) {
                        document.querySelectorAll(`[data-bs-target='#${panelId}']`).forEach((btn) => btn.remove());
                    }
                    panel.remove();
                });
            } catch (error) {
                // Keep default auth links when auth check is unavailable.
            }
        }

        syncAuthCtasForSignedInUser();
    </script>
    <script>
        (function () {
            const mediaOverlay = document.getElementById('mediaPreviewOverlay');
            const mediaClose = document.getElementById('mediaPreviewClose');
            const mediaPreviewImage = document.getElementById('mediaPreviewImage');
            const mediaPreviewVideo = document.getElementById('mediaPreviewVideo');
            const mediaPreviewDetails = document.getElementById('mediaPreviewDetails');
            const mediaPreviewTitle = document.getElementById('mediaPreviewTitle');
            const mediaPreviewPrice = document.getElementById('mediaPreviewPrice');
            const mediaPreviewDescription = document.getElementById('mediaPreviewDescription');
            const mediaWatermark = document.getElementById('mediaPreviewWatermark');
            const countedPreviewViews = new Set();
            const countedVideoViews = new Set();
            const pendingVideoViewTimers = new Map();

            function updateMediaPreviewDetails(sourceEl) {
                if (!mediaPreviewDetails) {
                    return;
                }

                const title = String(sourceEl?.dataset.previewTitle || '').trim();
                const price = String(sourceEl?.dataset.previewPrice || '').trim();
                const description = String(sourceEl?.dataset.previewDescription || '');
                const hasDetails = title !== '' || price !== '' || description !== '';

                mediaPreviewTitle.textContent = title;
                mediaPreviewTitle.style.display = title ? 'block' : 'none';
                mediaPreviewPrice.textContent = price ? `Price: ${price}` : '';
                mediaPreviewPrice.style.display = price ? 'block' : 'none';
                mediaPreviewDescription.textContent = description;
                mediaPreviewDescription.style.whiteSpace = 'pre-wrap';
                mediaPreviewDescription.style.wordBreak = 'break-word';
                mediaPreviewDescription.style.display = description ? 'block' : 'none';
                mediaPreviewDetails.style.display = hasDetails ? 'block' : 'none';
            }

            async function incrementPreviewImageView(sourceEl) {
                const type = String(sourceEl?.dataset.previewType || '').trim();
                const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || '', 10);
                if (type !== 'image' || !Number.isInteger(listingId) || listingId <= 0 || countedPreviewViews.has(listingId)) {
                    return;
                }

                countedPreviewViews.add(listingId);

                try {
                    await fetch(`php/increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
                } catch (error) {
                }
            }

            async function incrementVideoPlaybackView(listingId) {
                if (!Number.isInteger(listingId) || listingId <= 0 || countedVideoViews.has(listingId)) {
                    return;
                }

                countedVideoViews.add(listingId);

                try {
                    await fetch(`php/increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
                } catch (error) {
                }
            }

            function clearPendingVideoView(videoEl) {
                const timerId = pendingVideoViewTimers.get(videoEl);
                if (timerId) {
                    clearTimeout(timerId);
                    pendingVideoViewTimers.delete(videoEl);
                }
            }

            function scheduleVideoViewIncrement(videoEl) {
                const listingId = Number.parseInt(videoEl?.dataset.previewListingId || '', 10);
                if (!Number.isInteger(listingId) || listingId <= 0 || countedVideoViews.has(listingId) || pendingVideoViewTimers.has(videoEl)) {
                    return;
                }

                const timerId = setTimeout(() => {
                    pendingVideoViewTimers.delete(videoEl);
                    if (countedVideoViews.has(listingId) || videoEl.paused || videoEl.ended) {
                        return;
                    }
                    incrementVideoPlaybackView(listingId);
                }, 2000);

                pendingVideoViewTimers.set(videoEl, timerId);
            }

            function registerVideoViewTracking(videoEl) {
                if (!(videoEl instanceof HTMLVideoElement) || videoEl.dataset.viewTrackingBound === '1') {
                    return;
                }

                videoEl.dataset.viewTrackingBound = '1';
                videoEl.addEventListener('play', () => scheduleVideoViewIncrement(videoEl));
                videoEl.addEventListener('pause', () => clearPendingVideoView(videoEl));
                videoEl.addEventListener('ended', () => clearPendingVideoView(videoEl));
                videoEl.addEventListener('emptied', () => clearPendingVideoView(videoEl));
            }

            function closeMediaPreview() {
                if (!mediaOverlay) {
                    return;
                }

                mediaPreviewVideo.pause();
                mediaPreviewVideo.removeAttribute('src');
                delete mediaPreviewVideo.dataset.previewListingId;
                mediaPreviewImage.removeAttribute('src');
                mediaPreviewImage.style.display = 'none';
                mediaPreviewVideo.style.display = 'none';
                if (mediaPreviewDetails) {
                    mediaPreviewDetails.style.display = 'none';
                }
                mediaOverlay.classList.remove('active');
                mediaOverlay.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                if (mediaWatermark) {
                    mediaWatermark.style.display = 'none';
                }
            }

            function openMediaPreview(type, src, sourceEl) {
                if (!mediaOverlay || !src) {
                    return;
                }

                mediaPreviewImage.style.display = 'none';
                mediaPreviewVideo.style.display = 'none';
                updateMediaPreviewDetails(sourceEl);
                incrementPreviewImageView(sourceEl);

                if (type === 'video') {
                    mediaPreviewVideo.src = src;
                    mediaPreviewVideo.dataset.previewListingId = sourceEl?.dataset.previewListingId || '';
                    mediaPreviewVideo.style.display = 'block';
                    mediaPreviewVideo.play().catch(() => {});
                    if (mediaWatermark) {
                        mediaWatermark.style.display = 'none';
                    }
                } else {
                    mediaPreviewImage.src = src;
                    mediaPreviewImage.style.display = 'block';
                    if (mediaWatermark) {
                        mediaWatermark.style.display = 'block';
                    }
                }

                mediaOverlay.classList.add('active');
                mediaOverlay.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function openPreviewFromSource(sourceEl) {
                if (!sourceEl) {
                    return;
                }

                const type = sourceEl.dataset.previewType || (sourceEl.tagName.toLowerCase() === 'video' ? 'video' : 'image');
                const src = sourceEl.dataset.previewSrc || sourceEl.getAttribute('src') || '';
                openMediaPreview(type, src, sourceEl);
            }

            let lastTapTime = 0;
            let lastTapSrc = '';

            document.querySelector('main')?.addEventListener('click', (event) => {
                const sourceEl = event.target.closest('.media-preview-source') || event.target.closest('.video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link')?.closest('.card')?.querySelector('.media-preview-source');
                openPreviewFromSource(sourceEl);
            });

            document.querySelector('main')?.addEventListener('touchend', (event) => {
                const sourceEl = event.target.closest('.media-preview-source') || event.target.closest('.video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link')?.closest('.card')?.querySelector('.media-preview-source');
                if (!sourceEl) {
                    return;
                }

                const sourceKey = sourceEl.dataset.previewSrc || sourceEl.getAttribute('src') || '';
                const now = Date.now();
                const isDoubleTap = now - lastTapTime < 350 && sourceKey !== '' && sourceKey === lastTapSrc;

                lastTapTime = now;
                lastTapSrc = sourceKey;

                if (!isDoubleTap) {
                    return;
                }

                event.preventDefault();
                openPreviewFromSource(sourceEl);
                lastTapTime = 0;
                lastTapSrc = '';
            }, { passive: false });

            document.addEventListener('touchstart', () => {
                if (Date.now() - lastTapTime > 600) {
                    lastTapTime = 0;
                    lastTapSrc = '';
                }
            });

            document.querySelectorAll('video[data-preview-listing-id]').forEach((videoEl) => {
                registerVideoViewTracking(videoEl);
            });
            registerVideoViewTracking(mediaPreviewVideo);

            if (mediaClose) {
                mediaClose.addEventListener('click', closeMediaPreview);
            }

            if (mediaOverlay) {
                mediaOverlay.addEventListener('click', (event) => {
                    if (event.target === mediaOverlay) {
                        closeMediaPreview();
                    }
                });
            }

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeMediaPreview();
                }
            });

            document.addEventListener('play', (event) => {
                const currentVideo = event.target;
                if (!(currentVideo instanceof HTMLVideoElement) || !currentVideo.hasAttribute('controls')) {
                    return;
                }

                document.querySelectorAll('video[controls]').forEach((video) => {
                    if (video !== currentVideo && !video.paused) {
                        video.pause();
                    }
                });
            }, true);
        })();
    </script>
    <script>
        function setupSeeMoreListings(options) {
            const root = document.getElementById(options.rootId);
            const button = document.getElementById(options.buttonId);
            if (!root || !button) return;

            const itemSelector = options.itemSelector || ':scope > *';
            const items = Array.from(root.querySelectorAll(itemSelector));
            if (items.length === 0) {
                button.style.display = 'none';
                return;
            }

            const mobileQuery = window.matchMedia('(max-width: 767.98px)');
            let visibleCount = 0;
            let lastBatchSize = 0;

            function getBatchSize() {
                return mobileQuery.matches ? options.mobileBatch : options.desktopBatch;
            }

            function render(resetCount = false) {
                const batchSize = getBatchSize();
                if (resetCount || visibleCount === 0) {
                    visibleCount = batchSize;
                } else if (batchSize !== lastBatchSize) {
                    const previouslyShownBatches = Math.max(1, Math.ceil(visibleCount / Math.max(lastBatchSize, 1)));
                    visibleCount = previouslyShownBatches * batchSize;
                }

                lastBatchSize = batchSize;
                const limitedVisibleCount = Math.min(visibleCount, items.length);

                items.forEach((item, index) => {
                    item.style.display = index < limitedVisibleCount ? '' : 'none';
                });

                button.style.display = limitedVisibleCount < items.length ? 'inline-flex' : 'none';
            }

            button.addEventListener('click', () => {
                visibleCount += getBatchSize();
                render();
            });

            const handleViewportChange = () => render(visibleCount === 0);
            if (typeof mobileQuery.addEventListener === 'function') {
                mobileQuery.addEventListener('change', handleViewportChange);
            } else if (typeof mobileQuery.addListener === 'function') {
                mobileQuery.addListener(handleViewportChange);
            }

            render(true);
        }

        setupSeeMoreListings({
            rootId: 'imageListingsRowDesktop',
            buttonId: 'imageSeeMoreButtonDesktop',
            desktopBatch: 24,
            mobileBatch: 24
        });

        setupSeeMoreListings({
            rootId: 'imageListingsRowMobile',
            buttonId: 'imageSeeMoreButtonMobile',
            desktopBatch: 20,
            mobileBatch: 20
        });

        setupSeeMoreListings({
            rootId: 'videoListingsDesktopContainer',
            buttonId: 'videoSeeMoreButtonDesktop',
            desktopBatch: 8,
            mobileBatch: 8,
            itemSelector: ':scope > .mb-2'
        });

        setupSeeMoreListings({
            rootId: 'videoListingsRowMobile',
            buttonId: 'videoSeeMoreButtonMobile',
            desktopBatch: 4,
            mobileBatch: 4,
            itemSelector: ':scope > .col-6'
        });
    </script>
    <script>
        (function () {
            const mobileAuthButton = document.getElementById('newarrivals-mobile-toggler');
            const mobileAuthMenu = document.getElementById('newarrivals-mobile-auth-menu');
            if (!mobileAuthButton || !mobileAuthMenu) {
                return;
            }

            function positionMobileAuthMenu() {
                const buttonRect = mobileAuthButton.getBoundingClientRect();
                const rightOffset = Math.max(8, window.innerWidth - buttonRect.right);
                mobileAuthMenu.style.right = `${rightOffset}px`;
                mobileAuthMenu.style.top = `${buttonRect.bottom + 8}px`;
            }

            function setMobileAuthOpen(isOpen) {
                if (isOpen) {
                    positionMobileAuthMenu();
                }
                mobileAuthMenu.classList.toggle('show', isOpen);
                mobileAuthButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            mobileAuthButton.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                setMobileAuthOpen(!mobileAuthMenu.classList.contains('show'));
            });

            document.addEventListener('click', (event) => {
                if (mobileAuthButton.contains(event.target) || mobileAuthMenu.contains(event.target)) {
                    return;
                }

                setMobileAuthOpen(false);
            });

            window.addEventListener('resize', () => {
                if (mobileAuthMenu.classList.contains('show')) {
                    positionMobileAuthMenu();
                }
            });
        })();

        (function () {
            const mobileAuthItems = Array.from(document.querySelectorAll('[data-mobile-auth-link]'));
            if (mobileAuthItems.length === 0) {
                return;
            }

            mobileAuthItems.forEach((item) => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const nextUrl = item.getAttribute('data-mobile-auth-link');
                    if (!nextUrl) {
                        return;
                    }

                    window.location.assign(nextUrl);
                });
            });
        })();
    </script>

    <script>
        async function syncNewArrivalsAuthButtons() {
            try {
                const response = await fetch('/php/auth_status.php', { credentials: 'same-origin' });
                if (!response.ok) return;
                const data = await response.json();
                if (!data || data.signed_in !== true) return;

                const signInDesktop = document.getElementById('newarrivals-signin-desktop');
                const createDesktop = document.getElementById('newarrivals-createaccount-desktop');
                const signInMobile = document.getElementById('newarrivals-signin-mobile');
                const createMobile = document.getElementById('newarrivals-createaccount-mobile');
                const mobileToggler = document.getElementById('newarrivals-mobile-toggler');
                const mobileOffcanvas = document.getElementById('offcanvasNav');

                signInDesktop?.closest('li')?.remove();
                createDesktop?.remove();
                signInMobile?.closest('li')?.remove();
                createMobile?.remove();
                mobileToggler?.remove();
                mobileOffcanvas?.remove();
            } catch (error) {
                // Keep default navbar links when auth check is unavailable.
            }
        }

        syncNewArrivalsAuthButtons();
    </script>
    <script src="assets/cookie-consent.js"></script>
</body>
</html>

