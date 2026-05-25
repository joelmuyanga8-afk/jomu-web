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

function vendorshopsApparelFilterMap(): array
{
    return [
        'default' => ['title' => 'Apparel & Accessories', 'terms' => ['apparel', 'fashion', 'clothing', 'wear', 'accessories', 'shoes', 'bags', 'jewelry', 'watch', 'sunglasses', 'belt']],
        'hoodies' => ['title' => 'Hoodies & Sweatshirts', 'terms' => ['hoodie', 'hoodies', 'sweatshirt', 'sweatshirts', 'jumper', 'jumpers']],
        'ethnic' => ['title' => 'Ethnic Wear', 'terms' => ['ethnic wear', 'traditional wear', 'gomesi', 'kitenge', 'cultural wear']],
        'casual' => ['title' => 'Casual Wear', 'terms' => ['casual wear', 'casual', 't-shirt', 'tshirts', 'shirt', 'jeans', 'trousers']],
        'formal' => ['title' => 'Formal Wear', 'terms' => ['formal wear', 'formal', 'office wear', 'blazer', 'suit', 'dress shirt']],
        'work' => ['title' => 'Work Wear', 'terms' => ['work wear', 'uniform', 'workwear', 'office wear', 'industrial wear']],
        'tshirts' => ['title' => 'T-shirts & Polos', 'terms' => ['t-shirt', 'tshirts', 'tee', 'polo', 'polos']],
        'shirts' => ['title' => 'Shirts', 'terms' => ['shirt', 'shirts', 'button shirt', 'dress shirt']],
        'jeans' => ['title' => 'Jeans & Trousers', 'terms' => ['jeans', 'trousers', 'pants', 'slacks']],
        'suits' => ['title' => 'Suits & Jackets', 'terms' => ['suit', 'suits', 'jacket', 'jackets', 'blazer', 'coat']],
        'active' => ['title' => 'Active Wear', 'terms' => ['active wear', 'sportswear', 'gym wear', 'track suit', 'leggings']],
        'inner' => ['title' => 'Inner Wear', 'terms' => ['inner wear', 'underwear', 'vest', 'brief', 'boxer', 'lingerie']],
        'dresses' => ['title' => 'Dresses & Gowns', 'terms' => ['dress', 'dresses', 'gown', 'gowns']],
        'tops' => ['title' => 'Tops & Blouses', 'terms' => ['top', 'tops', 'blouse', 'blouses']],
        'skirts' => ['title' => 'Skirts & Pants', 'terms' => ['skirt', 'skirts', 'pant', 'pants', 'trouser', 'trousers']],
        'coords' => ['title' => 'Suits & Co-ords', 'terms' => ['co-ord', 'co ord', 'coords', 'matching set', 'suit']],
        'maternity' => ['title' => 'Maternity Wear', 'terms' => ['maternity', 'pregnancy wear', 'nursing wear']],
        'sleep' => ['title' => 'Lingerie & Sleepwear', 'terms' => ['lingerie', 'sleepwear', 'nightwear', 'pajama', 'night dress']],
        'boys' => ['title' => "Boys' Wear", 'terms' => ['boys wear', 'boy clothing', 'boys clothing', 'boys fashion']],
        'girls' => ['title' => "Girls' Wear", 'terms' => ['girls wear', 'girl clothing', 'girls clothing', 'girls fashion']],
        'baby' => ['title' => 'Baby Clothing', 'terms' => ['baby clothing', 'baby wear', 'infant wear', 'newborn clothes']],
        'schooluniforms' => ['title' => 'School Uniforms', 'terms' => ['school uniform', 'schoolwear', 'uniform']],
        'men' => ['title' => "Men's Shoes", 'terms' => ['mens shoes', 'men shoes', 'male shoes', 'oxford shoes', 'loafer']],
        'women' => ['title' => "Women's Shoes", 'terms' => ['womens shoes', 'women shoes', 'heels', 'ladies shoes', 'flat shoes']],
        'sneakers' => ['title' => 'Sneakers', 'terms' => ['sneaker', 'sneakers', 'trainer', 'canvas shoes']],
        'sandals' => ['title' => 'Sandals & Slippers', 'terms' => ['sandal', 'sandals', 'slipper', 'slippers', 'slides']],
        'kidsfootwear' => ['title' => "Kids' Footwear", 'terms' => ['kids footwear', 'children shoes', 'school shoes', 'baby shoes']],
        'bagpacks' => ['title' => 'Bagpacks', 'terms' => ['backpack', 'bagpack', 'school bag', 'rucksack']],
        'handbags' => ['title' => 'Hand Bags', 'terms' => ['handbag', 'handbags', 'purse', 'ladies bag']],
        'travelbags' => ['title' => 'Travel Bags', 'terms' => ['travel bag', 'duffle', 'duffel', 'luggage', 'trolley bag']],
        'wallets' => ['title' => 'Wallets & Purses', 'terms' => ['wallet', 'wallets', 'purse', 'purses', 'card holder']],
        'bracelets' => ['title' => 'Bracelets & Bangles', 'terms' => ['bracelet', 'bracelets', 'bangle', 'bangles']],
        'necklaces' => ['title' => 'Necklaces & Chains', 'terms' => ['necklace', 'necklaces', 'chain', 'chains']],
        'earrings' => ['title' => 'Earrings', 'terms' => ['earring', 'earrings']],
        'smartwatches' => ['title' => 'Smartwatches & Wristwatches', 'terms' => ['smartwatch', 'wristwatch', 'watch', 'watches']],
        'sunglasses' => ['title' => 'Sunglasses', 'terms' => ['sunglasses', 'sun glasses', 'shades']],
        'optical' => ['title' => 'Optical Glasses', 'terms' => ['optical glasses', 'spectacles', 'prescription glasses']],
        'frames' => ['title' => 'Frames', 'terms' => ['frames', 'eyeglass frame', 'glass frame']],
        'caps' => ['title' => 'Caps & Hats', 'terms' => ['cap', 'caps', 'hat', 'hats', 'bucket hat']],
        'hijabs' => ['title' => 'Hijabs & Scarves', 'terms' => ['hijab', 'hijabs', 'scarf', 'scarves', 'veil']],
        'hair' => ['title' => 'Hair Accessories', 'terms' => ['hair accessories', 'wig', 'wigs', 'hair band', 'hair clip']],
        'leatherbelts' => ['title' => 'Leather Belts', 'terms' => ['leather belt', 'belts', 'belt']],
        'fashionbelts' => ['title' => 'Fashion Belts', 'terms' => ['fashion belt', 'belts', 'waist belt']],
        'gloves' => ['title' => 'Gloves', 'terms' => ['glove', 'gloves']],
        'socks' => ['title' => 'Socks & Tights', 'terms' => ['socks', 'sock', 'tight', 'tights', 'stockings']],
        'ties' => ['title' => 'Ties & Bowties', 'terms' => ['tie', 'ties', 'bowtie', 'bow tie']],
        'fashionmasks' => ['title' => 'Fashion Masks', 'terms' => ['fashion mask', 'mask', 'masks']],
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

$filters = vendorshopsApparelFilterMap();
$filterKey = strtolower(trim((string) ($_GET['filter'] ?? 'default')));
$filter = $filters[$filterKey] ?? $filters['default'];
$searchQuery = trim((string) ($_GET['search'] ?? ''));

$params = [];
$types = '';
$categoryClause = buildCategoryClauses(['apparel'], $params, $types);
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
