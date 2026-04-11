<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('attendance');
}

if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ha')) {
    function ha($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$roleName = trim((string) ($_SESSION['role_name'] ?? ''));

$canAllBranches = 0;
try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

function isAttendanceReadOnlyViewer(string $roleName): bool
{
    return in_array($roleName, ['Super Admin', 'Admin', 'HR', 'Front Office'], true);
}

function fetchAttendanceStudent(PDO $pdo, int $registrationId, int $userId, int $branchId, int $canAllBranches, bool $canViewAllAttendance)
{
    $params = [$registrationId];
    $sql = "
        SELECT
            r.id,
            r.branch_id,
            r.registration_no,
            r.joined_on,
            r.created_at,
            r.updated_at,
            rc.assigned_at AS guide_assigned_at,
            r.enquiry_snapshot_name,
            r.enquiry_snapshot_phone,
            r.enquiry_snapshot_email,
            COALESCE(rp.parent_name, e.father_name) AS parent_name,
            " . crmBuildParentEmailFallbackSelect($pdo, 'rp', 'e') . " AS parent_email,
            r.program_name,
            r.batch_name
        FROM registrations r
        LEFT JOIN registration_courses rc ON rc.registration_id = r.id
        LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
        LEFT JOIN enquiries e ON e.id = r.enquiry_id
        WHERE r.id = ?
          AND r.reg_type = 'course'
          AND r.registration_status IN ('active','completed')
    ";

    if (!$canViewAllAttendance) {
        $sql .= " AND rc.guide_staff_id = ?";
        $params[] = $userId;
    }

    if ($canAllBranches !== 1 && $branchId > 0) {
        $sql .= " AND r.branch_id = ?";
        $params[] = $branchId;
    }

    $sql .= " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetch(PDO::FETCH_ASSOC);
}

function isFutureAttendanceDate(string $date): bool
{
    return $date > date('Y-m-d');
}

function isPastAttendanceDateTooOld(string $date): bool
{
    return $date < date('Y-m-d', strtotime('-1 day'));
}

function resolveAttendanceStartDate(array $student): string
{
    $candidates = [];

    $joinedOn = trim((string) ($student['joined_on'] ?? ''));
    $createdAt = trim((string) ($student['created_at'] ?? ''));
    $updatedAt = trim((string) ($student['updated_at'] ?? ''));
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

    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $updatedAt) && $updatedAt > $createdAt) {
        $candidates[] = substr($updatedAt, 0, 10);
    }

    if (empty($candidates)) {
        return date('Y-m-d');
    }

    return max($candidates);
}

function calculateAttendanceTotalDays(string $startDate): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        return 0;
    }

    $today = date('Y-m-d');
    if ($startDate > $today) {
        return 0;
    }

    try {
        $start = new DateTime($startDate);
        $end = new DateTime($today);
        return ((int) $start->diff($end)->days) + 1;
    } catch (Exception $e) {
        return 0;
    }
}

