<?php
/// Php for listings.
 session_start();

 if (!isset($_SESSION['emailormobilenumber'])) {
    header('location: /?error=Not+Signed+In!');
    exit;
 }

include "connection/dbconn.php";
include "partials/helpers.php";
include "partials/admin_helpers.php";

jomu_ensure_admin_schema($conn);

function decodeLegacyHtmlEntities(string $value): string {
    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

 $new_listing = null;
 $other_listings = [];

 $profileImage = "/assets/images/profile.png";
 $stmt = $conn->prepare("SELECT * from users WHERE emailormobilenumber = ? limit 1");
 $stmt->bind_param('s', $_SESSION['emailormobilenumber']);
 $stmt->execute();
 $user = $stmt->get_result()->fetch_assoc();
 if (!empty($user['profilepic'])) {
    $profileImage = jomu_resolve_public_profile_image_path((string) $user['profilepic']);
 }

$jomuDashboardUrl = jomu_page_url('dashboard');

$profileUserId = (int) ($user['id'] ?? 0);

$conn->query(
    "CREATE TABLE IF NOT EXISTS profile_pinned_listings (
        user_id INT NOT NULL,
        listing_id INT NOT NULL,
        pinned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, listing_id),
        KEY idx_profile_pinned_listings_user_time (user_id, pinned_at),
        KEY idx_profile_pinned_listings_listing (listing_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['profile_pin_action'] ?? '') !== '') {
    header('Content-Type: application/json; charset=UTF-8');
    jomu_require_csrf();

    $pinAction = (string) ($_POST['profile_pin_action'] ?? '');
    $pinListingId = (int) ($_POST['listing_id'] ?? 0);

    if ($profileUserId <= 0 || $pinListingId <= 0 || !in_array($pinAction, ['pin', 'unpin'], true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid pin request.']);
        exit;
    }

    $ownerCheckStmt = $conn->prepare("SELECT listing_id FROM listings WHERE listing_id = ? AND user_id = ? AND COALESCE(moderation_status, 'visible') <> 'hidden' LIMIT 1");
    if (!$ownerCheckStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to check listing.']);
        exit;
    }

    $ownerCheckStmt->bind_param('ii', $pinListingId, $profileUserId);
    $ownerCheckStmt->execute();
    $ownerListing = $ownerCheckStmt->get_result()->fetch_assoc();
    $ownerCheckStmt->close();

    if (!$ownerListing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Listing not found.']);
        exit;
    }

    if ($pinAction === 'unpin') {
        $unpinStmt = $conn->prepare("DELETE FROM profile_pinned_listings WHERE user_id = ? AND listing_id = ? LIMIT 1");
        if (!$unpinStmt) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to unpin listing.']);
            exit;
        }
        $unpinStmt->bind_param('ii', $profileUserId, $pinListingId);
        $unpinStmt->execute();
        $unpinStmt->close();

        echo json_encode(['success' => true, 'pinned' => false, 'message' => 'Listing unpinned.']);
        exit;
    }

    $pinCountStmt = $conn->prepare("SELECT COUNT(*) AS pin_count FROM profile_pinned_listings WHERE user_id = ?");
    if (!$pinCountStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to count pinned listings.']);
        exit;
    }
    $pinCountStmt->bind_param('i', $profileUserId);
    $pinCountStmt->execute();
    $pinCountRow = $pinCountStmt->get_result()->fetch_assoc();
    $pinCountStmt->close();

    $pinExistsStmt = $conn->prepare("SELECT 1 FROM profile_pinned_listings WHERE user_id = ? AND listing_id = ? LIMIT 1");
    if (!$pinExistsStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to check pin.']);
        exit;
    }
    $pinExistsStmt->bind_param('ii', $profileUserId, $pinListingId);
    $pinExistsStmt->execute();
    $alreadyPinned = (bool) $pinExistsStmt->get_result()->fetch_row();
    $pinExistsStmt->close();

    if (!$alreadyPinned && (int) ($pinCountRow['pin_count'] ?? 0) >= 6) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'You can pin up to 6 listings.']);
        exit;
    }

    $pinStmt = $conn->prepare(
        "INSERT INTO profile_pinned_listings (user_id, listing_id, pinned_at)
         VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE pinned_at = NOW()"
    );
    if (!$pinStmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to pin listing.']);
        exit;
    }
    $pinStmt->bind_param('ii', $profileUserId, $pinListingId);
    $pinStmt->execute();
    $pinStmt->close();

    echo json_encode(['success' => true, 'pinned' => true, 'message' => 'Listing pinned.']);
    exit;
}

$businessName = trim(decodeLegacyHtmlEntities((string) ($user['businessname'] ?? '')));
$businessBio = trim((string) ($user['bio'] ?? ''));
$businessContact = trim((string) ($user['business_contact'] ?? ''));
$businessEmail = trim((string) ($user['business_email'] ?? ''));
if ($businessBio === '') {
    $businessBio = 'Tell customers about your business.';
}

$businessNameCanEdit = true;
$businessNameNextAllowed = '';
if (!empty($user['businessnameupdated_at'])) {
    $nextAllowed = (new DateTimeImmutable($user['businessnameupdated_at']))->modify('+3 months');
    $now = new DateTimeImmutable('now');
    if ($now < $nextAllowed) {
        $businessNameCanEdit = false;
        $businessNameNextAllowed = $nextAllowed->format('j M Y');
    }
}

