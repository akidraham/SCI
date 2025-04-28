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

// Retrieves an active product information from the database based on the slug, function from api_functions.php
$productInfo = getActiveProductInfoBySlug($slug);

if (!$productInfo) {
    header("HTTP/1.0 404 Not Found");
    exit("Produk tidak ditemukan");
}

// Build WhatsApp link
$phoneNumber = $_ENV['PHONE_NUMBER'] ?? '';
// Get current page URL dengan encoding yang tepat
$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
    . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// message template untuk WhatsApp
$messageTemplate = <<<MSG
Halo kak 👋,

Setelah membaca informasi di website, saya tertarik untuk memesan produk:
%s

URL produk: 
%s

Mohon info lebih lanjut 🙏
MSG;
// Membuat pesan untuk WhatsApp
$messageText = sprintf($messageTemplate, $productInfo['product_name'], $currentUrl);
$encodedMessage = rawurlencode($messageText);
$whatsappLink = "https://wa.me/{$phoneNumber}?text={$encodedMessage}";
// Error handling untuk nomor telepon
if (empty($phoneNumber)) {
    error_log("PHONE_NUMBER not set in .env");
    $whatsappLink = 'javascript:void(0);';
    $whatsappError = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($productInfo['product_name']) ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $baseUrl; ?>favicon.ico" />
    <!-- Bootstrap css -->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/vendor/css/bootstrap.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" type="text/css"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="<?php echo $baseUrl; ?>assets/css/styles.css" />

    <!-- Structured Data for SEO -->
    <?php if ($productInfo): ?>
        <script type="application/ld+json">
            {
                "@context": "https://schema.org",
                "@type": "Service",
                "name": "<?= htmlspecialchars($productInfo['product_name']) ?>",
                "description": "<?= htmlspecialchars(strip_tags($productInfo['description'])) ?>",
                "provider": {
                    "@type": "Organization",
                    "name": "Sarjana Canggih",
                    "url": "https://sarjanacanggihindonesia.com"
                },
                "areaServed": {
                    "@type": "Country",
                    "name": "Indonesia"
                },
                "category": [
                    <?= implode(',', array_map(fn($cat) => '"' . htmlspecialchars($cat) . '"', $productInfo['categories'])) ?>
                ],
                "offers": {
                    "@type": "Offer",
                    "priceCurrency": "<?= isset($productInfo['price']['currency']) ? htmlspecialchars($productInfo['price']['currency']) : 'IDR' ?>",
                    "price": "<?= isset($productInfo['price']['amount']) ? htmlspecialchars($productInfo['price']['amount']) : '0' ?>",
                    "availability": "https://schema.org/InStock"
                }
            }
        </script>
    <?php endif; ?>
</head>

<body>
    <!--========== INSERT HEADER ==========-->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <!--========== AKHIR INSERT HEADER ==========-->

    <!--========== AREA KONTEN DETAIL PRODUK ==========-->
    <div id="halamanDetailProduk-<?= htmlspecialchars($slug) ?>" class="container py-5 jarak-kustom">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $baseUrl ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $baseUrl ?>products">Produk</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($productInfo['product_name']) ?></li>
            </ol>
        </nav>

        <div class="row g-5">
            <!-- Kolom Gambar -->
            <div class="col-lg-6">
                <div id="productCarousel-<?= htmlspecialchars($slug) ?>" class="carousel slide shadow-lg rounded-3" data-bs-ride="carousel">
                    <!-- Bagian carousel -->
                    <div class="carousel-inner">
                        <?php foreach ($productInfo['images'] as $index => $image): ?>
                            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                <img src="<?= $baseUrl . htmlspecialchars($image) ?>"
                                    class="d-block w-100 img-fluid py-3 px-3" role="img"
                                    alt="<?= htmlspecialchars($productInfo['product_name']) ?>"
                                    style="max-height: 600px; object-fit: contain;">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Tombol navigasi carousel hanya ditampilkan jika ada lebih dari satu gambar -->
                    <?php if (count($productInfo['images']) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel-<?= htmlspecialchars($slug) ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#productCarousel-<?= htmlspecialchars($slug) ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon bg-dark rounded-circle p-3" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Thumbnail Gambar di Bawah Carousel -->
                <div class="d-flex gap-2 mt-3">
                    <?php foreach ($productInfo['images'] as $index => $image): ?>
                        <img src="<?= $baseUrl . htmlspecialchars($image) ?>"
                            class="img-thumbnail" role="img"
                            style="width: 75px; height: 75px; object-fit: cover; cursor: pointer;"
                            onclick="document.querySelector('#productCarousel-<?= htmlspecialchars($slug) ?> .carousel-item.active').classList.remove('active'); 
                      document.querySelectorAll('#productCarousel-<?= htmlspecialchars($slug) ?> .carousel-item')[<?= $index ?>].classList.add('active');" />
                    <?php endforeach; ?>
                </div>

            </div>

            <!-- Kolom Deskripsi -->
            <div class="col-lg-6">
                <div class="d-flex flex-column h-100">
                    <h1 id="productTitle-<?= htmlspecialchars($slug) ?>" class="display-5 fw-bold mb-3"><?= htmlspecialchars($productInfo['product_name']) ?></h1>

                    <!-- Bagian harga -->
                    <div class="mb-4">
                        <span class="display-6 text-primary fw-bold">
                            <?= $productInfo['price']['currency'] ?>
                            <?= number_format($productInfo['price']['amount'], 0, ',', '.') ?>
                        </span>
                    </div>

                    <!-- Bagian Kategori -->
                    <div class="d-flex align-items-center mb-3">
                        <span class="me-2 text-secondary">Kategori:</span>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!empty($productInfo['categories'])): ?>
                                <?php foreach ($productInfo['categories'] as $category): ?>
                                    <span class="badge bg-primary bg-gradient text-white rounded-pill px-3">
                                        <?= htmlspecialchars($category) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-gradient text-white rounded-pill px-3">
                                    Tidak ada kategori
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bagian Tag -->
                    <div class="d-flex align-items-center mb-4">
                        <span class="me-2 text-secondary">Tag:</span>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if (!empty($productInfo['tags'])): ?>
                                <?php foreach ($productInfo['tags'] as $tag): ?>
                                    <span class="badge bg-success bg-gradient text-white rounded-pill px-3">
                                        <?= htmlspecialchars($tag) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-gradient text-white rounded-pill px-3">
                                    Tidak ada tag
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bagian Deskripsi Produk -->
                    <div class="card border-0 shadow mb-4" id="productDescriptionCard-<?= htmlspecialchars($slug) ?>">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Deskripsi Produk</h5>
                            <div class="text-secondary lh-lg" id="productDescription-<?= htmlspecialchars($slug) ?>">
                                <?= nl2br(htmlspecialchars($productInfo['description'])) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Button Add to Cart & Contact Us -->
                    <div class="d-flex gap-3 mb-4">
                        <!-- Add to Cart -->
                        <button type="button"
                            class="btn btn-primary btn-lg flex-grow-1"
                            onclick="addToCart('#')">
                            <i class="fas fa-cart-shopping me-2"></i>Add to Cart
                        </button>
                        <!-- Contact Us -->
                        <!-- Button Contact Us -->
                        <a href="<?= $whatsappLink ?>"
                            class="btn btn-outline-primary btn-lg flex-grow-1 <?= isset($whatsappError) ? 'disabled' : '' ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Hubungi kami via WhatsApp"
                            <?= isset($whatsappError) ? 'tabindex="-1"' : '' ?>>
                            <i class="fas fa-headset me-2"></i>Contact Us
                        </a>

                        <?php if (isset($whatsappError)): ?>
                            <div class="mt-2 text-danger small">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Nomor WhatsApp belum terkonfigurasi
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Bagian Tanggal Produk Dibuat -->
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Ditambahkan: <?= date('d M Y', strtotime($productInfo['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--========== AKHIR AREA KONTEN DETAIL PRODUK ==========-->

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
    <script>
        function addToCart(productId) {
            const button = event.currentTarget;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menambahkan...';

            // Simulasi proses (ganti dengan request async jika sudah tersedia)
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-cart-shopping me-2"></i> Add to Cart';
                alert('Produk berhasil ditambahkan ke keranjang!');
            }, 1200);
        }
    </script>
</body>

</html>