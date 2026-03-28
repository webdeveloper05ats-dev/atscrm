<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

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
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'wrapText' => true,
            ],
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

if (!function_exists('courseBuildSheet')) {
    function courseBuildSheet(
        \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet,
        string $title,
        array $headers,
        array $rows,
        array $currencyColumns = [],
        array $percentColumns = []
    ): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet {
        $sheet = $spreadsheet->getSheetCount() === 0
            ? $spreadsheet->getActiveSheet()
            : $spreadsheet->createSheet();

        $sheet->setTitle($title);

        $colIndex = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $header);
            $colIndex++;
        }

        $rowIndex = 2;
        foreach ($rows as $row) {
            $colIndex = 1;
            foreach ($row as $value) {
                $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $value);
                $colIndex++;
            }
            $rowIndex++;
        }

        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($headers)));
        courseApplyExportHeaderStyle($sheet, 'A1:' . $lastColumn . '1');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:' . $lastColumn . max(2, $rowIndex - 1))->getAlignment()->setVertical(
            \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
        );
        $sheet->getStyle('A1:' . $lastColumn . max(2, $rowIndex - 1))->getAlignment()->setWrapText(true);

        foreach ($currencyColumns as $column) {
            $sheet->getStyle($column . '2:' . $column . max(2, $rowIndex - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        foreach ($percentColumns as $column) {
            $sheet->getStyle($column . '2:' . $column . max(2, $rowIndex - 1))
                ->getNumberFormat()
                ->setFormatCode('0.00%');
        }

        courseAutosizeColumns($sheet, range('A', $lastColumn));
        return $sheet;
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

if (!function_exists('courseFormatDate')) {
    function courseFormatDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : $value;
    }
}

if (!function_exists('courseFormatDateTime')) {
    function courseFormatDateTime($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d H:i', $timestamp) : $value;
    }
}

if (!function_exists('courseFormatStatus')) {
    function courseFormatStatus($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        return ucwords(str_replace('_', ' ', $value));
    }
}

if (!function_exists('courseText')) {
    function courseText($value, string $fallback = '-'): string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('courseFloat')) {
    function courseFloat($value): float
    {
        return (float) ($value ?? 0);
    }
}

if (!function_exists('courseMaskPersonalValue')) {
    function courseMaskPersonalValue(bool $canViewPersonal, $value, string $fallback = '-'): string
    {
        if (!$canViewPersonal) {
            return 'Restricted';
        }

        return courseText($value, $fallback);
    }
}

if (!function_exists('courseLatestRowByRegistration')) {
    function courseLatestRowByRegistration(array $rows, string $dateField, string $fallbackField = ''): array
    {
        $latest = [];
        foreach ($rows as $row) {
            $registrationId = (int) ($row['registration_id'] ?? 0);
            if ($registrationId <= 0) {
                continue;
            }

            $candidate = trim((string) ($row[$dateField] ?? ''));
            if ($candidate === '' && $fallbackField !== '') {
                $candidate = trim((string) ($row[$fallbackField] ?? ''));
            }

            $candidateKey = $candidate !== '' ? $candidate : '0000-00-00 00:00:00';
            $existingKey = $latest[$registrationId]['__sort_key'] ?? '';

            if (!isset($latest[$registrationId]) || strcmp($candidateKey, $existingKey) >= 0) {
                $row['__sort_key'] = $candidateKey;
                $latest[$registrationId] = $row;
            }
        }

        foreach ($latest as $registrationId => $row) {
            unset($row['__sort_key']);
            $latest[$registrationId] = $row;
        }

        return $latest;
    }
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleName = trim((string) ($_SESSION['role_name'] ?? ''));
$branchId = (int) ($_SESSION['branch_id'] ?? 0);

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$program = trim((string) ($_GET['program'] ?? ''));
$paymentStatus = trim((string) ($_GET['payment_status'] ?? ''));
$staffId = (int) ($_GET['staff_id'] ?? 0);

$canAccessAllBranches = false;
try {
    $scopeStmt = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id = ? LIMIT 1");
    $scopeStmt->execute([$roleId]);
    $canAccessAllBranches = (int) ($scopeStmt->fetchColumn() ?? 0) === 1;
} catch (Exception $e) {
    $canAccessAllBranches = false;
}

$normalizedRoleName = strtolower($roleName);
$canViewPersonalDetails = in_array($normalizedRoleName, ['super admin', 'hr'], true) || $roleId === 1;

$where = ["r.reg_type = 'course'"];
$params = [];

$fullAccessRoles = [1, 3, 6]; // Super Admin, HR, Marketing
if (!in_array($roleId, $fullAccessRoles, true)) {
    if ($roleName === 'Staff') {
        $where[] = "rc.guide_staff_id = ?";
        $params[] = $userId;
    } else {
        $where[] = "(r.assigned_to = ? OR (COALESCE(r.source_type, '') = 'direct' AND r.created_by = ?))";
        $params[] = $userId;
        $params[] = $userId;
    }
}

if (!$canAccessAllBranches && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($dateFrom !== '') {
    $where[] = "r.joined_on >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "r.joined_on <= ?";
    $params[] = $dateTo;
}

if ($program !== '') {
    $where[] = "r.program_name = ?";
    $params[] = $program;
}

if ($paymentStatus !== '') {
    $where[] = "r.payment_status = ?";
    $params[] = $paymentStatus;
}

if (in_array($roleId, [1, 3], true) && $staffId > 0) {
    $where[] = "rc.guide_staff_id = ?";
    $params[] = $staffId;
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

$baseSql = "
    SELECT
        r.id,
        r.registration_no,
        r.branch_id,
        r.enquiry_id,
        r.source_type,
        r.joined_on,
        r.enquiry_snapshot_name,
        r.enquiry_snapshot_phone,
        r.enquiry_snapshot_email,
        r.program_name,
        r.batch_name,
        r.total_fee,
        r.discount_amount,
        r.final_fee,
        r.paid_amount,
        r.balance_amount,
        r.payment_status,
        r.notes,
        r.registration_status,
        r.created_at,
        r.updated_at,
        r.assigned_to,
        COALESCE(owner.name, '-') AS owner_name,
        COALESCE(creator.name, '-') AS created_by_name,
        COALESCE(rc.guide_staff_id, 0) AS guide_staff_id,
        COALESCE(staff.name, '-') AS guide_staff_name,
        rc.assigned_at AS guide_assigned_at,
        rp.student_name,
        rp.gender,
        rp.dob,
        rp.address,
        rp.qualification,
        rp.college_name,
        rp.year_of_passout,
        rp.parent_name,
        rp.parent_phone,
        rp.parent_occupation,
        rp.emergency_contact,
        rp.aadhaar_no,
        rp.remarks AS profile_remarks,
        a.assessment_1,
        a.assessment_2,
        a.assessment_3,
        a.average_marks AS assessment_average,
        m.theoretical_marks,
        m.machine_task_marks,
        m.mock_average,
        m.workflow_status AS mock_workflow_status,
        m.completed_at AS mock_completed_at,
        shi.company_name AS hr_company_name,
        shi.interview_date AS hr_interview_date,
        shi.interview_status AS hr_interview_status,
        shi.sent_to_hr_at,
        approver.name AS hr_updated_by_name
    FROM registrations r
    LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
    LEFT JOIN registration_courses rc ON rc.registration_id = r.id
    LEFT JOIN users staff ON staff.id = rc.guide_staff_id
    LEFT JOIN users owner ON owner.id = r.assigned_to
    LEFT JOIN users creator ON creator.id = r.created_by
    LEFT JOIN assessment a ON a.registration_id = r.id
    LEFT JOIN mock_interviews m ON m.registration_id = r.id
    LEFT JOIN student_hr_interviews shi ON shi.registration_id = r.id
    LEFT JOIN users approver ON approver.id = shi.hr_updated_by
    $whereSql
    ORDER BY r.created_at DESC, r.id DESC
";

$stmt = $pdo->prepare($baseSql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$registrationIds = array_values(array_unique(array_map(static function ($row) {
    return (int) ($row['id'] ?? 0);
}, $rows)));
$registrationIds = array_values(array_filter($registrationIds));

$attendanceByRegistration = [];
$paymentsByRegistration = [];
$placementsByRegistration = [];
$attendanceSummaryByRegistration = [];
$latestPlacementByRegistration = [];

if (!empty($registrationIds)) {
    $placeholders = implode(',', array_fill(0, count($registrationIds), '?'));

    $attendanceSql = "
        SELECT registration_id, attendance_date, status, topics_taught, task_given, absent_informed, absent_reason, absent_informed_by, marked_by, created_at, updated_at
        FROM attendance
        WHERE registration_id IN ($placeholders)
        ORDER BY registration_id ASC, attendance_date ASC, id ASC
    ";
    $attendanceStmt = $pdo->prepare($attendanceSql);
    $attendanceStmt->execute($registrationIds);
    foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $attendanceRow) {
        $registrationId = (int) ($attendanceRow['registration_id'] ?? 0);
        $attendanceByRegistration[$registrationId][] = $attendanceRow;

        if (!isset($attendanceSummaryByRegistration[$registrationId])) {
            $attendanceSummaryByRegistration[$registrationId] = [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'tracked' => 0,
            ];
        }

        $statusKey = strtolower(trim((string) ($attendanceRow['status'] ?? '')));
        if ($statusKey === 'present') {
            $attendanceSummaryByRegistration[$registrationId]['present']++;
            $attendanceSummaryByRegistration[$registrationId]['tracked']++;
        } elseif ($statusKey === 'absent') {
            $attendanceSummaryByRegistration[$registrationId]['absent']++;
            $attendanceSummaryByRegistration[$registrationId]['tracked']++;
        } elseif ($statusKey === 'late') {
            $attendanceSummaryByRegistration[$registrationId]['late']++;
            $attendanceSummaryByRegistration[$registrationId]['tracked']++;
        }
    }

    $paymentsSql = "
        SELECT
            registration_id,
            payment_date,
            amount,
            payment_mode,
            payment_type,
            reference_no,
            receipt_no,
            approval_status,
            remarks,
            created_at
        FROM registration_payments
        WHERE registration_id IN ($placeholders)
        ORDER BY registration_id ASC, payment_date DESC, id DESC
    ";
    $paymentsStmt = $pdo->prepare($paymentsSql);
    $paymentsStmt->execute($registrationIds);
    foreach ($paymentsStmt->fetchAll(PDO::FETCH_ASSOC) as $paymentRow) {
        $registrationId = (int) ($paymentRow['registration_id'] ?? 0);
        $paymentsByRegistration[$registrationId][] = $paymentRow;
    }

    $placementsSql = "
        SELECT
            registration_id,
            company_name,
            interview_date,
            interview_time,
            interview_mode,
            status,
            remarks,
            created_at,
            updated_at
        FROM placement_interviews
        WHERE registration_id IN ($placeholders)
        ORDER BY registration_id ASC, interview_date DESC, id DESC
    ";
    $placementsStmt = $pdo->prepare($placementsSql);
    $placementsStmt->execute($registrationIds);
    $placementRows = $placementsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($placementRows as $placementRow) {
        $registrationId = (int) ($placementRow['registration_id'] ?? 0);
        $placementsByRegistration[$registrationId][] = $placementRow;
    }
    $latestPlacementByRegistration = courseLatestRowByRegistration($placementRows, 'interview_date', 'updated_at');
}

courseRequireSpreadsheet();

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$summaryHeaders = [
    'S.No',
    'Registration No',
    'Student Name',
    'Program',
    'Batch',
    'Joined On',
    'Owner / Front Office',
    'Guide Staff',
];

if ($canViewPersonalDetails) {
    $summaryHeaders[] = 'Student Phone';
    $summaryHeaders[] = 'Student Email';
}

$summaryHeaders = array_merge($summaryHeaders, [
    'Total Fee',
    'Discount',
    'Final Fee',
    'Paid Amount',
    'Balance',
    'Payment Status',
    'Attendance %',
    'Present Days',
    'Absent Days',
    'Late Days',
    'Assessment Avg',
    'Mock Avg',
    'HR Status',
    'Latest Placement Status',
]);

$summaryRows = [];
$personalRows = [];
$academicRows = [];
$attendanceRows = [];
$paymentRows = [];
$placementRows = [];

foreach ($rows as $index => $row) {
    $registrationId = (int) ($row['id'] ?? 0);
    $studentName = courseText($row['student_name'] ?: $row['enquiry_snapshot_name']);
    $attendanceSummary = $attendanceSummaryByRegistration[$registrationId] ?? ['present' => 0, 'absent' => 0, 'late' => 0, 'tracked' => 0];
    $attendancePercent = $attendanceSummary['tracked'] > 0
        ? round($attendanceSummary['present'] / $attendanceSummary['tracked'], 4)
        : 0.0;
    $latestPlacement = $latestPlacementByRegistration[$registrationId] ?? [];

    $summaryRow = [
        $index + 1,
        courseText($row['registration_no']),
        $studentName,
        courseText($row['program_name']),
        courseText($row['batch_name']),
        courseFormatDate($row['joined_on'] ?? ''),
        courseText($row['owner_name']),
        courseText($row['guide_staff_name']),
    ];

    if ($canViewPersonalDetails) {
        $summaryRow[] = courseText($row['enquiry_snapshot_phone']);
        $summaryRow[] = courseText($row['enquiry_snapshot_email']);
    }

    $summaryRow = array_merge($summaryRow, [
        courseFloat($row['total_fee'] ?? 0),
        courseFloat($row['discount_amount'] ?? 0),
        courseFloat($row['final_fee'] ?? 0),
        courseFloat($row['paid_amount'] ?? 0),
        courseFloat($row['balance_amount'] ?? 0),
        courseFormatStatus($row['payment_status'] ?? ''),
        $attendancePercent,
        (int) $attendanceSummary['present'],
        (int) $attendanceSummary['absent'],
        (int) $attendanceSummary['late'],
        courseFloat($row['assessment_average'] ?? 0),
        courseFloat($row['mock_average'] ?? 0),
        courseFormatStatus($row['hr_interview_status'] ?? ''),
        courseFormatStatus($latestPlacement['status'] ?? ''),
    ]);
    $summaryRows[] = $summaryRow;

    $academicRows[] = [
        $index + 1,
        courseText($row['registration_no']),
        $studentName,
        courseText($row['program_name']),
        courseText($row['guide_staff_name']),
        courseText($row['owner_name']),
        courseFloat($row['assessment_1'] ?? 0),
        courseFloat($row['assessment_2'] ?? 0),
        courseFloat($row['assessment_3'] ?? 0),
        courseFloat($row['assessment_average'] ?? 0),
        courseFloat($row['theoretical_marks'] ?? 0),
        courseFloat($row['machine_task_marks'] ?? 0),
        courseFloat($row['mock_average'] ?? 0),
        courseFormatStatus($row['mock_workflow_status'] ?? ''),
        courseFormatDateTime($row['mock_completed_at'] ?? ''),
        courseFormatStatus($row['hr_interview_status'] ?? ''),
        courseText($row['hr_company_name']),
        courseFormatDate($row['hr_interview_date'] ?? ''),
        courseFormatDateTime($row['sent_to_hr_at'] ?? ''),
        courseText($row['hr_updated_by_name']),
    ];

    if ($canViewPersonalDetails) {
        $personalRows[] = [
            $index + 1,
            courseText($row['registration_no']),
            $studentName,
            courseText($row['enquiry_snapshot_phone']),
            courseText($row['enquiry_snapshot_email']),
            courseText($row['gender']),
            courseFormatDate($row['dob'] ?? ''),
            courseText($row['qualification']),
            courseText($row['college_name']),
            courseText($row['year_of_passout']),
            courseText($row['address']),
            courseText($row['parent_name']),
            courseText($row['parent_phone']),
            courseText($row['parent_occupation']),
            courseText($row['emergency_contact']),
            courseText($row['aadhaar_no']),
            courseText($row['notes']),
            courseText($row['profile_remarks']),
        ];
    }

    foreach ($attendanceByRegistration[$registrationId] ?? [] as $attendanceRow) {
        $attendanceRows[] = [
            courseText($row['registration_no']),
            $studentName,
            courseText($row['program_name']),
            courseText($row['guide_staff_name']),
            courseFormatDate($attendanceRow['attendance_date'] ?? ''),
            courseFormatStatus($attendanceRow['status'] ?? ''),
            courseText($attendanceRow['topics_taught']),
            courseText($attendanceRow['task_given']),
            courseText($attendanceRow['absent_informed']),
            courseText($attendanceRow['absent_reason']),
            courseText($attendanceRow['absent_informed_by']),
            courseFormatDateTime($attendanceRow['updated_at'] ?? ''),
        ];
    }

    foreach ($paymentsByRegistration[$registrationId] ?? [] as $paymentRow) {
        $paymentRows[] = [
            courseText($row['registration_no']),
            $studentName,
            courseText($row['program_name']),
            courseFormatDate($paymentRow['payment_date'] ?? ''),
            courseFloat($paymentRow['amount'] ?? 0),
            courseFormatStatus($paymentRow['payment_mode'] ?? ''),
            courseFormatStatus($paymentRow['payment_type'] ?? ''),
            courseFormatStatus($paymentRow['approval_status'] ?? ''),
            courseText($paymentRow['reference_no']),
            courseText($paymentRow['receipt_no']),
            courseText($paymentRow['remarks']),
            courseFormatDateTime($paymentRow['created_at'] ?? ''),
        ];
    }

    foreach ($placementsByRegistration[$registrationId] ?? [] as $placementRow) {
        $placementRows[] = [
            courseText($row['registration_no']),
            $studentName,
            courseText($row['program_name']),
            courseText($row['guide_staff_name']),
            courseText($placementRow['company_name']),
            courseFormatDate($placementRow['interview_date'] ?? ''),
            courseText($placementRow['interview_time']),
            courseFormatStatus($placementRow['interview_mode'] ?? ''),
            courseFormatStatus($placementRow['status'] ?? ''),
            courseText($placementRow['remarks']),
            courseFormatDateTime($placementRow['updated_at'] ?? ''),
        ];
    }
}

courseBuildSheet(
    $spreadsheet,
    'Summary',
    $summaryHeaders,
    $summaryRows,
    $canViewPersonalDetails ? ['K', 'L', 'M', 'N', 'O', 'U', 'V'] : ['I', 'J', 'K', 'L', 'M', 'S', 'T'],
    $canViewPersonalDetails ? ['Q'] : ['O']
);

if ($canViewPersonalDetails) {
    courseBuildSheet(
        $spreadsheet,
        'Personal Details',
        [
            'S.No',
            'Registration No',
            'Student Name',
            'Phone',
            'Email',
            'Gender',
            'Date of Birth',
            'Qualification',
            'College',
            'Year of Passout',
            'Address',
            'Parent Name',
            'Parent Phone',
            'Parent Occupation',
            'Emergency Contact',
            'Aadhaar No',
            'Registration Notes',
            'Profile Remarks',
        ],
        $personalRows
    );
}

courseBuildSheet(
    $spreadsheet,
    'Academic & Workflow',
    [
        'S.No',
        'Registration No',
        'Student Name',
        'Program',
        'Guide Staff',
        'Owner / Front Office',
        'Assessment 1',
        'Assessment 2',
        'Assessment 3',
        'Assessment Avg',
        'Mock Theory',
        'Mock Machine Task',
        'Mock Avg',
        'Mock Workflow',
        'Mock Completed At',
        'HR Status',
        'HR Company',
        'HR Interview Date',
        'Sent To HR At',
        'HR Updated By',
    ],
    $academicRows,
    ['G', 'H', 'I', 'J', 'K', 'L', 'M']
);

courseBuildSheet(
    $spreadsheet,
    'Attendance Log',
    [
        'Registration No',
        'Student Name',
        'Program',
        'Guide Staff',
        'Attendance Date',
        'Status',
        'Topics Taught',
        'Task Given',
        'Absent Informed',
        'Absent Reason',
        'Absent Informed By',
        'Updated At',
    ],
    $attendanceRows
);

courseBuildSheet(
    $spreadsheet,
    'Payments',
    [
        'Registration No',
        'Student Name',
        'Program',
        'Payment Date',
        'Amount',
        'Payment Mode',
        'Payment Type',
        'Approval Status',
        'Reference No',
        'Receipt No',
        'Remarks',
        'Created At',
    ],
    $paymentRows,
    ['E']
);

courseBuildSheet(
    $spreadsheet,
    'Placements',
    [
        'Registration No',
        'Student Name',
        'Program',
        'Guide Staff',
        'Company Name',
        'Interview Date',
        'Interview Time',
        'Mode',
        'Status',
        'Remarks',
        'Updated At',
    ],
    $placementRows
);

$spreadsheet->setActiveSheetIndex(0);
courseStreamSpreadsheet($spreadsheet, 'course_report_' . date('Y-m-d_His') . '.xlsx');
