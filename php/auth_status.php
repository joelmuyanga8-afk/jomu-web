<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/partials/helpers.php';

$accountSuspendedBrowse = jomu_is_suspended_browse_session();
if ($accountSuspendedBrowse) {
    $suspendedUntil = trim((string) ($_SESSION['jomu_suspended_until'] ?? ''));
    if ($suspendedUntil !== '' && strtotime($suspendedUntil) !== false && strtotime($suspendedUntil) <= time()) {
        unset($_SESSION['jomu_suspended_browse'], $_SESSION['jomu_suspended_until']);
        $accountSuspendedBrowse = false;
    }
}

$isLoggedIn = isset($_SESSION['emailormobilenumber']) && trim((string) $_SESSION['emailormobilenumber']) !== '';
$userKey = '';
$guestActionMessage = '';

if ($accountSuspendedBrowse) {
    $isLoggedIn = false;
    $guestActionMessage = 'Your account was suspended.';
} elseif ($isLoggedIn) {
    $userKey = 'user:' . trim((string) $_SESSION['emailormobilenumber']);
}

$loggedInUserId = 0;
if ($isLoggedIn) {
    if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
        $loggedInUserId = (int) $_SESSION['id'];
    } else {
        require_once __DIR__ . '/connection/dbconn.php';
        $stmt = $conn->prepare('SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1');
        if ($stmt) {
            $emailOrMobile = trim((string) $_SESSION['emailormobilenumber']);
            $stmt->bind_param('s', $emailOrMobile);
            if ($stmt->execute()) {
                $stmt->bind_result($dbUserId);
                if ($stmt->fetch()) {
                    $loggedInUserId = is_numeric($dbUserId) ? (int) $dbUserId : 0;
                }
            }
            $stmt->close();
        }
        $conn->close();
    }
}

echo json_encode([
    'signed_in' => $isLoggedIn,
    'user_key' => $userKey,
    'user_id' => $loggedInUserId,
    'csrf_token' => jomu_csrf_token(),
    'account_suspended_browse' => $accountSuspendedBrowse,
    'guest_action_message' => $guestActionMessage,
]);
