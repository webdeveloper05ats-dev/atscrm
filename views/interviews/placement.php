<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_workflow_helpers.php';

if (!in_array(($_SESSION['role_name'] ?? ''), ['HR', 'Super Admin'], true)) {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Segoe UI,sans-serif'>
            <h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2>
            <p style='margin:0;color:#666'>This page is available only for HR users.</p>
          </div>";
    return;
}

function placementWorkflowH($value): string
{
    return interviewWorkflowH($value);
}

function placementWorkflowCanAccessRegistration(PDO $pdo, int $registrationId, int $branchId, bool $canAllBranches): ?array
{
    if ($registrationId <= 0) {
        return null;
    }

    $params = [$registrationId];
    $sql = "
        SELECT
            r.id,
            r.branch_id,
            r.registration_no,
            r.enquiry_snapshot_name,
            r.program_name,
            shi.id AS hr_workflow_id,
            shi.interview_status,
            shi.company_name
        FROM registrations r
        INNER JOIN student_hr_interviews shi ON shi.registration_id = r.id
        WHERE r.id = ?
          AND r.reg_type = 'course'
    ";

    if (!$canAllBranches && $branchId > 0) {
        $sql .= " AND r.branch_id = ?";
        $params[] = $branchId;
    }

    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function placementWorkflowCanAccessInterview(PDO $pdo, int $interviewId, int $branchId, bool $canAllBranches): ?array
{
    if ($interviewId <= 0) {
        return null;
    }

    $params = [$interviewId];
    $sql = "
        SELECT
            pi.*,
            r.registration_no,
            r.enquiry_snapshot_name,
            r.program_name
        FROM placement_interviews pi
        INNER JOIN registrations r ON r.id = pi.registration_id
        WHERE pi.id = ?
          AND r.reg_type = 'course'
    ";

    if (!$canAllBranches && $branchId > 0) {
        $sql .= " AND pi.branch_id = ?";
        $params[] = $branchId;
    }

    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function renderPlacementInterviewListHtml(array $student, array $interviews): string
{
    ob_start();
    ?>
    <div class="placement-modal-copy">
        <div class="placement-modal-heading"><?= placementWorkflowH($student['enquiry_snapshot_name'] ?: '-') ?></div>
        <div class="placement-modal-subheading">
            <?= placementWorkflowH($student['registration_no'] ?: '-') ?> | <?= placementWorkflowH($student['program_name'] ?: '-') ?>
        </div>
    </div>

    <?php if (!$interviews): ?>
        <div class="placement-empty-card">No interviews added for this student yet.</div>
    <?php else: ?>
        <div class="placement-list-wrap">
            <?php foreach ($interviews as $interview): ?>
                <button type="button" class="placement-interview-card js-open-interview-detail" data-interview-id="<?= (int) $interview['id'] ?>">
                    <div class="placement-interview-card-top">
                        <div class="placement-interview-company"><?= placementWorkflowH($interview['company_name'] ?: '-') ?></div>
                        <span class="placement-status placement-status-<?= placementWorkflowH($interview['status'] ?: 'scheduled') ?>">
                            <?= placementWorkflowH(ucwords(str_replace('_', ' ', (string) ($interview['status'] ?? 'scheduled')))) ?>
                        </span>
                    </div>
                    <div class="placement-interview-meta">
                        <?= placementWorkflowH($interview['interview_date'] ?: '-') ?>
                        <?php if (!empty($interview['interview_time'])): ?>
                            | <?= placementWorkflowH($interview['interview_time']) ?>
                        <?php endif; ?>
                        | <?= placementWorkflowH($interview['interview_mode'] ?: '-') ?>
                    </div>
                    <div class="placement-interview-meta"><?= placementWorkflowH($interview['remarks'] ?: 'No remarks yet') ?></div>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php

    return (string) ob_get_clean();
}

function renderPlacementInterviewDetailHtml(array $interview): string
{
    ob_start();
    ?>
    <form method="POST" id="placementInterviewUpdateForm">
        <input type="hidden" name="csrf_token" value="<?= placementWorkflowH(generateCSRF()) ?>">
        <input type="hidden" name="update_placement_interview" value="1">
        <input type="hidden" name="interview_id" value="<?= (int) $interview['id'] ?>">

        <div class="placement-detail-grid">
            <div class="placement-detail-card">
                <div class="placement-label">Student</div>
                <div class="placement-value"><?= placementWorkflowH($interview['enquiry_snapshot_name'] ?: '-') ?></div>
                <div class="placement-sub"><?= placementWorkflowH($interview['registration_no'] ?: '-') ?> | <?= placementWorkflowH($interview['program_name'] ?: '-') ?></div>
            </div>
            <div class="placement-detail-card">
                <div class="placement-label">Company</div>
                <div class="placement-value"><?= placementWorkflowH($interview['company_name'] ?: '-') ?></div>
                <div class="placement-sub">
                    <?= placementWorkflowH($interview['interview_date'] ?: '-') ?>
                    <?php if (!empty($interview['interview_time'])): ?>
                        | <?= placementWorkflowH($interview['interview_time']) ?>
                    <?php endif; ?>
                    | <?= placementWorkflowH($interview['interview_mode'] ?: '-') ?>
                </div>
            </div>
        </div>

        <div class="placement-form-grid" style="margin-top:14px;">
            <div>
                <label>Interview Status</label>
                <select name="status" required>
                    <?php foreach (['scheduled' => 'Scheduled', 'attended' => 'Attended', 'selected' => 'Selected', 'rejected' => 'Rejected', 'on_hold' => 'On Hold'] as $key => $label): ?>
                        <option value="<?= placementWorkflowH($key) ?>" <?= ($interview['status'] ?? '') === $key ? 'selected' : '' ?>><?= placementWorkflowH($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="placement-form-full">
                <label>Remarks</label>
                <textarea name="remarks" rows="5" placeholder="Update interview result, comments, or next steps"><?= placementWorkflowH($interview['remarks'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="placement-form-actions">
            <button type="submit" class="btn btn-primary">Update Interview</button>
        </div>
    </form>
    <?php

    return (string) ob_get_clean();
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = 0;

try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

$hrWorkflowReady = hrWorkflowTableExistsShared($pdo);
$placementTableReady = placementInterviewTableExistsShared($pdo);
$canAllBranchesBool = $canAllBranches === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_placement_interview'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid request. Please refresh and try again.');
        redirect('index.php?page=interviews/placement');
    } elseif (!$placementTableReady) {
        setFlash('error', 'Placement interview table is missing. Run mock_interview_placement_workflow.sql first.');
        redirect('index.php?page=interviews/placement');
    } else {
        try {
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $companyName = trim((string) ($_POST['company_name'] ?? ''));
            $interviewDate = trim((string) ($_POST['interview_date'] ?? ''));
            $interviewTime = trim((string) ($_POST['interview_time'] ?? ''));
            $interviewMode = trim((string) ($_POST['interview_mode'] ?? 'Offline'));
            $status = trim((string) ($_POST['status'] ?? 'scheduled'));
            $remarks = trim((string) ($_POST['remarks'] ?? ''));

            if ($companyName === '') {
                throw new RuntimeException('Company name is required.');
            }
            if ($interviewDate === '') {
                throw new RuntimeException('Interview date is required.');
            }
            if (!in_array($interviewMode, ['Online', 'Offline'], true)) {
                throw new RuntimeException('Invalid interview mode selected.');
            }
            if (!in_array($status, ['scheduled', 'attended', 'selected', 'rejected', 'on_hold'], true)) {
                throw new RuntimeException('Invalid placement status selected.');
            }

            $studentRow = placementWorkflowCanAccessRegistration($pdo, $registrationId, $branchId, $canAllBranchesBool);
            if (!$studentRow) {
                throw new RuntimeException('Student not found in the HR interview pipeline.');
            }

            $pdo->beginTransaction();

            $st = $pdo->prepare("
                INSERT INTO placement_interviews (
                    registration_id,
                    branch_id,
                    hr_workflow_id,
                    company_name,
                    interview_date,
                    interview_time,
                    interview_mode,
                    status,
                    remarks,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");
            $st->execute([
                $registrationId,
                (int) $studentRow['branch_id'],
                (int) ($studentRow['hr_workflow_id'] ?? 0) ?: null,
                $companyName,
                $interviewDate,
                $interviewTime !== '' ? $interviewTime : null,
                $interviewMode,
                $status,
                $remarks !== '' ? $remarks : null,
                $userId,
                $userId,
            ]);

            $st = $pdo->prepare("
                UPDATE student_hr_interviews
                SET company_name = ?,
                    interview_date = ?,
                    interview_status = ?,
                    rejection_reason = ?,
                    hr_updated_by = ?,
                    updated_at = NOW()
                WHERE registration_id = ?
                LIMIT 1
            ");
            $st->execute([
                $companyName,
                $interviewDate,
                $status,
                $status === 'rejected' ? ($remarks !== '' ? $remarks : 'Rejected in placement workflow') : null,
                $userId,
                $registrationId,
            ]);

            $pdo->commit();
            setFlash('success', 'Interview added successfully.');
            redirect('index.php?page=interviews/placement');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            redirect('index.php?page=interviews/placement');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_placement_interview'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid request. Please refresh and try again.');
        redirect('index.php?page=interviews/placement');
    } elseif (!$placementTableReady) {
        setFlash('error', 'Placement interview table is missing. Run mock_interview_placement_workflow.sql first.');
        redirect('index.php?page=interviews/placement');
    } else {
        try {
            $interviewId = (int) ($_POST['interview_id'] ?? 0);
            $status = trim((string) ($_POST['status'] ?? 'scheduled'));
            $remarks = trim((string) ($_POST['remarks'] ?? ''));

            if (!in_array($status, ['scheduled', 'attended', 'selected', 'rejected', 'on_hold'], true)) {
                throw new RuntimeException('Invalid interview status selected.');
            }

            $interviewRow = placementWorkflowCanAccessInterview($pdo, $interviewId, $branchId, $canAllBranchesBool);
            if (!$interviewRow) {
                throw new RuntimeException('Interview not found or access denied.');
            }

            $pdo->beginTransaction();

            $st = $pdo->prepare("
                UPDATE placement_interviews
                SET status = ?,
                    remarks = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $st->execute([
                $status,
                $remarks !== '' ? $remarks : null,
                $userId,
                $interviewId,
            ]);

            $st = $pdo->prepare("
                UPDATE student_hr_interviews
                SET company_name = ?,
                    interview_date = ?,
                    interview_status = ?,
                    rejection_reason = ?,
                    hr_updated_by = ?,
                    updated_at = NOW()
                WHERE registration_id = ?
                LIMIT 1
            ");
            $st->execute([
                $interviewRow['company_name'],
                $interviewRow['interview_date'],
                $status,
                $status === 'rejected' ? ($remarks !== '' ? $remarks : 'Rejected in placement workflow') : null,
                $userId,
                (int) $interviewRow['registration_id'],
            ]);

            $pdo->commit();
            setFlash('success', 'Interview updated successfully.');
            redirect('index.php?page=interviews/placement');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            redirect('index.php?page=interviews/placement');
        }
    }
}

$isAjax = isset($_GET['ajax']) && (int) $_GET['ajax'] === 1;
if ($isAjax) {
    $action = trim((string) ($_GET['action'] ?? ''));

    try {
        if (!$hrWorkflowReady) {
            throw new RuntimeException('HR workflow is not ready.');
        }

        if ($action === 'add_form') {
            $registrationId = (int) ($_GET['registration_id'] ?? 0);
            $student = placementWorkflowCanAccessRegistration($pdo, $registrationId, $branchId, $canAllBranchesBool);
            if (!$student) {
                throw new RuntimeException('Student not found in the HR queue.');
            }
            ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= placementWorkflowH(generateCSRF()) ?>">
                <input type="hidden" name="save_placement_interview" value="1">
                <input type="hidden" name="registration_id" value="<?= (int) $student['id'] ?>">
                <div class="placement-modal-copy">
                    <div class="placement-modal-heading"><?= placementWorkflowH($student['enquiry_snapshot_name'] ?: '-') ?></div>
                    <div class="placement-modal-subheading"><?= placementWorkflowH($student['registration_no'] ?: '-') ?> | <?= placementWorkflowH($student['program_name'] ?: '-') ?></div>
                </div>
                <div class="placement-form-grid">
                    <div>
                        <label>Company Name</label>
                        <input type="text" name="company_name" required>
                    </div>
                    <div>
                        <label>Interview Date</label>
                        <input type="date" name="interview_date" required>
                    </div>
                    <div>
                        <label>Interview Time</label>
                        <input type="time" name="interview_time">
                    </div>
                    <div>
                        <label>Mode</label>
                        <select name="interview_mode">
                            <option value="Offline">Offline</option>
                            <option value="Online">Online</option>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="scheduled">Scheduled</option>
                            <option value="attended">Attended</option>
                            <option value="selected">Selected</option>
                            <option value="rejected">Rejected</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    <div class="placement-form-full">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="4" placeholder="Interview feedback, next steps, or comments"></textarea>
                    </div>
                </div>
                <div class="placement-form-actions">
                    <button type="submit" class="btn btn-primary">Save Interview</button>
                </div>
            </form>
            <?php
            exit;
        }

        if ($action === 'interview_list') {
            if (!$placementTableReady) {
                throw new RuntimeException('Placement interview table is missing.');
            }

            $registrationId = (int) ($_GET['registration_id'] ?? 0);
            $student = placementWorkflowCanAccessRegistration($pdo, $registrationId, $branchId, $canAllBranchesBool);
            if (!$student) {
                throw new RuntimeException('Student not found in the HR queue.');
            }

            $params = [$registrationId];
            $sql = "SELECT id, company_name, interview_date, interview_time, interview_mode, status, remarks FROM placement_interviews WHERE registration_id = ?";
            if (!$canAllBranchesBool && $branchId > 0) {
                $sql .= " AND branch_id = ?";
                $params[] = $branchId;
            }
            $sql .= " ORDER BY interview_date DESC, id DESC";

            $st = $pdo->prepare($sql);
            $st->execute($params);
            echo renderPlacementInterviewListHtml($student, $st->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }

        if ($action === 'interview_detail') {
            if (!$placementTableReady) {
                throw new RuntimeException('Placement interview table is missing.');
            }

            $interviewId = (int) ($_GET['interview_id'] ?? 0);
            $interview = placementWorkflowCanAccessInterview($pdo, $interviewId, $branchId, $canAllBranchesBool);
            if (!$interview) {
                throw new RuntimeException('Interview not found.');
            }

            echo renderPlacementInterviewDetailHtml($interview);
            exit;
        }

        throw new RuntimeException('Invalid request.');
    } catch (Exception $e) {
        echo '<div class="placement-empty-card">' . placementWorkflowH($e->getMessage()) . '</div>';
        exit;
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$rows = [];

if ($hrWorkflowReady) {
    $where = [
        "r.reg_type = 'course'",
    ];
    $params = [];

    if (!$canAllBranchesBool && $branchId > 0) {
        $where[] = "shi.branch_id = ?";
        $params[] = $branchId;
    }

    if ($status !== '' && in_array($status, ['pending', 'scheduled', 'selected', 'rejected', 'on_hold', 'attended'], true)) {
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

    $placementJoin = $placementTableReady
        ? "LEFT JOIN (
                SELECT registration_id, COUNT(*) AS placement_count, MAX(interview_date) AS last_interview_date
                FROM placement_interviews
                GROUP BY registration_id
           ) pi ON pi.registration_id = r.id"
        : "";
    $placementSelect = $placementTableReady
        ? "COALESCE(pi.placement_count, 0) AS placement_count, pi.last_interview_date,"
        : "0 AS placement_count, NULL AS last_interview_date,";

    $sql = "
        SELECT
            r.id AS registration_id,
            r.registration_no,
            r.enquiry_snapshot_name,
            r.enquiry_snapshot_phone,
            r.enquiry_snapshot_email,
            r.program_name,
            r.batch_name,
            shi.interview_status,
            shi.company_name,
            shi.interview_date,
            mi.mock_average,
            a.average_marks AS assessment_average,
            {$placementSelect}
            u.name AS staff_name
        FROM student_hr_interviews shi
        INNER JOIN registrations r ON r.id = shi.registration_id
        LEFT JOIN mock_interviews mi ON mi.registration_id = r.id
        LEFT JOIN assessment a ON a.registration_id = r.id
        LEFT JOIN users u ON u.id = shi.staff_user_id
        {$placementJoin}
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(pi.last_interview_date, shi.interview_date, r.id) DESC, r.id DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h2 style="margin-bottom:20px;">Interview Status</h2>

<div class="card">
    <div class="card-header">Interview Status</div>
    <?php if (!$hrWorkflowReady): ?>
        <div class="placement-alert placement-alert-warning" style="margin-top:14px;">
            HR interview workflow table is missing. Run <b>mock_interview_placement_workflow.sql</b> first.
        </div>
    <?php elseif (!$placementTableReady): ?>
        <div class="placement-alert placement-alert-warning" style="margin-top:14px;">
            Placement interview table is missing. Run <b>mock_interview_placement_workflow.sql</b> first.
        </div>
    <?php else: ?>
        <form method="GET" action="index.php" style="padding:14px;">
            <input type="hidden" name="page" value="interviews/placement">
            <div class="placement-filter-row">
                <div>
                    <label>Search</label>
                    <input type="text" name="q" value="<?= placementWorkflowH($q) ?>" placeholder="Registration, student, program, company">
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="attended" <?= $status === 'attended' ? 'selected' : '' ?>>Attended</option>
                        <option value="selected" <?= $status === 'selected' ? 'selected' : '' ?>>Selected</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                    </select>
                </div>
                <div class="placement-filter-actions">
                    <button class="btn btn-primary">Apply</button>
                    <a href="index.php?page=interviews/placement" class="btn" style="background:#f3f4f6;">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive" style="padding:0 14px 14px;">
            <table class="table placement-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Scores</th>
                        <th>Current HR Status</th>
                        <th>Placement Interviews</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="5" class="placement-empty">No students sent to HR are ready for placement yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <div class="placement-primary"><?= placementWorkflowH($row['enquiry_snapshot_name'] ?: '-') ?></div>
                                    <div class="placement-sub"><?= placementWorkflowH($row['registration_no'] ?: '-') ?> | <?= placementWorkflowH($row['program_name'] ?: '-') ?></div>
                                    <div class="placement-sub"><?= placementWorkflowH(visibleStudentContactPair($row['enquiry_snapshot_phone'] ?? '', $row['enquiry_snapshot_email'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <div class="placement-sub">Mock: <?= placementWorkflowH(isset($row['mock_average']) && $row['mock_average'] !== null ? number_format((float) $row['mock_average'], 2, '.', '') : '-') ?></div>
                                    <div class="placement-sub">Assessment: <?= placementWorkflowH(isset($row['assessment_average']) && $row['assessment_average'] !== null ? number_format((float) $row['assessment_average'], 2, '.', '') : '-') ?></div>
                                </td>
                                <td>
                                    <span class="placement-status placement-status-<?= placementWorkflowH($row['interview_status'] ?: 'pending') ?>">
                                        <?= placementWorkflowH(ucwords(str_replace('_', ' ', $row['interview_status'] ?: 'pending'))) ?>
                                    </span>
                                    <div class="placement-sub"><?= placementWorkflowH($row['company_name'] ?: 'No company scheduled yet') ?></div>
                                </td>
                                <td>
                                    <div class="placement-primary"><?= (int) ($row['placement_count'] ?? 0) ?> interview(s)</div>
                                    <div class="placement-sub"><?= placementWorkflowH($row['last_interview_date'] ?: 'No placement interview yet') ?></div>
                                </td>
                                <td>
                                    <div class="placement-action-stack">
                                        <button type="button" class="btn btn-primary js-placement-add" data-registration-id="<?= (int) $row['registration_id'] ?>" data-student-name="<?= placementWorkflowH($row['enquiry_snapshot_name'] ?: '-') ?>">
                                            Add Interview
                                        </button>
                                        <button type="button" class="btn placement-view-btn js-placement-view" data-registration-id="<?= (int) $row['registration_id'] ?>" data-student-name="<?= placementWorkflowH($row['enquiry_snapshot_name'] ?: '-') ?>">
                                            View Interviews
                                        </button>
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

<div class="placement-modal-backdrop" id="placementModalBackdrop"></div>

<div class="placement-modal" id="placementAddModal">
    <div class="placement-modal-head">
        <div>
            <div class="placement-modal-title">Add Interview</div>
            <div class="placement-modal-subtitle" id="placementAddSubtitle">Loading student details...</div>
        </div>
        <button type="button" class="placement-close" data-close-modal="placementAddModal">&times;</button>
    </div>
    <div class="placement-modal-body" id="placementAddBody"><div class="placement-empty-card">Loading...</div></div>
</div>

<div class="placement-modal" id="placementListModal">
    <div class="placement-modal-head">
        <div>
            <div class="placement-modal-title">Interview List</div>
            <div class="placement-modal-subtitle" id="placementListSubtitle">Loading interviews...</div>
        </div>
        <button type="button" class="placement-close" data-close-modal="placementListModal">&times;</button>
    </div>
    <div class="placement-modal-body" id="placementListBody"><div class="placement-empty-card">Loading...</div></div>
</div>

<div class="placement-modal placement-modal-narrow" id="placementDetailModal">
    <div class="placement-modal-head">
        <div>
            <div class="placement-modal-title">Interview Details</div>
            <div class="placement-modal-subtitle">Review and update status</div>
        </div>
        <button type="button" class="placement-close" data-close-modal="placementDetailModal">&times;</button>
    </div>
    <div class="placement-modal-body" id="placementDetailBody"><div class="placement-empty-card">Loading...</div></div>
</div>

<style>
.placement-alert{padding:14px 16px;border-radius:14px;font-weight:600;}
.placement-alert-warning{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa;}
.placement-filter-row{display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:end;}
.placement-filter-actions,.placement-action-stack,.placement-form-actions{display:flex;gap:8px;flex-wrap:wrap;}
.placement-table th,.placement-table td{white-space:normal;vertical-align:top;}
.placement-primary{font-weight:800;color:#1f2937;}
.placement-sub{margin-top:4px;color:#64748b;font-size:12px;}
.placement-empty{text-align:center;color:#64748b;font-weight:600;padding:16px;}
.placement-empty-card{padding:18px;border-radius:16px;background:#f8fafc;color:#64748b;font-weight:600;}
.placement-status{display:inline-flex;align-items:center;justify-content:center;min-width:100px;padding:6px 12px;border-radius:999px;font-weight:800;font-size:12px;}
.placement-status-pending{background:#fef3c7;color:#92400e;}
.placement-status-scheduled{background:#dbeafe;color:#1d4ed8;}
.placement-status-attended{background:#e0f2fe;color:#0369a1;}
.placement-status-selected{background:#dcfce7;color:#15803d;}
.placement-status-rejected{background:#fee2e2;color:#b91c1c;}
.placement-status-on_hold{background:#ede9fe;color:#6d28d9;}
.placement-view-btn{background:#fff7ed;color:#c2410c;border:1px solid #fdba74;}
.placement-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;z-index:9998;}
.placement-modal{position:fixed;inset:28px 40px;background:#fff;border-radius:22px;display:none;z-index:9999;overflow:hidden;box-shadow:0 20px 60px rgba(15,23,42,.28);}
.placement-modal-narrow{inset:50px auto;width:min(780px, calc(100vw - 32px));left:50%;transform:translateX(-50%);}
.placement-modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:18px 22px;border-bottom:1px solid #e5e7eb;background:#fff7fb;}
.placement-modal-title{font-size:20px;font-weight:900;color:#111827;}
.placement-modal-subtitle{margin-top:4px;color:#64748b;font-size:13px;}
.placement-close{border:none;background:transparent;font-size:28px;line-height:1;color:#64748b;cursor:pointer;}
.placement-modal-body{padding:18px 22px;overflow:auto;height:calc(100% - 84px);}
.placement-modal-copy{margin-bottom:16px;}
.placement-modal-heading{font-size:18px;font-weight:900;color:#111827;}
.placement-modal-subheading{margin-top:6px;color:#64748b;font-size:13px;}
.placement-form-grid,.placement-detail-grid{display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:12px;}
.placement-form-full{grid-column:1 / -1;}
.placement-form-grid input,.placement-form-grid select,.placement-form-grid textarea{width:100%;border:1px solid #e5e7eb;border-radius:12px;padding:11px 12px;background:#fff;}
.placement-form-grid label{display:block;font-weight:700;margin-bottom:6px;color:#475569;}
.placement-detail-card{background:#fff;border:1px solid #f3e8ef;border-radius:16px;padding:14px;}
.placement-label{font-size:12px;font-weight:800;color:#9d174d;text-transform:uppercase;letter-spacing:.04em;}
.placement-value{margin-top:7px;font-size:20px;font-weight:900;color:#111827;}
.placement-list-wrap{display:flex;flex-direction:column;gap:12px;}
.placement-interview-card{width:100%;text-align:left;border:1px solid #f3e8ef;border-radius:16px;padding:14px 16px;background:#fff;cursor:pointer;}
.placement-interview-card:hover{border-color:#f9a8d4;box-shadow:0 10px 24px rgba(236,72,153,.08);}
.placement-interview-card-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;}
.placement-interview-company{font-size:16px;font-weight:900;color:#111827;}
.placement-interview-meta{margin-top:6px;color:#64748b;font-size:13px;line-height:1.5;}
@media (max-width: 1000px){
    .placement-filter-row,.placement-form-grid,.placement-detail-grid{grid-template-columns:1fr;}
    .placement-modal{inset:20px 16px;}
    .placement-modal-narrow{width:auto;inset:20px 16px;left:auto;transform:none;}
}
</style>

<script>
(function(){
    const backdrop = document.getElementById('placementModalBackdrop');
    const addModal = document.getElementById('placementAddModal');
    const listModal = document.getElementById('placementListModal');
    const detailModal = document.getElementById('placementDetailModal');
    const addBody = document.getElementById('placementAddBody');
    const listBody = document.getElementById('placementListBody');
    const detailBody = document.getElementById('placementDetailBody');
    const addSubtitle = document.getElementById('placementAddSubtitle');
    const listSubtitle = document.getElementById('placementListSubtitle');

    function showModal(modal) {
        backdrop.style.display = 'block';
        modal.style.display = 'block';
    }

    function hideModal(modal) {
        modal.style.display = 'none';
        if (![addModal, listModal, detailModal].some(function(item){ return item.style.display === 'block'; })) {
            backdrop.style.display = 'none';
        }
    }

    function resetBody(node) {
        node.innerHTML = '<div class="placement-empty-card">Loading...</div>';
    }

    async function loadInto(url, bodyNode) {
        const res = await fetch(url);
        bodyNode.innerHTML = await res.text();
        bindInterviewListButtons();
    }

    async function openAddModal(registrationId, studentName) {
        addSubtitle.textContent = studentName || 'Loading student details...';
        resetBody(addBody);
        showModal(addModal);
        await loadInto('index.php?page=interviews/placement&ajax=1&action=add_form&registration_id=' + encodeURIComponent(registrationId), addBody);
    }

    async function openListModal(registrationId, studentName) {
        listSubtitle.textContent = studentName || 'Loading interviews...';
        resetBody(listBody);
        showModal(listModal);
        await loadInto('index.php?page=interviews/placement&ajax=1&action=interview_list&registration_id=' + encodeURIComponent(registrationId), listBody);
    }

    async function openDetailModal(interviewId) {
        resetBody(detailBody);
        showModal(detailModal);
        await loadInto('index.php?page=interviews/placement&ajax=1&action=interview_detail&interview_id=' + encodeURIComponent(interviewId), detailBody);
    }

    function bindInterviewListButtons() {
        document.querySelectorAll('.js-open-interview-detail').forEach(function(btn){
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function(){
                openDetailModal(this.getAttribute('data-interview-id'));
            });
        });
    }

    document.querySelectorAll('.js-placement-add').forEach(function(btn){
        btn.addEventListener('click', function(){
            openAddModal(this.getAttribute('data-registration-id'), this.getAttribute('data-student-name'));
        });
    });

    document.querySelectorAll('.js-placement-view').forEach(function(btn){
        btn.addEventListener('click', function(){
            openListModal(this.getAttribute('data-registration-id'), this.getAttribute('data-student-name'));
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach(function(btn){
        btn.addEventListener('click', function(){
            const modal = document.getElementById(this.getAttribute('data-close-modal'));
            hideModal(modal);
        });
    });

    backdrop.addEventListener('click', function(){
        hideModal(addModal);
        hideModal(listModal);
        hideModal(detailModal);
    });

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            hideModal(addModal);
            hideModal(listModal);
            hideModal(detailModal);
        }
    });
})();
</script>
