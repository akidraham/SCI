<?php
// manage_promos.php

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

// Retrieve promo categories from the database.
$categories = getPromoCategories($config, $env);

// Create an array of unique main categories
$mainCategories = [];
foreach ($categories as $cat) {
    $mainCategoryId = $cat['main_category_id'];
    $mainCategoryName = $cat['main_category_name'];

    if ($mainCategoryId !== null && !isset($mainCategories[$mainCategoryId])) {
        $mainCategories[$mainCategoryId] = $mainCategoryName;
    }
}

// Load dynamic URL configuration.
$config = getEnvironmentConfig();
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']);
$isLive = $config['is_live'];
$pdo = getPDOConnection($config, $env);

// Set security headers.
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// Set no-cache headers in the local environment.
setCacheHeaders($isLive);

// Handle success/error messages and update cache headers
$flash = processFlashMessagesAndHeaders($isLive);
$successMessage = $flash['success'];
$errorMessage = $flash['error'];
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
                                        onclick="if(confirm('Kembali ke halaman Admin Dashboard?')) { window.location.href='<?php echo $baseUrl; ?>admin-dashboard'; return false; } else { return false; }"
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
                        <?php foreach ($mainCategories as $id => $name): ?>
                            <option value="<?= htmlspecialchars($id) ?>">
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!--========== AREA GENERIC FLASH MESSAGES ==========-->
            <div class="area-generic-flash-messages mb-4">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($successMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($errorMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            </div>
            <!--========== AKHIR AREA GENERIC FLASH MESSAGES ==========-->

            <!-- Tombol untuk membuka modal Add Promo -->
            <div class="button-add-promo">
                <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addPromoModal">
                    <i class="fas fa-plus"></i> Add Promo
                </button>
            </div>

            <!-- Promos Table -->
            <div class="halaman-manage-promos-bagian-table table-responsive mb-4">
                <table class="table table-bordered table-sm table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>No.</th>
                            <th>Promo Name</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Discount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="promosTableBody">
                        <?php
                        $promos = getAllPromoWithCategories($config, $env);
                        $counter = 1;
                        foreach ($promos as $promo):
                            $encodedId = $optimus->encode($promo['promo_id']);
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_promos[]" value="<?= $promo['promo_id'] ?>"
                                        class="promo-checkbox">
                                    <?= $counter++ ?>
                                </td>
                                <td><?= htmlspecialchars($promo['promo_name']) ?></td>
                                <td><?= htmlspecialchars($promo['category_name'] ?? 'General') ?></td>

                                <!-- Kolom Status Promo -->
                                <td>
                                    <div class="dropdown">
                                        <?php
                                        $allowedStatuses = ['active', 'inactive', 'scheduled', 'expired'];
                                        $status = in_array(strtolower($promo['status']), $allowedStatuses)
                                            ? strtolower($promo['status'])
                                            : 'inactive';

                                        $badgeClass = match ($status) {
                                            'active' => 'success',
                                            'scheduled' => 'info',
                                            'expired' => 'secondary',
                                            default => 'danger'
                                        };
                                        ?>
                                        <button class="btn btn-sm btn-<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?> dropdown-toggle d-flex align-items-center"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                            <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item <?= $status === 'active' ? 'disabled' : '' ?>"
                                                    href="#"
                                                    data-promo-id="<?= intval($promo['promo_id']) ?>"
                                                    data-new-status="active">
                                                    Active
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item <?= $status === 'inactive' ? 'disabled' : '' ?>"
                                                    href="#"
                                                    data-promo-id="<?= intval($promo['promo_id']) ?>"
                                                    data-new-status="inactive">
                                                    Inactive
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item <?= $status === 'scheduled' ? 'disabled' : '' ?>"
                                                    href="#"
                                                    data-promo-id="<?= intval($promo['promo_id']) ?>"
                                                    data-new-status="scheduled">
                                                    Scheduled
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item <?= $status === 'expired' ? 'disabled' : '' ?>"
                                                    href="#"
                                                    data-promo-id="<?= intval($promo['promo_id']) ?>"
                                                    data-new-status="expired">
                                                    Expired
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>

                                <td>
                                    <?php if ($promo['discount_type'] === 'percentage'): ?>
                                        <?= number_format($promo['discount_value'], 0) ?>%
                                    <?php else: ?>
                                        Rp <?= number_format($promo['discount_value'], 0, ',', '.') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Tombol View Details -->
                                    <button class="btn btn-info btn-sm"
                                        onclick="viewPromoDetails(<?= $promo['promo_id'] ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <!-- Tombol Edit -->
                                    <button class="btn btn-warning btn-sm"
                                        onclick="editPromo('<?= $promo['slug'] ?>', <?= $encodedId ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
    <!-- Load baseUrl for JS -->
    <script>
        const BASE_URL = '<?= $baseUrl ?>';
    </script>
</body>

</html>