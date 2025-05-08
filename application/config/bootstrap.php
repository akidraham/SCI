<?php
// Set application environment, default to 'local' if not set.
$appEnv = getenv('APP_ENV') ?: 'local';

// Define the path to the environment files.
$envPath = dirname(__DIR__);
$envFile = '.env.' . $appEnv;

/**
 * Attempt to load environment configuration from the appropriate .env file.
 * It will first check for environment-specific files like .env.{environment},
 * then fall back to a default .env file, or terminate with an error if neither is found.
 */
if (file_exists($envPath . '/' . $envFile)) {
    // Load the environment configuration from the specific file for the current environment.
    $dotenv = Dotenv\Dotenv::createImmutable($envPath, $envFile);
} elseif (file_exists($envPath . '/.env')) {
    // Fallback to loading from the default .env file if the environment-specific file is not found.
    $dotenv = Dotenv\Dotenv::createImmutable($envPath);
} else {
    // Terminate the script if no .env file is found.
    die('Environment file not found. Please create a valid .env or .env.{environment} file.');
}

// Load the environment variables into the application.
$dotenv->load();
