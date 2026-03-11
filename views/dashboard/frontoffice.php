<?php
// =======================================================
// Front Office Dashboard
// File: views/dashboard/frontoffice.php
// =======================================================

requireView('dashboard/frontoffice');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// Role protection (optional but recommended)
if (($_SESSION['role_name'] ?? '') !== 'Front Office') {
    redirect('index.php');
    exit;
}

$pageTitle = "Front Office Dashboard";

// -------------------------------------------------------
// Branch scope (if role can't access all branches)
// roles.can_access_all_branches : 1 = all branches
// -------------------------------------------------------
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$canAllBranches = 0;
try {
    $r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $r->execute([$roleId]);
    $canAllBranches = (int)($r->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

// Helper to safely run count queries (returns 0 if table/column not exists)
function safeCount(PDO $pdo, string $sql, array $params = []): int {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function safeSum(PDO $pdo, string $sql, array $params = []): float {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (float)$st->fetchColumn();
    } catch (Exception $e) {
        return 0.0;
    }
}

// -------------------------------------------------------
// Dashboard stats (adjust table names if yours differ)
// Uses branch filter if needed (branch_id column assumed)
// -------------------------------------------------------
$params = [];
$branchWhere = "";

if ($canAllBranches !== 1 && $branchId > 0) {
    // If your tables use a different column name, tell me
    $branchWhere = " WHERE branch_id = ? ";
    $params = [$branchId];
}

$totalLeads         = safeCount($pdo, "SELECT COUNT(*) FROM leads" . $branchWhere, $params);
$totalEnquiries     = safeCount($pdo, "SELECT COUNT(*) FROM enquiries" . $branchWhere, $params);
$totalRegistrations = safeCount($pdo, "SELECT COUNT(*) FROM registrations" . $branchWhere, $params);
$totalStudents      = safeCount($pdo, "SELECT COUNT(*) FROM students" . $branchWhere, $params);

// Payments (sum)
$totalRevenue = safeSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM payments" . $branchWhere, $params);

// Optional: "Today" registrations (created_at column assumed)
$todayRegs = 0;
try {
    if ($canAllBranches !== 1 && $branchId > 0) {
        $todayRegs = safeCount(
            $pdo,
            "SELECT COUNT(*) FROM registrations WHERE DATE(created_at)=CURDATE() AND branch_id=?",
            [$branchId]
        );
    } else {
        $todayRegs = safeCount(
            $pdo,
            "SELECT COUNT(*) FROM registrations WHERE DATE(created_at)=CURDATE()",
            []
        );
    }
} catch (Exception $e) {
    $todayRegs = 0;
}

// -------------------------------------------------------
// Quick Access menus (ONLY permitted menus for this role)
// - Shows MAIN menus + children
// - Sidebar already shows menus, but this gives a dashboard shortcut grid
// -------------------------------------------------------
$menus = [];
try {
    $st = $pdo->prepare("
        SELECT m.*
        FROM menus m
        JOIN role_permissions rp ON rp.menu_id = m.id
        WHERE rp.role_id = ?
          AND rp.can_view = 1
          AND m.status = 1
        ORDER BY m.parent_id IS NOT NULL, m.parent_id ASC, m.sort_order ASC
    ");
    $st->execute([$roleId]);
    $menus = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $menus = [];
}

// Build tree
$tree = [];
foreach ($menus as $m) {
    if ($m['parent_id'] === null) {
        $tree[(int)$m['id']] = $m;
        $tree[(int)$m['id']]['children'] = [];
    }
}
foreach ($menus as $m) {
    if ($m['parent_id'] !== null) {
        $pid = (int)$m['parent_id'];
        if (isset($tree[$pid])) {
            $tree[$pid]['children'][] = $m;
        }
    }
}

// Flatten quick links:
// - show children first (more useful)
// - if no children, show parent
$quickLinks = [];
foreach ($tree as $parent) {
    if (!empty($parent['children'])) {
        foreach ($parent['children'] as $child) {
            $quickLinks[] = $child;
        }
    } else {
        $quickLinks[] = $parent;
    }
}

// Remove dashboards/menus you don't want as tiles (optional)
$skipSlugs = [
    'dashboard/frontoffice', // current page
];
$quickLinks = array_values(array_filter($quickLinks, function($m) use ($skipSlugs) {
    return !in_array($m['menu_slug'], $skipSlugs, true);
}));

$userName   = $_SESSION['user_name'] ?? 'Front Office';
$branchName = $_SESSION['branch_name'] ?? 'Branch';
?>

<!-- =========================
     FRONT OFFICE DASHBOARD UI
     ========================= -->

<div class="card">
    <div class="card-header">Welcome Front Office 👋</div>
    <p style="line-height:1.7; color: var(--text-dark);">
        Hello <strong><?= htmlspecialchars($userName) ?></strong> —
        You are working under <strong><?= htmlspecialchars($branchName) ?></strong>.
        Use the quick actions below to manage registrations, students, attendance, certificates, and payments.
    </p>
</div>

<div class="dashboard-grid">

    <div class="card stat-card">
        <h3>Today Registrations</h3>
        <h2><?= (int)$todayRegs ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Registrations</h3>
        <h2><?= (int)$totalRegistrations ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Students</h3>
        <h2><?= (int)$totalStudents ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Enquiries</h3>
        <h2><?= (int)$totalEnquiries ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Leads</h3>
        <h2><?= (int)$totalLeads ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Revenue</h3>
        <h2>₹ <?= number_format((float)$totalRevenue, 2) ?></h2>
    </div>

</div>

<div class="card" style="margin-top:18px;">
    <div class="card-header">Quick Access</div>

    <?php if (empty($quickLinks)): ?>
        <p style="color:var(--text-light);">
            No menus are assigned to this role yet. Please ask Super Admin to enable permissions.
        </p>
    <?php else: ?>

        <div class="dashboard-grid" style="margin-top:0;">
            <?php foreach ($quickLinks as $m): ?>
                <?php
                    $slug = $m['menu_slug'];
                    $name = $m['menu_name'];
                    $icon = $m['icon'] ?: 'fas fa-circle';
                ?>
                <a href="index.php?page=<?= htmlspecialchars($slug) ?>"
                   style="text-decoration:none;">
                    <div class="card stat-card" style="text-align:left;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="font-size:22px; color: var(--primary); width:32px;">
                                <i class="<?= htmlspecialchars($icon) ?>"></i>
                            </div>
                            <div>
                                <div style="font-weight:800; color: var(--text-dark);">
                                    <?= htmlspecialchars($name) ?>
                                </div>
                                <div style="font-size:12px; color: var(--text-light); margin-top:2px;">
                                    <?= htmlspecialchars($slug) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>