<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('interviews/schedule');
}

if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!in_array(($_SESSION['role_name'] ?? ''), ['HR', 'Super Admin'], true)) {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Segoe UI,sans-serif'>
            <h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2>
            <p style='margin:0;color:#666'>This page is available only for HR users.</p>
          </div>";
    return;
}

function hrScheduleWorkflowTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $st = $pdo->query("SHOW TABLES LIKE 'student_hr_interviews'");
        $exists = (bool) $st->fetchColumn();
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$tableReady = hrScheduleWorkflowTableExists($pdo);
$csrfToken = generateCSRF();

$canAllBranches = 0;
try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_interview_schedule'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid request. Please refresh and try again.');
        redirect('index.php?page=interviews/schedule');
    } elseif (!$tableReady) {
        setFlash('error', 'HR interview workflow table not found. Run mock_interview_setup.sql first.');
        redirect('index.php?page=interviews/schedule');
    } else {
        try {
            $workflowId = (int) ($_POST['workflow_id'] ?? 0);
            $companyName = trim((string) ($_POST['company_name'] ?? ''));
            $interviewDate = trim((string) ($_POST['interview_date'] ?? ''));
            $status = trim((string) ($_POST['interview_status'] ?? 'pending'));
            $rejectionReason = trim((string) ($_POST['rejection_reason'] ?? ''));

            if ($workflowId <= 0) {
                throw new RuntimeException('Invalid interview record selected.');
            }

            if (!in_array($status, ['pending', 'scheduled', 'selected', 'rejected', 'on_hold'], true)) {
                throw new RuntimeException('Invalid interview status selected.');
            }

            if (in_array($status, ['scheduled', 'selected'], true) && ($companyName === '' || $interviewDate === '')) {
                throw new RuntimeException('Company and interview date are required for scheduled or selected students.');
            }

            if ($status === 'rejected' && $rejectionReason === '') {
                throw new RuntimeException('Please enter the rejection reason.');
            }

            $params = [$workflowId];
            $checkSql = "SELECT id FROM student_hr_interviews WHERE id = ?";
            if (!$canAllBranches && $branchId > 0) {
                $checkSql .= " AND branch_id = ?";
                $params[] = $branchId;
            }
            $checkSql .= " LIMIT 1";

            $st = $pdo->prepare($checkSql);
            $st->execute($params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Interview workflow record not found or access denied.');
            }

            $update = $pdo->prepare("
                UPDATE student_hr_interviews
                SET company_name = ?,
                    interview_date = ?,
                    interview_status = ?,
                    rejection_reason = ?,
                    hr_updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $update->execute([
                $companyName !== '' ? $companyName : null,
                $interviewDate !== '' ? $interviewDate : null,
                $status,
                $rejectionReason !== '' ? $rejectionReason : null,
                $userId,
                $workflowId,
            ]);

            setFlash('success', 'Interview details updated successfully.');
            redirect('index.php?page=interviews/schedule');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
            redirect('index.php?page=interviews/schedule');
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$rows = [];

if ($tableReady) {
    $where = ["1=1"];
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

    $sql = "
        SELECT
            shi.id,
            shi.registration_id,
            shi.sent_to_hr_at,
            shi.company_name,
            shi.interview_date,
            shi.interview_status,
            shi.rejection_reason,
            r.registration_no,
            r.enquiry_snapshot_name,
            r.enquiry_snapshot_phone,
            r.enquiry_snapshot_email,
            r.program_name,
            r.batch_name,
            mi.mock_average,
            a.average_marks AS assessment_average
        FROM student_hr_interviews shi
        INNER JOIN registrations r ON r.id = shi.registration_id
        LEFT JOIN mock_interviews mi ON mi.registration_id = r.id
        LEFT JOIN assessment a ON a.registration_id = r.id
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

<h2 style="margin-bottom:20px;">Interview Schedule</h2>

<div class="card">
    <div class="card-header">HR Interview Processing</div>
    <?php if (!$tableReady): ?>
        <div class="hrsch-alert hrsch-alert-warning" style="margin-top:14px;">
            HR interview workflow table is missing. Run <b>mock_interview_setup.sql</b> first.
        </div>
    <?php else: ?>
        <form method="GET" action="index.php" style="padding:14px;">
            <input type="hidden" name="page" value="interviews/schedule">
            <div class="hrsch-filter-row">
                <div>
                    <label>Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Registration, student, program, company">
                </div>
                <div>
                    <label>Status</label>
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
                    <button class="btn btn-primary">Apply</button>
                    <a href="index.php?page=interviews/schedule" class="btn" style="background:#f3f4f6;">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive" style="padding:0 14px 14px;">
            <table class="table hrsch-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Scores</th>
                        <th>Company</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Rejection Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="7" class="hrsch-empty">No students available for interview scheduling yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php $formId = 'hr-schedule-form-' . (int) $r['id']; ?>
                            <tr>
                                <td>
                                    <div class="hrsch-primary"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                    <div class="hrsch-sub"><?= h($r['registration_no'] ?: '-') ?> | <?= h($r['program_name'] ?: '-') ?></div>
                                    <div class="hrsch-sub"><?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <div class="hrsch-sub">Mock: <?= h(isset($r['mock_average']) && $r['mock_average'] !== null ? number_format((float) $r['mock_average'], 2, '.', '') : '-') ?></div>
                                    <div class="hrsch-sub">Assessment: <?= h(isset($r['assessment_average']) && $r['assessment_average'] !== null ? number_format((float) $r['assessment_average'], 2, '.', '') : '-') ?></div>
                                </td>
                                <td>
                                    <input type="text" name="company_name" form="<?= h($formId) ?>" value="<?= h($r['company_name'] ?? '') ?>" placeholder="Company name" class="hrsch-input">
                                </td>
                                <td>
                                    <input type="date" name="interview_date" form="<?= h($formId) ?>" value="<?= h($r['interview_date'] ?? '') ?>" class="hrsch-input">
                                </td>
                                <td>
                                    <select name="interview_status" form="<?= h($formId) ?>" class="hrsch-input">
                                        <option value="pending" <?= ($r['interview_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="scheduled" <?= ($r['interview_status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                        <option value="selected" <?= ($r['interview_status'] ?? '') === 'selected' ? 'selected' : '' ?>>Selected</option>
                                        <option value="rejected" <?= ($r['interview_status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        <option value="on_hold" <?= ($r['interview_status'] ?? '') === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                                    </select>
                                </td>
                                <td>
                                    <textarea name="rejection_reason" form="<?= h($formId) ?>" rows="3" class="hrsch-input" placeholder="Reason if rejected"><?= h($r['rejection_reason'] ?? '') ?></textarea>
                                </td>
                                <td>
                                    <form method="POST" id="<?= h($formId) ?>" class="hrsch-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="workflow_id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" name="save_interview_schedule" value="1" class="btn btn-primary">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.hrsch-filter-row{
    display:grid;
    grid-template-columns:2fr 1fr auto;
    gap:12px;
    align-items:end;
}

.hrsch-filter-actions{
    display:flex;
    gap:8px;
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

.hrsch-form{
    margin:0;
}

.hrsch-input{
    width:100%;
    min-width:140px;
    border:1px solid #e2e8f0;
    border-radius:10px;
    padding:10px 11px;
    background:#fff;
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

@media (max-width: 900px){
    .hrsch-filter-row{
        grid-template-columns:1fr;
    }
}
</style>
