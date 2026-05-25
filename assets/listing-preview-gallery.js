(function () {
    if (window.jomuListingPreviewGalleryLoaded) {
        return;
    }

    window.jomuListingPreviewGalleryLoaded = true;

    const previewConfigs = [
        {
            overlayId: 'mediaPreviewOverlay',
            panelSelector: '.media-preview-panel',
            imageId: 'mediaPreviewImage',
            videoId: 'mediaPreviewVideo',
            detailsId: 'mediaPreviewDetails',
            sourceSelector: '.media-preview-source'
        },
        {
            overlayId: 'profileMediaPreviewOverlay',
            panelSelector: '.profile-media-preview-panel',
            imageId: 'profileMediaPreviewImage',
            videoId: 'profileMediaPreviewVideo',
            detailsId: 'profileMediaPreviewDetails',
            sourceSelector: '.media-preview-source'
        },
        {
            overlayId: 'visitorMediaPreviewOverlay',
            panelSelector: '.visitor-media-preview-panel',
            imageId: 'visitorMediaPreviewImage',
            videoId: 'visitorMediaPreviewVideo',
            detailsId: 'visitorMediaPreviewDetails',
            sourceSelector: '.media-preview-source'
        },
        {
            overlayId: 'dashboardMediaPreviewOverlay',
            panelSelector: '.dashboard-media-preview-panel',
            imageId: 'dashboardMediaPreviewImage',
            videoId: 'dashboardMediaPreviewVideo',
            detailsId: 'dashboardMediaPreviewDetails',
            sourceSelector: '.media-preview-source'
        }
    ];

    if (!document.getElementById('media-preview-gallery-style')) {
        const styleEl = document.createElement('style');
        styleEl.id = 'media-preview-gallery-style';
        styleEl.textContent = `
            .media-preview-gallery {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
                gap: 8px;
                width: min(96vw, 620px);
            }

            .media-preview-gallery-thumb {
                width: 72px;
                height: 72px;
                object-fit: cover;
                border-radius: 8px;
                border: 1px solid rgba(241, 90, 36, 0.28);
                cursor: pointer;
                background: #fff;
            }

            .media-preview-gallery-thumb.is-active {
                border-color: rgb(241, 90, 36);
                box-shadow: 0 0 0 2px rgba(241, 90, 36, 0.18);
            }
        `;
        document.head.appendChild(styleEl);
    }

    function normalizeMediaUrl(pathValue) {
        const raw = String(pathValue || '').trim().replace(/\\/g, '/');
        if (!raw) return '';
        if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('/')) {
            return raw;
        }
        if (raw.startsWith('assets/')) {
            return `/${raw}`;
        }
        if (raw.startsWith('php/')) {
            return `/${raw}`;
        }
        if (raw.startsWith('uploads/')) {
            return `/php/${raw}`;
        }
        return `/php/${raw}`;
    }

    function initPreviewGallery(config) {
        const overlay = document.getElementById(config.overlayId);
        const panel = overlay ? overlay.querySelector(config.panelSelector) : null;
        const previewImage = document.getElementById(config.imageId);
        const previewVideo = document.getElementById(config.videoId);
        const previewDetails = document.getElementById(config.detailsId);
        const watermarkEl = overlay ? overlay.querySelector('[class*="watermark"]') : null;

        if (!overlay || !panel || !previewImage || !previewVideo) {
            return;
        }

        const galleryEl = document.createElement('div');
        galleryEl.className = 'media-preview-gallery';
        galleryEl.style.display = 'none';

        if (previewDetails && previewDetails.parentNode === panel) {
            panel.insertBefore(galleryEl, previewDetails);
        } else {
            panel.appendChild(galleryEl);
        }

        const galleryCache = new Map();
        let lastSourceEl = null;

        function hideGallery() {
            galleryEl.innerHTML = '';
            galleryEl.style.display = 'none';
        }

        function positionWatermark() {
            if (!overlay || !watermarkEl || !previewImage) {
                return;
            }

            const isOverlayOpen = overlay.classList.contains('active');
            const isImageVisible = previewImage.style.display !== 'none' && !!previewImage.getAttribute('src');
            if (!isOverlayOpen || !isImageVisible) {
                return;
            }

            const overlayRect = overlay.getBoundingClientRect();
            const imageRect = previewImage.getBoundingClientRect();
            if (imageRect.width <= 0 || imageRect.height <= 0) {
                return;
            }

            const centerX = imageRect.left - overlayRect.left + (imageRect.width / 2);
            const centerY = imageRect.top - overlayRect.top + (imageRect.height / 2);
            const minDimension = Math.min(imageRect.width, imageRect.height);
            const preferredSize = Math.max(88, Math.min(260, minDimension * 0.56));
            const size = Math.min(preferredSize, minDimension * 0.9);

            watermarkEl.style.left = `${centerX}px`;
            watermarkEl.style.top = `${centerY}px`;
            watermarkEl.style.width = `${size}px`;
            watermarkEl.style.maxWidth = `${size}px`;
        }

        function setLastSourceFromEventTarget(target) {
            const sourceEl = target instanceof Element ? target.closest(config.sourceSelector) : null;
            if (sourceEl) {
                lastSourceEl = sourceEl;
            }
        }

        async function fetchGallery(listingId) {
            if (galleryCache.has(listingId)) {
                return galleryCache.get(listingId);
            }

            const request = fetch(`/php/get_listing_gallery.php?listing_id=${encodeURIComponent(String(listingId))}`, {
                credentials: 'same-origin'
            })
                .then((response) => response.ok ? response.json() : null)
                .then((data) => {
                    if (!data || data.success !== true) {
                        return null;
                    }
                    return data;
                })
                .catch(() => null);

            galleryCache.set(listingId, request);
            return request;
        }

        function renderGallery(mainSrc, extraImages) {
            const normalizedMain = normalizeMediaUrl(mainSrc);
            const normalizedExtras = Array.isArray(extraImages)
                ? extraImages.map((item) => normalizeMediaUrl(item)).filter(Boolean)
                : [];
            const allImages = [normalizedMain, ...normalizedExtras].filter(Boolean);

            galleryEl.innerHTML = '';
            if (allImages.length <= 1) {
                galleryEl.style.display = 'none';
                return;
            }

            allImages.forEach((imageSrc, index) => {
                const thumb = document.createElement('img');
                thumb.src = imageSrc;
                thumb.alt = index === 0 ? 'Main listing image' : `Additional listing image ${index}`;
                thumb.className = 'media-preview-gallery-thumb';
                if (imageSrc === normalizedMain) {
                    thumb.classList.add('is-active');
                }
                thumb.addEventListener('click', () => {
                    previewImage.src = imageSrc;
                    galleryEl.querySelectorAll('.media-preview-gallery-thumb').forEach((thumbEl) => {
                        thumbEl.classList.toggle('is-active', thumbEl === thumb);
                    });
                });
                galleryEl.appendChild(thumb);
            });

            galleryEl.style.display = '';
        }

        async function syncGalleryWithOverlay() {
            const isOverlayOpen = overlay.classList.contains('active');
            const isImageVisible = previewImage.style.display !== 'none' && previewImage.getAttribute('src');
            const previewType = String(lastSourceEl?.dataset.previewType || '').trim();
            const listingId = Number.parseInt(lastSourceEl?.dataset.previewListingId || '', 10);

            positionWatermark();

            if (!isOverlayOpen || !isImageVisible || previewType !== 'image' || !Number.isInteger(listingId) || listingId <= 0) {
                hideGallery();
                return;
            }

            const galleryData = await fetchGallery(listingId);
            if (!overlay.classList.contains('active')) {
                return;
            }

            if (!galleryData) {
                hideGallery();
                return;
            }

            renderGallery(galleryData.main_media || previewImage.getAttribute('src') || '', galleryData.images || []);
        }

        document.addEventListener('click', (event) => {
            setLastSourceFromEventTarget(event.target);
            window.setTimeout(syncGalleryWithOverlay, 0);
        }, true);

        document.addEventListener('touchend', (event) => {
            setLastSourceFromEventTarget(event.target);
            window.setTimeout(syncGalleryWithOverlay, 400);
        }, true);

        const overlayObserver = new MutationObserver(() => {
            if (!overlay.classList.contains('active')) {
                hideGallery();
                return;
            }
            syncGalleryWithOverlay();
        });

        overlayObserver.observe(overlay, {
            attributes: true,
            attributeFilter: ['class']
        });

        previewImage.addEventListener('load', () => {
            if (overlay.classList.contains('active')) {
                syncGalleryWithOverlay();
            }
        });

        previewVideo.addEventListener('play', hideGallery);
        window.addEventListener('resize', () => {
            if (overlay.classList.contains('active')) {
                positionWatermark();
            }
        });
    }

    previewConfigs.forEach(initPreviewGallery);
})();
