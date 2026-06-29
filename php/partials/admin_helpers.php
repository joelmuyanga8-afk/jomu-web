<?php

if (!function_exists('jomu_reject_cross_site_request')) {
    function jomu_reject_cross_site_request(): void
    {
        $secFetchSite = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? ''));
        if (in_array($secFetchSite, ['cross-site'], true)) {
            http_response_code(403);
            exit;
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') {
            return;
        }

        $originHost = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = preg_replace('/:\d+$/', '', $requestHost) ?? $requestHost;

        if ($originHost === '' || $requestHost === '' || $originHost !== $requestHost) {
            http_response_code(403);
            exit;
        }
    }
}

function jomu_table_exists(mysqli $conn, string $tableName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return false;
    }

    $safeTable = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result && $result->num_rows > 0;
}

function jomu_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
        return false;
    }

    $safeColumn = $conn->real_escape_string($columnName);
    $result = $conn->query("SHOW COLUMNS FROM {$tableName} LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function jomu_ensure_admin_schema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS admin_users (
            admin_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(120) NOT NULL DEFAULT 'JoMu Admin',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS admin_password_resets (
            reset_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_password_resets_token (token_hash),
            INDEX idx_admin_password_resets_admin (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS admin_logs (
            log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NULL,
            action VARCHAR(80) NOT NULL,
            target_type VARCHAR(60) NOT NULL,
            target_id INT UNSIGNED NULL,
            details TEXT NULL,
            ip_address VARCHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_logs_target (target_type, target_id),
            INDEX idx_admin_logs_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS admin_terminated_users (
            terminated_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            businessname VARCHAR(255) NULL,
            emailormobilenumber VARCHAR(255) NULL,
            reason VARCHAR(255) NULL,
            terminated_by_admin_id INT UNSIGNED NULL,
            terminated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_terminated_users_user (user_id),
            INDEX idx_admin_terminated_users_at (terminated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    if (!jomu_column_exists($conn, 'listings', 'moderation_status')) {
        $conn->query("ALTER TABLE listings ADD COLUMN moderation_status VARCHAR(20) NOT NULL DEFAULT 'visible' AFTER listing_type");
    }
    if (!jomu_column_exists($conn, 'listings', 'hidden_reason')) {
        $conn->query("ALTER TABLE listings ADD COLUMN hidden_reason VARCHAR(255) NULL AFTER moderation_status");
    }
    if (!jomu_column_exists($conn, 'listings', 'hidden_at')) {
        $conn->query("ALTER TABLE listings ADD COLUMN hidden_at DATETIME NULL AFTER hidden_reason");
    }
    if (!jomu_column_exists($conn, 'listings', 'hidden_by_admin_id')) {
        $conn->query("ALTER TABLE listings ADD COLUMN hidden_by_admin_id INT UNSIGNED NULL AFTER hidden_at");
    }
    if (!jomu_column_exists($conn, 'listings', 'admin_reviewed_at')) {
        $conn->query("ALTER TABLE listings ADD COLUMN admin_reviewed_at DATETIME NULL AFTER hidden_by_admin_id");
    }
    if (!jomu_column_exists($conn, 'listings', 'admin_purged_at')) {
        $conn->query("ALTER TABLE listings ADD COLUMN admin_purged_at DATETIME NULL AFTER admin_reviewed_at");
    }

    if (!jomu_column_exists($conn, 'users', 'account_status')) {
        $conn->query("ALTER TABLE users ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT 'active'");
    }
    if (!jomu_column_exists($conn, 'users', 'inactive_until')) {
        $conn->query("ALTER TABLE users ADD COLUMN inactive_until DATETIME NULL");
    }
    if (!jomu_column_exists($conn, 'users', 'status_reason')) {
        $conn->query("ALTER TABLE users ADD COLUMN status_reason VARCHAR(255) NULL");
    }
    if (!jomu_column_exists($conn, 'users', 'inactive_since')) {
        $conn->query("ALTER TABLE users ADD COLUMN inactive_since DATETIME NULL");
    }
    if (!jomu_column_exists($conn, 'users', 'terminated_at')) {
        $conn->query("ALTER TABLE users ADD COLUMN terminated_at DATETIME NULL AFTER inactive_since");
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS business_messages (
            message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            sender_user_id INT UNSIGNED NOT NULL,
            receiver_user_id INT UNSIGNED NOT NULL,
            message_type VARCHAR(20) NOT NULL DEFAULT 'text',
            message_text TEXT NULL,
            media_path VARCHAR(255) NULL,
            reply_to_message_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sender_created (sender_user_id, created_at),
            INDEX idx_receiver_created (receiver_user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    if (!jomu_column_exists($conn, 'business_messages', 'is_system_message')) {
        $conn->query("ALTER TABLE business_messages ADD COLUMN is_system_message TINYINT(1) NOT NULL DEFAULT 0 AFTER reply_to_message_id");
    }
    if (!jomu_column_exists($conn, 'business_messages', 'admin_message_batch_id')) {
        $conn->query("ALTER TABLE business_messages ADD COLUMN admin_message_batch_id INT UNSIGNED NULL AFTER is_system_message");
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS admin_message_batches (
            batch_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id INT UNSIGNED NULL,
            message_text TEXT NOT NULL,
            recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_message_batches_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS bulk_order_posts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            business_name VARCHAR(255) NOT NULL,
            profilepic VARCHAR(500) NOT NULL DEFAULT '/assets/images/profile.png',
            content TEXT NOT NULL,
            fulfilled TINYINT(1) NOT NULL DEFAULT 0,
            fulfilled_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bulk_order_posts_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    if (!jomu_column_exists($conn, 'bulk_order_posts', 'moderation_status')) {
        $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN moderation_status VARCHAR(20) NOT NULL DEFAULT 'visible' AFTER fulfilled_at");
    }
    if (!jomu_column_exists($conn, 'bulk_order_posts', 'hidden_reason')) {
        $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN hidden_reason VARCHAR(255) NULL AFTER moderation_status");
    }
    if (!jomu_column_exists($conn, 'bulk_order_posts', 'hidden_at')) {
        $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN hidden_at DATETIME NULL AFTER hidden_reason");
    }
    if (!jomu_column_exists($conn, 'bulk_order_posts', 'hidden_by_admin_id')) {
        $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN hidden_by_admin_id INT UNSIGNED NULL AFTER hidden_at");
    }
    if (!jomu_column_exists($conn, 'bulk_order_posts', 'admin_reviewed_at')) {
        $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN admin_reviewed_at DATETIME NULL AFTER hidden_by_admin_id");
    }
    if (!jomu_column_exists($conn, 'bulk_order_posts', 'admin_purged_at')) {
        $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN admin_purged_at DATETIME NULL AFTER admin_reviewed_at");
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS site_links (
            link_key VARCHAR(40) PRIMARY KEY,
            label VARCHAR(80) NOT NULL,
            url VARCHAR(500) NOT NULL DEFAULT '',
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS site_assets (
            asset_key VARCHAR(80) PRIMARY KEY,
            label VARCHAR(160) NOT NULL,
            asset_type VARCHAR(20) NOT NULL DEFAULT 'other',
            page VARCHAR(255) NOT NULL,
            path VARCHAR(500) NOT NULL,
            updated_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_site_assets_type (asset_type),
            INDEX idx_site_assets_page (page)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $defaultLinks = [
        'app' => 'JoMu Application (components/nav.php)',
        'facebook' => 'Facebook (components/nav.php, /, /support)',
        'instagram' => 'Instagram (components/nav.php, /, /support)',
        'tiktok' => 'Tiktok (components/nav.php, /, /support)',
        'x' => 'X (components/nav.php, /, /support)',
        'support_email' => 'Support email (/support)',
        'privacy_email' => 'Privacy policy email (/privacy-policy)',
        'support_phone' => 'Support phone call (/support)',
        'support_whatsapp' => 'Support WhatsApp (/support)',
    ];
    $defaultLinkUrls = [
        'support_email' => 'jomumarket@email.com',
        'privacy_email' => 'ContactJoMu@gmail.com',
        'support_phone' => '+256 708973632',
        'support_whatsapp' => '+256 708973632',
    ];
    foreach ($defaultLinks as $key => $label) {
        $defaultUrl = (string) ($defaultLinkUrls[$key] ?? '');
        $stmt = $conn->prepare("INSERT IGNORE INTO site_links (link_key, label, url) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sss', $key, $label, $defaultUrl);
            $stmt->execute();
            $stmt->close();
        }

        $labelStmt = $conn->prepare("UPDATE site_links SET label = ? WHERE link_key = ?");
        if ($labelStmt) {
            $labelStmt->bind_param('ss', $label, $key);
            $labelStmt->execute();
            $labelStmt->close();
        }
    }
}

function jomu_purge_expired_hidden_listings(mysqli $conn, int $adminId = 0): int
{
    if (!function_exists('jomu_delete_listing_completely')) {
        return 0;
    }

    $stmt = $conn->prepare(
        "SELECT listing_id, stockname
         FROM listings
         WHERE COALESCE(moderation_status, 'visible') = 'hidden'
           AND admin_purged_at IS NULL
           AND hidden_at IS NOT NULL
           AND hidden_at <= DATE_SUB(NOW(), INTERVAL 14 DAY)"
    );
    if (!$stmt) {
        return 0;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $expiredListings = [];
    while ($row = $result->fetch_assoc()) {
        $expiredListings[] = [
            'listing_id' => (int) ($row['listing_id'] ?? 0),
            'stockname' => (string) ($row['stockname'] ?? ''),
        ];
    }
    $stmt->close();

    $deletedCount = 0;
    foreach ($expiredListings as $listing) {
        $listingId = (int) $listing['listing_id'];
        if ($listingId <= 0) {
            continue;
        }
        if (jomu_delete_listing_completely($conn, $listingId)) {
            $deletedCount++;
            if (function_exists('jomu_admin_log')) {
                jomu_admin_log($conn, $adminId, 'auto_purge_hidden_listing', 'listing', $listingId, $listing['stockname']);
            }
        }
    }

    return $deletedCount;
}

/**
 * URL path prefix for the site root when the app lives in a subdirectory
 * (e.g. "/JoMu%20Website" from SCRIPT_NAME .../php/admin/dashboard.php).
 */
function jomu_web_app_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script === '' || !preg_match('#/php/admin/#', $script)) {
        return '';
    }
    $base = preg_replace('#/php/admin/.*$#', '', $script);
    $base = rtrim((string) $base, '/');

    return $base === '/' ? '' : $base;
}

/**
 * Encode each path segment (spaces etc.) for use in HTML src/href.
 */
function jomu_encode_url_path_segments(string $path): string
{
    $path = trim($path);
    if ($path === '' || preg_match('#^[a-z][a-z0-9+\-.]*://#i', $path)) {
        return $path;
    }
    $isAbs = str_starts_with($path, '/');
    $trimmed = trim($path, '/');
    if ($trimmed === '') {
        return $isAbs ? '/' : '';
    }
    $segments = explode('/', $trimmed);
    $encoded = [];
    foreach ($segments as $segment) {
        $encoded[] = rawurlencode(rawurldecode($segment));
    }

    return ($isAbs ? '/' : '') . implode('/', $encoded);
}

/**
 * Absolute-from-host-root path for public assets (fixes relative URLs when the current page is under /php/admin/).
 */
function jomu_public_site_path(string $siteRelativePath): string
{
    $siteRelativePath = str_replace('\\', '/', ltrim($siteRelativePath, '/'));
    $prefix = jomu_web_app_base_path();
    $prefix = rtrim($prefix, '/');
    if ($prefix === '' || $prefix === '/') {
        return '/' . $siteRelativePath;
    }

    return $prefix . '/' . $siteRelativePath;
}

/**
 * Public URL for listing media, profile images, or root-level assets (videos under assets/).
 */
function jomu_admin_media_public_url(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return jomu_encode_url_path_segments(jomu_public_site_path('/assets/images/profile.png'));
    }
    if (str_starts_with($path, '/') || preg_match('#^https?://#i', $path)) {
        return jomu_encode_url_path_segments($path);
    }
    $path = str_replace('\\', '/', ltrim($path, '/'));
    while (str_starts_with($path, '../')) {
        $path = substr($path, 3);
    }
    if (str_starts_with($path, 'assets/')) {
        return jomu_encode_url_path_segments(jomu_public_site_path($path));
    }

    return jomu_encode_url_path_segments(jomu_public_site_path('php/' . $path));
}

function jomu_admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['admin_csrf_token'];
}

function jomu_require_admin_csrf(): void
{
    if (function_exists('jomu_reject_cross_site_request')) {
        jomu_reject_cross_site_request();
    }

    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || empty($_SESSION['admin_csrf_token']) || !hash_equals((string) $_SESSION['admin_csrf_token'], $token)) {
        http_response_code(403);
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Admin security token expired. Please refresh and try again.']);
            exit;
        }
        exit('Invalid admin security token.');
    }
}

function jomu_current_admin(mysqli $conn): ?array
{
    $adminId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($adminId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT admin_id, email, name FROM admin_users WHERE admin_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $admin ?: null;
}

function jomu_require_admin(mysqli $conn): array
{
    $admin = jomu_current_admin($conn);
    if (!$admin) {
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Admin session expired. Please sign in again.']);
            exit;
        }
        header('Location: login.php');
        exit;
    }

    return $admin;
}

function jomu_admin_log(mysqli $conn, ?int $adminId, string $action, string $targetType, ?int $targetId, string $details = ''): void
{
    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    $stmt = $conn->prepare(
        "INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('ississ', $adminId, $action, $targetType, $targetId, $details, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

function jomu_system_user_id(mysqli $conn): int
{
    $systemEmail = 'system@jomu.local';
    $stmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $systemEmail);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && (int) ($row['id'] ?? 0) > 0) {
            return (int) $row['id'];
        }
    }

    $businessName = 'JoMu';
    $password = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
    $profilePic = '../assets/images/JoMu logo redesigned.png';
    $insert = $conn->prepare("INSERT INTO users (businessname, emailormobilenumber, password, profilepic) VALUES (?, ?, ?, ?)");
    if ($insert) {
        $insert->bind_param('ssss', $businessName, $systemEmail, $password, $profilePic);
        $insert->execute();
        $newId = (int) $insert->insert_id;
        $insert->close();
        if ($newId > 0) {
            return $newId;
        }
    }

    return 0;
}

function jomu_send_system_message(mysqli $conn, int $receiverUserId, string $messageText, ?int $adminMessageBatchId = null): bool
{
    if ($receiverUserId <= 0 || trim($messageText) === '') {
        return false;
    }

    jomu_ensure_admin_schema($conn);
    $senderUserId = jomu_system_user_id($conn);
    if ($senderUserId <= 0 || $senderUserId === $receiverUserId) {
        return false;
    }

    $messageType = 'text';
    $mediaPath = null;
    $replyTo = null;
    $isSystem = 1;
    $stmt = $conn->prepare(
        "INSERT INTO business_messages (sender_user_id, receiver_user_id, message_type, message_text, media_path, reply_to_message_id, is_system_message, admin_message_batch_id, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        return false;
    }

    $batchId = $adminMessageBatchId !== null && $adminMessageBatchId > 0 ? $adminMessageBatchId : null;
    $stmt->bind_param('iisssiii', $senderUserId, $receiverUserId, $messageType, $messageText, $mediaPath, $replyTo, $isSystem, $batchId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function jomu_listing_url(int $listingId): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host . '/purchase-wholesale?listing_id=' . $listingId . '&owner_view=1';
}
