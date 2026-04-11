<?php
// =======================================================
// Front Office Dashboard
// =======================================================

requireView('dashboard/frontoffice');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (($_SESSION['role_name'] ?? '') !== 'Front Office') {
    redirect('index.php');
    exit;
}

$pageTitle = "Front Office Dashboard";

/* ======================================================
USER DETAILS
====================================================== */

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Front Office';
$branchName = $_SESSION['branch_name'] ?? 'Branch';

$todayFollowups = 0;
$missedFollowups = 0;
$upcomingFollowups = 0;

try {
    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM enquiry_followups
        WHERE followup_date = CURDATE()
          AND status = 'pending'
          AND created_by = ?
    ");
    $st->execute([$userId]);
    $todayFollowups = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM enquiry_followups
        WHERE followup_date < CURDATE()
          AND status = 'pending'
          AND created_by = ?
    ");
    $st->execute([$userId]);
    $missedFollowups = (int)$st->fetchColumn();

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM enquiry_followups
        WHERE followup_date > CURDATE()
          AND status = 'pending'
          AND created_by = ?
    ");
    $st->execute([$userId]);
    $upcomingFollowups = (int)$st->fetchColumn();
} catch (Exception $e) {
    $todayFollowups = 0;
    $missedFollowups = 0;
    $upcomingFollowups = 0;
}

function safeCount($pdo, $sql, $params = [])
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function safeSum($pdo, $sql, $params = [])
{
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (float)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

$todayRegs = safeCount(
    $pdo,
    "SELECT COUNT(*) 
     FROM registrations 
     WHERE DATE(created_at)=CURDATE()
       AND created_by=?",
    [$userId]
);

$totalRegistrations = safeCount(
    $pdo,
    "SELECT COUNT(*) 
     FROM registrations
     WHERE created_by=?",
    [$userId]
);

$internshipStudents = safeCount(
    $pdo,
    "SELECT COUNT(*)
     FROM registrations
     WHERE reg_type='internship'
       AND created_by=?",
    [$userId]
);

$courseStudents = safeCount(
    $pdo,
    "SELECT COUNT(*)
     FROM registrations
     WHERE reg_type='course'
       AND created_by=?",
    [$userId]
);

$totalStudents = $internshipStudents + $courseStudents;

$totalEnquiries = safeCount(
    $pdo,
    "SELECT COUNT(*)
     FROM enquiries
     WHERE handled_by=?",
    [$userId]
);

$convertedEnquiries = safeCount(
    $pdo,
    "SELECT COUNT(*)
     FROM enquiries
     WHERE handled_by=?
       AND status='converted'",
    [$userId]
);

$missedEnquiries = $totalEnquiries - $convertedEnquiries;

$totalLeads = safeCount(
    $pdo,
    "SELECT COUNT(*)
     FROM leads
     WHERE assigned_to=?",
    [$userId]
);

$convertedLeads = safeCount(
    $pdo,
    "SELECT COUNT(*)
     FROM leads
     WHERE assigned_to=?
       AND status='converted'",
    [$userId]
);

$missedLeads = $totalLeads - $convertedLeads;

$currentMonth = date('n');
$currentYear = date('Y');
$monthName = date('F');

$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth === 0) {
    $prevMonth = 12;
    $prevYear--;
}

$prevTarget = safeSum(
    $pdo,
    "SELECT target_amount
     FROM monthly_targets
     WHERE user_id=?
       AND target_month=?
       AND target_year=?
       AND status='active'
     LIMIT 1",
    [$userId, $prevMonth, $prevYear]
);

$prevAchieved = safeSum(
    $pdo,
    "SELECT IFNULL(SUM(amount),0)
     FROM registration_payments
     WHERE staff_id=?
       AND MONTH(payment_date)=?
       AND YEAR(payment_date)=?",
    [$userId, $prevMonth, $prevYear]
);

$carryForward = max($prevTarget - $prevAchieved, 0);

$targetAmount = safeSum(
    $pdo,
    "SELECT target_amount
     FROM monthly_targets
     WHERE user_id=?
       AND target_month=?
       AND target_year=?
       AND status='active'
     LIMIT 1",
    [$userId, $currentMonth, $currentYear]
);

$finalTarget = $targetAmount + $carryForward;

$achievedAmount = safeSum(
    $pdo,
    "SELECT IFNULL(SUM(amount),0)
     FROM registration_payments
     WHERE staff_id=?
       AND MONTH(payment_date)=?
       AND YEAR(payment_date)=?",
    [$userId, $currentMonth, $currentYear]
);

$balanceAmount = max($finalTarget - $achievedAmount, 0);
$progressPct = $finalTarget > 0 ? ($achievedAmount / $finalTarget) * 100 : 0;
$progressPct = max(0, min($progressPct, 100));

