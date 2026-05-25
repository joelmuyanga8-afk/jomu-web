<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

require __DIR__ . '/connection/dbconn.php';

$default = [
    'signed_in' => false,
    'businessname' => 'Business',
    'profilepic' => 'assets/images/profile.png',
];

$emailOrMobile = trim((string) ($_SESSION['emailormobilenumber'] ?? ''));
if ($emailOrMobile === '') {
    echo json_encode($default);
    exit;
}

$stmt = $conn->prepare("SELECT businessname, profilepic FROM users WHERE emailormobilenumber = ? LIMIT 1");
if (!$stmt) {
    echo json_encode($default);
    exit;
}

$stmt->bind_param('s', $emailOrMobile);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$businessName = trim((string) ($row['businessname'] ?? ''));
$profilePic = trim((string) ($row['profilepic'] ?? ''));

echo json_encode([
    'signed_in' => true,
    'businessname' => $businessName !== '' ? $businessName : 'Business',
    'profilepic' => $profilePic !== '' ? $profilePic : 'assets/images/profile.png',
]);
