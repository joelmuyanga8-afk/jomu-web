<?php

session_start();
include "connection/dbconn.php";
require "partials/helpers.php";
require "partials/admin_helpers.php";
require_once "partials/_media_upload.php";

jomu_ensure_admin_schema($conn);

if (!isset($_SESSION['emailormobilenumber'])) {
    header('location: /?error=Not+Signed+In!');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Invalid request method");
}

jomu_require_csrf();

$category = trim($_POST["category"] ?? "");
$region = trim($_POST["region"] ?? "");
$cityTown = trim($_POST["city_town"] ?? "");
$stockname = trim($_POST["stockname"] ?? "");
$description = trim($_POST["description"] ?? "");
$rawPrice = trim($_POST["price"] ?? "");
$price = str_replace([",", " "], "", $rawPrice);
$rawPriceFrom = trim($_POST["price_from"] ?? "");
$rawPriceTo = trim($_POST["price_to"] ?? "");
$priceFrom = str_replace([",", " "], "", $rawPriceFrom);
$priceTo = str_replace([",", " "], "", $rawPriceTo);
$hashtagsInput = trim($_POST["hashtags"] ?? "");
$listingTypeRaw = strtolower(trim($_POST["listing_type"] ?? ""));
$listingType = $listingTypeRaw;
if ($listingTypeRaw === 'products') {
    $listingType = 'product';
} elseif ($listingTypeRaw === 'services') {
    $listingType = 'service';
}
$user_id = null;

$userStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
$userStmt->bind_param("s", $_SESSION['emailormobilenumber']);
$userStmt->execute();
$userRow = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$userRow) {
    http_response_code(401);
    die("User not found.");
}
$user_id = (int) $userRow['id'];

$errors = [];
$priceError = null;
$maxDescriptionLengthDefault = 400;
$maxDescriptionLengthVideo = 400;
$maxHashtagsLength = 220;
$mediaTypeForValidation = '';
$maxExtraImages = 5;

