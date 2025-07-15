<?php
// product_functions.php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../user_actions_config.php';
require_once __DIR__ . '/../auth/validate.php';
require_once __DIR__ . '/tag_functions.php';

use voku\helper\AntiXSS;
use Brick\Money\Money;
use Brick\Money\Currency;
use Brick\Money\Context\CustomContext;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Math\RoundingMode;

// Load variable
$config = getEnvironmentConfig();
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']);
$env = ($_SERVER['HTTP_HOST'] === 'localhost') ? 'local' : 'live';

/**
 * Sanitizes product data to ensure it is safe for storage and processing.
 *
 * This function performs the following sanitization steps:
 * - Removes leading and trailing spaces from all string inputs.
 * - Prevents XSS attacks by cleaning the `name` and `description` fields.
 * - Converts `price_amount` to an integer to ensure numerical consistency.
 * - Converts `currency` to uppercase for standardization.
 * - Converts `slug` to lowercase to maintain URL consistency.
 *
 * @param array $data The raw product data to be sanitized.
 * @return array The sanitized product data.
 */
function sanitizeProductData($data)
{
    $antiXSS = new AntiXSS(); // Initialize the XSS protection library.

    // Sanitize the name field by trimming spaces and removing potential XSS content.
    $data['name'] = $antiXSS->xss_clean(trim($data['name']));
    // Ensure price_amount is stored as an integer for consistency.
    $data['price_amount'] = (int) $data['price_amount'];
    // Standardize currency format by converting to uppercase.
    $data['currency'] = strtoupper(trim($data['currency']));
    // Sanitize the description field by trimming spaces and removing potential XSS content.
    $data['description'] = $antiXSS->xss_clean(trim($data['description']));
    // Standardize the slug by converting it to lowercase.
    $data['slug'] = strtolower(trim($data['slug']));

    return $data; // Return the sanitized product data.
}

/**
 * Generates a URL-friendly slug from a product name.
 *
 * This function converts a given product name into a slug format by:
 * - Trimming unnecessary whitespaces
 * - Transliterating non-ASCII characters to ASCII equivalents
 * - Converting to lowercase
 * - Replacing non-alphanumeric characters with hyphens
 * - Removing duplicate and trailing hyphens
 * - Ensuring the slug is not empty, defaulting to "untitled" if needed
 * - Escaping the output for safe use in HTML
 *
 * @param string $productName The original product name to be converted.
 * @return string The generated slug.
 */
function generateSlug(string $productName): string
{
    $productName = trim($productName);
    if ($productName === '') {
        return 'untitled';
    }

    // Transliterate non-ASCII characters to ASCII
    $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII;', $productName);

    // Convert to lowercase using multibyte function
    $slug = mb_strtolower($transliterated, 'UTF-8');

    // Replace any non-alphanumeric characters (except hyphens) with hyphens
    $slug = preg_replace('/[^a-z0-9-]/u', '-', $slug);

    // Remove duplicate hyphens
    $slug = preg_replace('/-+/', '-', $slug);

    // Trim hyphens from the start and end
    $slug = trim($slug, '-');

    // Ensure a valid slug and escape output
    return $slug === '' ? 'untitled' : htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
}

/**
 * Retrieves all products from the database.
 *
 * This function establishes a connection to the database using PDO,
 * executes a query to fetch all products from the 'products' table,
 * and returns the result as an associative array. If an error occurs,
 * it returns an array with an error message.
 *
 * @return array Returns an associative array containing all products on success,
 *               or an array with an error message on failure.
 */
function getProducts($config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env);
        $stmt = $pdo->query("SELECT * FROM products");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Log the error for debugging purposes
        handleError($e->getMessage(), $env);

        // Return a user-friendly error message
        return [
            'error' => true,
            'message' => 'Terjadi kesalahan saat mengambil data produk. Silakan hubungi admin.'
        ];
    }
}

/**
 * Retrieves the image path of a product from the database.
 *
 * This function queries the `product_images` table to fetch the first image
 * path associated with a given product ID. If no image is found, it returns `null`.
 * In case of an exception, it logs the error and also returns `null`.
 *
 * @param int $productId The ID of the product whose image path is to be retrieved.
 * @param array $config The database configuration settings.
 * @param string $env The environment configuration settings.
 * @return string|null The image path if found, otherwise null.
 */
function getProductImagePath($productId, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish database connection

        // Prepare query to fetch the first image path for the given product ID
        $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = :product_id LIMIT 1");
        $stmt->execute(['product_id' => $productId]); // Execute the query with the product ID parameter
        $image = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch the result as an associative array

        return $image ? $image['image_path'] : null; // Return image path if found, otherwise return null
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Log the error and return null in case of an exception
        return null;
    }
}

/**
 * Retrieves tag relations for a specific product.
 * 
 * @param int $productId The product ID
 * @param array $config Database config
 * @param string $env Environment
 * @return array Array of tag IDs
 */
function getProductTagRelations($productId, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env);
        $stmt = $pdo->prepare("
            SELECT tag_id 
            FROM product_tag_mapping 
            WHERE product_id = :product_id
        ");
        $stmt->execute(['product_id' => $productId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        handleError($e->getMessage(), $env);
        return [];
    }
}

/**
 * Generates an HTML badge representing the product status.
 *
 * This function takes the product's status value from the 'active' column 
 * and returns an HTML `<span>` element styled as a badge. If the status 
 * is 'active', a green badge labeled "Active" is returned; otherwise, 
 * a red badge labeled "Inactive" is returned.
 *
 * @param string $status The status of the product ('active' or 'inactive').
 * @return string The HTML badge representing the product status.
 */
function getProductStatus($status)
{
    // Check if the product status is 'active' and return the corresponding HTML badge
    if ($status === 'active') {
        return '<span class="badge bg-success">Active</span>'; // Green badge for active products
    } else {
        return '<span class="badge bg-danger">Inactive</span>'; // Red badge for inactive products
    }
}

/**
 * Retrieves all images associated with a product from the database
 * 
 * @param PDO $pdo Koneksi PDO yang valid
 * @param int $productId ID produk yang akan dicari
 * @return array Array berisi path gambar produk
 * @throws RuntimeException Jika terjadi error database
 */
function getProductImages(PDO $pdo, int $productId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT image_id, image_path 
            FROM product_images 
            WHERE product_id = ?
            ORDER BY created_at ASC
        ");

        $stmt->execute([$productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new RuntimeException("Gagal mengambil gambar produk: " . $e->getMessage());
    }
}

/**
 * Retrieves a single product by its ID from the database.
 *
 * This function establishes a connection to the database using PDO,
 * prepares and executes a query to fetch a product by its ID,
 * and returns the product data as an associative array.
 * If no product is found, it returns null.
 *
 * @param int $id The ID of the product to retrieve.
 * @return array|null Returns an associative array containing the product data, or null if no product is found.
 */
function getProductById($id, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env);
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        handleError($e->getMessage(), $env);
    }
}

/**
 * Retrieves all product categories from the database.
 * 
 * This function connects to the database and fetches all records from the `product_categories` table.
 * If an error occurs during the database query, it catches the exception and handles it appropriately.
 * The result is returned as an associative array.
 * 
 * @return array An associative array containing the product categories data.
 */
function getProductCategories($config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Get the PDO database connection
        $stmt = $pdo->query("SELECT * FROM product_categories"); // Execute the query to fetch all product categories
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Return the fetched data as an associative array
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle errors if the query fails
        return []; // Return an empty array in case of an error
    }
}

