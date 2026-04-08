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


<style>
:root {
  --primary: #e91e63;
  --primary-light: #f8bbd0;
  --primary-dark: #c2185b;
  --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
  --gray-200: #e9ecef;
  --gray-300: #dee2e6;
  --gray-400: #ced4da;
  --gray-700: #495057;
  --gray-800: #343a40;
  --gray-900: #212529;
  --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
  --radius-sm: 6px;
  --radius-md: 8px;
  --transition: all 0.2s ease;
  --reg-text: #343a40;
  --reg-muted: #6c757d;
  --reg-primary: #e91e63;
  --reg-primary-soft: #fff0f5;
  --reg-shadow-soft: 0 8px 20px rgba(0,0,0,.05);
}

[data-tooltip] {
  position: relative;
  cursor: pointer;
}

[data-tooltip]:before {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(-5px);
  background: var(--gray-800);
  color: white;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  white-space: nowrap;
  z-index: 10;
  opacity: 0;
  visibility: hidden;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
  pointer-events: none;
  font-weight: normal;
  letter-spacing: 0.3px;
}

[data-tooltip]:after {
  content: '';
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(5px);
  border-width: 5px;
  border-style: solid;
  border-color: var(--gray-800) transparent transparent transparent;
  opacity: 0;
  visibility: hidden;
  transition: var(--transition);
  pointer-events: none;
}

[data-tooltip]:hover:before,
[data-tooltip]:hover:after {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}

.payments-page-title {
  margin: 0 0 10px;
  line-height: 1.2;
}

.payments-dashboard {
  max-width: 1400px;
  margin: 0 auto;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 0;
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.dashboard-header h2 {
  margin: 0;
  font-size: 1.18rem;
  font-weight: 600;
  color: var(--gray-800);
}

.header-stats {
  background: #fff;
  padding: 7px 12px;
  border-radius: 40px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
  font-weight: 500;
  font-size: 0.86rem;
}

.stat-item {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--gray-700);
}

.card {
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: visible;
  margin-bottom: 14px;
}

.card-header {
  padding: 9px 12px;
  border-bottom: 1px solid #e9ecef;
  background: #f8f9fa;
  color: var(--gray-800);
  font-weight: 600;
  font-size: 0.92rem;
}

.table-header-flex {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

.table-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.filter-form {
  padding: 10px 12px;
}

.filter-grid {
  display: flex;
  gap: 10px;
  align-items: end;
  flex-wrap: wrap;
}

.filter-item {
  flex: 1 1 170px;
  min-width: 150px;
}

.filter-item label {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  color: #6c757d;
}

.filter-item input,
.filter-item select {
  width: 100%;
  min-height: 0;
  padding: 7px 10px;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  background: #fff;
  color: var(--gray-800);
  outline: none;
  transition: var(--transition);
  font-size: 0.84rem;
}

.filter-item input:focus,
.filter-item select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.12);
}

.filter-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}

.btn-icon-only {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  text-decoration: none;
  border: 1px solid transparent;
  transition: var(--transition);
  cursor: pointer;
}

.btn-icon-only.apply {
  background: var(--primary);
  color: #fff;
}

.btn-icon-only.apply:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.btn-icon-only.reset {
  background: #fff7fb;
  color: var(--primary-dark);
  border-color: #f3cede;
}

.btn-icon-only.reset:hover {
  background: #fff0f6;
}

#datatableControls {
  min-height: 40px;
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

#datatableControls .dt-top {
  display: flex !important;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

#datatableControls .dataTables_length,
#datatableControls .dataTables_filter,
#datatableControls .dt-buttons {
  display: flex;
  align-items: center;
  margin: 0;
}

#datatableControls .dataTables_length {
  flex: 0 0 auto;
}

#datatableControls .dataTables_length label {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  white-space: nowrap;
}

#datatableControls .dataTables_length select {
  min-width: 64px;
  padding: 4px 8px;
}

#datatableControls .dataTables_filter {
  flex: 0 0 auto;
  justify-content: flex-start;
}

#datatableControls .dataTables_filter label {
  width: auto;
  min-width: 170px;
  margin: 0;
}

#datatableControls .dataTables_filter input {
  min-width: 170px;
}

.dt-bottom {
  display: flex !important;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
  flex-wrap: wrap;
  gap: 10px;
  width: 100%;
}

.dt-bottom .dataTables_info {
  margin: 0;
  float: none !important;
  flex: 1 1 auto;
}

