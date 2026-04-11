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

function safeSum(PDO $pdo, string $sql, array $params = []): float {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (float)($st->fetchColumn() ?? 0);
    } catch (Exception $e) {
        return 0.0;
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
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$myTodayLeads = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND DATE(created_at)=CURDATE()",
    [$userId]
);
$myWeekLeads = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND DATE(created_at) >= CURDATE() - INTERVAL 6 DAY",
    [$userId]
);
$myStaleLeads = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM leads WHERE assigned_to = ? AND status IN ('new','followup') AND DATE(updated_at) <= CURDATE() - INTERVAL 3 DAY",
    [$userId]
);
$myOpenRate = $myTotalLeads > 0 ? round(($myOpenLeads / $myTotalLeads) * 100, 1) : 0.0;
$targetAmount = safeSum(
    $pdo,
    "SELECT IFNULL(SUM(target_amount),0)
     FROM monthly_targets
     WHERE user_id = ?
       AND target_month = ?
       AND target_year = ?
       AND status='active'",
    [$userId, $currentMonth, $currentYear]
);
$achievedAmount = safeSum(
    $pdo,
    "SELECT IFNULL(SUM(amount),0)
     FROM registration_payments
     WHERE staff_id = ?
       AND MONTH(payment_date) = ?
       AND YEAR(payment_date) = ?",
    [$userId, $currentMonth, $currentYear]
);
$balanceAmount = max($targetAmount - $achievedAmount, 0);
$targetPct = $targetAmount > 0 ? round(($achievedAmount / $targetAmount) * 100, 1) : 0.0;
$targetPct = max(0.0, min(100.0, $targetPct));
$monthName = date('F');

$healthCards = [
    [
        'title' => 'Today New Leads',
        'value' => number_format($myTodayLeads),
        'meta' => 'Last 7 days: ' . number_format($myWeekLeads),
        'insight' => 'Track source quality for daily lead spikes.',
        'tone' => 'tone-rose',
        'icon' => 'fas fa-bolt',
    ],
    [
        'title' => 'Stale Followups',
        'value' => number_format($myStaleLeads),
        'meta' => 'Leads untouched for 3+ days',
        'insight' => 'Follow up stale leads first to avoid drop-offs.',
        'tone' => 'tone-amber',
        'icon' => 'fas fa-hourglass-half',
    ],
    [
        'title' => 'Open Lead Share',
        'value' => number_format($myOpenRate, 1) . '%',
        'meta' => number_format($myOpenLeads) . ' open out of ' . number_format($myTotalLeads),
        'insight' => 'Lower open share usually means stronger closure rhythm.',
        'tone' => 'tone-plum',
        'icon' => 'fas fa-chart-pie',
    ],
    [
        'title' => 'Conversion Momentum',
        'value' => number_format($myConversionRate, 1) . '%',
        'meta' => number_format($myConvertedLeads) . ' converted leads',
        'insight' => 'Sustain momentum with same-day callbacks.',
        'tone' => 'tone-violet',
        'icon' => 'fas fa-rocket',
    ],
];
?>

