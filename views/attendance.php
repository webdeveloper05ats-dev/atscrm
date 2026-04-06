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
                    <button type="submit" class="btn-icon-only apply" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="index.php?page=attendance" class="btn-icon-only reset" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                        <i class="fas fa-undo-alt"></i>
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
                                data-modern-tooltip="Attendance Calendar"
                                aria-label="Attendance Calendar">
                                <i class="fas fa-calendar-check"></i>
                            </button>
                            <a href="index.php?page=reports/student_schedule&registration_id=<?= (int) $r['id'] ?>" class="att-icon-btn att-icon-btn-report" data-modern-tooltip="Student Schedule Report" aria-label="Student Schedule Report">
                                <i class="fas fa-file-alt"></i>
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

<style>
    :root {
        --primary: #e91e63;
        --primary-light: #f8bbd0;
        --primary-dark: #c2185b;
        --gray-200: #e9ecef;
        --gray-300: #dee2e6;
        --gray-400: #ced4da;
        --gray-700: #495057;
        --gray-800: #343a40;
        --gray-900: #212529;
    }
    .payments-dashboard.attendance-dashboard {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .dashboard-header h2 {
        margin: 0;
        font-size: 28px;
        font-weight: 900;
        color: var(--gray-800);
        letter-spacing: .2px;
    }
    .header-stats {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .stat-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: #fff5f9;
        color: var(--primary-dark);
        font-size: 13px;
        font-weight: 800;
        border: 1px solid #f5d6e3;
    }
    .card-header {
        font-weight: 900;
        font-size: 16px;
        color: var(--gray-800);
        border-bottom: 1px solid #f2f2f2;
    }
    .table-header-flex {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        width: 100%;
        flex-wrap: nowrap;
    }
    .table-title {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 900;
        color: var(--gray-800);
        flex: 1 1 auto;
        min-width: 0;
        white-space: nowrap;
    }
    .filter-form {
        padding: 12px 14px;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: minmax(240px, 1fr) minmax(180px, 220px) minmax(180px, 220px) auto;
        gap: 14px;
        align-items: end;
    }
    .filter-item label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 800;
        color: #5f6b7a;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .filter-item input {
        width: 100%;
        border: 1px solid #d7dde5;
        border-radius: 10px;
        min-height: 42px;
        padding: 10px 12px;
        background: #fff;
        outline: none;
        transition: .15s ease;
    }
    .filter-item input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .14);
    }
    .filter-actions {
        display: inline-flex;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }
    .btn-icon-only {
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        cursor: pointer;
        transition: .15s ease;
    }
    .btn-icon-only.apply {
        background: var(--primary);
        color: #fff;
    }
    .btn-icon-only.apply:hover {
        background: var(--primary-dark);
    }
    .btn-icon-only.reset {
        background: #f1f3f5;
        color: var(--primary-dark);
    }
    .btn-icon-only.reset:hover {
        background: #e9ecef;
    }
    .table-container {
        padding: 12px 14px 16px;
    }
    .crm-table-wrapper {
        width: 100%;
        overflow-x: auto;
    }
    #attendanceTable.crm-table {
        width: 100%;
        border-collapse: collapse;
    }
    #attendanceTable.crm-table th,
    #attendanceTable.crm-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
        font-size: 13px;
    }
    #attendanceTable.crm-table th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .35px;
        font-weight: 800;
        color: var(--gray-800);
        background: #fafbfd;
    }
    #attendanceTable.crm-table tbody tr:hover {
        background: #fff5f9;
    }
    .att-primary { font-weight: 800; color: #111827; }
    .att-sub { font-size: 12px; color: #6b7280; }
    .att-reg-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        background: #eaf7ee;
        color: #2e7d32;
        font-weight: 800;
        font-size: 12px;
    }
    .att-summary-chip { display:inline-flex; align-items:center; justify-content:center; min-width:44px; padding:6px 10px; border-radius:999px; font-weight:800; font-size:12px; }
    .att-summary-present { background:#eaf7ee; color:#2e7d32; }
    .att-summary-absent { background:#fdecec; color:#c62828; }
    .att-icon-btn {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        background: #ffeaf3;
        color: var(--primary-dark);
    }
    .att-icon-btn:hover {
        background: #ffd3e5;
    }
    .att-icon-btn-report {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
        margin-left:6px;
        background:#fff0f6;
        color:var(--primary-dark);
    }
    #datatableControls {
        width: auto;
        margin-left: 0;
        display: flex;
        justify-content: flex-end;
        flex: 0 0 auto;
    }
    #datatableControls .dt-top {
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:12px;
        flex-wrap:nowrap;
    }
    .dataTables_wrapper .dt-top,
    .dataTables_wrapper .dt-bottom { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .dataTables_wrapper .dt-bottom { justify-content:space-between; margin-top:12px; }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter { margin:0; }
    .dataTables_wrapper .dataTables_length {
        display:inline-flex !important;
        align-items:center;
        width:auto !important;
        white-space:nowrap !important;
        flex:0 0 auto;
    }
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label { display:flex; align-items:center; gap:8px; font-weight:700; color:#334155; margin:0; white-space:nowrap !important; flex-wrap:nowrap !important; }
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter { display:flex; align-items:center; }
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border:1px solid var(--gray-300);
        border-radius:10px;
        padding:8px 12px;
        background:#fff;
        min-height:38px;
        outline:none;
    }
    .dataTables_wrapper .dataTables_filter input { min-width: 240px; }
    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .14);
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border:1px solid #f1d6e3 !important;
        background:#fff !important;
        color:var(--gray-700) !important;
        border-radius:8px !important;
        padding:6px 10px !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background:var(--primary) !important;
        border-color:var(--primary) !important;
        color:#fff !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background:#fff5f9 !important;
        color:var(--primary-dark) !important;
        border-color:#f1d6e3 !important;
    }
    .dataTables_wrapper .dataTables_info { color:#64748b; font-weight:600; }
    .att-modal-backdrop {
        position: fixed;
        inset: 0;
        background: radial-gradient(circle at top, rgba(30, 41, 59, .42), rgba(15, 23, 42, .62));
        backdrop-filter: blur(6px);
        z-index: 9998;
        align-items: center;
        justify-content: center;
        padding: 22px;
    }
    .att-modal, .att-entry-modal {
        width: min(1020px, 96vw);
        max-height: 90vh;
        overflow: auto;
        background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
        border-radius: 22px;
        border: 1px solid #e8edf5;
        box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
    }
    .att-entry-modal { width: min(680px, 94vw); }
    .att-modal-header {
        position: sticky;
        top: 0;
        z-index: 4;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 18px;
        border-bottom: 1px solid #edf2f7;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(255,255,255,.94));
        backdrop-filter: blur(4px);
    }
    .att-modal-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 900;
        color: #0f172a;
    }
    .att-modal-body { padding: 18px; }
    .att-close-btn {
        width: 38px;
        height: 38px;
        border: 1px solid #e5e7eb;
        border-radius: 11px;
        background: #f8fafc;
        color: #334155;
        font-size: 22px;
        cursor: pointer;
        transition: .15s ease;
    }
    .att-close-btn:hover {
        background: #eef2ff;
        border-color: #cbd5e1;
    }
    .att-calendar-toolbar {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        padding: 10px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px solid #edf2f7;
    }
    .att-calendar-title {
        min-width: 220px;
        text-align: center;
        font-size: 24px;
        font-weight: 900;
        color: #0f172a;
        letter-spacing: .2px;
    }
    .att-month-btn {
        min-width: 40px;
        height: 36px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
    }
    .att-month-btn-disabled { opacity: .45; cursor: not-allowed; }
    .att-student-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 14px;
        padding: 14px;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fbff, #f8fafc);
        border: 1px solid #e8eef6;
    }
    .att-student-meta b {
        color: #334155;
    }
    .att-legend {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 14px;
        padding: 10px 12px;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #edf2f7;
    }
    .att-legend-item { display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#374151; }
    .att-dot { width:12px; height:12px; border-radius:50%; display:inline-block; }
    .att-dot-present { background:#16a34a; }
    .att-dot-absent { background:#dc2626; }
    .att-dot-empty { background:#cbd5e1; }
    .att-dot-pending { background:#f59e0b; }
    .att-dot-locked { background:#94a3b8; }
    
    .att-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 10px;
        padding: 12px;
        border-radius: 14px;
        background: linear-gradient(180deg, #fbfdff, #f8fafc);
        border: 1px solid #edf2f7;
    }
    .att-grid-head {
        padding: 10px;
        text-align: center;
        font-weight: 900;
        color: #334155;
        background: #f1f5f9;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        font-size: 12px;
        letter-spacing: .35px;
        text-transform: uppercase;
    }
    .att-grid-cell { min-height: 92px; border-radius: 12px; }
    .att-grid-cell-empty { background:transparent; }
    .att-grid-day {
        border: 1px solid #dbe4ee;
        background: linear-gradient(180deg, #ffffff, #f8fbff);
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: space-between;
        padding: 10px;
        cursor: pointer;
        text-align: left;
        transition: .18s ease;
    }
    .att-grid-day:hover {
        transform: translateY(-2px);
        border-color: #cbd5e1;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .10);
    }
    .att-grid-day:disabled { cursor:not-allowed; transform:none; box-shadow:none; }
    .att-day-no { font-size: 19px; font-weight: 900; color: #0f172a; }
    .att-day-text { font-size: 12px; font-weight: 800; }
    .att-status-present { background:#16a34a !important; border-color:#15803d !important; }
    .att-status-present .att-day-no, .att-status-present .att-day-text { color:#ffffff !important; }
    .att-status-absent { background:#dc2626 !important; border-color:#b91c1c !important; }
    .att-status-absent .att-day-no, .att-status-absent .att-day-text { color:#ffffff !important; }
    .att-status-empty .att-day-text { color:#64748b; }
    .att-status-pending { background:#fffbeb !important; border-color:#fcd34d !important; opacity: 0.9 !important; }
    .att-status-pending .att-day-text { color:#b45309 !important; }
    .att-status-locked { background:#f8fafc; border-color:#e2e8f0; opacity:.72; box-shadow:none; }
    .att-status-locked .att-day-text { color:#94a3b8; }
    .att-day-today {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37, 99, 235, .20);
    }
    .att-info-box { padding:12px 14px; border:1px solid #eceff3; border-radius:12px; background:#f8fafc; display:flex; flex-direction:column; gap:4px; }
    .att-info-box b { font-size:12px; color:#6b7280; text-transform:uppercase; }
    .att-info-box span { font-weight:700; color:#111827; }
    .att-info-box-full { grid-column:1 / -1; }
    .att-entry-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
    .att-form-row { margin-bottom:14px; }
    .att-form-label { display:block; margin-bottom:6px; font-weight:700; color:#334155; }
    .att-form-control { width:100%; border:1px solid #dbe2ea; border-radius:12px; padding:10px 12px; background:#fff; }
    .att-radio-row { display:flex; gap:18px; flex-wrap:wrap; }
    .att-radio-option { display:inline-flex; align-items:center; gap:8px; font-weight:700; color:#334155; }
    .att-radio-option input { margin:0; }
    .att-form-note {
        padding: 12px 14px;
        border-radius: 12px;
        background: linear-gradient(180deg, #ecf3ff, #e9f1ff);
        color: #1d4ed8;
        font-weight: 800;
        margin-bottom: 14px;
        border: 1px solid #dbeafe;
    }
    .att-form-note-danger { background:#fdecec; color:#c62828; }
    .att-form-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }
    .att-empty-note { color:#64748b; padding:10px 0; }
    .att-empty-note-danger { color:#c62828; font-weight:700; }
    .att-loading {
        display:flex;
        align-items:center;
        justify-content:center;
        gap:10px;
        min-height:140px;
        color:#475569;
        font-weight:700;
    }
    .att-spinner {
        width:22px;
        height:22px;
        border:3px solid #e2e8f0;
        border-top-color:#1565c0;
        border-radius:50%;
        animation:attSpin .8s linear infinite;
    }
    .btn.is-loading {
        opacity:.8;
        pointer-events:none;
    }
    .modern-tooltip {
        position: fixed;
        left: 0;
        top: 0;
        z-index: 100000;
        pointer-events: none;
        background: linear-gradient(180deg, #0f172a, #1e293b);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .16);
        box-shadow: 0 14px 34px rgba(2, 6, 23, .35);
        border-radius: 10px;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
        opacity: 0;
        transform: translateY(-4px);
        transition: opacity .14s ease, transform .14s ease;
    }
    .modern-tooltip.is-show {
        opacity: 1;
        transform: translateY(0);
    }
    .modern-tooltip::after {
        content: "";
        position: absolute;
        left: 50%;
        bottom: -6px;
        width: 10px;
        height: 10px;
        background: #1e293b;
        border-right: 1px solid rgba(255, 255, 255, .14);
        border-bottom: 1px solid rgba(255, 255, 255, .14);
        transform: translateX(-50%) rotate(45deg);
    }
    @keyframes attSpin {
        to { transform:rotate(360deg); }
    }

    @media (max-width: 900px) {
        .table-header-flex { flex-wrap: wrap; align-items: flex-start; }
        .dashboard-header h2 { font-size:24px; }
        .filter-grid { grid-template-columns: 1fr; }
        .filter-actions { justify-content: flex-start; }
        .att-student-meta, .att-entry-grid { grid-template-columns:1fr; }
        .att-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .att-grid-head { display:none; }
        #attendanceTable.crm-table th,
        #attendanceTable.crm-table td { font-size: 12px; padding:10px 8px; }
    }
    @media (max-width: 768px) {
        #datatableControls { width:100%; margin-left:0; justify-content:flex-start; }
        #datatableControls .dt-top,
        .dataTables_wrapper .dt-bottom { justify-content:flex-start; }
        #datatableControls .dt-top { flex-wrap: wrap; }
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length { width:100%; }
        .dataTables_wrapper .dataTables_filter label { width:100%; }
        .dataTables_wrapper .dataTables_filter input { width:100% !important; }
    }
</style>

<script>
    let modernTooltipEl = null;
    let modernTooltipTarget = null;

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
        const target = e.target.closest('[data-modern-tooltip]');
        if (!target) {
            hideModernTooltip();
            return;
        }
        showModernTooltip(target);
    });

    document.addEventListener('mouseout', function (e) {
        const from = e.target.closest('[data-modern-tooltip]');
        if (!from) return;
        const to = e.relatedTarget ? e.relatedTarget.closest('[data-modern-tooltip]') : null;
        if (from !== to) {
            hideModernTooltip();
        }
    });

    document.addEventListener('focusin', function (e) {
        const target = e.target.closest('[data-modern-tooltip]');
        if (!target) return;
        showModernTooltip(target);
    });

    document.addEventListener('focusout', function (e) {
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
