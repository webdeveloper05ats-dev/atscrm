<?php
// =======================================================
// Marketing Dashboard (Phase 1 - Safe)
// File: views/dashboard/marketing.php
// =======================================================

requireView('dashboard/marketing');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// Role protection
if (($_SESSION['role_name'] ?? '') !== 'Marketing') {
    redirect('index.php');
    exit;
}

$pageTitle = "Marketing Dashboard";

$roleId     = (int)($_SESSION['role_id'] ?? 0);
$userId     = (int)($_SESSION['user_id'] ?? 0);
$userName   = $_SESSION['user_name'] ?? 'Marketing';
$branchName = $_SESSION['branch_name'] ?? 'Branch';

// Helpers
function safeCount(PDO $pdo, string $sql, array $params = []): int {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// ===============================
// Stats (based on YOUR leads table)
// status enum: new, followup, converted, closed
// ===============================
$myTotalLeads = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM leads WHERE assigned_to = ?",
    [$userId]
);

$myActiveLeads = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM leads
     WHERE assigned_to = ?
       AND status IN ('new','followup')",
    [$userId]
);

$myConvertedLeads = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM leads
     WHERE assigned_to = ?
       AND status = 'converted'",
    [$userId]
);

$myClosedLeads = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM leads
     WHERE assigned_to = ?
       AND status = 'closed'",
    [$userId]
);

// ===============================
// Quick Access menus (from RBAC)
// ===============================
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

// Flatten for tiles
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

// Skip this dashboard itself
$skipSlugs = ['dashboard/marketing'];
$quickLinks = array_values(array_filter($quickLinks, function($m) use ($skipSlugs) {
    return !in_array($m['menu_slug'], $skipSlugs, true);
}));
?>

<div class="card">
    <div class="card-header">Welcome Marketing 📣</div>
    <p style="line-height:1.7; color: var(--text-dark);">
        Hello <strong><?= htmlspecialchars($userName) ?></strong> —
        You are working under <strong><?= htmlspecialchars($branchName) ?></strong>.
        Track your assigned leads and conversions here.
    </p>
</div>

<div class="dashboard-grid">

    <div class="card stat-card">
        <h3>My Active Leads</h3>
        <h2><?= (int)$myActiveLeads ?></h2>
    </div>

    <div class="card stat-card">
        <h3>My Total Leads</h3>
        <h2><?= (int)$myTotalLeads ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Converted</h3>
        <h2><?= (int)$myConvertedLeads ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Closed</h3>
        <h2><?= (int)$myClosedLeads ?></h2>
    </div>

</div>

<div class="card" style="margin-top:18px;">
    <div class="card-header">Quick Access</div>

    <?php if (empty($quickLinks)): ?>
        <p style="color:var(--text-light);">
            No menus are assigned to this role yet. Please enable permissions for Marketing role.
        </p>
    <?php else: ?>

        <div class="dashboard-grid" style="margin-top:0;">
            <?php foreach ($quickLinks as $m): ?>
                <?php
                    $slug = $m['menu_slug'];
                    $name = $m['menu_name'];
                    $icon = $m['icon'] ?: 'fas fa-circle';
                ?>
                <a href="index.php?page=<?= htmlspecialchars($slug) ?>" style="text-decoration:none;">
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