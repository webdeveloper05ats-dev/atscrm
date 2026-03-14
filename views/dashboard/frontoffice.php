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


/* ======================================================
HELPER FUNCTIONS
====================================================== */

function safeCount($pdo,$sql,$params=[]){

try{
$st=$pdo->prepare($sql);
$st->execute($params);
return (int)$st->fetchColumn();
}catch(Exception $e){
return 0;
}

}

function safeSum($pdo,$sql,$params=[]){

try{
$st=$pdo->prepare($sql);
$st->execute($params);
return (float)$st->fetchColumn();
}catch(Exception $e){
return 0;
}

}


/* ======================================================
REGISTRATIONS (ONLY FRONT OFFICE USER)
====================================================== */

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


/* ======================================================
STUDENTS
====================================================== */

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


/* ======================================================
ENQUIRIES
====================================================== */

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


/* ======================================================
LEADS
====================================================== */

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


/* ======================================================
MONTHLY TARGET
====================================================== */

$currentMonth = date('n');
$currentYear  = date('Y');

$monthName = date('F');

/* =========================================
PREVIOUS MONTH
========================================= */

$prevMonth = $currentMonth - 1;
$prevYear  = $currentYear;

if ($prevMonth == 0) {

    $prevMonth = 12;
    $prevYear--;

}

/* =========================================
PREVIOUS MONTH TARGET
========================================= */

$prevTarget = safeSum(
$pdo,
"SELECT target_amount
FROM monthly_targets
WHERE user_id=?
AND target_month=?
AND target_year=?
AND status='active'
LIMIT 1",
[$userId,$prevMonth,$prevYear]
);

/* =========================================
PREVIOUS MONTH ACHIEVED
========================================= */

$prevAchieved = safeSum(
$pdo,
"SELECT IFNULL(SUM(amount),0)
FROM registration_payments
WHERE staff_id=?
AND MONTH(payment_date)=?
AND YEAR(payment_date)=?",
[$userId,$prevMonth,$prevYear]
);

/* =========================================
CARRY FORWARD
========================================= */

$carryForward = max($prevTarget - $prevAchieved,0);

/* TARGET AMOUNT */

$targetAmount = safeSum(
$pdo,
"SELECT target_amount
FROM monthly_targets
WHERE user_id=?
AND target_month=?
AND target_year=?
AND status='active'
LIMIT 1",
[$userId,$currentMonth,$currentYear]
);


/* FINAL TARGET */

$finalTarget = $targetAmount + $carryForward;
/* COLLECTION (FRONT OFFICE CREDIT) */

$achievedAmount = safeSum(
$pdo,
"SELECT IFNULL(SUM(amount),0)
FROM registration_payments
WHERE staff_id=?
AND MONTH(payment_date)=?
AND YEAR(payment_date)=?",
[$userId,$currentMonth,$currentYear]
);

/* BALANCE */

$balanceAmount = max($finalTarget - $achievedAmount,0);

?>


<!-- ======================================
WELCOME
====================================== -->
<?php if($carryForward > 0): ?>

<div style="
background:#fff4e5;
border:1px solid #ffd9a3;
padding:10px;
border-radius:8px;
margin-bottom:12px;
color:#b36b00;
font-size:14px;">

⚠ Carry Forward from <?= date('F', mktime(0,0,0,$prevMonth,1)) ?>

₹ <?= number_format($carryForward,2) ?>

</div>

<?php endif; ?>
<div class="card">

<div class="card-header">
Welcome Front Office 👋
</div>

<p style="line-height:1.7;color:var(--text-dark);">

Hello <strong><?= htmlspecialchars($userName) ?></strong>

You are working under

<strong><?= htmlspecialchars($branchName) ?></strong>

</p>

</div>



<!-- ======================================
STATS CARDS
====================================== -->

<div class="dashboard-grid">

<div class="card stat-card">
<h3>Today Registrations</h3>
<h2><?= $todayRegs ?></h2>
</div>

<div class="card stat-card">
<h3>Total Registrations</h3>
<h2><?= $totalRegistrations ?></h2>
</div>

<div class="card stat-card">
<h3>Total Students</h3>
<h2><?= $totalStudents ?></h2>
</div>

<div class="card stat-card">
<h3>Internship Students</h3>
<h2><?= $internshipStudents ?></h2>
</div>

<div class="card stat-card">
<h3>Course Students</h3>
<h2><?= $courseStudents ?></h2>
</div>

<div class="card stat-card">
<h3>Total Enquiries</h3>
<h2><?= $totalEnquiries ?></h2>

<div style="font-size:12px;color:#777">
Converted: <?= $convertedEnquiries ?> |
Missed: <?= $missedEnquiries ?>
</div>

</div>

<div class="card stat-card">
<h3>Total Leads</h3>
<h2><?= $totalLeads ?></h2>

<div style="font-size:12px;color:#777">
Converted: <?= $convertedLeads ?> |
Missed: <?= $missedLeads ?>
</div>

</div>

</div>



<!-- ======================================
TARGET SECTION
====================================== -->

<div class="card">

<div class="card-header">

<?= $monthName ?> Target

</div>


<div style="text-align:center;margin-bottom:10px">

<strong>Monthly Target Amount:</strong>

₹ <?= number_format($targetAmount,2) ?>

</div>


<div style="max-width:420px;margin:auto">

<canvas id="targetChart"></canvas>

</div>


<div style="text-align:center;margin-top:10px;font-size:13px">

Achieved: ₹ <?= number_format($achievedAmount,2) ?>

|

Balance: ₹ <?= number_format($balanceAmount,2) ?>

</div>

</div>



<!-- ======================================
CHART SCRIPT
====================================== -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ctx=document.getElementById('targetChart');

new Chart(ctx,{

type:'doughnut',

data:{

labels:['Achieved','Balance'],

datasets:[{

data:[
<?= $achievedAmount ?>,
<?= $balanceAmount ?>
],

backgroundColor:[
'#e91e63',
'#f3c6d3'
],

borderWidth:0

}]

},

options:{

cutout:'65%',

plugins:{

legend:{
position:'bottom'
}

}

}

});

</script>