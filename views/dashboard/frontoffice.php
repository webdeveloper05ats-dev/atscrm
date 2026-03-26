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

<style>
:root{
--fo-pink:#e61b72;
--fo-pink-dark:#b91558;
--fo-ink:#1f2937;
--fo-muted:#6b7280;
--fo-border:#efdbe5;
--fo-surface:#ffffff;
--fo-shadow:0 18px 44px rgba(15, 23, 42, 0.08);
--fo-success:#17986a;
--fo-warning:#d97706;
--fo-danger:#dc4c64;
}

.fo-dashboard{display:flex;flex-direction:column;gap:18px;font-size:14px;}
.fo-hero{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(320px,0.9fr);gap:18px;}
.fo-panel,.fo-hero-main,.fo-hero-side{border:1px solid var(--fo-border);border-radius:26px;overflow:hidden;box-shadow:var(--fo-shadow);}
.fo-hero-main{position:relative;padding:28px;background:radial-gradient(circle at top right, rgba(255,255,255,0.16), transparent 28%),linear-gradient(135deg, rgba(230,27,114,.98), rgba(185,21,88,.96));color:#fff;}
.fo-hero-main::after{content:"";position:absolute;right:-60px;bottom:-70px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.08);}
.fo-hero-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:rgba(255,255,255,.14);font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;}
.fo-hero-title{margin:16px 0 8px;font-size:31px;line-height:1.05;font-weight:800;letter-spacing:-.03em;}
.fo-hero-copy{max-width:680px;font-size:14px;line-height:1.75;color:rgba(255,255,255,.86);}
.fo-hero-meta{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;}
.fo-hero-chip{padding:14px 16px;min-width:160px;border-radius:18px;background:rgba(255,255,255,.12);backdrop-filter:blur(4px);}
.fo-hero-chip-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.76);margin-bottom:6px;}
.fo-hero-chip-value{font-size:18px;font-weight:800;}
.fo-hero-side{padding:22px;background:linear-gradient(180deg, #ffffff 0%, #fff8fb 100%);display:flex;flex-direction:column;gap:16px;}
.fo-side-title{font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--fo-muted);}
.fo-side-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.fo-mini-stat{padding:16px;border-radius:18px;background:#fff;border:1px solid var(--fo-border);}
.fo-mini-stat-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;color:var(--fo-muted);margin-bottom:8px;}
.fo-mini-stat-value{font-size:24px;line-height:1;font-weight:800;color:var(--fo-ink);}
.fo-mini-stat-note{margin-top:8px;font-size:12px;line-height:1.5;color:var(--fo-muted);}
.fo-carry-banner{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:18px;background:#fff4e5;border:1px solid #ffd8a5;color:#a45c00;}
.fo-carry-icon{width:40px;height:40px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:rgba(217,119,6,.12);font-size:16px;}
.fo-carry-text{font-size:14px;line-height:1.6;}
.fo-section{display:flex;flex-direction:column;gap:14px;}
.fo-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;}
.fo-section-title{font-size:20px;font-weight:800;letter-spacing:-.02em;color:var(--fo-ink);}
.fo-section-copy{margin-top:4px;font-size:13px;line-height:1.65;color:var(--fo-muted);}
.fo-action-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;}
.fo-action-card{display:flex;gap:14px;align-items:flex-start;padding:18px;border-radius:22px;border:1px solid var(--fo-border);background:var(--fo-surface);text-decoration:none;color:inherit;box-shadow:0 10px 26px rgba(15,23,42,.05);transition:transform .2s ease, box-shadow .2s ease;}
.fo-action-card:hover{transform:translateY(-2px);box-shadow:0 16px 34px rgba(15,23,42,.08);}
.fo-action-icon{width:52px;height:52px;border-radius:16px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0;}
.fo-action-card.today .fo-action-icon{background:linear-gradient(135deg, #e61b72, #b91558);}
.fo-action-card.missed .fo-action-icon{background:linear-gradient(135deg, #ff7a18, #e85d04);}
.fo-action-card.upcoming .fo-action-icon{background:linear-gradient(135deg, #4f46e5, #3730a3);}
.fo-action-label{font-size:16px;font-weight:800;color:var(--fo-ink);line-height:1.2;}
.fo-action-count{margin-top:6px;font-size:27px;font-weight:800;letter-spacing:-.03em;color:var(--fo-ink);}
.fo-action-caption{margin-top:8px;font-size:13px;line-height:1.6;color:var(--fo-muted);}
.fo-metrics-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;}
.fo-metric-card{padding:18px;border-radius:22px;background:var(--fo-surface);border:1px solid var(--fo-border);box-shadow:0 10px 24px rgba(15,23,42,.04);}
.fo-metric-top{display:flex;align-items:center;justify-content:space-between;gap:12px;}
.fo-metric-title{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--fo-muted);}
.fo-metric-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;}
.fo-tone-rose{background:#e61b72;}.fo-tone-violet{background:#7c3aed;}.fo-tone-cyan{background:#0891b2;}.fo-tone-amber{background:#d97706;}.fo-tone-emerald{background:#059669;}.fo-tone-indigo{background:#4f46e5;}.fo-tone-slate{background:#475569;}
.fo-metric-value{margin-top:16px;font-size:31px;line-height:1;font-weight:800;letter-spacing:-.03em;color:var(--fo-ink);}
.fo-metric-meta{margin-top:10px;font-size:13px;line-height:1.6;color:var(--fo-muted);}
.fo-target-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.95fr);gap:18px;}
.fo-target-panel{padding:22px;background:linear-gradient(180deg,#ffffff 0%,#fff9fc 100%);}
.fo-target-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px;}
.fo-target-kpi{padding:16px;border-radius:18px;background:#fff;border:1px solid var(--fo-border);}
.fo-target-kpi-label{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--fo-muted);margin-bottom:8px;}
.fo-target-kpi-value{font-size:22px;line-height:1.1;font-weight:800;color:var(--fo-ink);}
.fo-progress-wrap{margin-top:18px;}
.fo-progress-head{display:flex;justify-content:space-between;gap:12px;align-items:center;font-size:14px;font-weight:700;color:var(--fo-ink);margin-bottom:10px;}
.fo-progress{height:16px;border-radius:999px;background:#f3dce6;overflow:hidden;}
.fo-progress-bar{height:100%;width:<?= $progressPct ?>%;max-width:100%;min-width:8px;background:linear-gradient(90deg,#e61b72,#ff5ca9);border-radius:999px;}
.fo-progress-meta{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px;font-size:13px;color:var(--fo-muted);}
.fo-target-note{margin-top:16px;padding:14px 16px;border-radius:16px;background:#fff;border:1px solid var(--fo-border);font-size:13px;line-height:1.7;color:var(--fo-muted);}
.fo-target-chart-wrap{padding:22px;display:flex;flex-direction:column;justify-content:space-between;background:radial-gradient(circle at top left, rgba(230,27,114,.08), transparent 28%),linear-gradient(180deg,#ffffff 0%,#fff7fb 100%);}
.fo-chart-shell{max-width:360px;width:100%;margin:8px auto 0;}
.fo-chart-footer{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px;}
.fo-chart-stat{padding:14px 16px;border-radius:18px;border:1px solid var(--fo-border);background:#fff;}
.fo-chart-stat-label{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--fo-muted);margin-bottom:8px;}
.fo-chart-stat-value{font-size:20px;font-weight:800;color:var(--fo-ink);}
@media (max-width:1180px){.fo-hero,.fo-target-grid{grid-template-columns:1fr;}.fo-metrics-grid{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media (max-width:900px){.fo-action-grid,.fo-metrics-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width:640px){.fo-hero-main,.fo-hero-side,.fo-target-panel,.fo-target-chart-wrap,.fo-metric-card,.fo-action-card{padding:16px;border-radius:20px;}.fo-hero-title{font-size:26px;}.fo-hero-meta,.fo-side-grid,.fo-target-kpis,.fo-chart-footer,.fo-action-grid,.fo-metrics-grid{grid-template-columns:1fr;}.fo-side-grid,.fo-target-kpis,.fo-chart-footer{display:grid;}.fo-section-head{flex-direction:column;}.fo-hero-chip{width:100%;}}
</style>

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
                    <div class="fo-hero-chip-value">Rs <?= number_format($finalTarget, 0) ?></div>
                </div>
                <div class="fo-hero-chip">
                    <div class="fo-hero-chip-label">Collections Achieved</div>
                    <div class="fo-hero-chip-value">Rs <?= number_format($achievedAmount, 0) ?></div>
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
                        <strong>Rs <?= number_format($carryForward, 2) ?></strong>
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
                        <div class="fo-target-kpi-value">Rs <?= number_format($targetAmount, 2) ?></div>
                    </div>
                    <div class="fo-target-kpi">
                        <div class="fo-target-kpi-label">Final Target</div>
                        <div class="fo-target-kpi-value">Rs <?= number_format($finalTarget, 2) ?></div>
                    </div>
                    <div class="fo-target-kpi">
                        <div class="fo-target-kpi-label">Achieved</div>
                        <div class="fo-target-kpi-value">Rs <?= number_format($achievedAmount, 2) ?></div>
                    </div>
                    <div class="fo-target-kpi">
                        <div class="fo-target-kpi-label">Balance</div>
                        <div class="fo-target-kpi-value">Rs <?= number_format($balanceAmount, 2) ?></div>
                    </div>
                </div>

                <div class="fo-progress-wrap">
                    <div class="fo-progress-head">
                        <span>Target Completion</span>
                        <span><?= number_format($progressPct, 1) ?>%</span>
                    </div>
                    <div class="fo-progress">
                        <div class="fo-progress-bar"></div>
                    </div>
                    <div class="fo-progress-meta">
                        <span>Carry Forward: Rs <?= number_format($carryForward, 2) ?></span>
                        <span>Collected This Month: Rs <?= number_format($achievedAmount, 2) ?></span>
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
                        <div class="fo-chart-stat-value">Rs <?= number_format($achievedAmount, 2) ?></div>
                    </div>
                    <div class="fo-chart-stat">
                        <div class="fo-chart-stat-label">Balance</div>
                        <div class="fo-chart-stat-value">Rs <?= number_format($balanceAmount, 2) ?></div>
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
