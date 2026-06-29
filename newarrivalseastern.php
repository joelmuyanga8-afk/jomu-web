<?php
session_start();
require 'php/connection/dbconn.php';
require 'php/partials/helpers.php';

$image_listings = [];
$video_listings = [];
$currentUserId = 0;

$outOfStockColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'out_of_stock'");
if (!$outOfStockColumnCheck || $outOfStockColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN out_of_stock TINYINT(1) NOT NULL DEFAULT 0");
}

$regionColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'region'");
if (!$regionColumnCheck || $regionColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN region VARCHAR(20) NULL AFTER category");
}

$cityTownColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'city_town'");
if (!$cityTownColumnCheck || $cityTownColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN city_town VARCHAR(120) NULL AFTER region");
}

$createdAtColumnCheck = $conn->query("SHOW COLUMNS FROM listings LIKE 'created_at'");
if (!$createdAtColumnCheck || $createdAtColumnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE listings ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
}

if (!empty($_SESSION['emailormobilenumber'])) {
    $currentUserStmt = $conn->prepare("SELECT id FROM users WHERE emailormobilenumber = ? LIMIT 1");
    if ($currentUserStmt) {
        $currentUserStmt->bind_param("s", $_SESSION['emailormobilenumber']);
        $currentUserStmt->execute();
        $currentUserRow = $currentUserStmt->get_result()->fetch_assoc();
        $currentUserStmt->close();
        if (!empty($currentUserRow['id'])) {
            $currentUserId = (int) $currentUserRow['id'];
        }
    }
}

$easternListingsStmt = $conn->prepare(
    "SELECT l.*, u.businessname AS seller_businessname, u.profilepic AS seller_profilepic
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     WHERE COALESCE(l.out_of_stock, 0) = 0
       AND COALESCE(l.moderation_status, 'visible') <> 'hidden'
       AND l.admin_purged_at IS NULL
       AND LOWER(TRIM(COALESCE(l.region, ''))) = 'eastern'
       AND l.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
     ORDER BY l.created_at DESC, l.listing_id DESC"
);

if ($easternListingsStmt) {
    $easternListingsStmt->execute();
    $easternListingsRes = $easternListingsStmt->get_result();
    while ($row = $easternListingsRes->fetch_assoc()) {
        if (getMediaType($row['media']) === 'video') {
            $video_listings[] = $row;
            continue;
        }

        $image_listings[] = $row;
    }
    $easternListingsStmt->close();
}