/**
 * Retrieves category relations for a specific product.
 * 
 * This function fetches category data related to a given product ID from the database.
 * It returns an associative array containing:
 * - `category_ids`: An array of category IDs associated with the product.
 * - `category_names`: An array of category names associated with the product.
 * - `error`: A boolean indicating whether an error occurred.
 * 
 * @param int $productId The ID of the product whose category relations are being retrieved.
 * @param array $config Database configuration settings.
 * @param string $env Environment settings used for error handling.
 * @return array An associative array containing category relations and an error status.
 */
function getProductCategoryRelations($productId, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establishes a database connection

        // SQL query to fetch category details for a specific product
        $sql = "SELECT pc.category_id, pc.category_name FROM product_categories pc 
                INNER JOIN product_category_mapping pcm ON pc.category_id = pcm.category_id 
                WHERE pcm.product_id = :product_id";

        $stmt = $pdo->prepare($sql); // Prepares the SQL statement
        $stmt->execute(['product_id' => $productId]); // Executes query with bound parameter

        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetches results as an associative array

        return [
            'category_ids' => array_column($categories, 'category_id'), // Extracts category IDs
            'category_names' => array_column($categories, 'category_name'), // Extracts category names
            'error' => false // Indicates successful execution
        ];
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handles any errors
        return [
            'category_ids' => [],
            'category_names' => [],
            'error' => true // Indicates an error occurred
        ];
    }
}

/**
 * Retrieves a list of products with their associated categories, tags, and images.
 * 
 * This function fetches product data from the database, including:
 * - Product details (ID, name, slug, description, price, currency, active status, timestamps)
 * - Associated images as a concatenated string
 * - Associated categories as a concatenated string
 * - Associated tags as a concatenated string
 * 
 * It supports pagination through the `limit` and `offset` parameters.
 * 
 * @param array $config Database configuration settings.
 * @param string $env Environment settings used for error handling.
 * @param int $limit The number of products to retrieve per page.
 * @param int $offset The number of products to skip before fetching results.
 * @return array An associative array containing product data.
 */
function getAllProductsWithCategoriesAndTags($config, $env, $limit = 10, $offset = 0)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establishes a database connection

        // SQL query to fetch product details along with associated categories, tags, and images
        $sql = "SELECT 
                    p.product_id, p.product_name, p.slug, p.description, 
                    p.price_amount, p.currency, p.active, 
                    GROUP_CONCAT(DISTINCT pi.image_path ORDER BY pi.image_id SEPARATOR ', ') AS images, 
                    p.created_at, p.updated_at, 
                    GROUP_CONCAT(DISTINCT pc.category_name ORDER BY pc.category_name SEPARATOR ', ') AS categories, 
                    GROUP_CONCAT(DISTINCT t.tag_name ORDER BY t.tag_name SEPARATOR ', ') AS tags
                FROM products p
                LEFT JOIN product_images pi ON p.product_id = pi.product_id  -- Joining product images
                LEFT JOIN product_category_mapping pcm ON p.product_id = pcm.product_id  -- Mapping products to categories
                LEFT JOIN product_categories pc ON pcm.category_id = pc.category_id  -- Fetching category names
                LEFT JOIN product_tag_mapping ptm ON p.product_id = ptm.product_id  -- Mapping products to tags
                LEFT JOIN tags t ON ptm.tag_id = t.tag_id  -- Fetching tag names
                GROUP BY p.product_id
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql); // Prepares the SQL statement
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT); // Binds the limit parameter
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT); // Binds the offset parameter
        $stmt->execute(); // Executes the query

        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetches the results as an associative array
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handles any errors
        return [];
    }
}

/**
 * Retrieves detailed information of a product, including images, categories, and tags.
 *
 * This function queries the database to fetch a product's details and aggregates related images, 
 * categories, and tags using GROUP_CONCAT. Additionally, it converts the tags field from a 
 * comma-separated string to an array for easier processing.
 *
 * @param int $productId The unique identifier of the product.
 * @return array|null Returns an associative array containing product details or null if an error occurs.
 */
function getProductWithDetails($productId, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish a PDO connection

        $sql = "SELECT 
                    p.product_id, 
                    p.product_name, 
                    p.description, 
                    p.price_amount, 
                    p.currency, 
                    GROUP_CONCAT(DISTINCT pi.image_path ORDER BY pi.image_id SEPARATOR ', ') AS images, 
                    p.created_at, 
                    p.updated_at, 
                    GROUP_CONCAT(DISTINCT pc.category_id ORDER BY pc.category_id SEPARATOR ', ') AS category_ids, 
                    GROUP_CONCAT(DISTINCT pc.category_name ORDER BY pc.category_name SEPARATOR ', ') AS categories, 
                    GROUP_CONCAT(DISTINCT t.tag_name ORDER BY t.tag_name SEPARATOR ', ') AS tags 
                FROM products p 
                LEFT JOIN product_images pi ON p.product_id = pi.product_id 
                LEFT JOIN product_category_mapping pcm ON p.product_id = pcm.product_id 
                LEFT JOIN product_categories pc ON pcm.category_id = pc.category_id 
                LEFT JOIN product_tag_mapping ptm ON p.product_id = ptm.product_id 
                LEFT JOIN tags t ON ptm.tag_id = t.tag_id 
                WHERE p.product_id = :product_id 
                GROUP BY p.product_id";

        $stmt = $pdo->prepare($sql); // Prepare the SQL statement
        $stmt->execute(['product_id' => $productId]); // Execute the query with productId parameter
        $product = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch product data

        if ($product && !empty($product['tags'])) {
            $product['tags'] = explode(', ', $product['tags']); // Convert tags from a string to an array
        } else {
            $product['tags'] = []; // Ensure tags are always returned as an array
        }

        return $product; // Return the product data

    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle errors based on environment
        return null; // Return null in case of an error
    }
}

/**
 * Retrieves a product by its slug and encoded Optimus ID.
 *
 * This function decodes the Optimus ID to obtain the original product ID
 * and then verifies if the provided slug matches the retrieved product.
 * If the product is found, it returns the product data; otherwise, it
 * throws an exception and handles the error.
 *
 * @param string $slug The slug of the product.
 * @param int $encodedId The encoded product ID using Optimus.
 * @return array|null The product data as an associative array or null if not found.
 */
function getProductBySlugAndOptimus($slug, $encodedId, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establishes a database connection
        global $optimus;
        $productId = $optimus->decode($encodedId); // Decodes the Optimus ID to get the real product ID
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = :id AND slug = :slug");
        $stmt->execute(['id' => $productId, 'slug' => $slug]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product)
            throw new Exception("Invalid product URL");
        return $product;
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handles the error and logs it
        return null;
    }
}

/**
 * Retrieves active products along with their associated images from the database.
 *
 * This function fetches product details such as product ID, name, slug, description, 
 * price amount, and currency. Additionally, it retrieves all associated images 
 * for each product using `GROUP_CONCAT`, ensuring that multiple images per product 
 * are concatenated into a single string.
 *
 * @param array $config Database configuration settings.
 * @param string $env Environment settings.
 * @param int $limit Number of products to retrieve (default: 10).
 * @param int $offset Offset for pagination (default: 0).
 * @return array Returns an array of active products with their respective images.
 */
function getActiveProductsWithImages($config, $env, $limit = 10, $offset = 0)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish database connection

        $sql = "SELECT 
                    p.product_id, p.product_name, p.slug, p.description, 
                    p.price_amount, p.currency,
                    GROUP_CONCAT(pi.image_path ORDER BY pi.image_id SEPARATOR ', ') AS images
                FROM products p
                LEFT JOIN product_images pi ON p.product_id = pi.product_id
                WHERE p.active = 'active'
                GROUP BY p.product_id
                LIMIT :limit OFFSET :offset"; // Query to fetch active products with images

        $stmt = $pdo->prepare($sql); // Prepare the SQL statement
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT); // Bind limit parameter
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT); // Bind offset parameter
        $stmt->execute(); // Execute the query

        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch and return results
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle any errors that occur
        return [];
    }
}

