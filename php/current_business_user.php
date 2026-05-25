<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/partials/helpers.php';
require __DIR__ . '/connection/dbconn.php';

$accountSuspendedBrowse = jomu_is_suspended_browse_session();
if ($accountSuspendedBrowse) {
    $suspendedUntil = trim((string) ($_SESSION['jomu_suspended_until'] ?? ''));
    if ($suspendedUntil !== '' && strtotime($suspendedUntil) !== false && strtotime($suspendedUntil) <= time()) {
        unset($_SESSION['jomu_suspended_browse'], $_SESSION['jomu_suspended_until']);
        $accountSuspendedBrowse = false;
    }
}

if ($accountSuspendedBrowse) {
    echo json_encode([
        'signed_in' => false,
        'user_id' => 0,
        'business_name' => '',
        'csrf_token' => '',
        'account_suspended_browse' => true,
        'guest_action_message' => 'Your account was suspended.',
    ]);
    exit;
}

$emailOrMobile = trim((string) ($_SESSION['emailormobilenumber'] ?? ''));
if ($emailOrMobile === '') {
    echo json_encode([
        'signed_in' => false,
        'user_id' => 0,
        'business_name' => '',
        'csrf_token' => '',
        'account_suspended_browse' => false,
        'guest_action_message' => '',
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id, businessname FROM users WHERE emailormobilenumber = ? LIMIT 1");
if (!$stmt) {
    echo json_encode([
        'signed_in' => true,
        'user_id' => 0,
        'business_name' => '',
        'csrf_token' => jomu_csrf_token(),
        'account_suspended_browse' => false,
        'guest_action_message' => '',
    ]);
    exit;
}

$stmt->bind_param('s', $emailOrMobile);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'signed_in' => true,
    'user_id' => (int) ($row['id'] ?? 0),
    'business_name' => trim((string) ($row['businessname'] ?? '')),
    'csrf_token' => jomu_csrf_token(),
    'account_suspended_browse' => false,
    'guest_action_message' => '',
]);
