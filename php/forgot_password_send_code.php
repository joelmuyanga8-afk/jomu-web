<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/connection/env.php';
require_once __DIR__ . '/partials/helpers.php';
require_once __DIR__ . '/partials/smtp_mailer.php';
load_env_file(dirname(__DIR__) . '/.env');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

$method = strtolower(trim($_POST['method'] ?? ''));
$identifier = trim($_POST['identifier'] ?? '');
$genericResetMessage = 'If an account exists with those details, we have sent a reset code.';

jomu_require_csrf();

if ($method !== 'email' && $method !== 'phone') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please choose email or WhatsApp recovery.']);
    exit();
}

if ($identifier === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Email or mobile number is required.']);
    exit();
}

jomu_require_rate_limit('forgot_password_send', 5, 15 * 60, 'Too many reset attempts. Please wait and try again.', $identifier);

$isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
$isPhone = preg_match('/^(\+?256|0)?7\d{8}$/', $identifier) === 1;

if (!$isEmail && !$isPhone) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid email or Ugandan mobile number.']);
    exit();
}

if ($method === 'email' && !$isEmail) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'For email recovery, enter a valid email address.']);
    exit();
}

if ($method === 'phone' && !$isPhone) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'For WhatsApp recovery, enter a valid mobile number.']);
    exit();
}

include __DIR__ . '/connection/dbconn.php';
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$lookupCandidates = [$identifier];

if ($isEmail) {
    $emailLower = strtolower($identifier);
    if ($emailLower !== $identifier) {
        $lookupCandidates[] = $emailLower;
    }
}

if ($isPhone) {
    $digits = preg_replace('/\D+/', '', $identifier);
    if ($digits !== null) {
        if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
            $lookupCandidates[] = '+256' . substr($digits, 1);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '256')) {
            $lookupCandidates[] = '0' . substr($digits, 3);
            $lookupCandidates[] = '+' . $digits;
        } elseif (strlen($digits) === 13 && str_starts_with($digits, '2567')) {
            $lookupCandidates[] = '0' . substr($digits, 4);
        }
    }
}

$lookupCandidates = array_values(array_unique(array_filter($lookupCandidates, static fn($v) => $v !== '')));
$placeholders = implode(',', array_fill(0, count($lookupCandidates), '?'));
$sql = "SELECT emailormobilenumber FROM users WHERE emailormobilenumber IN ($placeholders) LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to process request right now.']);
    $conn->close();
    exit();
}

$types = str_repeat('s', count($lookupCandidates));
$stmt->bind_param($types, ...$lookupCandidates);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['forgot_password'] = [
        'identifier' => $identifier,
        'method' => $method,
        'otp_hash' => password_hash((string) random_int(100000, 999999), PASSWORD_DEFAULT),
        'expires_at' => time() + (10 * 60),
        'attempts' => 0,
        'verified' => false,
        'dummy' => true
    ];
    echo json_encode(['success' => true, 'message' => $genericResetMessage, 'identifier' => $identifier]);
    $stmt->close();
    $conn->close();
    exit();
}

$accountRow = $result->fetch_assoc();
$accountIdentifier = (string) ($accountRow['emailormobilenumber'] ?? '');

if ($accountIdentifier === '') {
    $_SESSION['forgot_password'] = [
        'identifier' => $identifier,
        'method' => $method,
        'otp_hash' => password_hash((string) random_int(100000, 999999), PASSWORD_DEFAULT),
        'expires_at' => time() + (10 * 60),
        'attempts' => 0,
        'verified' => false,
        'dummy' => true
    ];
    echo json_encode(['success' => true, 'message' => $genericResetMessage, 'identifier' => $identifier]);
    $stmt->close();
    $conn->close();
    exit();
}

$otp = (string) random_int(100000, 999999);
$otpHash = password_hash($otp, PASSWORD_DEFAULT);