/**
 * Retrieves the total number of products in the database.
 *
 * This function connects to the database using `getPDOConnection()`, prepares a query 
 * to count the total number of products, executes the query, and returns the result. 
 * If an error occurs, it logs the error and returns 0.
 *
 * @param array $config Database configuration settings.
 * @param string $env Environment settings used for error handling.
 * @return int The total number of products or 0 if an error occurs.
 */
function getTotalProducts($config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish a database connection

        // SQL query to count the total number of products
        $sql = "SELECT COUNT(*) AS total FROM products";

        $stmt = $pdo->prepare($sql); // Prepare the query
        $stmt->execute(); // Execute the query
        return (int) $stmt->fetchColumn(); // Retrieve and return the total count
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Log the error message
        return 0; // Return 0 if an error occurs
    }
}

/**
 * Retrieves the total number of products based on a specific category.
 *
 * This function connects to the database using `getPDOConnection()`, executes a query 
 * to count the total number of products within a given category, and returns the result. 
 * If an error occurs, it logs the error and returns 0.
 *
 * @param array $config Database configuration settings.
 * @param string $env Environment settings used for error handling.
 * @param int|null $categoryId Optional category ID. If null, counts all products.
 * @return int The total number of products in the specified category or 0 if an error occurs.
 */
function getTotalProductsByCategory($config, $env, $categoryId = null)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish a database connection

        // SQL query to count distinct products within the specified category
        $sql = "SELECT COUNT(DISTINCT p.product_id) AS total FROM products p 
                LEFT JOIN product_category_mapping pcm ON p.product_id = pcm.product_id 
                LEFT JOIN product_categories pc ON pcm.category_id = pc.category_id 
                WHERE (:category_id IS NULL OR pc.category_id = :category_id)";

        $stmt = $pdo->prepare($sql); // Prepare the query
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT); // Bind category ID parameter
        $stmt->execute(); // Execute the query
        return (int) $stmt->fetchColumn(); // Retrieve and return the total count
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Log the error message
        return 0; // Return 0 if an error occurs
    }
}

/**
 * Retrieves the total number of products based on a search keyword and category.
 *
 * This function connects to the database using `getPDOConnection()`, executes a query 
 * to count the total number of products matching a given keyword and category, and returns the result. 
 * If an error occurs, it logs the error and returns 0.
 *
 * @param array $config Database configuration settings.
 * @param string $env Environment settings used for error handling.
 * @param string $keyword The search keyword for filtering products.
 * @param int|null $categoryId Optional category ID. If null, counts all products matching the keyword.
 * @return int The total number of products matching the keyword and category or 0 if an error occurs.
 */
function getTotalProductsByKeywordAndCategory($config, $env, $keyword, $categoryId = null)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish a database connection

        // SQL query to count distinct products matching the keyword and category
        $sql = "SELECT COUNT(DISTINCT p.product_id) AS total
                FROM products p
                LEFT JOIN product_category_mapping pcm ON p.product_id = pcm.product_id
                LEFT JOIN product_categories pc ON pcm.category_id = pc.category_id
                LEFT JOIN product_images pi ON p.product_id = pi.product_id
                WHERE p.product_name LIKE :keyword
                AND (:category_id IS NULL OR pc.category_id = :category_id)";

        $stmt = $pdo->prepare($sql); // Prepare the query
        $searchKeyword = "%{$keyword}%"; // Add wildcard for partial matching
        $stmt->bindParam(':keyword', $searchKeyword, PDO::PARAM_STR); // Bind keyword parameter
        $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT); // Bind category ID parameter
        $stmt->execute(); // Execute the query
        return (int) $stmt->fetchColumn(); // Retrieve and return the total count
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Log the error message
        return 0; // Return 0 if an error occurs
    }
}

/**
 * Retrieves the total number of products based on a search keyword.
 *
 * This function connects to the database using `getPDOConnection()`, executes a query 
 * to count the total number of products matching a given keyword, and returns the result. 
 * If an error occurs, it logs the error and returns 0.
 *
 * @param array $config Database configuration settings.
 * @param string $env Environment settings used for error handling.
 * @param string $keyword The search keyword for filtering products.
 * @return int The total number of products matching the keyword or 0 if an error occurs.
 */
function getTotalProductsByKeyword($config, $env, $keyword)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish a database connection

        // SQL query to count distinct products matching the keyword
        $sql = "SELECT COUNT(DISTINCT p.product_id) AS total
                FROM products p
                LEFT JOIN product_category_mapping pcm ON p.product_id = pcm.product_id
                LEFT JOIN product_categories pc ON pcm.category_id = pc.category_id
                LEFT JOIN product_images pi ON p.product_id = pi.product_id
                WHERE p.product_name LIKE :keyword";

        $stmt = $pdo->prepare($sql); // Prepare the query
        $searchKeyword = "%{$keyword}%"; // Add wildcard for partial matching
        $stmt->bindParam(':keyword', $searchKeyword, PDO::PARAM_STR); // Bind keyword parameter
        $stmt->execute(); // Execute the query
        return (int) $stmt->fetchColumn(); // Retrieve and return the total count
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Log the error message
        return 0; // Return 0 if an error occurs
    }
}

/**
 * Adds a new product to the database, including its details, images, category, and tags.
 * 
 * This function performs the following steps:
 * - Validates required fields in the input data.
 * - Ensures the number of product images is within the allowed range.
 * - Sanitizes the input data.
 * - Inserts the product into the `products` table.
 * - Associates images with the product in the `product_images` table.
 * - Links the product to a category in the `product_category_mapping` table.
 * - Processes and associates tags with the product in the `tags` and `product_tag_mapping` tables.
 * - Rolls back the transaction if an error occurs.
 * 
 * @param array $data The product data including name, price, currency, description, slug, images, category, and tags.
 * @param array $config Database configuration.
 * @param string $env Environment settings.
 * @return array Returns an array indicating success or failure with a message.
 */
