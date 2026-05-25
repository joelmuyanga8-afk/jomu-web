<?php
session_start();
require_once __DIR__ . '/partials/helpers.php';

$error = $_GET['error'] ?? null;
$priceError = $_GET['price_error'] ?? null;
$oldCategory = $_GET['old_category'] ?? '';
$oldStockName = $_GET['old_stockname'] ?? '';
$oldDescription = $_GET['old_description'] ?? '';
$oldPrice = $_GET['old_price'] ?? '';
$oldPriceFrom = $_GET['old_price_from'] ?? '';
$oldPriceTo = $_GET['old_price_to'] ?? '';
$oldListingType = $_GET['old_listing_type'] ?? '';
$oldRegion = $_GET['old_region'] ?? '';
$oldCityTown = $_GET['old_city_town'] ?? '';
$oldHashtags = $_GET['old_hashtags'] ?? '';
$oldMediaType = $_GET['old_media_type'] ?? '';
$descriptionMaxLengthDefault = 400;
$descriptionMaxLengthVideo = 400;

function parseIniSizeToBytes($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;

    switch ($unit) {
        case 'g':
            return (int) ($number * 1024 * 1024 * 1024);
        case 'm':
            return (int) ($number * 1024 * 1024);
        case 'k':
            return (int) ($number * 1024);
        default:
            return (int) $number;
    }
}

$uploadMaxBytes = parseIniSizeToBytes(ini_get('upload_max_filesize'));
$postMaxBytes = parseIniSizeToBytes(ini_get('post_max_size'));
$serverUploadLimitBytes = min(array_filter([$uploadMaxBytes, $postMaxBytes])) ?: 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Listing</title>
    <link rel="stylesheet" href="/assets/bootstrap.css">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        #listing-form .form-floating,
        #listing-form .form-control,
        #listing-form .form-select {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        #listing-form .form-floating {
            min-width: 0;
        }

        @media (max-width: 576px) {
            #listing-form {
                width: 100%;
                max-width: 100%;
            }

            #listing-form .form-control,
            #listing-form .form-select {
                font-size: 16px;
                width: 100% !important;
                max-width: 100% !important;
            }
        }

        .jomu-dropdown {
            position: relative;
            width: 100%;
        }

        .jomu-dropdown-list {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1200;
            max-height: 220px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            display: none;
        }

        #category-list {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }

        .jomu-dropdown-list.show {
            display: block;
        }

        .jomu-dropdown-item {
            width: 100%;
            border: 0;
            background: #fff;
            text-align: left;
            padding: 10px 12px;
            line-height: 1.3;
            white-space: normal;
            word-break: break-word;
        }

        .jomu-dropdown-item:hover {
            background: #f8f9fa;
        }

        @media (max-width: 576px) {
            #category-list {
                max-height: calc(100dvh - 120px);
            }
        }

        .listing-extra-images-note {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .listing-preview-wrap {
            display: flex;
            align-items: stretch;
            gap: 10px;
        }

        .listing-preview-main {
            width: 100%;
            max-width: 220px;
            flex: 0 0 220px;
        }

        #avatar-container {
            overflow: hidden;
        }

        #avatar-container.is-empty {
            min-height: 0;
            height: 220px;
        }

        #avatar-container.is-empty #avatar-image {
            height: 156px;
            object-fit: contain;
        }

        #avatar-container.is-empty > .card.text-center {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #avatar-container.is-empty > .card.text-center label {
            font-size: 1.05rem !important;
            margin: 0;
        }

        .listing-extra-images-strip {
            display: flex;
            flex-direction: column;
            flex-wrap: wrap;
            align-content: flex-start;
            gap: 8px;
            margin-top: 0;
            height: auto;
            max-height: none;
            overflow-x: auto;
            overflow-y: hidden;
            flex: 0 0 auto;
        }

        .listing-extra-images-strip img {
            width: 88px;
            height: 88px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(241, 90, 36, 0.35);
            cursor: pointer;
        }

        .listing-extra-images-strip img.is-active {
            border-color: rgb(241, 90, 36);
            box-shadow: 0 0 0 2px rgba(241, 90, 36, 0.18);
        }

        @media (max-width: 576px) {
            .listing-preview-wrap {
                align-items: flex-start;
                gap: 8px;
            }

            .listing-preview-main {
                max-width: 180px;
                flex: 0 0 180px;
            }

            .listing-extra-images-strip {
                flex: 0 0 auto;
            }

            .listing-extra-images-strip img {
                width: 64px;
                height: 64px;
            }

            #avatar-container.is-empty #avatar-image {
                height: 140px;
                object-fit: contain;
            }

            #avatar-container.is-empty > .card.text-center {
                height: 60px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #avatar-container.is-empty > .card.text-center label {
                font-size: 1rem !important;
                margin: 0;
            }
        }
    </style>
