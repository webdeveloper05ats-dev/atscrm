<?php
// =======================================================
// Marketing Dashboard
// File: views/dashboard/marketing.php
// =======================================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (($_SESSION['role_name'] ?? '') !== 'Marketing') {
    requireView('dashboard/marketing');
    redirect('index.php');
    exit;
}

$pageTitle = "Marketing Dashboard";

$roleId     = (int)($_SESSION['role_id'] ?? 0);
$userId     = (int)($_SESSION['user_id'] ?? 0);
$userName   = $_SESSION['user_name'] ?? 'Marketing';
$branchName = $_SESSION['branch_name'] ?? 'Branch';

function safeCount(PDO $pdo, string $sql, array $params = []): int {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

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

$skipSlugs = ['dashboard/marketing'];
$quickLinks = array_values(array_filter($quickLinks, function ($m) use ($skipSlugs) {
    return !in_array($m['menu_slug'], $skipSlugs, true);
}));

$myOpenLeads = max(0, $myTotalLeads - $myConvertedLeads - $myClosedLeads);
$myConversionRate = $myTotalLeads > 0 ? round(($myConvertedLeads / $myTotalLeads) * 100, 1) : 0.0;
$myClosureRate = $myTotalLeads > 0 ? round(($myClosedLeads / $myTotalLeads) * 100, 1) : 0.0;
?>

<div class="mk-dashboard">
    <section class="mk-card mk-hero">
        <span class="mk-kicker"><i class="fas fa-bullhorn"></i> Marketing Dashboard</span>
        <h1 class="mk-title">Welcome, <?= h($userName) ?></h1>
        <p class="mk-copy">
            You are handling leads for <strong><?= h($branchName) ?></strong>.
            Track assigned pipeline, conversion movement, and close rates from one focused workspace.
        </p>
        <div class="mk-chip-row">
            <span class="mk-chip"><i class="fas fa-user-check"></i> Active Leads: <?= (int)$myActiveLeads ?></span>
            <span class="mk-chip"><i class="fas fa-percent"></i> Conversion Rate: <?= h(number_format($myConversionRate, 1)) ?>%</span>
            <span class="mk-chip"><i class="fas fa-flag-checkered"></i> Closure Rate: <?= h(number_format($myClosureRate, 1)) ?>%</span>
        </div>
    </section>

    <section class="mk-stats">
        <article class="mk-card mk-stat">
            <p class="mk-stat-label">My Active Leads</p>
            <p class="mk-stat-value"><?= (int)$myActiveLeads ?></p>
            <p class="mk-stat-sub">Need followup or conversion</p>
        </article>

        <article class="mk-card mk-stat">
            <p class="mk-stat-label">My Total Leads</p>
            <p class="mk-stat-value"><?= (int)$myTotalLeads ?></p>
            <p class="mk-stat-sub">Assigned to your account</p>
        </article>

        <article class="mk-card mk-stat">
            <p class="mk-stat-label">Converted</p>
            <p class="mk-stat-value"><?= (int)$myConvertedLeads ?></p>
            <p class="mk-stat-sub"><?= h(number_format($myConversionRate, 1)) ?>% of total</p>
        </article>

        <article class="mk-card mk-stat">
            <p class="mk-stat-label">Closed / Open</p>
            <p class="mk-stat-value"><?= (int)$myClosedLeads ?> / <?= (int)$myOpenLeads ?></p>
            <p class="mk-stat-sub">Closed vs currently open</p>
        </article>
    </section>

    <section class="mk-card mk-links">
        <div class="mk-links-head">
            <h2 class="mk-links-title">Quick Access</h2>
            <span class="mk-links-sub"><?= (int)count($quickLinks) ?> menus</span>
        </div>

        <?php if (empty($quickLinks)): ?>
            <div class="mk-empty">
                No menus assigned to this role yet. Please enable permissions for Marketing role.
            </div>
        <?php else: ?>
            <div class="mk-link-grid">
                <?php foreach ($quickLinks as $m): ?>
                    <?php
                        $slug = $m['menu_slug'];
                        $name = $m['menu_name'];
                        $icon = $m['icon'] ?: 'fas fa-circle';
                    ?>
                    <a class="mk-link" href="index.php?page=<?= h($slug) ?>">
                        <span class="mk-link-icon"><i class="<?= h($icon) ?>"></i></span>
                        <span>
                            <span class="mk-link-name"><?= h($name) ?></span>
                            <span class="mk-link-slug"><?= h($slug) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>