function addProduct($data, $config, $env)
{
    $requiredKeys = ['name', 'price_amount', 'currency', 'description', 'slug', 'images', 'category'];
    foreach ($requiredKeys as $key) {
        if (!isset($data[$key])) return ['error' => true, 'message' => "Key '$key' not found in product data."];
    }

    $violations = validateProductData($data);
    if (count($violations) > 0) {
        handleError("Validation failed: " . implode(", ", array_map(fn($v) => $v->getMessage(), $violations)), getEnvironmentConfig()['local']);
        return ['error' => true, 'message' => 'Invalid product data. Please check your input.'];
    }

    if (empty($data['images']) || !is_array($data['images'])) {
        handleError("Product images required", getEnvironmentConfig()['local']);
        return ['error' => true, 'message' => 'Product images are required.'];
    }

    $imageCount = count($data['images']);
    if ($imageCount < 1 || $imageCount > 10) {
        handleError("Number of images must be between 1 and 10", getEnvironmentConfig()['local']);
        return ['error' => true, 'message' => 'Number of images must be between 1 and 10.'];
    }

    $data = sanitizeProductData($data);

    try {
        $pdo = getPDOConnection($config, $env);
        $pdo->beginTransaction();

        // Insert product into `products` table
        $stmt = $pdo->prepare("INSERT INTO products (product_name, price_amount, currency, description, slug) VALUES (:name, :price_amount, :currency, :description, :slug)");
        $success = $stmt->execute([
            'name' => $data['name'],
            'price_amount' => $data['price_amount'],
            'currency' => $data['currency'],
            'description' => $data['description'],
            'slug' => $data['slug']
        ]);
        if (!$success || $stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['error' => true, 'message' => 'Failed to save product to database.'];
        }
        $product_id = $pdo->lastInsertId();

        // Insert product images into `product_images` table
        $stmtImages = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
        foreach ($data['images'] as $imagePath) $stmtImages->execute([$product_id, $imagePath]);

        // Link product to category in `product_category_mapping`
        $stmt = $pdo->prepare("INSERT INTO product_category_mapping (product_id, category_id) VALUES (:product_id, :category_id)");
        $success = $stmt->execute(['product_id' => $product_id, 'category_id' => $data['category']]);
        if (!$success) {
            $pdo->rollBack();
            return ['error' => true, 'message' => 'Failed to link product to category.'];
        }

        // Process tags
        if (!empty($data['tags'])) {
            foreach ($data['tags'] as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;

                // Check if tag exists
                $stmt = $pdo->prepare("SELECT tag_id FROM tags WHERE tag_name = :tag_name");
                $stmt->execute(['tag_name' => $tagName]);
                $tag = $stmt->fetch();

                if (!$tag) {
                    // Insert new tag into `tags` table
                    $insertTag = $pdo->prepare("INSERT INTO tags (tag_name) VALUES (:tag_name)");
                    $insertTag->execute(['tag_name' => $tagName]);
                    $tag_id = $pdo->lastInsertId();
                } else {
                    $tag_id = $tag['tag_id'];
                }

                // Link product to tag in `product_tag_mapping`
                $insertMapping = $pdo->prepare("INSERT INTO product_tag_mapping (product_id, tag_id) VALUES (:product_id, :tag_id)");
                $insertMapping->execute(['product_id' => $product_id, 'tag_id' => $tag_id]);
            }
        }

        $pdo->commit();
        return ['error' => false, 'message' => 'Product successfully added.', 'product_id' => $product_id];
    } catch (Exception $e) {
        // Delete uploaded images if an error occurs
        foreach ($data['images'] as $imagePath) {
            $absPath = __DIR__ . '/../../public_html' . $imagePath;
            if (file_exists($absPath)) @unlink($absPath);
        }

        $pdo->rollBack();
        handleError($e->getMessage(), $env);
        return ['error' => true, 'message' => 'A system error occurred. Please try again.'];
    }
}

/**
 * Handles the submission of the "Add Product" form.
 *
 * This function processes the form data when a product is being added. It performs the following steps:
 * - Validates the CSRF token for security.
 * - Extracts and sanitizes product data from the form input.
 * - Ensures that the price is valid and formatted correctly.
 * - Generates a slug for the product name.
 * - Handles product image uploads and validates their count.
 * - Calls `addProduct()` to insert the product into the database.
 * - If successful, redirects to the product management page.
 * - If an error occurs, deletes uploaded images and stores the error message in the session.
 *
 * @return void Redirects to the manage products page with success or error messages.
 */
function handleAddProductForm($config, $env)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
        try {
            validateCSRFToken($_POST['csrf_token']); // Validate CSRF token to prevent cross-site request forgery

            // Prepare product data from form input
            $productData = [
                'name' => $_POST['productName'] ?? '',
                'category' => (int) ($_POST['productCategory'] ?? 0),
                'price_amount' => 0,
                'currency' => 'IDR',
                'description' => $_POST['productDescription'] ?? '',
                'slug' => '',
                'images' => []
            ];

            $tagsInput = $_POST['productTags'] ?? '[]';
            $tags = json_decode($tagsInput);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $tagsInput = trim($_POST['productTags'] ?? '');
                $tags = $tagsInput ? explode(',', $tagsInput) : [];
                $tags = array_map('trim', $tags);
            }

            $tags = array_filter($tags, function ($tag) {
                return !empty($tag);
            });

            $productData['tags'] = $tags;

            // Validate product price
            $rawPrice = $_POST['productPriceAmount'] ?? '';
            $cleanedPrice = str_replace(['.', ','], '', $rawPrice);

            if (!is_numeric($cleanedPrice)) {
                throw new Exception("Format harga tidak valid");
            }

            // Convert price to appropriate format using Money library
            $money = Money::of($cleanedPrice, $_POST['productCurrency'], null, RoundingMode::DOWN);
            $productData['price_amount'] = $money->getAmount()->toInt();
            $productData['currency'] = $money->getCurrency()->getCurrencyCode();

            // Generate slug for the product based on its name
            $productData['slug'] = generateSlug($_POST['productName']);

            // Handle product image uploads
            if (!isset($_FILES['productImages']) || empty($_FILES['productImages']['name'][0])) {
                throw new Exception("Minimum 1 image required, maximum 10 images allowed");
            }

            // Upload and store image paths
            $productData['images'] = handleProductImagesUpload($_FILES['productImages']);

            // Add product to the database
            $result = addProduct($productData, $config, $env);

            if ($result['error']) {
                // Delete uploaded images if product addition fails
                foreach ($productData['images'] as $imagePath) {
                    $absPath = __DIR__ . '/../../public_html' . $imagePath;
                    if (file_exists($absPath))
                        @unlink($absPath);
                }
                throw new Exception($result['message']);
            }

            // Log admin activity after successful addition
            logAdminAction(
                admin_id: $_SESSION['user_id'],
                action: 'add_product',
                config: $config,
                env: $env,
                table_name: 'products',
                record_id: $result['product_id'],
                details: "Added new product: " . $productData['name'],
            );

            // Store success message and reset form input
            $_SESSION['success_message'] = 'Produk berhasil ditambahkan!';
            $_SESSION['form_success'] = true;
            $_SESSION['old_input'] = [];
            session_write_close();

            // Redirect to the manage products page
            $config = getEnvironmentConfig();
            header("Location: " . getBaseUrl($config, $_ENV['LIVE_URL']) . "manage_products");
            exit();
        } catch (Throwable $e) {
            // Delete uploaded images if an error occurs
            if (!empty($productData['images'])) {
                foreach ($productData['images'] as $imagePath) {
                    $absPath = __DIR__ . '/../../public_html' . $imagePath;
                    if (file_exists($absPath))
                        @unlink($absPath);
                }
            }

            // Store error message and retain form input data
            $_SESSION['error_message'] = $e->getMessage();
            $_SESSION['form_success'] = false;
            $_SESSION['old_input'] = $_POST;
            session_write_close();

            // Redirect to the manage products page
            $config = getEnvironmentConfig();
            header("Location: " . getBaseUrl($config, $_ENV['LIVE_URL']) . "manage_products");
            exit();
        }
    } else {
        // Handle invalid requests
        $_SESSION['error_message'] = 'Permintaan tidak valid';
        $_SESSION['form_success'] = false;
        session_write_close();
    }
}

/**
 * Handles the upload of multiple product images.
 *
 * This function processes uploaded product images by:
 * - Validating the images using `validateProductImages()`.
 * - Creating the upload directory if it does not exist.
 * - Generating unique filenames to prevent conflicts.
 * - Moving valid images to the designated upload directory.
 * - Returning an array of successfully uploaded image paths.
 *
 * @param array $files The uploaded files array from `$_FILES`.
 * 
 * @return array An array containing the relative paths of successfully uploaded images.
 */
