<?php

declare(strict_types=1);

$rootDir = realpath(__DIR__) ?: __DIR__;

if (PHP_SAPI === 'cli-server') {
    $sessionPath = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'jomu-website-sessions';

    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }

    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        ini_set('session.save_path', $sessionPath);
    }
}

function router_normalize_path(string $path): string
{
    $path = str_replace('\\', '/', $path);
    return '/' . ltrim($path, '/');
}

function router_path_is_inside(string $path, string $rootDir): bool
{
    $path = rtrim(str_replace('\\', '/', $path), '/');
    $root = rtrim(str_replace('\\', '/', $rootDir), '/');

    if (PHP_OS_FAMILY === 'Windows') {
        $path = strtolower($path);
        $root = strtolower($root);
    }

    return $path === $root || str_starts_with($path, $root . '/');
}

function router_resolve_public_path(string $publicPath, string $rootDir): ?string
{
    $relativePath = rawurldecode(ltrim(router_normalize_path($publicPath), '/'));

    if ($relativePath === '' || str_contains($relativePath, "\0")) {
        return null;
    }

    $localPath = realpath($rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

    if (!$localPath || !router_path_is_inside($localPath, $rootDir)) {
        return null;
    }

    return $localPath;
}

function router_redirect(string $targetPath): void
{
    $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $location = $targetPath . ($queryString !== '' ? '?' . $queryString : '');
    header('Location: ' . $location, true, 301);
    exit;
}

function router_send_static_file(string $localPath): void
{
    $contentTypes = [
        'avif' => 'image/avif',
        'css' => 'text/css; charset=UTF-8',
        'gif' => 'image/gif',
        'html' => 'text/html; charset=UTF-8',
        'ico' => 'image/x-icon',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'mp4' => 'video/mp4',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));

    if (isset($contentTypes[$extension])) {
        header('Content-Type: ' . $contentTypes[$extension]);
    }

    header('Content-Length: ' . filesize($localPath));
    readfile($localPath);
}

function router_send_file(string $localPath): void
{
    if (strtolower(pathinfo($localPath, PATHINFO_EXTENSION)) === 'php') {
        require $localPath;
        return;
    }

    router_send_static_file($localPath);
}

$routes = [
    '/' => 'index.php',
    '/about' => 'about.html',
    '/bulk-orders' => 'businessbulkorders.html',
    '/business-vendor-dashboard' => 'php/businessvendordashboard.php',
    '/profile' => 'php/profile.php',
    '/visitor-profile' => 'php/visitor_profile.php',
    '/add-new-listing' => 'php/addnewlisting.php',
    '/categories/agriculture-produce' => 'categoriesagriculture&produce.html',
    '/categories/apparel' => 'categoriesapparel.html',
    '/categories/automotive-transport' => 'categoriesautomotive&transport.html',
    '/categories/construction-building' => 'categoriesconstruction&building.html',
    '/categories/electronics-gadgets' => 'categorieselectronics&gagdets.html',
    '/categories/food-beverages' => 'categoriesfood&beverages.html',
    '/categories/furniture-home' => 'categoriesfurniture&home.html',
    '/categories/general-services' => 'categoriesgeneralservices.html',
    '/categories/health-beauty' => 'categorieshealth&beauty.html',
    '/categories/it-software' => 'categoriesit&software.html',
    '/categories/livestock-animals' => 'categorieslivestock&animals.html',
    '/categories/office-supply-stationery' => 'categoriesofficesupply&stationery.html',
    '/categories/printing-branding' => 'categoriesprinting&branding.html',
    '/categories/wholesale-retail' => 'categorieswholesale&retail.html',
    '/create-account' => 'createaccount.html',
    '/feedback' => 'feedback.html',
    '/help' => 'help.html',
    '/new-arrivals/central' => 'newarrivalscentral.php',
    '/new-arrivals/eastern' => 'newarrivalseastern.php',
    '/new-arrivals/northern' => 'newarrivalsnorthern.php',
    '/new-arrivals/western' => 'newarrivalswestern.php',
    '/offers-discounts' => 'offers&discounts.html',
    '/privacy-policy' => 'privacypolicy.html',
    '/purchase-wholesale' => 'purchasewholesale.html',
    '/recently-viewed' => 'recentlyviewed.html',
    '/sign-in' => 'signin.html',
    '/suggested/same-category' => 'suggestedsamecategory.html',
    '/suggested/top-picks' => 'suggestedtoppicks.php',
    '/support' => 'support.html',
    '/terms-and-conditions' => 'termsandconditions.html',
    '/top-rated-sellers/central' => 'topratedsellerscentral.html',
    '/top-rated-sellers/eastern' => 'topratedsellerseastern.html',
    '/top-rated-sellers/northern' => 'topratedsellersnorthern.html',
    '/top-rated-sellers/western' => 'topratedsellerswestern.html',
    '/trending/hot-deals' => 'trendinghotdeals.html',
    '/trending/seasonal-trends' => 'trendingseasonaltrends.html',
    '/vendor-shops/apparel' => 'vendorshops-apparel.html',
    '/vendor-shops/shoes' => 'vendorshops-shoes.html',
];

