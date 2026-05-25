<?php
session_start();
include "connection/dbconn.php";
require_once "partials/helpers.php";
require_once "partials/admin_helpers.php";

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

function ensureBusinessMessageReplyColumns(mysqli $conn): void {
    $replyColumnCheck = $conn->query("SHOW COLUMNS FROM business_messages LIKE 'reply_to_message_id'");
    if (!$replyColumnCheck || $replyColumnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE business_messages ADD COLUMN reply_to_message_id INT UNSIGNED NULL AFTER media_path");
    }
}

function formatInboxTimestampLabel(string $dateTimeValue): string {
    $ts = strtotime($dateTimeValue);
    if (!$ts) {
        return '';
    }

    $oneYearAgoTs = strtotime('-1 year');
    $format = ($oneYearAgoTs !== false && $ts <= $oneYearAgoTs) ? 'j M. Y. g:ia' : 'j M. g:ia';
    return date($format, $ts);
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

if (!ensureBusinessMessagesTable($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare message storage.']);
    exit;
}

ensureBusinessMessageReplyColumns($conn);
jomu_ensure_admin_schema($conn);

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

$senderUserId = (int) $userRow['id'];
$receiverUserId = (int) ($_POST['receiver_id'] ?? 0);
$messageText = trim((string) ($_POST['message_text'] ?? ''));
$replyToMessageId = (int) ($_POST['reply_to_message_id'] ?? 0);

if ($receiverUserId <= 0 || $receiverUserId === $senderUserId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid conversation recipient.']);
    exit;
}

if ($receiverUserId === jomu_system_user_id($conn)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'JoMu notices cannot receive replies.']);
    exit;
}

if ($messageText === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Message content is required.']);
    exit;
}

$receiverStmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$receiverStmt->bind_param("i", $receiverUserId);
$receiverStmt->execute();
$receiverRow = $receiverStmt->get_result()->fetch_assoc();
$receiverStmt->close();

if (!$receiverRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Recipient business not found.']);
    exit;
}

$messageType = 'text';
$storedMediaPath = null;
$replyMessageText = '';
$replyDirection = 'incoming';

if ($replyToMessageId > 0) {
    $replyStmt = $conn->prepare(
        "SELECT message_id, sender_user_id, receiver_user_id, message_text
         FROM business_messages
         WHERE message_id = ? LIMIT 1"
    );
    $replyStmt->bind_param("i", $replyToMessageId);
    $replyStmt->execute();
    $replyRow = $replyStmt->get_result()->fetch_assoc();
    $replyStmt->close();

    if (
        !$replyRow
        || (
            (int) ($replyRow['sender_user_id'] ?? 0) !== $senderUserId
            && (int) ($replyRow['sender_user_id'] ?? 0) !== $receiverUserId
        )
        || (
            (int) ($replyRow['receiver_user_id'] ?? 0) !== $senderUserId
            && (int) ($replyRow['receiver_user_id'] ?? 0) !== $receiverUserId
        )
    ) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid reply target.']);
        exit;
    }

    $replyMessageText = trim((string) ($replyRow['message_text'] ?? ''));
    $replyDirection = (int) ($replyRow['sender_user_id'] ?? 0) === $senderUserId ? 'outgoing' : 'incoming';
}

$insertStmt = $conn->prepare(
    "INSERT INTO business_messages (sender_user_id, receiver_user_id, message_type, message_text, media_path, reply_to_message_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, NOW())"
);
$insertStmt->bind_param("iisssi", $senderUserId, $receiverUserId, $messageType, $messageText, $storedMediaPath, $replyToMessageId);

if (!$insertStmt->execute()) {
    $insertStmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to send message.']);
    exit;
}

$messageId = (int) $insertStmt->insert_id;
$insertStmt->close();

$messageFetchStmt = $conn->prepare("SELECT created_at FROM business_messages WHERE message_id = ? LIMIT 1");
$messageFetchStmt->bind_param("i", $messageId);
$messageFetchStmt->execute();
$messageRow = $messageFetchStmt->get_result()->fetch_assoc();
$messageFetchStmt->close();

$createdAt = (string) ($messageRow['created_at'] ?? date('Y-m-d H:i:s'));

echo json_encode([
    'success' => true,
    'message' => [
        'message_id' => $messageId,
        'partner_id' => $receiverUserId,
        'direction' => 'outgoing',
        'type' => $messageType,
        'text' => $messageText,
        'media_path' => $storedMediaPath ? '/php/' . ltrim($storedMediaPath, '/') : '',
        'reply_to_message_id' => $replyToMessageId,
        'reply_text' => $replyMessageText,
        'reply_direction' => $replyDirection,
        'created_at' => $createdAt,
        'timestamp_label' => formatInboxTimestampLabel($createdAt)
    ]
]);
exit;
