<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_student_report_helpers.php';

if (!in_array(($_SESSION['role_name'] ?? ''), ['Staff', 'Super Admin'], true)) {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Segoe UI,sans-serif'><h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2><p style='margin:0;color:#666'>This page is available only for staff users.</p></div>";
    return;
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = studentReportRoleScope($pdo, $roleId) === 1;
$registrationId = (int) ($_GET['registration_id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));

$students = [];
$params = [];
$where = [
    "r.reg_type = 'course'",
    "r.registration_status IN ('active','completed')",
];

if (($_SESSION['role_name'] ?? '') === 'Staff') {
    $where[] = "rc.guide_staff_id = ?";
    $params[] = $userId;
}

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
    SELECT r.id, r.registration_no, r.enquiry_snapshot_name, r.program_name, r.batch_name
    FROM registrations r
    LEFT JOIN registration_courses rc ON rc.registration_id = r.id
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
    $student = studentReportFetchBaseStudent($pdo, $registrationId, $userId, $branchId, $canAllBranches, 'staff');
    if ($student) {
        $attendanceRows = studentReportFetchAttendanceRows($pdo, $registrationId);
        $attendanceSummary = studentReportBuildAttendanceSummary($student, $attendanceRows);
        $academicData = studentReportFetchAcademicAndHrData($pdo, $registrationId);
    }
}
?>

<h2 style="margin-bottom:20px;">Student Schedule Report</h2>

<div class="card">
    <div class="card-header">Choose Student</div>
    <form method="GET" action="index.php" style="padding:14px;">
        <input type="hidden" name="page" value="reports/student_schedule">
        <div class="ssr-filter-row">
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
            <div class="ssr-filter-actions">
                <button class="btn btn-primary">Load Report</button>
                <a href="index.php?page=reports/student_schedule" class="btn" style="background:#f3f4f6;">Reset</a>
            </div>
        </div>
    </form>
</div>

<?php if ($registrationId > 0 && !$student): ?>
    <div class="ssr-alert">Student not found or access denied.</div>
<?php endif; ?>

<?php if ($student): ?>
    <div class="card" style="margin-top:16px;">
        <div class="card-header">Schedule Report</div>
        <div class="ssr-wrap">
            <div class="ssr-topbar">
                <div>
                    <div class="ssr-name"><?= studentReportH($student['student_name'] ?: $student['enquiry_snapshot_name'] ?: '-') ?></div>
                    <div class="ssr-meta"><?= studentReportH($student['registration_no'] ?: '-') ?> | <?= studentReportH($student['program_name'] ?: '-') ?> | <?= studentReportH($student['batch_name'] ?: '-') ?></div>
                </div>
                <div class="ssr-actions">
                    <a href="index.php?page=reports/export_student_schedule&registration_id=<?= (int) $student['id'] ?>" class="btn btn-primary">Download CSV</a>
                    <button type="button" class="btn" style="background:#f3f4f6;" onclick="window.print()">Print</button>
                </div>
            </div>

            <div class="ssr-summary-grid">
                <div class="ssr-card"><div class="ssr-label">Attendance %</div><div class="ssr-value"><?= studentReportH(number_format((float) ($attendanceSummary['attendance_percent'] ?? 0), 2)) ?>%</div></div>
                <div class="ssr-card"><div class="ssr-label">Present Days</div><div class="ssr-value"><?= (int) ($attendanceSummary['present_days'] ?? 0) ?></div></div>
                <div class="ssr-card"><div class="ssr-label">Absent Days</div><div class="ssr-value"><?= (int) ($attendanceSummary['absent_days'] ?? 0) ?></div></div>
                <div class="ssr-card"><div class="ssr-label">Assessment Avg</div><div class="ssr-value"><?= studentReportH(isset($academicData['assessment']['average_marks']) ? number_format((float) $academicData['assessment']['average_marks'], 2) : '-') ?></div></div>
                <div class="ssr-card"><div class="ssr-label">Mock Avg</div><div class="ssr-value"><?= studentReportH(isset($academicData['mock']['mock_average']) ? number_format((float) $academicData['mock']['mock_average'], 2) : '-') ?></div></div>
            </div>

            <div class="table-responsive">
                <table class="table ssr-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Topic Taught</th>
                            <th>Task Given</th>
                            <th>Absent Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$attendanceRows): ?>
                            <tr><td colspan="5" class="ssr-empty">No attendance schedule entries recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendanceRows as $row): ?>
                                <?php $isAbsent = strtolower((string) ($row['status'] ?? '')) === 'absent'; ?>
                                <tr>
                                    <td><?= studentReportH($row['attendance_date'] ?: '-') ?></td>
                                    <td><?= studentReportH($row['status'] ?: '-') ?></td>
                                    <td><?= nl2br(studentReportH($row['topics_taught'] ?: '-')) ?></td>
                                    <td><?= nl2br(studentReportH($row['task_given'] ?: '-')) ?></td>
                                    <td>
                                        <?php if ($isAbsent): ?>
                                            <?= studentReportH('Informed: ' . (($row['absent_informed'] ?? '') === 'yes' ? 'Yes' : 'No')) ?><br>
                                            <?= studentReportH('Reason: ' . ($row['absent_reason'] ?: '-')) ?><br>
                                            <?= studentReportH('By: ' . ($row['absent_informed_by'] ?: '-')) ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.ssr-filter-row,.ssr-filter-actions,.ssr-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:end;}
.ssr-filter-row > div{flex:1 1 260px;}
.ssr-alert{margin-top:16px;padding:14px 16px;border-radius:14px;background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;font-weight:700;}
.ssr-wrap{padding:16px;}
.ssr-topbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px;}
.ssr-name{font-size:22px;font-weight:900;color:#111827;}
.ssr-meta{margin-top:6px;color:#64748b;}
.ssr-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:16px;}
.ssr-card{border:1px solid #f3e8ef;border-radius:16px;padding:14px;background:#fff;}
.ssr-label{font-size:12px;font-weight:800;color:#9d174d;text-transform:uppercase;}
.ssr-value{margin-top:8px;font-size:22px;font-weight:900;color:#111827;}
.ssr-table th,.ssr-table td{vertical-align:top;white-space:normal;}
.ssr-empty{text-align:center;color:#64748b;font-weight:700;}
@media (max-width: 1000px){.ssr-summary-grid{grid-template-columns:1fr 1fr;}}
@media print{.wrapper aside,.card:first-of-type,.ssr-actions,.sidebar,.topbar,.header{display:none !important;}.content,.main-content{padding:0 !important;}.card{box-shadow:none !important;border:none !important;}}
</style>
