<?php
// products.php

// Load config dan dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/user_actions_config.php';
require_once __DIR__ . '/../../config/products/product_functions.php';
require_once __DIR__ . '/../../config/api/api_functions.php';

// Mulai session jika belum dimulai
startSession();

// Load konfigurasi URL dinamis berdasarkan environment
$config = getEnvironmentConfig();
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']);
$isLive = $config['is_live'];
$isLiveEnvironment = ($config['BASE_URL'] === $_ENV['LIVE_URL']);
setCacheHeaders($isLive);

// Ambil data produk aktif
$activeProducts = getFilteredActiveProducts(categoryNames: null, minPrice: null, maxPrice: null, sortBy: 'created', sortOrder: 'DESC');

// Ambil daftar kategori
$categories = getCategoriesWithActiveProducts($config, $env);

// Proses data produk untuk ditampilkan
$productsData = [];
if (!empty($activeProducts)) {
    foreach ($activeProducts as $product) {
        $productsData[] = [
            'image' => !empty($product['image_path']) ? $baseUrl . $product['image_path'] : $baseUrl . 'assets/images/default-product.png',
            'name' => htmlspecialchars($product['product_name']),
            'description' => htmlspecialchars($product['description']),
            'price' => $product['currency'] . ' ' . number_format($product['price_amount'], 0, ',', '.'),
            'slug' => htmlspecialchars($product['slug']),
            'product_name' => htmlspecialchars($product['product_name']),
            'image_path' => !empty($product['image_path']) ? $baseUrl . $product['image_path'] : $baseUrl . 'assets/images/default-product.png'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Sarjana Canggih Indonesia</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $baseUrl; ?>favicon.ico" />
    <!-- Bootstrap css -->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/vendor/css/bootstrap.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" type="text/css"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/css/styles.css" />
</head>

<body style="background-color: #f7f9fb;">

    <!--========== INSERT HEADER ==========-->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <!--========== AKHIR INSERT HEADER ==========-->

    <!--========== AREA SCROLL TO TOP ==========-->
    <div class="scroll">
        <!-- Scroll to Top Button -->
        <a href="#" class="scroll-to-top" id="scrollToTopBtn">
            <i class="fa-solid fa-angles-up"></i>
        </a>
    </div>
    <!--========== AKHIR AREA SCROLL TO TOP ==========-->

    <!--========== AREA BANNER ==========-->
    <section class="py-5 bg-banner-halaman-products text-white">
        <div class="container py-5">
            <div class="row justify-content-center align-items-center">
                <div class="col-lg-8 text-center">
                    <h1 class="display-1 fw-bold mb-4">Products</h1>
                    <p class="lead mb-0">Explore our high-quality solutions tailored for your success.</p>
                </div>
            </div>
        </div>
    </section>
    <!--========== AKHIR AREA BANNER ==========-->

    <!--========== AREA FILTER PRODUCTS ==========-->
    <div class="container my-4 filter-halaman-products">
        <div class="row g-3 align-items-end">
            <!-- Kategori -->
            <div class="col-md-3">
                <label for="halamanProductsCategoryFilter" class="form-label">Category</label>
                <select class="form-select" id="halamanProductsCategoryFilter">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?= htmlspecialchars($category['category_name']) ?>">
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Min Price -->
            <div class="col-md-2">
                <label for="halamanProductsMinPrice" class="form-label">Min Price</label>
                <input type="number"
                    class="form-control"
                    id="halamanProductsMinPrice"
                    placeholder="Min"
                    inputmode="numeric" pattern="[0-9]*">
            </div>

            <!-- Max Price -->
            <div class="col-md-2">
                <label for="halamanProductsMaxPrice" class="form-label">Max Price</label>
                <input type="number"
                    class="form-control"
                    id="halamanProductsMaxPrice"
                    placeholder="Max"
                    inputmode="numeric" pattern="[0-9]*">
            </div>

            <!-- Sorting -->
            <div class="col-md-2">
                <label for="halamanProductsSortBy" class="form-label">Sort By</label>
                <select class="form-select" id="halamanProductsSortBy">
                    <option value="latest">Latest</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                </select>
            </div>

            <!-- Apply Button -->
            <div class="col-md-2">
                <button class="btn btn-primary w-100" id="halamanProductsApplyFilter">
                    <i class="fas fa-filter me-2"></i>Apply
                </button>
            </div>

            <!-- Clear Filter Button -->
            <div class="col-md-1">
                <button class="btn btn-outline-danger w-100" id="halamanProductsClearFilter">
                    <i class="fas fa-times me-2"></i>Clear
                </button>
            </div>
        </div>
    </div>
    <!--========== AKHIR AREA FILTER PRODUCTS ==========-->

    <!--========== AREA KONTEN PRODUCTS ==========-->
    <div class="area-konten-halaman-products">
        <div class="container">
            <?php if (!empty($productsData)) : ?>
                <div id="halamanProductsContainer">
                    <?php foreach ($productsData as $product) : ?>
                        <div class="col mb-4">
                            <div class="card h-100">
                                <img src="<?= $product['image'] ?>" class="card-img-top" alt="<?= $product['name'] ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?= $product['name'] ?></h5>
                                    <p class="card-text"><?= $product['description'] ?></p>
                                    <p class="text-primary fw-bold"><?= $product['price'] ?></p>
                                    <a href="<?php echo $baseUrl; ?>products/<?php echo $product['slug']; ?>/" class="btn btn-primary btn-sm mt-2">
                                        <i class="fa-solid fa-circle-info me-1"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info" role="alert">
                    No products found.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!--========== AKHIR AREA KONTEN PRODUCTS ==========-->

    <!--========== AREA PAGINATION PRODUCTS ==========-->
    <section class="halamanProductsPagination">
        <div class="container my-4">
            <div id="paginationContainer"></div>
        </div>
    </section>
    <!--========== AKHIR AREA PAGINATION PRODUCTS ==========-->

    <!--================ AREA FOOTER =================-->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <!--================ AKHIR AREA FOOTER =================-->

    <!-- External JS libraries -->
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/vendor/js/jquery-slim.min.js"></script>
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/vendor/js/popper.min.js"></script>
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/vendor/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/vendor/js/slick.min.js"></script>
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/js/custom.js"></script>
    <script>
        // Tambahkan BASE_URL global
        const BASE_URL = '<?= $baseUrl ?>';
    </script>
    <!-- Custom JS -->
    <script type="text/javascript" src="<?php echo $baseUrl; ?>assets/js/products/halaman-products.js"></script>
</body>

</html>