$quickActions = [
    [
        'tone' => 'today',
        'icon' => 'fas fa-bell',
        'count' => $todayFollowups,
        'label' => 'Today Followups',
        'caption' => 'Calls and conversations due today',
        'link' => 'index.php?page=enquiries/followups&tab=today',
    ],
    [
        'tone' => 'missed',
        'icon' => 'fas fa-exclamation-triangle',
        'count' => $missedFollowups,
        'label' => 'Missed Followups',
        'caption' => 'Pending followups that slipped behind',
        'link' => 'index.php?page=enquiries/followups&tab=missed',
    ],
    [
        'tone' => 'upcoming',
        'icon' => 'fas fa-calendar-alt',
        'count' => $upcomingFollowups,
        'label' => 'Upcoming Followups',
        'caption' => 'Next scheduled followup commitments',
        'link' => 'index.php?page=enquiries/followups&tab=pending',
    ],
];

$pipelineCards = [
    ['title' => 'Today Registrations', 'value' => $todayRegs, 'meta' => 'Registrations added today by you', 'tone' => 'rose'],
    ['title' => 'Total Registrations', 'value' => $totalRegistrations, 'meta' => 'All registrations created under your login', 'tone' => 'violet'],
    ['title' => 'Total Students', 'value' => $totalStudents, 'meta' => 'Course and internship students combined', 'tone' => 'cyan'],
    ['title' => 'Internship Students', 'value' => $internshipStudents, 'meta' => 'Students registered in internship programs', 'tone' => 'amber'],
    ['title' => 'Course Students', 'value' => $courseStudents, 'meta' => 'Students registered in course programs', 'tone' => 'emerald'],
    ['title' => 'Enquiries', 'value' => $totalEnquiries, 'meta' => 'Converted: ' . $convertedEnquiries . ' | Open/Missed: ' . $missedEnquiries, 'tone' => 'indigo'],
    ['title' => 'Leads', 'value' => $totalLeads, 'meta' => 'Converted: ' . $convertedLeads . ' | Open/Missed: ' . $missedLeads, 'tone' => 'slate'],
];
?>

