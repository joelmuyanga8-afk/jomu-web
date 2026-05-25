<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';

header('Content-Type: text/html; charset=UTF-8');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function resolveVendorProfilePicPath(string $path): string
{
    $normalized = str_replace('\\', '/', trim($path));
    if ($normalized === '') {
        return '/assets/images/profile.png';
    }
    if (preg_match('/^(https?:)?\/\//i', $normalized) || strpos($normalized, 'data:') === 0 || strpos($normalized, 'blob:') === 0) {
        return $normalized;
    }
    if (strpos($normalized, '/') === 0) {
        return $normalized;
    }
    if (strpos($normalized, 'assets/') === 0 || strpos($normalized, 'php/') === 0) {
        return '/' . ltrim($normalized, '/');
    }
    if (strpos($normalized, 'uploads/') === 0) {
        return '/php/' . ltrim($normalized, '/');
    }
    return '/php/' . ltrim($normalized, '/');
}

function vendorshopsShoesFilterMap(): array
{
    return [
        'default' => ['title' => 'Shoes', 'terms' => ['shoe', 'shoes', 'footwear', 'sneaker', 'loafer', 'heel', 'boot', 'sandal', 'slide', 'flip flop', 'crocs', 'canvas', 'gumboot']],
        'menssneakers' => ['title' => "Men's Sneakers", 'terms' => ['mens sneakers', 'men sneakers', 'male sneakers', 'sneaker']],
        'loafers' => ['title' => 'Loafers & Slip-ons', 'terms' => ['loafer', 'loafers', 'slip-on', 'slip ons', 'slipons', 'moccasin']],
        'formalshoes' => ['title' => 'Official/Formal Shoes', 'terms' => ['formal shoes', 'official shoes', 'oxford', 'derby', 'dress shoes', 'office shoes']],
        'mensboots' => ['title' => "Men's Boots", 'terms' => ['mens boots', 'men boots', 'boot', 'boots']],
        'menssandals' => ['title' => "Men's Sandals", 'terms' => ['mens sandals', 'men sandals', 'sandal', 'sandals']],
        'mensslides' => ['title' => "Men's Slides", 'terms' => ['mens slides', 'men slides', 'slides', 'slide sandals']],
        'womenssandals' => ['title' => "Women's Sandals", 'terms' => ['womens sandals', 'women sandals', 'ladies sandals', 'sandal', 'sandals']],
        'heels' => ['title' => 'Heels', 'terms' => ['heel', 'heels', 'high heels', 'block heels', 'stiletto']],
        'flats' => ['title' => 'Flats', 'terms' => ['flat shoes', 'flats', 'ballet flats']],
        'womenssneakers' => ['title' => "Women's Sneakers", 'terms' => ['womens sneakers', 'women sneakers', 'ladies sneakers', 'sneaker']],
        'womensboots' => ['title' => "Women's Boots", 'terms' => ['womens boots', 'women boots', 'ladies boots', 'boot', 'boots']],
        'womensslides' => ['title' => "Women's Slides & Flip-flops", 'terms' => ['womens slides', 'women slides', 'flip flop', 'flip-flops', 'slides']],
        'bridalshoes' => ['title' => 'Bridal / Occasion Shoes', 'terms' => ['bridal shoes', 'wedding shoes', 'occasion shoes', 'party heels']],
        'openshoes' => ['title' => 'Open Shoes', 'terms' => ['open shoes', 'open sandals', 'open heels']],
        'schoolshoes' => ['title' => 'School Shoes', 'terms' => ['school shoes', 'uniform shoes', 'black shoes']],
        'kidssneakers' => ['title' => "Kids' Sneakers", 'terms' => ['kids sneakers', 'children sneakers', 'durable sneakers', 'school sneakers']],
        'kidssandals' => ['title' => "Kids' Sandals", 'terms' => ['kids sandals', 'children sandals', 'sandals']],
        'kidsslipons' => ['title' => "Kids' Slip-ons", 'terms' => ['kids slip-ons', 'children slip-ons', 'slip-ons']],
        'sportsshoes' => ['title' => 'Sports Shoes', 'terms' => ['sports shoes', 'running shoes', 'training shoes', 'athletic shoes']],
        'gumboots' => ['title' => 'Gumboots', 'terms' => ['gumboot', 'gumboots', 'rain boots', 'rain shoe']],
        'lightupshoes' => ['title' => 'Light-up / Cartoon Shoes', 'terms' => ['light-up shoes', 'cartoon shoes', 'kids character shoes']],
        'unisexsneakers' => ['title' => 'Unisex Sneakers', 'terms' => ['unisex sneakers', 'sneakers', 'casual sneakers']],
        'unisexslides' => ['title' => 'Unisex Slides & Flip-flops', 'terms' => ['slides', 'flip flop', 'flip-flops', 'rubber slides']],
        'crocs' => ['title' => 'Crocs / Rubber Clogs', 'terms' => ['crocs', 'rubber clogs', 'clogs']],
        'canvasshoes' => ['title' => 'Canvas Shoes', 'terms' => ['canvas shoes', 'canvas sneaker', 'canvas']],
        'unisexgumboots' => ['title' => 'Unisex Gumboots', 'terms' => ['gumboot', 'gumboots', 'rain boots']],
        'outdoorshoes' => ['title' => 'Outdoor / Work Shoes', 'terms' => ['outdoor shoes', 'work shoes', 'safety shoes', 'hiking shoes']],
    ];
}

