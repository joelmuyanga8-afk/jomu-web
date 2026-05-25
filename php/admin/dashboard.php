<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/helpers.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$admin = jomu_require_admin($conn);
jomu_purge_expired_hidden_listings($conn, (int) ($admin['admin_id'] ?? 0));
$csrf = jomu_admin_csrf_token();

$message = trim((string) ($_GET['message'] ?? ''));
$page = strtolower(trim((string) ($_GET['page'] ?? 'overview')));
$allowedPages = ['overview', 'listings', 'users', 'messages', 'bulk_orders', 'links', 'ads'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'overview';
}

$listingWindow = strtolower(trim((string) ($_GET['listing_window'] ?? 'all')));
if (!in_array($listingWindow, ['all', 'hidden'], true)) {
    $listingWindow = 'all';
}
$userWindow = strtolower(trim((string) ($_GET['user_window'] ?? 'all')));
if (!in_array($userWindow, ['all', 'engaging', 'inactive', 'terminated'], true)) {
    $userWindow = 'all';
}
$bulkWindow = strtolower(trim((string) ($_GET['bulk_window'] ?? 'all')));
if (!in_array($bulkWindow, ['all', 'hidden'], true)) {
    $bulkWindow = 'all';
}

$listingSearch = trim((string) ($_GET['listing_search'] ?? ''));
$userSearch = trim((string) ($_GET['user_search'] ?? ''));
$messageSearch = trim((string) ($_GET['message_search'] ?? ''));
$bulkSearch = trim((string) ($_GET['bulk_search'] ?? ''));
$adsSearch = trim((string) ($_GET['ads_search'] ?? ''));
$returnQuery = $_GET;
unset($returnQuery['message']);
$currentAdminUrl = 'dashboard.php' . ($returnQuery ? '?' . http_build_query($returnQuery) : '');

function admin_page_url(array $params = []): string
{
    $query = array_merge($_GET, $params);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    return 'dashboard.php' . ($query ? '?' . http_build_query($query) : '');
}


function admin_listing_type(array $listing): string
{
    $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
    if ($listingType === 'product' || $listingType === 'service') {
        return $listingType;
    }
    return str_contains(strtolower((string) ($listing['category'] ?? '')), 'service') ? 'service' : 'product';
}

function admin_listing_price(array $listing): string
{
    $type = admin_listing_type($listing);
    $priceFrom = trim((string) ($listing['price_from'] ?? ''));
    $priceTo = trim((string) ($listing['price_to'] ?? ''));
    if ($type === 'product' && $priceFrom !== '' && $priceTo !== '') {
        return formatProductPriceRange($priceFrom, $priceTo);
    }
    return trim((string) ($listing['price'] ?? ''));
}

function admin_inactive_remaining_text(?string $inactiveUntil): string
{
    $inactiveUntil = trim((string) $inactiveUntil);
    if ($inactiveUntil === '') {
        return '';
    }
    $untilTimestamp = strtotime($inactiveUntil);
    if ($untilTimestamp === false) {
        return $inactiveUntil;
    }
    $remainingSeconds = $untilTimestamp - time();
    if ($remainingSeconds <= 0) {
        return 'Due for activation';
    }
    $days = (int) ceil($remainingSeconds / 86400);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' remaining';
}

function admin_inactive_since_text(?string $inactiveSince): string
{
    $inactiveSince = trim((string) $inactiveSince);
    if ($inactiveSince === '') {
        return '';
    }
    $sinceTimestamp = strtotime($inactiveSince);
    if ($sinceTimestamp === false) {
        return $inactiveSince;
    }
    $diffSeconds = time() - $sinceTimestamp;
    $relative = '';
    if ($diffSeconds < 0) {
        $relative = 'starts in ' . ceil(abs($diffSeconds) / 86400) . ' day' . (abs($diffSeconds) < 86400 ? '' : 's');
    } elseif ($diffSeconds < 86400) {
        $relative = 'Today';
    } else {
        $days = (int) floor($diffSeconds / 86400);
        $relative = $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    return date('Y-m-d H:i', $sinceTimestamp) . ' • ' . $relative;
}

$listingWhere = ['l.admin_purged_at IS NULL'];
$listingTypes = '';
$listingParams = [];
if ($listingWindow === 'hidden') {
    $listingWhere[] = "COALESCE(l.moderation_status, 'visible') = 'hidden'";
} else {
    $listingWhere[] = "COALESCE(l.moderation_status, 'visible') <> 'hidden'";
    $listingWhere[] = 'l.admin_reviewed_at IS NULL';
}
if ($listingSearch !== '') {
    $listingWhere[] = "LOWER(CONCAT_WS(' ', COALESCE(l.stockname, ''), COALESCE(l.category, ''), COALESCE(l.description, ''), COALESCE(l.region, ''), COALESCE(l.city_town, ''), COALESCE(u.businessname, ''))) LIKE ?";
    $listingTypes .= 's';
    $listingParams[] = '%' . strtolower($listingSearch) . '%';
}
$listingWhereSql = $listingWhere ? 'WHERE ' . implode(' AND ', $listingWhere) : '';

$listings = [];
$listingStmt = $conn->prepare(
    "SELECT l.*, u.businessname, u.emailormobilenumber, u.profilepic AS seller_profilepic
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     {$listingWhereSql}
     ORDER BY l.created_at ASC, l.listing_id ASC
     LIMIT 160"
);
if ($listingStmt) {
    if ($listingTypes !== '') {
        $listingStmt->bind_param($listingTypes, ...$listingParams);
    }
    $listingStmt->execute();
    $res = $listingStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $listings[] = $row;
    }
    $listingStmt->close();
}

function admin_listing_tab_count(mysqli $conn, string $window, string $search): int
{
    $where = ['l.admin_purged_at IS NULL'];
    $types = '';
    $params = [];
    if ($window === 'hidden') {
        $where[] = "COALESCE(l.moderation_status, 'visible') = 'hidden'";
    } else {
        $where[] = "COALESCE(l.moderation_status, 'visible') <> 'hidden'";
        $where[] = 'l.admin_reviewed_at IS NULL';
    }
    if ($search !== '') {
        $where[] = "LOWER(CONCAT_WS(' ', COALESCE(l.stockname, ''), COALESCE(l.category, ''), COALESCE(l.description, ''), COALESCE(l.region, ''), COALESCE(l.city_town, ''), COALESCE(u.businessname, ''))) LIKE ?";
        $types .= 's';
        $params[] = '%' . strtolower($search) . '%';
    }
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    $sql = "SELECT COUNT(*) AS total FROM listings l INNER JOIN users u ON u.id = l.user_id {$whereSql}";
    if ($types !== '') {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($row['total'] ?? 0);
    }
    $res = $conn->query($sql);
    if (!$res) {
        return 0;
    }
    $row = $res->fetch_assoc();
    return (int) ($row['total'] ?? 0);
}

$pendingListingsCount = admin_listing_tab_count($conn, 'all', $listingSearch);
$hiddenListingsTotal = admin_listing_tab_count($conn, 'hidden', $listingSearch);
$listingSectionCount = count($listings);

$userWhere = ["emailormobilenumber <> 'system@jomu.local'", "account_status <> 'terminated'"];
$userTypes = '';
$userParams = [];
if ($userWindow === 'inactive') {
    $userWhere[] = "account_status = 'inactive'";
}
if ($userSearch !== '') {
    $userWhere[] = "LOWER(CONCAT_WS(' ', COALESCE(businessname, ''), COALESCE(emailormobilenumber, ''))) LIKE ?";
    $userTypes .= 's';
    $userParams[] = '%' . strtolower($userSearch) . '%';
}
$userWhereSql = 'WHERE ' . implode(' AND ', $userWhere);

$users = [];
if ($userWindow !== 'terminated') {
    if ($userWindow === 'engaging') {
        $userStmt = $conn->prepare(
            "SELECT u.id, u.businessname, u.emailormobilenumber, u.profilepic, u.account_status, u.inactive_since, u.inactive_until, u.status_reason,
                    COALESCE(SUM(l.views), 0) AS engagement_score
             FROM users u
             LEFT JOIN listings l ON l.user_id = u.id AND l.admin_purged_at IS NULL
             {$userWhereSql}
             GROUP BY u.id, u.businessname, u.emailormobilenumber, u.profilepic, u.account_status, u.inactive_since, u.inactive_until, u.status_reason
             ORDER BY engagement_score DESC, u.id DESC
             LIMIT 160"
        );
    } else {
        $userStmt = $conn->prepare(
            "SELECT id, businessname, emailormobilenumber, profilepic, account_status, inactive_since, inactive_until, status_reason
             FROM users
             {$userWhereSql}
             ORDER BY id DESC
             LIMIT 160"
        );
    }
    if ($userStmt) {
        if ($userTypes !== '') {
            $userStmt->bind_param($userTypes, ...$userParams);
        }
        $userStmt->execute();
        $res = $userStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
        $userStmt->close();
    }
}