$listing_id = null;
if(isset($_GET['listing_id']) && $_GET['listing_id'] !== "") {
    $listing_id = intval($_GET['listing_id']); 
}
if ($listing_id !== null) {
    $stmt = $conn->prepare("SELECT * FROM listings WHERE listing_id = ? AND user_id = ? AND admin_purged_at IS NULL");
    $stmt->bind_param("ii",$listing_id, $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $new_listing = $result->fetch_assoc();
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT * FROM listings WHERE listing_id != ? AND user_id = ? AND admin_purged_at IS NULL ORDER BY listing_id DESC");
    if ($stmt) {
        $stmt->bind_param("ii", $listing_id, $user['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $other_listings[] = $row;
        }
        $stmt->close();
    }
} else {
    $res = $conn->query("SELECT * FROM listings WHERE user_id = " . (int) $profileUserId . " AND admin_purged_at IS NULL ORDER BY listing_id DESC");
    while ($row = $res->fetch_assoc()){
        $other_listings[] = $row;
    }
        
}

$profilePinnedListings = [];
$profilePinnedStmt = $conn->prepare("SELECT listing_id, pinned_at FROM profile_pinned_listings WHERE user_id = ?");
if ($profilePinnedStmt) {
    $profilePinnedStmt->bind_param('i', $profileUserId);
    $profilePinnedStmt->execute();
    $profilePinnedResult = $profilePinnedStmt->get_result();
    while ($pinRow = $profilePinnedResult->fetch_assoc()) {
        $profilePinnedListings[(int) ($pinRow['listing_id'] ?? 0)] = (string) ($pinRow['pinned_at'] ?? '');
    }
    $profilePinnedStmt->close();
}

$profileListings = $other_listings;
if ($new_listing) {
    $profileListings[] = $new_listing;
}

foreach ($profileListings as &$profileListing) {
    $profileListingId = (int) ($profileListing['listing_id'] ?? 0);
    $profileListing['_profile_is_pinned'] = isset($profilePinnedListings[$profileListingId]);
    $profileListing['_profile_pinned_at'] = $profilePinnedListings[$profileListingId] ?? '';
}
unset($profileListing);

usort($profileListings, function (array $a, array $b): int {
    $aPinned = !empty($a['_profile_is_pinned']);
    $bPinned = !empty($b['_profile_is_pinned']);
    if ($aPinned !== $bPinned) {
        return $aPinned ? -1 : 1;
    }

    if ($aPinned && $bPinned) {
        return strcmp((string) ($b['_profile_pinned_at'] ?? ''), (string) ($a['_profile_pinned_at'] ?? ''));
    }

    return (int) ($b['listing_id'] ?? 0) <=> (int) ($a['listing_id'] ?? 0);
});

$productListingsCount = 0;
$serviceListingsCount = 0;
$allListingsForTypeCount = $profileListings;

foreach ($allListingsForTypeCount as $listingForType) {
    $listingTypeValue = strtolower(trim((string) ($listingForType['listing_type'] ?? '')));
    if ($listingTypeValue === 'services') {
        $listingTypeValue = 'service';
    } elseif ($listingTypeValue === 'products') {
        $listingTypeValue = 'product';
    }

    if ($listingTypeValue !== 'service' && $listingTypeValue !== 'product') {
        $categoryText = strtolower((string) ($listingForType['category'] ?? ''));
        $listingTypeValue = strpos($categoryText, 'service') !== false ? 'service' : 'product';
    }

    if ($listingTypeValue === 'service') {
        $serviceListingsCount++;
    } else {
        $productListingsCount++;
    }
}

$showShowroomTitle = $productListingsCount > $serviceListingsCount;
$visitorProfileSharePath = '/visitor-profile?user_id=' . (int) ($user['id'] ?? 0);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Profile</title>
    <link rel="stylesheet" href="/assets/bootstrap.css">
    <link rel="stylesheet" href="/assets/style.css">
        <link rel="icon" type="image/png" sizes="16x16" href="/./assets/images/jomu_favicon_orange-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/./assets/images/jomu_favicon_orange-32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/./assets/images/jomu_favicon_orange-48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/./assets/images/jomu_favicon_orange-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/./assets/images/jomu_favicon_orange-512.png">
    <style>
        body.profile-page {
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            padding: 68px 0 0;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }

        body.profile-page > footer {
            margin-top: auto;
        }

        @media (max-width: 767.98px) {
            body.profile-page {
                padding-top: 45px;
            }

            body.profile-page #navbarone {
                height: 45px;
                min-height: 45px;
                padding-top: 0;
                padding-bottom: 0;
                align-items: center;
                line-height: 1;
            }

            body.profile-page #navbarone .profile-nav-inner {
                min-height: 45px;
                align-items: center;
            }
        }

        .profile-business-summary {
            --profile-summary-gap: 0.75rem;
            margin-bottom: var(--profile-summary-gap) !important;
        }

        .profile-page .business-name-wrapper h2 {
            margin: 0;
            line-height: 1;
        }

        .profile-page #business-name-input {
            display: block;
            min-height: 0 !important;
            padding-top: 0;
            padding-bottom: 0;
            line-height: 1.08;
        }

        .business-bio-wrapper {
            margin-top: var(--profile-summary-gap);
        }

        .profile-page #business-bio-input {
            margin: 0 auto;
        }

        .profile-page #business-profile-status {
            margin-top: 4px !important;
        }

        .profile-showroom-grid .owner-hidden-card {
            position: relative;
            overflow: hidden;
        }

        .profile-showroom-grid .owner-hidden-card .media-preview-source,
        .profile-showroom-grid .owner-hidden-card video {
            filter: blur(5px) grayscale(0.45);
            opacity: 0.62;
            pointer-events: none;
        }

        .profile-showroom-grid .owner-hidden-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 4;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .profile-page .footer-links {
            gap: 4px 8px;
        }

        #profileMediaPreviewOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: flex-start;
            justify-content: center;
            z-index: 9999;
            padding: 8px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        #profileMediaPreviewOverlay.active {
            display: flex;
        }

        body.profile-preview-open {
            overflow: hidden;
        }

        .profile-media-preview-content {
            max-width: 100%;
            max-height: 72vh;
            width: auto;
            height: auto;
            object-fit: contain;
            background: #000;
        }

        .profile-media-preview-panel {
            width: 100%;
            max-width: none;
            min-height: calc(100svh - 16px);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .profile-media-preview-details {
            width: 100%;
            box-sizing: border-box;
            background: rgba(9, 9, 9, 0.82);
            color: #fff;
            border-radius: 6px;
            padding: 8px;
            text-align: left;
        }

        .profile-media-preview-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.3;
            color: rgb(241, 90, 36);
        }

        .profile-media-preview-price,
        .profile-media-preview-description {
            margin: 4px 0 0;
            font-size: 0.9rem;
            line-height: 1.35;
        }

        .profile-media-preview-close {
            position: absolute;
            top: 14px;
            right: 16px;
            border: 0;
            background: transparent;
            color: #fff;
            font-size: 34px;
            line-height: 1;
            cursor: pointer;
            padding: 2px 8px;
        }

        .showroom-img .video-wrapper {
            padding-top: 0;
            height: 400px;
        }

        .profile-showroom-grid .dots-icon {
            cursor: pointer;
            margin-top: 30px;
        }

        .profile-showroom-grid .dropdown:hover .dropdown-content {
            display: none;
        }

        .profile-showroom-grid .dropdown.is-open .dropdown-content {
            display: block;
            animation: slideDown 0.25s;
        }

        .profile-showroom-grid .card-options .dropdown-content {
            top: 74px !important;
        }

        .profile-showroom-grid .dropdown-content .li-1 {
            padding-right: 0;
        }

        .profile-showroom-grid .dropdown-content .li-1::after {
            display: none;
        }

        #profileDeleteListingModal .modal-header {
            justify-content: center;
            position: relative;
        }

        #profileDeleteListingModal .modal-title {
            width: 100%;
            text-align: center;
        }

        #profileDeleteListingModal .btn-close {
            position: absolute;
            right: 1rem;
        }

        @media (max-width: 767.98px) {
            .profile-page .footer-feedback br {
                display: none;
            }

            .profile-page .footer-feedback small {
                display: block;
                margin-top: 0;
            }
        }

        #profileDeleteListingModal .modal-footer {
            justify-content: center;
        }

        .profile-pinned-badge {
            position: absolute;
            top: 6px;
            left: 6px;
            z-index: 9;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 8px;
            border-radius: 6px;
            background-color: rgb(241, 90, 36);
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            line-height: 1.1;
            pointer-events: none;
        }

        @media (max-width: 767.98px) {
            .profile-pinned-badge {
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 0.68rem;
            }
        }

        .profile-toast-message {
            position: fixed;
            left: 50%;
            bottom: 22px;
            transform: translateX(-50%) translateY(12px);
            z-index: 10000;
            max-width: min(88vw, 320px);
            padding: 7px 11px;
            border-radius: 6px;
            background: rgba(22, 21, 21, 0.92);
            color: #fff;
            font-size: 0.78rem;
            font-weight: 600;
            line-height: 1.25;
            text-align: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .profile-toast-message.is-visible {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        @media (max-width: 767.98px) {
            .profile-showroom-card .card-img-showroom {
                width: 100%;
                height: auto;
                aspect-ratio: 4 / 5;
                object-fit: cover;
            }

            .profile-showroom-card .video-wrapper {
                height: auto;
                padding-top: 125%;
            }

            .profile-showroom-grid .dots-icon {
                width: 24px;
                height: 40px;
                margin-top: 30px;
            }

            .profile-showroom-grid .card-options .dropdown-content {
                top: 64px !important;
            }
        }

        .owner-out-of-stock-card {
            border: 3px solid #dc3545 !important;
        }

    </style>
</head>

<body class="bg-white profile-page">
    <header>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top navbarone navbar-help bg-dark" id="navbarone">
            <div class="container-fluid profile-nav-inner">
                <a class="mobile-profile-back d-flex d-md-none" href="<?php echo htmlspecialchars($jomuDashboardUrl); ?>" aria-label="Go back">←</a>
                <h2 id="nav-business-name-mobile" class="mobile-profile-title d-block d-md-none"><?php echo htmlspecialchars($businessName !== '' ? $businessName : ($_SESSION['emailormobilenumber'] ?? 'Business')); ?></h2>
                <a class="navbar-brand brand-logos d-none d-md-inline-block" href="/">
                    <img src="/assets/images/JoMu black and white.png" class="img-fluid logo">
                    <img src="/assets/images/JoMu logo redesigned.png" class="img-fluid logo logo-hover">
                </a>
                <!-- <h2 class="d-none d-md-block d-lg-block" style="color: white;">M&M Cleaning Company Ltd</h2> -->
                 <h2 id="nav-business-name" class="d-none d-md-block d-lg-block" style="color: white;"><?php echo htmlspecialchars($businessName !== '' ? $businessName : ($_SESSION['emailormobilenumber'] ?? 'Business')); ?></h2>
                <button class="button button-createaccount d-none d-md-inline-block"
                    onclick="location.href='<?php echo htmlspecialchars($jomuDashboardUrl, ENT_QUOTES); ?>'">Dashboard</button>
                <div class="mobile-nav-spacer d-md-none" aria-hidden="true"></div>
            </div>
            </div>
        </nav>
    </header>
    <main>

    <div class="container text-center mt-2">
        <div class="profile-pic-container position-relative d-inline-block">
            <img id="profile-pic-preview" src="<?php echo htmlspecialchars($profileImage); ?>" class="img-fluid profile-img" alt="Profile Picture">
            <input type="file" id="profile-pic" name="profile-pic" accept="image/*" style="display:none;">
            <button class=" rounded-circle position-absolute bottom-0 end-0 translate-middle p-0 text-center add-icon-btn" id="add-icon"
            ><span class="add-icon-glyph">+</span></button>
        </div>
    </div>

        <div class="container text-center mt-2 profile-business-summary">
            <div class="business-name-wrapper">
                <h2>
                    <textarea
                        id="business-name-input"
                        class="form-control text-center fw-bold border-0 bg-transparent"
                        rows="1"
                        wrap="soft"
                        maxlength="40"
                        aria-label="Business name"
                        disabled
                    ><?php echo htmlspecialchars($businessName); ?></textarea>
                </h2>
                <small
                    class="warning"
                    id="business-name-rule"
                    data-can-edit-name="<?php echo $businessNameCanEdit ? '1' : '0'; ?>"
                    data-next-allowed="<?php echo htmlspecialchars($businessNameNextAllowed); ?>"
                    style="display: none; color: #b00020;"
                >
                    <?php if ($businessNameCanEdit): ?>
                        <!-- You can change your business name once every 3 months. -->
                    <?php else: ?>
                        Business name can be changed after <?php echo htmlspecialchars($businessNameNextAllowed); ?>.
                    <?php endif; ?>
                </small>
            </div>

            <div class="business-bio-wrapper">
                <h6
                    id="business-bio-input"
                    contenteditable="false"
                    data-maxlength="200"
                    style="white-space: pre-wrap;"
                ><?php echo htmlspecialchars($businessBio); ?></h6>
            </div>
            <small id="business-profile-status" style="display: block; color: #b00020; margin-top: 6px;"></small>
        </div>
        <div class="contacts-profile text-center mb-2">
            <button id="business-contact-btn" class="btn" type="button" style="background-color: rgb(206, 207, 207);">
                <?php echo htmlspecialchars($businessContact !== '' ? $businessContact : 'Contact'); ?>
            </button>
            <button id="business-email-btn" class="btn" type="button" style="background-color: rgb(206, 207, 207);">
                <?php echo htmlspecialchars($businessEmail !== '' ? $businessEmail : 'Email'); ?>
            </button>
            <div id="business-contact-editor-wrap" class="mt-2 px-3 d-none">
                <input
                    id="business-contact-input"
                    class="form-control form-control-sm mx-auto"
                    type="tel"
                    inputmode="tel"
                    maxlength="20"
                    pattern="^\+?[0-9]{6,20}$"
                    style="max-width: 208px;"
                    placeholder="Enter business phone number"
                    aria-label="Business contact number"
                    autocomplete="off"
                    value="<?php echo htmlspecialchars($businessContact); ?>"
                >
            </div>
            <div id="business-email-editor-wrap" class="mt-2 px-3 d-none">
                <input
                    id="business-email-input"
                    class="form-control form-control-sm mx-auto"
                    type="email"
                    inputmode="email"
                    maxlength="120"
                    style="max-width: 300px;"
                    placeholder="Enter business email e.g name@example.com"
                    aria-label="Business email"
                    autocomplete="off"
                    value="<?php echo htmlspecialchars($businessEmail); ?>"
                >
            </div>
        </div>
        <!-- For the user business themselves.  -->
        <div class="text-center mb-4">
            <button id="edit-business-profile-btn" class="btn bg-dark" style="color: white;">Edit Business Profile</button>
            <button id="save-business-profile-btn" class="btn bg-dark d-none" style="color: white;">Save Profile</button>
            <button id="cancel-business-profile-btn" class="btn bg-secondary d-none" style="color: white;">Cancel</button>
            <button id="share-business-profile-btn" class="btn bg-dark" style="color: white;" data-share-url="<?php echo htmlspecialchars($visitorProfileSharePath); ?>">Share Business Profile</button>
        </div>

        <?php if ($showShowroomTitle): ?>
            <div class="text-center">
                <h5 style="color: rgb(241,90,36);">Showroom</h5>
            </div>
        <?php endif; ?>
        
            <!-- Product Card -->


        <div class="container my-2 cards-container profile-showroom-grid">
            <div class="row g-1">
                <?php if (!empty($profileListings) ) {echo '<h4 class="profile-listings-count-title">Listings: ' . count($profileListings) . '</h4>'; } else {echo "<h3>No Listings Found!</h3>";}

                 foreach ($profileListings as $listing) { ?>
                    <div class="col-4 col-md-4 col-lg-3">
                        <?php
                            $base = '';
                            $listCardClass = 'profile-showroom-card';
                            $showManageMenu = true;
                            $showProfilePinOption = true;
                            $shareSellerBusinessName = $businessName;
                            $shareSellerProfilePic = $profileImage;
                            $ownerHiddenShowroomCard = strtolower((string) ($listing['moderation_status'] ?? 'visible')) === 'hidden';
                            include __DIR__ . '/../components/list_item.php';
                        ?>
                    </div>
                <?php } ?>
            </div>
        </div>

            <!-- <div class="row g-0 showroom-container">
            <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100 showroom-img">
                    <img src="/assets/images/Watermelon1.jpeg" class="card-img-showroom img-fluid" alt="jumper">
                    <div class="card-views">
                        <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                        <p>1.1M Views</p>
                    </div>
                </div>
            </div>

            <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100 showroom-img">
                    <img src="/assets/images/Curtains.jpeg" class="card-img-showroom img-fluid" alt="jumper">
                    <div class="card-views">
                        <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                        <p>120 Views</p>
                    </div>
                </div>
            </div>

            <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100 showroom-img">
                    <img src="/assets/images/Laptops.jpeg" class="card-img-showroom img-fluid" alt="jumper">
                    <div class="card-views">
                        <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                        <p>100k Views</p>
                    </div>
                </div>
            </div>

            <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100 showroom-img">
                    <img src="/assets/images/Beverages.jpeg" class="card-img-showroom img-fluid" alt="jumper">
                    <div class="card-views">
                        <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                        <p>100k Views</p>
                    </div>
                </div>
            </div> -->

        </div>
        <!-- <div class="row g-0 video-container-showroom"> -->
            <!-- Video card -->
            <!-- <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100">
                    <div class="video-wrapper">
                        <video class="video-content" controls muted>
                            <source src="/assets/videos/Video (1).mp4" type="video/mp4">
                            <div class="card-views">
                                <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                                <p>100k Views</p>
                            </div>

                        </video>
                    </div>
                </div>
            </div>

            <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100">
                    <div class="video-wrapper">
                        <video class="video-content" controls muted>
                            <source src="/assets/videos/Video (2).mp4" type="video/mp4">
                            <div class="card-views">
                                <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                                <p>100k Views</p>
                            </div>
                        </video>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100">
                    <div class="video-wrapper">
                        <video class="video-content" controls muted>
                            <source src="/assets/videos/Video (3).mp4" type="video/mp4">
                            <div class="card-views">
                                <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                                <p>100k Views</p>
                            </div>
                        </video>
                    </div>
                </div>
            </div>


            <div class="col-4 col-md-4 col-lg-3">
                <div class="card h-100">
                    <div class="video-wrapper">
                        <video class="video-content" controls muted>
                            <source src="/assets/videos/Video (4).mp4" type="video/mp4">
                            <div class="card-views">
                                <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
                                <p>100k Views</p>
                            </div>
                        </video>
                    </div>
                </div>
            </div>
        </div>
        </div> -->


    </main>
    <div id="profileMediaPreviewOverlay" aria-hidden="true">
        <button type="button" class="profile-media-preview-close" id="profileMediaPreviewClose" aria-label="Close preview">&times;</button>
        <div class="profile-media-preview-panel">
            <img id="profileMediaPreviewImage" class="profile-media-preview-content" alt="Listing preview" style="display:none;">
            <video id="profileMediaPreviewVideo" class="profile-media-preview-content" controls style="display:none;"></video>
            <div id="profileMediaPreviewDetails" class="profile-media-preview-details" style="display:none;">
                <p id="profileMediaPreviewTitle" class="profile-media-preview-title"></p>
                <p id="profileMediaPreviewPrice" class="profile-media-preview-price"></p>
                <p id="profileMediaPreviewDescription" class="profile-media-preview-description"></p>
            </div>
        </div>
    </div>
    <div class="modal fade" id="profileDeleteListingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Listing</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-footer">
                    <button type="button" id="profile-proceed-delete-btn" class="btn" style="background-color: red; color: white;">Proceed</button>
                    <button type="button" id="profile-decline-delete-btn" class="btn" style="background-color: green; color: white;">Decline</button>
                </div>
            </div>
        </div>
    </div>
    <footer class=" footer-feedback py-2 text-center bg-white">
        <div class="footer-links">
            <a href="/terms-and-conditions">Terms of Use</a>
            <a href="/privacy-policy">Privacy Policy</a>
            <a href="/help">Help</a>
            <a href="/support">Support</a>
            <a href="/feedback">Give Feedback</a>
            <a href="/about">About JoMu</a>
        </div>
        <br>
        <small>&copy; 2026 JoMu. All rights reserved.</small>
    </footer>

    <script>
        const jomuUserCsrfToken = <?php echo json_encode(jomu_csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const JOMU_PHP_ROOT = '/php/';
        // Profile pic change functionality. 
        const profilePicInput = document.getElementById('profile-pic');
        const profilePicPreview = document.getElementById('profile-pic-preview');
        const addIcon = document.getElementById('add-icon');

        addIcon.addEventListener('click',()=> {
            profilePicInput.click();
        });

        profilePicInput.addEventListener('change',()=> {
            const file = profilePicInput.files[0];
            const imageUrl = URL.createObjectURL(file);
            profilePicPreview.src = imageUrl;

            const formData = new FormData();
            formData.append('profile-pic',file);
            formData.append('csrf_token', jomuUserCsrfToken);

            fetch(JOMU_PHP_ROOT + 'upload_profile.php', {
                method: 'POST',
                body: formData,
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    profilePicPreview.src = data.imageUrl;
                } else {
                    console.error(data.error);
                }
            })
            .catch((error) => console.error(error));
        });    
     </script>

     <script>
        const profileMediaPreviewOverlay = document.getElementById('profileMediaPreviewOverlay');
        const profileMediaPreviewClose = document.getElementById('profileMediaPreviewClose');
        const profileMediaPreviewImage = document.getElementById('profileMediaPreviewImage');
        const profileMediaPreviewVideo = document.getElementById('profileMediaPreviewVideo');
        const profileMediaPreviewDetails = document.getElementById('profileMediaPreviewDetails');
        const profileMediaPreviewTitle = document.getElementById('profileMediaPreviewTitle');
        const profileMediaPreviewPrice = document.getElementById('profileMediaPreviewPrice');
        const profileMediaPreviewDescription = document.getElementById('profileMediaPreviewDescription');
        const countedProfilePreviewViews = new Set();
        const countedProfileVideoViews = new Set();
        const pendingProfileVideoViewTimers = new Map();

        function updateListingViewLabels(listingId, label) {
            if (!Number.isInteger(listingId) || listingId <= 0 || !label) return;
            document.querySelectorAll(`[data-listing-view-label="${listingId}"]`).forEach((labelEl) => {
                labelEl.textContent = label;
            });
        }

        async function incrementPreviewImageView(sourceEl) {
            const type = String(sourceEl?.dataset.previewType || '').trim();
            const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || '', 10);
            if (type !== 'image' || !Number.isInteger(listingId) || listingId <= 0 || countedProfilePreviewViews.has(listingId)) {
                return;
            }

            countedProfilePreviewViews.add(listingId);

            try {
                const response = await fetch(JOMU_PHP_ROOT + 'increment_listing_view.php?listing_id=' + encodeURIComponent(String(listingId)));
                if (!response.ok) return;
                const data = await response.json();
                if (data?.success && typeof data.label === 'string') {
                    updateListingViewLabels(listingId, data.label);
                }
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        async function incrementVideoPlaybackView(listingId) {
            if (!Number.isInteger(listingId) || listingId <= 0 || countedProfileVideoViews.has(listingId)) {
                return;
            }

            countedProfileVideoViews.add(listingId);

            try {
                const response = await fetch(JOMU_PHP_ROOT + 'increment_listing_view.php?listing_id=' + encodeURIComponent(String(listingId)));
                if (!response.ok) return;
                const data = await response.json();
                if (data?.success && typeof data.label === 'string') {
                    updateListingViewLabels(listingId, data.label);
                }
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        function clearPendingVideoView(videoEl) {
            const timerId = pendingProfileVideoViewTimers.get(videoEl);
            if (timerId) {
                clearTimeout(timerId);
                pendingProfileVideoViewTimers.delete(videoEl);
            }
        }

        function scheduleVideoViewIncrement(videoEl) {
            const listingId = Number.parseInt(videoEl?.dataset.previewListingId || '', 10);
            if (!Number.isInteger(listingId) || listingId <= 0 || countedProfileVideoViews.has(listingId) || pendingProfileVideoViewTimers.has(videoEl)) {
                return;
            }

            const timerId = setTimeout(() => {
                pendingProfileVideoViewTimers.delete(videoEl);
                if (countedProfileVideoViews.has(listingId) || videoEl.paused || videoEl.ended) {
                    return;
                }
                incrementVideoPlaybackView(listingId);
            }, 2000);

            pendingProfileVideoViewTimers.set(videoEl, timerId);
        }

        function registerVideoViewTracking(videoEl) {
            if (!(videoEl instanceof HTMLVideoElement) || videoEl.dataset.viewTrackingBound === '1') {
                return;
            }

            videoEl.dataset.viewTrackingBound = '1';
            videoEl.addEventListener('play', () => scheduleVideoViewIncrement(videoEl));
            videoEl.addEventListener('pause', () => clearPendingVideoView(videoEl));
            videoEl.addEventListener('ended', () => clearPendingVideoView(videoEl));
            videoEl.addEventListener('emptied', () => clearPendingVideoView(videoEl));
        }

        function updateProfilePreviewDetails(sourceEl) {
            if (!profileMediaPreviewDetails) return;
            const title = String(sourceEl?.dataset.previewTitle || '').trim();
            const price = String(sourceEl?.dataset.previewPrice || '').trim();
            const description = String(sourceEl?.dataset.previewDescription || '');
            const hasDetails = title !== '' || price !== '' || description !== '';

            profileMediaPreviewTitle.textContent = title;
            profileMediaPreviewTitle.style.display = title ? 'block' : 'none';
            profileMediaPreviewPrice.textContent = price ? `Price: ${price}` : '';
            profileMediaPreviewPrice.style.display = price ? 'block' : 'none';
            profileMediaPreviewDescription.textContent = description;
            profileMediaPreviewDescription.style.whiteSpace = 'pre-wrap';
            profileMediaPreviewDescription.style.wordBreak = 'break-word';
            profileMediaPreviewDescription.style.display = description ? 'block' : 'none';
            profileMediaPreviewDetails.style.display = hasDetails ? 'block' : 'none';
        }

        function closeProfileMediaPreview() {
            profileMediaPreviewOverlay.classList.remove('active');
            profileMediaPreviewOverlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('profile-preview-open');
            profileMediaPreviewVideo.pause();
            profileMediaPreviewVideo.removeAttribute('src');
            delete profileMediaPreviewVideo.dataset.previewListingId;
            profileMediaPreviewImage.removeAttribute('src');
            profileMediaPreviewImage.style.display = 'none';
            profileMediaPreviewVideo.style.display = 'none';
            if (profileMediaPreviewDetails) {
                profileMediaPreviewDetails.style.display = 'none';
            }
        }

        function openProfileMediaPreview(type, src, sourceEl) {
            if (!src) return;
            profileMediaPreviewImage.style.display = 'none';
            profileMediaPreviewVideo.style.display = 'none';
            updateProfilePreviewDetails(sourceEl);
            incrementPreviewImageView(sourceEl);

            if (type === 'video') {
                profileMediaPreviewVideo.src = src;
                profileMediaPreviewVideo.dataset.previewListingId = sourceEl?.dataset.previewListingId || '';
                profileMediaPreviewVideo.style.display = 'block';
                profileMediaPreviewVideo.play().catch(() => {});
            } else {
                profileMediaPreviewImage.src = src;
                profileMediaPreviewImage.style.display = 'block';
            }

            profileMediaPreviewOverlay.classList.add('active');
            profileMediaPreviewOverlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('profile-preview-open');
            profileMediaPreviewOverlay.scrollTop = 0;
        }

        function openProfilePreviewFromSource(sourceEl) {
            if (!sourceEl) return;
            const type = sourceEl.dataset.previewType || (sourceEl.tagName.toLowerCase() === 'video' ? 'video' : 'image');
            const src = sourceEl.dataset.previewSrc || sourceEl.getAttribute('src') || '';
            openProfileMediaPreview(type, src, sourceEl);
        }

        const profileMainEl = document.querySelector('main');
        let lastProfileTapTime = 0;
        let lastProfileTapSrc = '';

        profileMainEl?.addEventListener('click', (event) => {
            const sourceEl = event.target.closest('.media-preview-source') || event.target.closest('.video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link')?.closest('.card')?.querySelector('.media-preview-source');
            openProfilePreviewFromSource(sourceEl);
        });

        profileMainEl?.addEventListener('touchend', (event) => {
            const sourceEl = event.target.closest('.media-preview-source') || event.target.closest('.video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link')?.closest('.card')?.querySelector('.media-preview-source');
            if (!sourceEl) return;
            const sourceKey = sourceEl.dataset.previewSrc || sourceEl.getAttribute('src') || '';
            const now = Date.now();
            const isDoubleTap = now - lastProfileTapTime < 350 && sourceKey !== '' && sourceKey === lastProfileTapSrc;

            lastProfileTapTime = now;
            lastProfileTapSrc = sourceKey;

            if (!isDoubleTap) return;
            event.preventDefault();
            openProfilePreviewFromSource(sourceEl);
            lastProfileTapTime = 0;
            lastProfileTapSrc = '';
        }, { passive: false });

        document.addEventListener('touchstart', () => {
            if (Date.now() - lastProfileTapTime > 600) {
                lastProfileTapTime = 0;
                lastProfileTapSrc = '';
            }
        });

        document.querySelectorAll('video[data-preview-listing-id]').forEach((videoEl) => {
            registerVideoViewTracking(videoEl);
        });
        registerVideoViewTracking(profileMediaPreviewVideo);

        profileMediaPreviewClose?.addEventListener('click', closeProfileMediaPreview);
        profileMediaPreviewOverlay?.addEventListener('click', (event) => {
            if (event.target === profileMediaPreviewOverlay) {
                closeProfileMediaPreview();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && profileMediaPreviewOverlay.classList.contains('active')) {
                closeProfileMediaPreview();
            }
        });

        const profileDeleteListingModalEl = document.getElementById('profileDeleteListingModal');
        const profileProceedDeleteBtn = document.getElementById('profile-proceed-delete-btn');
        const profileDeclineDeleteBtn = document.getElementById('profile-decline-delete-btn');
        let profileDeleteListingModal = null;
        let profilePendingDeleteListingId = null;
        let profilePendingDeleteCard = null;

        function ensureProfileDeleteModal() {
            if (profileDeleteListingModal) return profileDeleteListingModal;
            if (profileDeleteListingModalEl && window.bootstrap && window.bootstrap.Modal) {
                profileDeleteListingModal = new window.bootstrap.Modal(profileDeleteListingModalEl);
            }
            return profileDeleteListingModal;
        }

        function closeProfileListingDropdowns(exceptDropdown = null) {
            document.querySelectorAll('.profile-showroom-grid .card-options.dropdown.is-open').forEach((dropdown) => {
                if (dropdown === exceptDropdown) {
                    return;
                }

                dropdown.classList.remove('is-open');
                dropdown.querySelector('.manage-listing-options-trigger')?.setAttribute('aria-expanded', 'false');
            });
        }

        document.querySelectorAll('.profile-showroom-grid .manage-listing-options-trigger').forEach((trigger) => {
            const toggleDropdown = (event) => {
                event.preventDefault();
                event.stopPropagation();

                const dropdown = trigger.closest('.card-options.dropdown');
                if (!dropdown) {
                    return;
                }

                const willOpen = !dropdown.classList.contains('is-open');
                closeProfileListingDropdowns(dropdown);
                dropdown.classList.toggle('is-open', willOpen);
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            };

            trigger.addEventListener('click', toggleDropdown);
            trigger.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                toggleDropdown(event);
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('.profile-showroom-grid .card-options.dropdown')) {
                return;
            }
            closeProfileListingDropdowns();
        });

        let profileToastTimer = null;

        function showProfileToast(message) {
            const toastMessage = String(message || '').trim();
            if (toastMessage === '') {
                return;
            }

            let toastEl = document.getElementById('profile-toast-message');
            if (!toastEl) {
                toastEl = document.createElement('div');
                toastEl.id = 'profile-toast-message';
                toastEl.className = 'profile-toast-message';
                toastEl.setAttribute('role', 'status');
                toastEl.setAttribute('aria-live', 'polite');
                document.body.appendChild(toastEl);
            }

            toastEl.textContent = toastMessage;
            toastEl.classList.add('is-visible');
            window.clearTimeout(profileToastTimer);
            profileToastTimer = window.setTimeout(() => {
                toastEl.classList.remove('is-visible');
            }, 2600);
        }

        document.querySelectorAll('.manage-listing-share').forEach((shareLink) => {
            shareLink.addEventListener('click', async (event) => {
                event.preventDefault();
                const relativeUrl = shareLink.dataset.shareUrl || '';
                if (!relativeUrl) return;
                const absoluteUrl = new URL(relativeUrl, window.location.origin).toString();

                if (navigator.share) {
                    try {
                        await navigator.share({ url: absoluteUrl });
                        return;
                    } catch (error) {
                        // Fall back to clipboard if share is canceled/unavailable.
                    }
                }

                try {
                    await navigator.clipboard.writeText(absoluteUrl);
                    showProfileToast('Listing link copied.');
                } catch (error) {
                    showProfileToast(`Copy this listing link: ${absoluteUrl}`);
                }
            });
        });

        document.querySelectorAll('.manage-listing-pin').forEach((pinLink) => {
            pinLink.addEventListener('click', async (event) => {
                event.preventDefault();

                const listingId = parseInt(pinLink.dataset.listingId || '', 10);
                const pinAction = pinLink.dataset.pinAction === 'unpin' ? 'unpin' : 'pin';
                if (!Number.isInteger(listingId) || listingId <= 0) {
                    return;
                }

                const body = new URLSearchParams();
                body.set('profile_pin_action', pinAction);
                body.set('listing_id', String(listingId));
                body.set('csrf_token', jomuUserCsrfToken);

                try {
                    pinLink.style.pointerEvents = 'none';
                    const response = await fetch(JOMU_PHP_ROOT + 'profile.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: body.toString()
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        showProfileToast(data.message || 'Unable to update pin.');
                        return;
                    }

                    window.location.reload();
                } catch (error) {
                    showProfileToast('Network error while updating pin.');
                } finally {
                    pinLink.style.pointerEvents = '';
                }
            });
        });

        document.querySelectorAll('.manage-listing-delete').forEach((deleteLink) => {
            deleteLink.addEventListener('click', (event) => {
                event.preventDefault();
                profilePendingDeleteListingId = parseInt(deleteLink.dataset.listingId || '', 10);
                profilePendingDeleteCard = deleteLink.closest('.col-4');
                if (!Number.isInteger(profilePendingDeleteListingId) || profilePendingDeleteListingId <= 0) {
                    profilePendingDeleteListingId = null;
                    profilePendingDeleteCard = null;
                    return;
                }
                const modal = ensureProfileDeleteModal();
                if (modal) {
                    modal.show();
                    return;
                }

                showProfileToast('Please use the delete listing popup to confirm this action.');
            });
        });

        profileDeclineDeleteBtn?.addEventListener('click', () => {
            profilePendingDeleteListingId = null;
            profilePendingDeleteCard = null;
            const modal = ensureProfileDeleteModal();
            if (modal) modal.hide();
        });

        profileProceedDeleteBtn?.addEventListener('click', async () => {
            if (!Number.isInteger(profilePendingDeleteListingId) || profilePendingDeleteListingId <= 0) {
                const modal = ensureProfileDeleteModal();
                if (modal) modal.hide();
                return;
            }

            try {
                const body = new URLSearchParams();
                body.set('listing_id', String(profilePendingDeleteListingId));
                body.set('csrf_token', jomuUserCsrfToken);
                const response = await fetch(JOMU_PHP_ROOT + 'delete_listing.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    showProfileToast(data.message || 'Unable to delete listing.');
                    return;
                }

                if (profilePendingDeleteCard) {
                    profilePendingDeleteCard.remove();
                } else {
                    window.location.reload();
                }
            } catch (error) {
                showProfileToast('Network error while deleting listing.');
            } finally {
                profilePendingDeleteListingId = null;
                profilePendingDeleteCard = null;
                const modal = ensureProfileDeleteModal();
                if (modal) modal.hide();
            }
        });
     </script>

     <script>
        const shareBusinessProfileBtn = document.getElementById('share-business-profile-btn');
        shareBusinessProfileBtn?.addEventListener('click', async () => {
            const relativeUrl = shareBusinessProfileBtn.dataset.shareUrl || '';
            if (!relativeUrl) return;
            const absoluteUrl = new URL(relativeUrl, window.location.origin).toString();

            if (navigator.share) {
                try {
                    await navigator.share({ url: absoluteUrl });
                    return;
                } catch (error) {
                    // Fall back to clipboard prompt.
                }
            }

            try {
                await navigator.clipboard.writeText(absoluteUrl);
                showProfileToast('Business profile link copied.');
            } catch (error) {
                showProfileToast(`Copy this business profile link: ${absoluteUrl}`);
            }
        });
     </script>

     <script>
        const editBusinessProfileBtn = document.getElementById('edit-business-profile-btn');
        const saveBusinessProfileBtn = document.getElementById('save-business-profile-btn');
        const cancelBusinessProfileBtn = document.getElementById('cancel-business-profile-btn');
        const businessNameInput = document.getElementById('business-name-input');
        const businessBioInput = document.getElementById('business-bio-input');
        const businessNameRule = document.getElementById('business-name-rule');
        const businessProfileStatus = document.getElementById('business-profile-status');
        const navBusinessName = document.getElementById('nav-business-name');
        const navBusinessNameMobile = document.getElementById('nav-business-name-mobile');
        const businessContactBtn = document.getElementById('business-contact-btn');
        const businessContactEditorWrap = document.getElementById('business-contact-editor-wrap');
        const businessContactInput = document.getElementById('business-contact-input');
        const businessEmailBtn = document.getElementById('business-email-btn');
        const businessEmailEditorWrap = document.getElementById('business-email-editor-wrap');
        const businessEmailInput = document.getElementById('business-email-input');
        const BUSINESS_BIO_MAX_LENGTH = 200;
        const BUSINESS_CONTACT_MAX_LENGTH = 60;
        const BUSINESS_EMAIL_MAX_LENGTH = 120;

        let canEditName = businessNameRule.dataset.canEditName === '1';
        let nextAllowed = businessNameRule.dataset.nextAllowed;
        let originalBusinessName = businessNameInput.value.trim();
        let originalBusinessBio = businessBioInput.textContent.trim();
        let originalBusinessContact = (businessContactInput?.value || '').trim();
        let originalBusinessEmail = (businessEmailInput?.value || '').trim();
        let isEditingBusinessProfile = false;

        const BUSINESS_STATUS_DEFAULT_COLOR = '#b00020';
        const BUSINESS_STATUS_TIP_COLOR = 'var(--bs-orange, #fd7e14)';

        function resizeBusinessNameInput() {
            if (!businessNameInput || businessNameInput.tagName !== 'TEXTAREA') {
                return;
            }

            businessNameInput.style.height = 'auto';
            businessNameInput.style.height = `${businessNameInput.scrollHeight}px`;
        }

        function setStatus(message, tone = 'default') {
            if (!businessProfileStatus) {
                return;
            }

            businessProfileStatus.textContent = message || '';
            businessProfileStatus.style.color = tone === 'tip'
                ? BUSINESS_STATUS_TIP_COLOR
                : BUSINESS_STATUS_DEFAULT_COLOR;
        }

        function setBusinessContactWhatsAppHint() {
            if (!isEditingBusinessProfile || !businessContactInput) {
                return;
            }

            const currentStatus = String(businessProfileStatus.textContent || '');
            const contactValue = normalizeBusinessContact(businessContactInput.value || '');
            if (contactValue === '') {
                if (currentStatus.startsWith('Tip:')) {
                    setStatus('', 'default');
                }
                return;
            }

            if (currentStatus === '' || currentStatus.startsWith('Tip:')) {
                setStatus('Tip: Enter a phone number that is also active on WhatsApp.', 'tip');
            }
        }

        function normalizeBusinessContact(value) {
            return String(value ?? '').trim();
        }

        function normalizeBusinessEmail(value) {
            return String(value ?? '').trim();
        }

        function getBusinessContactLabel(contactValue) {
            return contactValue !== '' ? contactValue : 'Contact';
        }

        function getBusinessEmailLabel(emailValue) {
            return emailValue !== '' ? emailValue : 'Email';
        }

        function hideBusinessContactEditor() {
            businessContactEditorWrap?.classList.add('d-none');
            businessContactBtn?.classList.remove('d-none');
        }

        function hideBusinessEmailEditor() {
            businessEmailEditorWrap?.classList.add('d-none');
            businessEmailBtn?.classList.remove('d-none');
        }

        function setBusinessProfileEditMode(enabled) {
            isEditingBusinessProfile = enabled;
            businessBioInput.contentEditable = enabled ? 'true' : 'false';
            businessNameInput.disabled = !enabled;
            businessNameInput.readOnly = !canEditName;
            businessNameRule.style.display = 'none';
            resizeBusinessNameInput();

            editBusinessProfileBtn.classList.toggle('d-none', enabled);
            saveBusinessProfileBtn.classList.toggle('d-none', !enabled);
            cancelBusinessProfileBtn.classList.toggle('d-none', !enabled);

            if (!enabled) {
                hideBusinessContactEditor();
                hideBusinessEmailEditor();
                if (businessContactBtn) {
                    businessContactBtn.textContent = getBusinessContactLabel(originalBusinessContact);
                }
                if (businessEmailBtn) {
                    businessEmailBtn.textContent = getBusinessEmailLabel(originalBusinessEmail);
                }
                setStatus('');
                return;
            }

            if (businessContactBtn) {
                businessContactBtn.textContent = getBusinessContactLabel(normalizeBusinessContact(businessContactInput?.value || ''));
            }
            if (businessEmailBtn) {
                businessEmailBtn.textContent = getBusinessEmailLabel(normalizeBusinessEmail(businessEmailInput?.value || ''));
            }

            if (canEditName) {
                businessNameRule.textContent = 'You can change your business name once every 3 months.';
            } else {
                businessNameRule.textContent = `Business name can't be edited now. You can change it after ${nextAllowed}.`;
            }
        }

        function enforceBusinessBioLimit() {
            if (!businessBioInput) return;
            const rawBio = businessBioInput.textContent || '';
            if (rawBio.length <= BUSINESS_BIO_MAX_LENGTH) {
                return;
            }

            businessBioInput.textContent = rawBio.slice(0, BUSINESS_BIO_MAX_LENGTH);
            if (isEditingBusinessProfile) {
                setStatus(`Business bio must be ${BUSINESS_BIO_MAX_LENGTH} characters or less.`);
            }
        }

        editBusinessProfileBtn.addEventListener('click', () => {
            setBusinessProfileEditMode(true);
            if (canEditName) {
                businessNameInput.focus();
            } else {
                businessBioInput.focus();
            }
        });

        cancelBusinessProfileBtn.addEventListener('click', () => {
            businessNameInput.value = originalBusinessName;
            resizeBusinessNameInput();
            businessBioInput.textContent = originalBusinessBio;
            if (businessContactInput) {
                businessContactInput.value = originalBusinessContact;
            }
            if (businessEmailInput) {
                businessEmailInput.value = originalBusinessEmail;
            }
            hideBusinessContactEditor();
            hideBusinessEmailEditor();
            setBusinessProfileEditMode(false);
        });

        businessNameInput.addEventListener('click', () => {
            if (isEditingBusinessProfile && !canEditName) {
                businessNameRule.style.display = 'block';
            }
        });

        businessNameInput.addEventListener('focus', () => {
            if (isEditingBusinessProfile && !canEditName) {
                businessNameRule.style.display = 'block';
            }
        });

        businessNameInput.addEventListener('input', () => {
            resizeBusinessNameInput();

            if (!isEditingBusinessProfile) {
                return;
            }

            if (canEditName) {
                businessNameRule.textContent = 'You can change your business name once every 3 months.';
                businessNameRule.style.display = businessNameInput.value.trim() !== '' ? 'block' : 'none';
                setStatus('');
                return;
            }

            businessNameRule.textContent = `Business name cannot be edited now. You can change it after ${nextAllowed}.`;
            businessNameRule.style.display = 'block';
        });

        businessBioInput.addEventListener('input', enforceBusinessBioLimit);
        businessContactBtn?.addEventListener('click', () => {
            if (!isEditingBusinessProfile) {
                return;
            }

            businessContactBtn.classList.add('d-none');
            businessContactEditorWrap?.classList.remove('d-none');
            businessContactInput?.focus();
            setBusinessContactWhatsAppHint();
        });

        businessContactInput?.addEventListener('focus', () => {
            setBusinessContactWhatsAppHint();
        });

        businessContactInput?.addEventListener('input', () => {
            setBusinessContactWhatsAppHint();
        });

        businessEmailBtn?.addEventListener('click', () => {
            if (!isEditingBusinessProfile) {
                return;
            }

            businessEmailBtn.classList.add('d-none');
            businessEmailEditorWrap?.classList.remove('d-none');
            businessEmailInput?.focus();
        });

        saveBusinessProfileBtn.addEventListener('click', async () => {
            const trimmedName = businessNameInput.value.trim().replace(/\s+/g, ' ');
            const trimmedBio = businessBioInput.textContent.trim();
            const trimmedContact = normalizeBusinessContact(businessContactInput?.value || '');
            const trimmedEmail = normalizeBusinessEmail(businessEmailInput?.value || '');
            const shouldUpdateName = canEditName && trimmedName !== originalBusinessName;

            if (shouldUpdateName && (trimmedName.length < 3 || trimmedName.length > 40)) {
                setStatus('Business name must be between 3 and 40 characters.');
                return;
            }

            if (trimmedBio.length > BUSINESS_BIO_MAX_LENGTH) {
                setStatus(`Bio must be ${BUSINESS_BIO_MAX_LENGTH} characters or fewer.`);
                return;
            }

            if (trimmedContact.length > BUSINESS_CONTACT_MAX_LENGTH) {
                setStatus(`Business contact must be ${BUSINESS_CONTACT_MAX_LENGTH} characters or fewer.`);
                return;
            }

            if (trimmedContact !== '' && !/^\+?[0-9]{6,20}$/.test(trimmedContact)) {
                setStatus('Please enter one valid phone number with digits and optional + country code.');
                return;
            }

            if (trimmedEmail.length > BUSINESS_EMAIL_MAX_LENGTH) {
                setStatus(`Business email must be ${BUSINESS_EMAIL_MAX_LENGTH} characters or fewer.`);
                return;
            }

            if (trimmedEmail !== '' && /[,;\s]/.test(trimmedEmail)) {
                setStatus('Please enter one email address only.');
                return;
            }

            if (trimmedEmail !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmedEmail)) {
                setStatus('Please enter a valid business email address.');
                return;
            }

            const payload = new URLSearchParams();
            payload.append('businessname', trimmedName);
            payload.append('bio', trimmedBio);
            payload.append('business_contact', trimmedContact);
            payload.append('business_email', trimmedEmail);
            payload.append('update_name', shouldUpdateName ? '1' : '0');
            payload.append('csrf_token', jomuUserCsrfToken);

            try {
                saveBusinessProfileBtn.disabled = true;
                const response = await fetch(JOMU_PHP_ROOT + 'update_business_profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: payload.toString()
                });

                const data = await response.json();
                if (!response.ok || !data.success) {
                    setStatus(data.message || 'Unable to update profile right now.');
                    return;
                }

                originalBusinessName = data.businessname;
                originalBusinessBio = data.bio;
                originalBusinessContact = data.business_contact || '';
                originalBusinessEmail = data.business_email || '';
                businessNameInput.value = data.businessname;
                resizeBusinessNameInput();
                businessBioInput.textContent = data.bio;
                if (businessContactInput) {
                    businessContactInput.value = originalBusinessContact;
                }
                if (businessContactBtn) {
                    businessContactBtn.textContent = getBusinessContactLabel(originalBusinessContact);
                }
                if (businessEmailInput) {
                    businessEmailInput.value = originalBusinessEmail;
                }
                if (businessEmailBtn) {
                    businessEmailBtn.textContent = getBusinessEmailLabel(originalBusinessEmail);
                }
                navBusinessName.textContent = data.businessname;
                if (navBusinessNameMobile) {
                    navBusinessNameMobile.textContent = data.businessname;
                }
                canEditName = !!data.can_edit_name;
                nextAllowed = data.next_allowed || '';
                businessNameRule.dataset.canEditName = canEditName ? '1' : '0';
                businessNameRule.dataset.nextAllowed = nextAllowed;
                setStatus('Profile updated.');
                setBusinessProfileEditMode(false);
            } catch (error) {
                setStatus('Network error while updating profile.');
            } finally {
                saveBusinessProfileBtn.disabled = false;
            }
        });

        businessNameInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });

        window.addEventListener('resize', resizeBusinessNameInput);
        resizeBusinessNameInput();
     </script>


    <script src="/assets/listing-preview-modal.js"></script>
    <script src="/assets/listing-preview-gallery.js"></script>
    <script src="/assets/bootstrap.bundle.min.js"></script>
    <script src="/assets/cookie-consent.js"></script>
</body>

</html>
