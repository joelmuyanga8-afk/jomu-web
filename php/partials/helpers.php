<?php

function getMediaType($media): string {
    $extension = strtolower(pathinfo($media, PATHINFO_EXTENSION));
    $videoExtensions = [
        'mp4', 'mpeg', 'mpg', 'avi', '3gp', '3g2', 'mov', 'mkv', 'webm',
        'wmv', 'flv', 'm4v', 'ogv', 'ts', 'mts', 'm2ts'
    ];

    if (in_array($extension, $videoExtensions, true)) {
        return 'video';
    }

    return 'img';
}

function getMediaPath($media, $base = '') {
    $media = trim((string) $media);
    if ($media === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $media) || str_starts_with($media, 'data:') || str_starts_with($media, 'blob:')) {
        return $media;
    }

    $normalized = str_replace('\\', '/', $media);
    if (str_starts_with($normalized, '/')) {
        return $normalized;
    }
    if (str_starts_with($normalized, 'assets/')) {
        return '/' . ltrim($normalized, '/');
    }
    if (str_starts_with($normalized, 'php/')) {
        return '/' . ltrim($normalized, '/');
    }
    if (str_starts_with($normalized, 'uploads/')) {
        return '/php/' . ltrim($normalized, '/');
    }

    $base = str_replace('\\', '/', (string) $base);
    if ($base !== '') {
        if (!str_ends_with($base, '/')) {
            $base .= '/';
        }
        return $base . ltrim($normalized, '/');
    }

    if (!str_contains($normalized, '/')) {
        return '/php/uploads/profile/' . $normalized;
    }

    return '/php/' . ltrim($normalized, '/');
}

function ensureListingGalleryTable(mysqli $conn): bool
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS listing_gallery_images (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            listing_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_listing_gallery_images_listing_id (listing_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    return $conn->errno === 0;
}

function getListingGalleryImages(mysqli $conn, int $listingId): array
{
    if ($listingId <= 0) {
        return [];
    }

    ensureListingGalleryTable($conn);

    $stmt = $conn->prepare(
        "SELECT image_path
         FROM listing_gallery_images
         WHERE listing_id = ?
         ORDER BY sort_order ASC, id ASC"
    );
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $listingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $images = [];

    while ($row = $result->fetch_assoc()) {
        $imagePath = trim((string) ($row['image_path'] ?? ''));
        if ($imagePath !== '') {
            $images[] = $imagePath;
        }
    }

    $stmt->close();

    return $images;
}

function formatListingViewsLabel($views): string {
    $count = max(0, (int) $views);

    if ($count < 100) {
        return $count . ' Views';
    }

    if ($count < 1000) {
        return ((int) floor($count / 100) * 100) . '+ Views';
    }

    if ($count < 1000000) {
        return ((int) floor($count / 1000)) . 'k+ Views';
    }

    return ((int) floor($count / 1000000)) . 'M+ Views';
}

function formatMoneyNumberPart(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $normalized = str_replace([',', ' '], '', $trimmed);
    if (!preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
        return $trimmed;
    }

    $negative = str_starts_with($normalized, '-');
    if ($negative) {
        $normalized = substr($normalized, 1);
    }

    $parts = explode('.', $normalized, 2);
    $integer = $parts[0] ?? '0';
    $decimal = $parts[1] ?? '';
    $formattedInteger = number_format((int) $integer, 0, '.', ',');
    $formatted = $decimal !== '' ? ($formattedInteger . '.' . $decimal) : $formattedInteger;

    return $negative ? ('-' . $formatted) : $formatted;
}

function formatPriceText(string $value): string
{
    if ($value === '') {
        return '';
    }

    return preg_replace_callback(
        '/(?<![\d,])\d[\d,]*(?:\.\d+)?(?![\d,])/',
        static function (array $matches): string {
            return formatMoneyNumberPart((string) ($matches[0] ?? ''));
        },
        $value
    ) ?? $value;
}

function formatProductPriceRange(string $priceFrom, string $priceTo): string
{
    return 'USh ' . formatMoneyNumberPart($priceFrom) . ' - ' . formatMoneyNumberPart($priceTo) . ' / unit';
}

function jomu_resolve_public_profile_image_path(string $path, string $default = '/assets/images/profile.png'): string
{
    $normalized = str_replace('\\', '/', trim($path));
    if ($normalized === '') {
        return $default;
    }
    if (preg_match('/^(https?:)?\/\//i', $normalized) || str_starts_with($normalized, 'data:') || str_starts_with($normalized, 'blob:')) {
        return $normalized;
    }
    if (str_starts_with($normalized, '/')) {
        return $normalized;
    }
    if (str_starts_with($normalized, 'assets/')) {
        return '/' . ltrim($normalized, '/');
    }
    if (str_starts_with($normalized, 'php/')) {
        return '/' . ltrim($normalized, '/');
    }
    if (str_starts_with($normalized, 'uploads/')) {
        return '/php/' . ltrim($normalized, '/');
    }
    if (!str_contains($normalized, '/')) {
        return '/php/uploads/profile/' . $normalized;
    }
    return '/php/' . ltrim($normalized, '/');
}

function listingTableHasColumn(mysqli $conn, string $columnName): bool
{
    static $cache = [];
    $cacheKey = spl_object_id($conn) . ':' . $columnName;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $safeColumn = preg_replace('/[^a-z0-9_]/', '', strtolower($columnName));
    if ($safeColumn === '') {
        return $cache[$cacheKey] = false;
    }

    $result = $conn->query("SHOW COLUMNS FROM listings LIKE '{$safeColumn}'");
    $hasColumn = $result instanceof mysqli_result && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
        $result->free();
    }

    return $cache[$cacheKey] = $hasColumn;
}

