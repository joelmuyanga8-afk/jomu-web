<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$admin = jomu_require_admin($conn);
jomu_require_admin_csrf();

$returnTo = trim((string) ($_POST['return_to'] ?? 'dashboard.php?page=links'));
if ($returnTo === '' || preg_match('/^https?:\/\//i', $returnTo) || str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
    $returnTo = 'dashboard.php?page=links';
}

$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$finish = static function (string $message, bool $ok = true) use ($returnTo, $isAjax): void {
    if ($isAjax) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }
    header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'message=' . rawurlencode($message));
    exit;
};

$key = strtolower(trim((string) ($_POST['link_key'] ?? '')));
$url = trim((string) ($_POST['url'] ?? ''));
$urlKeys = ['app', 'facebook', 'instagram', 'tiktok', 'x'];
$contactKeys = ['support_email', 'privacy_email', 'support_phone', 'support_whatsapp'];
if (!in_array($key, array_merge($urlKeys, $contactKeys), true)) {
    $finish('Unknown link.', false);
}
if (in_array($key, $urlKeys, true) && $url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
    $finish('Please enter a full valid URL, starting with https://', false);
}
$urlScheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
if (in_array($key, $urlKeys, true) && $url !== '' && !in_array($urlScheme, ['http', 'https'], true)) {
    $finish('Please enter a full valid URL, starting with https://', false);
}
if (in_array($key, ['support_email', 'privacy_email'], true) && $url !== '' && !filter_var($url, FILTER_VALIDATE_EMAIL)) {
    $finish('Please enter a valid email address.', false);
}

$stmt = $conn->prepare("UPDATE site_links SET url = ?, updated_at = NOW() WHERE link_key = ?");
if (!$stmt) {
    $finish('Unable to update link.', false);
}
$stmt->bind_param('ss', $url, $key);
$stmt->execute();
$stmt->close();

jomu_admin_log($conn, (int) $admin['admin_id'], 'update_site_link', 'site_link', null, $key);
$finish('Link updated.');
