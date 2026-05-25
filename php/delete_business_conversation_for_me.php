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
$partnerId = (int) ($_POST['partner_id'] ?? 0);

if ($partnerId <= 0 || $partnerId === $userId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid conversation partner.']);
    exit;
}

$hideConversationStmt = $conn->prepare(
    "INSERT INTO business_message_hidden_for_user (message_id, user_id, hidden_at)
     SELECT bm.message_id, ?, NOW()
     FROM business_messages bm
     WHERE (bm.sender_user_id = ? AND bm.receiver_user_id = ?)
        OR (bm.sender_user_id = ? AND bm.receiver_user_id = ?)
     ON DUPLICATE KEY UPDATE hidden_at = VALUES(hidden_at)"
);

if (!$hideConversationStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare conversation delete.']);
    exit;
}

$hideConversationStmt->bind_param("iiiii", $userId, $userId, $partnerId, $partnerId, $userId);

if (!$hideConversationStmt->execute()) {
    $hideConversationStmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete conversation on your side.']);
    exit;
}

$affected = (int) $hideConversationStmt->affected_rows;
$hideConversationStmt->close();

echo json_encode([
    'success' => true,
    'partner_id' => $partnerId,
    'affected' => $affected
]);
exit;
