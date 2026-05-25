<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/helpers.php';
require __DIR__ . '/partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

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
        profilepic VARCHAR(500) NOT NULL DEFAULT 'assets/images/profile.png',
        content TEXT NOT NULL,
        fulfilled TINYINT(1) NOT NULL DEFAULT 0,
        fulfilled_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_bulk_order_posts_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$conn->query(
    "CREATE TABLE IF NOT EXISTS bulk_order_post_likes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        post_id INT UNSIGNED NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_bulk_order_post_like (post_id, user_id),
        INDEX idx_bulk_order_post_likes_post_id (post_id),
        INDEX idx_bulk_order_post_likes_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

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

$userStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
if (!$userStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to verify user']);
    exit;
}

$userStmt->bind_param('s', $emailOrMobile);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$viewerUserId = (int) ($userRow['id'] ?? 0);
if ($viewerUserId <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'User not found']);
    exit;
}

$postStmt = $conn->prepare("SELECT id FROM bulk_order_posts WHERE id = ? AND admin_purged_at IS NULL LIMIT 1");
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

$checkStmt = $conn->prepare("SELECT id FROM bulk_order_post_likes WHERE post_id = ? AND user_id = ? LIMIT 1");
if (!$checkStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to check like']);
    exit;
}

$checkStmt->bind_param('ii', $postId, $viewerUserId);
$checkStmt->execute();
$existingLike = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

$viewerLiked = false;
if ($existingLike) {
    $deleteStmt = $conn->prepare("DELETE FROM bulk_order_post_likes WHERE id = ? LIMIT 1");
    if (!$deleteStmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to remove like']);
        exit;
    }

    $likeId = (int) ($existingLike['id'] ?? 0);
    $deleteStmt->bind_param('i', $likeId);
    $ok = $deleteStmt->execute();
    $deleteStmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to remove like']);
        exit;
    }
} else {
    $insertStmt = $conn->prepare("INSERT INTO bulk_order_post_likes (post_id, user_id, created_at) VALUES (?, ?, NOW())");
    if (!$insertStmt) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to add like']);
        exit;
    }

    $insertStmt->bind_param('ii', $postId, $viewerUserId);
    $ok = $insertStmt->execute();
    $insertStmt->close();

    if (!$ok) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Unable to add like']);
        exit;
    }

    $viewerLiked = true;
}

$countStmt = $conn->prepare("SELECT COUNT(*) AS like_count FROM bulk_order_post_likes WHERE post_id = ?");
if (!$countStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to count likes']);
    exit;
}

$countStmt->bind_param('i', $postId);
$countStmt->execute();
$countRow = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

echo json_encode([
    'ok' => true,
    'post_id' => $postId,
    'viewer_liked' => $viewerLiked,
    'like_count' => (int) ($countRow['like_count'] ?? 0),
]);
