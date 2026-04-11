<?php

if (!isLoggedIn()) {
    redirect('login.php');
    exit;
}

if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
    redirect('index.php');
    exit;
}

$pageTitle = "Super Admin Dashboard";

/* TODAY LEADS */

$todayLeads = $pdo->query("
SELECT COUNT(*)
FROM enquiries
WHERE DATE(created_at)=CURDATE()
")->fetchColumn();

/* TODAY REGISTRATIONS */

$todayRegistrations = $pdo->query("
SELECT COUNT(*)
FROM registrations
WHERE DATE(created_at)=CURDATE()
")->fetchColumn();

/* TOTAL STUDENTS */

$totalStudents = $pdo->query("
SELECT COUNT(*)
FROM registrations
WHERE registration_status='active'
")->fetchColumn();

/* ACTIVE BATCHES */

$activeBatches = $pdo->query("
SELECT COUNT(*)
FROM batches
WHERE status=1
")->fetchColumn();

/* TODAY COLLECTION */

$todayCollection = $pdo->query("
SELECT IFNULL(SUM(amount),0)
FROM registration_payments
WHERE payment_date = CURDATE()
")->fetchColumn();

/* TOTAL COLLECTION */

$totalCollection = $pdo->query("
SELECT IFNULL(SUM(amount),0)
FROM registration_payments
")->fetchColumn();

/* FOLLOWUPS DUE TODAY */
$todayFollowups = $pdo->query("
SELECT COUNT(*)
FROM enquiry_followups
WHERE followup_date = CURDATE()
")->fetchColumn();

/* PENDING DUES */
$pendingDueStudents = $pdo->query("
SELECT COUNT(*)
FROM registrations
WHERE registration_status='active'
  AND balance_amount > 0
  AND payment_status IN ('unpaid','partial')
")->fetchColumn();

$pendingDueAmount = $pdo->query("
SELECT IFNULL(SUM(balance_amount),0)
FROM registrations
WHERE registration_status='active'
  AND balance_amount > 0
  AND payment_status IN ('unpaid','partial')
")->fetchColumn();

$todayConversionRate = ((float)$todayLeads > 0)
    ? round((((float)$todayRegistrations / (float)$todayLeads) * 100), 1)
    : 0.0;

/* RECENT REGISTRATIONS */

$recentRegistrations = $pdo->query("
SELECT registration_no,enquiry_snapshot_name,program_name,joined_on
FROM registrations
WHERE registration_status='active'
ORDER BY id DESC
LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/* RECENT PAYMENTS */

$recentPayments = $pdo->query("
SELECT
r.id,
p.student_name,
r.program_name,
SUM(rp.amount) as total_paid,
MAX(rp.payment_date) as last_payment_date
FROM registration_payments rp
JOIN registrations r ON r.id = rp.registration_id
LEFT JOIN registration_profiles p ON p.registration_id = r.id
GROUP BY r.id
ORDER BY last_payment_date DESC
LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

/* REVENUE ANALYTICS (30 DAYS) */

$chartLabels = [];
$chartData = [];

for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[$date] = date('d M', strtotime($date));
    $chartData[$date] = 0;
}

$stmt = $pdo->query("
SELECT payment_date, SUM(amount) AS revenue
FROM registration_payments
WHERE payment_date >= CURDATE() - INTERVAL 30 DAY
GROUP BY payment_date
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $chartData[$row['payment_date']] = (float)$row['revenue'];
}

$chartLabels = array_values($chartLabels);
$chartData = array_values($chartData);

?>

<!-- Super Admin dashboard styles moved to assets/css/style.css -->


<div class="sa-dashboard">
    <section class="sa-hero">
        <div class="sa-card sa-hero-main">
            <div class="sa-kicker">
                <i class="fas fa-shield-halved"></i>
                Super Admin Dashboard
            </div>
            <h1 class="sa-hero-title">
                Command center for <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>
            </h1>
            <p class="sa-hero-copy">
                Track admissions momentum, fee collections, active delivery capacity, and recent operational movement from one high-visibility dashboard designed for daily review.
            </p>
            <div class="sa-hero-meta">
                <span class="sa-chip">
                    <i class="fas fa-percent"></i>
                    <?= number_format((float)$todayConversionRate, 1) ?>% conversion today
                </span>
                <span class="sa-chip">
                    <i class="fas fa-phone"></i>
                    <?= number_format((float)$todayFollowups) ?> followups due today
                </span>
                <span class="sa-chip">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <?= inr_symbol() ?> <?= number_format((float)$pendingDueAmount, 2) ?> pending dues
                </span>
            </div>
        </div>

        <aside class="sa-card sa-hero-side">
            <div>
                <div class="sa-panel-label">
                    <i class="fas fa-gauge-high"></i>
                    Daily Snapshot
                </div>
                <div class="sa-panel-title">Business health at a glance</div>
                <div class="sa-panel-copy">
                    This panel keeps the fastest-moving numbers in view so you can spot operational pressure before opening deeper reports.
                </div>
            </div>

            <div class="sa-mini-grid">
                <div class="sa-mini-stat">
                    <div class="label">Total Students</div>
                    <div class="value"><?= number_format((float)$totalStudents) ?></div>
                    <div class="sub">Active enrolled students</div>
                </div>
                <div class="sa-mini-stat">
                    <div class="label">Active Batches</div>
                    <div class="value"><?= number_format((float)$activeBatches) ?></div>
                    <div class="sub">Currently running batches</div>
                </div>
                <div class="sa-mini-stat">
                    <div class="label">Total Collection</div>
                    <div class="value"><?= inr_symbol() ?> <?= number_format((float)$totalCollection, 0) ?></div>
                    <div class="sub">Lifetime collected amount</div>
                </div>
                <div class="sa-mini-stat">
                    <div class="label">Revenue Window</div>
                    <div class="value">30 Days</div>
                    <div class="sub">Analytics chart time range</div>
                </div>
            </div>
        </aside>
    </section>

    <section class="sa-action-grid">
        <a href="index.php?page=enquiries/add" class="sa-action-card">
            <span class="sa-action-icon"><i class="fas fa-user-plus"></i></span>
            <span>
                <span class="sa-action-title">Add Enquiry</span>
                <span class="sa-action-sub">Capture new inbound lead instantly.</span>
            </span>
        </a>
        <a href="index.php?page=registrations/add" class="sa-action-card">
            <span class="sa-action-icon"><i class="fas fa-id-card"></i></span>
            <span>
                <span class="sa-action-title">New Registration</span>
                <span class="sa-action-sub">Create direct or walk-in admission.</span>
            </span>
        </a>
        <a href="index.php?page=payments" class="sa-action-card">
            <span class="sa-action-icon"><i class="fas fa-wallet"></i></span>
            <span>
                <span class="sa-action-title">Collect Payment</span>
                <span class="sa-action-sub">Post fees and clear due balances.</span>
            </span>
        </a>
        <a href="index.php?page=enquiries/followups&ui=list&tab=today" class="sa-action-card">
            <span class="sa-action-icon"><i class="fas fa-list-check"></i></span>
            <span>
                <span class="sa-action-title">Today Followups</span>
                <span class="sa-action-sub">Work pending calls for better conversion.</span>
            </span>
        </a>
    </section>

    <section class="sa-kpi-grid">
        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-user-plus"></i></div>
            <div class="sa-kpi-label">Today Leads</div>
            <div class="sa-kpi-value"><?= number_format((float)$todayLeads) ?></div>
            <div class="sa-kpi-sub">Fresh enquiries created today across the CRM.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-user-check"></i></div>
            <div class="sa-kpi-label">Today Registrations</div>
            <div class="sa-kpi-value"><?= number_format((float)$todayRegistrations) ?></div>
            <div class="sa-kpi-sub">New student conversions recorded today.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-wallet"></i></div>
            <div class="sa-kpi-label">Today Collection</div>
            <div class="sa-kpi-value"><?= inr_symbol() ?> <?= number_format((float)$todayCollection, 2) ?></div>
            <div class="sa-kpi-sub">Payments captured against registrations today.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-percent"></i></div>
            <div class="sa-kpi-label">Today Conversion</div>
            <div class="sa-kpi-value"><?= number_format((float)$todayConversionRate, 1) ?>%</div>
            <div class="sa-kpi-sub">Lead-to-registration conversion based on today activity.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="sa-kpi-label">Total Students</div>
            <div class="sa-kpi-value"><?= number_format((float)$totalStudents) ?></div>
            <div class="sa-kpi-sub">Students with active registration status.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="sa-kpi-label">Pending Dues</div>
            <div class="sa-kpi-value"><?= inr_symbol() ?> <?= number_format((float)$pendingDueAmount, 0) ?></div>
            <div class="sa-kpi-sub"><?= number_format((float)$pendingDueStudents) ?> active students with unpaid or partial balances.</div>
        </article>
    </section>

    <section class="sa-main-grid">
        <div class="sa-card sa-block">
            <div class="sa-block-head">
                <div class="sa-block-copy">
                    <div class="sa-block-kicker">
                        <i class="fas fa-chart-area"></i>
                        Revenue Analytics
                    </div>
                    <h2 class="sa-block-title">Collection trend over the last 30 days</h2>
                    <div class="sa-block-sub">
                        Use this trendline to spot momentum shifts in fee realization and identify flat periods that may need collection follow-up.
                    </div>
                </div>
            </div>

            <div class="sa-chart-shell">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <aside class="sa-side-stack">
            <div class="sa-card sa-block">
                <div class="sa-block-copy">
                    <div class="sa-block-kicker">
                        <i class="fas fa-binoculars"></i>
                        Operations Snapshot
                    </div>
                    <h2 class="sa-block-title">Where attention is needed</h2>
                    <div class="sa-block-sub">
                        A quick read on the most relevant activity signals before jumping into module-level pages.
                    </div>
                </div>

                <div class="sa-snapshot-grid" style="margin-top:18px;">
                    <div class="sa-snapshot-item">
                        <div>
                            <div class="sa-snapshot-label">Lead to registration pulse</div>
                            <div class="sa-snapshot-copy">Today conversion quality with quick path to enquiry followups.</div>
                            <a href="index.php?page=enquiries/followups&ui=list&tab=today" class="sa-link-btn" style="margin-top:10px;height:34px;padding:0 12px;font-size:12px;">
                                <i class="fas fa-arrow-right"></i>
                                Open Followups
                            </a>
                        </div>
                        <div class="sa-snapshot-value"><?= number_format((float)$todayConversionRate, 1) ?>%</div>
                    </div>
                    <div class="sa-snapshot-item">
                        <div>
                            <div class="sa-snapshot-label">Due collections</div>
                            <div class="sa-snapshot-copy">Students with outstanding balances requiring payment follow-up.</div>
                            <a href="index.php?page=payments" class="sa-link-btn" style="margin-top:10px;height:34px;padding:0 12px;font-size:12px;">
                                <i class="fas fa-arrow-right"></i>
                                Open Payments
                            </a>
                        </div>
                        <div class="sa-snapshot-value"><?= inr_symbol() ?> <?= number_format((float)$pendingDueAmount, 0) ?></div>
                    </div>
                    <div class="sa-snapshot-item">
                        <div>
                            <div class="sa-snapshot-label">Followups due today</div>
                            <div class="sa-snapshot-copy">Open call tasks expected to move enquiries closer to conversion.</div>
                            <a href="index.php?page=enquiries/followups&ui=list&tab=today" class="sa-link-btn" style="margin-top:10px;height:34px;padding:0 12px;font-size:12px;">
                                <i class="fas fa-arrow-right"></i>
                                Review Queue
                            </a>
                        </div>
                        <div class="sa-snapshot-value"><?= number_format((float)$todayFollowups) ?></div>
                    </div>
                </div>
            </div>

            <div class="sa-card sa-block">
                <div class="sa-note">
                    <div class="sa-note-title">Management note</div>
                    <div class="sa-note-copy">
                        This dashboard stays intentionally summary-first. For action-taking, use the registrations list and payments page from the sections below.
                    </div>
                </div>
            </div>
        </aside>
    </section>

    <section class="sa-table-grid">
        <div class="sa-card sa-block">
            <div class="sa-block-head">
                <div class="sa-block-copy">
                    <div class="sa-block-kicker">
                        <i class="fas fa-user-clock"></i>
                        Latest Registrations
                    </div>
                    <h2 class="sa-block-title">Newest active student entries</h2>
                    <div class="sa-block-sub">
                        Review recent admissions and jump into the full registration list for deeper verification.
                    </div>
                </div>
                <a href="index.php?page=registrations/list" class="sa-icon-btn" data-modern-tooltip="View all registrations" aria-label="View all registrations">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Reg No</th>
                            <th>Name</th>
                            <th>Program</th>
                            <th>Join Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentRegistrations)): ?>
                            <?php foreach ($recentRegistrations as $r): ?>
                                <tr>
                                    <td class="sa-strong"><?= htmlspecialchars($r['registration_no']) ?></td>
                                    <td><?= htmlspecialchars($r['enquiry_snapshot_name']) ?></td>
                                    <td><?= htmlspecialchars($r['program_name']) ?></td>
                                    <td class="sa-muted"><?= htmlspecialchars($r['joined_on']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="sa-empty">
                                    <i class="fas fa-inbox"></i>
                                    No recent registrations found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sa-card sa-block">
            <div class="sa-block-head">
                <div class="sa-block-copy">
                    <div class="sa-block-kicker">
                        <i class="fas fa-money-check-dollar"></i>
                        Recent Payments
                    </div>
                    <h2 class="sa-block-title">Latest payment movement by student</h2>
                    <div class="sa-block-sub">
                        Keep a quick watch on the most recent paid accounts before moving into full payment tracking.
                    </div>
                </div>
                <a href="index.php?page=payments" class="sa-icon-btn" data-modern-tooltip="View all payments" aria-label="View all payments">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Total Paid</th>
                            <th>Last Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentPayments)): ?>
                            <?php foreach ($recentPayments as $p): ?>
                                <tr>
                                    <td class="sa-strong"><?= htmlspecialchars($p['student_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($p['program_name'] ?? '') ?></td>
                                    <td class="sa-money"><?= inr_symbol() ?> <?= number_format((float)$p['total_paid'], 2) ?></td>
                                    <td class="sa-muted"><?= htmlspecialchars($p['last_payment_date']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="sa-empty">
                                    <i class="fas fa-inbox"></i>
                                    No recent payments found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>

document.addEventListener("DOMContentLoaded", function(){

const canvas = document.getElementById("revenueChart");

if(!canvas){
console.log("Chart canvas not found");
return;
}

const ctx = canvas.getContext("2d");

new Chart(ctx,{

type:'line',

data:{
labels: <?= json_encode($chartLabels) ?>,

datasets:[{
label:'Revenue',
data: <?= json_encode($chartData) ?>,
borderColor:'#e83e8c',
backgroundColor:'rgba(232,62,140,0.14)',
fill:true,
tension:0.4,
pointRadius:3,
pointHoverRadius:5,
borderWidth:3
}]
},

options:{
responsive:true,
maintainAspectRatio:false,

plugins:{
legend:{
display:true,
labels:{
usePointStyle:true,
boxWidth:10,
color:'#5b6477',
font:{
weight:'700'
}
}
}
},

scales:{
y:{
beginAtZero:true,
grid:{
color:'rgba(217,70,139,0.08)'
},
ticks:{
color:'#667085'
}
},
x:{
grid:{
display:false
},
ticks:{
color:'#667085'
}
}
}

}

});

});

</script>
