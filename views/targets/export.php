<?php
// =====================================
// Targets - Export Summary CSV
// Slug: targets/export
// File: views/targets/export.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
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

if (!$userId || !$branchId) {
    die('Invalid session. Please login again.');
}

if (!in_array($roleName, $allowedRoles, true)) {
    die('Access denied. Only HR and Super Admin can export target reports.');
}

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
// Load eligible users only
// --------------------------------------------------
$usersMap = [];
try {
    $stmtAllUsers = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.role_id,
            u.status,
            r.role_name,
            r.is_target_applicable
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.branch_id = :branch_id
          AND u.status = 1
          AND r.status = 1
          AND r.is_target_applicable = 1
        ORDER BY u.name ASC
    ");
    $stmtAllUsers->execute([':branch_id' => $branchId]);

    foreach ($stmtAllUsers->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $usersMap[(int)$u['id']] = $u;
    }
} catch (Throwable $e) {
    die('Unable to load users. ' . $e->getMessage());
}

// --------------------------------------------------
// Current month targets
// --------------------------------------------------
$currentTargets = [];
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
    die('Unable to load monthly targets. ' . $e->getMessage());
}

// --------------------------------------------------
// Current month achieved map
// --------------------------------------------------
$currentAchievedMap = [];
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
    die('Unable to load current achieved collections. ' . $e->getMessage());
}

// --------------------------------------------------
// Previous targets by user
// --------------------------------------------------
$previousTargetsByUser = [];
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
        ':branch_id'    => $branchId,
        ':target_year1' => $fYear,
        ':target_year2' => $fYear,
        ':target_month' => $fMonth,
    ]);

    foreach ($stmtPrevTargets->fetchAll(PDO::FETCH_ASSOC) as $ptr) {
        $uid = (int)$ptr['user_id'];
        if (!isset($previousTargetsByUser[$uid])) {
            $previousTargetsByUser[$uid] = [];
        }
        $previousTargetsByUser[$uid][] = $ptr;
    }
} catch (Throwable $e) {
    die('Unable to load previous target history. ' . $e->getMessage());
}

// --------------------------------------------------
// Previous achieved map by user-year-month
// --------------------------------------------------
$previousAchievedMap = [];
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
    die('Unable to load previous achieved data. ' . $e->getMessage());
}

// --------------------------------------------------
// Build export rows
// --------------------------------------------------
$allRelevantUserIds = array_keys($usersMap);

$exportRows = [];

foreach ($allRelevantUserIds as $uid) {
    $uid = (int)$uid;

    if (!isset($usersMap[$uid])) {
        continue;
    }

    $user = $usersMap[$uid];
    $target = $currentTargets[$uid] ?? null;

    $baseTarget   = $target ? (float)$target['target_amount'] : 0.00;
    $incentivePct = $target ? (float)$target['incentive_percent'] : 0.00;

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
    $statusText = 'No Target';

    if ($baseTarget <= 0 && $achieved > 0) {
        $statusText = 'Collected (No Target)';
    } elseif ($effectiveTarget > 0 && $achieved >= $effectiveTarget) {
        $excess = $achieved - $effectiveTarget;
        if ($incentivePct > 0) {
            $incentiveAmount = ($excess * $incentivePct) / 100;
        }
        $statusText = 'Achieved';
    } elseif ($effectiveTarget > 0 && $achieved > 0 && $achieved < $effectiveTarget) {
        $shortfall = $effectiveTarget - $achieved;
        $statusText = 'In Progress';
    } elseif ($effectiveTarget > 0) {
        $shortfall = $effectiveTarget - $achieved;
        $statusText = 'Not Started';
    }

    // Search filter
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

    // User filter
    if ($fUserId > 0 && $uid !== $fUserId) {
        continue;
    }

    // Role filter
    if ($fRoleId > 0 && (int)$user['role_id'] !== $fRoleId) {
        continue;
    }

    $exportRows[] = [
        'User Name'         => $user['name'] ?? '-',
        'Email'             => $user['email'] ?? '-',
        'Role'              => $user['role_name'] ?? '-',
        'Base Target'       => number_format($baseTarget, 2, '.', ''),
        'Opening Carry'     => number_format($openingCarry, 2, '.', ''),
        'Effective Target'  => number_format($effectiveTarget, 2, '.', ''),
        'Achieved'          => number_format($achieved, 2, '.', ''),
        'Excess'            => number_format($excess, 2, '.', ''),
        'Shortfall'         => number_format($shortfall, 2, '.', ''),
        'Incentive %'       => number_format($incentivePct, 2, '.', ''),
        'Incentive Amount'  => number_format($incentiveAmount, 2, '.', ''),
        'Status'            => $statusText,
    ];
}

// --------------------------------------------------
// Output CSV
// --------------------------------------------------
$monthLabel = $monthNames[$fMonth] ?? ('Month-' . $fMonth);
$filename = 'target_report_' . strtolower($monthLabel) . '_' . $fYear . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

fwrite($output, "\xEF\xBB\xBF");

// Title rows
fputcsv($output, ['ATS CRM - Target Report Export']);
fputcsv($output, ['Branch', $_SESSION['branch_name'] ?? 'Main Branch']);
fputcsv($output, ['Period', $monthLabel . ' ' . $fYear]);
fputcsv($output, ['Exported By', $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User']);
fputcsv($output, ['Exported At', date('Y-m-d H:i:s')]);
fputcsv($output, []);

// Header
$headers = [
    'S.No',
    'User Name',
    'Email',
    'Role',
    'Base Target',
    'Opening Carry',
    'Effective Target',
    'Achieved',
    'Excess',
    'Shortfall',
    'Incentive %',
    'Incentive Amount',
    'Status'
];
fputcsv($output, $headers);

// Rows
$serial = 1;
foreach ($exportRows as $row) {
    fputcsv($output, [
        $serial++,
        $row['User Name'],
        $row['Email'],
        $row['Role'],
        $row['Base Target'],
        $row['Opening Carry'],
        $row['Effective Target'],
        $row['Achieved'],
        $row['Excess'],
        $row['Shortfall'],
        $row['Incentive %'],
        $row['Incentive Amount'],
        $row['Status'],
    ]);
}

fclose($output);
exit;