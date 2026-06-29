<?php

$jomuPrettyRoutes = [
    '/about' => 'about.html',
    '/bulk-orders' => 'businessbulkorders.html',
    '/business-vendor-dashboard' => 'php/businessvendordashboard.php',
    '/profile' => 'php/profile.php',
    '/visitor-profile' => 'php/visitor_profile.php',
    '/add-new-listing' => 'php/addnewlisting.php',
    '/categories/agriculture-produce' => 'categoriesagriculture&produce.html',
    '/categories/apparel' => 'categoriesapparel.html',
    '/categories/automotive-transport' => 'categoriesautomotive&transport.html',
    '/categories/construction-building' => 'categoriesconstruction&building.html',
    '/categories/electronics-gadgets' => 'categorieselectronics&gagdets.html',
    '/categories/food-beverages' => 'categoriesfood&beverages.html',
    '/categories/furniture-home' => 'categoriesfurniture&home.html',
    '/categories/general-services' => 'categoriesgeneralservices.html',
    '/categories/health-beauty' => 'categorieshealth&beauty.html',
    '/categories/it-software' => 'categoriesit&software.html',
    '/categories/livestock-animals' => 'categorieslivestock&animals.html',
    '/categories/office-supply-stationery' => 'categoriesofficesupply&stationery.html',
    '/categories/printing-branding' => 'categoriesprinting&branding.html',
    '/categories/wholesale-retail' => 'categorieswholesale&retail.html',
    '/create-account' => 'createaccount.html',
    '/feedback' => 'feedback.html',
    '/help' => 'help.html',
    '/new-arrivals/central' => 'newarrivalscentral.php',
    '/new-arrivals/eastern' => 'newarrivalseastern.php',
    '/new-arrivals/northern' => 'newarrivalsnorthern.php',
    '/new-arrivals/western' => 'newarrivalswestern.php',
    '/offers-discounts' => 'offers&discounts.html',
    '/privacy-policy' => 'privacypolicy.html',
    '/purchase-wholesale' => 'purchasewholesale.html',
    '/recently-viewed' => 'recentlyviewed.html',
    '/sign-in' => 'signin.html',
    '/suggested/same-category' => 'suggestedsamecategory.html',
    '/suggested/top-picks' => 'suggestedtoppicks.php',
    '/support' => 'support.html',
    '/terms-and-conditions' => 'termsandconditions.html',
    '/top-rated-sellers/central' => 'topratedsellerscentral.html',
    '/top-rated-sellers/eastern' => 'topratedsellerseastern.html',
    '/top-rated-sellers/northern' => 'topratedsellersnorthern.html',
    '/top-rated-sellers/western' => 'topratedsellerswestern.html',
    '/trending/hot-deals' => 'trendinghotdeals.html',
    '/trending/seasonal-trends' => 'trendingseasonaltrends.html',
    '/vendor-shops/apparel' => 'vendorshops-apparel.html',
    '/vendor-shops/shoes' => 'vendorshops-shoes.html',
];

$jomuRequestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$jomuRequestPath = '/' . ltrim(str_replace('\\', '/', (string) $jomuRequestPath), '/');
$jomuNormalizedPath = rtrim($jomuRequestPath, '/') ?: '/';

if (preg_match('#/index\.php$#i', $jomuNormalizedPath)) {
    $jomuQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: /' . ($jomuQuery !== '' ? '?' . $jomuQuery : ''), true, 301);
    exit;
}

if ($jomuNormalizedPath !== '/' && isset($jomuPrettyRoutes[$jomuNormalizedPath])) {
    $jomuRouteTarget = __DIR__ . DIRECTORY_SEPARATOR . $jomuPrettyRoutes[$jomuNormalizedPath];
    if (is_file($jomuRouteTarget)) {
        if (str_ends_with($jomuRouteTarget, '.php')) {
            require $jomuRouteTarget;
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            readfile($jomuRouteTarget);
        }
        exit;
    }
}

if (preg_match('#^/(?:categories|new-arrivals|suggested|top-rated-sellers|trending|vendor-shops)/(assets|php)/(.+)$#', $jomuNormalizedPath, $jomuNestedMatch)) {
    $jomuNestedRoot = $jomuNestedMatch[1] === 'assets' ? 'assets' : 'php';
    $jomuNestedTarget = realpath(__DIR__ . DIRECTORY_SEPARATOR . $jomuNestedRoot . DIRECTORY_SEPARATOR . $jomuNestedMatch[2]);
    $jomuAllowedRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . $jomuNestedRoot);
    if ($jomuNestedTarget && $jomuAllowedRoot && str_starts_with($jomuNestedTarget, $jomuAllowedRoot) && is_file($jomuNestedTarget)) {
        if ($jomuNestedRoot === 'php' && str_ends_with($jomuNestedTarget, '.php')) {
            require $jomuNestedTarget;
        } else {
            $jomuMimeType = function_exists('mime_content_type') ? mime_content_type($jomuNestedTarget) : '';
            if (is_string($jomuMimeType) && $jomuMimeType !== '') {
                header('Content-Type: ' . $jomuMimeType);
            }
            readfile($jomuNestedTarget);
        }
        exit;
    }
}

session_start();
require 'php/connection/dbconn.php';
require 'php/partials/helpers.php';
require 'php/partials/admin_helpers.php';
jomu_ensure_admin_schema($conn);

if (isset($_GET['jomu_suspended_browse']) && (string) $_GET['jomu_suspended_browse'] === '1') {
    $_SESSION['jomu_suspended_browse'] = true;
    unset($_SESSION['emailormobilenumber'], $_SESSION['id']);
}

$error = null;

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

$searchQuery = trim((string) ($_GET['search'] ?? ''));
$hasActiveSearch = $searchQuery !== '';

function normalizeHomeSearchText(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim(preg_replace('/\s+/', ' ', (string) $value));
}

function singularHomeSearchToken(string $token): string
{
    if (strlen($token) > 3 && substr($token, -3) === 'ies') {
        return substr($token, 0, -3) . 'y';
    }

    if (strlen($token) > 3 && substr($token, -1) === 's') {
        return substr($token, 0, -1);
    }

    return $token;
}

function homeSearchTokens(string $query): array
{
    $normalized = normalizeHomeSearchText($query);
    if ($normalized === '') {
        return [];
    }

    $stopWords = [
        'a' => true,
        'an' => true,
        'and' => true,
        'for' => true,
        'from' => true,
        'in' => true,
        'of' => true,
        'on' => true,
        'the' => true,
        'to' => true,
        'with' => true,
    ];

    $tokens = [];
    foreach (explode(' ', $normalized) as $token) {
        $token = singularHomeSearchToken($token);
        if ($token === '' || isset($stopWords[$token])) {
            continue;
        }
        $tokens[$token] = true;
    }

    return array_keys($tokens);
}

function resolveHomeListingType(array $listing): string
{
    $listingType = normalizeHomeSearchText((string) ($listing['listing_type'] ?? ''));
    if ($listingType === 'products') {
        return 'product';
    }
    if ($listingType === 'services') {
        return 'service';
    }
    if ($listingType === 'product' || $listingType === 'service') {
        return $listingType;
    }

    $category = normalizeHomeSearchText((string) ($listing['category'] ?? ''));
    return strpos($category, 'service') !== false ? 'service' : 'product';
}

function requestedHomeListingType(string $query): string
{
    $tokens = homeSearchTokens($query);
    if (count($tokens) !== 1) {
        return '';
    }

    if ($tokens[0] === 'product') {
        return 'product';
    }
    if ($tokens[0] === 'service') {
        return 'service';
    }

    return '';
}

function homeSearchContainsToken(string $haystack, string $token): bool
{
    if ($haystack === '' || $token === '') {
        return false;
    }

    return preg_match('/(^| )' . preg_quote($token, '/') . '($| )/', $haystack) === 1;
}

