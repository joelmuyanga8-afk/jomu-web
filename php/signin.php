<?php
session_start();
require __DIR__ . '/connection/dbconn.php';
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/admin_helpers.php';
jomu_ensure_admin_schema($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../signin.html');
    exit();
}

$stmt = $conn->prepare("SELECT id, password, account_status, inactive_until, status_reason FROM users WHERE emailormobilenumber = ? LIMIT 1");
$emailormobilenumber = trim((string) ($_POST['emailormobilenumber'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
jomu_require_csrf();
jomu_require_rate_limit('signin', 8, 15 * 60, 'Too many sign in attempts. Please wait and try again.', $emailormobilenumber);
$stmt->bind_param("s", $emailormobilenumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $accountStatus = strtolower(trim((string) ($row['account_status'] ?? 'active')));
    if (!password_verify($password, (string) ($row['password'] ?? ''))) {
        header("Location: ../signin.html?error=Invalid+password");
        exit();
    }
    if ($accountStatus === 'terminated') {
        $terminatedMsg = 'This business account was terminated.';
        header('Location: ../signin.html?error=' . rawurlencode($terminatedMsg));
        exit();
    }
    $inactiveUntil = trim((string) ($row['inactive_until'] ?? ''));
    if ($accountStatus === 'inactive') {
        if ($inactiveUntil !== '' && strtotime($inactiveUntil) !== false && strtotime($inactiveUntil) <= time()) {
            $activateStmt = $conn->prepare("UPDATE users SET account_status = 'active', inactive_until = NULL, status_reason = NULL WHERE id = ?");
            if ($activateStmt) {
                $activateStmt->bind_param('i', $row['id']);
                $activateStmt->execute();
                $activateStmt->close();
            }
        }
    }
    session_regenerate_id(true);
    unset($_SESSION['jomu_suspended_browse'], $_SESSION['jomu_suspended_until']);
    $_SESSION['emailormobilenumber'] = $emailormobilenumber;
    if (isset($row['id']) && is_numeric($row['id'])) {
        $_SESSION['id'] = (int) $row['id'];
    }
    header("location: businessvendordashboard.php");
    exit();
} else {
    header("Location: ../signin.html?error=Invalid+password");
}
$stmt->close();
$conn->close();
