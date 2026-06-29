(function () {
    if (window.jomuListingPreviewModalLoaded) {
        return;
    }

    window.jomuListingPreviewModalLoaded = true;

    const previewTextSelector = '.listing-name-top, .listing-description, .listing-description-text, .listing-description-link, .product-price-range, .video-stock-title, .video-description-brief, .video-hashtags, .card-title, .card-text';
    const closeSelector = '.media-preview-close, .profile-media-preview-close, .visitor-media-preview-close, .dashboard-media-preview-close';
    const countedImageViews = new Set();
    const countedVideoViews = new Set();
    const pendingVideoViewTimers = new Map();
    let lastTapTime = 0;
    let lastTapSrc = '';
    let activeConfig = null;
    let adminBootstrapCache = null;

    function listingAdminActionUrl() {
        try {
            return new URL('/php/admin/listing_action.php', window.location.origin).href;
        } catch (error) {
            return '/php/admin/listing_action.php';
        }
    }

    function fetchAdminBootstrap() {
        if (adminBootstrapCache) {
            return Promise.resolve(adminBootstrapCache);
        }
        return fetch('/php/admin/listing_preview_bootstrap.php', { credentials: 'same-origin' })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                adminBootstrapCache = {
                    isAdmin: Boolean(data?.is_admin),
                    csrf: String(data?.csrf_token || ''),
                };
                return adminBootstrapCache;
            })
            .catch(() => {
                adminBootstrapCache = { isAdmin: false, csrf: '' };
                return adminBootstrapCache;
            });
    }

    function ensureAdminListingBar(elements) {
        const panel = elements.previewDetails?.parentElement;
        if (!panel) {
            return null;
        }
        let bar = panel.querySelector('.jomu-admin-listing-preview-bar');
        if (bar) {
            return bar;
        }
        bar = document.createElement('div');
        bar.className = 'jomu-admin-listing-preview-bar';
        const meta = document.createElement('p');
        meta.className = 'jomu-admin-listing-preview-meta';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger btn-sm jomu-admin-listing-hide-btn';
        btn.textContent = 'Hide listing';
        bar.appendChild(meta);
        bar.appendChild(btn);
        if (elements.previewDetails.nextSibling) {
            panel.insertBefore(bar, elements.previewDetails.nextSibling);
        } else {
            panel.appendChild(bar);
        }
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const rootBar = e.currentTarget.closest('.jomu-admin-listing-preview-bar');
            if (!rootBar) {
                return;
            }
            const listingId = Number.parseInt(rootBar.dataset.listingId || '', 10);
            const csrf = String(rootBar.dataset.csrfToken || '');
            if (!Number.isInteger(listingId) || listingId <= 0 || !csrf) {
                return;
            }
            openListingAdminConfirm(
                'Hide this listing? It will move to Hidden listings in the admin dashboard and the seller will be notified.',
                'Hide'
            ).then((confirmed) => {
                if (!confirmed) {
                    return;
                }
                const fd = new FormData();
                fd.append('csrf_token', csrf);
                fd.append('listing_id', String(listingId));
                fd.append('action', 'hide');
                fd.append('return_to', window.location.href);
                return fetch(listingAdminActionUrl(), {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                });
            })
                .then((response) => {
                    if (!response) {
                        return null;
                    }
                    return response.text().then((t) => ({ ok: response.ok, text: t }));
                })
                .then((result) => {
                    if (!result) {
                        return;
                    }
                    const { ok, text } = result;
                    let data = {};
                    try {
                        data = text ? JSON.parse(text) : {};
                    } catch (err) {
                        data = {};
                    }
                    if (!ok || data.ok === false) {
                        throw new Error(data.message || text || 'Action failed.');
                    }
                    removeListingFromPublicDom(listingId);
                    closePreview();
                })
                .catch((err) => {
                    openListingAdminConfirm(err?.message || String(err), 'OK');
                });
        });
        return bar;
    }

    function syncAdminListingPreviewBar(elements, sourceEl) {
        fetchAdminBootstrap().then((boot) => {
            const bar = ensureAdminListingBar(elements);
            if (!bar) {
                return;
            }
            if (!boot?.isAdmin || !boot.csrf) {
                bar.style.display = 'none';
                return;
            }
            const listingId = Number.parseInt(sourceEl?.dataset?.previewListingId || '', 10);
            if (!Number.isInteger(listingId) || listingId <= 0) {
                bar.style.display = 'none';
                return;
            }
            const biz = String(sourceEl?.dataset?.previewBusiness || '').trim() || 'Business';
            const posted = String(sourceEl?.dataset?.previewPosted || '').trim();
            const parts = [];
            if (biz) {
                parts.push(`Posted by: ${biz}`);
            }
            if (posted) {
                parts.push(`Posted: ${posted}`);
            }
            const meta = bar.querySelector('.jomu-admin-listing-preview-meta');
            if (meta) {
                meta.textContent = parts.join(' · ');
            }
            bar.dataset.listingId = String(listingId);
            bar.dataset.csrfToken = boot.csrf;
            bar.style.display = 'flex';
        });
    }

    const previewConfigs = [
        {
            overlayId: 'mediaPreviewOverlay',
            closeId: 'mediaPreviewClose',
            imageId: 'mediaPreviewImage',
            videoId: 'mediaPreviewVideo',
            detailsId: 'mediaPreviewDetails',
            titleId: 'mediaPreviewTitle',
            priceId: 'mediaPreviewPrice',
            descriptionId: 'mediaPreviewDescription',
            watermarkId: 'mediaPreviewWatermark',
            panelClass: 'media-preview-panel',
            contentClass: 'media-preview-content',
            detailsClass: 'media-preview-details',
            titleClass: 'media-preview-title',
            priceClass: 'media-preview-price',
            descriptionClass: 'media-preview-description',
            closeClass: 'media-preview-close',
            bodyClass: 'media-preview-open',
            createIfMissing: true
        },
        {
            overlayId: 'profileMediaPreviewOverlay',
            closeId: 'profileMediaPreviewClose',
            imageId: 'profileMediaPreviewImage',
            videoId: 'profileMediaPreviewVideo',
            detailsId: 'profileMediaPreviewDetails',
            titleId: 'profileMediaPreviewTitle',
            priceId: 'profileMediaPreviewPrice',
            descriptionId: 'profileMediaPreviewDescription',
            bodyClass: 'profile-preview-open'
        },
        {
            overlayId: 'visitorMediaPreviewOverlay',
            closeId: 'visitorMediaPreviewClose',
            imageId: 'visitorMediaPreviewImage',
            videoId: 'visitorMediaPreviewVideo',
            detailsId: 'visitorMediaPreviewDetails',
            titleId: 'visitorMediaPreviewTitle',
            priceId: 'visitorMediaPreviewPrice',
            descriptionId: 'visitorMediaPreviewDescription',
            purchaseId: 'visitorMediaPreviewPurchase',
            bodyClass: 'media-preview-open'
        },
        {
            overlayId: 'dashboardMediaPreviewOverlay',
            closeId: 'dashboardMediaPreviewClose',
            imageId: 'dashboardMediaPreviewImage',
            videoId: 'dashboardMediaPreviewVideo',
            detailsId: 'dashboardMediaPreviewDetails',
            titleId: 'dashboardMediaPreviewTitle',
            priceId: 'dashboardMediaPreviewPrice',
            descriptionId: 'dashboardMediaPreviewDescription',
            bodyClass: 'media-preview-open'
        }
    ];

    function ensurePreviewStyles() {
        if (document.getElementById('jomu-listing-preview-modal-style')) {
            return;
        }

        const styleEl = document.createElement('style');
        styleEl.id = 'jomu-listing-preview-modal-style';
        styleEl.textContent = `
            #mediaPreviewOverlay,
            #profileMediaPreviewOverlay,
            #visitorMediaPreviewOverlay,
            #dashboardMediaPreviewOverlay {
                position: fixed !important;
                inset: 0 !important;
                background: rgba(0, 0, 0, 0.92) !important;
                display: none;
                align-items: flex-start !important;
                justify-content: center !important;
                z-index: 9999 !important;
                padding: 8px !important;
                overflow-y: auto !important;
                overscroll-behavior: contain;
            }

            #mediaPreviewOverlay.active,
            #profileMediaPreviewOverlay.active,
            #visitorMediaPreviewOverlay.active,
            #dashboardMediaPreviewOverlay.active {
                display: flex !important;
            }

            body.media-preview-open,
            body.profile-preview-open {
                overflow: hidden;
            }

            .media-preview-panel,
            .profile-media-preview-panel,
            .visitor-media-preview-panel,
            .dashboard-media-preview-panel {
                width: 100% !important;
                max-width: none !important;
                min-height: calc(100svh - 16px);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .media-preview-content,
            .profile-media-preview-content,
            .visitor-media-preview-content,
            .dashboard-media-preview-content {
                max-width: 100% !important;
                max-height: 72vh !important;
                width: auto;
                height: auto;
                object-fit: contain;
                background: #000;
            }

            .media-preview-details,
            .profile-media-preview-details,
            .visitor-media-preview-details,
            .dashboard-media-preview-details {
                width: 100% !important;
                box-sizing: border-box;
                background: rgba(9, 9, 9, 0.82);
                color: #fff;
                border-radius: 6px !important;
                padding: 8px !important;
                text-align: left;
            }

            .media-preview-close,
            .profile-media-preview-close,
            .visitor-media-preview-close,
            .dashboard-media-preview-close {
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
                z-index: 10001;
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

            .jomu-admin-listing-preview-bar {
                width: 100%;
                box-sizing: border-box;
                background: rgba(9, 9, 9, 0.92);
                color: #fff;
                border-radius: 6px;
                padding: 10px 12px;
                text-align: left;
                z-index: 5;
                display: none;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
                justify-content: space-between;
            }

            .jomu-admin-listing-preview-meta {
                margin: 0;
                font-size: 0.85rem;
                line-height: 1.35;
                flex: 1 1 200px;
            }

            .jomu-listing-admin-confirm-overlay {
                position: fixed;
                inset: 0;
                z-index: 10050;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 16px;
                background: rgba(17, 17, 17, 0.52);
            }

            .jomu-listing-admin-confirm-overlay.active {
                display: flex;
            }

            .jomu-listing-admin-confirm-panel {
                width: min(420px, 100%);
                background: #fff;
                border-radius: 10px;
                border-top: 5px solid rgb(241, 90, 36);
                padding: 18px;
                box-shadow: 0 18px 50px rgba(0, 0, 0, 0.24);
            }

            .jomu-listing-admin-confirm-panel h3 {
                margin: 0 0 8px;
                font-size: 1.1rem;
                font-weight: 800;
            }

            .jomu-listing-admin-confirm-panel p {
                margin: 0 0 14px;
                color: #4b5563;
                line-height: 1.45;
            }

            .jomu-listing-admin-confirm-actions {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
            }
        `;
        document.head.appendChild(styleEl);
    }

    let listingAdminConfirmResolver = null;

    function ensureListingAdminConfirmOverlay() {
        let overlay = document.getElementById('jomuListingAdminConfirmOverlay');
        if (overlay) {
            return overlay;
        }
        overlay = document.createElement('div');
        overlay.id = 'jomuListingAdminConfirmOverlay';
        overlay.className = 'jomu-listing-admin-confirm-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <div class="jomu-listing-admin-confirm-panel" role="dialog" aria-modal="true">
                <h3>JoMu Admin</h3>
                <p id="jomuListingAdminConfirmMessage"></p>
                <div class="jomu-listing-admin-confirm-actions">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-confirm-cancel>Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" data-confirm-proceed>Proceed</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => closeListingAdminConfirm(false));
        overlay.querySelector('[data-confirm-proceed]')?.addEventListener('click', () => closeListingAdminConfirm(true));
        return overlay;
    }

    function openListingAdminConfirm(message, proceedLabel = 'Proceed') {
        return new Promise((resolve) => {
            const overlay = ensureListingAdminConfirmOverlay();
            const messageEl = overlay?.querySelector('#jomuListingAdminConfirmMessage');
            const proceedBtn = overlay?.querySelector('[data-confirm-proceed]');
            if (!overlay || !messageEl) {
                resolve(window.confirm(message));
                return;
            }
            messageEl.textContent = message;
            if (proceedBtn) {
                proceedBtn.textContent = proceedLabel;
            }
            listingAdminConfirmResolver = resolve;
            overlay.classList.add('active');
            overlay.setAttribute('aria-hidden', 'false');
        });
    }

    function closeListingAdminConfirm(result) {
        const overlay = document.getElementById('jomuListingAdminConfirmOverlay');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
        }
        if (listingAdminConfirmResolver) {
            listingAdminConfirmResolver(result);
            listingAdminConfirmResolver = null;
        }
    }

    let recentStorageKeyPromise = null;

    async function getRecentStorageKey() {
        if (recentStorageKeyPromise) {
            return recentStorageKeyPromise;
        }
        recentStorageKeyPromise = fetch('/php/auth_status.php', { credentials: 'same-origin' })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => (data?.signed_in && data?.user_key
                ? `jomuRecentlyViewedListings:${data.user_key}`
                : 'jomuRecentlyViewedListings:guest'))
            .catch(() => 'jomuRecentlyViewedListings:guest');
        return recentStorageKeyPromise;
    }

    function saveRecentlyViewedListing(item, storageKey) {
        if (!item || !item.listing_id) {
            return;
        }
        try {
            const raw = localStorage.getItem(storageKey);
            const currentItems = raw ? JSON.parse(raw) : [];
            const safeItems = Array.isArray(currentItems) ? currentItems : [];
            const nextItem = {
                listing_id: Number(item.listing_id) || 0,
                viewed_at: new Date().toISOString(),
                media_type: item.media_type === 'video' ? 'video' : 'image',
                html: String(item.html || '').trim(),
                media_src: String(item.media_src || '').trim(),
                title: String(item.title || '').trim(),
                description: String(item.description || '').trim(),
                price: String(item.price || '').trim(),
                preview_business: String(item.preview_business || '').trim(),
                preview_posted: String(item.preview_posted || '').trim(),
                action_label: String(item.action_label || 'Purchase Wholesale').trim(),
                purchase_url: String(item.purchase_url || '#').trim(),
                seller_name: String(item.seller_name || '').trim(),
                seller_profilepic: String(item.seller_profilepic || '').trim(),
            };
            const filteredItems = safeItems.filter((entry) => Number(entry?.listing_id || 0) !== nextItem.listing_id);
            filteredItems.unshift(nextItem);
            localStorage.setItem(storageKey, JSON.stringify(filteredItems.slice(0, 20)));
        } catch (error) {
            // Non-blocking recent-history update.
        }
    }

    function storeRecentlyViewedFromSource(sourceEl) {
        const listingId = Number.parseInt(sourceEl?.dataset?.previewListingId || '', 10);
        if (!Number.isInteger(listingId) || listingId <= 0) {
            return;
        }
        const cardEl = sourceEl.closest('.card');
        const columnEl = sourceEl.closest('.col-6.col-md-4.col-lg-3, .col-4.col-md-4.col-lg-3, .listing-card-item');
        const actionLink = cardEl?.querySelector('a[href*="/purchase-wholesale"], a.listing-action-btn');
        const payload = {
            listing_id: listingId,
            media_type: sourceEl?.dataset?.previewType || 'image',
            html: columnEl?.outerHTML || '',
            media_src: sourceEl?.dataset?.previewSrc || sourceEl?.getAttribute('src') || '',
            title: sourceEl?.dataset?.previewTitle || '',
            description: sourceEl?.dataset?.previewDescription || '',
            price: sourceEl?.dataset?.previewPrice || '',
            preview_business: sourceEl?.dataset?.previewBusiness || '',
            preview_posted: sourceEl?.dataset?.previewPosted || '',
            action_label: actionLink?.textContent?.trim() || 'Purchase Wholesale',
            purchase_url: sourceEl?.dataset?.purchaseUrl || actionLink?.getAttribute('href') || '#',
            seller_name: sourceEl?.dataset?.previewBusiness || '',
            seller_profilepic: '',
        };
        getRecentStorageKey().then((storageKey) => saveRecentlyViewedListing(payload, storageKey));
    }

    function removeListingFromPublicDom(listingId) {
        document.querySelectorAll(`[data-preview-listing-id="${listingId}"]`).forEach((el) => {
            const card = el.closest('.col-6, .col-4, .col-md-4, .col-lg-3, .listing-card-item, .admin-listing-card');
            card?.remove();
        });
    }

    function ensureDefaultOverlay(config) {
        let overlay = document.getElementById(config.overlayId);
        if (overlay || !config.createIfMissing) {
            return overlay;
        }
        const hasPageOverlay = previewConfigs.some((item) => item !== config && document.getElementById(item.overlayId));
        if (hasPageOverlay) {
            return null;
        }

        overlay = document.createElement('div');
        overlay.id = config.overlayId;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = `
            <button type="button" class="${config.closeClass}" id="${config.closeId}" aria-label="Close preview">&times;</button>
            <div class="${config.panelClass}">
                <img id="${config.imageId}" class="${config.contentClass}" alt="Listing preview" style="display:none;">
                <video id="${config.videoId}" class="${config.contentClass}" controls style="display:none;"></video>
                <div id="${config.detailsId}" class="${config.detailsClass}" style="display:none;">
                    <p id="${config.titleId}" class="${config.titleClass}"></p>
                    <p id="${config.priceId}" class="${config.priceClass}"></p>
                    <p id="${config.descriptionId}" class="${config.descriptionClass}"></p>
                </div>
            </div>
            <img id="${config.watermarkId}" class="media-preview-watermark" src="/assets/images/JoMu logo redesigned.png" alt="JoMu watermark">
        `;
        document.body.appendChild(overlay);
        return overlay;
    }

    function getElements(config) {
        const overlay = ensureDefaultOverlay(config);
        if (!overlay) {
            return null;
        }

        const elements = {
            config,
            overlay,
            closeButton: document.getElementById(config.closeId),
            previewImage: document.getElementById(config.imageId),
            previewVideo: document.getElementById(config.videoId),
            previewDetails: document.getElementById(config.detailsId),
            previewTitle: document.getElementById(config.titleId),
            previewPrice: document.getElementById(config.priceId),
            previewDescription: document.getElementById(config.descriptionId),
            purchaseLink: config.purchaseId ? document.getElementById(config.purchaseId) : null,
            watermark: config.watermarkId ? document.getElementById(config.watermarkId) : null
        };

        if (!elements.previewImage || !elements.previewVideo) {
            return null;
        }

        return elements;
    }

    function getAvailableConfig() {
        return previewConfigs.find((config) => document.getElementById(config.overlayId))
            || previewConfigs.find((config) => config.createIfMissing)
            || previewConfigs[0];
    }

    function normalizeMediaSource(src) {
        return String(src || '').trim();
    }

    function decorateStaticCards() {
        document.querySelectorAll('.cards-container .card img.card-img-top:not(.media-preview-source), .cards-container .card img.card-img-showroom:not(.media-preview-source)').forEach((imageEl) => {
            const card = imageEl.closest('.card');
            if (!card || card.classList.contains('add-listing-card')) {
                return;
            }

            const title = card.querySelector('.card-title, .listing-name-top')?.textContent?.trim() || imageEl.alt || 'Listing preview';
            const price = card.querySelector('.product-price-range, .card-text')?.textContent?.trim() || '';
            const description = card.querySelector('.listing-description, .listing-description-link')?.textContent?.trim() || '';
            const src = normalizeMediaSource(imageEl.getAttribute('src'));

            imageEl.classList.add('media-preview-source');
            imageEl.dataset.previewType = 'image';
            imageEl.dataset.previewSrc = src;
            imageEl.dataset.previewTitle = title;
            imageEl.dataset.previewPrice = price;
            imageEl.dataset.previewDescription = description;
        });
    }

    function optimizeListingMedia() {
        document.querySelectorAll('img').forEach((imageEl) => {
            if (!imageEl.hasAttribute('loading')) {
                imageEl.loading = 'lazy';
            }
            if (!imageEl.hasAttribute('decoding')) {
                imageEl.decoding = 'async';
            }
        });

        document.querySelectorAll('video:not([autoplay])').forEach((videoEl) => {
            if (!videoEl.hasAttribute('preload')) {
                videoEl.preload = 'none';
            }
        });
    }

    function updateListingViewLabels(listingId, label) {
        if (!Number.isInteger(listingId) || listingId <= 0 || !label) return;
        document.querySelectorAll(`[data-listing-view-label="${listingId}"]`).forEach((labelEl) => {
            labelEl.textContent = label;
        });
    }

    async function incrementListingView(listingId) {
        try {
            const response = await fetch(`/php/increment_listing_view.php?listing_id=${encodeURIComponent(String(listingId))}`, {
                credentials: 'same-origin'
            });
            if (!response.ok) return;
            const data = await response.json();
            if (data?.success && typeof data.label === 'string') {
                updateListingViewLabels(listingId, data.label);
            }
        } catch (error) {
            // Non-blocking analytics update.
        }
    }

    function incrementPreviewImageView(sourceEl) {
        const type = String(sourceEl?.dataset.previewType || '').trim();
        const listingId = Number.parseInt(sourceEl?.dataset.previewListingId || '', 10);
        if (type !== 'image' || !Number.isInteger(listingId) || listingId <= 0 || countedImageViews.has(listingId)) {
            return;
        }

        countedImageViews.add(listingId);
        storeRecentlyViewedFromSource(sourceEl);
        incrementListingView(listingId);
    }

    function incrementVideoPlaybackView(listingId) {
        if (!Number.isInteger(listingId) || listingId <= 0 || countedVideoViews.has(listingId)) {
            return;
        }

        countedVideoViews.add(listingId);
        const sourceEl = document.querySelector(`[data-preview-listing-id="${listingId}"]`);
        if (sourceEl) {
            storeRecentlyViewedFromSource(sourceEl);
        }
        incrementListingView(listingId);
    }

    function clearPendingVideoView(videoEl) {
        const timerId = pendingVideoViewTimers.get(videoEl);
        if (timerId) {
            clearTimeout(timerId);
            pendingVideoViewTimers.delete(videoEl);
        }
    }

    function scheduleVideoViewIncrement(videoEl) {
        const listingId = Number.parseInt(videoEl?.dataset.previewListingId || '', 10);
        if (!Number.isInteger(listingId) || listingId <= 0 || countedVideoViews.has(listingId) || pendingVideoViewTimers.has(videoEl)) {
            return;
        }

        const timerId = setTimeout(() => {
            pendingVideoViewTimers.delete(videoEl);
            if (countedVideoViews.has(listingId) || videoEl.paused || videoEl.ended) {
                return;
            }
            incrementVideoPlaybackView(listingId);
        }, 2000);

        pendingVideoViewTimers.set(videoEl, timerId);
    }

    function registerVideoViewTracking(videoEl) {
        if (!(videoEl instanceof HTMLVideoElement) || videoEl.dataset.sharedViewTrackingBound === '1') {
            return;
        }

        videoEl.dataset.sharedViewTrackingBound = '1';
        videoEl.addEventListener('play', () => scheduleVideoViewIncrement(videoEl));
        videoEl.addEventListener('pause', () => clearPendingVideoView(videoEl));
        videoEl.addEventListener('ended', () => clearPendingVideoView(videoEl));
        videoEl.addEventListener('emptied', () => clearPendingVideoView(videoEl));
    }

    function updatePreviewDetails(elements, sourceEl) {
        if (!elements.previewDetails || !elements.previewTitle || !elements.previewPrice || !elements.previewDescription) return;
        const title = String(sourceEl?.dataset.previewTitle || '').trim();
        const price = String(sourceEl?.dataset.previewPrice || '').trim();
        const description = String(sourceEl?.dataset.previewDescription || '');
        const purchaseUrl = String(sourceEl?.dataset.purchaseUrl || '').trim();
        const previewType = String(sourceEl?.dataset.previewType || '').trim().toLowerCase();
        const isImagePreview = previewType !== 'video';
        const hasDetails = title !== '' || price !== '' || description !== '';

        elements.previewTitle.textContent = title;
        elements.previewTitle.style.display = title ? 'block' : 'none';
        elements.previewPrice.textContent = price ? `Price: ${price}` : '';
        elements.previewPrice.style.display = price ? 'block' : 'none';
        elements.previewDescription.textContent = description;
        elements.previewDescription.style.whiteSpace = 'pre-wrap';
        elements.previewDescription.style.wordBreak = 'break-word';
        elements.previewDescription.style.display = description ? 'block' : 'none';
        elements.previewDetails.style.display = hasDetails ? 'block' : 'none';

        if (elements.purchaseLink) {
            if (isImagePreview && purchaseUrl !== '') {
                elements.purchaseLink.href = purchaseUrl;
                elements.purchaseLink.style.display = 'inline-flex';
            } else {
                elements.purchaseLink.removeAttribute('href');
                elements.purchaseLink.style.display = 'none';
            }
        }

        syncAdminListingPreviewBar(elements, sourceEl);
    }

    function closePreview(config = activeConfig) {
        if (!config) {
            previewConfigs.forEach((item) => {
                if (document.getElementById(item.overlayId)) {
                    closePreview(item);
                }
            });
            return;
        }

        const elements = getElements(config);
        if (!elements) {
            return;
        }

        elements.overlay.classList.remove('active');
        elements.overlay.setAttribute('aria-hidden', 'true');
        elements.previewVideo.pause();
        elements.previewVideo.removeAttribute('src');
        delete elements.previewVideo.dataset.previewListingId;
        elements.previewImage.removeAttribute('src');
        elements.previewImage.style.display = 'none';
        elements.previewVideo.style.display = 'none';

        if (elements.previewDetails) {
            elements.previewDetails.style.display = 'none';
        }
        if (elements.purchaseLink) {
            elements.purchaseLink.removeAttribute('href');
            elements.purchaseLink.style.display = 'none';
        }
        if (elements.watermark) {
            elements.watermark.style.display = 'none';
        }

        const adminBar = elements.overlay.querySelector('.jomu-admin-listing-preview-bar');
        if (adminBar) {
            adminBar.style.display = 'none';
        }

        document.body.classList.remove('media-preview-open', 'profile-preview-open');
        document.body.style.overflow = '';

        if (activeConfig === config) {
            activeConfig = null;
        }
    }

    function openPreview(type, src, sourceEl) {
        if (!src) return;

        const config = getAvailableConfig();
        const elements = getElements(config);
        if (!elements) return;

        closePreview();
        activeConfig = config;

        elements.previewImage.style.display = 'none';
        elements.previewVideo.style.display = 'none';
        updatePreviewDetails(elements, sourceEl);
        incrementPreviewImageView(sourceEl);

        if (type === 'video') {
            elements.previewImage.removeAttribute('src');
            elements.previewVideo.src = src;
            elements.previewVideo.dataset.previewListingId = sourceEl?.dataset.previewListingId || '';
            elements.previewVideo.style.display = 'block';
            if (elements.watermark) elements.watermark.style.display = 'none';
            elements.previewVideo.play().catch(() => {});
        } else {
            elements.previewVideo.pause();
            elements.previewVideo.removeAttribute('src');
            elements.previewImage.src = src;
            elements.previewImage.style.display = 'block';
            if (elements.watermark) elements.watermark.style.display = 'block';
        }

        elements.overlay.classList.add('active');
        elements.overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add(config.bodyClass || 'media-preview-open');
        document.body.style.overflow = 'hidden';
        elements.overlay.scrollTop = 0;
    }

    function openPreviewFromSource(sourceEl) {
        if (!sourceEl) return;
        const type = sourceEl.dataset.previewType || (sourceEl.tagName.toLowerCase() === 'video' ? 'video' : 'image');
        const src = sourceEl.dataset.previewSrc || sourceEl.currentSrc || sourceEl.getAttribute('src') || sourceEl.querySelector('source')?.getAttribute('src') || '';
        openPreview(type, src, sourceEl);
    }

    function getSourceFromEvent(event) {
        const target = event.target instanceof Element ? event.target : null;
        if (!target || target.closest(closeSelector) || target.closest('[id$="MediaPreviewOverlay"], #mediaPreviewOverlay')) {
            return null;
        }
        if (target.closest('.jomu-admin-listing-preview-bar')) {
            return null;
        }
        if (target.closest('.listing-action-btn, .card-img-button, .manage-listing-options-trigger, .dropdown-content')) {
            return null;
        }
        if (target.closest('.add-listing-card')) {
            return null;
        }

        return target.closest('.media-preview-source')
            || target.closest(previewTextSelector)?.closest('.card')?.querySelector('.media-preview-source');
    }

    function closeConfigForTarget(target) {
        return previewConfigs.find((config) => {
            const overlay = document.getElementById(config.overlayId);
            if (!overlay) return false;
            return target.closest(`#${config.closeId}`) || target === overlay;
        }) || null;
    }

    function bindOverlayCloseHandlers() {
        previewConfigs.forEach((config) => {
            const elements = getElements(config);
            if (!elements || elements.overlay.dataset.sharedCloseBound === '1') {
                return;
            }

            elements.overlay.dataset.sharedCloseBound = '1';
            elements.closeButton?.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                closePreview(config);
            });
            elements.overlay.addEventListener('click', (event) => {
                if (event.target === elements.overlay) {
                    event.preventDefault();
                    event.stopPropagation();
                    closePreview(config);
                }
            });
            registerVideoViewTracking(elements.previewVideo);
        });
    }

    function registerAllVideos() {
        document.querySelectorAll('video[data-preview-listing-id]').forEach((videoEl) => {
            registerVideoViewTracking(videoEl);
        });
    }

    function initPreviewModal() {
        ensurePreviewStyles();
        fetchAdminBootstrap();
        bindOverlayCloseHandlers();
        optimizeListingMedia();
        decorateStaticCards();
        registerAllVideos();
        let scheduledDomRefresh = false;

        function scheduleDomRefresh() {
            if (scheduledDomRefresh) {
                return;
            }

            scheduledDomRefresh = true;
            window.requestAnimationFrame(() => {
                scheduledDomRefresh = false;
                bindOverlayCloseHandlers();
                optimizeListingMedia();
                decorateStaticCards();
                registerAllVideos();
            });
        }

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (!target) return;

            const closeConfig = closeConfigForTarget(target);
            if (closeConfig) {
                event.preventDefault();
                event.stopImmediatePropagation();
                closePreview(closeConfig);
                return;
            }

            const sourceEl = getSourceFromEvent(event);
            if (!sourceEl) return;
            event.preventDefault();
            event.stopPropagation();
            openPreviewFromSource(sourceEl);
        }, true);

        document.addEventListener('touchend', (event) => {
            const sourceEl = getSourceFromEvent(event);
            if (!sourceEl) return;
            const sourceKey = sourceEl.dataset.previewSrc || sourceEl.getAttribute('src') || '';
            const now = Date.now();
            const isDoubleTap = now - lastTapTime < 350 && sourceKey !== '' && sourceKey === lastTapSrc;

            lastTapTime = now;
            lastTapSrc = sourceKey;

            if (!isDoubleTap) return;
            event.preventDefault();
            event.stopPropagation();
            openPreviewFromSource(sourceEl);
            lastTapTime = 0;
            lastTapSrc = '';
        }, { passive: false });

        document.addEventListener('touchstart', () => {
            if (Date.now() - lastTapTime > 600) {
                lastTapTime = 0;
                lastTapSrc = '';
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                const hasOpenOverlay = previewConfigs.some((config) => document.getElementById(config.overlayId)?.classList.contains('active'));
                if (hasOpenOverlay) {
                    closePreview();
                }
            }
        });

        const observer = new MutationObserver((mutations) => {
            const shouldRefresh = mutations.some((mutation) => {
                const target = mutation.target instanceof Element ? mutation.target : mutation.target?.parentElement;
                if (target?.closest(
                    '#mediaPreviewOverlay, #profileMediaPreviewOverlay, #visitorMediaPreviewOverlay, #dashboardMediaPreviewOverlay, #jomuListingAdminConfirmOverlay'
                )) {
                    return false;
                }

                return Array.from(mutation.addedNodes).some((node) => {
                    if (!(node instanceof Element)) {
                        return false;
                    }

                    return node.matches?.('.card, .media-preview-source, video[data-preview-listing-id]')
                        || node.querySelector?.('.card, .media-preview-source, video[data-preview-listing-id]');
                });
            });

            if (shouldRefresh) {
                scheduleDomRefresh();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.body) {
        initPreviewModal();
    } else {
        document.addEventListener('DOMContentLoaded', initPreviewModal);
    }
})();
