<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/connection/env.php';
require_once __DIR__ . '/partials/helpers.php';
load_env_file(__DIR__ . '/../.env');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$idToken = trim($_POST['id_token'] ?? '');
$flow = strtolower(trim((string) ($_POST['flow'] ?? 'signin')));
jomu_require_csrf();
jomu_require_rate_limit('google_auth', 15, 15 * 60, 'Too many Google sign in attempts. Please wait and try again.');
if ($idToken === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Google token is required.']);
    exit();
}

if (!in_array($flow, ['signin', 'signup'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid Google auth flow.']);
    exit();
}

$googleClientId = env_value('GOOGLE_CLIENT_ID');
if (!$googleClientId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Google client ID is not configured.']);
    exit();
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'cURL extension is required for Google auth.']);
    exit();
}

$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
$ch = curl_init($verifyUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20
]);
$caBundlePath = jomu_configure_curl_ca_bundle($ch);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    error_log('Google token verify failed. HTTP: ' . $httpCode . '; cURL: ' . $curlError . '; CA bundle: ' . ($caBundlePath ?: 'default'));
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Google authentication could not be verified right now. Please try again.']);
    exit();
}

if ($httpCode < 200 || $httpCode >= 300) {
    $safeResponse = substr(preg_replace('/\s+/', ' ', (string) $response) ?? '', 0, 500);
    error_log('Google token verify rejected. HTTP: ' . $httpCode . '; Response: ' . $safeResponse . '; CA bundle: ' . ($caBundlePath ?: 'default'));
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Google authentication failed. Please try again.']);
    exit();
}

$tokenData = json_decode($response, true);
if (!is_array($tokenData)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid Google token response.']);
    exit();
}

$aud = (string) ($tokenData['aud'] ?? '');
$email = strtolower(trim((string) ($tokenData['email'] ?? '')));
$emailVerified = (string) ($tokenData['email_verified'] ?? '');

if ($aud !== $googleClientId || $email === '' || $emailVerified !== 'true') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Google token is invalid for this app.']);
    exit();
}

include __DIR__ . '/connection/dbconn.php';
require_once __DIR__ . '/partials/admin_helpers.php';
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

jomu_ensure_admin_schema($conn);

$stmt = $conn->prepare('SELECT id, businessname, account_status, inactive_until, status_reason FROM users WHERE emailormobilenumber = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process login right now.']);
    $conn->close();
    exit();
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$isNewAccount = $result->num_rows === 0;
$requiresBusinessName = false;
$existingUser = null;

if ($flow === 'signup') {
    if ($isNewAccount) {
        $generatedPassword = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $businessName = '';
        $insertStmt = $conn->prepare('INSERT INTO users (businessname, emailormobilenumber, password) VALUES (?, ?, ?)');
        if (!$insertStmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to create account right now.']);
            $stmt->close();
            $conn->close();
            exit();
        }

        $insertStmt->bind_param('sss', $businessName, $email, $generatedPassword);
        if (!$insertStmt->execute()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create your Google account.']);
            $insertStmt->close();
            $stmt->close();
            $conn->close();
            exit();
        }

        $existingUser = [
            'id' => (int) $conn->insert_id,
            'businessname' => '',
            'account_status' => 'active',
            'inactive_until' => '',
            'status_reason' => '',
        ];
        $insertStmt->close();
        $requiresBusinessName = true;
    } else {
        $existingUser = $result->fetch_assoc();
        $existingBusinessName = trim((string) ($existingUser['businessname'] ?? ''));
        $requiresBusinessName = $existingBusinessName === '';
    }
} else {
    if ($isNewAccount) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No account found for this Google email. Please create an account first.'
        ]);
        $stmt->close();
        $conn->close();
        exit();
    }

    $existingUser = $result->fetch_assoc();
    $requiresBusinessName = trim((string) ($existingUser['businessname'] ?? '')) === '';
}

if ($existingUser) {
    $accountStatus = strtolower(trim((string) ($existingUser['account_status'] ?? 'active')));
    if ($accountStatus === 'terminated') {
        $terminatedMsg = 'This business account was terminated.';
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $terminatedMsg]);
        $stmt->close();
        $conn->close();
        exit();
    }
    if ($accountStatus === 'inactive') {
        $inactiveUntil = trim((string) ($existingUser['inactive_until'] ?? ''));
        if ($inactiveUntil !== '' && strtotime($inactiveUntil) !== false && strtotime($inactiveUntil) <= time()) {
            $activateStmt = $conn->prepare("UPDATE users SET account_status = 'active', inactive_until = NULL, status_reason = NULL WHERE id = ?");
            if ($activateStmt) {
                $activateStmt->bind_param('i', $existingUser['id']);
                $activateStmt->execute();
                $activateStmt->close();
            }
        }
    }
}

session_regenerate_id(true);
$_SESSION['emailormobilenumber'] = $email;
if (!empty($existingUser['id'])) {
    $_SESSION['id'] = (int) $existingUser['id'];
}
$_SESSION['google_user_email'] = $email;
$_SESSION['google_requires_business_name'] = $requiresBusinessName;

echo json_encode([
    'success' => true,
    'requires_business_name' => $requiresBusinessName,
    'redirect' => $requiresBusinessName ? '/create-account?google_complete=1' : '/business-vendor-dashboard',
    'flow' => $flow
]);

$stmt->close();
$conn->close();
