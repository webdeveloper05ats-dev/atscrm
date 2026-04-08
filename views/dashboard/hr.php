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

<style>
.hrd-dashboard {
    --hr-rose-50: #fff0f7;
    --hr-rose-100: #fff0f7;
    --hr-rose-200: #efd7e5;
    --hr-rose-500: #d9468b;
    --hr-rose-600: #b83273;
    --hr-ink-900: #4a1f39;
    --hr-ink-700: #6b4b5e;
    --hr-ink-500: #8a6177;
    --hr-border: #efd7e5;
    --hr-card: #ffffff;
    --hr-shadow: 0 10px 24px rgba(38, 24, 45, 0.06);

    padding: 18px;
    background:
        radial-gradient(circle at top left, rgba(217,70,139,0.10), transparent 22%),
        radial-gradient(circle at top right, rgba(217,70,139,0.08), transparent 20%),
        #f7f4f8;
}

.hrd-hero {
    border: 1px solid var(--hr-border);
    border-radius: 18px;
    background: linear-gradient(135deg, #ffffff 0%, #fff7f9 100%);
    box-shadow: var(--hr-shadow);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.hrd-title {
    margin: 0;
    color: var(--hr-ink-900);
    font-size: 1.35rem;
    font-weight: 800;
    letter-spacing: 0.2px;
}

.hrd-sub {
    margin: 6px 0 0;
    color: var(--hr-ink-700);
    font-size: 0.92rem;
    max-width: 760px;
}

.hrd-chip-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.hrd-chip {
    background: var(--hr-rose-100);
    border: 1px solid var(--hr-rose-200);
    color: var(--hr-rose-600);
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 0.8rem;
    font-weight: 700;
    white-space: nowrap;
}

.hrd-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 16px;
}

.hrd-kpi {
    background: var(--hr-card);
    border: 1px solid var(--hr-border);
    border-radius: 16px;
    box-shadow: var(--hr-shadow);
    padding: 14px;
    position: relative;
    overflow: hidden;
}

.hrd-kpi::after {
    content: '';
    position: absolute;
    right: -24px;
    bottom: -24px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    opacity: 0.16;
}

.hrd-kpi.is-leads::after { background: #f7931e; }
.hrd-kpi.is-enquiries::after { background: #0ea5a4; }
.hrd-kpi.is-students::after { background: #22c55e; }
.hrd-kpi.is-revenue::after { background: #d9468b; }

.hrd-kpi-label {
    margin: 0;
    color: var(--hr-ink-700);
    font-weight: 700;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.45px;
}

.hrd-kpi-value {
    margin: 6px 0 4px;
    color: var(--hr-ink-900);
    font-size: 1.6rem;
    line-height: 1.15;
    font-weight: 800;
}

.hrd-kpi-meta {
    color: var(--hr-ink-500);
    font-size: 0.84rem;
    font-weight: 600;
}

.hrd-layout {
    display: grid;
    grid-template-columns: 1.25fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
}

.hrd-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.hrd-card {
    background: var(--hr-card);
    border: 1px solid var(--hr-border);
    border-radius: 16px;
    box-shadow: var(--hr-shadow);
    padding: 16px;
}

.hrd-card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.hrd-card-title {
    margin: 0;
    font-size: 1.02rem;
    color: var(--hr-ink-900);
    font-weight: 800;
}

.hrd-muted {
    color: var(--hr-ink-500);
    font-size: 0.82rem;
    font-weight: 600;
}

.hrd-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.hrd-item {
    border: 1px solid #f2ebef;
    background: #fff;
    border-radius: 12px;
    padding: 10px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.hrd-item-main {
    min-width: 0;
}

.hrd-item-name {
    font-weight: 700;
    color: var(--hr-ink-900);
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hrd-item-sub {
    font-size: 0.83rem;
    color: var(--hr-ink-500);
}

.hrd-time {
    color: var(--hr-rose-600);
    font-weight: 700;
    font-size: 0.8rem;
    white-space: nowrap;
}

.hrd-table-wrap {
    overflow-x: auto;
}

.hrd-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 420px;
}

.hrd-table th,
.hrd-table td {
    border-bottom: 1px solid #f2ebef;
    padding: 9px 4px;
    text-align: left;
    font-size: 0.86rem;
}

.hrd-table th {
    color: var(--hr-ink-500);
    text-transform: uppercase;
    letter-spacing: 0.35px;
    font-size: 0.74rem;
    font-weight: 800;
}

.hrd-table td {
    color: var(--hr-ink-900);
}

.hrd-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.25px;
    border: 1px solid transparent;
}

.hrd-badge.is-positive {
    color: #0e8e4a;
    background: #e7f8ee;
    border-color: #bceccd;
}

.hrd-badge.is-neutral {
    color: #7a4b00;
    background: #fff3de;
    border-color: #ffdca4;
}

.hrd-badge.is-info {
    color: #0f5f96;
    background: #e8f4ff;
    border-color: #c2e1ff;
}

.hrd-empty {
    padding: 14px;
    border: 1px dashed #e7d7de;
    border-radius: 12px;
    color: var(--hr-ink-500);
    font-size: 0.88rem;
    background: #fff;
}

.hrd-chart-wrap {
    position: relative;
    height: 292px;
}

@media (max-width: 1280px) {
    .hrd-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .hrd-layout { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    .hrd-dashboard { padding: 12px; }
    .hrd-grid { grid-template-columns: 1fr; }
    .hrd-row { grid-template-columns: 1fr; }
    .hrd-card { padding: 13px; }
    .hrd-chart-wrap { height: 250px; }
}

/* =====================================================
GLOBAL TYPOGRAPHY STYLECSS SYNC
font-family + font-size + font-weight only
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){font-size:clamp(2rem, 2.5vw, 2.4rem) !important;font-weight:700 !important;}
:where(h2,.h2,.section-title){font-size:clamp(1.6rem, 2vw, 2rem) !important;font-weight:600 !important;}
:where(h3,.h3,.card-header,.table-title){font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;font-weight:600 !important;}
:where(h4,.h4){font-size:1.2rem !important;font-weight:500 !important;}
:where(h5,.h5){font-size:1rem !important;font-weight:500 !important;}
:where(h6,.h6){font-size:0.9rem !important;font-weight:500 !important;}
:where(body){font-size:1rem !important;}
:where(p,.text-body,li,td,.text-muted,.help-text,.form-text,.small,small,.secondary-text){font-size:0.95rem !important;font-weight:400 !important;}
:where(.small,small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){font-size:0.85rem !important;font-weight:400 !important;}
:where(label,.form-label){font-size:0.85rem !important;font-weight:500 !important;}
:where(input,select,textarea,.form-control,.form-select){font-size:0.95rem !important;font-weight:400 !important;}
:where(input::placeholder,textarea::placeholder){font-weight:400 !important;}
:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){font-size:0.9rem !important;font-weight:600 !important;}
:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{font-size:0.75rem !important;font-weight:600 !important;}
:where(.table th,.crm-table th,.dataTables_wrapper th,th){font-size:0.75rem !important;font-weight:600 !important;}
:where(.table td,.dataTables_wrapper tbody td){font-size:0.9rem !important;}
:where(.dataTables_wrapper .dataTables_info){font-size:0.85rem !important;font-weight:400 !important;}
:where(.dataTables_wrapper .paginate_button){font-size:0.9rem !important;font-weight:600 !important;}
:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){font-weight:600 !important;}

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>

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
            <span class="hrd-chip">This Month Revenue: Rs <?= h(number_format($monthRevenue, 2)) ?></span>
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
            <p class="hrd-kpi-value">Rs <?= h(number_format($totalRevenue, 2)) ?></p>
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
                <span class="hrd-muted">Peak: Rs <?= h(number_format($peakRevenue, 2)) ?></span>
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

