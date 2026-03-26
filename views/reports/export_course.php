<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

/* ===============================
   NEW MODIFICATION DONE ON 2026-03-23
   Switch course overall export to organized Excel workbook
=============================== */

if (!function_exists('courseRequireSpreadsheet')) {
    function courseRequireSpreadsheet(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        }
    }
}

if (!function_exists('courseApplyExportHeaderStyle')) {
    function courseApplyExportHeaderStyle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
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

if (!function_exists('courseAutosizeColumns')) {
    function courseAutosizeColumns(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $columns): void
    {
        foreach ($columns as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}

if (!function_exists('courseStreamSpreadsheet')) {
    function courseStreamSpreadsheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $fileName): void
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

$roleId = (int)($_SESSION['role_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$roleName = trim((string) ($_SESSION['role_name'] ?? ''));

$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$program   = $_GET['program'] ?? '';
$status    = $_GET['payment_status'] ?? '';
$staffId   = (int)($_GET['staff_id'] ?? 0);

$formatDate = static function ($value): string {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime((string)$value);
    return $timestamp ? date('Y-m-d', $timestamp) : (string)$value;
};

$formatStatus = static function ($value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    return ucwords(str_replace('_', ' ', $value));
};

$where = ["r.reg_type = 'course'"];
$params = [];

$fullAccessRoles = [1, 3, 6]; // Super Admin, HR, Marketing
if (!in_array($roleId, $fullAccessRoles, true)) {
    if ($roleName === 'Staff') {
        $where[] = "rc.guide_staff_id = ?";
        $params[] = $userId;
    } else {
        $where[] = "r.assigned_to = ?";
        $params[] = $userId;
    }
}

if ($date_from !== '') {
    $where[] = "r.joined_on >= ?";
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where[] = "r.joined_on <= ?";
    $params[] = $date_to;
}

if ($program !== '') {
    $where[] = "r.program_name = ?";
    $params[] = $program;
}

if ($status !== '') {
    $where[] = "r.payment_status = ?";
    $params[] = $status;
}

if (in_array($roleId, [1, 3], true) && $staffId > 0) {
    $where[] = "rc.guide_staff_id = ?";
    $params[] = $staffId;
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$sql = "
SELECT
    r.id,
    r.registration_no,
    r.enquiry_snapshot_name,
    r.enquiry_snapshot_phone,
    r.enquiry_snapshot_email,
    r.program_name,
    r.batch_name,
    r.joined_on,
    r.total_fee,
    r.paid_amount,
    r.balance_amount,
    r.payment_status,
    COALESCE(u.name, '-') AS assigned_staff_name,
    COALESCE(att.present_days, 0) AS present_days,
    COALESCE(att.absent_days, 0) AS absent_days,
    COALESCE(att.late_days, 0) AS late_days,
    a.assessment_1,
    a.assessment_2,
    a.assessment_3,
    a.average_marks AS assessment_average,
    m.theoretical_marks,
    m.machine_task_marks,
    m.mock_average,
    m.workflow_status AS mock_workflow_status,
    shi.company_name AS hr_company_name,
    shi.interview_date AS hr_interview_date,
    shi.interview_status AS hr_interview_status
FROM registrations r
LEFT JOIN registration_courses rc ON rc.registration_id = r.id
LEFT JOIN users u ON u.id = rc.guide_staff_id
LEFT JOIN (
    SELECT
        registration_id,
        SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present_days,
        SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) AS absent_days,
        SUM(CASE WHEN LOWER(status) = 'late' THEN 1 ELSE 0 END) AS late_days
    FROM attendance
    GROUP BY registration_id
) att ON att.registration_id = r.id
LEFT JOIN assessment a ON a.registration_id = r.id
LEFT JOIN mock_interviews m ON m.registration_id = r.id
LEFT JOIN student_hr_interviews shi ON shi.registration_id = r.id
$whereSql
ORDER BY r.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

courseRequireSpreadsheet();

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$fileName = 'course_report_' . date('Y-m-d_His') . '.xlsx';

$summarySheet = $spreadsheet->getActiveSheet();
$summarySheet->setTitle('Summary');

$summaryHeaders = [
    'A1' => 'S.No',
    'B1' => 'Student Name',
    'C1' => 'Registration No',
    'D1' => 'Phone',
    'E1' => 'Email',
    'F1' => 'Program',
    'G1' => 'Batch',
    'H1' => 'Assigned Staff',
    'I1' => 'Join Date',
    'J1' => 'Total Fee',
    'K1' => 'Paid Amount',
    'L1' => 'Balance',
    'M1' => 'Payment Status',
    'N1' => 'Attendance %',
    'O1' => 'Present Days',
    'P1' => 'Absent Days',
    'Q1' => 'Late Days',
];

foreach ($summaryHeaders as $cell => $label) {
    $summarySheet->setCellValue($cell, $label);
}
courseApplyExportHeaderStyle($summarySheet, 'A1:Q1');

$summaryRow = 2;
foreach ($rows as $index => $row) {
    $presentDays = (int)($row['present_days'] ?? 0);
    $absentDays = (int)($row['absent_days'] ?? 0);
    $lateDays = (int)($row['late_days'] ?? 0);
    $trackedDays = $presentDays + $absentDays + $lateDays;
    $attendancePercent = $trackedDays > 0 ? round(($presentDays / $trackedDays) * 100, 2) : 0;

    $summarySheet->setCellValue("A{$summaryRow}", $index + 1);
    $summarySheet->setCellValue("B{$summaryRow}", trim((string)($row['enquiry_snapshot_name'] ?? '')));
    $summarySheet->setCellValue("C{$summaryRow}", trim((string)($row['registration_no'] ?? '')));
    $summarySheet->setCellValue("D{$summaryRow}", trim((string)($row['enquiry_snapshot_phone'] ?? '')));
    $summarySheet->setCellValue("E{$summaryRow}", trim((string)($row['enquiry_snapshot_email'] ?? '')));
    $summarySheet->setCellValue("F{$summaryRow}", trim((string)($row['program_name'] ?? '')));
    $summarySheet->setCellValue("G{$summaryRow}", trim((string)($row['batch_name'] ?? '')));
    $summarySheet->setCellValue("H{$summaryRow}", (string)(($row['assigned_staff_name'] ?? '') !== '' ? $row['assigned_staff_name'] : '-'));
    $summarySheet->setCellValue("I{$summaryRow}", $formatDate($row['joined_on'] ?? ''));
    $summarySheet->setCellValue("J{$summaryRow}", (float)($row['total_fee'] ?? 0));
    $summarySheet->setCellValue("K{$summaryRow}", (float)($row['paid_amount'] ?? 0));
    $summarySheet->setCellValue("L{$summaryRow}", (float)($row['balance_amount'] ?? 0));
    $summarySheet->setCellValue("M{$summaryRow}", $formatStatus($row['payment_status'] ?? ''));
    $summarySheet->setCellValue("N{$summaryRow}", $attendancePercent / 100);
    $summarySheet->setCellValue("O{$summaryRow}", $presentDays);
    $summarySheet->setCellValue("P{$summaryRow}", $absentDays);
    $summarySheet->setCellValue("Q{$summaryRow}", $lateDays);
    $summaryRow++;
}

$summarySheet->freezePane('A2');
$summarySheet->getStyle("J2:L{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0.00');
$summarySheet->getStyle("N2:N{$summaryRow}")->getNumberFormat()->setFormatCode('0.00%');
courseAutosizeColumns($summarySheet, range('A', 'Q'));

$detailSheet = $spreadsheet->createSheet();
$detailSheet->setTitle('Academic & HR');

$detailHeaders = [
    'A1' => 'S.No',
    'B1' => 'Student Name',
    'C1' => 'Registration No',
    'D1' => 'Program',
    'E1' => 'Assigned Staff',
    'F1' => 'Assessment 1',
    'G1' => 'Assessment 2',
    'H1' => 'Assessment 3',
    'I1' => 'Assessment Avg',
    'J1' => 'Mock Theory',
    'K1' => 'Mock Machine Task',
    'L1' => 'Mock Avg',
    'M1' => 'Mock Workflow',
    'N1' => 'HR Status',
    'O1' => 'Company Name',
    'P1' => 'Interview Date',
];

foreach ($detailHeaders as $cell => $label) {
    $detailSheet->setCellValue($cell, $label);
}
courseApplyExportHeaderStyle($detailSheet, 'A1:P1');

$detailRow = 2;
foreach ($rows as $index => $row) {
    $detailSheet->setCellValue("A{$detailRow}", $index + 1);
    $detailSheet->setCellValue("B{$detailRow}", trim((string)($row['enquiry_snapshot_name'] ?? '')));
    $detailSheet->setCellValue("C{$detailRow}", trim((string)($row['registration_no'] ?? '')));
    $detailSheet->setCellValue("D{$detailRow}", trim((string)($row['program_name'] ?? '')));
    $detailSheet->setCellValue("E{$detailRow}", (string)(($row['assigned_staff_name'] ?? '') !== '' ? $row['assigned_staff_name'] : '-'));
    $detailSheet->setCellValue("F{$detailRow}", trim((string)($row['assessment_1'] ?? '')));
    $detailSheet->setCellValue("G{$detailRow}", trim((string)($row['assessment_2'] ?? '')));
    $detailSheet->setCellValue("H{$detailRow}", trim((string)($row['assessment_3'] ?? '')));
    $detailSheet->setCellValue("I{$detailRow}", trim((string)($row['assessment_average'] ?? '')));
    $detailSheet->setCellValue("J{$detailRow}", trim((string)($row['theoretical_marks'] ?? '')));
    $detailSheet->setCellValue("K{$detailRow}", trim((string)($row['machine_task_marks'] ?? '')));
    $detailSheet->setCellValue("L{$detailRow}", trim((string)($row['mock_average'] ?? '')));
    $detailSheet->setCellValue("M{$detailRow}", $formatStatus($row['mock_workflow_status'] ?? ''));
    $detailSheet->setCellValue("N{$detailRow}", $formatStatus($row['hr_interview_status'] ?? ''));
    $detailSheet->setCellValue("O{$detailRow}", trim((string)($row['hr_company_name'] ?? '')));
    $detailSheet->setCellValue("P{$detailRow}", $formatDate($row['hr_interview_date'] ?? ''));
    $detailRow++;
}

$detailSheet->freezePane('A2');
courseAutosizeColumns($detailSheet, range('A', 'P'));

$spreadsheet->setActiveSheetIndex(0);
courseStreamSpreadsheet($spreadsheet, $fileName);