function jomu_listing_public_visibility_filters(mysqli $conn, string $alias = 'l'): string
{
    $parts = [];
    if (listingTableHasColumn($conn, 'moderation_status')) {
        $parts[] = "COALESCE({$alias}.moderation_status, 'visible') <> 'hidden'";
    }
    if (listingTableHasColumn($conn, 'admin_purged_at')) {
        $parts[] = "{$alias}.admin_purged_at IS NULL";
    }

    return $parts === [] ? '' : implode(' AND ', $parts) . ' AND ';
}

function jomu_not_signed_in_message(): string
{
    return !empty($_SESSION['jomu_suspended_browse'])
        ? 'Your account was suspended.'
        : 'Not signed in.';
}

function jomu_is_suspended_browse_session(): bool
{
    return !empty($_SESSION['jomu_suspended_browse']);
}

function jomu_configure_curl_ca_bundle($curlHandle): ?string
{
    $envValue = static function (string $key): string {
        if (function_exists('env_value')) {
            return (string) env_value($key, '');
        }

        $value = getenv($key);
        return $value === false ? '' : (string) $value;
    };

    $candidates = [
        $envValue('CURL_CA_BUNDLE'),
        $envValue('SSL_CERT_FILE'),
        $envValue('GOOGLE_CA_CERT_PATH'),
        (string) ini_get('curl.cainfo'),
        (string) ini_get('openssl.cafile'),
        __DIR__ . '/../certs/cacert.pem',
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }

        $paths = [$candidate];
        if (!preg_match('/^(?:[a-z]:[\\\\\/]|\/)/i', $candidate)) {
            $paths[] = __DIR__ . '/../../' . ltrim($candidate, '/\\');
        }

        foreach ($paths as $path) {
            if (is_file($path)) {
                curl_setopt($curlHandle, CURLOPT_CAINFO, $path);
                return $path;
            }
        }
    }

    if (PHP_OS_FAMILY === 'Windows' && defined('CURLOPT_SSL_OPTIONS') && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($curlHandle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        return 'Windows native CA store';
    }

    $fallbackCandidates = [
        'C:/wamp64/apps/phpmyadmin5.2.1/vendor/composer/ca-bundle/res/cacert.pem',
        'C:/wamp64/wamp64/apps/phpmyadmin5.2.1/vendor/composer/ca-bundle/res/cacert.pem',
        'C:/xampp/php/extras/ssl/cacert.pem',
    ];

    foreach ($fallbackCandidates as $candidate) {
        if (is_file($candidate)) {
            curl_setopt($curlHandle, CURLOPT_CAINFO, $candidate);
            return $candidate;
        }
    }

    return null;
}

