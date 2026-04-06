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
Modern SaaS UI - Product Ready
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

/* ROOT */

.iso-report-root{
    background:#fff;
    border-radius:16px;
    padding:26px;
    box-shadow:var(--shadow-md);
    border:1px solid var(--border);
    font-family:'Poppins',sans-serif;
    color:#333;
    font-weight:600;
}

/* HERO */

.iso-report-hero{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:24px;
    flex-wrap:wrap;
    gap:20px;
}

.iso-report-title{
    font-size:28px;
    font-weight:700;
    color:var(--primary);
    margin-bottom:6px;
}

.iso-report-subtitle{
    font-size:14px;
    color:#555;
    font-weight:600;
}

/* BUTTONS */

.iso-report-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    height:40px;
    padding:0 18px;
    border-radius:30px;
    font-size:13px;
    font-weight:600;
    text-decoration:none;
    cursor:pointer;
    border:1px solid transparent;
    transition:all .25s ease;
}

.iso-report-btn-primary{
    background:linear-gradient(135deg,#e83e8c,#d2317a);
    color:#fff;
    box-shadow:0 5px 12px rgba(232,62,140,.25);
}

.iso-report-btn-primary:hover{
    transform:translateY(-1px);
    box-shadow:0 8px 18px rgba(232,62,140,.35);
}

.iso-report-btn-outline{
    background:#fff;
    border:1px solid var(--border);
    color:#444;
}

.iso-report-btn-outline:hover{
    border-color:var(--primary);
    color:var(--primary);
}

/* CARDS */

.iso-report-card{
    background:#fff;
    border-radius:var(--radius);
    border:1px solid var(--border);
    margin-bottom:24px;
    overflow:hidden;
}

.iso-report-card-head{
    padding:16px 20px;
    background:var(--primary-soft);
    font-weight:700;
    font-size:15px;
    color:var(--primary);
}

.iso-report-card-body{
    padding:22px;
}

/* FILTER GRID */

.iso-report-filter-row{
    display:grid;
    grid-template-columns:1.5fr 0.7fr 0.8fr 1.2fr 1fr auto auto;
    gap:14px;
    align-items:end;
}

/* FORM */

.form-label{
    font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    color:#666;
    margin-bottom:5px;
}

.form-control,
.form-select{
    height:40px;
    border-radius:8px;
    border:1px solid #ddd;
    padding:0 12px;
    font-size:13px;
    font-weight:600;
}

.form-control:focus,
.form-select:focus{
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(232,62,140,.15);
}

/* SUMMARY */

.iso-report-summary{
    display:grid;
    grid-template-columns:repeat(6,1fr);
    gap:14px;
    margin-bottom:20px;
}

.iso-report-summary-card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:10px;
    padding:16px;
}

.iso-report-summary-label{
    font-size:11px;
    font-weight:700;
    color:#666;
    text-transform:uppercase;
}

.iso-report-summary-value{
    font-size:20px;
    font-weight:700;
    color:var(--primary);
}

/* TABLE */

.iso-report-table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
    border:1px solid #f1d6e3;
}

.iso-report-table thead th{
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.5px;
    color:#555;
    padding:14px;
    background:var(--primary-soft);
    border-bottom:1px solid var(--border);
    font-weight:700;
}

.iso-report-table tbody td{
    padding:14px;
    border-bottom:1px solid #eee;
    font-weight:600;
}

.iso-report-table th,
.iso-report-table td{
    border-right:1px solid #f5e3ec;
}

.iso-report-table tr td:last-child,
.iso-report-table tr th:last-child{
    border-right:none;
}

.iso-report-table tbody tr:hover{
    background:#fff7fb;
}

/* USER */

.iso-report-name{
    font-weight:700;
}

.iso-report-meta{
    font-size:11px;
    color:#666;
    font-weight:600;
}

/* MONEY */

.iso-report-money{
    font-weight:700;
    color:var(--primary);
}

.iso-report-soft{
    font-weight:600;
    color:#444;
}

/* BADGES */

.iso-report-badge{
    font-size:11px;
    padding:5px 12px;
    border-radius:20px;
    font-weight:700;
}

.badge-soft-success{
    background:#e8f7ed;
    color:#28a745;
}

.badge-soft-warning{
    background:#fff5db;
    color:#d39e00;
}

