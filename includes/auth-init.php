<?php
/**
 * Authentication Initialization
 * Centralizes SSO authentication and session management
 */

// Start session
session_start();

// Include JWT authentication
require __DIR__ . '/../auth/include/jwt_include.php';
$config = require __DIR__ . '/../auth/config.php';

// Initialize JWT and require login
jwt_init();
jwt_require_login();
