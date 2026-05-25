<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$admin = jomu_require_admin($conn);
jomu_require_admin_csrf();

$returnTo = trim((string) ($_POST['return_to'] ?? 'dashboard.php?page=ads'));
if ($returnTo === '' || preg_match('/^https?:\/\//i', $returnTo) || str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
    $returnTo = 'dashboard.php?page=ads';
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

$allowedPaths = [
    'assets/videos/Over Navbar JoMu 70px.mp4',
    'assets/videos/Over Navbar JoMu 1080px.mp4',
    'assets/videos/JoMu animation large_screens.mp4',
    'assets/videos/JoMu animation.mp4',
    'assets/videos/JoMu Animation small_screens.mp4',
    'assets/images/about us.jpg',
    'assets/images/JoMu logo redesigned.png',
    'assets/images/JoMu laptop 3-1.png',
    'assets/images/JoMu Phone 2.png',
    'assets/images/JoMu laptop 1.png',
    'assets/images/JoMu Phone 1.png',
    'assets/images/JoMu laptop 2.png',
    'assets/images/JoMu Screenshots-lg-1.png',
    'assets/images/JoMu Screenshot-sm-1.png',
    'assets/images/JoMu Screenshots-lg-2.png',
    'assets/images/JoMu Screenshot-sm-2.png',
    'assets/images/JoMu Screenshots-lg-3.png',
    'assets/images/JoMu Screenshot-sm-3.png',
    'assets/images/JoMu Screenshot-sm-4.png',
];

$assetPath = str_replace('\\', '/', trim((string) ($_POST['asset_path'] ?? '')));
if (!in_array($assetPath, $allowedPaths, true)) {
    $finish('Unknown platform asset.', false);
}

if (empty($_FILES['asset_file']) || !is_array($_FILES['asset_file'])) {
    $finish('Choose an image or video first.', false);
}

$file = $_FILES['asset_file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $finish('Upload failed. Please choose another file.', false);
}
if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
    $finish('Asset file is too large.', false);
}

$targetAbsolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
$targetRealDir = realpath(dirname($targetAbsolute));
$rootReal = realpath(dirname(__DIR__, 2));
if (!$targetRealDir || !$rootReal || strpos($targetRealDir, $rootReal) !== 0) {
    $finish('Asset location is not writable.', false);
}

$expectedType = str_starts_with($assetPath, 'assets/videos/') ? 'video/' : 'image/';
$tmpPath = (string) ($file['tmp_name'] ?? '');
$mime = '';
if (is_file($tmpPath)) {
    $info = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $info->file($tmpPath);
}
if ($mime === '' || strpos($mime, $expectedType) !== 0) {
    $finish($expectedType === 'video/' ? 'Please upload a video for this asset.' : 'Please upload an image for this asset.', false);
}

if (!move_uploaded_file($tmpPath, $targetAbsolute)) {
    $finish('Unable to save the new asset file.', false);
}

$stmt = $conn->prepare(
    "INSERT INTO site_assets (asset_key, label, asset_type, page, path, updated_at)
     VALUES (?, ?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE path = VALUES(path), updated_at = NOW()"
);
if ($stmt) {
    $assetKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($assetPath));
    $label = basename($assetPath);
    $assetType = $expectedType === 'video/' ? 'video' : 'image';
    $page = 'Platform asset';
    $stmt->bind_param('sssss', $assetKey, $label, $assetType, $page, $assetPath);
    $stmt->execute();
    $stmt->close();
}

jomu_admin_log($conn, (int) $admin['admin_id'], 'update_site_asset', 'site_asset', null, $assetPath);
$finish('Asset updated.');
