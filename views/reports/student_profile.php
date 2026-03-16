<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo "<div class='alert alert-danger'>Invalid student ID</div>";
    return;
}

/* =========================
   FETCH STUDENT
========================= */

$stmt = $pdo->prepare("
SELECT *
FROM registrations
WHERE id=?
LIMIT 1
");

$stmt->execute([$id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "<div class='alert alert-danger'>Student not found</div>";
    return;
}

/* =========================
   PAYMENT HISTORY
========================= */

$payments = $pdo->prepare("
SELECT 
p.payment_date,
p.amount,
p.payment_mode,
u.name AS collected_by_name
FROM registration_payments p
LEFT JOIN users u ON u.id = p.collected_by
WHERE p.registration_id = ?
ORDER BY p.payment_date DESC
");

$payments->execute([$id]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);
?>

<style>

/* HEADER */

.student-header{
background:#fff;
padding:20px;
border-radius:12px;
box-shadow:0 6px 15px rgba(0,0,0,.05);
margin-bottom:20px;
}

.student-info h2{
margin:0;
font-size:24px;
}

.student-meta{
margin-top:6px;
display:flex;
gap:12px;
flex-wrap:wrap;
}

.meta-item{
font-size:13px;
color:#777;
}

.status-badge{
padding:4px 10px;
border-radius:20px;
font-size:12px;
font-weight:600;
}

.status-paid{
background:#e8f8f1;
color:#1b9e77;
}

.status-unpaid{
background:#ffecec;
color:#e53935;
}

/* SUMMARY CARDS */

.summary-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:15px;
margin-bottom:20px;
}

.summary-card{
background:#fff;
padding:18px;
border-radius:10px;
box-shadow:0 6px 15px rgba(0,0,0,.05);
}

.summary-title{
font-size:13px;
color:#777;
}

.summary-value{
font-size:20px;
font-weight:700;
margin-top:4px;
}

/* TABS */

.profile-tabs{
display:flex;
gap:10px;
margin-bottom:15px;
}

.tab-btn{
background:#f5f5f5;
border:none;
padding:8px 14px;
border-radius:6px;
cursor:pointer;
}

.tab-btn.active{
background:#e91e63;
color:#fff;
}

.tab-content{
display:none;
}

.tab-content.active{
display:block;
}

/* PROFILE GRID */

.profile-grid{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:15px;
}

.profile-item{
background:#fff;
padding:12px;
border-radius:8px;
border:1px solid #eee;
}

.profile-label{
font-size:12px;
color:#777;
margin-bottom:3px;
}

.profile-value{
font-weight:600;
}

/* PAYMENT TABLE */

.payment-table{
width:100%;
border-collapse:collapse;
background:#fff;
}

.payment-table th,
.payment-table td{
border:1px solid #eee;
padding:8px;
font-size:13px;
}

.payment-table th{
background:#fff0f5;
}

</style>


<!-- HEADER -->

<div class="student-header">

<div class="student-info">

<h2><?= htmlspecialchars($student['enquiry_snapshot_name']) ?></h2>

<div class="student-meta">

<span class="meta-item">
<?= htmlspecialchars($student['program_name']) ?>
</span>

<span class="meta-item">
<?= htmlspecialchars($student['registration_no']) ?>
</span>

<span class="status-badge status-<?= $student['payment_status'] ?>">
<?= ucfirst($student['payment_status']) ?>
</span>

</div>

</div>

</div>


<!-- SUMMARY CARDS -->

<div class="summary-grid">

<div class="summary-card">
<div class="summary-title">Total Fee</div>
<div class="summary-value">₹<?= number_format($student['total_fee'],2) ?></div>
</div>

<div class="summary-card">
<div class="summary-title">Paid</div>
<div class="summary-value">₹<?= number_format($student['paid_amount'],2) ?></div>
</div>

<div class="summary-card">
<div class="summary-title">Balance</div>
<div class="summary-value">₹<?= number_format($student['balance_amount'],2) ?></div>
</div>

<div class="summary-card">
<div class="summary-title">Internship Days</div>
<div class="summary-value"><?= htmlspecialchars($student['internship_days'] ?? '-') ?></div>
</div>

</div>


<!-- TABS -->

<div class="profile-tabs">

<button class="tab-btn active" data-tab="profile">Profile</button>
<button class="tab-btn" data-tab="payments">Payments</button>
<button class="tab-btn" data-tab="internship">Internship</button>

</div>


<!-- PROFILE TAB -->

<div class="tab-content active" id="profile">

<div class="profile-grid">

<div class="profile-item">
<div class="profile-label">Phone</div>
<div class="profile-value"><?= htmlspecialchars($student['enquiry_snapshot_phone']) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Email</div>
<div class="profile-value"><?= htmlspecialchars($student['enquiry_snapshot_email'] ?? '-') ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Batch</div>
<div class="profile-value"><?= htmlspecialchars($student['batch_name']) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Joined On</div>
<div class="profile-value"><?= htmlspecialchars($student['joined_on']) ?></div>
</div>

</div>

</div>


<!-- PAYMENTS TAB -->

<div class="tab-content" id="payments">

<table class="payment-table">

<tr>
<th>Date</th>
<th>Amount</th>
<th>Mode</th>
<th>Collected By</th>
</tr>

<?php foreach($payments as $p): ?>

<tr>

<td><?= htmlspecialchars($p['payment_date']) ?></td>

<td>₹<?= number_format($p['amount'],2) ?></td>

<td><?= htmlspecialchars($p['payment_mode']) ?></td>

<td><?= htmlspecialchars($p['collected_by_name'] ?? 'System') ?></td>

</tr>

<?php endforeach; ?>

</table>

</div>


<!-- INTERNSHIP TAB -->

<div class="tab-content" id="internship">

<div class="profile-grid">

<div class="profile-item">
<div class="profile-label">Start Date</div>
<div class="profile-value"><?= htmlspecialchars($student['internship_start_date'] ?? '-') ?></div>
</div>

<div class="profile-item">
<div class="profile-label">End Date</div>
<div class="profile-value"><?= htmlspecialchars($student['internship_end_date'] ?? '-') ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Completion Status</div>
<div class="profile-value"><?= ucfirst($student['internship_completion_status']) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Certificate Status</div>
<div class="profile-value"><?= ucfirst($student['internship_certificate_status']) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Certificate Issued</div>
<div class="profile-value">
<?= $student['internship_certificate_issued_at'] 
? htmlspecialchars($student['internship_certificate_issued_at']) 
: 'Not Issued' ?>
</div>
</div>

<div class="profile-item">
<div class="profile-label">Report Status</div>
<div class="profile-value"><?= ucfirst($student['internship_report_status']) ?></div>
</div>

</div>

</div>



<script>

document.querySelectorAll('.tab-btn').forEach(btn=>{

btn.addEventListener('click',function(){

document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));

this.classList.add('active');

document.getElementById(this.dataset.tab).classList.add('active');

});

});

</script>