<?php

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

/* ===============================
HELPER (SAFE HTML)
=============================== */

if (!function_exists('h')) {
function h($v){
return htmlspecialchars((string)($v ?? '-'), ENT_QUOTES, 'UTF-8');
}
}

/* ===============================
GET ID
=============================== */

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
echo "Invalid student.";
exit;
}

/* ===============================
FETCH REGISTRATION
=============================== */

$stmt = $pdo->prepare("
SELECT r.*, e.phone, e.email
FROM registrations r
LEFT JOIN enquiries e 
ON e.converted_registration_id = r.id
WHERE r.id=?
LIMIT 1
");

$stmt->execute([$id]);

$reg = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* ===============================
FETCH PROFILE
=============================== */

$stmt = $pdo->prepare("
SELECT *
FROM registration_profiles
WHERE registration_id=?
LIMIT 1
");

$stmt->execute([$id]);

$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

/* ===============================
FETCH PAYMENTS
=============================== */

$stmt = $pdo->prepare("
SELECT *
FROM registration_payments
WHERE registration_id=?
ORDER BY payment_date DESC
");

$stmt->execute([$id]);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>

.student-page{
background:#fff;
padding:30px;
border-radius:20px;
box-shadow:0 8px 30px rgba(0,0,0,.05);
}

.profile-card{
display:flex;
gap:25px;
margin-bottom:30px;
}

.profile-left img{
border-radius:12px;
width:120px;
}

.profile-right h3{
margin:0;
font-size:24px;
}

.section-title{
margin-top:30px;
margin-bottom:15px;
font-weight:700;
}

.table{
width:100%;
border-collapse:collapse;
}

.table td,.table th{
padding:10px;
border-bottom:1px solid #eee;
}

</style>

<div class="student-page">

<h2>Student Profile</h2>

<!-- ===============================
PROFILE CARD
=============================== -->

<div class="profile-card">

<div class="profile-left">

<?php if(!empty($profile['photo_path'])): ?>

<img src="<?= h($profile['photo_path']) ?>">

<?php else: ?>

<img src="assets/images/default-user.png">

<?php endif; ?>

</div>

<div class="profile-right">

<h3><?= h($profile['student_name'] ?: $reg['enquiry_snapshot_name']) ?></h3>

<p><strong>Registration:</strong> <?= h($reg['registration_no']) ?></p>

<p><strong>Program:</strong> <?= h($reg['program_name']) ?></p>

<p><strong>Phone:</strong> <?= h($reg['phone']) ?></p>

<p><strong>Email:</strong> <?= h($reg['email']) ?></p>

</div>

</div>


<!-- ===============================
PERSONAL INFORMATION
=============================== -->

<h3 class="section-title">Personal Information</h3>

<table class="table">

<tr>
<td>Gender</td>
<td><?= h($profile['gender']) ?></td>
</tr>

<tr>
<td>Date of Birth</td>
<td><?= h($profile['dob']) ?></td>
</tr>

<tr>
<td>Qualification</td>
<td><?= h($profile['qualification']) ?></td>
</tr>

<tr>
<td>College</td>
<td><?= h($profile['college_name']) ?></td>
</tr>

<tr>
<td>Year of Passout</td>
<td><?= h($profile['year_of_passout']) ?></td>
</tr>

<tr>
<td>Parent Name</td>
<td><?= h($profile['parent_name']) ?></td>
</tr>

<tr>
<td>Parent Phone</td>
<td><?= h($profile['parent_phone']) ?></td>
</tr>

<tr>
<td>Address</td>
<td><?= h($profile['address']) ?></td>
</tr>

</table>


<!-- ===============================
COURSE / INTERNSHIP
=============================== -->

<h3 class="section-title">Course / Internship Information</h3>

<table class="table">

<tr>
<td>Course Type</td>
<td><?= h($reg['reg_type']) ?></td>
</tr>

<tr>
<td>Program</td>
<td><?= h($reg['program_name']) ?></td>
</tr>

<tr>
<td>Joining Date</td>
<td><?= h($reg['joined_on']) ?></td>
</tr>

<tr>
<td>Status</td>
<td><?= h($reg['registration_status']) ?></td>
</tr>

</table>


<!-- ===============================
FEE SUMMARY
=============================== -->

<h3 class="section-title">Fee Summary</h3>

<table class="table">

<tr>
<td>Total Fee</td>
<td>₹<?= number_format((float)$reg['total_fee'],2) ?></td>
</tr>

<tr>
<td>Discount</td>
<td>₹<?= number_format((float)$reg['discount_amount'],2) ?></td>
</tr>

<tr>
<td>Final Fee</td>
<td>₹<?= number_format((float)$reg['final_fee'],2) ?></td>
</tr>

<tr>
<td>Paid</td>
<td>₹<?= number_format((float)$reg['paid_amount'],2) ?></td>
</tr>

<tr>
<td>Balance</td>
<td>₹<?= number_format((float)$reg['balance_amount'],2) ?></td>
</tr>

</table>


<!-- ===============================
PAYMENT HISTORY
=============================== -->

<h3 class="section-title">Payment History</h3>

<table class="table">

<thead>

<tr>
<th>Date</th>
<th>Amount</th>
<th>Mode</th>
<th>Reference</th>
<th>Receipt</th>
</tr>

</thead>

<tbody>

<?php if(!$payments): ?>

<tr>
<td colspan="5">No payments yet.</td>
</tr>

<?php else: ?>

<?php foreach($payments as $p): ?>

<tr>

<td><?= h($p['payment_date']) ?></td>

<td>₹<?= number_format((float)$p['amount'],2) ?></td>

<td><?= h($p['payment_mode']) ?></td>

<td><?= h($p['reference_no']) ?></td>

<td>

<a
href="index.php?page=payments/receipt&id=<?= $p['id'] ?>"
target="_blank">

Print

</a>

</td>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>