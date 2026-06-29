<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Invalid request method']);
    exit;
}

jomu_require_csrf();

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

$fulfilledColumn = $conn->query("SHOW COLUMNS FROM bulk_order_posts LIKE 'fulfilled'");
if ($fulfilledColumn && $fulfilledColumn->num_rows === 0) {
    $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN fulfilled TINYINT(1) NOT NULL DEFAULT 0 AFTER content");
}

$fulfilledAtColumn = $conn->query("SHOW COLUMNS FROM bulk_order_posts LIKE 'fulfilled_at'");
if ($fulfilledAtColumn && $fulfilledAtColumn->num_rows === 0) {
    $conn->query("ALTER TABLE bulk_order_posts ADD COLUMN fulfilled_at DATETIME NULL DEFAULT NULL AFTER fulfilled");
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
$content = trim((string) ($data['content'] ?? ''));

if ($content === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Empty content']);
    exit;
}

if (mb_strlen($content) > 2000) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Content too long']);
    exit;
}

$emailOrMobile = trim((string) ($_SESSION['emailormobilenumber'] ?? ''));
if ($emailOrMobile === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => jomu_not_signed_in_message()]);
    exit;
}

$defaultName = 'Guest Business';
$defaultProfile = '/assets/images/profile.png';
$userId = null;
$businessName = $defaultName;
$profilepic = $defaultProfile;

$userStmt = $conn->prepare("SELECT id, businessname, profilepic FROM users WHERE emailormobilenumber = ? LIMIT 1");
if ($userStmt) {
    $userStmt->bind_param('s', $emailOrMobile);
    $userStmt->execute();
    $userRow = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!empty($userRow)) {
        $userId = (int) ($userRow['id'] ?? 0);
        $candidateName = trim((string) ($userRow['businessname'] ?? ''));
        $candidatePic = trim((string) ($userRow['profilepic'] ?? ''));
        if ($candidateName !== '') {
            $businessName = $candidateName;
        }
        if ($candidatePic !== '') {
            $profilepic = $candidatePic;
        }
    }
}

$insertStmt = $conn->prepare("INSERT INTO bulk_order_posts (user_id, business_name, profilepic, content, created_at) VALUES (?, ?, ?, ?, NOW())");
if (!$insertStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to prepare insert']);
    exit;
}

$userIdParam = $userId > 0 ? $userId : null;
$insertStmt->bind_param('isss', $userIdParam, $businessName, $profilepic, $content);
$ok = $insertStmt->execute();
$newId = (int) $insertStmt->insert_id;
$insertStmt->close();

if (!$ok || $newId <= 0) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to create post']);
    exit;
}

$postStmt = $conn->prepare("SELECT id, user_id, business_name, profilepic, content, fulfilled, created_at FROM bulk_order_posts WHERE id = ? LIMIT 1");
if (!$postStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to fetch post']);
    exit;
}
$postStmt->bind_param('i', $newId);
$postStmt->execute();
$post = $postStmt->get_result()->fetch_assoc();
$postStmt->close();

if (!$post) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Post not found']);
    exit;
}

echo json_encode([
    'ok' => true,
    'post' => [
        'id' => (int) ($post['id'] ?? 0),
        'user_id' => (int) ($post['user_id'] ?? 0),
        'business_name' => trim((string) ($post['business_name'] ?? '')),
        'profilepic' => trim((string) ($post['profilepic'] ?? '')),
        'content' => trim((string) ($post['content'] ?? '')),
        'fulfilled' => (int) ($post['fulfilled'] ?? 0) === 1,
        'created_at' => (string) ($post['created_at'] ?? ''),
        'is_hidden' => false,
    ],
]);
