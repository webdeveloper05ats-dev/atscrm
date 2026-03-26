<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

function studentReportH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function studentReportRoleScope(PDO $pdo, int $roleId): int
{
    try {
        $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
        $st->execute([$roleId]);
        return (int) ($st->fetchColumn() ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

function studentReportResolveAttendanceStartDate(array $student): string
{
    $candidates = [];
    $joinedOn = trim((string) ($student['joined_on'] ?? ''));
    $createdAt = trim((string) ($student['created_at'] ?? ''));
    $assignedAt = trim((string) ($student['guide_assigned_at'] ?? ''));

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $joinedOn)) {
        $candidates[] = $joinedOn;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt)) {
        $candidates[] = substr($createdAt, 0, 10);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $assignedAt)) {
        $candidates[] = substr($assignedAt, 0, 10);
    }

    return $candidates ? max($candidates) : date('Y-m-d');
}

function studentReportFetchBaseStudent(PDO $pdo, int $registrationId, int $userId, int $branchId, bool $canAllBranches, string $mode): ?array
{
    $params = [$registrationId];
    $sql = "
        SELECT
            r.*,
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
            rp.remarks AS profile_remarks,
            rc.guide_staff_id,
            rc.assigned_at AS guide_assigned_at,
            u.name AS assigned_staff_name
        FROM registrations r
        LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
        LEFT JOIN registration_courses rc ON rc.registration_id = r.id
        LEFT JOIN users u ON u.id = rc.guide_staff_id
        WHERE r.id = ?
          AND r.reg_type = 'course'
    ";

    if ($mode === 'staff') {
        $sql .= " AND rc.guide_staff_id = ?";
        $params[] = $userId;
    } elseif ($mode === 'hr') {
        $sql .= " AND EXISTS (SELECT 1 FROM student_hr_interviews shi WHERE shi.registration_id = r.id)";
    }

    if (!$canAllBranches && $branchId > 0) {
        $sql .= " AND r.branch_id = ?";
        $params[] = $branchId;
    }

    $sql .= " LIMIT 1";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function studentReportFetchAttendanceRows(PDO $pdo, int $registrationId): array
{
    try {
        $st = $pdo->prepare("
            SELECT attendance_date, status, topics_taught, task_given, absent_informed, absent_reason, absent_informed_by, marked_by, created_at, updated_at
            FROM attendance
            WHERE registration_id = ?
            ORDER BY attendance_date ASC, id ASC
        ");
        $st->execute([$registrationId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function studentReportBuildAttendanceSummary(array $student, array $attendanceRows): array
{
    $presentDays = 0;
    $absentDays = 0;

    foreach ($attendanceRows as $row) {
        $status = strtolower((string) ($row['status'] ?? ''));
        if ($status === 'present') {
            $presentDays++;
        } elseif ($status === 'absent') {
            $absentDays++;
        }
    }

    $startDate = studentReportResolveAttendanceStartDate($student);
    $totalDays = 0;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) && $startDate <= date('Y-m-d')) {
        try {
            $start = new DateTime($startDate);
            $end = new DateTime(date('Y-m-d'));
            $totalDays = ((int) $start->diff($end)->days) + 1;
        } catch (Exception $e) {
            $totalDays = 0;
        }
    }

    $attendancePercent = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 2) : 0.0;

    return [
        'start_date' => $startDate,
        'present_days' => $presentDays,
        'absent_days' => $absentDays,
        'total_days' => $totalDays,
        'attendance_percent' => $attendancePercent,
    ];
}

function studentReportFetchAcademicAndHrData(PDO $pdo, int $registrationId): array
{
    $data = [
        'assessment' => null,
        'mock' => null,
        'hr' => null,
        'placement_history' => [],
        'payments' => [],
    ];

    try {
        $st = $pdo->prepare("SELECT assessment_1, assessment_2, assessment_3, average_marks FROM assessment WHERE registration_id = ? LIMIT 1");
        $st->execute([$registrationId]);
        $data['assessment'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
    }

    try {
        $st = $pdo->prepare("SELECT theoretical_marks, machine_task_marks, mock_average, workflow_status, completed_at FROM mock_interviews WHERE registration_id = ? LIMIT 1");
        $st->execute([$registrationId]);
        $data['mock'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
    }

    try {
        $st = $pdo->prepare("
            SELECT shi.*, sender.name AS sent_by_name, updater.name AS hr_updated_by_name
            FROM student_hr_interviews shi
            LEFT JOIN users sender ON sender.id = shi.sent_to_hr_by
            LEFT JOIN users updater ON updater.id = shi.hr_updated_by
            WHERE shi.registration_id = ?
            LIMIT 1
        ");
        $st->execute([$registrationId]);
        $data['hr'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
    }

    try {
        $st = $pdo->prepare("
            SELECT company_name, interview_date, interview_time, interview_mode, status, remarks, created_at, updated_at
            FROM placement_interviews
            WHERE registration_id = ?
            ORDER BY interview_date DESC, id DESC
        ");
        $st->execute([$registrationId]);
        $data['placement_history'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
    }

    try {
        $st = $pdo->prepare("
            SELECT p.payment_date, p.amount, p.payment_mode, p.payment_type, p.approval_status, p.reference_no, p.receipt_no, p.remarks, u.name AS collected_by_name
            FROM registration_payments p
            LEFT JOIN users u ON u.id = p.collected_by
            WHERE p.registration_id = ?
            ORDER BY p.payment_date DESC, p.id DESC
        ");
        $st->execute([$registrationId]);
        $data['payments'] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
    }

    return $data;
}
