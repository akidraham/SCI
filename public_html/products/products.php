<?php
// products.php

// Load config and dependencies
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/user_actions_config.php';

// start session if not already started
startSession(); // from user_actions_config.php

// Load dynamic URL configuration based on environment
$config = getEnvironmentConfig(); // Load environment configuration from config.php
$baseUrl = getBaseUrl($config, $_ENV['LIVE_URL']); // Get the base URL from the configuration from config.php
$isLive = $config['is_live'];
// Detect environment
$isLiveEnvironment = ($config['BASE_URL'] === $_ENV['LIVE_URL']);
setCacheHeaders($isLive); // Set header no cache in local from config.php
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <!-- PLACEHOLDER -->
</body>

</html>