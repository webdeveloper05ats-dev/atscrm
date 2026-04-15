<?php
// ============================================
// ATS CRM - Logout Endpoint (Final)
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/helper.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/remember.php';

// Revoke remember cookie/token
if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];

    // Audit: logout before session is destroyed
    crmAuditInsert($pdo, [
        'user_id' => $uid,
        'action' => 'LOGOUT',
        'table_name' => 'users',
        'record_id' => $uid,
        'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);

    remember_revoke($pdo, $uid);
} else {
    remember_cookie_clear();
}

// Destroy session + redirect
logoutUser();
