<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLogged = isset($_SESSION['emailormobilenumber']);
$isAdminLogged = isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0;
$dashboardHref = $isAdminLogged ? '/php/admin/dashboard.php' : '/php/businessvendordashboard.php';
$dashboardLabel = $isAdminLogged ? 'Admin dashboard' : 'Dashboard';
$popularSearchTerms = [];
$siteLinks = [
    'app' => '',
    'facebook' => '',
    'instagram' => '',
    'tiktok' => '',
    'x' => '',
];

if (!function_exists('jomuTruncatePopularSearchLabel')) {
    function jomuTruncatePopularSearchLabel(string $term, int $limit = 40): string
    {
        $normalized = trim($term);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($normalized, 'UTF-8') <= $limit) {
                return $normalized;
            }

            return rtrim(mb_substr($normalized, 0, $limit, 'UTF-8')) . '...';
        }

        if (strlen($normalized) <= $limit) {
            return $normalized;
        }

        return rtrim(substr($normalized, 0, $limit)) . '...';
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS site_links (
            link_key VARCHAR(40) PRIMARY KEY,
            label VARCHAR(80) NOT NULL,
            url VARCHAR(500) NOT NULL DEFAULT '',
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $siteLinkRes = $conn->query("SELECT link_key, url FROM site_links");
    if ($siteLinkRes) {
        while ($siteLinkRow = $siteLinkRes->fetch_assoc()) {
            $key = (string) ($siteLinkRow['link_key'] ?? '');
            if (array_key_exists($key, $siteLinks)) {
                $siteLinks[$key] = (string) ($siteLinkRow['url'] ?? '');
            }
        }
    }

    $conn->query(
        "CREATE TABLE IF NOT EXISTS user_search_interest (
            user_id INT NOT NULL,
            search_term VARCHAR(190) NOT NULL,
            search_count INT NOT NULL DEFAULT 1,
            last_searched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, search_term),
            KEY idx_user_search_interest_last (last_searched_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $popularSearchStmt = $conn->prepare(
        "SELECT search_term, SUM(search_count) AS total_searches, MAX(last_searched_at) AS latest_search
         FROM user_search_interest
         WHERE TRIM(search_term) <> ''
         GROUP BY search_term
         ORDER BY total_searches DESC, latest_search DESC
         LIMIT 5"
    );

    if ($popularSearchStmt) {
        $popularSearchStmt->execute();
        $popularSearchResult = $popularSearchStmt->get_result();
        while ($popularSearchRow = $popularSearchResult->fetch_assoc()) {
            $term = trim((string) ($popularSearchRow['search_term'] ?? ''));
            if ($term !== '') {
                $popularSearchTerms[] = $term;
            }
        }
        $popularSearchStmt->close();
    }
}

?>

<style>
    .mobile-auth-dropdown {
        position: relative;
    }

    .mobile-auth-menu {
        left: auto;
        right: 0;
    }

    .mobile-auth-menu::before {
        content: "";
        position: absolute;
        top: -7px;
        right: 10px;
        width: 14px;
        height: 14px;
        background: #fff;
        transform: rotate(45deg);
        border-radius: 2px;
        box-shadow: -2px -2px 8px rgba(17, 24, 39, 0.04);
    }

    @media (max-width: 991.98px) {
        .navbartwo .mobile-viewport-dropdown {
            position: fixed;
            top: auto !important;
            left: 8px !important;
            right: 8px !important;
            width: auto !important;
            max-width: calc(100vw - 16px);
            max-height: calc(100vh - 150px);
            box-sizing: border-box;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .navbartwo .mobile-viewport-dropdown > .container {
            width: 100%;
            max-width: 100%;
            margin-left: 0;
            margin-right: 0;
            box-sizing: border-box;
        }
       }
</style>

<nav class="navbar navbar-expand-lg navbar-light fixed-top navbarone bg-dark" id="navbarone">

    <!-- Logo for large screens.-->
    <div class="container d-none d-md-block d-lg-block">
        <a class="navbar-brand brand-logos" href="#">
            <img src="assets/images/JoMu black and white.png" class="img-fluid logo">
            <img src="assets/images/JoMu logo redesigned.png" class="img-fluid logo logo-hover">

        </a>
    </div>

    <!-- Logo for small screens. -->
    <div class="d-block d-md-none d-lg-none py-0 px-0">
        <a class="navbar-brand brand-logos px-0" href="#">
            <img src="assets/images/JoMu black and white.png" class="img-fluid logo"
                style="margin-right: 60px;">
            <img src="assets/images/JoMu logo redesigned.png" class="img-fluid logo logo-hover">

        </a>
    </div>


    <!-- Auth actions for small and medium screens -->
    <?php if (!$isLogged && !$isAdminLogged) { ?>
        <div class="dropdown d-lg-none mobile-auth-dropdown">
            <button class="btn mobile-auth-trigger" type="button" id="mobileAuthMenuButton"
                data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open sign in menu">
                <img src="assets/images/icons/Signin.png" class="mobile-auth-icon" alt="Sign in">
            </button>
            <div class="dropdown-menu dropdown-menu-end mobile-auth-menu" aria-labelledby="mobileAuthMenuButton">
                <a class="dropdown-item mobile-auth-item" href="signin.html" data-mobile-auth-link="signin.html" style="color:
                rgb(0,0,255);">Sign In</a>
                <button class="dropdown-item mobile-auth-item mobile-auth-create" type="button"
                    data-mobile-auth-link="createaccount.html">Create account</button>
            </div>
        </div>
    <?php } else { ?>
        <a class="navbar-toggler d-lg-none" href="<?php echo htmlspecialchars($dashboardHref); ?>"
            aria-label="Open dashboard">
            <span>
                <img src="assets/images/icons/Container orange.png" style="width: 45px; height: 45px; margin-right: -15px;">
            </span>
        </a>
    <?php } ?>
    <!-- Navbar links for large screens -->
    <div class="collapse navbar-collapse d-none d-lg-flex me-4 links-container" id="navbarNav">

        <!-- Search bar for large screens. -->
        <div class="container-fluid searching">
            <input type="text" id="searchbarInput" class="searchbar" placeholder="Search...">
            <button class="button search" type="submit">
                <span><img src="assets/images/icons/Search Icon.png" class="options-icons"></span>
                Search</button>
        </div>
        <!-- <li class="nav-item dropdown">
            <button class="button language dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="true"
                aria-haspopup="true" aria-expanded="false">
                <span>
                    <img src="assets/images/icons/Language Icon 2.png" class="img-fluid options-icons">
                </span>ENG</button>
            <ul class="dropdown-menu" aria-labelledby="languageDropdown">
                <li><a class="dropdown-item" href="#">Swahili</a></li>
                <li><a class="dropdown-item" href="#">Luganda</a></li>
            </ul>
        </li> -->
        <ul class="navbar-nav">
            <li class="nav-item active signin">
                <?php
                    if ($isLogged || $isAdminLogged) {
                ?>
                    <a class="nav-link link-text button-createaccount<?php echo $isAdminLogged ? ' admin-dashboard-pill' : ''; ?>" href="<?php echo htmlspecialchars($dashboardHref); ?>"><?php echo htmlspecialchars($dashboardLabel); ?></a>
                <?php
                    }else {
                ?>
                    <a class="nav-link link-text" href="signin.html">Sign In</a>
                <?php        
                    }
                ?>
            </li>
        </ul>
    <?php if (!$isLogged && !$isAdminLogged) { ?>
            <button class="button button-createaccount" onclick="location.href='createaccount.html'">
                Create account
            </button>
        <?php } ?>
    </div>
    <!-- Search bar for medium screens -->
    <div class="container-fluid d-none d-md-block d-lg-none searching-medium">
        <input type="text" id="searchbarInput" class="searchbar-medium" placeholder="Search...">
        <button class="btn" type="submit"><span><img src="assets/images/icons/Search Icon.png"
                    class="search-icon-medium search-medium"></span>
        </button>
    </div>
    <!-- Search Bar for Small screens. -->
    <!-- <div class="d-block d-md-none d-lg-none searching-small input-group">
        <input type="text" id="searchbar" class="searchbar-small"
            placeholder="Search businesses, deals and clients">
        <input type="text" id="searchbarInput" class="searchbar-small" placeholder="Search...">
        <button class="btn" type="submit"><span><img src="assets/images/icons/Search Icon.png"
                    class="search-icon-small search-small"></span>
        </button>
    </div> -->
    <div class="d-block d-md-none d-lg-none searching-small">
        <div class="input-group">
            <input type="text" id="searchbarInput" class="form-control searchbar-small" placeholder="Search...">
            <button class="btn btn-search-small" type="submit"><span><img
                        src="assets/images/icons/Search Icon.png" class="search-icon-small search-small"></span>
            </button>
        </div>
    </div>

    <?php if ($isLogged) { ?>
        <!-- offcanvas menu for small and medium screens -->
        <div class="offcanvas offcanvas-bottom d-lg-none" tabindex="-1" id="offcanvasNav"
            aria-labelledby="offcanvasNavbarLabel" style="height: 25vh;">
            <div class="offcanvas-header">
                <button type="button" class="btn-close bg-white " data-bs-dismiss="offcanvas"
                    aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item active">
                        <a class="nav-link link-text<?php echo $isAdminLogged ? ' admin-dashboard-pill' : ''; ?>" href="<?php echo htmlspecialchars($dashboardHref); ?>"><?php echo htmlspecialchars($dashboardLabel); ?></a>
                    </li>
                </ul>
            </div>
        </div>
    <?php } ?>
    </div>
</nav>
<!-- Navbar two for large screens -->
<nav class="navbar navbar-expand-lg navbar-light d-none d-lg-block navbartwo">
    <div class="containerone">
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="all">
                <span>
                    <img src="assets/images/icons/All icon.png" class="img-fluid options-icons">
                </span>All</button>
            <ul class="dropdown-content" aria-labelledby="all">
                <div class="dropdown-two">
                    <!-- Recently added products from all vendors -->
                    <li class="li-1 hover-underline-two submenu-trigger" style="cursor: pointer;" id="newarrivals"><a
                            class="dropdown-item">New
                            Arrivals</a></li>
                    <ul class="dropdown-content-two" aria-labelledby="newarrivals">
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalscentral.html">Central</a>
                        </li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalseastern.html">Eastern</a>
                        </li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalswestern.html">Western</a>
                        </li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalsnorthern.html">Northern</a></li>
                    </ul>
                </div>
                <div class="dropdown-two">
                    <!-- List of all sellers/businesses on the platform.                        -->
                    <li class="li-1 hover-underline-two submenu-trigger" id="vendor" style="cursor: pointer;"><a
                            class="dropdown-item">Vendors/Shops</a>
                    </li>
                    <ul class="dropdown-content-two" aria-labelledby="vendors">
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="vendorshops-apparel.html">Apparel &
                                Accessories</a></li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="vendorshops-shoes.html">Shoes</a></li>
                        <!-- <li class="li-2"><a class="dropdown-item-two" href=""> Kids & Toys</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Lights & Lighting</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Medical Supplies</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Rubber & Plastics</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Beauty & Cosmetics</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Sports & Entertainment</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Jewelry</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Gift Hampers</a></li> -->
                    </ul>
                </div>
                <!-- Products with ongoing promotions. -->
                <li class="li-1"><a class="dropdown-item" href="offers&discounts.html">Offers & Discounts</a>
                </li>
                <!-- Items the user previously clicked on.   -->
                <li class="li-1"><a class="dropdown-item" href="recentlyviewed.html">Recently Viewed</a></li>
                <!-- Hot items currently gaining attention. -->
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger" style="cursor: pointer;"><a class="dropdown-item">Popular Searches</a></li>
                    <div class="dropdown-content-two" style="width: 40vw;" aria-labelledby="why-choose-us">
                        <div class="container shadow px-1 py-1"
                            style="display: flex; flex-direction: row; gap: 2px; cursor: pointer;">
                            <div>
                                <h5 class="mb-2 mt-0" style="color: rgb(241, 90, 36);"><b>Items currently
                                        gaining attention.</b></h5>
                                <hr class="mb-0 mt-0">
                                <?php if ($popularSearchTerms !== []) { ?>
                                    <?php foreach ($popularSearchTerms as $popularSearchTerm) { ?>
                                        <p class="mb-0 mt-0" style="color: rgb(0, 0, 255); cursor: pointer;"
                                            onclick="location.href='<?php echo htmlspecialchars('index.php?search=' . urlencode($popularSearchTerm), ENT_QUOTES, 'UTF-8'); ?>'">
                                            <?php echo htmlspecialchars(jomuTruncatePopularSearchLabel(ucwords($popularSearchTerm))); ?>
                                        </p>
                                    <?php } ?>
                                <?php } else { ?>
                                    <p class="mb-0 mt-0" style="color: rgb(108, 117, 125);">
                                        No tracked searches yet.</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="home">
                <span>
                    <img src="assets/images/icons/Home icon 2.png" class="img-fluid options-icons">
                </span>Home</button>
            <ul class="dropdown-content" aria-labelledby="home">
                <?php if (!$isLogged && !$isAdminLogged) { ?>
                    <li class="li-1"><a class="dropdown-item" href="createaccount.html">Join as Seller</a></li>
                <?php } ?>
                <!-- <div class="dropdown-two">
                    <li class="li-1" style="cursor: pointer;"><a class="dropdown-item">Top-Rated Sellers</a>
                    </li>
                    <ul class="dropdown-content-two" aria-labelledby="top-rated">
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellerscentral.html">Central</a></li>
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellerseastern.html">Eastern</a></li>
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellerswestern.html">Western</a></li>
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellersnorthern.html">Northern</a></li>
                    </ul>
                </div> -->
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger"><a class="dropdown-item" href="">Links</a></li>
                    <ul class="dropdown-content-two" aria-labelledby="appdownload">
                        <?php if ($siteLinks['app'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['app']); ?>">JoMu Application</a></li><?php endif; ?>
                        <?php if ($siteLinks['facebook'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['facebook']); ?>">Facebook</a></li><?php endif; ?>
                        <?php if ($siteLinks['instagram'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['instagram']); ?>">Instagram</a></li><?php endif; ?>
                        <?php if ($siteLinks['tiktok'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['tiktok']); ?>">Tiktok</a></li><?php endif; ?>
                        <?php if ($siteLinks['x'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['x']); ?>">X</a></li><?php endif; ?>
                    </ul>
                </div>
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger" style="cursor: pointer;"><a class="dropdown-item">Why Choose Us?</a></li>
                    <div class="dropdown-content-two" style="width: 70vw;" aria-labelledby="why-choose-us">
                        <div class="container shadow"
                            style="display: flex; flex-direction: row; gap: 2px; cursor: pointer;">
                            <div>
                                <h5 class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><u>Why Buyers Choose
                                            Us?</u></b></h5>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Wide
                                        selection of Wholesale Products.</b></p>
                                <p class="mb-0 mt-0" style="margin-right: 2px;">Access a variety of verified
                                    businesses offering bulk goods
                                    in
                                    different categories.
                                </p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Easy Comparison &
                                        Browsing.</b></p>
                                <p class="mb-0 mt-0">JoMu lets you compare offers, check profiles, and make
                                    informed
                                    decisions.</p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Direct
                                        Communication</b></p>
                                <p class="mb-0 mt-0">Chat directly with sellers, no middlemen, no delays.</p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><span
                                        style="color: rgb(241, 90, 36);">&#9642;</span><b>Save Time & Costs</b>
                                </p>
                                <p class="mb-0 mt-0">Quickly find bulk deals without the need to travel or go
                                    through
                                    agents.</p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Mobile-friendly
                                        Experience</b></p>
                                <p class="mb-0 mt-0">Find suppliers, make deals, and manage inquiries right from
                                    your mobile device.</p>
                            </div>
                            <div>
                                <h5 class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><u>Why Sellers Choose
                                            Us?</u></b></h5>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><span
                                        style="color: rgb(0, 0, 255);">&#9642;</span><b>Reach
                                        Serious Buyers Only.</b></p>
                                <p class="mb-0 mt-0">We connect you with businesses that are ready to buy in
                                    bulk.</p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>Showcase Your Business
                                        Profile.</b></p>
                                <p class="mb-0 mt-0">Build a professional presence with your products, contact
                                    info, and
                                    business credibility.</p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>No
                                        Complex Setup</b></p>
                                <p class="mb-0 mt-0">Create an account, list your products and start selling.
                                    It's that
                                    easy.</p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>Increase Visibility</b>
                                </p>
                                <p class="mb-0 mt-0">Get discovered by businesses across different regions and
                                    sectors.
                                </p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>Communicate Directly
                                        with Clients.</b></p>
                                <p class="mb-0 mt-0">Receive messages directly from potential buyers, reducing
                                    delays.
                                </p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b>&#9642;Grow
                                        Your Brand.</b></p>
                                <p class="mb-0 mt-0">Whether you're a wholesaler or manufacturer, we help you
                                    grow your
                                    network and customer base.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- <li class="li-1"><a class="dropdown-item" href="">Partners/Brands</a></li> -->
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="categories">
                <span>
                    <img src="assets/images/icons/Categories personal icon.png" class="img-fluid options-icons">
                </span>Categories</button>
            <ul class="dropdown-content" aria-labelledby="categories">
                <li class="li-1"><a class="dropdown-item" href="categoriesapparel.html">Apparel/Ndiboota</a>
                </li>
                <li class="li-1"><a class="dropdown-item" href="categorieswholesale&retail.html">Wholesale &
                        Retail</a></li>
                <li class="li-1"><a class="dropdown-item" href="categorieselectronics&gagdets.html">Electronics
                        & Gadgets</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesagriculture&produce.html">Agriculture
                        & Produce</a></li>
                <li class="li-1"><a class="dropdown-item" href="categorieslivestock&animals.html">Livestock &
                        Animals</a></li>
                <li class="li-1"><a class="dropdown-item"
                        href="categoriesconstruction&building.html">Construction & Building Materials</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesofficesupply&stationery.html">Office
                        Supplies & Stationery</a></li>
                <li class="li-1"><a class="dropdown-item" href="categorieshealth&beauty.html">Health &
                        Beauty</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesfood&beverages.html">Food &
                        Beverages</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesautomotive&transport.html">Automotive
                        & Transport</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesfurniture&home.html">Furniture & Home
                        Decor</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesit&software.html">IT & Software
                        Accessories</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesprinting&branding.html">Printing &
                        Branding</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesgeneralservices.html">General
                        Services</a></li>
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="business">
                <span>
                    <img src="assets/images/icons/Business Icon.png" class="img-fluid options-icons">
                </span>Business</button>
            <ul class="dropdown-content" aria-labelledby="business">
                <!-- Deals for large scale purchases.  Also a place where users can post what they need and 
                    potential sellers check in from-->
                <li class="li-1"><a class="dropdown-item" href="businessbulkorders.html">Bulk Orders</a></li>
                <!-- Separate section for bulk buyers/sellers. -->
                <!-- <li class="li-1"><a class="dropdown-item" href="">Wholesale Marketplace</a></li> -->
                <!-- Access to manage products, orders, and earnings. -->
                <?php if (!$isAdminLogged): ?>
                    <li class="li-1"><a class="dropdown-item" href="php/businessvendordashboard.php">Vendor
                            Dashboard</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="suggested">
                <span>
                    <img src="assets/images/icons/Suggested Icon.png" class="img-fluid options-icons">
                </span>Suggested</button>
            <ul class="dropdown-content" aria-labelledby="suggested">
                    <li class="li-1"><a class="dropdown-item" href="suggestedtoppicks.php">Top Picks</a></li>
                <li class="li-1"><a class="dropdown-item" href="suggestedsamecategory.html">Same Category</a>
                </li>
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="trending">
                <span>
                    <img src="assets/images/icons/Trending Icon.png" class="img-fluid options-icons">
                </span>Trending</button>
            <ul class="dropdown-content" aria-labelledby="trending">
                <!-- <li class="li-1"><a class="dropdown-item" href="trendinghotdeals.html">Hot Deals</a></li> -->
                <!-- Listings of items trending in the season or incoming season -->
                <li class="li-1"><a class="dropdown-item" href="trendingseasonaltrends.html">Seasonal Trends</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- Navbar two for small and medium screens from start  -->
<nav class="navbar navbar-expand-lg navbar-light d-block d-lg-none navbartwo">
    <div class="containerone">
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="all">
                <span>
                    <img src="assets/images/icons/All icon.png" class="img-fluid options-icons">
                </span>All</button>
            <ul class="dropdown-content" aria-labelledby="all">
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger"><a class="dropdown-item">New Arrivals</a></li>
                    <ul class="dropdown-content-two"
                        style="width: 200px; height: auto; top: 100%;left: 80px; padding: 10px;"
                        aria-labelledby="newarrivals">
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalscentral.html">Central</a>
                        </li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalseastern.html">Eastern</a>
                        </li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalswestern.html">Western</a>
                        </li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="newarrivalsnorthern.html">Northern</a></li>
                    </ul>
                </div>
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger"><a class="dropdown-item">Vendors/Shops</a></li>
                    <ul class="dropdown-content-two"
                        style="width: 200px; height: auto; top: 100%;left: 80px; padding: 10px;">
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="vendorshops-apparel.html">Apparel &
                                Accessories</a></li>
                        <li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="vendorshops-shoes.html">Shoes</a></li>
                        <!-- <li class="li-2"><a class="dropdown-item-two" href=""> Kids & Toys</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Lights & Lighting</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Medical Supplies</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Rubber & Plastics</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Beauty & Cosmetics</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Sports & Entertainment</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Jewelry</a></li> -->
                        <!-- <li class="li-2"><a class="dropdown-item-two" href="">Gift Hampers</a></li> -->
                    </ul>
                </div>
                <li class="li-1"><a class="dropdown-item" href="offers&discounts.html">Offers & Discounts</a>
                </li>
                <li class="li-1"><a class="dropdown-item" href="recentlyviewed.html">Recently Viewed</a></li>
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger"><a class="dropdown-item">Popular Searches</a></li>
                    <div class="dropdown-content-two mobile-viewport-dropdown"
                        style="width: 100vw; height: auto; top: 100%;left: -50px; padding: 10px;"
                        aria-labelledby="why-choose-us">
                        <div class="container shadow px-1 py-3"
                            style="display: flex; flex-direction: row; gap: 2px; cursor: pointer;">
                            <div>
                                <h5 class="mb-2 mt-0" style="color: rgb(241, 90, 36);"><b>Items currently
                                        gaining attention.</b></h5>
                                <hr class="mb-0 mt-0">
                                <?php if ($popularSearchTerms !== []) { ?>
                                    <?php foreach ($popularSearchTerms as $popularSearchTerm) { ?>
                                        <p class="mb-0 mt-0" style="color: rgb(0, 0, 255); cursor: pointer;"
                                            onclick="location.href='<?php echo htmlspecialchars('index.php?search=' . urlencode($popularSearchTerm), ENT_QUOTES, 'UTF-8'); ?>'">
                                            <?php echo htmlspecialchars(jomuTruncatePopularSearchLabel(ucwords($popularSearchTerm))); ?>
                                        </p>
                                    <?php } ?>
                                <?php } else { ?>
                                    <p class="mb-0 mt-0" style="color: rgb(108, 117, 125);">
                                        No tracked searches yet.</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="home">
                <span>
                    <img src="assets/images/icons/Home Icon 2.png" class="img-fluid options-icons">
                </span>Home</button>
            <ul class="dropdown-content" style="left: -40px;" aria-labelledby="home">
                <?php if (!$isLogged) { ?>
                    <li class="li-1"><a class="dropdown-item" href="createaccount.html">Join as Seller</a></li>
                <?php } ?>
                <!-- <div class="dropdown-two">
                    <li class="li-1"><a class="dropdown-item">Top-Rated Sellers</a></li>
                    <ul class="dropdown-content-two"
                        style="width: 200px; height: auto; top: 100%;left: 80px; padding: 10px;"
                        aria-labelledby="top-rated">
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellerscentral.html">Central</a></li>
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellerseastern.html">Eastern</a></li>
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellerswestern.html">Western</a></li>
                        <li class="li-2"><a class="dropdown-item-two"
                                href="topratedsellersnorthern.html">Northern</a></li>
                    </ul>
                </div> -->
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger"><a class="dropdown-item">Links</a></li>
                    <ul class="dropdown-content-two"
                        style="width: 200px; height: auto; top: 100%;left: 80px; padding: 10px;"
                        aria-labelledby="appdownload">
                        <?php if ($siteLinks['app'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['app']); ?>">JoMu Application</a></li><?php endif; ?>
                        <?php if ($siteLinks['facebook'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['facebook']); ?>">Facebook</a></li><?php endif; ?>
                        <?php if ($siteLinks['instagram'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['instagram']); ?>">Instagram</a></li><?php endif; ?>
                        <?php if ($siteLinks['tiktok'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['tiktok']); ?>">Tiktok</a></li><?php endif; ?>
                        <?php if ($siteLinks['x'] !== ''): ?><li class="li-2"><a class="dropdown-item-two" style="text-decoration: none;" href="<?php echo htmlspecialchars($siteLinks['x']); ?>">X</a></li><?php endif; ?>
                    </ul>
                </div>
                <div class="dropdown-two">
                    <li class="li-1 submenu-trigger"><a class="dropdown-item">Why Choose Us?</a></li>
                    <div class="dropdown-content-two mobile-viewport-dropdown"
                        style="width: 100vw; height: auto; top: 100%;left: -110px; padding: 10px;"
                        aria-labelledby="why-choose-us">
                        <div class="container shadow"
                            style="display: flex; flex-direction: column; gap: 2px; cursor: pointer; padding: 20px;">
                            <div>
                                <h5 class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><u>Why Buyers Choose
                                            Us?</u></b></h5>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Wide
                                        selection of Wholesale Products.</b></p>
                                <p class="mb-0 mt-0" style="margin-right: 2px;">Access a variety of verified
                                    businesses offering bulk goods
                                    in
                                    different categories.
                                </p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Easy Comparison &
                                        Browsing.</b></p>
                                <p class="mb-0 mt-0">JoMu lets you compare offers, check profiles, and make
                                    informed
                                    decisions.</p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Direct
                                        Communication</b></p>
                                <p class="mb-0 mt-0">Chat directly with sellers, no middlemen, no delays.</p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><span
                                        style="color: rgb(241, 90, 36);">&#9642;</span><b>Save Time & Costs</b>
                                </p>
                                <p class="mb-0 mt-0">Quickly find bulk deals without the need to travel or go
                                    through
                                    agents.</p>
                                <p class="mb-0 mt-0" style="color: rgb(241, 90, 36);"><b><span
                                            style="color: rgb(241, 90, 36);">&#9642;</span>Mobile-friendly
                                        Experience</b></p>
                                <p class="mb-0 mt-0">Find suppliers, make deals, and manage inquiries right from
                                    your mobile device.</p>
                            </div>
                            <div>
                                <h5 class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><u>Why Sellers Choose
                                            Us?</u></b></h5>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><span
                                        style="color: rgb(0, 0, 255);">&#9642;</span><b>Reach
                                        Serious Buyers Only.</b></p>
                                <p class="mb-0 mt-0">We connect you with businesses that are ready to buy in
                                    bulk.</p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>Showcase Your Business
                                        Profile.</b></p>
                                <p class="mb-0 mt-0">Build a professional presence with your products, contact
                                    info, and
                                    business credibility.</p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>No
                                        Complex Setup</b></p>
                                <p class="mb-0 mt-0">Create an account, list your products and start selling.
                                    It's that
                                    easy.</p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>Increase Visibility</b>
                                </p>
                                <p class="mb-0 mt-0">Get discovered by businesses across different regions and
                                    sectors.
                                </p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b><span
                                            style="color: rgb(0, 0, 255);">&#9642;</span>Communicate Directly
                                        with Clients.</b></p>
                                <p class="mb-0 mt-0">Receive messages directly from potential buyers, reducing
                                    delays.
                                </p>
                                <p class="mb-0 mt-0" style="color: rgb(0, 0, 255);"><b>&#9642;Grow
                                        Your Brand.</b></p>
                                <p class="mb-0 mt-0">Whether you're a wholesaler or manufacturer, we help you
                                    grow your
                                    network and customer base.</p>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- <li class="li-1"><a class="dropdown-item" href="">Partners/Brands</a></li> -->
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="categories">
                <span>
                    <img src="assets/images/icons/Categories personal icon.png" class="img-fluid options-icons">
                </span>Categories</button>
            <ul class="dropdown-content" style="left: -100px;" aria-labelledby="categories">
                <li class="li-1"><a class="dropdown-item" href="categoriesapparel.html">Apparel/Ndiboota</a>
                </li>
                <li class="li-1"><a class="dropdown-item" href="categorieswholesale&retail.html">Wholesale &
                        Retail</a></li>
                <li class="li-1"><a class="dropdown-item" href="categorieselectronics&gagdets.html">Electronics
                        & Gadgets</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesagriculture&produce.html">Agriculture
                        & Produce</a></li>
                <li class="li-1"><a class="dropdown-item" href="categorieslivestock&animals.html">Livestock &
                        Animals</a></li>
                <li class="li-1"><a class="dropdown-item"
                        href="categoriesconstruction&building.html">Construction & Building Materials</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesofficesupply&stationery.html">Office
                        Supplies & Stationery</a></li>
                <li class="li-1"><a class="dropdown-item" href="categorieshealth&beauty.html">Health &
                        Beauty</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesfood&beverages.html">Food &
                        Beverages</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesautomotive&transport.html">Automotive
                        & Transport</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesfurniture&home.html">Furniture & Home
                        Decor</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesit&software.html">IT & Software
                        Accessories</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesprinting&branding.html">Printing &
                        Branding</a></li>
                <li class="li-1"><a class="dropdown-item" href="categoriesgeneralservices.html">General
                        Services</a></li>
            </ul>
        </div>
    </div>
</nav>
<!-- Navbar two for small and medium screens from end  -->
<nav class="navbar navbar-expand-lg navbar-light d-block d-lg-none navbartwo">
    <div class="containerone containerone-left">
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="business">
                <span>
                    <img src="assets/images/icons/Business Icon.png" class="img-fluid options-icons">
                </span>Business</button>
            <ul class="dropdown-content" style="width: 200px; height: auto; top: 30px;left: 2px; padding: 10px;"
                aria-labelledby="business">
                <li class="li-1"><a class="dropdown-item" href="businessbulkorders.html">Bulk Orders</a></li>
                <!-- <li class="li-1"><a class="dropdown-item" href="">Wholesale Marketplace</a></li> -->
                <?php if (!$isAdminLogged): ?>
                    <li class="li-1"><a class="dropdown-item" href="php/businessvendordashboard.php">Vendor
                            Dashboard</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="suggested">
                <span>
                    <img src="assets/images/icons/Suggested Icon.png" class="img-fluid options-icons">
                </span>Suggested</button>
            <ul class="dropdown-content" style="width: 200px; height: auto; top: 30px;left: 2px; padding: 10px;"
                aria-labelledby="suggested">
                <!-- Listings trending in the users category.  -->
                <li class="li-1"><a class="dropdown-item" href="suggestedtoppicks.php">Top Picks</a></li>
                <!-- Businesses of same category as the user.  -->
                <li class="li-1"><a class="dropdown-item" href="suggestedsamecategory.html">Same Category</a>
                </li>
            </ul>
        </div>
        <div class="dropdown">
            <button class="button nav-options hover-underline" id="trending">
                <span>
                    <img src="assets/images/icons/Trending Icon.png" class="img-fluid options-icons">
                </span>Trending</button>
            <ul class="dropdown-content"
                style="width: 184px; height: auto; top: 30px;left: -80px; padding: 10px;"
                aria-labelledby="trending">
                <!-- <li class="li-1"><a class="dropdown-item" href="trendinghotdeals.html">Hot Deals</a></li> -->
                <li class="li-1"><a class="dropdown-item" href="trendingseasonaltrends.html">Seasonal Trends</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<script>
    (function () {
        const mobileAuthButton = document.getElementById('mobileAuthMenuButton');
        const mobileAuthMenu = document.querySelector('.mobile-auth-menu');
        if (!mobileAuthButton || !mobileAuthMenu) {
            return;
        }

        mobileAuthButton.removeAttribute('data-bs-toggle');

        function setMobileAuthOpen(isOpen) {
            mobileAuthMenu.classList.toggle('show', isOpen);
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

    (function () {
        const searchInputs = Array.from(document.querySelectorAll('.searchbar, .searchbar-medium, .searchbar-small'));
        const searchButtons = Array.from(document.querySelectorAll('.searching .search, .searching-medium button, .searching-small button'));

        function findSearchValue(preferredInput) {
            const candidates = preferredInput ? [preferredInput, ...searchInputs] : searchInputs;
            for (const input of candidates) {
                const value = String(input?.value || '').trim();
                if (value !== '') {
                    return value;
                }
            }

            return '';
        }

        function trackSearch(term) {
            const payload = JSON.stringify({ term });

            if (navigator.sendBeacon) {
                const blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon('/php/track_search_interest.php', blob);
                return;
            }

            fetch('/php/track_search_interest.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true,
            }).catch(() => {});
        }

        searchButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const relatedInput = button.closest('.searching, .searching-medium, .searching-small')?.querySelector('input');
                const term = findSearchValue(relatedInput);
                if (term !== '') {
                    trackSearch(term);
                }
            });
        });

        searchInputs.forEach((input) => {
            input.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }

                const term = findSearchValue(input);
                if (term !== '') {
                    trackSearch(term);
                }
            });
        });
    })();
</script>
