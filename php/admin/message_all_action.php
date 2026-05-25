<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$admin = jomu_require_admin($conn);
jomu_require_admin_csrf();

$returnTo = trim((string) ($_POST['return_to'] ?? 'dashboard.php?page=messages'));
if ($returnTo === '' || preg_match('/^https?:\/\//i', $returnTo) || str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
    $returnTo = 'dashboard.php?page=messages';
}

$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$respond = static function (string $message, bool $ok = true, array $extra = []) use ($returnTo, $isAjax): void {
    if ($isAjax) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
        exit;
    }

    header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'message=' . rawurlencode($message));
    exit;
};

$messageText = trim((string) ($_POST['message_text'] ?? ''));
if ($messageText === '') {
    $respond('Please write the message before sending to all users.', false);
}

$users = [];
$stmt = $conn->prepare(
    "SELECT id
     FROM users
     WHERE emailormobilenumber <> 'system@jomu.local'
       AND COALESCE(account_status, 'active') <> 'terminated'
     ORDER BY id ASC"
);
if (!$stmt) {
    $respond('Unable to load users right now.', false);
}

$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $users[] = (int) ($row['id'] ?? 0);
}
$stmt->close();

$batchId = 0;
$batchStmt = $conn->prepare(
    "INSERT INTO admin_message_batches (admin_id, message_text, recipient_count, created_at)
     VALUES (?, ?, 0, NOW())"
);
if ($batchStmt) {
    $adminId = (int) $admin['admin_id'];
    $batchStmt->bind_param('is', $adminId, $messageText);
    if ($batchStmt->execute()) {
        $batchId = (int) $batchStmt->insert_id;
    }
    $batchStmt->close();
}

$sent = 0;
foreach ($users as $userId) {
    if ($userId > 0 && jomu_send_system_message($conn, $userId, $messageText, $batchId > 0 ? $batchId : null)) {
        $sent++;
    }
}

if ($batchId > 0) {
    $updateBatchStmt = $conn->prepare("UPDATE admin_message_batches SET recipient_count = ? WHERE batch_id = ?");
    if ($updateBatchStmt) {
        $updateBatchStmt->bind_param('ii', $sent, $batchId);
        $updateBatchStmt->execute();
        $updateBatchStmt->close();
    }
}

jomu_admin_log($conn, (int) $admin['admin_id'], 'send_system_message_all', 'users', null, $sent . ' recipients: ' . $messageText);
$respond('Message sent to ' . $sent . ' user' . ($sent === 1 ? '' : 's') . '.', true, ['sent_count' => $sent]);
