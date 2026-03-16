<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

/* ===============================
   HEADERS FOR CSV DOWNLOAD
=============================== */

$filename = "course_report_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename='.$filename);

/* UTF-8 BOM for Excel */
echo "\xEF\xBB\xBF";

/* ===============================
   USER SESSION
=============================== */

$roleId = (int)($_SESSION['role_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

/* ===============================
   FILTERS
=============================== */

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$program   = $_GET['program'] ?? '';
$status    = $_GET['payment_status'] ?? '';

/* ===============================
   BASE QUERY
=============================== */

$where = " WHERE r.reg_type='course' ";

/* Role restriction */

if (!in_array($roleId,[1,2])) {
    $where .= " AND r.created_by=".$userId;
}

/* Filters */

if ($date_from!='') {
    $where .= " AND r.joined_on >= ".$pdo->quote($date_from);
}

if ($date_to!='') {
    $where .= " AND r.joined_on <= ".$pdo->quote($date_to);
}

if ($program!='') {
    $where .= " AND r.program_name=".$pdo->quote($program);
}

if ($status!='') {
    $where .= " AND r.payment_status=".$pdo->quote($status);
}

/* ===============================
   FETCH DATA
=============================== */

$sql = "
SELECT
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
   OPEN OUTPUT STREAM
=============================== */

$output = fopen("php://output","w");

/* ===============================
   CSV HEADER
=============================== */

fputcsv($output,[
'S.No',
'Student Name',
'Registration No',
'Phone',
'Program',
'Batch',
'Join Date',
'Total Fee',
'Paid Amount',
'Balance',
'Payment Status'
]);

/* ===============================
   CSV DATA
=============================== */

$sn=1;

foreach($rows as $r){

fputcsv($output,[

$sn++,

$r['enquiry_snapshot_name'],

$r['registration_no'],

$r['enquiry_snapshot_phone'],

$r['program_name'],

$r['batch_name'],

$r['joined_on'],

$r['total_fee'],

$r['paid_amount'],

$r['balance_amount'],

ucfirst($r['payment_status'])

]);

}

fclose($output);
exit;