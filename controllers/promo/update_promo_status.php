<?php
// controller: update_promo_status.php

// Load environment and database configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database/database-config.php';
require_once __DIR__ . '/../../config/model/promo/promo-functions.php';
require_once __DIR__ . '/../../config/auth/admin_functions.php';

// Set header for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure admin is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate JSON data
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate CSRF token
if (!isset($data['csrf_token'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'CSRF token is missing']);
    exit;
}

try {
    validateCSRFToken($data['csrf_token']);
} catch (Exception $e) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get environment config
$config = getEnvironmentConfig();
$env = ($_SERVER['HTTP_HOST'] === 'localhost') ? 'local' : 'live';
$pdo = getPDOConnection($config, $env);

// Get data from request
$promoId = $data['promo_id'] ?? null;
$newStatus = $data['new_status'] ?? null;
$adminId = $_SESSION['user_id'] ?? null;

/**
 * Updates the status of a promo.
 *
 * Validates the required parameters (`promoId`, `newStatus`, `adminId`) and returns a JSON response
 * indicating success or failure. If any required parameter is missing, returns an error message with the
 * missing parameters. On success, updates the promo status using the PromoService and returns a success response.
 * Catches and returns any exceptions as a JSON error response.
 *
 * @uses \App\Promo\PromoService
 * @param int $promoId      The ID of the promo to update.
 * @param string $newStatus The new status to set for the promo.
 * @param int $adminId      The ID of the admin performing the update.
 * @param array $config     Application configuration array.
 * @param string $env       The current environment.
 *
 * @return void Outputs a JSON response and exits.
 */
// Validate required data
if (!$promoId || !$newStatus || !$adminId) {
    $missing = [];
    if (!$promoId) $missing[] = 'promo_id';
    if (!$newStatus) $missing[] = 'new_status';
    if (!$adminId) $missing[] = 'admin_id';

    echo json_encode([
        'success' => false,
        'message' => 'Missing required parameters: ' . implode(', ', $missing)
    ]);
    exit;
}

try {
    $promoService = new \App\Promo\PromoService($pdo);
    ob_start(); // Start output buffering to capture any output
    $promoService->updateStatus((int) $promoId, $newStatus, (int) $adminId, $config, $env);
    ob_end_clean(); // Clear output buffer to prevent any unwanted output

    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
