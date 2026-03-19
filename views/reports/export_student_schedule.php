<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_student_report_helpers.php';

if (!in_array(($_SESSION['role_name'] ?? ''), ['Staff', 'Super Admin'], true)) {
    http_response_code(403);
    exit('Access denied');
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = studentReportRoleScope($pdo, $roleId) === 1;
$registrationId = (int) ($_GET['registration_id'] ?? 0);

$student = studentReportFetchBaseStudent($pdo, $registrationId, $userId, $branchId, $canAllBranches, 'staff');
if (!$student) {
    exit('Student not found or access denied.');
}

$rows = studentReportFetchAttendanceRows($pdo, $registrationId);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=student_schedule_' . $registrationId . '_' . date('Ymd_His') . '.csv');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['Student Name', $student['student_name'] ?: $student['enquiry_snapshot_name'] ?: '-']);
fputcsv($out, ['Registration No', $student['registration_no'] ?: '-']);
fputcsv($out, ['Program', $student['program_name'] ?: '-']);
fputcsv($out, ['Batch', $student['batch_name'] ?: '-']);
fputcsv($out, []);
fputcsv($out, ['Date', 'Status', 'Topic Taught', 'Task Given', 'Absent Informed', 'Absent Reason', 'Absent Informed By']);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['attendance_date'] ?: '',
        $row['status'] ?: '',
        $row['topics_taught'] ?: '',
        $row['task_given'] ?: '',
        $row['absent_informed'] ?: '',
        $row['absent_reason'] ?: '',
        $row['absent_informed_by'] ?: '',
    ]);
}

fclose($out);
exit;