function handleProductImagesUpload($files)
{
    if (!isset($files) || empty($files['name']))
        return [];

    $uploadDir = __DIR__ . '/../../public_html/uploads/products/';
    if (!file_exists($uploadDir))
        mkdir($uploadDir, 0755, true);

    // Siapkan folder dan file untuk log error
    $logDir = __DIR__ . '/../../public_html/uploads/logs/';
    if (!file_exists($logDir))
        mkdir($logDir, 0755, true);
    $logFile = $logDir . 'error.log';

    $validationResults = validateProductImages($files);
    $uploadedImages = [];

    foreach ($validationResults as $result) {
        if ($result['error'])
            continue;

        $filename = uniqid('product_', true) . '.' . $result['data']['extension'];
        $destinationPath = $uploadDir . $filename;

        if (move_uploaded_file($result['data']['tmp_path'], $destinationPath)) {
            $uploadedImages[] = '/uploads/products/' . $filename;
        } else {
            error_log("Gagal memindahkan file ke: $destinationPath\n", 3, $logFile);
            error_log("Tmp path: " . $result['data']['tmp_path'] . "\n", 3, $logFile);
        }
    }

    return $uploadedImages;
}

/**
 * Updates a product's information, including core details, images, category, and tags.
 *
 * This function performs a transaction-based update process to ensure data integrity. 
 * It updates the product's core information, manages images (deleting old ones and 
 * uploading new ones), updates the product category, and updates tags if provided.
 * If an error occurs, the transaction is rolled back, and any uploaded images 
 * during the process are deleted to prevent orphaned files.
 *
 * @param array $data An associative array containing product details, including new images and deletions.
 * @param int $product_id The unique identifier of the product to be updated.
 * @param array $config The database configuration settings.
 * @param string $env The environment configuration settings.
 * @return array An associative array indicating success or failure with an appropriate message.
 */
function updateProduct($data, $product_id, $config, $env)
{
    $pdo = null;
    $new_images_uploaded = []; // Stores newly uploaded image paths

    try {
        $pdo = getPDOConnection($config, $env); // Establish database connection
        $pdo->beginTransaction(); // Begin transaction to ensure atomic operations

        updateProductCoreInfo($pdo, $product_id, $data); // Update core product details

        // Handle images: delete old ones and upload new ones
        $new_images_uploaded = handleProductImages($pdo, $product_id, $data['images_to_delete'] ?? [], $data['new_images'] ?? []);

        updateProductCategory($pdo, $product_id, $data['category']); // Update product category

        if (isset($data['tags']))
            updateProductTags($pdo, $product_id, $data['tags']); // Update product tags if provided

        $pdo->commit(); // Commit transaction if all operations succeed
        return ['error' => false, 'message' => 'Produk berhasil diperbarui', 'product_id' => $product_id];
    } catch (PDOException $e) {
        if ($pdo)
            $pdo->rollBack(); // Roll back transaction on database error
        deleteUploadedImagesOnError($new_images_uploaded); // Delete uploaded images to prevent orphaned files
        return ['error' => true, 'message' => 'Database error: ' . $e->getMessage()];
    } catch (RuntimeException $e) {
        if ($pdo)
            $pdo->rollBack(); // Roll back transaction on runtime error
        deleteUploadedImagesOnError($new_images_uploaded);
        return ['error' => true, 'message' => $e->getMessage()];
    } catch (Exception $e) {
        if ($pdo)
            $pdo->rollBack(); // Roll back transaction on general exception
        deleteUploadedImagesOnError($new_images_uploaded);
        handleError("System Error: " . $e->getMessage(), $env); // Log error for system monitoring
        return ['error' => true, 'message' => 'Terjadi kesalahan sistem'];
    }
}

/**
 * Updates the core information of a product.
 *
 * This function validates the required fields before updating the product record in the `products` table.
 * If any required field is missing, an error is logged, and an exception is thrown.
 * 
 * The function prepares an SQL `UPDATE` statement to modify:
 * - `product_name`, `price_amount`, `currency`, `description`, `slug`, `active`, and `updated_at` fields.
 * - The `updated_at` field is automatically set to the current timestamp.
 *
 * If the update fails or no rows are affected, appropriate error messages are logged and exceptions are thrown.
 * 
 * @param PDO $pdo The PDO database connection instance.
 * @param int $product_id The unique identifier of the product to be updated.
 * @param array $data An associative array containing the new product data. 
 *                    Expected keys: `name`, `price_amount`, `currency`, `description`, `slug`, `active`.
 * @throws RuntimeException If a required field is missing, the query fails, or no rows are updated.
 */
function updateProductCoreInfo($pdo, $product_id, $data)
{
    // Define required fields and their labels for error messages
    $required_fields = [
        'name' => 'Product Name',
        'price_amount' => 'Price',
        'currency' => 'Currency',
        'description' => 'Description',
        'slug' => 'Slug'
    ];

    // Validate required fields
    foreach ($required_fields as $field => $label) {
        if (empty($data[$field])) {
            error_log("[updateProductCoreInfo] Error: Missing required field '{$field}' for product ID: {$product_id}");
            throw new RuntimeException("$label is required");
        }
    }

    // Prepare SQL query to update product information
    $stmt = $pdo->prepare("
        UPDATE products 
        SET 
            product_name = :product_name, 
            price_amount = :price_amount, 
            currency = :currency, 
            description = :description, 
            slug = :slug, 
            active = :active, 
            updated_at = NOW() 
        WHERE product_id = :product_id
    ");

    // Check if statement preparation was successful
    if (!$stmt) {
        $errorInfo = json_encode($pdo->errorInfo());
        error_log("[updateProductCoreInfo] PDO Prepare Error: {$errorInfo} | Product ID: {$product_id}");
        throw new RuntimeException("Failed to prepare product update query");
    }

    // Bind parameters for execution
    $params = [
        'product_name' => $data['name'],
        'price_amount' => $data['price_amount'],
        'currency' => $data['currency'],
        'description' => $data['description'],
        'slug' => $data['slug'],
        'active' => $data['active'],
        'product_id' => $product_id
    ];

    // Execute the update query
    $success = $stmt->execute($params);

    // Check if execution was successful
    if (!$success) {
        $errorInfo = json_encode($stmt->errorInfo());
        $paramsLog = json_encode($params);
        error_log("[updateProductCoreInfo] Execute Error: {$errorInfo} | Params: {$paramsLog}");
        throw new RuntimeException("Failed to update product information");
    }

    // Check if any rows were affected (if no rows updated, either no changes or product not found)
    if ($stmt->rowCount() === 0) {
        error_log("[updateProductCoreInfo] Warning: No rows affected for product ID: {$product_id} | Data: " . json_encode($data));
        throw new RuntimeException("No changes made or product not found");
    }

    error_log("[updateProductCoreInfo] Successfully updated product ID: {$product_id} | Changes: " . json_encode($data));
}

/**
 * Handles the deletion and addition of product images.
 *
 * This function removes selected images from both the database and the filesystem. 
 * It then inserts new images into the database and returns a list of successfully uploaded image paths. 
 * If an error occurs during the deletion or insertion process, a `RuntimeException` is thrown.
 *
 * @param PDO $pdo The PDO database connection instance.
 * @param int $product_id The unique identifier of the product.
 * @param array $images_to_delete An array of image paths to be removed.
 * @param array $new_images An array of new image paths to be added.
 * @return array A list of newly uploaded image paths.
 * @throws RuntimeException If an error occurs while updating images.
 */
function handleProductImages($pdo, $product_id, $images_to_delete, $new_images)
{
    $new_images_uploaded = [];
    try {
        if (!empty($images_to_delete)) {
            $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND image_path = ?");
            foreach ($images_to_delete as $image_path) {
                // Validasi path gambar untuk mencegah path traversal
                if (strpos($image_path, '..') !== false) {
                    throw new RuntimeException("Path gambar tidak valid: $image_path");
                }

                $stmt->execute([$product_id, $image_path]);
                $abs_path = getAbsoluteImagePathForProduct($image_path);

                if (file_exists($abs_path) && !unlink($abs_path)) {
                    throw new RuntimeException("Gagal menghapus gambar: $image_path");
                }
            }
        }

        if (!empty($new_images)) {
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path) VALUES (?, ?)");
            foreach ($new_images as $image_path) {
                $stmt->execute([$product_id, $image_path]);
                $new_images_uploaded[] = $image_path;
            }
        }
        return $new_images_uploaded;
    } catch (PDOException $e) {
        throw new RuntimeException("Gagal memperbarui gambar: " . $e->getMessage());
    }
}