function scoreHomeListingSearch(array $listing, string $query, array $tokens, string $requestedType): int
{
    if ($query === '') {
        return 1;
    }

    $listingType = resolveHomeListingType($listing);
    if ($requestedType !== '' && $listingType !== $requestedType) {
        return 0;
    }

    $queryText = normalizeHomeSearchText($query);
    $business = normalizeHomeSearchText((string) ($listing['seller_businessname'] ?? ''));
    $stock = normalizeHomeSearchText((string) ($listing['stockname'] ?? ''));
    $category = normalizeHomeSearchText((string) ($listing['category'] ?? ''));
    $description = normalizeHomeSearchText((string) ($listing['description'] ?? ''));
    $hashtags = normalizeHomeSearchText((string) ($listing['hashtags'] ?? ''));
    $typeText = normalizeHomeSearchText($listingType);
    $score = 0;

    if ($requestedType !== '') {
        $score += 40;
    }

    foreach ([
        [$business, 300, 220, 70],
        [$category, 180, 130, 45],
        [$typeText, 170, 120, 40],
        [$stock, 150, 100, 35],
        [$description, 70, 35, 16],
        [$hashtags, 60, 30, 14],
    ] as $fieldRule) {
        [$field, $exactPoints, $phrasePoints, $tokenPoints] = $fieldRule;
        if ($field === '') {
            continue;
        }

        if ($field === $queryText) {
            $score += $exactPoints;
        } elseif ($queryText !== '' && strpos($field, $queryText) !== false) {
            $score += $phrasePoints;
        }

        foreach ($tokens as $token) {
            if (homeSearchContainsToken($field, $token)) {
                $score += $tokenPoints;
            } elseif (strlen($token) >= 4 && strpos($field, $token) !== false) {
                $score += (int) floor($tokenPoints / 2);
            }
        }
    }

    return $score;
}

function homeListingCreatedTimestamp(array $listing): int
{
    $createdAt = (string) ($listing['created_at'] ?? '');
    $timestamp = $createdAt !== '' ? strtotime($createdAt) : false;
    if ($timestamp !== false) {
        return (int) $timestamp;
    }

    return (int) ($listing['listing_id'] ?? 0);
}

function homeTableExists(mysqli $conn, string $tableName): bool
{
    $safeTableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTableName}'");
    if ($result instanceof mysqli_result) {
        $exists = $result->num_rows > 0;
        $result->free();
        return $exists;
    }

    return false;
}

function addHomeWeightedValue(array &$target, string $key, int $weight): void
{
    $key = normalizeHomeSearchText($key);
    if ($key === '' || $weight <= 0) {
        return;
    }

    $target[$key] = ($target[$key] ?? 0) + $weight;
}

function buildHomePersonalizationContext(mysqli $conn, int $currentUserId): array
{
    $context = [
        'listing_ids' => [],
        'seller_ids' => [],
        'categories' => [],
        'types' => [],
        'terms' => [],
        'global_terms' => [],
    ];

    if ($currentUserId <= 0) {
        return $context;
    }

    if (homeTableExists($conn, 'listing_view_stats')) {
        $viewStmt = $conn->prepare(
            "SELECT l.listing_id, l.user_id, l.category, l.listing_type, l.stockname, l.description, u.businessname, lvs.view_count
             FROM listing_view_stats lvs
             INNER JOIN listings l ON l.listing_id = lvs.listing_id
             INNER JOIN users u ON u.id = l.user_id
             WHERE lvs.user_id = ?
             ORDER BY lvs.view_count DESC, lvs.last_viewed_at DESC
             LIMIT 40"
        );
        if ($viewStmt) {
            $viewStmt->bind_param('i', $currentUserId);
            $viewStmt->execute();
            $viewResult = $viewStmt->get_result();
            while ($row = $viewResult->fetch_assoc()) {
                $weight = max(1, min(8, (int) ($row['view_count'] ?? 1)));
                $listingId = (int) ($row['listing_id'] ?? 0);
                $sellerId = (int) ($row['user_id'] ?? 0);
                if ($listingId > 0) {
                    $context['listing_ids'][$listingId] = ($context['listing_ids'][$listingId] ?? 0) + 8 + $weight;
                }
                if ($sellerId > 0) {
                    $context['seller_ids'][$sellerId] = ($context['seller_ids'][$sellerId] ?? 0) + 3 + $weight;
                }
                addHomeWeightedValue($context['categories'], (string) ($row['category'] ?? ''), 5 + $weight);
                addHomeWeightedValue($context['types'], resolveHomeListingType($row), 3 + $weight);
                addHomeWeightedValue($context['terms'], (string) ($row['stockname'] ?? ''), 2 + $weight);
                addHomeWeightedValue($context['terms'], (string) ($row['businessname'] ?? ''), 2 + $weight);
                addHomeWeightedValue($context['terms'], (string) ($row['description'] ?? ''), 1 + $weight);
            }
            $viewStmt->close();
        }
    }

    if (homeTableExists($conn, 'purchase_requests')) {
        $purchaseStmt = $conn->prepare(
            "SELECT l.listing_id, l.user_id, l.category, l.listing_type, l.stockname, l.description, u.businessname, COUNT(*) AS request_count
             FROM purchase_requests pr
             INNER JOIN listings l ON l.listing_id = pr.listing_id
             INNER JOIN users u ON u.id = l.user_id
             WHERE pr.buyer_user_id = ?
             GROUP BY l.listing_id, l.user_id, l.category, l.listing_type, l.stockname, l.description, u.businessname
             ORDER BY request_count DESC, MAX(pr.created_at) DESC
             LIMIT 40"
        );
        if ($purchaseStmt) {
            $purchaseStmt->bind_param('i', $currentUserId);
            $purchaseStmt->execute();
            $purchaseResult = $purchaseStmt->get_result();
            while ($row = $purchaseResult->fetch_assoc()) {
                $weight = max(1, min(10, (int) ($row['request_count'] ?? 1)));
                $listingId = (int) ($row['listing_id'] ?? 0);
                $sellerId = (int) ($row['user_id'] ?? 0);
                if ($listingId > 0) {
                    $context['listing_ids'][$listingId] = ($context['listing_ids'][$listingId] ?? 0) + 18 + $weight;
                }
                if ($sellerId > 0) {
                    $context['seller_ids'][$sellerId] = ($context['seller_ids'][$sellerId] ?? 0) + 8 + $weight;
                }
                addHomeWeightedValue($context['categories'], (string) ($row['category'] ?? ''), 12 + $weight);
                addHomeWeightedValue($context['types'], resolveHomeListingType($row), 8 + $weight);
                addHomeWeightedValue($context['terms'], (string) ($row['stockname'] ?? ''), 5 + $weight);
                addHomeWeightedValue($context['terms'], (string) ($row['businessname'] ?? ''), 5 + $weight);
                addHomeWeightedValue($context['terms'], (string) ($row['description'] ?? ''), 2 + $weight);
            }
            $purchaseStmt->close();
        }
    }

    if (homeTableExists($conn, 'user_search_interest')) {
        $searchStmt = $conn->prepare(
            "SELECT search_term, search_count
             FROM user_search_interest
             WHERE user_id = ?
             ORDER BY search_count DESC, last_searched_at DESC
             LIMIT 30"
        );
        if ($searchStmt) {
            $searchStmt->bind_param('i', $currentUserId);
            $searchStmt->execute();
            $searchResult = $searchStmt->get_result();
            while ($row = $searchResult->fetch_assoc()) {
                $weight = max(1, min(10, (int) ($row['search_count'] ?? 1)));
                addHomeWeightedValue($context['terms'], (string) ($row['search_term'] ?? ''), 6 + $weight);
            }
            $searchStmt->close();
        }

        $globalSearchStmt = $conn->prepare(
            "SELECT search_term, SUM(search_count) AS total_searches
             FROM user_search_interest
             WHERE user_id <> ?
             GROUP BY search_term
             ORDER BY total_searches DESC, MAX(last_searched_at) DESC
             LIMIT 30"
        );
        if ($globalSearchStmt) {
            $globalSearchStmt->bind_param('i', $currentUserId);
            $globalSearchStmt->execute();
            $globalSearchResult = $globalSearchStmt->get_result();
            while ($row = $globalSearchResult->fetch_assoc()) {
                $weight = max(1, min(20, (int) ($row['total_searches'] ?? 1)));
                addHomeWeightedValue($context['global_terms'], (string) ($row['search_term'] ?? ''), $weight);
            }
            $globalSearchStmt->close();
        }
    }

    return $context;
}

function scoreHomeListingPersonalization(array $listing, array $context): int
{
    $score = 0;
    $listingId = (int) ($listing['listing_id'] ?? 0);
    $sellerId = (int) ($listing['user_id'] ?? 0);

    if ($listingId > 0) {
        $score += (int) ($context['listing_ids'][$listingId] ?? 0);
    }
    if ($sellerId > 0) {
        $score += (int) ($context['seller_ids'][$sellerId] ?? 0);
    }

    $category = normalizeHomeSearchText((string) ($listing['category'] ?? ''));
    $listingType = resolveHomeListingType($listing);
    $searchableText = normalizeHomeSearchText(implode(' ', [
        (string) ($listing['stockname'] ?? ''),
        (string) ($listing['category'] ?? ''),
        (string) ($listing['description'] ?? ''),
        (string) ($listing['hashtags'] ?? ''),
        (string) ($listing['seller_businessname'] ?? ''),
        $listingType,
    ]));

    if ($category !== '') {
        $score += (int) ($context['categories'][$category] ?? 0);
    }
    $score += (int) ($context['types'][$listingType] ?? 0);

    foreach ($context['terms'] as $term => $weight) {
        $term = normalizeHomeSearchText((string) $term);
        if ($term === '') {
            continue;
        }

        if (strpos($searchableText, $term) !== false) {
            $score += (int) $weight;
            continue;
        }

        foreach (homeSearchTokens($term) as $token) {
            if (strlen($token) >= 3 && strpos($searchableText, $token) !== false) {
                $score += max(1, (int) floor((int) $weight / 2));
                break;
            }
        }
    }

    return $score;
}