function jomu_csrf_token(string $scope = 'user'): string
{
    $sessionKey = 'jomu_csrf_' . preg_replace('/[^a-z0-9_]/i', '', $scope);
    if (empty($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[$sessionKey];
}

function jomu_require_csrf(string $scope = 'user'): void
{
    jomu_reject_cross_site_request();

    $token = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $sessionKey = 'jomu_csrf_' . preg_replace('/[^a-z0-9_]/i', '', $scope);

    if ($token === '' || empty($_SESSION[$sessionKey]) || !hash_equals((string) $_SESSION[$sessionKey], $token)) {
        http_response_code(403);
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Security token expired. Please refresh and try again.']);
            exit;
        }
        exit('Security token expired. Please refresh and try again.');
    }
}

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

function jomu_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
}

function jomu_rate_limit_key(string $scope, string $identity = ''): string
{
    $raw = $scope . ':' . ($identity !== '' ? $identity : jomu_client_ip());
    return preg_replace('/[^a-z0-9_.:-]/i', '_', strtolower($raw));
}

function jomu_rate_limit_check(string $scope, int $maxAttempts, int $windowSeconds, string $identity = ''): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $key = jomu_rate_limit_key($scope, $identity);
    $now = time();
    $_SESSION['jomu_rate_limits'] = $_SESSION['jomu_rate_limits'] ?? [];
    $entry = $_SESSION['jomu_rate_limits'][$key] ?? ['count' => 0, 'reset_at' => $now + $windowSeconds];

    if ((int) ($entry['reset_at'] ?? 0) <= $now) {
        $entry = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    $entry['count'] = (int) ($entry['count'] ?? 0) + 1;
    $_SESSION['jomu_rate_limits'][$key] = $entry;

    return $entry['count'] <= $maxAttempts;
}

function jomu_require_rate_limit(string $scope, int $maxAttempts, int $windowSeconds, string $message, string $identity = ''): void
{
    if (jomu_rate_limit_check($scope, $maxAttempts, $windowSeconds, $identity)) {
        return;
    }

    http_response_code(429);
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'ok' => false, 'message' => $message]);
        exit;
    }

    exit($message);
}

function jomu_is_reserved_business_name(string $businessName): bool
{
    return trim($businessName) === 'JoMu';
}

function jomu_delete_listing_media_if_safe(string $mediaPath): bool
{
    $trimmed = trim($mediaPath);
    if ($trimmed === '') {
        return false;
    }

    $allowedRoots = [];
    $uploadsInPhp = realpath(__DIR__ . '/../uploads');
    if ($uploadsInPhp !== false) {
        $allowedRoots[] = $uploadsInPhp;
    }
    $uploadsAtWebRoot = realpath(__DIR__ . '/../..' . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsAtWebRoot !== false) {
        $allowedRoots[] = $uploadsAtWebRoot;
    }

    $candidates = [];
    if (preg_match('/^[a-zA-Z]:\\\\/', $trimmed) === 1 || str_starts_with($trimmed, '/')) {
        $candidates[] = $trimmed;
    } else {
        $candidates[] = __DIR__ . '/../' . $trimmed;
        $candidates[] = __DIR__ . '/../../' . $trimmed;
    }

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            continue;
        }
        foreach ($allowedRoots as $root) {
            $normalizedPath = str_replace('\\', '/', $resolved);
            $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
            if ($normalizedPath === $normalizedRoot || str_starts_with($normalizedPath, $normalizedRoot . '/')) {
                return @unlink($resolved);
            }
        }
    }

    return false;
}

