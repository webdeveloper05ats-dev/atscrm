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
<!-- Font Awesome for Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="container-fluid py-3">
    <div class="iso-report-root iso-report-v2">

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
<script src="<?= BASE_URL ?>assets/js/pages/targets/report.js?v=<?= urlencode((string) (@filemtime(ROOT_PATH . '/assets/js/pages/targets/report.js') ?: time())) ?>"></script>

