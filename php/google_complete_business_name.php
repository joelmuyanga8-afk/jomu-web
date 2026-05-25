<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/partials/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

jomu_require_csrf();

$email = trim((string) ($_SESSION['google_user_email'] ?? ''));
$needsBusinessName = (bool) ($_SESSION['google_requires_business_name'] ?? false);
function normalizeBusinessNameInput(string $value): string {
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $stripped = strip_tags($decoded);
    $normalized = preg_replace('/\s+/u', ' ', $stripped);
    return trim((string) $normalized);
}

$businessName = normalizeBusinessNameInput((string) ($_POST['business_name'] ?? ''));

if ($email === '' || !$needsBusinessName) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No Google account pending business-name completion.']);
    exit();
}

if ($businessName === '' || strlen($businessName) < 2) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid business name.']);
    exit();
}

if (jomu_is_reserved_business_name($businessName)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Try another business name.']);
    exit();
}

include __DIR__ . '/connection/dbconn.php';
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

$stmt = $conn->prepare('UPDATE users SET businessname = ? WHERE emailormobilenumber = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update business name right now.']);
    $conn->close();
    exit();
}

$stmt->bind_param('ss', $businessName, $email);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save business name.']);
    $stmt->close();
    $conn->close();
    exit();
}

$_SESSION['google_requires_business_name'] = false;

echo json_encode([
    'success' => true,
    'message' => 'Business name saved successfully.',
    'redirect' => 'php/businessvendordashboard.php'
]);

$stmt->close();
$conn->close();
