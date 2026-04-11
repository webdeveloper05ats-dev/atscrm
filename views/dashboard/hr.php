<?php
if (!defined('APP_NAME')) {
    die("Unauthorized");
}

if (($_SESSION['role_name'] ?? '') !== 'HR') {
    redirect('index.php');
    exit;
}

if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function hrCount(PDO $pdo, string $sql): int {
    try {
        return (int)($pdo->query($sql)->fetchColumn() ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

function hrSum(PDO $pdo, string $sql): float {
    try {
        return (float)($pdo->query($sql)->fetchColumn() ?? 0);
    } catch (Exception $e) {
        return 0.0;
    }
}

function hrSafeDate(?string $dateValue, string $format = 'd M Y'): string {
    $v = trim((string)$dateValue);
    if ($v === '') {
        return '-';
    }
    $ts = strtotime($v);
    if ($ts === false) {
        return h($v);
    }
    return date($format, $ts);
}

function hrPct(int $part, int $total): float {
    if ($total <= 0) {
        return 0.0;
    }
    return round(($part / $total) * 100, 1);
}

$totalLeads = hrCount($pdo, "SELECT COUNT(*) FROM leads");
$leadConverted = hrCount($pdo, "SELECT COUNT(*) FROM leads WHERE status='converted'");
$leadMissed = max(0, $totalLeads - $leadConverted);
$leadRate = hrPct($leadConverted, $totalLeads);

$totalEnquiries = hrCount($pdo, "SELECT COUNT(*) FROM enquiries");
$enqConverted = hrCount($pdo, "SELECT COUNT(*) FROM enquiries WHERE status='converted'");
$enqMissed = max(0, $totalEnquiries - $enqConverted);
$enqRate = hrPct($enqConverted, $totalEnquiries);

$totalStudents = hrCount($pdo, "SELECT COUNT(*) FROM registrations WHERE registration_status='active'");
$completedStudents = hrCount($pdo, "SELECT COUNT(*) FROM registrations WHERE registration_status='completed'");
$ongoingStudents = max(0, $totalStudents - $completedStudents);
$completionRate = hrPct($completedStudents, max(1, $completedStudents + $ongoingStudents));

$totalRevenue = hrSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM registration_payments");
$monthRevenue = hrSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM registration_payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())");
$todayFollowupCount = hrCount($pdo, "SELECT COUNT(*) FROM enquiry_followups WHERE DATE(followup_date)=CURDATE()");

$todayFollowups = [];
try {
    $todayFollowups = $pdo->query("
        SELECT e.name, e.phone, f.followup_time, f.followup_date
        FROM enquiry_followups f
        JOIN enquiries e ON e.id = f.enquiry_id
        WHERE DATE(f.followup_date) = CURDATE()
        ORDER BY COALESCE(f.followup_time, '23:59:59') ASC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $todayFollowups = [];
}

$interviews = [];
try {
    $interviews = $pdo->query("
        SELECT company_name, interview_date, status
        FROM interviews
        ORDER BY interview_date DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $interviews = [];
}

$regs = [];
try {
    $regs = $pdo->query("
        SELECT enquiry_snapshot_name, program_name, registration_status, joined_on, created_at
        FROM registrations
        ORDER BY id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $regs = [];
}

$chartLabelMap = [];
$chartValueMap = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabelMap[$d] = date('d M', strtotime($d));
    $chartValueMap[$d] = 0.0;
}

try {
    $stmt = $pdo->query("
        SELECT payment_date AS pay_day, SUM(amount) AS revenue
        FROM registration_payments
        WHERE payment_date >= CURDATE() - INTERVAL 13 DAY
        GROUP BY payment_date
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $day = (string)($row['pay_day'] ?? '');
        if (isset($chartValueMap[$day])) {
            $chartValueMap[$day] = (float)($row['revenue'] ?? 0);
        }
    }
} catch (Exception $e) {
    // Keep prefilled zeros if query fails
}

$chartLabels = array_values($chartLabelMap);
$chartData = array_values($chartValueMap);
$peakRevenue = (float)(count($chartData) ? max($chartData) : 0);

$todayRevenue = hrSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM registration_payments WHERE payment_date=CURDATE()");
$last7Revenue = hrSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM registration_payments WHERE payment_date >= CURDATE() - INTERVAL 6 DAY");
$prev7Revenue = hrSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM registration_payments WHERE payment_date BETWEEN CURDATE() - INTERVAL 13 DAY AND CURDATE() - INTERVAL 7 DAY");
$revenueTrendPct = ($prev7Revenue > 0) ? round((($last7Revenue - $prev7Revenue) / $prev7Revenue) * 100, 1) : ($last7Revenue > 0 ? 100.0 : 0.0);

$missedFollowups = hrCount($pdo, "SELECT COUNT(*) FROM enquiry_followups WHERE followup_date < CURDATE() AND status='pending'");
$upcomingFollowups = hrCount($pdo, "SELECT COUNT(*) FROM enquiry_followups WHERE followup_date > CURDATE() AND status='pending'");

$selectedInterviews = hrCount($pdo, "SELECT COUNT(*) FROM interviews WHERE LOWER(TRIM(status)) IN ('selected','completed','done','success')");
$scheduledInterviews = hrCount($pdo, "SELECT COUNT(*) FROM interviews WHERE LOWER(TRIM(status)) IN ('scheduled','pending','in progress','ongoing')");

$dueStudents = hrCount($pdo, "SELECT COUNT(*) FROM registrations WHERE registration_status='active' AND balance_amount > 0");
$dueAmount = hrSum($pdo, "SELECT IFNULL(SUM(balance_amount),0) FROM registrations WHERE registration_status='active' AND balance_amount > 0");
$dueSharePct = hrPct($dueStudents, max(1, $totalStudents));

$hrUserId = (int)($_SESSION['user_id'] ?? 0);
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

$hrTargetAmount = hrSum(
    $pdo,
    "SELECT IFNULL(SUM(target_amount),0)
     FROM monthly_targets
     WHERE user_id = {$hrUserId}
       AND target_month = {$currentMonth}
       AND target_year = {$currentYear}
       AND status='active'"
);
$hrAchievedAmount = hrSum(
    $pdo,
    "SELECT IFNULL(SUM(amount),0)
     FROM registration_payments
     WHERE staff_id = {$hrUserId}
       AND MONTH(payment_date) = {$currentMonth}
       AND YEAR(payment_date) = {$currentYear}"
);
$hrTargetCompletionPct = ($hrTargetAmount > 0) ? round(($hrAchievedAmount / $hrTargetAmount) * 100, 1) : 0.0;
$hrTargetCompletionPct = max(0.0, min(100.0, $hrTargetCompletionPct));

$overallTargetAmount = hrSum(
    $pdo,
    "SELECT IFNULL(SUM(mt.target_amount),0)
     FROM monthly_targets mt
     WHERE mt.target_month = {$currentMonth}
       AND mt.target_year = {$currentYear}
       AND mt.status = 'active'"
);
$overallAchievedAmount = hrSum(
    $pdo,
    "SELECT IFNULL(SUM(rp.amount),0)
     FROM registration_payments rp
     INNER JOIN (
        SELECT DISTINCT user_id
        FROM monthly_targets
        WHERE target_month = {$currentMonth}
          AND target_year = {$currentYear}
          AND status = 'active'
     ) tu ON tu.user_id = rp.staff_id
     WHERE MONTH(rp.payment_date) = {$currentMonth}
       AND YEAR(rp.payment_date) = {$currentYear}"
);
$overallTargetCompletionPct = ($overallTargetAmount > 0) ? round(($overallAchievedAmount / $overallTargetAmount) * 100, 1) : 0.0;
$overallTargetCompletionPct = max(0.0, min(100.0, $overallTargetCompletionPct));

$priorityItems = [
    [
        'tone' => $missedFollowups > 0 ? 'is-danger' : 'is-neutral',
        'title' => 'Missed Followups',
        'meta' => $missedFollowups > 0 ? 'Needs immediate recovery calls' : 'No missed items pending',
        'value' => number_format($missedFollowups),
        'is_currency' => false,
        'link' => 'index.php?page=enquiries/followups&tab=missed',
    ],
    [
        'tone' => $dueAmount > 0 ? 'is-warn' : 'is-neutral',
        'title' => 'Pending Collections',
        'meta' => number_format($dueStudents) . ' students with open balance',
        'value' => number_format($dueAmount, 2),
        'is_currency' => true,
        'link' => 'index.php?page=reports/payment',
    ],
    [
        'tone' => $scheduledInterviews > 0 ? 'is-info' : 'is-neutral',
        'title' => 'Interview Pipeline',
        'meta' => number_format($selectedInterviews) . ' selected so far',
        'value' => number_format($scheduledInterviews) . ' scheduled',
        'is_currency' => false,
        'link' => 'index.php?page=interviews/schedule',
    ],
];
?>

<div class="hrd-dashboard">
    <section class="hrd-hero">
        <div class="hrd-hero-grid">
            <div>
                <h2 class="hrd-title">HR Performance Dashboard</h2>
                <p class="hrd-sub">
                    A live view of lead movement, enquiry conversion, active students, and collection momentum to support day-to-day HR operations.
                </p>
                <div class="hrd-chip-row">
                    <span class="hrd-chip">Today Followups: <?= h(number_format($todayFollowupCount)) ?></span>
                    <span class="hrd-chip">This Month Revenue: <?= inr_symbol() ?> <?= h(number_format($monthRevenue, 2)) ?></span>
                    <span class="hrd-chip">Lead Conversion: <?= h(number_format($leadRate, 1)) ?>%</span>
                </div>
            </div>
            <div class="hrd-hero-aside">
                <div class="hrd-hero-stat">
                    <span>Today Revenue</span>
                    <strong><?= inr_symbol() ?> <?= h(number_format($todayRevenue, 2)) ?></strong>
                </div>
                <div class="hrd-hero-stat">
                    <span>7-Day Trend</span>
                    <strong class="<?= $revenueTrendPct >= 0 ? 'is-positive' : 'is-negative' ?>">
                        <?= $revenueTrendPct >= 0 ? '+' : '' ?><?= h(number_format($revenueTrendPct, 1)) ?>%
                    </strong>
                </div>
                <div class="hrd-hero-stat">
                    <span>Due Share</span>
                    <strong><?= h(number_format($dueSharePct, 1)) ?>%</strong>
                </div>
            </div>
        </div>
        <div class="hrd-quick-actions">
            <a href="index.php?page=enquiries/followups&ui=add" class="hrd-action-btn">Add Followup</a>
            <a href="index.php?page=interviews/schedule" class="hrd-action-btn">Interview Schedule</a>
            <a href="index.php?page=reports/payment" class="hrd-action-btn">Payment Report</a>
        </div>
    </section>

    <section class="hrd-health-strip">
        <article class="hrd-health-card tone-danger">
            <div class="hrd-health-flip">
                <div class="hrd-health-face hrd-health-front">
                    <div class="hrd-health-head">
                        <p class="hrd-health-label">Missed Followups</p>
                        <span class="hrd-health-icon"><i class="fas fa-triangle-exclamation"></i></span>
                    </div>
                    <p class="hrd-health-value"><?= h(number_format($missedFollowups)) ?></p>
                    <p class="hrd-health-meta">Upcoming: <?= h(number_format($upcomingFollowups)) ?></p>
                </div>
                <div class="hrd-health-face hrd-health-back">
                    <p class="hrd-health-back-title">Action Insight</p>
                    <p class="hrd-health-back-copy">Start with overdue callbacks first. Clearing missed followups improves conversion speed.</p>
                </div>
            </div>
        </article>
        <article class="hrd-health-card tone-info">
            <div class="hrd-health-flip">
                <div class="hrd-health-face hrd-health-front">
                    <div class="hrd-health-head">
                        <p class="hrd-health-label">Selected Interviews</p>
                        <span class="hrd-health-icon"><i class="fas fa-user-check"></i></span>
                    </div>
                    <p class="hrd-health-value"><?= h(number_format($selectedInterviews)) ?></p>
                    <p class="hrd-health-meta">Scheduled: <?= h(number_format($scheduledInterviews)) ?></p>
                </div>
                <div class="hrd-health-face hrd-health-back">
                    <p class="hrd-health-back-title">Action Insight</p>
                    <p class="hrd-health-back-copy">Keep interview feedback updated every day so scheduled candidates can move faster.</p>
                </div>
            </div>
        </article>
        <article class="hrd-health-card tone-warn">
            <div class="hrd-health-flip">
                <div class="hrd-health-face hrd-health-front">
                    <div class="hrd-health-head">
                        <p class="hrd-health-label">Due Students</p>
                        <span class="hrd-health-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    </div>
                    <p class="hrd-health-value"><?= h(number_format($dueStudents)) ?></p>
                    <p class="hrd-health-meta"><?= inr_symbol() ?> <?= h(number_format($dueAmount, 2)) ?> pending</p>
                </div>
                <div class="hrd-health-face hrd-health-back">
                    <p class="hrd-health-back-title">Action Insight</p>
                    <p class="hrd-health-back-copy">Prioritize high-balance students first and run reminder followups before due date.</p>
                </div>
            </div>
        </article>
        <article class="hrd-health-card tone-success">
            <div class="hrd-health-flip">
                <div class="hrd-health-face hrd-health-front">
                    <div class="hrd-health-head">
                        <p class="hrd-health-label">7-Day Revenue</p>
                        <span class="hrd-health-icon"><i class="fas fa-chart-line"></i></span>
                    </div>
                    <p class="hrd-health-value"><?= inr_symbol() ?> <?= h(number_format($last7Revenue, 2)) ?></p>
                    <p class="hrd-health-meta">Prev 7 days: <?= inr_symbol() ?> <?= h(number_format($prev7Revenue, 2)) ?></p>
                </div>
                <div class="hrd-health-face hrd-health-back">
                    <p class="hrd-health-back-title">Action Insight</p>
                    <p class="hrd-health-back-copy">Watch weekly momentum against the previous week to catch slowdowns early.</p>
                </div>
            </div>
        </article>
        <article class="hrd-health-card tone-progress">
            <div class="hrd-health-flip">
                <div class="hrd-health-face hrd-health-front">
                    <div class="hrd-health-head">
                        <p class="hrd-health-label">HR Target Completion</p>
                        <span class="hrd-health-icon"><i class="fas fa-bullseye"></i></span>
                    </div>
                    <p class="hrd-health-value"><?= h(number_format($hrTargetCompletionPct, 1)) ?>%</p>
                    <p class="hrd-health-meta"><?= inr_symbol() ?> <?= h(number_format($hrAchievedAmount, 2)) ?> / <?= inr_symbol() ?> <?= h(number_format($hrTargetAmount, 2)) ?></p>
                    <div class="hrd-health-meter"><span style="width: <?= h(number_format($hrTargetCompletionPct, 1)) ?>%"></span></div>
                </div>
                <div class="hrd-health-face hrd-health-back">
                    <p class="hrd-health-back-title">Action Insight</p>
                    <p class="hrd-health-back-copy">This is your personal monthly target. Try to stay above 70% by mid-month.</p>
                </div>
            </div>
        </article>
        <article class="hrd-health-card tone-progress">
            <div class="hrd-health-flip">
                <div class="hrd-health-face hrd-health-front">
                    <div class="hrd-health-head">
                        <p class="hrd-health-label">Overall Staff Target</p>
                        <span class="hrd-health-icon"><i class="fas fa-users"></i></span>
                    </div>
                    <p class="hrd-health-value"><?= h(number_format($overallTargetCompletionPct, 1)) ?>%</p>
                    <p class="hrd-health-meta"><?= inr_symbol() ?> <?= h(number_format($overallAchievedAmount, 2)) ?> / <?= inr_symbol() ?> <?= h(number_format($overallTargetAmount, 2)) ?></p>
                    <div class="hrd-health-meter"><span style="width: <?= h(number_format($overallTargetCompletionPct, 1)) ?>%"></span></div>
                </div>
                <div class="hrd-health-face hrd-health-back">
                    <p class="hrd-health-back-title">Action Insight</p>
                    <p class="hrd-health-back-copy">Team progress for staff with active targets this month. Use it for weekly reviews.</p>
                </div>
            </div>
        </article>
    </section>

    <section class="hrd-grid">
        <article class="hrd-kpi is-leads">
            <div class="hrd-kpi-flip">
                <div class="hrd-kpi-face hrd-kpi-front">
                    <p class="hrd-kpi-label">Total Leads</p>
                    <p class="hrd-kpi-value"><?= h(number_format($totalLeads)) ?></p>
                    <p class="hrd-kpi-meta"><?= h(number_format($leadConverted)) ?> converted | <?= h(number_format($leadMissed)) ?> open</p>
                </div>
                <div class="hrd-kpi-face hrd-kpi-back">
                    <p class="hrd-kpi-back-title">Lead Conversion</p>
                    <p class="hrd-kpi-back-value"><?= h(number_format($leadRate, 1)) ?>%</p>
                    <p class="hrd-kpi-back-copy">Converted leads against total leads.</p>
                </div>
            </div>
        </article>

        <article class="hrd-kpi is-enquiries">
            <div class="hrd-kpi-flip">
                <div class="hrd-kpi-face hrd-kpi-front">
                    <p class="hrd-kpi-label">Total Enquiries</p>
                    <p class="hrd-kpi-value"><?= h(number_format($totalEnquiries)) ?></p>
                    <p class="hrd-kpi-meta"><?= h(number_format($enqConverted)) ?> converted | <?= h(number_format($enqMissed)) ?> open</p>
                </div>
                <div class="hrd-kpi-face hrd-kpi-back">
                    <p class="hrd-kpi-back-title">Enquiry Conversion</p>
                    <p class="hrd-kpi-back-value"><?= h(number_format($enqRate, 1)) ?>%</p>
                    <p class="hrd-kpi-back-copy">Converted enquiries against total enquiries.</p>
                </div>
            </div>
        </article>

        <article class="hrd-kpi is-students">
            <div class="hrd-kpi-flip">
                <div class="hrd-kpi-face hrd-kpi-front">
                    <p class="hrd-kpi-label">Active Students</p>
                    <p class="hrd-kpi-value"><?= h(number_format($totalStudents)) ?></p>
                    <p class="hrd-kpi-meta"><?= h(number_format($completedStudents)) ?> completed | <?= h(number_format($ongoingStudents)) ?> ongoing</p>
                </div>
                <div class="hrd-kpi-face hrd-kpi-back">
                    <p class="hrd-kpi-back-title">Completion Focus</p>
                    <p class="hrd-kpi-back-value"><?= h(number_format($completionRate, 1)) ?>%</p>
                    <p class="hrd-kpi-back-copy">Completed students as a share of tracked students.</p>
                </div>
            </div>
        </article>

        <article class="hrd-kpi is-revenue">
            <div class="hrd-kpi-flip">
                <div class="hrd-kpi-face hrd-kpi-front">
                    <p class="hrd-kpi-label">Lifetime Revenue</p>
                    <p class="hrd-kpi-value"><?= inr_symbol() ?> <?= h(number_format($totalRevenue, 2)) ?></p>
                    <p class="hrd-kpi-meta">Completion rate: <?= h(number_format($completionRate, 1)) ?>%</p>
                </div>
                <div class="hrd-kpi-face hrd-kpi-back">
                    <p class="hrd-kpi-back-title">Revenue Signal</p>
                    <p class="hrd-kpi-back-value"><?= inr_symbol() ?> <?= h(number_format($monthRevenue, 2)) ?></p>
                    <p class="hrd-kpi-back-copy">Current month collection from registration payments.</p>
                </div>
            </div>
        </article>
    </section>

    <section class="hrd-layout">
        <article class="hrd-card">
            <div class="hrd-card-head">
                <h3 class="hrd-card-title">Priority Desk</h3>
                <span class="hrd-muted">Action Queue</span>
            </div>

            <div class="hrd-priority-list">
                <?php foreach ($priorityItems as $item): ?>
                    <a href="<?= h($item['link']) ?>" class="hrd-priority-item <?= h($item['tone']) ?>">
                        <div>
                            <div class="hrd-priority-title"><?= h($item['title']) ?></div>
                            <div class="hrd-priority-meta"><?= h($item['meta']) ?></div>
                        </div>
                        <div class="hrd-priority-value">
                            <?php if (!empty($item['is_currency'])): ?>
                                <?= inr_symbol() ?> <?= h($item['value']) ?>
                            <?php else: ?>
                                <?= h($item['value']) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="hrd-card-head hrd-subhead">
                <h4 class="hrd-card-mini-title">Today's Followups</h4>
                <span class="hrd-muted">Top 5</span>
            </div>

            <?php if (empty($todayFollowups)): ?>
                <div class="hrd-empty">No followups scheduled for today.</div>
            <?php else: ?>
                <div class="hrd-list">
                    <?php foreach ($todayFollowups as $f): ?>
                        <div class="hrd-item">
                            <div class="hrd-item-main">
                                <div class="hrd-item-name"><?= h($f['name'] ?? '-') ?></div>
                                <div class="hrd-item-sub"><?= h($f['phone'] ?? '-') ?> | <?= h(hrSafeDate($f['followup_date'] ?? '', 'd M Y')) ?></div>
                            </div>
                            <span class="hrd-time"><?= h(hrSafeDate($f['followup_time'] ?? '', 'h:i A')) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="hrd-card">
            <div class="hrd-card-head">
                <h3 class="hrd-card-title">Revenue Trend (Last 14 Days)</h3>
                <span class="hrd-muted">Peak: <?= inr_symbol() ?> <?= h(number_format($peakRevenue, 2)) ?></span>
            </div>
            <div class="hrd-chart-wrap">
                <canvas id="hrRevenueChart"></canvas>
            </div>
        </article>
    </section>

    <section class="hrd-row">
        <article class="hrd-card">
            <div class="hrd-card-head">
                <h3 class="hrd-card-title">Recent Interviews</h3>
                <span class="hrd-muted">Latest 5</span>
            </div>

            <?php if (empty($interviews)): ?>
                <div class="hrd-empty">No interview records found.</div>
            <?php else: ?>
                <div class="hrd-table-wrap">
                    <table class="hrd-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($interviews as $i):
                                $status = strtolower(trim((string)($i['status'] ?? '')));
                                $statusClass = 'is-neutral';
                                if (in_array($status, ['selected', 'completed', 'done', 'success'], true)) {
                                    $statusClass = 'is-positive';
                                } elseif (in_array($status, ['scheduled', 'pending', 'in progress', 'ongoing'], true)) {
                                    $statusClass = 'is-info';
                                }
                            ?>
                                <tr>
                                    <td><?= h($i['company_name'] ?? '-') ?></td>
                                    <td><?= h(hrSafeDate($i['interview_date'] ?? '')) ?></td>
                                    <td><span class="hrd-badge <?= h($statusClass) ?>"><?= h($i['status'] ?? '-') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="hrd-card">
            <div class="hrd-card-head">
                <h3 class="hrd-card-title">Recent Registrations</h3>
                <span class="hrd-muted">Latest 5</span>
            </div>

            <?php if (empty($regs)): ?>
                <div class="hrd-empty">No registrations found yet.</div>
            <?php else: ?>
                <div class="hrd-table-wrap">
                    <table class="hrd-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Program</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($regs as $r):
                                $rs = strtolower(trim((string)($r['registration_status'] ?? '')));
                                $rClass = 'is-neutral';
                                if ($rs === 'active' || $rs === 'completed') {
                                    $rClass = 'is-positive';
                                } elseif ($rs === 'draft') {
                                    $rClass = 'is-info';
                                }
                            ?>
                                <tr>
                                    <td><?= h($r['enquiry_snapshot_name'] ?? '-') ?></td>
                                    <td><?= h($r['program_name'] ?? '-') ?></td>
                                    <td><span class="hrd-badge <?= h($rClass) ?>"><?= h(ucfirst($r['registration_status'] ?? '-')) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
    const el = document.getElementById('hrRevenueChart');
    if (!el || typeof Chart === 'undefined') return;

    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_SLASHES) ?>;
    const values = <?= json_encode($chartData, JSON_NUMERIC_CHECK) ?>;
    const trendDirection = <?= json_encode($revenueTrendPct >= 0 ? 'up' : 'down') ?>;

    new Chart(el, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: values,
                borderColor: '#e11d74',
                backgroundColor: trendDirection === 'up' ? 'rgba(225, 29, 116, 0.16)' : 'rgba(244, 63, 94, 0.16)',
                pointBackgroundColor: '#e11d74',
                pointBorderColor: '#fff',
                pointRadius: 3,
                pointHoverRadius: 5,
                fill: true,
                tension: 0.34,
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx){
                            return 'Revenue: Rs ' + Number(ctx.raw || 0).toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#6f7786', maxRotation: 0, autoSkip: true, maxTicksLimit: 7 }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#6f7786',
                        callback: function(value){
                            return 'Rs ' + Number(value || 0).toLocaleString('en-IN');
                        }
                    },
                    grid: { color: 'rgba(110, 118, 133, 0.14)' }
                }
            }
        }
    });
})();
</script>



