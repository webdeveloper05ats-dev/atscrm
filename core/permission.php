<?php
// ============================================
// ATS CRM - Permission Core (RBAC) - FULL WORKING
// File: core/permission.php
// Uses: role_permissions + menus.menu_slug
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Helper: Super Admin always allowed
|--------------------------------------------------------------------------
*/
function isSuperAdmin(): bool
{
    return (!empty($_SESSION['role_name']) && $_SESSION['role_name'] === 'Super Admin');
}

/*
|--------------------------------------------------------------------------
| Get permission row for current role + menu slug
|--------------------------------------------------------------------------
*/
function getPermission(string $menu_slug)
{
    global $pdo;

    if (empty($_SESSION['role_id'])) {
        return false;
    }

    // Super Admin full access (no DB check needed)
    if (isSuperAdmin()) {
        return [
            'can_view'   => 1,
            'can_add'    => 1,
            'can_edit'   => 1,
            'can_delete' => 1,
        ];
    }

    $role_id = (int)$_SESSION['role_id'];

    $stmt = $pdo->prepare("
        SELECT rp.can_view, rp.can_add, rp.can_edit, rp.can_delete
        FROM role_permissions rp
        INNER JOIN menus m ON rp.menu_id = m.id
        WHERE rp.role_id = :role_id
          AND m.menu_slug = :menu_slug
          AND m.status = 1
        LIMIT 1
    ");

    $stmt->execute([
        'role_id'   => $role_id,
        'menu_slug' => $menu_slug
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| Permission checks
|--------------------------------------------------------------------------
*/
function canView(string $menu_slug): bool
{
    $permission = getPermission($menu_slug);
    return ($permission && (int)$permission['can_view'] === 1);
}

function canAdd(string $menu_slug): bool
{
    $permission = getPermission($menu_slug);
    return ($permission && (int)$permission['can_add'] === 1);
}

function canEdit(string $menu_slug): bool
{
    $permission = getPermission($menu_slug);
    return ($permission && (int)$permission['can_edit'] === 1);
}

function canDelete(string $menu_slug): bool
{
    $permission = getPermission($menu_slug);
    return ($permission && (int)$permission['can_delete'] === 1);
}

/*
|--------------------------------------------------------------------------
| Require permission (protect page)
|--------------------------------------------------------------------------
*/
function requireView(string $menu_slug): void
{
    if (!canView($menu_slug)) {
        http_response_code(403);
        echo "<div style='padding:20px;font-family:Segoe UI,sans-serif'>
                <h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2>
                <p style='margin:0;color:#666'>You don't have permission to view this page.</p>
              </div>";
        exit;
    }
}

function requireAdd(string $menu_slug): void
{
    if (!canAdd($menu_slug)) {
        http_response_code(403);
        die("Access denied.");
    }
}

function requireEdit(string $menu_slug): void
{
    if (!canEdit($menu_slug)) {
        http_response_code(403);
        die("Access denied.");
    }
}

function requireDelete(string $menu_slug): void
{
    if (!canDelete($menu_slug)) {
        http_response_code(403);
        die("Access denied.");
    }
}