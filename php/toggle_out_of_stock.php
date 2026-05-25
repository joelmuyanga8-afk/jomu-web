<?php
session_start();
include "connection/dbconn.php";
require_once "partials/helpers.php";

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['emailormobilenumber'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => jomu_not_signed_in_message()]);
    exit;
}

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

jomu_require_csrf();

$userStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
$userStmt->bind_param("s", $_SESSION['emailormobilenumber']);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$userRow || empty($userRow['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User account not found.']);
    exit;
}

$ownerUserId = (int) $userRow['id'];
$listingId = (int) ($_POST['listing_id'] ?? 0);

if ($listingId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Listing is missing.']);
    exit;
}

$listingStmt = $conn->prepare(
    "SELECT listing_id, user_id, COALESCE(out_of_stock, 0) AS out_of_stock, LOWER(TRIM(COALESCE(listing_type, 'product'))) AS listing_type
     FROM listings
     WHERE listing_id = ? AND user_id = ?
     LIMIT 1"
);
$listingStmt->bind_param("ii", $listingId, $ownerUserId);
$listingStmt->execute();
$listingRow = $listingStmt->get_result()->fetch_assoc();
$listingStmt->close();

if (!$listingRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Listing not found.']);
    exit;
}

$listingType = (string) ($listingRow['listing_type'] ?? 'product');
if ($listingType !== 'product') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Only product listings can be marked out of stock.']);
    exit;
}

$newOutOfStockState = !empty($listingRow['out_of_stock']) ? 0 : 1;

$updateStmt = $conn->prepare("UPDATE listings SET out_of_stock = ? WHERE listing_id = ? AND user_id = ?");
$updateStmt->bind_param("iii", $newOutOfStockState, $listingId, $ownerUserId);
$updateStmt->execute();
$updateStmt->close();

echo json_encode([
    'success' => true,
    'listing_id' => $listingId,
    'out_of_stock' => $newOutOfStockState === 1,
    'button_label' => $newOutOfStockState === 1 ? 'Out of Stock.' : 'Mark as out of stock'
]);
exit;