$legacyRoutes = [
    '/index.php' => '/',
    '/about.html' => '/about',
    '/businessbulkorders.html' => '/bulk-orders',
    '/php/businessvendordashboard.php' => '/business-vendor-dashboard',
    '/php/profile.php' => '/profile',
    '/php/visitor_profile.php' => '/visitor-profile',
    '/php/addnewlisting.php' => '/add-new-listing',
    '/categoriesagriculture&produce.html' => '/categories/agriculture-produce',
    '/categoriesapparel.html' => '/categories/apparel',
    '/categoriesautomotive&transport.html' => '/categories/automotive-transport',
    '/categoriesconstruction&building.html' => '/categories/construction-building',
    '/categorieselectronics&gagdets.html' => '/categories/electronics-gadgets',
    '/categoriesfood&beverages.html' => '/categories/food-beverages',
    '/categoriesfurniture&home.html' => '/categories/furniture-home',
    '/categoriesgeneralservices.html' => '/categories/general-services',
    '/categorieshealth&beauty.html' => '/categories/health-beauty',
    '/categoriesit&software.html' => '/categories/it-software',
    '/categorieslivestock&animals.html' => '/categories/livestock-animals',
    '/categoriesofficesupply&stationery.html' => '/categories/office-supply-stationery',
    '/categoriesprinting&branding.html' => '/categories/printing-branding',
    '/categorieswholesale&retail.html' => '/categories/wholesale-retail',
    '/createaccount.html' => '/create-account',
    '/feedback.html' => '/feedback',
    '/help.html' => '/help',
    '/newarrivalscentral.php' => '/new-arrivals/central',
    '/newarrivalseastern.php' => '/new-arrivals/eastern',
    '/newarrivalsnorthern.php' => '/new-arrivals/northern',
    '/newarrivalswestern.php' => '/new-arrivals/western',
    '/offers&discounts.html' => '/offers-discounts',
    '/privacypolicy.html' => '/privacy-policy',
    '/purchasewholesale.html' => '/purchase-wholesale',
    '/recentlyviewed.html' => '/recently-viewed',
    '/signin.html' => '/sign-in',
    '/suggestedsamecategory.html' => '/suggested/same-category',
    '/suggestedtoppicks.php' => '/suggested/top-picks',
    '/support.html' => '/support',
    '/termsandconditions.html' => '/terms-and-conditions',
    '/topratedsellerscentral.html' => '/top-rated-sellers/central',
    '/topratedsellerseastern.html' => '/top-rated-sellers/eastern',
    '/topratedsellersnorthern.html' => '/top-rated-sellers/northern',
    '/topratedsellerswestern.html' => '/top-rated-sellers/western',
    '/trendinghotdeals.html' => '/trending/hot-deals',
    '/trendingseasonaltrends.html' => '/trending/seasonal-trends',
    '/vendorshops-apparel.html' => '/vendor-shops/apparel',
    '/vendorshops-shoes.html' => '/vendor-shops/shoes',
];

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = router_normalize_path(is_string($requestPath) && $requestPath !== '' ? $requestPath : '/');
$localPath = router_resolve_public_path($requestPath, $rootDir);
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (in_array($requestMethod, ['GET', 'HEAD'], true) && isset($legacyRoutes[$requestPath])) {
    router_redirect($legacyRoutes[$requestPath]);
}

if ($requestPath !== '/' && str_ends_with($requestPath, '/') && (!$localPath || !is_dir($localPath))) {
    router_redirect(rtrim($requestPath, '/'));
}

if ($requestPath !== '/' && $localPath && is_file($localPath)) {
    return false;
}

if (preg_match('#^/(categories|new-arrivals|suggested|top-rated-sellers|trending|vendor-shops)/(assets|php)/(.*)$#', $requestPath, $matches)) {
    $fallbackPath = '/' . $matches[2] . '/' . $matches[3];
    $fallbackLocalPath = router_resolve_public_path($fallbackPath, $rootDir);

    if ($fallbackLocalPath && is_file($fallbackLocalPath)) {
        router_send_file($fallbackLocalPath);
        return true;
    }
}

$target = $routes[$requestPath] ?? null;

if (is_string($target)) {
    $targetPath = router_resolve_public_path('/' . $target, $rootDir);

    if ($targetPath && is_file($targetPath)) {
        router_send_file($targetPath);
        return true;
    }
}

http_response_code(404);
$notFoundPath = router_resolve_public_path('/index.php', $rootDir);

if ($notFoundPath && is_file($notFoundPath)) {
    router_send_file($notFoundPath);
}

return true;
