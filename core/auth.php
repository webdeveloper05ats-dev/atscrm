<?php
// ============================================
// ATS CRM - Authentication Core
// ============================================

// Make sure app.php is loaded first
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// ✅ Load Remember Me after APP_NAME exists
require_once __DIR__ . '/remember.php';

/*
|--------------------------------------------------------------------------
| Check If User Is Logged In
|--------------------------------------------------------------------------
*/
function isLoggedIn()
{
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }

    // Auto-login via Remember Me cookie
    if (!empty($_COOKIE[remember_cookie_name()])) {
        global $pdo;

        if ($pdo instanceof PDO) {
            $user = remember_consume($pdo);
            if ($user) {
                $_SESSION['user_id']     = (int)$user['id'];
                $_SESSION['user_name']   = $user['name'];
                $_SESSION['user_email']  = $user['email'];

                $_SESSION['role_id']     = (int)$user['role_id'];
                $_SESSION['role_name']   = $user['role_name'] ?? '';

                $_SESSION['branch_id']   = (int)($user['branch_id'] ?? 0);
                $_SESSION['branch_name'] = $user['branch_name'] ?? '';

                $_SESSION['last_login']  = date('Y-m-d H:i:s');

                session_regenerate_id(true);
                return true;
            }
        }
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Require Login (Protect Pages)
|--------------------------------------------------------------------------
*/
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "login.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Login User
|--------------------------------------------------------------------------
*/
function loginUser($user)
{
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['role_id']    = $user['role_id'];
    $_SESSION['branch_id']  = $user['branch_id'];
    $_SESSION['last_login'] = date('Y-m-d H:i:s');

    session_regenerate_id(true);
}

/*
|--------------------------------------------------------------------------
| Logout User
|--------------------------------------------------------------------------
*/
function logoutUser()
{
    $_SESSION = [];
    session_unset();
    session_destroy();

    header("Location: " . BASE_URL . "login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function authUserId()   { return $_SESSION['user_id'] ?? null; }
function authRoleId()   { return $_SESSION['role_id'] ?? null; }
function authBranchId() { return $_SESSION['branch_id'] ?? null; }

function isAdmin()
{
    return (authRoleId() == 1);
}

function requireAdmin()
{
    if (!isAdmin()) {
        die("Access denied.");
    }
}