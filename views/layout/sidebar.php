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

// Fetch allowed menus
$stmt = $pdo->prepare("
    SELECT m.*
    FROM menus m
    JOIN role_permissions rp ON rp.menu_id = m.id
    WHERE rp.role_id = :role_id
      AND rp.can_view = 1
      AND m.status = 1
    ORDER BY m.parent_id ASC, m.sort_order ASC
");
$stmt->execute(['role_id' => $role_id]);
$menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------
// IMPORTANT FIX:
// Remove any dashboard/* items from DB menus
// because we show Dashboard manually as ONE item
// -------------------------------
$menus = array_values(array_filter($menus, function($m) {
    $slug = $m['menu_slug'] ?? '';
    return !(strpos($slug, 'dashboard/') === 0 || $slug === 'dashboard');
}));

// Build parent → child tree
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

    <ul class="menu-list">

        <!-- Dashboard (ONLY ONE) -->
        <li class="<?= $isDashboardActive ? 'active' : '' ?>">
            <a href="index.php?page=<?= htmlspecialchars($dashboardSlug) ?>">
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

            <li class="<?= $parentActive ? 'active' : '' ?> <?= $hasChildren ? 'has-children' : '' ?> <?= $parentOpen ? 'open' : '' ?>">

                <?php if ($hasChildren): ?>
                    <!-- Parent toggle -->
                    <a href="javascript:void(0)" class="menu-toggle" data-menu-id="<?= (int)$parent['id'] ?>">
                        <i class="<?= htmlspecialchars($parent['icon'] ?: 'fas fa-circle') ?>"></i>
                        <span><?= htmlspecialchars($parent['menu_name']) ?></span>
                        <i class="fas fa-chevron-down caret"></i>
                    </a>

                    <ul class="submenu" <?= $parentOpen ? '' : 'style="display:none;"' ?>>
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
                    <a href="index.php?page=<?= htmlspecialchars($parent['menu_slug']) ?>">
                        <i class="<?= htmlspecialchars($parent['icon'] ?: 'fas fa-circle') ?>"></i>
                        <span><?= htmlspecialchars($parent['menu_name']) ?></span>
                    </a>
                <?php endif; ?>

            </li>
        <?php endforeach; ?>

        <!-- Logout -->
        <li>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>

    </ul>

</div>