<div class="mk-dashboard">
    <section class="mk-card mk-hero">
        <div class="mk-hero-grid">
            <div>
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
            </div>
            <aside class="mk-hero-aside">
                <div class="mk-live-pill"><span class="mk-pulse"></span> Live Pipeline</div>
                <div class="mk-hero-kpi">
                    <span>Open Leads</span>
                    <strong><?= (int)$myOpenLeads ?></strong>
                </div>
                <div class="mk-hero-kpi">
                    <span>Closed Leads</span>
                    <strong><?= (int)$myClosedLeads ?></strong>
                </div>
                <div class="mk-hero-actions">
                    <a href="index.php?page=leads/add" class="mk-action-btn">Add Lead</a>
                    <a href="index.php?page=leads/list" class="mk-action-btn">Lead List</a>
                </div>
            </aside>
        </div>
    </section>

    <section class="mk-health-strip">
        <?php foreach ($healthCards as $hc): ?>
            <article class="mk-health-card <?= h($hc['tone']) ?>">
                <div class="mk-health-flip">
                    <div class="mk-health-face mk-health-front">
                        <div class="mk-health-head">
                            <p class="mk-health-label"><?= h($hc['title']) ?></p>
                            <span class="mk-health-icon"><i class="<?= h($hc['icon']) ?>"></i></span>
                        </div>
                        <p class="mk-health-value"><?= h($hc['value']) ?></p>
                        <p class="mk-health-meta"><?= h($hc['meta']) ?></p>
                    </div>
                    <div class="mk-health-face mk-health-back">
                        <p class="mk-health-back-title"><?= h($hc['title']) ?></p>
                        <p class="mk-health-back-copy"><?= h($hc['insight']) ?></p>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="mk-stats">
        <article class="mk-card mk-stat">
            <div class="mk-stat-flip">
                <div class="mk-stat-face mk-stat-front">
                    <p class="mk-stat-label">My Active Leads</p>
                    <p class="mk-stat-value"><?= (int)$myActiveLeads ?></p>
                    <p class="mk-stat-sub">Need followup or conversion</p>
                </div>
                <div class="mk-stat-face mk-stat-back">
                    <p class="mk-stat-back-title">Focus Tip</p>
                    <p class="mk-stat-back-value"><?= h(number_format($myOpenRate, 1)) ?>%</p>
                    <p class="mk-stat-back-copy">Open lead share of your total assigned pipeline.</p>
                </div>
            </div>
        </article>

        <article class="mk-card mk-stat">
            <div class="mk-stat-flip">
                <div class="mk-stat-face mk-stat-front">
                    <p class="mk-stat-label">My Total Leads</p>
                    <p class="mk-stat-value"><?= (int)$myTotalLeads ?></p>
                    <p class="mk-stat-sub">Assigned to your account</p>
                </div>
                <div class="mk-stat-face mk-stat-back">
                    <p class="mk-stat-back-title">Weekly Intake</p>
                    <p class="mk-stat-back-value"><?= (int)$myWeekLeads ?></p>
                    <p class="mk-stat-back-copy">Leads added in the last 7 days.</p>
                </div>
            </div>
        </article>

        <article class="mk-card mk-stat">
            <div class="mk-stat-flip">
                <div class="mk-stat-face mk-stat-front">
                    <p class="mk-stat-label">Converted</p>
                    <p class="mk-stat-value"><?= (int)$myConvertedLeads ?></p>
                    <p class="mk-stat-sub"><?= h(number_format($myConversionRate, 1)) ?>% of total</p>
                </div>
                <div class="mk-stat-face mk-stat-back">
                    <p class="mk-stat-back-title">Conversion Rate</p>
                    <p class="mk-stat-back-value"><?= h(number_format($myConversionRate, 1)) ?>%</p>
                    <p class="mk-stat-back-copy">Converted leads against assigned leads.</p>
                </div>
            </div>
        </article>

        <article class="mk-card mk-stat">
            <div class="mk-stat-flip">
                <div class="mk-stat-face mk-stat-front">
                    <p class="mk-stat-label">Closed / Open</p>
                    <p class="mk-stat-value"><?= (int)$myClosedLeads ?> / <?= (int)$myOpenLeads ?></p>
                    <p class="mk-stat-sub">Closed vs currently open</p>
                </div>
                <div class="mk-stat-face mk-stat-back">
                    <p class="mk-stat-back-title">Closure Rate</p>
                    <p class="mk-stat-back-value"><?= h(number_format($myClosureRate, 1)) ?>%</p>
                    <p class="mk-stat-back-copy">Leads marked closed from your total pipeline.</p>
                </div>
            </div>
        </article>
    </section>

    <section class="mk-target-grid">
        <article class="mk-card mk-target-card">
            <div class="mk-links-head">
                <h2 class="mk-links-title"><?= h($monthName) ?> Target Health</h2>
                <span class="mk-links-sub"><?= h(number_format($targetPct, 1)) ?>% completed</span>
            </div>
            <div class="mk-target-kpis">
                <div class="mk-target-kpi">
                    <p class="mk-target-kpi-label">Target</p>
                    <p class="mk-target-kpi-value"><?= inr_symbol() ?> <?= h(number_format($targetAmount, 2)) ?></p>
                </div>
                <div class="mk-target-kpi">
                    <p class="mk-target-kpi-label">Achieved</p>
                    <p class="mk-target-kpi-value"><?= inr_symbol() ?> <?= h(number_format($achievedAmount, 2)) ?></p>
                </div>
                <div class="mk-target-kpi">
                    <p class="mk-target-kpi-label">Balance</p>
                    <p class="mk-target-kpi-value"><?= inr_symbol() ?> <?= h(number_format($balanceAmount, 2)) ?></p>
                </div>
                <div class="mk-target-kpi">
                    <p class="mk-target-kpi-label">Completion</p>
                    <p class="mk-target-kpi-value"><?= h(number_format($targetPct, 1)) ?>%</p>
                </div>
            </div>
            <div class="mk-target-progress">
                <span style="width:<?= h(number_format($targetPct, 1)) ?>%"></span>
            </div>
        </article>

        <article class="mk-card mk-target-chart-card">
            <div class="mk-links-head">
                <h2 class="mk-links-title">Target Mix</h2>
                <span class="mk-links-sub">Achieved vs Balance</span>
            </div>
            <div class="mk-target-chart-shell">
                <canvas id="mkTargetChart"></canvas>
            </div>
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
                        <span class="mk-link-body">
                            <span class="mk-link-name"><?= h($name) ?></span>
                            <span class="mk-link-slug"><?= h($slug) ?></span>
                        </span>
                        <span class="mk-link-arrow"><i class="fas fa-arrow-up-right-from-square"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
    const el = document.getElementById('mkTargetChart');
    if (!el || typeof Chart === 'undefined') return;

    new Chart(el, {
        type: 'doughnut',
        data: {
            labels: ['Achieved', 'Balance'],
            datasets: [{
                data: [<?= $achievedAmount ?>, <?= $balanceAmount ?>],
                backgroundColor: ['#e11d74', '#fbcfe8'],
                borderColor: ['#e11d74', '#fbcfe8'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        color: '#7f6071',
                        padding: 14
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context){
                            return context.label + ': Rs ' + Number(context.raw || 0).toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            }
        }
    });
})();
</script>


