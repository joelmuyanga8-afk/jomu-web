<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/connection/dbconn.php';
require_once __DIR__ . '/partials/helpers.php';

header('Content-Type: application/json; charset=UTF-8');
jomu_reject_cross_site_request();

if (empty($_SESSION['emailormobilenumber'])) {
    echo json_encode(['ok' => true, 'tracked' => false]);
    exit;
}
jomu_require_rate_limit('track_search_interest', 120, 60 * 60, 'Too many search tracking requests. Please wait and try again.', (string) $_SESSION['emailormobilenumber']);

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload ?: '{}', true);
$searchTerm = trim((string) ($payload['term'] ?? $_POST['term'] ?? ''));
$searchTerm = strtolower(preg_replace('/\s+/', ' ', $searchTerm) ?? '');

if ($searchTerm === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing search term']);
    exit;
}

if (strlen($searchTerm) > 190) {
    $searchTerm = substr($searchTerm, 0, 190);
}

$currentUserStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
if (!$currentUserStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to resolve user']);
    exit;
}

$emailOrMobile = (string) $_SESSION['emailormobilenumber'];
$currentUserStmt->bind_param('s', $emailOrMobile);
$currentUserStmt->execute();
$currentUserRow = $currentUserStmt->get_result()->fetch_assoc();
$currentUserStmt->close();

$currentUserId = (int) ($currentUserRow['id'] ?? 0);
if ($currentUserId <= 0) {
    echo json_encode(['ok' => true, 'tracked' => false]);
    exit;
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS user_search_interest (
        user_id INT NOT NULL,
        search_term VARCHAR(190) NOT NULL,
        search_count INT NOT NULL DEFAULT 1,
        last_searched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, search_term),
        KEY idx_user_search_interest_last (last_searched_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$trackSearchStmt = $conn->prepare(
    "INSERT INTO user_search_interest (user_id, search_term, search_count, last_searched_at)
     VALUES (?, ?, 1, NOW())
     ON DUPLICATE KEY UPDATE
        search_count = search_count + 1,
        last_searched_at = NOW()"
);

if (!$trackSearchStmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to track search']);
    exit;
}

$trackSearchStmt->bind_param('is', $currentUserId, $searchTerm);
$trackSearchStmt->execute();
$trackSearchStmt->close();

echo json_encode(['ok' => true, 'tracked' => true]);
