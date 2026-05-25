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
$code = trim($_POST['code'] ?? '');
jomu_require_csrf();
jomu_require_rate_limit('forgot_password_verify', 10, 15 * 60, 'Too many verification attempts. Please wait and request a new code.', $identifier);

if ($identifier === '' || $code === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Identifier and verification code are required.']);
    exit();
}

if (!isset($_SESSION['forgot_password'])) {
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'Reset session not found. Please request a new code.']);
    exit();
}

$reset = $_SESSION['forgot_password'];

if (!isset($reset['identifier'], $reset['otp_hash'], $reset['expires_at'], $reset['attempts'])) {
    unset($_SESSION['forgot_password']);
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'Reset session is invalid. Please request a new code.']);
    exit();
}

if ($reset['identifier'] !== $identifier) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Provided account does not match the reset request.']);
    exit();
}

if (time() > (int) $reset['expires_at']) {
    unset($_SESSION['forgot_password']);
    http_response_code(410);
    echo json_encode(['success' => false, 'message' => 'Code expired. Please request a new one.']);
    exit();
}

if ((int) $reset['attempts'] >= 5) {
    unset($_SESSION['forgot_password']);
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Request a new code.']);
    exit();
}

if (!password_verify($code, $reset['otp_hash'])) {
    $_SESSION['forgot_password']['attempts'] = (int) $reset['attempts'] + 1;
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid code. Please try again.']);
    exit();
}

$_SESSION['forgot_password']['verified'] = true;

echo json_encode(['success' => true, 'message' => 'Code verified. You can now set a new password.']);
