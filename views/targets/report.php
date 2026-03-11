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
        ];

        $totalUsers++;
        $totalBaseTarget += $baseTarget;
        $totalOpeningCarry += $openingCarry;
        $totalEffectiveTarget += $effectiveTarget;
        $totalAchieved += $achieved;
        $totalShortfall += $shortfall;
        $totalExcess += $excess;
    }
}
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
min-width: 1400px; /* Ensures all columns visible */
table-layout: auto;
}

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
}

.iso-report-meta {
font-size:11px;
color:#888;
display: flex;
align-items: center;
gap: 4px;
margin-top: 4px;
}

/* MONEY VALUES */
.iso-report-money {
font-weight:600;
color:var(--primary);
white-space: nowrap;
}

.iso-report-soft {
font-weight:500;
color:#555;
white-space: nowrap;
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
}

.iso-report-action-btn {
width:36px;
height:36px;
border-radius:10px;
background:#f8f8f8;
display:inline-flex;
align-items:center;
justify-content:center;
color:#666;
border:1px solid #e6e6e6;
cursor: pointer;
text-decoration: none;
transition: all 0.2s;
}

.iso-report-action-btn:hover {
background:var(--primary);
color:#fff;
border-color:var(--primary);
}

/* TOOLTIP */
[data-tooltip] {
position: relative;
cursor: help;
}

[data-tooltip]:before {
content: attr(data-tooltip);
position: absolute;
bottom: 100%;
left: 50%;
transform: translateX(-50%);
padding: 6px 12px;
background: #333;
color: white;
font-size: 11px;
font-weight: 500;
border-radius: 6px;
white-space: nowrap;
opacity: 0;
visibility: hidden;
transition: all 0.2s;
pointer-events: none;
z-index: 1000;
}

