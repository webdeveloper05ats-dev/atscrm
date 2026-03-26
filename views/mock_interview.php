<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('mock_interview');
}

if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (($_SESSION['role_name'] ?? '') !== 'Staff') {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Segoe UI,sans-serif'>
            <h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2>
            <p style='margin:0;color:#666'>This page is available only for staff users.</p>
          </div>";
    return;
}

function mockInterviewTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $st = $pdo->query("SHOW TABLES LIKE 'mock_interviews'");
        $exists = (bool) $st->fetchColumn();
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function mockInterviewAssessmentTableExists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $st = $pdo->query("SHOW TABLES LIKE 'assessment'");
        $exists = (bool) $st->fetchColumn();
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function mockInterviewHrWorkflowTableExists(PDO $pdo): bool
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

function mockInterviewWorkflowColumnsReady(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $st = $pdo->query("SHOW COLUMNS FROM mock_interviews LIKE 'workflow_status'");
        $exists = (bool) $st->fetchColumn();
    } catch (Exception $e) {
        $exists = false;
    }

    return $exists;
}

function mockInterviewToNull($value): ?float
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Mock interview marks must be numeric.');
    }

    $number = (float) $value;
    if ($number < 0 || $number > 100) {
        throw new InvalidArgumentException('Mock interview marks must be between 0 and 100.');
    }

    return round($number, 2);
}

function mockInterviewAverage(?float $theoretical, ?float $machineTask): ?float
{
    $marks = array_values(array_filter([$theoretical, $machineTask], static function ($value) {
        return $value !== null;
    }));

    if (!$marks) {
        return null;
    }

    return round(array_sum($marks) / count($marks), 2);
}

function mockInterviewIsReadyForCompletion(?float $theoretical, ?float $machineTask): bool
{
    return $theoretical !== null && $machineTask !== null;
}

