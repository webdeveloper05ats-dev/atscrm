<?php
// =======================================================
// Marketing Dashboard
// File: views/dashboard/marketing.php
// =======================================================

requireView('dashboard/marketing');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (($_SESSION['role_name'] ?? '') !== 'Marketing') {
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

<style>
.mk-dashboard {
    --mk-primary: #d9468b;
    --mk-primary-dark: #b83273;
    --mk-soft: #fff0f7;
    --mk-ink: #4a1f39;
    --mk-muted: #8a6177;
    --mk-border: #efd7e5;
    --mk-card: #ffffff;
    --mk-shadow: 0 10px 24px rgba(38, 24, 45, 0.06);

    display: grid;
    gap: 18px;
    padding: 8px 0 20px;
}

.mk-card {
    background: var(--mk-card);
    border: 1px solid var(--mk-border);
    border-radius: 20px;
    box-shadow: var(--mk-shadow);
}

.mk-hero {
    padding: 22px;
    background:
        linear-gradient(135deg, rgba(217, 70, 139, 0.12), rgba(255, 255, 255, 0.95)),
        linear-gradient(180deg, #fff 0%, #fff7fb 100%);
}

.mk-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid rgba(217, 70, 139, 0.2);
    background: #fff;
    color: var(--mk-primary-dark);
    font-size: 11px;
    letter-spacing: .08em;
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: 12px;
}

.mk-title {
    margin: 0;
    color: var(--mk-ink);
    font-size: 1.55rem;
    line-height: 1.15;
    font-weight: 800;
}

.mk-copy {
    margin: 10px 0 0;
    max-width: 900px;
    line-height: 1.7;
    color: #5a4660;
    font-size: .93rem;
}

.mk-chip-row {
    margin-top: 14px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.mk-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    border: 1px solid var(--mk-border);
    padding: 8px 12px;
    background: #fff;
    color: #6b4b5e;
    font-size: .8rem;
    font-weight: 700;
}

.mk-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.mk-stat {
    padding: 15px;
    position: relative;
    overflow: hidden;
}

.mk-stat::after {
    content: '';
    position: absolute;
    right: -18px;
    bottom: -18px;
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: rgba(217, 70, 139, 0.11);
}

.mk-stat-label {
    margin: 0;
    color: var(--mk-muted);
    font-size: .77rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.mk-stat-value {
    margin: 7px 0 0;
    font-size: 1.7rem;
    color: var(--mk-ink);
    font-weight: 800;
    line-height: 1.1;
}

.mk-stat-sub {
    margin-top: 6px;
    color: var(--mk-muted);
    font-size: .78rem;
    font-weight: 700;
}

.mk-links {
    padding: 16px;
}

.mk-links-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.mk-links-title {
    margin: 0;
    color: var(--mk-ink);
    font-size: 1.05rem;
    font-weight: 800;
}

.mk-links-sub {
    color: var(--mk-muted);
    font-size: .82rem;
    font-weight: 700;
}

.mk-link-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}

.mk-link {
    text-decoration: none;
    border: 1px solid var(--mk-border);
    border-radius: 14px;
    background: #fff;
    padding: 12px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    transition: all .2s ease;
}

.mk-link:hover {
    transform: translateY(-2px);
    border-color: #ddb7ca;
    box-shadow: 0 10px 20px rgba(195, 80, 140, 0.12);
    text-decoration: none;
}

.mk-link-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--mk-soft);
    border: 1px solid var(--mk-border);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--mk-primary-dark);
    font-size: 16px;
    flex-shrink: 0;
}

.mk-link-name {
    margin: 0;
    color: #2f2330;
    font-size: .92rem;
    font-weight: 800;
    line-height: 1.3;
    display: block;
}

.mk-link-slug {
    margin-top: 3px;
    color: var(--mk-muted);
    font-size: .72rem;
    font-weight: 600;
    line-height: 1.35;
    word-break: break-word;
    display: block;
}

.mk-empty {
    border: 1px dashed var(--mk-border);
    border-radius: 14px;
    padding: 14px;
    color: var(--mk-muted);
    background: #fff;
    font-size: .88rem;
}

@media (max-width: 1100px) {
    .mk-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .mk-link-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px) {
    .mk-title { font-size: 1.28rem; }
    .mk-stats { grid-template-columns: 1fr; }
    .mk-link-grid { grid-template-columns: 1fr; }
}
</style>

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