/**
 * Updates or inserts a product's category in the database.
 *
 * This function associates a product with a category. If the product already has 
 * an assigned category, it updates it. Otherwise, it inserts a new mapping. 
 * It uses the `ON DUPLICATE KEY UPDATE` SQL clause to handle both insertions and updates efficiently.
 *
 * @param PDO $pdo The PDO database connection instance.
 * @param int $product_id The unique identifier of the product.
 * @param int $category_id The unique identifier of the category.
 * @throws RuntimeException If the database operation fails.
 */
function updateProductCategory($pdo, $product_id, $category_id)
{
    try {
        // Validasi category_id
        if ($category_id <= 0) {
            throw new RuntimeException("Kategori tidak valid");
        }

        $stmt = $pdo->prepare("
            INSERT INTO product_category_mapping (product_id, category_id) 
            VALUES (:product_id, :category_id) 
            ON DUPLICATE KEY UPDATE category_id = :category_id
        ");
        $success = $stmt->execute([
            'product_id' => $product_id,
            'category_id' => $category_id
        ]);

        if (!$success) {
            throw new RuntimeException("Gagal memperbarui kategori produk");
        }
    } catch (PDOException $e) {
        throw new RuntimeException("Error database saat memperbarui kategori: " . $e->getMessage());
    }
}

/**
 * Deletes uploaded images from the filesystem in case of an error.
 *
 * This function ensures that any newly uploaded images are removed if an error 
 * occurs during the process. It converts relative image paths to absolute paths 
 * and deletes them if they exist.
 *
 * @param array $image_paths An array of image paths to be deleted.
 */
function deleteUploadedImagesOnError($image_paths)
{
    foreach ($image_paths as $path) {
        $abs_path = getAbsoluteImagePathForProduct($path); // Converts to absolute path
        if (file_exists($abs_path))
            @unlink($abs_path); // Deletes the file if it exists
    }
}

/**
 * Converts a relative image path to an absolute file system path.
 *
 * This function appends the provided relative path to the base directory 
 * where public images are stored. It ensures that the correct absolute 
 * path is generated for file operations such as deletion or retrieval.
 *
 * @param string $relative_path The relative path of the image.
 * @return string The absolute path to the image file.
 */
function getAbsoluteImagePathForProduct($relative_path)
{
    return __DIR__ . '/../../public_html/uploads/products/' . $relative_path; // Constructs the absolute path
}

/**
 * Handles the product edit form submission and updates product details.
 *
 * This function processes a submitted product edit form, including validating CSRF tokens,
 * connecting to the database, validating and updating product information, handling images,
 * and logging actions. If the process fails, it stores error messages in the session and redirects the user.
 *
 * @param array $config Application configuration settings.
 * @param string $env Environment variables.
 */
function handleEditProductForm($config, $env)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
        $pdo = null;
        try {
            validateCSRFToken($_POST['csrf_token']); // Validate CSRF token

            $pdo = getPDOConnection($config, $env); // Initialize database connection
            if (!$pdo)
                throw new RuntimeException("Failed to connect to database");

            $productData = [
                'name' => trim($_POST['product_name'] ?? ''),
                'category' => (int) ($_POST['productCategory'] ?? 0),
                'price_amount' => trim($_POST['price_amount'] ?? ''),
                'currency' => 'IDR',
                'description' => trim($_POST['product_description'] ?? ''),
                'slug' => '',
                'images_to_delete' => $_POST['images_to_delete'] ?? [],
                'new_images' => [],
                'product_id' => (int) ($_POST['product_id'] ?? 0),
                'active' => $_POST['active'] ?? 'inactive',
                'tags' => isset($_POST['tags']) ?
                    array_filter(
                        array_map('trim', explode(',', $_POST['tags'])),
                        fn($tag) => !empty($tag)
                    ) : []
            ];

            $price = filter_var($_POST['price_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            if (!is_numeric($price)) {
                throw new Exception("Harga harus berupa angka");
            }

            // Validasi field required
            $requiredKeys = ['name', 'price_amount', 'description', 'product_id'];
            foreach ($requiredKeys as $key) {
                if (empty($productData[$key])) {
                    throw new Exception("Field '$key' tidak boleh kosong");
                }
            }

            if (!isset($_POST['price_amount']) || !is_numeric($_POST['price_amount']))
                throw new Exception("Invalid price format");

            $money = validatePrice($_POST['price_amount'], 'IDR');
            $productData['price_amount'] = $money->getAmount()->toInt();
            // Generate slug dari product_name
            $productData['slug'] = generateSlug($productData['name']);

            if (isset($_FILES['productImages']) && !empty($_FILES['productImages']['name'][0])) {
                $imageValidationResults = validateProductImages($_FILES['productImages']);
                foreach ($imageValidationResults as $result) {
                    if ($result['error'])
                        throw new Exception($result['message']);
                }
                $productData['new_images'] = handleProductImagesUpload($_FILES['productImages']);
            }

            $violations = validateProductData($productData);
            $imageCountViolations = validateProductImageCount($pdo, $productData['product_id'], $productData['images_to_delete'], $productData['new_images']);
            $allViolations = array_merge($violations, $imageCountViolations);

            if (count($allViolations) > 0) {
                handleError("Validation failed: " . implode(", ", array_map(fn($v) => $v->getMessage(), $allViolations)), $env);
                throw new Exception('Invalid product data');
            }

            // Panggil updateProduct dengan data yang lengkap
            $result = updateProduct($productData, $productData['product_id'], $config, $env);
            if ($result['error'])
                throw new Exception($result['message']);

            logAdminAction(
                admin_id: $_SESSION['user_id'],
                action: 'update_product',
                config: $config,
                env: $env,
                table_name: 'products',
                record_id: $result['product_id'],
                details: "Updated product: " . $productData['name'],
            );

            $_SESSION['success_message'] = 'Product updated successfully!';
            $_SESSION['form_success'] = true;
            $_SESSION['old_input'] = [];
            session_write_close();
            header("Location: " . getBaseUrl($config, $_ENV['LIVE_URL']) . "manage_products");
            exit();
        } catch (Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            $_SESSION['form_success'] = false;
            $_SESSION['old_input'] = $_POST;
            session_write_close();
            header("Location: " . getBaseUrl($config, $_ENV['LIVE_URL']) . "manage_products");
            exit();
        }
    } else {
        $_SESSION['error_message'] = 'Invalid request';
        $_SESSION['form_success'] = false;
        session_write_close();
    }
}

/**
 * Deletes a product from the database and removes associated image files.
 *
 * This function handles the deletion of a product by performing the following steps:
 * 1. Validates the product ID to ensure it is a valid numeric value.
 * 2. Retrieves image paths associated with the product from the `product_images` table.
 * 3. Deletes product-category mappings from the `product_category_mapping` table.
 * 4. Deletes associated images from the `product_images` table.
 * 5. Deletes the product from the `products` table.
 * 6. Removes image files from the server if they exist.
 * 7. Logs the admin action using `logAdminAction`.
 * 8. Uses database transactions to ensure data consistency.
 *
 * @param int $id The ID of the product to be deleted.
 * @param int $admin_id The ID of the admin performing the deletion.
 * @param array $config The configuration array.
 * @param string $env The environment setting ('local' or 'live').
 * @return array Returns an associative array with 'error' (boolean) and 'message' (string).
 */
