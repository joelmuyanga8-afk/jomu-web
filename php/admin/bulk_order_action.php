<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$admin = jomu_require_admin($conn);
jomu_require_admin_csrf();

$postId = (int) ($_POST['post_id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$returnTo = trim((string) ($_POST['return_to'] ?? 'dashboard.php?page=bulk_orders'));
if ($returnTo === '' || preg_match('/^https?:\/\//i', $returnTo) || str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
    $returnTo = 'dashboard.php?page=bulk_orders';
}
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$redirect = static function (string $message, bool $ok = true) use ($returnTo, $isAjax): void {
    if ($isAjax) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }
    header('Location: ' . $returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'message=' . rawurlencode($message));
    exit;
};

$stmt = $conn->prepare(
    "SELECT id, user_id, business_name, content, moderation_status, admin_purged_at, admin_reviewed_at
     FROM bulk_order_posts WHERE id = ? LIMIT 1"
);
if (!$stmt) {
    $redirect('Unable to load bulk order comment.', false);
}
$stmt->bind_param('i', $postId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    $redirect('Bulk order comment not found.', false);
}

$isPurged = trim((string) ($post['admin_purged_at'] ?? '')) !== '';
if ($isPurged) {
    $redirect('This comment is no longer in the admin queue.', false);
}

$isHidden = strtolower((string) ($post['moderation_status'] ?? 'visible')) === 'hidden';

if ($action === 'approve') {
    if ($isHidden) {
        $redirect('Hidden comments cannot be approved from this queue.', false);
    }
    $update = $conn->prepare(
        "UPDATE bulk_order_posts SET admin_reviewed_at = NOW()
         WHERE id = ? AND admin_reviewed_at IS NULL AND COALESCE(moderation_status, 'visible') <> 'hidden' AND admin_purged_at IS NULL"
    );
    if (!$update) {
        $redirect('Database error.', false);
    }
    $update->bind_param('i', $postId);
    $update->execute();
    if ($update->affected_rows < 1) {
        $update->close();
        $redirect('Comment could not be approved.', false);
    }
    $update->close();
    jomu_admin_log($conn, (int) $admin['admin_id'], 'approve_bulk_order_comment', 'bulk_order_post', $postId, (string) ($post['content'] ?? ''));
    $redirect('Comment approved and removed from the pending queue.');
}

if ($action === 'purge') {
    if (!$isHidden) {
        $redirect('Only hidden comments can be deleted from admin.', false);
    }
    $delete = $conn->prepare(
        "DELETE FROM bulk_order_posts
         WHERE id = ? AND COALESCE(moderation_status, 'visible') = 'hidden' AND admin_purged_at IS NULL"
    );
    if (!$delete) {
        $redirect('Database error.', false);
    }
    $delete->bind_param('i', $postId);
    $delete->execute();
    if ($delete->affected_rows < 1) {
        $delete->close();
        $redirect('Comment could not be deleted.', false);
    }
    $delete->close();
    if (jomu_table_exists($conn, 'bulk_order_post_likes')) {
        $likesDelete = $conn->prepare('DELETE FROM bulk_order_post_likes WHERE post_id = ?');
        if ($likesDelete) {
            $likesDelete->bind_param('i', $postId);
            $likesDelete->execute();
            $likesDelete->close();
        }
    }
    jomu_admin_log($conn, (int) $admin['admin_id'], 'purge_bulk_order_comment', 'bulk_order_post', $postId, (string) ($post['content'] ?? ''));
    $redirect('Comment permanently deleted and removed from the platform.');
}

if ($action === 'hide') {
    $reason = 'This bulk order comment has been hidden for being against the JoMu Terms of Use.';
    $update = $conn->prepare("UPDATE bulk_order_posts SET moderation_status = 'hidden', hidden_reason = ?, hidden_at = NOW(), hidden_by_admin_id = ? WHERE id = ?");
    if ($update) {
        $update->bind_param('sii', $reason, $admin['admin_id'], $postId);
        $update->execute();
        $update->close();
    }
    $ownerId = (int) ($post['user_id'] ?? 0);
    if ($ownerId > 0) {
        $notice = $reason . "\n\nComment:\n" . trim((string) ($post['content'] ?? ''));
        jomu_send_system_message($conn, $ownerId, $notice);
    }
    jomu_admin_log($conn, (int) $admin['admin_id'], 'hide_bulk_order_comment', 'bulk_order_post', $postId, (string) ($post['content'] ?? ''));
    $redirect('Bulk order comment hidden and user notified.');
}

if ($action === 'unhide') {
    $update = $conn->prepare("UPDATE bulk_order_posts SET moderation_status = 'visible', hidden_reason = NULL, hidden_at = NULL, hidden_by_admin_id = NULL WHERE id = ?");
    if ($update) {
        $update->bind_param('i', $postId);
        $update->execute();
        $update->close();
    }
    $ownerId = (int) ($post['user_id'] ?? 0);
    if ($ownerId > 0) {
        jomu_send_system_message($conn, $ownerId, "Your bulk order comment has been restored on JoMu.\n\nComment:\n" . trim((string) ($post['content'] ?? '')));
    }
    jomu_admin_log($conn, (int) $admin['admin_id'], 'unhide_bulk_order_comment', 'bulk_order_post', $postId, (string) ($post['content'] ?? ''));
    $redirect('Bulk order comment restored.');
}

$redirect('Unknown bulk order action.', false);
