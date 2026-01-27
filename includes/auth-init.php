<?php
/**
 * Authentication Initialization
 * Centralizes SSO authentication and session management
 */

// Start session
session_start();

// Check if auth files exist (may not be present in all environments)
$jwtIncludePath = __DIR__ . '/../auth/include/jwt_include.php';
$configPath = __DIR__ . '/../auth/config.php';

if (file_exists($jwtIncludePath) && file_exists($configPath)) {
    // Include JWT authentication
    require $jwtIncludePath;
    $config = require $configPath;
    
    // Initialize JWT and require login
    jwt_init();
    jwt_require_login();
} else {
    // Auth system not deployed yet - log warning
    error_log("WARNING: SSO auth files not found. Application is currently running without authentication.");
    // TODO: Remove this fallback once auth system is fully deployed to all environments
}
