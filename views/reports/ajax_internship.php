<?php
ob_clean();

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

header('Content-Type: application/json');

/* ===============================
   SESSION
=============================== */
$roleId = (int)($_SESSION['role_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

/* ===============================
   BASE WHERE
=============================== */
$where = " WHERE r.reg_type = 'internship' ";
$params = [];

/* ===============================
   ROLE BASED FILTER
=============================== */
$fullAccessRoles = [1, 3, 6]; // Super Admin, HR, Marketing

if (!in_array($roleId, $fullAccessRoles)) {
    $where .= " AND (r.assigned_to = ? OR (COALESCE(r.source_type, '') = 'direct' AND r.created_by = ?))";
    $params[] = $userId;
    $params[] = $userId;
}

/* ===============================
   FILTERS
=============================== */

/* Date From */
if (!empty($_POST['date_from'])) {
    $where .= " AND r.joined_on >= ?";
    $params[] = $_POST['date_from'];
}

/* Date To */
if (!empty($_POST['date_to'])) {
    $where .= " AND r.joined_on <= ?";
    $params[] = $_POST['date_to'];
}

/* Program */
if (!empty($_POST['program'])) {
    $where .= " AND r.program_name = ?";
    $params[] = $_POST['program'];
}

/* Payment Status */
if (!empty($_POST['payment_status'])) {
    $where .= " AND r.payment_status = ?";
    $params[] = $_POST['payment_status'];
}

/* Staff Filter - Super Admin / HR only */
if (in_array($roleId, [1, 3], true) && !empty($_POST['staff_id'])) {
    $staffId = (int)$_POST['staff_id'];
    $where .= " AND (r.assigned_to = ? OR (COALESCE(r.source_type, '') = 'direct' AND r.created_by = ?))";
    $params[] = $staffId;
    $params[] = $staffId;
}

/* ===============================
   QUERY
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

/* ===============================
   EXECUTE
=============================== */
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   FORMAT FOR DATATABLE
=============================== */
$data = [];
$sn = 1;

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
    <span class='fee-total'>Total: ₹" . number_format($r['total_fee'], 2) . "</span><br>
    <span class='fee-paid'>Paid: ₹" . number_format($r['paid_amount'], 2) . "</span><br>
    <span class='fee-balance'>Balance: ₹" . number_format($r['balance_amount'], 2) . "</span>
    ";

    $statusClass = "status-" . $r['payment_status'];

    $status = "
    <span class='status-badge $statusClass'>
        <i class='fa fa-circle'></i> " . ucfirst($r['payment_status']) . "
    </span>
    ";

    $action = '
    <div class="action-group">
        <a href="index.php?page=reports/student_profile&id=' . $r['id'] . '" 
        class="action-btn" 
        title="View Student Profile">
            <i class="fa fa-eye"></i>
        </a>
        <a href="index.php?page=reports/student_profile&id=' . $r['id'] . '&print=1" 
        class="action-btn download-report" 
        title="Download Report"
        target="_blank"
        rel="noopener">
            <i class="fa fa-download"></i>
        </a>
    </div>';

    $data[] = [
        $sn++,
        $student,
        $program,
        $fees,
        $status,
        $action
    ];
}

/* ===============================
   OUTPUT JSON
=============================== */
echo json_encode([
    "data" => $data
]);
exit;