function usersTableHasColumn(mysqli $conn, string $columnName): bool
{
    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    if ($result instanceof mysqli_result) {
        $hasColumn = $result->num_rows > 0;
        $result->free();
        return $hasColumn;
    }

    return false;
}

function buildLikeClauses(array $terms, array &$params, string &$types): string
{
    $clauses = [];
    foreach ($terms as $term) {
        $clauses[] = "LOWER(CONCAT_WS(' ', COALESCE(l.stockname, ''), COALESCE(l.category, ''), COALESCE(l.description, ''))) LIKE ?";
        $params[] = '%' . strtolower($term) . '%';
        $types .= 's';
    }

    return implode(' OR ', $clauses);
}

function buildCategoryClauses(array $categories, array &$params, string &$types): string
{
    $clauses = [];
    foreach ($categories as $category) {
        $normalized = strtolower(trim((string) $category));
        if ($normalized === '') {
            continue;
        }
        $clauses[] = "LOWER(TRIM(COALESCE(l.category, ''))) = ?";
        $params[] = $normalized;
        $types .= 's';
    }

    return $clauses === [] ? '1=1' : '(' . implode(' OR ', $clauses) . ')';
}

function normalizeSearchQuery(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';
    return trim($value);
}

function buildSearchClauses(string $searchQuery, array &$params, string &$types): string
{
    $normalized = normalizeSearchQuery($searchQuery);
    if ($normalized === '') {
        return '';
    }

    $tokens = array_values(array_filter(explode(' ', $normalized), static fn(string $token): bool => $token !== ''));
    if ($tokens === []) {
        return '';
    }

    $parts = [];
    foreach ($tokens as $token) {
        $parts[] = "LOWER(CONCAT_WS(' ', COALESCE(l.stockname, ''), COALESCE(l.category, ''), COALESCE(l.description, ''))) LIKE ?";
        $params[] = '%' . $token . '%';
        $types .= 's';
    }

    return implode(' AND ', $parts);
}

