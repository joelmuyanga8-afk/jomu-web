<?php
session_start();
include "connection/dbconn.php";
require_once "partials/helpers.php";
require_once "partials/admin_helpers.php";

jomu_ensure_admin_schema($conn);

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['emailormobilenumber'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => jomu_not_signed_in_message()]);
    // echo json_encode(['success' => false, 'message' => 'Sign in to submit.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

jomu_require_csrf();

$buyerStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
$buyerStmt->bind_param("s", $_SESSION['emailormobilenumber']);
$buyerStmt->execute();
$buyerRow = $buyerStmt->get_result()->fetch_assoc();
$buyerStmt->close();

if (!$buyerRow || empty($buyerRow['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Buyer account not found.']);
    exit;
}

$buyerUserId = (int) $buyerRow['id'];
$listingId = (int) ($_POST['listing_id'] ?? 0);
$sellerUserId = (int) ($_POST['seller_id'] ?? 0);
$listingType = strtolower(trim((string) ($_POST['listing_type'] ?? 'product')));
$amount = trim((string) ($_POST['amount'] ?? ''));
$paymentMode = trim((string) ($_POST['payment'] ?? ''));
$deliveryMethod = trim((string) ($_POST['delivery'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));

if ($listingType !== 'service') {
    $listingType = 'product';
}

if ($listingId <= 0 || $sellerUserId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Listing details are missing.']);
    exit;
}

if ($buyerUserId === $sellerUserId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'You cannot submit purchase details to your own listing.']);
    exit;
}

if ($amount === '' || $paymentMode === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Required fields are missing.']);
    exit;
}

$listingOwnerStmt = $conn->prepare(
    "SELECT l.user_id, COALESCE(l.out_of_stock, 0) AS out_of_stock
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     WHERE l.listing_id = ?
       AND COALESCE(l.moderation_status, 'visible') <> 'hidden'
     LIMIT 1"
);
$listingOwnerStmt->bind_param("i", $listingId);
$listingOwnerStmt->execute();
$listingOwnerRow = $listingOwnerStmt->get_result()->fetch_assoc();
$listingOwnerStmt->close();

if (!$listingOwnerRow || (int) ($listingOwnerRow['user_id'] ?? 0) !== $sellerUserId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Listing owner mismatch.']);
    exit;
}

if (!empty($listingOwnerRow['out_of_stock'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'This listing is currently out of stock.']);
    exit;
}

$createSql = "
CREATE TABLE IF NOT EXISTS purchase_requests (
    request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    seller_user_id INT UNSIGNED NOT NULL,
    buyer_user_id INT UNSIGNED NOT NULL,
    listing_type VARCHAR(20) NOT NULL DEFAULT 'product',
    amount VARCHAR(255) NOT NULL,
    payment_mode VARCHAR(255) NOT NULL,
    delivery_method VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller_created (seller_user_id, created_at),
    INDEX idx_listing_created (listing_id, created_at),
    INDEX idx_buyer_created (buyer_user_id, created_at)
)";

if (!$conn->query($createSql)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to prepare purchase storage.']);
    exit;
}

$insertStmt = $conn->prepare(
    "INSERT INTO purchase_requests (listing_id, seller_user_id, buyer_user_id, listing_type, amount, payment_mode, delivery_method, location, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())"
);

$insertStmt->bind_param(
    "iiisssss",
    $listingId,
    $sellerUserId,
    $buyerUserId,
    $listingType,
    $amount,
    $paymentMode,
    $deliveryMethod,
    $location
);

if (!$insertStmt->execute()) {
    $insertStmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to submit request.']);
    exit;
}

$insertStmt->close();

echo json_encode(['success' => true, 'message' => 'Request submitted.']);
exit;
