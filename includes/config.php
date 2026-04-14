<?php
/**
 * AI Sales Calling Assistant Dashboard
 * Core Configuration
 */

// Error reporting — set to 0 in production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// -------------------------------------------------------
// Database Configuration
// -------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai_sales_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// -------------------------------------------------------
// Application Settings
// -------------------------------------------------------
define('APP_NAME', 'AI Sales Calling Assistant');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/ai-sales-dashboard'); // Update for production
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_CSV_ROWS', 5000);

// -------------------------------------------------------
// Session
// -------------------------------------------------------
define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('SESSION_NAME', 'AISALES_SESS');

// -------------------------------------------------------
// Call Queue Settings
// -------------------------------------------------------
define('QUEUE_MAX_RETRIES', 2);
define('QUEUE_CALL_DELAY', 5); // seconds between calls

// -------------------------------------------------------
// Timezone
// -------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');

// -------------------------------------------------------
// Start Session
// -------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => false, // Set true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