$totalUsers = 0;
$inactiveUsersTotal = 0;
$countUserRes = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN account_status = 'inactive' THEN 1 ELSE 0 END) AS inactive_total FROM users WHERE emailormobilenumber <> 'system@jomu.local' AND account_status <> 'terminated'");
if ($countUserRes) {
    $countUserRow = $countUserRes->fetch_assoc();
    $totalUsers = (int) ($countUserRow['total'] ?? 0);
    $inactiveUsersTotal = (int) ($countUserRow['inactive_total'] ?? 0);
}

$terminatedUsers = [];
if ($userWindow === 'terminated' || $page === 'overview') {
    $terminatedWhere = ["account_status = 'terminated'", "emailormobilenumber <> 'system@jomu.local'"];
    $terminatedTypes = '';
    $terminatedParams = [];
    if ($userSearch !== '') {
        $terminatedWhere[] = "LOWER(CONCAT_WS(' ', COALESCE(businessname, ''), COALESCE(emailormobilenumber, ''), COALESCE(status_reason, ''))) LIKE ?";
        $terminatedTypes .= 's';
        $terminatedParams[] = '%' . strtolower($userSearch) . '%';
    }
    $terminatedWhereSql = 'WHERE ' . implode(' AND ', $terminatedWhere);
    $terminatedStmt = $conn->prepare(
        "SELECT id AS user_id, businessname, emailormobilenumber, status_reason AS reason, terminated_at AS terminated_at
         FROM users
         {$terminatedWhereSql}
         ORDER BY terminated_at DESC, id DESC
         LIMIT 160"
    );
    if ($terminatedStmt) {
        if ($terminatedTypes !== '') {
            $terminatedStmt->bind_param($terminatedTypes, ...$terminatedParams);
        }
        $terminatedStmt->execute();
        $res = $terminatedStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $terminatedUsers[] = $row;
        }
        $terminatedStmt->close();
    }
}
$terminatedUsersTotal = 0;
$terminatedCountRes = $conn->query("SELECT COUNT(*) AS total FROM users WHERE account_status = 'terminated' AND emailormobilenumber <> 'system@jomu.local'");
if ($terminatedCountRes) {
    $terminatedUsersTotal = (int) (($terminatedCountRes->fetch_assoc())['total'] ?? 0);
}

$messages = [];
$systemUserId = jomu_system_user_id($conn);
$messageBatchWhere = [];
$messageWhere = ["bm.sender_user_id = ?", "bm.is_system_message = 1", "bm.admin_message_batch_id IS NULL"];
$messageTypes = '';
$messageParams = [];
if ($messageSearch !== '') {
    $messageBatchWhere[] = "LOWER(CONCAT_WS(' ', 'to all users', COALESCE(amb.message_text, ''))) LIKE ?";
    $messageWhere[] = "LOWER(CONCAT_WS(' ', COALESCE(u.businessname, ''), COALESCE(u.emailormobilenumber, ''), COALESCE(bm.message_type, ''), COALESCE(bm.message_text, ''))) LIKE ?";
    $messageTypes .= 's';
    $messageParams[] = '%' . strtolower($messageSearch) . '%';
}
$messageWhereSql = implode(' AND ', $messageWhere);
$messageBatchWhereSql = $messageBatchWhere ? 'WHERE ' . implode(' AND ', $messageBatchWhere) : '';
$messageStmt = $conn->prepare(
    "SELECT *
     FROM (
        SELECT
            CONCAT('batch-', amb.batch_id) AS message_key,
            amb.batch_id AS message_id,
            0 AS receiver_user_id,
            'text' AS message_type,
            amb.message_text,
            amb.created_at,
            'To All Users' AS businessname,
            '' AS emailormobilenumber,
            '' AS profilepic,
            'batch' AS row_type
        FROM admin_message_batches amb
        {$messageBatchWhereSql}
        UNION ALL
        SELECT
            CONCAT('message-', bm.message_id) AS message_key,
            bm.message_id,
            bm.receiver_user_id,
            bm.message_type,
            bm.message_text,
            bm.created_at,
            u.businessname,
            u.emailormobilenumber,
            u.profilepic,
            'single' AS row_type
        FROM business_messages bm
        INNER JOIN users u ON u.id = bm.receiver_user_id
        WHERE {$messageWhereSql}
     ) sent_messages
     ORDER BY created_at DESC, message_id DESC
     LIMIT 160"
);
if ($messageStmt) {
    $messageTypes .= 'i';
    $messageParams[] = $systemUserId;
    if ($messageSearch !== '') {
        $messageTypes .= 's';
        $messageParams[] = '%' . strtolower($messageSearch) . '%';
    }
    $messageStmt->bind_param($messageTypes, ...$messageParams);
    $messageStmt->execute();
    $res = $messageStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $messages[] = $row;
    }
    $messageStmt->close();
}

$bulkWhere = ['p.admin_purged_at IS NULL'];
$bulkTypes = '';
$bulkParams = [];
if ($bulkSearch !== '') {
    if ($bulkWindow === 'hidden') {
        $bulkWhere[] = "COALESCE(p.moderation_status, 'visible') = 'hidden'";
    }
    $bulkWhere[] = "LOWER(CONCAT_WS(' ', COALESCE(p.business_name, ''), COALESCE(p.content, ''), COALESCE(u.businessname, ''), COALESCE(u.emailormobilenumber, ''))) LIKE ?";
    $bulkTypes .= 's';
    $bulkParams[] = '%' . strtolower($bulkSearch) . '%';
} else {
    if ($bulkWindow === 'hidden') {
        $bulkWhere[] = "COALESCE(p.moderation_status, 'visible') = 'hidden'";
    } else {
        $bulkWhere[] = "COALESCE(p.moderation_status, 'visible') <> 'hidden'";
        $bulkWhere[] = 'p.admin_reviewed_at IS NULL';
    }
}
$bulkWhereSql = 'WHERE ' . implode(' AND ', $bulkWhere);
$bulkPosts = [];
$bulkStmt = $conn->prepare(
    "SELECT p.*, u.businessname AS user_businessname, u.emailormobilenumber, u.profilepic AS user_profilepic
     FROM bulk_order_posts p
     LEFT JOIN users u ON u.id = p.user_id
     {$bulkWhereSql}
     ORDER BY p.created_at DESC, p.id DESC
     LIMIT 160"
);
if ($bulkStmt) {
    if ($bulkTypes !== '') {
        $bulkStmt->bind_param($bulkTypes, ...$bulkParams);
    }
    $bulkStmt->execute();
    $res = $bulkStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $bulkPosts[] = $row;
    }
    $bulkStmt->close();
}
$pendingBulkPostsCount = 0;
$hiddenBulkPostsTotal = 0;
$bulkCountRes = $conn->query(
    "SELECT
        SUM(CASE WHEN admin_purged_at IS NULL AND admin_reviewed_at IS NULL AND COALESCE(moderation_status, 'visible') <> 'hidden' THEN 1 ELSE 0 END) AS pending_total,
        SUM(CASE WHEN admin_purged_at IS NULL AND COALESCE(moderation_status, 'visible') = 'hidden' THEN 1 ELSE 0 END) AS hidden_total
     FROM bulk_order_posts"
);
if ($bulkCountRes) {
    $bulkCountRow = $bulkCountRes->fetch_assoc();
    $pendingBulkPostsCount = (int) ($bulkCountRow['pending_total'] ?? 0);
    $hiddenBulkPostsTotal = (int) ($bulkCountRow['hidden_total'] ?? 0);
}

$siteLinks = [];
$linkRes = $conn->query("SELECT link_key, label, url, updated_at FROM site_links ORDER BY FIELD(link_key, 'app', 'facebook', 'instagram', 'tiktok', 'x', 'support_email', 'privacy_email', 'support_phone', 'support_whatsapp')");
if ($linkRes) {
    while ($row = $linkRes->fetch_assoc()) {
        $siteLinks[] = $row;
    }
}