function deleteProduct($id, $admin_id, $config, $env)
{
    if (!is_numeric($id) || $id <= 0) { // Validate product ID.
        return ['error' => true, 'message' => 'Invalid product ID.'];
    }

    try {
        $config = getEnvironmentConfig(); // Load environment-specific configuration.
        $pdo = getPDOConnection($config, $env); // Establish a PDO database connection.
        if (!$pdo) { // Check if the database connection failed.
            return ['error' => true, 'message' => 'Database connection failed.'];
        }

        $pdo->beginTransaction(); // Start a database transaction.

        // Retrieve all image paths associated with the product.
        $stmtImage = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = :id");
        $stmtImage->execute(['id' => $id]);
        $imagePaths = $stmtImage->fetchAll(PDO::FETCH_COLUMN, 0);

        // Delete product-category mappings from the database.
        $stmtMapping = $pdo->prepare("DELETE FROM product_category_mapping WHERE product_id = :id");
        $stmtMapping->execute(['id' => $id]);

        // Delete product images from the database.
        $stmtImagesDelete = $pdo->prepare("DELETE FROM product_images WHERE product_id = :id");
        $stmtImagesDelete->execute(['id' => $id]);

        // Delete the product from the database.
        $stmtProduct = $pdo->prepare("DELETE FROM products WHERE product_id = :id");
        $stmtProduct->execute(['id' => $id]);

        if ($stmtProduct->rowCount() > 0) { // Check if the product was deleted successfully.
            // Delete image files from the server if they exist.
            if (!empty($imagePaths)) {
                foreach ($imagePaths as $imagePath) {
                    if (!empty($imagePath)) {
                        $absolutePath = __DIR__ . '/../../public_html' . $imagePath; // Construct the absolute file path.
                        if (file_exists($absolutePath)) { // Check if the file exists.
                            unlink($absolutePath); // Delete the file.
                        }
                    }
                }
            }

            $pdo->commit(); // Commit the transaction if all operations succeed.

            // Log the admin action for auditing purposes.
            logAdminAction(
                admin_id: $admin_id,
                action: 'delete',
                config: $config,
                env: $env,
                table_name: 'products',
                record_id: $id,
                details: 'Deleted product ID ' . $id . ' along with associated categories and images.',

            );

            return ['error' => false, 'message' => 'Product, category, and images successfully deleted.'];
        } else {
            $pdo->rollBack(); // Rollback the transaction if the product was not found.
            return ['error' => true, 'message' => 'Product not found or already deleted.'];
        }
    } catch (PDOException $e) { // Handle database exceptions.
        if (isset($pdo) && $pdo->inTransaction()) { // Check if a transaction is active.
            $pdo->rollBack(); // Rollback the transaction in case of an error.
        }

        error_log("Database Error: " . $e->getMessage()); // Log the error for debugging.
        return ['error' => true, 'message' => 'A database error occurred.'];
    }
}

/**
 * Searches for products based on a given keyword.
 *
 * This function queries the database to find products whose names or descriptions 
 * match the provided keyword. The search is performed using a partial match (`LIKE`).
 *
 * @param string $keyword The search keyword used to filter products.
 *                        - The keyword is matched against `product_name` and `description`.
 * 
 * @return array Returns an array of matching products. If an error occurs, an empty array is returned.
 */
function searchProducts($keyword, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish database connection
        $stmt = $pdo->prepare("SELECT * FROM products WHERE product_name LIKE :keyword OR description LIKE :keyword"); // Prepare SQL query
        $stmt->execute(['keyword' => "%$keyword%"]); // Bind keyword and execute query
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch and return results as an associative array
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle errors based on environment
        return []; // Return an empty array in case of failure
    }
}

/**
 * Performs an advanced product search based on multiple filters.
 *
 * This function allows filtering products using various criteria such as keyword, category, 
 * and price range. The SQL query is dynamically constructed based on provided filters.
 *
 * @param array $filters An associative array containing the following optional keys:
 *                       - `keyword` (string): Searches within product names and descriptions.
 *                       - `category` (int): Filters by category ID.
 *                       - `min_price` (float): Sets the minimum price limit.
 *                       - `max_price` (float): Sets the maximum price limit.
 * 
 * @return array Returns an array of filtered products. Returns an empty array if an error occurs.
 */
function advancedProductSearch($filters, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish database connection
        $query = "SELECT * FROM products WHERE 1=1"; // Base query, ensures dynamic conditions can be appended
        $params = [];

        if (!empty($filters['keyword'])) { // Apply keyword filter (search in name and description)
            $query .= " AND (product_name LIKE :keyword OR description LIKE :keyword)";
            $params['keyword'] = "%" . $filters['keyword'] . "%";
        }
        if (!empty($filters['category'])) { // Apply category filter
            $query .= " AND category_id = :category";
            $params['category'] = $filters['category'];
        }
        if (!empty($filters['min_price'])) { // Apply minimum price filter
            $query .= " AND price >= :min_price";
            $params['min_price'] = $filters['min_price'];
        }
        if (!empty($filters['max_price'])) { // Apply maximum price filter
            $query .= " AND price <= :max_price";
            $params['max_price'] = $filters['max_price'];
        }

        $stmt = $pdo->prepare($query); // Prepare the query
        $stmt->execute($params); // Execute query with bound parameters
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch and return filtered results
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle errors based on environment
        return []; // Return an empty array in case of failure
    }
}

/**
 * Retrieves search suggestions for autocomplete functionality.
 *
 * This function queries the database for product names that match the provided 
 * keyword, returning up to five suggestions. It is useful for real-time search 
 * suggestions in user interfaces.
 *
 * @param string $keyword The search keyword used to find matching product names.
 *                        - The keyword is matched against the beginning of `product_name`.
 * 
 * @return array Returns an array of suggested product names. Returns an empty array on failure.
 */
function getSearchSuggestions($keyword, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish database connection
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_name LIKE :keyword LIMIT 5"); // Prepare SQL query
        $stmt->execute(['keyword' => "$keyword%"]); // Bind keyword and execute query
        return $stmt->fetchAll(PDO::FETCH_COLUMN); // Fetch only product names as an array
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle errors based on environment
        return []; // Return an empty array in case of failure
    }
}

/**
 * Performs a fuzzy search for products, allowing for minor spelling mistakes.
 *
 * This function compares the product names with the provided keyword using the SOUNDEX 
 * function, which enables matching even if there are minor typos or phonetic variations 
 * in the search term.
 *
 * @param string $keyword The search keyword that may contain minor spelling errors.
 * 
 * @return array Returns an array of matching products. If no products are found, an empty array is returned.
 */
function fuzzySearchProducts($keyword, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish database connection
        $stmt = $pdo->prepare("SELECT * FROM products WHERE SOUNDEX(product_name) = SOUNDEX(:keyword)"); // Prepare SQL query with SOUNDEX for fuzzy matching
        $stmt->execute(['keyword' => $keyword]); // Bind the keyword and execute the query
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch and return all matching products as an associative array
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle errors based on environment
        return []; // Return an empty array if an error occurs
    }
}

/**
 * Logs a search query for analytics and tracking purposes.
 *
 * This function inserts a record into the `search_logs` table to track the keyword search 
 * made by a user, including the user ID and the search date. This data is useful for 
 * analyzing search trends and user behavior.
 *
 * @param string $keyword The search keyword entered by the user.
 * @param int|null $userId The ID of the user performing the search. Pass `null` for guests.
 * 
 * @return void No value is returned. If an error occurs, it is handled internally.
 */
