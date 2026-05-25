<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
require "connection/dbconn.php";
require "partials/helpers.php";

if (!isset($_SESSION['emailormobilenumber'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => jomu_not_signed_in_message()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

jomu_require_csrf();

$listingId = isset($_POST['listing_id']) ? (int) $_POST['listing_id'] : 0;
if ($listingId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid listing id.']);
    exit;
}

function isPathWithinRoot(string $path, string $root): bool {
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
    return $normalizedPath === $normalizedRoot || strpos($normalizedPath, $normalizedRoot . '/') === 0;
}

function deleteListingMediaIfSafe(string $mediaPath): bool {
    $trimmed = trim($mediaPath);
    if ($trimmed === '') {
        return false;
    }

    $allowedRoots = [];
    $uploadsInPhp = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsInPhp !== false) {
        $allowedRoots[] = $uploadsInPhp;
    }
    $uploadsAtWebRoot = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads');
    if ($uploadsAtWebRoot !== false) {
        $allowedRoots[] = $uploadsAtWebRoot;
    }

    $candidates = [];
    if (preg_match('/^[a-zA-Z]:\\\\/', $trimmed) === 1 || strpos($trimmed, '/') === 0) {
        $candidates[] = $trimmed;
    } else {
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $trimmed;
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $trimmed;
    }

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            continue;
        }

        $withinAllowedRoot = false;
        foreach ($allowedRoots as $root) {
            if (isPathWithinRoot($resolved, $root)) {
                $withinAllowedRoot = true;
                break;
            }
        }

        if (!$withinAllowedRoot) {
            continue;
        }

        return @unlink($resolved);
    }

    return false;
}

function deleteListingTableRowsIfTableExists(mysqli $conn, string $tableName, int $listingId): void {
    if ($listingId <= 0 || !preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return;
    }

    $escapedTable = $conn->real_escape_string($tableName);
    $tableCheck = $conn->query("SHOW TABLES LIKE '{$escapedTable}'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return;
    }

    $stmt = $conn->prepare("DELETE FROM {$tableName} WHERE listing_id = ?");
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('i', $listingId);
    $stmt->execute();
    $stmt->close();
}

function listingTableExists(mysqli $conn, string $tableName): bool {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
        return false;
    }

    $escapedTable = $conn->real_escape_string($tableName);
    $tableCheck = $conn->query("SHOW TABLES LIKE '{$escapedTable}'");
    return $tableCheck && $tableCheck->num_rows > 0;
}

function listingMediaIsReferencedElsewhere(mysqli $conn, string $mediaPath, int $deletedListingId): bool {
    $mediaPath = trim($mediaPath);
    if ($mediaPath === '') {
        return false;
    }

    $listingRefStmt = $conn->prepare("SELECT COUNT(*) AS total FROM listings WHERE media = ? LIMIT 1");
    if ($listingRefStmt) {
        $listingRefStmt->bind_param("s", $mediaPath);
        $listingRefStmt->execute();
        $listingRefRow = $listingRefStmt->get_result()->fetch_assoc();
        $listingRefStmt->close();
        if ((int) ($listingRefRow['total'] ?? 0) > 0) {
            return true;
        }
    }

    if (!listingTableExists($conn, 'listing_gallery_images')) {
        return false;
    }

    $galleryRefStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM listing_gallery_images
         WHERE image_path = ? AND listing_id <> ?
         LIMIT 1"
    );
    if (!$galleryRefStmt) {
        return false;
    }

    $galleryRefStmt->bind_param("si", $mediaPath, $deletedListingId);
    $galleryRefStmt->execute();
    $galleryRefRow = $galleryRefStmt->get_result()->fetch_assoc();
    $galleryRefStmt->close();

    return (int) ($galleryRefRow['total'] ?? 0) > 0;
}

$userStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
$userStmt->bind_param("s", $_SESSION['emailormobilenumber']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$listingStmt = $conn->prepare("SELECT media FROM listings WHERE listing_id = ? AND user_id = ? LIMIT 1");
$listingStmt->bind_param("ii", $listingId, $user['id']);
$listingStmt->execute();
$listing = $listingStmt->get_result()->fetch_assoc();
$listingStmt->close();

if (!$listing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Listing not found or not owned by user.']);
    exit;
}

$mediaPath = (string) ($listing['media'] ?? '');
$galleryMediaPaths = getListingGalleryImages($conn, $listingId);

$deleteStmt = $conn->prepare("DELETE FROM listings WHERE listing_id = ? AND user_id = ? LIMIT 1");
$deleteStmt->bind_param("ii", $listingId, $user['id']);
$deleteStmt->execute();
$deletedRows = $deleteStmt->affected_rows;
$deleteStmt->close();

if ($deletedRows < 1) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Listing not found or not owned by user.']);
    exit;
}

$mediaDeleted = false;
if ($mediaPath !== '' && !listingMediaIsReferencedElsewhere($conn, $mediaPath, $listingId)) {
    $mediaDeleted = deleteListingMediaIfSafe($mediaPath);
}

foreach ($galleryMediaPaths as $galleryMediaPath) {
    $galleryMediaPath = (string) $galleryMediaPath;
    if (!listingMediaIsReferencedElsewhere($conn, $galleryMediaPath, $listingId)) {
        deleteListingMediaIfSafe($galleryMediaPath);
    }
}

deleteListingTableRowsIfTableExists($conn, 'listing_gallery_images', $listingId);
deleteListingTableRowsIfTableExists($conn, 'profile_pinned_listings', $listingId);
deleteListingTableRowsIfTableExists($conn, 'listing_view_stats', $listingId);

echo json_encode(['success' => true, 'media_deleted' => $mediaDeleted]);
