<?php
// =======================================================
// HR Dashboard
// File: views/dashboard/hr.php
// =======================================================

requireView('dashboard/hr');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// Role protection (optional but recommended)
if (($_SESSION['role_name'] ?? '') !== 'HR') {
    redirect('index.php');
    exit;
}

$pageTitle = "HR Dashboard";

// -------------------------------------------------------
// Branch scope
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

function safeSum(PDO $pdo, string $sql, array $params = []): float {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (float)$st->fetchColumn();
    } catch (Exception $e) {
        return 0.0;
    }
}

$params = [];
$branchWhere = "";

if ($canAllBranches !== 1 && $branchId > 0) {
    $branchWhere = " WHERE branch_id = ? ";
    $params = [$branchId];
}

// -------------------------------------------------------
// HR Stats (table names assumed as in your DB list)
// -------------------------------------------------------
$totalLeads     = safeCount($pdo, "SELECT COUNT(*) FROM leads" . $branchWhere, $params);
$totalEnquiries = safeCount($pdo, "SELECT COUNT(*) FROM enquiries" . $branchWhere, $params);

$totalInterviews = safeCount($pdo, "SELECT COUNT(*) FROM interviews" . $branchWhere, $params);
$totalPlacements = safeCount($pdo, "SELECT COUNT(*) FROM placements" . $branchWhere, $params);

// Optional: Today interviews (interview_date column assumed)
$todayInterviews = 0;
try {
    if ($canAllBranches !== 1 && $branchId > 0) {
        $todayInterviews = safeCount(
            $pdo,
            "SELECT COUNT(*) FROM interviews WHERE DATE(interview_date)=CURDATE() AND branch_id=?",
            [$branchId]
        );
    } else {
        $todayInterviews = safeCount(
            $pdo,
            "SELECT COUNT(*) FROM interviews WHERE DATE(interview_date)=CURDATE()",
            []
        );
    }
} catch (Exception $e) {
    $todayInterviews = 0;
}

// Revenue (payments)
$totalRevenue = safeSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM payments" . $branchWhere, $params);

// -------------------------------------------------------
// Quick Access menus for HR
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

// Skip the dashboard itself (optional)
$skipSlugs = ['dashboard/hr'];
$quickLinks = array_values(array_filter($quickLinks, function($m) use ($skipSlugs) {
    return !in_array($m['menu_slug'], $skipSlugs, true);
}));

$userName   = $_SESSION['user_name'] ?? 'HR';
$branchName = $_SESSION['branch_name'] ?? 'Branch';
?>

<div class="card">
    <div class="card-header">Welcome HR 👩‍💼</div>
    <p style="line-height:1.7; color: var(--text-dark);">
        Hello <strong><?= htmlspecialchars($userName) ?></strong> —
        You are working under <strong><?= htmlspecialchars($branchName) ?></strong>.
        Track interviews, placement progress, and conversion outcomes here.
    </p>
</div>

<div class="dashboard-grid">

    <div class="card stat-card">
        <h3>Today Interviews</h3>
        <h2><?= (int)$todayInterviews ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Interviews</h3>
        <h2><?= (int)$totalInterviews ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Placements</h3>
        <h2><?= (int)$totalPlacements ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Leads</h3>
        <h2><?= (int)$totalLeads ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Enquiries</h3>
        <h2><?= (int)$totalEnquiries ?></h2>
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