<div class="fo-dashboard">
    <section class="fo-hero">
        <div class="fo-hero-main">
            <div class="fo-hero-eyebrow">Front Office Operations</div>
            <h1 class="fo-hero-title">Welcome back, <?= htmlspecialchars($userName) ?></h1>
            <div class="fo-hero-copy">
                This dashboard gives you a live view of your followups, conversion workload, registration activity, and collection target performance for <?= htmlspecialchars($branchName) ?>.
            </div>
            <div class="fo-hero-meta">
                <div class="fo-hero-chip">
                    <div class="fo-hero-chip-label">Branch</div>
                    <div class="fo-hero-chip-value"><?= htmlspecialchars($branchName) ?></div>
                </div>
                <div class="fo-hero-chip">
                    <div class="fo-hero-chip-label">This Month Target</div>
                    <div class="fo-hero-chip-value"><?= inr_symbol() ?> <?= number_format($finalTarget, 0) ?></div>
                </div>
                <div class="fo-hero-chip">
                    <div class="fo-hero-chip-label">Collections Achieved</div>
                    <div class="fo-hero-chip-value"><?= inr_symbol() ?> <?= number_format($achievedAmount, 0) ?></div>
                </div>
            </div>
        </div>

        <div class="fo-hero-side">
            <div class="fo-side-title">Today At A Glance</div>

            <?php if ($carryForward > 0): ?>
                <div class="fo-carry-banner">
                    <div class="fo-carry-icon"><i class="fas fa-bolt"></i></div>
                    <div class="fo-carry-text">
                        Carry forward from <?= date('F', mktime(0, 0, 0, $prevMonth, 1)) ?>:
                        <strong><?= inr_symbol() ?> <?= number_format($carryForward, 2) ?></strong>
                    </div>
                </div>
            <?php endif; ?>

            <div class="fo-side-grid">
                <div class="fo-mini-stat">
                    <div class="fo-mini-stat-label">Registrations Today</div>
                    <div class="fo-mini-stat-value"><?= $todayRegs ?></div>
                    <div class="fo-mini-stat-note">New registrations created under your login today.</div>
                </div>
                <div class="fo-mini-stat">
                    <div class="fo-mini-stat-label">Open Followups</div>
                    <div class="fo-mini-stat-value"><?= $todayFollowups + $missedFollowups + $upcomingFollowups ?></div>
                    <div class="fo-mini-stat-note">Combined workload across today, missed, and upcoming followups.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="fo-section">
        <div class="fo-section-head">
            <div>
                <div class="fo-section-title">Priority Followups</div>
                <div class="fo-section-copy">Jump straight into the most important communication buckets for the day.</div>
            </div>
        </div>

        <div class="fo-action-grid">
            <?php foreach ($quickActions as $item): ?>
                <a class="fo-action-card <?= htmlspecialchars($item['tone']) ?>" href="<?= htmlspecialchars($item['link']) ?>">
                    <div class="fo-action-icon"><i class="<?= htmlspecialchars($item['icon']) ?>"></i></div>
                    <div class="fo-action-content">
                        <div class="fo-action-label"><?= htmlspecialchars($item['label']) ?></div>
                        <div class="fo-action-count"><?= (int)$item['count'] ?></div>
                        <div class="fo-action-caption"><?= htmlspecialchars($item['caption']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="fo-section">
        <div class="fo-section-head">
            <div>
                <div class="fo-section-title">Pipeline Snapshot</div>
                <div class="fo-section-copy">A quick operational summary of registrations, students, enquiries, and leads currently linked to you.</div>
            </div>
        </div>

        <div class="fo-metrics-grid">
            <?php foreach ($pipelineCards as $card): ?>
                <div class="fo-metric-card">
                    <div class="fo-metric-top">
                        <div class="fo-metric-title"><?= htmlspecialchars($card['title']) ?></div>
                        <span class="fo-metric-dot fo-tone-<?= htmlspecialchars($card['tone']) ?>"></span>
                    </div>
                    <div class="fo-metric-value"><?= (int)$card['value'] ?></div>
                    <div class="fo-metric-meta"><?= htmlspecialchars($card['meta']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="fo-section">
        <div class="fo-section-head">
            <div>
                <div class="fo-section-title"><?= htmlspecialchars($monthName) ?> Target Health</div>
                <div class="fo-section-copy">See how your monthly collection target is shaping up, including carry-forward and current achievement.</div>
            </div>
        </div>

        <div class="fo-target-grid">
            <div class="fo-panel fo-target-panel">
                <div class="fo-side-title">Collection Summary</div>
                <div class="fo-target-kpis">
                    <div class="fo-target-kpi">
                        <div class="fo-target-kpi-label">Base Target</div>
                        <div class="fo-target-kpi-value"><?= inr_symbol() ?> <?= number_format($targetAmount, 2) ?></div>
                    </div>
                    <div class="fo-target-kpi">
                        <div class="fo-target-kpi-label">Final Target</div>
                        <div class="fo-target-kpi-value"><?= inr_symbol() ?> <?= number_format($finalTarget, 2) ?></div>
                    </div>
                    <div class="fo-target-kpi">
                        <div class="fo-target-kpi-label">Achieved</div>
                        <div class="fo-target-kpi-value"><?= inr_symbol() ?> <?= number_format($achievedAmount, 2) ?></div>
                    </div>
                    <div class="fo-target-kpi">
                        <div class="fo-target-kpi-label">Balance</div>
                        <div class="fo-target-kpi-value"><?= inr_symbol() ?> <?= number_format($balanceAmount, 2) ?></div>
                    </div>
                </div>

                <div class="fo-progress-wrap">
                    <div class="fo-progress-head">
                        <span>Target Completion</span>
                        <span><?= number_format($progressPct, 1) ?>%</span>
                    </div>
                    <div class="fo-progress">
                        <div class="fo-progress-bar" style="width:<?= $progressPct ?>%"></div>
                    </div>
                    <div class="fo-progress-meta">
                        <span>Carry Forward: <?= inr_symbol() ?> <?= number_format($carryForward, 2) ?></span>
                        <span>Collected This Month: <?= inr_symbol() ?> <?= number_format($achievedAmount, 2) ?></span>
                    </div>
                </div>

                <div class="fo-target-note">
                    <?= $balanceAmount > 0
                        ? 'There is still Rs ' . number_format($balanceAmount, 2) . ' left to close this month\'s final target.'
                        : 'You have fully covered the active target for this month. Additional collections now move you beyond target.' ?>
                </div>
            </div>

            <div class="fo-panel fo-target-chart-wrap">
                <div>
                    <div class="fo-side-title">Achievement Mix</div>
                    <div class="fo-section-copy">A visual split of collected amount versus remaining balance for the active monthly target.</div>
                </div>

                <div class="fo-chart-shell">
                    <canvas id="targetChart"></canvas>
                </div>

                <div class="fo-chart-footer">
                    <div class="fo-chart-stat">
                        <div class="fo-chart-stat-label">Achieved</div>
                        <div class="fo-chart-stat-value"><?= inr_symbol() ?> <?= number_format($achievedAmount, 2) ?></div>
                    </div>
                    <div class="fo-chart-stat">
                        <div class="fo-chart-stat-label">Balance</div>
                        <div class="fo-chart-stat-value"><?= inr_symbol() ?> <?= number_format($balanceAmount, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('targetChart');

new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Achieved', 'Balance'],
        datasets: [{
            data: [<?= $achievedAmount ?>, <?= $balanceAmount ?>],
            backgroundColor: ['#e61b72', '#f4cddd'],
            borderColor: ['#e61b72', '#f4cddd'],
            borderWidth: 0,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '68%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    boxWidth: 10,
                    color: '#6b7280',
                    padding: 18,
                    font: {
                        size: 12,
                        weight: '600'
                    }
                }
            },
            tooltip: {
                backgroundColor: '#111827',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return context.label + ': Rs ' + Number(context.raw).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
            }
        }
    }
});
</script>



