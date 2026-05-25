<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$admin = jomu_require_admin($conn);
jomu_require_admin_csrf();

$userId = (int) ($_POST['user_id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$template = strtolower(trim((string) ($_POST['template'] ?? 'warning')));
$customMessage = trim((string) ($_POST['custom_message'] ?? ''));
$inactiveReason = trim((string) ($_POST['inactive_reason'] ?? ''));
$inactiveDays = (int) ($_POST['inactive_days'] ?? 7);
$returnTo = trim((string) ($_POST['return_to'] ?? 'dashboard.php'));
if ($returnTo === '' || preg_match('/^https?:\/\//i', $returnTo) || str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
    $returnTo = 'dashboard.php';
}
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$redirectWithMessage = static function (string $message, bool $ok = true, array $extra = []) use ($returnTo, $isAjax): void {
    if ($isAjax) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
        exit;
    }
    header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'message=' . rawurlencode($message));
    exit;
};

$stmt = $conn->prepare("SELECT id, businessname FROM users WHERE id = ? AND emailormobilenumber <> 'system@jomu.local' LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute(); 
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $redirectWithMessage('User not found.', false);
}

$templates = [
    'warning' => 'JoMu warning: please review your activity and make sure it follows the JoMu Terms of Use.',
    'terms' => 'JoMu reminder: your listings must follow the JoMu Terms of Use.',
    'support' => 'JoMu notice: please contact the Support team about your account.',
    'custom' => $customMessage
];

if ($action === 'message') {
    $messageText = trim((string) ($templates[$template] ?? $templates['warning']));
    if ($messageText === '') {
        $redirectWithMessage('Please write the JoMu message first.', false);
    }
    jomu_send_system_message($conn, $userId, $messageText);
    jomu_admin_log($conn, (int) $admin['admin_id'], 'send_system_message', 'user', $userId, $template === 'custom' ? $messageText : $template);
    $redirectWithMessage('JoMu message sent.');
}

if ($action === 'inactive') {
    if (!in_array($inactiveDays, [7, 14, 21, 28], true)) {
        $inactiveDays = 7;
    }
    if ($inactiveReason === '') {
        $redirectWithMessage('Please enter the reason for suspending this account.', false);
    }
    $reason = $inactiveReason;
    try {
        $inactiveUntil = (new DateTime('now', new DateTimeZone('UTC')))->modify("+{$inactiveDays} days")->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $redirectWithMessage('Error calculating suspension date: ' . $e->getMessage(), false);
    }
    $notice = "Your account has been temporarily suspended for {$inactiveDays} days.\n\nReason: {$reason}";
    $update = $conn->prepare("UPDATE users SET account_status = 'inactive', inactive_since = NOW(), inactive_until = ?, status_reason = ? WHERE id = ?");
    if (!$update) {
        $redirectWithMessage('Database error: ' . $conn->error, false);
    }
    $update->bind_param('ssi', $inactiveUntil, $reason, $userId);
    if (!$update->execute()) {
        $redirectWithMessage('Failed to inactivate account: ' . $update->error, false);
    }
    $update->close();
    jomu_send_system_message($conn, $userId, $notice);
    jomu_admin_log($conn, (int) $admin['admin_id'], 'suspend_account', 'user', $userId, $inactiveDays . ' days: ' . $reason);
    $redirectWithMessage(
        "Account suspended for {$inactiveDays} days.",
        true,
        [
            'inactive_until' => $inactiveUntil,
            'inactive_until_text' => 'Ends ' . $inactiveUntil,
            'inactive_since' => date('Y-m-d H:i:s'),
            'status_reason' => $reason,
        ]
    );
}

