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
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    padding: 40px;
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.08), 0 8px 20px rgba(0, 0, 0, 0.04);
    max-width: 1100px;
    margin: 30px auto;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    color: #1e293b;
    line-height: 1.6;
}

h2 {
    font-size: 32px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 32px;
    letter-spacing: -0.5px;
}

.profile-card {
    display: flex;
    gap: 32px;
    background: rgba(255, 255, 255, 0.75);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(226, 232, 240, 0.6);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 40px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.profile-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.1);
}

.profile-left img,
.profile-photo {
    width: 160px;
    height: 160px;
    border-radius: 16px;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    transition: transform 0.3s ease;
}

.profile-left img:hover,
.profile-photo:hover {
    transform: scale(1.06) rotate(2deg);
}

.profile-right h3 {
    margin: 0 0 16px;
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
}

.profile-right p {
    margin: 10px 0;
    font-size: 15.5px;
    color: #475569;
}

.profile-right p strong {
    color: #1e293b;
    font-weight: 700;
}

.section-title {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
    margin: 48px 0 20px;
    position: relative;
    display: inline-block;
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 70px;
    height: 4px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    border-radius: 4px;
}

.table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    font-size: 15px;
}

.table th,
.table td {
    padding: 16px 20px;
    text-align: left;
}

.table th {
    background: #f1f5f9;
    font-weight: 700;
    color: #334155;
    text-transform: uppercase;
    font-size: 13px;
    letter-spacing: 0.6px;
    border-bottom: 2px solid #e2e8f0;
}

.table td {
    color: #475569;
    border-bottom: 1px solid #f1f5f9;
}

.table tr:last-child td {
    border-bottom: none;
}

.table tr:hover td {
    background: #f8fafc;
}

/* Fee emphasis - premium look */
.table tr td:first-child {
    font-weight: 600;
    color: #1e293b;
}

.table tr:has(td:contains("Final Fee")) td {
    background: #ecfdf5;
    font-weight: 700;
}

.table tr:has(td:contains("Paid")) td {
    color: #059669;
    font-weight: 700;
}

.table tr:has(td:contains("Balance")) td {
    color: #dc2626;
    font-weight: 700;
    background: #fef2f2;
}

/* Payment history */
.table thead th {
    background: #e0f2fe;
    color: #0369a1;
}

.table td a {
    color: #6366f1;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s;
}

.table td a:hover {
    color: #4f46e5;
    text-decoration: underline;
}

/* Responsive - important for product */
@media (max-width: 992px) {
    .profile-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
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
    .table th,
    .table td {
        padding: 12px 14px;
        font-size: 14px;
    }
    h2 { font-size: 26px; }
    .section-title { font-size: 20px; }
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

<p><strong>Phone:</strong> <?= h($reg['enquiry_snapshot_phone']) ?></p>

<p><strong>Email:</strong> <?= h($reg['enquiry_snapshot_email']) ?></p>

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