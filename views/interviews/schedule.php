<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('interviews/schedule');
}

require_once __DIR__ . '/_workflow_helpers.php';

if (!in_array(($_SESSION['role_name'] ?? ''), ['HR', 'Super Admin'], true)) {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Poppins,sans-serif'>
            <h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2>
            <p style='margin:0;color:#666'>This page is available only for HR users.</p>
          </div>";
    return;
}

function renderHrStudentDetailHtml(array $detail): string
{
    $student = $detail['student'];
    $followups = $detail['followups'];
    $payments = $detail['payments'];
    $placementHistory = $detail['placement_history'];

    ob_start();
    ?>
    <div class="hrd-wrap">
        <div class="hrd-grid">
            <div class="hrd-card">
                <div class="hrd-title">Student</div>
                <div class="hrd-value"><?= interviewWorkflowH($student['student_name'] ?: $student['enquiry_snapshot_name'] ?: '-') ?></div>
                <div class="hrd-meta"><?= interviewWorkflowH($student['registration_no'] ?: '-') ?> | <?= interviewWorkflowH($student['program_name'] ?: '-') ?></div>
                <div class="hrd-meta"><?= interviewWorkflowH(visibleStudentContactPair($student['enquiry_snapshot_phone'] ?? '', $student['enquiry_snapshot_email'] ?? '', '-')) ?></div>
            </div>
            <div class="hrd-card">
                <div class="hrd-title">Course Status</div>
                <div class="hrd-value"><?= interviewWorkflowH(ucfirst((string) ($student['registration_status'] ?? '-'))) ?></div>
                <div class="hrd-meta">Joined: <?= interviewWorkflowH($student['joined_on'] ?: '-') ?></div>
                <div class="hrd-meta">Batch: <?= interviewWorkflowH($student['batch_name'] ?: '-') ?></div>
            </div>
            <div class="hrd-card">
                <div class="hrd-title">Scores</div>
                <div class="hrd-meta">Assessment Avg: <?= interviewWorkflowH($student['assessment_average'] !== null ? number_format((float) $student['assessment_average'], 2, '.', '') : '-') ?></div>
                <div class="hrd-meta">Mock Avg: <?= interviewWorkflowH($student['mock_average'] !== null ? number_format((float) $student['mock_average'], 2, '.', '') : '-') ?></div>
                <div class="hrd-meta">Mock Marks: <?= interviewWorkflowH($student['theoretical_marks'] !== null ? $student['theoretical_marks'] : '-') ?> / <?= interviewWorkflowH($student['machine_task_marks'] !== null ? $student['machine_task_marks'] : '-') ?></div>
            </div>
            <div class="hrd-card">
                <div class="hrd-title">HR Pipeline</div>
                <div class="hrd-value"><?= interviewWorkflowH(ucwords(str_replace('_', ' ', (string) ($student['interview_status'] ?? 'pending')))) ?></div>
                <div class="hrd-meta">Sent To HR: <?= interviewWorkflowH($student['sent_to_hr_at'] ?: '-') ?></div>
                <div class="hrd-meta">Sent By: <?= interviewWorkflowH($student['hr_sent_by_name'] ?: '-') ?></div>
            </div>
        </div>

        <div class="hrd-section">
            <div class="hrd-section-title">Profile Details</div>
            <div class="hrd-grid hrd-grid-two">
                <div class="hrd-card"><div class="hrd-title">Gender</div><div class="hrd-meta"><?= interviewWorkflowH($student['gender'] ?: '-') ?></div></div>
                <div class="hrd-card"><div class="hrd-title">DOB</div><div class="hrd-meta"><?= interviewWorkflowH($student['dob'] ?: '-') ?></div></div>
                <div class="hrd-card"><div class="hrd-title">Qualification</div><div class="hrd-meta"><?= interviewWorkflowH($student['qualification'] ?: '-') ?></div></div>
                <div class="hrd-card"><div class="hrd-title">College</div><div class="hrd-meta"><?= interviewWorkflowH($student['college_name'] ?: '-') ?></div></div>
                <div class="hrd-card"><div class="hrd-title">Parent</div><div class="hrd-meta"><?= interviewWorkflowH($student['parent_name'] ?: '-') ?> | <?= interviewWorkflowH(visibleStudentContactValue($student['parent_phone'] ?? '-')) ?></div></div>
                <div class="hrd-card"><div class="hrd-title">Emergency Contact</div><div class="hrd-meta"><?= interviewWorkflowH(visibleStudentContactValue($student['emergency_contact'] ?? '-')) ?></div></div>
                <div class="hrd-card hrd-card-full"><div class="hrd-title">Address</div><div class="hrd-meta"><?= nl2br(interviewWorkflowH($student['address'] ?: '-')) ?></div></div>
            </div>
        </div>

        <div class="hrd-section">
            <div class="hrd-section-title">Fee Summary</div>
            <div class="hrd-grid hrd-grid-two">
                <div class="hrd-card"><div class="hrd-title">Final Fee</div><div class="hrd-value">Rs <?= interviewWorkflowH(number_format((float) ($student['final_fee'] ?? 0), 2)) ?></div></div>
                <div class="hrd-card"><div class="hrd-title">Paid</div><div class="hrd-value">Rs <?= interviewWorkflowH(number_format((float) ($student['paid_amount'] ?? 0), 2)) ?></div></div>
                <div class="hrd-card"><div class="hrd-title">Balance</div><div class="hrd-value">Rs <?= interviewWorkflowH(number_format((float) ($student['balance_amount'] ?? 0), 2)) ?></div></div>
                <div class="hrd-card"><div class="hrd-title">Payment Status</div><div class="hrd-meta"><?= interviewWorkflowH(ucfirst((string) ($student['payment_status'] ?? '-'))) ?></div></div>
            </div>
        </div>

        <div class="hrd-section">
            <div class="hrd-section-title">Payment History</div>
            <?php if (!$payments): ?>
                <div class="hrd-empty">No payments recorded.</div>
            <?php else: ?>
                <div class="hrd-table-wrap">
                    <table class="hrd-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Collected By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= interviewWorkflowH($payment['payment_date'] ?: '-') ?></td>
                                    <td>Rs <?= interviewWorkflowH(number_format((float) ($payment['amount'] ?? 0), 2)) ?></td>
                                    <td><?= interviewWorkflowH($payment['payment_mode'] ?: '-') ?></td>
                                    <td><?= interviewWorkflowH(ucfirst((string) ($payment['approval_status'] ?? '-'))) ?></td>
                                    <td><?= interviewWorkflowH($payment['collected_by_name'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="hrd-section">
            <div class="hrd-section-title">Follow-up History</div>
            <?php if (!$followups): ?>
                <div class="hrd-empty">No follow-ups found.</div>
            <?php else: ?>
                <div class="hrd-table-wrap">
                    <table class="hrd-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($followups as $followup): ?>
                                <tr>
                                    <td><?= interviewWorkflowH(trim(($followup['followup_date'] ?? '-') . ' ' . ($followup['followup_time'] ?? ''))) ?></td>
                                    <td><?= interviewWorkflowH($followup['followup_type'] ?: '-') ?></td>
                                    <td><?= interviewWorkflowH(ucfirst((string) ($followup['status'] ?? '-'))) ?></td>
                                    <td><?= nl2br(interviewWorkflowH($followup['notes'] ?: '-')) ?></td>
                                    <td><?= interviewWorkflowH($followup['created_by_name'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="hrd-section">
            <div class="hrd-section-title">Placement Interviews</div>
            <?php if (!$placementHistory): ?>
                <div class="hrd-empty">No placement interviews added yet.</div>
            <?php else: ?>
                <div class="hrd-table-wrap">
                    <table class="hrd-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Mode</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($placementHistory as $placement): ?>
                                <tr>
                                    <td><?= interviewWorkflowH($placement['company_name'] ?: '-') ?></td>
                                    <td><?= interviewWorkflowH(trim(($placement['interview_date'] ?? '-') . ' ' . ($placement['interview_time'] ?? ''))) ?></td>
                                    <td><?= interviewWorkflowH(ucwords(str_replace('_', ' ', (string) ($placement['status'] ?? '-')))) ?></td>
                                    <td><?= interviewWorkflowH($placement['interview_mode'] ?: '-') ?></td>
                                    <td><?= nl2br(interviewWorkflowH($placement['remarks'] ?: '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = 0;

try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

$tableReady = hrWorkflowTableExistsShared($pdo);
$placementTableReady = placementInterviewTableExistsShared($pdo);

$isAjax = isset($_GET['ajax']) && (int) $_GET['ajax'] === 1;
if ($isAjax) {
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($action === 'student_detail') {
        try {
            $registrationId = (int) ($_GET['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }

            $detail = fetchInterviewStudentDetailShared($pdo, $registrationId, $branchId, $canAllBranches === 1, true);
            echo renderHrStudentDetailHtml($detail);
        } catch (Exception $e) {
            echo '<div class="hrd-empty">' . interviewWorkflowH($e->getMessage()) . '</div>';
        }
        exit;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$rows = [];

if ($tableReady) {
    $where = [
        "r.reg_type = 'course'",
    ];
    $params = [];

    if (!$canAllBranches && $branchId > 0) {
        $where[] = "shi.branch_id = ?";
        $params[] = $branchId;
    }

    if ($status !== '' && in_array($status, ['pending', 'scheduled', 'selected', 'rejected', 'on_hold'], true)) {
        $where[] = "shi.interview_status = ?";
        $params[] = $status;
    }

    if ($q !== '') {
        $where[] = "(
            r.registration_no LIKE ?
            OR r.enquiry_snapshot_name LIKE ?
            OR r.program_name LIKE ?
            OR COALESCE(shi.company_name, '') LIKE ?
        )";
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }

    $placementCountSql = $placementTableReady
        ? "LEFT JOIN (
                SELECT registration_id, COUNT(*) AS placement_count, MAX(interview_date) AS last_interview_date
                FROM placement_interviews
                GROUP BY registration_id
           ) pi ON pi.registration_id = r.id"
        : "";
    $placementSelectSql = $placementTableReady
        ? "COALESCE(pi.placement_count, 0) AS placement_count, pi.last_interview_date,"
        : "0 AS placement_count, NULL AS last_interview_date,";

    $sql = "
        SELECT
            shi.id,
            shi.registration_id,
            shi.sent_to_hr_at,
            shi.interview_status,
            shi.company_name,
            shi.interview_date,
            shi.rejection_reason,
            r.registration_no,
            r.enquiry_snapshot_name,
            r.enquiry_snapshot_phone,
            r.enquiry_snapshot_email,
            r.program_name,
            r.batch_name,
            mi.mock_average,
            a.average_marks AS assessment_average,
            {$placementSelectSql}
            u.name AS staff_name
        FROM student_hr_interviews shi
        INNER JOIN registrations r ON r.id = shi.registration_id
        LEFT JOIN mock_interviews mi ON mi.registration_id = r.id
        LEFT JOIN assessment a ON a.registration_id = r.id
        LEFT JOIN users u ON u.id = shi.staff_user_id
        {$placementCountSql}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY shi.sent_to_hr_at DESC, shi.id DESC
    ";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        setFlash('error', 'Unable to load interview schedule rows: ' . $e->getMessage());
        redirect('index.php?page=interviews/schedule');
    }
}
?>

<h2 style="margin-bottom:20px;">Course Completed Students</h2>

<div class="card">
    <div class="card-header">Course Completed Students</div>
    <?php if (!$tableReady): ?>
        <div class="hrsch-alert hrsch-alert-warning" style="margin-top:14px;">
            HR interview workflow table is missing. Run <b>mock_interview_placement_workflow.sql</b> first.
        </div>
    <?php else: ?>
        <form method="GET" action="index.php" style="padding:14px;">
            <input type="hidden" name="page" value="interviews/schedule">
            <div class="hrsch-filter-row">
                <div>
                    <label>Search</label>
                    <input type="text" name="q" value="<?= interviewWorkflowH($q) ?>" placeholder="Registration, student, program, company">
                </div>
                <div>
                    <label>HR Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="selected" <?= $status === 'selected' ? 'selected' : '' ?>>Selected</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                    </select>
                </div>
                <div class="hrsch-filter-actions">
                    <div class="crm-icon-actions">
                        <button type="submit" class="crm-icon-btn is-primary" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="index.php?page=interviews/schedule" class="crm-icon-btn is-muted" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>

<div class="hrsch-note">
            This screen shows every course student already sent from staff to HR. HR can inspect the full student profile here, and manage company interviews from the separate Placement workflow.
        </div>

        <div class="table-responsive" style="padding:0 14px 14px;">
            <table class="table hrsch-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Scores</th>
                        <th>HR Status</th>
                        <th>Placement</th>
                        <th>Sent</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="6" class="hrsch-empty">No students have been sent to HR yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <div class="hrsch-primary"><?= interviewWorkflowH($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                    <div class="hrsch-sub"><?= interviewWorkflowH($r['registration_no'] ?: '-') ?> | <?= interviewWorkflowH($r['program_name'] ?: '-') ?></div>
                                    <div class="hrsch-sub"><?= interviewWorkflowH(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <div class="hrsch-sub">Mock: <?= interviewWorkflowH(isset($r['mock_average']) && $r['mock_average'] !== null ? number_format((float) $r['mock_average'], 2, '.', '') : '-') ?></div>
                                    <div class="hrsch-sub">Assessment: <?= interviewWorkflowH(isset($r['assessment_average']) && $r['assessment_average'] !== null ? number_format((float) $r['assessment_average'], 2, '.', '') : '-') ?></div>
                                </td>
                                <td>
                                    <span class="hrsch-status hrsch-status-<?= interviewWorkflowH($r['interview_status'] ?: 'pending') ?>">
                                        <?= interviewWorkflowH(ucwords(str_replace('_', ' ', $r['interview_status'] ?: 'pending'))) ?>
                                    </span>
                                    <div class="hrsch-sub"><?= interviewWorkflowH($r['company_name'] ?: 'Company pending') ?></div>
                                </td>
                                <td>
                                    <div class="hrsch-primary"><?= (int) ($r['placement_count'] ?? 0) ?> interview(s)</div>
                                    <div class="hrsch-sub"><?= interviewWorkflowH($r['last_interview_date'] ?: 'No placement interview yet') ?></div>
                                </td>
                                <td>
                                    <div class="hrsch-primary"><?= interviewWorkflowH(!empty($r['sent_to_hr_at']) ? date('d M Y', strtotime($r['sent_to_hr_at'])) : '-') ?></div>
                                    <div class="hrsch-sub">By <?= interviewWorkflowH($r['staff_name'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <div class="hrsch-actions crm-icon-actions">
                                        <button type="button" class="crm-icon-btn is-primary hrsch-view-btn" data-registration-id="<?= (int) $r['registration_id'] ?>" data-modern-tooltip="View details" aria-label="View details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <a href="index.php?page=interviews/placement&registration_id=<?= (int) $r['registration_id'] ?>" class="crm-icon-btn is-warning" data-modern-tooltip="Open placement" aria-label="Open placement">
                                            <i class="fas fa-briefcase"></i>
                                        </a>
                                        <a href="index.php?page=reports/student_overall&registration_id=<?= (int) $r['registration_id'] ?>" class="crm-icon-btn is-muted" data-modern-tooltip="Open overall report" aria-label="Open overall report">
                                            <i class="fas fa-chart-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="hrsch-modal-backdrop" id="hrDetailBackdrop"></div>
<div class="hrsch-modal" id="hrDetailModal">
    <div class="hrsch-modal-head">
        <div>
            <div class="hrsch-modal-title">Student Detail</div>
            <div class="hrsch-modal-subtitle">Complete interview-ready profile</div>
        </div>
        <button type="button" class="hrsch-close" id="hrDetailClose">&times;</button>
    </div>
    <div class="hrsch-modal-body" id="hrDetailBody">
        <div class="hrd-empty">Loading...</div>
    </div>
</div>

<style>
.hrsch-filter-row{
    display:grid;
    grid-template-columns:2fr 1fr auto;
    gap:12px;
    align-items:end;
}

.hrsch-filter-actions,
.hrsch-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.hrsch-note{
    margin:0 14px 14px;
    padding:14px 16px;
    border-radius:14px;
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
    font-weight:600;
}

.hrsch-table th,
.hrsch-table td{
    white-space:normal;
    vertical-align:top;
}

.hrsch-primary{
    font-weight:800;
    color:#1f2937;
}

.hrsch-sub{
    margin-top:4px;
    color:#64748b;
    font-size:12px;
}

.hrsch-empty{
    text-align:center;
    color:#64748b;
    font-weight:600;
}

.hrsch-alert{
    padding:14px 16px;
    border-radius:14px;
    font-weight:600;
}

.hrsch-alert-warning{
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
}

.hrsch-status{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:96px;
    padding:6px 12px;
    border-radius:999px;
    font-weight:800;
    font-size:12px;
}

.hrsch-status-pending{ background:#fef3c7; color:#92400e; }
.hrsch-status-scheduled{ background:#dbeafe; color:#1d4ed8; }
.hrsch-status-selected{ background:#dcfce7; color:#15803d; }
.hrsch-status-rejected{ background:#fee2e2; color:#b91c1c; }
.hrsch-status-on_hold{ background:#ede9fe; color:#6d28d9; }

.hrsch-placement-btn{
    background:#eff6ff;
    color:#1d4ed8;
    border:1px solid #bfdbfe;
}

.hrsch-modal-backdrop{
    position:fixed;
    inset:0;
    background:rgba(15,23,42,.55);
    display:none;
    z-index:9998;
}

.hrsch-modal{
    position:fixed;
    inset:30px 40px;
    background:#fff;
    border-radius:20px;
    display:none;
    z-index:9999;
    overflow:hidden;
    box-shadow:0 20px 60px rgba(15,23,42,.28);
}

.hrsch-modal-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    padding:18px 22px;
    border-bottom:1px solid #e5e7eb;
    background:#fff7fb;
}

.hrsch-modal-title{
    font-size:20px;
    font-weight:900;
    color:#111827;
}

.hrsch-modal-subtitle{
    margin-top:4px;
    color:#64748b;
    font-size:13px;
}

.hrsch-close{
    border:none;
    background:transparent;
    font-size:28px;
    line-height:1;
    color:#64748b;
    cursor:pointer;
}

.hrsch-modal-body{
    padding:18px 22px;
    overflow:auto;
    height:calc(100% - 84px);
}

.hrd-wrap{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.hrd-grid{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:12px;
}

.hrd-grid-two{
    grid-template-columns:repeat(2, minmax(0, 1fr));
}

.hrd-card{
    border:1px solid #f3e8ef;
    border-radius:16px;
    padding:14px;
    background:#fff;
}

.hrd-card-full{
    grid-column:1 / -1;
}

.hrd-title{
    font-size:12px;
    font-weight:800;
    color:#9d174d;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.hrd-value{
    margin-top:7px;
    font-size:20px;
    font-weight:900;
    color:#111827;
}

.hrd-meta{
    margin-top:6px;
    color:#475569;
    line-height:1.5;
    font-size:13px;
}

.hrd-section{
    border:1px solid #f3e8ef;
    border-radius:18px;
    padding:16px;
    background:#fff;
}

.hrd-section-title{
    margin-bottom:12px;
    font-size:16px;
    font-weight:900;
    color:#111827;
}

.hrd-table-wrap{
    overflow:auto;
}

.hrd-table{
    width:100%;
    border-collapse:collapse;
}

.hrd-table th,
.hrd-table td{
    border:1px solid #f3e8ef;
    padding:10px;
    text-align:left;
    vertical-align:top;
}

.hrd-table th{
    background:#fff7fb;
    color:#9d174d;
    font-weight:800;
}

.hrd-empty{
    padding:16px;
    border-radius:14px;
    background:#f8fafc;
    color:#64748b;
    font-weight:600;
}

@media (max-width: 1100px){
    .hrd-grid,
    .hrd-grid-two,
    .hrsch-filter-row{
        grid-template-columns:1fr;
    }

    .hrsch-modal{
        inset:20px 16px;
    }
}

/* =====================================================
GLOBAL TYPOGRAPHY STYLECSS SYNC
font-family + font-size + font-weight only
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){font-size:clamp(2rem, 2.5vw, 2.4rem) !important;font-weight:700 !important;}
:where(h2,.h2,.section-title){font-size:clamp(1.6rem, 2vw, 2rem) !important;font-weight:600 !important;}
:where(h3,.h3,.card-header,.table-title){font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;font-weight:600 !important;}
:where(h4,.h4){font-size:1.2rem !important;font-weight:500 !important;}
:where(h5,.h5){font-size:1rem !important;font-weight:500 !important;}
:where(h6,.h6){font-size:0.9rem !important;font-weight:500 !important;}
:where(body){font-size:1rem !important;}
:where(p,.text-body,li,td,.text-muted,.help-text,.form-text,.small,small,.secondary-text){font-size:0.95rem !important;font-weight:400 !important;}
:where(.small,small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){font-size:0.85rem !important;font-weight:400 !important;}
:where(label,.form-label){font-size:0.85rem !important;font-weight:500 !important;}
:where(input,select,textarea,.form-control,.form-select){font-size:0.95rem !important;font-weight:400 !important;}
:where(input::placeholder,textarea::placeholder){font-weight:400 !important;}
:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){font-size:0.9rem !important;font-weight:600 !important;}
:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{font-size:0.75rem !important;font-weight:600 !important;}
:where(.table th,.crm-table th,.dataTables_wrapper th,th){font-size:0.75rem !important;font-weight:600 !important;}
:where(.table td,.dataTables_wrapper tbody td){font-size:0.9rem !important;}
:where(.dataTables_wrapper .dataTables_info){font-size:0.85rem !important;font-weight:400 !important;}
:where(.dataTables_wrapper .paginate_button){font-size:0.9rem !important;font-weight:600 !important;}
:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){font-weight:600 !important;}

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>

<script>
(function(){
    const modal = document.getElementById('hrDetailModal');
    const backdrop = document.getElementById('hrDetailBackdrop');
    const body = document.getElementById('hrDetailBody');
    const closeBtn = document.getElementById('hrDetailClose');

    function closeModal() {
        modal.style.display = 'none';
        backdrop.style.display = 'none';
        body.innerHTML = '<div class="hrd-empty">Loading...</div>';
    }

    async function openDetail(registrationId) {
        modal.style.display = 'block';
        backdrop.style.display = 'block';
        body.innerHTML = '<div class="hrd-empty">Loading...</div>';

        try {
            const res = await fetch(`index.php?page=interviews/schedule&ajax=1&action=student_detail&registration_id=${encodeURIComponent(registrationId)}`);
            body.innerHTML = await res.text();
        } catch (e) {
            body.innerHTML = '<div class="hrd-empty">Unable to load student detail.</div>';
        }
    }

    document.querySelectorAll('.hrsch-view-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            openDetail(this.getAttribute('data-registration-id'));
        });
    });

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            closeModal();
        }
    });
})();
</script>