function renderCard(array $business, int $currentUserId): string
{
    $businessName = trim((string) ($business['businessname'] ?? ''));
    $businessName = $businessName !== '' ? $businessName : 'Business';
    $businessBio = trim((string) ($business['bio'] ?? ''));
    if ($businessBio === '') {
        $businessBio = 'This business has not added a bio yet.';
    }

    $profilePic = resolveVendorProfilePicPath((string) ($business['profilepic'] ?? ''));

    $businessUserId = (int) ($business['id'] ?? 0);
    $profileUrl = ($currentUserId > 0 && $businessUserId === $currentUserId)
        ? 'php/profile.php'
        : ('php/visitor_profile.php?user_id=' . $businessUserId);

    return '
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card-vendorshops">
                <div class="front-page-vendorshops">
                    <div class="card-img-vendorshops">
                        <img src="' . h($profilePic) . '" class="img-fluid card-img-image-vendorshops" alt="' . h($businessName) . '" onerror="this.onerror=null;this.src=\'/assets/images/profile.png\';">
                    </div>
                    <div class="card-info-vendorshops">
                        <h5 class="card-title-vendorshops px-1 mb-0">' . h($businessName) . '</h5>
                    </div>
                </div>
                <div class="back-page-vendorshops bg-dark">
                    <div class="card-content-vendorshops">
                        <h5 style="color:white; -webkit-text-stroke: 0.5px rgb(241,90,36);">' . h($businessName) . '</h5>
                        <hr>
                        <p class="card-description-vendorshops mt-4">' . h($businessBio) . '</p>
                        <button class="card-button-vendorshops mt-3" type="button" onclick="window.location.href=\'' . h($profileUrl) . '\'">Visit Profile</button>
                    </div>
                </div>
            </div>
        </div>';
}

$filters = vendorshopsShoesFilterMap();
$filterKey = strtolower(trim((string) ($_GET['filter'] ?? 'default')));
$filter = $filters[$filterKey] ?? $filters['default'];
$searchQuery = trim((string) ($_GET['search'] ?? ''));

$params = [];
$types = '';
$categoryClause = buildCategoryClauses(['shoes'], $params, $types);
$searchClause = buildSearchClauses($searchQuery, $params, $types);
$whereClause = $categoryClause;
if (listingTableHasColumn($conn, 'moderation_status')) {
    $whereClause .= " AND COALESCE(l.moderation_status, 'visible') <> 'hidden'";
}
if (listingTableHasColumn($conn, 'admin_purged_at')) {
    $whereClause .= ' AND l.admin_purged_at IS NULL';
}
if ($filterKey !== 'default') {
    $filterTermsClause = buildLikeClauses($filter['terms'], $params, $types);
    $whereClause .= " AND ({$filterTermsClause})";
}
if ($searchClause !== '') {
    $whereClause .= " AND ({$searchClause})";
}

$bioSelect = usersTableHasColumn($conn, 'bio') ? "COALESCE(u.bio, '')" : "''";
$bioGroupBy = usersTableHasColumn($conn, 'bio') ? ', u.bio' : '';

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

$sql = "
    SELECT
        u.id,
        u.businessname,
        u.profilepic,
        {$bioSelect} AS bio,
        COUNT(l.listing_id) AS matched_listings,
        MAX(l.listing_id) AS latest_listing_id
    FROM listings l
    INNER JOIN users u ON u.id = l.user_id
    WHERE {$whereClause}
    GROUP BY u.id, u.businessname, u.profilepic{$bioGroupBy}
    ORDER BY matched_listings DESC, latest_listing_id DESC
    LIMIT 24
";

$businesses = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $businesses[] = $row;
            }
        }
    }
    $stmt->close();
}

echo '<h3 style="color: white; font-weight: 800; -webkit-text-stroke: 1px rgb(0,0,255); text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5);">' . h($filter['title']) . '</h3>';
echo '<div class="row g-2">';

if ($businesses === []) {
    echo '<div class="col-12"><div class="bg-white rounded p-3"><p class="mb-0">No businesses with matching listings are available right now.</p></div></div>';
} else {
    foreach ($businesses as $business) {
        echo renderCard($business, $currentUserId);
    }
}

echo '</div>';
