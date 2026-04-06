<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

require_once __DIR__ . '/_student_report_helpers.php';

if (!in_array(($_SESSION['role_name'] ?? ''), ['Staff', 'Super Admin'], true)) {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Poppins,sans-serif'><h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2><p style='margin:0;color:#666'>This page is available only for staff users.</p></div>";
    return;
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = studentReportRoleScope($pdo, $roleId) === 1;
$q = trim((string) ($_GET['q'] ?? ''));
$hrSentFilter = trim((string) ($_GET['hr_sent_filter'] ?? ''));
$overallAvgFilter = trim((string) ($_GET['overall_avg_filter'] ?? ''));
if (!in_array($hrSentFilter, ['sent', 'not_sent'], true)) {
    $hrSentFilter = '';
}
$allowedOverallFilters = ['lt40', '40_60', '60_80', '80_100', 'not_set'];
if (!in_array($overallAvgFilter, $allowedOverallFilters, true)) {
    $overallAvgFilter = '';
}
$hrWorkflowTableReady = false;
try {
    $stHr = $pdo->query("SHOW TABLES LIKE 'student_hr_interviews'");
    $hrWorkflowTableReady = (bool) $stHr->fetchColumn();
} catch (Exception $e) {
    $hrWorkflowTableReady = false;
}

$students = [];
$params = [];
$where = [
    "r.reg_type = 'course'",
    "r.registration_status IN ('active','completed')",
];

if (($_SESSION['role_name'] ?? '') === 'Staff') {
    $where[] = "rc.guide_staff_id = ?";
    $params[] = $userId;
}

if (!$canAllBranches && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($q !== '') {
    $where[] = "(r.registration_no LIKE ? OR r.enquiry_snapshot_name LIKE ? OR r.program_name LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

if ($hrSentFilter !== '') {
    if ($hrWorkflowTableReady) {
        $where[] = $hrSentFilter === 'sent'
            ? "shi.sent_to_hr_at IS NOT NULL"
            : "shi.sent_to_hr_at IS NULL";
    } elseif ($hrSentFilter === 'sent') {
        $where[] = "1=0";
    }
}

if ($overallAvgFilter !== '') {
    $overallExpr = "CASE
        WHEN a.average_marks IS NULL AND mi.mock_average IS NULL THEN NULL
        WHEN a.average_marks IS NULL THEN mi.mock_average
        WHEN mi.mock_average IS NULL THEN a.average_marks
        ELSE (a.average_marks + mi.mock_average) / 2
    END";
    if ($overallAvgFilter === 'lt40') {
        $where[] = "($overallExpr) IS NOT NULL AND ($overallExpr) < 40";
    } elseif ($overallAvgFilter === '40_60') {
        $where[] = "($overallExpr) IS NOT NULL AND ($overallExpr) >= 40 AND ($overallExpr) < 60";
    } elseif ($overallAvgFilter === '60_80') {
        $where[] = "($overallExpr) IS NOT NULL AND ($overallExpr) >= 60 AND ($overallExpr) < 80";
    } elseif ($overallAvgFilter === '80_100') {
        $where[] = "($overallExpr) IS NOT NULL AND ($overallExpr) >= 80 AND ($overallExpr) <= 100";
    } elseif ($overallAvgFilter === 'not_set') {
        $where[] = "($overallExpr) IS NULL";
    }
}

$hrSelect = $hrWorkflowTableReady ? "shi.sent_to_hr_at, shi.interview_status," : "NULL AS sent_to_hr_at, NULL AS interview_status,";
$hrJoin = $hrWorkflowTableReady ? "LEFT JOIN student_hr_interviews shi ON shi.registration_id = r.id" : "";

$st = $pdo->prepare("
    SELECT
        r.id,
        r.registration_no,
        r.enquiry_snapshot_name,
        r.program_name,
        r.batch_name,
        {$hrSelect}
        COALESCE(att.present_days, 0) AS present_days,
        COALESCE(att.absent_days, 0) AS absent_days,
        CASE
            WHEN COALESCE(att.marked_days, 0) > 0
                THEN ROUND((COALESCE(att.present_days, 0) / att.marked_days) * 100, 2)
            ELSE 0
        END AS attendance_percent,
        a.average_marks AS assessment_avg,
        mi.mock_average AS mock_avg,
        CASE
            WHEN a.average_marks IS NULL AND mi.mock_average IS NULL THEN NULL
            WHEN a.average_marks IS NULL THEN mi.mock_average
            WHEN mi.mock_average IS NULL THEN a.average_marks
            ELSE ROUND((a.average_marks + mi.mock_average) / 2, 2)
        END AS overall_avg
    FROM registrations r
    LEFT JOIN registration_courses rc ON rc.registration_id = r.id
    {$hrJoin}
    LEFT JOIN (
        SELECT
            registration_id,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_days,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent_days,
            SUM(CASE WHEN status IN ('Present', 'Absent') THEN 1 ELSE 0 END) AS marked_days
        FROM attendance
        GROUP BY registration_id
    ) att ON att.registration_id = r.id
    LEFT JOIN assessment a ON a.registration_id = r.id
    LEFT JOIN mock_interviews mi ON mi.registration_id = r.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY r.enquiry_snapshot_name ASC, r.id DESC
");
$st->execute($params);
$students = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>

<div class="payments-dashboard ssr-dashboard">
    <div class="dashboard-header">
        <h2><i class="fas fa-clipboard-list" style="margin-right:12px; color:#e91e63;"></i>Student Schedule Report</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-database"></i> Total: <?= (int) count($students) ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-sliders-h" style="margin-right:8px;"></i> Filter Students
        </div>
        <form method="GET" action="index.php" class="filter-form">
            <input type="hidden" name="page" value="reports/student_schedule">
            <div class="filter-grid">
                <div class="filter-item">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="q" value="<?= studentReportH($q) ?>" placeholder="Registration, student, program">
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-user-check"></i> Moved to HR</label>
                    <select name="hr_sent_filter">
                        <option value="">All</option>
                        <option value="sent" <?= $hrSentFilter === 'sent' ? 'selected' : '' ?>>Moved</option>
                        <option value="not_sent" <?= $hrSentFilter === 'not_sent' ? 'selected' : '' ?>>Not Moved</option>
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
                <div class="filter-actions">
                    <button class="btn-icon-only apply" data-mobile-label="Apply" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="index.php?page=reports/student_schedule" class="btn-icon-only reset" data-mobile-label="Reset" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                        <i class="fas fa-undo-alt"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header ssr-table-head">
            <span><i class="fas fa-list" style="margin-right:8px;"></i>Students List (<?= (int) count($students) ?>)</span>
            <div id="datatableControls"></div>
        </div>
        <div class="ssr-wrap">
            <table id="studentScheduleTable" class="table ssr-table display" style="width:100%;">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Batch</th>
                        <th>Attendance</th>
                        <th>Assessment Avg</th>
                        <th>Mock Avg</th>
                        <th>Overall Avg</th>
                        <th>Moved to HR</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $row): ?>
                        <?php
                        $assessmentAvg = isset($row['assessment_avg']) && $row['assessment_avg'] !== null ? number_format((float) $row['assessment_avg'], 2) : '-';
                        $mockAvg = isset($row['mock_avg']) && $row['mock_avg'] !== null ? number_format((float) $row['mock_avg'], 2) : '-';
                        $overallAvg = isset($row['overall_avg']) && $row['overall_avg'] !== null ? number_format((float) $row['overall_avg'], 2) : '-';
                        $sentToHr = !empty($row['sent_to_hr_at']);
                        ?>
                        <tr>
                            <td>
                                <div class="ssr-student-primary"><?= studentReportH($row['enquiry_snapshot_name'] ?: '-') ?></div>
                                <div class="ssr-student-sub"><?= studentReportH($row['registration_no'] ?: ('REG-' . $row['id'])) ?></div>
                            </td>
                            <td><?= studentReportH($row['program_name'] ?: '-') ?></td>
                            <td><?= studentReportH($row['batch_name'] ?: '-') ?></td>
                            <td>
                                <div class="ssr-attendance-wrap">
                                    <span class="ssr-chip ssr-chip-pink"><?= studentReportH(number_format((float) ($row['attendance_percent'] ?? 0), 2)) ?>%</span>
                                    <div class="ssr-attendance-sub">P: <?= (int) ($row['present_days'] ?? 0) ?> | A: <?= (int) ($row['absent_days'] ?? 0) ?></div>
                                </div>
                            </td>
                            <td><span class="ssr-chip ssr-chip-violet"><?= studentReportH($assessmentAvg) ?></span></td>
                            <td><span class="ssr-chip ssr-chip-cyan"><?= studentReportH($mockAvg) ?></span></td>
                            <td><span class="ssr-chip ssr-chip-green"><?= studentReportH($overallAvg) ?></span></td>
                            <td>
                                <span class="ssr-mini-chip <?= $sentToHr ? 'ssr-mini-chip-success' : 'ssr-mini-chip-muted' ?>">
                                    <?= $sentToHr ? 'Moved to HR' : 'Not Moved to HR' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="ssr-action-group">
                                    <a
                                        href="index.php?page=reports/student_profile&id=<?= (int) $row['id'] ?>"
                                        class="ssr-action-btn ssr-action-view"
                                        data-mobile-label="View"
                                        data-modern-tooltip="View Full Details"
                                        aria-label="View Full Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a
                                        href="index.php?page=reports/student_profile&id=<?= (int) $row['id'] ?>&print=1"
                                        class="ssr-action-btn ssr-action-download"
                                        data-mobile-label="Download"
                                        data-modern-tooltip="Download Full Report"
                                        aria-label="Download Full Report"
                                        target="_blank"
                                        rel="noopener">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

    .payments-dashboard.ssr-dashboard {
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

    .filter-form {
        padding: 12px 14px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) minmax(170px, 190px) minmax(180px, 220px) auto;
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

    .ssr-table-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    #datatableControls {
        margin-left: auto;
        width: auto;
    }

    #datatableControls .dt-top {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: nowrap;
    }

    .ssr-wrap {
        padding: 16px;
    }

    .ssr-table th,
    .ssr-table td {
        vertical-align: top;
        white-space: normal;
    }

    .ssr-student-primary {
        font-weight: 800;
        color: #0f172a;
    }

    .ssr-student-sub {
        margin-top: 3px;
        font-size: 12px;
        color: #64748b;
    }

    .ssr-attendance-wrap {
        display: flex;
        flex-direction: column;
        gap: 5px;
        align-items: flex-start;
    }

    .ssr-attendance-sub {
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
    }

    .ssr-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 74px;
        height: 30px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
    }

    .ssr-chip-pink { background:#fde8f3; color:#be185d; }
    .ssr-chip-violet { background:#f3e8ff; color:#7c3aed; }
    .ssr-chip-cyan { background:#e0f7ff; color:#0e7490; }
    .ssr-chip-green { background:#eaf7ee; color:#15803d; }

    .ssr-status-stack {
        display: flex;
        flex-direction: column;
        gap: 6px;
        align-items: flex-start;
    }

    .ssr-mini-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 24px;
        padding: 0 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .ssr-mini-chip-info { background:#e0f2fe; color:#0369a1; }
    .ssr-mini-chip-success { background:#dcfce7; color:#15803d; }
    .ssr-mini-chip-warn { background:#fff7ed; color:#c2410c; }
    .ssr-mini-chip-muted { background:#f1f5f9; color:#475569; }

    .ssr-action-group {
        display: inline-flex;
        gap: 8px;
        align-items: center;
    }

    .ssr-action-btn {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: .15s ease;
        color:#fff;
        box-shadow:0 8px 18px rgba(15,23,42,.18);
    }

    .ssr-action-view {
        background:#2563eb !important;
        color:#fff !important;
        border:1px solid #2563eb !important;
    }
    .ssr-action-view:hover {
        background:#1d4ed8 !important;
        color:#fff !important;
        border-color:#1d4ed8 !important;
    }
    .ssr-action-download {
        background:#15803d !important;
        color:#fff !important;
        border:1px solid #15803d !important;
    }
    .ssr-action-download:hover {
        background:#166534 !important;
        color:#fff !important;
        border-color:#166534 !important;
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

    .dataTables_wrapper .dataTables_length {
        display: inline-flex !important;
        align-items: center;
        width: auto !important;
        white-space: nowrap !important;
        flex: 0 0 auto;
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
        box-shadow: 0 0 0 3px rgba(233,30,99,.14);
    }

    .dataTables_wrapper .dataTables_filter input {
        min-width: 240px;
    }

    .dataTables_wrapper .dataTables_length select {
        width: auto !important;
        min-width: 84px;
        flex: 0 0 auto;
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
        .dashboard-header h2 { font-size: 24px; }
        .filter-grid { grid-template-columns: 1fr; }
        .filter-actions { justify-content: flex-start; }
        .filter-actions .btn-icon-only[data-mobile-label]{
            width:auto !important;
            min-width:64px !important;
            height:auto !important;
            min-height:40px !important;
            padding:6px 8px !important;
            display:inline-flex !important;
            flex-direction:column !important;
            align-items:center !important;
            justify-content:center !important;
            gap:3px !important;
            border-radius:10px !important;
        }
        .filter-actions .btn-icon-only[data-mobile-label]::before{
            content:none !important;
            display:none !important;
        }
        .filter-actions .btn-icon-only[data-mobile-label]::after{
            content:attr(data-mobile-label) !important;
            position:static !important;
            display:block !important;
            opacity:1 !important;
            visibility:visible !important;
            transform:none !important;
            background:none !important;
            border:0 !important;
            box-shadow:none !important;
            padding:0 !important;
            margin:0 !important;
            font-size:10px !important;
            line-height:1.1 !important;
            font-weight:700 !important;
            letter-spacing:.1px !important;
            color:currentColor !important;
            white-space:nowrap !important;
        }
        #datatableControls { width:100%; margin-left:0; }
        #datatableControls .dt-top,
        .dataTables_wrapper .dt-bottom { justify-content:flex-start; }
        #datatableControls .dt-top { flex-wrap: wrap; }
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length { width:100%; }
        .dataTables_wrapper .dataTables_filter label { width:100%; }
        .dataTables_wrapper .dataTables_filter input { width:100% !important; min-width:0; }

        .ssr-action-btn[data-mobile-label]{
            width:auto !important;
            min-width:64px !important;
            height:auto !important;
            min-height:40px !important;
            padding:6px 8px !important;
            display:inline-flex !important;
            flex-direction:column !important;
            align-items:center !important;
            justify-content:center !important;
            gap:3px !important;
            border-radius:10px !important;
        }

        .ssr-action-btn[data-mobile-label]::before{
            content:none !important;
            display:none !important;
        }

        .ssr-action-btn[data-mobile-label]::after{
            content:attr(data-mobile-label) !important;
            position:static !important;
            display:block !important;
            opacity:1 !important;
            visibility:visible !important;
            transform:none !important;
            background:none !important;
            border:0 !important;
            box-shadow:none !important;
            padding:0 !important;
            margin:0 !important;
            font-size:10px !important;
            line-height:1.1 !important;
            font-weight:700 !important;
            letter-spacing:.1px !important;
            color:currentColor !important;
            white-space:nowrap !important;
        }
    }

    @media (hover: none), (pointer: coarse), (any-pointer: coarse) {
        .ssr-action-btn[data-mobile-label]{
            width:auto !important;
            min-width:64px !important;
            height:auto !important;
            min-height:40px !important;
            padding:6px 8px !important;
            display:inline-flex !important;
            flex-direction:column !important;
            align-items:center !important;
            justify-content:center !important;
            gap:3px !important;
            border-radius:10px !important;
        }

        .ssr-action-btn[data-mobile-label]::before{
            content:none !important;
            display:none !important;
        }

        .ssr-action-btn[data-mobile-label]::after{
            content:attr(data-mobile-label) !important;
            position:static !important;
            display:block !important;
            opacity:1 !important;
            visibility:visible !important;
            transform:none !important;
            background:none !important;
            border:0 !important;
            box-shadow:none !important;
            padding:0 !important;
            margin:0 !important;
            font-size:10px !important;
            line-height:1.1 !important;
            font-weight:700 !important;
            letter-spacing:.1px !important;
            color:currentColor !important;
            white-space:nowrap !important;
        }

        .modern-tooltip{
            display:none !important;
            opacity:0 !important;
            visibility:hidden !important;
        }
    }

    @media print {
        .wrapper aside,.card:first-of-type,.ssr-actions,.sidebar,.topbar,.header { display:none !important; }
        .content,.main-content { padding:0 !important; }
        .card { box-shadow:none !important; border:none !important; }
    }

    /* Final lock: keep project solid action style in all layouts */
    .ssr-action-btn.ssr-action-view,
    .ssr-action-btn.ssr-action-view:link,
    .ssr-action-btn.ssr-action-view:visited{
        background: #2563eb !important;
        background-image: none !important;
        border: 1px solid #2563eb !important;
        color: #fff !important;
        box-shadow: 0 8px 18px rgba(37,99,235,.24) !important;
    }

    .ssr-action-btn.ssr-action-view:hover,
    .ssr-action-btn.ssr-action-view:focus-visible{
        background: #1d4ed8 !important;
        border-color: #1d4ed8 !important;
        color: #fff !important;
    }

    .ssr-action-btn.ssr-action-download,
    .ssr-action-btn.ssr-action-download:link,
    .ssr-action-btn.ssr-action-download:visited{
        background: #15803d !important;
        background-image: none !important;
        border: 1px solid #15803d !important;
        color: #fff !important;
        box-shadow: 0 8px 18px rgba(21,128,61,.24) !important;
    }

    .ssr-action-btn.ssr-action-download:hover,
    .ssr-action-btn.ssr-action-download:focus-visible{
        background: #166534 !important;
        border-color: #166534 !important;
        color: #fff !important;
    }
</style>

<script>
let modernTooltipEl = null;
let modernTooltipTarget = null;
const isTouchLikeDevice = window.matchMedia('(hover: none), (pointer: coarse), (any-pointer: coarse)').matches
    || ('ontouchstart' in window)
    || (navigator.maxTouchPoints && navigator.maxTouchPoints > 0);

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
    if (isTouchLikeDevice) return;
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
        crmDataTable('#studentScheduleTable', {
            pageLength: 10,
            lengthMenu: [5, 10, 20, 50, 100],
            ordering: true,
            scrollX: false,
            responsive: false,
            searchPlaceholder: 'Search students...',
            columnDefs: [{ orderable: false, targets: 8 }],
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

document.addEventListener('mouseover', function (e) {
    if (isTouchLikeDevice) return;
    const target = e.target.closest('[data-modern-tooltip]');
    if (!target) {
        hideModernTooltip();
        return;
    }
    showModernTooltip(target);
});

document.addEventListener('mouseout', function (e) {
    if (isTouchLikeDevice) return;
    const from = e.target.closest('[data-modern-tooltip]');
    if (!from) return;
    const to = e.relatedTarget ? e.relatedTarget.closest('[data-modern-tooltip]') : null;
    if (from !== to) {
        hideModernTooltip();
    }
});

document.addEventListener('focusin', function (e) {
    if (isTouchLikeDevice) return;
    const target = e.target.closest('[data-modern-tooltip]');
    if (!target) return;
    showModernTooltip(target);
});

document.addEventListener('focusout', function (e) {
    if (isTouchLikeDevice) return;
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
