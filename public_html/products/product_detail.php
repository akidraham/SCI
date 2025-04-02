<?php
// product_detail.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/products/product_functions.php';
require_once __DIR__ . '/../../config/api/api_functions.php';

$config = getEnvironmentConfig(); // Load konfigurasi URL dinamis berdasarkan environment from config.php
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']); // retrieves the appropriate base URL based on the environment from config.php

$slug = $_GET['slug'] ?? null;

if (!$slug) {
    header("HTTP/1.0 404 Not Found");
    exit("Produk tidak ditemukan");
}

// Koneksi database dan query from api_functions.php
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
</body>

</html>