function scoreHomeListingTrending(array $listing, array $globalTerms = []): int
{
    $views = max(0, (int) ($listing['views'] ?? 0));
    $recentBoost = max(0, 30 - (int) floor((time() - homeListingCreatedTimestamp($listing)) / 86400));
    $score = ($views * 10) + $recentBoost + (int) ($listing['listing_id'] ?? 0);

    if ($globalTerms !== []) {
        $searchableText = normalizeHomeSearchText(implode(' ', [
            (string) ($listing['stockname'] ?? ''),
            (string) ($listing['category'] ?? ''),
            (string) ($listing['description'] ?? ''),
            (string) ($listing['hashtags'] ?? ''),
            (string) ($listing['seller_businessname'] ?? ''),
            resolveHomeListingType($listing),
        ]));

        foreach ($globalTerms as $term => $weight) {
            $term = normalizeHomeSearchText((string) $term);
            if ($term === '') {
                continue;
            }

            if (strpos($searchableText, $term) !== false) {
                $score += ((int) $weight * 6);
                continue;
            }

            foreach (homeSearchTokens($term) as $token) {
                if (strlen($token) >= 3 && strpos($searchableText, $token) !== false) {
                    $score += ((int) $weight * 3);
                    break;
                }
            }
        }
    }

    return $score;
}

function shuffleHomeListings(array $listings): array
{
    if (count($listings) <= 1) {
        return $listings;
    }

    shuffle($listings);
    return $listings;
}

function appendRemainingHomeListings(array $listings, array $selected, array $selectedIds): array
{
    foreach ($listings as $listing) {
        $listingId = (int) ($listing['listing_id'] ?? 0);
        if ($listingId > 0 && isset($selectedIds[$listingId])) {
            continue;
        }
        $selected[] = $listing;
        if ($listingId > 0) {
            $selectedIds[$listingId] = true;
        }
    }

    return $selected;
}