function logSearchQuery($keyword, $userId = null, $config, $env)
{
    try {
        $pdo = getPDOConnection($config, $env); // Establish a database connection
        $stmt = $pdo->prepare("INSERT INTO search_logs (user_id, keyword, search_date) VALUES (:user_id, :keyword, NOW())"); // Prepare the insert query
        $stmt->execute([ // Bind values and execute the insert
            'user_id' => $userId,
            'keyword' => $keyword,
        ]);
    } catch (Exception $e) {
        handleError($e->getMessage(), $env); // Handle any potential error
    }
}

/**
 * Deletes multiple products from the database.
 * 
 * This function removes multiple products based on their IDs using 
 * the `DELETE FROM` SQL statement with placeholders for secure 
 * parameter binding.
 * 
 * If an error occurs during the deletion process, the function logs 
 * the error and displays an error message to the user.
 * 
 * @param array $ids An array of product IDs to be deleted.
 * @return void
 */
function batchDeleteProducts($ids, $config, $env)
{
    $pdo = getPDOConnection($config, $env);
    if (!$pdo)
        return; // Stop execution if database connection fails

    try {
        // Create placeholders for parameter binding (?, ?, ?)
        $placeholders = rtrim(str_repeat('?,', count($ids)), ',');

        // SQL query to delete products with the specified IDs
        $sql = "DELETE FROM products WHERE id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);

        // Execute the query with the array of IDs
        $stmt->execute($ids);

        // Display the number of products deleted
        echo "Deleted " . $stmt->rowCount() . " products successfully";
    } catch (PDOException $e) {
        // Handle database errors and log them appropriately
        handleError(
            "Delete Error: " . $e->getMessage(),
            $env
        );
        echo 'Error deleting products';
    } finally {
        $pdo = null; // Close database connection
    }
}

/**
 * Soft deletes a product by updating its `deleted_at` column.
 * 
 * This function marks a product as deleted by setting the `deleted_at` 
 * field to the current timestamp instead of permanently removing 
 * the product from the database. 
 * 
 * If the product ID is not found, it returns a message stating that no 
 * product was found with the given ID.
 * 
 * If an error occurs during the process, the function logs the error 
 * and displays an error message to the user.
 * 
 * @param int $id The ID of the product to be soft deleted.
 * @return void
 */
function softDeleteProduct($id, $config, $env)
{
    $pdo = getPDOConnection($config, $env);
    if (!$pdo)
        return; // Stop execution if database connection fails

    try {
        // SQL query to update the `deleted_at` column with the current timestamp
        $sql = "UPDATE products SET deleted_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        // Bind product ID to the query as an integer
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute(); // Execute the query

        // Check if any rows were affected (product found and updated)
        echo $stmt->rowCount() > 0
            ? "Product soft deleted successfully"
            : "No product found with ID $id";
    } catch (PDOException $e) {
        // Handle database errors and log them appropriately
        handleError(
            "Soft Delete Error: " . $e->getMessage(),
            $env
        );
        echo 'Error soft deleting product';
    } finally {
        $pdo = null; // Close database connection
    }
}

/**
 * Exports products to a CSV file.
 * 
 * This function retrieves all products that are not deleted from the database 
 * and exports them to a CSV file with the format `products_export_YYYYMMDD.csv`.
 * If no products are found, it displays a message indicating that no products 
 * are available for export.
 * 
 * The CSV file contains the following columns: 
 * - ID: The unique identifier of the product.
 * - Name: The name of the product.
 * - Price: The price of the product.
 * - Description: A brief description of the product.
 * 
 * If an error occurs during the export process, the function logs the error 
 * and displays an error message to the user.
 * 
 * @return void
 */
function exportProductsToCSV($config, $env)
{
    $pdo = getPDOConnection($config, $env);
    if (!$pdo)
        return; // Stop execution if the database connection fails

    try {
        // Query to fetch all active products (excluding deleted ones)
        $sql = "SELECT * FROM products WHERE deleted_at IS NULL";
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($products) > 0) {
            // Generate CSV filename with the current date
            $filename = "products_export_" . date('Ymd') . ".csv";
            $file = fopen($filename, 'w'); // Open file for writing

            // Define CSV headers
            $headers = array('ID', 'Name', 'Price', 'Description');
            fputcsv($file, $headers); // Write headers to the CSV file

            // Write product data to the CSV file
            foreach ($products as $product) {
                fputcsv($file, $product);
            }

            fclose($file); // Close the file after writing
            echo "Exported " . count($products) . " products to $filename";
        } else {
            echo "No products found to export"; // Message if no products are available
        }
    } catch (PDOException $e) {
        // Handle database errors and log them appropriately
        handleError(
            "Export Error: " . $e->getMessage(),
            $env
        );
        echo 'Error exporting products';
    } finally {
        $pdo = null; // Close database connection
    }
}

/**
 * Generates pagination links for navigating between pages.
 * 
 * This function creates an array of pagination links, including:
 * - A "Previous" button if the current page is greater than 1.
 * - Numbered page links, with the current page highlighted.
 * - A "Next" button if the current page is less than the total pages.
 * 
 * The links are returned as an HTML string with each link separated by a space.
 * 
 * @param int $currentPage The current active page number.
 * @param int $totalPages The total number of pages available.
 * @param string $urlPattern The URL pattern for pagination links, default is '?page='.
 * @return string The generated HTML pagination links.
 */
function generatePaginationLinks($currentPage, $totalPages, $urlPattern = '?page=')
{
    $links = [];
    if ($currentPage > 1)
        $links[] = "<a href='{$urlPattern}" . ($currentPage - 1) . "'>Previous</a>"; // Previous button
    for ($i = 1; $i <= $totalPages; $i++) {
        $links[] = ($i == $currentPage) ? "<strong>$i</strong>" : "<a href='{$urlPattern}$i'>$i</a>"; // Page links
    }
    if ($currentPage < $totalPages)
        $links[] = "<a href='{$urlPattern}" . ($currentPage + 1) . "'>Next</a>"; // Next button
    return implode(" ", $links);
}

/**
 * Highlights a keyword within a given text using the `<mark>` HTML tag.
 * 
 * This function searches for the specified keyword in the provided text and 
 * wraps it with a `<mark>` tag for highlighting. It performs a case-insensitive 
 * search and ensures that only whole words are matched.
 * 
 * If the keyword is empty, the function returns the original text without modification.
 * 
 * @param string $text The input text where the keyword should be highlighted.
 * @param string $keyword The word to be highlighted within the text.
 * @return string The modified text with the keyword wrapped in a `<mark>` tag.
 */
function highlightKeyword($text, $keyword)
{
    if (empty($keyword))
        return $text; // Return original text if keyword is empty
    return preg_replace("/\b($keyword)\b/i", '<mark>$1</mark>', $text); // Wrap matched keyword with <mark> tag
}

/**
 * Formats a given amount as a currency string according to the specified locale.
 * 
 * This function creates a Money object with the provided amount and currency code, 
 * then formats it according to the given locale. If an unknown currency is provided, 
 * it catches the exception and returns an error message.
 * 
 * @param float|int $amount The monetary amount to be formatted.
 * @param string $currencyCode The currency code (e.g., 'IDR' for Indonesian Rupiah).
 * @param string $locale The locale used for formatting (e.g., 'id_ID' for Indonesian format).
 * @return string The formatted currency string or an error message if the currency is invalid.
 */
function formatPrice($amount, $currencyCode = 'IDR', $locale = 'id_ID')
{
    try {
        $money = Money::of($amount, $currencyCode); // Create a Money object with amount and currency
        return $money->formatTo($locale); // Format the currency based on the specified locale
    } catch (UnknownCurrencyException $e) {
        return "Error: Currency code '$currencyCode' is not valid."; // Handle invalid currency codes
    }
}
