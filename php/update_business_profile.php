<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require_once 'partials/helpers.php';

if (!isset($_SESSION['emailormobilenumber'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

jomu_require_csrf();

include 'connection/dbconn.php';
const BIO_MAX_LENGTH = 200;
const BUSINESS_CONTACT_MAX_LENGTH = 60;
const BUSINESS_EMAIL_MAX_LENGTH = 120;

function normalizeBusinessNameInput(string $value): string {
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $stripped = strip_tags($decoded);
    $normalized = preg_replace('/\s+/u', ' ', $stripped);
    return trim((string) $normalized);
}

function ensureProfileColumns(mysqli $conn): bool {
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if (!$check || $check->num_rows === 0) {
        if (!$conn->query("ALTER TABLE users ADD COLUMN bio TEXT NULL")) {
            return false;
        }
    }

    $contactCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'business_contact'");
    if (!$contactCheck || $contactCheck->num_rows === 0) {
        if (!$conn->query("ALTER TABLE users ADD COLUMN business_contact VARCHAR(60) NULL")) {
            return false;
        }
    }

    $emailCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'business_email'");
    if (!$emailCheck || $emailCheck->num_rows === 0) {
        if (!$conn->query("ALTER TABLE users ADD COLUMN business_email VARCHAR(120) NULL")) {
            return false;
        }
    }

    return true;
}

if (!ensureProfileColumns($conn)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare profile fields.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, businessname, bio, business_contact, business_email, businessnameupdated_at FROM users WHERE emailormobilenumber = ? LIMIT 1");
$stmt->bind_param('s', $_SESSION['emailormobilenumber']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User account not found.']);
    exit;
}

$businessNameInput = normalizeBusinessNameInput((string) ($_POST['businessname'] ?? ''));
$bioInput = trim((string) ($_POST['bio'] ?? ''));
$businessContactInput = trim((string) ($_POST['business_contact'] ?? ''));
$businessEmailInput = trim((string) ($_POST['business_email'] ?? ''));
$updateName = ($_POST['update_name'] ?? '0') === '1';

$businessNameInput = normalizeBusinessNameInput($businessNameInput);
$bioInput = preg_replace('/\s+/', ' ', strip_tags($bioInput));
$businessContactInput = trim(strip_tags($businessContactInput));
$businessEmailInput = trim(strip_tags($businessEmailInput));

if ($updateName) {
    $nameLen = mb_strlen($businessNameInput);
    if ($nameLen < 3 || $nameLen > 40) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Business name must be between 3 and 40 characters.']);
        exit;
    }
    if (jomu_is_reserved_business_name($businessNameInput)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Try another business name.']);
        exit;
    }
}

if (mb_strlen($bioInput) > BIO_MAX_LENGTH) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Bio must be ' . BIO_MAX_LENGTH . ' characters or fewer.']);
    exit;
}

if (mb_strlen($businessContactInput) > BUSINESS_CONTACT_MAX_LENGTH) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Business contact must be ' . BUSINESS_CONTACT_MAX_LENGTH . ' characters or fewer.']);
    exit;
}

if ($businessContactInput !== '' && preg_match('/^\+?[0-9]{6,20}$/', $businessContactInput) !== 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter one valid phone number with digits and optional + country code.']);
    exit;
}

if (mb_strlen($businessEmailInput) > BUSINESS_EMAIL_MAX_LENGTH) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Business email must be ' . BUSINESS_EMAIL_MAX_LENGTH . ' characters or fewer.']);
    exit;
}

if ($businessEmailInput !== '' && preg_match('/[,;\s]/', $businessEmailInput) === 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter one email address only.']);
    exit;
}

if ($businessEmailInput !== '' && !filter_var($businessEmailInput, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid business email address.']);
    exit;
}

$canEditName = true;
$nextAllowedDate = null;
if (!empty($user['businessnameupdated_at'])) {
    $nextAllowedDate = (new DateTimeImmutable($user['businessnameupdated_at']))->modify('+3 months');
    if (new DateTimeImmutable('now') < $nextAllowedDate) {
        $canEditName = false;
    }
}

if ($updateName && !$canEditName) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Business name can only be changed every 3 months.'
    ]);
    exit;
}

$userId = (int) $user['id'];
if ($updateName) {
    $updateStmt = $conn->prepare("UPDATE users SET businessname = ?, bio = ?, business_contact = ?, business_email = ?, businessnameupdated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('ssssi', $businessNameInput, $bioInput, $businessContactInput, $businessEmailInput, $userId);
} else {
    $updateStmt = $conn->prepare("UPDATE users SET bio = ?, business_contact = ?, business_email = ? WHERE id = ?");
    $updateStmt->bind_param('sssi', $bioInput, $businessContactInput, $businessEmailInput, $userId);
}

if (!$updateStmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Update failed.']);
    $updateStmt->close();
    exit;
}
$updateStmt->close();

$freshStmt = $conn->prepare("SELECT businessname, bio, business_contact, business_email, businessnameupdated_at FROM users WHERE id = ? LIMIT 1");
$freshStmt->bind_param('i', $userId);
$freshStmt->execute();
$fresh = $freshStmt->get_result()->fetch_assoc();
$freshStmt->close();

$freshCanEditName = true;
$freshNextAllowed = '';
if (!empty($fresh['businessnameupdated_at'])) {
    $freshNextDate = (new DateTimeImmutable($fresh['businessnameupdated_at']))->modify('+3 months');
    if (new DateTimeImmutable('now') < $freshNextDate) {
        $freshCanEditName = false;
        $freshNextAllowed = $freshNextDate->format('j M Y');
    }
}

echo json_encode([
    'success' => true,
    'businessname' => (string) ($fresh['businessname'] ?? ''),
    'bio' => trim((string) ($fresh['bio'] ?? '')),
    'business_contact' => trim((string) ($fresh['business_contact'] ?? '')),
    'business_email' => trim((string) ($fresh['business_email'] ?? '')),
    'can_edit_name' => $freshCanEditName,
    'next_allowed' => $freshNextAllowed
]);
