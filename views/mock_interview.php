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
    echo "<div style='padding:20px;font-family:Poppins,sans-serif'>
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

            $studentDisplayName = trim((string) ($studentRow['enquiry_snapshot_name'] ?? 'Student'));
            $parentDisplayName = trim((string) ($studentRow['parent_name'] ?? '')) !== '' ? trim((string) ($studentRow['parent_name'] ?? '')) : 'Parent';
            $recipients = [
                ['email' => $studentRow['enquiry_snapshot_email'] ?? '', 'name' => $studentDisplayName],
                ['email' => $studentRow['parent_email'] ?? '', 'name' => $parentDisplayName],
            ];
            $htmlBody = '
                <p>Dear Student and Parent,</p>
                <p>The mock interview marks for the course student have been updated.</p>
                <p><strong>Student:</strong> ' . h($studentDisplayName) . '<br>
                <strong>Registration No:</strong> ' . h((string) ($studentRow['registration_no'] ?? '')) . '<br>
                <strong>Program:</strong> ' . h((string) ($studentRow['program_name'] ?? '')) . '<br>
                <strong>Theoretical Marks:</strong> ' . h($theoreticalMarks !== null ? number_format((float) $theoreticalMarks, 2, '.', '') : '-') . '<br>
                <strong>Machine Task Marks:</strong> ' . h($machineTaskMarks !== null ? number_format((float) $machineTaskMarks, 2, '.', '') : '-') . '<br>
                <strong>Mock Average:</strong> ' . h($mockAverage !== null ? number_format((float) $mockAverage, 2, '.', '') : '-') . '</p>
                <p>Regards,<br>' . h(APP_NAME) . '</p>';
            $textBody = "Dear Student and Parent,\n\n"
                . "The mock interview marks for the course student have been updated.\n"
                . "Student: {$studentDisplayName}\n"
                . "Registration No: " . (string) ($studentRow['registration_no'] ?? '') . "\n"
                . "Program: " . (string) ($studentRow['program_name'] ?? '') . "\n"
                . "Theoretical Marks: " . ($theoreticalMarks !== null ? number_format((float) $theoreticalMarks, 2, '.', '') : '-') . "\n"
                . "Machine Task Marks: " . ($machineTaskMarks !== null ? number_format((float) $machineTaskMarks, 2, '.', '') : '-') . "\n"
                . "Mock Average: " . ($mockAverage !== null ? number_format((float) $mockAverage, 2, '.', '') : '-') . "\n\n"
                . "Regards,\n" . APP_NAME;
            $mailError = null;
            $mailWarning = '';
            if (!crmSendEmail($recipients, 'Mock interview marks updated for ' . $studentDisplayName, $htmlBody, $textBody, $mailError)) {
                $mailWarning = ' Email delivery failed';
                if ($mailError) {
                    $mailWarning .= ': ' . $mailError;
                }
                $mailWarning .= '.';
            }

            setFlash('success', 'Mock interview marks saved successfully.' . $mailWarning);
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
$mockAvgFilter = trim((string) ($_GET['mock_avg_filter'] ?? ''));
$assessmentAvgFilter = trim((string) ($_GET['assessment_avg_filter'] ?? ''));
$overallAvgFilter = trim((string) ($_GET['overall_avg_filter'] ?? ''));
$hrSentFilter = trim((string) ($_GET['hr_sent_filter'] ?? ''));
$allowedAverageFilters = ['lt40', '40_60', '60_80', '80_100', 'not_set'];
if (!in_array($mockAvgFilter, $allowedAverageFilters, true)) {
    $mockAvgFilter = '';
}
if (!in_array($assessmentAvgFilter, $allowedAverageFilters, true)) {
    $assessmentAvgFilter = '';
}
if (!in_array($overallAvgFilter, $allowedAverageFilters, true)) {
    $overallAvgFilter = '';
}
if (!in_array($hrSentFilter, ['sent', 'not_sent'], true)) {
    $hrSentFilter = '';
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

$buildAverageWhere = static function (string $expr, string $filter): string {
    if ($filter === 'lt40') {
        return "($expr) IS NOT NULL AND ($expr) < 40";
    }
    if ($filter === '40_60') {
        return "($expr) IS NOT NULL AND ($expr) >= 40 AND ($expr) < 60";
    }
    if ($filter === '60_80') {
        return "($expr) IS NOT NULL AND ($expr) >= 60 AND ($expr) < 80";
    }
    if ($filter === '80_100') {
        return "($expr) IS NOT NULL AND ($expr) >= 80 AND ($expr) <= 100";
    }
    if ($filter === 'not_set') {
        return "($expr) IS NULL";
    }
    return '';
};

if ($mockAvgFilter !== '') {
    $condition = $buildAverageWhere('mi.mock_average', $mockAvgFilter);
    if ($condition !== '') {
        $where[] = $condition;
    }
}

if ($assessmentAvgFilter !== '') {
    if ($assessmentTableReady) {
        $condition = $buildAverageWhere('a.average_marks', $assessmentAvgFilter);
        if ($condition !== '') {
            $where[] = $condition;
        }
    } else {
        if ($assessmentAvgFilter === 'not_set') {
            $where[] = '1=1';
        } else {
            $where[] = '1=0';
        }
    }
}

if ($overallAvgFilter !== '') {
    $overallExpr = $assessmentTableReady
        ? "CASE
            WHEN a.average_marks IS NULL AND mi.mock_average IS NULL THEN NULL
            WHEN a.average_marks IS NULL THEN mi.mock_average
            WHEN mi.mock_average IS NULL THEN a.average_marks
            ELSE (a.average_marks + mi.mock_average) / 2
          END"
        : 'mi.mock_average';
    $condition = $buildAverageWhere($overallExpr, $overallAvgFilter);
    if ($condition !== '') {
        $where[] = $condition;
    }
}

if ($hrSentFilter !== '') {
    if ($hrWorkflowTableReady) {
        $where[] = $hrSentFilter === 'sent'
            ? "shi.sent_to_hr_at IS NOT NULL"
            : "shi.sent_to_hr_at IS NULL";
    } elseif ($hrSentFilter === 'sent') {
        $where[] = '1=0';
    }
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

<div class="payments-dashboard mock-dashboard">
    <div class="dashboard-header">
        <h2><i class="fas fa-user-graduate" style="margin-right:12px; color:#e91e63;"></i>Mock Interview Management</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-database"></i> Total: <?= (int) count($rows) ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-sliders-h" style="margin-right:8px;"></i> Filter Mock Interviews
        </div>
        <div class="card-body">
        <?php if (!$tableReady): ?>
            <div class="mock-alert mock-alert-warning">
                Mock interview table is missing. Run <b>mock_interview_setup.sql</b> before using this page.
            </div>
        <?php endif; ?>

        <form method="GET" action="index.php" class="filter-form">
            <input type="hidden" name="page" value="mock_interview">
            <div class="filter-grid">
                <div class="filter-item">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Registration, student, program, batch">
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-percent"></i> Mock Avg</label>
                    <select name="mock_avg_filter">
                        <option value="">All Mock Avg</option>
                        <option value="lt40" <?= $mockAvgFilter === 'lt40' ? 'selected' : '' ?>>Below 40</option>
                        <option value="40_60" <?= $mockAvgFilter === '40_60' ? 'selected' : '' ?>>40 to 59.99</option>
                        <option value="60_80" <?= $mockAvgFilter === '60_80' ? 'selected' : '' ?>>60 to 79.99</option>
                        <option value="80_100" <?= $mockAvgFilter === '80_100' ? 'selected' : '' ?>>80 to 100</option>
                        <option value="not_set" <?= $mockAvgFilter === 'not_set' ? 'selected' : '' ?>>Not Set</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-chart-line"></i> Assessment Avg</label>
                    <select name="assessment_avg_filter">
                        <option value="">All Assessment Avg</option>
                        <option value="lt40" <?= $assessmentAvgFilter === 'lt40' ? 'selected' : '' ?>>Below 40</option>
                        <option value="40_60" <?= $assessmentAvgFilter === '40_60' ? 'selected' : '' ?>>40 to 59.99</option>
                        <option value="60_80" <?= $assessmentAvgFilter === '60_80' ? 'selected' : '' ?>>60 to 79.99</option>
                        <option value="80_100" <?= $assessmentAvgFilter === '80_100' ? 'selected' : '' ?>>80 to 100</option>
                        <option value="not_set" <?= $assessmentAvgFilter === 'not_set' ? 'selected' : '' ?>>Not Set</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-layer-group"></i> Overall Avg</label>
                    <select name="overall_avg_filter">
                        <option value="">All Overall Avg</option>
                        <option value="lt40" <?= $overallAvgFilter === 'lt40' ? 'selected' : '' ?>>Below 40</option>
                        <option value="40_60" <?= $overallAvgFilter === '40_60' ? 'selected' : '' ?>>40 to 59.99</option>
                        <option value="60_80" <?= $overallAvgFilter === '60_80' ? 'selected' : '' ?>>60 to 79.99</option>
                        <option value="80_100" <?= $overallAvgFilter === '80_100' ? 'selected' : '' ?>>80 to 100</option>
                        <option value="not_set" <?= $overallAvgFilter === 'not_set' ? 'selected' : '' ?>>Not Set</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-user-check"></i> HR Sent</label>
                    <select name="hr_sent_filter">
                        <option value="">All</option>
                        <option value="sent" <?= $hrSentFilter === 'sent' ? 'selected' : '' ?>>Sent to HR</option>
                        <option value="not_sent" <?= $hrSentFilter === 'not_sent' ? 'selected' : '' ?>>Not Sent to HR</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button class="btn-icon-only filter-action-btn apply" type="submit" title="Apply filters" aria-label="Apply filters">
                        <span class="btn-inner">
                            <i class="fas fa-filter"></i>
                            <span class="btn-mobile-label">Apply</span>
                        </span>
                    </button>
                    <a href="index.php?page=mock_interview" class="btn-icon-only filter-action-btn reset" title="Reset filters" aria-label="Reset filters">
                        <span class="btn-inner">
                            <i class="fas fa-undo-alt"></i>
                            <span class="btn-mobile-label">Reset</span>
                        </span>
                    </a>
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

        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="table-header-flex">
                <div class="table-title"><i class="fas fa-list"></i> Mock Interviews</div>
                <div id="datatableControls"></div>
            </div>
        </div>
        <div class="table-wrap">
            <table id="mockInterviewTable" class="crm-table mock-table display" style="width:100%;">
                <thead>
                    <tr>
                        <th>Student Details</th>
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
                                    <div class="mock-primary"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                    <div class="mock-sub"><b><?= h($r['registration_no'] ?: ('REG-' . $r['id'])) ?></b> | <?= h($r['joined_on'] ?: '-') ?></div>
                                    <div class="mock-sub"><?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                                    <div class="mock-sub"><?= h($r['program_name'] ?: '-') ?><?= ($r['batch_name'] ?? '') !== '' ? ' | ' . h($r['batch_name']) : '' ?></div>
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
                                        <button
                                            type="submit"
                                            name="save_mock_interview"
                                            value="1"
                                            class="btn btn-primary mock-save-btn"
                                            data-modern-tooltip="Save Mock Interview"
                                            aria-label="Save Mock Interview">
                                            <i class="fas fa-floppy-disk" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="mock-row-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                                            <button
                                                type="submit"
                                                name="mark_mock_done"
                                                value="1"
                                                class="btn mock-done-btn"
                                                data-modern-tooltip="<?= h(($workflowStatus === 'done' || $workflowStatus === 'sent_to_hr') ? 'Already Marked Done' : 'Mark Workflow as Done') ?>"
                                                aria-label="<?= h(($workflowStatus === 'done' || $workflowStatus === 'sent_to_hr') ? 'Already Marked Done' : 'Mark Workflow as Done') ?>"
                                                <?= ($canMarkDone && $workflowColumnsReady) ? '' : 'disabled' ?>>
                                                <i class="fas <?= ($workflowStatus === 'done' || $workflowStatus === 'sent_to_hr') ? 'fa-circle-check' : 'fa-check-double' ?>" aria-hidden="true"></i>
                                                <span class="sr-only"><?= ($workflowStatus === 'done' || $workflowStatus === 'sent_to_hr') ? 'Already Marked Done' : 'Mark Workflow as Done' ?></span>
                                            </button>
                                        </form>
                                    <?php if ($hrWorkflowTableReady): ?>
                                        <form method="POST" class="mock-row-form">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">
                                            <button
                                                type="submit"
                                                name="send_to_hr"
                                                value="1"
                                                class="btn mock-send-btn"
                                                data-modern-tooltip="<?= h($sentToHrAt !== '' ? 'Re-send to HR' : 'Send Student to HR') ?>"
                                                aria-label="<?= h($sentToHrAt !== '' ? 'Re-send to HR' : 'Send Student to HR') ?>"
                                                <?= ($canSendToHr && $workflowColumnsReady) ? '' : 'disabled' ?>>
                                                <i class="fas <?= $sentToHrAt !== '' ? 'fa-paper-plane' : 'fa-share-from-square' ?>" aria-hidden="true"></i>
                                                <span class="sr-only"><?= $sentToHrAt !== '' ? 'Re-send to HR' : 'Send Student to HR' ?></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
        crmDataTable('#mockInterviewTable', {
            pageLength: 10,
            lengthMenu: [5, 10, 20, 50, 100],
            ordering: true,
            scrollX: false,
            responsive: false,
            searchPlaceholder: 'Search mock interviews...',
            columnDefs: [
                { orderable: false, targets: [1, 2, 7] }
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


