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
$roleName = trim((string) ($_SESSION['role_name'] ?? ''));
$isStaffViewer = strtolower($roleName) === 'staff';

/* ===============================
   BASE WHERE
=============================== */
$where = " WHERE r.reg_type = 'course' ";
$params = [];

/* ===============================
   ROLE BASED FILTER
=============================== */
$fullAccessRoles = [1, 3, 6]; // Super Admin, HR, Marketing

if (!in_array($roleId, $fullAccessRoles)) {
    if ($roleName === 'Staff') {
        $where .= " AND rc.guide_staff_id = ?";
        $params[] = $userId;
    } else {
        $where .= " AND (r.assigned_to = ? OR (COALESCE(r.source_type, '') = 'direct' AND r.created_by = ?))";
        $params[] = $userId;
        $params[] = $userId;
    }
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
    $where .= " AND rc.guide_staff_id = ?";
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
LEFT JOIN registration_courses rc ON rc.registration_id = r.id
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
    $studentPhone = $isStaffViewer
        ? ''
        : '<div class="course-phone">' . htmlspecialchars((string)($r['enquiry_snapshot_phone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';

    $student = "
    <div class='course-student'>
        <div class='course-name'>" . htmlspecialchars((string)$r['enquiry_snapshot_name'], ENT_QUOTES, 'UTF-8') . "</div>
        <div class='course-reg'>" . htmlspecialchars((string)$r['registration_no'], ENT_QUOTES, 'UTF-8') . "</div>
        {$studentPhone}
    </div>
    ";

    $program = "
    <div class='course-program'>
        <div class='course-program-name'>" . htmlspecialchars((string)$r['program_name'], ENT_QUOTES, 'UTF-8') . "</div>
        <div class='course-program-batch'>" . htmlspecialchars((string)$r['batch_name'], ENT_QUOTES, 'UTF-8') . "</div>
        <div class='course-program-date'>" . htmlspecialchars((string)$r['joined_on'], ENT_QUOTES, 'UTF-8') . "</div>
    </div>
    ";

    $fees = "
    <div class='course-fees'>
        <div class='fee-total'>Total: ₹" . number_format((float)$r['total_fee'], 2) . "</div>
        <div class='fee-paid'>Paid: ₹" . number_format((float)$r['paid_amount'], 2) . "</div>
        <div class='fee-balance'>Balance: ₹" . number_format((float)$r['balance_amount'], 2) . "</div>
    </div>
    ";

    $statusClass = "status-" . $r['payment_status'];

    $status = "
    <span class='status-badge $statusClass'>
        <i class='fa fa-circle'></i> " . ucfirst($r['payment_status']) . "
    </span>
    ";

    $action = '
    <div class="action-buttons action-group">
        <a href="index.php?page=reports/student_profile&id=' . $r['id'] . '" 
        class="action-btn view" 
        title="View Student Profile"
        data-mobile-label="View">
            <i class="fa fa-eye"></i>
        </a>
        <a href="index.php?page=reports/student_profile&id=' . $r['id'] . '&print=1" 
        class="action-btn download-report export" 
        title="Download Report"
        data-mobile-label="Download"
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
