<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

/* ===============================
   NEW MODIFICATION DONE ON 2026-03-23
   Improve internship overall export for management use
=============================== */

/* ===============================
   HEADERS FOR CSV DOWNLOAD
=============================== */

$filename = "internship_report_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename='.$filename);

/* UTF-8 BOM for Excel */
echo "\xEF\xBB\xBF";

/* ===============================
   USER SESSION
=============================== */

$roleId = (int)($_SESSION['role_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$roleName = trim((string) ($_SESSION['role_name'] ?? ''));
$canViewPersonalDetails = in_array(strtolower($roleName), ['super admin', 'hr'], true) || $roleId === 1;

/* ===============================
   FILTERS
=============================== */

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$program   = $_GET['program'] ?? '';
$status    = $_GET['payment_status'] ?? '';
$staffId   = (int)($_GET['staff_id'] ?? 0);

/* ===============================
   HELPERS
=============================== */

$formatDate = static function ($value): string {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp ? date('Y-m-d', $timestamp) : (string)$value;
};

$formatStatus = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    return ucwords(str_replace('_', ' ', $value));
};

/* ===============================
   BASE QUERY
=============================== */

$where = ["r.reg_type = 'internship'"];
$params = [];

/* Role restriction */

/* Match the report page access behavior */
$fullAccessRoles = [1, 3, 6]; // Super Admin, HR, Marketing
if (!in_array($roleId, $fullAccessRoles, true)) {
    if ($roleName === 'Staff') {
        $where[] = "ri.guide_staff_id = ?";
        $params[] = $userId;
    } else {
        $where[] = "r.assigned_to = ?";
        $params[] = $userId;
    }
}

/* Filters */

if ($date_from!='') {
    $where[] = "r.joined_on >= ?";
    $params[] = $date_from;
}

if ($date_to!='') {
    $where[] = "r.joined_on <= ?";
    $params[] = $date_to;
}

if ($program!='') {
    $where[] = "r.program_name = ?";
    $params[] = $program;
}

if ($status!='') {
    $where[] = "r.payment_status = ?";
    $params[] = $status;
}

if (in_array($roleId,[1,3], true) && $staffId > 0) {
    $where[] = "(r.assigned_to = ? OR ri.guide_staff_id = ? OR (COALESCE(r.source_type, '') = 'direct' AND r.created_by = ?))";
    $params[] = $staffId;
    $params[] = $staffId;
    $params[] = $staffId;
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

/* ===============================
   FETCH DATA
=============================== */

$sql = "
SELECT
r.id,
r.registration_no,
r.enquiry_snapshot_name,
r.enquiry_snapshot_phone,
r.enquiry_snapshot_email,
r.program_name,
r.batch_name,
r.joined_on,
r.assigned_to,
ri.internship_days,
ri.internship_start_date,
ri.internship_end_date,
ri.completion_status AS internship_completion_status,
ri.certificate_status AS internship_certificate_status,
ri.certificate_issued_at AS internship_certificate_issued_at,
ri.report_status AS internship_report_status,
ri.report_issued_at AS internship_report_issued_at,
r.total_fee,
r.paid_amount,
r.balance_amount,
 r.payment_status,
 COALESCE(u.name, '-') AS assigned_staff_name
FROM registrations r
LEFT JOIN registration_internships ri ON ri.registration_id = r.id
LEFT JOIN users u ON u.id = ri.guide_staff_id
$whereSql
ORDER BY r.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   OPEN OUTPUT STREAM
=============================== */

$output = fopen("php://output","w");

/* ===============================
   CSV HEADER
=============================== */

$headers = [
    'S.No',
    'Student Name',
    'Registration No',
];

if ($canViewPersonalDetails) {
    $headers[] = 'Phone';
    $headers[] = 'Email';
}

$headers = array_merge($headers, [
    'Program',
    'Batch',
    'Assigned Staff',
    'Join Date',
    'Internship Days',
    'Start Date',
    'End Date',
    'Completion Status',
    'Certificate Status',
    'Certificate Issued Date',
    'Report Status',
    'Report Issued Date',
    'Total Fee',
    'Paid Amount',
    'Balance',
    'Payment Status'
]);

fputcsv($output, $headers);

/* ===============================
   CSV DATA
=============================== */

$sn=1;

foreach($rows as $r){

$totalFee = (float)($r['total_fee'] ?? 0);
$paidAmount = (float)($r['paid_amount'] ?? 0);
$balanceAmount = (float)($r['balance_amount'] ?? 0);

    $csvRow = [
        $sn++,
        trim((string)($r['enquiry_snapshot_name'] ?? '')),
        trim((string)($r['registration_no'] ?? '')),
    ];

    if ($canViewPersonalDetails) {
        $csvRow[] = trim((string)($r['enquiry_snapshot_phone'] ?? ''));
        $csvRow[] = trim((string)($r['enquiry_snapshot_email'] ?? ''));
    }

    $csvRow = array_merge($csvRow, [
        trim((string)($r['program_name'] ?? '')),
        trim((string)($r['batch_name'] ?? '')),
        $r['assigned_staff_name'] !== '' ? $r['assigned_staff_name'] : '-',
        $formatDate($r['joined_on'] ?? ''),
        (string)($r['internship_days'] ?? ''),
        $formatDate($r['internship_start_date'] ?? ''),
        $formatDate($r['internship_end_date'] ?? ''),
        $formatStatus($r['internship_completion_status'] ?? ''),
        $formatStatus($r['internship_certificate_status'] ?? ''),
        $formatDate($r['internship_certificate_issued_at'] ?? ''),
        $formatStatus($r['internship_report_status'] ?? ''),
        $formatDate($r['internship_report_issued_at'] ?? ''),
        number_format($totalFee, 2, '.', ''),
        number_format($paidAmount, 2, '.', ''),
        number_format($balanceAmount, 2, '.', ''),
        $formatStatus($r['payment_status'] ?? '')
    ]);

    fputcsv($output, $csvRow);

}

fclose($output);
exit;
