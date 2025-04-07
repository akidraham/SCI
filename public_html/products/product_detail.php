<?php
// product_detail.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/products/product_functions.php';
require_once __DIR__ . '/../../config/api/api_functions.php';

// Start session if not already started, from config.php
startSession();

// Load konfigurasi URL dinamis berdasarkan environment
$config = getEnvironmentConfig(); // Load dynamic URL configuration based on the environment, from config.php
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']); // retrieves the appropriate base URL based on the environment, from config.php
$isLiveEnvironment = isLive(); // Check if the environment is live, from config.php
setCacheHeaders($isLiveEnvironment); // Set cache headers based on the environment, from config.php

$slug = $_GET['slug'] ?? null;

if (!$slug) {
    header("HTTP/1.0 404 Not Found");
    exit("Produk tidak ditemukan");
}

// Retrieves an active product from the database based on the slug, function from api_functions.php
$product = getActiveProductBySlug($slug);

if (!$product) {
    header("HTTP/1.0 404 Not Found");
    exit("Produk tidak ditemukan");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['product_name']) ?></title>
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

<body>
    <!-- PLACEHOLDER -->
    <h1 class="fs-1"><?= htmlspecialchars($product['product_name']) ?></h1>

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
</body>

</html>