$_SESSION['forgot_password'] = [
    'identifier' => $identifier,
    'account_identifier' => $accountIdentifier,
    'method' => $method,
    'otp_hash' => $otpHash,
    'expires_at' => time() + (10 * 60),
    'attempts' => 0,
    'verified' => false
];

$message = '';
$deliveryOk = false;

function send_smtp_otp(string $to, string $otp, ?string &$reason): bool
{
    $subject = 'JoMu Password Reset Code';
    $textBody = "Your JoMu verification code is: {$otp}\n\nThis code expires in 10 minutes.";

    return jomu_send_smtp_mail($to, $subject, $textBody, '', $reason, [
        'from_email' => env_value('SMTP_RESET_FROM_EMAIL', env_value('SMTP_FROM_EMAIL', '')),
        'from_name' => env_value('SMTP_RESET_FROM_NAME', env_value('SMTP_FROM_NAME', 'JoMu')),
    ]);
}

function format_ug_phone_to_e164(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null || $digits === '') {
        return null;
    }

    if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
        return '+256' . substr($digits, 1);
    }

    if (strlen($digits) === 12 && str_starts_with($digits, '2567')) {
        return '+' . $digits;
    }

    if (strlen($digits) === 13 && str_starts_with($digits, '2567')) {
        return '+' . substr($digits, 1);
    }

    return null;
}

function send_meta_whatsapp_otp(string $phone, string $otp, ?string &$reason): bool
{
    $token = env_value('META_WHATSAPP_TOKEN');
    $phoneNumberId = env_value('META_WHATSAPP_PHONE_NUMBER_ID');
    $templateName = env_value('META_WHATSAPP_TEMPLATE_NAME');
    $templateLang = env_value('META_WHATSAPP_TEMPLATE_LANG', 'en_US');
    $apiVersion = env_value('META_WHATSAPP_API_VERSION', 'v22.0');

    if (!$token || !$phoneNumberId || !$templateName) {
        $reason = 'Meta WhatsApp credentials/template are missing in .env.';
        return false;
    }

    if (!function_exists('curl_init')) {
        $reason = 'cURL extension is required for Meta API calls.';
        return false;
    }

    $e164 = format_ug_phone_to_e164($phone);
    if ($e164 === null) {
        $reason = 'Invalid mobile number format for WhatsApp.';
        return false;
    }

    $endpoint = 'https://graph.facebook.com/' . rawurlencode($apiVersion) . '/' . rawurlencode($phoneNumberId) . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => ltrim($e164, '+'),
        'type' => 'template',
        'template' => [
            'name' => $templateName,
            'language' => ['code' => $templateLang],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $otp]
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        $reason = 'Meta WhatsApp request failed.';
        error_log('Meta WhatsApp OTP error. HTTP: ' . $httpCode . '; cURL: ' . $curlError);
        return false;
    }

    return true;
}

$reason = '';

if ($method === 'email') {
    $deliveryOk = send_smtp_otp($accountIdentifier, $otp, $reason);
    if ($deliveryOk) {
        $message = $genericResetMessage;
    } else {
        unset($_SESSION['forgot_password']);
        http_response_code(500);
        error_log('Password reset email delivery failed: ' . $reason);
        echo json_encode(['success' => false, 'message' => 'Unable to send reset code right now. Please try again later.']);
        $stmt->close();
        $conn->close();
        exit();
    }
} else {
    $deliveryOk = send_meta_whatsapp_otp($accountIdentifier, $otp, $reason);
    if ($deliveryOk) {
        $message = $genericResetMessage;
    } else {
        unset($_SESSION['forgot_password']);
        http_response_code(500);
        error_log('Password reset WhatsApp delivery failed: ' . $reason);
        echo json_encode(['success' => false, 'message' => 'Unable to send reset code right now. Please try again later.']);
        $stmt->close();
        $conn->close();
        exit();
    }
}

echo json_encode([
    'success' => true,
    'message' => $message,
    'identifier' => $identifier
]);

$stmt->close();
$conn->close();
