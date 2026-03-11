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
FROM leads
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

/* Prepare last 30 days with default 0 revenue */
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

.dashboard-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:20px;
margin-top:20px;
}

.stat-card{
background:#fff;
padding:22px;
border-radius:14px;
box-shadow:0 10px 25px rgba(0,0,0,.08);
position:relative;
transition:0.3s;
}

.stat-card:hover{
transform:translateY(-4px);
}

.stat-card h3{
font-size:14px;
color:#888;
}

.stat-card h2{
font-size:30px;
font-weight:700;
color:#e83e8c;
}

.stat-icon{
position:absolute;
top:15px;
right:15px;
font-size:22px;
color:#ddd;
}

.section-card{
background:#fff;
border-radius:14px;
padding:25px;
margin-top:25px;
box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.section-title{
font-size:18px;
font-weight:600;
margin-bottom:15px;
}

.chart-box{
height:350px;
position:relative;
width:100%;
}

table{
width:100%;
border-collapse:collapse;
}

table th, table td{
padding:10px;
border-bottom:1px solid #eee;
text-align:left;
}



.modern-table-wrapper{
overflow-x:auto;
margin-top:10px;
}

.modern-table{
width:100%;
border-collapse:collapse;
min-width:600px;
}

.modern-table th{
background:#fafafa;
font-size:13px;
text-transform:uppercase;
color:#777;
padding:12px;
text-align:left;
border-bottom:2px solid #eee;
}

.modern-table td{
padding:12px;
border-bottom:1px solid #eee;
font-size:14px;
}

.modern-table tr:hover{
background:#fafafa;
}

.table-header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:10px;
}

.view-btn{
background:#e83e8c;
color:white;
padding:6px 12px;
border-radius:6px;
font-size:13px;
text-decoration:none;
}

.view-btn:hover{
background:#d52e79;
}


.crm-table-card{
background:#f9fafc;
border-radius:14px;
padding:22px;
margin-top:25px;
box-shadow:0 6px 18px rgba(0,0,0,0.06);
}

.crm-table-header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:15px;
}

.crm-table-title{
font-size:18px;
font-weight:600;
}

.crm-view-btn{
background:#e83e8c;
color:#fff;
padding:6px 12px;
border-radius:6px;
text-decoration:none;
font-size:13px;
}

.crm-table-wrapper{
overflow-x:auto;
}

.crm-table{
width:100%;
border-collapse:collapse;
min-width:650px;
}

.crm-table th{
background:#f1f3f7;
padding:12px;
font-size:13px;
text-transform:uppercase;
color:#666;
border-bottom:2px solid #e6e8ee;
}

.crm-table td{
padding:12px;
border-bottom:1px solid #e6e8ee;
font-size:14px;
}

.crm-table tr:hover{
background:#f6f7fb;
}
</style>


<div class="section-card">

<h2>Welcome Super Admin 👑</h2>

<p>
Hello <strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></strong>,  
here is your complete CRM overview.
</p>

</div>


<!-- KPI CARDS -->

<div class="dashboard-grid">

<div class="stat-card">
<i class="fas fa-user-plus stat-icon"></i>
<h3>Today Leads</h3>
<h2><?= $todayLeads ?></h2>
</div>

<div class="stat-card">
<i class="fas fa-user-check stat-icon"></i>
<h3>Today Registrations</h3>
<h2><?= $todayRegistrations ?></h2>
</div>

<div class="stat-card">
<i class="fas fa-rupee-sign stat-icon"></i>
<h3>Today Collection</h3>
<h2>₹ <?= number_format($todayCollection) ?></h2>
</div>

<div class="stat-card">
<i class="fas fa-chart-line stat-icon"></i>
<h3>Total Collection</h3>
<h2>₹ <?= number_format($totalCollection) ?></h2>
</div>

<div class="stat-card">
<i class="fas fa-user-graduate stat-icon"></i>
<h3>Total Students</h3>
<h2><?= $totalStudents ?></h2>
</div>

<div class="stat-card">
<i class="fas fa-layer-group stat-icon"></i>
<h3>Active Batches</h3>
<h2><?= $activeBatches ?></h2>
</div>

</div>



<!-- REVENUE ANALYTICS -->

<div class="section-card">

<div class="section-title">Revenue Analytics (Last 30 Days)</div>

<div class="chart-box">
<canvas id="revenueChart"></canvas>
</div>

</div>



<!-- LATEST REGISTRATIONS -->

<div class="section-card">

<div class="table-header">
<h3>Latest Registrations</h3>

<a href="index.php?page=registrations/list" class="view-btn">
View More
</a>
</div>

<div class="modern-table-wrapper">

<table class="modern-table">

<thead>
<tr>
<th>Reg No</th>
<th>Name</th>
<th>Program</th>
<th>Join Date</th>
</tr>
</thead>

<tbody>

<?php foreach($recentRegistrations as $r){ ?>

<tr>

<td><?= htmlspecialchars($r['registration_no']) ?></td>

<td><?= htmlspecialchars($r['enquiry_snapshot_name']) ?></td>

<td><?= htmlspecialchars($r['program_name']) ?></td>

<td><?= htmlspecialchars($r['joined_on']) ?></td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>



<!-- RECENT PAYMENTS -->

<div class="crm-table-card">

<div class="crm-table-header">

<div class="crm-table-title">
Recent Payments
</div>

<a href="index.php?page=payments" class="crm-view-btn">
View More
</a>

</div>


<div class="crm-table-wrapper">

<table class="crm-table">

<thead>
<tr>
<th>Student</th>
<th>Program</th>
<th>Total Paid</th>
<th>Last Payment</th>
</tr>
</thead>

<tbody>

<?php foreach($recentPayments as $p){ ?>

<tr>

<td><?= htmlspecialchars($p['student_name'] ?? 'N/A') ?></td>

<td><?= htmlspecialchars($p['program_name'] ?? '') ?></td>

<td>₹ <?= number_format($p['total_paid']) ?></td>

<td><?= htmlspecialchars($p['last_payment_date']) ?></td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

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
backgroundColor:'rgba(232,62,140,0.15)',
fill:true,
tension:0.4,
pointRadius:4
}]
},

options:{
responsive:true,
maintainAspectRatio:false,

plugins:{
legend:{
display:true
}
},

scales:{
y:{
beginAtZero:true
}
}

}

});

});

</script>