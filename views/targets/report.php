<?php
// =====================================
// Targets - Report
// Slug: targets/report
// File: views/targets/report.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('targetInt')) {
    function targetInt($value)
    {
        return (int) trim((string)$value);
    }
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = trim((string)($_SESSION['role_name'] ?? ''));

$allowedRoles = ['Super Admin', 'HR'];
$error = '';

if (!$userId || !$branchId) {
    $error = 'Invalid session. Please login again.';
}

if (!$error && !in_array($roleName, $allowedRoles, true)) {
    $error = 'Access denied. Only HR and Super Admin can access target reports.';
}

$monthNames = [
    1  => 'January',
    2  => 'February',
    3  => 'March',
    4  => 'April',
    5  => 'May',
    6  => 'June',
    7  => 'July',
    8  => 'August',
    9  => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];

// --------------------------------------------------
// Filters
// --------------------------------------------------
$fYear   = targetInt($_GET['year'] ?? date('Y'));
$fMonth  = targetInt($_GET['month'] ?? date('n'));
$fUserId = targetInt($_GET['user_id'] ?? 0);
$fRoleId = targetInt($_GET['role_id'] ?? 0);
$search  = trim((string)($_GET['search'] ?? ''));

if ($fYear < 2000 || $fYear > 2100) {
    $fYear = (int) date('Y');
}
if ($fMonth < 1 || $fMonth > 12) {
    $fMonth = (int) date('n');
}

// --------------------------------------------------
// Filter dropdowns
// --------------------------------------------------
$filterUsers = [];
$filterRoles = [];