if ($action === 'activate') {
    $update = $conn->prepare("UPDATE users SET account_status = 'active', inactive_since = NULL, inactive_until = NULL, status_reason = NULL WHERE id = ?");
    if (!$update) {
        $redirectWithMessage('Database error: ' . $conn->error, false);
    }
    $update->bind_param('i', $userId);
    if (!$update->execute()) {
        $redirectWithMessage('Failed to activate account: ' . $update->error, false);
    }
    $update->close();
    jomu_send_system_message($conn, $userId, 'Your JoMu account suspension has been lifted and your account is now active again.');
    jomu_admin_log($conn, (int) $admin['admin_id'], 'activate_account', 'user', $userId);
    $redirectWithMessage('Account activated.', true, ['inactive_since' => null, 'inactive_until' => null, 'inactive_until_text' => '', 'status_reason' => null]);
}

if ($action === 'terminate') {
    $terminationReason = trim((string) ($_POST['termination_reason'] ?? 'Terminated by admin.'));
    if ($terminationReason === '') {
        $terminationReason = 'Terminated by admin.';
    }
    // Archive to history table (replace prior row for same user if re-terminating after restore edge cases)
    if (jomu_table_exists($conn, 'admin_terminated_users')) {
        $delArchive = $conn->prepare('DELETE FROM admin_terminated_users WHERE user_id = ?');
        if ($delArchive) {
            $delArchive->bind_param('i', $userId);
            $delArchive->execute();
            $delArchive->close();
        }
        $archive = $conn->prepare(
            "INSERT INTO admin_terminated_users (user_id, businessname, emailormobilenumber, reason, terminated_by_admin_id, terminated_at)
             SELECT id, businessname, emailormobilenumber, ?, ?, NOW()
             FROM users
             WHERE id = ?
             LIMIT 1"
        );
        if ($archive) {
            $archive->bind_param('sii', $terminationReason, $admin['admin_id'], $userId);
            $archive->execute();
            $archive->close();
        }
    }
    jomu_admin_log($conn, (int) $admin['admin_id'], 'terminate_account', 'user', $userId, $terminationReason);
    // Delete related data but keep the users row with status=terminated
    $tables = [
        'business_message_reads' => 'user_id',
        'business_message_hidden_for_user' => 'user_id',
        'profile_pinned_listings' => 'user_id',
        'listing_view_stats' => 'user_id',
        'bulk_order_posts' => 'user_id',
        'purchase_requests' => 'buyer_user_id',
    ];
    foreach ($tables as $table => $column) {
        if (!jomu_table_exists($conn, $table)) {
            continue;
        }
        $cleanup = $conn->prepare("DELETE FROM {$table} WHERE {$column} = ?");
        if ($cleanup) {
            $cleanup->bind_param('i', $userId);
            $cleanup->execute();
            $cleanup->close();
        }
    }
    if (jomu_table_exists($conn, 'purchase_requests')) {
        $cleanup = $conn->prepare("DELETE FROM purchase_requests WHERE seller_user_id = ?");
        if ($cleanup) {
            $cleanup->bind_param('i', $userId);
            $cleanup->execute();
            $cleanup->close();
        }
    }
    if (jomu_table_exists($conn, 'business_messages')) {
        $cleanup = $conn->prepare("DELETE FROM business_messages WHERE sender_user_id = ? OR receiver_user_id = ?");
        if ($cleanup) {
            $cleanup->bind_param('ii', $userId, $userId);
            $cleanup->execute();
            $cleanup->close();
        }
    }
    if (jomu_table_exists($conn, 'listings')) {
        $cleanup = $conn->prepare("DELETE FROM listings WHERE user_id = ?");
        if ($cleanup) {
            $cleanup->bind_param('i', $userId);
            $cleanup->execute();
            $cleanup->close();
        }
    }
    // Mark user as terminated — keep businessname, email, profile picture; clear password so sign-in fails.
    $markTerminated = $conn->prepare(
        "UPDATE users SET account_status = 'terminated', inactive_since = NULL, inactive_until = NULL, terminated_at = NOW(), status_reason = ? WHERE id = ? LIMIT 1"
    );
    if ($markTerminated) {
        $markTerminated->bind_param('si', $terminationReason, $userId);
        $markTerminated->execute();
        $markTerminated->close();
    }
    $redirectWithMessage('Account terminated.');
}

$redirectWithMessage('Unknown user action.', false);