function renderAttendanceCalendar(array $student, array $attendanceMap, string $month, bool $readOnlyMode = false): string
{
    $first = DateTime::createFromFormat('Y-m-d', $month . '-01');
    if (!$first) {
        $first = new DateTime('first day of this month');
    }

    $monthKey = $first->format('Y-m');
    $daysInMonth = (int) $first->format('t');
    $startWeekDay = (int) $first->format('N');
    $prevMonth = (clone $first)->modify('-1 month')->format('Y-m');
    $nextMonth = (clone $first)->modify('+1 month')->format('Y-m');
    $today = date('Y-m-d');
    $currentMonth = date('Y-m');
    $canGoNextMonth = ($nextMonth <= $currentMonth);
    $attendanceStartDate = resolveAttendanceStartDate($student);

    ob_start();
    ?>
    <div class="att-calendar-wrap">
        <div class="att-calendar-toolbar">
            <button type="button" class="btn btn-light att-month-btn"
                onclick="loadAttendanceCalendar(<?= (int) $student['id'] ?>, '<?= h($student['enquiry_snapshot_name']) ?>', '<?= h($prevMonth) ?>')">
                <i class="fas fa-angle-left"></i>
            </button>
            <div class="att-calendar-title"><?= h($first->format('F Y')) ?></div>
            <button type="button" class="btn btn-light att-month-btn <?= $canGoNextMonth ? '' : 'att-month-btn-disabled' ?>"
                <?= $canGoNextMonth ? "onclick=\"loadAttendanceCalendar(" . (int) $student['id'] . ", '" . h($student['enquiry_snapshot_name']) . "', '" . h($nextMonth) . "')\"" : 'disabled' ?>>
                <i class="fas fa-angle-right"></i>
            </button>
        </div>

        <div class="att-student-meta">
            <div><b>Student:</b> <?= h($student['enquiry_snapshot_name']) ?></div>
            <div><b>Registration:</b> <?= h($student['registration_no']) ?></div>
            <div><b>Program:</b> <?= h($student['program_name']) ?></div>
            <div><b>Assigned On:</b> <?= h($attendanceStartDate) ?></div>
        </div>

        <div class="att-legend">
            <span class="att-legend-item"><i class="att-dot att-dot-present"></i> Present</span>
            <span class="att-legend-item"><i class="att-dot att-dot-absent"></i> Absent</span>
            <span class="att-legend-item"><i class="att-dot att-dot-pending"></i> Not Marked</span>
            <span class="att-legend-item"><i class="att-dot att-dot-locked"></i> Locked</span>
        </div>

        <div class="att-form-note" style="margin-bottom:16px;">
            <?php if ($readOnlyMode): ?>
                View only access. Attendance is visible from <b><?= h($attendanceStartDate) ?></b> up to today.
            <?php else: ?>
                Attendance can be marked strictly for <b>Today</b> and <b>Yesterday</b> only.
            <?php endif; ?>
        </div>

        <div class="att-grid">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayLabel): ?>
                <div class="att-grid-head"><?= h($dayLabel) ?></div>
            <?php endforeach; ?>
            <?php for ($blank = 1; $blank < $startWeekDay; $blank++): ?>
                <div class="att-grid-cell att-grid-cell-empty"></div>
            <?php endfor; ?>
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                $date = $monthKey . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                $record = $attendanceMap[$date] ?? null;
                $status = strtolower((string) ($record['status'] ?? ''));
                $isBeforeAssignment = ($date < $attendanceStartDate);
                $isFutureDate = isFutureAttendanceDate($date);
                $isTooOldDate = isPastAttendanceDateTooOld($date);
                $isLockedDate = ($isBeforeAssignment || $isFutureDate || $isTooOldDate);
                $canOpenDay = !$isLockedDate;
                $cellClass = 'att-status-empty';
                $buttonText = $readOnlyMode ? 'View' : 'Mark';
                
                if ($status === 'present') {
                    $cellClass = 'att-status-present';
                    $buttonText = 'Present';
                } elseif ($status === 'absent') {
                    $cellClass = 'att-status-absent';
                    $buttonText = 'Absent';
                } elseif (!$isBeforeAssignment && !$isFutureDate) {
                    $cellClass = 'att-status-pending';
                    $buttonText = 'Not Marked';
                }
                if ($isLockedDate && $cellClass !== 'att-status-pending') {
                    $cellClass .= ' att-status-locked';
                }
                $lockTitle = '';
                if ($isBeforeAssignment) {
                    $lockTitle = 'Dates before staff assignment are locked';
                } elseif ($isFutureDate) {
                    $lockTitle = 'Future dates are locked';
                } elseif ($isTooOldDate) {
                    $lockTitle = 'Attendance can only be marked for today and yesterday';
                }
                if ($readOnlyMode && !$isLockedDate) {
                    $lockTitle = 'View attendance details';
                }
                ?>
                <button type="button"
                    class="att-grid-cell att-grid-day <?= h($cellClass) ?> <?= $date === $today ? 'att-day-today' : '' ?>"
                    <?= !$canOpenDay ? 'disabled data-modern-tooltip="' . h($lockTitle) . '" aria-label="' . h($lockTitle) . '"' : "onclick=\"openAttendanceEntry(" . (int) $student['id'] . ", '" . h($student['enquiry_snapshot_name']) . "', '" . h($date) . "', " . ($readOnlyMode ? 'true' : 'false') . ")\" data-modern-tooltip=\"" . h($lockTitle) . "\" aria-label=\"" . h($lockTitle) . "\"" ?>>
                    <span class="att-day-no"><?= (int) $day ?></span>
                    <span class="att-day-text"><?= $isLockedDate && $cellClass !== 'att-status-pending' && $cellClass === 'att-status-empty' ? 'Locked' : $buttonText ?></span>
                </button>
            <?php endfor; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_attendance'])) {
    $readOnlyViewer = isAttendanceReadOnlyViewer($roleName);
    if ($readOnlyViewer) {
        responseJson('error', 'You have view only access for attendance.');
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        responseJson('error', 'Invalid CSRF token.');
    }

    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $date = trim((string) ($_POST['attendance_date'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? ''));
    $topicsTaught = trim((string) ($_POST['topics_taught'] ?? ''));
    $taskGiven = trim((string) ($_POST['task_given'] ?? ''));
    $absentInformed = trim((string) ($_POST['absent_informed'] ?? ''));
    $absentReason = trim((string) ($_POST['absent_reason'] ?? ''));
    $absentInformedBy = trim((string) ($_POST['absent_informed_by'] ?? ''));

    $student = fetchAttendanceStudent($pdo, $registrationId, $userId, $branchId, $canAllBranches, false);
    if (!$student) {
        responseJson('error', 'Student not found or access denied.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        responseJson('error', 'Invalid attendance date.');
    }
    $attendanceStartDate = resolveAttendanceStartDate($student);
    if ($date < $attendanceStartDate) {
        responseJson('error', 'Attendance is locked before the student was assigned to you.');
    }
    if (isFutureAttendanceDate($date)) {
        responseJson('error', 'Future attendance dates are locked.');
    }
    if (isPastAttendanceDateTooOld($date)) {
        responseJson('error', 'You can only mark attendance for today and yesterday.');
    }

    try {
        $st = $pdo->prepare("
            SELECT id, status
            FROM attendance
            WHERE registration_id = ?
              AND attendance_date = ?
            LIMIT 1
        ");
        $st->execute([$registrationId, $date]);
        $existing = $st->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if (strtolower((string) ($existing['status'] ?? '')) !== 'present') {
                responseJson('error', 'Absent attendance cannot be edited.');
            }

            $upd = $pdo->prepare("
                UPDATE attendance
                SET topics_taught = ?,
                    task_given = ?,
                    updated_at = NOW()
                WHERE id = ?
                LIMIT 1
            ");
            $upd->execute([
                $topicsTaught !== '' ? $topicsTaught : null,
                $taskGiven !== '' ? $taskGiven : null,
                (int) $existing['id']
            ]);
            responseJson('success', 'Attendance details updated successfully.');
        }

        if (!in_array($status, ['Present', 'Absent'], true)) {
            responseJson('error', 'Please select attendance status.');
        }

        if ($status === 'Present' && ($topicsTaught === '' || $taskGiven === '')) {
            responseJson('error', 'Topics taught and task given are required for present attendance.');
        }

        if ($status === 'Absent') {
            if (!in_array($absentInformed, ['yes', 'no'], true)) {
                responseJson('error', 'Please select whether the student informed or not.');
            }
            if ($absentInformed === 'yes' && ($absentReason === '' || $absentInformedBy === '')) {
                responseJson('error', 'Reason and person who informed are required when informed is yes.');
            }
            if ($absentInformed === 'no') {
                $absentReason = '';
                $absentInformedBy = '';
            }
            $topicsTaught = '';
            $taskGiven = '';
        } else {
            $absentInformed = '';
            $absentReason = '';
            $absentInformedBy = '';
        }

        $ins = $pdo->prepare("
            INSERT INTO attendance (
                registration_id,
                user_id,
                course_id,
                branch_id,
                attendance_date,
                status,
                topics_taught,
                task_given,
                absent_informed,
                absent_reason,
                absent_informed_by,
                marked_by,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([
            $registrationId,
            $registrationId, // Used to bypass the (user_id, course_id, attendance_date) legacy unique constraint properly
            0,               // Default legacy course_id
            (int) ($student['branch_id'] ?? 0),
            $date,
            $status,
            $topicsTaught !== '' ? $topicsTaught : null,
            $taskGiven !== '' ? $taskGiven : null,
            $absentInformed !== '' ? $absentInformed : null,
            $absentReason !== '' ? $absentReason : null,
            $absentInformedBy !== '' ? $absentInformedBy : null,
            $userId > 0 ? $userId : null
        ]);

        if ($status === 'Absent') {
            $studentDisplayName = trim((string) ($student['enquiry_snapshot_name'] ?? 'Student'));
            $parentDisplayName = trim((string) ($student['parent_name'] ?? '')) !== '' ? trim((string) ($student['parent_name'] ?? '')) : 'Parent';
            $recipients = [
                ['email' => $student['enquiry_snapshot_email'] ?? '', 'name' => $studentDisplayName],
                ['email' => $student['parent_email'] ?? '', 'name' => $parentDisplayName],
            ];
            $absenceReasonText = $absentReason !== '' ? $absentReason : 'Not provided';
            $informedByText = $absentInformedBy !== '' ? $absentInformedBy : 'Not provided';
            $htmlBody = '
                <p>Dear Student and Parent,</p>
                <p>This is to inform you that the student was marked absent.</p>
                <p><strong>Student:</strong> ' . h($studentDisplayName) . '<br>
                <strong>Registration No:</strong> ' . h((string) ($student['registration_no'] ?? '')) . '<br>
                <strong>Program:</strong> ' . h((string) ($student['program_name'] ?? '')) . '<br>
                <strong>Date:</strong> ' . h($date) . '<br>
                <strong>Informed:</strong> ' . h(strtoupper($absentInformed) === 'YES' ? 'Yes' : 'No') . '<br>
                <strong>Reason:</strong> ' . h($absenceReasonText) . '<br>
                <strong>Informed By:</strong> ' . h($informedByText) . '</p>
                <p>Regards,<br>' . h(APP_NAME) . '</p>';
            $textBody = "Dear Student and Parent,\n\n"
                . "This is to inform you that the student was marked absent.\n"
                . "Student: {$studentDisplayName}\n"
                . "Registration No: " . (string) ($student['registration_no'] ?? '') . "\n"
                . "Program: " . (string) ($student['program_name'] ?? '') . "\n"
                . "Date: {$date}\n"
                . "Informed: " . (strtoupper($absentInformed) === 'YES' ? 'Yes' : 'No') . "\n"
                . "Reason: {$absenceReasonText}\n"
                . "Informed By: {$informedByText}\n\n"
                . "Regards,\n" . APP_NAME;
            crmSendEmail($recipients, 'Absence notification for ' . $studentDisplayName, $htmlBody, $textBody);
        }

        responseJson('success', 'Attendance saved successfully.');
    } catch (Exception $e) {
        responseJson('error', 'Unable to save attendance: ' . $e->getMessage());
    }
}

$isAjax = isset($_GET['ajax']) && (int) $_GET['ajax'] === 1;
$attendanceReadOnlyViewer = isAttendanceReadOnlyViewer($roleName);

if ($isAjax) {
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($action === 'calendar') {
        $registrationId = (int) ($_GET['registration_id'] ?? 0);
        $month = trim((string) ($_GET['month'] ?? date('Y-m')));
        $student = fetchAttendanceStudent($pdo, $registrationId, $userId, $branchId, $canAllBranches, $attendanceReadOnlyViewer);
        if (!$student) {
            echo "<div class='att-empty-note'>Student not found or access denied.</div>";
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $attendanceMap = [];
        try {
            $from = $month . '-01';
            $to = date('Y-m-t', strtotime($from));
            $st = $pdo->prepare("
                SELECT attendance_date, status, topics_taught, task_given, absent_informed, absent_reason, absent_informed_by
                FROM attendance
                WHERE registration_id = ?
                  AND attendance_date BETWEEN ? AND ?
            ");
            $st->execute([$registrationId, $from, $to]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $attendanceMap[$row['attendance_date']] = $row;
            }
        } catch (Exception $e) {
        }

        echo renderAttendanceCalendar($student, $attendanceMap, $month, $attendanceReadOnlyViewer);
        exit;
    }

    if ($action === 'entry_form') {
        $registrationId = (int) ($_GET['registration_id'] ?? 0);
        $date = trim((string) ($_GET['date'] ?? ''));
        $student = fetchAttendanceStudent($pdo, $registrationId, $userId, $branchId, $canAllBranches, $attendanceReadOnlyViewer);
        if (!$student) {
            echo "<div class='att-empty-note'>Student not found or access denied.</div>";
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo "<div class='att-empty-note'>Invalid attendance date.</div>";
            exit;
        }
        $attendanceStartDate = resolveAttendanceStartDate($student);
        if ($date < $attendanceStartDate) {
            echo "<div class='att-empty-note att-empty-note-danger'>Attendance is locked before the student was assigned to you.</div>";
            exit;
        }
        if (isFutureAttendanceDate($date)) {
            echo "<div class='att-empty-note att-empty-note-danger'>Future attendance dates are locked.</div>";
            exit;
        }
        if (isPastAttendanceDateTooOld($date)) {
            echo "<div class='att-empty-note att-empty-note-danger'>You can only mark attendance for today and yesterday.</div>";
            exit;
        }

        $record = null;
        try {
            $st = $pdo->prepare("
                SELECT id, status, topics_taught, task_given, absent_informed, absent_reason, absent_informed_by
                FROM attendance
                WHERE registration_id = ?
                  AND attendance_date = ?
                LIMIT 1
            ");
            $st->execute([$registrationId, $date]);
            $record = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $record = null;
        }

        $hasRecord = !empty($record);
        $isPresent = $hasRecord && strtolower((string) $record['status']) === 'present';
        $isAbsent = $hasRecord && strtolower((string) $record['status']) === 'absent';
        ?>
        <?php if ($attendanceReadOnlyViewer): ?>
            <div class="att-form-note">View only access. Attendance cannot be edited from this account.</div>
            <div class="att-entry-grid">
                <div class="att-info-box"><b>Student</b><span><?= h($student['enquiry_snapshot_name']) ?></span></div>
                <div class="att-info-box"><b>Date</b><span><?= h($date) ?></span></div>
                <div class="att-info-box att-info-box-full"><b>Program</b><span><?= h($student['program_name']) ?></span></div>
            </div>

            <?php if (!$hasRecord): ?>
                <div class="att-empty-note">No attendance recorded for this date.</div>
            <?php elseif ($isPresent): ?>
                <div class="att-form-row">
                    <label class="att-form-label">Attendance</label>
                    <input type="text" class="att-form-control" value="Present" readonly>
                </div>
                <div class="att-form-row">
                    <label class="att-form-label">Topics Taught</label>
                    <textarea class="att-form-control" rows="4" readonly><?= h($record['topics_taught'] ?? '') ?></textarea>
                </div>
                <div class="att-form-row">
                    <label class="att-form-label">Task Given</label>
                    <textarea class="att-form-control" rows="4" readonly><?= h($record['task_given'] ?? '') ?></textarea>
                </div>
            <?php else: ?>
                <div class="att-form-row">
                    <label class="att-form-label">Attendance</label>
                    <input type="text" class="att-form-control" value="Absent" readonly>
                </div>
                <div class="att-form-row">
                    <label class="att-form-label">Has Informed?</label>
                    <input type="text" class="att-form-control" value="<?= h(strtolower((string) ($record['absent_informed'] ?? '')) === 'yes' ? 'Yes' : 'No') ?>" readonly>
                </div>
                <?php if (strtolower((string) ($record['absent_informed'] ?? '')) === 'yes'): ?>
                    <div class="att-form-row">
                        <label class="att-form-label">Reason</label>
                        <textarea class="att-form-control" rows="3" readonly><?= h($record['absent_reason'] ?? '') ?></textarea>
                    </div>
                    <div class="att-form-row">
                        <label class="att-form-label">Person Who Informed</label>
                        <input type="text" class="att-form-control" value="<?= h($record['absent_informed_by'] ?? '') ?>" readonly>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="att-form-actions">
                <button type="button" class="btn" style="background:#f3f4f6;" onclick="closeAttendanceEntry()">Close</button>
            </div>
        <?php else: ?>
        <form id="attendanceEntryForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="ajax_save_attendance" value="1">
            <input type="hidden" name="registration_id" value="<?= (int) $registrationId ?>">
            <input type="hidden" name="attendance_date" value="<?= h($date) ?>">

            <div class="att-entry-grid">
                <div class="att-info-box"><b>Student</b><span><?= h($student['enquiry_snapshot_name']) ?></span></div>
                <div class="att-info-box"><b>Date</b><span><?= h($date) ?></span></div>
                <div class="att-info-box att-info-box-full"><b>Program</b><span><?= h($student['program_name']) ?></span></div>
            </div>
            <?php if (!$hasRecord): ?>
                <div class="att-form-row">
                    <label class="att-form-label">Attendance</label>
                    <select name="status" id="attendanceStatusSelect" class="att-form-control" required>
                        <option value="">Select</option>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                    </select>
                </div>

                <div id="presentDetailsWrap" style="display:none;">
                    <div class="att-form-row">
                        <label class="att-form-label">Topics Taught</label>
                        <textarea name="topics_taught" id="topicsTaughtInput" class="att-form-control" rows="4"></textarea>
                    </div>
                    <div class="att-form-row">
                        <label class="att-form-label">Task Given</label>
                        <textarea name="task_given" id="taskGivenInput" class="att-form-control" rows="4"></textarea>
                    </div>
                </div>

                <div id="absentDetailsWrap" style="display:none;">
                    <div class="att-form-row">
                        <label class="att-form-label">Has Informed?</label>
                        <div class="att-radio-row">
                            <label class="att-radio-option">
                                <input type="radio" name="absent_informed" value="yes">
                                <span>Yes</span>
                            </label>
                            <label class="att-radio-option">
                                <input type="radio" name="absent_informed" value="no">
                                <span>No</span>
                            </label>
                        </div>
                    </div>

                    <div id="absentInfoDetailsWrap" style="display:none;">
                        <div class="att-form-row">
                            <label class="att-form-label">Reason</label>
                            <textarea name="absent_reason" id="absentReasonInput" class="att-form-control" rows="3"></textarea>
                        </div>
                        <div class="att-form-row">
                            <label class="att-form-label">Person Who Informed</label>
                            <input type="text" name="absent_informed_by" id="absentInformedByInput" class="att-form-control" placeholder="Student / Parent / Guardian / Other">
                        </div>
                    </div>
                </div>

                <div class="att-form-actions">
                    <button type="button" class="btn" style="background:#f3f4f6;" onclick="closeAttendanceEntry()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Attendance</button>
                </div>
            <?php elseif ($isPresent): ?>
                <div class="att-form-row">
                    <label class="att-form-label">Attendance</label>
                    <input type="text" class="att-form-control" value="Present" readonly>
                </div>
                <input type="hidden" name="status" value="Present">

                <div class="att-form-row">
                    <label class="att-form-label">Topics Taught</label>
                    <textarea name="topics_taught" id="topicsTaughtInput" class="att-form-control" rows="4"><?= h($record['topics_taught'] ?? '') ?></textarea>
                </div>
                <div class="att-form-row">
                    <label class="att-form-label">Task Given</label>
                    <textarea name="task_given" id="taskGivenInput" class="att-form-control" rows="4"><?= h($record['task_given'] ?? '') ?></textarea>
                </div>

                <div class="att-form-note">Attendance status is locked. Only topics taught and task given can be edited.</div>

                <div class="att-form-actions">
                    <button type="button" class="btn" style="background:#f3f4f6;" onclick="closeAttendanceEntry()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Details</button>
                </div>
            <?php else: ?>
                <div class="att-form-row">
                    <label class="att-form-label">Attendance</label>
                    <input type="text" class="att-form-control" value="Absent" readonly>
                </div>

                <div class="att-form-row">
                    <label class="att-form-label">Has Informed?</label>
                    <input type="text" class="att-form-control" value="<?= h(strtolower((string) ($record['absent_informed'] ?? '')) === 'yes' ? 'Yes' : 'No') ?>" readonly>
                </div>

                <?php if (strtolower((string) ($record['absent_informed'] ?? '')) === 'yes'): ?>
                    <div class="att-form-row">
                        <label class="att-form-label">Reason</label>
                        <textarea class="att-form-control" rows="3" readonly><?= h($record['absent_reason'] ?? '') ?></textarea>
                    </div>
                    <div class="att-form-row">
                        <label class="att-form-label">Person Who Informed</label>
                        <input type="text" class="att-form-control" value="<?= h($record['absent_informed_by'] ?? '') ?>" readonly>
                    </div>
                <?php endif; ?>

                <div class="att-form-note att-form-note-danger">This date is already marked absent. Attendance cannot be changed later.</div>

                <div class="att-form-actions">
                    <button type="button" class="btn" style="background:#f3f4f6;" onclick="closeAttendanceEntry()">Close</button>
                </div>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <?php
        exit;
    }

    echo "<div class='att-empty-note'>Invalid request.</div>";
    exit;
}

$q = trim($_GET['q'] ?? '');
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$where = ["r.reg_type = 'course'", "r.registration_status IN ('active','completed')"];
$params = [];

if (!$attendanceReadOnlyViewer) {
    $where[] = "rc.guide_staff_id = ?";
    $params[] = $userId;
}

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($q !== '') {
    $where[] = "(
        r.registration_no LIKE ?
        OR r.enquiry_snapshot_name LIKE ?
        OR r.enquiry_snapshot_phone LIKE ?
        OR r.enquiry_snapshot_email LIKE ?
        OR r.program_name LIKE ?
        OR r.batch_name LIKE ?
    )";
    $like = "%{$q}%";
    array_push($params, $like, $like, $like, $like, $like, $like);
}

if ($dateFrom !== '') {
    $where[] = "DATE(r.joined_on) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "DATE(r.joined_on) <= ?";
    $params[] = $dateTo;
}

$whereSql = "WHERE " . implode(" AND ", $where);

$totalRows = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM registrations r LEFT JOIN registration_courses rc ON rc.registration_id = r.id {$whereSql}");
    $st->execute($params);
    $totalRows = (int) $st->fetchColumn();
} catch (Exception $e) {
    $totalRows = 0;
}

$rows = [];
try {
    $sql = "
        SELECT
            r.id,
            r.registration_no,
            r.joined_on,
            r.created_at,
            r.updated_at,
            rc.assigned_at AS guide_assigned_at,
            r.enquiry_snapshot_name,
            r.enquiry_snapshot_phone,
            r.enquiry_snapshot_email,
            r.program_name,
            r.batch_name,
            r.registration_status
        FROM registrations r
        LEFT JOIN registration_courses rc ON rc.registration_id = r.id
        {$whereSql}
        ORDER BY r.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

$attendanceSummaryMap = [];
if (!empty($rows)) {
    $registrationIds = array_values(array_filter(array_map(static function ($row) {
        return (int) ($row['id'] ?? 0);
    }, $rows)));

    if (!empty($registrationIds)) {
        try {
            $placeholders = implode(',', array_fill(0, count($registrationIds), '?'));
            $st = $pdo->prepare("
                SELECT
                    registration_id,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present_days,
                    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent_days
                FROM attendance
                WHERE registration_id IN ($placeholders)
                GROUP BY registration_id
            ");
            $st->execute($registrationIds);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $summaryRow) {
                $attendanceSummaryMap[(int) $summaryRow['registration_id']] = [
                    'present_days' => (int) ($summaryRow['present_days'] ?? 0),
                    'absent_days' => (int) ($summaryRow['absent_days'] ?? 0),
                ];
            }
        } catch (Exception $e) {
            $attendanceSummaryMap = [];
        }
    }
}
?>

<div class="payments-dashboard attendance-dashboard">
    <div class="dashboard-header">
        <h2><i class="fas fa-user-check" style="margin-right:12px; color:#e91e63;"></i>Attendance Management</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-database"></i> Total: <?= (int) $totalRows ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-sliders-h" style="margin-right:8px;"></i> Filter Attendance
        </div>
        <form method="GET" action="index.php" class="filter-form">
            <input type="hidden" name="page" value="attendance">
            <div class="filter-grid">
                <div class="filter-item">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / name / phone / program">
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-calendar-day"></i> Date From</label>
                    <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
                </div>
                <div class="filter-item">
                    <label><i class="fas fa-calendar-check"></i> Date To</label>
                    <input type="date" name="date_to" value="<?= h($dateTo) ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-icon-only filter-action-btn apply" title="Apply filters" aria-label="Apply filters">
                        <span class="btn-inner">
                            <i class="fas fa-filter"></i>
                            <span class="btn-mobile-label">Apply</span>
                        </span>
                    </button>
                    <a href="index.php?page=attendance" class="btn-icon-only filter-action-btn reset" title="Reset filters" aria-label="Reset filters">
                        <span class="btn-inner">
                            <i class="fas fa-undo-alt"></i>
                            <span class="btn-mobile-label">Reset</span>
                        </span>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <div class="table-header-flex">
                <div class="table-title">
                    <i class="fas fa-list"></i>
                    <?= $attendanceReadOnlyViewer ? 'All Course Students Attendance' : 'Assigned Course Students Attendance' ?>
                </div>
                <div id="datatableControls"></div>
            </div>
        </div>
        <div class="table-container">
            <div class="crm-table-wrapper">
                <table id="attendanceTable" class="crm-table att-table display" style="width:100%;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Registration</th>
                    <th>Student</th>
                    <th>Program</th>
                    <th>Present Days</th>
                    <th>Absent Days</th>
                    <th>Total Days</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;">No assigned course students found.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): ?>
                    <?php
                    $summary = $attendanceSummaryMap[(int) $r['id']] ?? ['present_days' => 0, 'absent_days' => 0];
                    $attendanceStartDate = resolveAttendanceStartDate($r);
                    $totalDays = calculateAttendanceTotalDays($attendanceStartDate);
                    ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td>
                            <div class="att-primary"><?= h($r['registration_no']) ?></div>
                            <div class="att-sub"><?= h($r['joined_on']) ?></div>
                        </td>
                        <td>
                            <div class="att-primary"><?= h($r['enquiry_snapshot_name']) ?></div>
                            <div class="att-sub"><?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                        </td>
                        <td>
                            <div><?= h($r['program_name']) ?></div>
                            <div class="att-sub"><?= h($r['batch_name']) ?></div>
                        </td>
                        <td><span class="att-summary-chip att-summary-present"><?= (int) $summary['present_days'] ?></span></td>
                        <td><span class="att-summary-chip att-summary-absent"><?= (int) $summary['absent_days'] ?></span></td>
                        <td>
                            <div class="att-primary"><?= (int) $totalDays ?></div>
                            <div class="att-sub">From <?= h($attendanceStartDate) ?></div>
                        </td>
                        <td><span class="att-reg-badge"><?= h(ucfirst((string) $r['registration_status'])) ?></span></td>
                        <td class="text-center">
                            <button type="button" class="att-icon-btn"
                                onclick="openAttendanceCalendar(<?= (int) $r['id'] ?>, '<?= h($r['enquiry_snapshot_name']) ?>')"
                                data-mobile-label="Calendar"
                                data-modern-tooltip="Attendance Calendar"
                                aria-label="Attendance Calendar">
                                <i class="fas fa-calendar-check"></i>
                                <span class="mobile-action-label">Calendar</span>
                            </button>
                            <a href="index.php?page=reports/student_schedule&registration_id=<?= (int) $r['id'] ?>" class="att-icon-btn att-icon-btn-report" data-mobile-label="Report" data-modern-tooltip="Student Schedule Report" aria-label="Student Schedule Report">
                                <i class="fas fa-file-alt"></i>
                                <span class="mobile-action-label">Report</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="attendanceCalendarModal" class="att-modal-backdrop" style="display:none;">
    <div class="att-modal">
        <div class="att-modal-header">
            <h3 id="attendanceCalendarTitle">Attendance Calendar</h3>
            <button type="button" class="att-close-btn" onclick="closeAttendanceCalendar()">&times;</button>
        </div>
        <div class="att-modal-body" id="attendanceCalendarBody">Calendar loading...</div>
    </div>
</div>

<div id="attendanceEntryModal" class="att-modal-backdrop" style="display:none;z-index:9999;">
    <div class="att-entry-modal">
        <div class="att-modal-header">
            <h3 id="attendanceEntryTitle">Attendance Entry</h3>
            <button type="button" class="att-close-btn" onclick="closeAttendanceEntry()">&times;</button>
        </div>
        <div class="att-modal-body" id="attendanceEntryBody">Loading form...</div>
    </div>
</div>

<script>
    let modernTooltipEl = null;
    let modernTooltipTarget = null;
    const isTouchLikeDevice = window.matchMedia('(hover: none), (pointer: coarse), (any-pointer: coarse)').matches
        || ('ontouchstart' in window)
        || (navigator.maxTouchPoints && navigator.maxTouchPoints > 0);

    function ensureModernTooltip() {
        if (modernTooltipEl) return modernTooltipEl;
        modernTooltipEl = document.createElement('div');
        modernTooltipEl.className = 'modern-tooltip';
        modernTooltipEl.setAttribute('role', 'tooltip');
        document.body.appendChild(modernTooltipEl);
        return modernTooltipEl;
    }

    function hideModernTooltip() {
        if (!modernTooltipEl) return;
        modernTooltipEl.classList.remove('is-show');
        modernTooltipTarget = null;
    }

    function positionModernTooltip(target) {
        if (!modernTooltipEl || !target) return;
        const rect = target.getBoundingClientRect();
        const tipRect = modernTooltipEl.getBoundingClientRect();
        const gap = 10;
        let top = rect.top - tipRect.height - gap;
        if (top < 8) {
            top = rect.bottom + gap;
        }
        let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
        const maxLeft = window.innerWidth - tipRect.width - 8;
        left = Math.max(8, Math.min(left, maxLeft));
        modernTooltipEl.style.top = `${top}px`;
        modernTooltipEl.style.left = `${left}px`;
    }

    function showModernTooltip(target) {
        if (isTouchLikeDevice) return;
        const text = (target.getAttribute('data-modern-tooltip') || '').trim();
        if (!text) return;
        const tip = ensureModernTooltip();
        modernTooltipTarget = target;
        tip.textContent = text;
        tip.classList.add('is-show');
        positionModernTooltip(target);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof crmDataTable !== 'function') return;

        try {
            crmDataTable('#attendanceTable', {
                pageLength: 10,
                lengthMenu: [5, 10, 20, 50, 100],
                ordering: true,
                scrollX: false,
                responsive: false,
                searchPlaceholder: 'Search attendance...',
                columnDefs: [
                    { orderable: false, targets: 8 }
                ],
                dom:
                    "<'dt-top'lf>" +
                    "rt" +
                    "<'dt-bottom'ip>"
            });

            setTimeout(function () {
                const controls = document.querySelector('.dt-top');
                const target = document.getElementById('datatableControls');
                if (controls && target) {
                    target.appendChild(controls);
                }
            }, 100);
        } catch (e) {}
    });

    document.addEventListener('mouseover', function (e) {
        if (isTouchLikeDevice) return;
        const target = e.target.closest('[data-modern-tooltip]');
        if (!target) {
            hideModernTooltip();
            return;
        }
        showModernTooltip(target);
    });

    document.addEventListener('mouseout', function (e) {
        if (isTouchLikeDevice) return;
        const from = e.target.closest('[data-modern-tooltip]');
        if (!from) return;
        const to = e.relatedTarget ? e.relatedTarget.closest('[data-modern-tooltip]') : null;
        if (from !== to) {
            hideModernTooltip();
        }
    });

    document.addEventListener('focusin', function (e) {
        if (isTouchLikeDevice) return;
        const target = e.target.closest('[data-modern-tooltip]');
        if (!target) return;
        showModernTooltip(target);
    });

    document.addEventListener('focusout', function (e) {
        if (isTouchLikeDevice) return;
        const target = e.target.closest('[data-modern-tooltip]');
        if (!target) return;
        hideModernTooltip();
    });

    window.addEventListener('scroll', function () {
        if (modernTooltipTarget) {
            positionModernTooltip(modernTooltipTarget);
        }
    }, true);

    window.addEventListener('resize', function () {
        if (modernTooltipTarget) {
            positionModernTooltip(modernTooltipTarget);
        }
    });

    let activeAttendanceRegistrationId = 0;
    let activeAttendanceStudentName = '';
    let activeAttendanceMonth = '';

    async function fetchAttendanceHtml(url) {
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return await res.text();
    }

    async function loadAttendanceCalendar(registrationId, studentName, month = '') {
        activeAttendanceRegistrationId = registrationId;
        activeAttendanceStudentName = studentName;
        activeAttendanceMonth = month || new Date().toISOString().slice(0, 7);
        document.getElementById('attendanceCalendarTitle').textContent = 'Attendance Calendar - ' + studentName;
        document.getElementById('attendanceCalendarBody').innerHTML = '<div class="att-loading"><span class="att-spinner"></span><span>Loading calendar...</span></div>';
        const url = `index.php?page=attendance&ajax=1&action=calendar&registration_id=${registrationId}&month=${encodeURIComponent(activeAttendanceMonth)}`;
        const html = await fetchAttendanceHtml(url);
        document.getElementById('attendanceCalendarBody').innerHTML = html;
    }

    function openAttendanceCalendar(registrationId, studentName) {
        document.getElementById('attendanceCalendarModal').style.display = 'flex';
        loadAttendanceCalendar(registrationId, studentName);
    }

    function closeAttendanceCalendar() {
        document.getElementById('attendanceCalendarModal').style.display = 'none';
    }

    async function openAttendanceEntry(registrationId, studentName, date) {
        document.getElementById('attendanceEntryTitle').textContent = 'Attendance Entry - ' + studentName;
        document.getElementById('attendanceEntryBody').innerHTML = '<div class="att-loading"><span class="att-spinner"></span><span>Loading form...</span></div>';
        document.getElementById('attendanceEntryModal').style.display = 'flex';
        const url = `index.php?page=attendance&ajax=1&action=entry_form&registration_id=${registrationId}&date=${encodeURIComponent(date)}`;
        const html = await fetchAttendanceHtml(url);
        document.getElementById('attendanceEntryBody').innerHTML = html;

        const statusSelect = document.getElementById('attendanceStatusSelect');
        if (statusSelect) {
            statusSelect.addEventListener('change', syncAttendanceEntryState);
            document.querySelectorAll('input[name="absent_informed"]').forEach(function (radio) {
                radio.addEventListener('change', syncAttendanceEntryState);
            });
            syncAttendanceEntryState();
        }
    }

    function closeAttendanceEntry() {
        document.getElementById('attendanceEntryModal').style.display = 'none';
    }

    function syncAttendanceEntryState() {
        const statusSelect = document.getElementById('attendanceStatusSelect');
        const presentWrap = document.getElementById('presentDetailsWrap');
        const absentWrap = document.getElementById('absentDetailsWrap');
        const absentInfoWrap = document.getElementById('absentInfoDetailsWrap');
        const topicsInput = document.getElementById('topicsTaughtInput');
        const taskInput = document.getElementById('taskGivenInput');
        const absentReasonInput = document.getElementById('absentReasonInput');
        const absentInformedByInput = document.getElementById('absentInformedByInput');
        if (!statusSelect || !presentWrap) return;

        const isPresent = statusSelect.value === 'Present';
        const isAbsent = statusSelect.value === 'Absent';
        const informedRadio = document.querySelector('input[name="absent_informed"]:checked');
        const hasInformed = isAbsent && informedRadio && informedRadio.value === 'yes';

        presentWrap.style.display = isPresent ? 'block' : 'none';
        if (absentWrap) {
            absentWrap.style.display = isAbsent ? 'block' : 'none';
        }
        if (absentInfoWrap) {
            absentInfoWrap.style.display = hasInformed ? 'block' : 'none';
        }
        if (topicsInput) {
            topicsInput.required = isPresent;
            if (!isPresent) topicsInput.value = '';
        }
        if (taskInput) {
            taskInput.required = isPresent;
            if (!isPresent) taskInput.value = '';
        }
        document.querySelectorAll('input[name="absent_informed"]').forEach(function (radio) {
            radio.required = isAbsent;
            if (!isAbsent) {
                radio.checked = false;
            }
        });
        if (absentReasonInput) {
            absentReasonInput.required = hasInformed;
            if (!hasInformed) absentReasonInput.value = '';
        }
        if (absentInformedByInput) {
            absentInformedByInput.required = hasInformed;
            if (!hasInformed) absentInformedByInput.value = '';
        }
    }

    document.addEventListener('submit', async function (e) {
        if (!(e.target && e.target.id === 'attendanceEntryForm')) return;

        e.preventDefault();
        const formData = new FormData(e.target);
        const submitBtn = e.target.querySelector('button[type="submit"]');
        try {
            if (submitBtn) {
                submitBtn.classList.add('is-loading');
                submitBtn.dataset.originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = 'Saving...';
            }
            const res = await fetch('index.php?page=attendance', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const json = await res.json();
            if (!json || json.status !== 'success') {
                Swal.fire({
                    icon: 'error',
                    title: 'Attendance Not Saved',
                    text: (json && json.message) ? json.message : 'Unable to save attendance.',
                    confirmButtonColor: '#e91e63'
                });
                return;
            }
            closeAttendanceEntry();
            closeAttendanceCalendar();
            Swal.fire({
                icon: 'success',
                title: 'Attendance Saved',
                text: json.message || 'Attendance saved successfully.',
                confirmButtonColor: '#e91e63'
            });
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Attendance Not Saved',
                text: 'Unable to save attendance.',
                confirmButtonColor: '#e91e63'
            });
        } finally {
            if (submitBtn) {
                submitBtn.classList.remove('is-loading');
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Save';
            }
        }
    });

    document.getElementById('attendanceCalendarModal').addEventListener('click', function (e) {
        if (e.target === this) closeAttendanceCalendar();
    });

    document.getElementById('attendanceEntryModal').addEventListener('click', function (e) {
        if (e.target === this) closeAttendanceEntry();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAttendanceEntry();
            closeAttendanceCalendar();
        }
    });
</script>


