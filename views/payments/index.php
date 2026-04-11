<?php
// =====================================
// ATS CRM - Payments Module
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

requireView('payments/index');

$pageTitle = "Payments";
$success = '';
$error = '';

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleName = $_SESSION['role_name'] ?? '';
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// =========================================================
// NEW MODIFICATION DONE ON 2026-03-23
// Payments filters, row permissions, and Excel export support
// =========================================================
$q = trim((string)($_GET['q'] ?? ''));
$statusFilter = trim((string)($_GET['payment_status'] ?? ''));
$staffFilter = (int)($_GET['staff_id'] ?? 0);
$exportAction = trim((string)($_GET['export'] ?? ''));
$isPrivilegedRole = in_array($roleName, ['Super Admin', 'HR'], true);
$canDownloadAll = $isPrivilegedRole || $roleName === 'Front Office';

if (!function_exists('paymentsH')) {
    function paymentsH($value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('paymentsNull')) {
    function paymentsNull($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}

if (!function_exists('paymentsDecimal')) {
    function paymentsDecimal($value): float
    {
        $value = trim((string) $value);
        return $value === '' ? 0.0 : (float) $value;
    }
}

if (!function_exists('paymentsMakeReceiptNo')) {
    function paymentsMakeReceiptNo(PDO $pdo): string
    {
        $prefix = 'RCPT-' . date('Ym') . '-';
        $st = $pdo->prepare("
            SELECT MAX(
                CAST(
                    SUBSTRING(
                        receipt_no,
                        CHAR_LENGTH(CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci) + 1
                    ) AS UNSIGNED
                )
            )
            FROM registration_payments
            WHERE receipt_no COLLATE utf8mb4_general_ci LIKE CONCAT(
                CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci,
                '%'
            )
        ");
        $st->execute([$prefix, $prefix]);
        $maxNum = (int) ($st->fetchColumn() ?? 0);
        return $prefix . str_pad((string) ($maxNum + 1), 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('paymentsRecalcRegistrationSummary')) {
    function paymentsRecalcRegistrationSummary(PDO $pdo, int $regId): void
    {
        $st = $pdo->prepare("SELECT final_fee FROM registrations WHERE id=? LIMIT 1");
        $st->execute([$regId]);
        $finalFee = (float) ($st->fetchColumn() ?? 0);

        $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM registration_payments WHERE registration_id=? AND approval_status='approved'");
        $st->execute([$regId]);
        $paidSum = (float) $st->fetchColumn();

        $balance = max(0, $finalFee - $paidSum);
        $paymentStatus = 'unpaid';

        if ($paidSum > 0 && $paidSum < $finalFee) {
            $paymentStatus = 'partial';
        } elseif ($paidSum >= $finalFee && $finalFee > 0) {
            $paymentStatus = 'paid';
            $balance = 0;
        }

        $upd = $pdo->prepare("
            UPDATE registrations
            SET paid_amount=?, balance_amount=?, payment_status=?, updated_at=NOW()
            WHERE id=?
        ");
        $upd->execute([$paidSum, $balance, $paymentStatus, $regId]);
    }
}

if (!function_exists('paymentsBuildFilters')) {
    function paymentsBuildFilters(string $roleName, int $userId, string $q, string $statusFilter, int $staffFilter, int $branchId = 0, int $canAllBranches = 0): array
    {
        $where = [];
        $params = [];

        // Branch filtering
        if ($canAllBranches !== 1 && $branchId > 0) {
            $where[] = 'r.branch_id = ?';
            $params[] = $branchId;
        }

        if ($roleName === 'Front Office') {
            $where[] = 'COALESCE(lp.staff_id, r.assigned_to) = ?';
            $params[] = $userId;
        } elseif (in_array($roleName, ['Super Admin', 'HR'], true) && $staffFilter > 0) {
            $where[] = 'COALESCE(lp.staff_id, r.assigned_to) = ?';
            $params[] = $staffFilter;
        }

        if ($statusFilter !== '' && in_array($statusFilter, ['paid', 'partial', 'unpaid'], true)) {
            $where[] = 'r.payment_status = ?';
            $params[] = $statusFilter;
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(
                r.registration_no LIKE ?
                OR r.enquiry_snapshot_name LIKE ?
                OR COALESCE(owner_u.name, fallback_u.name, \'\') LIKE ?
            )';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return [
            'where_sql' => $where ? ('WHERE ' . implode(' AND ', $where)) : '',
            'params' => $params,
        ];
    }
}

if (!function_exists('paymentsCanDownloadRegistration')) {
    function paymentsCanDownloadRegistration(string $roleName, int $userId, int $ownerId): bool
    {
        return in_array($roleName, ['Super Admin', 'HR'], true) || $ownerId === $userId;
    }
}

if (!function_exists('paymentsRequireSpreadsheet')) {
    function paymentsRequireSpreadsheet(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        }
    }
}

if (!function_exists('paymentsApplyExportHeaderStyle')) {
    function paymentsApplyExportHeaderStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E91E63'],
            ],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
    }
}

if (!function_exists('paymentsAutosizeColumns')) {
    function paymentsAutosizeColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}

if (!function_exists('paymentsStreamSpreadsheet')) {
    function paymentsStreamSpreadsheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $fileName): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

$canAllBranches = 0;
try {
    $roleStmt = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $roleStmt->execute([$roleId]);
    $canAllBranches = (int) ($roleStmt->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF). Please refresh and try again.";
    } else {
        $regId = (int) ($_POST['registration_id'] ?? 0);
        $amount = paymentsDecimal($_POST['amount'] ?? 0);
        $paymentDate = paymentsNull($_POST['payment_date'] ?? '') ?: date('Y-m-d');
        $paymentMode = paymentsNull($_POST['payment_mode'] ?? 'Cash') ?: 'Cash';
        $paymentType = paymentsNull($_POST['payment_type'] ?? 'partial') ?: 'partial';
        $referenceNo = paymentsNull($_POST['reference_no'] ?? '');
        $remarksPay = paymentsNull($_POST['remarks'] ?? '');
        $approvalStatus = 'approved';

        if ($regId <= 0) {
            $error = "Invalid registration selected.";
        } elseif ($amount <= 0) {
            $error = "Amount must be greater than zero.";
        } else {
            try {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND branch_id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
                    $st->execute([$regId, $branchId]);
                } else {
                    $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
                    $st->execute([$regId]);
                }

                $reg = $st->fetch(PDO::FETCH_ASSOC);

                if (!$reg) {
                    throw new Exception("Registration not found or access denied.");
                }

                $finalFee = (float) ($reg['final_fee'] ?? 0);
                $paidAmount = (float) ($reg['paid_amount'] ?? 0);
                $balance = max(0, $finalFee - $paidAmount);

                if ($paymentType !== 'refund' && $amount > $balance && $balance > 0) {
                    throw new Exception("Amount cannot be greater than balance.");
                }

                $staffId = (int) ($reg['assigned_to'] ?? 0);
                if ($staffId <= 0) {
                    throw new Exception("Front office owner missing in registration.");
                }

                $receiptNo = paymentsMakeReceiptNo($pdo);

                $pdo->beginTransaction();

                $ins = $pdo->prepare("
                    INSERT INTO registration_payments (
                        registration_id, branch_id, staff_id, collected_by, approved_by,
                        amount, payment_date, payment_mode, payment_type,
                        reference_no, receipt_no, approval_status, remarks,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        NOW(), NOW()
                    )
                ");
                $ins->execute([
                    $regId,
                    (int) ($reg['branch_id'] ?? 0),
                    $staffId,
                    $userId,
                    $userId,
                    $amount,
                    $paymentDate,
                    $paymentMode,
                    $paymentType,
                    $referenceNo,
                    $receiptNo,
                    $approvalStatus,
                    $remarksPay,
                ]);

                paymentsRecalcRegistrationSummary($pdo, $regId);
                $pdo->commit();

                $success = "Payment saved successfully! Receipt No: " . $receiptNo;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Failed to save payment. " . $e->getMessage();
            }
        }
    }
}

$filterParts = paymentsBuildFilters($roleName, $userId, $q, $statusFilter, $staffFilter, $branchId, $canAllBranches);
$whereSql = $filterParts['where_sql'];
$params = $filterParts['params'];

$staffOptions = [];
if ($isPrivilegedRole) {
    try {
        $staffStmt = $pdo->query("
            -- =========================================================
            -- NEW MODIFICATION DONE ON 2026-03-23
            -- Show only target-role staff in the payments filter dropdown
            -- =========================================================
            SELECT u.id, u.name, COALESCE(r.role_name, '-') AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.status = 1
              AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'hr', 'marketing', 'corporate')
            ORDER BY r.role_name ASC, u.name ASC
        ");
        $staffOptions = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $staffOptions = [];
    }
}

/* ===============================
NEW MODIFICATION DONE ON 2026-03-23
Excel Exports
=============================== */
if ($exportAction === 'all_transactions' && $canDownloadAll) {
    paymentsRequireSpreadsheet();

    // =========================================================
    // NEW MODIFICATION DONE ON 2026-03-23
    // Build export filters separately so staff exports use payment target-credit rows
    // =========================================================
    $targetOwnerId = 0;
    if ($roleName === 'Front Office') {
        $targetOwnerId = $userId;
    } elseif ($isPrivilegedRole && $staffFilter > 0) {
        $targetOwnerId = $staffFilter;
    }

    $exportWhere = [];
    $exportParams = [];

    // Branch filter for exports
    if ($canAllBranches !== 1 && $branchId > 0) {
        $exportWhere[] = 'r.branch_id = ?';
        $exportParams[] = $branchId;
    }

    if ($targetOwnerId > 0) {
        $exportWhere[] = 'p.staff_id = ?';
        $exportParams[] = $targetOwnerId;
    }

    if ($statusFilter !== '' && in_array($statusFilter, ['paid', 'partial', 'unpaid'], true)) {
        $exportWhere[] = 'r.payment_status = ?';
        $exportParams[] = $statusFilter;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $exportWhere[] = '(
            r.registration_no LIKE ?
            OR r.enquiry_snapshot_name LIKE ?
            OR COALESCE(owner_u.name, fallback_u.name, \'\') LIKE ?
        )';
        $exportParams[] = $like;
        $exportParams[] = $like;
        $exportParams[] = $like;
    }

    $exportWhereSql = $exportWhere ? ('WHERE ' . implode(' AND ', $exportWhere)) : '';

    $exportStmt = $pdo->prepare("
        SELECT
            r.id AS registration_id,
            r.registration_no,
            r.reg_type,
            r.enquiry_snapshot_name,
            r.final_fee,
            r.paid_amount,
            r.balance_amount,
            r.payment_status,
            p.staff_id,
            COALESCE(owner_u.name, fallback_u.name, '-') AS owner_name,
            p.payment_date,
            p.receipt_no,
            p.payment_mode,
            p.payment_type,
            p.approval_status,
            p.amount,
            p.reference_no,
            p.remarks
        FROM registrations r
        INNER JOIN registration_payments p ON p.registration_id = r.id
        LEFT JOIN (
            SELECT rp1.registration_id, rp1.staff_id
            FROM registration_payments rp1
            INNER JOIN (
                SELECT registration_id, MAX(id) AS max_id
                FROM registration_payments
                GROUP BY registration_id
            ) rp2 ON rp2.registration_id = rp1.registration_id AND rp2.max_id = rp1.id
        ) lp ON lp.registration_id = r.id
        LEFT JOIN users owner_u ON owner_u.id = p.staff_id
        LEFT JOIN users fallback_u ON fallback_u.id = r.assigned_to
        $exportWhereSql
        ORDER BY p.payment_date DESC, p.id DESC
    ");
    $exportStmt->execute($exportParams);
    $rows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

    $grandTotal = 0.0;
    foreach ($rows as $row) {
        $grandTotal += (float)($row['amount'] ?? 0);
    }

    if ($targetOwnerId > 0) {
        // =========================================================
        // NEW MODIFICATION DONE ON 2026-03-23
        // Staff-selected Download All now exports Summary + Detailed Transactions sheets
        // =========================================================
        $targetStmt = $pdo->prepare("
            SELECT COALESCE(SUM(target_amount), 0) AS target_amount
            FROM monthly_targets
            WHERE user_id = ?
              AND target_year = ?
              AND target_month = ?
              AND status = 'active'
        ");
        $targetStmt->execute([$targetOwnerId, (int)date('Y'), (int)date('n')]);
        $monthlyTarget = (float)($targetStmt->fetchColumn() ?: 0);
        $balanceTarget = $monthlyTarget - $grandTotal;

        $summaryRows = [];
        foreach ($rows as $row) {
            $registrationKey = (int)($row['registration_id'] ?? 0);
            if (!isset($summaryRows[$registrationKey])) {
                $summaryRows[$registrationKey] = [
                    'student_name' => (string)($row['enquiry_snapshot_name'] ?? '-'),
                    'registration_no' => (string)($row['registration_no'] ?? '-'),
                    'reg_type' => (string)($row['reg_type'] ?? '-'),
                    'final_fee' => (float)($row['final_fee'] ?? 0),
                    'paid_amount' => (float)($row['paid_amount'] ?? 0),
                    'total_collection' => 0.0,
                ];
            }
            $summaryRows[$registrationKey]['total_collection'] += (float)($row['amount'] ?? 0);
        }

        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');
        $summaryHeaders = [
            'A1' => 'Student Name',
            'B1' => 'Registration No',
            'C1' => 'Registration Type',
            'D1' => 'Final Fee',
            'E1' => 'Paid Amount',
            'F1' => 'Total Collection',
        ];
        foreach ($summaryHeaders as $cell => $label) {
            $summarySheet->setCellValue($cell, $label);
        }
        paymentsApplyExportHeaderStyle($summarySheet, 'A1:F1');

        $summaryRowIndex = 2;
        foreach ($summaryRows as $summaryRow) {
            $summarySheet->setCellValue("A{$summaryRowIndex}", $summaryRow['student_name']);
            $summarySheet->setCellValue("B{$summaryRowIndex}", $summaryRow['registration_no']);
            $summarySheet->setCellValue("C{$summaryRowIndex}", ucfirst(str_replace('_', ' ', (string)$summaryRow['reg_type'])));
            $summarySheet->setCellValue("D{$summaryRowIndex}", $summaryRow['final_fee']);
            $summarySheet->setCellValue("E{$summaryRowIndex}", $summaryRow['paid_amount']);
            $summarySheet->setCellValue("F{$summaryRowIndex}", $summaryRow['total_collection']);
            $summaryRowIndex++;
        }

        // =========================================================
        // NEW MODIFICATION DONE ON 2026-03-23
        // Show monthly target and balance target once in the summary footer block
        // =========================================================
        $summarySheet->setCellValue("E{$summaryRowIndex}", 'Grand Total');
        $summarySheet->setCellValue("F{$summaryRowIndex}", $grandTotal);
        $summarySheet->getStyle("E{$summaryRowIndex}:F{$summaryRowIndex}")->getFont()->setBold(true);

        $summaryRowIndex += 2;
        $summarySheet->setCellValue("E{$summaryRowIndex}", 'Monthly Target');
        $summarySheet->setCellValue("F{$summaryRowIndex}", $monthlyTarget);
        $summarySheet->setCellValue("E" . ($summaryRowIndex + 1), 'Balance Target');
        $summarySheet->setCellValue("F" . ($summaryRowIndex + 1), $balanceTarget);
        $summarySheet->getStyle("E{$summaryRowIndex}:F" . ($summaryRowIndex + 1))->getFont()->setBold(true);
        $summarySheet->freezePane('A2');
        paymentsAutosizeColumns($summarySheet, range('A', 'F'));

        $detailSheet = $spreadsheet->createSheet();
        $detailSheet->setTitle('Detailed Transactions');
        $detailHeaders = [
            'A1' => 'Student Name',
            'B1' => 'Registration No',
            'C1' => 'Registration Type',
            'D1' => 'Staff Owner',
            'E1' => 'Collection Date',
            'F1' => 'Receipt No',
            'G1' => 'Payment Mode',
            'H1' => 'Payment Type',
            'I1' => 'Approval Status',
            'J1' => 'Collected Amount',
            'K1' => 'Final Fee',
            'L1' => 'Total Paid',
            'M1' => 'Balance',
            'N1' => 'Reference No',
            'O1' => 'Remarks',
        ];
        foreach ($detailHeaders as $cell => $label) {
            $detailSheet->setCellValue($cell, $label);
        }
        paymentsApplyExportHeaderStyle($detailSheet, 'A1:O1');

        $detailRowIndex = 2;
        foreach ($rows as $row) {
            $detailSheet->setCellValue("A{$detailRowIndex}", (string)($row['enquiry_snapshot_name'] ?? '-'));
            $detailSheet->setCellValue("B{$detailRowIndex}", (string)($row['registration_no'] ?? '-'));
            $detailSheet->setCellValue("C{$detailRowIndex}", ucfirst(str_replace('_', ' ', (string)($row['reg_type'] ?? '-'))));
            $detailSheet->setCellValue("D{$detailRowIndex}", (string)($row['owner_name'] ?? '-'));
            $detailSheet->setCellValue("E{$detailRowIndex}", (string)($row['payment_date'] ?? '-'));
            $detailSheet->setCellValue("F{$detailRowIndex}", (string)($row['receipt_no'] ?? '-'));
            $detailSheet->setCellValue("G{$detailRowIndex}", ucfirst((string)($row['payment_mode'] ?? '-')));
            $detailSheet->setCellValue("H{$detailRowIndex}", ucfirst((string)($row['payment_type'] ?? '-')));
            $detailSheet->setCellValue("I{$detailRowIndex}", ucfirst((string)($row['approval_status'] ?? '-')));
            $detailSheet->setCellValue("J{$detailRowIndex}", (float)($row['amount'] ?? 0));
            $detailSheet->setCellValue("K{$detailRowIndex}", (float)($row['final_fee'] ?? 0));
            $detailSheet->setCellValue("L{$detailRowIndex}", (float)($row['paid_amount'] ?? 0));
            $detailSheet->setCellValue("M{$detailRowIndex}", (float)($row['balance_amount'] ?? 0));
            $detailSheet->setCellValue("N{$detailRowIndex}", (string)($row['reference_no'] ?? '-'));
            $detailSheet->setCellValue("O{$detailRowIndex}", (string)($row['remarks'] ?? '-'));
            $detailRowIndex++;
        }
        $detailSheet->setCellValue("I{$detailRowIndex}", 'Grand Total');
        $detailSheet->setCellValue("J{$detailRowIndex}", $grandTotal);
        $detailSheet->getStyle("I{$detailRowIndex}:J{$detailRowIndex}")->getFont()->setBold(true);
        $detailSheet->freezePane('A2');
        paymentsAutosizeColumns($detailSheet, range('A', 'O'));
    } else {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('All Payments');

        $headers = [
            'A1' => 'Student Name',
            'B1' => 'Registration No',
            'C1' => 'Registration Type',
            'D1' => 'Staff Owner',
            'E1' => 'Collection Date',
            'F1' => 'Receipt No',
            'G1' => 'Payment Mode',
            'H1' => 'Payment Type',
            'I1' => 'Approval Status',
            'J1' => 'Collected Amount',
            'K1' => 'Final Fee',
            'L1' => 'Total Paid',
            'M1' => 'Balance',
            'N1' => 'Reference No',
            'O1' => 'Remarks',
        ];

        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }
        paymentsApplyExportHeaderStyle($sheet, 'A1:O1');

        $rowIndex = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue("A{$rowIndex}", (string)($row['enquiry_snapshot_name'] ?? '-'));
            $sheet->setCellValue("B{$rowIndex}", (string)($row['registration_no'] ?? '-'));
            $sheet->setCellValue("C{$rowIndex}", ucfirst(str_replace('_', ' ', (string)($row['reg_type'] ?? '-'))));
            $sheet->setCellValue("D{$rowIndex}", (string)($row['owner_name'] ?? '-'));
            $sheet->setCellValue("E{$rowIndex}", (string)($row['payment_date'] ?? '-'));
            $sheet->setCellValue("F{$rowIndex}", (string)($row['receipt_no'] ?? '-'));
            $sheet->setCellValue("G{$rowIndex}", ucfirst((string)($row['payment_mode'] ?? '-')));
            $sheet->setCellValue("H{$rowIndex}", ucfirst((string)($row['payment_type'] ?? '-')));
            $sheet->setCellValue("I{$rowIndex}", ucfirst((string)($row['approval_status'] ?? '-')));
            $sheet->setCellValue("J{$rowIndex}", (float)($row['amount'] ?? 0));
            $sheet->setCellValue("K{$rowIndex}", (float)($row['final_fee'] ?? 0));
            $sheet->setCellValue("L{$rowIndex}", (float)($row['paid_amount'] ?? 0));
            $sheet->setCellValue("M{$rowIndex}", (float)($row['balance_amount'] ?? 0));
            $sheet->setCellValue("N{$rowIndex}", (string)($row['reference_no'] ?? '-'));
            $sheet->setCellValue("O{$rowIndex}", (string)($row['remarks'] ?? '-'));
            $rowIndex++;
        }

        $sheet->setCellValue("I{$rowIndex}", 'Grand Total');
        $sheet->setCellValue("J{$rowIndex}", $grandTotal);
        $sheet->getStyle("I{$rowIndex}:J{$rowIndex}")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        paymentsAutosizeColumns($sheet, range('A', 'O'));
    }

    paymentsStreamSpreadsheet($spreadsheet, 'payments-all-' . date('Ymd-His') . '.xlsx');
}

if ($exportAction === 'student_details') {
    $registrationId = (int)($_GET['reg_id'] ?? 0);
    if ($registrationId > 0) {
        $studentExportSql = "
            SELECT
                r.id,
                r.registration_no,
                r.enquiry_snapshot_name,
                r.final_fee,
                r.paid_amount,
                r.balance_amount,
                r.payment_status,
                COALESCE(lp.staff_id, r.assigned_to) AS credit_owner_id,
                COALESCE(owner_u.name, fallback_u.name, '-') AS target_owner_name,
                COALESCE(fallback_u.name, '-') AS assigned_owner_name
            FROM registrations r
            LEFT JOIN (
                SELECT rp1.registration_id, rp1.staff_id
                FROM registration_payments rp1
                INNER JOIN (
                    SELECT registration_id, MAX(id) AS max_id
                    FROM registration_payments
                    GROUP BY registration_id
                ) rp2 ON rp2.registration_id = rp1.registration_id AND rp2.max_id = rp1.id
            ) lp ON lp.registration_id = r.id
            LEFT JOIN users owner_u ON owner_u.id = lp.staff_id
            LEFT JOIN users fallback_u ON fallback_u.id = r.assigned_to
            WHERE r.id = ?";

        $studentExportParams = [$registrationId];

        // Branch scope for student export
        if ($canAllBranches !== 1 && $branchId > 0) {
            $studentExportSql .= " AND r.branch_id = ?";
            $studentExportParams[] = $branchId;
        }

        $studentExportSql .= " LIMIT 1";

        $studentStmt = $pdo->prepare($studentExportSql);
        $studentStmt->execute($studentExportParams);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if ($student && paymentsCanDownloadRegistration($roleName, $userId, (int)($student['credit_owner_id'] ?? 0))) {
            paymentsRequireSpreadsheet();

            $historyStmt = $pdo->prepare("
                SELECT
                    payment_date,
                    receipt_no,
                    COALESCE(u.name, '-') AS staff_owner_name,
                    payment_mode,
                    payment_type,
                    approval_status,
                    amount,
                    reference_no,
                    remarks
                FROM registration_payments
                LEFT JOIN users u ON u.id = registration_payments.staff_id
                WHERE registration_id = ?
                -- =========================================================
                -- NEW MODIFICATION DONE ON 2026-03-23
                -- Qualify payment id in export history sorting to avoid SQL ambiguity
                -- =========================================================
                ORDER BY payment_date DESC, registration_payments.id DESC
            ");
            $historyStmt->execute([$registrationId]);
            $historyRows = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Student Summary');
            $summarySheet->fromArray(
                [
                    ['Field', 'Value'],
                    ['Student Name', (string)($student['enquiry_snapshot_name'] ?? '-')],
                    ['Registration No', (string)($student['registration_no'] ?? '-')],
                    // =========================================================
                    // NEW MODIFICATION DONE ON 2026-03-23
                    // Show both target-credit staff and assigned staff in student export
                    // =========================================================
                    ['Converted / Target Staff', (string)($student['target_owner_name'] ?? '-')],
                    ['Assigned Staff', (string)($student['assigned_owner_name'] ?? '-')],
                    ['Payment Status', ucfirst((string)($student['payment_status'] ?? '-'))],
                    ['Final Fee', (float)($student['final_fee'] ?? 0)],
                    ['Total Paid', (float)($student['paid_amount'] ?? 0)],
                    ['Balance', (float)($student['balance_amount'] ?? 0)],
                    ['Payments Count', count($historyRows)],
                ],
                null,
                'A1'
            );
            paymentsApplyExportHeaderStyle($summarySheet, 'A1:B1');
            $summarySheet->freezePane('A2');
            paymentsAutosizeColumns($summarySheet, ['A', 'B']);

            $historySheet = $spreadsheet->createSheet();
            $historySheet->setTitle('Payment History');
            $historySheet->fromArray(
                [[
                    'Payment Date',
                    'Receipt No',
                    'Staff Owner',
                    'Amount',
                    'Payment Mode',
                    'Payment Type',
                    'Approval Status',
                    'Reference No',
                    'Remarks'
                ]],
                null,
                'A1'
            );
            paymentsApplyExportHeaderStyle($historySheet, 'A1:I1');

            $rowIndex = 2;
            foreach ($historyRows as $historyRow) {
                $historySheet->setCellValue("A{$rowIndex}", (string)($historyRow['payment_date'] ?? '-'));
                $historySheet->setCellValue("B{$rowIndex}", (string)($historyRow['receipt_no'] ?? '-'));
                $historySheet->setCellValue("C{$rowIndex}", (string)($historyRow['staff_owner_name'] ?? '-'));
                $historySheet->setCellValue("D{$rowIndex}", (float)($historyRow['amount'] ?? 0));
                $historySheet->setCellValue("E{$rowIndex}", ucfirst((string)($historyRow['payment_mode'] ?? '-')));
                $historySheet->setCellValue("F{$rowIndex}", ucfirst((string)($historyRow['payment_type'] ?? '-')));
                $historySheet->setCellValue("G{$rowIndex}", ucfirst((string)($historyRow['approval_status'] ?? '-')));
                $historySheet->setCellValue("H{$rowIndex}", (string)($historyRow['reference_no'] ?? '-'));
                $historySheet->setCellValue("I{$rowIndex}", (string)($historyRow['remarks'] ?? '-'));
                $rowIndex++;
            }

            $historySheet->setCellValue("C{$rowIndex}", 'Grand Total');
            $historySheet->setCellValue("D{$rowIndex}", (float)($student['paid_amount'] ?? 0));
            $historySheet->getStyle("C{$rowIndex}:D{$rowIndex}")->getFont()->setBold(true);
            $historySheet->freezePane('A2');
            paymentsAutosizeColumns($historySheet, range('A', 'I'));

            paymentsStreamSpreadsheet(
                $spreadsheet,
                'student-payment-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($student['registration_no'] ?? $registrationId)) . '.xlsx'
            );
        }
    }
}

/* ===============================
NEW MODIFICATION DONE ON 2026-03-23
Fetch Data
=============================== */
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.registration_no,
        r.enquiry_snapshot_name,
        r.final_fee,
        r.balance_amount,
        r.paid_amount,
        r.payment_status,
        COALESCE(lp.staff_id, r.assigned_to) AS credit_owner_id,
        COALESCE(owner_u.name, fallback_u.name, '-') AS owner_name,
        IFNULL(SUM(p.amount),0) AS total_paid,
        MAX(p.payment_date) AS last_payment_date
    FROM registrations r
    LEFT JOIN registration_payments p ON p.registration_id = r.id
    LEFT JOIN (
        SELECT rp1.registration_id, rp1.staff_id
        FROM registration_payments rp1
        INNER JOIN (
            SELECT registration_id, MAX(id) AS max_id
            FROM registration_payments
            GROUP BY registration_id
        ) rp2 ON rp2.registration_id = rp1.registration_id AND rp2.max_id = rp1.id
    ) lp ON lp.registration_id = r.id
    LEFT JOIN users owner_u ON owner_u.id = lp.staff_id
    LEFT JOIN users fallback_u ON fallback_u.id = r.assigned_to
    $whereSql
    GROUP BY
        r.id,
        r.registration_no,
        r.enquiry_snapshot_name,
        r.final_fee,
        r.balance_amount,
        r.paid_amount,
        r.payment_status,
        COALESCE(lp.staff_id, r.assigned_to),
        COALESCE(owner_u.name, fallback_u.name, '-')
    ORDER BY last_payment_date DESC, r.id DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$basePageUrl = 'index.php?page=payments/index'
    . '&q=' . urlencode($q)
    . '&payment_status=' . urlencode($statusFilter)
    . '&staff_id=' . (int)$staffFilter;

$exportBaseUrl = 'index.php?page=payments/index&ajax=1'
    . '&q=' . urlencode($q)
    . '&payment_status=' . urlencode($statusFilter)
    . '&staff_id=' . (int)$staffFilter;
?>

<div class="payments-dashboard">
<div class="dashboard-header">
<h2><i class="fas fa-wallet" style="margin-right: 12px; color: #e91e63;"></i>Payments Management</h2>
<div class="header-stats">
<span class="stat-item"><i class="fas fa-database"></i> Total: <?= count($payments) ?></span>
</div>
</div>

<?php if ($success): ?>
<script>
(function(){
  const msg = '<?= addslashes($success) ?>';
  if (window.Swal && Swal.fire) {
    Swal.fire({
      icon:'success',
      title:'Success',
      text: msg,
      confirmButtonColor:'#e91e63'
    }).then(()=>{ window.location.href = "index.php?page=payments/index"; });
  } else {
    alert(msg);
    window.location.href = "index.php?page=payments/index";
  }
})();
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
(function(){
  const msg = '<?= addslashes($error) ?>';
  if (window.Swal && Swal.fire) {
    Swal.fire({
      icon:'error',
      title:'Error',
      text: msg,
      confirmButtonColor:'#e91e63'
    });
  } else {
    alert(msg);
  }
})();
</script>
<?php endif; ?>

<div class="card">
<div class="card-header">
<i class="fas fa-sliders-h" style="margin-right: 8px;"></i> Filter Payments
</div>

<form method="GET" action="index.php" class="filter-form">
<input type="hidden" name="page" value="payments/index">

<div class="filter-grid">
<div class="filter-item">
<label><i class="fas fa-search"></i> Search</label>
<input type="text" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Student or registration...">
</div>

<div class="filter-item">
<label><i class="fas fa-wallet"></i> Payment Status</label>
<select name="payment_status">
<option value="">All Status</option>
<option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
<option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
<option value="unpaid" <?= $statusFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
</select>
</div>

<?php if ($isPrivilegedRole): ?>
<div class="filter-item">
<label><i class="fas fa-user-tie"></i> Staff</label>
<select name="staff_id">
<option value="">All Staff</option>
<?php foreach ($staffOptions as $staff): ?>
<option value="<?= (int)$staff['id'] ?>" <?= $staffFilter === (int)$staff['id'] ? 'selected' : '' ?>>
<?= paymentsH($staff['name']) ?> (<?= paymentsH($staff['role_name'] ?? '-') ?>)
</option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<div class="filter-actions">
<?php if ($canDownloadAll): ?>
<a href="<?= paymentsH($exportBaseUrl . '&export=all_transactions') ?>" class="btn-download-all" title="Download all filtered payments" data-mobile-label="Download">
<span class="btn-inner">
<i class="fas fa-file-excel"></i>
</span>
</a>
<?php endif; ?>
<button type="submit" class="btn-icon-only apply" title="Apply filters" data-mobile-label="Apply">
<span class="btn-inner">
<i class="fas fa-filter"></i>
</span>
</button>
<a href="index.php?page=payments/index" class="btn-icon-only reset" title="Reset filters" data-mobile-label="Reset">
<span class="btn-inner">
<i class="fas fa-undo-alt"></i>
</span>
</a>
</div>
</div>
</form>
</div>

<div class="card">
<div class="card-header">
<div class="table-header-flex">
<div class="table-title">
<i class="fas fa-list"></i> Payments List
</div>
<div id="datatableControls"></div>
</div>
</div>
<div class="table-container">
<div class="crm-table-wrapper">

<table id="userTable" class="crm-table display" style="width:100%">

<thead>
<tr>
<th>Student</th>
<th>Registration</th>
<th>Total Fee</th>
<th>Paid</th>
<th>Balance</th>
<th>Status</th>
<th>Last Payment</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($payments as $p): ?>

<tr>

<td><?= htmlspecialchars($p['enquiry_snapshot_name'] ?? '-') ?></td>

<td><?= htmlspecialchars($p['registration_no'] ?? '-') ?></td>

<td><?= inr_symbol() ?> <?= number_format($p['final_fee'] ?? 0,2) ?></td>

<td><?= inr_symbol() ?> <?= number_format($p['total_paid'] ?? 0,2) ?></td>

<td><?= inr_symbol() ?> <?= number_format($p['balance_amount'] ?? 0,2) ?></td>

<td>

<?php
$status=$p['payment_status'] ?? 'unpaid';

if($status=="paid"){
echo "<span class='status-paid'>Paid</span>";
}
elseif($status=="partial"){
echo "<span class='status-partial'>Partial</span>";
}
else{
echo "<span class='status-unpaid'>Unpaid</span>";
}
?>

</td>

<td><?= htmlspecialchars($p['last_payment_date'] ?? '-') ?></td>

<td>

<div class="pay-action">

<?php if($roleName === "Front Office"): ?>
<button
class="btn-add-payment"
data-mobile-label="Add"
data-tooltip="Add payment"
onclick="openPaymentModal(<?= (int)$p['id'] ?>)">
<span class="btn-inner">
<i class="fas fa-plus"></i>
</span>
</button>
<?php endif; ?>

<?php
// =========================================================
// NEW MODIFICATION DONE ON 2026-03-23
// Use payment credit owner for row-level download permission
// =========================================================
?>
<?php if (paymentsCanDownloadRegistration($roleName, $userId, (int)($p['credit_owner_id'] ?? 0))): ?>
<a
class="btn-icon btn-download"
data-mobile-label="Download"
href="<?= paymentsH($exportBaseUrl . '&export=student_details&reg_id=' . (int)$p['id']) ?>"
target="_blank"
rel="noopener"
title="Download Excel"
data-tooltip="Download Excel">
<span class="btn-inner">
<i class="fas fa-file-excel"></i>
</span>

</a>
<?php endif; ?>

<a
class="btn-icon btn-view"
data-mobile-label="View"
href="index.php?page=students/profile&id=<?= $p['id'] ?>"
title="Student Profile"
data-tooltip="Student profile">
<span class="btn-inner">
<i class="fas fa-eye"></i>
</span>

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
</div>

<!-- MODAL -->

<div class="crm-modal-backdrop" id="crmModalBackdrop">

<div class="crm-modal">

<div class="crm-modal-header">

<div class="crm-modal-title" id="crmModalTitle">
Payment Entry
</div>

<button class="crm-modal-close" onclick="closeCrmModal()">�</button>

</div>

<div class="crm-modal-body" id="crmModalBody">

Loading...

</div>

</div>

</div>

<script>

document.addEventListener("DOMContentLoaded", function(){
if (typeof crmDataTable !== 'function') return;

try {
crmDataTable('#userTable', {
pageLength: 10,
lengthMenu: [5, 10, 20, 50, 100],
ordering: true,
scrollX: false,
responsive: false,
order: [[6, 'desc']],
searchPlaceholder: "Search payments...",
dom:
"<'dt-top'lfB>" +
"rt" +
"<'dt-bottom'ip>"
});

setTimeout(() => {
const controls = document.querySelector('.dt-top');
const target = document.getElementById('datatableControls');

if (controls && target) {
target.appendChild(controls);
}
}, 100);
} catch (e) {}
});

/* ===============================
MODAL FUNCTIONS
=============================== */

function openCrmModal(title){

document.getElementById("crmModalTitle").innerHTML=title;

document.getElementById("crmModalBackdrop").style.display="flex";

}

function closeCrmModal(){

document.getElementById("crmModalBackdrop").style.display="none";

document.getElementById("crmModalBody").innerHTML="Loading...";

}

/* ===============================
OPEN PAYMENT FORM
=============================== */

async function openPaymentModal(regId){

openCrmModal("Payment Entry");

const url = `index.php?page=payments/payment_modal&ajax=1&reg_id=${regId}`;

try{

const res = await fetch(url);

const html = await res.text();

const modalBody = document.getElementById("crmModalBody");
modalBody.innerHTML = html;
if (typeof window.initModernSelect === 'function') {
window.initModernSelect(modalBody);
}

}
catch(e){

document.getElementById("crmModalBody").innerHTML =
"<div style='color:red'>Failed to load payment form</div>";

}

}

</script>



