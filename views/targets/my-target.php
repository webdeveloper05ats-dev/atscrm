<?php
// =====================================
// Targets - My Target
// Slug: targets/my-target
// File: views/targets/my-target.php
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
$userName = trim((string)($_SESSION['user_name'] ?? $_SESSION['name'] ?? 'User'));
$roleName = trim((string)($_SESSION['role_name'] ?? ''));

$error = '';

if (!$userId || !$branchId) {
    $error = 'Invalid session. Please login again.';
}

$currentYear  = targetInt($_GET['year'] ?? date('Y'));
$currentMonth = targetInt($_GET['month'] ?? date('n'));

if ($currentYear < 2000 || $currentYear > 2100) {
    $currentYear = (int)date('Y');
}
if ($currentMonth < 1 || $currentMonth > 12) {
    $currentMonth = (int)date('n');
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

$currentTarget = null;
$achievedAmount = 0.00;
$openingCarry = 0.00;
$effectiveTarget = 0.00;
$excessAmount = 0.00;
$shortfallAmount = 0.00;
$incentiveAmount = 0.00;
$historyRows = [];

// --------------------------------------------------
// Current month target
// --------------------------------------------------
if (!$error) {
    try {
        $stmtTarget = $pdo->prepare("
            SELECT 
                mt.*,
                r.role_name
            FROM monthly_targets mt
            INNER JOIN roles r ON r.id = mt.role_id
            WHERE mt.branch_id = :branch_id
              AND mt.user_id = :user_id
              AND mt.target_year = :target_year
              AND mt.target_month = :target_month
            LIMIT 1
        ");
        $stmtTarget->execute([
            ':branch_id'    => $branchId,
            ':user_id'      => $userId,
            ':target_year'  => $currentYear,
            ':target_month' => $currentMonth,
        ]);
        $currentTarget = $stmtTarget->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load current target. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Current month achieved amount
// --------------------------------------------------
if (!$error) {
    try {
        $stmtAchieved = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_achieved
            FROM registration_payments
            WHERE branch_id = :branch_id
              AND staff_id = :staff_id
              AND approval_status = 'approved'
              AND YEAR(payment_date) = :target_year
              AND MONTH(payment_date) = :target_month
        ");
        $stmtAchieved->execute([
            ':branch_id'    => $branchId,
            ':staff_id'     => $userId,
            ':target_year'  => $currentYear,
            ':target_month' => $currentMonth,
        ]);
        $achievedAmount = (float)($stmtAchieved->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $error = 'Unable to load achieved amount. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Carry forward calculation
// Simple running carry from previous months
// --------------------------------------------------
if (!$error) {
    try {
    $stmtPrev = $pdo->prepare("
    SELECT 
        mt.id,
        mt.target_year,
        mt.target_month,
        mt.target_amount,
        mt.incentive_percent,
        COALESCE((
            SELECT SUM(rp.amount)
            FROM registration_payments rp
            WHERE rp.branch_id = mt.branch_id
              AND rp.collected_by = mt.user_id
              AND rp.approval_status = 'approved'
              AND YEAR(rp.payment_date) = mt.target_year
              AND MONTH(rp.payment_date) = mt.target_month
        ), 0) AS achieved_amount
    FROM monthly_targets mt
    WHERE mt.branch_id = :branch_id
      AND mt.user_id = :user_id
      AND (
            mt.target_year < :target_year1
            OR (mt.target_year = :target_year2 AND mt.target_month < :target_month)
          )
    ORDER BY mt.target_year ASC, mt.target_month ASC, mt.id ASC
");
$stmtPrev->execute([
    ':branch_id'    => $branchId,
    ':user_id'      => $userId,
    ':target_year1' => $currentYear,
    ':target_year2' => $currentYear,
    ':target_month' => $currentMonth,
]);
        $prevRows = $stmtPrev->fetchAll(PDO::FETCH_ASSOC);

        $runningCarry = 0.00;

        foreach ($prevRows as $pr) {
            $monthTarget = (float)$pr['target_amount'];
            $monthAchieved = (float)$pr['achieved_amount'];
            $monthEffective = $monthTarget + $runningCarry;

            if ($monthAchieved >= $monthEffective) {
                $runningCarry = 0.00;
            } else {
                $runningCarry = $monthEffective - $monthAchieved;
            }
        }

        $openingCarry = $runningCarry;
    } catch (Throwable $e) {
        $error = 'Unable to calculate carry forward. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Final current month summary
// --------------------------------------------------
if (!$error && $currentTarget) {
    $baseTarget      = (float)$currentTarget['target_amount'];
    $incentivePct    = (float)$currentTarget['incentive_percent'];
    $effectiveTarget = $baseTarget + $openingCarry;

    if ($achievedAmount >= $effectiveTarget) {
        $excessAmount = $achievedAmount - $effectiveTarget;
        $shortfallAmount = 0.00;
        $incentiveAmount = ($excessAmount * $incentivePct) / 100;
    } else {
        $excessAmount = 0.00;
        $shortfallAmount = $effectiveTarget - $achievedAmount;
        $incentiveAmount = 0.00;
    }
}

// --------------------------------------------------
// History rows
// --------------------------------------------------
if (!$error) {
    try {
        $stmtHistory = $pdo->prepare("
            SELECT 
                mt.*,
                r.role_name,
                COALESCE((
                    SELECT SUM(rp.amount)
                    FROM registration_payments rp
                    WHERE rp.branch_id = mt.branch_id
                      AND rp.staff_id = mt.user_id
                      AND rp.approval_status = 'approved'
                      AND YEAR(rp.payment_date) = mt.target_year
                      AND MONTH(rp.payment_date) = mt.target_month
                ), 0) AS achieved_amount
            FROM monthly_targets mt
            INNER JOIN roles r ON r.id = mt.role_id
            WHERE mt.branch_id = :branch_id
              AND mt.user_id = :user_id
            ORDER BY mt.target_year DESC, mt.target_month DESC, mt.id DESC
            LIMIT 12
        ");
        $stmtHistory->execute([
            ':branch_id' => $branchId,
            ':user_id'   => $userId,
        ]);
        $historyRows = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load history. ' . $e->getMessage();
    }
}
?>

<style>
:root {
    --pink-primary: #ec1670;
    --pink-dark: #c8135b;
    --pink-light: #fcf8fb;
    --pink-border: #f0d9e5;
    --pink-soft: #fff6fb;
    --text-dark: #202020;
    --text-light: #6d6d6d;
    --white: #ffffff;
    --success-bg: #e8f8ee;
    --success-text: #157347;
    --warning-bg: #fff3cd;
    --warning-text: #8a6d3b;
    --danger-bg: #fce9ea;
    --danger-text: #b02a37;
}

/* Main Container */
.my-target-wrap {
    background: var(--pink-light);
    border-radius: 22px;
    padding: 20px;
}

/* Top Bar */
.my-target-topbar {
    background: linear-gradient(135deg, var(--white) 0%, var(--pink-soft) 100%);
    border: 1px solid var(--pink-border);
    border-radius: 20px;
    padding: 18px 20px;
    box-shadow: 0 8px 24px rgba(233, 30, 99, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.my-target-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--text-dark);
    margin-bottom: 4px;
}

.my-target-title i {
    color: var(--pink-primary);
    margin-right: 8px;
}

.my-target-text {
    margin: 0;
    color: var(--text-light);
    font-size: 0.95rem;
}

/* Filter Form */
.my-target-filter {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-light);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.my-target-filter .form-control,
.my-target-filter .form-select {
    min-height: 44px;
    border-radius: 12px;
    border: 1px solid var(--pink-border);
    background: var(--white);
    color: var(--text-dark);
    padding: 0 16px;
    font-size: 0.95rem;
}

.my-target-filter .form-control:focus,
.my-target-filter .form-select:focus {
    outline: none;
    border-color: var(--pink-primary);
    box-shadow: 0 0 0 3px rgba(236, 22, 112, 0.1);
}

.my-target-btn {
    border-radius: 12px;
    padding: 9px 16px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.my-target-btn-primary {
    background: linear-gradient(135deg, var(--pink-primary) 0%, var(--pink-dark) 100%);
    color: var(--white);
}

.my-target-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 16px rgba(236, 22, 112, 0.2);
}

.my-target-btn-outline {
    background: var(--white);
    border: 1px solid var(--pink-border);
    color: #444;
}

.my-target-btn-outline:hover {
    background: var(--pink-soft);
}

/* Stats Grid */
.my-target-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 18px;
}

.my-target-stat-card {
    background: var(--white);
    border: 1px solid var(--pink-border);
    border-radius: 18px;
    padding: 16px 18px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.04);
    transition: all 0.2s;
}

.my-target-stat-card:hover {
    border-color: var(--pink-primary);
    box-shadow: 0 12px 24px rgba(236, 22, 112, 0.08);
}

.my-target-stat-label {
    color: var(--text-light);
    font-size: 0.83rem;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.my-target-stat-label i {
    color: var(--pink-primary);
    font-size: 0.9rem;
}

.my-target-stat-value {
    color: var(--text-dark);
    font-size: 1.1rem;
    font-weight: 700;
}

/* Main Grid */
.my-target-main-grid {
    display: grid;
    grid-template-columns: 1.25fr 0.9fr;
    gap: 18px;
}

/* Cards */
.my-target-card {
    background: var(--white);
    border: 1px solid var(--pink-border);
    border-radius: 22px;
    overflow: hidden;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
}

.my-target-card-head {
    background: linear-gradient(135deg, var(--pink-primary) 0%, var(--pink-dark) 100%);
    color: var(--white);
    padding: 15px 20px;
    font-size: 1rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.my-target-card-head i {
    font-size: 1.1rem;
}

.my-target-card-body {
    padding: 20px;
}

/* Info Grid */
.my-target-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.my-target-info-item {
    background: var(--pink-soft);
    border: 1px solid var(--pink-border);
    border-radius: 16px;
    padding: 14px 16px;
    transition: all 0.2s;
}

.my-target-info-item:hover {
    border-color: var(--pink-primary);
    background: var(--white);
}

.my-target-info-item.full-width {
    grid-column: 1 / -1;
}

.my-target-info-label {
    font-size: 0.82rem;
    color: var(--text-light);
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.my-target-info-label i {
    color: var(--pink-primary);
    font-size: 0.85rem;
}

.my-target-info-value {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-dark);
}

/* Progress Bar */
.progress-container {
    background: var(--pink-soft);
    border: 1px solid var(--pink-border);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 16px;
}

.progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 0.9rem;
    color: var(--text-light);
}

.progress-bar {
    height: 8px;
    background: var(--pink-border);
    border-radius: 20px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--pink-primary), var(--pink-dark));
    border-radius: 20px;
    transition: width 0.3s ease;
}

/* Status Badges */
.my-target-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 0.78rem;
    font-weight: 700;
    gap: 5px;
}

.my-target-success {
    background: var(--success-bg);
    color: var(--success-text);
}

.my-target-warning {
    background: var(--warning-bg);
    color: var(--warning-text);
}

.my-target-danger {
    background: var(--danger-bg);
    color: var(--danger-text);
}

/* Quick Snapshot */
.snapshot-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--pink-border);
}

.snapshot-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.snapshot-item:first-child {
    padding-top: 0;
}

.snapshot-label {
    color: var(--text-light);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.snapshot-label i {
    color: var(--pink-primary);
    width: 16px;
}

.snapshot-value {
    font-weight: 700;
    color: var(--text-dark);
    font-size: 1rem;
}

/* Table Styles */
.my-target-table {
    margin-bottom: 0;
    vertical-align: middle;
    width: 100%;
}

.my-target-table thead th {
    background: var(--pink-soft);
    color: var(--pink-dark);
    font-weight: 700;
    border-bottom: 1px solid var(--pink-border);
    padding: 12px 16px;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.my-target-table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--pink-border);
    color: var(--text-dark);
}

.my-target-table tbody tr:hover {
    background: var(--pink-soft);
}

.my-target-table tbody tr:last-child td {
    border-bottom: none;
}

/* Empty State */
.my-target-empty {
    text-align: center;
    padding: 35px 20px;
    color: var(--text-light);
}

.my-target-empty i {
    font-size: 2.5rem;
    color: var(--pink-border);
    margin-bottom: 12px;
}

/* Alert */
.alert-custom {
    background: var(--white);
    border-left: 4px solid var(--pink-primary);
    border-radius: 16px;
    padding: 16px 20px;
    margin-bottom: 18px;
    border: 1px solid var(--pink-border);
    border-left-color: var(--pink-primary);
    color: var(--pink-dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-custom i {
    font-size: 1.2rem;
}

/* Performance Score */
.performance-score {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px dashed var(--pink-border);
}

.performance-score-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: var(--text-light);
}

.performance-score-value {
    font-weight: 700;
    color: var(--pink-primary);
}

.score-segments {
    display: flex;
    gap: 6px;
}

.score-segment {
    flex: 1;
    height: 6px;
    background: var(--pink-border);
    border-radius: 6px;
}

.score-segment.filled {
    background: linear-gradient(90deg, var(--pink-primary), var(--pink-dark));
}

/* Difference Colors */
.text-success-custom {
    color: var(--success-text);
}

.text-danger-custom {
    color: var(--danger-text);
}

/* Responsive */
@media (max-width: 1199.98px) {
    .my-target-stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .my-target-main-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 767.98px) {
    .my-target-stat-grid,
    .my-target-info-grid {
        grid-template-columns: 1fr;
    }
    .my-target-full {
        grid-column: auto;
    }
    .my-target-topbar {
        flex-direction: column;
        align-items: stretch;
    }
    .my-target-filter {
        flex-direction: column;
    }
    .my-target-filter .form-control,
    .my-target-filter .form-select {
        width: 100%;
    }
}
</style>

<div class="container-fluid py-3">
    <div class="my-target-wrap">

        <!-- Header -->
        <div class="my-target-topbar">
            <div>
                <div class="my-target-title">
                    <i class="fas fa-bullseye"></i> My Target
                </div>
                <p class="my-target-text">
                    View your monthly target, achievement, carry forward, and performance history.
                </p>
            </div>

            <form method="get" action="" class="my-target-filter">
                <input type="hidden" name="page" value="targets/my-target">

                <div class="filter-group">
                    <label><i class="far fa-calendar-alt"></i> Year</label>
                    <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= h($currentYear) ?>">
                </div>

                <div class="filter-group">
                    <label><i class="far fa-calendar"></i> Month</label>
                    <select name="month" class="form-select">
                        <?php foreach ($monthNames as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $currentMonth === $num ? 'selected' : '' ?>>
                                <?= h($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="my-target-btn my-target-btn-primary">
                        <i class="fas fa-filter me-1"></i> Load
                    </button>
                </div>
            </form>
        </div>

        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="alert-custom">
                <i class="fas fa-exclamation-circle"></i>
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="my-target-stat-grid">
            <div class="my-target-stat-card">
                <div class="my-target-stat-label">
                    <i class="fas fa-user"></i> Staff Name
                </div>
                <div class="my-target-stat-value"><?= h($userName) ?></div>
            </div>
            <div class="my-target-stat-card">
                <div class="my-target-stat-label">
                    <i class="fas fa-briefcase"></i> Role
                </div>
                <div class="my-target-stat-value"><?= h($roleName ?: '-') ?></div>
            </div>
            <div class="my-target-stat-card">
                <div class="my-target-stat-label">
                    <i class="fas fa-clock"></i> Selected Period
                </div>
                <div class="my-target-stat-value"><?= h($monthNames[$currentMonth] . ' ' . $currentYear) ?></div>
            </div>
            <div class="my-target-stat-card">
                <div class="my-target-stat-label">
                    <i class="fas fa-store"></i> Branch
                </div>
                <div class="my-target-stat-value"><?= h($_SESSION['branch_name'] ?? 'Main Branch') ?></div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="my-target-main-grid">
            <!-- Current Month Summary -->
            <div class="my-target-card">
                <div class="my-target-card-head">
                    <i class="fas fa-chart-line"></i> Current Month Summary
                </div>
                <div class="my-target-card-body">

                    <?php if (!$currentTarget): ?>
                        <div class="my-target-empty">
                            <i class="fas fa-bullseye"></i>
                            <p>No target setup found for <?= h($monthNames[$currentMonth] . ' ' . $currentYear) ?>.</p>
                        </div>
                    <?php else: ?>
                        <?php
                            $baseTarget = (float)$currentTarget['target_amount'];
                            $progressPercent = $effectiveTarget > 0 ? min(($achievedAmount / $effectiveTarget) * 100, 100) : 0;
                            
                            $statusLabel = 'Pending';
                            $statusClass = 'my-target-warning';
                            $statusIcon = 'fa-clock';

                            if ($achievedAmount >= $effectiveTarget && $effectiveTarget > 0) {
                                $statusLabel = 'Target Achieved';
                                $statusClass = 'my-target-success';
                                $statusIcon = 'fa-check-circle';
                            } elseif ($achievedAmount > 0 && $achievedAmount < $effectiveTarget) {
                                $statusLabel = 'In Progress';
                                $statusClass = 'my-target-warning';
                                $statusIcon = 'fa-spinner';
                            } else {
                                $statusLabel = 'Not Started';
                                $statusClass = 'my-target-danger';
                                $statusIcon = 'fa-hourglass-start';
                            }
                        ?>

                        <!-- Progress Bar -->
                        <div class="progress-container">
                            <div class="progress-header">
                                <span><i class="fas fa-chart-pie me-1" style="color: var(--pink-primary);"></i> Progress</span>
                                <span><?= number_format($progressPercent, 1) ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $progressPercent ?>%;"></div>
                            </div>
                        </div>

                        <div class="my-target-info-grid">
                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-bullseye"></i> Base Target
                                </div>
                                <div class="my-target-info-value">₹<?= number_format($baseTarget, 2) ?></div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-rupee-sign"></i> Achieved
                                </div>
                                <div class="my-target-info-value">₹<?= number_format($achievedAmount, 2) ?></div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-forward"></i> Opening Carry
                                </div>
                                <div class="my-target-info-value">₹<?= number_format($openingCarry, 2) ?></div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-crosshairs"></i> Effective Target
                                </div>
                                <div class="my-target-info-value">₹<?= number_format($effectiveTarget, 2) ?></div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-plus-circle"></i> Excess
                                </div>
                                <div class="my-target-info-value">₹<?= number_format($excessAmount, 2) ?></div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-minus-circle"></i> Shortfall
                                </div>
                                <div class="my-target-info-value">₹<?= number_format($shortfallAmount, 2) ?></div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-percent"></i> Incentive %
                                </div>
                                <div class="my-target-info-value"><?= number_format((float)$currentTarget['incentive_percent'], 2) ?>%</div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-gift"></i> Est. Incentive
                                </div>
                                <div class="my-target-info-value">₹<?= number_format($incentiveAmount, 2) ?></div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-tag"></i> Setup Status
                                </div>
                                <div class="my-target-info-value">
                                    <?= ucfirst(h($currentTarget['status'])) ?>
                                </div>
                            </div>

                            <div class="my-target-info-item">
                                <div class="my-target-info-label">
                                    <i class="fas fa-chart-simple"></i> Progress Status
                                </div>
                                <div class="my-target-info-value">
                                    <span class="my-target-badge <?= $statusClass ?>">
                                        <i class="fas <?= $statusIcon ?> me-1"></i>
                                        <?= h($statusLabel) ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($currentTarget['remarks'])): ?>
                            <div class="my-target-info-item my-target-full">
                                <div class="my-target-info-label">
                                    <i class="fas fa-comment"></i> Remarks
                                </div>
                                <div class="my-target-info-value"><?= h($currentTarget['remarks']) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Quick Snapshot -->
            <div class="my-target-card">
                <div class="my-target-card-head">
                    <i class="fas fa-camera"></i> Quick Snapshot
                </div>
                <div class="my-target-card-body">
                    
                    <div class="snapshot-item">
                        <span class="snapshot-label">
                            <i class="fas fa-calendar-alt"></i> This Month
                        </span>
                        <span class="snapshot-value"><?= h($monthNames[$currentMonth] . ' ' . $currentYear) ?></span>
                    </div>

                    <div class="snapshot-item">
                        <span class="snapshot-label">
                            <i class="fas fa-check-circle"></i> Target Available
                        </span>
                        <span class="snapshot-value">
                            <span class="my-target-badge <?= $currentTarget ? 'my-target-success' : 'my-target-danger' ?>" style="padding: 4px 10px;">
                                <?= $currentTarget ? 'Yes' : 'No' ?>
                            </span>
                        </span>
                    </div>

                    <div class="snapshot-item">
                        <span class="snapshot-label">
                            <i class="fas fa-rupee-sign"></i> Approved Collections
                        </span>
                        <span class="snapshot-value">₹<?= number_format($achievedAmount, 2) ?></span>
                    </div>

                    <div class="snapshot-item">
                        <span class="snapshot-label">
                            <i class="fas fa-forward"></i> Carry to Clear
                        </span>
                        <span class="snapshot-value">₹<?= number_format($shortfallAmount, 2) ?></span>
                    </div>

                    <div class="snapshot-item">
                        <span class="snapshot-label">
                            <i class="fas fa-gift"></i> Potential Incentive
                        </span>
                        <span class="snapshot-value">₹<?= number_format($incentiveAmount, 2) ?></span>
                    </div>

                    <?php if ($currentTarget && $effectiveTarget > 0): ?>
                    <div class="performance-score">
                        <div class="performance-score-header">
                            <span><i class="fas fa-star me-1" style="color: var(--pink-primary);"></i> Performance Score</span>
                            <span class="performance-score-value"><?= number_format(($achievedAmount / $effectiveTarget) * 100, 1) ?>%</span>
                        </div>
                        <div class="score-segments">
                            <?php
                            $score = ($achievedAmount / $effectiveTarget) * 100;
                            $segments = 5;
                            for ($i = 1; $i <= $segments; $i++): 
                                $segmentScore = ($i / $segments) * 100;
                            ?>
                            <div class="score-segment <?= $score >= $segmentScore ? 'filled' : '' ?>"></div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- History Table -->
        <div class="my-target-card mt-3">
            <div class="my-target-card-head">
                <i class="fas fa-history"></i> Last 12 Months History
            </div>
            <div class="my-target-card-body">
                <div class="table-responsive">
                    <table class="table my-target-table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Period</th>
                                <th>Role</th>
                                <th>Target</th>
                                <th>Achieved</th>
                                <th>Difference</th>
                                <th>Incentive %</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$historyRows): ?>
                                <tr>
                                    <td colspan="8" class="my-target-empty">
                                        <i class="fas fa-history"></i>
                                        <p>No target history found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($historyRows as $index => $row): ?>
                                    <?php
                                        $hTarget = (float)$row['target_amount'];
                                        $hAchieved = (float)$row['achieved_amount'];
                                        $hDiff = $hAchieved - $hTarget;
                                    ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark p-2"><?= $index + 1 ?></span></td>
                                        <td><strong><?= h(($monthNames[(int)$row['target_month']] ?? 'Month') . ' ' . $row['target_year']) ?></strong></td>
                                        <td><?= h($row['role_name']) ?></td>
                                        <td>₹<?= number_format($hTarget, 2) ?></td>
                                        <td>₹<?= number_format($hAchieved, 2) ?></td>
                                        <td class="<?= $hDiff >= 0 ? 'text-success-custom' : 'text-danger-custom' ?>">
                                            <?= $hDiff >= 0 ? '+' : '-' ?>
                                            ₹<?= number_format(abs($hDiff), 2) ?>
                                        </td>
                                        <td><?= number_format((float)$row['incentive_percent'], 2) ?>%</td>
                                        <td>
                                            <?php
                                            $statusClass = $row['status'] === 'active' ? 'my-target-success' : 
                                                          ($row['status'] === 'pending' ? 'my-target-warning' : 'my-target-warning');
                                            ?>
                                            <span class="my-target-badge <?= $statusClass ?>" style="padding: 4px 10px;">
                                                <?= ucfirst(h($row['status'])) ?>
                                            </span>
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