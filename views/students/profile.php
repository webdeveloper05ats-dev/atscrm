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
.student-page {
    background:
        radial-gradient(circle at top right, rgba(255, 204, 224, 0.45), transparent 28%),
        linear-gradient(180deg, #fff8fb 0%, #ffffff 100%);
    padding: 32px;
    border-radius: 24px;
    border: 1px solid #f6d5e2;
    box-shadow: 0 24px 52px rgba(236, 72, 153, 0.08);
    max-width: 1180px;
    margin: 30px auto;
    font-family: 'Poppins', 'Segoe UI', sans-serif;
    color: #342431;
    line-height: 1.6;
}

h2 {
    font-size: 28px;
    font-weight: 800;
    color: #9f1249;
    margin: 0 0 26px;
    letter-spacing: -0.5px;
}

.profile-card {
    display: flex;
    gap: 32px;
    align-items: center;
    background: linear-gradient(180deg, #ffffff 0%, #fff7fb 100%);
    border: 1px solid #f6d5e2;
    border-radius: 22px;
    padding: 28px;
    margin-bottom: 26px;
    box-shadow: 0 18px 40px rgba(236, 72, 153, 0.08);
}

.profile-left img,
.profile-photo {
    width: 160px;
    height: 160px;
    border-radius: 20px;
    object-fit: cover;
    background: #fff;
    border: 5px solid #fff0f6;
    box-shadow: 0 16px 34px rgba(236, 72, 153, 0.14);
}

.profile-right h3 {
    margin: 0 0 16px;
    font-size: 30px;
    font-weight: 800;
    color: #7e1842;
}

.profile-right p {
    margin: 12px 0;
    font-size: 14px;
    color: #6a5565;
}

.profile-right p strong {
    color: #a01453;
    font-weight: 700;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
    align-items: start;
    grid-auto-rows: min-content;
}

.student-profile-card-block {
    background: linear-gradient(180deg, #ffffff 0%, #fff8fb 100%);
    border: 1px solid #f7d7e4;
    border-radius: 20px;
    padding: 22px;
    box-shadow: 0 14px 30px rgba(236, 72, 153, 0.07);
    align-self: start;
    height: auto !important;
    min-height: 0 !important;
}

.section-title {
    font-size: 17px;
    font-weight: 700;
    color: #8d1246;
    margin: 0 0 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title::before {
    content: '';
    width: 12px;
    height: 12px;
    flex: 0 0 12px;
    border-radius: 50%;
    background: linear-gradient(180deg, #ff4f91 0%, #ff8fb8 100%);
    box-shadow: 0 0 0 6px #fff0f6;
}

.student-profile-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: transparent;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #f9dce8;
    font-size: 13px;
}

.student-profile-table th,
.student-profile-table td {
    padding: 14px 16px;
    text-align: left;
}

.student-profile-table th {
    background: #fff0f6;
    font-weight: 700;
    color: #a01453;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.7px;
    border-bottom: 1px solid #f5cddd;
}

.student-profile-table td {
    color: #5f4d5b;
    border-bottom: 1px solid #fbe5ee;
    background: rgba(255, 255, 255, 0.92);
}

.student-profile-table tbody tr:nth-child(even) td {
    background: #fffafc;
}

.student-profile-table tr:last-child td {
    border-bottom: none;
}

.student-profile-table tr td:first-child {
    font-weight: 600;
    color: #7d103f;
}

.student-profile-table td a {
    color: #e91e63;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 12px;
    border-radius: 999px;
    background: #fff0f6;
}

.student-profile-table td a:hover {
    background: #ffe0ec;
}

@media (max-width: 992px) {
    .profile-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .details-grid {
        grid-template-columns: 1fr;
    }

    .profile-left img,
    .profile-photo {
        width: 140px;
        height: 140px;
    }

    .student-page {
        padding: 28px;
    }
}

@media (max-width: 640px) {
    .student-profile-table th,
    .student-profile-table td {
        padding: 12px 14px;
        font-size: 14px;
    }

    h2 { font-size: 24px; }
    .section-title { font-size: 18px; }
    .profile-right h3 { font-size: 25px; }
    .student-page { padding: 18px; }
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

<p><strong>Phone:</strong> <?= h(visibleStudentContactValue($reg['enquiry_snapshot_phone'])) ?></p>

<p><strong>Email:</strong> <?= h(visibleStudentContactValue($reg['enquiry_snapshot_email'])) ?></p>

</div>

</div>

<div class="details-grid">

<div class="student-profile-card-block">


<!-- ===============================
PERSONAL INFORMATION
=============================== -->

<h3 class="section-title">Personal Information</h3>

<table class="student-profile-table">

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
<td><?= h(visibleStudentContactValue($profile['parent_phone'] ?? '-')) ?></td>
</tr>

<tr>
<td>Address</td>
<td><?= h($profile['address']) ?></td>
</tr>

</table>

</div>

<div class="student-profile-card-block">


<!-- ===============================
COURSE / INTERNSHIP
=============================== -->

<h3 class="section-title">Course / Internship Information</h3>

<table class="student-profile-table">

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

</div>

<div class="student-profile-card-block">


<!-- ===============================
FEE SUMMARY
=============================== -->

<h3 class="section-title">Fee Summary</h3>

<table class="student-profile-table">

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


</div>

<div class="student-profile-card-block">

<!-- ===============================
PAYMENT HISTORY
=============================== -->

<h3 class="section-title">Payment History</h3>

<table class="student-profile-table">

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
href="index.php?page=payments/receipt&payment_id=<?= (int)$p['id'] ?>"
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

</div>
