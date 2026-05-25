<?php
    $baseCardClass = trim((string) ($listCardClass ?? ''));
    $isOutOfStock = !empty($listing['out_of_stock']);
    $isListingHidden = strtolower((string) ($listing['moderation_status'] ?? 'visible')) === 'hidden';
    $ownerHiddenShowroomCard = !empty($ownerHiddenShowroomCard) && $isListingHidden;
    $fullCardClass = trim('card h-100 showroom-img ' . $baseCardClass . ($isOutOfStock ? ' owner-out-of-stock-card' : '') . ($ownerHiddenShowroomCard ? ' owner-hidden-card' : ''));
?>
<div class="<?php echo htmlspecialchars($fullCardClass); ?>">
    <?php
        $media = $listing['media'];
        $type = getMediaType($media);
        $mediaPath = getMediaPath($media, $base);
        $viewsLabel = formatListingViewsLabel($listing['views'] ?? 0);
        $showManageMenu = !empty($showManageMenu);
        $showProfilePinOption = !empty($showProfilePinOption);
        $isProfilePinned = !empty($listing['_profile_is_pinned']);
        $shareSellerBusinessName = (string) ($shareSellerBusinessName ?? '');
        $shareSellerProfilePic = (string) ($shareSellerProfilePic ?? '');

        $listingType = strtolower((string) ($listing['listing_type'] ?? ''));
        if ($listingType !== 'product' && $listingType !== 'service') {
            $categoryText = strtolower((string) ($listing['category'] ?? ''));
            $listingType = strpos($categoryText, 'service') !== false ? 'service' : 'product';
        }
        $priceFrom = trim((string) ($listing['price_from'] ?? ''));
        $priceTo = trim((string) ($listing['price_to'] ?? ''));
        $productPriceLabel = '';
        if ($listingType === 'product' && $priceFrom !== '' && $priceTo !== '') {
            $productPriceLabel = formatProductPriceRange($priceFrom, $priceTo);
        } elseif ($listingType === 'product') {
            $productPriceLabel = formatPriceText(trim((string) ($listing['price'] ?? '')));
        }
        $shareParams = http_build_query([
            'image' => $mediaPath,
            'title' => $listing['stockname'] ?? '',
            'price' => $productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? ''),
            'raw_price' => $listing['price'] ?? '',
            'price_from' => $priceFrom,
            'price_to' => $priceTo,
            'description' => $listing['description'] ?? '',
            'category' => $listing['category'] ?? '',
            'seller_businessname' => $shareSellerBusinessName,
            'seller_profilepic' => $shareSellerProfilePic,
            'seller_id' => $listing['user_id'] ?? '',
            'listing_id' => $listing['listing_id'] ?? '',
            'listing_type' => $listingType,
        ]);
        $purchaseUrl = '/purchasewholesale.html?' . $shareParams;
        $previewTitle = trim((string) ($listing['stockname'] ?? ''));
        $previewDescription = trim((string) ($listing['description'] ?? ''));
        $previewPrice = trim((string) ($productPriceLabel !== '' ? $productPriceLabel : ($listing['price'] ?? '')));

        $previewPostedLabel = '';
        $createdRaw = trim((string) ($listing['created_at'] ?? ''));
        if ($createdRaw !== '') {
            $createdTs = strtotime($createdRaw);
            if ($createdTs !== false) {
                $previewPostedLabel = date('j M Y, g:i a', $createdTs);
            }
        }
        $previewBusinessLabel = trim((string) ($shareSellerBusinessName ?? ''));
        if ($previewBusinessLabel === '') {
            $previewBusinessLabel = 'Business';
        }

        if ($showManageMenu) {
    ?>
    <div class="card-options dropdown">
        <img src="/assets/images/icons/Dots icons.png" class="img-fluid options-icons dots-icon manage-listing-options-trigger" role="button" tabindex="0" aria-expanded="false" alt="Listing options">
        <ul class="dropdown-content"
            style="width: 120px; height: auto; top: 80px;left: -90px; padding: 10px; color: black;"
            aria-labelledby="dotsoptions">
            <?php if ($showProfilePinOption) { ?>
            <li class="li-1">
                <a class="dropdown-item manage-listing-pin" href="#" data-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>" data-pin-action="<?php echo $isProfilePinned ? 'unpin' : 'pin'; ?>"><?php echo $isProfilePinned ? 'Unpin' : 'Pin'; ?></a>
            </li>
            <?php } ?>
            <?php if (!$isListingHidden) { ?>
            <li class="li-1">
                <a class="dropdown-item manage-listing-share" href="#" data-share-url="/purchasewholesale.html?<?php echo htmlspecialchars($shareParams); ?>">Share</a>
            </li>
            <?php } ?>
            <li class="li-1">
                <a class="dropdown-item manage-listing-delete" href="#" data-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>">Delete</a>
            </li>
        </ul>
    </div>
    <?php
        }

        if ($isProfilePinned) {
    ?>
    <span class="profile-pinned-badge">Pinned</span>
    <?php
        }

        if ($ownerHiddenShowroomCard) {
    ?>
    <span class="owner-hidden-badge">Hidden</span>
    <?php
        }

        if ($type == 'video') {
        ?>
        <div class="showroom-media-frame">
        <div class="video-wrapper">
            <video class="video-content media-preview-source" controls autoplay muted data-preview-type="video" data-preview-src="<?php echo htmlspecialchars($mediaPath); ?>" data-preview-title="<?php echo htmlspecialchars($previewTitle); ?>" data-preview-description="<?php echo htmlspecialchars($previewDescription); ?>" data-preview-price="<?php echo htmlspecialchars($previewPrice); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>" data-preview-business="<?php echo htmlspecialchars($previewBusinessLabel); ?>" data-preview-posted="<?php echo htmlspecialchars($previewPostedLabel); ?>" data-purchase-url="<?php echo htmlspecialchars($purchaseUrl); ?>">
                <source src="<?php echo htmlspecialchars($mediaPath); ?>" >
            </video>
        </div>
        <div class="card-views">
            <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
            <p data-listing-view-label="<?php echo (int) ($listing['listing_id'] ?? 0); ?>"><?php echo htmlspecialchars($viewsLabel); ?></p>
        </div>
        </div>
    <?php
        } else {
    ?>
    <div class="showroom-media-frame">
    <img src="<?php echo htmlspecialchars($mediaPath); ?>" class="card-img-showroom img-fluid media-preview-source" alt="<?php echo htmlspecialchars((string) ($listing['stockname'] ?? '')); ?>" data-preview-type="image" data-preview-src="<?php echo htmlspecialchars($mediaPath); ?>" data-preview-title="<?php echo htmlspecialchars($previewTitle); ?>" data-preview-description="<?php echo htmlspecialchars($previewDescription); ?>" data-preview-price="<?php echo htmlspecialchars($previewPrice); ?>" data-preview-listing-id="<?php echo (int) ($listing['listing_id'] ?? 0); ?>" data-preview-business="<?php echo htmlspecialchars($previewBusinessLabel); ?>" data-preview-posted="<?php echo htmlspecialchars($previewPostedLabel); ?>" data-purchase-url="<?php echo htmlspecialchars($purchaseUrl); ?>">
    <div class="card-views">
        <img src="/assets/images/icons/View icon white.png" class="img-fluid view-icon">
        <p data-listing-view-label="<?php echo (int) ($listing['listing_id'] ?? 0); ?>"><?php echo htmlspecialchars($viewsLabel); ?></p>
    </div>
    </div>
    <?php } ?>
</div>
