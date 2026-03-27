<?php
// ============================================
// ATS CRM - Collapsible Dynamic Sidebar (RBAC)
// FIX: Show only ONE role-based Dashboard
//      Hide dashboard/* menus from DB list
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (isset($hideSidebar) && $hideSidebar === true) {
    return;
}

$role_id     = $_SESSION['role_id'] ?? 0;
$currentPage = $_GET['page'] ?? '';

// -------------------------------
// Role based Dashboard link
// (index.php already sets $defaultPage, we reuse it)
// -------------------------------
$dashboardSlug = $defaultPage ?? 'dashboard/superadmin'; // fallback

// Fetch allowed menus (with short session cache to reduce repeated DB load)
$menus = [];
$sidebarMenuCacheTtl = 120; // seconds
$sidebarMenuCacheKey = 'role_' . (int)$role_id;
$sidebarMenuCacheRoot = $_SESSION['sidebar_menu_cache'] ?? [];
$cached = $sidebarMenuCacheRoot[$sidebarMenuCacheKey] ?? null;
$nowTs = time();

if (
    is_array($cached)
    && isset($cached['menus'], $cached['ts'])
    && is_array($cached['menus'])
    && (($nowTs - (int)$cached['ts']) < $sidebarMenuCacheTtl)
) {
    $menus = $cached['menus'];
} else {
    $stmt = $pdo->prepare("
        SELECT m.*
        FROM menus m
        INNER JOIN role_permissions rp
            ON rp.menu_id = m.id
        WHERE rp.role_id = :role_id
          AND rp.can_view = 1
          AND m.status = 1
        ORDER BY m.parent_id ASC, m.sort_order ASC
    ");
    $stmt->execute([
        'role_id' => $role_id
    ]);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $_SESSION['sidebar_menu_cache'][$sidebarMenuCacheKey] = [
        'ts' => $nowTs,
        'menus' => $menus
    ];
}

// -------------------------------
// IMPORTANT FIX:
// Remove any dashboard/* items from DB menus
// because we show Dashboard manually as ONE item
// -------------------------------
$menus = array_values(array_filter($menus, function($m) {
    $slug = $m['menu_slug'] ?? '';
    return !(strpos($slug, 'dashboard/') === 0 || $slug === 'dashboard');
}));

// Build parent -> child tree
$menuTree = [];

foreach ($menus as $menu) {
    if ($menu['parent_id'] === NULL) {
        $menuTree[$menu['id']] = $menu;
        $menuTree[$menu['id']]['children'] = [];
    }
}

foreach ($menus as $menu) {
    if ($menu['parent_id'] !== NULL && isset($menuTree[$menu['parent_id']])) {
        $menuTree[$menu['parent_id']]['children'][] = $menu;
    }
}

// Helper: is parent open?
function isParentOpen($parent, $currentPage) {
    if (!isset($parent['children']) || empty($parent['children'])) return false;

    foreach ($parent['children'] as $child) {
        if ($currentPage === $child['menu_slug']) return true;
    }
    return false;
}

// Active dashboard?
$isDashboardActive = ($currentPage === '' || strpos($currentPage, 'dashboard/') === 0);
?>

<div class="sidebar" id="crmSidebar">

    <div class="logo"><?= APP_NAME ?></div>
    <div class="sidebar-search">
        <label for="sidebarMenuSearch" class="sidebar-search-label">Search Menu</label>
        <input
            type="text"
            id="sidebarMenuSearch"
            class="sidebar-search-input"
            placeholder="Search menu..."
            autocomplete="off"
        >
    </div>

    <ul class="menu-list">

        <!-- Dashboard (ONLY ONE) -->
        <li class="<?= $isDashboardActive ? 'active' : '' ?>">
            <a href="index.php?page=<?= htmlspecialchars($dashboardSlug) ?>" data-tooltip="Dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <?php foreach ($menuTree as $parent): ?>
            <?php
                $hasChildren  = !empty($parent['children']);
                $parentOpen   = isParentOpen($parent, $currentPage);
                $parentActive = ($currentPage === $parent['menu_slug']) || $parentOpen;
            ?>

            <li class="<?= $parentActive ? 'active' : '' ?> <?= $hasChildren ? 'has-children' : '' ?> <?= $parentOpen ? 'open' : '' ?>"<?= $hasChildren ? ' data-initial-open="' . ($parentOpen ? '1' : '0') . '"' : '' ?>>

                <?php if ($hasChildren): ?>
                    <!-- Parent toggle -->
                    <?php $submenuId = 'submenu-' . (int)$parent['id']; ?>
                    <button
                        type="button"
                        class="menu-toggle"
                        data-menu-id="<?= (int)$parent['id'] ?>"
                        data-tooltip="<?= htmlspecialchars($parent['menu_name']) ?>"
                        aria-expanded="<?= $parentOpen ? 'true' : 'false' ?>"
                        aria-controls="<?= htmlspecialchars($submenuId) ?>"
                    >
                        <i class="<?= htmlspecialchars($parent['icon'] ?: 'fas fa-circle') ?>"></i>
                        <span><?= htmlspecialchars($parent['menu_name']) ?></span>
                        <i class="fas fa-chevron-down caret"></i>
                    </button>

                    <ul id="<?= htmlspecialchars($submenuId) ?>" class="submenu" <?= $parentOpen ? '' : 'style="display:none;" hidden' ?>>
                        <?php foreach ($parent['children'] as $child): ?>
                            <li class="<?= ($currentPage === $child['menu_slug']) ? 'active' : '' ?>">
                                <a href="index.php?page=<?= htmlspecialchars($child['menu_slug']) ?>">
                                    <i class="<?= htmlspecialchars($child['icon'] ?: 'fas fa-dot-circle') ?>"></i>
                                    <span><?= htmlspecialchars($child['menu_name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                <?php else: ?>
                    <!-- Normal menu link -->
                    <a href="index.php?page=<?= htmlspecialchars($parent['menu_slug']) ?>" data-tooltip="<?= htmlspecialchars($parent['menu_name']) ?>">
                        <i class="<?= htmlspecialchars($parent['icon'] ?: 'fas fa-circle') ?>"></i>
                        <span><?= htmlspecialchars($parent['menu_name']) ?></span>
                    </a>
                <?php endif; ?>

            </li>
        <?php endforeach; ?>

        <!-- Logout -->
        <li class="sidebar-logout">
            <a href="logout.php" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>

    </ul>

</div>

