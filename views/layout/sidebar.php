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
$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$sessionRoleName = trim((string)($_SESSION['role_name'] ?? ''));
$sessionBranchId = (int)($_SESSION['branch_id'] ?? 0);
$isSuperAdminSidebar = function_exists('crmIsSuperAdminRole') ? crmIsSuperAdminRole() : (strtolower($sessionRoleName) === 'super admin');

// -------------------------------
// Role based Dashboard link
// (index.php already sets $defaultPage, we reuse it)
// -------------------------------
$dashboardSlug = $defaultPage ?? 'dashboard/superadmin'; // fallback

// Fetch allowed menus (always fresh to avoid stale/missing role menus)
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

// -------------------------------
// IMPORTANT FIX:
// Remove any dashboard/* items from DB menus
// because we show Dashboard manually as ONE item
// -------------------------------
$menus = array_values(array_filter($menus, function($m) {
    $slug = $m['menu_slug'] ?? '';
    return !(
        strpos($slug, 'dashboard/') === 0
        || $slug === 'dashboard'
        || $slug === 'system/demo_reset'
    );
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

// Fallback: if a child menu's parent isn't present in fetched list,
// surface it as a top-level item instead of dropping it.
foreach ($menus as $menu) {
    if ($menu['parent_id'] !== NULL && !isset($menuTree[$menu['parent_id']])) {
        if (!isset($menuTree[$menu['id']])) {
            $menu['parent_id'] = null;
            $menu['children'] = [];
            $menuTree[$menu['id']] = $menu;
        }
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

// --------------------------------
// Sidebar live badges (safe scoped)
// --------------------------------
$sidebarBadges = [
    'lead' => 0,
    'enquiries' => 0,
    'enquiries/followups' => 0,
];

try {
    $sidebarBadgeCacheKey = 'sidebar_badges_' . $sessionUserId;
    $cachedSidebarBadges = $_SESSION[$sidebarBadgeCacheKey] ?? null;
    $useBadgeCache = is_array($cachedSidebarBadges)
        && isset($cachedSidebarBadges['ts'], $cachedSidebarBadges['values'])
        && (time() - (int)$cachedSidebarBadges['ts']) <= 30
        && (int)($cachedSidebarBadges['role_id'] ?? 0) === (int)$role_id
        && (int)($cachedSidebarBadges['branch_id'] ?? 0) === (int)$sessionBranchId;

    if ($useBadgeCache) {
        $vals = (array)$cachedSidebarBadges['values'];
        foreach ($sidebarBadges as $k => $v) {
            if (isset($vals[$k])) $sidebarBadges[$k] = max(0, (int)$vals[$k]);
        }
    } else {
        $canAllBranchesSidebar = 0;
        try {
            $stRole = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
            $stRole->execute([(int)$role_id]);
            $canAllBranchesSidebar = (int)($stRole->fetchColumn() ?? 0);
        } catch (Throwable $e) {
            $canAllBranchesSidebar = 0;
        }

        // Lead badge count (open actionable leads)
        $leadSql = "SELECT COUNT(*) FROM leads l WHERE l.status NOT IN ('converted','closed')";
        $leadParams = [];
        if ($canAllBranchesSidebar !== 1 && $sessionBranchId > 0) {
            $leadSql .= " AND l.branch_id = ?";
            $leadParams[] = $sessionBranchId;
        }
        $leadGlobalRoles = ['Super Admin', 'HR'];
        if (!in_array($sessionRoleName, $leadGlobalRoles, true)) {
            $leadSql .= " AND (l.assigned_to = ? OR l.created_by = ?)";
            $leadParams[] = $sessionUserId;
            $leadParams[] = $sessionUserId;
        }
        $stLead = $pdo->prepare($leadSql);
        $stLead->execute($leadParams);
        $sidebarBadges['lead'] = (int)($stLead->fetchColumn() ?: 0);

        // Enquiry badge count (open actionable enquiries)
        $enqSql = "SELECT COUNT(*) FROM enquiries e WHERE e.status IN ('new','followup')";
        $enqParams = [];
        if ($canAllBranchesSidebar !== 1 && $sessionBranchId > 0) {
            $enqSql .= " AND e.branch_id = ?";
            $enqParams[] = $sessionBranchId;
        }
        $enqGlobalRoles = ['Super Admin', 'HR'];
        if (!in_array($sessionRoleName, $enqGlobalRoles, true)) {
            $enqSql .= " AND (e.handled_by = ? OR e.created_by = ?)";
            $enqParams[] = $sessionUserId;
            $enqParams[] = $sessionUserId;
        }
        $stEnq = $pdo->prepare($enqSql);
        $stEnq->execute($enqParams);
        $sidebarBadges['enquiries'] = (int)($stEnq->fetchColumn() ?: 0);

        // Follow-up badge count (pending follow-ups)
        $fuSql = "
            SELECT COUNT(*)
            FROM enquiry_followups f
            INNER JOIN enquiries e ON e.id = f.enquiry_id
            WHERE LOWER(TRIM(COALESCE(f.status,'pending'))) = 'pending'
        ";
        $fuParams = [];
        if ($canAllBranchesSidebar !== 1 && $sessionBranchId > 0) {
            $fuSql .= " AND f.branch_id = ?";
            $fuParams[] = $sessionBranchId;
        }
        $fuGlobalRoles = ['Super Admin', 'HR'];
        if (!in_array($sessionRoleName, $fuGlobalRoles, true)) {
            $fuSql .= " AND (e.handled_by = ? OR f.created_by = ?)";
            $fuParams[] = $sessionUserId;
            $fuParams[] = $sessionUserId;
        }
        $stFu = $pdo->prepare($fuSql);
        $stFu->execute($fuParams);
        $sidebarBadges['enquiries/followups'] = (int)($stFu->fetchColumn() ?: 0);

        $_SESSION[$sidebarBadgeCacheKey] = [
            'ts' => time(),
            'role_id' => (int)$role_id,
            'branch_id' => (int)$sessionBranchId,
            'values' => $sidebarBadges,
        ];
    }
} catch (Throwable $e) {
    // keep sidebar safe even if badge query fails
}

if (!function_exists('sidebarBadgeText')) {
    function sidebarBadgeText($count): string {
        $c = max(0, (int)$count);
        return $c > 99 ? '99+' : (string)$c;
    }
}
?>

<style>
.sidebar .menu-badge{
    flex:0 0 auto;
    margin-left:8px;
    min-width:24px;
    height:18px;
    border-radius:999px;
    padding:0 6px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:11px;
    font-weight:700;
    color:#fff;
    background:#d81b60;
    line-height:1;
    letter-spacing:.01em;
}
.sidebar .menu-tail{
    margin-left:auto;
    display:inline-flex;
    align-items:center;
    gap:8px;
    flex:0 0 auto;
}
.sidebar .menu-toggle .menu-tail .menu-badge{
    margin-left:0;
}
.sidebar .menu-list li:not(.active) > a .menu-badge,
.sidebar .menu-list li:not(.active) > .menu-toggle .menu-badge{
    opacity:.84;
}
.sidebar .menu-list li.active > a .menu-badge,
.sidebar .menu-list li.active > .menu-toggle .menu-badge{
    opacity:1;
}
.sidebar .menu-list li.has-children > .menu-toggle .menu-tail .caret{
    margin-left:0 !important;
    width:auto !important;
}
.sidebar .menu-label{
    flex:1 1 auto;
    min-width:0;
}
.sidebar .menu-list a .menu-label,
.sidebar .menu-list .menu-toggle .menu-label{
    flex:1 1 auto !important;
    min-width:0;
}
.sidebar .menu-list a .menu-tail,
.sidebar .menu-list .menu-toggle .menu-tail{
    flex:0 0 auto !important;
    margin-left:auto;
    min-width:max-content;
}
.sidebar .menu-list a .menu-badge,
.sidebar .menu-list .menu-toggle .menu-badge{
    flex:0 0 auto !important;
    white-space:nowrap;
}
.sidebar .menu-list li.has-children > .menu-toggle .caret{
    flex:0 0 auto !important;
    margin-left:0 !important;
}
</style>

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
                <span class="menu-label">Dashboard</span>
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
                        <span class="menu-label"><?= htmlspecialchars($parent['menu_name']) ?></span>
                        <?php
                            $parentSlug = strtolower(trim((string)($parent['menu_slug'] ?? '')));
                            $parentName = strtolower(trim((string)($parent['menu_name'] ?? '')));
                            $parentBadgeCount = 0;
                            if ($parentSlug === 'lead' || strpos($parentName, 'lead') !== false) {
                                $parentBadgeCount = (int)($sidebarBadges['lead'] ?? 0);
                            } elseif ($parentSlug === 'enquiries' || strpos($parentName, 'enquir') !== false) {
                                $parentBadgeCount = (int)($sidebarBadges['enquiries'] ?? 0);
                            }
                        ?>
                        <?php if ($parentBadgeCount > 0): ?>
                            <span class="menu-tail">
                                <span class="menu-badge" data-modern-tooltip="Open <?= htmlspecialchars($parent['menu_name']) ?>: <?= (int)$parentBadgeCount ?>">
                                    <?= htmlspecialchars(sidebarBadgeText($parentBadgeCount)) ?>
                                </span>
                                <i class="fas fa-chevron-down caret"></i>
                            </span>
                        <?php else: ?>
                            <span class="menu-tail">
                                <i class="fas fa-chevron-down caret"></i>
                            </span>
                        <?php endif; ?>
                    </button>

                    <ul id="<?= htmlspecialchars($submenuId) ?>" class="submenu" <?= $parentOpen ? '' : 'style="display:none;" hidden' ?>>
                        <?php foreach ($parent['children'] as $child): ?>
                            <li class="<?= ($currentPage === $child['menu_slug']) ? 'active' : '' ?>">
                                <a href="index.php?page=<?= htmlspecialchars($child['menu_slug']) ?>">
                                    <i class="<?= htmlspecialchars($child['icon'] ?: 'fas fa-dot-circle') ?>"></i>
                                    <span class="menu-label"><?= htmlspecialchars($child['menu_name']) ?></span>
                                    <?php
                                        $childSlug = strtolower(trim((string)($child['menu_slug'] ?? '')));
                                        $childBadgeCount = 0;
                                        if ($childSlug === 'enquiries/followups') {
                                            $childBadgeCount = (int)($sidebarBadges['enquiries/followups'] ?? 0);
                                        }
                                    ?>
                                    <?php if ($childBadgeCount > 0): ?>
                                        <span class="menu-badge" data-modern-tooltip="Open <?= htmlspecialchars($child['menu_name']) ?>: <?= (int)$childBadgeCount ?>">
                                            <?= htmlspecialchars(sidebarBadgeText($childBadgeCount)) ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                <?php else: ?>
                    <!-- Normal menu link -->
                    <a href="index.php?page=<?= htmlspecialchars($parent['menu_slug']) ?>" data-tooltip="<?= htmlspecialchars($parent['menu_name']) ?>">
                        <i class="<?= htmlspecialchars($parent['icon'] ?: 'fas fa-circle') ?>"></i>
                        <span class="menu-label"><?= htmlspecialchars($parent['menu_name']) ?></span>
                        <?php
                            $parentSlug = strtolower(trim((string)($parent['menu_slug'] ?? '')));
                            $parentName = strtolower(trim((string)($parent['menu_name'] ?? '')));
                            $parentBadgeCount = 0;
                            if ($parentSlug === 'lead' || strpos($parentName, 'lead') !== false) {
                                $parentBadgeCount = (int)($sidebarBadges['lead'] ?? 0);
                            } elseif ($parentSlug === 'enquiries' || strpos($parentName, 'enquir') !== false) {
                                $parentBadgeCount = (int)($sidebarBadges['enquiries'] ?? 0);
                            } elseif ($parentSlug === 'enquiries/followups') {
                                $parentBadgeCount = (int)($sidebarBadges['enquiries/followups'] ?? 0);
                            }
                        ?>
                        <?php if ($parentBadgeCount > 0): ?>
                            <span class="menu-badge" data-modern-tooltip="Open <?= htmlspecialchars($parent['menu_name']) ?>: <?= (int)$parentBadgeCount ?>">
                                <?= htmlspecialchars(sidebarBadgeText($parentBadgeCount)) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

            </li>
        <?php endforeach; ?>

        <!-- Audit Logs (quick access) -->
        <li class="<?= ($currentPage === 'system/audit_logs') ? 'active' : '' ?>">
            <a href="index.php?page=system/audit_logs" data-tooltip="Audit Logs">
                <i class="fas fa-history"></i>
                <span class="menu-label">Audit Logs</span>
            </a>
        </li>
        <li class="<?= ($currentPage === 'system/onboarding') ? 'active' : '' ?>">
            <a href="index.php?page=system/onboarding" data-tooltip="Onboarding Guide">
                <i class="fas fa-book-open"></i>
                <span class="menu-label">Onboarding Guide</span>
            </a>
        </li>

        <?php if ($isSuperAdminSidebar): ?>
        <li class="<?= ($currentPage === 'system/backup_health') ? 'active' : '' ?>">
            <a href="index.php?page=system/backup_health" data-tooltip="Backup & Health">
                <i class="fas fa-shield-alt"></i>
                <span class="menu-label">Backup & Health</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Logout -->
        <li class="sidebar-logout">
            <a href="logout.php" data-tooltip="Logout">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-label">Logout</span>
            </a>
        </li>

    </ul>

</div>

