<?php
/**
 * PHPUnit Bootstrap file for Pathfinder Integration Tests
 */

// Load Composer autoloader
$autoloader = require __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    try {
        $dotenv->load();
    } catch (\Dotenv\Exception\InvalidPathException $e) {
        // .env file doesn't exist - will rely on environment variables or skip tests
        echo "Warning: .env file not found. Copy .env.example to .env and configure your ESI credentials.\n";
    }
}

// Helper function to get environment variable
if (!function_exists('env')) {
    function env($key, $default = '') {
        return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
    }
}

// Define test constants for easy access
if (!defined('ESI_CLIENT_ID')) {
    define('ESI_CLIENT_ID', env('ESI_CLIENT_ID'));
    define('ESI_CLIENT_SECRET', env('ESI_CLIENT_SECRET'));
    define('ESI_REFRESH_TOKEN', env('ESI_REFRESH_TOKEN'));
    define('ESI_CHARACTER_ID', env('ESI_CHARACTER_ID') ? (int)env('ESI_CHARACTER_ID') : 0);
    define('ESI_BASE_URL', env('ESI_BASE_URL', 'https://esi.evetech.net'));
    define('SSO_BASE_URL', env('SSO_BASE_URL', 'https://login.eveonline.com'));
    define('ESI_DATASOURCE', env('ESI_DATASOURCE', 'tranquility'));
}

// Override environment.ini settings with .env values if present
if (ESI_CLIENT_ID && ESI_CLIENT_SECRET) {
    putenv('CCP_SSO_CLIENT_ID=' . ESI_CLIENT_ID);
    putenv('CCP_SSO_SECRET_KEY=' . ESI_CLIENT_SECRET);
    $_ENV['CCP_SSO_CLIENT_ID'] = ESI_CLIENT_ID;
    $_ENV['CCP_SSO_SECRET_KEY'] = ESI_CLIENT_SECRET;
    $_SERVER['CCP_SSO_CLIENT_ID'] = ESI_CLIENT_ID;
    $_SERVER['CCP_SSO_SECRET_KEY'] = ESI_CLIENT_SECRET;
}

echo "\n";
echo "=================================================================\n";
echo "Pathfinder Integration Test Bootstrap\n";
echo "=================================================================\n";
echo "ESI Base URL: " . ESI_BASE_URL . "\n";
echo "SSO Base URL: " . SSO_BASE_URL . "\n";
echo "Data Source: " . ESI_DATASOURCE . "\n";
echo "Character ID: " . (ESI_CHARACTER_ID ?: 'NOT SET') . "\n";
echo "Client ID: " . (ESI_CLIENT_ID ? 'SET' : 'NOT SET') . "\n";
echo "Client Secret: " . (ESI_CLIENT_SECRET ? 'SET' : 'NOT SET') . "\n";
echo "Refresh Token: " . (ESI_REFRESH_TOKEN ? 'SET' : 'NOT SET') . "\n";
echo "=================================================================\n\n";
