<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/partials/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$identifier = trim($_POST['identifier'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
jomu_require_csrf();
jomu_require_rate_limit('forgot_password_reset', 6, 15 * 60, 'Too many password reset attempts. Please wait and try again.', $identifier);

if ($identifier === '' || $newPassword === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Identifier and new password are required.']);
    exit();
}

if (strlen($newPassword) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit();
}

if (!isset($_SESSION['forgot_password'])) {
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'Reset session not found. Please start again.']);
    exit();
}

$reset = $_SESSION['forgot_password'];

if (($reset['identifier'] ?? '') !== $identifier) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Account does not match this reset request.']);
    exit();
}

if (($reset['verified'] ?? false) !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Please verify your code first.']);
    exit();
}

if (time() > (int) ($reset['expires_at'] ?? 0)) {
    unset($_SESSION['forgot_password']);
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'Reset session expired. Please request a new code.']);
    exit();
}

include __DIR__ . '/connection/dbconn.php';
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$accountIdentifier = trim((string) ($reset['account_identifier'] ?? $identifier));
$stmt = $conn->prepare('UPDATE users SET password = ? WHERE emailormobilenumber = ? LIMIT 1');

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process password reset.']);
    $conn->close();
    exit();
}

$stmt->bind_param('ss', $passwordHash, $accountIdentifier);
$stmt->execute();

if ($stmt->affected_rows < 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update password. Please try again.']);
    $stmt->close();
    $conn->close();
    exit();
}

unset($_SESSION['forgot_password']);

echo json_encode(['success' => true, 'message' => 'Password reset successful. You can now sign in.']);

$stmt->close();
$conn->close();