.badge-soft-danger{
    background:#ffeaea;
    color:#dc3545;
}

.badge-soft-secondary{
    background:#f1f1f1;
    color:#777;
}

.badge-soft-info{
    background:#e8f7ff;
    color:#17a2b8;
}

/* ACTION BUTTON */

.iso-report-action-btn{
    width:32px;
    height:32px;
    border-radius:8px;
    background:#f5f5f5;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#555;
    border:1px solid #e0e0e0;
    transition:all .2s;
}

.iso-report-action-btn:hover{
    background:var(--primary);
    color:#fff;
    border-color:var(--primary);
}

/* STATUS TWO LINES */

.iso-report-status{
    display:flex;
    flex-direction:column;
    gap:3px;
}

.iso-report-status-text{
    font-size:11px;
    color:#666;
    font-weight:600;
}

/* EMPTY STATE */

.iso-report-empty{
    text-align:center;
    padding:50px;
    font-size:14px;
    color:#666;
    font-weight:600;
}

/* TOOLTIP */

[data-tooltip]{
    position:relative;
}

[data-tooltip]:hover::after{
    content:attr(data-tooltip);
    position:absolute;
    bottom:130%;
    left:50%;
    transform:translateX(-50%);
    background:#333;
    color:#fff;
    font-size:11px;
    padding:6px 10px;
    border-radius:6px;
    white-space:nowrap;
}

/* RESPONSIVE */

@media(max-width:1400px){
    .iso-report-summary{
        grid-template-columns:repeat(3,1fr);
    }
}

@media(max-width:992px){
    .iso-report-filter-row{
        grid-template-columns:1fr 1fr;
    }

    .iso-report-summary{
        grid-template-columns:1fr 1fr;
    }
}

@media(max-width:768px){
    .iso-report-filter-row{
        grid-template-columns:1fr;
    }

    .iso-report-summary{
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
                <div class="iso-report-summary-value">?<?= number_format($totalBaseTarget, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total opening carry forward">
                <div class="iso-report-summary-label">
                    <i class="fas fa-forward" style="margin-right: 4px; color: #e83e8c;"></i>
                    Opening Carry
                </div>
                <div class="iso-report-summary-value">?<?= number_format($totalOpeningCarry, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total effective target">
                <div class="iso-report-summary-label">
                    <i class="fas fa-crosshairs" style="margin-right: 4px; color: #e83e8c;"></i>
                    Effective Target
                </div>
                <div class="iso-report-summary-value">?<?= number_format($totalEffectiveTarget, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total achieved amount">
                <div class="iso-report-summary-label">
                    <i class="fas fa-trophy" style="margin-right: 4px; color: #e83e8c;"></i>
                    Achieved
                </div>
                <div class="iso-report-summary-value">?<?= number_format($totalAchieved, 2) ?></div>
            </div>
            <div class="iso-report-summary-card" data-tooltip="Total shortfall amount">
                <div class="iso-report-summary-label">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 4px; color: #e83e8c;"></i>
                    Shortfall
                </div>
                <div class="iso-report-summary-value">?<?= number_format($totalShortfall, 2) ?></div>
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
                    <table class="iso-report-table no-mobile-cards">
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
                                        <td class="iso-report-money">?<?= number_format((float)($row['base_target'] ?? 0), 2) ?></td>
                                        <td class="iso-report-soft">?<?= number_format((float)($row['opening_carry'] ?? 0), 2) ?></td>
                                        <td class="iso-report-money">?<?= number_format((float)($row['effective_target'] ?? 0), 2) ?></td>
                                        <td class="iso-report-money">?<?= number_format((float)($row['achieved_amount'] ?? 0), 2) ?></td>
                                        <td class="iso-report-soft">?<?= number_format((float)($row['excess_amount'] ?? 0), 2) ?></td>
                                        <td class="iso-report-soft">?<?= number_format((float)($row['shortfall_amount'] ?? 0), 2) ?></td>
                                        <td>
                                            <span class="iso-report-soft">
                                                <?= number_format((float)($row['incentive_percent'] ?? 0), 2) ?>%
                                            </span>
                                        </td>
                                        <td class="iso-report-money">
                                            ?<?= number_format((float)($row['incentive_amount'] ?? 0), 2) ?>
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
