<?php
// ================== COMPONENT DATA PROPS ==================
$kenapaKamiProps = [
    [
        'image' => 'assets/images/knp/3.webp',
        'title' => 'Dipercaya di Indonesia hingga UK.',
        'description' => 'Kami telah membantu peneliti di berbagai kota dan lintas benua untuk menyelesaikan tugas akhirnya, bahkan mempublikasi hingga SINTA 1.'
    ],
    [
        'image' => 'assets/images/knp/4.webp',
        'title' => 'Pioneer paket unlimited dan video penjelasan.',
        'description' => 'Kami memberikan layanan yang paling spesial untuk memastikan kesiapan kamu.'
    ],
    [
        'image' => 'assets/images/knp/1.webp',
        'title' => 'Pilihan paket terlengkap.',
        'description' => 'Dari paket basic hingga unlimited, semua ada!'
    ],
    [
        'image' => 'assets/images/knp/2.webp',
        'title' => 'Layanan prima.',
        'description' => 'Kami dukung penuh proses penelitian kamu hingga selesai. Rasi kami siap menjawab pertanyaan kamu.'
    ],
];
?>

<style>
    /* Modern Styling */
    .kenapakami .kenapa-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: auto;
    }

    .kenapakami .kenapa-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .kenapakami .card-image-container {
        position: relative;
        padding-top: 70%;
        /* Aspect ratio 1:1.4 */
        overflow: hidden;
    }

    .kenapakami .card-image {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .kenapakami .card-content {
        padding: 1.5rem;
        text-align: center;
    }

    .kenapakami .card-content h4 {
        color: #2A2A2A;
        font-weight: 600;
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }

    .kenapakami .card-content p {
        color: #666;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    /* Carousel Customization */
    .kenapakami .carousel-indicators [data-bs-target] {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: 0 6px;
        background-color: rgba(0, 0, 0, 0.2);
        border: none;
    }

    .kenapakami .carousel-indicators .active {
        background-color: #007bff;
        width: 30px;
        border-radius: 5px;
    }

    .kenapakami .carousel-control-prev,
    .kenapakami .carousel-control-next {
        width: 8%;
    }

    @media (max-width: 991.98px) {
        .kenapakami .kenapa-card {
            margin: 0 10px;
        }

        .kenapakami .card-content {
            padding: 1.25rem;
        }
    }

    /* Mobile-specific styles */
    @media (max-width: 767.98px) {
        .kenapakami .carousel-item {
            padding: 0 10px;
            /* Tambahkan padding samping */
        }

        .kenapakami .kenapa-card {
            margin-bottom: 20px;
            /* Ruang antar card */
            border-radius: 15px;
            /* Lebih rounded */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            /* Shadow lebih lembut */
        }

        .kenapakami .card-content {
            padding: 1.2rem;
            /* Padding lebih rapat */
        }

        .kenapakami .card-content h4 {
            font-size: 1.1rem;
            /* Ukuran font lebih kecil */
            margin-bottom: 0.8rem;
            /* Ruang lebih rapat */
        }

        .kenapakami .card-content p {
            font-size: 0.9rem;
            /* Ukuran font lebih kecil */
            line-height: 1.5;
            /* Line height lebih rapat */
        }

        /* Perbaikan tampilan carousel controls */
        .kenapakami .carousel-control-prev,
        .kenapakami .carousel-control-next {
            width: 40px;
            height: 40px;
            top: 40%;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 50%;
        }
    }
</style>

<!--========== AREA KENAPA KAMI ==========-->
<section class="kenapakami py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-3">Kenapa Memilih Kami?</h2>
        </div>

        <!-- Mobile (XS-MD) -->
        <div class="d-block d-md-none">
            <div id="mobileCarousel" class="carousel slide" data-bs-interval="false">
                <div class="carousel-indicators">
                    <?php foreach ($kenapaKamiProps as $i => $item): ?>
                        <button type="button"
                            data-bs-target="#mobileCarousel"
                            data-bs-slide-to="<?= $i ?>"
                            <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
                            aria-label="Slide <?= $i + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>

                <div class="carousel-inner">
                    <?php foreach ($kenapaKamiProps as $i => $item): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <div class="kenapa-card">
                                <div class="card-image-container">
                                    <img src="<?= $baseUrl . $item['image'] ?>"
                                        class="card-image"
                                        alt="<?= htmlspecialchars($item['title']) ?>"
                                        loading="lazy">
                                </div>
                                <div class="card-content">
                                    <h4><?= htmlspecialchars($item['title']) ?></h4>
                                    <p><?= htmlspecialchars($item['description']) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="carousel-control-prev" type="button" data-bs-target="#mobileCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#mobileCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>

        <!-- Tablet (MD-LG) -->
        <div class="d-none d-md-block d-lg-none">
            <div id="tabletCarousel" class="carousel slide" data-bs-interval="false">
                <div class="carousel-indicators">
                    <?php $chunks = array_chunk($kenapaKamiProps, 2); ?>
                    <?php foreach ($chunks as $i => $chunk): ?>
                        <button type="button"
                            data-bs-target="#tabletCarousel"
                            data-bs-slide-to="<?= $i ?>"
                            <?= $i === 0 ? 'class="active" aria-current="true"' : '' ?>
                            aria-label="Slide <?= $i + 1 ?>"></button>
                    <?php endforeach; ?>
                </div>

                <div class="carousel-inner">
                    <?php $chunks = array_chunk($kenapaKamiProps, 2); ?>
                    <?php foreach ($chunks as $i => $chunk): ?>
                        <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                            <div class="row g-4 px-2">
                                <?php foreach ($chunk as $item): ?>
                                    <div class="col-md-6">
                                        <div class="kenapa-card h-100">
                                            <div class="card-image-container">
                                                <img src="<?= $baseUrl . $item['image'] ?>"
                                                    class="card-image"
                                                    alt="<?= htmlspecialchars($item['title']) ?>"
                                                    loading="lazy">
                                            </div>
                                            <div class="card-content">
                                                <h4><?= htmlspecialchars($item['title']) ?></h4>
                                                <p><?= htmlspecialchars($item['description']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="carousel-control-prev" type="button" data-bs-target="#tabletCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#tabletCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>

        <!-- Desktop (LG+) -->
        <div class="d-none d-lg-block">
            <div class="row g-4">
                <?php foreach ($kenapaKamiProps as $item): ?>
                    <div class="col-lg-3">
                        <div class="kenapa-card h-100">
                            <div class="card-image-container">
                                <img src="<?= $baseUrl . $item['image'] ?>"
                                    class="card-image"
                                    alt="<?= htmlspecialchars($item['title']) ?>"
                                    width="306"
                                    height="214"
                                    loading="lazy">
                            </div>
                            <div class="card-content">
                                <h4><?= htmlspecialchars($item['title']) ?></h4>
                                <p><?= htmlspecialchars($item['description']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<!--========== AKHIR AREA KENAPA KAMI ==========-->