function normalizeUploadedFilesArray($fileField): array
{
    if (!is_array($fileField) || !isset($fileField['name']) || !is_array($fileField['name'])) {
        return [];
    }

    $files = [];
    $total = count($fileField['name']);
    for ($index = 0; $index < $total; $index++) {
        $files[] = [
            'name' => $fileField['name'][$index] ?? '',
            'type' => $fileField['type'][$index] ?? '',
            'tmp_name' => $fileField['tmp_name'][$index] ?? '',
            'error' => $fileField['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $fileField['size'][$index] ?? 0,
        ];
    }

    return $files;
}

$uploadedMediaFiles = normalizeUploadedFilesArray($_FILES['media_files'] ?? null);
if ($uploadedMediaFiles === [] && isset($_FILES['media'])) {
    $uploadedMediaFiles = [$_FILES['media']];
}

$uploadedMediaFiles = array_values(array_filter($uploadedMediaFiles, static function ($file) {
    return (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}));
$detectedUploadMimeTypes = [];

$primaryUpload = $uploadedMediaFiles[0] ?? null;
if ($primaryUpload && isset($primaryUpload["tmp_name"]) && is_uploaded_file($primaryUpload["tmp_name"])) {
    $finfoForValidation = new finfo(FILEINFO_MIME_TYPE);
    $mimeForValidation = $finfoForValidation->file($primaryUpload["tmp_name"]);
    if (is_string($mimeForValidation) && strpos($mimeForValidation, 'video/') === 0) {
        $mediaTypeForValidation = 'video';
    } elseif (is_string($mimeForValidation) && strpos($mimeForValidation, 'image/') === 0) {
        $mediaTypeForValidation = 'image';
    }
}

if ($category === "") { 
    $errors[] = "Category is required.";
}
if (!in_array($region, ["Central", "Eastern", "Western", "Northern"], true)) {
    $errors[] = "Please choose a valid region.";
}
if ($cityTown === "") {
    $errors[] = "City/Town is required.";
}
if ($stockname === "") {
    $errors[] = "Name is required.";
}
if (!in_array($listingType, ["product", "service"], true)) {
    $errors[] = "Listing type is required.";
}
if ($uploadedMediaFiles === []) {
    $errors[] = "Please choose an image or video to upload.";
}

$descriptionLength = function_exists('mb_strlen')
    ? mb_strlen($description, 'UTF-8')
    : strlen($description);
$activeDescriptionMaxLength = $mediaTypeForValidation === 'video'
    ? $maxDescriptionLengthVideo
    : $maxDescriptionLengthDefault;
if ($descriptionLength > $activeDescriptionMaxLength) {
    $errors[] = $mediaTypeForValidation === 'video'
        ? "For video listings, description must be {$maxDescriptionLengthVideo} characters or less."
        : "Description must be {$maxDescriptionLengthDefault} characters or less.";
}
if ($hashtagsInput !== '') {
    $hashtagsLength = function_exists('mb_strlen')
        ? mb_strlen($hashtagsInput, 'UTF-8')
        : strlen($hashtagsInput);
    if ($hashtagsLength > $maxHashtagsLength) {
        $errors[] = "Hashtags must be 220 characters or less.";
    }
}

if ($mediaTypeForValidation === 'video' && count($uploadedMediaFiles) > 1) {
    $errors[] = "Video listings can only have one media file.";
}

if ($mediaTypeForValidation === 'image' && count($uploadedMediaFiles) > ($maxExtraImages + 1)) {
    $errors[] = "You can upload up to 6 images in total: 1 main image and 5 extra images.";
}

if ($uploadedMediaFiles !== []) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($uploadedMediaFiles as $uploadedFile) {
        $detectedMime = $finfo->file((string) ($uploadedFile['tmp_name'] ?? ''));
        if (!is_string($detectedMime) || $detectedMime === '') {
            $errors[] = "Unable to validate one of the selected files.";
            break;
        }
        $detectedUploadMimeTypes[] = $detectedMime;
    }

    $containsVideo = false;
    $containsImage = false;
    foreach ($detectedUploadMimeTypes as $detectedMime) {
        if (strpos($detectedMime, 'video/') === 0) {
            $containsVideo = true;
        } elseif (strpos($detectedMime, 'image/') === 0) {
            $containsImage = true;
        }
    }

    if ($containsVideo && $containsImage) {
        $errors[] = "Please upload either images only or one video only.";
    }
}

if ($listingType === "product") {
    if ($rawPriceFrom === "" || $rawPriceTo === "") {
        $priceError = "Initial and highest unit price are required for products.";
        $errors[] = $priceError;
    } elseif (!is_numeric($priceFrom) || !is_numeric($priceTo)) {
        $priceError = "Price range must be numeric values.";
        $errors[] = $priceError;
    } elseif ((float) $priceFrom > (float) $priceTo) {
        $priceError = "Highest unit price must be greater than or equal to initial price.";
        $errors[] = $priceError;
    }
} else {
    if ($rawPrice !== "" && !is_numeric($price)) {
        $priceError = "Charge must be a number.";
        $errors[] = $priceError;
    }
}

if ($description === "") {
    $description = null;
}
if ($rawPrice === "") {
    $price = null;
}
if ($rawPriceFrom === "") {
    $priceFrom = null;
}
if ($rawPriceTo === "") {
    $priceTo = null;
}

if ($errors) {
    $query = [
        "old_category" => $category,
        "old_stockname" => $stockname,
        "old_description" => (string) $description,
        "old_region" => $region,
        "old_city_town" => $cityTown,
        "old_price" => (string) $rawPrice,
        "old_price_from" => (string) $rawPriceFrom,
        "old_price_to" => (string) $rawPriceTo,
        "old_listing_type" => $listingType,
        "old_hashtags" => $hashtagsInput,
        "old_media_type" => $mediaTypeForValidation,
    ];

    if ($priceError !== null) {
        $query["price_error"] = $priceError;
    } else {
        $query["error"] = $errors[0];
    }

    header("Location: addnewlisting.php?" . http_build_query($query));
    exit;
}

if ($uploadedMediaFiles !== []) {
    $allowedTypes = [ "image/jpeg", "image/jpg", "image/png", "image/webp", "video/*"];
    $uploadDir = "uploads/profile/";
    $uploadErrorRedirect = "addnewlisting.php";
    $uploadedGalleryPaths = [];
    $firstDetectedMediaType = '';

    foreach ($uploadedMediaFiles as $index => $uploadedFile) {
        $uploadResult = processUploadedMediaFile($uploadedFile, $allowedTypes, $uploadDir);
        $detectedMime = (string) ($uploadResult['detectedMime'] ?? '');
        $currentMediaType = strpos($detectedMime, 'video/') === 0 ? 'video' : 'image';

        if ($index === 0) {
            $media = $uploadResult['targetPath'];
            $mediaType = $currentMediaType;
            $firstDetectedMediaType = $currentMediaType;
            continue;
        }

        if ($firstDetectedMediaType !== 'image' || $currentMediaType !== 'image') {
            handleUploadFailure('Only image listings can have extra images.');
        }

        $uploadedGalleryPaths[] = $uploadResult['targetPath'];
    }
} else {
    die('No image found');
}

$hashtags = null;
if ($mediaType === 'video') {
    $normalizedHashtags = preg_replace('/\s+/', ' ', $hashtagsInput);
    $normalizedHashtags = trim((string) $normalizedHashtags);
    if ($normalizedHashtags !== '') {
        $hashtags = $normalizedHashtags;
    }
}


// Backward-compatible schema update for listing type support.
$hasListingType = false;
$listingTypeColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'listing_type'");
if ($listingTypeColumnCheck && $listingTypeColumnCheck->num_rows > 0) {
    $hasListingType = true;
} else {
    $conn->query("ALTER TABLE listings ADD COLUMN listing_type VARCHAR(20) NOT NULL DEFAULT 'product'");
    $listingTypeColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'listing_type'");
    if ($listingTypeColumnCheck && $listingTypeColumnCheck->num_rows > 0) {
        $hasListingType = true;
    }
}

$hasPriceFrom = false;
$hasPriceTo = false;
$priceFromColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'price_from'");
if ($priceFromColumnCheck && $priceFromColumnCheck->num_rows > 0) {
    $hasPriceFrom = true;
} else {
    $conn->query("ALTER TABLE listings ADD COLUMN price_from VARCHAR(30) NULL AFTER price");
    $priceFromColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'price_from'");
    if ($priceFromColumnCheck && $priceFromColumnCheck->num_rows > 0) {
        $hasPriceFrom = true;
    }
}

$hasHashtags = false;
$hashtagsColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'hashtags'");
if ($hashtagsColumnCheck && $hashtagsColumnCheck->num_rows > 0) {
    $hasHashtags = true;
} else {
    $conn->query("ALTER TABLE listings ADD COLUMN hashtags VARCHAR(220) NULL AFTER description");
    $hashtagsColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'hashtags'");
    if ($hashtagsColumnCheck && $hashtagsColumnCheck->num_rows > 0) {
        $hasHashtags = true;
    }
}

$priceToColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'price_to'");
if ($priceToColumnCheck && $priceToColumnCheck->num_rows > 0) {
    $hasPriceTo = true;
} else {
    $conn->query("ALTER TABLE listings ADD COLUMN price_to VARCHAR(30) NULL AFTER price_from");
    $priceToColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'price_to'");
    if ($priceToColumnCheck && $priceToColumnCheck->num_rows > 0) {
        $hasPriceTo = true;
    }
}

$hasRegion = false;
$regionColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'region'");
if ($regionColumnCheck && $regionColumnCheck->num_rows > 0) {
    $hasRegion = true;
} else {
    $conn->query("ALTER TABLE listings ADD COLUMN region VARCHAR(20) NULL AFTER category");
    $regionColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'region'");
    if ($regionColumnCheck && $regionColumnCheck->num_rows > 0) {
        $hasRegion = true;
    }
}

$hasCityTown = false;
$cityTownColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'city_town'");
if ($cityTownColumnCheck && $cityTownColumnCheck->num_rows > 0) {
    $hasCityTown = true;
} else {
    $conn->query("ALTER TABLE listings ADD COLUMN city_town VARCHAR(120) NULL AFTER region");
    $cityTownColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'city_town'");
    if ($cityTownColumnCheck && $cityTownColumnCheck->num_rows > 0) {
        $hasCityTown = true;
    }
}

// Ensure old schemas do not truncate listing descriptions (for example VARCHAR(179)).
$descriptionColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'description'");
if ($descriptionColumnCheck && $descriptionColumnCheck->num_rows > 0) {
    $descriptionColumn = $descriptionColumnCheck->fetch_assoc();
    $descriptionType = strtolower(trim((string) ($descriptionColumn['Type'] ?? '')));
    if (preg_match('/^varchar\\((\\d+)\\)$/', $descriptionType, $descriptionMatch) === 1) {
        $currentDescriptionLength = (int) ($descriptionMatch[1] ?? 0);
        if ($currentDescriptionLength > 0 && $currentDescriptionLength < $maxDescriptionLengthDefault) {
            $conn->query("ALTER TABLE listings MODIFY COLUMN description VARCHAR(400) NULL");
        }
    }
}

if ($listingType === "product" && $priceFrom !== null && $priceTo !== null) {
    $price = "USh " . $priceFrom . " - " . $priceTo . " / unit";
}

ensureListingGalleryTable($conn);

if ($hasListingType && $hasPriceFrom && $hasPriceTo && $hasHashtags && $hasRegion && $hasCityTown) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, region, city_town, media, stockname, description, hashtags, price, price_from, price_to, listing_type, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "isssssssssss",
        $user_id, $category, $region, $cityTown, $media, $stockname, $description, $hashtags, $price, $priceFrom, $priceTo, $listingType
    );
} elseif ($hasListingType && $hasPriceFrom && $hasPriceTo && $hasRegion && $hasCityTown) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, region, city_town, media, stockname, description, price, price_from, price_to, listing_type, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "issssssssss",
        $user_id, $category, $region, $cityTown, $media, $stockname, $description, $price, $priceFrom, $priceTo, $listingType
    );
} elseif ($hasListingType && $hasHashtags && $hasRegion && $hasCityTown) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, region, city_town, media, stockname, description, hashtags, price, listing_type, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "isssssssss",
        $user_id, $category, $region, $cityTown, $media, $stockname, $description, $hashtags, $price, $listingType
    );
} elseif ($hasListingType && $hasRegion && $hasCityTown) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, region, city_town, media, stockname, description, price, listing_type, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "issssssss",
        $user_id, $category, $region, $cityTown, $media, $stockname, $description, $price, $listingType
    );
} elseif ($hasHashtags && $hasRegion && $hasCityTown) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, region, city_town, media, stockname, description, hashtags, price, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "issssssss",
        $user_id, $category, $region, $cityTown, $media, $stockname, $description, $hashtags, $price
    );
} elseif ($hasRegion && $hasCityTown) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, region, city_town, media, stockname, description, price, created_at) VALUES (?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "isssssss",
        $user_id, $category, $region, $cityTown, $media, $stockname, $description, $price
    );
} elseif ($hasListingType && $hasPriceFrom && $hasPriceTo && $hasHashtags) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, media, stockname, description, hashtags, price, price_from, price_to, listing_type, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "isssssssss",
        $user_id, $category, $media, $stockname, $description, $hashtags, $price, $priceFrom, $priceTo, $listingType
    );
} elseif ($hasListingType && $hasPriceFrom && $hasPriceTo) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, media, stockname, description, price, price_from, price_to, listing_type, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "issssssss",
        $user_id, $category, $media, $stockname, $description, $price, $priceFrom, $priceTo, $listingType
    );
} elseif ($hasListingType && $hasHashtags) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, media, stockname, description, hashtags, price, listing_type, created_at) VALUES (?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "isssssss",
        $user_id, $category, $media, $stockname, $description, $hashtags, $price, $listingType
    );
} elseif ($hasListingType) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, media, stockname, description, price, listing_type, created_at) VALUES (?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "issssss",
        $user_id, $category, $media, $stockname, $description, $price, $listingType
    );
} elseif ($hasHashtags) {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, media, stockname, description, hashtags, price, created_at) VALUES (?,?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "issssss",
        $user_id, $category, $media, $stockname, $description, $hashtags, $price
    );
} else {
    $stmt = $conn->prepare(
        "INSERT INTO listings (user_id, category, media, stockname, description, price, created_at) VALUES (?,?,?,?,?,?,NOW())"
    );

    $stmt->bind_param(
        "isssss",
        $user_id, $category, $media, $stockname, $description, $price
    );
}

if (!$stmt->execute()) {
    error_log('Listing insert failed: ' . $stmt->error);
    http_response_code(500);
    die("Unable to create listing right now. Please try again.");
}


$listing_id = $conn->insert_id;

if ($listing_id > 0 && $mediaType === 'image' && !empty($uploadedGalleryPaths)) {
    $galleryStmt = $conn->prepare(
        "INSERT INTO listing_gallery_images (listing_id, image_path, sort_order, created_at)
         VALUES (?, ?, ?, NOW())"
    );

    if ($galleryStmt) {
        foreach ($uploadedGalleryPaths as $index => $imagePath) {
            $sortOrder = $index + 1;
            $galleryStmt->bind_param("isi", $listing_id, $imagePath, $sortOrder);
            $galleryStmt->execute();
        }
        $galleryStmt->close();
    }
}

$stmt->close();
$conn->close();

header('Location: profile.php?listing_id='.$listing_id);
exit;
