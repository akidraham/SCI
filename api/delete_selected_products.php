<?php
// api/delete_selected_products.php

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/products/product_functions.php';

startsession();

ob_end_clean();

header("Content-Type: application/json");

$config = getEnvironmentConfig();
$env = $config['is_live'] ? 'live' : 'local';

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handleError('Request method not allowed.', $env);
}

// Pastikan admin sudah login
if (!isset($_SESSION['user_id'])) {
    handleError('Unauthorized access.', $env);
}

$admin_id = $_SESSION['user_id'];

// Retrieve CSRF token from the request header
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

// Validate CSRF token
if (!$csrfToken || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    handleError('Invalid CSRF token.', $env);
}

// Decode the JSON request body
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    handleError('Failed to read JSON data.', $env);
}

// Validate product IDs input
if (!isset($data['product_ids']) || !is_array($data['product_ids'])) {
    handleError('Invalid product data.', $env);
}

$deletedProducts = [];
$failedProducts = [];

// Process each product ID for deletion
foreach ($data['product_ids'] as $id) {
    $id = intval($id);
    $result = deleteProduct($id, $admin_id, $config, $env);
    if ($result['error']) {
        $failedProducts[] = [
            'id' => $id,
            'message' => $result['message']
        ];
    } else {
        $deletedProducts[] = $id;
    }
}

ob_end_clean();
// Prepare and return the JSON response
$response = [
    'error' => !empty($failedProducts),
    'deleted_products' => $deletedProducts,
    'failed_products' => $failedProducts
];

echo json_encode($response);
exit;
