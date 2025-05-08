<?

/**
 * Establishes a PDO database connection using environment variables for configuration.
 * If the connection fails, an error is handled and null is returned.
 *
 * @return PDO|null Returns a PDO instance if successful, otherwise null.
 */
function getPDOConnection()
{
    $host = getenv('DB_HOST');  // Database host address
    $dbName = getenv('DB_NAME');  // Database name
    $user = getenv('DB_USER');  // Database username
    $pass = getenv('DB_PASS');  // Database password
    $charset = 'utf8mb4';  // Default charset for modern apps

    $dsn = "mysql:host=$host;dbname=$dbName;charset=$charset";  // Data source name for PDO

    try {
        $pdo = new PDO($dsn, $user, $pass);  // Attempt to create a new PDO instance
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);  // Enable exception mode for better error handling
        return $pdo;  // Return the PDO instance if successful
    } catch (PDOException $e) {
        handleError("Database connection failed: " . $e->getMessage());  // Custom error handling
        return null;  // Return null if connection fails
    }
}

/**
 * Handles errors by logging them and displaying appropriate error messages based on the environment.
 * In a local environment, detailed error messages are displayed using Whoops. 
 * In a live environment, only a generic error message is shown to the user.
 *
 * @param string $message The error message to handle.
 * @throws Exception Throws an exception in local environment for Whoops to handle.
 */
function handleError($message)
{
    error_log($message);  // Log the error message securely

    $env = getenv('APP_ENV') ?: 'local';  // Default to 'local' if environment variable is not set

    if ($env === 'local') {
        $whoops = new \Whoops\Run;  // Create a Whoops instance for local error handling
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);  // Use PrettyPageHandler for detailed error display
        $whoops->register();  // Register Whoops to handle errors

        throw new Exception($message);  // Throw the exception for Whoops to display detailed error
    } else {
        exit('An error occurred. Please try again later.');  // Exit the script with a generic message in live environment
    }
}
