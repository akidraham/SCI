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
 * @param string|array|null $categoryNames The category name(s) to filter products by
 * @param int|null $minPrice The minimum price filter (integer)
 * @param int|null $maxPrice The maximum price filter (integer)
 * @param string $sortBy The column to sort by (allowed: 'price_amount', 'created_at', 'updated_at')
 * @param string $sortOrder The sorting order ('ASC' or 'DESC')
 * @param int $limit Number of results per page
 * @param int $offset The starting point for pagination
 * @return array An array of filtered products
 */
function getFilteredActiveProducts(
    string|array|null $categoryNames = null,
    ?int $minPrice = null,
    ?int $maxPrice = null,
    string $sortBy = 'created_at',
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
            'price_amount' => 'p.price_amount',
            'created_at' => 'p.created_at',
            'updated_at' => 'p.updated_at'
        ];

        // Validate the sorting column, defaulting to 'created_at' if invalid
        $sortColumn = $validSortColumns[$sortBy] ?? 'p.created_at';

        // Validate sorting order, ensuring it is either 'ASC' or 'DESC'
        $sortOrder = in_array(strtoupper($sortOrder), ['ASC', 'DESC']) ? $sortOrder : 'DESC';

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
 * Retrieves the count of active products filtered by category names and price range.
 * 
 * This function counts the number of active products in the database, 
 * optionally filtering them by one or multiple category names and a price range.
 * 
 * @param string|array|null $categoryNames The name(s) of the category to filter products. Can be a string or an array of strings. If null, no category filter is applied.
 * @param int|null $minPrice The minimum price threshold. If null, no lower price limit is applied.
 * @param int|null $maxPrice The maximum price threshold. If null, no upper price limit is applied.
 * 
 * @return int The count of active products matching the provided filters.
 */
function getFilteredActiveProductsCount(
    string|array|null $categoryNames = null,
    ?int $minPrice = null,
    ?int $maxPrice = null
): int {
    // Retrieve environment configuration
    $config = getEnvironmentConfig(); // retrieves the environment-specific configuration settings
    $env = isLive() ? 'live' : 'local'; // Determine the environment (live/local)
    $pdo = getPDOConnection($config, $env); // Establish a database connection

    // If database connection fails, log an error and return 0
    if (!$pdo) {
        handleError("Database connection failed in getActiveProductsCount", $env);
        return 0;
    }

    // Validate price range: if minPrice is greater than maxPrice, return 0
    if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
        return 0;
    }

    try {
        // Base SQL query to count distinct active products
        $sql = "SELECT COUNT(DISTINCT p.product_id) as total
                FROM products p
                JOIN product_category_mapping pcm ON p.product_id = pcm.product_id
                JOIN product_categories pc ON pcm.category_id = pc.category_id
                WHERE p.active = 'active'";

        $params = []; // Array to store bound parameters

        // Filter by category names (if provided)
        if ($categoryNames !== null) {
            if (is_array($categoryNames)) {
                // If multiple categories are provided, create named placeholders
                $categoryParams = [];
                foreach ($categoryNames as $index => $name) {
                    $paramName = ":category_" . $index;
                    $categoryParams[] = $paramName;
                    $params[$paramName] = $name;
                }
                $placeholders = implode(',', $categoryParams);
                $sql .= " AND pc.category_name IN ($placeholders)";
            } else {
                // If a single category is provided, use a single placeholder
                $sql .= " AND pc.category_name = :categoryName";
                $params[':categoryName'] = $categoryNames;
            }
        }

        // Filter by minimum price (if provided)
        if ($minPrice !== null) {
            $sql .= " AND p.price_amount >= :minPrice";
            $params[':minPrice'] = $minPrice;
        }

        // Filter by maximum price (if provided)
        if ($maxPrice !== null) {
            $sql .= " AND p.price_amount <= :maxPrice";
            $params[':maxPrice'] = $maxPrice;
        }

        $stmt = $pdo->prepare($sql); // Prepare the SQL statement

        // Bind parameters dynamically
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }

        $stmt->execute(); // Execute the statement
        $result = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch the result

        return (int) $result['total']; // Return the total count as an integer
    } catch (PDOException $e) {
        // Handle any database query errors
        handleError("Database Query Error: " . $e->getMessage(), $env);
        return 0;
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

/**
 * Retrieves detailed information of an active product by its slug.
 * 
 * This function fetches product details including price, images, description,
 * categories, tags, product name, and creation date. It combines data from
 * multiple related tables using JOIN operations.
 *
 * @param string $slug The slug of the product to retrieve.
 * @return array Associative array containing product details or empty array if not found.
 * @throws Exception If a database error occurs.
 */
function getActiveProductInfoBySlug(string $slug): array
{
    try {
        $config = getEnvironmentConfig();
        $pdo = getPDOConnection($config, isLive() ? 'live' : 'local');
        $env = isLive() ? 'live' : 'local';

        // SQL query with JOINs and GROUP_CONCAT to aggregate related data
        $sql = "
            SELECT 
                p.product_name,
                p.description,
                p.created_at,
                p.price_amount,
                p.currency,
                GROUP_CONCAT(DISTINCT pc.category_name) AS categories,
                GROUP_CONCAT(DISTINCT t.tag_name) AS tags,
                GROUP_CONCAT(DISTINCT pi.image_path) AS images
            FROM products p
            LEFT JOIN product_category_mapping pcm ON p.product_id = pcm.product_id
            LEFT JOIN product_categories pc ON pcm.category_id = pc.category_id
            LEFT JOIN product_tag_mapping ptm ON p.product_id = ptm.product_id
            LEFT JOIN tags t ON ptm.tag_id = t.tag_id
            LEFT JOIN product_images pi ON p.product_id = pi.product_id
            WHERE p.slug = ? 
                AND p.active = 'active'
            GROUP BY p.product_id
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            return [];
        }

        // Process aggregated data into arrays
        $categories = !empty($product['categories']) ? explode(',', $product['categories']) : [];
        $tags = !empty($product['tags']) ? explode(',', $product['tags']) : [];
        $images = !empty($product['images']) ? explode(',', $product['images']) : [];

        // Structure the final output
        return [
            'product_name' => $product['product_name'],
            'description' => $product['description'],
            'price' => [
                'amount' => (int)$product['price_amount'], // Convert to integer if needed
                'currency' => $product['currency']
            ],
            'created_at' => $product['created_at'],
            'categories' => $categories,
            'tags' => $tags,
            'images' => $images
        ];
    } catch (Exception $e) {
        handleError($e->getMessage(), $env);
        return [];
    }
}
