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
    echo "<div style='padding:20px;font-family:Poppins,sans-serif'>
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
$csrfToken = generateCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assessment'])) {
    if (!$tableReady) {
        setFlash('error', 'Assessment table not found. Run assessment_table.sql first.');
        redirect('index.php?page=assessment');
    } else {
        try {
            $token = $_POST['csrf_token'] ?? '';
            if (!verifyCSRF($token)) {
                throw new RuntimeException('Invalid CSRF token. Please try again.');
            }

            $registrationId = (int) ($_POST['registration_id'] ?? 0);
            $mark1 = assessmentToNull($_POST['assessment_1'] ?? '');
            $mark2 = assessmentToNull($_POST['assessment_2'] ?? '');
            $mark3 = assessmentToNull($_POST['assessment_3'] ?? '');
            $average = calculateAssessmentAverage($mark1, $mark2, $mark3);

            if ($registrationId <= 0) {
                throw new RuntimeException('Invalid student selected.');
            }

            $studentSql = "
                SELECT
                    r.id,
                    r.branch_id,
                    r.registration_no,
                    r.enquiry_snapshot_name,
                    r.enquiry_snapshot_email,
                    r.program_name,
                    COALESCE(rp.parent_name, e.father_name) AS parent_name,
                    " . crmBuildParentEmailFallbackSelect($pdo, 'rp', 'e') . " AS parent_email
                FROM registrations r
                INNER JOIN registration_courses rc ON rc.registration_id = r.id
                LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
                LEFT JOIN enquiries e ON e.id = r.enquiry_id
                WHERE r.id = ?
                  AND rc.guide_staff_id = ?
                  AND r.reg_type = 'course'
                  AND r.registration_status IN ('active','completed')
            ";
            $studentParams = [$registrationId, $userId];

            if ($canAllBranches !== 1 && $branchId > 0) {
                $studentSql .= " AND r.branch_id = ?";
                $studentParams[] = $branchId;
            }

            $studentSql .= " LIMIT 1";
            $st = $pdo->prepare($studentSql);
            $st->execute($studentParams);
            $studentRow = $st->fetch(PDO::FETCH_ASSOC);

            if (!$studentRow) {
                throw new RuntimeException('Student not found or access denied.');
            }

            $pdo->beginTransaction();

            $findSql = "
                SELECT id
                FROM assessment
                WHERE registration_id = ?
                ORDER BY id DESC
                LIMIT 1
            ";
            $find = $pdo->prepare($findSql);
            $find->execute([$registrationId]);
            $existingId = (int) ($find->fetchColumn() ?: 0);

            if ($existingId > 0) {
                $updateSql = "
                    UPDATE assessment
                    SET branch_id = ?,
                        staff_user_id = ?,
                        assessment_1 = ?,
                        assessment_2 = ?,
                        assessment_3 = ?,
                        average_marks = ?,
                        updated_at = NOW()
                    WHERE id = ?
                    LIMIT 1
                ";
                $update = $pdo->prepare($updateSql);
                $update->execute([
                    (int) $studentRow['branch_id'],
                    $userId,
                    $mark1,
                    $mark2,
                    $mark3,
                    $average,
                    $existingId,
                ]);
            } else {
                $insertSql = "
                    INSERT INTO assessment (
                        registration_id,
                        branch_id,
                        staff_user_id,
                        assessment_1,
                        assessment_2,
                        assessment_3,
                        average_marks
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ";
                $insert = $pdo->prepare($insertSql);
                $insert->execute([
                    $registrationId,
                    (int) $studentRow['branch_id'],
                    $userId,
                    $mark1,
                    $mark2,
                    $mark3,
                    $average,
                ]);
            }

            $pdo->commit();

            $studentDisplayName = trim((string) ($studentRow['enquiry_snapshot_name'] ?? 'Student'));
            $parentDisplayName = trim((string) ($studentRow['parent_name'] ?? '')) !== '' ? trim((string) ($studentRow['parent_name'] ?? '')) : 'Parent';
            $recipients = [
                ['email' => $studentRow['enquiry_snapshot_email'] ?? '', 'name' => $studentDisplayName],
                ['email' => $studentRow['parent_email'] ?? '', 'name' => $parentDisplayName],
            ];
            $htmlBody = '
                <p>Dear Student and Parent,</p>
                <p>The assessment marks for the course student have been updated.</p>
                <p><strong>Student:</strong> ' . h($studentDisplayName) . '<br>
                <strong>Registration No:</strong> ' . h((string) ($studentRow['registration_no'] ?? '')) . '<br>
                <strong>Program:</strong> ' . h((string) ($studentRow['program_name'] ?? '')) . '<br>
                <strong>Assessment 1:</strong> ' . h($mark1 !== null ? number_format((float) $mark1, 2, '.', '') : '-') . '<br>
                <strong>Assessment 2:</strong> ' . h($mark2 !== null ? number_format((float) $mark2, 2, '.', '') : '-') . '<br>
                <strong>Assessment 3:</strong> ' . h($mark3 !== null ? number_format((float) $mark3, 2, '.', '') : '-') . '<br>
                <strong>Average:</strong> ' . h($average !== null ? number_format((float) $average, 2, '.', '') : '-') . '</p>
                <p>Regards,<br>' . h(APP_NAME) . '</p>';
            $textBody = "Dear Student and Parent,\n\n"
                . "The assessment marks for the course student have been updated.\n"
                . "Student: {$studentDisplayName}\n"
                . "Registration No: " . (string) ($studentRow['registration_no'] ?? '') . "\n"
                . "Program: " . (string) ($studentRow['program_name'] ?? '') . "\n"
                . "Assessment 1: " . ($mark1 !== null ? number_format((float) $mark1, 2, '.', '') : '-') . "\n"
                . "Assessment 2: " . ($mark2 !== null ? number_format((float) $mark2, 2, '.', '') : '-') . "\n"
                . "Assessment 3: " . ($mark3 !== null ? number_format((float) $mark3, 2, '.', '') : '-') . "\n"
                . "Average: " . ($average !== null ? number_format((float) $average, 2, '.', '') : '-') . "\n\n"
                . "Regards,\n" . APP_NAME;
            $mailError = null;
            $mailWarning = '';
            if (!crmSendEmail($recipients, 'Assessment marks updated for ' . $studentDisplayName, $htmlBody, $textBody, $mailError)) {
                $mailWarning = ' Email delivery failed';
                if ($mailError) {
                    $mailWarning .= ': ' . $mailError;
                }
                $mailWarning .= '.';
            }

            setFlash('success', 'Assessment marks saved successfully.' . $mailWarning);
            redirect('index.php?page=assessment');
        } catch (InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            redirect('index.php?page=assessment');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            redirect('index.php?page=assessment');
        }
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$averageFilter = trim((string) ($_GET['avg_filter'] ?? ''));
$allowedAverageFilters = ['lt40', '40_60', '60_80', '80_100', 'not_set'];
if (!in_array($averageFilter, $allowedAverageFilters, true)) {
    $averageFilter = '';
}
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

if ($averageFilter === 'lt40') {
    $where[] = "a.average_marks IS NOT NULL AND a.average_marks < 40";
} elseif ($averageFilter === '40_60') {
    $where[] = "a.average_marks IS NOT NULL AND a.average_marks >= 40 AND a.average_marks < 60";
} elseif ($averageFilter === '60_80') {
    $where[] = "a.average_marks IS NOT NULL AND a.average_marks >= 60 AND a.average_marks < 80";
} elseif ($averageFilter === '80_100') {
    $where[] = "a.average_marks IS NOT NULL AND a.average_marks >= 80 AND a.average_marks <= 100";
} elseif ($averageFilter === 'not_set') {
    $where[] = "a.average_marks IS NULL";
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
        INNER JOIN registration_courses rc ON rc.registration_id = r.id
        LEFT JOIN (
            SELECT a1.*
            FROM assessment a1
            INNER JOIN (
                SELECT registration_id, MAX(id) AS latest_id
                FROM assessment
                GROUP BY registration_id
            ) latest_assessment
                ON latest_assessment.latest_id = a1.id
        ) a ON a.registration_id = r.id
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

<div class="payments-dashboard assessment-dashboard">
    <div class="dashboard-header">
        <h2><i class="fas fa-clipboard-check" style="margin-right:12px; color:#e91e63;"></i>Assessment Management</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-database"></i> Total: <?= (int) count($rows) ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-sliders-h" style="margin-right:8px;"></i> Filter Assessments
        </div>
        <div class="card-body">
            <?php if (!$tableReady): ?>
                <div class="assessment-alert assessment-alert-warning">
                    Assessment table is missing. Run <b>assessment_table.sql</b> before using this page.
                </div>
            <?php endif; ?>

            <form method="GET" action="index.php" class="filter-form">
                <input type="hidden" name="page" value="assessment">
                <div class="filter-grid">
                    <div class="filter-item">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Registration, student, program, batch">
                    </div>
                    <div class="filter-item">
                        <label><i class="fas fa-chart-line"></i> Average</label>
                        <select name="avg_filter">
                            <option value="">All Average</option>
                            <option value="lt40" <?= $averageFilter === 'lt40' ? 'selected' : '' ?>>Below 40</option>
                            <option value="40_60" <?= $averageFilter === '40_60' ? 'selected' : '' ?>>40 to 59.99</option>
                            <option value="60_80" <?= $averageFilter === '60_80' ? 'selected' : '' ?>>60 to 79.99</option>
                            <option value="80_100" <?= $averageFilter === '80_100' ? 'selected' : '' ?>>80 to 100</option>
                            <option value="not_set" <?= $averageFilter === 'not_set' ? 'selected' : '' ?>>Not Set</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button class="btn-icon-only apply" type="submit" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="index.php?page=assessment" class="btn-icon-only reset" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                            <i class="fas fa-undo-alt"></i>
                        </a>
                    </div>
                </div>
            </form>

            <div class="assessment-note">
                Enter marks for the three assessments conducted for each assigned course student. Average updates automatically from the entered marks.
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="table-header-flex">
                <div class="table-title"><i class="fas fa-list"></i> Student Assessments</div>
                <div id="datatableControls"></div>
            </div>
        </div>
        <div class="table-wrap">
            <table id="assessmentTable" class="crm-table assessment-table display" style="width:100%;">
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
                                    <form method="POST" id="<?= h($formId) ?>" class="assessment-row-form" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                                        <button
                                            type="submit"
                                            name="save_assessment"
                                            value="1"
                                            class="btn btn-primary assessment-save-btn"
                                            data-modern-tooltip="Save Assessment"
                                            aria-label="Save Assessment">
                                            <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --primary: #e91e63;
        --primary-light: #f8bbd0;
        --primary-dark: #c2185b;
        --gray-300: #dee2e6;
        --gray-700: #495057;
        --gray-800: #343a40;
    }

    .payments-dashboard.assessment-dashboard {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .dashboard-header h2 {
        margin: 0;
        font-size: 28px;
        font-weight: 900;
        color: var(--gray-800);
        letter-spacing: .2px;
    }

    .header-stats {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .stat-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #fff5f9;
        color: var(--primary-dark);
        font-size: 13px;
        font-weight: 800;
        border: 1px solid #f5d6e3;
    }

    .card-header {
        font-weight: 900;
        font-size: 16px;
        color: var(--gray-800);
        border-bottom: 1px solid #f2f2f2;
    }

    .table-header-flex {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
    }

    .table-title {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 900;
        color: var(--gray-800);
    }

    .filter-form {
        padding: 12px 14px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) minmax(180px, 220px) auto;
        gap: 14px;
        align-items: end;
    }

    .filter-item label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 800;
        color: #5f6b7a;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .filter-item input,
    .filter-item select {
        width: 100%;
        border: 1px solid #d7dde5;
        border-radius: 10px;
        min-height: 42px;
        padding: 10px 12px;
        background: #fff;
        outline: none;
        transition: .15s ease;
    }

    .filter-item input:focus,
    .filter-item select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .14);
    }

    .filter-actions {
        display: inline-flex;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }

    .btn-icon-only {
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        cursor: pointer;
        transition: .15s ease;
    }

    .btn-icon-only.apply {
        background: var(--primary);
        color: #fff;
    }

    .btn-icon-only.apply:hover {
        background: var(--primary-dark);
    }

    .btn-icon-only.reset {
        background: #f1f3f5;
        color: var(--primary-dark);
    }

    .btn-icon-only.reset:hover {
        background: #e9ecef;
    }

    .assessment-note {
        margin: 14px 14px 4px;
        padding: 12px 14px;
        border-radius: 12px;
        background: linear-gradient(180deg, #ecf3ff, #e9f1ff);
        border: 1px solid #dbeafe;
        color: #1d4ed8;
        font-weight: 700;
    }

    .assessment-alert {
        padding: 12px 14px;
        border-radius: 10px;
        margin: 0 14px 14px;
        font-weight: 700;
    }

    .assessment-alert-warning {
        background: #fff8e8;
        border: 1px solid #f2ddb0;
        color: #8a5b00;
    }

    .table-wrap {
        padding: 14px;
        overflow-x: auto;
    }
    #datatableControls {
        width: auto;
        margin-left: auto;
        display: flex;
        justify-content: flex-end;
    }
    #datatableControls .dt-top {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: nowrap;
    }
    .dataTables_wrapper .dt-top,
    .dataTables_wrapper .dt-bottom {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .dataTables_wrapper .dt-bottom {
        justify-content: space-between;
        margin-top: 12px;
    }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin: 0;
        display: flex;
        align-items: center;
    }
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        color: #334155;
        margin: 0;
        white-space: nowrap;
    }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid var(--gray-300);
        border-radius: 10px;
        padding: 8px 12px;
        background: #fff;
        min-height: 38px;
        outline: none;
    }
    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .14);
    }
    .dataTables_wrapper .dataTables_filter input {
        min-width: 240px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border: 1px solid #f1d6e3 !important;
        background: #fff !important;
        color: var(--gray-700) !important;
        border-radius: 8px !important;
        padding: 6px 10px !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
        color: #fff !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #fff5f9 !important;
        color: var(--primary-dark) !important;
        border-color: #f1d6e3 !important;
    }
    .dataTables_wrapper .dataTables_info {
        color: #64748b;
        font-weight: 600;
    }

    .crm-table.assessment-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 980px;
    }

    .crm-table.assessment-table th,
    .crm-table.assessment-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
        font-size: 13px;
    }
    .crm-table.assessment-table td:nth-child(4),
    .crm-table.assessment-table td:nth-child(5),
    .crm-table.assessment-table td:nth-child(6),
    .crm-table.assessment-table td:nth-child(7),
    .crm-table.assessment-table td:nth-child(8) {
        text-align: center;
    }

    .crm-table.assessment-table th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .35px;
        font-weight: 800;
        color: var(--gray-800);
        background: #fafbfd;
        white-space: nowrap;
    }

    .crm-table.assessment-table tbody tr:hover {
        background: #fff5f9;
    }

    .assessment-primary {
        font-weight: 800;
        color: #111827;
    }

    .assessment-sub {
        font-size: 12px;
        color: #6b7280;
    }

    .assessment-input {
        width: 100%;
        min-width: 112px;
        height: 42px;
        padding: 9px 14px;
        border: 1px solid #cfd8e3;
        border-radius: 12px;
        background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%);
        box-shadow: inset 0 1px 1px rgba(255, 255, 255, .9), 0 1px 2px rgba(15, 23, 42, .04);
        text-align: right;
        font-weight: 700;
        color: #0f172a;
        transition: border-color .2s ease, box-shadow .2s ease, transform .15s ease;
    }
    .assessment-input::placeholder {
        color: #94a3b8;
        font-weight: 600;
    }

    .assessment-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .13), 0 8px 22px rgba(233, 30, 99, .12);
        transform: translateY(-1px);
    }

    .assessment-average {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 84px;
        height: 34px;
        padding: 6px 14px;
        border-radius: 999px;
        background: linear-gradient(180deg, #e8f4fd, #dff0ff);
        color: #0b61a4;
        font-weight: 800;
        letter-spacing: .2px;
        border: 1px solid #d2e7fb;
    }

    .assessment-row-form {
        margin: 0;
    }

    .assessment-save-btn {
        width: 40px;
        height: 36px;
        padding: 0;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        box-shadow: 0 6px 16px rgba(233, 30, 99, .24);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .assessment-save-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(233, 30, 99, .28);
    }

    .assessment-empty {
        text-align: center;
        padding: 24px 12px;
        color: #6b7280;
        font-weight: 700;
    }

    .modern-tooltip {
        position: fixed;
        left: 0;
        top: 0;
        z-index: 100000;
        pointer-events: none;
        background: linear-gradient(180deg, #0f172a, #1e293b);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .16);
        box-shadow: 0 14px 34px rgba(2, 6, 23, .35);
        border-radius: 10px;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
        opacity: 0;
        transform: translateY(-4px);
        transition: opacity .14s ease, transform .14s ease;
    }

    .modern-tooltip.is-show {
        opacity: 1;
        transform: translateY(0);
    }

    .modern-tooltip::after {
        content: "";
        position: absolute;
        left: 50%;
        bottom: -6px;
        width: 10px;
        height: 10px;
        background: #1e293b;
        border-right: 1px solid rgba(255, 255, 255, .14);
        border-bottom: 1px solid rgba(255, 255, 255, .14);
        transform: translateX(-50%) rotate(45deg);
    }

    @media (max-width: 900px) {
        .dashboard-header h2 {
            font-size: 24px;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .filter-actions {
            justify-content: flex-start;
        }
        #datatableControls {
            width: 100%;
            margin-left: 0;
            justify-content: flex-start;
        }
        #datatableControls .dt-top,
        .dataTables_wrapper .dt-bottom {
            justify-content: flex-start;
        }
        #datatableControls .dt-top {
            flex-wrap: wrap;
        }
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length {
            width: 100%;
        }
        .dataTables_wrapper .dataTables_filter label {
            width: 100%;
        }
        .dataTables_wrapper .dataTables_filter input {
            width: 100% !important;
            min-width: 0;
        }
    }
</style>

<script>
let modernTooltipEl = null;
let modernTooltipTarget = null;

function ensureModernTooltip() {
    if (modernTooltipEl) return modernTooltipEl;
    modernTooltipEl = document.createElement('div');
    modernTooltipEl.className = 'modern-tooltip';
    modernTooltipEl.setAttribute('role', 'tooltip');
    document.body.appendChild(modernTooltipEl);
    return modernTooltipEl;
}

function hideModernTooltip() {
    if (!modernTooltipEl) return;
    modernTooltipEl.classList.remove('is-show');
    modernTooltipTarget = null;
}

function positionModernTooltip(target) {
    if (!modernTooltipEl || !target) return;
    const rect = target.getBoundingClientRect();
    const tipRect = modernTooltipEl.getBoundingClientRect();
    const gap = 10;
    let top = rect.top - tipRect.height - gap;
    if (top < 8) {
        top = rect.bottom + gap;
    }
    let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
    const maxLeft = window.innerWidth - tipRect.width - 8;
    left = Math.max(8, Math.min(left, maxLeft));
    modernTooltipEl.style.top = `${top}px`;
    modernTooltipEl.style.left = `${left}px`;
}

function showModernTooltip(target) {
    const text = (target.getAttribute('data-modern-tooltip') || '').trim();
    if (!text) return;
    const tip = ensureModernTooltip();
    modernTooltipTarget = target;
    tip.textContent = text;
    tip.classList.add('is-show');
    positionModernTooltip(target);
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof crmDataTable !== 'function') return;

    try {
        crmDataTable('#assessmentTable', {
            pageLength: 10,
            lengthMenu: [5, 10, 20, 50, 100],
            ordering: true,
            scrollX: false,
            responsive: false,
            searchPlaceholder: 'Search assessments...',
            columnDefs: [
                { orderable: false, targets: [3, 4, 5, 7] }
            ],
            dom:
                "<'dt-top'lf>" +
                "rt" +
                "<'dt-bottom'ip>"
        });

        setTimeout(function () {
            const controls = document.querySelector('.dt-top');
            const target = document.getElementById('datatableControls');
            if (controls && target) {
                target.appendChild(controls);
            }
        }, 100);
    } catch (e) {}
});

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

