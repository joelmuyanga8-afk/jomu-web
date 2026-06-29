<?php
session_start();
require "connection/dbconn.php";
require "partials/helpers.php";
require "partials/admin_helpers.php";

jomu_ensure_admin_schema($conn);

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

function usersTableHasColumn(mysqli $conn, string $columnName): bool {
    $safeColumn = $conn->real_escape_string($columnName);
    $check = $conn->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return $check && $check->num_rows > 0;
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$listingId = isset($_GET['listing_id']) ? (int) $_GET['listing_id'] : 0;
$requestType = trim((string) ($_GET['req_type'] ?? ''));
$requestAmount = trim((string) ($_GET['req_amount'] ?? ''));
$requestPaymentMode = trim((string) ($_GET['req_payment_mode'] ?? ''));
$requestDeliveryMethod = trim((string) ($_GET['req_delivery_method'] ?? ''));
$requestLocation = trim((string) ($_GET['req_location'] ?? ''));
$hasSubmittedRequestDetails = $requestAmount !== '' || $requestPaymentMode !== '' || $requestDeliveryMethod !== '' || $requestLocation !== '';
$requestTypeLabel = $requestType === 'service' ? 'Schedule' : 'Purchase';
$deliveryLabel = $requestType === 'service' ? 'Timeline' : 'Delivery Method';

if ($userId <= 0 && $listingId > 0) {
    $ownerStmt = $conn->prepare("SELECT user_id FROM listings WHERE listing_id = ? AND admin_purged_at IS NULL LIMIT 1");
    $ownerStmt->bind_param("i", $listingId);
    $ownerStmt->execute();
    $ownerRow = $ownerStmt->get_result()->fetch_assoc();
    $ownerStmt->close();

    if ($ownerRow && !empty($ownerRow['user_id'])) {
        $userId = (int) $ownerRow['user_id'];
    }
}

if ($userId <= 0) {
    http_response_code(400);
    die("Invalid seller. This listing is not linked to a business account.");
}

$selectColumns = ['id', 'businessname', 'emailormobilenumber', 'profilepic'];
if (usersTableHasColumn($conn, 'account_status')) {
    $selectColumns[] = 'account_status';
}
if (usersTableHasColumn($conn, 'inactive_until')) {
    $selectColumns[] = 'inactive_until';
}
if (usersTableHasColumn($conn, 'status_reason')) {
    $selectColumns[] = 'status_reason';
}
if (usersTableHasColumn($conn, 'bio')) {
    $selectColumns[] = 'bio';
}
if (usersTableHasColumn($conn, 'business_contact')) {
    $selectColumns[] = 'business_contact';
}
if (usersTableHasColumn($conn, 'business_email')) {
    $selectColumns[] = 'business_email';
}
$userSelect = "SELECT " . implode(', ', $selectColumns) . " FROM users WHERE id = ? LIMIT 1";

$userStmt = $conn->prepare($userSelect);
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    http_response_code(404);
    die("Seller not found.");
}

$profileImage = jomu_resolve_public_profile_image_path((string) ($user['profilepic'] ?? ''));
$businessName = trim((string) ($user['businessname'] ?? 'Business'));
$businessBio = trim((string) ($user['bio'] ?? ''));
if ($businessBio === '') {
    $businessBio = 'No business bio shared yet.';
}
$businessContact = trim((string) ($user['business_contact'] ?? ''));
$businessContactDial = preg_replace('/[^0-9+]/', '', $businessContact);
$hasCallableBusinessContact = $businessContactDial !== '';
$businessEmail = trim((string) ($user['business_email'] ?? ''));
$hasBusinessEmail = $businessEmail !== '' && (bool) filter_var($businessEmail, FILTER_VALIDATE_EMAIL);
$gmailComposeUrl = $hasBusinessEmail
    ? ('https://mail.google.com/mail/?view=cm&fs=1&to=' . rawurlencode($businessEmail))
    : '';
$viewerUserId = 0;
if (!empty($_SESSION['emailormobilenumber'])) {
    $viewerStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if ($viewerStmt) {
        $viewerStmt->bind_param("s", $_SESSION['emailormobilenumber']);
        $viewerStmt->execute();
        $viewerRow = $viewerStmt->get_result()->fetch_assoc();
        $viewerStmt->close();
        $viewerUserId = (int) ($viewerRow['id'] ?? 0);
    }
}
$jomuDashboardInboxUrl = jomu_page_url('dashboard') . '?section=inbox&partner_id=' . (int) $userId;
$isOwnProfile = $viewerUserId > 0 && $viewerUserId === $userId;
$visitorAdmin = jomu_current_admin($conn);

