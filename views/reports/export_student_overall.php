<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_student_report_helpers.php';

if (!in_array(($_SESSION['role_name'] ?? ''), ['HR', 'Super Admin'], true)) {
    http_response_code(403);
    exit('Access denied');
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = studentReportRoleScope($pdo, $roleId) === 1;
$registrationId = (int) ($_GET['registration_id'] ?? 0);

$student = studentReportFetchBaseStudent($pdo, $registrationId, $userId, $branchId, $canAllBranches, 'hr');
if (!$student) {
    exit('Student not found or access denied.');
}

$attendanceRows = studentReportFetchAttendanceRows($pdo, $registrationId);
$attendanceSummary = studentReportBuildAttendanceSummary($student, $attendanceRows);
$academicData = studentReportFetchAcademicAndHrData($pdo, $registrationId);
$assessment = $academicData['assessment'] ?? [];
$mock = $academicData['mock'] ?? [];
$hr = $academicData['hr'] ?? [];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=student_overall_' . $registrationId . '_' . date('Ymd_His') . '.csv');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['Student Name', $student['student_name'] ?: $student['enquiry_snapshot_name'] ?: '-']);
fputcsv($out, ['Registration No', $student['registration_no'] ?: '-']);
fputcsv($out, ['Program', $student['program_name'] ?: '-']);
fputcsv($out, ['Attendance %', $attendanceSummary['attendance_percent'] ?? 0]);
fputcsv($out, ['Present Days', $attendanceSummary['present_days'] ?? 0]);
fputcsv($out, ['Absent Days', $attendanceSummary['absent_days'] ?? 0]);
fputcsv($out, ['Assessment Avg', $assessment['average_marks'] ?? '']);
fputcsv($out, ['Assessment 1', $assessment['assessment_1'] ?? '']);
fputcsv($out, ['Assessment 2', $assessment['assessment_2'] ?? '']);
fputcsv($out, ['Assessment 3', $assessment['assessment_3'] ?? '']);
fputcsv($out, ['Mock Avg', $mock['mock_average'] ?? '']);
fputcsv($out, ['Mock Theory', $mock['theoretical_marks'] ?? '']);
fputcsv($out, ['Mock Machine', $mock['machine_task_marks'] ?? '']);
fputcsv($out, ['HR Status', $hr['interview_status'] ?? '']);
fputcsv($out, ['Last Company', $hr['company_name'] ?? '']);
fputcsv($out, ['Interview Date', $hr['interview_date'] ?? '']);
fputcsv($out, ['Fee Total', $student['total_fee'] ?? 0]);
fputcsv($out, ['Fee Discount', $student['discount_amount'] ?? 0]);
fputcsv($out, ['Fee Final', $student['final_fee'] ?? 0]);
fputcsv($out, ['Fee Paid', $student['paid_amount'] ?? 0]);
fputcsv($out, ['Fee Balance', $student['balance_amount'] ?? 0]);
fputcsv($out, []);
fputcsv($out, ['Placement History']);
fputcsv($out, ['Company', 'Date', 'Time', 'Mode', 'Status', 'Remarks']);

foreach (($academicData['placement_history'] ?? []) as $row) {
    fputcsv($out, [
        $row['company_name'] ?? '',
        $row['interview_date'] ?? '',
        $row['interview_time'] ?? '',
        $row['interview_mode'] ?? '',
        $row['status'] ?? '',
        $row['remarks'] ?? '',
    ]);
}

fclose($out);
exit;