document.addEventListener('submit', function (e) {
    const form = e.target && e.target.classList ? e.target : null;
    if (!form || !form.classList.contains('assessment-row-form')) {
        return;
    }

    if (form.dataset.assessmentConfirmed === '1') {
        delete form.dataset.assessmentConfirmed;
        return;
    }

    e.preventDefault();

    const formId = form.getAttribute('id');
    const markInputs = formId
        ? document.querySelectorAll('.js-assessment-mark[form="' + formId + '"]')
        : [];

    let invalidInput = null;
    let invalidMessage = '';

    markInputs.forEach(function (input, index) {
        if (invalidInput) return;
        const raw = (input.value || '').trim();
        if (raw === '') return;

        const value = Number(raw);
        if (Number.isNaN(value)) {
            invalidInput = input;
            invalidMessage = 'Assessment ' + (index + 1) + ' must be a valid number.';
            return;
        }

        if (value < 0 || value > 100) {
            invalidInput = input;
            invalidMessage = 'Assessment ' + (index + 1) + ' must be between 0 and 100.';
        }
    });

    if (invalidInput) {
        if (window.Swal && Swal.fire) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Marks',
                text: invalidMessage,
                confirmButtonColor: '#e91e63'
            }).then(function () {
                invalidInput.focus();
                invalidInput.select();
            });
        } else {
            alert(invalidMessage);
            invalidInput.focus();
            invalidInput.select();
        }
        return;
    }

    if (window.Swal && Swal.fire) {
        Swal.fire({
            icon: 'question',
            title: 'Save Assessment?',
            text: 'Do you want to save these marks?',
            showCancelButton: true,
            confirmButtonText: 'Yes, Save',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#e91e63'
        }).then(function (result) {
            if (result.isConfirmed) {
                submitAssessmentForm(form);
            }
        });
        return;
    }

    submitAssessmentForm(form);
});