.dt-bottom .dataTables_paginate {
  margin: 0 0 0 auto !important;
  float: none !important;
  text-align: right;
  flex: 0 0 auto;
}

.table-container {
  padding: 8px 10px;
  overflow-x: auto;
}

.payments-wrapper {
  padding: 0;
  width: 100%;
  overflow-x: hidden;
}

.crm-right {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow-x: hidden;
}

.crm-card {
  background: #fff;
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 8px 20px rgba(0,0,0,.05);
  border: 1px solid #f1d6e3;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow: hidden;
}

.crm-card h3 {
  margin: 0 0 10px;
  font-size: 20px;
  font-weight: 700;
  color: var(--gray-800);
  display: flex;
  align-items: center;
  gap: 8px;
}

.crm-table-wrapper {
  width: 100%;
  max-width: 100%;
  overflow-x: visible;
  overflow-y: visible;
  box-sizing: border-box;
}

#userTable.crm-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #f1d6e3;
  table-layout: auto;
}

#userTable.crm-table th,
#userTable.crm-table td {
  border: 1px solid #f1d6e3;
  padding: 12px 10px;
  font-size: 13px;
  white-space: normal;
  word-break: break-word;
  vertical-align: middle;
}

#userTable.crm-table th {
  background: #fff0f5;
  font-weight: 600;
  text-align: left;
  color: var(--gray-800);
}

#userTable.crm-table tbody tr:hover {
  background: #fff9fc;
}

.crm-table td:nth-child(1),
.crm-table td:nth-child(2) {
  font-weight: 700;
}

.crm-table td:nth-child(2) {
  color: var(--primary-dark);
}

.crm-table td:nth-child(3),
.crm-table td:nth-child(4),
.crm-table td:nth-child(5) {
  font-weight: 700;
  color: var(--gray-900);
}

.pay-student {
  font-weight: 700;
  color: var(--gray-900);
}

.pay-reg {
  font-weight: 700;
  color: var(--primary-dark);
}

.pay-money {
  font-weight: 700;
  color: var(--gray-900);
}

.status-paid,
.status-partial,
.status-unpaid {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 84px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  border: 1px solid transparent;
}

.status-paid {
  background: #eaf7ee;
  color: #2e7d32;
  border-color: rgba(46,125,50,.18);
}

.status-partial {
  background: #fff8e8;
  color: #fb8c00;
  border-color: rgba(251,140,0,.18);
}

.status-unpaid {
  background: #fdecec;
  color: #d32f2f;
  border-color: rgba(211,47,47,.18);
}

.pay-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-add-payment {
  background: var(--primary);
  color: #fff;
  border: none;
  width: 34px;
  height: 34px;
  padding: 0;
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  line-height: 1;
}

.btn-add-payment:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: var(--transition);
  gap: 0;
  padding: 0;
  font-size: 13px;
  font-weight: 600;
  line-height: 1;
}

.btn-view {
  background: #e8f4fd;
  color: #1565c0;
}

.btn-view:hover {
  background: #d9edf9;
}

.btn-download {
  background: #eaf7ee;
  color: #2e7d32;
}

.btn-download:hover {
  background: #dff2e6;
}

.btn-icon i,
.btn-add-payment i {
  font-size: 11px;
}

.btn-download-all {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  padding: 0;
  border-radius: 8px;
  text-decoration: none;
  background: #2e7d32;
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  border: none;
  transition: var(--transition);
}

.btn-download-all:hover {
  background: #256628;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
  margin-bottom: 8px;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dt-buttons {
  display: flex;
  align-items: center;
  box-sizing: border-box;
}

.crm-table-header,
.crm-table-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  width: 100%;
  box-sizing: border-box;
}

.crm-table-header {
  margin-bottom: 10px;
}

.crm-table-footer {
  margin-top: 10px;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
  font-size: 13px;
  color: var(--gray-700);
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.dataTables_wrapper {
  width: 100%;
  overflow-x: auto;
  /*overflow-y: hidden;*/
}

.dataTables_wrapper .dataTables_scroll,
.dataTables_wrapper .dataTables_scrollHead,
.dataTables_wrapper .dataTables_scrollHeadInner,
.dataTables_wrapper .dataTables_scrollBody {
  width: 100% !important;
  max-width: 100% !important;
}

.dataTables_wrapper .dataTables_scrollBody {
  overflow-x: auto !important;
}

.dataTables_wrapper .dataTables_length {
  flex: 0 0 auto;
}

.dataTables_wrapper .dataTables_filter {
  flex: 1 1 280px;
  justify-content: center;
  min-width: 0;
}

.dataTables_wrapper .dt-buttons {
  flex: 0 0 auto;
  margin-left: auto;
}

.dataTables_wrapper .dataTables_length label,
.dataTables_wrapper .dataTables_filter label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  color: var(--gray-800);
}