function applyHomeFeedMix(array $listings, array $context): array
{
    if (count($listings) <= 1) {
        return $listings;
    }

    if (count($listings) <= 60) {
        return shuffleHomeListings($listings);
    }

    $personalized = [];
    $trending = [];
    foreach ($listings as $listing) {
        $listing['_home_personal_score'] = scoreHomeListingPersonalization($listing, $context);
        $listing['_home_trending_score'] = scoreHomeListingTrending($listing, $context['global_terms'] ?? []);
        if ((int) $listing['_home_personal_score'] > 0) {
            $personalized[] = $listing;
        } else {
            $trending[] = $listing;
        }
    }

    usort($personalized, function (array $a, array $b): int {
        $scoreCompare = (int) ($b['_home_personal_score'] ?? 0) <=> (int) ($a['_home_personal_score'] ?? 0);
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }
        return (int) ($b['_home_trending_score'] ?? 0) <=> (int) ($a['_home_trending_score'] ?? 0);
    });

    usort($trending, function (array $a, array $b): int {
        return (int) ($b['_home_trending_score'] ?? 0) <=> (int) ($a['_home_trending_score'] ?? 0);
    });

    $totalCount = count($listings);
    $personalTarget = min(count($personalized), (int) round($totalCount * 0.8));
    $similarLowViewTarget = min(count($personalized), (int) ceil($totalCount * 0.1), $personalTarget);
    $selected = [];
    $selectedIds = [];

    if ($similarLowViewTarget > 0) {
        $similarLowViewListings = $personalized;
        usort($similarLowViewListings, function (array $a, array $b): int {
            $viewsCompare = (int) ($a['views'] ?? 0) <=> (int) ($b['views'] ?? 0);
            if ($viewsCompare !== 0) {
                return $viewsCompare;
            }

            $scoreCompare = (int) ($b['_home_personal_score'] ?? 0) <=> (int) ($a['_home_personal_score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return homeListingCreatedTimestamp($b) <=> homeListingCreatedTimestamp($a);
        });

        foreach ($similarLowViewListings as $listing) {
            $listingId = (int) ($listing['listing_id'] ?? 0);
            if ($listingId > 0 && isset($selectedIds[$listingId])) {
                continue;
            }
            $selected[] = $listing;
            if ($listingId > 0) {
                $selectedIds[$listingId] = true;
            }
            if (count($selected) >= $similarLowViewTarget) {
                break;
            }
        }
    }

    foreach ($personalized as $listing) {
        if (count($selected) >= $personalTarget) {
            break;
        }
        $listingId = (int) ($listing['listing_id'] ?? 0);
        if ($listingId > 0 && isset($selectedIds[$listingId])) {
            continue;
        }
        $selected[] = $listing;
        if ($listingId > 0) {
            $selectedIds[$listingId] = true;
        }
    }

    $trendingPool = array_merge($trending, array_slice($personalized, $personalTarget));
    usort($trendingPool, function (array $a, array $b): int {
        return (int) ($b['_home_trending_score'] ?? 0) <=> (int) ($a['_home_trending_score'] ?? 0);
    });

    foreach ($trendingPool as $listing) {
        $listingId = (int) ($listing['listing_id'] ?? 0);
        if ($listingId > 0 && isset($selectedIds[$listingId])) {
            continue;
        }
        $selected[] = $listing;
        if ($listingId > 0) {
            $selectedIds[$listingId] = true;
        }
        if (count($selected) >= $totalCount) {
            break;
        }
    }

    $selected = appendRemainingHomeListings($listings, $selected, $selectedIds);

    return shuffleHomeListings($selected);
}

function applyGuestTrendingFeed(array $listings): array
{
    if (count($listings) <= 1) {
        return $listings;
    }

    if (count($listings) <= 60) {
        return shuffleHomeListings($listings);
    }

    $totalCount = count($listings);
    $discoveryTarget = min($totalCount, (int) ceil($totalCount * 0.1));
    $currentPostTarget = min($discoveryTarget, max(1, (int) ceil($totalCount * 0.02)));
    $lowViewTarget = max(0, $discoveryTarget - $currentPostTarget);
    $selected = [];
    $selectedIds = [];

    $currentPostings = $listings;
    usort($currentPostings, function (array $a, array $b): int {
        $createdCompare = homeListingCreatedTimestamp($b) <=> homeListingCreatedTimestamp($a);
        if ($createdCompare !== 0) {
            return $createdCompare;
        }

        return (int) ($a['views'] ?? 0) <=> (int) ($b['views'] ?? 0);
    });

    foreach ($currentPostings as $listing) {
        $listingId = (int) ($listing['listing_id'] ?? 0);
        if ($listingId > 0 && isset($selectedIds[$listingId])) {
            continue;
        }
        $selected[] = $listing;
        if ($listingId > 0) {
            $selectedIds[$listingId] = true;
        }
        if (count($selected) >= $currentPostTarget) {
            break;
        }
    }

    if ($lowViewTarget > 0) {
        $lowViewListings = $listings;
        usort($lowViewListings, function (array $a, array $b): int {
            $viewsCompare = (int) ($a['views'] ?? 0) <=> (int) ($b['views'] ?? 0);
            if ($viewsCompare !== 0) {
                return $viewsCompare;
            }

            return homeListingCreatedTimestamp($b) <=> homeListingCreatedTimestamp($a);
        });

        foreach ($lowViewListings as $listing) {
            $listingId = (int) ($listing['listing_id'] ?? 0);
            if ($listingId > 0 && isset($selectedIds[$listingId])) {
                continue;
            }
            $selected[] = $listing;
            if ($listingId > 0) {
                $selectedIds[$listingId] = true;
            }
            if (count($selected) >= $discoveryTarget) {
                break;
            }
        }
    }

    $trendingListings = $listings;
    usort($trendingListings, function (array $a, array $b): int {
        $viewsCompare = (int) ($b['views'] ?? 0) <=> (int) ($a['views'] ?? 0);
        if ($viewsCompare !== 0) {
            return $viewsCompare;
        }

        return homeListingCreatedTimestamp($b) <=> homeListingCreatedTimestamp($a);
    });

    foreach ($trendingListings as $listing) {
        $listingId = (int) ($listing['listing_id'] ?? 0);
        if ($listingId > 0 && isset($selectedIds[$listingId])) {
            continue;
        }
        $selected[] = $listing;
        if ($listingId > 0) {
            $selectedIds[$listingId] = true;
        }
        if (count($selected) >= $totalCount) {
            break;
        }
    }

    $selected = appendRemainingHomeListings($listings, $selected, $selectedIds);

    return shuffleHomeListings($selected);
}

$image_listings = [];
$video_listings = [];
$currentUserId = 0;

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

$viewsColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'views'");
if (!$viewsColumnCheck || $viewsColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN views INT NOT NULL DEFAULT 0");
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

$searchTokens = homeSearchTokens($searchQuery);
$requestedListingType = requestedHomeListingType($searchQuery);
$homePersonalizationContext = buildHomePersonalizationContext($conn, $currentUserId);
$matchedListings = [];

$res = $conn->query(
    "SELECT l.*, u.businessname AS seller_businessname, u.profilepic AS seller_profilepic
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     WHERE COALESCE(l.out_of_stock, 0) = 0
       AND COALESCE(l.moderation_status, 'visible') <> 'hidden'
       AND l.admin_purged_at IS NULL
     ORDER BY l.created_at DESC"
);
while ($row = $res->fetch_assoc()){
    $row['_home_search_score'] = $hasActiveSearch
        ? scoreHomeListingSearch($row, $searchQuery, $searchTokens, $requestedListingType)
        : 1;

    if ($hasActiveSearch && (int) $row['_home_search_score'] <= 0) {
        continue;
    }

    $matchedListings[] = $row;
}

$categoryCounts = [];
foreach ($matchedListings as $listing) {
    $categoryKey = normalizeHomeSearchText((string) ($listing['category'] ?? ''));
    if ($categoryKey === '') {
        continue;
    }
    $categoryCounts[$categoryKey] = ($categoryCounts[$categoryKey] ?? 0) + 1;
}

if ($hasActiveSearch) {
    usort($matchedListings, function (array $a, array $b) use ($requestedListingType, $categoryCounts): int {
        $scoreCompare = (int) ($b['_home_search_score'] ?? 0) <=> (int) ($a['_home_search_score'] ?? 0);
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }

        if ($requestedListingType !== '') {
            $aCategory = normalizeHomeSearchText((string) ($a['category'] ?? ''));
            $bCategory = normalizeHomeSearchText((string) ($b['category'] ?? ''));
            $categoryCompare = (int) ($categoryCounts[$bCategory] ?? 0) <=> (int) ($categoryCounts[$aCategory] ?? 0);
            if ($categoryCompare !== 0) {
                return $categoryCompare;
            }
        }

        return homeListingCreatedTimestamp($b) <=> homeListingCreatedTimestamp($a);
    });
} else {
    $matchedListings = $currentUserId > 0
        ? applyHomeFeedMix($matchedListings, $homePersonalizationContext)
        : applyGuestTrendingFeed($matchedListings);
}

foreach ($matchedListings as $row) {
    if (getMediaType($row['media']) === 'video') {
        $video_listings[] = $row;
        continue;
    }

    $image_listings[] = $row;
}

$showImageNoMatch = $hasActiveSearch && count($image_listings) === 0;
$showVideoNoMatch = $hasActiveSearch && count($video_listings) === 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JoMu</title>
    <meta name="description" content="Find suppliers, wholesalers, manufacturers, service providers and clients
    across Uganda on JoMu, your trusted B2B marketplace for business growth and connections.">
    <link rel="stylesheet" href="/assets/bootstrap.css">
    <link rel="stylesheet" href="/assets/style.css">
    <!-- <link rel="stylesheet" href="/assets/bootstrap.min.css"> -->
         <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/jomu_favicon_orange-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/jomu_favicon_orange-32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/assets/images/jomu_favicon_orange-48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/jomu_favicon_orange-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/jomu_favicon_orange-512.png">
    <style>
        html {
            background-color: #161515;
        }

        body.home-page {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }

        body.home-page > footer {
            margin-top: auto;
        }

        @media (max-width: 767.98px) {
            body.home-page {
                padding-top: 60px;
            }

            body.home-page #navbarone {
                display: flex;
                align-items: center;
                justify-content: space-between;
                height: 60px;
                min-height: 60px;
                padding-left: 0;
                padding-right: 4px;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
            }

            body.home-page #navbarone > .d-block.d-md-none.d-lg-none.py-0.px-0 {
                width: auto;
                margin: 0 0 0 -8px;
                padding: 0 !important;
                flex: 0 0 auto;
            }

            body.home-page #navbarone .brand-logos {
                display: block;
                margin: 0;
                padding-left: 0 !important;
            }

            body.home-page #navbarone .navbar-brand {
                margin-left: 0;
                margin-right: 0;
            }

            body.home-page #navbarone .brand-logos .logo {
                margin-right: 0 !important;
            }

            body.home-page #navbarone .searching-small {
                position: absolute;
                left: calc(50% + 24px);
                transform: translateX(-50%);
                width: clamp(165px, calc(100vw - 165px), 265px);
                max-width: calc(100vw - 165px);
                min-width: 0;
                padding: 0;
            }

            body.home-page.home-page-guest #navbarone .searching-small {
                left: calc(50% + 30px);
                width: clamp(174px, calc(100vw - 152px), 278px);
                max-width: calc(100vw - 152px);
            }

            body.home-page #navbarone .searching-small .input-group {
                width: 100%;
            }

            body.home-page #navbarone .searching-small .form-control,
            body.home-page #navbarone .searching-small .btn {
                margin-top: 0;
                margin-left: 0;
            }

            body.home-page #navbarone .searching-small .form-control {
                border-radius: 10px 0 0 10px;
            }

            body.home-page #navbarone .navbar-toggler {
                position: static;
                right: auto;
                top: auto;
                margin: 0;
                flex: 0 0 auto;
            }

            body.home-page #navbarone .navbar-toggler.d-lg-none {
                margin-right: -7px;
            }

            body.home-page #navbarone .navbar-toggler img {
                margin-right: 0 !important;
            }

            @media (max-width: 420px) {
                body.home-page #navbarone .searching-small {
                    left: calc(50% + 28px);
                    width: clamp(153px, calc(100vw - 180px), 245px);
                    max-width: calc(100vw - 180px);
                }

                body.home-page.home-page-guest #navbarone .searching-small {
                    left: calc(50% + 31px);
                    width: clamp(162px, calc(100vw - 168px), 258px);
                    max-width: calc(100vw - 168px);
                }
            }

            @media (max-width: 360px) {
                body.home-page #navbarone .searching-small {
                    left: calc(50% + 32px);
                    width: clamp(141px, calc(100vw - 190px), 215px);
                    max-width: calc(100vw - 190px);
                }

                body.home-page.home-page-guest #navbarone .searching-small {
                    left: calc(50% + 34px);
                    width: clamp(148px, calc(100vw - 180px), 225px);
                    max-width: calc(100vw - 180px);
                }
            }

            .home-page footer {
                padding-left: 0;
                padding-right: 0;
            }

            .home-page footer .footer-shell {
                width: 100%;
                max-width: none;
                margin: 0;
                padding-left: 9px !important;
                padding-right: 9px !important;
            }

            .home-page footer .footer-links {
                /* gap: 0 4x
                justify-content: space-between;*/
                gap: 6px 12px;
                justify-content: center;
            }

            .home-page footer .footer-shell > .footer-links:first-child {
                padding-left: 0;
                padding-right: 0;
                box-sizing: border-box;
            }

            .home-page footer .footer-links a:last-child {
                width: 100%;
                text-align: center;
            }

            .home-page footer .social-media-links {
                display: grid;
                grid-template-columns: repeat(4, max-content);
                justify-content: center;
                column-gap: 6px;
                row-gap: 0 !important;
            }

            .home-page footer .social-media-links h6 {
                grid-column: 1 / -1;
                text-align: center;
                margin: 6px 0 0 0;
                line-height: 1;
            }

            .home-page footer h6,
            .home-page footer p {
                margin-top: 0;
                margin-bottom: 0;
            }

            .home-page footer .social-media-links + p {
                margin-top: 6px;
            }

            .home-page footer br {
                display: none;
            }

            .home-page footer .footer-shell {
                padding-bottom: 0;
            }
        }

        img.media-preview-item,
        img.media-preview-source {
            cursor: pointer;
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

        .media-preview-admin-bar {
            width: min(96vw, 620px);
            background: rgba(9, 9, 9, 0.92);
            color: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: left;
            z-index: 4;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            justify-content: space-between;
        }

        .media-preview-admin-meta {
            margin: 0;
            font-size: 0.85rem;
            line-height: 1.35;
            flex: 1 1 200px;
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
            cursor: pointer;
        }

        .listing-description {
            order: 2;
            cursor: pointer;
        }

        .listing-description-text {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-clamp: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            line-height: 1.15;
            min-height: 2.3em;
            cursor: pointer;
        }

        .product-price-range {
            cursor: pointer;
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
            line-height: 1.15;
        }

        .jomu-card-typography .product-price-range {
            font-weight: 800;
        }

        .jomu-card-typography .product-price-range .price-unit {
            font-weight: 400;
        }

        .jomu-card-typography .card-img-button {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-weight: 600;
        }

        .video-card-body {
            display: flex;
            flex-direction: column;
            gap: 0.03rem;
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
            min-width: 0;
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
            cursor: pointer;
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
            cursor: pointer;
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
            cursor: pointer;
        }

        .cards-container .card-img-top.media-preview-item {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            background: #fff;
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
            .cards-container .listing-search-item {
                display: flex;
            }

            .cards-container .card.h-100 {
                width: 100%;
                height: 318px !important;
                overflow: hidden;
            }

            .cards-container .card-img-top.media-preview-item {
                height: 190px;
                object-fit: cover;
                flex: 0 0 auto;
            }

            .cards-container .card-body {
                display: grid !important;
                grid-template-rows: 1.18em 2.42em 1.3em auto;
                row-gap: 0.1rem;
                align-content: start;
                flex: 1 1 auto;
                min-height: 0;
                overflow: hidden;
            }

            .cards-container .listing-name-top {
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                line-height: 1.18;
                min-height: 1.18em;
                max-height: 1.18em;
                flex: 0 0 auto;
            }

            .cards-container .listing-description-text {
                max-height: 2.42em;
            }

            .cards-container .product-price-range {
                align-self: start;
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                line-height: 1.18;
                min-height: 1.3em;
                max-height: 1.3em;
                flex: 0 0 auto;
                margin-bottom: 0 !important;
            }

            .cards-container .listing-action-btn {
                flex: 0 0 auto;
                margin-top: 0 !important;
            }

            .video-card-body,
            .video-seller-row {
                min-width: 0;
                overflow: hidden;
            }
        }

        @media (max-width: 767.98px) {
            .cards-container {
                width: 100%;
                max-width: none;
                padding-left: 4px;
                padding-right: 4px;
            }

            .cards-container .card.h-100 {
                height: auto !important;
                min-height: 0 !important;
                overflow: hidden;
            }

            .cards-container .row {
                --bs-gutter-x: 0.25rem;
                --bs-gutter-y: 0.25rem;
            }

            .cards-container .card-img-top.media-preview-item {
                aspect-ratio: auto;
                height: clamp(100px, 30vw, 140px);
                object-fit: cover;
            }

            .cards-container .card-body {
                display: grid !important;
                grid-template-rows: 1.05em 2.2em 1.1em auto;
                row-gap: 0;
                align-content: start;
                padding: 0.25rem !important;
                min-height: 0;
                overflow: hidden;
            }

            .cards-container .listing-name-top {
                font-size: 0.82rem;
                line-height: 1.05;
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                min-height: 1.05em;
            }

            .cards-container .listing-description-text {
                -webkit-line-clamp: 2;
                line-clamp: 2;
                min-height: 2.2em;
                max-height: 2.2em;
                font-size: 0.76rem;
            }

            .cards-container .product-price-range {
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                font-size: 0.76rem;
                line-height: 1.08;
                min-height: 1.1em;
                max-height: 1.1em;
                margin-bottom: 0 !important;
            }

            .cards-container .listing-action-btn {
                font-size: 0.72rem;
                line-height: 1.1;
                padding: 0.22rem 0.15rem !important;
                margin-top: 0 !important;
            }

            .video-container {
                width: 100%;
                max-width: none;
                padding-left: 4px;
                padding-right: 4px;
            }

            .video-container .row {
                --bs-gutter-x: 0.25rem;
                --bs-gutter-y: 0.25rem;
            }

            .video-card-body,
            .video-seller-row {
                min-width: 0;
                overflow: hidden;
            }

            .video-seller-name,
            .video-stock-title {
                display: block;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .video-description-brief {
                -webkit-line-clamp: 2;
                line-clamp: 2;
            }
        }
    </style>
</head>

<body class="body-one home-page<?php echo empty($_SESSION['emailormobilenumber']) && empty($_SESSION['admin_id']) ? ' home-page-guest' : ''; ?>">
    <header>
        <?php require 'components/nav.php' ?>
    </header>
    <main class="main-one">
        <?php if ($error != null) {
            echo "<h1 style='color: red; text-align: center;padding: 10px;'>".$error."</h1>";
        } ?>
        <!-- Welcome div for large screens -->
        <div class="container-fluid mt-2 d-none d-md-none d-lg-block">
            <video width="53.5%" autoplay loop muted src="/assets/videos/JoMu animation large_screens.mp4"
                class="animation welcome-carousel" style="object-fit: cover;"></video>
            <div id="myCourasel" class="carousel-slide" data-bs-ride="carousel">
                <div class="carousel-inner welcome-carousel" id="carousel-inner">
                    <div class="carousel-item active">
                        <img src="/assets/images/Buy-2.png" class="d-block w-100 h-100" alt="">
                    </div>
                    <button class="button find-partner" onclick="location.href='/bulk-orders'">Get a business
                        partner</button>
                </div>
            </div>
        </div>
        <!-- Welcome div for medium screens -->
        <div class="container-fluid mt-2 d-none d-md-block d-lg-none">
            <video width="53.5%" autoplay loop muted src="/assets/videos/JoMu animation.mp4"
                class="animation welcome-carousel-medium" style="object-fit: cover"></video>
            <div id="myCourasel2" class="carousel-slide" data-bs-ride="carousel">
                <div class="carousel-inner welcome-carousel-medium" id="carousel-inner-medium">
                    <div class="carousel-item active">
                        <img src="/assets/images/Buy-2.png" class="d-block w-100 h-100" alt="">
                    </div>
                    <button class="button find-partner-small" onclick="location.href='/bulk-orders'">Get a
                        business partner</button>
                </div>
            </div>
        </div>
        <!-- Welcome div for small screens -->
        <div class="mt-1 d-block d-md-none d-lg-none">
<!-- width="53.5% -->
            <video width="69%" autoplay loop muted src="/assets/videos/JoMu Animation small_screens.mp4"
                class="animation welcome-carousel-small" style="object-fit: cover; border-radius: 10px"></video>
            <div id="myCourasel2" class="carousel-slide" data-bs-ride="carousel">
                <div class="carousel-inner welcome-carousel-small" id="carousel-inner-small">
                    <div class="carousel-item active">
                        <img src="/assets/images/Buy-2.png" class="d-block w-100 h-100" alt="" style="margin-left: 40px;">
                    </div>
                    <button class="button find-partner-small" style="margin-bottom: -1px" onclick="location.href='/bulk-orders'">Get a
                        business partner</button>
                </div>
            </div>
        </div>
        <!-- Cards-->
        <div class="container my-2 cards-container">
            <div class="row g-1" id="imageListingsRow">
            <!-- Product Card -->                 
            <?php
             foreach ($image_listings as $listing) { ?>
                <div class="col-6 col-md-4 col-lg-3 listing-search-item" data-search-stock="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?>" data-search-category="<?php echo htmlspecialchars((string) ($listing['category'] ?? '')); ?>">
                    <div class="card h-100">
                        <?php
                            $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
                            if ($listingType !== 'product' && $listingType !== 'service') {
                                $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                            }
                            $isOwnListing = $currentUserId > 0 && (int) ($listing['user_id'] ?? 0) === $currentUserId;
                            $actionButtonLabel = $isOwnListing
                                ? 'See Listing'
                                : ($listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale');
                            $priceFrom = trim((string) ($listing['price_from'] ?? ''));
                            $priceTo = trim((string) ($listing['price_to'] ?? ''));
                            $productPriceLabel = '';
                            if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
                                $productPriceLabel = formatProductPriceRange($priceFrom, $priceTo);
                            } elseif ($listingType === 'product') {
                                $productPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                            }
                            $displayPriceLabel = $productPriceLabel;
                            if ($listingType === 'service') {
                                $displayPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                            }
                            $purchaseParams = http_build_query([
                                'image' => getMediaPath($listing['media'], '/php/'),
                                'title' => $listing['stockname'] ?? '',
                                'price' => $productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? ''),
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
                            $purchaseUrl = '/purchase-wholesale?' . $purchaseParams;
                            $actionUrl = (!$isOwnListing && $currentUserId <= 0)
                                ? '/?error=Not+Signed+In!'
                                : $purchaseUrl;
                            $homePreviewPosted = '';
                            $homeCreatedRaw = trim((string) ($listing['created_at'] ?? ''));
                            if ($homeCreatedRaw !== '') {
                                $homeCreatedTs = strtotime($homeCreatedRaw);
                                if ($homeCreatedTs !== false) {
                                    $homePreviewPosted = date('j M Y, g:i a', $homeCreatedTs);
                                }
                            }
                            $homePreviewBusiness = trim((string) ($listing['seller_businessname'] ?? ''));
                            if ($homePreviewBusiness === '') {
                                $homePreviewBusiness = 'Business';
                            }
                        ?>
                        <img src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" class="card-img-top img-fluid media-preview-item media-preview-source" alt="<?php echo htmlspecialchars($listing['stockname']); ?>" data-preview-type="image" data-preview-src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" data-preview-title="<?php echo htmlspecialchars($listing['stockname'] ?? ''); ?>" data-preview-description="<?php echo htmlspecialchars($listing['description'] ?? ''); ?>" data-preview-price="<?php echo htmlspecialchars($productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? '')); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>" data-preview-business="<?php echo htmlspecialchars($homePreviewBusiness); ?>" data-preview-posted="<?php echo htmlspecialchars($homePreviewPosted); ?>">
                        <div class="card-body p-2 d-flex flex-column jomu-card-typography" style="gap: 1px;">
                            <h5 class="card-title mb-0 listing-name-top"><?php echo htmlspecialchars($listing['stockname']); ?></h5>
                            <p class="card-text mb-0 listing-description"><span class="listing-description-text"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></span></p>
                            <?php
                                $priceText = $displayPriceLabel;
                                $unitText = '';
                                if ($listingType === 'product' && substr($displayPriceLabel, -7) === ' / unit') {
                                    $priceText = substr($displayPriceLabel, 0, -7);
                                    $unitText = ' / unit';
                                }
                            ?>
                            <p class="card-text mb-0 product-price-range"><?php echo htmlspecialchars($priceText); ?><?php if ($unitText !== '') { ?><span class="price-unit"><?php echo htmlspecialchars($unitText); ?></span><?php } ?></p>
                            <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn card-img-button py-1 mt-0 listing-action-btn"><?php echo htmlspecialchars($actionButtonLabel); ?></a>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
            <div class="listing-load-more-wrap">
                <button type="button" id="imageSeeMoreButton" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
            </div>
            <p id="imageNoMatchMessage" class="text-center py-3 mb-0" style="display: <?php echo $showImageNoMatch ? 'block' : 'none'; ?>;">No matching listings</p>
        </div>
        <!-- Videos -->
        <div class="container video-container">
            <h3> Featured Videos</h3>
            <p>See what businesses are sharing.</p>

            <div class="container my-1 video-container">
                <div class="row g-2" id="videoListingsRow">
                    <?php foreach ($video_listings as $listing) { ?>
                    <div class="col-6 col-md-4 col-lg-3 listing-search-item" data-search-stock="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?>" data-search-category="<?php echo htmlspecialchars((string) ($listing['category'] ?? '')); ?>">
                        <div class="card h-100">
                            <div class="video-wrapper">
                                <?php
                                    $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
                                    if ($listingType !== 'product' && $listingType !== 'service') {
                                        $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                        $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                    }
                                    $priceFrom = trim((string) ($listing['price_from'] ?? ''));
                                    $priceTo = trim((string) ($listing['price_to'] ?? ''));
                                    $productPriceLabel = '';
                                    if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
                                        $productPriceLabel = formatProductPriceRange($priceFrom, $priceTo);
                                    } elseif ($listingType === 'product') {
                                        $productPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                    }
                                    $homePreviewPosted = '';
                                    $homeCreatedRaw = trim((string) ($listing['created_at'] ?? ''));
                                    if ($homeCreatedRaw !== '') {
                                        $homeCreatedTs = strtotime($homeCreatedRaw);
                                        if ($homeCreatedTs !== false) {
                                            $homePreviewPosted = date('j M Y, g:i a', $homeCreatedTs);
                                        }
                                    }
                                    $homePreviewBusiness = trim((string) ($listing['seller_businessname'] ?? ''));
                                    if ($homePreviewBusiness === '') {
                                        $homePreviewBusiness = 'Business';
                                    }
                                ?>
                                <video class="video-content media-preview-item media-preview-source" controls muted data-preview-type="video" data-preview-src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" data-preview-title="<?php echo htmlspecialchars($listing['stockname'] ?? ''); ?>" data-preview-description="<?php echo htmlspecialchars($listing['description'] ?? ''); ?>" data-preview-price="<?php echo htmlspecialchars($productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? '')); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>" data-preview-business="<?php echo htmlspecialchars($homePreviewBusiness); ?>" data-preview-posted="<?php echo htmlspecialchars($homePreviewPosted); ?>">
                                    <source src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" type="video/mp4">
                                </video>
                            </div>
                            <div class="card-body p-2 video-card-body">
                                <?php
                                    $sellerName = trim((string) ($listing['seller_businessname'] ?? ''));
                                    if ($sellerName === '') {
                                        $sellerName = 'Business';
                                    }
                                    $sellerProfile = trim((string) ($listing['seller_profilepic'] ?? ''));
                                    $sellerInitial = strtoupper(substr($sellerName, 0, 1));
                                    if ($sellerInitial === '') {
                                        $sellerInitial = 'B';
                                    }
                                    $sellerProfileUrl = ((int) ($listing['user_id'] ?? 0) === $currentUserId && $currentUserId > 0)
                                        ? '/profile'
                                        : ('/visitor-profile?user_id=' . urlencode((string) ($listing['user_id'] ?? '')));

                                    $savedHashtags = trim((string) ($listing['hashtags'] ?? ''));
                                    if ($savedHashtags !== '') {
                                        $savedHashtags = preg_replace('/\s+/', ' ', $savedHashtags);
                                    }

                                    $tagSources = [
                                        (string) ($listing['listing_type'] ?? ''),
                                        (string) ($listing['category'] ?? ''),
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
                                    $hashtagDisplay = $savedHashtags !== '' ? $savedHashtags : implode(' ', $hashtags);
                                ?>
                                <a href="<?php echo htmlspecialchars($sellerProfileUrl); ?>" class="video-seller-row video-seller-link">
                                    <?php if ($sellerProfile !== '') { ?>
                                        <img src="<?php echo htmlspecialchars(getMediaPath($sellerProfile, '/php/')); ?>" alt="<?php echo htmlspecialchars($sellerName); ?>" class="video-seller-dp">
                                    <?php } else { ?>
                                        <span class="video-seller-dp video-seller-dp-fallback"><?php echo htmlspecialchars($sellerInitial); ?></span>
                                    <?php } ?>
                                    <p class="video-seller-name"><?php echo htmlspecialchars($sellerName); ?></p>
                                </a>
                                <h6 class="video-stock-title"><?php echo htmlspecialchars($listing['stockname']); ?></h6>
                                <p class="video-description-brief"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></p>
                                <p class="video-hashtags mb-0"><?php echo htmlspecialchars($hashtagDisplay); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="listing-load-more-wrap">
                    <button type="button" id="videoSeeMoreButton" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                </div>
                <p id="videoNoMatchMessage" class="text-center py-3 mb-0" style="display: <?php echo $showVideoNoMatch ? 'block' : 'none'; ?>;">No matching listings</p>
            </div>

        </div>





    </main>
    <footer>
        <div class="container-fluid footer-shell">
            <div class="footer-links">
                <a href="/terms-and-conditions">Terms of Use</a>
                <a href="/privacy-policy">Privacy Policy</a>
                <a href="/help">Help</a>
                <a href="/support">Support</a>
                <a href="/feedback">Give Feedback</a>
                <a href="/about">About JoMu</a>
            </div>
            <div class="footer-links social-media-links" style="gap: 15px;">
                <h6>Stay in Touch:</h6>
                <?php
                $footerSiteLinks = isset($siteLinks) && is_array($siteLinks) ? $siteLinks : [];
                $footerSocialHref = static function (string $key) use ($footerSiteLinks): string {
                    $url = trim((string) ($footerSiteLinks[$key] ?? ''));
                    return $url !== '' ? htmlspecialchars($url, ENT_QUOTES, 'UTF-8') : '#';
                };
                ?>
                <a href="<?php echo $footerSocialHref('facebook'); ?>"><span><img src="/assets/images/icons/Facebook Icon.png"
                            class="social-media-icons"></span>Facebook</a>
                <a href="<?php echo $footerSocialHref('tiktok'); ?>"><span><img src="/assets/images/icons/Tiktok Icon.png"
                            class="social-media-icons"></span>TikTok</a>
                <a href="<?php echo $footerSocialHref('instagram'); ?>"><span><img src="/assets/images/icons/Instagram Icon.png"
                            class="social-media-icons"></span>Instagram</a>
                <a href="<?php echo $footerSocialHref('x'); ?>"><span><img src="/assets/images/icons/X Icon.png"
                            class="social-media-icons"></span>Twitter(X)</a>
            </div>
            <!-- <a href=""><img src="/assets/images/JoMu logo redesigned.png" class="img-fluid footer-jomu"></a>
                DOWNLOAD JoMu APP FREE.
                Enjoy exclusive offers!             -->
            <br>
            <p style="font-size: 13px;">&copy; 2026 JoMu. All rights reserved.</p>
        </div>
    </footer>

    <!-- Back to top button -->
    <button id="backToTop">&#8593;</button>
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
        <img id="mediaPreviewWatermark" class="media-preview-watermark" src="/assets/images/JoMu logo redesigned.png" alt="JoMu watermark">
    </div>

    <script>

        const searchInputs = Array.from(document.querySelectorAll('#searchbarInput'));

        // Search bar moving texts.
        const phrases = ["Search for Products", "Search for Services", "Search for Businesses", "Search for Deals", "Search for Clients"];
        let index = 0;

        setInterval(() => {
            searchInputs.forEach((input) => {
                input.setAttribute("placeholder", phrases[index]);
            });
            index = (index + 1) % phrases.length;
        }, 2500);

        // Home listings search.
        const searchButtons = Array.from(
            document.querySelectorAll('.searching button[type="submit"], .searching-medium button[type="submit"], .searching-small button[type="submit"]')
        );
        const currentSearchValue = new URLSearchParams(window.location.search).get('search') || '';

        searchInputs.forEach((input) => {
            input.value = currentSearchValue;
        });

        function normalizeSearchText(value) {
            return String(value || '').trim();
        }

        function trackSearchInterest(query) {
            if (query === '') {
                return;
            }

            const payload = JSON.stringify({ term: query });
            try {
                if (navigator.sendBeacon) {
                    const blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon('/php/track_search_interest.php', blob);
                    return;
                }
            } catch (error) {
                // Fall back to fetch below.
            }

            fetch('/php/track_search_interest.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true,
                credentials: 'same-origin'
            }).catch(() => {});
        }

        function submitListingsSearch(rawQuery) {
            const query = normalizeSearchText(rawQuery);
            const url = new URL(window.location.href);

            if (query === '') {
                url.searchParams.delete('search');
            } else {
                trackSearchInterest(query);
                url.searchParams.set('search', query);
            }

            window.location.href = url.toString();
        }

        function getPrimarySearchInput() {
            const activeInput = document.activeElement;
            if (activeInput && searchInputs.includes(activeInput)) {
                return activeInput;
            }
            return searchInputs.find((input) => normalizeSearchText(input.value) !== '') || searchInputs[0] || null;
        }

        function syncSearchInputValues(sourceInput) {
            const sourceValue = sourceInput?.value ?? '';
            searchInputs.forEach((input) => {
                if (input !== sourceInput) {
                    input.value = sourceValue;
                }
            });
        }

        searchInputs.forEach((input) => {
            input.addEventListener('input', () => {
                syncSearchInputValues(input);
            });

            input.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                syncSearchInputValues(input);
                submitListingsSearch(input.value);
            });
        });

        searchButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const input = getPrimarySearchInput();
                if (!input) return;
                syncSearchInputValues(input);
                submitListingsSearch(input.value);
            });
        });

        function setupSeeMoreListings(options) {
            const row = document.getElementById(options.rowId);
            const button = document.getElementById(options.buttonId);
            if (!row || !button) return;

            const items = Array.from(row.querySelectorAll('.listing-search-item'));
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
            rowId: 'imageListingsRow',
            buttonId: 'imageSeeMoreButton',
            desktopBatch: 24,
            mobileBatch: 20
        });

        setupSeeMoreListings({
            rowId: 'videoListingsRow',
            buttonId: 'videoSeeMoreButton',
            desktopBatch: 8,
            mobileBatch: 4
        });



        // Navbar change.
        document.addEventListener('DOMConentLoaded', () => {
            const navbar = document.getElementById('navbarone');

            function handleScroll() {
                if (window.scrollY > 100) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            }

            handleScroll();
            window.addEventListener('scroll', handleScroll, { passive: true });
        });

        // Back to top functionality.
        const btn = document.getElementById("backToTop");

        window.addEventListener("scroll", () => {
            if (window.scrollY > 400) {
                btn.style.display = "block";
            } else {
                btn.style.display = "none"
            }
        });

        btn.addEventListener("click", () => {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });

        // Fullscreen-style preview for listing images and videos.
        const mediaOverlay = document.getElementById("mediaPreviewOverlay");
        const mediaClose = document.getElementById("mediaPreviewClose");
        const mediaPreviewImage = document.getElementById("mediaPreviewImage");
        const mediaPreviewVideo = document.getElementById("mediaPreviewVideo");
        const mediaPreviewDetails = document.getElementById("mediaPreviewDetails");
        const mediaPreviewTitle = document.getElementById("mediaPreviewTitle");
        const mediaPreviewPrice = document.getElementById("mediaPreviewPrice");
        const mediaPreviewDescription = document.getElementById("mediaPreviewDescription");
        const mediaWatermark = document.getElementById("mediaPreviewWatermark");
        const countedHomePreviewViews = new Set();
        const countedHomeVideoViews = new Set();
        const pendingHomeVideoViewTimers = new Map();
        let recentStorageKey = "jomuRecentlyViewedListings:guest";
        let recentStorageKeyPromise = null;

        function saveRecentlyViewedListing(item, storageKey) {
            if (!item || !item.listing_id) return;
            try {
                const raw = localStorage.getItem(storageKey);
                const currentItems = raw ? JSON.parse(raw) : [];
                const safeItems = Array.isArray(currentItems) ? currentItems : [];
                const nextItem = {
                    listing_id: Number(item.listing_id) || 0,
                    viewed_at: new Date().toISOString(),
                    media_type: item.media_type === "video" ? "video" : "image",
                    html: String(item.html || "").trim(),
                    media_src: String(item.media_src || "").trim(),
                    title: String(item.title || "").trim(),
                    description: String(item.description || "").trim(),
                    price: String(item.price || "").trim(),
                    preview_business: String(item.preview_business || "").trim(),
                    preview_posted: String(item.preview_posted || "").trim(),
                    action_label: String(item.action_label || "Purchase Wholesale").trim(),
                    purchase_url: String(item.purchase_url || "#").trim(),
                };

                const filteredItems = safeItems.filter((entry) => Number(entry?.listing_id || 0) !== nextItem.listing_id);
                filteredItems.unshift(nextItem);
                localStorage.setItem(storageKey, JSON.stringify(filteredItems.slice(0, 20)));
            } catch (error) {
                // Non-blocking recent-history update.
            }
        }

        async function getRecentStorageKey() {
            if (recentStorageKeyPromise) {
                return recentStorageKeyPromise;
            }

            recentStorageKeyPromise = fetch("/php/auth_status.php", { credentials: "same-origin" })
                .then((response) => response.ok ? response.json() : null)
                .then((data) => {
                    recentStorageKey = data?.signed_in && data?.user_key
                        ? `jomuRecentlyViewedListings:${data.user_key}`
                        : "jomuRecentlyViewedListings:guest";
                    return recentStorageKey;
                })
                .catch(() => recentStorageKey);

            return recentStorageKeyPromise;
        }

        function storeRecentlyViewedListing(item) {
            getRecentStorageKey().then((storageKey) => saveRecentlyViewedListing(item, storageKey));
        }

        function storeRecentListingFromSource(sourceEl) {
            const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || "", 10);
            if (!Number.isInteger(listingId) || listingId <= 0) return;

            const cardEl = sourceEl.closest(".card");
            const columnEl = sourceEl.closest(".col-6.col-md-4.col-lg-3");
            const actionLink = cardEl?.querySelector('a[href*="/purchase-wholesale"]');
            storeRecentlyViewedListing({
                listing_id: listingId,
                media_type: sourceEl?.dataset.previewType || "image",
                html: columnEl?.outerHTML || "",
                media_src: sourceEl?.dataset.previewSrc || sourceEl?.getAttribute("src") || "",
                title: sourceEl?.dataset.previewTitle || "",
                description: sourceEl?.dataset.previewDescription || "",
                price: sourceEl?.dataset.previewPrice || "",
                preview_business: sourceEl?.dataset.previewBusiness || "",
                preview_posted: sourceEl?.dataset.previewPosted || "",
                action_label: actionLink?.textContent?.trim() || "Purchase Wholesale",
                purchase_url: actionLink?.getAttribute("href") || "#",
            });
        }

        async function incrementPreviewImageView(sourceEl) {
            const type = String(sourceEl?.dataset.previewType || "").trim();
            const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || "", 10);
            if (type !== "image" || !Number.isInteger(listingId) || listingId <= 0 || countedHomePreviewViews.has(listingId)) {
                return;
            }

            countedHomePreviewViews.add(listingId);
            storeRecentListingFromSource(sourceEl);

            try {
                await fetch(`/php/increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        async function incrementVideoPlaybackView(listingId) {
            if (!Number.isInteger(listingId) || listingId <= 0 || countedHomeVideoViews.has(listingId)) {
                return;
            }

            countedHomeVideoViews.add(listingId);
            const sourceEl = document.querySelector(`.media-preview-source[data-preview-listing-id="${listingId}"]`);
            storeRecentListingFromSource(sourceEl);

            try {
                await fetch(`/php/increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        function clearPendingVideoView(videoEl) {
            const timerId = pendingHomeVideoViewTimers.get(videoEl);
            if (timerId) {
                clearTimeout(timerId);
                pendingHomeVideoViewTimers.delete(videoEl);
            }
        }

        function scheduleVideoViewIncrement(videoEl) {
            const listingId = Number.parseInt(videoEl?.dataset.previewListingId || "", 10);
            if (!Number.isInteger(listingId) || listingId <= 0 || countedHomeVideoViews.has(listingId) || pendingHomeVideoViewTimers.has(videoEl)) {
                return;
            }

            const timerId = setTimeout(() => {
                pendingHomeVideoViewTimers.delete(videoEl);
                if (countedHomeVideoViews.has(listingId) || videoEl.paused || videoEl.ended) {
                    return;
                }
                incrementVideoPlaybackView(listingId);
            }, 2000);

            pendingHomeVideoViewTimers.set(videoEl, timerId);
        }

        function registerVideoViewTracking(videoEl) {
            if (!(videoEl instanceof HTMLVideoElement) || videoEl.dataset.viewTrackingBound === "1") {
                return;
            }

            videoEl.dataset.viewTrackingBound = "1";
            videoEl.addEventListener("play", () => scheduleVideoViewIncrement(videoEl));
            videoEl.addEventListener("pause", () => clearPendingVideoView(videoEl));
            videoEl.addEventListener("ended", () => clearPendingVideoView(videoEl));
            videoEl.addEventListener("emptied", () => clearPendingVideoView(videoEl));
        }

        function updateMediaPreviewDetails(sourceEl) {
            if (!mediaPreviewDetails) return;
            const title = String(sourceEl?.dataset.previewTitle || "").trim();
            const price = String(sourceEl?.dataset.previewPrice || "").trim();
            const description = String(sourceEl?.dataset.previewDescription || "");
            const hasDetails = title !== "" || price !== "" || description !== "";

            mediaPreviewTitle.textContent = title;
            mediaPreviewTitle.style.display = title ? "block" : "none";
            mediaPreviewPrice.textContent = price ? `Price: ${price}` : "";
            mediaPreviewPrice.style.display = price ? "block" : "none";
            mediaPreviewDescription.textContent = description;
            mediaPreviewDescription.style.whiteSpace = 'pre-wrap';
            mediaPreviewDescription.style.wordBreak = 'break-word';
            mediaPreviewDescription.style.display = description ? 'block' : 'none';
            mediaPreviewDetails.style.display = hasDetails ? "block" : "none";
        }

        function closeMediaPreview() {
            if (!mediaOverlay) return;
            mediaPreviewVideo.pause();
            mediaPreviewVideo.removeAttribute("src");
            delete mediaPreviewVideo.dataset.previewListingId;
            mediaPreviewImage.removeAttribute("src");
            mediaPreviewImage.style.display = "none";
            mediaPreviewVideo.style.display = "none";
            if (mediaPreviewDetails) {
                mediaPreviewDetails.style.display = "none";
            }
            mediaOverlay.classList.remove("active");
            mediaOverlay.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
            if (mediaWatermark) mediaWatermark.style.display = "none";
        }

        function openMediaPreview(type, src, sourceEl) {
            if (!mediaOverlay || !src) return;
            mediaPreviewImage.style.display = "none";
            mediaPreviewVideo.style.display = "none";
            updateMediaPreviewDetails(sourceEl);
            incrementPreviewImageView(sourceEl);

            if (type === "video") {
                mediaPreviewImage.removeAttribute("src");
                mediaPreviewVideo.src = src;
                mediaPreviewVideo.dataset.previewListingId = sourceEl?.dataset.previewListingId || "";
                mediaPreviewVideo.style.display = "block";
                mediaPreviewVideo.play().catch(() => {});
                if (mediaWatermark) mediaWatermark.style.display = "none";
            } else {
                mediaPreviewVideo.pause();
                mediaPreviewVideo.removeAttribute("src");
                mediaPreviewImage.src = src;
                mediaPreviewImage.style.display = "block";
                if (mediaWatermark) mediaWatermark.style.display = "block";
            }
            mediaOverlay.classList.add("active");
            mediaOverlay.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        }

        function openPreviewFromSource(sourceEl) {
            if (!sourceEl) return;
            const type = sourceEl.dataset.previewType || (sourceEl.tagName.toLowerCase() === "video" ? "video" : "image");
            const src = sourceEl.dataset.previewSrc || sourceEl.getAttribute("src") || "";
            openMediaPreview(type, src, sourceEl);
        }

        const homeMainEl = document.querySelector("main");
        let lastTapTime = 0;
        let lastTapSrc = "";

        homeMainEl?.addEventListener("click", (event) => {
            const sourceEl = event.target.closest(".media-preview-source") || event.target.closest(".video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-text, .product-price-range")?.closest(".card")?.querySelector(".media-preview-source");
            openPreviewFromSource(sourceEl);
        });

        homeMainEl?.addEventListener("touchend", (event) => {
            const sourceEl = event.target.closest(".media-preview-source") || event.target.closest(".video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-text, .product-price-range")?.closest(".card")?.querySelector(".media-preview-source");
            if (!sourceEl) return;
            const sourceKey = sourceEl.dataset.previewSrc || sourceEl.getAttribute("src") || "";
            const now = Date.now();
            const isDoubleTap = now - lastTapTime < 350 && sourceKey !== "" && sourceKey === lastTapSrc;

            lastTapTime = now;
            lastTapSrc = sourceKey;

            if (!isDoubleTap) return;
            event.preventDefault();
            openPreviewFromSource(sourceEl);
            lastTapTime = 0;
            lastTapSrc = "";
        }, { passive: false });

        document.addEventListener("touchstart", () => {
            if (Date.now() - lastTapTime > 600) {
                lastTapTime = 0;
                lastTapSrc = "";
            }
        });

        document.querySelectorAll('video[data-preview-listing-id]').forEach((videoEl) => {
            registerVideoViewTracking(videoEl);
        });
        registerVideoViewTracking(mediaPreviewVideo);

        if (mediaClose) {
            mediaClose.addEventListener("click", closeMediaPreview);
        }

        if (mediaOverlay) {
            mediaOverlay.addEventListener("click", (event) => {
                if (event.target === mediaOverlay) {
                    closeMediaPreview();
                }
            });
        }

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeMediaPreview();
            }
        });

        // Keep only one user-controlled video playing at a time.
        document.addEventListener("play", (event) => {
            const currentVideo = event.target;
            if (!(currentVideo instanceof HTMLVideoElement) || !currentVideo.hasAttribute("controls")) {
                return;
            }

            document.querySelectorAll("video[controls]").forEach((video) => {
                if (video !== currentVideo && !video.paused) {
                    video.pause();
                }
            });
        }, true);

    </script>
    <script src="/assets/listing-preview-modal.js"></script>
    <script src="/assets/listing-preview-gallery.js"></script>
    <script src="/assets/bootstrap.bundle.min.js"></script>
    <script src="/assets/cookie-consent.js"></script>
</body>

</html>
