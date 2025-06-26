<?php
// manage_promos.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/user_actions_config.php';
require_once __DIR__ . '/../../config/promos/promo_functions.php';
require_once __DIR__ . '/../../config/products/product_functions.php';

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

// Load products for the promo form.
$products = getProducts($config, $env);

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

            <!-- Add Promo Modal -->
            <div class="modal fade" id="addPromoModal" tabindex="-1" aria-labelledby="addPromoModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addPromoModalLabel">Add New Promo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Form Promo Baru -->
                            <form id="addPromoForm" action="<?php echo $baseUrl; ?>manage_promos" method="POST">
                                <!-- Bagian Nama Promo -->
                                <div class="mb-3">
                                    <label for="promoName" class="form-label">Nama Promo</label>
                                    <input type="text" class="form-control" id="promoName" name="promoName" required>
                                </div>

                                <!-- Bagian Kode Promo -->
                                <div class="mb-3">
                                    <label for="promoCode" class="form-label">Kode Promo</label>
                                    <input type="text" class="form-control" id="promoCode" name="promoCode" required>
                                </div>

                                <!-- Bagian Deskripsi Promo -->
                                <div class="mb-3">
                                    <label for="promoDescription" class="form-label">Masukkan Deskripsi Promo</label>
                                    <textarea class="form-control" id="promoDescription" name="promoDescription" rows="3"></textarea>
                                </div>

                                <!-- Bagian Tipe Diskon -->
                                <div class="mb-3">
                                    <label class="form-label">Tipe Promo</label>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <select class="form-select" id="discountType" name="discountType" required>
                                                <option value="" selected disabled>Pilih Tipe Promo</option>
                                                <option value="percentage">Percentage</option>
                                                <option value="fixed">Jumlah Tetap</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="discountValue" name="discountValue" step="0.01" min="0" required>
                                                <span class="input-group-text" id="discountSuffix">%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Max Discount (Hanya untuk tipe percentage) -->
                                <div class="mb-3" id="maxDiscountField" style="display: none;">
                                    <label for="maxDiscount" class="form-label">Maksimal Diskon</label>
                                    <div class="input-group">
                                        <span class="input-group-text">IDR</span>
                                        <input type="number" class="form-control" id="maxDiscount" name="maxDiscount" step="1000" min="0">
                                        <span class="input-group-text">,00</span>
                                    </div>
                                    <ksimal class="form-text">Jumlah maksimal diskon untuk promo berbasis persentase.
                                </div>
                                </div>

                                <!-- Kategori Promo -->
                                <div class="mb-3">
                            <label for="promoCategory" class="form-label">Kategori</label>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <select class="form-select" id="mainPromoCategory" name="mainPromoCategory" required>
                                        <option value="" selected disabled>Pilih Kategori Utamaa</option>
                                                <?php foreach ($mainCategories as $id => $name): ?>
                                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <select class="form-select" id="subPromoCategory" name="subcategory_id" required>
                                        <option value="" selected disabled>Pilih Sub Kategori</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <?php if ($cat['main_category_id']): ?>
                                                        <option class="subcat-option subcat-<?php echo $cat['main_category_id']; ?>"
                                                            value="<?php echo $cat['subcategory_id']; ?>"
                                                            style="display: none;">
                                                            <?php echo htmlspecialchars($cat['subcategory_name']); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                        <!-- Masa Berlaku Promo -->
                                <div class="mb-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="infiniteDuration" name="infiniteDuration">
                                <label class="form-check-label" for="infiniteDuration">Durasi Tak Terbatas</label>
                            </div>
                            <label class="form-label">Masa Berlaku Promo</label>
                            <div class="row g-2" id="dateFields">
                                        <div class="col-md-6">
                                    <label for="startDate" class="form-label small text-muted">Tanggal Mulai</label>
                                    <input type="datetime-local" class="form-control" id="startDate" name="startDate">
                                        </div>
                                        <div class="col-md-6">
                                    <label for="endDate" class="form-label small text-muted">Tanggal Akhir</label>
                                    <input type="datetime-local" class="form-control" id="endDate" name="endDate">
                                        </div>
                                    </div>
                                </div>

                        <!-- Produk yang Berlaku untuk Promo -->
                                <div class="mb-3">
                            <label class="form-label">Pilih Produk Promo</label>

                                    <!-- Search and filter bar -->
                                    <div class="input-group mb-2">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" id="productSearch" placeholder="Cari produk...">
                                    </div>

                                    <!-- Products table with checkboxes -->
                                    <div class="border rounded" style="max-height: 300px; overflow-y: auto;">
                                        <table class="table table-hover mb-0">
                                            <thead class="sticky-top bg-light">
                                                <tr>
                                                    <th scope="col" style="width: 20px;">
                                                        <input type="checkbox" class="form-check-input" id="selectAllProducts">
                                                    </th>
                                            <th scope="col">Nama Produk</th>
                                            <th scope="col" class="text-end">Harga</th>
                                                </tr>
                                            </thead>
                                            <tbody id="productList">
                                                <?php foreach ($products as $product): ?>
                                                    <tr class="product-row">
                                                        <td>
                                                            <input type="checkbox" class="form-check-input product-check"
                                                                name="applicableProducts[]"
                                                                value="<?php echo $product['product_id']; ?>">
                                                        </td>
                                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                        <td class="text-end">
                                                            IDR <?php echo number_format($product['price_amount'], 0, ',', '.'); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                            <div class="mt-1 text-muted small" id="selectedCount">0 produk yang dipilih.</div>
                                </div>

                                <!-- Kelayakan & Minimal Pembelian -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                <label for="eligibility" class="form-label">Syarat Promo</label>
                                        <select class="form-select" id="eligibility" name="eligibility" required>
                                    <option value="all" selected>Semua User</option>
                                    <option value="referral">Hanya Referral</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                <label for="minPurchase" class="form-label">Minimal Pembelian</label>
                                        <div class="input-group">
                                            <span class="input-group-text">IDR</span>
                                            <input type="number" class="form-control" id="minPurchase" name="minPurchase" step="1000" min="0" value="0">
                                            <span class="input-group-text">,00</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Maksimal Klaim -->
                                <div class="mb-3">
                            <label for="maxClaims" class="form-label">Maksimal Jumlah Klaim per User</label>
                                    <input type="number" class="form-control" id="maxClaims" name="maxClaims" min="0" value="0">
                                    <div class="form-text">0 = unlimited claims</div>
                                </div>

                                <!-- Opsi Tambahan -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="autoApply" name="autoApply" value="1">
                                            <label class="form-check-label" for="autoApply">Auto Apply</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="promoStatus" name="promoStatus" value="active" checked>
                                    <label class="form-check-label" for="promoStatus">Aktifkan Promo</label>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary" id="savePromoBtn">Save Promo</button>
                                </div>
                            </form>
                        </div>
                    </div>
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
    <!-- Load baseUrl for JS -->
    <script>
        const BASE_URL = '<?= $baseUrl ?>';
    </script>
    <script>
        // Script untuk menangani perubahan tipe diskon
        document.getElementById('discountType').addEventListener('change', function() {
            const discountType = this.value;
            const maxDiscountField = document.getElementById('maxDiscountField');
            const discountSuffix = document.getElementById('discountSuffix');

            if (discountType === 'percentage') {
                maxDiscountField.style.display = 'block';
                discountSuffix.textContent = '%';
            } else {
                maxDiscountField.style.display = 'none';
                discountSuffix.textContent = 'IDR';
            }
        });

        // Script untuk menangani perubahan kategori utama
        document.getElementById('mainPromoCategory').addEventListener('change', function() {
            const mainCatId = this.value;
            const subOptions = document.querySelectorAll('#subPromoCategory option.subcat-option');

            // Sembunyikan semua opsi subkategori
            subOptions.forEach(option => {
                option.style.display = 'none';
            });

            // Tampilkan hanya opsi yang sesuai dengan kategori utama
            const validOptions = document.querySelectorAll(`.subcat-${mainCatId}`);
            validOptions.forEach(option => {
                option.style.display = 'block';
            });

            // Reset pilihan subkategori
            document.getElementById('subPromoCategory').value = '';
        });

        // Inisialisasi datepicker dengan tanggal minimal hari ini
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const today = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);

            document.getElementById('startDate').min = today;
            document.getElementById('endDate').min = today;

            // Update min end date saat start date berubah
            document.getElementById('startDate').addEventListener('change', function() {
                document.getElementById('endDate').min = this.value;
            });
        });
    </script>
    <script>
        // Product search functionality
        document.getElementById('productSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.product-row');

            rows.forEach(row => {
                const productName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                row.style.display = productName.includes(searchTerm) ? '' : 'none';
            });
        });

        // Select all functionality
        document.getElementById('selectAllProducts').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-check');
            checkboxes.forEach(checkbox => {
                if (checkbox.closest('tr').style.display !== 'none') {
                    checkbox.checked = this.checked;
                }
            });
            updateSelectedCount();
        });

        // Update selected count
        function updateSelectedCount() {
            const selected = document.querySelectorAll('.product-check:checked').length;
            document.getElementById('selectedCount').textContent =
                `${selected} product${selected !== 1 ? 's' : ''} selected`;
        }

        // Initialize count and add event listeners
        document.querySelectorAll('.product-check').forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        updateSelectedCount();
    </script>
</body>

</html>