.dataTables_wrapper .dataTables_filter label {
  min-width: 0;
  width: min(100%, 320px);
  position: relative;
}

.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-md);
  padding: 8px 12px;
  background: #fff;
  outline: none;
}

.dataTables_wrapper .dataTables_filter input:focus,
.dataTables_wrapper .dataTables_length select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-light);
}

.dataTables_wrapper .dataTables_filter label:before {
  content: '\f002';
  font-family: 'Font Awesome 6 Free';
  font-weight: 900;
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  pointer-events: none;
}

.dataTables_wrapper .dataTables_filter input {
  min-width: 0;
  width: 100% !important;
  padding-left: 38px;
}

.crm-export-btn {
  background: var(--primary) !important;
  color: #fff !important;
  border: none !important;
  padding: 10px 16px !important;
  border-radius: var(--radius-md) !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  box-shadow: none !important;
}

.crm-export-btn:hover {
  background: var(--primary-dark) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  border: 1px solid #f1d6e3 !important;
  background: #fff !important;
  border-radius: 8px !important;
  color: var(--gray-700) !important;
  padding: 6px 12px !important;
  margin-left: 4px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
  background: var(--primary) !important;
  color: #fff !important;
  border-color: var(--primary) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  background: #fff5f9 !important;
  color: var(--primary-dark) !important;
  border-color: #f1d6e3 !important;
}

