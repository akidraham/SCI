<?php
// controller: add-promo.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log script start
error_log("===== START PROMO ADD PROCESS =====");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request Time: " . date('Y-m-d H:i:s'));

// Load environment and database configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database/database-config.php';
require_once __DIR__ . '/../../config/model/slug/slug_functions.php';

// Debug: Log config loaded
error_log("Configuration files loaded");

// Set header for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    error_log("Error: Method not allowed (405)");
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("Session started");
}

// Debug: Log POST data (sensitive data should be filtered in production)
error_log("POST Data: " . json_encode($_POST, JSON_PRETTY_PRINT));

// Validate CSRF token
if (!isset($_POST['csrf_token'])) {
    http_response_code(400); // Bad Request
    $_SESSION['error_message'] = 'CSRF token is missing';
    error_log("CSRF token missing (400)");
    echo json_encode(['success' => false, 'message' => 'CSRF token is missing']);
    exit;
}

try {
    validateCSRFToken($_POST['csrf_token']);
    error_log("CSRF token validated successfully");
} catch (Exception $e) {
    http_response_code(403); // Forbidden
    $_SESSION['error_message'] = 'Invalid CSRF token';
    error_log("Invalid CSRF token: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Get database connection
    $pdo = getPDOConnection($config, $env);
    error_log("Database connection established");

    // Retrieve and sanitize form data
    $promoName = trim($_POST['promoName']);
    $promoCode = trim($_POST['promoCode']);
    $promoDescription = trim($_POST['promoDescription'] ?? '');
    $discountType = $_POST['discountType'];
    $discountValue = $_POST['discountValue'];
    $maxDiscount = $_POST['maxDiscount'] ?? null;
    $mainPromoCategory = $_POST['mainPromoCategory'];
    $subcategoryId = $_POST['subcategory_id'];
    $infiniteDuration = isset($_POST['infiniteDuration']);
    $startDate = $_POST['startDate'] ?? null;
    $endDate = $_POST['endDate'] ?? null;
    $applicableProducts = $_POST['applicableProducts'] ?? [];
    $eligibility = $_POST['eligibility'];
    $minPurchase = $_POST['minPurchase'] ?? 0;
    $maxClaims = $_POST['maxClaims'] ?? 0;
    $promoStatus = isset($_POST['promoStatus']) ? 'active' : 'inactive';
    $autoApply = isset($_POST['autoApply']) ? 1 : 0;

    // Debug: Log sanitized input values
    error_log("Sanitized Input Values:");
    error_log("- promoName: " . $promoName);
    error_log("- promoCode: " . $promoCode);
    error_log("- discountType: " . $discountType);
    error_log("- discountValue: " . $discountValue);
    error_log("- maxDiscount: " . ($maxDiscount ?? 'null'));
    error_log("- mainPromoCategory: " . $mainPromoCategory);
    error_log("- subcategoryId: " . $subcategoryId);
    error_log("- infiniteDuration: " . ($infiniteDuration ? 'true' : 'false'));
    error_log("- startDate: " . ($startDate ?? 'null'));
    error_log("- endDate: " . ($endDate ?? 'null'));
    error_log("- applicableProducts: " . json_encode($applicableProducts));
    error_log("- eligibility: " . $eligibility);
    error_log("- minPurchase: " . $minPurchase);
    error_log("- maxClaims: " . $maxClaims);
    error_log("- promoStatus: " . $promoStatus);
    error_log("- autoApply: " . $autoApply);

    // Validate required fields
    $errors = [];

    if (empty($promoName)) {
        $errors[] = 'Nama promo harus diisi';
        error_log("Validation error: Nama promo harus diisi");
    }

    if (empty($promoCode)) {
        $errors[] = 'Kode promo harus diisi';
        error_log("Validation error: Kode promo harus diisi");
    }

    if (empty($discountType)) {
        $errors[] = 'Tipe diskon harus dipilih';
        error_log("Validation error: Tipe diskon harus dipilih");
    }

    if (empty($discountValue)) {
        $errors[] = 'Nilai diskon harus diisi';
        error_log("Validation error: Nilai diskon harus diisi");
    }

    if (empty($mainPromoCategory)) {
        $errors[] = 'Kategori utama harus dipilih';
        error_log("Validation error: Kategori utama harus dipilih");
    }

    if (empty($subcategoryId)) {
        $errors[] = 'Sub kategori harus dipilih';
        error_log("Validation error: Sub kategori harus dipilih");
    }

    if (!$infiniteDuration) {
        if (empty($startDate)) {
            $errors[] = 'Tanggal mulai harus diisi';
            error_log("Validation error: Tanggal mulai harus diisi");
        }

        if (empty($endDate)) {
            $errors[] = 'Tanggal berakhir harus diisi';
            error_log("Validation error: Tanggal berakhir harus diisi");
        }

        if (!empty($startDate) && !empty($endDate) && strtotime($startDate) > strtotime($endDate)) {
            $errors[] = 'Tanggal berakhir harus setelah tanggal mulai';
            error_log("Validation error: Tanggal berakhir harus setelah tanggal mulai");
        }
    }

    if (!empty($errors)) {
        http_response_code(422); // Unprocessable Entity
        $_SESSION['error_messages'] = $errors;
        error_log("Validation failed with " . count($errors) . " errors");
        echo json_encode([
            'success' => false,
            'message' => 'Validasi gagal',
            'errors' => $errors
        ]);
        exit;
    }

    // Process date values
    if ($infiniteDuration) {
        $startDate = date('Y-m-d H:i:s');
        $endDate = '9999-12-31 23:59:59';
        error_log("Infinite duration set. Using default dates");
    } else {
        $startDate = date('Y-m-d H:i:s', strtotime($startDate));
        $endDate = date('Y-m-d H:i:s', strtotime($endDate));
        error_log("Date range set: $startDate to $endDate");
    }

    // Process discount values
    $discountValue = (float) str_replace(',', '', $discountValue);
    error_log("Processed discountValue: $discountValue");

    if ($discountType === 'percentage') {
        $maxDiscount = $maxDiscount ? (float) str_replace(',', '', $maxDiscount) : null;
        error_log("Processed maxDiscount (percentage): " . ($maxDiscount ?? 'null'));
    } else {
        $maxDiscount = null;
        error_log("maxDiscount set to null (fixed amount discount)");
    }

    $minPurchase = (float) str_replace(',', '', $minPurchase);
    error_log("Processed minPurchase: $minPurchase");

    // Check if promo code is unique
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM promos WHERE promo_code = :promo_code");
    $stmt->execute([':promo_code' => $promoCode]);
    $count = $stmt->fetchColumn();

    error_log("Promo code uniqueness check: count = $count");

    if ($count > 0) {
        http_response_code(409); // Conflict
        $_SESSION['error_message'] = 'Kode promo sudah digunakan';
        error_log("Error: Promo code already exists (409)");
        echo json_encode([
            'success' => false,
            'message' => 'Kode promo sudah digunakan'
        ]);
        exit;
    }

    try {
        $slugService = new SlugService($pdo, $env);
        $promoSlug = $slugService->generatePromoSlug($promoName);
        error_log("Generated promo slug: " . $promoSlug);
    } catch (Exception $e) {
        handleError("Slug generation failed: " . $e->getMessage(), $env);
    }

    // Start transaction
    $pdo->beginTransaction();
    error_log("Database transaction started");

    try {
        // Insert promo data
        $stmt = $pdo->prepare("
            INSERT INTO promos (
                promo_name, 
                slug,
                description, 
                discount_type, 
                discount_value, 
                start_date, 
                end_date, 
                promo_code, 
                max_discount, 
                max_claims, 
                eligibility, 
                min_purchase, 
                subcategory_id, 
                auto_apply, 
                status
            ) VALUES (
                :promo_name, 
                :slug,
                :description, 
                :discount_type, 
                :discount_value, 
                :start_date, 
                :end_date, 
                :promo_code, 
                :max_discount, 
                :max_claims, 
                :eligibility, 
                :min_purchase, 
                :subcategory_id, 
                :auto_apply, 
                :status
            )
        ");

        error_log("Preparing promo insert statement");

        $insertData = [
            ':promo_name' => $promoName,
            ':slug' => $promoSlug,
            ':description' => $promoDescription,
            ':discount_type' => $discountType,
            ':discount_value' => $discountValue,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':promo_code' => $promoCode,
            ':max_discount' => $maxDiscount,
            ':max_claims' => $maxClaims,
            ':eligibility' => $eligibility,
            ':min_purchase' => $minPurchase,
            ':subcategory_id' => $subcategoryId,
            ':auto_apply' => $autoApply,
            ':status' => $promoStatus
        ];

        // Debug: Log the insert data
        error_log("Insert data: " . json_encode($insertData, JSON_PRETTY_PRINT));

        $stmt->execute($insertData);
        $promoId = $pdo->lastInsertId();
        error_log("Promo inserted successfully. ID: $promoId");

        // Process applicable products - remove duplicates first
        $uniqueProducts = array_unique($applicableProducts);
        error_log("Unique products to insert: " . json_encode($uniqueProducts));

        if (!empty($uniqueProducts)) {
            // Use INSERT IGNORE to avoid duplicate errors
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO promo_product_mapping (promo_id, product_id)
                VALUES (:promo_id, :product_id)
            ");

            $productCount = 0;
            foreach ($uniqueProducts as $productId) {
                $productId = (int)$productId;
                if ($productId > 0) {
                    $stmt->execute([
                        ':promo_id' => $promoId,
                        ':product_id' => $productId
                    ]);
                    $productCount += $stmt->rowCount();
                }
            }
            error_log("Inserted $productCount applicable products");
        } else {
            error_log("No applicable products to insert");
        }

        // Commit transaction
        $pdo->commit();
        error_log("Transaction committed successfully");

        // Set success message in session
        $_SESSION['success_message'] = 'Promo berhasil ditambahkan';
        error_log("Success message set in session");

        // Return success response
        http_response_code(201); // Created
        error_log("Promo created successfully (201)");
        echo json_encode([
            'success' => true,
            'message' => 'Promo berhasil ditambahkan',
            'promo_id' => $promoId,
            'redirect_url' => $baseUrl . 'manage_promos'
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500); // Internal Server Error
        $_SESSION['error_message'] = 'Gagal menambahkan promo: ' . $e->getMessage();
        error_log("Database error during transaction: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'message' => 'Gagal menambahkan promo: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    $_SESSION['error_message'] = 'Terjadi kesalahan: ' . $e->getMessage();
    error_log("General error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

error_log("===== END PROMO ADD PROCESS =====");
