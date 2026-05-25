<?php

session_start();
include 'connection/dbconn.php';
require_once 'partials/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (empty($_SESSION['id']) || empty($_SESSION['emailormobilenumber'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => jomu_not_signed_in_message()]);
    exit;
}

jomu_require_csrf();

$userId = (int) $_SESSION['id'];
function normalizeBusinessNameInput(string $value): string {
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $stripped = strip_tags($decoded);
    $normalized = preg_replace('/\s+/u', ' ', $stripped);
    return trim((string) $normalized);
}

$newName = normalizeBusinessNameInput((string) ($_POST['businessname'] ?? ''));

$len = mb_strlen($newName);
if ($len < 3 || $len > 40) {
    echo json_encode([
        'success' => false,
        'message' => 'Business name must be between 3 and 40 characters.'
    ]);
    exit;
}

if (jomu_is_reserved_business_name($newName)) {
    echo json_encode([
        'success' => false,
        'message' => 'Try another business name.'
    ]);
    exit;
}

//Get last updated name
$stmt = $conn->prepare(
    "SELECT businessnameupdated_at FROM users WHERE id = ?"
);
$stmt->bind_param("i",$userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();


if ($row['businessnameupdated_at'] !== null) {
    $lastUpdate = new
    DateTime($row['businessnameupdated_at']);
    $now = new DateTime();
    $diff = $lastUpdate->diff($now);

    if ($diff->y === 0 && $diff->m <3) {
        echo json_encode([
            'success' => false,
            'message' => 'You can change your business name once every 3 months'
        ]);
        exit;
    }
}

$update = $conn->prepare(
    "UPDATE users SET businessname = ?, businessnameupdated_at = NOW() WHERE id = ?"
);
$update->bind_param("si", $newName, $userId);

if ($update->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}




