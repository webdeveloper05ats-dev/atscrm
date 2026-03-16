<?php
ob_clean(); // clear any previous output
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

header('Content-Type: application/json');

$roleId = (int)($_SESSION['role_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

/* ===============================
   BASE WHERE CONDITION
=============================== */

$where = " WHERE r.reg_type='course' ";
$params = [];

/* Front Office restriction */

if (!in_array($roleId,[1,2])) {

    $where .= " AND r.assigned_to = ?";
    $params[] = $userId;

}

/* ===============================
   FILTERS
=============================== */

if (!empty($_POST['date_from'])) {
    $date_from = $_POST['date_from'];
    $where .= " AND r.joined_on >= '$date_from'";
}

if (!empty($_POST['date_to'])) {
    $date_to = $_POST['date_to'];
    $where .= " AND r.joined_on <= '$date_to'";
}

if (!empty($_POST['program'])) {
    $program = $_POST['program'];
    $where .= " AND r.program_name=".$pdo->quote($program);
}

if (!empty($_POST['payment_status'])) {
    $status = $_POST['payment_status'];
    $where .= " AND r.payment_status=".$pdo->quote($status);
}

/* ===============================
   FETCH DATA
=============================== */

$sql = "
SELECT
r.id,
r.registration_no,
r.enquiry_snapshot_name,
r.enquiry_snapshot_phone,
r.program_name,
r.batch_name,
r.joined_on,
r.total_fee,
r.paid_amount,
r.balance_amount,
r.payment_status
FROM registrations r
$where
ORDER BY r.created_at DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   FORMAT DATA FOR DATATABLE
=============================== */

$sn = 1;
$data = [];

foreach ($rows as $r) {

$student = "
<strong>{$r['enquiry_snapshot_name']}</strong><br>
<small>{$r['registration_no']}</small><br>
<small>{$r['enquiry_snapshot_phone']}</small>
";

$program = "
<strong>{$r['program_name']}</strong><br>
<small>{$r['batch_name']}</small><br>
<small>{$r['joined_on']}</small>
";

$fees = "
<span class='fee-total'>Total: ₹".number_format($r['total_fee'],2)."</span><br>
<span class='fee-paid'>Paid: ₹".number_format($r['paid_amount'],2)."</span><br>
<span class='fee-balance'>Balance: ₹".number_format($r['balance_amount'],2)."</span>
";

$statusClass = "status-".$r['payment_status'];

$status = "<span class='status-badge $statusClass'>
<i class='fa fa-circle'></i> ".ucfirst($r['payment_status'])."
</span>";

$action = '<a href="index.php?page=reports/student_profile&id='.$r['id'].'" 
class="action-btn" 
title="View Student Profile">
<i class="fa fa-eye"></i>
</a>';

$data[] = [
$sn++,
$student,
$program,
$fees,
$status,
$action
];

}

echo json_encode([
"data"=>$data
]);
exit;