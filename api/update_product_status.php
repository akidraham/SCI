<?php
// update_product_status.php

ob_start(); // Tangkap semua output sebelum JSON

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database/database-config.php';
require_once __DIR__ . '/../config/auth/admin_functions.php';
require_once __DIR__ . '/../config/products/product_functions.php';

startsession();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_clean(); // Bersihkan output sebelum mengirim JSON

// Debugging: Log request method
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Pastikan admin sudah login
if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access - No user_id in session");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$adminId = $_SESSION['user_id']; // FIX: Gunakan session yang pasti ada

// Get the raw request body for debugging
$requestBody = file_get_contents('php://input');
error_log("Raw request body: " . $requestBody);

$data = json_decode($requestBody, true);

// Debugging: Log decoded JSON data
error_log("Decoded JSON: " . print_r($data, true));

$productId = $data['product_id'] ?? null;
$newStatus = $data['new_status'] ?? null;
$token = $data['csrf_token'] ?? null;

// Validate CSRF token
if (!validateCsrfToken($token)) {
    error_log("CSRF token validation failed");
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

// Input validation
if (!$productId || !in_array($newStatus, ['active', 'inactive'])) {
    error_log("Invalid input: product_id={$productId}, new_status={$newStatus}");
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Determine environment and get config
    $envConfig = getEnvironmentConfig();
    $pdo = getPDOConnection($envConfig, isLive() ? 'live' : 'local');

    if (!$pdo) {
        throw new PDOException("Database connection failed");
    }

    // Debugging: Log database update query
    error_log("Updating product_id={$productId} to status={$newStatus}");

    // Ambil nama produk dan status sebelum diubah
    $stmt = $pdo->prepare("SELECT product_name, active FROM products WHERE product_id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        error_log("Product not found for product_id={$productId}");
        echo json_encode([
            'success' => false,
            'message' => 'Product not found'
        ]);
        exit;
    }

    $oldStatus = $product['active'];
    $productName = $product['product_name'];

    // Cek apakah status sudah sama
    if ($oldStatus === $newStatus) {
        error_log("No update needed. Status for product_id={$productId} is already {$newStatus}");
        echo json_encode([
            'success' => false,
            'message' => 'Status is already set to ' . $newStatus
        ]);
        exit;
    }

    // Eksekusi UPDATE ke database
    $stmt = $pdo->prepare("UPDATE products SET active = ? WHERE product_id = ?");
    $stmt->execute([$newStatus, $productId]);

    if ($stmt->rowCount() > 0) {
        error_log("Status updated successfully for product_id={$productId}");

        $logMessage = "Update Status - Changed status from \"{$productName}\" to {$newStatus}";

        if ($adminId) {
            logAdminAction(
                admin_id: $adminId,
                action: 'update_status',
                config: $envConfig,
                env: isLive() ? 'live' : 'local',
                table_name: 'products',
                record_id: $productId,
                details: $logMessage
            );
        } else {
            error_log("Admin ID not found, skipping log entry.");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
        exit;
    } else {
        error_log("No rows affected, status might be the same for product_id={$productId}");
        echo json_encode([
            'success' => false,
            'message' => 'No changes were made'
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);
} catch (PDOException $e) {
    // Log database error
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status'
    ]);
}
