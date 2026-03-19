<?php

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (!function_exists('interviewWorkflowH')) {
    function interviewWorkflowH($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hrWorkflowTableExistsShared')) {
    function hrWorkflowTableExistsShared(PDO $pdo): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $st = $pdo->query("SHOW TABLES LIKE 'student_hr_interviews'");
            $exists = (bool) $st->fetchColumn();
        } catch (Exception $e) {
            $exists = false;
        }

        return $exists;
    }
}

if (!function_exists('placementInterviewTableExistsShared')) {
    function placementInterviewTableExistsShared(PDO $pdo): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        try {
            $st = $pdo->query("SHOW TABLES LIKE 'placement_interviews'");
            $exists = (bool) $st->fetchColumn();
        } catch (Exception $e) {
            $exists = false;
        }

        return $exists;
    }
}

if (!function_exists('fetchInterviewStudentDetailShared')) {
    function fetchInterviewStudentDetailShared(PDO $pdo, int $registrationId, int $branchId, bool $canAllBranches, bool $includePlacementHistory = true): array
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
                rp.aadhaar_no,
                rp.photo_path,
                rp.signature_path,
                rp.remarks AS profile_remarks,
                e.enquiry_no,
                e.remarks AS enquiry_remarks,
                owner.name AS owner_name,
                staff.name AS hr_sent_by_name,
                approver.name AS hr_updated_by_name,
                shi.id AS hr_workflow_id,
                shi.sent_to_hr_at,
                shi.interview_status,
                shi.company_name AS hr_company_name,
                shi.interview_date AS hr_interview_date,
                shi.rejection_reason,
                mi.theoretical_marks,
                mi.machine_task_marks,
                mi.mock_average,
                a.assessment_1,
                a.assessment_2,
                a.assessment_3,
                a.average_marks AS assessment_average
            FROM registrations r
            LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
            LEFT JOIN enquiries e ON e.id = r.enquiry_id
            LEFT JOIN users owner ON owner.id = r.assigned_to
            LEFT JOIN student_hr_interviews shi ON shi.registration_id = r.id
            LEFT JOIN users staff ON staff.id = shi.sent_to_hr_by
            LEFT JOIN users approver ON approver.id = shi.hr_updated_by
            LEFT JOIN mock_interviews mi ON mi.registration_id = r.id
            LEFT JOIN assessment a ON a.registration_id = r.id
            WHERE r.id = ?
        ";

        if (!$canAllBranches && $branchId > 0) {
            $sql .= " AND r.branch_id = ?";
            $params[] = $branchId;
        }

        $sql .= " LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $student = $st->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new RuntimeException('Student not found or access denied.');
        }

        $followups = [];
        if (!empty($student['enquiry_id'])) {
            $st = $pdo->prepare("
                SELECT
                    f.*,
                    u.name AS created_by_name
                FROM enquiry_followups f
                LEFT JOIN users u ON u.id = f.created_by
                WHERE f.enquiry_id = ?
                ORDER BY f.followup_date DESC, f.followup_time DESC, f.id DESC
            ");
            $st->execute([(int) $student['enquiry_id']]);
            $followups = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        $payments = [];
        $st = $pdo->prepare("
            SELECT
                p.*,
                collector.name AS collected_by_name,
                approver.name AS approved_by_name
            FROM registration_payments p
            LEFT JOIN users collector ON collector.id = p.collected_by
            LEFT JOIN users approver ON approver.id = p.approved_by
            WHERE p.registration_id = ?
            ORDER BY p.payment_date DESC, p.id DESC
        ");
        $st->execute([$registrationId]);
        $payments = $st->fetchAll(PDO::FETCH_ASSOC);

        $placementHistory = [];
        if ($includePlacementHistory && placementInterviewTableExistsShared($pdo)) {
            $st = $pdo->prepare("
                SELECT
                    pi.*,
                    creator.name AS created_by_name,
                    updater.name AS updated_by_name
                FROM placement_interviews pi
                LEFT JOIN users creator ON creator.id = pi.created_by
                LEFT JOIN users updater ON updater.id = pi.updated_by
                WHERE pi.registration_id = ?
                ORDER BY pi.interview_date DESC, pi.id DESC
            ");
            $st->execute([$registrationId]);
            $placementHistory = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return [
            'student' => $student,
            'followups' => $followups,
            'payments' => $payments,
            'placement_history' => $placementHistory,
        ];
    }
}
