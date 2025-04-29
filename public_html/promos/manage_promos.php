<?php
// manage_promo.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/user_actions_config.php';
require_once __DIR__ . '/../../config/promos/promo_functions.php';

use Carbon\Carbon;

startSession();

// Step 1: Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    $baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']);
    header("Location: " . $baseUrl . "login");
    exit();
}

// Step 2: Retrieve user information from the session and database.
$userInfo = getUserInfo($_SESSION['user_id'], $config, $env);
$profileImage = null;

// Step 3: Handle cases where the user is not found in the database.
if (!$userInfo) {
    handleError("User not found in the database. Redirecting...", $_ENV['ENVIRONMENT']);
    exit();
}

// Step 4: Set the user profile image. If not available, use a default image.
$profileImage = $userInfo['image_filename'] ?? 'default_profile_image.jpg';
$profileImageUrl = $baseUrl . "uploads/profile_images/" . $profileImage;

// Restrict access to non-admin users.
if ($userInfo['role'] !== 'admin') {
    handleError("Access denied! Role: " . $userInfo['role'], $_ENV['ENVIRONMENT']);
    header("Location: " . $baseUrl . "login");
    exit();
}

// Retrieve product categories from the database.
$categories = getPromoCategories($config, $env);

// Load dynamic URL configuration.
$config = getEnvironmentConfig();
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']);
$isLive = $config['is_live'];
$pdo = getPDOConnection($config, $env);

// Set no-cache headers in the local environment.
setCacheHeaders($isLive);

// Set security headers.
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Promos - Sarjana Canggih Indonesia</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $baseUrl; ?>favicon.ico" />
    <!-- Bootstrap css -->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/vendor/css/bootstrap.min.css" />
    <!-- Slick Slider css -->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/vendor/css/slick.min.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/vendor/css/slick-theme.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" type="text/css"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <!-- Tagify CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/css/styles.css" />
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/css/halaman-admin.css" />
</head>

<body style="background-color: #f7f9fb;">

    <!--========== INSERT HEADER ==========-->
    <?php include __DIR__ . '/../includes/admin-navbar.php'; ?>
    <!--========== AKHIR INSERT HEADER.PHP ==========-->

    <!--========== AREA SCROLL TO TOP ==========-->
    <div class="scroll">
        <!-- Scroll to Top Button -->
        <a href="#" class="scroll-to-top" id="scrollToTopBtn">
            <i class="fa-solid fa-angles-up"></i>
        </a>
    </div>
    <!--========== AKHIR AREA SCROLL TO TOP ==========-->

    <!--========== AREA MANAGE PROMO ==========-->
    <div class="area-konten-manage-promos">
        <div class="container">
            <!-- Judul Halaman -->
            <section class="judul-halaman-admin-dashboard">
                <h2 class="fs-1 my-5 text-center fw-bold">Manage Promos</h2>
            </section>

            <!-- User Info Section & Admin Navigation -->
            <div class="row mb-4">
                <!-- User Info -->
                <div class="col-md-6 user-info-halaman-admin">
                    <div class="card shadow-sm border-0 overflow-hidden">
                        <div class="card-header bg-primary bg-gradient text-white py-3 position-relative">
                            <h5 class="mb-0 fw-semibold">
                                <i class="fa-solid fa-user-shield me-2"></i>Admin Profile
                            </h5>
                            <div class="header-accent"></div>
                        </div>
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                                <!-- Profile Image -->
                                <div class="position-relative">
                                    <img src="<?php echo htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                        alt="Profile Image" class="profile-img shadow-sm rounded-circle"
                                        data-bs-toggle="tooltip" title="Admin Profile Picture">
                                </div>

                                <!-- User Details -->
                                <div class="flex-grow-1 w-100">
                                    <div class="d-flex flex-column gap-3">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-user-tag fs-5 text-primary me-3"></i>
                                            <div>
                                                <div class="text-muted small">USERNAME</div>
                                                <div class="h5 mb-0 fw-semibold">
                                                    <?php echo htmlspecialchars($userInfo['username']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-envelope fs-5 text-primary me-3"></i>
                                            <div>
                                                <div class="text-muted small">EMAIL</div>
                                                <div class="h5 mb-0 fw-semibold">
                                                    <?php echo htmlspecialchars($userInfo['email']); ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-user-gear fs-5 text-primary me-3"></i>
                                            <div>
                                                <div class="text-muted small">ROLE</div>
                                                <div class="h5 mb-0 fw-semibold">
                                                    <span
                                                        class="badge bg-primary bg-gradient"><?php echo htmlspecialchars($userInfo['role']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigasi Halaman Admin -->
                <div class="col-md-6 navigasi-halaman-admin">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="mb-0 text-primary fw-semibold">
                                <i class="fa-solid fa-compass me-2"></i>Quick Navigation
                            </h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-2">
                                <div class="col-6 mb-2">
                                    <a href="<?php echo $baseUrl; ?>admin-dashboard"
                                        onclick="if(document.referrer) { if(confirm('Kembali ke halaman Admin Dashboard?')) { history.back(); } return false; }"
                                        class="btn btn-light w-100 h-100 p-3 text-start border-hover">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-arrow-left fs-4 text-secondary me-2"></i>
                                            <span class="fw-medium">Back</span>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 mb-2">
                                    <a href="<?php echo $baseUrl; ?>manage_users"
                                        class="btn btn-light w-100 h-100 p-3 text-start border-hover">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-users fs-4 text-primary me-2"></i>
                                            <span class="fw-medium">Users</span>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 mb-2">
                                    <a href="<?php echo $baseUrl; ?>manage_promos"
                                        class="btn btn-light w-100 h-100 p-3 text-start border-hover">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-percent fs-4 text-danger me-2"></i>
                                            <span class="fw-medium">Promos</span>
                                        </div>
                                    </a>
                                </div>
                                <div class="col-6 mb-2">
                                    <a href="#" class="btn btn-light w-100 h-100 p-3 text-start border-hover">
                                        <div class="d-flex align-items-center">
                                            <i class="fa-solid fa-pen-nib fs-4 text-warning me-2"></i>
                                            <span class="fw-medium">Blogs</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="mb-4 d-flex">
                <input type="text" class="form-control flex-grow-1" id="searchInput"
                    placeholder="Cari promo berdasarkan nama">
                <button class="btn btn-primary ms-3 d-inline-flex align-items-center">
                    <i class="fas fa-search me-2"></i>
                    Search
                </button>
            </div>

            <!-- Filter by Category Section -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-primary-subtle">
                    <h5 class="mb-0">Filter by Category</h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <select class="form-select w-45" id="categoryFilter" aria-label="Filter by Category">
                        <option value="" selected>All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['promo_category_id']) ?>">
                                <?= htmlspecialchars($category['promo_category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

        </div>
    </div>
    <!--========== AKHIR AREA MANAGE PROMO ==========-->

    <!--================ AREA FOOTER =================-->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <!--================ AKHIR AREA FOOTER =================-->

    <!-- External JS libraries -->
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/vendor/js/jquery-slim.min.js"></script>
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/vendor/js/popper.min.js"></script>
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/vendor/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/js/custom.js"></script>
</body>

</html>