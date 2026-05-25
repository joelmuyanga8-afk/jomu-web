<?php

/**
 * Labels for data-preview-business / data-preview-posted on listing media elements
 * (used by admin listing preview + shared listing-preview-modal.js).
 */
function jomu_listing_preview_dataset_attrs(array $listing): array
{
    $business = trim((string) ($listing['seller_businessname'] ?? ''));
    if ($business === '') {
        $business = 'Business';
    }

    $posted = '';
    $createdRaw = trim((string) ($listing['created_at'] ?? ''));
    if ($createdRaw !== '') {
        $ts = strtotime($createdRaw);
        if ($ts !== false) {
            $posted = date('j M Y, g:i a', $ts);
        }
    }

    return [
        'business' => $business,
        'posted' => $posted,
    ];
}

function jomu_listing_preview_dataset_attr_html(array $listing): string
{
    $dataset = jomu_listing_preview_dataset_attrs($listing);

    return sprintf(
        ' data-preview-business="%s" data-preview-posted="%s"',
        htmlspecialchars($dataset['business'], ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($dataset['posted'], ENT_QUOTES, 'UTF-8')
    );
}
