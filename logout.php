<?php
// ============================================
// ATS CRM - Logout Endpoint (Final)
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';      // ✅ REQUIRED for logoutUser()
require_once __DIR__ . '/core/remember.php';  // ✅ for remember_revoke(

// Revoke remember cookie/token
if (!empty($_SESSION['user_id'])) {
    remember_revoke($pdo, (int)$_SESSION['user_id']);
} else {
    remember_cookie_clear();
}

// Destroy session + redirect
logoutUser();