$featuredListing = null;
$listings = [];
$visitorPinnedListings = [];

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

$visitorPinnedStmt = $conn->prepare("SELECT listing_id, pinned_at FROM profile_pinned_listings WHERE user_id = ?");
if ($visitorPinnedStmt) {
    $visitorPinnedStmt->bind_param('i', $userId);
    $visitorPinnedStmt->execute();
    $visitorPinnedResult = $visitorPinnedStmt->get_result();
    while ($pinRow = $visitorPinnedResult->fetch_assoc()) {
        $visitorPinnedListings[(int) ($pinRow['listing_id'] ?? 0)] = (string) ($pinRow['pinned_at'] ?? '');
    }
    $visitorPinnedStmt->close();
}

if ($listingId > 0) {
    $featuredHiddenClause = $isOwnProfile
        ? 'admin_purged_at IS NULL'
        : "admin_purged_at IS NULL AND COALESCE(moderation_status, 'visible') <> 'hidden'";
    $featuredStmt = $conn->prepare("SELECT * FROM listings WHERE listing_id = ? AND user_id = ? AND {$featuredHiddenClause} LIMIT 1");
    $featuredStmt->bind_param("ii", $listingId, $userId);
    $featuredStmt->execute();
    $featuredRes = $featuredStmt->get_result();
    if ($featuredRes && $featuredRes->num_rows > 0) {
        $featuredListing = $featuredRes->fetch_assoc();
    }
    $featuredStmt->close();
}

$listingsHiddenClause = $isOwnProfile
    ? 'admin_purged_at IS NULL'
    : "admin_purged_at IS NULL AND COALESCE(moderation_status, 'visible') <> 'hidden'";
$listingsStmt = $conn->prepare("SELECT * FROM listings WHERE user_id = ? AND {$listingsHiddenClause} ORDER BY listing_id DESC");
$listingsStmt->bind_param("i", $userId);
$listingsStmt->execute();
$listingsRes = $listingsStmt->get_result();
while ($row = $listingsRes->fetch_assoc()) {
    if ($featuredListing && (int) $row['listing_id'] === (int) $featuredListing['listing_id']) {
        continue;
    }
    $listings[] = $row;
}
$listingsStmt->close();

if ($featuredListing) {
    $featuredListingId = (int) ($featuredListing['listing_id'] ?? 0);
    $featuredListing['_profile_is_pinned'] = isset($visitorPinnedListings[$featuredListingId]);
    $featuredListing['_profile_pinned_at'] = $visitorPinnedListings[$featuredListingId] ?? '';
}

foreach ($listings as &$visitorListing) {
    $visitorListingId = (int) ($visitorListing['listing_id'] ?? 0);
    $visitorListing['_profile_is_pinned'] = isset($visitorPinnedListings[$visitorListingId]);
    $visitorListing['_profile_pinned_at'] = $visitorPinnedListings[$visitorListingId] ?? '';
}
unset($visitorListing);

