<?php
// =====================================
// Targets - Export User Details CSV
// Slug: targets/export-user-details
// File: views/targets/export-user-details.php
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
    die('Access denied. Only HR and Super Admin can export detailed target reports.');
}

$selectedUserId = targetInt($_GET['user_id'] ?? 0);
$fYear          = targetInt($_GET['year'] ?? date('Y'));
$fMonth         = targetInt($_GET['month'] ?? date('n'));

if ($selectedUserId <= 0) {
    die('Invalid user selected.');
}

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

$monthLabel = $monthNames[$fMonth] ?? ('Month-' . $fMonth);

// --------------------------------------------------
// Load selected user
// --------------------------------------------------
try {
    $stmtUser = $pdo->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.role_id,
            r.role_name
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.id = :user_id
          AND u.branch_id = :branch_id
          AND u.status = 1
        LIMIT 1
    ");
    $stmtUser->execute([
        ':user_id'   => $selectedUserId,
        ':branch_id' => $branchId,
    ]);
    $selectedUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$selectedUser) {
        die('Selected user not found in this branch.');
    }
} catch (Throwable $e) {
    die('Unable to load selected user. ' . $e->getMessage());
}

// --------------------------------------------------
// Load current month target
// --------------------------------------------------
$currentTarget = null;
try {
    $stmtTarget = $pdo->prepare("
        SELECT *
        FROM monthly_targets
        WHERE branch_id = :branch_id
          AND user_id = :user_id
          AND target_year = :target_year
          AND target_month = :target_month
        LIMIT 1
    ");
    $stmtTarget->execute([
        ':branch_id'    => $branchId,
        ':user_id'      => $selectedUserId,
        ':target_year'  => $fYear,
        ':target_month' => $fMonth,
    ]);
    $currentTarget = $stmtTarget->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Unable to load current target. ' . $e->getMessage());
}

$baseTarget = $currentTarget ? (float)$currentTarget['target_amount'] : 0.00;
$incentivePercent = $currentTarget ? (float)$currentTarget['incentive_percent'] : 0.00;

// --------------------------------------------------
// Load previous targets for carry calculation
// --------------------------------------------------
$previousTargets = [];
try {
    $stmtPrevTargets = $pdo->prepare("
        SELECT *
        FROM monthly_targets
        WHERE branch_id = :branch_id
          AND user_id = :user_id
          AND (
                target_year < :target_year1
                OR (target_year = :target_year2 AND target_month < :target_month)
              )
        ORDER BY target_year ASC, target_month ASC, id ASC
    ");
    $stmtPrevTargets->execute([
        ':branch_id'    => $branchId,
        ':user_id'      => $selectedUserId,
        ':target_year1' => $fYear,
        ':target_year2' => $fYear,
        ':target_month' => $fMonth,
    ]);
    $previousTargets = $stmtPrevTargets->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Unable to load previous target history. ' . $e->getMessage());
}

// --------------------------------------------------
// Load previous achieved map by year-month
// --------------------------------------------------
$previousAchievedMap = [];
try {
    $stmtPrevAchieved = $pdo->prepare("
        SELECT
            YEAR(payment_date) AS pay_year,
            MONTH(payment_date) AS pay_month,
            COALESCE(SUM(amount), 0) AS total_amount
        FROM registration_payments
        WHERE branch_id = :branch_id
          AND collected_by = :collected_by
          AND approval_status = 'approved'
          AND (
                YEAR(payment_date) < :pay_year1
                OR (YEAR(payment_date) = :pay_year2 AND MONTH(payment_date) < :pay_month)
              )
        GROUP BY YEAR(payment_date), MONTH(payment_date)
    ");
    $stmtPrevAchieved->execute([
        ':branch_id'    => $branchId,
        ':collected_by' => $selectedUserId,
        ':pay_year1'    => $fYear,
        ':pay_year2'    => $fYear,
        ':pay_month'    => $fMonth,
    ]);
    foreach ($stmtPrevAchieved->fetchAll(PDO::FETCH_ASSOC) as $par) {
        $key = (int)$par['pay_year'] . '-' . (int)$par['pay_month'];
        $previousAchievedMap[$key] = (float)$par['total_amount'];
    }
} catch (Throwable $e) {
    die('Unable to load previous achieved data. ' . $e->getMessage());
}

// --------------------------------------------------
// Calculate opening carry
// --------------------------------------------------
$openingCarry = 0.00;

foreach ($previousTargets as $pt) {
    $prevTargetAmount = (float)$pt['target_amount'];
    $prevKey = (int)$pt['target_year'] . '-' . (int)$pt['target_month'];
    $prevAchieved = $previousAchievedMap[$prevKey] ?? 0.00;

    $prevEffective = $prevTargetAmount + $openingCarry;

    if ($prevAchieved >= $prevEffective) {
        $openingCarry = 0.00;
    } else {
        $openingCarry = $prevEffective - $prevAchieved;
    }
}

$effectiveTarget = $baseTarget + $openingCarry;

// --------------------------------------------------
// Load detailed collections for selected month
// --------------------------------------------------
try {
    $stmt = $pdo->prepare("
        SELECT
            rp.id,
            rp.registration_id,
            rp.amount,
            rp.payment_date,
            rp.payment_mode,
            rp.payment_type,
            rp.reference_no,
            rp.receipt_no,
            rp.approval_status,
            rp.remarks,
            rp.created_at,

            r.registration_no,
            r.enquiry_snapshot_name,

            u.name AS collected_by_name

        FROM registration_payments rp
        LEFT JOIN registrations r
            ON r.id = rp.registration_id
        LEFT JOIN users u
            ON u.id = rp.collected_by

        WHERE rp.branch_id = :branch_id
          AND rp.collected_by = :collected_by
          AND YEAR(rp.payment_date) = :pay_year
          AND MONTH(rp.payment_date) = :pay_month
          AND rp.approval_status = 'approved'

        ORDER BY rp.payment_date ASC, rp.id ASC
    ");

    $stmt->execute([
        ':branch_id'    => $branchId,
        ':collected_by' => $selectedUserId,
        ':pay_year'     => $fYear,
        ':pay_month'    => $fMonth,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die('Unable to load collection details. ' . $e->getMessage());
}

// --------------------------------------------------
// Totals and status
// --------------------------------------------------
$totalCollected = 0.00;
foreach ($rows as $row) {
    $totalCollected += (float)($row['amount'] ?? 0);
}

$balanceAmount = 0.00;
$excessAmount = 0.00;
$incentiveAmount = 0.00;
$statusText = 'No Target';

if ($baseTarget <= 0 && $totalCollected > 0) {
    $statusText = 'Collected (No Target)';
} elseif ($effectiveTarget > 0 && $totalCollected >= $effectiveTarget) {
    $excessAmount = $totalCollected - $effectiveTarget;
    if ($incentivePercent > 0) {
        $incentiveAmount = ($excessAmount * $incentivePercent) / 100;
    }
    $statusText = 'Achieved';
} elseif ($effectiveTarget > 0 && $totalCollected > 0 && $totalCollected < $effectiveTarget) {
    $balanceAmount = $effectiveTarget - $totalCollected;
    $statusText = 'In Progress';
} elseif ($effectiveTarget > 0) {
    $balanceAmount = $effectiveTarget - $totalCollected;
    $statusText = 'Not Started';
}

// --------------------------------------------------
// Output CSV
// --------------------------------------------------
$safeUserName = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($selectedUser['name'] ?? 'user'));
$filename = 'target_user_details_' . strtolower($safeUserName) . '_' . strtolower($monthLabel) . '_' . $fYear . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($output, "\xEF\xBB\xBF");

// --------------------------------------------------
// Top summary
// --------------------------------------------------
fputcsv($output, ['ATS CRM - User Collection Details Export']);
fputcsv($output, ['Branch', $_SESSION['branch_name'] ?? 'Main Branch']);
fputcsv($output, ['Period', $monthLabel . ' ' . $fYear]);
fputcsv($output, ['User', $selectedUser['name'] ?? '-']);
fputcsv($output, ['Email', $selectedUser['email'] ?? '-']);
fputcsv($output, ['Role', $selectedUser['role_name'] ?? '-']);
fputcsv($output, ['Exported By', $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'User']);
fputcsv($output, ['Exported At', date('Y-m-d H:i:s')]);
fputcsv($output, []);

fputcsv($output, ['TARGET SUMMARY']);
fputcsv($output, ['Base Target', number_format($baseTarget, 2, '.', '')]);
fputcsv($output, ['Opening Carry', number_format($openingCarry, 2, '.', '')]);
fputcsv($output, ['Effective Target', number_format($effectiveTarget, 2, '.', '')]);
fputcsv($output, ['Total Approved Collection', number_format($totalCollected, 2, '.', '')]);
fputcsv($output, ['Balance / Shortfall', number_format($balanceAmount, 2, '.', '')]);
fputcsv($output, ['Excess', number_format($excessAmount, 2, '.', '')]);
fputcsv($output, ['Incentive %', number_format($incentivePercent, 2, '.', '')]);
fputcsv($output, ['Incentive Amount', number_format($incentiveAmount, 2, '.', '')]);
fputcsv($output, ['Status', $statusText]);
fputcsv($output, []);

// --------------------------------------------------
// Detail header
// --------------------------------------------------
fputcsv($output, ['COLLECTION DETAILS']);
$headers = [
    'S.No',
    'Payment Date',
    'Registration No',
    'Student Name',
    'Amount',
    'Payment Mode',
    'Payment Type',
    'Reference No',
    'Receipt No',
    'Approval Status',
    'Remarks',
    'Collected By'
];
fputcsv($output, $headers);

// --------------------------------------------------
// Detail rows
// --------------------------------------------------
$serial = 1;

foreach ($rows as $row) {
    $studentName = $row['enquiry_snapshot_name'] ?? '-';
    $amount = (float)($row['amount'] ?? 0);

    fputcsv($output, [
        $serial++,
        $row['payment_date'] ?? '-',
        $row['registration_no'] ?? '-',
        $studentName ?: '-',
        number_format($amount, 2, '.', ''),
        $row['payment_mode'] ?? '-',
        $row['payment_type'] ?? '-',
        $row['reference_no'] ?? '-',
        $row['receipt_no'] ?? '-',
        $row['approval_status'] ?? '-',
        $row['remarks'] ?? '-',
        $row['collected_by_name'] ?? '-',
    ]);
}

// --------------------------------------------------
// Footer total
// --------------------------------------------------
fputcsv($output, []);
fputcsv($output, [
    '',
    '',
    '',
    'Total Approved Collection',
    number_format($totalCollected, 2, '.', ''),
    '',
    '',
    '',
    '',
    '',
    '',
    ''
]);

fclose($output);
exit;