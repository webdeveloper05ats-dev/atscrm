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

$totalRevenue = hrSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM payments");
$monthRevenue = hrSum($pdo, "SELECT IFNULL(SUM(amount),0) FROM payments WHERE MONTH(COALESCE(payment_date, created_at))=MONTH(CURDATE()) AND YEAR(COALESCE(payment_date, created_at))=YEAR(CURDATE())");
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
        SELECT DATE(COALESCE(payment_date, created_at)) AS pay_day, SUM(amount) AS revenue
        FROM payments
        WHERE DATE(COALESCE(payment_date, created_at)) >= CURDATE() - INTERVAL 13 DAY
        GROUP BY DATE(COALESCE(payment_date, created_at))
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
?>

<div class="hrd-dashboard">
    <section class="hrd-hero">
        <div>
            <h2 class="hrd-title">HR Performance Dashboard</h2>
            <p class="hrd-sub">
                A live view of lead movement, enquiry conversion, active students, and collection momentum to support day-to-day HR operations.
            </p>
        </div>
        <div class="hrd-chip-row">
            <span class="hrd-chip">Today Followups: <?= h(number_format($todayFollowupCount)) ?></span>
            <span class="hrd-chip">This Month Revenue: <?= inr_symbol() ?> <?= h(number_format($monthRevenue, 2)) ?></span>
            <span class="hrd-chip">Lead Conversion: <?= h(number_format($leadRate, 1)) ?>%</span>
        </div>
    </section>

    <section class="hrd-grid">
        <article class="hrd-kpi is-leads">
            <p class="hrd-kpi-label">Total Leads</p>
            <p class="hrd-kpi-value"><?= h(number_format($totalLeads)) ?></p>
            <p class="hrd-kpi-meta"><?= h(number_format($leadConverted)) ?> converted | <?= h(number_format($leadMissed)) ?> open</p>
        </article>

        <article class="hrd-kpi is-enquiries">
            <p class="hrd-kpi-label">Total Enquiries</p>
            <p class="hrd-kpi-value"><?= h(number_format($totalEnquiries)) ?></p>
            <p class="hrd-kpi-meta"><?= h(number_format($enqConverted)) ?> converted | <?= h(number_format($enqMissed)) ?> open</p>
        </article>

        <article class="hrd-kpi is-students">
            <p class="hrd-kpi-label">Active Students</p>
            <p class="hrd-kpi-value"><?= h(number_format($totalStudents)) ?></p>
            <p class="hrd-kpi-meta"><?= h(number_format($completedStudents)) ?> completed | <?= h(number_format($ongoingStudents)) ?> ongoing</p>
        </article>

        <article class="hrd-kpi is-revenue">
            <p class="hrd-kpi-label">Lifetime Revenue</p>
            <p class="hrd-kpi-value"><?= inr_symbol() ?> <?= h(number_format($totalRevenue, 2)) ?></p>
            <p class="hrd-kpi-meta">Completion rate: <?= h(number_format($completionRate, 1)) ?>%</p>
        </article>
    </section>

    <section class="hrd-layout">
        <article class="hrd-card">
            <div class="hrd-card-head">
                <h3 class="hrd-card-title">Today's Followups</h3>
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

    new Chart(el, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data: values,
                borderColor: '#d9468b',
                backgroundColor: 'rgba(217, 70, 139, 0.14)',
                pointBackgroundColor: '#d9468b',
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



