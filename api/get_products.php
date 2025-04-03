<?php
// get_products.php

// Load configuration and functions
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/user_actions_config.php';
require_once __DIR__ . '/../config/products/product_functions.php';
require_once __DIR__ . '/../config/api/api_functions.php';

// Debugging: Log errors to error_log only in local environment
if (!isLive()) {
    ini_set('log_errors', 1);
}

// Header for JSON response
header('Content-Type: application/json');

// Start session if not already started from user_actions_config.php
startSession();
// Retrieves the environment-specific configuration settings from config.php
$config = getEnvironmentConfig();

// Get parameters
$categories = isset($_GET['categories']) ? array_map('sanitize_input', (array)$_GET['categories']) : null;
$minPrice = isset($_GET['min_price']) ? (int)sanitize_input($_GET['min_price']) : null;
$maxPrice = isset($_GET['max_price']) ? (int)sanitize_input($_GET['max_price']) : null;
$sortBy = isset($_GET['sort_by']) ? sanitize_input($_GET['sort_by']) : 'latest';
$sortOrder = isset($_GET['sort_order']) ? sanitize_input($_GET['sort_order']) : 'DESC';

// Pagination parameters with defaults and validation
$limit = isset($_GET['limit']) ? max(1, (int)sanitize_input($_GET['limit'])) : 10;
$offset = isset($_GET['offset']) ? max(0, (int)sanitize_input($_GET['offset'])) : 0;

// Validate price range
if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Invalid price range: min price cannot be greater than max price'
    ]);
    exit;
}

// Mapping sorting options
$sortMapping = [
    'price_low' => ['field' => 'price_amount', 'order' => 'ASC'],
    'price_high' => ['field' => 'price_amount', 'order' => 'DESC'],
    'latest' => ['field' => 'created_at', 'order' => 'DESC'],
    'oldest' => ['field' => 'created_at', 'order' => 'ASC']
];

// Apply sorting from mapping if exists, otherwise use default
if (array_key_exists($sortBy, $sortMapping)) {
    $sortField = $sortMapping[$sortBy]['field'];
    $sortDirection = $sortMapping[$sortBy]['order'];
} else {
    // Default sorting
    $sortField = 'created_at';
    $sortDirection = 'DESC';
}

// Debug output (remove in production)
if (!isLive()) {
    error_log("Sorting Parameters - Field: $sortField, Direction: $sortDirection");
}

// Get filtered products with pagination
try {
    $products = getFilteredActiveProducts( // from api_functions.php
        $categories,
        $minPrice,
        $maxPrice,
        $sortField,
        $sortDirection,
        $limit,
        $offset
    );

    $totalProducts = getFilteredActiveProductsCount($categories, $minPrice, $maxPrice); // from api_functions.php

    // Calculate pagination details
    $currentPage = $offset / $limit + 1;
    $totalPages = ceil($totalProducts / $limit);

    // Format response
    $baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']); // from config.php
    $formattedProducts = [];

    foreach ($products as $product) {
        $formattedProducts[] = [
            'image' => $product['image_path'] ? $baseUrl . $product['image_path'] : $baseUrl . 'assets/images/default-product.png',
            'name' => htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8'),
            'description' => htmlspecialchars($product['description'], ENT_QUOTES, 'UTF-8'),
            'price' => $product['currency'] . ' ' . number_format($product['price_amount'], 0, ',', '.'),
            'slug' => htmlspecialchars($product['slug'], ENT_QUOTES, 'UTF-8'),
            'product_id' => (int)$product['product_id']
        ];
    }

    // Return complete response with pagination info
    echo json_encode([
        'success' => true,
        'data' => $formattedProducts,
        'pagination' => [
            'total_items' => $totalProducts,
            'items_per_page' => $limit,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ]
    ]);
} catch (Exception $e) {
    if (!isLive()) {
        error_log('Error in get_products.php: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred while fetching products',
        'details' => isLive() ? null : $e->getMessage()
    ]);
}