$adminAdAssetsAll = require __DIR__ . '/../partials/admin_ad_assets.php';
$adminAdAssets = $adminAdAssetsAll;
if ($page === 'ads' && $adsSearch !== '') {
    $needle = strtolower($adsSearch);
    $adminAdAssets = array_values(array_filter(
        $adminAdAssetsAll,
        static function (array $asset) use ($needle): bool {
            $chunks = [
                strtolower((string) ($asset['label'] ?? '')),
                strtolower((string) ($asset['path'] ?? '')),
                strtolower((string) pathinfo((string) ($asset['path'] ?? ''), PATHINFO_BASENAME)),
            ];
            foreach ($asset['pages'] ?? [] as $pageLine) {
                $chunks[] = strtolower((string) $pageLine);
            }
            if (!empty($asset['page'])) {
                $chunks[] = strtolower((string) $asset['page']);
            }
            $haystack = implode("\n", $chunks);
            return strpos($haystack, $needle) !== false;
        }
    ));
}

function render_listing_cards(array $listings, string $csrf): void
{
    if (!$listings) {
        echo '<div class="admin-empty">No listings found.</div>';
        return;
    }

    echo '<div class="admin-listing-grid">';
    foreach ($listings as $listing) {
        $listingId = (int) ($listing['listing_id'] ?? 0);
        $isHidden = strtolower((string) ($listing['moderation_status'] ?? 'visible')) === 'hidden';
        $media = (string) ($listing['media'] ?? '');
        $mediaType = getMediaType($media);
        $galleryImages = getListingGalleryImages($GLOBALS['conn'], $listingId);
        $details = [
            'id' => $listingId,
            'title' => trim((string) ($listing['stockname'] ?? 'Listing')),
            'description' => trim((string) ($listing['description'] ?? '')),
            'category' => trim((string) ($listing['category'] ?? '')),
            'region' => trim((string) ($listing['region'] ?? '')),
            'town' => trim((string) ($listing['city_town'] ?? '')),
            'price' => admin_listing_price($listing),
            'type' => ucfirst(admin_listing_type($listing)),
            'hashtags' => trim((string) ($listing['hashtags'] ?? '')),
            'seller' => trim((string) ($listing['businessname'] ?? 'Business')),
            'seller_login' => trim((string) ($listing['emailormobilenumber'] ?? '')),
            'seller_profilepic' => jomu_admin_media_public_url((string) ($listing['seller_profilepic'] ?? '')),
            'seller_profile_url' => '../visitor_profile.php?user_id=' . (int) ($listing['user_id'] ?? 0),
            'created_at' => trim((string) ($listing['created_at'] ?? '')),
            'status' => $isHidden ? 'Hidden' : 'Visible',
            'is_hidden' => $isHidden,
            'hidden_reason' => trim((string) ($listing['hidden_reason'] ?? '')),
            'media_type' => $mediaType,
            'media_src' => jomu_admin_media_public_url($media),
            'gallery' => array_values(array_unique(array_merge([jomu_admin_media_public_url($media)], array_map('jomu_admin_media_public_url', $galleryImages)))),
        ];
        ?>
        <article class="admin-listing-card<?php echo $isHidden ? ' is-hidden' : ''; ?>">
            <button type="button" class="admin-listing-open" data-listing-details="<?php echo htmlspecialchars(json_encode($details), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <span class="admin-media-frame">
                    <?php if ($mediaType === 'video'): ?>
                        <video muted preload="metadata"><source src="<?php echo htmlspecialchars(jomu_admin_media_public_url($media)); ?>"></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars(jomu_admin_media_public_url($media)); ?>" alt="">
                    <?php endif; ?>
                    <span class="admin-status-pill"><?php echo $isHidden ? 'Hidden' : 'Visible'; ?></span>
                </span>
                <span class="admin-listing-copy">
                    <strong><?php echo htmlspecialchars($details['title']); ?></strong>
                    <span><?php echo htmlspecialchars($details['category'] ?: 'Uncategorized'); ?></span>
                    <small><?php echo htmlspecialchars($details['seller']); ?></small>
                    <small>Posted <?php echo htmlspecialchars($details['created_at']); ?></small>
                </span>
            </button>
            <form method="post" action="listing_action.php" class="admin-ajax-form" data-admin-action-kind="listing">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($GLOBALS['currentAdminUrl']); ?>">
                <?php if ($isHidden): ?>
                    <button class="btn btn-success btn-sm" name="action" value="unhide" type="submit">Unhide</button>
                    <button class="btn btn-outline-secondary btn-sm" name="action" value="purge" type="submit">Delete</button>
                <?php else: ?>
                    <button class="btn btn-success btn-sm" name="action" value="approve" type="submit">Approve</button>
                    <button class="btn btn-danger btn-sm" name="action" value="hide" type="submit">Hide</button>
                <?php endif; ?>
            </form>
        </article>
        <?php
    }
    echo '</div>';
}

