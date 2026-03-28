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
$attendanceSummary = studentReportBuildAttendanceSummary($student, $rows);
$academicData = studentReportFetchAcademicAndHrData($pdo, $registrationId);
$assessment = $academicData['assessment'] ?? [];
$mock = $academicData['mock'] ?? [];
$hr = $academicData['hr'] ?? [];

$assessmentAverage = isset($assessment['average_marks']) && $assessment['average_marks'] !== null
    ? (float) $assessment['average_marks']
    : null;
$mockAverage = isset($mock['mock_average']) && $mock['mock_average'] !== null
    ? (float) $mock['mock_average']
    : null;
$overallAverage = null;
if ($assessmentAverage !== null && $mockAverage !== null) {
    $overallAverage = round(($assessmentAverage + $mockAverage) / 2, 2);
} elseif ($assessmentAverage !== null) {
    $overallAverage = $assessmentAverage;
} elseif ($mockAverage !== null) {
    $overallAverage = $mockAverage;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=student_schedule_' . $registrationId . '_' . date('Ymd_His') . '.csv');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['Student Name', $student['student_name'] ?: $student['enquiry_snapshot_name'] ?: '-']);
fputcsv($out, ['Registration No', $student['registration_no'] ?: '-']);
fputcsv($out, ['Program', $student['program_name'] ?: '-']);
fputcsv($out, ['Batch', $student['batch_name'] ?: '-']);
fputcsv($out, ['Attendance %', number_format((float) ($attendanceSummary['attendance_percent'] ?? 0), 2) . '%']);
fputcsv($out, ['Present Days', (int) ($attendanceSummary['present_days'] ?? 0)]);
fputcsv($out, ['Absent Days', (int) ($attendanceSummary['absent_days'] ?? 0)]);
fputcsv($out, ['Tracking Start', $attendanceSummary['start_date'] ?? '-']);
fputcsv($out, ['Recorded Entries', count($rows)]);
fputcsv($out, ['Assessment Avg', $assessmentAverage !== null ? number_format($assessmentAverage, 2) : '-']);
fputcsv($out, ['Mock Avg', $mockAverage !== null ? number_format($mockAverage, 2) : '-']);
fputcsv($out, ['Overall Avg', $overallAverage !== null ? number_format($overallAverage, 2) : '-']);
fputcsv($out, ['Mock Workflow', $mock['workflow_status'] ?? '-']);
fputcsv($out, ['HR Status', $hr['interview_status'] ?? '-']);

fclose($out);
exit;
