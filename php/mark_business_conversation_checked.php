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

function ensureBusinessMessageReadsTable(mysqli $conn): bool {
    $sql = "
    CREATE TABLE IF NOT EXISTS business_message_reads (
        user_id INT UNSIGNED NOT NULL,
        partner_user_id INT UNSIGNED NOT NULL,
        last_read_message_id INT UNSIGNED NOT NULL DEFAULT 0,
        last_read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, partner_user_id),
        INDEX idx_partner_user (partner_user_id, user_id)
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

function getDashboardInboxCount(mysqli $conn, int $userId): int {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM (
            SELECT bm.sender_user_id
            FROM business_messages bm
            LEFT JOIN business_message_hidden_for_user bmh
                ON bmh.message_id = bm.message_id
               AND bmh.user_id = ?
            LEFT JOIN business_message_reads bmr
                ON bmr.user_id = ?
               AND bmr.partner_user_id = bm.sender_user_id
            WHERE bm.receiver_user_id = ?
              AND bmh.message_id IS NULL
              AND bm.message_id > COALESCE(bmr.last_read_message_id, 0)
            GROUP BY bm.sender_user_id
         ) unread_business_conversations"
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function getDashboardInboxUnreadCounts(mysqli $conn, int $userId): array {
    $counts = [];

    $stmt = $conn->prepare(
        "SELECT
            bm.sender_user_id AS partner_id,
            COUNT(*) AS unread_count
         FROM business_messages bm
         LEFT JOIN business_message_hidden_for_user bmh
            ON bmh.message_id = bm.message_id
           AND bmh.user_id = ?
         LEFT JOIN business_message_reads bmr
            ON bmr.user_id = ?
           AND bmr.partner_user_id = bm.sender_user_id
         WHERE bm.receiver_user_id = ?
           AND bmh.message_id IS NULL
           AND bm.message_id > COALESCE(bmr.last_read_message_id, 0)
         GROUP BY bm.sender_user_id"
    );

    if (!$stmt) {
        return $counts;
    }

    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $partnerId = (int) ($row['partner_id'] ?? 0);
        if ($partnerId <= 0) {
            continue;
        }

        $counts[$partnerId] = (int) ($row['unread_count'] ?? 0);
    }
    $stmt->close();

    return $counts;
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

if (!ensureBusinessMessagesTable($conn) || !ensureBusinessMessageReadsTable($conn) || !ensureBusinessMessageHiddenTable($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare inbox storage.']);
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

$partnerStmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$partnerStmt->bind_param("i", $partnerId);
$partnerStmt->execute();
$partnerRow = $partnerStmt->get_result()->fetch_assoc();
$partnerStmt->close();

if (!$partnerRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Conversation partner not found.']);
    exit;
}

$latestIncomingStmt = $conn->prepare(
    "SELECT COALESCE(MAX(message_id), 0) AS last_read_message_id
     FROM business_messages
     WHERE sender_user_id = ? AND receiver_user_id = ?"
);
$latestIncomingStmt->bind_param("ii", $partnerId, $userId);
$latestIncomingStmt->execute();
$latestIncomingRow = $latestIncomingStmt->get_result()->fetch_assoc();
$latestIncomingStmt->close();

$lastReadMessageId = (int) ($latestIncomingRow['last_read_message_id'] ?? 0);

$upsertStmt = $conn->prepare(
    "INSERT INTO business_message_reads (user_id, partner_user_id, last_read_message_id, last_read_at)
     VALUES (?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
        last_read_message_id = VALUES(last_read_message_id),
        last_read_at = NOW()"
);
$upsertStmt->bind_param("iii", $userId, $partnerId, $lastReadMessageId);

if (!$upsertStmt->execute()) {
    $upsertStmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update conversation status.']);
    exit;
}
$upsertStmt->close();

echo json_encode([
    'success' => true,
    'inbox_count' => getDashboardInboxCount($conn, $userId),
    'inbox_unread_counts' => getDashboardInboxUnreadCounts($conn, $userId)
]);
exit;
