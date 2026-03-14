<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('assessment');
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

function assessmentTableExists(PDO $pdo): bool
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

function assessmentToNull($value): ?float
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        throw new InvalidArgumentException('Assessment marks must be numeric.');
    }

    $number = (float) $value;
    if ($number < 0 || $number > 100) {
        throw new InvalidArgumentException('Assessment marks must be between 0 and 100.');
    }

    return round($number, 2);
}

function calculateAssessmentAverage(?float $mark1, ?float $mark2, ?float $mark3): ?float
{
    $marks = array_values(array_filter([$mark1, $mark2, $mark3], static function ($value) {
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

$tableReady = assessmentTableExists($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assessment'])) {
    if (!$tableReady) {
        setFlash('error', 'Assessment table not found. Run assessment_table.sql first.');
        redirect('index.php?page=assessment');
    } else {
        try {
            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $mark1 = assessmentToNull($_POST['assessment_1'] ?? '');
            $mark2 = assessmentToNull($_POST['assessment_2'] ?? '');
            $mark3 = assessmentToNull($_POST['assessment_3'] ?? '');
            $average = calculateAssessmentAverage($mark1, $mark2, $mark3);

            if ($registrationId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }

            $studentSql = "
                SELECT id, branch_id
                FROM registrations
                WHERE id = ?
                  AND assigned_to = ?
                  AND reg_type = 'course'
                  AND registration_status IN ('active','completed')
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
                INSERT INTO assessment (
                    registration_id,
                    branch_id,
                    staff_user_id,
                    assessment_1,
                    assessment_2,
                    assessment_3,
                    average_marks
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    branch_id = VALUES(branch_id),
                    staff_user_id = VALUES(staff_user_id),
                    assessment_1 = VALUES(assessment_1),
                    assessment_2 = VALUES(assessment_2),
                    assessment_3 = VALUES(assessment_3),
                    average_marks = VALUES(average_marks)
            ";

            $st = $pdo->prepare($sql);
            $st->execute([
                $registrationId,
                (int) $studentRow['branch_id'],
                $userId,
                $mark1,
                $mark2,
                $mark3,
                $average,
            ]);

            setFlash('success', 'Assessment marks saved successfully.');
            redirect('index.php?page=assessment');
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
            redirect('index.php?page=assessment');
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
            redirect('index.php?page=assessment');
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$where = [
    "r.assigned_to = ?",
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
            a.assessment_1,
            a.assessment_2,
            a.assessment_3,
            a.average_marks
        FROM registrations r
        LEFT JOIN assessment a ON a.registration_id = r.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if ($tableReady) {
        setFlash('error', 'Unable to load assessment records: ' . $e->getMessage());
        redirect('index.php?page=assessment');
    }
}
?>

<div class="card">
    <div class="card-header">Student Assessments</div>
    <div class="card-body">
        <?php if (!$tableReady): ?>
            <div class="assessment-alert assessment-alert-warning">
                Assessment table is missing. Run <b>assessment_table.sql</b> before using this page.
            </div>
        <?php endif; ?>

        <form method="GET" action="index.php" class="assessment-filter">
            <input type="hidden" name="page" value="assessment">
            <div class="assessment-filter-row">
                <div class="assessment-field">
                    <label>Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Registration, student, program, batch">
                </div>
                <div class="assessment-filter-actions">
                    <button class="btn btn-primary" type="submit">Apply</button>
                    <a href="index.php?page=assessment" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>

        <div class="assessment-note">
            Enter marks for the three assessments conducted for each assigned course student. Average updates automatically from the entered marks.
        </div>

        <div class="table-wrap">
            <table class="assessment-table">
                <thead>
                    <tr>
                        <th>Registration</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Assessment 1</th>
                        <th>Assessment 2</th>
                        <th>Assessment 3</th>
                        <th>Average</th>
                        <th class="text-center">Save</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8" class="assessment-empty">No assigned course students found.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $r): ?>
                        <?php
                        $avgDisplay = $r['average_marks'] !== null
                            ? number_format((float) $r['average_marks'], 2)
                            : '-';
                        $formId = 'assessment-form-' . (int) $r['id'];
                        ?>
                        <tr>
                            <td>
                                <div class="assessment-primary"><?= h($r['registration_no'] ?: ('REG-' . $r['id'])) ?></div>
                                <div class="assessment-sub"><?= h($r['joined_on'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div class="assessment-primary"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                <div class="assessment-sub"><?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                            </td>
                            <td>
                                <div><?= h($r['program_name'] ?: '-') ?></div>
                                <div class="assessment-sub"><?= h($r['batch_name'] ?: '-') ?></div>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" name="assessment_1" form="<?= h($formId) ?>" class="assessment-input js-assessment-mark" value="<?= h($r['assessment_1'] ?? '') ?>" placeholder="0-100">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" name="assessment_2" form="<?= h($formId) ?>" class="assessment-input js-assessment-mark" value="<?= h($r['assessment_2'] ?? '') ?>" placeholder="0-100">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" max="100" name="assessment_3" form="<?= h($formId) ?>" class="assessment-input js-assessment-mark" value="<?= h($r['assessment_3'] ?? '') ?>" placeholder="0-100">
                            </td>
                            <td>
                                <span class="assessment-average js-assessment-average"><?= h($avgDisplay) ?></span>
                            </td>
                            <td class="text-center">
                                <form method="POST" id="<?= h($formId) ?>" class="assessment-row-form">
                                    <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" name="save_assessment" value="1" class="btn btn-primary assessment-save-btn">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.assessment-filter-row{
    display:flex;
    gap:16px;
    align-items:flex-end;
    flex-wrap:wrap;
}

.assessment-field{
    min-width:280px;
    flex:1 1 320px;
}

.assessment-field label{
    display:block;
    margin-bottom:6px;
    font-weight:700;
    color:#374151;
}

.assessment-field input{
    width:100%;
    min-width:250px;
    padding:10px 12px;
    border:1px solid #ddd;
    border-radius:8px;
    background:#fff;
}

.assessment-filter-actions{
    display:flex;
    gap:10px;
    align-items:center;
}

.assessment-note{
    margin:14px 16px 0;
    padding:12px 14px;
    border-radius:10px;
    background:#f8fafc;
    border:1px solid #e5e7eb;
    color:#475569;
    font-weight:600;
}

.assessment-alert{
    padding:12px 14px;
    border-radius:10px;
    margin:0 16px 14px;
    font-weight:600;
}

.assessment-alert-warning{
    background:#fff8e8;
    border:1px solid #f2ddb0;
    color:#8a5b00;
}

.table-wrap{
    padding:16px;
}

.assessment-table{
    width:100%;
    border-collapse:collapse;
}

.assessment-table th{
    background:#f5f6fa;
    padding:14px;
    text-align:left;
    font-weight:700;
    color:#111827;
    white-space:nowrap;
}

.assessment-table td{
    padding:14px;
    border-bottom:1px solid #eee;
    vertical-align:middle;
}

.assessment-table tbody tr:hover{
    background:#fafbfc;
}

.assessment-primary{
    font-weight:700;
    color:#111827;
}

.assessment-sub{
    font-size:12px;
    color:#6b7280;
}

.assessment-input{
    width:100%;
    min-width:92px;
    padding:10px 12px;
    border:1px solid #d1d5db;
    border-radius:8px;
    background:#fff;
    transition:border-color .2s ease, box-shadow .2s ease;
}

.assessment-input:focus{
    outline:none;
    border-color:#ec407a;
    box-shadow:0 0 0 3px rgba(236, 64, 122, .12);
}

.assessment-average{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:72px;
    padding:7px 12px;
    border-radius:999px;
    background:#e8f4fd;
    color:#1565c0;
    font-weight:700;
}

.assessment-row-form{
    margin:0;
}

.assessment-save-btn{
    min-width:84px;
    border-radius:8px;
}

.assessment-empty{
    text-align:center;
    padding:24px 12px;
    color:#6b7280;
    font-weight:600;
}

@media (max-width: 1100px){
    .table-wrap{
        overflow-x:auto;
    }

    .assessment-table{
        min-width:980px;
    }
}
</style>

<script>
document.querySelectorAll('.assessment-table tr').forEach(function(row){
    var inputs = row.querySelectorAll('.js-assessment-mark');
    var avgNode = row.querySelector('.js-assessment-average');
    if (!inputs.length || !avgNode) {
        return;
    }

    function updateAverage() {
        var marks = [];
        inputs.forEach(function(input){
            var value = input.value.trim();
            if (value === '') {
                return;
            }

            var number = parseFloat(value);
            if (!isNaN(number)) {
                marks.push(number);
            }
        });

        if (!marks.length) {
            avgNode.textContent = '-';
            return;
        }

        var total = marks.reduce(function(sum, item){ return sum + item; }, 0);
        avgNode.textContent = (total / marks.length).toFixed(2);
    }

    inputs.forEach(function(input){
        input.addEventListener('input', updateAverage);
    });
});
</script>