function overallPerformanceAverage(?float $assessmentAverage, ?float $mockAverage): ?float
{
    $marks = array_values(array_filter([$assessmentAverage, $mockAverage], static function ($value) {
        return $value !== null;
    }));

    if (!$marks) {
        return null;
    }

    return round(array_sum($marks) / count($marks), 2);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
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

$tableReady = mockInterviewTableExists($pdo);
$assessmentTableReady = mockInterviewAssessmentTableExists($pdo);
$hrWorkflowTableReady = mockInterviewHrWorkflowTableExists($pdo);
$workflowColumnsReady = $tableReady ? mockInterviewWorkflowColumnsReady($pdo) : false;
$csrfToken = generateCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mock_interview'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid request. Please refresh and try again.');
        redirect('index.php?page=mock_interview');
    } elseif (!$tableReady) {
        setFlash('error', 'Mock interview table not found. Run mock_interview_setup.sql first.');
        redirect('index.php?page=mock_interview');
    } else {
        try {
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $theoreticalMarks = mockInterviewToNull($_POST['theoretical_marks'] ?? '');
            $machineTaskMarks = mockInterviewToNull($_POST['machine_task_marks'] ?? '');
            $mockAverage = mockInterviewAverage($theoreticalMarks, $machineTaskMarks);

            if ($registrationId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }

            $studentSql = "
                SELECT r.id, r.branch_id
                FROM registrations r
                INNER JOIN registration_courses rc ON rc.registration_id = r.id
                WHERE r.id = ?
                  AND rc.guide_staff_id = ?
                  AND r.reg_type = 'course'
                  AND r.registration_status IN ('active','completed')
            ";
            $studentParams = [$registrationId, $userId];

            if ($canAllBranches !== 1 && $branchId > 0) {
                $studentSql .= " AND branch_id = ?";
                $studentParams[] = $branchId;
            }

            $studentSql .= " LIMIT 1";
            $st = $pdo->prepare($studentSql);
            $st->execute($studentParams);
            $studentRow = $st->fetch(PDO::FETCH_ASSOC);

            if (!$studentRow) {
                throw new RuntimeException('Student not found or access denied.');
            }

            $sql = "
                INSERT INTO mock_interviews (
                    registration_id,
                    branch_id,
                    staff_user_id,
                    theoretical_marks,
                    machine_task_marks,
                    mock_average
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    branch_id = VALUES(branch_id),
                    staff_user_id = VALUES(staff_user_id),
                    theoretical_marks = VALUES(theoretical_marks),
                    machine_task_marks = VALUES(machine_task_marks),
                    mock_average = VALUES(mock_average)
            ";

            $st = $pdo->prepare($sql);
            $st->execute([
                $registrationId,
                (int) $studentRow['branch_id'],
                $userId,
                $theoreticalMarks,
                $machineTaskMarks,
                $mockAverage,
            ]);

            setFlash('success', 'Mock interview marks saved successfully.');
            redirect('index.php?page=mock_interview');
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
            redirect('index.php?page=mock_interview');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
            redirect('index.php?page=mock_interview');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_mock_done'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid request. Please refresh and try again.');
        redirect('index.php?page=mock_interview');
    } elseif (!$tableReady) {
        setFlash('error', 'Mock interview table not found. Run mock_interview_setup.sql first.');
        redirect('index.php?page=mock_interview');
    } elseif (!$workflowColumnsReady) {
        setFlash('error', 'Mock interview workflow columns are missing. Run mock_interview_placement_workflow.sql first.');
        redirect('index.php?page=mock_interview');
    } else {
        try {
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }

            $sql = "
                SELECT
                    r.id,
                    mi.theoretical_marks,
                    mi.machine_task_marks
                FROM registrations r
                INNER JOIN registration_courses rc ON rc.registration_id = r.id
                INNER JOIN mock_interviews mi ON mi.registration_id = r.id
                WHERE r.id = ?
                  AND rc.guide_staff_id = ?
                  AND r.reg_type = 'course'
                  AND r.registration_status IN ('active','completed')
            ";
            $params = [$registrationId, $userId];

            if ($canAllBranches !== 1 && $branchId > 0) {
                $sql .= " AND r.branch_id = ?";
                $params[] = $branchId;
            }

            $sql .= " LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $studentRow = $st->fetch(PDO::FETCH_ASSOC);

            if (!$studentRow) {
                throw new RuntimeException('Mock interview record not found or access denied.');
            }

            $theoreticalMarks = isset($studentRow['theoretical_marks']) && $studentRow['theoretical_marks'] !== null
                ? (float) $studentRow['theoretical_marks']
                : null;
            $machineTaskMarks = isset($studentRow['machine_task_marks']) && $studentRow['machine_task_marks'] !== null
                ? (float) $studentRow['machine_task_marks']
                : null;

            if (!mockInterviewIsReadyForCompletion($theoreticalMarks, $machineTaskMarks)) {
                throw new RuntimeException('Enter both mock interview marks before marking the student as done.');
            }

            $st = $pdo->prepare("
                UPDATE mock_interviews
                SET workflow_status = 'done',
                    completed_at = NOW(),
                    completed_by = ?
                WHERE registration_id = ?
                LIMIT 1
            ");
            $st->execute([$userId, $registrationId]);

            setFlash('success', 'Mock interview marked as done.');
            redirect('index.php?page=mock_interview');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
            redirect('index.php?page=mock_interview');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_hr'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid request. Please refresh and try again.');
        redirect('index.php?page=mock_interview');
    } elseif (!$hrWorkflowTableReady) {
        setFlash('error', 'HR interview workflow table not found. Run mock_interview_setup.sql first.');
        redirect('index.php?page=mock_interview');
    } elseif (!$tableReady) {
        setFlash('error', 'Enter mock interview marks before sending students to HR.');
        redirect('index.php?page=mock_interview');
    } elseif (!$workflowColumnsReady) {
        setFlash('error', 'Mock interview workflow columns are missing. Run mock_interview_placement_workflow.sql first.');
        redirect('index.php?page=mock_interview');
    } else {
        try {
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }

            $sql = "
                SELECT
                    r.id,
                    r.branch_id,
                    r.registration_status,
                    r.payment_status,
                    r.balance_amount,
                    mi.workflow_status,
                    mi.mock_average
                FROM registrations r
                INNER JOIN registration_courses rc ON rc.registration_id = r.id
                INNER JOIN mock_interviews mi ON mi.registration_id = r.id
                WHERE r.id = ?
                  AND rc.guide_staff_id = ?
                  AND r.reg_type = 'course'
                  AND r.registration_status IN ('active','completed')
            ";
            $params = [$registrationId, $userId];

            if ($canAllBranches !== 1 && $branchId > 0) {
                $sql .= " AND r.branch_id = ?";
                $params[] = $branchId;
            }

            $sql .= " LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $studentRow = $st->fetch(PDO::FETCH_ASSOC);

            if (!$studentRow) {
                throw new RuntimeException('Mock interview record not found or access denied.');
            }

            if (!isset($studentRow['mock_average']) || $studentRow['mock_average'] === null) {
                throw new RuntimeException('Mock interview marks are required before sending to HR.');
            }

            if (($studentRow['workflow_status'] ?? '') !== 'done') {
                throw new RuntimeException('Mark the mock interview as done before sending the student to HR.');
            }

            if (($studentRow['payment_status'] ?? '') !== 'paid' || (float) ($studentRow['balance_amount'] ?? 0) > 0) {
                throw new RuntimeException('Student can be sent to HR only after full fee payment is completed.');
            }

            $upsert = $pdo->prepare("
                INSERT INTO student_hr_interviews (
                
                    registration_id,
                    branch_id,
                    staff_user_id,
                    sent_to_hr_by,
                    sent_to_hr_at,
                    interview_status
                ) VALUES (?, ?, ?, ?, NOW(), 'pending')
                ON DUPLICATE KEY UPDATE
                    branch_id = VALUES(branch_id),
                    staff_user_id = VALUES(staff_user_id),
                    sent_to_hr_by = VALUES(sent_to_hr_by),
                    sent_to_hr_at = VALUES(sent_to_hr_at)
            ");
            $upsert->execute([
                $registrationId,
                (int) $studentRow['branch_id'],
                $userId,
                $userId,
            ]);

            $st = $pdo->prepare("
                UPDATE mock_interviews
                SET workflow_status = 'sent_to_hr'
                WHERE registration_id = ?
                LIMIT 1
            ");
            $st->execute([$registrationId]);

            setFlash('success', 'Student sent to HR for interview processing.');
            redirect('index.php?page=mock_interview');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
            redirect('index.php?page=mock_interview');
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$where = [
    "rc.guide_staff_id = ?",
    "r.reg_type = 'course'",
    "r.registration_status IN ('active','completed')",
];
$params = [$userId];

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($q !== '') {
    $where[] = "(
        r.registration_no LIKE ?
        OR r.enquiry_snapshot_name LIKE ?
        OR r.program_name LIKE ?
        OR r.batch_name LIKE ?
    )";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

$rows = [];
try {
    $assessmentSelect = $assessmentTableReady ? "a.average_marks AS assessment_average," : "NULL AS assessment_average,";
    $assessmentJoin = $assessmentTableReady ? "LEFT JOIN assessment a ON a.registration_id = r.id" : "";
    $hrSelect = $hrWorkflowTableReady ? "shi.sent_to_hr_at, shi.interview_status," : "NULL AS sent_to_hr_at, NULL AS interview_status,";
    $hrJoin = $hrWorkflowTableReady ? "LEFT JOIN student_hr_interviews shi ON shi.registration_id = r.id" : "";
    $workflowSelect = $workflowColumnsReady ? "mi.workflow_status" : "NULL AS workflow_status";
    $sql = "
        SELECT
            r.id,
            r.registration_no,
            r.joined_on,
            r.enquiry_snapshot_name,
            r.enquiry_snapshot_phone,
            r.enquiry_snapshot_email,
            r.program_name,
            r.batch_name,
            r.registration_status,
            r.payment_status,
            r.balance_amount,
            {$assessmentSelect}
            {$hrSelect}
            mi.theoretical_marks,
            mi.machine_task_marks,
            mi.mock_average,
            {$workflowSelect}
        FROM registrations r
        INNER JOIN registration_courses rc ON rc.registration_id = r.id
        {$assessmentJoin}
        {$hrJoin}
        LEFT JOIN mock_interviews mi ON mi.registration_id = r.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if ($tableReady) {
        setFlash('error', 'Unable to load mock interview records: ' . $e->getMessage());
        redirect('index.php?page=mock_interview');
    }
}
?>

<div class="card">
    <div class="card-header">Mock Interviews</div>
    <div class="card-body">
        <?php if (!$tableReady): ?>
            <div class="mock-alert mock-alert-warning">
                Mock interview table is missing. Run <b>mock_interview_setup.sql</b> before using this page.
            </div>
        <?php endif; ?>

        <form method="GET" action="index.php" class="mock-filter">
            <input type="hidden" name="page" value="mock_interview">
            <div class="mock-filter-row">
                <div class="mock-field">
                    <label>Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Registration, student, program, batch">
                </div>
                <div class="mock-filter-actions">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a href="index.php?page=mock_interview" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>

        <div class="mock-note">
            Enter two mock interview scores for each assigned course student. The mock average updates from theoretical and machine task marks, and the overall average combines the mock average with the assessment average.
        </div>

        <?php if (!$assessmentTableReady): ?>
            <div class="mock-alert mock-alert-info">
                Assessment table is not available yet, so assessment and overall averages will use mock interview marks only.
            </div>
        <?php endif; ?>

        <?php if (!$hrWorkflowTableReady): ?>
            <div class="mock-alert mock-alert-warning">
                HR interview workflow table is missing. Run <b>mock_interview_setup.sql</b> to enable sending students to HR.
            </div>
        <?php endif; ?>

        <?php if (!$workflowColumnsReady): ?>
            <div class="mock-alert mock-alert-warning">
                Mock interview workflow columns are missing. Run <b>mock_interview_placement_workflow.sql</b> to enable Mark Done and Send to HR.
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="mock-table">
                <thead>
                    <tr>
                        <th>Registration</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Theoretical</th>
                        <th>Machine Task</th>
                        <th>Mock Avg</th>
                        <th>Assessment Avg</th>
                        <th>Overall Avg</th>
                        <th>HR Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="10" class="mock-empty">No assigned course students found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $formId = 'mock-interview-form-' . (int) $r['id'];
                            $mockAverage = isset($r['mock_average']) ? round((float) $r['mock_average'], 2) : null;
                            $assessmentAverage = isset($r['assessment_average']) ? round((float) $r['assessment_average'], 2) : null;
                            $overallAverage = overallPerformanceAverage($assessmentAverage, $mockAverage);
                            $hrStatus = trim((string) ($r['interview_status'] ?? ''));
                            $sentToHrAt = trim((string) ($r['sent_to_hr_at'] ?? ''));
                            $workflowStatus = trim((string) ($r['workflow_status'] ?? 'pending'));
                            $theoreticalMarks = isset($r['theoretical_marks']) && $r['theoretical_marks'] !== null ? (float) $r['theoretical_marks'] : null;
                            $machineTaskMarks = isset($r['machine_task_marks']) && $r['machine_task_marks'] !== null ? (float) $r['machine_task_marks'] : null;
                            $canMarkDone = mockInterviewIsReadyForCompletion($theoreticalMarks, $machineTaskMarks);
                            $feesPaidInFull = (($r['payment_status'] ?? '') === 'paid') && ((float) ($r['balance_amount'] ?? 0) <= 0);
                            $canSendToHr = $canMarkDone && $workflowStatus === 'done' && $feesPaidInFull;
                            ?>
                            <tr>
                                <td>
                                    <div class="mock-primary"><?= h($r['registration_no'] ?: ('REG-' . $r['id'])) ?></div>
                                    <div class="mock-sub"><?= h($r['joined_on'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <div class="mock-primary"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                    <div class="mock-sub"><?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <div class="mock-primary"><?= h($r['program_name'] ?: '-') ?></div>
                                    <div class="mock-sub"><?= h($r['batch_name'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="100" name="theoretical_marks" form="<?= h($formId) ?>" class="mock-input js-mock-mark" value="<?= h($r['theoretical_marks'] ?? '') ?>" placeholder="0-100">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" max="100" name="machine_task_marks" form="<?= h($formId) ?>" class="mock-input js-mock-mark" value="<?= h($r['machine_task_marks'] ?? '') ?>" placeholder="0-100">
                                </td>
                                <td>
                                    <span class="mock-average js-mock-average"><?= h($mockAverage !== null ? number_format($mockAverage, 2, '.', '') : '-') ?></span>
                                </td>
                                <td>
                                    <span class="mock-pill mock-pill-secondary js-assessment-average" data-assessment-average="<?= h($assessmentAverage !== null ? number_format($assessmentAverage, 2, '.', '') : '') ?>">
                                        <?= h($assessmentAverage !== null ? number_format($assessmentAverage, 2, '.', '') : '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="mock-pill mock-pill-primary js-overall-average">
                                        <?= h($overallAverage !== null ? number_format($overallAverage, 2, '.', '') : '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sentToHrAt !== ''): ?>
                                        <span class="mock-pill mock-pill-success">
                                            <?= h(ucwords(str_replace('_', ' ', $hrStatus !== '' ? $hrStatus : 'pending'))) ?>
                                        </span>
                                        <div class="mock-sub">Sent <?= h(date('d M Y', strtotime($sentToHrAt))) ?></div>
                                        <div class="mock-sub">Workflow: <?= h(ucwords(str_replace('_', ' ', $workflowStatus))) ?></div>
                                    <?php elseif ($workflowStatus === 'done'): ?>
                                        <span class="mock-pill mock-pill-primary">Done</span>
                                        <div class="mock-sub"><?= $feesPaidInFull ? 'Ready for HR send' : 'Full fee payment required before HR send' ?></div>
                                    <?php else: ?>
                                        <span class="mock-pill mock-pill-muted"><?= h(ucwords(str_replace('_', ' ', $workflowStatus ?: 'pending'))) ?></span>
                                        <div class="mock-sub">Complete both marks first</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" id="<?= h($formId) ?>" class="mock-row-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" name="save_mock_interview" value="1" class="btn btn-primary mock-save-btn">Save</button>
                                    </form>
                                    <form method="POST" class="mock-row-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit" name="mark_mock_done" value="1" class="btn mock-done-btn" <?= ($canMarkDone && $workflowColumnsReady) ? '' : 'disabled' ?>>
                                                <?= $workflowStatus === 'done' || $workflowStatus === 'sent_to_hr' ? 'Done' : 'Mark Done' ?>
                                            </button>
                                        </form>
                                    <?php if ($hrWorkflowTableReady): ?>
                                        <form method="POST" class="mock-row-form">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit" name="send_to_hr" value="1" class="btn mock-send-btn" <?= ($canSendToHr && $workflowColumnsReady) ? '' : 'disabled' ?>>
                                                <?= $sentToHrAt !== '' ? 'Re-send to HR' : 'Send to HR' ?>
                                            </button>
                                        </form>
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

<style>
.mock-filter-row{
    display:flex;
    gap:16px;
    align-items:flex-end;
    justify-content:space-between;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.mock-field{
    flex:1 1 320px;
}

.mock-field label{
    display:block;
    font-weight:700;
    margin-bottom:6px;
    color:#475569;
}

.mock-field input{
    width:100%;
    border:1px solid #f0c9d9;
    border-radius:12px;
    padding:11px 13px;
    background:#fff;
}

.mock-filter-actions{
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.btn-reset{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:0 16px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#475569;
    text-decoration:none;
    font-weight:700;
}

.mock-note{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:14px;
    background:#fff5f9;
    border:1px solid #f6cada;
    color:#9d174d;
    line-height:1.6;
}

.mock-alert{
    margin-bottom:16px;
    padding:14px 16px;
    border-radius:14px;
    font-weight:600;
}

.mock-alert-warning{
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
}

.mock-alert-info{
    background:#eff6ff;
    color:#1d4ed8;
    border:1px solid #bfdbfe;
}

.mock-table{
    width:100%;
    border-collapse:collapse;
    min-width:1080px;
    background:#fff;
    border:1px solid #f3d8e3;
}

.mock-table th{
    background:#fff0f5;
    color:#9d174d;
    padding:13px 12px;
    border:1px solid #f3d8e3;
    text-align:left;
    font-weight:800;
}

.mock-table td{
    padding:12px;
    border:1px solid #f6dfe8;
    vertical-align:middle;
    background:#fff;
}

.mock-table tbody tr:hover{
    background:#fff8fb;
}

.mock-primary{
    font-weight:800;
    color:#1f2937;
}

.mock-sub{
    margin-top:4px;
    font-size:12px;
    color:#64748b;
}

.mock-input{
    width:100%;
    min-width:110px;
    padding:10px 11px;
    border-radius:12px;
    border:1px solid #f0c9d9;
    background:#fff;
    font-weight:600;
}

.mock-input:focus{
    outline:none;
    border-color:#e91e63;
    box-shadow:0 0 0 3px rgba(233, 30, 99, 0.12);
}

.mock-average{
    font-weight:800;
    color:#be185d;
}

.mock-pill{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:78px;
    min-height:34px;
    padding:0 12px;
    border-radius:999px;
    font-weight:800;
    font-size:13px;
}

.mock-pill-primary{
    background:#fee2e2;
    color:#b91c1c;
}

.mock-pill-secondary{
    background:#eff6ff;
    color:#1d4ed8;
}

.mock-pill-success{
    background:#ecfdf5;
    color:#047857;
}

.mock-pill-muted{
    background:#f1f5f9;
    color:#475569;
}

.mock-row-form{
    margin:0 0 8px;
}

.mock-save-btn{
    min-width:78px;
}

.mock-done-btn{
    min-width:102px;
    background:#f59e0b;
    color:#fff;
    border:1px solid #f59e0b;
}

.mock-done-btn[disabled]{
    opacity:.55;
    cursor:not-allowed;
}

.mock-send-btn{
    min-width:102px;
    background:#fff7ed;
    color:#c2410c;
    border:1px solid #fdba74;
}

.mock-send-btn[disabled]{
    opacity:.55;
    cursor:not-allowed;
}

.mock-empty{
    text-align:center;
    color:#64748b;
    padding:18px;
    font-weight:600;
}

@media (max-width: 1100px){
    .table-wrap{
        overflow-x:auto;
    }
}

@media (max-width: 768px){
    .mock-filter-row{
        flex-direction:column;
        align-items:stretch;
    }

    .mock-filter-actions{
        width:100%;
    }

    .mock-filter-actions .btn,
    .btn-reset{
        width:100%;
    }
}
</style>

<script>
document.querySelectorAll('.mock-table tbody tr').forEach(function(row){
    var inputs = row.querySelectorAll('.js-mock-mark');
    var mockAvgNode = row.querySelector('.js-mock-average');
    var assessmentNode = row.querySelector('.js-assessment-average');
    var overallNode = row.querySelector('.js-overall-average');

    if (!inputs.length || !mockAvgNode || !assessmentNode || !overallNode) {
        return;
    }

    function parseMark(value) {
        var cleaned = String(value || '').trim();
        if (cleaned === '') return null;
        var parsed = parseFloat(cleaned);
        if (Number.isNaN(parsed)) return null;
        return parsed;
    }

    function computeAverage(values) {
        var filtered = values.filter(function(value){
            return value !== null;
        });
        if (!filtered.length) return null;
        var total = filtered.reduce(function(sum, value){
            return sum + value;
        }, 0);
        return total / filtered.length;
    }

    function formatAverage(value) {
        return value === null ? '-' : value.toFixed(2);
    }

    function updateAverages() {
        var theory = parseMark(inputs[0].value);
        var machine = parseMark(inputs[1].value);
        var mockAverage = computeAverage([theory, machine]);
        var assessmentAverage = parseMark(assessmentNode.dataset.assessmentAverage);
        var overallAverage = computeAverage([mockAverage, assessmentAverage]);

        mockAvgNode.textContent = formatAverage(mockAverage);
        overallNode.textContent = formatAverage(overallAverage);
    }

    inputs.forEach(function(input){
        input.addEventListener('input', updateAverages);
    });
});
</script>
