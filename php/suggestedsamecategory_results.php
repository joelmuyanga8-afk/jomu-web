<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';
require __DIR__ . '/partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

header('Content-Type: text/html; charset=UTF-8');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

function normalizeCategory(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return $value ?? '';
}

function getUserTopCategories(mysqli $conn, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT category, COUNT(*) AS category_count
         FROM listings
         WHERE user_id = ?
           AND COALESCE(moderation_status, 'visible') <> 'hidden'
           AND TRIM(COALESCE(category, '')) <> ''
         GROUP BY category
         ORDER BY category_count DESC, MAX(listing_id) DESC
         LIMIT 5"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];

    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $category = normalizeCategory((string) ($row['category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = true;
            }
        }
        $result->free();
    }

    $stmt->close();

    return array_keys($categories);
}

function renderCard(array $business): string
{
    $businessName = trim((string) ($business['businessname'] ?? ''));
    $businessName = $businessName !== '' ? $businessName : 'Business';
    $businessBio = trim((string) ($business['bio'] ?? ''));
    if ($businessBio === '') {
        $businessBio = 'This business has not added a bio yet.';
    }

    $profilePic = trim((string) ($business['profilepic'] ?? ''));
    if ($profilePic === '') {
        $profilePic = '/assets/images/profile.png';
    } else {
        $profilePic = getMediaPath($profilePic, '/php/');
    }

    $profileUrl = '/visitor-profile?user_id=' . (int) ($business['id'] ?? 0);

    return '
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card-vendorshops">
                <div class="front-page-vendorshops">
                    <div class="card-img-vendorshops">
                        <img src="' . h($profilePic) . '" class="img-fluid card-img-image-vendorshops" alt="' . h($businessName) . '">
                    </div>
                    <div class="card-info-vendorshops">
                        <h5 class="card-title-vendorshops px-1 mb-0">' . h($businessName) . '</h5>
                        <!-- <p class="card-subtitle-vendorshops">Followers</p> -->
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

$currentUserId = getCurrentUserId($conn);
$topCategories = getUserTopCategories($conn, $currentUserId);

echo '<div class="row g-2">';

if ($currentUserId <= 0 || $topCategories === []) {
    echo '<div class="col-12"><div class="bg-white rounded p-3"><p class="mb-0">No same-category businesses are available right now.</p></div></div>';
    echo '</div>';
    exit;
}

$bioSelect = usersTableHasColumn($conn, 'bio') ? "COALESCE(u.bio, '')" : "''";
$bioGroupBy = usersTableHasColumn($conn, 'bio') ? ', u.bio' : '';

$placeholders = implode(',', array_fill(0, count($topCategories), '?'));
$types = str_repeat('s', count($topCategories)) . 'i';
$params = array_merge($topCategories, [$currentUserId]);

$sql = "
    SELECT
        u.id,
        u.businessname,
        u.profilepic,
        {$bioSelect} AS bio,
        COUNT(l.listing_id) AS matched_category_posts,
        MAX(l.listing_id) AS latest_listing_id
    FROM listings l
    INNER JOIN users u ON u.id = l.user_id
    WHERE LOWER(TRIM(COALESCE(l.category, ''))) IN ({$placeholders})
      AND COALESCE(l.moderation_status, 'visible') <> 'hidden'
      AND u.id <> ?
    GROUP BY u.id, u.businessname, u.profilepic{$bioGroupBy}
    ORDER BY matched_category_posts DESC, latest_listing_id DESC
    LIMIT 24
";

$businesses = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $businesses[] = $row;
            }
            $result->free();
        }
    }
    $stmt->close();
}

if ($businesses === []) {
    echo '<div class="col-12"><div class="bg-white rounded p-3"><p class="mb-0">No same-category businesses are available right now.</p></div></div>';
} else {
    foreach ($businesses as $business) {
        echo renderCard($business);
    }
}

echo '</div>';