.crm-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(5px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    z-index: 99999;
  }

  .crm-modal {
    width: min(1180px, 96vw);
    background: #fff;
    border-radius: 24px;
    border: 1px solid rgba(0, 0, 0, .08);
    box-shadow: 0 20px 70px rgba(0, 0, 0, .22);
    overflow: hidden;
  }

  .crm-modal-header {
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #edf1f5;
    background: linear-gradient(180deg, #fff 0%, #fffafe 100%);
  }

  .crm-modal-title {
    font-weight: 900;
    font-size: 20px;
    color: var(--reg-text);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .crm-modal-close {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    border: 1px solid rgba(0, 0, 0, .08);
    background: #fff;
    cursor: pointer;
    font-size: 20px;
    transition: .2s ease;
  }

  .crm-modal-close:hover {
    background: #f8fafc;
  }

  .crm-modal-body {
    padding: 20px;
    max-height: 82vh;
    overflow: auto;
    background: #fbfcff;
  }

  .pro-modal-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .pro-modal-section {
    border: 1px solid #edf1f5;
    border-radius: 20px;
    padding: 18px;
    background: #fff;
    box-shadow: var(--reg-shadow-soft);
  }

  .pro-modal-head {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
  }

  .pro-modal-head-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: var(--reg-primary-soft);
    color: var(--reg-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .pro-modal-title {
    font-size: 17px;
    font-weight: 900;
    color: var(--reg-text);
    line-height: 1.3;
  }

  .pro-modal-subtitle {
    font-size: 13px;
    color: var(--reg-muted);
    margin-top: 4px;
  }

  .pro-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
  }

  .pro-info-card {
    border: 1px solid #eef2f6;
    border-radius: 16px;
    padding: 14px;
    background: linear-gradient(180deg, #fff, #fcfdff);
  }

  .pro-info-card .label,
  .pro-pay-card .label {
    display: block;
    font-size: 12px;
    font-weight: 800;
    color: var(--reg-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
  }

  .pro-info-card .value,
  .pro-pay-card .value {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: var(--reg-text);
    line-height: 1.5;
    word-break: break-word;
  }

  .pro-note-box {
    margin-top: 14px;
    padding: 14px 16px;
    border-radius: 16px;
    background: #fff7fb;
    border: 1px solid #f5d6e3;
    color: #4b5563;
    line-height: 1.7;
  }

  .pro-note-title {
    font-size: 13px;
    font-weight: 900;
    color: #9d174d;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .pro-timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .pro-timeline-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }

  .pro-timeline-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--reg-primary);
    margin-top: 8px;
    box-shadow: 0 0 0 5px rgba(233, 30, 99, .12);
    flex-shrink: 0;
  }

  .pro-timeline-content {
    flex: 1;
    border: 1px solid #eef2f6;
    border-radius: 16px;
    padding: 14px;
    background: #fff;
  }

  .pro-timeline-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--reg-text);
  }

  .pro-mini-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #fce7f3;
    color: #9d174d;
    font-size: 11px;
    font-weight: 800;
    margin-left: 8px;
  }

  .pro-timeline-sub {
    font-size: 12px;
    color: var(--reg-muted);
    margin-top: 6px;
  }

  .pro-timeline-note {
    margin-top: 10px;
    color: #374151;
    line-height: 1.7;
  }

  .pro-table-scroll {
    overflow: auto;
  }

  .pro-mini-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
  }

  .pro-mini-table th,
  .pro-mini-table td {
    padding: 12px 12px;
    border-bottom: 1px solid #edf1f5;
    text-align: left;
    font-size: 13px;
  }

  .pro-mini-table th {
    background: #fafbfe;
    font-weight: 900;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: .4px;
    font-size: 12px;
  }

  .receipt-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid #ddd;
    text-decoration: none;
    color: #333;
    background: #fff;
    font-weight: 800;
    transition: .2s ease;
  }

  .receipt-link:hover {
    background: #f7f7f7;
  }

  .pro-payment-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
  }

  .pro-pay-card {
    border: 1px solid #f1d9e4;
    background: #fff;
    border-radius: 16px;
    padding: 14px;
  }

  .pro-pay-card.highlight {
    background: linear-gradient(135deg, #fff7fb, #fff);
    border-color: #f5cfe0;
  }

  .pro-payment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .pro-payment-grid .full {
    grid-column: 1 / -1;
  }

  .m-label {
    display: block;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 6px;
    color: var(--reg-text);
  }

  .m-input {
    width: 100%;
    min-height: 46px;
    padding: 10px 12px;
    border: 1px solid #dfe5ec;
    border-radius: 12px;
    background: #fff;
    outline: none;
    transition: .2s ease;
  }

  .native-modern-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    padding-right: 42px;
    background-color: #fff;
    background-image:
      linear-gradient(45deg, transparent 50%, #be185d 50%),
      linear-gradient(135deg, #be185d 50%, transparent 50%);
    background-position:
      calc(100% - 20px) calc(50% - 3px),
      calc(100% - 14px) calc(50% - 3px);
    background-size: 7px 7px, 7px 7px;
    background-repeat: no-repeat;
    cursor: pointer;
  }

  .native-modern-select:focus {
    border-color: #e91e63;
    box-shadow: 0 0 0 4px rgba(233, 30, 99, .12);
  }

  .modal-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
  }

  .empty-note {
    color: var(--reg-muted);
    padding: 12px 0;
  }

  .error-note {
    color: #d32f2f;
  }

  @media (max-width: 1200px) {
    .reg-toolbar-item.actions {
      margin-left: 0;
    }
  }

  @media (max-width: 900px) {
    .filter-grid {
      grid-template-columns: 1fr 1fr;
    }

    .filter-actions {
      justify-content: flex-start;
    }

    .pro-payment-grid {
      grid-template-columns: 1fr;
    }

    .reg-page {
      padding: 12px;
      border-radius: 18px;
    }

    .pro-card-body,
    .crm-modal-body {
      padding: 14px;
    }

    .pro-card-head,
    .crm-modal-header {
      padding: 16px;
    }

    .reg-toolbar-form {
      gap: 10px;
    }

    .reg-toolbar-item {
      min-width: calc(50% - 8px);
      flex: 1 1 calc(50% - 8px);
    }

    .reg-toolbar-item.search,
    .reg-toolbar-item.actions {
      min-width: 100%;
      flex: 1 1 100%;
    }
  }

  @media (max-width: 576px) {
    .dashboard-header h2 {
      font-size: 24px;
    }

    .table-header-flex {
      align-items: stretch;
    }

    #datatableControls {
      justify-content: flex-start;
    }

    #datatableControls .dt-top,
    .dt-bottom {
      justify-content: flex-start;
    }

    .dt-bottom .dataTables_paginate {
      margin-left: 0 !important;
      text-align: left;
    }

    .filter-grid {
      grid-template-columns: 1fr;
    }

    .filter-actions {
      width: 100%;
    }

    .reg-page-title h2 {
      font-size: 22px;
    }

    .reg-toolbar-item {
      min-width: 100%;
      flex: 1 1 100%;
    }

    .reg-toolbar-item.actions {
      flex-direction: column;
      align-items: stretch;
    }

    .pro-btn {
      width: 100%;
    }
  }

