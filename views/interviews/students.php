<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('interviews/students');
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

function hrWorkflowTableExists(PDO $pdo): bool
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
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$tableReady = hrWorkflowTableExists($pdo);

$canAllBranches = 0;
try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
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
            u.name AS staff_name
        FROM student_hr_interviews shi
        INNER JOIN registrations r ON r.id = shi.registration_id
        LEFT JOIN mock_interviews mi ON mi.registration_id = r.id
        LEFT JOIN assessment a ON a.registration_id = r.id
        LEFT JOIN users u ON u.id = shi.staff_user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY shi.sent_to_hr_at DESC, shi.id DESC
    ";

    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        setFlash('error', 'Unable to load HR students list: ' . $e->getMessage());
        redirect('index.php?page=interviews/students');
    }
}
?>

<h2 style="margin-bottom:20px;">Students Sent To HR</h2>

<div class="card">
    <div class="card-header">Pipeline Students</div>
    <?php if (!$tableReady): ?>
        <div class="hr-alert hr-alert-warning" style="margin-top:14px;">
            HR interview workflow table is missing. Run <b>mock_interview_setup.sql</b> first.
        </div>
    <?php else: ?>
        <form method="GET" action="index.php" style="padding:14px;">
            <input type="hidden" name="page" value="interviews/students">
            <div class="hr-filter-row">
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
                <div class="hr-filter-actions">
                    <button class="btn btn-primary">Apply</button>
                    <a href="index.php?page=interviews/students" class="btn" style="background:#f3f4f6;">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive" style="padding:0 14px 14px;">
            <table class="table hr-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Mock Avg</th>
                        <th>Assessment Avg</th>
                        <th>Sent By</th>
                        <th>Interview</th>
                        <th>Status</th>
                        <th>Rejection Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="hr-empty">No students are in the HR interview pipeline yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <div class="hr-primary"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                    <div class="hr-sub"><?= h($r['registration_no'] ?: '-') ?> | <?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                                </td>
                                <td>
                                    <div class="hr-primary"><?= h($r['program_name'] ?: '-') ?></div>
                                    <div class="hr-sub"><?= h($r['batch_name'] ?: '-') ?></div>
                                </td>
                                <td><?= h(isset($r['mock_average']) && $r['mock_average'] !== null ? number_format((float) $r['mock_average'], 2, '.', '') : '-') ?></td>
                                <td><?= h(isset($r['assessment_average']) && $r['assessment_average'] !== null ? number_format((float) $r['assessment_average'], 2, '.', '') : '-') ?></td>
                                <td>
                                    <div class="hr-primary"><?= h($r['staff_name'] ?: '-') ?></div>
                                    <div class="hr-sub"><?= h(!empty($r['sent_to_hr_at']) ? date('d M Y', strtotime($r['sent_to_hr_at'])) : '-') ?></div>
                                </td>
                                <td>
                                    <div class="hr-primary"><?= h($r['company_name'] ?: '-') ?></div>
                                    <div class="hr-sub"><?= h($r['interview_date'] ?: '-') ?></div>
                                </td>
                                <td>
                                    <span class="hr-status hr-status-<?= h($r['interview_status'] ?: 'pending') ?>">
                                        <?= h(ucwords(str_replace('_', ' ', $r['interview_status'] ?: 'pending'))) ?>
                                    </span>
                                </td>
                                <td><?= h($r['rejection_reason'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.hr-filter-row{
    display:grid;
    grid-template-columns:2fr 1fr auto;
    gap:12px;
    align-items:end;
}

.hr-filter-actions{
    display:flex;
    gap:8px;
}

.hr-table th,
.hr-table td{
    white-space:normal;
}

.hr-primary{
    font-weight:800;
    color:#1f2937;
}

.hr-sub{
    margin-top:4px;
    color:#64748b;
    font-size:12px;
}

.hr-status{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:90px;
    padding:6px 12px;
    border-radius:999px;
    font-weight:800;
    font-size:12px;
}

.hr-status-pending{ background:#fef3c7; color:#92400e; }
.hr-status-scheduled{ background:#dbeafe; color:#1d4ed8; }
.hr-status-selected{ background:#dcfce7; color:#15803d; }
.hr-status-rejected{ background:#fee2e2; color:#b91c1c; }
.hr-status-on_hold{ background:#ede9fe; color:#6d28d9; }

.hr-empty{
    text-align:center;
    color:#64748b;
    font-weight:600;
}

.hr-alert{
    padding:14px 16px;
    border-radius:14px;
    font-weight:600;
}

.hr-alert-warning{
    background:#fff7ed;
    color:#9a3412;
    border:1px solid #fed7aa;
}

@media (max-width: 900px){
    .hr-filter-row{
        grid-template-columns:1fr;
    }
}
</style>
