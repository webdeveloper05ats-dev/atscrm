<?php
// ============================================
// ATS CRM - Application Configuration File
// ============================================

// -------------------------------
// Start Secure Session
// -------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------------
// App Basic Settings
// -------------------------------
if (!defined('APP_NAME')) {
    define('APP_NAME', 'ATS CRM');
}
define('APP_ENV', 'development'); // change to 'production' in live server
define('BASE_URL', 'http://localhost/new_2025/demo/crm git/'); // change in production

//define('BASE_URL', 'http://localhost/2026/crm/');

// -------------------------------
// Timezone
// -------------------------------
date_default_timezone_set('Asia/Kolkata');

// -------------------------------
// Error Reporting
// -------------------------------
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// -------------------------------
// Path Constants
// -------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config/');
define('CORE_PATH', ROOT_PATH . '/core/');
define('CONTROLLER_PATH', ROOT_PATH . '/controllers/');
define('MODEL_PATH', ROOT_PATH . '/models/');
define('VIEW_PATH', ROOT_PATH . '/views/');
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('LOG_PATH', ROOT_PATH . '/logs/');

// -------------------------------
// SMTP Configuration
// (Move these to .env in production)
// -------------------------------
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'sgssk63@gmail.com');
define('SMTP_PASSWORD', 'emdancgmhsvdcmfy');
define('SMTP_PORT', 465);
define('SMTP_ENCRYPTION', 'smtps');

// -------------------------------
// Security Headers
// -------------------------------
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer-when-downgrade");

// -------------------------------
// CSRF Token Generator
// -------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -------------------------------
// Auto Include Database
// -------------------------------
require_once CONFIG_PATH . 'database.php';

// -------------------------------
// Auto Include Helpers
// -------------------------------