function render_users_table(array $users, array $terminatedUsers, string $userWindow, string $csrf): void
{
    if ($userWindow === 'terminated') {
        if (!$terminatedUsers) {
            echo '<div class="admin-empty">No terminated users found.</div>';
            return;
        }
        echo '<div class="admin-table-wrap admin-table-card-sm admin-table-users-stack admin-table-users-terminated"><table class="table table-sm align-middle"><thead><tr><th>Business</th><th>Email / Phone</th><th>Reason</th><th>Terminated</th></tr></thead><tbody>';
        foreach ($terminatedUsers as $user) {
            echo '<tr><td data-label="Business">' . htmlspecialchars((string) ($user['businessname'] ?? 'Business')) . '</td><td data-label="Email / Phone">' . htmlspecialchars((string) ($user['emailormobilenumber'] ?? '')) . '</td><td data-label="Reason">' . htmlspecialchars((string) ($user['reason'] ?? '')) . '</td><td data-label="Terminated">' . htmlspecialchars((string) ($user['terminated_at'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        return;
    }

    if (!$users) {
        echo '<div class="admin-empty">No users found.</div>';
        return;
    }
    ?>
    <div class="admin-table-wrap admin-table-card-sm admin-table-users-stack<?php echo $userWindow === 'engaging' ? ' admin-table-users-engaging' : ''; ?>">
        <table class="table table-sm align-middle">
            <thead><tr><th>Business</th><th>Email / Phone</th><th>Status</th><th>Suspended Since</th><th>Suspended Until</th><th>Reason</th><?php if ($userWindow === 'engaging'): ?><th>Total Views</th><?php endif; ?><th>Message</th><th>Account</th></tr></thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td data-label="Business">
                            <a class="admin-user-link" href="../visitor_profile.php?user_id=<?php echo (int) $user['id']; ?>">
                                <img src="<?php echo htmlspecialchars(jomu_admin_media_public_url((string) ($user['profilepic'] ?? ''))); ?>" alt="">
                                <span><?php echo htmlspecialchars((string) ($user['businessname'] ?? 'Business')); ?></span>
                            </a>
                        </td>
                        <td data-label="Email / Phone"><?php echo htmlspecialchars((string) ($user['emailormobilenumber'] ?? '')); ?></td>
                        <td data-label="Status"><span class="admin-status-text"><?php echo htmlspecialchars((string) ($user['account_status'] ?? 'active')); ?></span></td>
                        <td class="admin-inactive-since" data-label="Inactive Since"><?php echo htmlspecialchars(admin_inactive_since_text((string) ($user['inactive_since'] ?? ''))); ?></td>
                        <td class="admin-inactive-until" data-label="Inactive Until" data-inactive-until="<?php echo htmlspecialchars((string) ($user['inactive_until'] ?? '')); ?>"><?php echo htmlspecialchars(admin_inactive_remaining_text((string) ($user['inactive_until'] ?? ''))); ?></td>
                        <td class="admin-status-reason" data-label="Reason"><?php echo htmlspecialchars((string) ($user['status_reason'] ?? '')); ?></td>
                        <?php if ($userWindow === 'engaging'): ?><td data-label="Total Views"><?php echo number_format((int) ($user['engagement_score'] ?? 0)); ?></td><?php endif; ?>
                        <td data-label="Message">
                            <form method="post" action="user_action.php" class="admin-inline-form admin-ajax-form" data-admin-action-kind="user-message">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($GLOBALS['currentAdminUrl']); ?>">
                                <select name="template" class="form-select form-select-sm">
                                    <option value="warning">Warning</option>
                                    <option value="terms">Terms reminder</option>
                                    <option value="support">Contact support</option>
                                    <option value="custom">Admin message</option>
                                </select>
                                <input type="hidden" name="custom_message" value="">
                                <button class="btn btn-dark btn-sm" name="action" value="message" type="submit">Send</button>
                            </form>
                        </td>
                        <td data-label="Account">
                            <form method="post" action="user_action.php" class="admin-inline-form admin-account-form admin-ajax-form" data-admin-action-kind="user-account">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($GLOBALS['currentAdminUrl']); ?>">
                                <input type="hidden" name="termination_reason" value="">
                                <input type="hidden" name="inactive_reason" value="">
                                <input type="hidden" name="inactive_days" value="7">
                                <?php if (($user['account_status'] ?? '') === 'inactive'): ?>
                                    <button class="btn btn-success btn-sm" name="action" value="activate" type="submit">Activate</button>
                                <?php else: ?>
                                    <button class="btn btn-warning btn-sm" name="action" value="inactive" type="submit">Suspend</button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" name="action" value="terminate" type="submit">Terminate</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function render_bulk_order_cards(array $bulkPosts, string $csrf): void
{
    if (!$bulkPosts) {
        echo '<div class="admin-empty">No bulk order comments found.</div>';
        return;
    }

    echo '<div class="admin-bulk-list">';
    foreach ($bulkPosts as $post) {
        $postId = (int) ($post['id'] ?? 0);
        $userId = (int) ($post['user_id'] ?? 0);
        $isHidden = strtolower((string) ($post['moderation_status'] ?? 'visible')) === 'hidden';
        $businessName = trim((string) (($post['user_businessname'] ?? '') ?: ($post['business_name'] ?? 'Business')));
        $profilePic = jomu_admin_media_public_url((string) (($post['user_profilepic'] ?? '') ?: ($post['profilepic'] ?? '')));
        $profileUrl = '../visitor_profile.php?user_id=' . $userId;
        ?>
        <article class="admin-bulk-card<?php echo $isHidden ? ' is-hidden' : ''; ?>">
            <div class="admin-bulk-author">
                <a class="admin-user-link" href="<?php echo htmlspecialchars($profileUrl); ?>">
                    <img src="<?php echo htmlspecialchars($profilePic); ?>" alt="">
                    <span><?php echo htmlspecialchars($businessName); ?></span>
                </a>
                <span class="admin-status-pill<?php echo $isHidden ? ' is-hidden' : ''; ?>"><?php echo $isHidden ? 'Hidden' : 'Visible'; ?></span>
            </div>
            <p><?php echo nl2br(htmlspecialchars((string) ($post['content'] ?? ''))); ?></p>
            <div class="admin-bulk-footer">
                <div class="admin-bulk-footer-left">
                    <span class="admin-bulk-date"><?php echo htmlspecialchars((string) ($post['created_at'] ?? '')); ?></span>
                    <?php if ($isHidden && trim((string) ($post['hidden_reason'] ?? '')) !== ''): ?>
                        <span class="admin-bulk-hidden-note"><?php echo htmlspecialchars((string) ($post['hidden_reason'] ?? '')); ?></span>
                    <?php endif; ?>
                </div>
                <form method="post" action="bulk_order_action.php" class="admin-bulk-footer-form admin-ajax-form" data-admin-action-kind="bulk-order">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="post_id" value="<?php echo $postId; ?>">
                    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($GLOBALS['currentAdminUrl']); ?>">
                    <?php if ($isHidden): ?>
                        <button class="btn btn-success btn-sm" name="action" value="unhide" type="submit">Unhide</button>
                        <button class="btn btn-outline-secondary btn-sm" name="action" value="purge" type="submit">Delete</button>
                    <?php else: ?>
                        <button class="btn btn-success btn-sm" name="action" value="approve" type="submit">Approve</button>
                        <button class="btn btn-danger btn-sm" name="action" value="hide" type="submit">Hide</button>
                    <?php endif; ?>
                </form>
            </div>
        </article>
        <?php
    }
    echo '</div>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JoMu Admin</title>
    <link rel="stylesheet" href="/assets/bootstrap.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="admin-page">
    <header class="admin-topbar">
        <a class="admin-brand" href="../../index.php"><img src="<?php echo htmlspecialchars(jomu_admin_media_public_url('assets/images/JoMu logo redesigned.png')); ?>" alt="JoMu"><strong>Admin</strong></a>
        <nav><a href="../../index.php">JoMu Platform</a><a href="logout.php" id="adminLogoutLink">Logout</a></nav>
    </header>
    <div class="admin-secondary-stack" role="navigation" aria-label="Admin sections">
        <nav class="admin-secondary-nav">
            <a class="<?php echo $page === 'overview' ? 'active' : ''; ?>" href="dashboard.php?page=overview">Main</a>
            <a class="<?php echo $page === 'listings' ? 'active' : ''; ?>" href="dashboard.php?page=listings">Latest Listings</a>
            <a class="<?php echo $page === 'users' ? 'active' : ''; ?>" href="dashboard.php?page=users">Users</a>
            <a class="<?php echo $page === 'messages' ? 'active' : ''; ?>" href="dashboard.php?page=messages">Sent Messages</a>
        </nav>
        <nav class="admin-secondary-nav">
            <a class="<?php echo $page === 'bulk_orders' ? 'active' : ''; ?>" href="dashboard.php?page=bulk_orders">Bulk Orders</a>
            <a class="<?php echo $page === 'links' ? 'active' : ''; ?>" href="dashboard.php?page=links">Links</a>
            <a class="<?php echo $page === 'ads' ? 'active' : ''; ?>" href="dashboard.php?page=ads">Ads</a>
        </nav>
    </div>
    <main class="admin-shell">
        <?php if ($message !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

        <?php if ($page === 'overview' || $page === 'listings'): ?>
            <section class="admin-section">
                <div class="admin-section-head">
                    <div><h2>Latest Listings <span class="admin-count"><?php echo number_format($listingSectionCount); ?></span></h2><p>Review new posts, open full details, hide or restore listings.</p></div>
                    <form class="admin-search" method="get">
                        <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                        <input type="hidden" name="listing_window" value="<?php echo htmlspecialchars($listingWindow); ?>">
                        <input class="form-control" type="search" name="listing_search" value="<?php echo htmlspecialchars($listingSearch); ?>" placeholder="Search listing name or category">
                        <button class="btn btn-dark" type="submit">Search</button>
                    </form>
                </div>
                <div class="admin-subnav">
                    <a class="<?php echo $listingWindow === 'all' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'listing_window' => 'all', 'listing_search' => $listingSearch])); ?>">Pending <span><?php echo number_format($pendingListingsCount); ?></span></a>
                    <a class="<?php echo $listingWindow === 'hidden' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'listing_window' => 'hidden', 'listing_search' => $listingSearch])); ?>">Hidden Listings <span><?php echo number_format($hiddenListingsTotal); ?></span></a>
                </div>
                <?php render_listing_cards($listings, $csrf); ?>
            </section>
        <?php endif; ?>

        <?php if ($page === 'overview' || $page === 'users'): ?>
            <section class="admin-section">
                <div class="admin-section-head">
                    <div><h2>Users <span class="admin-count"><?php echo number_format($totalUsers); ?></span></h2><p>Newest joined users show first. Search accounts, send JoMu notices, suspend, activate, or terminate.</p></div>
                    <form class="admin-search" method="get">
                        <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                        <input type="hidden" name="user_window" value="<?php echo htmlspecialchars($userWindow); ?>">
                        <input class="form-control" type="search" name="user_search" value="<?php echo htmlspecialchars($userSearch); ?>" placeholder="Search name, email or phone">
                        <button class="btn btn-dark" type="submit">Search</button>
                    </form>
                </div>
                <div class="admin-subnav admin-subnav-flow">
                    <a class="<?php echo $userWindow === 'all' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'user_window' => 'all', 'user_search' => $userSearch])); ?>">All Users <span><?php echo number_format($totalUsers); ?></span></a>
                    <a class="<?php echo $userWindow === 'engaging' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'user_window' => 'engaging', 'user_search' => $userSearch])); ?>">Most Engaging</a>
                    <a class="<?php echo $userWindow === 'inactive' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'user_window' => 'inactive', 'user_search' => $userSearch])); ?>">Suspended Users <span><?php echo number_format($inactiveUsersTotal); ?></span></a>
                    <a class="<?php echo $userWindow === 'terminated' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'user_window' => 'terminated', 'user_search' => $userSearch])); ?>">Terminated Users <span><?php echo number_format($terminatedUsersTotal); ?></span></a>
                </div>
                <?php render_users_table($users, $terminatedUsers, $userWindow, $csrf); ?>
            </section>
        <?php endif; ?>

        <?php if ($page === 'overview' || $page === 'messages'): ?>
            <section class="admin-section">
                <div class="admin-section-head">
                    <div><h2>Sent Messages</h2><p>Messages sent automatically or manually from the JoMu system profile.</p></div>
                    <form class="admin-search" method="get">
                        <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>">
                        <input class="form-control" type="search" name="message_search" value="<?php echo htmlspecialchars($messageSearch); ?>" placeholder="Search user or message type">
                        <button class="btn btn-dark" type="submit">Search</button>
                    </form>
                </div>
                <form method="post" action="message_all_action.php" class="admin-send-all-form admin-ajax-form" data-admin-action-kind="message-all">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($currentAdminUrl); ?>">
                    <textarea class="form-control" name="message_text" rows="3" placeholder="Write a JoMu message to send to all users" required></textarea>
                    <button class="btn btn-dark" name="action" value="message_all" type="submit">Send to all</button>
                </form>
                <div class="admin-table-wrap admin-table-card-sm admin-table-messages-stack">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>User</th><th>Email / Phone</th><th>Type</th><th>Message</th><th>Sent</th></tr></thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                                <tr>
                                    <td data-label="User">
                                        <a class="admin-user-link" href="../visitor_profile.php?user_id=<?php echo (int) ($msg['receiver_user_id'] ?? 0); ?>">
                                            <img src="<?php echo htmlspecialchars(jomu_admin_media_public_url((string) ($msg['profilepic'] ?? ''))); ?>" alt="">
                                            <span><?php echo htmlspecialchars((string) ($msg['businessname'] ?? 'Business')); ?></span>
                                        </a>
                                    </td>
                                    <td data-label="Email / Phone"><?php echo htmlspecialchars((string) ($msg['emailormobilenumber'] ?? '')); ?></td>
                                    <td data-label="Type"><?php echo htmlspecialchars((string) ($msg['message_type'] ?? 'text')); ?></td>
                                    <td data-label="Message"><?php echo nl2br(htmlspecialchars((string) ($msg['message_text'] ?? ''))); ?></td>
                                    <td data-label="Sent"><?php echo htmlspecialchars((string) ($msg['created_at'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$messages): ?><tr><td colspan="5">No sent messages yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($page === 'overview' || $page === 'bulk_orders'): ?>
            <section class="admin-section">
                <div class="admin-section-head">
                    <div><h2>Bulk Orders <span class="admin-count"><?php echo number_format($pendingBulkPostsCount); ?></span></h2><p>Moderate business comments posted in bulk orders.</p></div>
                    <form class="admin-search" method="get">
                        <input type="hidden" name="page" value="<?php echo htmlspecialchars($page === 'overview' ? 'overview' : 'bulk_orders'); ?>">
                        <input type="hidden" name="bulk_window" value="<?php echo htmlspecialchars($bulkWindow); ?>">
                        <input class="form-control" type="search" name="bulk_search" value="<?php echo htmlspecialchars($bulkSearch); ?>" placeholder="Search comment or business">
                        <button class="btn btn-dark" type="submit">Search</button>
                    </form>
                </div>
                <div class="admin-subnav">
                    <a class="<?php echo $bulkWindow === 'all' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'bulk_window' => 'all', 'bulk_search' => $bulkSearch])); ?>">All <span><?php echo number_format($pendingBulkPostsCount); ?></span></a>
                    <a class="<?php echo $bulkWindow === 'hidden' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(admin_page_url(['page' => $page, 'bulk_window' => 'hidden', 'bulk_search' => $bulkSearch])); ?>">Hidden <span><?php echo number_format($hiddenBulkPostsTotal); ?></span></a>
                </div>
                <?php render_bulk_order_cards($bulkPosts, $csrf); ?>
            </section>
        <?php endif; ?>

        <?php if ($page === 'links'): ?>
            <section class="admin-section">
                <div class="admin-section-head">
                    <div><h2>Links</h2><p>Edit social, app, and support contact details shown around the platform.</p></div>
                </div>
                <div class="admin-table-wrap admin-table-card-sm">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Link</th><th>Value, updated &amp; action</th></tr></thead>
                        <tbody>
                            <?php foreach ($siteLinks as $link): ?>
                                <?php
                                    $linkKey = (string) ($link['link_key'] ?? '');
                                    $inputType = 'url';
                                    $placeholder = 'https://...';
                                    if (in_array($linkKey, ['support_email', 'privacy_email'], true)) {
                                        $inputType = 'email';
                                        $placeholder = 'support@example.com';
                                    } elseif (in_array($linkKey, ['support_phone', 'support_whatsapp'], true)) {
                                        $inputType = 'tel';
                                        $placeholder = '+256 700000000';
                                    }
                                ?>
                                <tr>
                                    <td data-label="Link"><?php echo htmlspecialchars((string) ($link['label'] ?? 'Link')); ?></td>
                                    <td data-label="Value &amp; action">
                                        <form method="post" action="link_action.php" class="admin-link-edit-form admin-inline-form admin-ajax-form" data-admin-action-kind="site-link">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($currentAdminUrl); ?>">
                                            <input type="hidden" name="link_key" value="<?php echo htmlspecialchars($linkKey); ?>">
                                            <input class="form-control form-control-sm" type="<?php echo htmlspecialchars($inputType); ?>" name="url" value="<?php echo htmlspecialchars((string) ($link['url'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($placeholder); ?>">
                                            <span class="admin-link-updated-note text-muted small">Updated <?php echo htmlspecialchars((string) ($link['updated_at'] ?? '—')); ?></span>
                                            <button class="btn btn-dark btn-sm" type="submit">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($page === 'ads'): ?>
            <section class="admin-section">
                <div class="admin-section-head">
                    <div>
                        <h2>Ads <span class="admin-count"><?php echo number_format(count($adminAdAssets)); ?></span></h2>
                        <p>Videos and images used around the platform. Search by file name, label, or page (e.g. <code>index.php</code>, <code>nav</code>, <code>about</code>).</p>
                    </div>
                    <form class="admin-search" method="get" action="dashboard.php">
                        <input type="hidden" name="page" value="ads">
                        <input class="form-control" type="search" name="ads_search" value="<?php echo htmlspecialchars($adsSearch); ?>" placeholder="Search media or page…" autocomplete="off">
                        <button class="btn btn-dark" type="submit">Search</button>
                        <?php if ($adsSearch !== ''): ?>
                            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(admin_page_url(['page' => 'ads', 'ads_search' => ''])); ?>">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="admin-table-wrap admin-table-card-sm admin-table-ads">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Asset</th><th>Where used</th><th>Current Size</th><th>Replace file</th></tr></thead>
                        <tbody>
                            <?php if (!$adminAdAssets): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <?php if ($adsSearch !== ''): ?>
                                            No media matches <strong><?php echo htmlspecialchars($adsSearch); ?></strong>. Try another file name, path segment, or page reference.
                                        <?php else: ?>
                                            No assets in the manifest.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($adminAdAssets as $asset): ?>
                                <?php
                                    $assetPath = (string) $asset['path'];
                                    $absoluteAssetPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
                                    $sizeText = is_file($absoluteAssetPath) ? number_format((float) filesize($absoluteAssetPath) / 1024 / 1024, 2) . ' MB' : 'Missing file';
                                    $assetPages = $asset['pages'] ?? [];
                                    if (!$assetPages && !empty($asset['page'])) {
                                        $assetPages = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $asset['page']))));
                                    }
                                    if (!$assetPages) {
                                        $assetPages = ['—'];
                                    }
                                ?>
                                <tr>
                                    <td data-label="Asset">
                                        <button
                                            type="button"
                                            class="admin-asset-open"
                                            data-asset-details="<?php echo htmlspecialchars(json_encode([
                                                'id' => '',
                                                'title' => (string) $asset['label'],
                                                'description' => (string) $assetPath,
                                                'category' => 'Platform asset',
                                                'region' => '',
                                                'town' => '',
                                                'price' => '',
                                                'type' => ucfirst(getMediaType($assetPath)),
                                                'hashtags' => '',
                                                'seller' => 'JoMu Admin',
                                                'seller_login' => '',
                                                'seller_profilepic' => jomu_admin_media_public_url('assets/images/JoMu logo redesigned.png'),
                                                'seller_profile_url' => '#',
                                                'created_at' => '',
                                                'status' => is_file($absoluteAssetPath) ? 'Available' : 'Missing file',
                                                'hidden_reason' => '',
                                                'media_type' => getMediaType($assetPath),
                                                'media_src' => jomu_admin_media_public_url($assetPath),
                                                'gallery' => [jomu_admin_media_public_url($assetPath)],
                                            ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                                        ><?php echo htmlspecialchars($asset['label']); ?></button>
                                        <br><small><?php echo htmlspecialchars($assetPath); ?></small>
                                    </td>
                                    <td data-label="Where used">
                                        <ul class="admin-asset-pages mb-0 ps-3 small">
                                            <?php foreach ($assetPages as $pageLine): ?>
                                                <li><?php echo htmlspecialchars((string) $pageLine); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </td>
                                    <td data-label="Current Size"><?php echo htmlspecialchars($sizeText); ?></td>
                                    <td data-label="Replace file">
                                        <form method="post" action="asset_action.php" enctype="multipart/form-data" class="admin-inline-form admin-ads-update-form admin-ajax-form" data-admin-action-kind="site-asset">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($currentAdminUrl); ?>">
                                            <input type="hidden" name="asset_path" value="<?php echo htmlspecialchars($assetPath); ?>">
                                            <input class="form-control form-control-sm" type="file" name="asset_file" accept="image/*,video/*">
                                            <button class="btn btn-dark btn-sm" type="submit">Update</button>
                                            <a class="btn btn-outline-dark btn-sm" href="<?php echo htmlspecialchars(jomu_admin_media_public_url($assetPath)); ?>" download>Download</a>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <button type="button" id="backToTop" aria-label="Back to top">&#8593;</button>

    <div class="admin-dialog" id="adminDialog" aria-hidden="true">
        <div class="admin-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="adminDialogTitle">
            <h3 id="adminDialogTitle">JoMu Admin</h3>
            <p id="adminDialogMessage"></p>
            <textarea id="adminDialogText" class="form-control" rows="4" style="display:none;"></textarea>
            <select id="adminDialogSelect" class="form-select mt-2" style="display:none;">
                <option value="7">7 days</option>
                <option value="14">14 days</option>
                <option value="21">21 days</option>
                <option value="28">28 days</option>
            </select>
            <div class="admin-dialog-actions">
                <button type="button" class="btn btn-outline-dark btn-sm" id="adminDialogCancel">Cancel</button>
                <button type="button" class="btn btn-dark btn-sm" id="adminDialogOk">Continue</button>
            </div>
        </div>
    </div>

    <div class="admin-modal" id="listingDetailModal" aria-hidden="true">
        <div class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="listingDetailTitle">
            <button type="button" class="admin-modal-close" id="listingDetailClose" aria-label="Close">&times;</button>
            <div class="admin-modal-media" id="listingDetailMedia"></div>
            <div class="admin-modal-body">
                <div class="admin-modal-title-row">
                    <div>
                        <h2 id="listingDetailTitle"></h2>
                        <div id="listingDetailSeller"></div>
                    </div>
                    <span id="listingDetailStatus" class="admin-status-pill"></span>
                </div>
                <dl class="admin-detail-grid" id="listingDetailGrid"></dl>
                <div class="admin-description-block">
                    <h3>Description</h3>
                    <p id="listingDetailDescription"></p>
                </div>
                <div class="admin-modal-listing-actions" id="listingDetailActions"></div>
            </div>
        </div>
    </div>

    <script>
        const adminListingCsrf = <?php echo json_encode($csrf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const listingModal = document.getElementById('listingDetailModal');
        const listingModalClose = document.getElementById('listingDetailClose');
        const listingMedia = document.getElementById('listingDetailMedia');
        const listingTitle = document.getElementById('listingDetailTitle');
        const listingSeller = document.getElementById('listingDetailSeller');
        const listingStatus = document.getElementById('listingDetailStatus');
        const listingGrid = document.getElementById('listingDetailGrid');
        const listingDescription = document.getElementById('listingDetailDescription');
        const listingActions = document.getElementById('listingDetailActions');
        let activeListingModalDetails = null;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[char]));
        }

        function openListingModal(details) {
            if (!listingModal || !details) return;
            listingMedia.innerHTML = '';
            const mediaSrc = String(details.media_src || '').trim();
            const mediaType = String(details.media_type || '').toLowerCase();
            const main = document.createElement(mediaType === 'video' ? 'video' : 'img');
            main.className = 'admin-modal-main-media';
            if (mediaType === 'video') {
                main.setAttribute('controls', 'controls');
                main.setAttribute('playsinline', '');
                const source = document.createElement('source');
                source.src = mediaSrc;
                source.type = 'video/mp4';
                main.appendChild(source);
                main.load();
            } else {
                if (mediaSrc) {
                    main.src = mediaSrc;
                }
                main.alt = String(details.title || 'Preview');
            }
            listingMedia.appendChild(main);

            if (mediaType !== 'video' && Array.isArray(details.gallery) && details.gallery.length) {
                const strip = document.createElement('div');
                strip.className = 'admin-gallery-strip';
                details.gallery.forEach((src, index) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.gallerySrc = String(src || '');
                    if (index === 0) btn.classList.add('is-active');
                    const thumb = document.createElement('img');
                    thumb.src = String(src || '');
                    thumb.alt = '';
                    btn.appendChild(thumb);
                    strip.appendChild(btn);
                });
                listingMedia.appendChild(strip);
            }
            listingTitle.textContent = details.title || 'Listing';
            const showFullJoMuLogo = String(details.seller || '').trim() === 'JoMu Admin'
                || String(details.category || '').trim() === 'Platform asset';
            const sellerLinkClass = showFullJoMuLogo
                ? 'admin-modal-seller-link admin-modal-seller-link--logo-preview'
                : 'admin-modal-seller-link';
            listingSeller.innerHTML = `<a class="${sellerLinkClass}" href="${escapeHtml(details.seller_profile_url || '#')}"><img src="${escapeHtml(details.seller_profilepic || '')}" alt=""><span>${escapeHtml(details.seller || 'Business')}</span></a>${details.seller_login ? `<small>${escapeHtml(details.seller_login)}</small>` : ''}`;
            listingStatus.textContent = details.status || 'Visible';
            listingStatus.classList.toggle('is-hidden', String(details.status || '').toLowerCase() === 'hidden');
            const rows = [
                ['Listing ID', details.id],
                ['Type', details.type],
                ['Category', details.category],
                ['Region', details.region],
                ['Town', details.town],
                ['Price / Charge', details.price],
                ['Hashtags', details.hashtags],
                ['Posted', details.created_at],
                ['Hidden Reason', details.hidden_reason],
            ].filter((row) => String(row[1] ?? '').trim() !== '');
            listingGrid.innerHTML = rows.map(([label, value]) => `<dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd>`).join('');
            listingDescription.textContent = details.description || 'No description provided.';
            activeListingModalDetails = details;
            renderListingModalActions(details);
            listingModal.classList.add('active');
            listingModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('admin-modal-open');
        }

        function closeListingModal() {
            if (!listingModal) return;
            listingModal.classList.remove('active');
            listingModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('admin-modal-open');
            listingMedia.innerHTML = '';
            if (listingActions) listingActions.innerHTML = '';
            activeListingModalDetails = null;
        }

        function renderListingModalActions(details) {
            if (!listingActions || !details?.id) {
                if (listingActions) listingActions.innerHTML = '';
                return;
            }
            const isHidden = Boolean(details.is_hidden) || String(details.status || '').toLowerCase() === 'hidden';
            const metaParts = [];
            if (String(details.seller || '').trim()) metaParts.push(`Posted by: ${details.seller}`);
            if (String(details.created_at || '').trim()) metaParts.push(`Posted: ${details.created_at}`);
            const actionLabel = isHidden ? 'Unhide' : 'Hide';
            const actionValue = isHidden ? 'unhide' : 'hide';
            const actionClass = isHidden ? 'btn btn-success btn-sm' : 'btn btn-danger btn-sm';
            listingActions.innerHTML = `
                <p class="admin-modal-listing-meta">${escapeHtml(metaParts.join(' · '))}</p>
                <button type="button" class="${actionClass}" data-listing-modal-action="${actionValue}">${actionLabel}</button>
            `;
        }

        async function submitListingModalAction(action) {
            const details = activeListingModalDetails;
            const listingId = Number.parseInt(String(details?.id || ''), 10);
            if (!Number.isInteger(listingId) || listingId <= 0 || !adminListingCsrf) return;
            if (action === 'hide') {
                const confirmHide = await openAdminDialog({
                    title: 'Hide listing',
                    message: 'Hide this listing? It will move to Hidden listings in the admin dashboard and the seller will be notified.',
                    okText: 'Proceed',
                    cancelText: 'Cancel'
                });
                if (!confirmHide.ok) return;
            } else if (action === 'unhide') {
                const confirmUnhide = await openAdminDialog({
                    title: 'Restore listing',
                    message: 'Restore this listing to visible on the platform?',
                    okText: 'Proceed',
                    cancelText: 'Cancel'
                });
                if (!confirmUnhide.ok) return;
            }
            const fd = new FormData();
            fd.append('csrf_token', adminListingCsrf);
            fd.append('listing_id', String(listingId));
            fd.append('action', action);
            fd.append('return_to', window.location.href);
            try {
                const response = await fetch('listing_action.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
                const responseText = await response.text();
                let result = {};
                try {
                    result = responseText ? JSON.parse(responseText) : {};
                } catch (error) {
                    result = {};
                }
                if (!response.ok || result.ok === false) {
                    throw new Error(result.message || 'Action failed.');
                }
                if (action === 'hide') {
                    closeListingModal();
                    const card = document.querySelector(`.admin-listing-card form input[name="listing_id"][value="${listingId}"]`)?.closest('.admin-listing-card');
                    card?.remove();
                    showAdminNotice(result.message || 'Listing hidden.');
                    return;
                }
                activeListingModalDetails = {
                    ...details,
                    is_hidden: false,
                    status: 'Visible',
                    hidden_reason: '',
                };
                listingStatus.textContent = 'Visible';
                listingStatus.classList.remove('is-hidden');
                renderListingModalActions(activeListingModalDetails);
                const card = document.querySelector(`.admin-listing-card form input[name="listing_id"][value="${listingId}"]`)?.closest('.admin-listing-card');
                if (card) {
                    card.classList.remove('is-hidden');
                    card.querySelectorAll('.admin-status-pill').forEach((pill) => {
                        pill.textContent = 'Visible';
                        pill.classList.remove('is-hidden');
                    });
                }
                showAdminNotice(result.message || 'Listing restored.');
            } catch (error) {
                openAdminDialog({ title: 'Action failed', message: error.message || 'Action failed.', okText: 'OK', cancelText: 'Close' });
            }
        }

        listingActions?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-listing-modal-action]');
            if (!button) return;
            event.preventDefault();
            submitListingModalAction(button.dataset.listingModalAction || '');
        });

        listingMedia?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-gallery-src]');
            if (!button) return;
            const main = listingMedia.querySelector('.admin-modal-main-media');
            if (main?.tagName?.toLowerCase() === 'img') {
                main.src = button.dataset.gallerySrc || '';
                listingMedia.querySelectorAll('[data-gallery-src]').forEach((item) => {
                    item.classList.toggle('is-active', item === button);
                });
            }
        });

        document.querySelectorAll('[data-listing-details]').forEach((button) => {
            button.addEventListener('click', () => {
                try {
                    openListingModal(JSON.parse(button.dataset.listingDetails || '{}'));
                } catch (error) {
                    openListingModal(null);
                }
            });
        });
        document.querySelectorAll('[data-asset-details]').forEach((button) => {
            button.addEventListener('click', () => {
                try {
                    openListingModal(JSON.parse(button.dataset.assetDetails || '{}'));
                } catch (error) {
                    openListingModal(null);
                }
            });
        });
        listingModalClose?.addEventListener('click', closeListingModal);
        listingModal?.addEventListener('click', (event) => {
            if (event.target === listingModal) closeListingModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeListingModal();
        });

        const adminDialog = document.getElementById('adminDialog');
        const adminDialogTitle = document.getElementById('adminDialogTitle');
        const adminDialogMessage = document.getElementById('adminDialogMessage');
        const adminDialogText = document.getElementById('adminDialogText');
        const adminDialogSelect = document.getElementById('adminDialogSelect');
        const adminDialogCancel = document.getElementById('adminDialogCancel');
        const adminDialogOk = document.getElementById('adminDialogOk');

        function openAdminDialog({ title = 'JoMu Admin', message = '', text = false, select = false, okText = 'Continue', cancelText = 'Cancel', requireText = false } = {}) {
            return new Promise((resolve) => {
                adminDialogTitle.textContent = title;
                adminDialogMessage.textContent = message;
                adminDialogText.style.display = text ? 'block' : 'none';
                adminDialogText.value = '';
                adminDialogSelect.style.display = select ? 'block' : 'none';
                adminDialogOk.textContent = okText;
                adminDialogCancel.textContent = cancelText;
                adminDialog.classList.add('active');
                adminDialog.setAttribute('aria-hidden', 'false');

                let okClass = 'btn btn-sm btn-dark';
                if (okText === 'Suspend') okClass = 'btn btn-sm btn-warning';
                else if (okText === 'Terminate' || okText === 'Yes, terminate') okClass = 'btn btn-sm btn-danger';
                else if (okText === 'Send' || okText === 'Approve') okClass = 'btn btn-sm btn-success';
                adminDialogOk.className = okClass;

                let textInputListener = null;
                const syncRequireText = () => {
                    if (text && requireText) {
                        adminDialogOk.disabled = adminDialogText.value.trim() === '';
                    } else {
                        adminDialogOk.disabled = false;
                    }
                };
                syncRequireText();
                if (text && requireText) {
                    textInputListener = () => syncRequireText();
                    adminDialogText.addEventListener('input', textInputListener);
                }
                if (text) adminDialogText.focus();

                const close = (value) => {
                    if (textInputListener) {
                        adminDialogText.removeEventListener('input', textInputListener);
                    }
                    adminDialogOk.disabled = false;
                    adminDialogOk.className = 'btn btn-sm btn-dark';
                    adminDialog.classList.remove('active');
                    adminDialog.setAttribute('aria-hidden', 'true');
                    adminDialogOk.removeEventListener('click', onOk);
                    adminDialogCancel.removeEventListener('click', onCancel);
                    resolve(value);
                };
                const onOk = () => close({
                    ok: true,
                    text: adminDialogText.value.trim(),
                    days: adminDialogSelect.value
                });
                const onCancel = () => close({ ok: false });
                adminDialogOk.addEventListener('click', onOk);
                adminDialogCancel.addEventListener('click', onCancel);
            });
        }

        function runSendAllCountdown() {
            return new Promise((resolve) => {
                let remaining = 10;
                let settled = false;
                adminDialogTitle.textContent = 'Sending soon...';
                adminDialogText.style.display = 'none';
                adminDialogSelect.style.display = 'none';
                adminDialogOk.style.display = 'none';
                adminDialogCancel.textContent = 'Cancel';
                adminDialogCancel.className = 'btn btn-sm btn-outline-secondary';
                adminDialog.classList.add('active');
                adminDialog.setAttribute('aria-hidden', 'false');

                const close = (ok) => {
                    if (settled) return;
                    settled = true;
                    clearInterval(timer);
                    adminDialogOk.style.display = '';
                    adminDialogOk.disabled = false;
                    adminDialogOk.className = 'btn btn-sm btn-dark';
                    adminDialog.classList.remove('active');
                    adminDialog.setAttribute('aria-hidden', 'true');
                    adminDialogCancel.removeEventListener('click', onCancel);
                    resolve({ ok });
                };
                const tick = () => {
                    adminDialogMessage.textContent = `Message will be sent to all users in ${remaining} seconds.`;
                    if (remaining <= 0) {
                        close(true);
                        return;
                    }
                    remaining -= 1;
                };
                const onCancel = () => close(false);
                adminDialogCancel.addEventListener('click', onCancel);
                tick();
                const timer = setInterval(tick, 1000);
            });
        }

        document.getElementById('adminLogoutLink')?.addEventListener('click', async (e) => {
            e.preventDefault();
            const href = e.currentTarget.getAttribute('href');
            const result = await openAdminDialog({
                title: 'Log out',
                message: 'Are you sure you want to log out of the admin panel?',
                okText: 'Log out',
                cancelText: 'Cancel'
            });
            if (result.ok) {
                window.location.href = href;
            }
        });

        async function prepareAdminAction(form, action) {
            if (action === 'inactive') {
                const result = await openAdminDialog({
                    title: 'Suspend account',
                    message: 'Choose the number of days and write the reason for suspending this account.',
                    text: true,
                    select: true,
                    requireText: true,
                    okText: 'Suspend'
                });
                if (!result.ok || !result.text) return false;
                form.querySelector('input[name="inactive_reason"]').value = result.text;
                form.querySelector('input[name="inactive_days"]').value = result.days || '7';
            }
            if (action === 'terminate') {
                const result = await openAdminDialog({
                    title: 'Terminate account',
                    message: 'Write the reason. This deletes the account after you continue.',
                    text: true,
                    requireText: true,
                    okText: 'Terminate'
                });
                if (!result.ok || !result.text) return false;
                form.querySelector('input[name="termination_reason"]').value = result.text;
                const confirm = await openAdminDialog({
                    title: 'Final confirmation',
                    message: 'Terminate this account now? This cannot be undone from the admin page.',
                    okText: 'Yes, terminate'
                });
                if (!confirm.ok) return false;
            }
            if (action === 'message') {
                const templateSelect = form.querySelector('[name="template"]');
                const templateValue = templateSelect?.value || 'warning';
                const templateLabel = templateSelect?.selectedOptions?.[0]?.textContent?.trim() || 'message';
                const confirmSend = await openAdminDialog({
                    title: 'Send JoMu message',
                    message: `Continue to send the "${templateLabel}" notice to this user?`,
                    okText: 'Continue',
                    cancelText: 'Cancel'
                });
                if (!confirmSend.ok) return false;
                if (templateValue === 'custom') {
                    const result = await openAdminDialog({
                        title: 'Admin message',
                        message: 'Type the JoMu message to send to this business.',
                        text: true,
                        requireText: true,
                        okText: 'Send'
                    });
                    if (!result.ok || !result.text) return false;
                    form.querySelector('input[name="custom_message"]').value = result.text;
                }
            }
            if (action === 'message_all') {
                const text = form.querySelector('[name="message_text"]')?.value?.trim() || '';
                if (!text) {
                    await openAdminDialog({
                        title: 'Message required',
                        message: 'Write the message before sending to all users.',
                        okText: 'OK',
                        cancelText: 'Close'
                    });
                    return false;
                }
                const firstConfirm = await openAdminDialog({
                    title: 'Send to all users',
                    message: 'Send this message to all users?',
                    okText: 'Approve',
                    cancelText: 'Cancel'
                });
                if (!firstConfirm.ok) return false;
                const secondConfirm = await openAdminDialog({
                    title: 'Are you sure?',
                    message: 'This will place the message in every user inbox.',
                    okText: 'Approve',
                    cancelText: 'Cancel'
                });
                if (!secondConfirm.ok) return false;
                const countdown = await runSendAllCountdown();
                if (!countdown.ok) return false;
            }
            if (action === 'purge') {
                const confirm = await openAdminDialog({
                    title: 'Delete listing permanently',
                    message: 'Delete this hidden listing permanently? It will be removed from the platform and database. This cannot be undone.',
                    okText: 'Proceed',
                    cancelText: 'Cancel'
                });
                if (!confirm.ok) return false;
            }
            if (action === 'hide') {
                const confirm = await openAdminDialog({
                    title: 'Hide listing',
                    message: 'Hide this listing? It will move to Hidden listings in the admin dashboard and the seller will be notified.',
                    okText: 'Proceed',
                    cancelText: 'Cancel'
                });
                if (!confirm.ok) return false;
            }
            if (action === 'approve') {
                const confirm = await openAdminDialog({
                    title: 'Approve listing',
                    message: 'Approve this listing and remove it from the pending queue?',
                    okText: 'Proceed',
                    cancelText: 'Cancel'
                });
                if (!confirm.ok) return false;
            }
            if (action === 'unhide') {
                const confirm = await openAdminDialog({
                    title: 'Restore listing',
                    message: 'Restore this listing to visible on the platform?',
                    okText: 'Proceed',
                    cancelText: 'Cancel'
                });
                if (!confirm.ok) return false;
            }
            return true;
        }

        async function submitAdminFormData(form, formData) {
            const endpoint = new URL(form.getAttribute('action') || '', window.location.href).toString();
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const responseText = await response.text();
            let result = {};
            try {
                result = responseText ? JSON.parse(responseText) : {};
            } catch (error) {
                result = { message: responseText || 'Action failed.' };
            }
            if (!response.ok || result.ok === false) {
                throw new Error(result.message || 'Action failed.');
            }
            return result;
        }

        function showAdminNotice(message) {
            openAdminDialog({
                title: 'Done',
                message: message || 'Done.',
                okText: 'OK',
                cancelText: 'Close'
            });
        }

        function updateAdminActionUi(form, action, result) {
            showAdminNotice(result.message);
            const kind = form.dataset.adminActionKind || '';
            if (kind === 'listing' && (action === 'approve' || action === 'purge')) {
                form.closest('.admin-listing-card')?.remove();
                return;
            }
            if (kind === 'listing' && (action === 'hide' || action === 'unhide')) {
                const card = form.closest('.admin-listing-card');
                const hidden = action === 'hide';
                card?.classList.toggle('is-hidden', hidden);
                card?.querySelectorAll('.admin-status-pill').forEach((pill) => {
                    pill.textContent = hidden ? 'Hidden' : 'Visible';
                    pill.classList.toggle('is-hidden', hidden);
                });
                const hiddenInputs = Array.from(form.querySelectorAll('input[type="hidden"]'));
                form.replaceChildren();
                hiddenInputs.forEach((el) => form.appendChild(el));
                if (hidden) {
                    form.insertAdjacentHTML('beforeend', '<button class="btn btn-success btn-sm" name="action" value="unhide" type="submit">Unhide</button><button class="btn btn-outline-secondary btn-sm" name="action" value="purge" type="submit">Delete</button>');
                } else {
                    form.insertAdjacentHTML('beforeend', '<button class="btn btn-success btn-sm" name="action" value="approve" type="submit">Approve</button><button class="btn btn-danger btn-sm" name="action" value="hide" type="submit">Hide</button>');
                }
                return;
            }
            if (kind === 'bulk-order' && (action === 'approve' || action === 'purge')) {
                form.closest('.admin-bulk-card')?.remove();
                return;
            }
            if (kind === 'bulk-order' && (action === 'hide' || action === 'unhide')) {
                const card = form.closest('.admin-bulk-card');
                const hidden = action === 'hide';
                card?.classList.toggle('is-hidden', hidden);
                card?.querySelectorAll('.admin-status-pill').forEach((pill) => {
                    pill.textContent = hidden ? 'Hidden' : 'Visible';
                    pill.classList.toggle('is-hidden', hidden);
                });
                const hiddenInputs = Array.from(form.querySelectorAll('input[type="hidden"]'));
                form.replaceChildren();
                hiddenInputs.forEach((el) => form.appendChild(el));
                if (hidden) {
                    form.insertAdjacentHTML('beforeend', '<button class="btn btn-success btn-sm" name="action" value="unhide" type="submit">Unhide</button><button class="btn btn-outline-secondary btn-sm" name="action" value="purge" type="submit">Delete</button>');
                } else {
                    form.insertAdjacentHTML('beforeend', '<button class="btn btn-success btn-sm" name="action" value="approve" type="submit">Approve</button><button class="btn btn-danger btn-sm" name="action" value="hide" type="submit">Hide</button>');
                }
                return;
            }
            if (kind === 'user-account') {
                const row = form.closest('tr');
                const status = row?.querySelector('.admin-status-text');
                const primaryButton = form.querySelector('button[name="action"][value="activate"], button[name="action"][value="inactive"]');
                if (action === 'activate') {
                    if (status) status.textContent = 'active';
                    if (primaryButton) {
                        primaryButton.value = 'inactive';
                        primaryButton.textContent = 'Suspend';
                        primaryButton.classList.remove('btn-success');
                        primaryButton.classList.add('btn-warning');
                    }
                } else if (action === 'inactive') {
                    if (status) status.textContent = 'inactive';
                    if (primaryButton) {
                        primaryButton.value = 'activate';
                        primaryButton.textContent = 'Activate';
                        primaryButton.classList.remove('btn-warning');
                        primaryButton.classList.add('btn-success');
                    }
                    const inactiveSinceCell = row?.querySelector('.admin-inactive-since');
                    const inactiveUntilCell = row?.querySelector('.admin-inactive-until');
                    const statusReasonCell = row?.querySelector('.admin-status-reason');
                    if (inactiveSinceCell) {
                        inactiveSinceCell.textContent = result.inactive_since || '';
                    }
                    if (inactiveUntilCell) {
                        inactiveUntilCell.textContent = result.inactive_until_text || result.inactive_until || '';
                    }
                    if (statusReasonCell) {
                        statusReasonCell.textContent = result.status_reason || '';
                    }
                } else if (action === 'terminate') {
                    row?.remove();
                }
            }
            if (kind === 'message-all') {
                form.reset();
            }
        }

        (function initAdminBackToTop() {
            const btn = document.getElementById('backToTop');
            if (!btn) return;
            window.addEventListener('scroll', () => {
                btn.style.display = window.scrollY > 400 ? 'block' : 'none';
            }, { passive: true });
            btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
        })();

        document.querySelectorAll('.admin-ajax-form').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                if (event.defaultPrevented) return;
                event.preventDefault();
                const submitter = event.submitter;
                const action = submitter?.value || '';
                if (!(await prepareAdminAction(form, action))) return;
                const formData = new FormData(form);
                if (submitter?.name) {
                    formData.set(submitter.name, submitter.value);
                }
                form.classList.add('is-busy');
                form.querySelectorAll('button, select, textarea, input:not([type="hidden"])').forEach((control) => { control.disabled = true; });
                try {
                    const result = await submitAdminFormData(form, formData);
                    updateAdminActionUi(form, action, result);
                } catch (error) {
                    openAdminDialog({ title: 'Action failed', message: error.message || 'Action failed.', okText: 'OK', cancelText: 'Close' });
                } finally {
                    form.classList.remove('is-busy');
                    form.querySelectorAll('button, select, textarea, input:not([type="hidden"])').forEach((control) => { control.disabled = false; });
                }
            });
        });
    </script>
</body>
</html>