usort($listings, function (array $a, array $b): int {
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

if ($featuredListing) {
    $listings[] = $featuredListing;
    usort($listings, function (array $a, array $b): int {
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Showroom</title>
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

        .visitor-business-summary {
            --visitor-summary-gap: 0.45rem;
            margin-bottom: 0.65rem !important;
        }

        .visitor-business-summary #business-name-input {
            margin: 0;
            line-height: 1.12;
        }

        .visitor-business-summary #business-bio-input {
            margin: var(--visitor-summary-gap) auto 0;
        }

        .profile-page .footer-links {
            gap: 4px 8px;
        }

        #visitorMediaPreviewOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
            overflow-y: auto;
        }

        #visitorMediaPreviewOverlay.active {
            display: flex;
        }

        .visitor-media-preview-content {
            max-width: 96vw;
            max-height: 70vh;
            width: auto;
            height: auto;
            object-fit: contain;
            background: #000;
            z-index: 1;
        }

        .visitor-media-preview-panel {
            max-width: 96vw;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            z-index: 1;
            margin: 12px auto;
        }

        .visitor-media-preview-details {
            width: min(96vw, 620px);
            background: rgba(9, 9, 9, 0.82);
            color: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: left;
            z-index: 3;
        }

        .visitor-media-preview-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.3;
            color: rgb(241, 90, 36);
        }

        .visitor-media-preview-price,
        .visitor-media-preview-description {
            margin: 4px 0 0;
            font-size: 0.9rem;
            line-height: 1.35;
        }

        .visitor-preview-purchase-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 0;
            background: rgb(241, 90, 36);
            color: #fff;
            text-decoration: none;
            font-size: 18px;
            font-weight: 700;
            margin-top: 0;
            z-index: 3;
        }

        .visitor-preview-purchase-icon:hover {
            background: rgb(215, 74, 22);
            color: #fff;
        }

        .visitor-media-preview-close {
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
            z-index: 3;
        }

        .visitor-media-preview-watermark {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 140px;
            max-width: 28vw;
            opacity: 0.28;
            pointer-events: none;
            user-select: none;
            z-index: 2;
        }

        .showroom-img .video-wrapper {
            padding-top: 0;
            height: 400px;
        }

        .visitor-contact-error {
            display: none;
            margin: 0 auto 10px;
            max-width: 320px;
            padding: 10px 14px;
            border-radius: 8px;
            background: rgba(220, 53, 69, 0.12);
            color: #c0392b;
            font-weight: 700;
            text-align: center;
        }

        @media (max-width: 767.98px) {
            body.profile-page {
                justify-content: flex-start;
            }

            body.profile-page > main {
                margin: 0;
            }

            .profile-page .footer-feedback br {
                display: none;
            }

            .profile-page .footer-feedback small {
                display: block;
                margin-top: 0;
            }

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
            font-size: 12px;
            font-weight: 700;
        }

    </style>
