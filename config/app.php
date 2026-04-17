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
if (!function_exists('crm_brand_array_get')) {
    function crm_brand_array_get(array $source, string $path, $default = null) {
        $value = $source;
        foreach (explode('.', $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}

$crmBrandDefaults = [
    'app' => ['name' => 'ATS CRM'],
    'assets' => ['logo' => 'assets/images/logo.png', 'favicon' => 'assets/images/logo.png'],
    'theme' => [
        'font' => ['family' => "'Poppins', sans-serif", 'google_url' => ''],
        'colors' => [
            'primary' => '#e91e63',
            'primary_dark' => '#c2185b',
            'primary_light' => '#fce4ec',
            'accent' => '#ff4d8d',
            'text' => '#333333',
            'text_muted' => '#6b7280',
            'bg_light' => '#fff7fa',
            'border' => '#f3c6d3',
            'surface' => '#ffffff',
            'link' => '#be185d',
        ],
    ],
];
$crmBrandFile = __DIR__ . '/branding.php';
$crmBrandConfig = [];
if (is_file($crmBrandFile)) {
    $loaded = require $crmBrandFile;
    if (is_array($loaded)) {
        $crmBrandConfig = $loaded;
    }
}
$GLOBALS['CRM_BRANDING'] = array_replace_recursive($crmBrandDefaults, $crmBrandConfig);

if (!function_exists('crm_brand')) {
    function crm_brand(string $path, $default = null) {
        $brand = $GLOBALS['CRM_BRANDING'] ?? [];
        if (!is_array($brand)) return $default;
        return crm_brand_array_get($brand, $path, $default);
    }
}

if (!function_exists('inr_symbol')) {
    function inr_symbol(): string {
        // HTML entity keeps symbol stable even when a file/DB row has bad encoding.
        return '&#8377;';
    }
}

if (!defined('APP_NAME')) {
    define('APP_NAME', (string) crm_brand('app.name', 'ATS CRM'));
}
define('APP_ENV', 'development'); // change to 'production' in live server
define('BASE_URL', 'http://localhost/new_2025/demo/crm gitcopy/'); // change in production
if (!defined('LEAD_CONTACT_SLA_HOURS')) {
    // Lead contact SLA window from assignment/ownership point. Keep configurable for product rollout.
    define('LEAD_CONTACT_SLA_HOURS', 24);
}

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
