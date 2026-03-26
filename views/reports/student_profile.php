<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_student_report_helpers.php';

$id = (int)($_GET['id'] ?? 0);
$isPrintMode = (int)($_GET['print'] ?? 0) === 1;

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
    $courseStudent = studentReportFetchBaseStudent($pdo, $id, 0, 0, true, '');
    if ($courseStudent) {
        $student = array_merge($student, $courseStudent);
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

function studentProfileReportDate($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $time = strtotime($value);
    return $time ? date('Y-m-d', $time) : $value;
}

function studentProfileReportStatus($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    return ucwords(str_replace('_', ' ', $value));
}

function studentProfileReportPillClass($value): string
{
    $value = strtolower(trim((string)$value));
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
?>

<style>
.student-report-page{
max-width:1360px;
margin:0 auto;
padding:14px 10px 28px;
}

.report-toolbar{
display:flex;
align-items:center;
justify-content:flex-end;
gap:10px;
margin-bottom:14px;
}

.print-brand{
display:none;
align-items:center;
justify-content:space-between;
gap:18px;
margin-bottom:16px;
padding:0 2px;
}

.print-brand-logo{
height:56px;
width:auto;
object-fit:contain;
}

.print-brand-meta{
text-align:right;
}

.print-brand-title{
font-size:18px;
font-weight:800;
color:#1f2940;
}

.print-brand-sub{
margin-top:4px;
font-size:12px;
font-weight:600;
color:#8b94a7;
}

.report-toolbar-btn{
display:inline-flex;
align-items:center;
justify-content:center;
gap:8px;
padding:10px 14px;
border-radius:12px;
border:1px solid #f0d1df;
background:#fff;
color:#7b455f;
font-size:13px;
font-weight:700;
text-decoration:none;
cursor:pointer;
}

.report-toolbar-btn.primary{
background:#e91e63;
border-color:#e91e63;
color:#fff;
box-shadow:0 8px 20px rgba(233,30,99,.18);
}

.student-header{
background:linear-gradient(180deg, #ffffff 0%, #fff8fb 100%);
padding:24px 26px;
border-radius:18px;
border:1px solid #f3d9e5;
box-shadow:0 10px 28px rgba(15,23,42,.05);
margin-bottom:18px;
}

.student-header-top{
display:flex;
align-items:flex-start;
justify-content:space-between;
gap:18px;
flex-wrap:wrap;
}

.student-info h2{
margin:0;
font-size:30px;
font-weight:800;
color:#1f2940;
}

.student-subline{
margin-top:8px;
font-size:14px;
color:#64748b;
}

.student-meta{
margin-top:14px;
display:flex;
gap:10px;
flex-wrap:wrap;
}

.meta-chip{
display:inline-flex;
align-items:center;
gap:8px;
padding:9px 12px;
font-size:13px;
font-weight:600;
border-radius:999px;
background:#fff;
border:1px solid #f1d6e3;
color:#4b5563;
}

.meta-chip i{
color:#e91e63;
}

.status-badge{
display:inline-flex;
align-items:center;
gap:7px;
padding:7px 12px;
border-radius:999px;
font-size:12px;
font-weight:700;
text-transform:capitalize;
}

.status-badge::before{
content:'';
width:8px;
height:8px;
border-radius:50%;
background:currentColor;
opacity:.85;
}

.status-paid{
background:#e8f8f1;
color:#1b9e77;
}

.status-unpaid{
background:#ffecec;
color:#e53935;
}

.status-partial{
background:#fff4e5;
color:#f57c00;
}

.summary-grid{
display:grid;
grid-template-columns:repeat(4, minmax(0, 1fr));
gap:16px;
margin-bottom:18px;
}

.summary-card{
background:#fff;
padding:18px 18px 16px;
border-radius:16px;
border:1px solid #f1dce5;
box-shadow:0 8px 22px rgba(15,23,42,.04);
}

.summary-title{
font-size:12px;
font-weight:700;
letter-spacing:.04em;
text-transform:uppercase;
color:#8a94a6;
margin-bottom:8px;
}

.summary-value{
font-size:31px;
line-height:1;
font-weight:800;
color:#1f2940;
}

.summary-note{
margin-top:8px;
font-size:12px;
color:#94a3b8;
}

.profile-tabs{
display:flex;
gap:10px;
margin-bottom:16px;
flex-wrap:wrap;
}

.tab-btn{
background:#fff;
border:1px solid #f0d1df;
padding:10px 16px;
border-radius:999px;
cursor:pointer;
font-size:13px;
font-weight:700;
color:#7b455f;
transition:.18s ease;
}

.tab-btn.active{
background:#e91e63;
border-color:#e91e63;
color:#fff;
box-shadow:0 8px 20px rgba(233,30,99,.2);
}

.tab-content{
display:none;
}

.tab-content.active{
display:block;
}

.student-report-page.print-mode .profile-tabs{
display:none;
}

.student-report-page.print-mode .tab-content{
display:block;
margin-bottom:16px;
}

body.print-report-mode{
background:#fff !important;
}

body.print-report-mode .sidebar,
body.print-report-mode .topbar{
display:none !important;
}

body.print-report-mode .content,
body.print-report-mode .main-content{
margin-left:0 !important;
width:100% !important;
padding:0 !important;
}

body.print-report-mode .print-brand{
display:flex;
}

.tab-panel{
background:#fff;
border:1px solid #f1dce5;
border-radius:18px;
padding:20px;
box-shadow:0 10px 24px rgba(15,23,42,.04);
}

.panel-title{
margin:0 0 16px;
font-size:18px;
font-weight:800;
color:#1f2940;
}

.section-stack{
display:flex;
flex-direction:column;
gap:16px;
}

.profile-grid{
display:grid;
grid-template-columns:repeat(3, minmax(0, 1fr));
gap:14px;
}

.profile-item{
background:linear-gradient(180deg, #ffffff 0%, #fff9fc 100%);
padding:14px;
border-radius:14px;
border:1px solid #f2dde7;
min-height:84px;
}

.profile-label{
font-size:12px;
font-weight:700;
letter-spacing:.03em;
text-transform:uppercase;
color:#8b94a7;
margin-bottom:6px;
}

.profile-value{
font-size:15px;
font-weight:700;
color:#1f2940;
word-break:break-word;
}

.value-subnote{
margin-top:8px;
font-size:12px;
font-weight:600;
color:#8b94a7;
}

.value-pill{
display:inline-flex;
align-items:center;
gap:6px;
padding:6px 10px;
border-radius:999px;
font-size:12px;
font-weight:700;
}

.value-pill.success{
background:#e8f8f1;
color:#1b9e77;
}

.value-pill.warning{
background:#fff4e5;
color:#f57c00;
}

.value-pill.danger{
background:#ffecec;
color:#e53935;
}

.value-pill.neutral{
background:#f6f7fb;
color:#64748b;
}

.payment-table-wrap{
overflow-x:auto;
}

.report-table{
width:100%;
border-collapse:separate;
border-spacing:0;
background:#fff;
border:1px solid #f0dce5;
border-radius:14px;
overflow:hidden;
}

.report-table th,
.report-table td{
border-right:1px solid #f0dce5;
border-bottom:1px solid #f0dce5;
padding:12px 14px;
font-size:13px;
text-align:left;
}

.report-table th:last-child,
.report-table td:last-child{
border-right:none;
}

.report-table tr:last-child td{
border-bottom:none;
}

.report-table th{
background:#fff0f5;
color:#8d1246;
font-size:12px;
font-weight:800;
letter-spacing:.04em;
text-transform:uppercase;
}

.report-table td{
color:#445066;
background:#fff;
}

.report-table tbody tr:nth-child(even) td{
background:#fffafc;
}

.report-table tbody tr.attendance-absent td{
background:#fff6f6;
}

.report-table tbody tr.attendance-late td{
background:#fffaf0;
}

.mini-summary-grid{
display:grid;
grid-template-columns:repeat(4, minmax(0, 1fr));
gap:12px;
}

.mini-summary-card{
border:1px solid #f2dde7;
border-radius:14px;
padding:14px;
background:linear-gradient(180deg, #ffffff 0%, #fff9fc 100%);
}

.mini-summary-label{
font-size:12px;
font-weight:800;
letter-spacing:.04em;
text-transform:uppercase;
color:#8b94a7;
}

.mini-summary-value{
margin-top:8px;
font-size:22px;
font-weight:800;
color:#1f2940;
}

.empty-state{
padding:20px;
text-align:center;
font-size:14px;
font-weight:600;
color:#94a3b8;
}

@media (max-width: 1100px){
.summary-grid{
grid-template-columns:repeat(2, minmax(0, 1fr));
}

.mini-summary-grid,
.profile-grid{
grid-template-columns:repeat(2, minmax(0, 1fr));
}
}

@media (max-width: 700px){
.student-header{
padding:18px;
}

.student-info h2{
font-size:24px;
}

.summary-grid,
.mini-summary-grid,
.profile-grid{
grid-template-columns:1fr;
}

.summary-value{
font-size:26px;
}
}

@media print{
body{
background:#fff !important;
height:auto !important;
overflow:visible !important;
}

html{
background:#fff !important;
height:auto !important;
overflow:visible !important;
}

.wrapper,
.main-content,
.main-panel,
.content-wrapper,
.content,
.page-content,
.container,
.container-fluid{
height:auto !important;
min-height:0 !important;
max-height:none !important;
overflow:visible !important;
}

.topbar,
.sidebar{
display:none !important;
}

.content,
.main-content{
margin-left:0 !important;
width:100% !important;
padding:0 !important;
}

.no-print{
display:none !important;
}

.student-report-page{
max-width:none;
padding:0;
}

.student-header,
.summary-card,
.profile-item{
box-shadow:none;
page-break-inside:avoid;
break-inside:avoid;
}

.tab-panel,
.payment-table-wrap,
.section-stack{
page-break-inside:auto !important;
break-inside:auto !important;
}

.tab-content{
display:block !important;
margin-bottom:16px;
}

.payment-table-wrap,
.report-table{
overflow:visible !important;
height:auto !important;
max-height:none !important;
}

.print-brand{
display:flex !important;
}
}
</style>


<div class="student-report-page<?= $isPrintMode ? ' print-mode' : '' ?>">

<?php if ($isPrintMode): ?>
<script>
document.body.classList.add('print-report-mode');
</script>
<div class="report-toolbar no-print">
<a href="index.php?page=reports/student_profile&id=<?= (int)$id ?>" class="report-toolbar-btn">
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
<?php endif; ?>

<!-- HEADER -->

<div class="student-header">

<div class="student-header-top">
<div class="student-info">
<h2><?= htmlspecialchars((string)($student['enquiry_snapshot_name'] ?? '-')) ?></h2>
<div class="student-subline">Student progress summary and payment overview</div>
<div class="student-meta">
<span class="meta-chip"><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars((string)($student['program_name'] ?? '-')) ?></span>
<span class="meta-chip"><i class="fas fa-id-badge"></i> <?= htmlspecialchars((string)($student['registration_no'] ?? '-')) ?></span>
<span class="meta-chip"><i class="fas fa-calendar-alt"></i> Joined <?= htmlspecialchars((string)($student['joined_on'] ?? '-')) ?></span>
</div>
</div>
<span class="status-badge status-<?= htmlspecialchars((string)($student['payment_status'] ?? 'unpaid')) ?>">
<?= ucfirst((string)($student['payment_status'] ?? 'unpaid')) ?>
</span>
</div>

</div>


<!-- SUMMARY CARDS -->

<div class="summary-grid">

<div class="summary-card">
<div class="summary-title">Total Fee</div>
<div class="summary-value">Rs <?= number_format((float)$student['total_fee'],2) ?></div>
<div class="summary-note"><?= $isCourseStudent ? 'Overall course fee' : 'Overall internship fee' ?></div>
</div>

<div class="summary-card">
<div class="summary-title">Paid</div>
<div class="summary-value">Rs <?= number_format((float)$student['paid_amount'],2) ?></div>
<div class="summary-note">Amount collected so far</div>
</div>

<div class="summary-card">
<div class="summary-title">Balance</div>
<div class="summary-value">Rs <?= number_format((float)$student['balance_amount'],2) ?></div>
<div class="summary-note">Pending collection amount</div>
</div>

<?php if ($isCourseStudent): ?>
<div class="summary-card">
<div class="summary-title">Attendance %</div>
<div class="summary-value"><?= number_format((float)($attendanceSummary['attendance_percent'] ?? 0), 2) ?>%</div>
<div class="summary-note">Based on recorded attendance</div>
</div>
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
<button class="tab-btn" data-tab="payments">Payments</button>
<?php if ($isCourseStudent): ?>
<button class="tab-btn" data-tab="progress">Course Progress</button>
<button class="tab-btn" data-tab="attendance">Attendance</button>
<button class="tab-btn" data-tab="placement">Placement</button>
<?php else: ?>
<button class="tab-btn" data-tab="internship">Internship</button>
<?php endif; ?>

</div>


<!-- PROFILE TAB -->

<div class="tab-content active" id="profile">

<div class="tab-panel">
<h3 class="panel-title">Student Profile</h3>
<div class="profile-grid">

<div class="profile-item">
<div class="profile-label">Phone</div>
<div class="profile-value"><?= htmlspecialchars((string)($student['enquiry_snapshot_phone'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Email</div>
<div class="profile-value"><?= htmlspecialchars($student['enquiry_snapshot_email'] ?? '-') ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Batch</div>
<div class="profile-value"><?= htmlspecialchars((string)($student['batch_name'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Joined On</div>
<div class="profile-value"><?= htmlspecialchars((string)($student['joined_on'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Program</div>
<div class="profile-value"><?= htmlspecialchars((string)($student['program_name'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Registration No</div>
<div class="profile-value"><?= htmlspecialchars((string)($student['registration_no'] ?? '-')) ?></div>
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
<div class="profile-value"><?= htmlspecialchars($student['parent_phone'] ?? '-') ?></div>
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

<div class="tab-content" id="payments">

<div class="tab-panel">
<h3 class="panel-title">Payment History</h3>
<div class="payment-table-wrap">
<table class="report-table">

<thead>
<tr>
<th>Date</th>
<th>Amount</th>
<th>Mode</th>
<th>Collected By</th>
</tr>
</thead>

<tbody>

<?php if(!$payments): ?>
<tr>
<td colspan="4" class="empty-state">No payments recorded for this student.</td>
</tr>
<?php else: ?>

<?php foreach($payments as $p): ?>

<tr>

<td><?= htmlspecialchars($p['payment_date']) ?></td>

<td>Rs <?= number_format((float)$p['amount'],2) ?></td>

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


<?php if ($isCourseStudent): ?>
<div class="tab-content" id="progress">

<div class="tab-panel">
<h3 class="panel-title">Course Progress Overview</h3>
<div class="section-stack">

<div class="mini-summary-grid">
<div class="mini-summary-card">
<div class="mini-summary-label">Assessment Avg</div>
<div class="mini-summary-value"><?= isset($assessment['average_marks']) ? number_format((float)$assessment['average_marks'], 2) : '-' ?></div>
</div>
<div class="mini-summary-card">
<div class="mini-summary-label">Mock Avg</div>
<div class="mini-summary-value"><?= isset($mock['mock_average']) ? number_format((float)$mock['mock_average'], 2) : '-' ?></div>
</div>
<div class="mini-summary-card">
<div class="mini-summary-label">Present Days</div>
<div class="mini-summary-value"><?= (int)($attendanceSummary['present_days'] ?? 0) ?></div>
</div>
<div class="mini-summary-card">
<div class="mini-summary-label">Absent Days</div>
<div class="mini-summary-value"><?= (int)($attendanceSummary['absent_days'] ?? 0) ?></div>
</div>
</div>

<div class="profile-grid">
<div class="profile-item">
<div class="profile-label">Assessment 1</div>
<div class="profile-value"><?= htmlspecialchars((string)($assessment['assessment_1'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Assessment 2</div>
<div class="profile-value"><?= htmlspecialchars((string)($assessment['assessment_2'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Assessment 3</div>
<div class="profile-value"><?= htmlspecialchars((string)($assessment['assessment_3'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Mock Theory Marks</div>
<div class="profile-value"><?= htmlspecialchars((string)($mock['theoretical_marks'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Mock Machine Task Marks</div>
<div class="profile-value"><?= htmlspecialchars((string)($mock['machine_task_marks'] ?? '-')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Mock Workflow</div>
<div class="profile-value">
<span class="value-pill <?= studentProfileReportPillClass($mock['workflow_status'] ?? '') ?>"><?= studentProfileReportStatus($mock['workflow_status'] ?? '-') ?></span>
<div class="value-subnote">
<?= !empty($mock['completed_at']) ? 'Completed on ' . htmlspecialchars(studentProfileReportDate($mock['completed_at'])) : 'Date not available' ?>
</div>
</div>
</div>

<div class="profile-item">
<div class="profile-label">HR Status</div>
<div class="profile-value">
<span class="value-pill <?= studentProfileReportPillClass($hr['interview_status'] ?? '') ?>"><?= studentProfileReportStatus($hr['interview_status'] ?? 'pending') ?></span>
<div class="value-subnote">
<?= !empty($hr['sent_to_hr_at']) ? 'Sent to HR on ' . htmlspecialchars(studentProfileReportDate($hr['sent_to_hr_at'])) : 'Not yet sent to HR' ?>
</div>
</div>
</div>

<div class="profile-item">
<div class="profile-label">Last Company</div>
<div class="profile-value"><?= htmlspecialchars($hr['company_name'] ?? '-') ?></div>
</div>

<div class="profile-item">
<div class="profile-label">Interview Date</div>
<div class="profile-value"><?= htmlspecialchars(studentProfileReportDate($hr['interview_date'] ?? '')) ?></div>
</div>

<div class="profile-item">
<div class="profile-label">HR Remarks</div>
<div class="profile-value"><?= htmlspecialchars($hr['rejection_reason'] ?? '-') ?></div>
</div>
</div>

</div>
</div>

</div>

<div class="tab-content" id="attendance">

<div class="tab-panel">
<h3 class="panel-title">Attendance & Topics Covered</h3>
<div class="section-stack">

<div class="mini-summary-grid">
<div class="mini-summary-card">
<div class="mini-summary-label">Attendance %</div>
<div class="mini-summary-value"><?= number_format((float)($attendanceSummary['attendance_percent'] ?? 0), 2) ?>%</div>
</div>
<div class="mini-summary-card">
<div class="mini-summary-label">Present Days</div>
<div class="mini-summary-value"><?= (int)($attendanceSummary['present_days'] ?? 0) ?></div>
</div>
<div class="mini-summary-card">
<div class="mini-summary-label">Absent Days</div>
<div class="mini-summary-value"><?= (int)($attendanceSummary['absent_days'] ?? 0) ?></div>
</div>
<div class="mini-summary-card">
<div class="mini-summary-label">Tracking Start</div>
<div class="mini-summary-value"><?= htmlspecialchars(studentProfileReportDate($attendanceSummary['start_date'] ?? '')) ?></div>
</div>
</div>

<div class="payment-table-wrap">
<table class="report-table">
<thead>
<tr>
<th>Date</th>
<th>Status</th>
<th>Topics Taught</th>
<th>Task Given</th>
<th>Informed</th>
<th>Reason</th>
<th>Informed By</th>
</tr>
</thead>
<tbody>
<?php if(!$attendanceRows): ?>
<tr>
<td colspan="7" class="empty-state">No attendance records available for this student.</td>
</tr>
<?php else: ?>
<?php foreach($attendanceRows as $row): ?>
<?php $rowStatus = strtolower((string)($row['status'] ?? '')); ?>
<tr class="<?= $rowStatus === 'absent' ? 'attendance-absent' : ($rowStatus === 'late' ? 'attendance-late' : '') ?>">
<td><?= htmlspecialchars(studentProfileReportDate($row['attendance_date'] ?? '')) ?></td>
<td>
<span class="value-pill <?= studentProfileReportPillClass($row['status'] ?? '') ?>"><?= htmlspecialchars((string)($row['status'] ?? '-')) ?></span>
</td>
<td><?= nl2br(htmlspecialchars((string)($row['topics_taught'] ?? '-'))) ?></td>
<td><?= nl2br(htmlspecialchars((string)($row['task_given'] ?? '-'))) ?></td>
<td><?= studentProfileReportStatus($row['absent_informed'] ?? '-') ?></td>
<td><?= nl2br(htmlspecialchars((string)($row['absent_reason'] ?? '-'))) ?></td>
<td><?= htmlspecialchars((string)($row['absent_informed_by'] ?? '-')) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

</div>
</div>

</div>

<div class="tab-content" id="placement">

<div class="tab-panel">
<h3 class="panel-title">Placement Interview History</h3>
<div class="payment-table-wrap">
<table class="report-table">
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
<?php if(!$placementHistory): ?>
<tr>
<td colspan="5" class="empty-state">No placement interviews recorded yet.</td>
</tr>
<?php else: ?>
<?php foreach($placementHistory as $row): ?>
<tr>
<td><?= htmlspecialchars((string)($row['company_name'] ?? '-')) ?></td>
<td><?= htmlspecialchars(trim(studentProfileReportDate($row['interview_date'] ?? '') . ' ' . (string)($row['interview_time'] ?? ''))) ?></td>
<td><?= htmlspecialchars((string)($row['interview_mode'] ?? '-')) ?></td>
<td><span class="value-pill <?= studentProfileReportPillClass($row['status'] ?? '') ?>"><?= studentProfileReportStatus($row['status'] ?? '-') ?></span></td>
<td><?= nl2br(htmlspecialchars((string)($row['remarks'] ?? '-'))) ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
<?php endif; ?>


<!-- INTERNSHIP TAB -->

<?php if ($isInternshipStudent): ?>
<div class="tab-content" id="internship">

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
<span class="value-pill <?= ($student['internship_completion_status'] ?? '') === 'completed' ? 'success' : 'warning' ?>"><?= ucfirst($student['internship_completion_status']) ?></span>
<div class="value-subnote">
<?= !empty($student['internship_end_date']) ? 'Date: ' . htmlspecialchars($student['internship_end_date']) : 'Date not available' ?>
</div>
</div>
</div>

<div class="profile-item">
<div class="profile-label">Certificate Status</div>
<div class="profile-value">
<span class="value-pill <?= ($student['internship_certificate_status'] ?? '') === 'given' ? 'success' : 'neutral' ?>"><?= ucfirst($student['internship_certificate_status']) ?></span>
<div class="value-subnote">
<?= !empty($student['internship_certificate_issued_at']) ? 'Date: ' . htmlspecialchars($student['internship_certificate_issued_at']) : 'Date not available' ?>
</div>
</div>
</div>

<div class="profile-item">
<div class="profile-label">Certificate Issued</div>
<div class="profile-value"><span class="value-pill <?= !empty($student['internship_certificate_issued_at']) ? 'success' : 'neutral' ?>">
<?= $student['internship_certificate_issued_at'] 
? htmlspecialchars($student['internship_certificate_issued_at']) 
: 'Not Issued' ?>
</span></div>
</div>

<div class="profile-item">
<div class="profile-label">Report Status</div>
<div class="profile-value">
<span class="value-pill <?= ($student['internship_report_status'] ?? '') === 'provided' ? 'success' : 'neutral' ?>"><?= ucfirst($student['internship_report_status']) ?></span>
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

document.querySelectorAll('.tab-btn').forEach(btn=>{

btn.addEventListener('click',function(){

document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));

this.classList.add('active');

document.getElementById(this.dataset.tab).classList.add('active');

});

});

</script>