@media(max-width:768px){
  .filter-grid{
    display:flex !important;
    flex-direction:column !important;
    align-items:stretch !important;
    gap:12px !important;
  }

  .filter-item{
    flex:0 0 auto !important;
    min-width:0 !important;
    width:100% !important;
  }

  .filter-actions{
    display:grid !important;
    grid-template-columns:repeat(3, minmax(0,1fr));
    gap:8px !important;
    width:100% !important;
    margin-left:0 !important;
    align-items:stretch !important;
  }

  .btn-download-all{
    width:100% !important;
    min-height:40px !important;
    justify-content:center !important;
    border-radius:10px !important;
    padding:6px 8px !important;
    font-size:12px !important;
  }

  .btn-download-all[data-mobile-label]{
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

  .btn-download-all[data-mobile-label]::after{
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

  .filter-actions .btn-icon-only{
    width:100% !important;
    min-width:0 !important;
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

  .crm-table-header,
  .crm-table-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dt-buttons {
    flex: 1 1 100%;
    width: 100%;
    margin-left: 0;
  }

  .crm-table-wrapper{
    width:100%;
    overflow-x: visible;
    -webkit-overflow-scrolling: touch;
  }

  #userTable.crm-table{
    min-width: 0;
  }

  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dt-buttons {
    width:100%;
    display:flex;
    justify-content:center;
    margin-bottom:10px;
  }

  .dataTables_wrapper .dataTables_length label,
  .dataTables_wrapper .dataTables_filter label {
    width: 100%;
  }

  .dataTables_wrapper .dataTables_filter input{
    width:100% !important;
    max-width:100%;
    min-width:0;
  }

  .dataTables_wrapper .dataTables_paginate{
    display:flex;
    justify-content:center;
    margin-top:10px;
  }

  .crm-export-btn {
    width: 100% !important;
  }

  #userTable.crm-table th,
  #userTable.crm-table td {
    padding: 10px 8px;
    font-size: 12px;
  }

  .pay-action {
    gap: 4px;
    display:flex !important;
    flex-wrap:nowrap !important;
    justify-content:flex-end !important;
    align-items:center !important;
  }

  .btn-add-payment {
    padding: 8px 12px;
    font-size: 12px;
  }

  .pay-action .btn-add-payment[data-mobile-label],
  .pay-action .btn-icon[data-mobile-label]{
    width:auto !important;
    min-width:54px !important;
    height:auto !important;
    min-height:36px !important;
    padding:5px 6px !important;
    display:inline-flex !important;
    flex-direction:column !important;
    align-items:center !important;
    justify-content:center !important;
    gap:2px !important;
    border-radius:10px !important;
    line-height:1 !important;
  }

  .pay-action .btn-add-payment[data-mobile-label]::after,
  .pay-action .btn-icon[data-mobile-label]::after{
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
    text-align:center !important;
    line-height:1.1 !important;
    font-weight:700 !important;
    letter-spacing:.1px !important;
    color:currentColor !important;
    white-space:nowrap !important;
  }

  .crm-card {
    padding: 12px;
  }

  #userTable.crm-table tbody td::before{
    flex:0 0 34% !important;
    max-width:34% !important;
  }

  #userTable.crm-table tbody td .crm-card-value{
    max-width:66% !important;
    min-width:0 !important;
    overflow-wrap:break-word !important;
    word-break:normal !important;
    white-space:normal !important;
  }

  #userTable.crm-table tbody td .crm-card-value .pay-name,
  #userTable.crm-table tbody td .crm-card-value .pay-reg,
  #userTable.crm-table tbody td .crm-card-value .pay-money{
    word-break:normal !important;
    overflow-wrap:normal !important;
    white-space:normal !important;
  }
}

