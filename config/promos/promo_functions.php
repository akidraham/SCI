<?php
// Model: promo_functions.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../user_actions_config.php';
require_once __DIR__ . '/../auth/validate.php';

// Load variables
$config = getEnvironmentConfig();
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']);
$env = ($_SERVER['HTTP_HOST'] === 'localhost') ? 'local' : 'live';

/**
 * Retrieves all promos from the database.
 *
 * This function establishes a connection to the database using PDO,
 * executes a query to fetch all promos from the 'promos' table,
 * and returns the result as an associative array. If an error occurs,
 * it returns an array with an error message.
 *
 * @return array Returns an associative array containing all promos on success,
 *               or an array with an error message on failure.
 */
function getPromos($config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env);
        $stmt = $pdo->query("SELECT * FROM promos");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Log the error for debugging purposes
        handleError($e->getMessage(), $env);

        // Return a user-friendly error message
        return [
            'error' => true,
            'message' => 'Terjadi kesalahan saat mengambil data promo. Silakan hubungi admin.'
        ];
    }
}

/**
 * Retrieves a single promo by its ID from the database.
 *
 * This function establishes a connection to the database using PDO,
 * prepares and executes a query to fetch a promo by its ID,
 * and returns the promo data as an associative array.
 * If no promo is found, it returns null.
 *
 * @param int $id The ID of the promo to retrieve.
 * @return array|null Returns an associative array containing the promo data, or null if no promo is found.
 */
function getPromoById($id, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env);
        $stmt = $pdo->prepare("SELECT * FROM promos WHERE promo_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        handleError($e->getMessage(), $env);
    }
}

/**
 * Fetches all promo categories and their corresponding subcategories.
 *
 * Connects to the database using provided configuration and environment, then performs a LEFT JOIN 
 * between `promo_categories` and `promo_subcategories` tables. Returns a flat array of categories 
 * and their matching subcategories (if any).
 *
 * @param array $config Database configuration array.
 * @param string $env Current application environment.
 * @return array Associative array of promo categories and subcategories.
 */
function getPromoCategories($config, $env)
{
    try {
        // Get PDO connection based on the current environment
        $pdo = getPDOConnection($config, $env);

        // SQL query to retrieve categories with their subcategories
        $query = "
            SELECT 
                pc.category_id AS main_category_id,
                pc.category_name AS main_category_name,
                ps.subcategory_id,
                ps.subcategory_name
            FROM promo_categories pc
            LEFT JOIN promo_subcategories ps ON pc.category_id = ps.category_id
            ORDER BY pc.category_id, ps.subcategory_id
        ";

        // Execute the query and return the result as an associative array
        $stmt = $pdo->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Log the error message based on the current environment
        handleError($e->getMessage(), $env);
        return []; // Return an empty array on error
    }
}

/**
 * Fetch all promos with their associated category and subcategory names.
 *
 * This function retrieves data from the `promos` table and performs LEFT JOINs
 * to include related data from `promo_subcategories` and `promo_categories`.
 *
 * @param array $config Configuration array containing DB credentials.
 * @param string $env Environment indicator ('local' or 'live').
 * @return array Returns an array of promos with category info or error response.
 */
function getAllPromoWithCategories($config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish DB connection

        $query = "
            SELECT 
                p.*,
                COALESCE(sc.subcategory_name, 'Uncategorized') AS subcategory_name,
                COALESCE(c.category_name, 'Uncategorized') AS category_name
            FROM promos p
            LEFT JOIN promo_subcategories sc ON p.subcategory_id = sc.subcategory_id
            LEFT JOIN promo_categories c ON sc.category_id = c.category_id
            ORDER BY p.promo_id
        ";

        $stmt = $pdo->query($query); // Execute query
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all results
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Log error for developers

        return [
            'error' => true,
            'message' => 'Terjadi kesalahan saat mengambil data promo dan kategori. Silakan hubungi admin.'
        ]; // User-friendly error response
    }
}
