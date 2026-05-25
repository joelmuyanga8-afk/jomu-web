<?php
session_start();
require __DIR__ . '/../connection/dbconn.php';
require __DIR__ . '/../partials/helpers.php';
require __DIR__ . '/../partials/admin_helpers.php';

jomu_ensure_admin_schema($conn);
$admin = jomu_require_admin($conn);
jomu_require_admin_csrf();

$listingId = (int) ($_POST['listing_id'] ?? 0);
$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$returnTo = trim((string) ($_POST['return_to'] ?? 'dashboard.php'));
if ($returnTo === '' || preg_match('/^https?:\/\//i', $returnTo) || str_contains($returnTo, "\n") || str_contains($returnTo, "\r")) {
    $returnTo = 'dashboard.php';
}
$isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
$respond = static function (string $message, bool $ok = true) use ($returnTo, $isAjax): void {
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
    "SELECT listing_id, user_id, stockname, moderation_status, admin_purged_at, admin_reviewed_at
     FROM listings WHERE listing_id = ? LIMIT 1"
);
$stmt->bind_param('i', $listingId);
$stmt->execute();
$listing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$listing) {
    $respond('Listing not found.', false);
}

$isPurged = trim((string) ($listing['admin_purged_at'] ?? '')) !== '';
if ($isPurged) {
    $respond('This listing is no longer in the admin queue.', false);
}

$isHidden = strtolower((string) ($listing['moderation_status'] ?? 'visible')) === 'hidden';

if ($action === 'approve') {
    if ($isHidden) {
        $respond('Hidden listings cannot be approved from this queue.', false);
    }
    $update = $conn->prepare(
        "UPDATE listings SET admin_reviewed_at = NOW()
         WHERE listing_id = ? AND admin_reviewed_at IS NULL AND COALESCE(moderation_status, 'visible') <> 'hidden' AND admin_purged_at IS NULL"
    );
    if (!$update) {
        $respond('Database error.', false);
    }
    $update->bind_param('i', $listingId);
    $update->execute();
    if ($update->affected_rows < 1) {
        $update->close();
        $respond('Listing could not be approved.', false);
    }
    $update->close();
    jomu_admin_log($conn, (int) $admin['admin_id'], 'approve_listing', 'listing', $listingId, (string) ($listing['stockname'] ?? ''));
    $respond('Listing approved and removed from the pending queue.');
}

if ($action === 'purge') {
    if (!$isHidden) {
        $respond('Only hidden listings can be deleted from admin.', false);
    }
    if (!jomu_delete_listing_completely($conn, $listingId)) {
        $respond('Listing could not be deleted.', false);
    }
    jomu_admin_log($conn, (int) $admin['admin_id'], 'purge_listing', 'listing', $listingId, (string) ($listing['stockname'] ?? ''));
    $respond('Listing permanently deleted and removed from the platform.');
}

if ($action === 'hide') {
    $reason = 'This listing has been hidden for being against the JoMu Terms of Use.';
    $update = $conn->prepare("UPDATE listings SET moderation_status = 'hidden', hidden_reason = ?, hidden_at = NOW(), hidden_by_admin_id = ? WHERE listing_id = ?");
    $update->bind_param('sii', $reason, $admin['admin_id'], $listingId);
    $update->execute();
    $update->close();

    $listingUrl = jomu_listing_url($listingId);
    jomu_send_system_message($conn, (int) $listing['user_id'], $reason . "\n\nListing: " . $listingUrl);
    jomu_admin_log($conn, (int) $admin['admin_id'], 'hide_listing', 'listing', $listingId, (string) ($listing['stockname'] ?? ''));
    $respond('Listing hidden and user notified.');
}

if ($action === 'unhide') {
    $update = $conn->prepare("UPDATE listings SET moderation_status = 'visible', hidden_reason = NULL, hidden_at = NULL, hidden_by_admin_id = NULL WHERE listing_id = ?");
    $update->bind_param('i', $listingId);
    $update->execute();
    $update->close();

    jomu_send_system_message($conn, (int) $listing['user_id'], 'Your listing has been restored on JoMu. Listing: ' . jomu_listing_url($listingId));
    jomu_admin_log($conn, (int) $admin['admin_id'], 'unhide_listing', 'listing', $listingId, (string) ($listing['stockname'] ?? ''));
    $respond('Listing restored and user notified.');
}

$respond('Unknown listing action.', false);
