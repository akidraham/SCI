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
 * Retrieves active products based on category, price range, sorting, and pagination.
 *
 * This function queries the database to fetch active products, optionally filtering by category name(s),
 * a price range, and sorting options. The function ensures that `minPrice` and `maxPrice` are integers
 * and do not accept float values.
 *
 * @param string|array|null $categoryNames The category name(s) to filter products by.
 * @param int|null $minPrice The minimum price filter (must be an integer).
 * @param int|null $maxPrice The maximum price filter (must be an integer).
 * @param string $sortBy The column to sort by (allowed: 'price', 'created', 'updated').
 * @param string $sortOrder The sorting order ('ASC' or 'DESC').
 * @param int $limit The number of results per page.
 * @param int $offset The starting point for pagination.
 * @return array An array of filtered products.
 */
function getFilteredActiveProducts(
    string|array|null $categoryNames = null,
    ?int $minPrice = null,
    ?int $maxPrice = null,
    string $sortBy = 'created',
    string $sortOrder = 'DESC',
    int $limit = 10,
    int $offset = 0
): array {
    $config = getEnvironmentConfig();
    $env = isLive() ? 'live' : 'local';
    $pdo = getPDOConnection($config, $env);

    if (!$pdo) {
        handleError("Database connection failed in getActiveProductsByCategory", $env);
        return [];
    }

    // Validate the price range, ensuring minPrice is not greater than maxPrice
    if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
        return [];
    }

    // Ensure pagination values are positive integers
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    try {
        // Construct the base SQL query for selecting products
        $sql = "SELECT DISTINCT p.product_id, p.product_name, p.description, p.slug, 
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

        // Apply category filter if provided
        if ($categoryNames !== null) {
            if (is_array($categoryNames)) {
                // If multiple categories are provided, use a dynamic placeholder list
                $categoryParams = [];
                foreach ($categoryNames as $index => $name) {
                    $paramName = ":category_" . $index;
                    $categoryParams[] = $paramName;
                    $params[$paramName] = $name;
                }
                $placeholders = implode(',', $categoryParams);
                $sql .= " AND pc.category_name IN ($placeholders)";
            } else {
                $sql .= " AND pc.category_name = :categoryName";
                $params[':categoryName'] = $categoryNames;
            }
        }

        // Apply minimum price filter if provided
        if ($minPrice !== null) {
            $sql .= " AND p.price_amount >= :minPrice";
            $params[':minPrice'] = $minPrice;
        }

        // Apply maximum price filter if provided
        if ($maxPrice !== null) {
            $sql .= " AND p.price_amount <= :maxPrice";
            $params[':maxPrice'] = $maxPrice;
        }

        // Define the valid sorting columns to prevent SQL injection
        $validSortColumns = [
            'price' => 'p.price_amount',
            'created' => 'p.created_at',
            'updated' => 'p.updated_at'
        ];

        // Validate the sorting column, defaulting to 'created_at' if invalid
        $sortColumn = $validSortColumns[$sortBy] ?? 'p.created_at';

        // Validate sorting order, ensuring it is either 'ASC' or 'DESC'
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? $sortOrder : 'ASC';

        // Append sorting and pagination to the SQL query
        $sql .= " ORDER BY $sortColumn $sortOrder LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        // Prepare and execute the query
        $stmt = $pdo->prepare($sql);

        // Bind parameters dynamically based on their data type
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                throw new Exception("Invalid parameter key: $key. Ensure all parameters use named placeholders.");
            }
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        handleError("Database Query Error: " . $e->getMessage(), $env);
        return [];
    }
}

/**
 * Retrieves an active product from the database based on the provided slug.
 * 
 * This function connects to the database, searches for an active product that matches the given slug,
 * and returns the product details as an associative array. If no active product is found, an empty 
 * array is returned. Error handling is implemented using `handleError`.
 *
 * @param string $slug The slug of the product to retrieve.
 * @return array The product details as an associative array, or an empty array if not found.
 * @throws Exception If an error occurs during the database operation.
 */
function getActiveProductBySlug(string $slug): array
{
    try {
        $config = getEnvironmentConfig(); // Get database configuration settings
        $pdo = getPDOConnection($config, isLive() ? 'live' : 'local'); // Establish a PDO connection
        $env = isLive() ? 'live' : 'local'; // Determine the current environment (live or local)

        // Prepare SQL query to fetch an active product by slug
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE slug = ? 
            AND active = 'active'
            LIMIT 1
        ");
        $stmt->execute([$slug]); // Execute the query with the provided slug
        $product = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch product details

        return $product ?: []; // Return the product data or an empty array if not found
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle errors by logging and terminating based on environment
        return [];
    }
}
