<?php
// api_functions.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../products/product_functions.php';
require_once __DIR__ . '/../database/database-config.php';

/**
 * Retrieves categories that have at least one active product.
 *
 * This function connects to the database using PDO and fetches all category names 
 * from the `product_categories` table that have at least one associated product 
 * with `active` status.
 *
 * @param array $config Database configuration settings.
 * @param string $env The environment type (e.g., 'development', 'production').
 * @return array An associative array of active categories, or an empty array if an error occurs.
 */
function getCategoriesWithActiveProducts($config, $env)
{
    try {
        // Establish a PDO database connection
        $pdo = getPDOConnection($config, $env);

        // Define the SQL query to fetch categories with active products
        $sql = "
            SELECT DISTINCT pc.category_name 
            FROM product_categories pc
            INNER JOIN product_category_mapping pcm ON pc.category_id = pcm.category_id
            INNER JOIN products p ON pcm.product_id = p.product_id
            WHERE p.active = 'active'
            ORDER BY pc.category_name
        ";

        // Execute the query
        $stmt = $pdo->query($sql);

        // Fetch all results as an associative array and return them
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Handle any exceptions and log the error based on the environment
        handleError($e->getMessage(), $env);

        // Return an empty array in case of failure
        return [];
    }
}


/**
 * Retrieves active products filtered by category, price range, and sorting options.
 *
 * @param string|array|null $categoryNames Category name(s) to filter products
 * @param int|string|null $minPrice Minimum price filter
 * @param int|string|null $maxPrice Maximum price filter
 * @param string $sortBy Sorting column (price|created|updated)
 * @param string $sortOrder Sorting order (ASC|DESC)
 * @param int $limit Number of results per page
 * @param int $offset Results offset for pagination
 * @return array Filtered products
 */
function getFilteredActiveProducts($categoryNames = null, $minPrice = null, $maxPrice = null, $sortBy = 'created', $sortOrder = 'DESC', $limit = 10, $offset = 0)
{
    $config = getEnvironmentConfig();
    $env = isLive() ? 'live' : 'local';
    $pdo = getPDOConnection($config, $env);

    if (!$pdo) {
        handleError("Database connection failed in getActiveProductsByCategory", $env);
        return [];
    }

    // Debug input parameters (local only)
    if ($env === 'local') {
        error_log("[DEBUG] getFilteredActiveProducts called with parameters:");
        error_log("Categories: " . print_r($categoryNames, true));
        error_log("Price Range: $minPrice - $maxPrice");
        error_log("Sorting: $sortBy $sortOrder");
        error_log("Pagination: LIMIT $limit OFFSET $offset");
    }

    // Validate price range
    if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
        return [];
    }

    // Convert prices to string to avoid precision issues
    $minPrice = $minPrice !== null ? (string)$minPrice : null;
    $maxPrice = $maxPrice !== null ? (string)$maxPrice : null;

    // Ensure pagination values are positive integers
    $limit = max(1, (int)$limit);
    $offset = max(0, (int)$offset);

    try {
        // Base query
        $sql = "SELECT DISTINCT p.product_id, p.product_name, p.description, 
                p.price_amount, p.currency, p.created_at, p.updated_at,
                (SELECT pi.image_path FROM product_images pi 
                 WHERE pi.product_id = p.product_id 
                 ORDER BY pi.created_at ASC 
                 LIMIT 1) AS image_path
                FROM products p
                JOIN product_category_mapping pcm ON p.product_id = pcm.product_id
                JOIN product_categories pc ON pcm.category_id = pc.category_id
                WHERE p.active = 'active'";

        $params = [];

        // Category filter
        if ($categoryNames !== null) {
            if (is_array($categoryNames)) {
                $placeholders = implode(',', array_fill(0, count($categoryNames), '?'));
                $sql .= " AND pc.category_name IN ($placeholders)";
                foreach ($categoryNames as $name) {
                    $params[] = $name;
                }
            } else {
                $sql .= " AND pc.category_name = :categoryName";
                $params[':categoryName'] = $categoryNames;
            }
        }

        // Price filters
        if ($minPrice !== null) {
            $sql .= " AND p.price_amount >= :minPrice";
            $params[':minPrice'] = $minPrice;
        }
        if ($maxPrice !== null) {
            $sql .= " AND p.price_amount <= :maxPrice";
            $params[':maxPrice'] = $maxPrice;
        }

        // Sorting
        $validSortColumns = [
            'price' => 'p.price_amount',
            'created' => 'p.created_at',
            'updated' => 'p.updated_at'
        ];
        $sortColumn = $validSortColumns[$sortBy] ?? 'p.created_at';
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? $sortOrder : 'ASC';

        $sql .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        // Prepare and execute
        $stmt = $pdo->prepare($sql);

        // Bind parameters with proper types
        foreach ($params as $key => $value) {
            $paramType = PDO::PARAM_STR;
            if ($key === ':limit' || $key === ':offset') {
                $paramType = PDO::PARAM_INT;
                $value = (int)$value;
            } elseif (is_int($value)) {
                $paramType = PDO::PARAM_INT;
            }
            $stmt->bindValue($key, $value, $paramType);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        handleError("Database Query Error: " . $e->getMessage(), $env);
        return [];
    }
}
