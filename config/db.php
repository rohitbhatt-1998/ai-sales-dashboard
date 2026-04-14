<?php
// ============================================================
// Database Configuration
// ============================================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'ai_sales_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// App
define('APP_NAME',    'AI Sales Calling Dashboard');
define('APP_VERSION', '1.0.0');
define('APP_URL',     getenv('APP_URL') ?: 'http://localhost');
define('APP_SECRET',  getenv('APP_SECRET') ?: 'change-this-to-something-secure-in-production-!@#');
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// Paths
define('BASE_PATH', dirname(__DIR__));
define('LOG_PATH',  BASE_PATH . '/logs');
