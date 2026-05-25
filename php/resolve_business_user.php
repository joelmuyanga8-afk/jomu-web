<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';

$businessName = trim((string) ($_GET['business_name'] ?? ''));
if ($businessName === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Missing business name']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE businessname = ? ORDER BY id DESC LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to prepare lookup']);
    exit;
}

$stmt->bind_param('s', $businessName);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$userId = (int) ($row['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Business not found']);
    exit;
}

echo json_encode([
    'ok' => true,
    'user_id' => $userId,
]);
