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
    echo json_encode(['ok' => false, 'message' => 'Unable to verify user']);
    exit;
}

$userStmt->bind_param('s', $emailOrMobile);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$userRow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'User not found']);
    exit;
}

$viewerUserId = (int) ($userRow['id'] ?? 0);
$viewerBusinessName = trim((string) ($userRow['businessname'] ?? ''));

$postStmt = $conn->prepare("SELECT id, user_id, business_name FROM bulk_order_posts WHERE id = ? LIMIT 1");
if (!$postStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to load comment']);
    exit;
}

$postStmt->bind_param('i', $postId);
$postStmt->execute();
$postRow = $postStmt->get_result()->fetch_assoc();
$postStmt->close();

if (!$postRow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Comment not found']);
    exit;
}

$ownerUserId = (int) ($postRow['user_id'] ?? 0);
$ownerBusinessName = trim((string) ($postRow['business_name'] ?? ''));
$canDelete = ($viewerUserId > 0 && $ownerUserId > 0 && $viewerUserId === $ownerUserId)
    || ($viewerBusinessName !== '' && $ownerBusinessName !== '' && strcasecmp($viewerBusinessName, $ownerBusinessName) === 0);

if (!$canDelete) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'You can only delete your own comment']);
    exit;
}

$deleteLikesStmt = $conn->prepare("DELETE FROM bulk_order_post_likes WHERE post_id = ?");
if ($deleteLikesStmt) {
    $deleteLikesStmt->bind_param('i', $postId);
    $deleteLikesStmt->execute();
    $deleteLikesStmt->close();
}

$deletePostStmt = $conn->prepare("DELETE FROM bulk_order_posts WHERE id = ? LIMIT 1");
if (!$deletePostStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to delete comment']);
    exit;
}

$deletePostStmt->bind_param('i', $postId);
$ok = $deletePostStmt->execute();
$deletePostStmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Unable to delete comment']);
    exit;
}

echo json_encode([
    'ok' => true,
    'post_id' => $postId,
]);
exit;
