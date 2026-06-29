<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';
require __DIR__ . '/partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);

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

$viewerUserId = 0;
$emailOrMobile = trim((string) ($_SESSION['emailormobilenumber'] ?? ''));
if ($emailOrMobile !== '') {
    $viewerStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if ($viewerStmt) {
        $viewerStmt->bind_param('s', $emailOrMobile);
        $viewerStmt->execute();
        $viewerRow = $viewerStmt->get_result()->fetch_assoc();
        $viewerStmt->close();
        $viewerUserId = (int) ($viewerRow['id'] ?? 0);
    }
}

$isAdmin = jomu_current_admin($conn) !== null;
$hiddenClause = "COALESCE(p.moderation_status, 'visible') <> 'hidden'";

$posts = [];
$res = $conn->query(
    "SELECT
        p.id,
        p.user_id,
        p.business_name,
        COALESCE(NULLIF(u.profilepic, ''), p.profilepic) AS profilepic,
        p.content,
        p.fulfilled,
        p.created_at,
        MAX(COALESCE(p.moderation_status, 'visible')) AS moderation_status,
        COUNT(l.id) AS like_count,
        MAX(CASE WHEN l.user_id = {$viewerUserId} THEN 1 ELSE 0 END) AS viewer_liked
    FROM bulk_order_posts p
    LEFT JOIN bulk_order_post_likes l ON l.post_id = p.id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE {$hiddenClause}
      AND p.admin_purged_at IS NULL
    GROUP BY p.id, p.user_id, p.business_name, u.profilepic, p.profilepic, p.content, p.fulfilled, p.created_at
    ORDER BY p.created_at DESC, p.id DESC
    LIMIT 120"
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $posts[] = [
            'id' => (int) ($row['id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'business_name' => trim((string) ($row['business_name'] ?? '')),
            'profilepic' => trim((string) ($row['profilepic'] ?? '')),
            'content' => trim((string) ($row['content'] ?? '')),
            'fulfilled' => (int) ($row['fulfilled'] ?? 0) === 1,
            'like_count' => (int) ($row['like_count'] ?? 0),
            'viewer_liked' => (int) ($row['viewer_liked'] ?? 0) === 1,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'is_hidden' => strtolower((string) ($row['moderation_status'] ?? 'visible')) === 'hidden',
        ];
    }
}

echo json_encode([
    'ok' => true,
    'posts' => $posts,
    'viewer_is_admin' => $isAdmin,
    'admin_csrf' => $isAdmin ? jomu_admin_csrf_token() : '',
]);