function submitAssessmentForm(form) {
    const submitButton = form.querySelector('button[name="save_assessment"]');
    form.dataset.assessmentConfirmed = '1';

    if (typeof form.requestSubmit === 'function' && submitButton) {
        form.requestSubmit(submitButton);
        return;
    }

    if (submitButton && !form.querySelector('input[name="save_assessment"]')) {
        const hiddenSubmit = document.createElement('input');
        hiddenSubmit.type = 'hidden';
        hiddenSubmit.name = 'save_assessment';
        hiddenSubmit.value = submitButton.value || '1';
        form.appendChild(hiddenSubmit);
    }

    HTMLFormElement.prototype.submit.call(form);
}

document.addEventListener('mouseover', function (e) {
    const target = e.target.closest('[data-modern-tooltip]');
    if (!target) {
        hideModernTooltip();
        return;
    }
    showModernTooltip(target);
});

document.addEventListener('mouseout', function (e) {
    const from = e.target.closest('[data-modern-tooltip]');
    if (!from) return;
    const to = e.relatedTarget ? e.relatedTarget.closest('[data-modern-tooltip]') : null;
    if (from !== to) {
        hideModernTooltip();
    }
});

document.addEventListener('focusin', function (e) {
    const target = e.target.closest('[data-modern-tooltip]');
    if (!target) return;
    showModernTooltip(target);
});

document.addEventListener('focusout', function (e) {
    const target = e.target.closest('[data-modern-tooltip]');
    if (!target) return;
    hideModernTooltip();
});

window.addEventListener('scroll', function () {
    if (modernTooltipTarget) {
        positionModernTooltip(modernTooltipTarget);
    }
}, true);

window.addEventListener('resize', function () {
    if (modernTooltipTarget) {
        positionModernTooltip(modernTooltipTarget);
    }
});
</script>