</head>
<body class="bg-white" style="padding-top: 68px;">
    <header>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top navbarone navbar-help bg-dark" id="navbarone">
            <div class="container-fluid">
                <a class="navbar-brand brand-logos" href="/index.php">
                    <img src="/assets/images/JoMu black and white.png" class="img-fluid logo">
                    <img src="/assets/images/JoMu logo redesigned.png" class="img-fluid logo logo-hover">
                </a>
                <button class="button button-createaccount"
                    onclick="location.href='businessvendordashboard.php'">Dashboard</button>
            </div>
            </div>
        </nav>
    </header>
    <main>
        <div class="">
            <h3 class="text-center py-2"
                style="background-color: rgb(241, 90, 36); color: white; position: sticky; top: 65px; z-index: 10;">
                Add a Listing
            </h3>
            <hr>
        </div>
        <div class="container mt-2">
            <?php if ($error): ?>
                <div class="alert alert-warning" id="upload-message"><?php echo htmlspecialchars($error); ?></div>
            <?php else: ?>
                <div class="alert alert-warning d-none" id="upload-message"></div>
            <?php endif; ?>

            <form action="newlisting.php" method="POST" enctype="multipart/form-data" id="listing-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jomu_csrf_token()); ?>">
                <div class="row g-2">
                    <div class="col-12 col-lg-6">
                        <h5 class="mb-1 mt-2">Listing Type*</h5>
                        <div class="form-floating jomu-dropdown">
                            <input type="text" id="listing_type" class="form-control" placeholder="Choose Listing Type" value="<?php echo htmlspecialchars($oldListingType === 'service' ? 'Service' : ($oldListingType === 'product' ? 'Product' : '')); ?>" autocomplete="off" required>
                            <input type="hidden" name="listing_type" id="listing_type_value" value="<?php echo htmlspecialchars($oldListingType); ?>">
                            <label for="listing_type">Choose Listing Type</label>
                            <div id="listing-type-list" class="jomu-dropdown-list"></div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <h5 class="mb-1 mt-2">Category*</h5>
                        <div class="form-floating jomu-dropdown">
                            <input type="text" name="category" id="category" class="form-control" placeholder="Choose Category" value="<?php echo htmlspecialchars($oldCategory); ?>" autocomplete="off" required>
                            <label for="category">Choose Category</label>
                            <div id="category-list" class="jomu-dropdown-list"></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-lg-3 mt-2 mb-2">
                    <input type="file" id="file-input" name="media_files[]" accept="image/*,video/*" multiple style="display: none;" />
                    <div class="listing-preview-wrap">
                        <div class="listing-preview-main">
                            <div class="card h-100 is-empty" style="border:1px solid rgb(241, 90, 36);" id="avatar-container">
                              <img src="/assets/images/icons/Add listing icon.png" class="card-img-top img-fluid" id="avatar-image"
                                    style="opacity: 0.4; cursor:pointer;" alt="image">
                                <div class="card text-center" style="border:1px solid rgb(241, 90, 36);">
                                    <label for="media"  style="font-size: x-large; cursor:pointer;">Upload Image/Video </label>
                                </div>
                            </div>
                        </div>
                        <div id="extra-images-preview" class="listing-extra-images-strip" style="display: none;"></div>
                    </div>
                </div>
                <p id="listing-extra-images-note" class="listing-extra-images-note mb-2" style="display: none;">
                    <!-- Note that the first selected image becomes the main image. You can optionally add up to 5 more images. -->
                    Note that the first selected image becomes the main image. You can optionally add up to 6 images.
                </p>
                <h5 class="mb-0 mt-2" id="listing-name-heading">Listing Name *</h5>
                <div class="form-floating">
                    <input type="text" id="stockname" name="stockname" class="form-control" placeholder="Stock / Service name"
                        value="<?php echo htmlspecialchars($oldStockName); ?>"
                        required>
                    <label for="stockname" id="listing-name-label">Enter Listing Name</label>
                </div>
                <h5 class="mb-0 mt-2">Description*</h5>
                <div class="form-floating">
                    <input type="text" id="description" name="description" class="form-control" placeholder="description" value="<?php echo htmlspecialchars($oldDescription); ?>" maxlength="<?php echo (int) $descriptionMaxLengthDefault; ?>" required>
                    <label for="description">Enter Description</label>
                </div>
                <small id="description-limit-hint" class="text-danger" style="display: none;">
                    Description is too long. Maximum is <?php echo (int) $descriptionMaxLengthDefault; ?> characters.
                </small>
                <div class="row g-2">
                    <div class="col-12 col-lg-6">
                        <h5 class="mb-1 mt-2">Region*</h5>
                        <div class="form-floating jomu-dropdown">
                            <input type="text" id="region" class="form-control" placeholder="Choose Region" value="<?php echo htmlspecialchars($oldRegion); ?>" autocomplete="off" required>
                            <input type="hidden" name="region" id="region_value" value="<?php echo htmlspecialchars($oldRegion); ?>">
                            <label for="region">Choose Region</label>
                            <div id="region-list" class="jomu-dropdown-list"></div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <h5 class="mb-0 mt-2">City/Town*</h5>
                        <div class="form-floating">
                            <input type="text" id="city_town" name="city_town" class="form-control" placeholder="City or Town"
                                value="<?php echo htmlspecialchars($oldCityTown); ?>" required>
                            <label for="city_town">Enter City/Town</label>
                        </div>
                    </div>
                </div>
                <div id="hashtags-section" style="display: none;">
                    <h5 class="mb-0 mt-2">Hashtags</h5>
                    <div>
                        <input type="text" id="hashtags" name="hashtags" class="form-control" placeholder="Enter hashtags (e.g. #Therapist #Wholesale)" value="<?php echo htmlspecialchars($oldHashtags); ?>" maxlength="220">
                    </div>
                </div>
                <div id="product-price-range-section">
                    <h5 class="mb-1 mt-2">Wholesale Unit Price Range*</h5>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="text" id="price_from" name="price_from" class="form-control<?php echo $priceError ? ' is-invalid' : ''; ?>" placeholder="Initial Unit Price" value="<?php echo htmlspecialchars($oldPriceFrom); ?>" aria-describedby="price-error">
                                <label for="price_from">Initial Unit Price (USh)</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="text" id="price_to" name="price_to" class="form-control<?php echo $priceError ? ' is-invalid' : ''; ?>" placeholder="Highest Unit Price" value="<?php echo htmlspecialchars($oldPriceTo); ?>" aria-describedby="price-error">
                                <label for="price_to">Highest Unit Price (USh)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="service-price-section" style="display: none;">
                    <h5 class="mb-1 mt-2">Charge</h5>
                    <div class="form-floating">
                        <input type="text" id="price" name="price" class="form-control<?php echo $priceError ? ' is-invalid' : ''; ?>" placeholder="charge" value="<?php echo htmlspecialchars($oldPrice); ?>" aria-describedby="price-error">
                        <label for="price">Enter Charge (Optional)</label>
                    </div>
                </div>
                <div id="price-error" class="invalid-feedback<?php echo $priceError ? ' d-block' : ''; ?>"><?php echo $priceError ? htmlspecialchars($priceError) : ''; ?></div>
                <button type="submit" class="btn w-100 signin-button mt-2 mb-2">Post</button>
            </form>


            <!-- <div id="mediaPreview"></div> -->


        </div>
    </main>
    <footer class=" footer-feedback py-2 text-center bg-white">
        <div class="footer-links">
            <a href="/termsandconditions.html">Terms of Use</a>
            <a href="/privacypolicy.html">Privacy Policy</a>
            <a href="/help.html">Help</a>
            <a href="/support.html">Support</a>
            <a href="/feedback.html">Give Feedback</a>
            <a href="/about.html">About JoMu</a>
        </div>
        <!-- <br> -->
        <small>&copy; 2026 JoMu. All rights reserved.</small>
    </footer>
    <script>
        // Show image / video after upload functionality. 
        const form = document.getElementById('listing-form');
        const fileInput = document.getElementById('file-input');
        const avatarContainer = document.getElementById('avatar-container');
        const defaultAvatarMarkup = avatarContainer.innerHTML;
        const uploadMessage = document.getElementById('upload-message');
        const priceInput = document.getElementById('price');
        const priceFromInput = document.getElementById('price_from');
        const priceToInput = document.getElementById('price_to');
        const productPriceRangeSection = document.getElementById('product-price-range-section');
        const servicePriceSection = document.getElementById('service-price-section');
        const priceError = document.getElementById('price-error');
        const hashtagsSection = document.getElementById('hashtags-section');
        const hashtagsInput = document.getElementById('hashtags');
        const descriptionInput = document.getElementById('description');
        const descriptionLimitHint = document.getElementById('description-limit-hint');
        const listingTypeInput = document.getElementById('listing_type');
        const listingTypeValueInput = document.getElementById('listing_type_value');
        const listingTypeList = document.getElementById('listing-type-list');
        const stockNameInput = document.getElementById('stockname');
        const listingNameHeading = document.getElementById('listing-name-heading');
        const listingNameLabel = document.getElementById('listing-name-label');
        const categoryInput = document.getElementById('category');
        const categoryList = document.getElementById('category-list');
        const regionInput = document.getElementById('region');
        const regionValueInput = document.getElementById('region_value');
        const regionList = document.getElementById('region-list');
        const listingExtraImagesNote = document.getElementById('listing-extra-images-note');
        const extraImagesPreview = document.getElementById('extra-images-preview');
        const serverUploadLimitBytes = <?php echo (int) $serverUploadLimitBytes; ?>;
        const DESCRIPTION_MAX_DEFAULT = <?php echo (int) $descriptionMaxLengthDefault; ?>;
        const DESCRIPTION_MAX_VIDEO = <?php echo (int) $descriptionMaxLengthVideo; ?>;
        const MAX_EXTRA_IMAGES = 5;
        const listingTypeOptions = [
            { value: 'product', label: 'Product' },
            { value: 'service', label: 'Service' }
        ];
        const sampleCategories = {
            product: [
                'Apparel',
                'Wholesale & Retail',
                'Electronics & Gadgets',
                'Agriculture & Produce',
                'Livestock & Animals',
                'Construction & Building Materials',
                'Office Supplies & Stationery',
                'Healthy & Beauty',
                'Food & Beverages',
                'Automative & Transport',
                'Furniture & Home Decor',
                'IT & Software Accessories',
                'Printing & Branding'
            ],
            service: [
                'Cleaning & Laundry',
                'Catering Services',
                'Airport Pickup & Car Hire',
                'Event Planning & Decoration',
                'Rental & Utility',
                'Photography & Videography',
                'Agribusiness & Support',
                'Beauty, Wellness & Personal Care',
                'Education & Skill-Based',
                'Tour & Travel',
                'Security Services',
                'Content Creation & Influencer',
                'Transport',
                'Digital & ICT',
                'Logistics & Delivery',
                'Real Estate & Property'
            ]
        };
        const regionOptions = [
            { value: 'Central', label: 'Central' },
            { value: 'Eastern', label: 'Eastern' },
            { value: 'Western', label: 'Western' },
            { value: 'Northern', label: 'Northern' }
        ];
        let selectedMediaType = '';
        let selectedImageFiles = [];

        function formatBytesToMb(bytes) {
            return (bytes / (1024 * 1024)).toFixed(2);
        }

        function showUploadMessage(message) {
            uploadMessage.textContent = message;
            uploadMessage.classList.remove('d-none');
        }

        function clearUploadMessage() {
            uploadMessage.textContent = '';
            uploadMessage.classList.add('d-none');
        }

        function updateExtraImagesNoteVisibility() {
            if (!listingExtraImagesNote) return;
            listingExtraImagesNote.style.display = selectedMediaType === 'image' ? '' : 'none';
        }

        function showPriceError(message) {
            priceInput.classList.add('is-invalid');
            priceError.textContent = message;
            priceError.classList.add('d-block');
        }

        function clearPriceError() {
            if (priceInput) priceInput.classList.remove('is-invalid');
            if (priceFromInput) priceFromInput.classList.remove('is-invalid');
            if (priceToInput) priceToInput.classList.remove('is-invalid');
            priceError.textContent = '';
            priceError.classList.remove('d-block');
        }

        function isNumericPrice(value) {
            const trimmed = value.trim();
            if (trimmed === '') return false;
            const commaNumberPattern = /^(\d+|\d{1,3}(,\d{3})+)(\.\d+)?$/;
            return commaNumberPattern.test(trimmed);
        }

        function updatePriceInputsByType() {
            const selectedType = normalizeListingType(listingTypeValueInput.value || listingTypeInput.value);
            const isProduct = selectedType === 'product';
            const isVideo = selectedMediaType === 'video';

            productPriceRangeSection.style.display = isProduct ? '' : 'none';
            servicePriceSection.style.display = (isProduct || isVideo) ? 'none' : '';

            if (isProduct) {
                priceFromInput.required = true;
                priceToInput.required = true;
                if (priceInput) priceInput.required = false;
            } else {
                priceFromInput.required = false;
                priceToInput.required = false;
                if (priceInput) priceInput.required = false;
            }

            if (isVideo && priceInput) {
                priceInput.value = '';
            }
        }

        function updateHashtagsVisibility() {
            const shouldShow = selectedMediaType === 'video';
            hashtagsSection.style.display = shouldShow ? '' : 'none';
            hashtagsInput.required = shouldShow;
            if (!shouldShow) {
                hashtagsInput.value = '';
            }
        }

        function updateDescriptionLimitByMediaType() {
            const isVideo = selectedMediaType === 'video';
            descriptionInput.maxLength = DESCRIPTION_MAX_DEFAULT;
            if (!isVideo) {
                descriptionInput.classList.remove('is-invalid');
                descriptionLimitHint.style.display = 'none';
                return;
            }

            const isExceeded = descriptionInput.value.length > DESCRIPTION_MAX_VIDEO;
            descriptionInput.classList.toggle('is-invalid', isExceeded);
            descriptionLimitHint.style.display = isExceeded ? 'inline' : 'none';
        }

        function normalizeListingType(value) {
            const rawType = (value || '').trim().toLowerCase();
            if (rawType === 'service' || rawType === 'services') return 'service';
            if (rawType === 'product' || rawType === 'products') return 'product';
            return '';
        }

        function normalizeRegion(value) {
            const rawRegion = (value || '').trim().toLowerCase();
            const match = regionOptions.find((item) => item.value.toLowerCase() === rawRegion || item.label.toLowerCase() === rawRegion);
            return match ? match.value : '';
        }

        function setListingType(type) {
            const normalized = normalizeListingType(type);
            listingTypeValueInput.value = normalized;
            listingTypeInput.value = normalized === 'service' ? 'Service' : normalized === 'product' ? 'Product' : listingTypeInput.value;
            renderCategoryOptions();
            updateListingNameField();
            updatePriceInputsByType();
        }

        function updateListingNameField() {
            const selectedType = normalizeListingType(listingTypeValueInput.value || listingTypeInput.value);
            const isService = selectedType === 'service';
            const headingText = isService ? 'Service Name *' : selectedType === 'product' ? 'Stock Name *' : 'Listing Name *';
            const labelText = isService ? 'Enter Service Name' : selectedType === 'product' ? 'Enter Stock Name' : 'Enter Listing Name';
            const placeholderText = isService ? 'Service name' : selectedType === 'product' ? 'Stock name' : 'Listing name';

            listingNameHeading.textContent = headingText;
            listingNameLabel.textContent = labelText;
            stockNameInput.placeholder = placeholderText;
        }

        function renderList(container, items, onSelect) {
            container.innerHTML = '';
            if (!items.length) {
                container.classList.remove('show');
                return;
            }

            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'jomu-dropdown-item';
                button.textContent = item.label ?? item;
                // Capture selection before input blur hides the list (mobile-safe).
                button.addEventListener('pointerdown', (event) => {
                    event.preventDefault();
                    onSelect(item);
                });
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    onSelect(item);
                });
                container.appendChild(button);
            });

            container.classList.add('show');
            if (container === categoryList) {
                updateCategoryListViewportHeight();
            }
        }

        function updateCategoryListViewportHeight() {
            if (!categoryList || !categoryList.classList.contains('show')) {
                return;
            }

            const listRect = categoryList.getBoundingClientRect();
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            const availableHeight = Math.max(160, Math.floor(viewportHeight - listRect.top - 12));
            categoryList.style.maxHeight = `${availableHeight}px`;
        }

        function renderListingTypeOptions() {
            const query = listingTypeInput.value.trim().toLowerCase();
            const filtered = listingTypeOptions.filter((item) => item.label.toLowerCase().includes(query));
            renderList(listingTypeList, filtered, (item) => {
                listingTypeInput.value = item.label;
                listingTypeValueInput.value = item.value;
                listingTypeList.classList.remove('show');
                renderCategoryOptions();
                updateListingNameField();
                updatePriceInputsByType();
            });
        }

        function renderCategoryOptions() {
            const selectedType = normalizeListingType(listingTypeValueInput.value || listingTypeInput.value);
            const options = selectedType ? (sampleCategories[selectedType] || []) : [];
            const query = categoryInput.value.trim().toLowerCase();
            const filtered = options
                .filter((item) => item.toLowerCase().includes(query))
                .map((item) => ({ label: item }));

            renderList(categoryList, filtered, (item) => {
                categoryInput.value = item.label;
                categoryList.classList.remove('show');
            });
        }

        function setRegion(value) {
            const normalized = normalizeRegion(value);
            regionValueInput.value = normalized;
            regionInput.value = normalized || regionInput.value;
        }

        function renderRegionOptions() {
            const query = regionInput.value.trim().toLowerCase();
            const filtered = regionOptions.filter((item) => item.label.toLowerCase().includes(query));
            renderList(regionList, filtered, (item) => {
                regionInput.value = item.label;
                regionValueInput.value = item.value;
                regionList.classList.remove('show');
            });
        }

        function normalizePriceValue(value) {
            return value.replace(/,/g, '').trim();
        }

        function isPriceValid(value) {
            const trimmed = value.trim();
            if (trimmed === '') {
                return true;
            }

            const commaNumberPattern = /^(\d+|\d{1,3}(,\d{3})+)(\.\d+)?$/;
            return commaNumberPattern.test(trimmed);
        }

        avatarContainer.addEventListener('click',()=> {
            fileInput.click();
        });

        function resetMediaPreviewCard() {
            avatarContainer.innerHTML = defaultAvatarMarkup;
            avatarContainer.classList.add('is-empty');
        }

        function resetExtraImagesPreview() {
            extraImagesPreview.innerHTML = '';
            extraImagesPreview.style.display = 'none';
            extraImagesPreview.style.height = '';
            extraImagesPreview.style.maxHeight = '';
        }

        function updateFileInputOrder(files) {
            if (!Array.isArray(files) || typeof DataTransfer === 'undefined') {
                return;
            }

            const dataTransfer = new DataTransfer();
            files.forEach((selectedFile) => {
                dataTransfer.items.add(selectedFile);
            });
            fileInput.files = dataTransfer.files;
        }

        function renderSelectedMainImage(file) {
            avatarContainer.innerHTML = '';
            avatarContainer.classList.remove('is-empty');

            const img = document.createElement('img');
            img.style.display = 'none';
            img.style.opacity = 1;
            img.alt = '';
            avatarContainer.appendChild(img);

            const reader = new FileReader();
            reader.onload = (event) => {
                img.src = event.target.result;
                img.style.display = '';
                requestAnimationFrame(syncExtraImagesPreviewHeight);
            };
            reader.readAsDataURL(file);
        }

        function renderSelectedImageThumbnails(files) {
            resetExtraImagesPreview();

            if (!Array.isArray(files) || files.length <= 1) {
                requestAnimationFrame(syncExtraImagesPreviewHeight);
                return;
            }

            files.slice(1).forEach((extraFile) => {
                const thumb = document.createElement('img');
                thumb.alt = 'Additional listing image';
                thumb.dataset.fileName = extraFile.name;
                thumb.addEventListener('click', () => {
                    const selectedIndex = selectedImageFiles.findIndex((selectedFile) => selectedFile === extraFile);
                    if (selectedIndex <= 0) {
                        return;
                    }

                    const nextFiles = [...selectedImageFiles];
                    const [nextMainFile] = nextFiles.splice(selectedIndex, 1);
                    nextFiles.unshift(nextMainFile);
                    selectedImageFiles = nextFiles;
                    updateFileInputOrder(selectedImageFiles);
                    renderImageSelectionPreview();
                });

                const extraReader = new FileReader();
                extraReader.onload = (extraEvent) => {
                    thumb.src = extraEvent.target.result;
                    extraImagesPreview.appendChild(thumb);
                    extraImagesPreview.style.display = '';
                    syncExtraImagesPreviewHeight();
                };
                extraReader.readAsDataURL(extraFile);
            });
        }

        function renderImageSelectionPreview() {
            if (!Array.isArray(selectedImageFiles) || selectedImageFiles.length === 0) {
                resetExtraImagesPreview();
                resetMediaPreviewCard();
                return;
            }

            renderSelectedMainImage(selectedImageFiles[0]);
            renderSelectedImageThumbnails(selectedImageFiles);
        }

        function syncExtraImagesPreviewHeight() {
            if (!extraImagesPreview || extraImagesPreview.style.display === 'none') {
                return;
            }

            const mainPreviewHeight = avatarContainer.offsetHeight;
            if (mainPreviewHeight > 0) {
                extraImagesPreview.style.height = `${mainPreviewHeight}px`;
                extraImagesPreview.style.maxHeight = `${mainPreviewHeight}px`;
            }
        }

        fileInput.addEventListener('change', (e)=> {
            const files = Array.from(e.target.files || []);
            const file = files[0];
            if (!file) {
                selectedMediaType = '';
                selectedImageFiles = [];
                updateHashtagsVisibility();
                updateDescriptionLimitByMediaType();
                updateExtraImagesNoteVisibility();
                resetExtraImagesPreview();
                resetMediaPreviewCard();
                return;
            }

            const firstFileType = file.type.split('/')[0];
            const hasMixedMediaTypes = files.some((selectedFile) => selectedFile.type.split('/')[0] !== firstFileType);
            if (hasMixedMediaTypes) {
                showUploadMessage('Please select either images only or one video only.');
                fileInput.value = '';
                selectedMediaType = '';
                selectedImageFiles = [];
                updateExtraImagesNoteVisibility();
                resetExtraImagesPreview();
                resetMediaPreviewCard();
                return;
            }

            if (firstFileType === 'video' && files.length > 1) {
                showUploadMessage('Video listings can only have one media file.');
                fileInput.value = '';
                selectedMediaType = '';
                selectedImageFiles = [];
                updateExtraImagesNoteVisibility();
                resetExtraImagesPreview();
                resetMediaPreviewCard();
                return;
            }

            if (firstFileType === 'image' && files.length > MAX_EXTRA_IMAGES + 1) {
                showUploadMessage('You can select up to 6 images in total: 1 main image and 5 extra images.');
                fileInput.value = '';
                selectedMediaType = '';
                selectedImageFiles = [];
                updateExtraImagesNoteVisibility();
                resetExtraImagesPreview();
                resetMediaPreviewCard();
                return;
            }

            const oversizeFile = files.find((selectedFile) => serverUploadLimitBytes > 0 && selectedFile.size > serverUploadLimitBytes);
            if (oversizeFile) {
                showUploadMessage(`This file is ${formatBytesToMb(oversizeFile.size)} MB. The current server limit is ${formatBytesToMb(serverUploadLimitBytes)} MB, so please compress it before uploading.`);
                return;
            }

            clearUploadMessage();
            const reader = new FileReader();

            reader.onload = (event) => {
                const fileType = firstFileType;
                selectedMediaType = fileType === 'video' ? 'video' : 'image';
                updateHashtagsVisibility();
                updateDescriptionLimitByMediaType();
                updateExtraImagesNoteVisibility();
                updatePriceInputsByType();
                resetExtraImagesPreview();

                avatarContainer.innerHTML = "";
                avatarContainer.classList.remove('is-empty');

                if (fileType === 'image') {
                    selectedImageFiles = [...files];
                    updateFileInputOrder(selectedImageFiles);
                    renderImageSelectionPreview();
                } else if (fileType === 'video') {
                    selectedImageFiles = [];
                    const video = document.createElement('video');
                    video.src = event.target.result;
                    video.style.opacity = 1;
                    video.controls = true;
                    avatarContainer.appendChild(video);
                }
            };

            reader.readAsDataURL(file);
            
        });

        form.addEventListener('submit', (e) => {
            const normalizedType = normalizeListingType(listingTypeValueInput.value || listingTypeInput.value);
            if (!normalizedType) {
                e.preventDefault();
                listingTypeInput.setCustomValidity('Please choose listing type.');
                listingTypeInput.reportValidity();
                return;
            }

            listingTypeInput.setCustomValidity('');
            listingTypeValueInput.value = normalizedType;
            const normalizedRegion = normalizeRegion(regionValueInput.value || regionInput.value);
            if (!normalizedRegion) {
                e.preventDefault();
                regionInput.setCustomValidity('Please choose region.');
                regionInput.reportValidity();
                return;
            }
            regionInput.setCustomValidity('');
            regionValueInput.value = normalizedRegion;

            if (normalizedType === 'product') {
                if (!isNumericPrice(priceFromInput.value) || !isNumericPrice(priceToInput.value)) {
                    e.preventDefault();
                    showPriceError('Initial and highest unit price must be numeric.');
                    return;
                }

                const fromValue = parseFloat(normalizePriceValue(priceFromInput.value));
                const toValue = parseFloat(normalizePriceValue(priceToInput.value));
                if (fromValue > toValue) {
                    e.preventDefault();
                    showPriceError('Highest unit price must be greater than or equal to initial price.');
                    return;
                }

                clearPriceError();
                priceFromInput.value = normalizePriceValue(priceFromInput.value);
                priceToInput.value = normalizePriceValue(priceToInput.value);
                if (priceInput) priceInput.value = '';
            } else if (priceInput && priceInput.value.trim() !== '') {
                if (!isPriceValid(priceInput.value)) {
                    e.preventDefault();
                    showPriceError('Charge must be a number.');
                    return;
                }
                priceInput.value = normalizePriceValue(priceInput.value);
            }
            clearPriceError();

            if (selectedMediaType === 'video' && descriptionInput.value.length > DESCRIPTION_MAX_VIDEO) {
                e.preventDefault();
                descriptionInput.classList.add('is-invalid');
                descriptionLimitHint.style.display = 'inline';
                return;
            }

            const files = Array.from(fileInput.files || []);
            const file = files[0];
            if (!file) {
                return;
            }

            if (selectedMediaType === 'video' && files.length > 1) {
                e.preventDefault();
                showUploadMessage('Video listings can only have one media file.');
                return;
            }

            if (selectedMediaType === 'image' && files.length > MAX_EXTRA_IMAGES + 1) {
                e.preventDefault();
                showUploadMessage('You can select up to 6 images in total: 1 main image and 5 extra images.');
                return;
            }

            const oversizeFile = files.find((selectedFile) => serverUploadLimitBytes > 0 && selectedFile.size > serverUploadLimitBytes);
            if (oversizeFile) {
                e.preventDefault();
                showUploadMessage(`This file is ${formatBytesToMb(oversizeFile.size)} MB. The current server limit is ${formatBytesToMb(serverUploadLimitBytes)} MB, so it cannot be uploaded yet.`);
            }
        });

        if (priceInput) {
            priceInput.addEventListener('input', () => {
                if (priceInput.value.trim() === '' || isPriceValid(priceInput.value)) {
                    clearPriceError();
                }
            });
        }

        descriptionInput.addEventListener('input', () => {
            updateDescriptionLimitByMediaType();
        });

        if (priceFromInput) {
            priceFromInput.addEventListener('input', () => {
                clearPriceError();
            });
        }

        if (priceToInput) {
            priceToInput.addEventListener('input', () => {
                clearPriceError();
            });
        }

        listingTypeInput.addEventListener('focus', renderListingTypeOptions);
        listingTypeInput.addEventListener('click', renderListingTypeOptions);
        listingTypeInput.addEventListener('input', () => {
            listingTypeValueInput.value = normalizeListingType(listingTypeInput.value);
            renderListingTypeOptions();
            updateListingNameField();
            renderCategoryOptions();
        });
        listingTypeInput.addEventListener('blur', () => {
            setTimeout(() => listingTypeList.classList.remove('show'), 200);
        });

        categoryInput.addEventListener('focus', renderCategoryOptions);
        categoryInput.addEventListener('click', renderCategoryOptions);
        categoryInput.addEventListener('input', renderCategoryOptions);
        categoryInput.addEventListener('blur', () => {
            setTimeout(() => categoryList.classList.remove('show'), 200);
        });

        regionInput.addEventListener('focus', renderRegionOptions);
        regionInput.addEventListener('click', renderRegionOptions);
        regionInput.addEventListener('input', () => {
            regionValueInput.value = normalizeRegion(regionInput.value);
            renderRegionOptions();
        });
        regionInput.addEventListener('blur', () => {
            setTimeout(() => regionList.classList.remove('show'), 200);
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.jomu-dropdown')) {
                listingTypeList.classList.remove('show');
                categoryList.classList.remove('show');
                regionList.classList.remove('show');
            }
        });

        window.addEventListener('resize', updateCategoryListViewportHeight);
        window.addEventListener('resize', syncExtraImagesPreviewHeight);

        if (listingTypeValueInput.value) {
            setListingType(listingTypeValueInput.value);
        }
        if (regionValueInput.value) {
            setRegion(regionValueInput.value);
        }
        updateListingNameField();
        updatePriceInputsByType();
        if (hashtagsInput.value.trim() !== '' || "<?php echo htmlspecialchars($oldMediaType); ?>" === 'video') {
            selectedMediaType = 'video';
        }
        updateExtraImagesNoteVisibility();
        updateHashtagsVisibility();
        updateDescriptionLimitByMediaType();
        updatePriceInputsByType();
    
    </script>
    <script src="/assets/bootstrap.bundle.min.js"></script>
    <script src="../assets/cookie-consent.js"></script>
</body>
</html>
