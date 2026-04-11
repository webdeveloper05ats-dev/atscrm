<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_student_report_helpers.php';

if (!in_array(($_SESSION['role_name'] ?? ''), ['HR', 'Super Admin'], true)) {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Poppins,sans-serif'><h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2><p style='margin:0;color:#666'>This page is available only for HR users.</p></div>";
    return;
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = studentReportRoleScope($pdo, $roleId) === 1;
$registrationId = (int) ($_GET['registration_id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));

$params = [];
$where = [
    "r.reg_type = 'course'",
    "EXISTS (SELECT 1 FROM student_hr_interviews shi WHERE shi.registration_id = r.id)",
];

if (!$canAllBranches && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($q !== '') {
    $where[] = "(r.registration_no LIKE ? OR r.enquiry_snapshot_name LIKE ? OR r.program_name LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$st = $pdo->prepare("
    SELECT r.id, r.registration_no, r.enquiry_snapshot_name, r.program_name
    FROM registrations r
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.enquiry_snapshot_name ASC, r.id DESC
");
$st->execute($params);
$students = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$student = null;
$attendanceRows = [];
$attendanceSummary = null;
$academicData = [];

if ($registrationId > 0) {
    $student = studentReportFetchBaseStudent($pdo, $registrationId, $userId, $branchId, $canAllBranches, 'hr');
    if ($student) {
        $attendanceRows = studentReportFetchAttendanceRows($pdo, $registrationId);
        $attendanceSummary = studentReportBuildAttendanceSummary($student, $attendanceRows);
        $academicData = studentReportFetchAcademicAndHrData($pdo, $registrationId);
    }
}

$reportGeneratedAt = date('d M Y h:i A');
?>

<h2 style="margin-bottom:20px;">Student Overall Report</h2>

<div class="card">
    <div class="card-header">Choose Student</div>
    <form method="GET" action="index.php" style="padding:14px;">
        <input type="hidden" name="page" value="reports/student_overall">
        <div class="sor-filter-row">
            <div>
                <label>Search</label>
                <input type="text" name="q" value="<?= studentReportH($q) ?>" placeholder="Registration, student, program">
            </div>
            <div>
                <label>Student</label>
                <select name="registration_id">
                    <option value="">Select student</option>
                    <?php foreach ($students as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= $registrationId === (int) $row['id'] ? 'selected' : '' ?>>
                            <?= studentReportH(($row['enquiry_snapshot_name'] ?: '-') . ' | ' . ($row['registration_no'] ?: 'REG-' . $row['id'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sor-filter-actions">
                <div class="crm-icon-actions">
                    <button type="submit" class="crm-icon-btn is-primary" data-modern-tooltip="Load report" aria-label="Load report">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="index.php?page=reports/student_overall" class="crm-icon-btn is-muted" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<?php if ($registrationId > 0 && !$student): ?>
    <div class="sor-alert">Student not found or access denied.</div>
<?php endif; ?>

<?php if ($student): ?>
    <?php $hr = $academicData['hr'] ?? []; ?>
    <?php $assessment = $academicData['assessment'] ?? []; ?>
    <?php $mock = $academicData['mock'] ?? []; ?>
    <div class="card" style="margin-top:16px;">
        <div class="card-header">Overall Student Report</div>
        <div class="sor-wrap">
            <div class="sor-print-hero">
                <div class="sor-print-kicker">Official Report</div>
                <div class="sor-print-title">Student Overall Report</div>
                <div class="sor-print-copy">A consolidated summary of academic, attendance, HR, placement, and fee details for internal review.</div>
                <div class="sor-print-meta">
                    <div class="sor-print-meta-card">
                        <div class="sor-print-meta-label">Student</div>
                        <div class="sor-print-meta-value"><?= studentReportH($student['student_name'] ?: $student['enquiry_snapshot_name'] ?: '-') ?></div>
                    </div>
                    <div class="sor-print-meta-card">
                        <div class="sor-print-meta-label">Registration No</div>
                        <div class="sor-print-meta-value"><?= studentReportH($student['registration_no'] ?: '-') ?></div>
                    </div>
                    <div class="sor-print-meta-card">
                        <div class="sor-print-meta-label">Program</div>
                        <div class="sor-print-meta-value"><?= studentReportH($student['program_name'] ?: '-') ?></div>
                    </div>
                    <div class="sor-print-meta-card">
                        <div class="sor-print-meta-label">Generated On</div>
                        <div class="sor-print-meta-value"><?= studentReportH($reportGeneratedAt) ?></div>
                    </div>
                </div>
            </div>
            <div class="sor-topbar">
                <div>
                    <div class="sor-name"><?= studentReportH($student['student_name'] ?: $student['enquiry_snapshot_name'] ?: '-') ?></div>
                    <div class="sor-meta"><?= studentReportH($student['registration_no'] ?: '-') ?> | <?= studentReportH($student['program_name'] ?: '-') ?> | <?= studentReportH($student['assigned_staff_name'] ?: '-') ?></div>
                </div>
                <div class="sor-actions">
                    <div class="crm-icon-actions">
                        <a href="index.php?page=reports/export_student_overall&registration_id=<?= (int) $student['id'] ?>" class="crm-icon-btn is-success" data-modern-tooltip="Download CSV" aria-label="Download CSV">
                            <i class="fas fa-file-csv"></i>
                        </a>
                        <button type="button" class="crm-icon-btn is-muted" onclick="window.print()" data-modern-tooltip="Print report" aria-label="Print report">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sor-summary-grid">
                <div class="sor-card"><div class="sor-label">Attendance %</div><div class="sor-value"><?= studentReportH(number_format((float) ($attendanceSummary['attendance_percent'] ?? 0), 2)) ?>%</div></div>
                <div class="sor-card"><div class="sor-label">Assessment Avg</div><div class="sor-value"><?= studentReportH(isset($assessment['average_marks']) ? number_format((float) $assessment['average_marks'], 2) : '-') ?></div></div>
                <div class="sor-card"><div class="sor-label">Mock Avg</div><div class="sor-value"><?= studentReportH(isset($mock['mock_average']) ? number_format((float) $mock['mock_average'], 2) : '-') ?></div></div>
                <div class="sor-card"><div class="sor-label">HR Status</div><div class="sor-value"><?= studentReportH(ucwords(str_replace('_', ' ', (string) ($hr['interview_status'] ?? 'pending')))) ?></div></div>
                <div class="sor-card"><div class="sor-label">Placement Count</div><div class="sor-value"><?= (int) count($academicData['placement_history'] ?? []) ?></div></div>
            </div>

            <div class="sor-section-grid">
                <div class="sor-panel">
                    <div class="sor-panel-title">Profile</div>
                    <div class="sor-row"><b>Phone:</b> <?= studentReportH($student['enquiry_snapshot_phone'] ?: '-') ?></div>
                    <div class="sor-row"><b>Email:</b> <?= studentReportH($student['enquiry_snapshot_email'] ?: '-') ?></div>
                    <div class="sor-row"><b>Qualification:</b> <?= studentReportH($student['qualification'] ?: '-') ?></div>
                    <div class="sor-row"><b>College:</b> <?= studentReportH($student['college_name'] ?: '-') ?></div>
                    <div class="sor-row"><b>Parent:</b> <?= studentReportH($student['parent_name'] ?: '-') ?> | <?= studentReportH($student['parent_phone'] ?: '-') ?></div>
                </div>
                <div class="sor-panel">
                    <div class="sor-panel-title">Academic</div>
                    <div class="sor-row"><b>Present Days:</b> <?= (int) ($attendanceSummary['present_days'] ?? 0) ?></div>
                    <div class="sor-row"><b>Absent Days:</b> <?= (int) ($attendanceSummary['absent_days'] ?? 0) ?></div>
                    <div class="sor-row"><b>Assessment Marks:</b> <?= studentReportH(($assessment['assessment_1'] ?? '-') . ' / ' . ($assessment['assessment_2'] ?? '-') . ' / ' . ($assessment['assessment_3'] ?? '-')) ?></div>
                    <div class="sor-row"><b>Mock Marks:</b> <?= studentReportH(($mock['theoretical_marks'] ?? '-') . ' / ' . ($mock['machine_task_marks'] ?? '-')) ?></div>
                    <div class="sor-row"><b>Mock Workflow:</b> <?= studentReportH(ucwords(str_replace('_', ' ', (string) ($mock['workflow_status'] ?? 'pending')))) ?></div>
                </div>
                <div class="sor-panel">
                    <div class="sor-panel-title">HR & Placement</div>
                    <div class="sor-row"><b>Sent To HR:</b> <?= studentReportH($hr['sent_to_hr_at'] ?? '-') ?></div>
                    <div class="sor-row"><b>Sent By:</b> <?= studentReportH($hr['sent_by_name'] ?? '-') ?></div>
                    <div class="sor-row"><b>Last Company:</b> <?= studentReportH($hr['company_name'] ?? '-') ?></div>
                    <div class="sor-row"><b>Interview Date:</b> <?= studentReportH($hr['interview_date'] ?? '-') ?></div>
                    <div class="sor-row"><b>Remarks:</b> <?= studentReportH($hr['rejection_reason'] ?? '-') ?></div>
                </div>
                <div class="sor-panel">
                    <div class="sor-panel-title">Fees</div>
                    <div class="sor-row"><b>Total Fee:</b> <?= inr_symbol() ?> <?= studentReportH(number_format((float) ($student['total_fee'] ?? 0), 2)) ?></div>
                    <div class="sor-row"><b>Discount:</b> <?= inr_symbol() ?> <?= studentReportH(number_format((float) ($student['discount_amount'] ?? 0), 2)) ?></div>
                    <div class="sor-row"><b>Final Fee:</b> <?= inr_symbol() ?> <?= studentReportH(number_format((float) ($student['final_fee'] ?? 0), 2)) ?></div>
                    <div class="sor-row"><b>Paid:</b> <?= inr_symbol() ?> <?= studentReportH(number_format((float) ($student['paid_amount'] ?? 0), 2)) ?></div>
                    <div class="sor-row"><b>Balance:</b> <?= inr_symbol() ?> <?= studentReportH(number_format((float) ($student['balance_amount'] ?? 0), 2)) ?></div>
                    <div class="sor-row"><b>Payment Status:</b> <?= studentReportH(ucfirst((string) ($student['payment_status'] ?? '-'))) ?></div>
                </div>
            </div>

            <div class="sor-panel" style="margin-top:16px;">
                <div class="sor-panel-title">Placement Interview History</div>
                <div class="table-responsive">
                    <table class="table sor-table no-mobile-cards">
                        <thead><tr><th>Company</th><th>Date</th><th>Mode</th><th>Status</th><th>Remarks</th></tr></thead>
                        <tbody>
                            <?php if (empty($academicData['placement_history'])): ?>
                                <tr><td colspan="5" class="sor-empty">No placement interviews recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($academicData['placement_history'] as $row): ?>
                                    <tr>
                                        <td><?= studentReportH($row['company_name'] ?: '-') ?></td>
                                        <td><?= studentReportH(trim(($row['interview_date'] ?? '-') . ' ' . ($row['interview_time'] ?? ''))) ?></td>
                                        <td><?= studentReportH($row['interview_mode'] ?: '-') ?></td>
                                        <td><?= studentReportH(ucwords(str_replace('_', ' ', (string) ($row['status'] ?? '-')))) ?></td>
                                        <td><?= nl2br(studentReportH($row['remarks'] ?: '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>



