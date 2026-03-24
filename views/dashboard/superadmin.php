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

/* RECENT REGISTRATIONS */

$recentRegistrations = $pdo->query("
SELECT registration_no,enquiry_snapshot_name,program_name,joined_on
FROM registrations
WHERE registration_status='active'
ORDER BY id DESC
LIMIT 5
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
LIMIT 5
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

<style>
:root{
--sa-primary:#d9468b;
--sa-primary-dark:#b83273;
--sa-primary-soft:#fff0f7;
--sa-ink:#1f2940;
--sa-muted:#6b7280;
--sa-border:#efd7e5;
--sa-bg:#f7f4f8;
--sa-card:#ffffff;
--sa-accent:#223a5e;
--sa-shadow:0 18px 40px rgba(38, 24, 45, 0.08);
--sa-shadow-soft:0 10px 24px rgba(38, 24, 45, 0.06);
}

body{
background:
radial-gradient(circle at top left, rgba(217,70,139,0.10), transparent 22%),
radial-gradient(circle at top right, rgba(34,58,94,0.08), transparent 20%),
var(--sa-bg);
}

.sa-dashboard{
display:grid;
gap:22px;
padding:8px 0 28px;
}

.sa-card{
background:var(--sa-card);
border:1px solid var(--sa-border);
border-radius:24px;
box-shadow:var(--sa-shadow-soft);
}

.sa-hero{
display:grid;
grid-template-columns:minmax(0, 1.45fr) minmax(300px, .85fr);
gap:20px;
}

.sa-hero-main{
padding:28px;
background:
linear-gradient(135deg, rgba(217,70,139,0.14), rgba(255,255,255,0.95)),
linear-gradient(180deg, #fff 0%, #fff7fb 100%);
position:relative;
overflow:hidden;
}

.sa-hero-main::after{
content:'';
position:absolute;
right:-40px;
top:-48px;
width:180px;
height:180px;
border-radius:50%;
background:radial-gradient(circle, rgba(217,70,139,0.16) 0%, rgba(217,70,139,0) 70%);
}

.sa-kicker{
display:inline-flex;
align-items:center;
gap:8px;
padding:8px 14px;
border-radius:999px;
background:#fff;
border:1px solid rgba(217,70,139,0.18);
font-size:11px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:var(--sa-primary-dark);
margin-bottom:16px;
}

.sa-hero-title{
margin:0;
font-size:34px;
line-height:1.08;
font-weight:800;
color:var(--sa-ink);
max-width:760px;
}

.sa-hero-title strong{
color:var(--sa-primary-dark);
}

.sa-hero-copy{
margin:14px 0 0;
max-width:720px;
font-size:15px;
line-height:1.7;
color:#586174;
}

.sa-hero-meta{
display:flex;
flex-wrap:wrap;
gap:12px;
margin-top:22px;
}

.sa-chip{
display:inline-flex;
align-items:center;
gap:8px;
padding:10px 14px;
border-radius:999px;
background:#fff;
border:1px solid var(--sa-border);
font-size:13px;
font-weight:700;
color:#4b5563;
}

.sa-hero-side{
padding:24px;
display:grid;
gap:16px;
background:linear-gradient(180deg, #ffffff 0%, #fff8fb 100%);
}

.sa-panel-label{
font-size:11px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:#7b8190;
display:flex;
align-items:center;
gap:8px;
}

.sa-panel-title{
font-size:22px;
font-weight:800;
color:var(--sa-ink);
line-height:1.2;
margin-top:6px;
}

.sa-panel-copy{
font-size:13px;
line-height:1.6;
color:#6b7280;
}

.sa-mini-grid{
display:grid;
grid-template-columns:repeat(2, minmax(0, 1fr));
gap:12px;
}

.sa-mini-stat{
padding:14px;
border-radius:18px;
background:#fff;
border:1px solid #f3dce8;
}

.sa-mini-stat .label{
font-size:11px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:#7b8190;
margin-bottom:8px;
}

.sa-mini-stat .value{
font-size:24px;
font-weight:800;
color:var(--sa-accent);
line-height:1.1;
}

.sa-mini-stat .sub{
margin-top:6px;
font-size:12px;
color:#6b7280;
}

.sa-kpi-grid{
display:grid;
grid-template-columns:repeat(6, minmax(0, 1fr));
gap:16px;
}

.sa-kpi-card{
padding:18px;
position:relative;
overflow:hidden;
}

.sa-kpi-card::before{
content:'';
position:absolute;
top:0;
left:0;
right:0;
height:4px;
background:linear-gradient(90deg, var(--sa-primary), #ff8fbe);
}

.sa-kpi-icon{
width:46px;
height:46px;
display:inline-flex;
align-items:center;
justify-content:center;
border-radius:16px;
background:var(--sa-primary-soft);
color:var(--sa-primary-dark);
font-size:18px;
margin-bottom:14px;
}

.sa-kpi-label{
font-size:11px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:#7b8190;
margin-bottom:8px;
}

.sa-kpi-value{
font-size:28px;
font-weight:800;
line-height:1.1;
color:var(--sa-ink);
}

.sa-kpi-sub{
margin-top:8px;
font-size:12px;
line-height:1.55;
color:#6b7280;
}

.sa-main-grid{
display:grid;
grid-template-columns:minmax(0, 1.35fr) minmax(300px, .85fr);
gap:20px;
}

.sa-block{
padding:22px;
}

.sa-block-head{
display:flex;
justify-content:space-between;
align-items:flex-start;
gap:14px;
margin-bottom:18px;
}

.sa-block-copy{
display:grid;
gap:6px;
}

.sa-block-kicker{
font-size:11px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:#7b8190;
display:flex;
align-items:center;
gap:8px;
}

.sa-block-title{
margin:0;
font-size:22px;
font-weight:800;
color:var(--sa-ink);
}

.sa-block-sub{
font-size:13px;
line-height:1.6;
color:#6b7280;
}

.sa-link-btn{
display:inline-flex;
align-items:center;
gap:8px;
height:40px;
padding:0 16px;
border-radius:999px;
background:linear-gradient(135deg, var(--sa-primary), var(--sa-primary-dark));
color:#fff;
font-size:13px;
font-weight:700;
text-decoration:none;
box-shadow:0 12px 24px rgba(217,70,139,0.22);
transition:transform .18s ease, box-shadow .18s ease;
}

.sa-link-btn:hover{
color:#fff;
transform:translateY(-1px);
box-shadow:0 16px 28px rgba(217,70,139,0.28);
}

.sa-chart-shell{
height:360px;
padding:12px 4px 0;
}

.sa-side-stack{
display:grid;
gap:20px;
}

.sa-snapshot-grid{
display:grid;
gap:12px;
}

.sa-snapshot-item{
display:flex;
justify-content:space-between;
align-items:flex-start;
gap:12px;
padding:14px 0;
border-bottom:1px solid #f2e4ec;
}

.sa-snapshot-item:last-child{
border-bottom:none;
padding-bottom:0;
}

.sa-snapshot-item:first-child{
padding-top:0;
}

.sa-snapshot-label{
font-size:13px;
font-weight:700;
color:#4b5563;
}

.sa-snapshot-copy{
font-size:12px;
line-height:1.55;
color:#7b8190;
margin-top:4px;
}

.sa-snapshot-value{
font-size:22px;
font-weight:800;
color:var(--sa-accent);
white-space:nowrap;
}

.sa-note{
padding:16px 18px;
border-radius:18px;
background:linear-gradient(135deg, #fff4fa, #fff);
border:1px solid #f4d6e6;
}

.sa-note-title{
font-size:13px;
font-weight:800;
color:var(--sa-primary-dark);
margin-bottom:6px;
}

.sa-note-copy{
font-size:13px;
line-height:1.6;
color:#6b7280;
}

.sa-table-grid{
display:grid;
grid-template-columns:repeat(2, minmax(0, 1fr));
gap:20px;
}

.sa-table-wrap{
overflow:auto;
border:1px solid #f1dce8;
border-radius:18px;
background:#fff;
}

.sa-table{
width:100%;
border-collapse:collapse;
min-width:620px;
}

.sa-table th{
padding:14px 16px;
background:#fff4fa;
border-bottom:1px solid #f1dce8;
font-size:11px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:#7b8190;
text-align:left;
white-space:nowrap;
}

.sa-table td{
padding:15px 16px;
border-bottom:1px solid #f7e8f0;
font-size:14px;
color:#334155;
vertical-align:middle;
}

.sa-table tbody tr:hover{
background:#fff9fc;
}

.sa-table tbody tr:last-child td{
border-bottom:none;
}

.sa-strong{
font-weight:800;
color:var(--sa-ink);
}

.sa-muted{
color:#7b8190;
}

.sa-money{
font-weight:800;
color:var(--sa-primary-dark);
white-space:nowrap;
}

.sa-empty{
padding:34px 20px;
text-align:center;
color:#7b8190;
}

.sa-empty i{
display:block;
font-size:28px;
margin-bottom:10px;
color:#d39ab7;
}

@media (max-width: 1400px){
.sa-kpi-grid{
grid-template-columns:repeat(3, minmax(0, 1fr));
}
}

@media (max-width: 1100px){
.sa-hero,
.sa-main-grid,
.sa-table-grid{
grid-template-columns:1fr;
}
}

@media (max-width: 768px){
.sa-dashboard{
gap:18px;
}

.sa-hero-main,
.sa-hero-side,
.sa-block{
padding:18px;
}

.sa-hero-title{
font-size:28px;
}

.sa-kpi-grid,
.sa-mini-grid{
grid-template-columns:repeat(2, minmax(0, 1fr));
}

.sa-block-head{
flex-direction:column;
}
}

@media (max-width: 576px){
.sa-kpi-grid,
.sa-mini-grid{
grid-template-columns:1fr;
}

.sa-hero-title{
font-size:24px;
}

.sa-snapshot-item{
flex-direction:column;
}

.sa-link-btn{
width:100%;
justify-content:center;
}
}
</style>

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
                    <i class="fas fa-user-plus"></i>
                    <?= number_format((float)$todayLeads) ?> leads today
                </span>
                <span class="sa-chip">
                    <i class="fas fa-user-check"></i>
                    <?= number_format((float)$todayRegistrations) ?> registrations today
                </span>
                <span class="sa-chip">
                    <i class="fas fa-wallet"></i>
                    Rs <?= number_format((float)$todayCollection, 2) ?> collected today
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
                    <div class="value">Rs <?= number_format((float)$totalCollection, 0) ?></div>
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
            <div class="sa-kpi-value">Rs <?= number_format((float)$todayCollection, 2) ?></div>
            <div class="sa-kpi-sub">Payments captured against registrations today.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-chart-line"></i></div>
            <div class="sa-kpi-label">Total Collection</div>
            <div class="sa-kpi-value">Rs <?= number_format((float)$totalCollection, 2) ?></div>
            <div class="sa-kpi-sub">Overall fee collection accumulated in the system.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-user-graduate"></i></div>
            <div class="sa-kpi-label">Total Students</div>
            <div class="sa-kpi-value"><?= number_format((float)$totalStudents) ?></div>
            <div class="sa-kpi-sub">Students with active registration status.</div>
        </article>

        <article class="sa-card sa-kpi-card">
            <div class="sa-kpi-icon"><i class="fas fa-layer-group"></i></div>
            <div class="sa-kpi-label">Active Batches</div>
            <div class="sa-kpi-value"><?= number_format((float)$activeBatches) ?></div>
            <div class="sa-kpi-sub">Training batches marked active right now.</div>
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
                            <div class="sa-snapshot-copy">Compare new leads created today with actual registration movement.</div>
                        </div>
                        <div class="sa-snapshot-value"><?= number_format((float)$todayLeads) ?> / <?= number_format((float)$todayRegistrations) ?></div>
                    </div>
                    <div class="sa-snapshot-item">
                        <div>
                            <div class="sa-snapshot-label">Collection throughput</div>
                            <div class="sa-snapshot-copy">Cash realized today versus the lifetime collection base.</div>
                        </div>
                        <div class="sa-snapshot-value">Rs <?= number_format((float)$todayCollection, 0) ?></div>
                    </div>
                    <div class="sa-snapshot-item">
                        <div>
                            <div class="sa-snapshot-label">Delivery capacity</div>
                            <div class="sa-snapshot-copy">Active student population supported by current running batches.</div>
                        </div>
                        <div class="sa-snapshot-value"><?= number_format((float)$activeBatches) ?></div>
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
                <a href="index.php?page=registrations/list" class="sa-link-btn">
                    <i class="fas fa-arrow-right"></i>
                    View More
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
                <a href="index.php?page=payments" class="sa-link-btn">
                    <i class="fas fa-arrow-right"></i>
                    View More
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
                                    <td class="sa-money">Rs <?= number_format((float)$p['total_paid'], 2) ?></td>
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
