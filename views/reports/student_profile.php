<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_student_report_helpers.php';

$id = (int) ($_GET['id'] ?? 0);
$isPrintMode = (int) ($_GET['print'] ?? 0) === 1;
$isStaffViewer = strtolower(trim((string) ($_SESSION['role_name'] ?? ''))) === 'staff';
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = studentReportRoleScope($pdo, $roleId) === 1;

if ($id <= 0) {
    echo "<div class='alert alert-danger'>Invalid student ID</div>";
    return;
}

/* =========================
   FETCH STUDENT
========================= */

$stmt = $pdo->prepare("
SELECT
    r.*,
    ri.guide_staff_id AS internship_guide_staff_id,
    ri.internship_days,
    ri.internship_batch,
    ri.internship_start_date,
    ri.internship_end_date,
    ri.completion_status AS internship_completion_status,
    ri.certificate_status AS internship_certificate_status,
    ri.certificate_issued_at AS internship_certificate_issued_at,
    ri.report_status AS internship_report_status,
    ri.report_issued_at AS internship_report_issued_at,
    ri.report_due_days AS internship_report_due_days
FROM registrations r
LEFT JOIN registration_internships ri ON ri.registration_id = r.id
WHERE r.id=?
LIMIT 1
");

$stmt->execute([$id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "<div class='alert alert-danger'>Student not found</div>";
    return;
}

$isCourseStudent = (($student['reg_type'] ?? '') === 'course');
$isInternshipStudent = (($student['reg_type'] ?? '') === 'internship');

if ($isCourseStudent) {
    $courseStudentMode = $isStaffViewer ? 'staff' : '';
    $courseStudent = studentReportFetchBaseStudent($pdo, $id, $userId, $branchId, $canAllBranches, $courseStudentMode);
    if ($courseStudent) {
        $student = array_merge($student, $courseStudent);
    } elseif ($isStaffViewer) {
        echo "<div class='alert alert-danger'>Student not found or access denied</div>";
        return;
    }
}

/* =========================
   PAYMENT HISTORY
========================= */

$payments = $pdo->prepare("
SELECT 
p.payment_date,
p.amount,
p.payment_mode,
u.name AS collected_by_name
FROM registration_payments p
LEFT JOIN users u ON u.id = p.collected_by
WHERE p.registration_id = ?
ORDER BY p.payment_date DESC
");

$payments->execute([$id]);
$payments = $payments->fetchAll(PDO::FETCH_ASSOC);

$attendanceRows = [];
$attendanceSummary = null;
$academicData = [
    'assessment' => null,
    'mock' => null,
    'hr' => null,
    'placement_history' => [],
];

if ($isCourseStudent) {
    $attendanceRows = studentReportFetchAttendanceRows($pdo, $id);
    $attendanceSummary = studentReportBuildAttendanceSummary($student, $attendanceRows);
    $academicData = studentReportFetchAcademicAndHrData($pdo, $id);
}

$assessment = $academicData['assessment'] ?? [];
$mock = $academicData['mock'] ?? [];
$hr = $academicData['hr'] ?? [];
$placementHistory = $academicData['placement_history'] ?? [];

if ($isStaffViewer) {
    $student['enquiry_snapshot_phone'] = null;
    $student['enquiry_snapshot_email'] = null;
    $student['parent_phone'] = null;
    $student['emergency_contact'] = null;
}

$assessmentAverage = isset($assessment['average_marks']) && $assessment['average_marks'] !== null
    ? (float) $assessment['average_marks']
    : null;
$mockAverage = isset($mock['mock_average']) && $mock['mock_average'] !== null
    ? (float) $mock['mock_average']
    : null;
$overallAverage = 0.0;
if (($assessment['average_marks'] ?? null) !== null && ($mock['mock_average'] ?? null) !== null) {
    $overallAverage = round((((float) $assessmentAverage) + ((float) $mockAverage)) / 2, 2);
} elseif (($assessment['average_marks'] ?? null) !== null) {
    $overallAverage = (float) $assessmentAverage;
} elseif (($mock['mock_average'] ?? null) !== null) {
    $overallAverage = (float) $mockAverage;
}

function studentProfileReportDate($value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : $value;
}

function studentProfileReportStatus($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    return ucwords(str_replace('_', ' ', $value));
}

function studentProfileReportPillClass($value): string
{
    $value = strtolower(trim((string) $value));
    if (in_array($value, ['completed', 'given', 'provided', 'paid', 'present', 'done', 'selected', 'yes'], true)) {
        return 'success';
    }
    if (in_array($value, ['partial', 'pending', 'scheduled', 'late', 'on_hold'], true)) {
        return 'warning';
    }
    if (in_array($value, ['unpaid', 'rejected', 'absent', 'no'], true)) {
        return 'danger';
    }
    return 'neutral';
}

function studentProfileReportMark($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return is_numeric($value) ? number_format((float) $value, 2) : htmlspecialchars((string) $value);
}

$studentDisplayName = trim((string) ($student['enquiry_snapshot_name'] ?? $student['student_name'] ?? '-'));
$studentRegistrationNo = trim((string) ($student['registration_no'] ?? '-'));
$studentProgramName = trim((string) ($student['program_name'] ?? '-'));
$studentJoinedOn = trim((string) ($student['joined_on'] ?? '-'));
$reportGeneratedAt = date('d M Y h:i A');
$reportLabel = $isCourseStudent ? 'Course Student Report' : 'Internship Student Report';
?>

<div class="student-report-page<?= $isPrintMode ? ' print-mode' : '' ?>">

    <?php if ($isPrintMode): ?>
        <script>
            document.body.classList.add('print-report-mode');
        </script>
        <div class="report-toolbar no-print">
            <a href="index.php?page=reports/student_profile&id=<?= (int) $id ?>" class="report-toolbar-btn">
                <i class="fas fa-arrow-left"></i> Back to View
            </a>
            <button type="button" class="report-toolbar-btn primary" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    <?php endif; ?>

    <?php if ($isPrintMode): ?>
        <div class="print-brand">
            <img src="assets/images/logo.png" alt="Company Logo" class="print-brand-logo">
            <div class="print-brand-meta">
                <div class="print-brand-title"><?= htmlspecialchars(APP_NAME) ?></div>
                <div class="print-brand-sub">Student Detail Report</div>
            </div>
        </div>
        <div class="print-report-hero">
            <div class="print-report-kicker">Official Report</div>
            <div class="print-report-title"><?= htmlspecialchars($reportLabel) ?></div>
            <p class="print-report-copy">
                This report summarizes the student's profile, academic progress, attendance, and administrative status in a print-ready format for records and review.
            </p>
            <div class="print-report-meta">
                <div class="print-report-meta-card">
                    <div class="print-report-meta-label">Student</div>
                    <div class="print-report-meta-value"><?= htmlspecialchars($studentDisplayName) ?></div>
                </div>
                <div class="print-report-meta-card">
                    <div class="print-report-meta-label">Registration No</div>
                    <div class="print-report-meta-value"><?= htmlspecialchars($studentRegistrationNo) ?></div>
                </div>
                <div class="print-report-meta-card">
                    <div class="print-report-meta-label">Program</div>
                    <div class="print-report-meta-value"><?= htmlspecialchars($studentProgramName) ?></div>
                </div>
                <div class="print-report-meta-card">
                    <div class="print-report-meta-label">Generated On</div>
                    <div class="print-report-meta-value"><?= htmlspecialchars($reportGeneratedAt) ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- HEADER -->

    <div class="student-header">

        <div class="student-header-top">
            <div class="student-info">
                <h2><?= htmlspecialchars((string) ($student['enquiry_snapshot_name'] ?? '-')) ?></h2>
                <div class="student-subline">
                    <?= $isStaffViewer ? 'Student progress summary' : 'Student progress summary and payment overview' ?>
                </div>
                <div class="student-meta">
                    <span class="meta-chip"><i class="fas fa-graduation-cap"></i>
                        <?= htmlspecialchars((string) ($student['program_name'] ?? '-')) ?></span>
                    <span class="meta-chip"><i class="fas fa-id-badge"></i>
                        <?= htmlspecialchars((string) ($student['registration_no'] ?? '-')) ?></span>
                    <span class="meta-chip"><i class="fas fa-calendar-alt"></i> Joined
                        <?= htmlspecialchars((string) ($student['joined_on'] ?? '-')) ?></span>
                </div>
            </div>
            <?php if (!$isStaffViewer): ?>
                <span class="status-badge status-<?= htmlspecialchars((string) ($student['payment_status'] ?? 'unpaid')) ?>">
                    <?= ucfirst((string) ($student['payment_status'] ?? 'unpaid')) ?>
                </span>
            <?php endif; ?>
        </div>

    </div>

    <!-- SUMMARY CARDS -->

    <div class="summary-grid">

        <?php if (!$isStaffViewer): ?>
            <div class="summary-card">
                <div class="summary-title">Total Fee</div>
                <div class="summary-value"><?= inr_symbol() ?> <?= number_format((float) $student['total_fee'], 2) ?></div>
                <div class="summary-note"><?= $isCourseStudent ? 'Overall course fee' : 'Overall internship fee' ?></div>
            </div>

            <div class="summary-card">
                <div class="summary-title">Paid</div>
                <div class="summary-value"><?= inr_symbol() ?> <?= number_format((float) $student['paid_amount'], 2) ?></div>
                <div class="summary-note">Amount collected so far</div>
            </div>

            <div class="summary-card">
                <div class="summary-title">Balance</div>
                <div class="summary-value"><?= inr_symbol() ?> <?= number_format((float) $student['balance_amount'], 2) ?></div>
                <div class="summary-note">Pending collection amount</div>
            </div>
        <?php endif; ?>

        <?php if ($isCourseStudent): ?>
            <div class="summary-card">
                <div class="summary-title">Attendance %</div>
                <div class="summary-value"><?= number_format((float) ($attendanceSummary['attendance_percent'] ?? 0), 2) ?>%
                </div>
                <div class="summary-note">Based on recorded attendance</div>
            </div>
            <?php if ($isStaffViewer): ?>
                <div class="summary-card">
                    <div class="summary-title">Test Marks</div>
                    <div class="summary-value"><?= $assessmentAverage !== null ? number_format($assessmentAverage, 2) : '-' ?>
                    </div>
                    <div class="summary-note">
                        T1: <?= studentProfileReportMark($assessment['assessment_1'] ?? null) ?>
                        | T2: <?= studentProfileReportMark($assessment['assessment_2'] ?? null) ?>
                        | T3: <?= studentProfileReportMark($assessment['assessment_3'] ?? null) ?>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-title">Mock Assessment</div>
                    <div class="summary-value"><?= $mockAverage !== null ? number_format($mockAverage, 2) : '-' ?>
                    </div>
                    <div class="summary-note">
                        Theory: <?= studentProfileReportMark($mock['theoretical_marks'] ?? null) ?>
                        | Machine: <?= studentProfileReportMark($mock['machine_task_marks'] ?? null) ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="summary-card">
                <div class="summary-title">Internship Days</div>
                <div class="summary-value"><?= htmlspecialchars($student['internship_days'] ?? '-') ?></div>
                <div class="summary-note">Configured internship duration</div>
            </div>
        <?php endif; ?>

    </div>

    <!-- TABS -->

    <div class="profile-tabs">

        <button class="tab-btn active" data-tab="profile">Profile</button>
        <?php if (!$isStaffViewer): ?>
            <button class="tab-btn" data-tab="payments">Payments</button>
        <?php endif; ?>
        <?php if ($isCourseStudent): ?>
            <button class="tab-btn" data-tab="progress">Course Progress</button>
            <button class="tab-btn" data-tab="attendance">Attendance</button>
            <button class="tab-btn" data-tab="placement">Placement</button>
        <?php else: ?>
            <button class="tab-btn" data-tab="internship">Internship</button>
        <?php endif; ?>

    </div>

    <!-- PROFILE TAB -->

    <div class="tab-content active" id="profile" data-report-section-title="Profile Summary">

        <div class="tab-panel">
            <h3 class="panel-title">Student Profile</h3>
            <div class="profile-grid">

                <div class="profile-item">
                    <div class="profile-label">Phone</div>
                    <div class="profile-value">
                        <?= htmlspecialchars(visibleStudentContactValue($student['enquiry_snapshot_phone'] ?? '-')) ?>
                    </div>
                </div>

                <div class="profile-item">
                    <div class="profile-label">Email</div>
                    <div class="profile-value">
                        <?= htmlspecialchars(visibleStudentContactValue($student['enquiry_snapshot_email'] ?? '-')) ?>
                    </div>
                </div>

                <div class="profile-item">
                    <div class="profile-label">Batch</div>
                    <div class="profile-value"><?= htmlspecialchars((string) ($student['batch_name'] ?? '-')) ?></div>
                </div>

                <div class="profile-item">
                    <div class="profile-label">Joined On</div>
                    <div class="profile-value"><?= htmlspecialchars((string) ($student['joined_on'] ?? '-')) ?></div>
                </div>

                <div class="profile-item">
                    <div class="profile-label">Program</div>
                    <div class="profile-value"><?= htmlspecialchars((string) ($student['program_name'] ?? '-')) ?></div>
                </div>

                <div class="profile-item">
                    <div class="profile-label">Registration No</div>
                    <div class="profile-value"><?= htmlspecialchars((string) ($student['registration_no'] ?? '-')) ?>
                    </div>
                </div>

                <?php if ($isCourseStudent): ?>
                    <div class="profile-item">
                        <div class="profile-label">Assigned Staff</div>
                        <div class="profile-value"><?= htmlspecialchars($student['assigned_staff_name'] ?? '-') ?></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Qualification</div>
                        <div class="profile-value"><?= htmlspecialchars($student['qualification'] ?? '-') ?></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">College</div>
                        <div class="profile-value"><?= htmlspecialchars($student['college_name'] ?? '-') ?></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Parent</div>
                        <div class="profile-value"><?= htmlspecialchars($student['parent_name'] ?? '-') ?></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Parent Phone</div>
                        <div class="profile-value">
                            <?= htmlspecialchars(visibleStudentContactValue($student['parent_phone'] ?? '-')) ?></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Address</div>
                        <div class="profile-value"><?= htmlspecialchars($student['address'] ?? '-') ?></div>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- PAYMENTS TAB -->
    <?php if (!$isStaffViewer): ?>
        <div class="tab-content" id="payments" data-report-section-title="Payment Ledger">

            <div class="tab-panel">
                <h3 class="panel-title">Payment History</h3>
                <div class="payment-table-wrap">
                    <table class="report-table no-mobile-cards">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Mode</th>
                                <th>Collected By</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (!$payments): ?>
                                <tr>
                                    <td colspan="4" class="empty-state">No payments recorded for this student.</td>
                                </tr>
                            <?php else: ?>

                                <?php foreach ($payments as $p): ?>

                                    <tr>

                                        <td><?= htmlspecialchars($p['payment_date']) ?></td>

                                        <td><?= inr_symbol() ?> <?= number_format((float) $p['amount'], 2) ?></td>

                                        <td><?= htmlspecialchars($p['payment_mode']) ?></td>

                                        <td><?= htmlspecialchars($p['collected_by_name'] ?? 'System') ?></td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>
                    </table>

                </div>
            </div>

        </div>
    <?php endif; ?>

    <?php if ($isCourseStudent): ?>
        <div class="tab-content" id="progress" data-report-section-title="Academic Progress">

            <div class="tab-panel">
                <h3 class="panel-title">Course Progress Overview</h3>
                <div class="section-stack">
                    <div class="mini-summary-grid">
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">Assessment Avg</div>
                            <div class="mini-summary-value">
                                <?= $assessmentAverage !== null ? number_format($assessmentAverage, 2) : '-' ?>
                            </div>
                        </div>
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">Mock Avg</div>
                            <div class="mini-summary-value">
                                <?= $mockAverage !== null ? number_format($mockAverage, 2) : '-' ?></div>
                        </div>
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">Overall Avg</div>
                            <div class="mini-summary-value">
                                <?= $overallAverage > 0 ? number_format($overallAverage, 2) : '-' ?></div>
                        </div>
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">HR Status</div>
                            <div class="mini-summary-value">
                                <?= studentProfileReportStatus($hr['interview_status'] ?? 'pending') ?></div>
                        </div>
                    </div>

                    <div class="profile-grid">
                        <div class="profile-item">
                            <div class="profile-label">Assessment Status</div>
                            <div class="profile-value">
                                <span
                                    class="value-pill <?= isset($assessment['average_marks']) && $assessment['average_marks'] !== null ? 'success' : 'neutral' ?>">
                                    <?= isset($assessment['average_marks']) && $assessment['average_marks'] !== null ? 'Assessment Completed' : 'Assessment Pending' ?>
                                </span>
                                <div class="value-subnote">Assessment average is shown above in the course summary.</div>
                            </div>
                        </div>

                        <div class="profile-item">
                            <div class="profile-label">Mock Interview Status</div>
                            <div class="profile-value">
                                <span
                                    class="value-pill <?= isset($mock['mock_average']) && $mock['mock_average'] !== null ? 'success' : 'neutral' ?>">
                                    <?= isset($mock['mock_average']) && $mock['mock_average'] !== null ? 'Mock Completed' : 'Mock Pending' ?>
                                </span>
                                <div class="value-subnote">Mock average is shown above in the course summary.</div>
                            </div>
                        </div>

                        <div class="profile-item">
                            <div class="profile-label">Mock Workflow</div>
                            <div class="profile-value">
                                <span
                                    class="value-pill <?= studentProfileReportPillClass($mock['workflow_status'] ?? '') ?>"><?= studentProfileReportStatus($mock['workflow_status'] ?? '-') ?></span>
                                <div class="value-subnote">
                                    <?= !empty($mock['completed_at']) ? 'Completed on ' . htmlspecialchars(studentProfileReportDate($mock['completed_at'])) : 'Date not available' ?>
                                </div>
                            </div>
                        </div>

                        <div class="profile-item">
                            <div class="profile-label">HR Status</div>
                            <div class="profile-value">
                                <span
                                    class="value-pill <?= studentProfileReportPillClass($hr['interview_status'] ?? '') ?>"><?= studentProfileReportStatus($hr['interview_status'] ?? 'pending') ?></span>
                                <div class="value-subnote">
                                    <?= !empty($hr['sent_to_hr_at']) ? 'Sent to HR on ' . htmlspecialchars(studentProfileReportDate($hr['sent_to_hr_at'])) : 'Not yet sent to HR' ?>
                                </div>
                            </div>
                        </div>

                        <div class="profile-item">
                            <div class="profile-label">Placement Movement</div>
                            <div class="profile-value">
                                <span class="value-pill <?= !empty($hr['sent_to_hr_at']) ? 'success' : 'neutral' ?>">
                                    <?= !empty($hr['sent_to_hr_at']) ? 'Moved to HR' : 'Not Moved to HR' ?>
                                </span>
                                <div class="value-subnote">
                                    <?= !empty($hr['sent_to_hr_at']) ? 'Sent on ' . htmlspecialchars(studentProfileReportDate($hr['sent_to_hr_at'])) : 'No HR movement yet' ?>
                                </div>
                            </div>
                        </div>

                        <div class="profile-item">
                            <div class="profile-label">Academic Snapshot</div>
                            <div class="profile-value">
                                Assessment Avg:
                                <?= $assessmentAverage !== null ? number_format($assessmentAverage, 2) : '-' ?><br>
                                Mock Avg: <?= $mockAverage !== null ? number_format($mockAverage, 2) : '-' ?><br>
                                Overall Avg: <?= $overallAverage > 0 ? number_format($overallAverage, 2) : '-' ?>
                                <div class="value-subnote">Summary plus detailed test and mock marks are shown above.</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div class="tab-content" id="attendance" data-report-section-title="Attendance Register">

            <div class="tab-panel">
                <h3 class="panel-title">Attendance Overview</h3>
                <div class="section-stack">

                    <div class="mini-summary-grid">
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">Attendance %</div>
                            <div class="mini-summary-value">
                                <?= number_format((float) ($attendanceSummary['attendance_percent'] ?? 0), 2) ?>%</div>
                        </div>
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">Present Days</div>
                            <div class="mini-summary-value"><?= (int) ($attendanceSummary['present_days'] ?? 0) ?></div>
                        </div>
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">Absent Days</div>
                            <div class="mini-summary-value"><?= (int) ($attendanceSummary['absent_days'] ?? 0) ?></div>
                        </div>
                        <div class="mini-summary-card">
                            <div class="mini-summary-label">Tracking Start</div>
                            <div class="mini-summary-value">
                                <?= htmlspecialchars(studentProfileReportDate($attendanceSummary['start_date'] ?? '')) ?>
                            </div>
                        </div>
                    </div>

                    <div class="profile-grid">
                        <div class="profile-item">
                            <div class="profile-label">Recorded Entries</div>
                            <div class="profile-value"><?= (int) (count($attendanceRows)) ?></div>
                        </div>

                        <div class="profile-item">
                            <div class="profile-label">Attendance Status</div>
                            <div class="profile-value">
                                <span
                                    class="value-pill <?= ((float) ($attendanceSummary['attendance_percent'] ?? 0) >= 75) ? 'success' : (((float) ($attendanceSummary['attendance_percent'] ?? 0) >= 50) ? 'warning' : 'danger') ?>">
                                    <?= number_format((float) ($attendanceSummary['attendance_percent'] ?? 0), 2) ?>%
                                </span>
                                <div class="value-subnote">Overall attendance percentage only. Daily attendance rows are
                                    hidden in this report.</div>
                            </div>
                        </div>

                        <div class="profile-item">
                            <div class="profile-label">Summary</div>
                            <div class="profile-value">
                                P: <?= (int) ($attendanceSummary['present_days'] ?? 0) ?> | A:
                                <?= (int) ($attendanceSummary['absent_days'] ?? 0) ?>
                                <div class="value-subnote">Late entries are counted separately where available.</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <div class="tab-content" id="placement" data-report-section-title="Placement And HR Review">

            <div class="tab-panel">
                <?php if ($isStaffViewer): ?>
                    <h3 class="panel-title">Placement Status</h3>
                    <div class="profile-grid">
                        <div class="profile-item">
                            <div class="profile-label">Moved To HR</div>
                            <div class="profile-value">
                                <span class="value-pill <?= !empty($hr['sent_to_hr_at']) ? 'success' : 'neutral' ?>">
                                    <?= !empty($hr['sent_to_hr_at']) ? 'Moved to HR' : 'Not Moved to HR' ?>
                                </span>
                                <div class="value-subnote">
                                    <?= !empty($hr['sent_to_hr_at']) ? 'Sent on ' . htmlspecialchars(studentProfileReportDate($hr['sent_to_hr_at'])) : 'No HR movement yet' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <h3 class="panel-title">Placement Interview History</h3>
                    <div class="payment-table-wrap">
                        <table class="report-table no-mobile-cards">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Date</th>
                                    <th>Mode</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$placementHistory): ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">No placement interviews recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($placementHistory as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($row['company_name'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars(trim(studentProfileReportDate($row['interview_date'] ?? '') . ' ' . (string) ($row['interview_time'] ?? ''))) ?>
                                            </td>
                                            <td><?= htmlspecialchars((string) ($row['interview_mode'] ?? '-')) ?></td>
                                            <td><span
                                                    class="value-pill <?= studentProfileReportPillClass($row['status'] ?? '') ?>"><?= studentProfileReportStatus($row['status'] ?? '-') ?></span>
                                            </td>
                                            <td><?= nl2br(htmlspecialchars((string) ($row['remarks'] ?? '-'))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    <?php endif; ?>

    <!-- INTERNSHIP TAB -->

    <?php if ($isInternshipStudent): ?>
        <div class="tab-content" id="internship" data-report-section-title="Internship Summary">

            <div class="tab-panel">
                <h3 class="panel-title">Internship Overview</h3>
                <div class="profile-grid">

                    <div class="profile-item">
                        <div class="profile-label">Start Date</div>
                        <div class="profile-value"><?= htmlspecialchars($student['internship_start_date'] ?? '-') ?></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">End Date</div>
                        <div class="profile-value"><?= htmlspecialchars($student['internship_end_date'] ?? '-') ?></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Completion Status</div>
                        <div class="profile-value">
                            <span
                                class="value-pill <?= ($student['internship_completion_status'] ?? '') === 'completed' ? 'success' : 'warning' ?>"><?= htmlspecialchars(ucfirst((string) ($student['internship_completion_status'] ?? '-'))) ?></span>
                            <div class="value-subnote">
                                <?= !empty($student['internship_end_date']) ? 'Date: ' . htmlspecialchars($student['internship_end_date']) : 'Date not available' ?>
                            </div>
                        </div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Certificate Status</div>
                        <div class="profile-value">
                            <span
                                class="value-pill <?= ($student['internship_certificate_status'] ?? '') === 'given' ? 'success' : 'neutral' ?>"><?= htmlspecialchars(ucfirst((string) ($student['internship_certificate_status'] ?? '-'))) ?></span>
                            <div class="value-subnote">
                                <?= !empty($student['internship_certificate_issued_at']) ? 'Date: ' . htmlspecialchars($student['internship_certificate_issued_at']) : 'Date not available' ?>
                            </div>
                        </div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Certificate Issued</div>
                        <div class="profile-value"><span
                                class="value-pill <?= !empty($student['internship_certificate_issued_at']) ? 'success' : 'neutral' ?>">
                                <?= $student['internship_certificate_issued_at']
                                    ? htmlspecialchars($student['internship_certificate_issued_at'])
                                    : 'Not Issued' ?>
                            </span></div>
                    </div>

                    <div class="profile-item">
                        <div class="profile-label">Report Status</div>
                        <div class="profile-value">
                            <span
                                class="value-pill <?= ($student['internship_report_status'] ?? '') === 'provided' ? 'success' : 'neutral' ?>"><?= htmlspecialchars(ucfirst((string) ($student['internship_report_status'] ?? '-'))) ?></span>
                            <div class="value-subnote">
                                <?= !empty($student['internship_report_issued_at']) ? 'Date: ' . htmlspecialchars($student['internship_report_issued_at']) : 'Date not available' ?>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endif; ?>

        </div>

    </div>

</div>

<script>

    document.querySelectorAll('.tab-btn').forEach(btn => {

        btn.addEventListener('click', function () {

            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            this.classList.add('active');

            document.getElementById(this.dataset.tab).classList.add('active');

        });

    });

</script>