[data-tooltip]:hover:before {
opacity: 1;
visibility: visible;
bottom: calc(100% + 8px);
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

[data-tooltip]:before {
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
[data-tooltip]:before {
display: none;
}
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
   class="iso-report-btn iso-report-btn-primary"
   data-tooltip="Setup Target">
   <i class="fas fa-bullseye"></i>
</a>

<a href="index.php?page=targets/list"
   class="iso-report-btn iso-report-btn-outline"
   data-tooltip="Target List">
   <i class="fas fa-list"></i>
</a>

<a href="index.php?page=targets/export&year=<?= (int)$fYear ?>&month=<?= (int)$fMonth ?>&user_id=<?= (int)$fUserId ?>&role_id=<?= (int)$fRoleId ?>&search=<?= urlencode($search) ?>"
   class="iso-report-btn iso-report-btn-outline"
   data-tooltip="Export Summary">
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
        class="iso-report-btn iso-report-btn-primary iso-report-filter-btn"
        data-tooltip="Load Report">
    <i class="fas fa-filter"></i>
</button>
                        </div>

                        <div>
                            <a href="index.php?page=targets/report"
   class="iso-report-btn iso-report-btn-outline iso-report-filter-btn"
   data-tooltip="Reset Filters">
   <i class="fas fa-undo"></i>
</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modern Summary Cards -->
        <div class="iso-report-summary">
            <div class="iso-report-summary-card" data-tooltip="Total active users">
                <div class="iso-report-summary-label">
                    <i class="fas fa-users" style="margin-right: 4px; color: #e83e8c;"></i>
                    Users
                </div>
                <div class="iso-report-summary-value"><?= number_format($totalUsers) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total base target amount">
                <div class="iso-report-summary-label">
                    <i class="fas fa-bullseye" style="margin-right: 4px; color: #e83e8c;"></i>
                    Base Target
                </div>
                <div class="iso-report-summary-value">₹<?= number_format($totalBaseTarget, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total opening carry forward">
                <div class="iso-report-summary-label">
                    <i class="fas fa-forward" style="margin-right: 4px; color: #e83e8c;"></i>
                    Opening Carry
                </div>
                <div class="iso-report-summary-value">₹<?= number_format($totalOpeningCarry, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total effective target">
                <div class="iso-report-summary-label">
                    <i class="fas fa-crosshairs" style="margin-right: 4px; color: #e83e8c;"></i>
                    Effective Target
                </div>
                <div class="iso-report-summary-value">₹<?= number_format($totalEffectiveTarget, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total achieved amount">
                <div class="iso-report-summary-label">
                    <i class="fas fa-trophy" style="margin-right: 4px; color: #e83e8c;"></i>
                    Achieved
                </div>
                <div class="iso-report-summary-value">₹<?= number_format($totalAchieved, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total shortfall amount">
                <div class="iso-report-summary-label">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 4px; color: #e83e8c;"></i>
                    Shortfall
                </div>
                <div class="iso-report-summary-value">₹<?= number_format($totalShortfall, 2) ?></div>
            </div>
        </div>

        <!-- Modern Report Table Card -->
        <div class="iso-report-card">
            <div class="iso-report-card-head">
                <i class="fas fa-file-alt"></i>
                Report for <?= h($monthNames[$fMonth] . ' ' . $fYear) ?>
            </div>

            <div class="iso-report-card-body">
                <div class="iso-report-table-wrap">
                    <table class="iso-report-table">
                        <thead>
                            <tr>
                                <th data-tooltip="Serial number">#</th>
                                <th data-tooltip="User information">User</th>
                                <th data-tooltip="User role">Role</th>
                                <th data-tooltip="Base target amount">Base Target</th>
                                <th data-tooltip="Opening carry forward">Opening Carry</th>
                                <th data-tooltip="Effective target (Base + Carry)">Effective Target</th>
                                <th data-tooltip="Achieved amount">Achieved</th>
                                <th data-tooltip="Excess amount">Excess</th>
                                <th data-tooltip="Shortfall amount">Shortfall</th>
                                <th data-tooltip="Incentive percentage">Incentive %</th>
                                <th data-tooltip="Incentive amount">Incentive</th>
                                <th data-tooltip="Current status">Status</th>
                                <th data-tooltip="Actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="13" class="iso-report-empty">
                                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
                                        <div>No report data found.</div>
                                        <div style="font-size: 13px; margin-top: 8px;">Try adjusting your filters</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $i => $row): ?>
                                    <tr class="<?= !empty($row['row_highlight']) ? 'iso-report-row-highlight' : '' ?>">
                                        <td>
                                            <span class="iso-report-soft"><?= $i + 1 ?></span>
                                        </td>
                                        <td>
                                            <div class="iso-report-name">
                                                <i class="fas fa-user-circle" style="margin-right: 6px; color: #e83e8c;"></i>
                                                <?= h($row['user_name'] ?? '-') ?>
                                            </div>
                                            <div class="iso-report-meta">
                                                <i class="fas fa-envelope" style="margin-right: 4px; font-size: 10px;"></i>
                                                <?= h($row['user_email'] ?? '-') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="iso-report-meta">
                                                <i class="fas fa-briefcase" style="margin-right: 4px;"></i>
                                                <?= h($row['role_name'] ?? '-') ?>
                                            </span>
                                        </td>
                                        <td class="iso-report-money">₹<?= number_format((float)($row['base_target'] ?? 0), 2) ?></td>
                                        <td class="iso-report-soft">₹<?= number_format((float)($row['opening_carry'] ?? 0), 2) ?></td>
                                        <td class="iso-report-money">₹<?= number_format((float)($row['effective_target'] ?? 0), 2) ?></td>
                                        <td class="iso-report-money">₹<?= number_format((float)($row['achieved_amount'] ?? 0), 2) ?></td>
                                        <td class="iso-report-soft">₹<?= number_format((float)($row['excess_amount'] ?? 0), 2) ?></td>
                                        <td class="iso-report-soft">₹<?= number_format((float)($row['shortfall_amount'] ?? 0), 2) ?></td>
                                        <td>
                                            <span class="iso-report-soft">
                                                <?= number_format((float)($row['incentive_percent'] ?? 0), 2) ?>%
                                            </span>
                                        </td>
                                        <td class="iso-report-money">
                                            ₹<?= number_format((float)($row['incentive_amount'] ?? 0), 2) ?>
                                        </td>
                                        <td>
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
                                        <td>
                                            <a href="index.php?page=targets/export-user-details&user_id=<?= (int)($row['user_id'] ?? 0) ?>&year=<?= (int)$fYear ?>&month=<?= (int)$fMonth ?>" 
                                               class="iso-report-action-btn" 
                                               data-tooltip="Export detailed report for this user">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>