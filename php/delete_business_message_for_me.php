<?php
session_start();
include "connection/dbconn.php";
require_once "partials/helpers.php";

header('Content-Type: application/json; charset=UTF-8');

function ensureBusinessMessagesTable(mysqli $conn): bool {
    $sql = "
    CREATE TABLE IF NOT EXISTS business_messages (
        message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_user_id INT UNSIGNED NOT NULL,
        receiver_user_id INT UNSIGNED NOT NULL,
        message_type VARCHAR(20) NOT NULL DEFAULT 'text',
        message_text TEXT NULL,
        media_path VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_sender_created (sender_user_id, created_at),
        INDEX idx_receiver_created (receiver_user_id, created_at)
    )";

    return (bool) $conn->query($sql);
}

function ensureBusinessMessageHiddenTable(mysqli $conn): bool {
    $sql = "
    CREATE TABLE IF NOT EXISTS business_message_hidden_for_user (
        message_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        hidden_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (message_id, user_id),
        INDEX idx_user_hidden (user_id, hidden_at)
    )";

    return (bool) $conn->query($sql);
}

if (!isset($_SESSION['emailormobilenumber'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => jomu_not_signed_in_message()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

jomu_require_csrf();

if (!ensureBusinessMessagesTable($conn) || !ensureBusinessMessageHiddenTable($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare message storage.']);
    exit;
}

$userStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
$userStmt->bind_param("s", $_SESSION['emailormobilenumber']);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$userRow || empty($userRow['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User account not found.']);
    exit;
}

$userId = (int) $userRow['id'];
$messageId = (int) ($_POST['message_id'] ?? 0);

if ($messageId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid message id.']);
    exit;
}

$messageStmt = $conn->prepare("SELECT sender_user_id, receiver_user_id FROM business_messages WHERE message_id = ? LIMIT 1");
$messageStmt->bind_param("i", $messageId);
$messageStmt->execute();
$messageRow = $messageStmt->get_result()->fetch_assoc();
$messageStmt->close();

if (!$messageRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Message not found.']);
    exit;
}

if (
    (int) ($messageRow['sender_user_id'] ?? 0) !== $userId
    && (int) ($messageRow['receiver_user_id'] ?? 0) !== $userId
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You can only delete messages in your own conversation.']);
    exit;
}

$hideStmt = $conn->prepare(
    "INSERT INTO business_message_hidden_for_user (message_id, user_id, hidden_at)
     VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE hidden_at = NOW()"
);
$hideStmt->bind_param("ii", $messageId, $userId);

if (!$hideStmt->execute()) {
    $hideStmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete message on your side.']);
    exit;
}
$hideStmt->close();

echo json_encode([
    'success' => true,
    'message_id' => $messageId
]);
exit;
