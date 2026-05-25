<?php
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';

$commentRef = trim((string) ($_GET['comment_ref'] ?? ''));
if ($commentRef === '') {
    echo json_encode([
        'ok' => true,
        'exists' => false,
    ]);
    exit;
}

$postId = 0;
if (preg_match('/^post-(\d+)$/', $commentRef, $matches)) {
    $postId = (int) ($matches[1] ?? 0);
}

if ($postId <= 0) {
    echo json_encode([
        'ok' => true,
        'exists' => false,
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM bulk_order_posts WHERE id = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'exists' => false,
    ]);
    exit;
}

$stmt->bind_param('i', $postId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'ok' => true,
    'exists' => !empty($row['id']),
]);
exit;
