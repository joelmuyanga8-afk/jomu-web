<?php

declare(strict_types=1);

/**
 * Static + filesystem inventory for Admin → Ads (site images, videos, icons).
 * Paths are site-root relative (e.g. assets/videos/...).
 */
$jomuAdminAdManifestRoot = dirname(__DIR__, 2);

$jomuAdminAdSeen = [];
$jomuAdminAdRows = [];

$jomuAdminAdAdd = static function (string $path, string $label, array $pages) use (&$jomuAdminAdRows, &$jomuAdminAdSeen): void {
    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    if ($path === '' || isset($jomuAdminAdSeen[$path])) {
        return;
    }
    $jomuAdminAdSeen[$path] = true;
    $jomuAdminAdRows[] = [
        'label' => $label,
        'pages' => $pages,
        'path' => $path,
    ];
};

// --- Site videos ---
$jomuAdminAdAdd(
    'assets/videos/Over Navbar JoMu 70px.mp4',
    'Over-navbar desktop video',
    [
        'index.php',
        'about.html',
        'signin.html',
        'Category pages (e.g. categories*.html)',
        'suggestedtoppicks.php',
        'recentlyviewed.html',
    ]
);
$jomuAdminAdAdd(
    'assets/videos/Over Navbar JoMu 1080px.mp4',
    'Over-navbar mobile video',
    [
        'index.php',
        'about.html',
        'signin.html',
        'Category pages',
        'suggestedtoppicks.php',
        'recentlyviewed.html',
    ]
);
$jomuAdminAdAdd('assets/videos/JoMu animation large_screens.mp4', 'Home welcome video (large screens)', ['index.php']);
$jomuAdminAdAdd('assets/videos/JoMu animation.mp4', 'Home welcome video (medium screens)', ['index.php']);
$jomuAdminAdAdd('assets/videos/JoMu Animation small_screens.mp4', 'Home welcome video (small screens)', ['index.php']);

// --- Core branding & home carousel ---
$jomuAdminAdAdd('assets/images/JoMu logo redesigned.png', 'JoMu colour logo', ['components/nav.php', 'index.php (footer)', 'Chat / listing UI where used']);
$jomuAdminAdAdd('assets/images/JoMu black and white.png', 'JoMu greyscale logo (navbar stack)', ['components/nav.php']);
$jomuAdminAdAdd('assets/images/profile.png', 'Default profile / placeholder avatar', ['Listings, profiles, fallbacks site-wide']);
$jomuAdminAdAdd('assets/images/Buy-2.png', 'Home carousel image', ['index.php']);

// --- About page (representative hero / content images) ---
$jomuAdminAdAdd('assets/images/JoMu laptop 3-1.png', 'About hero — laptop', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Phone 2.png', 'About hero — phone', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu laptop 1.png', 'About section — laptop', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Phone 1.png', 'About section — phone', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu laptop 2.png', 'About section — laptop alt', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Screenshots-lg-1.png', 'About screenshots row (large)', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Screenshot-sm-1.png', 'About screenshots (small)', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Screenshots-lg-2.png', 'About screenshots block (large)', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Screenshot-sm-2.png', 'About screenshots (small)', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Screenshots-lg-3.png', 'About screenshots block 2 (large)', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Screenshot-sm-3.png', 'About screenshots (small)', ['about.html']);
$jomuAdminAdAdd('assets/images/JoMu Screenshot-sm-4.png', 'About screenshots (small)', ['about.html']);
$jomuAdminAdAdd('assets/images/icons/JoMu Vision-icon.png', 'About — vision icon', ['about.html']);

// --- Footer social (same files as index footer) ---
$jomuAdminAdAdd('assets/images/icons/Facebook Icon.png', 'Footer — Facebook icon', ['index.php']);
$jomuAdminAdAdd('assets/images/icons/Tiktok Icon.png', 'Footer — TikTok icon', ['index.php']);
$jomuAdminAdAdd('assets/images/icons/Instagram Icon.png', 'Footer — Instagram icon', ['index.php']);
$jomuAdminAdAdd('assets/images/icons/X Icon.png', 'Footer — X (Twitter) icon', ['index.php']);

// --- All other icons under assets/images/icons (skip files already registered above) ---
$jomuIconsDir = $jomuAdminAdManifestRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'icons';
if (is_dir($jomuIconsDir)) {
    $iconFiles = scandir($jomuIconsDir);
    if ($iconFiles !== false) {
        foreach ($iconFiles as $iconFile) {
            if ($iconFile === '.' || $iconFile === '..') {
                continue;
            }
            $full = $jomuIconsDir . DIRECTORY_SEPARATOR . $iconFile;
            if (!is_file($full)) {
                continue;
            }
            $ext = strtolower((string) pathinfo($iconFile, PATHINFO_EXTENSION));
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'], true)) {
                continue;
            }
            $rel = 'assets/images/icons/' . $iconFile;
            $jomuAdminAdAdd(
                $rel,
                'Icon: ' . pathinfo($iconFile, PATHINFO_FILENAME),
                [
                    'components/nav.php',
                    'index.php',
                    'Category HTML pages',
                    'signin.html',
                    'suggestedtoppicks.php',
                    'recentlyviewed.html',
                    'php/businessvendordashboard.php',
                    'php/profile.php',
                    'php/visitor_profile.php',
                    'purchasewholesale.html',
                ]
            );
        }
    }
}

return $jomuAdminAdRows;