$desktop_video_listings = $video_listings;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Arrivals | Eastern Uganda</title>
    <meta name="descrption" content="Find the newest products, suppliers, businesses and services
    in Eastern Uganda on JoMu and connect with markets.">
    <link rel="stylesheet" href="/assets/bootstrap.css">
    <link rel="stylesheet" href="/assets/style.css">
     <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/jomu_favicon_white-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/jomu_favicon_white-32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/assets/images/jomu_favicon_white-48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/images/jomu_favicon_white-192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/images/jomu_favicon_white-512.png">
    <style>
        .newarrivals-mobile-auth-menu {
            position: fixed;
            left: auto !important;
            right: var(--mobile-auth-menu-right, 35px) !important;
            top: var(--mobile-auth-menu-top, 48px) !important;
            z-index: 1200;
            margin-top: 0 !important;
            transform: none !important;
        }

        .newarrivals-mobile-auth-menu::before {
            content: "";
            position: absolute;
            top: -7px;
            right: var(--mobile-auth-tail-right, 10px);
            width: 14px;
            height: 14px;
            background: #fff;
            transform: rotate(45deg);
            border-radius: 2px;
            box-shadow: -2px -2px 8px rgba(17, 24, 39, 0.04);
            left: auto;
        }

        img.media-preview-item,
        img.media-preview-source {
            cursor: default;
        }

        video.media-preview-item,
        video.media-preview-source {
            cursor: pointer;
        }

        #mediaPreviewOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 18px;
        }

        #mediaPreviewOverlay.active {
            display: flex;
        }

        .media-preview-panel {
            max-width: 96vw;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            z-index: 1;
        }

        .media-preview-content {
            max-width: 96vw;
            max-height: 72vh;
            width: auto;
            height: auto;
            object-fit: contain;
            background: #000;
            z-index: 1;
        }

        .media-preview-details {
            width: min(96vw, 620px);
            background: rgba(9, 9, 9, 0.82);
            color: #fff;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: left;
            z-index: 3;
        }

        .media-preview-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.3;
            color: rgb(241, 90, 36);
        }

        .media-preview-price,
        .media-preview-description {
            margin: 4px 0 0;
            font-size: 0.9rem;
            line-height: 1.35;
        }

        .media-preview-close {
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

        .media-preview-watermark {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 140px;
            max-width: 28vw;
            opacity: 0.28;
            pointer-events: none;
            user-select: none;
            display: none;
            z-index: 2;
        }

        .product-price-range {
            font-size: 1.14rem;
            font-weight: 800;
        }

        .listing-name-top {
            order: 1;
        }

        .listing-description {
            order: 2;
        }

        .listing-description-link {
            color: inherit;
            text-decoration: none;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-clamp: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            line-height: 1.25;
            min-height: 2.5em;
            cursor: pointer;
        }

        .listing-description-link:hover {
            text-decoration: none;
            color: inherit;
        }

        .product-price-range {
            order: 3;
        }

        .listing-action-btn {
            order: 4;
        }

        .jomu-card-typography {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .jomu-card-typography .card-title {
            font-weight: 600;
            letter-spacing: 0;
        }

        .jomu-card-typography .card-text {
            font-weight: 500;
            line-height: 1.25;
        }

        .jomu-card-typography .product-price-range {
            font-weight: 800;
            line-height: 1.2;
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
        }

        .jomu-card-typography .product-price-range .price-unit {
            font-weight: 400;
        }

        .jomu-card-typography .card-img-button {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-weight: 600;
        }

        .cards-container .card-newarrivals .card-img-top {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            background: #fff;
            padding: 0 !important;
        }

        .cards-container .card-newarrivals .card-body {
            padding: 0.35rem !important;
            display: flex;
            flex-direction: column;
            gap: 1px;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        .cards-container .card-newarrivals .card-title {
            margin: 0;
            color: rgb(241, 90, 36);
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.15;
        }

        .cards-container .card-newarrivals .card-text {
            margin: 0;
            font-weight: 500;
            line-height: 1.25;
            font-size: 1rem;
        }

        .cards-container .card-newarrivals .product-price-range {
            font-weight: 800 !important;
            font-size: 0.95rem;
            letter-spacing: -0.01em;
        }

        .cards-container .card-newarrivals .card-img-button {
            margin-top: auto !important;
            margin-bottom: 0 !important;
            padding: 0.2rem 0.1rem !important;
            line-height: 1.1;
            font-weight: 600;
            font-size: 0.8rem;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .listing-load-more-wrap {
            display: flex;
            justify-content: center;
            padding-top: 0.9rem;
        }

        .listing-load-more-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 116px;
            padding: 0.35rem 0.9rem;
            background-color: rgb(241, 90, 36);
            border-color: rgb(241, 90, 36);
            color: #fff;
            text-align: center;
        }

        .listing-load-more-btn:hover,
        .listing-load-more-btn:focus,
        .listing-load-more-btn:active {
            background-color: rgb(241, 90, 36) !important;
            border-color: rgb(241, 90, 36) !important;
            color: #fff !important;
        }

        .video-card-body {
            display: flex;
            flex-direction: column;
            gap: 0.08rem;
        }

        .video-seller-row {
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .video-seller-link {
            color: inherit;
            text-decoration: none;
        }

        .video-seller-link:hover {
            color: inherit;
            text-decoration: none;
        }

        .video-seller-dp {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            background: #e9ecef;
        }

        .video-seller-dp-fallback {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #fff;
            background: #6c757d;
        }

        .video-seller-name {
            margin: 0;
            font-size: 0.82rem;
            line-height: 1.02;
            font-weight: 600;
            color: #343a40;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .video-stock-title {
            margin: 0;
            font-size: 0.92rem;
            line-height: 1.05;
            font-weight: 700;
            color: rgb(241, 90, 36);
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            line-clamp: 1;
            overflow: hidden;
        }

        .video-description-brief {
            margin: 0;
            font-size: 0.8rem;
            color: #5f676f;
            line-height: 1.08;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-clamp: 2;
            overflow: hidden;
        }

        .video-hashtags {
            margin-top: auto;
            font-size: 0.74rem;
            font-weight: 600;
            color: #0d6efd;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .footer-links {
            gap: 4px 8px;
        }

        .footer-feedback,
        .footer-feedback a,
        .footer-feedback a:visited,
        .footer-feedback a:hover,
        .footer-feedback a:focus,
        .footer-feedback small {
            color: #fff;
        }

        @media (max-width: 767.98px) {
            #navbarone {
                height: 45px;
                min-height: 45px;
                padding-top: 0;
                padding-bottom: 0;
                align-items: center;
                line-height: 1;
            }

            #navbarone>.container {
                min-height: 45px;
                display: flex;
                align-items: center;
                padding-left: 10px;
                padding-right: 6px;
            }

            #navbarone .navbar-brand,
            #navbarone .brand-logos {
                margin: 0;
                padding: 0;
                line-height: 1;
            }

            #navbarone .logo {
                width: clamp(88px, 26vw, 112px);
                display: block;
            }

            #newarrivals-mobile-toggler {
                position: absolute;
                top: 50%;
                right: 26px;
                transform: translateY(-50%);
                width: 36px;
                height: 36px;
                margin: 0;
                padding: 2px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            #newarrivals-mobile-toggler .signin-icon {
                width: 21px;
                height: 21px;
                margin-bottom: 0;
            }

            #navbarone + main.main-one {
                margin-top: 0;
                padding-top: 0;
            }

            #navbarone + main.main-one > .container-fluid {
                padding-top: 0 !important;
                margin-top: 0 !important;
            }

            #navbarone + main.main-one > .container-fluid > .newarrivals-container-one {
                margin-top: 0 !important;
            }

            html {
                background-color: #161515;
            }

            body {
                margin: 0;
            }

            footer {
                padding-left: 0;
                padding-right: 0;
            }

            .footer-feedback br {
                display: none;
            }

            .footer-feedback small {
                display: block;
                margin-top: 0;
            }

            main > .container-fluid,
            main > .container-fluid > .d-block.d-md-none.d-lg-none,
            main .newarrivals-container-one,
            main .videos-images-container-newarrivals-small,
            main .videos-images-container-newarrivals-small .images-container-newarrivals,
            main .videos-images-container-newarrivals-small .videos-container-newarrivals,
            main .videos-images-container-newarrivals-small .newarrivals-feedback {
                width: 100% !important;
                max-width: none !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                padding-left: 8px !important;
                padding-right: 8px !important;
                padding-bottom: 4px !important;
            }

            main .videos-images-container-newarrivals-small {
                padding-left: 4px !important;
                padding-right: 4px !important;
            }

            main .videos-images-container-newarrivals-small .images-container-newarrivals,
            main .videos-images-container-newarrivals-small .videos-container-newarrivals {
                flex: 0 0 100% !important;
            }

            .videos-container-newarrivals {
                width: 100%;
                max-width: none;
                padding-left: 4px !important;
                padding-right: 4px !important;
            }

            .videos-container-newarrivals .row {
                --bs-gutter-x: 0.25rem;
                --bs-gutter-y: 0.25rem;
            }

            .images-container-newarrivals .row {
                --bs-gutter-x: 0;
                --bs-gutter-y: 0.25rem;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            .images-container-newarrivals .row > * {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .cards-container {
                padding-left: 4px;
                padding-right: 4px;
            }

            .cards-container .card-newarrivals.h-100 {
                height: auto !important;
            }

            .cards-container .card-newarrivals .card-img-top {
                aspect-ratio: auto;
                height: clamp(100px, 30vw, 140px);
                object-fit: cover;
            }

            .cards-container .card-newarrivals .card-title {
                font-size: 0.82rem;
                line-height: 1.15;
            }

            .cards-container .listing-description-link {
                -webkit-line-clamp: 1;
                line-clamp: 1;
                min-height: 1.2em;
                font-size: 0.76rem;
            }

            .cards-container .card-newarrivals .card-text {
                font-size: 0.72rem;
                line-height: 1.15;
            }

            .cards-container .product-price-range {
                line-height: 1.2;
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
                font-size: 0.7rem;
            }

            .cards-container .card-newarrivals .card-img-button {
                font-size: 0.72rem;
                line-height: 1.1;
            }
        }

    </style>
</head>

<body class="newarrivals-page" style="background-color: #f0ecec;">
    <div class="container-fluid" style="z-index: 10; padding: 0px; margin: 0px;">
        <a href="/">
                <video class="d-none d-md-block d-lg-block" style="height: inherit; width: 100%;" autoplay loop muted
                src="/assets/videos/Over Navbar JoMu 70px.mp4"></video>
                 <video class="d-block d-md-none d-lg-none"  style="height: inherit; width: 100%;" autoplay loop muted
                src="/assets/videos/Over Navbar JoMu 1080px.mp4"></video>
        </a>
    </div>
    <nav class="navbar navbar-expand-lg navbar-light  sticky-top navbarone navbar-help bg-dark" id="navbarone"
        style="z-index: 100; margin-top: -6px;">
        <div class="container">
            <a class="navbar-brand brand-logos" href="/">
                <img src="/assets/images/JoMu black and white.png" class="img-fluid logo">
                <img src="/assets/images/JoMu logo redesigned.png" class="img-fluid logo logo-hover">

            </a>
        </div>
        <!-- Auth actions for small and medium screens -->
        <button id="newarrivals-mobile-toggler" class="navbar-toggler d-lg-none signin-icon-bg" type="button"
            aria-expanded="false" aria-label="Open sign in menu">
            <span>
                <img src="/assets/images/icons/Signin.png" class="signin-icon" alt="Sign in">
            </span>
        </button>
        <div id="newarrivals-mobile-auth-menu" class="dropdown-menu mobile-auth-menu newarrivals-mobile-auth-menu"
            aria-labelledby="newarrivals-mobile-toggler">
            <a id="newarrivals-signin-mobile" class="dropdown-item mobile-auth-item" href="/sign-in"
                data-mobile-auth-link="/sign-in">Sign In</a>
            <button id="newarrivals-createaccount-mobile" class="dropdown-item mobile-auth-item mobile-auth-create"
                type="button" data-mobile-auth-link="/create-account">Create account</button>
        </div>

        <!-- Navbar links for large screens -->
        <div class="collapse navbar-collapse d-none d-lg-flex me-4 links-container" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item active signin">
                    <a id="newarrivals-signin-desktop" class="nav-link link-text" href="/sign-in">Sign In</a>
                </li>
            </ul>
            <button id="newarrivals-createaccount-desktop" class="button button-createaccount" onclick="location.href='/create-account'">Create
                account</button>
        </div>
    </nav>
    <!-- Newly added listings should not exceed two weeks and then be removed. -->
    <main class="main-one">
        <div class="container-fluid">
            <div class="container mb-3 mt-3 newarrivals-container-one">
                <h4 style="font-weight: 700">New Arrivals - <span style="color: rgb(241, 90, 36);">Eastern
                        Uganda</span>
                </h4>
                <hr>
                <p>Discover the latest arrivals from Eastern Uganda! Explore newly listed products and services from key
                    towns such as<b style="color: rgb(241, 90, 36);"> Jinja, Mbale, Iganga, Tororo, Soroti,</b> and more.
                   Whether you're buying or selling, stay ahead with fresh opportunities curated to keep you connected and informed. <i><b>Check
                            back often, new listings are added regularly.</b></i>
                </p>
            </div>
            <!-- container for large and medium screens. -->
            <div class="d-none d-md-block d-lg-block">
                <div class="videos-images-container-newarrivals gap-2 px-1">
                    <div id="videoListingsDesktopContainer" class="container videos-container-newarrivals col-2">
                        <h4 class="mt-0 mb-0"> Featured Videos</h4>
                        <p>Discover what businesses just shared.</p>
                        <?php if (!empty($desktop_video_listings)) { ?>
                            <?php foreach ($desktop_video_listings as $listing) { ?>
                                <?php
                                    $sellerName = trim((string) ($listing['seller_businessname'] ?? ''));
                                    if ($sellerName === '') {
                                        $sellerName = 'Business';
                                    }
                                    $sellerProfile = trim((string) ($listing['seller_profilepic'] ?? ''));
                                    $sellerInitial = strtoupper(substr($sellerName, 0, 1));
                                    if ($sellerInitial === '') {
                                        $sellerInitial = 'B';
                                    }
                                    $sellerProfileUrl = ((int) ($listing['user_id'] ?? 0) === $currentUserId && $currentUserId > 0)
                                        ? '/profile'
                                        : ('/visitor-profile?user_id=' . urlencode((string) ($listing['user_id'] ?? '')));
                                    $savedHashtags = trim((string) ($listing['hashtags'] ?? ''));
                                    if ($savedHashtags !== '') {
                                        $savedHashtags = preg_replace('/\s+/', ' ', $savedHashtags);
                                    }
                                    $tagSources = [(string) ($listing['listing_type'] ?? ''), (string) ($listing['category'] ?? '')];
                                    $hashtags = [];
                                    foreach ($tagSources as $sourceTag) {
                                        $cleanedTag = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $sourceTag);
                                        $parts = preg_split('/\s+/', trim((string) $cleanedTag));
                                        $joined = '';
                                        foreach ($parts as $part) {
                                            if ($part === '') {
                                                continue;
                                            }
                                            $joined .= ucfirst(strtolower($part));
                                        }
                                        if ($joined !== '') {
                                            $hashTag = '#' . $joined;
                                            if (!in_array($hashTag, $hashtags, true)) {
                                                $hashtags[] = $hashTag;
                                            }
                                        }
                                    }
                                    if (count($hashtags) === 0) {
                                        $hashtags[] = '#JoMu';
                                    }
                                    $hashtagDisplay = $savedHashtags !== '' ? $savedHashtags : implode(' ', $hashtags);
                                ?>
                                <div class="mb-2">
                                    <div class="card h-100">
                                        <div class="video-wrapper">
                                            <?php
                                                $videoListingType = strtolower((string) ($listing['listing_type'] ?? ''));
                                                if ($videoListingType !== 'product' && $videoListingType !== 'service') {
                                                    $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                                    $videoListingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                                }
                                                $videoPriceFrom = trim((string) ($listing['price_from'] ?? ''));
                                                $videoPriceTo = trim((string) ($listing['price_to'] ?? ''));
                                                $videoPreviewPrice = '';
                                                if ($videoListingType === 'product' && $videoPriceFrom !== '' && $videoPriceTo !== '') {
                                                    $videoPreviewPrice = formatProductPriceRange($videoPriceFrom, $videoPriceTo);
                                                } elseif ($videoListingType === 'product') {
                                                    $videoPreviewPrice = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                                } else {
                                                    $videoPreviewPrice = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                                }
                                            ?>
                                            <video class="video-content media-preview-item media-preview-source" controls muted data-preview-type="video" data-preview-src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" data-preview-title="<?php echo htmlspecialchars($listing['stockname'] ?? ''); ?>" data-preview-description="<?php echo htmlspecialchars($listing['description'] ?? ''); ?>" data-preview-price="<?php echo htmlspecialchars($videoPreviewPrice); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                                <source src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" type="video/mp4">
                                            </video>
                                        </div>
                                        <div class="card-body p-2 video-card-body">
                                            <a href="<?php echo htmlspecialchars($sellerProfileUrl); ?>" class="video-seller-row video-seller-link">
                                                <?php if ($sellerProfile !== '') { ?>
                                                    <img src="<?php echo htmlspecialchars(getMediaPath($sellerProfile, '/php/')); ?>" alt="<?php echo htmlspecialchars($sellerName); ?>" class="video-seller-dp">
                                                <?php } else { ?>
                                                    <span class="video-seller-dp video-seller-dp-fallback"><?php echo htmlspecialchars($sellerInitial); ?></span>
                                                <?php } ?>
                                                <p class="video-seller-name"><?php echo htmlspecialchars($sellerName); ?></p>
                                            </a>
                                            <h6 class="video-stock-title"><?php echo htmlspecialchars($listing['stockname'] ?? ''); ?></h6>
                                            <p class="video-description-brief"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></p>
                                            <p class="video-hashtags mb-0"><?php echo htmlspecialchars($hashtagDisplay); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="card">
                                <div class="card-body p-2">
                                    <p class="mb-0">No Eastern-region video listings have been posted in the last 14 days.</p>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="listing-load-more-wrap">
                            <button type="button" id="videoSeeMoreButtonDesktop" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                        </div>
                    </div>

                    <div class="my-2 cards-container images-container-newarrivals col-10">
                        <?php if (!empty($image_listings)) { ?>
                            <div class="row g-1" id="imageListingsRowDesktop">
                                <?php foreach ($image_listings as $listing) { ?>
                                    <?php
                                        $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
                                        if ($listingType !== 'product' && $listingType !== 'service') {
                                            $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                            $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                        }
                                        $isOwnListing = $currentUserId > 0 && (int) ($listing['user_id'] ?? 0) === $currentUserId;
                                        $actionButtonLabel = $isOwnListing ? 'See Listing' : ($listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale');
                                        $priceFrom = trim((string) ($listing['price_from'] ?? ''));
                                        $priceTo = trim((string) ($listing['price_to'] ?? ''));
                                        $productPriceLabel = '';
                                        if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
                                            $productPriceLabel = formatProductPriceRange($priceFrom, $priceTo);
                                        } elseif ($listingType === 'product') {
                                            $productPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                        }
                                        $displayPriceLabel = $productPriceLabel;
                                        if ($listingType === 'service') {
                                            $displayPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                        }
                                        $purchaseParams = http_build_query([
                                            'image' => getMediaPath($listing['media'], '/php/'),
                                            'title' => $listing['stockname'] ?? '',
                                            'price' => $productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? ''),
                                            'raw_price' => $listing['price'] ?? '',
                                            'price_from' => $priceFrom,
                                            'price_to' => $priceTo,
                                            'description' => $listing['description'] ?? '',
                                            'category' => $listing['category'] ?? '',
                                            'seller_businessname' => $listing['seller_businessname'] ?? '',
                                            'seller_profilepic' => $listing['seller_profilepic'] ?? '',
                                            'seller_id' => $listing['user_id'] ?? '',
                                            'listing_id' => $listing['listing_id'] ?? '',
                                            'listing_type' => $listingType,
                                            'owner_view' => $isOwnListing ? '1' : '0',
                                        ]);
                                        $purchaseUrl = '/purchase-wholesale?' . $purchaseParams;
                                        $actionUrl = (!$isOwnListing && $currentUserId <= 0) ? '/?error=Not+Signed+In!' : $purchaseUrl;
                                    ?>
                                    <div class="col-6 col-md-3 custom-lg-newarrivals">
                                        <div class="card h-100 card-newarrivals">
                                            <img src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" class="card-img-top img-fluid media-preview-item media-preview-source"
                                                alt="<?php echo htmlspecialchars($listing['stockname'] ?? 'Listing image'); ?>"
                                                data-preview-type="image"
                                                data-preview-src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>"
                                                data-preview-title="<?php echo htmlspecialchars($listing['stockname'] ?? ''); ?>"
                                                data-preview-description="<?php echo htmlspecialchars($listing['description'] ?? ''); ?>"
                                                data-preview-price="<?php echo htmlspecialchars($productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? '')); ?>"
                                                data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                            <div class="card-body p-2 d-flex flex-column jomu-card-typography" style="gap: 1px;">
                                                <h5 class="card-title mb-0 listing-name-top"><?php echo htmlspecialchars($listing['stockname'] ?? ''); ?></h5>
                                                <p class="card-text mb-0 listing-description"><a href="<?php echo htmlspecialchars($purchaseUrl); ?>" class="listing-description-link"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></a></p>
                                                <?php if ($displayPriceLabel !== '') { ?>
                                                    <?php
                                                        $priceText = $displayPriceLabel;
                                                        $unitText = '';
                                                        if ($listingType === 'product' && substr($displayPriceLabel, -7) === ' / unit') {
                                                            $priceText = substr($displayPriceLabel, 0, -7);
                                                            $unitText = ' / unit';
                                                        }
                                                    ?>
                                                    <p class="card-text mb-0 product-price-range"><?php echo htmlspecialchars($priceText); ?><?php if ($unitText !== '') { ?><span class="price-unit"><?php echo htmlspecialchars($unitText); ?></span><?php } ?></p>
                                                <?php } ?>
                                                <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn card-img-button py-1 mt-0 listing-action-btn"><?php echo htmlspecialchars($actionButtonLabel); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="card">
                                <div class="card-body p-3">
                                    <p class="mb-0">No Eastern-region image listings have been posted in the last 14 days.</p>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="listing-load-more-wrap">
                            <button type="button" id="imageSeeMoreButtonDesktop" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                        </div>
                        <div class="container text-center mt-5 px-5 py-4 newarrivals-feedback">
                            <h6>We'd love to hear from you! Share your thoughts or suggestions.</h6>
                            <a href="/feedback"><button class="btn btn-newarrivals">Share Feedback</button></a>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Container for small screens. -->
            <div class="d-block d-md-none d-lg-none">
                <div class="videos-images-container-newarrivals-small gap-2 px-1">
                    <div class="my-2 cards-container images-container-newarrivals col-10 w-100">
                        <?php if (!empty($image_listings)) { ?>
                            <div class="row g-1" id="imageListingsRowMobile">
                                <?php foreach ($image_listings as $listing) { ?>
                                    <?php
                                        $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
                                        if ($listingType !== 'product' && $listingType !== 'service') {
                                            $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                            $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                        }
                                        $isOwnListing = $currentUserId > 0 && (int) ($listing['user_id'] ?? 0) === $currentUserId;
                                        $actionButtonLabel = $isOwnListing ? 'See Listing' : ($listingType === 'service' ? 'Schedule a Service' : 'Purchase Wholesale');
                                        $priceFrom = trim((string) ($listing['price_from'] ?? ''));
                                        $priceTo = trim((string) ($listing['price_to'] ?? ''));
                                        $productPriceLabel = '';
                                        if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
                                            $productPriceLabel = formatProductPriceRange($priceFrom, $priceTo);
                                        } elseif ($listingType === 'product') {
                                            $productPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                        }
                                        $displayPriceLabel = $productPriceLabel;
                                        if ($listingType === 'service') {
                                            $displayPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                        }
                                        $purchaseParams = http_build_query([
                                            'image' => getMediaPath($listing['media'], '/php/'),
                                            'title' => $listing['stockname'] ?? '',
                                            'price' => $productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? ''),
                                            'raw_price' => $listing['price'] ?? '',
                                            'price_from' => $priceFrom,
                                            'price_to' => $priceTo,
                                            'description' => $listing['description'] ?? '',
                                            'category' => $listing['category'] ?? '',
                                            'seller_businessname' => $listing['seller_businessname'] ?? '',
                                            'seller_profilepic' => $listing['seller_profilepic'] ?? '',
                                            'seller_id' => $listing['user_id'] ?? '',
                                            'listing_id' => $listing['listing_id'] ?? '',
                                            'listing_type' => $listingType,
                                            'owner_view' => $isOwnListing ? '1' : '0',
                                        ]);
                                        $purchaseUrl = '/purchase-wholesale?' . $purchaseParams;
                                        $actionUrl = (!$isOwnListing && $currentUserId <= 0) ? '/?error=Not+Signed+In!' : $purchaseUrl;
                                    ?>
                                    <div class="col-6 col-md-3 custom-lg-newarrivals">
                                        <div class="card h-100 card-newarrivals">
                                            <img src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" class="card-img-top img-fluid media-preview-item media-preview-source"
                                                alt="<?php echo htmlspecialchars($listing['stockname'] ?? 'Listing image'); ?>"
                                                data-preview-type="image"
                                                data-preview-src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>"
                                                data-preview-title="<?php echo htmlspecialchars($listing['stockname'] ?? ''); ?>"
                                                data-preview-description="<?php echo htmlspecialchars($listing['description'] ?? ''); ?>"
                                                data-preview-price="<?php echo htmlspecialchars($productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? '')); ?>"
                                                data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                            <div class="card-body p-2 d-flex flex-column jomu-card-typography" style="gap: 1px;">
                                                <h5 class="card-title mb-0 listing-name-top"><?php echo htmlspecialchars($listing['stockname'] ?? ''); ?></h5>
                                                <p class="card-text mb-0 listing-description"><a href="<?php echo htmlspecialchars($purchaseUrl); ?>" class="listing-description-link"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></a></p>
                                                <?php if ($displayPriceLabel !== '') { ?>
                                                    <?php
                                                        $priceText = $displayPriceLabel;
                                                        $unitText = '';
                                                        if ($listingType === 'product' && substr($displayPriceLabel, -7) === ' / unit') {
                                                            $priceText = substr($displayPriceLabel, 0, -7);
                                                            $unitText = ' / unit';
                                                        }
                                                    ?>
                                                    <p class="card-text mb-0 product-price-range"><?php echo htmlspecialchars($priceText); ?><?php if ($unitText !== '') { ?><span class="price-unit"><?php echo htmlspecialchars($unitText); ?></span><?php } ?></p>
                                                <?php } ?>
                                                <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn card-img-button py-1 mt-0 listing-action-btn"><?php echo htmlspecialchars($actionButtonLabel); ?></a>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="card">
                                <div class="card-body p-3">
                                    <p class="mb-0">No Eastern-region image listings have been posted in the last 14 days.</p>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="listing-load-more-wrap">
                            <button type="button" id="imageSeeMoreButtonMobile" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                        </div>
                    </div>
                    <div class="container videos-container-newarrivals">
                        <h4 class="mt-0 mb-0"> Featured Videos</h4>
                        <p>Discover what businesses just shared.</p>
                        <?php if (!empty($video_listings)) { ?>
                            <div class="row g-2" id="videoListingsRowMobile">
                            <?php foreach ($video_listings as $listing) { ?>
                                <?php
                                    $sellerName = trim((string) ($listing['seller_businessname'] ?? ''));
                                    if ($sellerName === '') {
                                        $sellerName = 'Business';
                                    }
                                    $sellerProfile = trim((string) ($listing['seller_profilepic'] ?? ''));
                                    $sellerInitial = strtoupper(substr($sellerName, 0, 1));
                                    if ($sellerInitial === '') {
                                        $sellerInitial = 'B';
                                    }
                                    $sellerProfileUrl = ((int) ($listing['user_id'] ?? 0) === $currentUserId && $currentUserId > 0)
                                        ? '/profile'
                                        : ('/visitor-profile?user_id=' . urlencode((string) ($listing['user_id'] ?? '')));
                                    $savedHashtags = trim((string) ($listing['hashtags'] ?? ''));
                                    if ($savedHashtags !== '') {
                                        $savedHashtags = preg_replace('/\s+/', ' ', $savedHashtags);
                                    }
                                    $tagSources = [(string) ($listing['listing_type'] ?? ''), (string) ($listing['category'] ?? '')];
                                    $hashtags = [];
                                    foreach ($tagSources as $sourceTag) {
                                        $cleanedTag = preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $sourceTag);
                                        $parts = preg_split('/\s+/', trim((string) $cleanedTag));
                                        $joined = '';
                                        foreach ($parts as $part) {
                                            if ($part === '') {
                                                continue;
                                            }
                                            $joined .= ucfirst(strtolower($part));
                                        }
                                        if ($joined !== '') {
                                            $hashTag = '#' . $joined;
                                            if (!in_array($hashTag, $hashtags, true)) {
                                                $hashtags[] = $hashTag;
                                            }
                                        }
                                    }
                                    if (count($hashtags) === 0) {
                                        $hashtags[] = '#JoMu';
                                    }
                                    $hashtagDisplay = $savedHashtags !== '' ? $savedHashtags : implode(' ', $hashtags);
                                ?>
                                <div class="col-6">
                                    <div class="card h-100">
                                        <div class="video-wrapper">
                                            <?php
                                                $videoListingType = strtolower((string) ($listing['listing_type'] ?? ''));
                                                if ($videoListingType !== 'product' && $videoListingType !== 'service') {
                                                    $categoryText = strtolower((string) ($listing['category'] ?? ''));
                                                    $videoListingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
                                                }
                                                $videoPriceFrom = trim((string) ($listing['price_from'] ?? ''));
                                                $videoPriceTo = trim((string) ($listing['price_to'] ?? ''));
                                                $videoPreviewPrice = '';
                                                if ($videoListingType === 'product' && $videoPriceFrom !== '' && $videoPriceTo !== '') {
                                                    $videoPreviewPrice = formatProductPriceRange($videoPriceFrom, $videoPriceTo);
                                                } elseif ($videoListingType === 'product') {
                                                    $videoPreviewPrice = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                                } else {
                                                    $videoPreviewPrice = formatPriceText(trim((string) ($listing['price'] ?? '')));
                                                }
                                            ?>
                                            <video class="video-content media-preview-item media-preview-source" controls muted data-preview-type="video" data-preview-src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" data-preview-title="<?php echo htmlspecialchars($listing['stockname'] ?? ''); ?>" data-preview-description="<?php echo htmlspecialchars($listing['description'] ?? ''); ?>" data-preview-price="<?php echo htmlspecialchars($videoPreviewPrice); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">
                                                <source src="<?php echo htmlspecialchars(getMediaPath($listing['media'], '/php/')); ?>" type="video/mp4">
                                            </video>
                                        </div>
                                        <div class="card-body p-2 video-card-body">
                                            <a href="<?php echo htmlspecialchars($sellerProfileUrl); ?>" class="video-seller-row video-seller-link">
                                                <?php if ($sellerProfile !== '') { ?>
                                                    <img src="<?php echo htmlspecialchars(getMediaPath($sellerProfile, '/php/')); ?>" alt="<?php echo htmlspecialchars($sellerName); ?>" class="video-seller-dp">
                                                <?php } else { ?>
                                                    <span class="video-seller-dp video-seller-dp-fallback"><?php echo htmlspecialchars($sellerInitial); ?></span>
                                                <?php } ?>
                                                <p class="video-seller-name"><?php echo htmlspecialchars($sellerName); ?></p>
                                            </a>
                                            <h6 class="video-stock-title"><?php echo htmlspecialchars($listing['stockname'] ?? ''); ?></h6>
                                            <p class="video-description-brief"><?php echo htmlspecialchars($listing['description'] ?? ''); ?></p>
                                            <p class="video-hashtags mb-0"><?php echo htmlspecialchars($hashtagDisplay); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            </div>
                        <?php } else { ?>
                            <div class="card">
                                <div class="card-body p-2">
                                    <p class="mb-0">No Eastern-region video listings have been posted in the last 14 days.</p>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="listing-load-more-wrap">
                            <button type="button" id="videoSeeMoreButtonMobile" class="btn btn-outline-dark listing-load-more-btn" style="display: none;">See More</button>
                        </div>
                        <div class="container text-center mt-5 px-5 py-4 newarrivals-feedback w-100">
                            <h6>We'd love to hear from you! Share your thoughts or suggestions.</h6>
                            <a href="/feedback"><button class="btn btn-newarrivals" style="margin-bottom: 13px;">Share Feedback</button></a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </main>
    <footer class="footer-feedback py-2 text-center">
        <div class="footer-links">
            <a href="/terms-and-conditions" style="color:white;">Terms of Use</a>
            <a href="/privacy-policy" style="color:white;">Privacy Policy</a>
            <a href="/help" style="color:white;">Help</a>
            <a href="/support" style="color:white;">Support</a>
            <a href="/feedback" style="color:white;">Give Feedback</a>
            <a href="/about" style="color:white;">About JoMu</a>
        </div>
        <br>
        <small>&copy; 2026 JoMu. All rights reserved.</small>
    </footer>

    <!-- Back to top button -->
    <button id="backToTop">&#8593;</button>
    <div id="mediaPreviewOverlay" aria-hidden="true">
        <button type="button" class="media-preview-close" id="mediaPreviewClose" aria-label="Close preview">&times;</button>
        <div class="media-preview-panel">
            <img id="mediaPreviewImage" class="media-preview-content" alt="Listing preview" style="display:none;">
            <video id="mediaPreviewVideo" class="media-preview-content" controls style="display:none;"></video>
            <div id="mediaPreviewDetails" class="media-preview-details" style="display:none;">
                <p id="mediaPreviewTitle" class="media-preview-title"></p>
                <p id="mediaPreviewPrice" class="media-preview-price"></p>
                <p id="mediaPreviewDescription" class="media-preview-description"></p>
            </div>
        </div>
        <img id="mediaPreviewWatermark" class="media-preview-watermark" src="/assets/images/JoMu logo redesigned.png" alt="JoMu watermark">
    </div>

    <script>
        (function () {
            const mobileAuthButton = document.getElementById('newarrivals-mobile-toggler');
            const mobileAuthMenu = document.getElementById('newarrivals-mobile-auth-menu');
            if (!mobileAuthButton || !mobileAuthMenu) {
                return;
            }

            function positionMobileAuthMenu() {
                const buttonRect = mobileAuthButton.getBoundingClientRect();
                const tailSize = 14;
                const tailTipReach = Math.round((tailSize * Math.SQRT2) / 2);
                const rightOffset = Math.max(8, window.innerWidth - buttonRect.right);
                const buttonCenterX = buttonRect.left + (buttonRect.width / 2);
                const tailRight = Math.max(8, Math.round(window.innerWidth - rightOffset - buttonCenterX - (tailSize / 2)));
                const menuTop = Math.round(buttonRect.bottom + tailTipReach - 1);
                mobileAuthMenu.style.setProperty('right', `${rightOffset}px`, 'important');
                mobileAuthMenu.style.setProperty('top', `${menuTop}px`, 'important');
                mobileAuthMenu.style.setProperty('--mobile-auth-menu-right', `${rightOffset}px`);
                mobileAuthMenu.style.setProperty('--mobile-auth-menu-top', `${menuTop}px`);
                mobileAuthMenu.style.setProperty('--mobile-auth-tail-right', `${tailRight}px`);
            }

            function setMobileAuthOpen(isOpen) {
                if (isOpen) {
                    positionMobileAuthMenu();
                }
                mobileAuthMenu.classList.toggle('show', isOpen);
                if (isOpen) {
                    window.requestAnimationFrame(positionMobileAuthMenu);
                }
                mobileAuthButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            mobileAuthButton.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                setMobileAuthOpen(!mobileAuthMenu.classList.contains('show'));
            });

            document.addEventListener('click', (event) => {
                if (mobileAuthButton.contains(event.target) || mobileAuthMenu.contains(event.target)) {
                    return;
                }

                setMobileAuthOpen(false);
            });

            window.addEventListener('resize', () => {
                if (mobileAuthMenu.classList.contains('show')) {
                    positionMobileAuthMenu();
                }
            });

            window.addEventListener('scroll', () => {
                if (mobileAuthMenu.classList.contains('show')) {
                    positionMobileAuthMenu();
                }
            }, { passive: true });
        })();

        (function () {
            const mobileAuthItems = Array.from(document.querySelectorAll('[data-mobile-auth-link]'));
            if (mobileAuthItems.length === 0) {
                return;
            }

            mobileAuthItems.forEach((item) => {
                item.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const nextUrl = item.getAttribute('data-mobile-auth-link');
                    if (!nextUrl) {
                        return;
                    }

                    window.location.assign(nextUrl);
                });
            });
        })();
    </script>

    <script>
        async function syncNewArrivalsAuthButtons() {
            try {
                const response = await fetch('/php/auth_status.php', { credentials: 'same-origin' });
                if (!response.ok) return;
                const data = await response.json();
                if (!data || data.signed_in !== true) return;

                const signInDesktop = document.getElementById('newarrivals-signin-desktop');
                const createDesktop = document.getElementById('newarrivals-createaccount-desktop');
                const signInMobile = document.getElementById('newarrivals-signin-mobile');
                const createMobile = document.getElementById('newarrivals-createaccount-mobile');
                const mobileToggler = document.getElementById('newarrivals-mobile-toggler');
                const mobileOffcanvas = document.getElementById('offcanvasNav');

                signInDesktop?.closest('li')?.remove();
                createDesktop?.remove();
                signInMobile?.closest('li')?.remove();
                createMobile?.remove();
                mobileToggler?.remove();
                mobileOffcanvas?.remove();
            } catch (error) {
                // Keep default navbar links when auth check is unavailable.
            }
        }

        syncNewArrivalsAuthButtons();
    </script>
    <script>
        function setupSeeMoreListings(options) {
            const root = document.getElementById(options.rootId);
            const button = document.getElementById(options.buttonId);
            if (!root || !button) return;

            const itemSelector = options.itemSelector || ':scope > *';
            const items = Array.from(root.querySelectorAll(itemSelector));
            if (items.length === 0) {
                button.style.display = 'none';
                return;
            }

            const mobileQuery = window.matchMedia('(max-width: 767.98px)');
            let visibleCount = 0;
            let lastBatchSize = 0;

            function getBatchSize() {
                return mobileQuery.matches ? options.mobileBatch : options.desktopBatch;
            }

            function render(resetCount = false) {
                const batchSize = getBatchSize();
                if (resetCount || visibleCount === 0) {
                    visibleCount = batchSize;
                } else if (batchSize !== lastBatchSize) {
                    const previouslyShownBatches = Math.max(1, Math.ceil(visibleCount / Math.max(lastBatchSize, 1)));
                    visibleCount = previouslyShownBatches * batchSize;
                }

                lastBatchSize = batchSize;
                const limitedVisibleCount = Math.min(visibleCount, items.length);

                items.forEach((item, index) => {
                    item.style.display = index < limitedVisibleCount ? '' : 'none';
                });

                button.style.display = limitedVisibleCount < items.length ? 'inline-flex' : 'none';
            }

            button.addEventListener('click', () => {
                visibleCount += getBatchSize();
                render();
            });

            const handleViewportChange = () => render(visibleCount === 0);
            if (typeof mobileQuery.addEventListener === 'function') {
                mobileQuery.addEventListener('change', handleViewportChange);
            } else if (typeof mobileQuery.addListener === 'function') {
                mobileQuery.addListener(handleViewportChange);
            }

            render(true);
        }

        setupSeeMoreListings({
            rootId: 'imageListingsRowDesktop',
            buttonId: 'imageSeeMoreButtonDesktop',
            desktopBatch: 24,
            mobileBatch: 24
        });

        setupSeeMoreListings({
            rootId: 'imageListingsRowMobile',
            buttonId: 'imageSeeMoreButtonMobile',
            desktopBatch: 20,
            mobileBatch: 20
        });

        setupSeeMoreListings({
            rootId: 'videoListingsDesktopContainer',
            buttonId: 'videoSeeMoreButtonDesktop',
            desktopBatch: 8,
            mobileBatch: 8,
            itemSelector: ':scope > .mb-2'
        });

        setupSeeMoreListings({
            rootId: 'videoListingsRowMobile',
            buttonId: 'videoSeeMoreButtonMobile',
            desktopBatch: 4,
            mobileBatch: 4,
            itemSelector: ':scope > .col-6'
        });

        // Back to top functionality.
        const btn = document.getElementById("backToTop");

        window.addEventListener("scroll", () => {
            if (window.scrollY > 400) {
                btn.style.display = "block";
            } else {
                btn.style.display = "none"
            }
        });

        btn.addEventListener("click", () => {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });

        const mediaOverlay = document.getElementById("mediaPreviewOverlay");
        const mediaClose = document.getElementById("mediaPreviewClose");
        const mediaPreviewImage = document.getElementById("mediaPreviewImage");
        const mediaPreviewVideo = document.getElementById("mediaPreviewVideo");
        const mediaPreviewDetails = document.getElementById("mediaPreviewDetails");
        const mediaPreviewTitle = document.getElementById("mediaPreviewTitle");
        const mediaPreviewPrice = document.getElementById("mediaPreviewPrice");
        const mediaPreviewDescription = document.getElementById("mediaPreviewDescription");
        const mediaWatermark = document.getElementById("mediaPreviewWatermark");
        const countedNewArrivalsPreviewViews = new Set();
        const countedNewArrivalsVideoViews = new Set();
        const pendingNewArrivalsVideoViewTimers = new Map();
        let recentStorageKey = "jomuRecentlyViewedListings:guest";
        let recentStorageKeyPromise = null;

        function saveRecentlyViewedListing(item, storageKey) {
            if (!item || !item.listing_id) return;
            try {
                const raw = localStorage.getItem(storageKey);
                const currentItems = raw ? JSON.parse(raw) : [];
                const safeItems = Array.isArray(currentItems) ? currentItems : [];
                const nextItem = {
                    listing_id: Number(item.listing_id) || 0,
                    viewed_at: new Date().toISOString(),
                    media_type: item.media_type === "video" ? "video" : "image",
                    html: String(item.html || "").trim(),
                    media_src: String(item.media_src || "").trim(),
                    title: String(item.title || "").trim(),
                    description: String(item.description || "").trim(),
                    price: String(item.price || "").trim(),
                    action_label: String(item.action_label || "Purchase Wholesale").trim(),
                    purchase_url: String(item.purchase_url || "#").trim(),
                };

                const filteredItems = safeItems.filter((entry) => Number(entry?.listing_id || 0) !== nextItem.listing_id);
                filteredItems.unshift(nextItem);
                localStorage.setItem(storageKey, JSON.stringify(filteredItems.slice(0, 20)));
            } catch (error) {
                // Non-blocking recent-history update.
            }
        }

        async function getRecentStorageKey() {
            if (recentStorageKeyPromise) {
                return recentStorageKeyPromise;
            }

            recentStorageKeyPromise = fetch("/php/auth_status.php", { credentials: "same-origin" })
                .then((response) => response.ok ? response.json() : null)
                .then((data) => {
                    recentStorageKey = data?.signed_in && data?.user_key
                        ? `jomuRecentlyViewedListings:${data.user_key}`
                        : "jomuRecentlyViewedListings:guest";
                    return recentStorageKey;
                })
                .catch(() => recentStorageKey);

            return recentStorageKeyPromise;
        }

        function storeRecentlyViewedListing(item) {
            getRecentStorageKey().then((storageKey) => saveRecentlyViewedListing(item, storageKey));
        }

        function storeRecentListingFromSource(sourceEl) {
            const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || "", 10);
            if (!Number.isInteger(listingId) || listingId <= 0) return;

            const cardEl = sourceEl.closest(".card");
            const columnEl = sourceEl.closest(".col-6.col-md-4.col-lg-3");
            const actionLink = cardEl?.querySelector('a[href*="/purchase-wholesale"]');
            storeRecentlyViewedListing({
                listing_id: listingId,
                media_type: sourceEl?.dataset.previewType || "image",
                html: columnEl?.outerHTML || "",
                media_src: sourceEl?.dataset.previewSrc || sourceEl?.getAttribute("src") || "",
                title: sourceEl?.dataset.previewTitle || "",
                description: sourceEl?.dataset.previewDescription || "",
                price: sourceEl?.dataset.previewPrice || "",
                action_label: actionLink?.textContent?.trim() || "Purchase Wholesale",
                purchase_url: actionLink?.getAttribute("href") || "#",
            });
        }

        async function incrementPreviewImageView(sourceEl) {
            const type = String(sourceEl?.dataset.previewType || "").trim();
            const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || "", 10);
            if (type !== "image" || !Number.isInteger(listingId) || listingId <= 0 || countedNewArrivalsPreviewViews.has(listingId)) {
                return;
            }

            countedNewArrivalsPreviewViews.add(listingId);
            storeRecentListingFromSource(sourceEl);

            try {
                await fetch(`/php/increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        async function incrementVideoPlaybackView(listingId) {
            if (!Number.isInteger(listingId) || listingId <= 0 || countedNewArrivalsVideoViews.has(listingId)) {
                return;
            }

            countedNewArrivalsVideoViews.add(listingId);
            const sourceEl = document.querySelector(`.media-preview-source[data-preview-listing-id="${listingId}"]`);
            storeRecentListingFromSource(sourceEl);

            try {
                await fetch(`/php/increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`);
            } catch (error) {
                // Non-blocking analytics update.
            }
        }

        function clearPendingVideoView(videoEl) {
            const timerId = pendingNewArrivalsVideoViewTimers.get(videoEl);
            if (timerId) {
                clearTimeout(timerId);
                pendingNewArrivalsVideoViewTimers.delete(videoEl);
            }
        }

        function scheduleVideoViewIncrement(videoEl) {
            const listingId = Number.parseInt(videoEl?.dataset.previewListingId || "", 10);
            if (!Number.isInteger(listingId) || listingId <= 0 || countedNewArrivalsVideoViews.has(listingId) || pendingNewArrivalsVideoViewTimers.has(videoEl)) {
                return;
            }

            const timerId = setTimeout(() => {
                pendingNewArrivalsVideoViewTimers.delete(videoEl);
                if (countedNewArrivalsVideoViews.has(listingId) || videoEl.paused || videoEl.ended) {
                    return;
                }
                incrementVideoPlaybackView(listingId);
            }, 2000);

            pendingNewArrivalsVideoViewTimers.set(videoEl, timerId);
        }

        function registerVideoViewTracking(videoEl) {
            if (!(videoEl instanceof HTMLVideoElement) || videoEl.dataset.viewTrackingBound === "1") {
                return;
            }

            videoEl.dataset.viewTrackingBound = "1";
            videoEl.addEventListener("play", () => scheduleVideoViewIncrement(videoEl));
            videoEl.addEventListener("pause", () => clearPendingVideoView(videoEl));
            videoEl.addEventListener("ended", () => clearPendingVideoView(videoEl));
            videoEl.addEventListener("emptied", () => clearPendingVideoView(videoEl));
        }

        function updateMediaPreviewDetails(sourceEl) {
            if (!mediaPreviewDetails) return;
            const title = String(sourceEl?.dataset.previewTitle || "").trim();
            const price = String(sourceEl?.dataset.previewPrice || "").trim();
            const description = String(sourceEl?.dataset.previewDescription || "");
            const hasDetails = title !== "" || price !== "" || description !== "";

            mediaPreviewTitle.textContent = title;
            mediaPreviewTitle.style.display = title ? "block" : "none";
            mediaPreviewPrice.textContent = price ? `Price: ${price}` : "";
            mediaPreviewPrice.style.display = price ? "block" : "none";
            mediaPreviewDescription.textContent = description;
            mediaPreviewDescription.style.whiteSpace = 'pre-wrap';
            mediaPreviewDescription.style.wordBreak = 'break-word';
            mediaPreviewDescription.style.display = description ? 'block' : 'none';
            mediaPreviewDetails.style.display = hasDetails ? "block" : "none";
        }

        function closeMediaPreview() {
            if (!mediaOverlay) return;
            mediaPreviewVideo.pause();
            mediaPreviewVideo.removeAttribute("src");
            delete mediaPreviewVideo.dataset.previewListingId;
            mediaPreviewImage.removeAttribute("src");
            mediaPreviewImage.style.display = "none";
            mediaPreviewVideo.style.display = "none";
            if (mediaPreviewDetails) {
                mediaPreviewDetails.style.display = "none";
            }
            mediaOverlay.classList.remove("active");
            mediaOverlay.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
            if (mediaWatermark) mediaWatermark.style.display = "none";
        }

        function openMediaPreview(type, src, sourceEl) {
            if (!mediaOverlay || !src) return;
            mediaPreviewImage.style.display = "none";
            mediaPreviewVideo.style.display = "none";
            updateMediaPreviewDetails(sourceEl);
            incrementPreviewImageView(sourceEl);

            if (type === "video") {
                mediaPreviewVideo.src = src;
                mediaPreviewVideo.dataset.previewListingId = sourceEl?.dataset.previewListingId || "";
                mediaPreviewVideo.style.display = "block";
                mediaPreviewVideo.play().catch(() => {});
                if (mediaWatermark) mediaWatermark.style.display = "none";
            } else {
                mediaPreviewImage.src = src;
                mediaPreviewImage.style.display = "block";
                if (mediaWatermark) mediaWatermark.style.display = "block";
            }
            mediaOverlay.classList.add("active");
            mediaOverlay.setAttribute("aria-hidden", "false");
            document.body.style.overflow = "hidden";
        }

        function openPreviewFromSource(sourceEl) {
            if (!sourceEl) return;
            const type = sourceEl.dataset.previewType || (sourceEl.tagName.toLowerCase() === "video" ? "video" : "image");
            const src = sourceEl.dataset.previewSrc || sourceEl.getAttribute("src") || "";
            openMediaPreview(type, src, sourceEl);
        }

        const newArrivalsMainEl = document.querySelector("main");
        let lastTapTime = 0;
        let lastTapSrc = "";

        newArrivalsMainEl?.addEventListener("click", (event) => {
            const sourceEl = event.target.closest(".media-preview-source") || event.target.closest(".video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link")?.closest(".card")?.querySelector(".media-preview-source");
            openPreviewFromSource(sourceEl);
        });

        newArrivalsMainEl?.addEventListener("touchend", (event) => {
            const sourceEl = event.target.closest(".media-preview-source") || event.target.closest(".video-stock-title, .video-description-brief, .video-hashtags, .listing-name-top, .listing-description, .listing-description-link")?.closest(".card")?.querySelector(".media-preview-source");
            if (!sourceEl) return;
            const sourceKey = sourceEl.dataset.previewSrc || sourceEl.getAttribute("src") || "";
            const now = Date.now();
            const isDoubleTap = now - lastTapTime < 350 && sourceKey !== "" && sourceKey === lastTapSrc;

            lastTapTime = now;
            lastTapSrc = sourceKey;

            if (!isDoubleTap) return;
            event.preventDefault();
            openPreviewFromSource(sourceEl);
            lastTapTime = 0;
            lastTapSrc = "";
        }, { passive: false });

        document.addEventListener("touchstart", () => {
            if (Date.now() - lastTapTime > 600) {
                lastTapTime = 0;
                lastTapSrc = "";
            }
        });

        document.querySelectorAll('video[data-preview-listing-id]').forEach((videoEl) => {
            registerVideoViewTracking(videoEl);
        });
        registerVideoViewTracking(mediaPreviewVideo);

        if (mediaClose) {
            mediaClose.addEventListener("click", closeMediaPreview);
        }

        if (mediaOverlay) {
            mediaOverlay.addEventListener("click", (event) => {
                if (event.target === mediaOverlay) {
                    closeMediaPreview();
                }
            });
        }

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeMediaPreview();
            }
        });

        document.addEventListener("play", (event) => {
            const currentVideo = event.target;
            if (!(currentVideo instanceof HTMLVideoElement) || !currentVideo.hasAttribute("controls")) {
                return;
            }

            document.querySelectorAll("video[controls]").forEach((video) => {
                if (video !== currentVideo && !video.paused) {
                    video.pause();
                }
            });
        }, true);
    </script>
    <script src="/assets/listing-preview-modal.js"></script>
    <script src="/assets/listing-preview-gallery.js"></script>
    <script src="/assets/bootstrap.bundle.min.js"></script>
    <script src="/assets/cookie-consent.js"></script>
</body>

</html>