/* Force lead/list-like card mode for payments on small screens */
@media (max-width: 900px){
  #userTable.crm-table{
    width:100% !important;
    min-width:0 !important;
    border-collapse:separate !important;
    border-spacing:0 !important;
  }

  #userTable.crm-table thead{
    display:none !important;
  }

  #userTable.crm-table tbody{
    display:block !important;
    width:100% !important;
  }

  #userTable.crm-table tbody tr{
    display:block !important;
    background:#fff !important;
    border:1px solid #f0d6e2 !important;
    border-radius:12px !important;
    margin:0 0 12px 0 !important;
    overflow:hidden !important;
  }

  #userTable.crm-table tbody td{
    display:flex !important;
    align-items:flex-start !important;
    justify-content:space-between !important;
    gap:10px !important;
    width:100% !important;
    text-align:right !important;
    padding:10px 12px !important;
    border-bottom:1px solid #f4e5ec !important;
    white-space:normal !important;
    word-break:normal !important;
    overflow-wrap:break-word !important;
  }

  #userTable.crm-table tbody td:last-child{
    border-bottom:none !important;
  }

  #userTable.crm-table tbody td::before{
    content:attr(data-label) !important;
    display:block !important;
    flex:0 0 42% !important;
    max-width:42% !important;
    font-size:11px !important;
    line-height:1.35 !important;
    font-weight:700 !important;
    letter-spacing:.25px !important;
    text-transform:uppercase !important;
    color:#7a6772 !important;
    text-align:left !important;
  }

  #userTable.crm-table tbody td .crm-card-value{
    margin-left:auto !important;
    display:flex !important;
    flex-direction:column !important;
    align-items:flex-end !important;
    justify-content:center !important;
    gap:2px !important;
    min-width:0 !important;
    max-width:58% !important;
    text-align:right !important;
  }

  #userTable.crm-table tbody td .crm-card-value > *{
    max-width:100% !important;
    word-break:normal !important;
    overflow-wrap:break-word !important;
    white-space:normal !important;
  }

  #userTable.crm-table tbody td .pay-action{
    display:flex !important;
    flex-wrap:nowrap !important;
    justify-content:flex-end !important;
    align-items:center !important;
    gap:4px !important;
  }
}

@media (hover: none), (pointer: coarse){
  .pay-action{
    display:flex !important;
    flex-wrap:nowrap !important;
    justify-content:flex-end !important;
    align-items:center !important;
    gap:4px !important;
  }

  .btn-download-all[data-mobile-label]{
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

  .btn-download-all[data-mobile-label]::after{
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

  .pay-action .btn-add-payment[data-mobile-label],
  .pay-action .btn-icon[data-mobile-label]{
    width:auto !important;
    min-width:54px !important;
    height:auto !important;
    min-height:36px !important;
    padding:5px 6px !important;
    display:inline-flex !important;
    flex-direction:column !important;
    align-items:center !important;
    justify-content:center !important;
    gap:2px !important;
    border-radius:10px !important;
    line-height:1 !important;
  }

  .pay-action .btn-add-payment[data-mobile-label]::after,
  .pay-action .btn-icon[data-mobile-label]::after{
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
    text-align:center !important;
    line-height:1.1 !important;
    font-weight:700 !important;
    letter-spacing:.1px !important;
    color:currentColor !important;
    white-space:nowrap !important;
  }

  #userTable.crm-table tbody td::before{
    flex:0 0 34% !important;
    max-width:34% !important;
  }

  #userTable.crm-table tbody td .crm-card-value{
    max-width:66% !important;
    min-width:0 !important;
    overflow-wrap:break-word !important;
    word-break:normal !important;
    white-space:normal !important;
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
<i class="fas fa-file-excel"></i>
</a>
<?php endif; ?>
<button type="submit" class="btn-icon-only apply" title="Apply filters" data-mobile-label="Apply">
<i class="fas fa-filter"></i>
</button>
<a href="index.php?page=payments/index" class="btn-icon-only reset" title="Reset filters" data-mobile-label="Reset">
<i class="fas fa-undo-alt"></i>
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

<td>?<?= number_format($p['final_fee'] ?? 0,2) ?></td>

<td>?<?= number_format($p['total_paid'] ?? 0,2) ?></td>

<td>?<?= number_format($p['balance_amount'] ?? 0,2) ?></td>

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
<i class="fas fa-plus"></i>
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

<i class="fas fa-file-excel"></i>

</a>
<?php endif; ?>

<a
class="btn-icon btn-view"
data-mobile-label="View"
href="index.php?page=students/profile&id=<?= $p['id'] ?>"
title="Student Profile"
data-tooltip="Student profile">

<i class="fas fa-eye"></i>

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