if (!$error) {
    try {
        $stmtUsers = $pdo->prepare("
            SELECT u.id, u.name, r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.branch_id = :branch_id
              AND u.status = 1
            ORDER BY u.name ASC
        ");
        $stmtUsers->execute([':branch_id' => $branchId]);
        $filterUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        $stmtRoles = $pdo->query("
            SELECT id, role_name
            FROM roles
            WHERE status = 1
            ORDER BY role_name ASC
        ");
        $filterRoles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load filters. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Load users in branch
// --------------------------------------------------
$usersMap = [];
if (!$error) {
    try {
        $stmtAllUsers = $pdo->prepare("
    SELECT
        u.id,
        u.name,
        u.email,
        u.role_id,
        u.status,
        r.role_name
    FROM users u
    INNER JOIN roles r ON r.id = u.role_id
    WHERE u.branch_id = :branch_id
      AND u.status = 1
      AND r.is_target_applicable = 1
    ORDER BY u.name ASC
");
        $stmtAllUsers->execute([':branch_id' => $branchId]);

        foreach ($stmtAllUsers->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $usersMap[(int)$u['id']] = $u;
        }
    } catch (Throwable $e) {
        $error = 'Unable to load users. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Current month targets
// --------------------------------------------------
$currentTargets = [];
if (!$error) {
    try {
        $stmtTargets = $pdo->prepare("
            SELECT *
            FROM monthly_targets
            WHERE branch_id = :branch_id
              AND target_year = :target_year
              AND target_month = :target_month
            ORDER BY id DESC
        ");
        $stmtTargets->execute([
            ':branch_id'    => $branchId,
            ':target_year'  => $fYear,
            ':target_month' => $fMonth,
        ]);

        foreach ($stmtTargets->fetchAll(PDO::FETCH_ASSOC) as $tr) {
            $currentTargets[(int)$tr['user_id']] = $tr;
        }
    } catch (Throwable $e) {
        $error = 'Unable to load monthly targets. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Current month achieved map
// --------------------------------------------------
$currentAchievedMap = [];
$currentCollectionDetailsByUser = [];
if (!$error) {
    try {
        $stmtAchieved = $pdo->prepare("
            SELECT
                collected_by,
                COALESCE(SUM(amount), 0) AS total_amount
            FROM registration_payments
            WHERE branch_id = :branch_id
              AND approval_status = 'approved'
              AND YEAR(payment_date) = :pay_year
              AND MONTH(payment_date) = :pay_month
              AND collected_by IS NOT NULL
            GROUP BY collected_by
        ");
        $stmtAchieved->execute([
            ':branch_id' => $branchId,
            ':pay_year'  => $fYear,
            ':pay_month' => $fMonth,
        ]);

        foreach ($stmtAchieved->fetchAll(PDO::FETCH_ASSOC) as $ar) {
            $currentAchievedMap[(int)$ar['collected_by']] = (float)$ar['total_amount'];
        }

        $stmtCollectionRows = $pdo->prepare("
            SELECT
                collected_by,
                payment_date,
                amount,
                approval_status
            FROM registration_payments
            WHERE branch_id = :branch_id
              AND YEAR(payment_date) = :pay_year
              AND MONTH(payment_date) = :pay_month
              AND collected_by IS NOT NULL
            ORDER BY payment_date DESC, id DESC
        ");
        $stmtCollectionRows->execute([
            ':branch_id' => $branchId,
            ':pay_year'  => $fYear,
            ':pay_month' => $fMonth,
        ]);

        foreach ($stmtCollectionRows->fetchAll(PDO::FETCH_ASSOC) as $collectionRow) {
            $collectionUserId = (int)$collectionRow['collected_by'];
            if (!isset($currentCollectionDetailsByUser[$collectionUserId])) {
                $currentCollectionDetailsByUser[$collectionUserId] = [];
            }
            $currentCollectionDetailsByUser[$collectionUserId][] = [
                'payment_date'    => $collectionRow['payment_date'] ?? '',
                'amount'          => (float)($collectionRow['amount'] ?? 0),
                'approval_status' => (string)($collectionRow['approval_status'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $error = 'Unable to load current achieved collections. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Previous targets by user
// --------------------------------------------------
$previousTargetsByUser = [];
if (!$error) {
    try {
        $stmtPrevTargets = $pdo->prepare("
            SELECT *
            FROM monthly_targets
            WHERE branch_id = :branch_id
              AND (
                    target_year < :target_year1
                    OR (target_year = :target_year2 AND target_month < :target_month)
                  )
            ORDER BY user_id ASC, target_year ASC, target_month ASC, id ASC
        ");
        $stmtPrevTargets->execute([
            ':branch_id'     => $branchId,
            ':target_year1'  => $fYear,
            ':target_year2'  => $fYear,
            ':target_month'  => $fMonth,
        ]);

        foreach ($stmtPrevTargets->fetchAll(PDO::FETCH_ASSOC) as $ptr) {
            $uid = (int)$ptr['user_id'];
            if (!isset($previousTargetsByUser[$uid])) {
                $previousTargetsByUser[$uid] = [];
            }
            $previousTargetsByUser[$uid][] = $ptr;
        }
    } catch (Throwable $e) {
        $error = 'Unable to load previous target history. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Previous achieved map by user-year-month
// --------------------------------------------------
$previousAchievedMap = [];
if (!$error) {
    try {
        $stmtPrevAchieved = $pdo->prepare("
            SELECT
                collected_by,
                YEAR(payment_date) AS pay_year,
                MONTH(payment_date) AS pay_month,
                COALESCE(SUM(amount), 0) AS total_amount
            FROM registration_payments
            WHERE branch_id = :branch_id
              AND approval_status = 'approved'
              AND collected_by IS NOT NULL
              AND (
                    YEAR(payment_date) < :pay_year1
                    OR (YEAR(payment_date) = :pay_year2 AND MONTH(payment_date) < :pay_month)
                  )
            GROUP BY collected_by, YEAR(payment_date), MONTH(payment_date)
        ");
        $stmtPrevAchieved->execute([
            ':branch_id' => $branchId,
            ':pay_year1' => $fYear,
            ':pay_year2' => $fYear,
            ':pay_month' => $fMonth,
        ]);

        foreach ($stmtPrevAchieved->fetchAll(PDO::FETCH_ASSOC) as $par) {
            $key = (int)$par['collected_by'] . '-' . (int)$par['pay_year'] . '-' . (int)$par['pay_month'];
            $previousAchievedMap[$key] = (float)$par['total_amount'];
        }
    } catch (Throwable $e) {
        $error = 'Unable to load previous achieved data. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Build report rows
// --------------------------------------------------
$allRelevantUserIds = array_keys($usersMap);

$rows = [];
$totalUsers = 0;
$totalBaseTarget = 0.00;
$totalOpeningCarry = 0.00;
$totalEffectiveTarget = 0.00;
$totalAchieved = 0.00;
$totalShortfall = 0.00;
$totalExcess = 0.00;
$totalCarryRiskUsers = 0;
$topPerformer = null;
$biggestShortfallUser = null;

if (!$error) {
    foreach ($allRelevantUserIds as $uid) {
        $uid = (int)$uid;

        if (!isset($usersMap[$uid])) {
            continue;
        }

        $user = $usersMap[$uid];
        $target = $currentTargets[$uid] ?? null;
        $baseTarget = $target ? (float)$target['target_amount'] : 0.00;

        // Opening carry calculation
        $openingCarry = 0.00;
        $userPrevTargets = $previousTargetsByUser[$uid] ?? [];

        foreach ($userPrevTargets as $pt) {
            $prevTargetAmount = (float)$pt['target_amount'];
            $prevKey = $uid . '-' . (int)$pt['target_year'] . '-' . (int)$pt['target_month'];
            $prevAchieved = $previousAchievedMap[$prevKey] ?? 0.00;
            $prevEffective = $prevTargetAmount + $openingCarry;

            if ($prevAchieved >= $prevEffective) {
                $openingCarry = 0.00;
            } else {
                $openingCarry = $prevEffective - $prevAchieved;
            }
        }

        $achieved = $currentAchievedMap[$uid] ?? 0.00;
        $effectiveTarget = $baseTarget + $openingCarry;

        $shortfall = 0.00;
$excess = 0.00;
$incentiveAmount = 0.00;

$incentivePercent = isset($target['incentive_percent'])
    ? (float)$target['incentive_percent']
    : 0.00;

$statusText = 'No Target';
$statusClass = 'badge-soft-secondary';
        $progressPercent = 0.00;
        $progressLabel = 'No Target';

        if ($baseTarget <= 0 && $achieved > 0) {
            $statusText = 'Collected (No Target)';
            $statusClass = 'badge-soft-info';
        } elseif ($effectiveTarget > 0 && $achieved >= $effectiveTarget) {

    $excess = $achieved - $effectiveTarget;

    if ($incentivePercent > 0) {
        $incentiveAmount = ($excess * $incentivePercent) / 100;
    }

    $statusText = 'Achieved';
    $statusClass = 'badge-soft-success';
} elseif ($effectiveTarget > 0 && $achieved > 0 && $achieved < $effectiveTarget) {
            $shortfall = $effectiveTarget - $achieved;
            $statusText = 'In Progress';
            $statusClass = 'badge-soft-warning';
        } elseif ($effectiveTarget > 0) {
            $shortfall = $effectiveTarget - $achieved;
            $statusText = 'Not Started';
            $statusClass = 'badge-soft-danger';
        }

        if ($effectiveTarget > 0) {
            $progressPercent = ($achieved / $effectiveTarget) * 100;
            if ($progressPercent >= 100) {
                $progressLabel = 'Exceeded';
            } elseif ($progressPercent >= 75) {
                $progressLabel = 'On Track';
            } elseif ($progressPercent > 0) {
                $progressLabel = 'Needs Push';
            } else {
                $progressLabel = 'Not Started';
            }
        } elseif ($achieved > 0) {
            $progressPercent = 100;
            $progressLabel = 'Collected';
        }

        $progressPercent = max(0, min($progressPercent, 999.99));

        if ($search !== '') {
            $haystack = strtolower(
                ($user['name'] ?? '') . ' ' .
                ($user['email'] ?? '') . ' ' .
                ($user['role_name'] ?? '')
            );
            if (strpos($haystack, strtolower($search)) === false) {
                continue;
            }
        }

        if ($fUserId > 0 && $uid !== $fUserId) {
            continue;
        }

        if ($fRoleId > 0 && (int)$user['role_id'] !== $fRoleId) {
            continue;
        }

        $rows[] = [
            'user_id'          => $uid,
            'user_name'        => $user['name'] ?? '-',
            'user_email'       => $user['email'] ?? '-',
            'role_name'        => $user['role_name'] ?? '-',
            'base_target'      => $baseTarget,
            'opening_carry'    => $openingCarry,
            'effective_target' => $effectiveTarget,
            'achieved_amount'  => $achieved,
            'shortfall_amount' => $shortfall,
            'excess_amount'    => $excess,
            'status_text'      => $statusText,
            'status_class'     => $statusClass,
            'row_highlight'    => ($achieved > 0),
			'incentive_percent' => $incentivePercent,
			'incentive_amount'  => $incentiveAmount,
            'progress_percent' => $progressPercent,
            'progress_label'   => $progressLabel,
        ];

        $totalUsers++;
        $totalBaseTarget += $baseTarget;
        $totalOpeningCarry += $openingCarry;
        $totalEffectiveTarget += $effectiveTarget;
        $totalAchieved += $achieved;
        $totalShortfall += $shortfall;
        $totalExcess += $excess;

        if ($openingCarry > 0) {
            $totalCarryRiskUsers++;
        }
    }
}

if ($rows) {
    usort($rows, function ($a, $b) {
        $shortfallCompare = ($b['shortfall_amount'] <=> $a['shortfall_amount']);
        if ($shortfallCompare !== 0) {
            return $shortfallCompare;
        }
        return ($a['progress_percent'] <=> $b['progress_percent']);
    });

    foreach ($rows as $row) {
        if ($topPerformer === null || $row['progress_percent'] > $topPerformer['progress_percent']) {
            $topPerformer = $row;
        }
        if ($biggestShortfallUser === null || $row['shortfall_amount'] > $biggestShortfallUser['shortfall_amount']) {
            $biggestShortfallUser = $row;
        }
    }
}

$selectedPeriodLabel = ($monthNames[$fMonth] ?? 'Month') . ' ' . $fYear;
$viewModalData = [];
foreach ($rows as $row) {
    $viewUserId = (int)$row['user_id'];
    $historyRows = [];
    $runningCarry = 0.00;

    foreach (($previousTargetsByUser[$viewUserId] ?? []) as $historyTarget) {
        $historyYear = (int)$historyTarget['target_year'];
        $historyMonth = (int)$historyTarget['target_month'];
        $historyTargetAmount = (float)$historyTarget['target_amount'];
        $historyAchievedKey = $viewUserId . '-' . $historyYear . '-' . $historyMonth;
        $historyAchievedAmount = (float)($previousAchievedMap[$historyAchievedKey] ?? 0.00);
        $historyEffectiveTarget = $historyTargetAmount + $runningCarry;
        $historyShortfall = max(0, $historyEffectiveTarget - $historyAchievedAmount);
        $historyExcess = max(0, $historyAchievedAmount - $historyEffectiveTarget);

        $historyRows[] = [
            'period'           => ($monthNames[$historyMonth] ?? ('Month ' . $historyMonth)) . ' ' . $historyYear,
            'base_target'      => $historyTargetAmount,
            'opening_carry'    => $runningCarry,
            'effective_target' => $historyEffectiveTarget,
            'achieved_amount'  => $historyAchievedAmount,
            'shortfall_amount' => $historyShortfall,
            'excess_amount'    => $historyExcess,
            'status_text'      => $historyAchievedAmount >= $historyEffectiveTarget ? 'Achieved' : ($historyAchievedAmount > 0 ? 'In Progress' : 'Not Started'),
        ];

        $runningCarry = $historyShortfall > 0 ? $historyShortfall : 0.00;
    }

    $historyRows[] = [
        'period'           => $selectedPeriodLabel,
        'base_target'      => (float)$row['base_target'],
        'opening_carry'    => (float)$row['opening_carry'],
        'effective_target' => (float)$row['effective_target'],
        'achieved_amount'  => (float)$row['achieved_amount'],
        'shortfall_amount' => (float)$row['shortfall_amount'],
        'excess_amount'    => (float)$row['excess_amount'],
        'status_text'      => (string)$row['status_text'],
    ];

    $currentCollections = $currentCollectionDetailsByUser[$viewUserId] ?? [];
    $insightMessage = 'No target assigned for the selected month.';
    if ((float)$row['effective_target'] > 0 && (float)$row['achieved_amount'] >= (float)$row['effective_target']) {
        $insightMessage = 'Target achieved. This user is eligible for excess-based incentive review.';
    } elseif ((float)$row['opening_carry'] > 0) {
        $insightMessage = 'Carry-forward risk exists. Review past performance trail and current recovery pace.';
    } elseif ((float)$row['achieved_amount'] > 0) {
        $insightMessage = 'Collections started, but target recovery still needs push for this month.';
    }

    $viewModalData[$viewUserId] = [
        'user_id'            => $viewUserId,
        'user_name'          => (string)$row['user_name'],
        'user_email'         => (string)$row['user_email'],
        'role_name'          => (string)$row['role_name'],
        'period_label'       => $selectedPeriodLabel,
        'status_text'        => (string)$row['status_text'],
        'status_class'       => (string)$row['status_class'],
        'base_target'        => (float)$row['base_target'],
        'opening_carry'      => (float)$row['opening_carry'],
        'effective_target'   => (float)$row['effective_target'],
        'achieved_amount'    => (float)$row['achieved_amount'],
        'shortfall_amount'   => (float)$row['shortfall_amount'],
        'excess_amount'      => (float)$row['excess_amount'],
        'incentive_percent'  => (float)$row['incentive_percent'],
        'incentive_amount'   => (float)$row['incentive_amount'],
        'progress_percent'   => (float)$row['progress_percent'],
        'progress_label'     => (string)$row['progress_label'],
        'insight_message'    => $insightMessage,
        'carry_risk'         => ((float)$row['opening_carry'] > 0),
        'download_url'       => 'index.php?page=targets/export-user-details&user_id=' . $viewUserId . '&year=' . (int)$fYear . '&month=' . (int)$fMonth,
        'history_rows'       => $historyRows,
        'collection_rows'    => $currentCollections,
        'collection_count'   => count($currentCollections),
    ];
}

$collectionEfficiency = $totalEffectiveTarget > 0 ? (($totalAchieved / $totalEffectiveTarget) * 100) : 0;
$collectionEfficiency = max(0, min($collectionEfficiency, 999.99));
?>
<style>

/* ======================================================
ATS CRM TARGET REPORT
FINAL FIX - ALL CONTENT VISIBLE ON ALL DEVICES
====================================================== */

:root{
--primary:#e83e8c;
--primary-dark:#d2317a;
--primary-soft:#fff1f7;
--border:#f1d6e3;
--text:#2c2c2c;
--muted:#7a7a7a;
--bg:#ffffff;
--shadow-sm:0 2px 6px rgba(0,0,0,.05);
--shadow-md:0 8px 20px rgba(0,0,0,.06);
--radius:12px;
}

/* GLOBAL RESET */
* {
box-sizing:border-box;
margin:0;
padding:0;
}

body {
overflow-x: hidden;
width: 100%;
background: #f8f4f7;
font-family: 'Poppins', sans-serif;
}

/* MAIN CONTAINER */
.container-fluid {
width: 100%;
max-width: 100%;
padding-left: 16px;
padding-right: 16px;
margin: 0 auto;
}

/* ROOT CONTAINER */
.iso-report-root {
background:#fff;
border-radius:24px;
padding:24px 20px;
box-shadow:var(--shadow-md);
border:1px solid var(--border);
color:#333;
font-weight:500;
width:100%;
overflow: visible;
}

/* HERO SECTION */
.iso-report-hero {
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:24px;
flex-wrap:wrap;
gap:20px;
}

.iso-report-title {
font-size:28px;
font-weight:700;
color:var(--primary);
display: flex;
align-items: center;
gap: 8px;
}

.iso-report-subtitle {
font-size:14px;
color:#666;
display: flex;
align-items: center;
gap: 6px;
}

/* BUTTONS */
.iso-report-btns {
display: flex;
gap: 10px;
flex-wrap: wrap;
}

.iso-report-btn {
display:inline-flex;
align-items:center;
justify-content:center;
gap:8px;
height:42px;
padding:0 20px;
border-radius:40px;
font-size:13px;
font-weight:600;
border:1px solid transparent;
cursor:pointer;
white-space: nowrap;
transition: all 0.2s ease;
text-decoration: none;
}

.iso-report-icon-btn{
width:42px;
min-width:42px;
padding:0;
border-radius:14px;
background:linear-gradient(135deg,#ff5ca8 0%,#e83e8c 100%);
border:1px solid rgba(232,62,140,.18);
color:#fff;
box-shadow:0 10px 22px rgba(232,62,140,.28);
}

.iso-report-icon-btn:hover{
transform:translateY(-2px);
box-shadow:0 14px 28px rgba(232,62,140,.34);
color:#fff;
}

.iso-report-icon-btn i{
font-size:14px;
font-weight:900;
line-height:1;
display:block;
color:currentColor;
}

.iso-report-export-btn{
background:linear-gradient(135deg,#22a06b 0%,#15803d 100%);
border-color:rgba(21,128,61,.18);
box-shadow:0 10px 22px rgba(21,128,61,.28);
}

.iso-report-export-btn:hover{
box-shadow:0 14px 28px rgba(21,128,61,.34);
}

.iso-report-btn-primary {
background:linear-gradient(135deg,#e83e8c,#d2317a);
color:#fff;
box-shadow: 0 4px 12px rgba(232,62,140,0.2);
}

.iso-report-btn-primary:hover {
transform: translateY(-2px);
box-shadow: 0 6px 16px rgba(232,62,140,0.3);
}

.iso-report-btn-outline {
background:#fff;
border:1px solid var(--border);
color:#444;
}

.iso-report-btn-outline:hover {
background:var(--primary-soft);
border-color:var(--primary);
color:var(--primary);
}

/* FILTER CARD */
.iso-report-card {
background:#fff;
border-radius:20px;
border:1px solid var(--border);
margin-bottom:24px;
overflow: visible;
}

.iso-report-card-head {
padding:16px 20px;
background:var(--primary-soft);
font-weight:700;
font-size:15px;
color:var(--primary);
border-bottom: 1px solid var(--border);
display: flex;
align-items: center;
gap: 8px;
}

.iso-report-card-body {
padding:24px;
}

/* FILTER GRID - IMPROVED */
.iso-report-filter-row {
display: grid;
grid-template-columns: 2fr 1fr 1fr 1.5fr 1.2fr auto auto;
gap: 16px;
align-items: end;
}

/* FORM ELEMENTS */
.form-label {
font-size:12px;
font-weight:600;
text-transform:uppercase;
color:#666;
margin-bottom:6px;
display: block;
letter-spacing: 0.3px;
}

.form-control,
.form-select {
height:44px;
border-radius:12px;
border:1px solid #e0e0e0;
padding:0 14px;
font-size:14px;
font-weight:500;
width:100%;
background: #fff;
transition: all 0.2s;
}

.form-control:focus,
.form-select:focus {
outline: none;
border-color: var(--primary);
box-shadow: 0 0 0 3px rgba(232,62,140,0.1);
}

/* FILTER BUTTONS */
.iso-report-filter-btn {
height:44px;
width:44px;
padding:0;
border-radius:12px;
}

/* SUMMARY CARDS - HORIZONTAL SCROLL FIX */
.iso-report-summary {
display: flex;
gap: 16px;
margin-bottom: 24px;
overflow-x: auto;
overflow-y: hidden;
padding: 4px 0 12px 0;
-webkit-overflow-scrolling: touch;
scrollbar-width: thin;
scrollbar-color: var(--primary) #f0f0f0;
}

.iso-report-summary::-webkit-scrollbar {
height: 6px;
}

.iso-report-summary::-webkit-scrollbar-track {
background: #f0f0f0;
border-radius: 10px;
}

.iso-report-summary::-webkit-scrollbar-thumb {
background: var(--primary);
border-radius: 10px;
}

.iso-report-summary-card {
flex: 0 0 auto;
background:#fff;
border:1px solid var(--border);
border-radius:16px;
padding:18px;
min-width: 180px;
transition: all 0.2s;
}

.iso-report-summary-card:hover {
transform: translateY(-2px);
box-shadow: var(--shadow-sm);
border-color: var(--primary);
}

.iso-report-summary-label {
font-size:12px;
font-weight:600;
color:#666;
text-transform:uppercase;
letter-spacing:0.3px;
margin-bottom:8px;
display: flex;
align-items: center;
gap: 6px;
}

.iso-report-summary-value {
font-size:24px;
font-weight:700;
color:var(--primary);
line-height: 1.2;
}

/* TABLE WRAPPER - CRITICAL FIX */
.iso-report-table-wrap {
width: 100%;
overflow-x: auto;
overflow-y: hidden;
margin: 0;
border-radius: 16px;
border: 1px solid var(--border);
background: white;
-webkit-overflow-scrolling: touch;
}

/* TABLE - PROPER WIDTH */
.iso-report-table {
width: 100%;
border-collapse: separate;
border-spacing: 0;
font-size: 13px;
min-width: 1460px;
table-layout: fixed;
}

.iso-report-table col.rank-col{width:72px;}
.iso-report-table col.user-col{width:230px;}
.iso-report-table col.role-col{width:150px;}
.iso-report-table col.progress-col{width:190px;}
.iso-report-table col.money-col{width:132px;}
.iso-report-table col.percent-col{width:110px;}
.iso-report-table col.status-col{width:156px;}
.iso-report-table col.action-col{width:72px;}

.iso-report-table thead th {
font-size:12px;
text-transform:uppercase;
letter-spacing:.5px;
color:#555;
padding:16px 12px;
background:var(--primary-soft);
border-bottom:2px solid var(--border);
font-weight:700;
white-space: nowrap;
position: sticky;
top: 0;
z-index: 10;
}

.iso-report-table tbody td {
padding:16px 12px;
border-bottom:1px solid #f0f0f0;
font-weight:500;
white-space: nowrap;
vertical-align:middle;
overflow:hidden;
text-overflow:ellipsis;
}

.iso-report-table th,
.iso-report-table td {
border-right:1px solid #f5e3ec;
}

.iso-report-table tr td:last-child,
.iso-report-table tr th:last-child {
border-right:none;
}

.iso-report-table tbody tr:hover {
background:#fff7fb;
}

.iso-report-table tbody tr.iso-report-row-highlight {
background: #fff9e6;
}

/* USER CELL */
.iso-report-name {
font-weight:600;
display: flex;
align-items: center;
gap: 6px;
color: #333;
min-width:0;
overflow:hidden;
text-overflow:ellipsis;
}

.iso-report-meta {
font-size:11px;
color:#888;
display: flex;
align-items: center;
gap: 4px;
margin-top: 4px;
min-width:0;
overflow:hidden;
text-overflow:ellipsis;
}

/* MONEY VALUES */
.iso-report-money {
font-weight:600;
color:var(--primary);
white-space: nowrap;
text-align:right;
display:block;
}

.iso-report-soft {
font-weight:500;
color:#555;
white-space: nowrap;
text-align:right;
display:block;
}

/* BADGES */
.iso-report-status {
display: inline-block;
}

.iso-report-badge {
font-size:11px;
padding:6px 12px;
border-radius:30px;
font-weight:600;
white-space: nowrap;
display: inline-flex;
align-items: center;
gap: 6px;
}

.badge-soft-success{
background:#e3f9e5;
color:#1e7b2c;
border:1px solid #b8e0be;
}

.badge-soft-warning{
background:#fff3d6;
color:#b45b0a;
border:1px solid #ffdb9f;
}

.badge-soft-danger{
background:#ffe3e3;
color:#b02a37;
border:1px solid #ffbbbb;
}

.badge-soft-secondary{
background:#f0f0f0;
color:#555;
border:1px solid #ddd;
}

.badge-soft-info{
background:#d9f0ff;
color:#096b9b;
border:1px solid #aad4ff;
}

/* ACTION BUTTONS */
.iso-report-actions {
display: flex;
gap: 8px;
white-space: nowrap;
justify-content:center;
}

.iso-report-action-btn {
width:36px;
height:36px;
border-radius:14px;
background:linear-gradient(135deg,#ff5ca8 0%,#e83e8c 100%);
display:inline-flex;
align-items:center;
justify-content:center;
color:#fff;
border:1px solid rgba(232,62,140,.16);
cursor: pointer;
text-decoration: none;
transition: all 0.2s ease;
box-shadow:0 10px 22px rgba(232,62,140,.22);
}

.iso-report-action-btn:hover {
transform:translateY(-2px);
box-shadow:0 14px 28px rgba(232,62,140,.3);
color:#fff;
border-color:rgba(232,62,140,.16);
}

.iso-report-action-btn i{
font-weight:900;
line-height:1;
display:block;
color:currentColor;
}

.iso-report-table td.user-cell,
.iso-report-table td.role-cell,
.iso-report-table td.progress-cell,
.iso-report-table td.rank-cell{
text-align:left;
overflow:visible;
}

.iso-report-table td.money-cell,
.iso-report-table th.money-head,
.iso-report-table td.percent-cell,
.iso-report-table th.percent-head{
text-align:right;
}

.iso-report-table th.status-head,
.iso-report-table td.status-cell,
.iso-report-table th.action-head,
.iso-report-table td.action-cell{
text-align:center;
}

.iso-report-table td.status-cell{
min-width:156px;
padding-left:14px;
padding-right:14px;
overflow:visible;
}

.iso-report-table td.action-cell{
min-width:72px;
padding-left:14px;
padding-right:14px;
overflow:visible;
}

.iso-report-status{
display:flex;
align-items:center;
justify-content:center;
min-width:100%;
}

.iso-report-action-btn{
margin:0 auto;
}

.iso-report-table td.status-cell .iso-report-badge{
justify-content:center;
min-width:108px;
}

/* TOOLTIP */
[data-tooltip] {
position: relative;
cursor: pointer;
}

[data-tooltip]::before,
[data-tooltip]::after {
position: absolute;
left: 50%;
pointer-events: none;
visibility: hidden;
opacity: 0;
transition: opacity .18s ease, transform .18s ease, visibility .18s ease;
}

[data-tooltip]::before {
content: attr(data-tooltip);
bottom: calc(100% + 14px);
transform: translate(-50%, 8px) scale(.96);
padding: 10px 14px;
background: linear-gradient(180deg, rgba(45, 49, 60, 0.98) 0%, rgba(27, 29, 36, 0.98) 100%);
color: #fff;
font-size: 12px;
font-weight: 700;
letter-spacing: .02em;
line-height: 1;
border-radius: 10px;
white-space: nowrap;
z-index: 1205;
box-shadow: 0 16px 32px rgba(24, 28, 38, 0.24);
border: 1px solid rgba(255,255,255,0.08);
backdrop-filter: blur(8px);
}

[data-tooltip]::after {
content: '';
bottom: calc(100% + 8px);
width: 10px;
height: 10px;
background: rgba(27, 29, 36, 0.98);
border-right: 1px solid rgba(255,255,255,0.08);
border-bottom: 1px solid rgba(255,255,255,0.08);
transform: translate(-50%, 6px) rotate(45deg);
z-index: 1204;
}

[data-tooltip]:hover::before,
[data-tooltip]:focus-visible::before {
opacity: 1;
visibility: visible;
transform: translate(-50%, 0) scale(1);
}

[data-tooltip]:hover::after,
[data-tooltip]:focus-visible::after {
opacity: 1;
visibility: visible;
transform: translate(-50%, 0) rotate(45deg);
}

.iso-report-btn[data-tooltip]::before,
.iso-report-btn[data-tooltip]::after,
.iso-report-action-btn[data-tooltip]::before,
.iso-report-action-btn[data-tooltip]::after {
left: 50%;
}

/* EMPTY STATE */
.iso-report-empty {
text-align:center;
padding:60px 20px;
color:#888;
}

/* RESPONSIVE BREAKPOINTS */

/* XL Desktop */
@media (max-width: 1600px) {
.iso-report-table {
min-width: 1300px;
}
}

/* Large Desktop */
@media (max-width: 1400px) {
.iso-report-table {
min-width: 1200px;
}
}

/* Desktop */
@media (max-width: 1200px) {
.iso-report-filter-row {
grid-template-columns: 1fr 1fr 1fr 1fr auto auto;
gap: 12px;
}

.iso-report-summary-card {
min-width: 160px;
padding: 16px;
}
}

/* Tablet Landscape */
@media (max-width: 992px) {
.iso-report-root {
padding: 20px 16px;
}

.iso-report-filter-row {
grid-template-columns: repeat(3, 1fr);
}

.iso-report-summary-card {
min-width: 150px;
padding: 14px;
}

.iso-report-summary-value {
font-size: 22px;
}
}

/* Tablet Portrait */
@media (max-width: 768px) {
.iso-report-root {
padding: 16px 12px;
}

.iso-report-hero {
flex-direction:column;
align-items:flex-start;
}

.iso-report-title {
font-size: 24px;
}

.iso-report-btns {
width: 100%;
justify-content: flex-start;
}

.iso-report-filter-row {
grid-template-columns: repeat(2, 1fr);
gap: 12px;
}

.iso-report-card-body {
padding: 18px;
}

.iso-report-summary-card {
min-width: 140px;
padding: 12px;
}

.iso-report-summary-value {
font-size: 20px;
}

/* Table adjustments */
.iso-report-table-wrap {
margin: 0;
border-radius: 12px;
}

.iso-report-table {
min-width: 1100px;
}

.iso-report-table th,
.iso-report-table td {
padding: 14px 10px;
}
}

/* Mobile Landscape */
@media (max-width: 576px) {
.container-fluid {
padding-left: 12px;
padding-right: 12px;
}

.iso-report-root {
padding: 12px 10px;
border-radius: 20px;
}

.iso-report-card-head {
padding: 14px 16px;
font-size: 14px;
}

.iso-report-card-body {
padding: 16px;
}

.iso-report-filter-row {
grid-template-columns: 1fr;
gap: 10px;
}

.iso-report-filter-btn {
width: 100%;
}

.iso-report-summary-card {
min-width: 130px;
padding: 12px;
}

.iso-report-summary-value {
font-size: 18px;
}

.iso-report-summary-label {
font-size: 11px;
}

.iso-report-table {
min-width: 1000px;
}

.iso-report-table th,
.iso-report-table td {
padding: 12px 8px;
font-size: 12px;
}

.iso-report-badge {
padding: 4px 8px;
font-size: 10px;
}
}

/* Mobile Portrait */
@media (max-width: 375px) {
.iso-report-summary-card {
min-width: 120px;
padding: 10px;
}

.iso-report-summary-value {
font-size: 16px;
}

.iso-report-table {
min-width: 900px;
}
}

/* Touch device optimizations */
@media (hover: none) and (pointer: coarse) {
.iso-report-table-wrap {
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}

.iso-report-summary {
overflow-x: auto;
-webkit-overflow-scrolling: touch;
}

[data-tooltip]::before,
[data-tooltip]::after {
display: none;
}
}

/* Print styles */
@media print {
.iso-report-table-wrap {
overflow: visible;
}

.iso-report-table {
min-width: 100%;
}

.iso-report-action-btn,
.iso-report-btns,
[data-tooltip]::before,
[data-tooltip]::after {
display: none;
}

.iso-report-icon-btn[data-mobile-label],
.iso-report-action-btn[data-mobile-label]{
width:auto !important;
min-width:64px !important;
height:auto !important;
min-height:40px !important;
padding:6px 8px !important;
display:inline-flex !important;
flex-direction:column !important;
align-items:center !important;
justify-content:center !important;
text-align:center !important;
gap:3px !important;
border-radius:10px !important;
}

.iso-report-icon-btn[data-mobile-label]::before,
.iso-report-action-btn[data-mobile-label]::before{
content:none !important;
display:none !important;
}

.iso-report-icon-btn[data-mobile-label]::after,
.iso-report-action-btn[data-mobile-label]::after{
content:attr(data-mobile-label) !important;
position:static !important;
display:block !important;
width:100% !important;
text-align:center !important;
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

@media (max-width: 1024px){
.iso-report-icon-btn[data-mobile-label],
.iso-report-action-btn[data-mobile-label]{
width:auto !important;
min-width:64px !important;
height:auto !important;
min-height:40px !important;
padding:6px 8px !important;
display:inline-flex !important;
flex-direction:column !important;
align-items:center !important;
justify-content:center !important;
text-align:center !important;
gap:3px !important;
border-radius:10px !important;
}

.iso-report-icon-btn[data-mobile-label]::before,
.iso-report-action-btn[data-mobile-label]::before{
content:none !important;
display:none !important;
}

.iso-report-icon-btn[data-mobile-label]::after,
.iso-report-action-btn[data-mobile-label]::after{
content:attr(data-mobile-label) !important;
position:static !important;
display:block !important;
width:100% !important;
text-align:center !important;
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

/* ======================================================
MANAGEMENT DASHBOARD ENHANCEMENTS
====================================================== */
.iso-report-management-strip{
display:grid;
grid-template-columns:1.35fr .85fr .85fr;
gap:16px;
margin-bottom:24px;
}

.iso-report-kpi-grid{
display:grid;
grid-template-columns:repeat(5, minmax(0, 1fr));
gap:16px;
margin-bottom:24px;
}

.iso-report-kpi-card,
.iso-report-insight-card{
background:linear-gradient(180deg,#fff 0%,#fff8fb 100%);
border:1px solid var(--border);
border-radius:18px;
padding:18px;
box-shadow:var(--shadow-sm);
min-width:0;
}

.iso-report-insight-card.is-hero{
background:linear-gradient(135deg,#fff1f7 0%,#ffffff 100%);
}

.iso-report-kpi-label,
.iso-report-insight-label{
font-size:11px;
font-weight:700;
text-transform:uppercase;
letter-spacing:.08em;
color:#7b8798;
margin-bottom:8px;
display:flex;
align-items:center;
gap:6px;
}

.iso-report-kpi-value{
font-size:26px;
font-weight:800;
color:#22304d;
line-height:1.15;
}

.iso-report-kpi-sub{
margin-top:8px;
font-size:12px;
color:#7a7a7a;
}

.iso-report-insight-title{
font-size:15px;
font-weight:800;
color:#22304d;
margin-bottom:6px;
display:flex;
align-items:center;
gap:8px;
}

.iso-report-insight-value{
font-size:20px;
font-weight:800;
color:var(--primary);
line-height:1.2;
}

.iso-report-insight-sub{
margin-top:6px;
font-size:12px;
color:#6b7280;
line-height:1.45;
}

.iso-report-insight-mini{
display:grid;
grid-template-columns:1fr;
gap:16px;
}

.iso-report-period-chip{
display:inline-flex;
align-items:center;
gap:8px;
padding:10px 14px;
border-radius:999px;
background:#fff;
border:1px solid var(--border);
font-size:12px;
font-weight:700;
color:#6b7280;
margin-top:10px;
}

.iso-report-card-head.table-head{
display:flex;
align-items:center;
justify-content:space-between;
gap:16px;
flex-wrap:wrap;
}

.iso-report-head-copy{
display:flex;
flex-direction:column;
gap:4px;
}

.iso-report-head-sub{
font-size:12px;
color:#7a7a7a;
font-weight:500;
}

#isoDatatableControls{
display:flex;
align-items:center;
justify-content:flex-end;
gap:12px;
margin-left:auto;
}

#isoDatatableControls .dt-top,
#isoDatatableControls .dataTables_length,
#isoDatatableControls .dataTables_filter{
display:flex;
align-items:center;
gap:8px;
margin:0 !important;
}

#isoDatatableControls .dataTables_length label,
#isoDatatableControls .dataTables_filter label{
display:flex;
align-items:center;
gap:8px;
margin:0;
font-size:13px;
font-weight:600;
color:#24324a;
}

#isoDatatableControls .dataTables_filter input{
width:220px;
height:40px;
margin:0 !important;
border:1px solid var(--border);
border-radius:12px;
padding:0 12px;
}

#isoDatatableControls .dataTables_length select{
height:40px;
border:1px solid var(--border);
border-radius:12px;
padding:0 10px;
background:#fff;
}

.iso-dt-bottom{
display:flex;
align-items:center;
justify-content:space-between;
gap:14px;
margin-top:16px;
flex-wrap:wrap;
padding:0 4px;
}

#targetsReportTable_wrapper .dataTables_scroll{
width:100%;
}

#targetsReportTable_wrapper .dataTables_scrollHead,
#targetsReportTable_wrapper .dataTables_scrollHeadInner,
#targetsReportTable_wrapper .dataTables_scrollHeadInner table,
#targetsReportTable_wrapper .dataTables_scrollBody table{
width:100% !important;
}

#targetsReportTable_wrapper .dataTables_scrollHeadInner table,
#targetsReportTable_wrapper .dataTables_scrollBody table{
min-width:1460px;
}

#targetsReportTable_wrapper .dataTables_scrollHead{
border-bottom:1px solid var(--border);
}

#targetsReportTable_wrapper .dataTables_scrollBody{
border-bottom:1px solid transparent;
}

.dataTables_info,
.dataTables_paginate{
float:none !important;
margin:0 !important;
}

.dataTables_info{
font-size:13px;
color:#667085;
}

.paginate_button{
border-radius:10px !important;
}

.iso-progress{
min-width:170px;
max-width:100%;
}

.iso-progress-top{
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
margin-bottom:6px;
}

.iso-progress-value{
font-size:13px;
font-weight:800;
color:#22304d;
}

.iso-progress-label{
font-size:11px;
font-weight:700;
color:#7a7a7a;
text-transform:uppercase;
letter-spacing:.05em;
}

.iso-progress-bar{
height:9px;
background:#f3dbe5;
border-radius:999px;
overflow:hidden;
position:relative;
}

.iso-progress-fill{
height:100%;
border-radius:999px;
background:linear-gradient(135deg,#ff6aa2,#e83e8c);
}

.iso-progress-fill.is-strong{
background:linear-gradient(135deg,#34c759,#1e9e49);
}

.iso-progress-fill.is-warning{
background:linear-gradient(135deg,#ffb347,#f59e0b);
}

.iso-progress-fill.is-risk{
background:linear-gradient(135deg,#ff7b7b,#e11d48);
}

.iso-report-rank{
display:inline-flex;
align-items:center;
justify-content:center;
width:30px;
height:30px;
border-radius:999px;
background:#fff0f7;
color:var(--primary);
font-weight:800;
font-size:12px;
border:1px solid #f8cfe1;
}

.iso-report-modal{
position:fixed;
inset:0;
display:none;
align-items:center;
justify-content:center;
padding:24px;
background:rgba(48, 21, 36, .48);
backdrop-filter:blur(4px);
z-index:1200;
}

.iso-report-modal.show{
display:flex;
}

.iso-report-modal-dialog{
width:min(1180px, 100%);
max-height:calc(100vh - 48px);
background:#fff;
border:1px solid var(--border);
border-radius:24px;
box-shadow:0 30px 70px rgba(69, 23, 48, .22);
overflow:hidden;
display:flex;
flex-direction:column;
}

.iso-report-modal-head{
display:flex;
align-items:flex-start;
justify-content:space-between;
gap:18px;
padding:22px 24px;
background:linear-gradient(135deg,#fff1f7 0%,#ffffff 100%);
border-bottom:1px solid var(--border);
}

.iso-report-modal-kicker{
display:inline-flex;
align-items:center;
gap:8px;
padding:6px 12px;
border-radius:999px;
background:#fff;
border:1px solid var(--border);
font-size:11px;
font-weight:800;
letter-spacing:.08em;
text-transform:uppercase;
color:var(--primary-dark);
margin-bottom:10px;
}

.iso-report-modal-title{
margin:0 0 6px;
font-size:28px;
font-weight:800;
color:#22304d;
}

.iso-report-modal-subtitle{
font-size:13px;
color:#6b7280;
line-height:1.5;
max-width:700px;
}

.iso-report-modal-head-actions{
display:flex;
align-items:center;
gap:10px;
flex-wrap:wrap;
justify-content:flex-end;
}

.iso-report-modal-close{
width:42px;
height:42px;
border:none;
border-radius:12px;
background:#fff;
border:1px solid var(--border);
color:#555;
cursor:pointer;
}

.iso-report-modal-close:hover{
background:#fff4fa;
color:var(--primary);
}

.iso-report-modal-body{
padding:24px;
overflow:auto;
display:grid;
gap:18px;
}

.iso-report-modal-hero{
display:grid;
grid-template-columns:1.35fr .95fr;
gap:18px;
}

.iso-report-modal-card{
background:#fff;
border:1px solid var(--border);
border-radius:20px;
padding:18px;
box-shadow:var(--shadow-sm);
min-width:0;
}

.iso-report-modal-main{
background:linear-gradient(135deg,#fff7fb 0%,#ffffff 100%);
}

.iso-report-modal-user{
display:flex;
align-items:flex-start;
justify-content:space-between;
gap:16px;
margin-bottom:16px;
}

.iso-report-modal-name{
font-size:24px;
font-weight:800;
color:#22304d;
}

.iso-report-modal-meta{
display:flex;
flex-wrap:wrap;
gap:12px;
margin-top:6px;
font-size:13px;
color:#6b7280;
}

.iso-report-modal-grid{
display:grid;
grid-template-columns:repeat(3, minmax(0, 1fr));
gap:12px;
}

.iso-report-modal-stat{
padding:14px;
border-radius:16px;
background:#fff;
border:1px solid #f3d8e5;
}

.iso-report-modal-stat-label{
font-size:11px;
font-weight:700;
text-transform:uppercase;
letter-spacing:.08em;
color:#7b8798;
margin-bottom:6px;
}

.iso-report-modal-stat-value{
font-size:20px;
font-weight:800;
color:#22304d;
line-height:1.2;
}

.iso-report-modal-side{
display:grid;
gap:18px;
}

.iso-report-modal-section-title{
font-size:15px;
font-weight:800;
color:#22304d;
margin-bottom:12px;
display:flex;
align-items:center;
gap:8px;
}

.iso-report-modal-info{
display:grid;
grid-template-columns:repeat(2, minmax(0, 1fr));
gap:12px;
}

.iso-report-modal-info-item{
padding:12px 14px;
border-radius:14px;
background:#fff8fb;
border:1px solid #f3d8e5;
}

.iso-report-modal-info-label{
font-size:11px;
font-weight:700;
text-transform:uppercase;
letter-spacing:.08em;
color:#7b8798;
margin-bottom:4px;
}

.iso-report-modal-info-value{
font-size:14px;
font-weight:700;
color:#22304d;
word-break:break-word;
}

.iso-report-insight-strip{
display:grid;
grid-template-columns:repeat(3, minmax(0, 1fr));
gap:12px;
}

.iso-report-insight-pill{
padding:14px 16px;
border-radius:16px;
background:#fff8fb;
border:1px solid #f3d8e5;
}

.iso-report-insight-pill strong{
display:block;
font-size:18px;
color:#22304d;
margin-top:4px;
}

.iso-report-insight-pill span{
font-size:11px;
font-weight:700;
text-transform:uppercase;
letter-spacing:.08em;
color:#7b8798;
}

.iso-report-history-wrap,
.iso-report-collection-wrap{
overflow:auto;
border:1px solid #f3d8e5;
border-radius:16px;
background:#fff;
}

.iso-report-collection-wrap{
display:flex;
justify-content:flex-start;
}

.iso-report-mini-table{
width:100%;
border-collapse:collapse;
font-size:13px;
min-width:0;
table-layout:fixed;
}

.iso-report-mini-table.compact-three-col{
width:min(760px, 100%);
min-width:520px;
table-layout:auto;
}

.iso-report-mini-table th{
background:#fff3f8;
color:#6d284a;
font-size:11px;
font-weight:800;
text-transform:uppercase;
letter-spacing:.08em;
padding:10px 14px;
border-bottom:1px solid #f1d6e3;
vertical-align:middle;
line-height:1.2;
}

.iso-report-mini-table td{
padding:12px 14px;
border-bottom:1px solid #f8e3ec;
color:#334155;
vertical-align:middle;
line-height:1.3;
}

.iso-report-mini-table tbody tr:last-child td{
border-bottom:none;
}

.iso-report-mini-table .col-date{
width:30%;
}

.iso-report-mini-table .col-amount{
width:24%;
text-align:right;
}

.iso-report-mini-table .col-status{
width:22%;
text-align:center;
}

.iso-report-collection-table{
width:min(760px, 100%);
min-width:560px;
table-layout:auto;
}

.iso-report-collection-table .col-date{
width:38%;
}

.iso-report-collection-table .col-amount{
width:30%;
}

.iso-report-collection-table .col-status{
width:32%;
}

.iso-report-mini-table.compact-three-col .col-date{
width:38%;
}

.iso-report-mini-table.compact-three-col .col-amount{
width:28%;
}

.iso-report-mini-table.compact-three-col .col-status{
width:24%;
}

.iso-report-mini-table th.col-date{
text-align:left;
}

.iso-report-mini-table th.col-amount{
text-align:right;
}

.iso-report-mini-table th.col-status{
text-align:center;
}

.iso-report-mini-table td.col-date{
font-weight:700;
color:#475569;
text-align:left;
font-variant-numeric:tabular-nums;
}

.iso-report-mini-table td.col-amount{
font-weight:800;
color:#22304d;
font-variant-numeric:tabular-nums;
}

.iso-report-mini-table td.col-status{
text-align:center;
}

.iso-report-mini-table .mini-status{
display:inline-flex;
align-items:center;
justify-content:center;
gap:6px;
min-width:92px;
padding:6px 12px;
border-radius:999px;
font-size:11px;
font-weight:700;
text-transform:capitalize;
border:1px solid #d7dee7;
background:#f8fafc;
color:#475569;
}

.iso-report-mini-table .mini-status.is-approved{
background:#e8f8ee;
border-color:#b8e0be;
color:#1e7b2c;
}

.iso-report-mini-table .mini-status.is-progress{
background:#fff3d6;
border-color:#ffdb9f;
color:#b45b0a;
}

.iso-report-mini-table .mini-status.is-pending{
background:#fff3d6;
border-color:#ffdb9f;
color:#b45b0a;
}

.iso-report-mini-table .mini-status.is-rejected{
background:#ffe3e3;
border-color:#ffbbbb;
color:#b02a37;
}

.iso-report-modal-empty{
padding:16px;
text-align:center;
color:#7a7a7a;
font-size:13px;
}

.iso-report-collection-wrap{
display:flex;
justify-content:flex-start;
padding:0;
}

.iso-report-modal-footer{
display:flex;
justify-content:flex-end;
gap:10px;
padding:18px 24px;
border-top:1px solid var(--border);
background:#fff;
}

@media (max-width: 1200px) {
.iso-report-management-strip{
grid-template-columns:1fr;
}
.iso-report-kpi-grid{
grid-template-columns:repeat(3, minmax(0, 1fr));
}
.iso-report-modal-hero{
grid-template-columns:1fr;
}
}

@media (max-width: 768px) {
.iso-report-kpi-grid{
grid-template-columns:repeat(2, minmax(0, 1fr));
}
#isoDatatableControls{
width:100%;
justify-content:flex-start;
}
#isoDatatableControls .dt-top{
width:100%;
flex-wrap:wrap;
}
.iso-dt-bottom{
flex-direction:column;
align-items:flex-start;
}
.iso-report-modal{
padding:12px;
}
.iso-report-modal-head{
padding:18px;
flex-direction:column;
}
.iso-report-modal-body{
padding:18px;
}
.iso-report-modal-grid,
.iso-report-modal-info,
.iso-report-insight-strip{
grid-template-columns:1fr;
}
.iso-report-collection-table{
min-width:100%;
}
.iso-report-modal-footer{
padding:16px 18px;
flex-wrap:wrap;
}
}

@media (max-width: 576px) {
.iso-report-kpi-grid{
grid-template-columns:1fr;
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
<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container-fluid py-3">
    <div class="iso-report-root">

        <!-- Modern Hero Section -->
        <div class="iso-report-hero">
            <div>
                <h2 class="iso-report-title">
                    <i class="fas fa-chart-line" style="background: linear-gradient(135deg, #e83e8c 0%, #d2317a 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-right: 8px;"></i>
                    Target Report
                </h2>
                <p class="iso-report-subtitle">
                    <i class="fas fa-calendar-alt" style="margin-right: 6px; color: #e83e8c;"></i>
                    Monthly target report with carry forward, effective target, achieved amount, shortfall, and excess.
                </p>
            </div>

           <div class="iso-report-btns">

<a href="index.php?page=targets/setup"
   class="iso-report-btn iso-report-icon-btn"
   data-mobile-label="Setup"
   data-tooltip="Setup Target">
   <i class="fas fa-bullseye"></i>
</a>

<a href="index.php?page=targets/list"
   class="iso-report-btn iso-report-icon-btn"
   data-mobile-label="List"
   data-tooltip="Target List">
   <i class="fas fa-list"></i>
</a>

<a href="index.php?page=targets/export&year=<?= (int)$fYear ?>&month=<?= (int)$fMonth ?>&user_id=<?= (int)$fUserId ?>&role_id=<?= (int)$fRoleId ?>&search=<?= urlencode($search) ?>"
   class="iso-report-btn iso-report-icon-btn iso-report-export-btn"
   data-mobile-label="Export"
   data-tooltip="Export summary for filtered users">
   <i class="fas fa-file-export"></i>
</a>

</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <div class="iso-report-management-strip">
            <div class="iso-report-insight-card is-hero">
                <div class="iso-report-insight-label">
                    <i class="fas fa-calendar-check" style="color:#e83e8c;"></i>
                    Management Overview
                </div>
                <div class="iso-report-insight-title">
                    <?= h($selectedPeriodLabel) ?> Performance Command Center
                </div>
                <div class="iso-report-insight-sub">
                    One-page target review for Super Admin and HR with target coverage, carry-forward risk, recovery focus, and user-wise export actions.
                </div>
                <span class="iso-report-period-chip">
                    <i class="fas fa-layer-group" style="color:#e83e8c;"></i>
                    <?= number_format($totalUsers) ?> active target users in scope
                </span>
            </div>
            <div class="iso-report-insight-mini">
                <div class="iso-report-insight-card">
                    <div class="iso-report-insight-label">
                        <i class="fas fa-crown" style="color:#e83e8c;"></i>
                        Top Performer
                    </div>
                    <div class="iso-report-insight-value"><?= h($topPerformer['user_name'] ?? 'No data') ?></div>
                    <div class="iso-report-insight-sub">
                        <?= isset($topPerformer['progress_percent']) ? number_format((float)$topPerformer['progress_percent'], 1) . '% achieved' : 'No progress yet' ?>
                    </div>
                </div>
                <div class="iso-report-insight-card">
                    <div class="iso-report-insight-label">
                        <i class="fas fa-triangle-exclamation" style="color:#e83e8c;"></i>
                        Biggest Shortfall
                    </div>
                    <div class="iso-report-insight-value"><?= h($biggestShortfallUser['user_name'] ?? 'No gap') ?></div>
                    <div class="iso-report-insight-sub">
                        Rs <?= number_format((float)($biggestShortfallUser['shortfall_amount'] ?? 0), 2) ?> pending against target
                    </div>
                </div>
            </div>
            <div class="iso-report-insight-mini">
                <div class="iso-report-insight-card">
                    <div class="iso-report-insight-label">
                        <i class="fas fa-gauge-high" style="color:#e83e8c;"></i>
                        Collection Efficiency
                    </div>
                    <div class="iso-report-insight-value"><?= number_format($collectionEfficiency, 1) ?>%</div>
                    <div class="iso-report-insight-sub">
                        Achieved vs effective target for the selected month
                    </div>
                </div>
                <div class="iso-report-insight-card">
                    <div class="iso-report-insight-label">
                        <i class="fas fa-repeat" style="color:#e83e8c;"></i>
                        Carry Risk Users
                    </div>
                    <div class="iso-report-insight-value"><?= number_format($totalCarryRiskUsers) ?></div>
                    <div class="iso-report-insight-sub">
                        Users carrying previous target liability into this month
                    </div>
                </div>
            </div>
        </div>

        <!-- Modern Filter Card -->
        <div class="iso-report-card">
            <div class="iso-report-card-head">
                <i class="fas fa-sliders-h"></i>
                Filter Report
            </div>
            <div class="iso-report-card-body">
                <form method="get" action="">
                    <input type="hidden" name="page" value="targets/report">

                    <div class="iso-report-filter-row">
                        <div>
                            <label class="form-label">
                                <i class="fas fa-search" style="margin-right: 4px; color: #e83e8c;"></i>
                                Search
                            </label>
                            <input type="text" name="search" class="form-control" value="<?= h($search) ?>" placeholder="Search user, email, role...">
                        </div>

                        <div>
                            <label class="form-label">
                                <i class="fas fa-calendar" style="margin-right: 4px; color: #e83e8c;"></i>
                                Year
                            </label>
                            <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= h($fYear) ?>">
                        </div>

                        <div>
                            <label class="form-label">
                                <i class="fas fa-calendar-alt" style="margin-right: 4px; color: #e83e8c;"></i>
                                Month
                            </label>
                            <select name="month" class="form-select">
                                <?php foreach ($monthNames as $num => $name): ?>
                                    <option value="<?= $num ?>" <?= $fMonth === $num ? 'selected' : '' ?>>
                                        <?= h($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">
                                <i class="fas fa-user" style="margin-right: 4px; color: #e83e8c;"></i>
                                User
                            </label>
                            <select name="user_id" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($filterUsers as $fu): ?>
                                    <option value="<?= (int)$fu['id'] ?>" <?= $fUserId === (int)$fu['id'] ? 'selected' : '' ?>>
                                        <?= h($fu['name']) ?> | <?= h($fu['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">
                                <i class="fas fa-briefcase" style="margin-right: 4px; color: #e83e8c;"></i>
                                Role
                            </label>
                            <select name="role_id" class="form-select">
                                <option value="">All Roles</option>
                                <?php foreach ($filterRoles as $fr): ?>
                                    <option value="<?= (int)$fr['id'] ?>" <?= $fRoleId === (int)$fr['id'] ? 'selected' : '' ?>>
                                        <?= h($fr['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
<button type="submit"
        class="iso-report-btn iso-report-icon-btn iso-report-filter-btn"
        data-mobile-label="Apply"
        data-tooltip="Load Report">
    <i class="fas fa-filter"></i>
</button>
                        </div>

                        <div>
<a href="index.php?page=targets/report"
   class="iso-report-btn iso-report-icon-btn iso-report-filter-btn"
   data-mobile-label="Reset"
   data-tooltip="Reset Filters">
   <i class="fas fa-undo"></i>
</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="iso-report-kpi-grid">
            <div class="iso-report-kpi-card" data-tooltip="Total effective target amount for this month">
                <div class="iso-report-kpi-label"><i class="fas fa-crosshairs" style="color:#e83e8c;"></i> Total Target</div>
                <div class="iso-report-kpi-value">Rs <?= number_format($totalEffectiveTarget, 2) ?></div>
                <div class="iso-report-kpi-sub">Base target plus opening carry forward</div>
            </div>
            <div class="iso-report-kpi-card" data-tooltip="Total achieved amount for this month">
                <div class="iso-report-kpi-label"><i class="fas fa-trophy" style="color:#e83e8c;"></i> Total Achieved</div>
                <div class="iso-report-kpi-value">Rs <?= number_format($totalAchieved, 2) ?></div>
                <div class="iso-report-kpi-sub">Approved collected amount mapped to staff</div>
            </div>
            <div class="iso-report-kpi-card" data-tooltip="Total shortfall across all users">
                <div class="iso-report-kpi-label"><i class="fas fa-arrow-trend-down" style="color:#e83e8c;"></i> Total Shortfall</div>
                <div class="iso-report-kpi-value">Rs <?= number_format($totalShortfall, 2) ?></div>
                <div class="iso-report-kpi-sub">Recovery amount still needed this month</div>
            </div>
            <div class="iso-report-kpi-card" data-tooltip="Total excess achieved beyond effective target">
                <div class="iso-report-kpi-label"><i class="fas fa-arrow-trend-up" style="color:#e83e8c;"></i> Total Excess</div>
                <div class="iso-report-kpi-value">Rs <?= number_format($totalExcess, 2) ?></div>
                <div class="iso-report-kpi-sub">Additional collection over effective target</div>
            </div>
            <div class="iso-report-kpi-card" data-tooltip="Overall collection efficiency percentage">
                <div class="iso-report-kpi-label"><i class="fas fa-percent" style="color:#e83e8c;"></i> Efficiency</div>
                <div class="iso-report-kpi-value"><?= number_format($collectionEfficiency, 1) ?>%</div>
                <div class="iso-report-kpi-sub">Achieved divided by effective target</div>
            </div>
        </div>

        <!-- Modern Report Table Card -->
        <div class="iso-report-card">
            <div class="iso-report-card-head table-head">
                <div class="iso-report-head-copy">
                    <div><i class="fas fa-file-alt"></i> Team Performance Table</div>
                    <div class="iso-report-head-sub">Review staff performance, rank risk, track carry-forward impact, and export detailed user reports.</div>
                </div>
                <div id="isoDatatableControls"></div>
            </div>

            <div class="iso-report-card-body">
                <div class="iso-report-table-wrap">
                    <table class="iso-report-table no-mobile-cards" id="targetsReportTable">
                        <colgroup>
                            <col class="rank-col">
                            <col class="user-col">
                            <col class="role-col">
                            <col class="progress-col">
                            <col class="money-col">
                            <col class="money-col">
                            <col class="money-col">
                            <col class="money-col">
                            <col class="money-col">
                            <col class="money-col">
                            <col class="percent-col">
                            <col class="money-col">
                            <col class="status-col">
                            <col class="action-col">
                        </colgroup>
                        <thead>
                            <tr>
                                <th data-tooltip="Performance rank">Rank</th>
                                <th data-tooltip="User information">User</th>
                                <th data-tooltip="User role">Role</th>
                                <th data-tooltip="Achievement percentage vs effective target">Progress</th>
                                <th class="money-head" data-tooltip="Base target amount">Base Target</th>
                                <th class="money-head" data-tooltip="Opening carry forward">Opening Carry</th>
                                <th class="money-head" data-tooltip="Effective target (Base + Carry)">Effective Target</th>
                                <th class="money-head" data-tooltip="Achieved amount">Achieved</th>
                                <th class="money-head" data-tooltip="Excess amount">Excess</th>
                                <th class="money-head" data-tooltip="Shortfall amount">Shortfall</th>
                                <th class="percent-head" data-tooltip="Incentive percentage">Incentive %</th>
                                <th class="money-head" data-tooltip="Incentive amount">Incentive</th>
                                <th class="status-head" data-tooltip="Current status">Status</th>
                                <th class="action-head" data-tooltip="Action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="14" class="iso-report-empty">
                                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                                        <div>No report data found.</div>
                                        <div style="font-size: 13px; margin-top: 8px;">Try adjusting your filters</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $i => $row): ?>
                                    <tr class="<?= !empty($row['row_highlight']) ? 'iso-report-row-highlight' : '' ?>">
                                        <td class="rank-cell" data-order="<?= number_format((float)($row['shortfall_amount'] ?? 0), 2, '.', '') ?>">
                                            <span class="iso-report-rank"><?= $i + 1 ?></span>
                                        </td>
                                        <td class="user-cell">
                                            <div class="iso-report-name">
                                                <i class="fas fa-user-circle" style="margin-right: 6px; color: #e83e8c;"></i>
                                                <?= h($row['user_name'] ?? '-') ?>
                                            </div>
                                            <div class="iso-report-meta">
                                                <i class="fas fa-envelope" style="margin-right: 4px; font-size: 10px;"></i>
                                                <?= h($row['user_email'] ?? '-') ?>
                                            </div>
                                        </td>
                                        <td class="role-cell">
                                            <span class="iso-report-meta">
                                                <i class="fas fa-briefcase" style="margin-right: 4px;"></i>
                                                <?= h($row['role_name'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td class="progress-cell" data-order="<?= number_format((float)($row['progress_percent'] ?? 0), 2, '.', '') ?>">
                                            <?php
                                                $progress = (float)($row['progress_percent'] ?? 0);
                                                $progressWidth = min($progress, 100);
                                                $progressClass = 'is-risk';
                                                if ($progress >= 100) {
                                                    $progressClass = 'is-strong';
                                                } elseif ($progress >= 75) {
                                                    $progressClass = 'is-warning';
                                                } elseif ($progress > 0) {
                                                    $progressClass = '';
                                                }
                                            ?>
                                            <div class="iso-progress">
                                                <div class="iso-progress-top">
                                                    <span class="iso-progress-value"><?= number_format($progress, 1) ?>%</span>
                                                    <span class="iso-progress-label"><?= h($row['progress_label'] ?? 'No Target') ?></span>
                                                </div>
                                                <div class="iso-progress-bar">
                                                    <div class="iso-progress-fill <?= $progressClass ?>" style="width: <?= $progressWidth ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="money-cell"><span class="iso-report-money">Rs <?= number_format((float)($row['base_target'] ?? 0), 2) ?></span></td>
                                        <td class="money-cell"><span class="iso-report-soft">Rs <?= number_format((float)($row['opening_carry'] ?? 0), 2) ?></span></td>
                                        <td class="money-cell"><span class="iso-report-money">Rs <?= number_format((float)($row['effective_target'] ?? 0), 2) ?></span></td>
                                        <td class="money-cell"><span class="iso-report-money">Rs <?= number_format((float)($row['achieved_amount'] ?? 0), 2) ?></span></td>
                                        <td class="money-cell"><span class="iso-report-soft">Rs <?= number_format((float)($row['excess_amount'] ?? 0), 2) ?></span></td>
                                        <td class="money-cell"><span class="iso-report-soft">Rs <?= number_format((float)($row['shortfall_amount'] ?? 0), 2) ?></span></td>
                                        <td class="percent-cell">
                                            <span class="iso-report-soft">
                                                <?= number_format((float)($row['incentive_percent'] ?? 0), 2) ?>%
                                            </span>
                                        </td>
                                        <td class="money-cell">
                                            <span class="iso-report-money">Rs <?= number_format((float)($row['incentive_amount'] ?? 0), 2) ?></span>
                                        </td>
                                        <td class="status-cell">
										<div class="iso-report-status">
                                            <span class="iso-report-badge <?= h($row['status_class'] ?? 'badge-soft-secondary') ?>">
                                                <i class="fas 
                                                    <?= $row['status_class'] == 'badge-soft-success' ? 'fa-check-circle' : 
                                                       ($row['status_class'] == 'badge-soft-warning' ? 'fa-clock' : 
                                                       ($row['status_class'] == 'badge-soft-danger' ? 'fa-times-circle' : 
                                                       ($row['status_class'] == 'badge-soft-info' ? 'fa-info-circle' : 'fa-circle'))) ?>">
                                                </i>
                                                <?= h($row['status_text'] ?? 'No Target') ?>
                                            </span>
											</div>
                                        </td>
                                        <td class="action-cell">
                                            <div class="iso-report-actions">
                                                <button
                                                    type="button"
                                                    class="iso-report-action-btn js-view-report"
                                                    data-user-id="<?= (int)($row['user_id'] ?? 0) ?>"
                                                    data-mobile-label="View"
                                                    data-tooltip="View detailed performance">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="iso-report-modal" id="isoReportViewModal">
            <div class="iso-report-modal-dialog">
                <div class="iso-report-modal-head">
                    <div>
                        <div class="iso-report-modal-kicker">
                            <i class="fas fa-chart-line"></i>
                            Staff Performance Snapshot
                        </div>
                        <h3 class="iso-report-modal-title" id="isoModalUserName">Performance Details</h3>
                        <div class="iso-report-modal-subtitle" id="isoModalSubtitle">
                            Review target summary, carry-forward trail, and current month collections before download.
                        </div>
                    </div>
                    <div class="iso-report-modal-head-actions">
                        <span class="iso-report-badge badge-soft-secondary" id="isoModalStatusBadge">No Target</span>
                        <a href="#" class="iso-report-btn iso-report-btn-outline" id="isoModalDownloadBtn">
                            <i class="fas fa-download"></i>
                            Download Detailed Report
                        </a>
                        <button type="button" class="iso-report-modal-close" id="isoModalCloseBtn" aria-label="Close performance view">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="iso-report-modal-body">
                    <div class="iso-report-modal-hero">
                        <div class="iso-report-modal-card iso-report-modal-main">
                            <div class="iso-report-modal-user">
                                <div>
                                    <div class="iso-report-modal-name" id="isoModalHeroName">-</div>
                                    <div class="iso-report-modal-meta">
                                        <span><i class="fas fa-briefcase"></i> <span id="isoModalHeroRole">-</span></span>
                                        <span><i class="fas fa-envelope"></i> <span id="isoModalHeroEmail">-</span></span>
                                        <span><i class="fas fa-calendar-alt"></i> <span id="isoModalHeroPeriod">-</span></span>
                                    </div>
                                </div>
                            </div>

                            <div class="iso-progress" style="margin-bottom:16px; max-width:100%;">
                                <div class="iso-progress-top">
                                    <span class="iso-progress-value" id="isoModalProgressValue">0.0%</span>
                                    <span class="iso-progress-label" id="isoModalProgressLabel">No Target</span>
                                </div>
                                <div class="iso-progress-bar">
                                    <div class="iso-progress-fill" id="isoModalProgressFill" style="width:0%;"></div>
                                </div>
                            </div>

                            <div class="iso-report-modal-grid">
                                <div class="iso-report-modal-stat">
                                    <div class="iso-report-modal-stat-label">Base Target</div>
                                    <div class="iso-report-modal-stat-value" id="isoModalBaseTarget">Rs 0.00</div>
                                </div>
                                <div class="iso-report-modal-stat">
                                    <div class="iso-report-modal-stat-label">Opening Carry</div>
                                    <div class="iso-report-modal-stat-value" id="isoModalOpeningCarry">Rs 0.00</div>
                                </div>
                                <div class="iso-report-modal-stat">
                                    <div class="iso-report-modal-stat-label">Effective Target</div>
                                    <div class="iso-report-modal-stat-value" id="isoModalEffectiveTarget">Rs 0.00</div>
                                </div>
                                <div class="iso-report-modal-stat">
                                    <div class="iso-report-modal-stat-label">Achieved</div>
                                    <div class="iso-report-modal-stat-value" id="isoModalAchieved">Rs 0.00</div>
                                </div>
                                <div class="iso-report-modal-stat">
                                    <div class="iso-report-modal-stat-label">Shortfall / Excess</div>
                                    <div class="iso-report-modal-stat-value" id="isoModalGap">Rs 0.00</div>
                                </div>
                                <div class="iso-report-modal-stat">
                                    <div class="iso-report-modal-stat-label">Incentive Amount</div>
                                    <div class="iso-report-modal-stat-value" id="isoModalIncentiveAmount">Rs 0.00</div>
                                </div>
                            </div>
                        </div>

                        <div class="iso-report-modal-side">
                            <div class="iso-report-modal-card">
                                <div class="iso-report-modal-section-title">
                                    <i class="fas fa-user-check"></i>
                                    Staff Details
                                </div>
                                <div class="iso-report-modal-info">
                                    <div class="iso-report-modal-info-item">
                                        <div class="iso-report-modal-info-label">User</div>
                                        <div class="iso-report-modal-info-value" id="isoModalInfoUser">-</div>
                                    </div>
                                    <div class="iso-report-modal-info-item">
                                        <div class="iso-report-modal-info-label">Role</div>
                                        <div class="iso-report-modal-info-value" id="isoModalInfoRole">-</div>
                                    </div>
                                    <div class="iso-report-modal-info-item">
                                        <div class="iso-report-modal-info-label">Email</div>
                                        <div class="iso-report-modal-info-value" id="isoModalInfoEmail">-</div>
                                    </div>
                                    <div class="iso-report-modal-info-item">
                                        <div class="iso-report-modal-info-label">Period</div>
                                        <div class="iso-report-modal-info-value" id="isoModalInfoPeriod">-</div>
                                    </div>
                                </div>
                            </div>

                            <div class="iso-report-modal-card">
                                <div class="iso-report-modal-section-title">
                                    <i class="fas fa-lightbulb"></i>
                                    Performance Insights
                                </div>
                                <div class="iso-report-insight-strip">
                                    <div class="iso-report-insight-pill">
                                        <span>Carry Risk</span>
                                        <strong id="isoModalCarryRisk">No</strong>
                                    </div>
                                    <div class="iso-report-insight-pill">
                                        <span>Shortfall Gap</span>
                                        <strong id="isoModalShortfall">Rs 0.00</strong>
                                    </div>
                                    <div class="iso-report-insight-pill">
                                        <span>Incentive %</span>
                                        <strong id="isoModalIncentivePercent">0.00%</strong>
                                    </div>
                                </div>
                                <div class="iso-report-modal-info-item" style="margin-top:12px;">
                                    <div class="iso-report-modal-info-label">Management Note</div>
                                    <div class="iso-report-modal-info-value" id="isoModalInsightMessage">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="iso-report-modal-card">
                        <div class="iso-report-modal-section-title">
                            <i class="fas fa-clock-rotate-left"></i>
                            Monthly History
                        </div>
                        <div class="iso-report-history-wrap">
                            <table class="iso-report-mini-table no-mobile-cards">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th class="col-amount">Base Target</th>
                                        <th class="col-amount">Opening Carry</th>
                                        <th class="col-amount">Effective Target</th>
                                        <th class="col-amount">Achieved</th>
                                        <th class="col-amount">Shortfall</th>
                                        <th class="col-status">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="isoModalHistoryRows">
                                    <tr><td colspan="7" class="iso-report-modal-empty">No history available.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="iso-report-modal-card">
                        <div class="iso-report-modal-section-title">
                            <i class="fas fa-money-check-dollar"></i>
                            Current Month Collections
                        </div>
                        <div class="iso-report-collection-wrap">
                            <table class="iso-report-mini-table iso-report-collection-table no-mobile-cards">
                                <thead>
                                    <tr>
                                        <th class="col-date">Date</th>
                                        <th class="col-amount">Amount</th>
                                        <th class="col-status">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="isoModalCollectionRows">
                                    <tr><td colspan="3" class="iso-report-modal-empty">No collection entries available.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="iso-report-modal-footer">
                    <button type="button" class="iso-report-btn iso-report-btn-outline" id="isoModalFooterCloseBtn">Close</button>
                    <a href="#" class="iso-report-btn iso-report-btn-primary" id="isoModalFooterDownloadBtn">
                        <i class="fas fa-download"></i>
                        Download Detailed Report
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
<script id="isoReportViewData" type="application/json"><?= json_encode($viewModalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatMoney(value) {
        const amount = Number(value || 0);
        return 'Rs ' + amount.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatPercent(value) {
        const amount = Number(value || 0);
        return amount.toLocaleString('en-IN', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        }) + '%';
    }

    function statusIcon(statusClass) {
        if (statusClass === 'badge-soft-success') return 'fa-check-circle';
        if (statusClass === 'badge-soft-warning') return 'fa-clock';
        if (statusClass === 'badge-soft-danger') return 'fa-times-circle';
        if (statusClass === 'badge-soft-info') return 'fa-info-circle';
        return 'fa-circle';
    }

    function paymentStatusClass(statusText) {
        const status = String(statusText || '').toLowerCase();
        if (status === 'approved') return 'mini-status is-approved';
        if (status === 'achieved') return 'mini-status is-approved';
        if (status === 'in progress') return 'mini-status is-progress';
        if (status === 'pending') return 'mini-status is-pending';
        if (status === 'not started') return 'mini-status is-rejected';
        if (status === 'rejected') return 'mini-status is-rejected';
        return 'mini-status';
    }

    const modal = document.getElementById('isoReportViewModal');
    const modalDataNode = document.getElementById('isoReportViewData');
    const modalData = modalDataNode ? JSON.parse(modalDataNode.textContent || '{}') : {};
    const modalCloseBtn = document.getElementById('isoModalCloseBtn');
    const modalFooterCloseBtn = document.getElementById('isoModalFooterCloseBtn');

    function setProgressState(progressPercent) {
        const progressValue = Number(progressPercent || 0);
        const progressFill = document.getElementById('isoModalProgressFill');
        const width = Math.min(progressValue, 100);
        let progressClass = 'iso-progress-fill is-risk';

        if (progressValue >= 100) {
            progressClass = 'iso-progress-fill is-strong';
        } else if (progressValue >= 75) {
            progressClass = 'iso-progress-fill is-warning';
        } else if (progressValue > 0) {
            progressClass = 'iso-progress-fill';
        }

        if (progressFill) {
            progressFill.className = progressClass;
            progressFill.style.width = width + '%';
        }
    }

    function openReportModal(userId) {
        const data = modalData[String(userId)] || modalData[userId];
        if (!data || !modal) return;

        document.getElementById('isoModalUserName').textContent = data.user_name || 'Performance Details';
        document.getElementById('isoModalSubtitle').textContent = 'Review target summary, carry-forward trail, and current month collections for ' + (data.period_label || 'the selected period') + '.';
        document.getElementById('isoModalHeroName').textContent = data.user_name || '-';
        document.getElementById('isoModalHeroRole').textContent = data.role_name || '-';
        document.getElementById('isoModalHeroEmail').textContent = data.user_email || '-';
        document.getElementById('isoModalHeroPeriod').textContent = data.period_label || '-';
        document.getElementById('isoModalInfoUser').textContent = data.user_name || '-';
        document.getElementById('isoModalInfoRole').textContent = data.role_name || '-';
        document.getElementById('isoModalInfoEmail').textContent = data.user_email || '-';
        document.getElementById('isoModalInfoPeriod').textContent = data.period_label || '-';
        document.getElementById('isoModalProgressValue').textContent = formatPercent(data.progress_percent || 0);
        document.getElementById('isoModalProgressLabel').textContent = data.progress_label || 'No Target';
        document.getElementById('isoModalBaseTarget').textContent = formatMoney(data.base_target || 0);
        document.getElementById('isoModalOpeningCarry').textContent = formatMoney(data.opening_carry || 0);
        document.getElementById('isoModalEffectiveTarget').textContent = formatMoney(data.effective_target || 0);
        document.getElementById('isoModalAchieved').textContent = formatMoney(data.achieved_amount || 0);
        document.getElementById('isoModalGap').textContent = (Number(data.shortfall_amount || 0) > 0 ? 'Shortfall ' : 'Excess ') + formatMoney(Number(data.shortfall_amount || 0) > 0 ? data.shortfall_amount : data.excess_amount);
        document.getElementById('isoModalIncentiveAmount').textContent = formatMoney(data.incentive_amount || 0);
        document.getElementById('isoModalCarryRisk').textContent = data.carry_risk ? 'Yes' : 'No';
        document.getElementById('isoModalShortfall').textContent = formatMoney(data.shortfall_amount || 0);
        document.getElementById('isoModalIncentivePercent').textContent = Number(data.incentive_percent || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '%';
        document.getElementById('isoModalInsightMessage').textContent = data.insight_message || '-';

        const statusBadge = document.getElementById('isoModalStatusBadge');
        if (statusBadge) {
            statusBadge.className = 'iso-report-badge ' + (data.status_class || 'badge-soft-secondary');
            statusBadge.innerHTML = '<i class="fas ' + statusIcon(data.status_class || '') + '"></i> ' + escapeHtml(data.status_text || 'No Target');
        }

        const downloadLink = data.download_url || '#';
        document.getElementById('isoModalDownloadBtn').setAttribute('href', downloadLink);
        document.getElementById('isoModalFooterDownloadBtn').setAttribute('href', downloadLink);

        setProgressState(data.progress_percent || 0);

        const historyTarget = document.getElementById('isoModalHistoryRows');
        const historyRows = Array.isArray(data.history_rows) ? data.history_rows : [];
        if (historyTarget) {
            historyTarget.innerHTML = historyRows.length
                ? historyRows.map(function (item) {
                    return '<tr>' +
                        '<td>' + escapeHtml(item.period || '-') + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.base_target || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.opening_carry || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.effective_target || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.achieved_amount || 0)) + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.shortfall_amount || 0)) + '</td>' +
                        '<td class="col-status"><span class="' + paymentStatusClass(item.status_text || '') + '">' + escapeHtml(item.status_text || '-') + '</span></td>' +
                    '</tr>';
                }).join('')
                : '<tr><td colspan="7" class="iso-report-modal-empty">No history available.</td></tr>';
        }

        const collectionTarget = document.getElementById('isoModalCollectionRows');
        const collectionRows = Array.isArray(data.collection_rows) ? data.collection_rows : [];
        if (collectionTarget) {
            collectionTarget.innerHTML = collectionRows.length
                ? collectionRows.map(function (item) {
                    return '<tr>' +
                        '<td class="col-date">' + escapeHtml(item.payment_date || '-') + '</td>' +
                        '<td class="col-amount">' + escapeHtml(formatMoney(item.amount || 0)) + '</td>' +
                        '<td class="col-status"><span class="' + paymentStatusClass(item.approval_status || '') + '">' + escapeHtml(item.approval_status || '-') + '</span></td>' +
                    '</tr>';
                }).join('')
                : '<tr><td colspan="3" class="iso-report-modal-empty">No collection entries available.</td></tr>';
        }

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeReportModal() {
        if (!modal) return;
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-view-report').forEach(function (button) {
        button.addEventListener('click', function () {
            openReportModal(this.getAttribute('data-user-id'));
        });
    });

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeReportModal);
    }

    if (modalFooterCloseBtn) {
        modalFooterCloseBtn.addEventListener('click', closeReportModal);
    }

    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeReportModal();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && modal.classList.contains('show')) {
            closeReportModal();
        }
    });

    if (typeof crmDataTable === 'function') {
        crmDataTable('#targetsReportTable', {
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            order: [[0, 'desc']],
            searchPlaceholder: 'Search performance...',
            autoWidth: false,
            responsive: false,
            scrollX: true,
            dom:
                "<'dt-top'lf>" +
                "rt" +
                "<'iso-dt-bottom'ip>",
            columnDefs: [
                { targets: [13], orderable: false }
            ]
        });

        function syncTargetsReportTable() {
            if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
                jQuery('#targetsReportTable').DataTable().columns.adjust().draw(false);
            }
        }

        setTimeout(function () {
            var controls = document.querySelector('#targetsReportTable_wrapper .dt-top');
            var target = document.getElementById('isoDatatableControls');
            if (controls && target) {
                target.appendChild(controls);
            }

            syncTargetsReportTable();
        }, 100);

        window.addEventListener('resize', function () {
            setTimeout(syncTargetsReportTable, 50);
        });

        window.addEventListener('load', function () {
            setTimeout(syncTargetsReportTable, 100);
        });
    }
});
</script>

