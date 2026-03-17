<?php
if (!defined('APP_NAME')) die("Unauthorized");

if (($_SESSION['role_name'] ?? '') !== 'HR') {
    redirect('index.php');
    exit;
}

/* ================= DATA ================= */

function c($pdo,$q){return (int)$pdo->query($q)->fetchColumn();}
function s($pdo,$q){return (float)$pdo->query($q)->fetchColumn();}

$totalLeads = c($pdo,"SELECT COUNT(*) FROM leads");
$leadConverted = c($pdo,"SELECT COUNT(*) FROM leads WHERE status='converted'");
$leadMissed = $totalLeads - $leadConverted;

$totalEnquiries = c($pdo,"SELECT COUNT(*) FROM enquiries");
$enqConverted = c($pdo,"SELECT COUNT(*) FROM enquiries WHERE status='converted'");
$enqMissed = $totalEnquiries - $enqConverted;

$totalStudents = c($pdo,"SELECT COUNT(*) FROM registrations WHERE registration_status='active'");
$completedStudents = c($pdo,"SELECT COUNT(*) FROM registrations WHERE registration_status='completed'");
$ongoingStudents = $totalStudents - $completedStudents;

$totalRevenue = s($pdo,"SELECT IFNULL(SUM(amount),0) FROM payments");

/* TODAY FOLLOWUPS */
$todayFollowups = $pdo->query("
SELECT e.name,e.phone,f.followup_time
FROM enquiry_followups f
JOIN enquiries e ON e.id=f.enquiry_id
WHERE DATE(f.followup_date)=CURDATE()
LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* INTERVIEWS */
$interviews = $pdo->query("
SELECT company_name,interview_date,status
FROM interviews ORDER BY interview_date DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

/* REGISTRATIONS */
$regs = $pdo->query("
SELECT enquiry_snapshot_name,program_name,registration_status
FROM registrations ORDER BY id DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>

/* ===== ROOT ===== */
.crm{
background:#f5f7fb;
padding:20px;
}

/* ===== CARDS ===== */
.grid{
display:grid;
grid-template-columns:repeat(4,1fr);
gap:16px;
margin-bottom:20px;
}

.kpi{
padding:18px;
border-radius:14px;
color:#fff;
position:relative;
overflow:hidden;
}

.kpi h2{font-size:26px;}
.kpi small{opacity:.9;}

.kpi i{
position:absolute;
right:15px;
top:15px;
font-size:26px;
opacity:.3;
}

.p1{background:#e91e63;}
.p2{background:#ff9800;}
.p3{background:#2e7d32;}
.p4{background:#3f51b5;}

/* ===== SECTION ===== */
.section{
background:#fff;
padding:18px;
border-radius:14px;
box-shadow:0 10px 25px rgba(0,0,0,.05);
}

/* ===== FLEX ===== */
.row{
display:grid;
grid-template-columns:2fr 1fr;
gap:20px;
margin-bottom:20px;
}

/* ===== TABLE ===== */
table{width:100%;}
td{padding:8px;border-bottom:1px solid #eee;}

/* ===== TASK ===== */
.task{
display:flex;
justify-content:space-between;
padding:10px;
border-bottom:1px solid #eee;
}

/* ===== RESPONSIVE ===== */
@media(max-width:900px){
.grid{grid-template-columns:1fr 1fr;}
.row{grid-template-columns:1fr;}
}

</style>

<div class="crm">

<!-- KPI -->
<div class="grid">

<div class="kpi p1">
<h2><?= $totalLeads ?></h2>
<small><?= $leadConverted ?> Converted | <?= $leadMissed ?> Missed</small>
<i class="fas fa-user-plus"></i>
</div>

<div class="kpi p2">
<h2><?= $totalEnquiries ?></h2>
<small><?= $enqConverted ?> Converted | <?= $enqMissed ?> Missed</small>
<i class="fas fa-question"></i>
</div>

<div class="kpi p3">
<h2><?= $totalStudents ?></h2>
<small><?= $completedStudents ?> Completed | <?= $ongoingStudents ?> Ongoing</small>
<i class="fas fa-user-graduate"></i>
</div>

<div class="kpi p4">
<h2>₹<?= number_format($totalRevenue) ?></h2>
<small>Total Revenue</small>
<i class="fas fa-rupee-sign"></i>
</div>

</div>

<!-- MIDDLE -->
<div class="row">

<!-- FOLLOWUPS -->
<div class="section">
<h3>Today Followups</h3>

<?php foreach($todayFollowups as $f): ?>
<div class="task">
<div>
<b><?= $f['name'] ?></b><br>
<small><?= $f['phone'] ?></small>
</div>
<span><?= $f['followup_time'] ?></span>
</div>
<?php endforeach; ?>

</div>

<!-- CHART -->
<div class="section">
<h3>Revenue</h3>
<canvas id="chart"></canvas>
</div>

</div>

<!-- BOTTOM -->
<div class="row">

<div class="section">
<h3>Interviews</h3>
<table>
<?php foreach($interviews as $i): ?>
<tr>
<td><?= $i['company_name'] ?></td>
<td><?= $i['interview_date'] ?></td>
<td><?= $i['status'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="section">
<h3>Recent Registrations</h3>
<table>
<?php foreach($regs as $r): ?>
<tr>
<td><?= $r['enquiry_snapshot_name'] ?></td>
<td><?= $r['program_name'] ?></td>
<td><?= $r['registration_status'] ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById("chart"),{
type:'line',
data:{
labels:["Mon","Tue","Wed","Thu","Fri"],
datasets:[{
label:"Revenue",
data:[1000,2000,1500,3000,2500],
borderColor:"#e91e63"
}]
}
});
</script>