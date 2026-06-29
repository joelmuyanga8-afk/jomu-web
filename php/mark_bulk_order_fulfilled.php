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
$postId = (int) ($data['post_id'] ?? 0);

if ($postId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid post id']);
    exit;
}

$emailOrMobile = trim((string) ($_SESSION['emailormobilenumber'] ?? ''));
if ($emailOrMobile === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => jomu_not_signed_in_message()]);
    exit;
}

$userStmt = $conn->prepare("SELECT id, businessname FROM users WHERE emailormobilenumber = ? LIMIT 1");
if (!$userStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to verify business owner']);
    exit;
}

$userStmt->bind_param('s', $emailOrMobile);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$viewerUserId = (int) ($userRow['id'] ?? 0);
$viewerBusinessName = trim((string) ($userRow['businessname'] ?? ''));

if ($viewerUserId <= 0 && $viewerBusinessName === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Business owner not found']);
    exit;
}

$postStmt = $conn->prepare("SELECT id, user_id, business_name, fulfilled FROM bulk_order_posts WHERE id = ? LIMIT 1");
if (!$postStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to load post']);
    exit;
}

$postStmt->bind_param('i', $postId);
$postStmt->execute();
$post = $postStmt->get_result()->fetch_assoc();
$postStmt->close();

if (!$post) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Post not found']);
    exit;
}

$ownerUserId = (int) ($post['user_id'] ?? 0);
$ownerBusinessName = trim((string) ($post['business_name'] ?? ''));
$isOwner = ($viewerUserId > 0 && $ownerUserId > 0 && $viewerUserId === $ownerUserId)
    || ($viewerBusinessName !== '' && $ownerBusinessName !== '' && strcasecmp($viewerBusinessName, $ownerBusinessName) === 0);

if (!$isOwner) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unable to update this post']);
    exit;
}

$isCurrentlyFulfilled = (int) ($post['fulfilled'] ?? 0) === 1;
$nextFulfilled = $isCurrentlyFulfilled ? 0 : 1;
$updateSql = $nextFulfilled === 1
    ? "UPDATE bulk_order_posts SET fulfilled = 1, fulfilled_at = NOW() WHERE id = ? LIMIT 1"
    : "UPDATE bulk_order_posts SET fulfilled = 0, fulfilled_at = NULL WHERE id = ? LIMIT 1";

$updateStmt = $conn->prepare($updateSql);
if (!$updateStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to update post']);
    exit;
}

$updateStmt->bind_param('i', $postId);
$ok = $updateStmt->execute();
$updateStmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => $nextFulfilled === 1 ? 'Unable to mark as fulfilled' : 'Unable to undo fulfilled status'
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'post_id' => $postId,
    'fulfilled' => $nextFulfilled === 1,
]);