</head>
<body class="bg-white profile-page">
    <header>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top navbarone navbar-help bg-dark" id="navbarone">
            <div class="container-fluid profile-nav-inner">
                <a id="visitor-profile-back" class="mobile-profile-back d-flex" href="/purchase-wholesale" aria-label="Go back">&#8592;</a>
                <h2 class="mobile-profile-title d-block d-md-none"><?php echo htmlspecialchars($businessName); ?></h2>
                <h2 id="nav-business-name" class="profile-nav-title-desktop d-none d-md-block d-lg-block"><?php echo htmlspecialchars($businessName); ?></h2>
                <div class="mobile-nav-spacer d-md-none" aria-hidden="true"></div>
            </div>
        </nav>
    </header>

    <main>
        <div class="container text-center mt-2">
            <div class="profile-pic-container position-relative d-inline-block">
                <img src="<?php echo htmlspecialchars($profileImage); ?>" class="img-fluid profile-img" alt="Business Profile">
            </div>
        </div>

        <div class="container text-center mt-2 visitor-business-summary">
            <h2 id="business-name-input"><?php echo htmlspecialchars($businessName); ?></h2>
            <h6 id="business-bio-input"><?php echo nl2br(htmlspecialchars($businessBio)); ?></h6>
        </div>

        <?php if ($visitorAdmin): ?>
            <div style="width:min(720px,calc(100% - 24px));margin:14px auto;padding:12px;border:1px solid #d9dde5;border-radius:8px;background:#fff7ed;display:flex;gap:8px;align-items:center;justify-content:center;flex-wrap:wrap;">
                <form method="post" action="/php/admin/user_action.php" class="d-flex gap-2 flex-wrap justify-content-center visitor-admin-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jomu_admin_csrf_token()); ?>">
                    <input type="hidden" name="user_id" value="<?php echo (int) $userId; ?>">
                    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/php/admin/dashboard.php?page=users'); ?>">
                    <input type="hidden" name="termination_reason" value="">
                    <input type="hidden" name="custom_message" value="">
                    <input type="hidden" name="inactive_days" value="7">
                    <input type="hidden" name="inactive_reason" value="">
                    <select name="template" class="form-select form-select-sm" aria-label="JoMu message template">
                        <option value="warning">Warning message</option>
                        <option value="terms">Terms reminder</option>
                        <option value="support">Contact support</option>
                        <option value="custom">Admin message</option>
                    </select>
                    <button class="btn btn-dark btn-sm" name="action" value="message" type="submit">Send</button>
                    <?php if (strtolower((string) ($user['account_status'] ?? 'active')) === 'inactive'): ?>
                        <button class="btn btn-success btn-sm" name="action" value="activate" type="submit">Activate account</button>
                    <?php else: ?>
                        <button class="btn btn-warning btn-sm" name="action" value="inactive" type="submit">Suspend</button>
                    <?php endif; ?>
                    <button class="btn btn-danger btn-sm" name="action" value="terminate" type="submit">Terminate</button>
                </form>
                <?php if (strtolower((string) ($user['account_status'] ?? 'active')) === 'inactive'): ?>
                    <small style="width:100%;text-align:center;color:#667085;">Suspended until <?php echo htmlspecialchars((string) ($user['inactive_until'] ?? '')); ?>. Reason: <?php echo htmlspecialchars((string) ($user['status_reason'] ?? '')); ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasSubmittedRequestDetails): ?>
            <div class="container mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-2"><?php echo htmlspecialchars($requestTypeLabel); ?> Submitted Details</h5>
                        <?php if ($requestAmount !== ''): ?>
                            <p class="mb-1"><b><?php echo htmlspecialchars($requestType === 'service' ? 'Service Requirement' : 'Amount'); ?>:</b> <?php echo htmlspecialchars($requestAmount); ?></p>
                        <?php endif; ?>
                        <?php if ($requestPaymentMode !== ''): ?>
                            <p class="mb-1"><b>Mode of Payment:</b> <?php echo htmlspecialchars($requestPaymentMode); ?></p>
                        <?php endif; ?>
                        <?php if ($requestDeliveryMethod !== ''): ?>
                            <p class="mb-1"><b><?php echo htmlspecialchars($deliveryLabel); ?>:</b> <?php echo htmlspecialchars($requestDeliveryMethod); ?></p>
                        <?php endif; ?>
                        <?php if ($requestLocation !== ''): ?>
                            <p class="mb-0"><b>Location:</b> <?php echo htmlspecialchars($requestLocation); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="contacts-profile text-center mb-2">
            <div id="visitor-contact-error" class="visitor-contact-error" aria-live="polite"></div>
            <?php if (!$isOwnProfile) { ?>
                <button
                    type="button"
                    class="btn visitor-contact-btn"
                    style="background-color: rgb(206, 207, 207);"
                    data-contact-action="message"
                    data-contact-href="<?php echo htmlspecialchars($jomuDashboardInboxUrl); ?>"
                >Message</button>
            <?php } ?>
            <?php if ($hasCallableBusinessContact): ?>
                <button
                    type="button"
                    id="visitor-call-btn"
                    class="btn visitor-contact-btn"
                    style="background-color: rgb(206, 207, 207);"
                    data-contact-action="call"
                    data-phone-dial="<?php echo htmlspecialchars($businessContactDial); ?>"
                    data-phone-display="<?php echo htmlspecialchars($businessContact); ?>"
                >Call</button>
            <?php else: ?>
                <button class="btn" style="background-color: rgb(206, 207, 207);" disabled>Call</button>
            <?php endif; ?>
            <?php if ($hasBusinessEmail): ?>
                <button
                    type="button"
                    class="btn visitor-contact-btn"
                    style="background-color: rgb(206, 207, 207);"
                    data-contact-action="email"
                    data-contact-href="<?php echo htmlspecialchars($gmailComposeUrl); ?>"
                >Send Email</button>
            <?php else: ?>
                <button class="btn" style="background-color: rgb(206, 207, 207);" disabled>Send Email</button>
            <?php endif; ?>
        </div>

        <div class="text-center">
            <h5 style="color: rgb(241,90,36);">Showroom</h5>
        </div>

        <div class="container my-2 cards-container profile-showroom-grid">
            <div class="row g-1">
                <?php
                    $totalListings = count($listings);
                    if ($totalListings === 0) {
                        echo "<h3>No Listings Found!</h3>";
                    }
                ?>

                <?php foreach ($listings as $listing) { ?>
                    <div class="col-4 col-md-4 col-lg-3">
                        <?php
                            $base = '';
                            $listCardClass = 'profile-showroom-card';
                            $shareSellerBusinessName = $businessName;
                            $shareSellerProfilePic = $profileImage;
                            $ownerHiddenShowroomCard = $isOwnProfile && strtolower((string) ($listing['moderation_status'] ?? 'visible')) === 'hidden';
                            include __DIR__ . '/../components/list_item.php';
                        ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </main>
    <div id="visitorMediaPreviewOverlay" aria-hidden="true">
        <button type="button" class="visitor-media-preview-close" id="visitorMediaPreviewClose" aria-label="Close preview">&times;</button>
        <div class="visitor-media-preview-panel">
            <img id="visitorMediaPreviewImage" class="visitor-media-preview-content" alt="Listing preview" style="display:none;">
            <video id="visitorMediaPreviewVideo" class="visitor-media-preview-content" controls style="display:none;"></video>
            <div id="visitorMediaPreviewDetails" class="visitor-media-preview-details" style="display:none;">
                <p id="visitorMediaPreviewTitle" class="visitor-media-preview-title"></p>
                <p id="visitorMediaPreviewPrice" class="visitor-media-preview-price"></p>
                <p id="visitorMediaPreviewDescription" class="visitor-media-preview-description"></p>
            </div>
            <?php if (!$isOwnProfile): ?>
                <a id="visitorMediaPreviewPurchase" class="visitor-preview-purchase-icon" href="#" title="Open listing" aria-label="Open listing">></a>
            <?php endif; ?>
        </div>
        <img id="visitorMediaPreviewWatermark" class="visitor-media-preview-watermark" src="/assets/images/JoMu logo redesigned.png" alt="JoMu watermark">
    </div>

    <footer class="footer-feedback py-2 text-center bg-white">
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

    <?php if ($visitorAdmin): ?>
    <div class="visitor-admin-dialog" id="visitorAdminDialog" aria-hidden="true">
        <div class="visitor-admin-dialog-panel" role="dialog" aria-modal="true" aria-labelledby="visitorAdminDialogTitle">
            <h3 id="visitorAdminDialogTitle">JoMu Admin</h3>
            <p id="visitorAdminDialogMessage"></p>
            <textarea id="visitorAdminDialogText" class="form-control" rows="4" style="display:none;"></textarea>
            <select id="visitorAdminDialogSelect" class="form-select mt-2" style="display:none;">
                <option value="7">7 days</option>
                <option value="14">14 days</option>
                <option value="21">21 days</option>
                <option value="28">28 days</option>
            </select>
            <div class="visitor-admin-dialog-actions">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="visitorAdminDialogCancel">Cancel</button>
                <button type="button" class="btn btn-dark btn-sm" id="visitorAdminDialogOk">Continue</button>
            </div>
        </div>
    </div>
    <style>
        .visitor-admin-dialog {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.48);
            z-index: 10000;
            padding: 16px;
        }

        .visitor-admin-dialog.active {
            display: flex;
        }

        .visitor-admin-dialog-panel {
            width: min(440px, 100%);
            background: #fff;
            border-radius: 8px;
            padding: 18px;
            box-shadow: 0 18px 54px rgba(0, 0, 0, 0.22);
        }

        .visitor-admin-dialog-panel h3 {
            margin: 0 0 8px;
            font-size: 1.15rem;
            font-weight: 800;
        }

        .visitor-admin-dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 14px;
        }

        .visitor-admin-dialog-actions .btn:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }
    </style>
    <?php endif; ?>

    <script>
        const visitorProfileBack = document.getElementById('visitor-profile-back');
        if (visitorProfileBack) {
            visitorProfileBack.addEventListener('click', (event) => {
                if (window.history.length > 1) {
                    event.preventDefault();
                    window.history.back();
                }
            });
        }
    </script>
    <?php if ($visitorAdmin): ?>
    <script>
        const visitorAdminDialog = document.getElementById('visitorAdminDialog');
        const visitorAdminDialogTitle = document.getElementById('visitorAdminDialogTitle');
        const visitorAdminDialogMessage = document.getElementById('visitorAdminDialogMessage');
        const visitorAdminDialogText = document.getElementById('visitorAdminDialogText');
        const visitorAdminDialogSelect = document.getElementById('visitorAdminDialogSelect');
        const visitorAdminDialogCancel = document.getElementById('visitorAdminDialogCancel');
        const visitorAdminDialogOk = document.getElementById('visitorAdminDialogOk');

        function openVisitorAdminDialog({ title = 'JoMu Admin', message = '', text = false, select = false, okText = 'Continue', cancelText = 'Cancel', requireText = false } = {}) {
            return new Promise((resolve) => {
                visitorAdminDialogTitle.textContent = title;
                visitorAdminDialogMessage.textContent = message;
                visitorAdminDialogText.style.display = text ? 'block' : 'none';
                visitorAdminDialogText.value = '';
                visitorAdminDialogSelect.style.display = select ? 'block' : 'none';
                visitorAdminDialogOk.textContent = okText;
                visitorAdminDialogCancel.textContent = cancelText;
                visitorAdminDialog.classList.add('active');
                visitorAdminDialog.setAttribute('aria-hidden', 'false');

                let okClass = 'btn btn-sm btn-dark';
                if (okText === 'Suspend') okClass = 'btn btn-sm btn-warning';
                else if (okText === 'Terminate' || okText === 'Yes, terminate') okClass = 'btn btn-sm btn-danger';
                else if (okText === 'Send') okClass = 'btn btn-sm btn-success';
                visitorAdminDialogOk.className = okClass;

                let textInputListener = null;
                const syncRequireText = () => {
                    if (text && requireText) {
                        visitorAdminDialogOk.disabled = visitorAdminDialogText.value.trim() === '';
                    } else {
                        visitorAdminDialogOk.disabled = false;
                    }
                };
                syncRequireText();
                if (text && requireText) {
                    textInputListener = () => syncRequireText();
                    visitorAdminDialogText.addEventListener('input', textInputListener);
                }
                if (text) visitorAdminDialogText.focus();

                const close = (value) => {
                    if (textInputListener) {
                        visitorAdminDialogText.removeEventListener('input', textInputListener);
                    }
                    visitorAdminDialogOk.disabled = false;
                    visitorAdminDialogOk.className = 'btn btn-sm btn-dark';
                    visitorAdminDialog.classList.remove('active');
                    visitorAdminDialog.setAttribute('aria-hidden', 'true');
                    visitorAdminDialogOk.removeEventListener('click', onOk);
                    visitorAdminDialogCancel.removeEventListener('click', onCancel);
                    resolve(value);
                };
                const onOk = () => close({ ok: true, text: visitorAdminDialogText.value.trim(), days: visitorAdminDialogSelect.value });
                const onCancel = () => close({ ok: false });
                visitorAdminDialogOk.addEventListener('click', onOk);
                visitorAdminDialogCancel.addEventListener('click', onCancel);
            });
        }

        async function prepareVisitorAdminAction(form, action) {
            if (action === 'inactive') {
                const result = await openVisitorAdminDialog({
                    title: 'Suspend account',
                    message: 'Choose the number of days and write the reason for suspending this account.',
                    text: true,
                    select: true,
                    requireText: true,
                    okText: 'Suspend'
                });
                if (!result.ok || !result.text) return false;
                form.querySelector('[name="inactive_reason"]').value = result.text;
                form.querySelector('[name="inactive_days"]').value = result.days || '7';
            }
            if (action === 'terminate') {
                const result = await openVisitorAdminDialog({
                    title: 'Terminate account',
                    message: 'Write the reason. This deletes the account after you continue.',
                    text: true,
                    requireText: true,
                    okText: 'Terminate'
                });
                if (!result.ok || !result.text) return false;
                form.querySelector('[name="termination_reason"]').value = result.text;
                const confirm = await openVisitorAdminDialog({
                    title: 'Final confirmation',
                    message: 'Terminate this account now? This cannot be undone from the admin page.',
                    okText: 'Yes, terminate'
                });
                if (!confirm.ok) return false;
            }
            if (action === 'message') {
                const templateSelect = form.querySelector('[name="template"]');
                const templateValue = templateSelect?.value || 'warning';
                const templateLabel = templateSelect?.selectedOptions?.[0]?.textContent?.trim() || 'message';
                const confirmSend = await openVisitorAdminDialog({
                    title: 'Send JoMu message',
                    message: `Continue to send the "${templateLabel}" notice to this user?`,
                    okText: 'Continue',
                    cancelText: 'Cancel'
                });
                if (!confirmSend.ok) return false;
                if (templateValue === 'custom') {
                    const result = await openVisitorAdminDialog({
                        title: 'Admin message',
                        message: 'Type the JoMu message to send to this business.',
                        text: true,
                        requireText: true,
                        okText: 'Send'
                    });
                    if (!result.ok || !result.text) return false;
                    form.querySelector('[name="custom_message"]').value = result.text;
                }
            }
            return true;
        }

        document.querySelectorAll('.visitor-admin-form').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submitter = event.submitter;
                const action = submitter?.value || '';
                if (!(await prepareVisitorAdminAction(form, action))) return;

                const data = new FormData(form);
                if (submitter?.name) data.set(submitter.name, submitter.value);
                form.querySelectorAll('button, select, input').forEach((control) => control.disabled = true);
                try {
                    const endpoint = new URL(form.getAttribute('action') || '', window.location.href).toString();
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        body: data,
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    const responseText = await response.text();
                    let result = {};
                    try {
                        result = responseText ? JSON.parse(responseText) : {};
                    } catch (error) {
                        result = { message: responseText || 'Action failed.' };
                    }
                    if (!response.ok || result.ok === false) {
                        throw new Error(result.message || 'Action failed.');
                    }
                    await openVisitorAdminDialog({ title: 'Done', message: result.message || 'Done.', okText: 'OK', cancelText: 'Close' });
                    window.location.reload();
                } catch (error) {
                    openVisitorAdminDialog({ title: 'Action failed', message: error.message || 'Action failed.', okText: 'OK', cancelText: 'Close' });
                } finally {
                    form.querySelectorAll('button, select, input').forEach((control) => control.disabled = false);
                }
            });
        });
    </script>
    <?php endif; ?>
    <script>
        const visitorProfileSignedIn = <?php echo $viewerUserId > 0 ? 'true' : 'false'; ?>;
        const visitorProfileGuestMessage = <?php echo json_encode(jomu_is_suspended_browse_session() ? 'Your account was suspended.' : 'Not signed in!', JSON_UNESCAPED_UNICODE); ?>;
        const visitorContactError = document.getElementById('visitor-contact-error');

        function showVisitorContactError(message) {
            if (!visitorContactError) return;

            const safeMessage = String(message || '').trim();
            if (safeMessage === '') {
                visitorContactError.textContent = '';
                visitorContactError.style.display = 'none';
                return;
            }

            visitorContactError.textContent = safeMessage;
            visitorContactError.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function handleVisitorCall(buttonEl) {
            const dialNumber = String(buttonEl.dataset.phoneDial || '').trim();
            const displayNumber = String(buttonEl.dataset.phoneDisplay || dialNumber).trim();
            if (!dialNumber) return;

            const isMobileDevice = /Android|iPhone|iPad|iPod|Windows Phone|Mobile/i.test(navigator.userAgent || '');
            if (isMobileDevice) {
                window.location.href = `tel:${dialNumber}`;
                return;
            }

            try {
                await navigator.clipboard.writeText(displayNumber);
                const toast = document.createElement('div');
                toast.style.cssText = 'position:fixed;bottom:16px;left:16px;right:16px;background:#28a745;color:#fff;padding:12px 16px;border-radius:4px;z-index:9999;font-size:14px;';
                toast.textContent = `Business number copied: ${displayNumber}`;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            } catch (error) {
                // Copy failed, user can see the number in the contact card
            }
        }

        document.querySelectorAll('.visitor-contact-btn').forEach((buttonEl) => {
            buttonEl.addEventListener('click', async () => {
                if (!visitorProfileSignedIn) {
                    showVisitorContactError(visitorProfileGuestMessage);
                    return;
                }

                showVisitorContactError('');
                const action = String(buttonEl.dataset.contactAction || '').trim();

                if (action === 'call') {
                    await handleVisitorCall(buttonEl);
                    return;
                }

                const targetHref = String(buttonEl.dataset.contactHref || '').trim();
                if (targetHref !== '') {
                    window.location.href = targetHref;
                }
            });
        });
    </script>
    <script src="/assets/listing-preview-modal.js"></script>
    <script src="/assets/listing-preview-gallery.js"></script>
    <script src="/assets/bootstrap.bundle.min.js"></script>
    <script src="/assets/cookie-consent.js"></script>
</body>
</html>