function jomu_listing_media_is_referenced_elsewhere(mysqli $conn, string $mediaPath, int $deletedListingId): bool
{
    $mediaPath = trim($mediaPath);
    if ($mediaPath === '') {
        return false;
    }

    $listingRefStmt = $conn->prepare('SELECT COUNT(*) AS total FROM listings WHERE media = ? AND listing_id <> ? LIMIT 1');
    if ($listingRefStmt) {
        $listingRefStmt->bind_param('si', $mediaPath, $deletedListingId);
        $listingRefStmt->execute();
        $listingRefRow = $listingRefStmt->get_result()->fetch_assoc();
        $listingRefStmt->close();
        if ((int) ($listingRefRow['total'] ?? 0) > 0) {
            return true;
        }
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'listing_gallery_images'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return false;
    }

    $galleryRefStmt = $conn->prepare(
        'SELECT COUNT(*) AS total FROM listing_gallery_images WHERE image_path = ? AND listing_id <> ? LIMIT 1'
    );
    if (!$galleryRefStmt) {
        return false;
    }
    $galleryRefStmt->bind_param('si', $mediaPath, $deletedListingId);
    $galleryRefStmt->execute();
    $galleryRefRow = $galleryRefStmt->get_result()->fetch_assoc();
    $galleryRefStmt->close();

    return (int) ($galleryRefRow['total'] ?? 0) > 0;
}

function jomu_delete_listing_table_rows_if_exists(mysqli $conn, string $tableName, int $listingId): void
{
    if ($listingId <= 0 || !preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return;
    }

    $escapedTable = $conn->real_escape_string($tableName);
    $tableCheck = $conn->query("SHOW TABLES LIKE '{$escapedTable}'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $stmt = $conn->prepare("DELETE FROM {$tableName} WHERE listing_id = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $listingId);
    $stmt->execute();
    $stmt->close();
}

function jomu_delete_listing_completely(mysqli $conn, int $listingId): bool
{
    if ($listingId <= 0) {
        return false;
    }

    $listingStmt = $conn->prepare('SELECT media FROM listings WHERE listing_id = ? LIMIT 1');
    if (!$listingStmt) {
        return false;
    }
    $listingStmt->bind_param('i', $listingId);
    $listingStmt->execute();
    $listing = $listingStmt->get_result()->fetch_assoc();
    $listingStmt->close();
    if (!$listing) {
        return false;
    }

    $mediaPath = (string) ($listing['media'] ?? '');
    $galleryMediaPaths = getListingGalleryImages($conn, $listingId);

    $deleteStmt = $conn->prepare('DELETE FROM listings WHERE listing_id = ? LIMIT 1');
    if (!$deleteStmt) {
        return false;
    }
    $deleteStmt->bind_param('i', $listingId);
    $deleteStmt->execute();
    $deleted = $deleteStmt->affected_rows > 0;
    $deleteStmt->close();
    if (!$deleted) {
        return false;
    }

    if ($mediaPath !== '' && !jomu_listing_media_is_referenced_elsewhere($conn, $mediaPath, $listingId)) {
        jomu_delete_listing_media_if_safe($mediaPath);
    }
    foreach ($galleryMediaPaths as $galleryMediaPath) {
        $galleryMediaPath = (string) $galleryMediaPath;
        if ($galleryMediaPath !== '' && !jomu_listing_media_is_referenced_elsewhere($conn, $galleryMediaPath, $listingId)) {
            jomu_delete_listing_media_if_safe($galleryMediaPath);
        }
    }

    jomu_delete_listing_table_rows_if_exists($conn, 'listing_gallery_images', $listingId);
    jomu_delete_listing_table_rows_if_exists($conn, 'profile_pinned_listings', $listingId);
    jomu_delete_listing_table_rows_if_exists($conn, 'listing_view_stats', $listingId);

    return true;
}

function jomu_page_url(string $page): string
{
    static $routes = [
        'home' => '/',
        'profile' => '/profile',
        'dashboard' => '/business-vendor-dashboard',
        'add-listing' => '/add-new-listing',
        'visitor-profile' => '/visitor-profile',
        'purchase-wholesale' => '/purchase-wholesale',
    ];

    return $routes[$page] ?? '/';
}

function jomu_php_url(string $script): string
{
    $script = ltrim(str_replace('\\', '/', $script), '/');
    if ($script === '') {
        return '/php/';
    }
    if (!str_starts_with($script, 'php/')) {
        $script = 'php/' . $script;
    }

    return '/' . $script;
}
