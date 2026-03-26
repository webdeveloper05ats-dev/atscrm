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
            r.program_name,
            r.batch_name
        FROM registrations r
        LEFT JOIN registration_courses rc ON rc.registration_id = r.id
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
        </div>

        <div class="att-legend">
            <span class="att-legend-item"><i class="att-dot att-dot-present"></i> Present</span>
            <span class="att-legend-item"><i class="att-dot att-dot-absent"></i> Absent</span>
            <span class="att-legend-item"><i class="att-dot att-dot-empty"></i> Not Marked</span>
            <span class="att-legend-item"><i class="att-dot att-dot-locked"></i> Locked</span>
        </div>

        <div class="att-form-note" style="margin-bottom:16px;">
            <?php if ($readOnlyMode): ?>
                View only access. Attendance is visible from <b><?= h($attendanceStartDate) ?></b> up to today.
            <?php else: ?>
                Attendance can be marked only from <b><?= h($attendanceStartDate) ?></b> up to today.
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
                $isLockedDate = ($isBeforeAssignment || $isFutureDate);
                $canOpenDay = !$isLockedDate;
                $cellClass = 'att-status-empty';
                if ($status === 'present') {
                    $cellClass = 'att-status-present';
                } elseif ($status === 'absent') {
                    $cellClass = 'att-status-absent';
                }
                if ($isLockedDate) {
                    $cellClass .= ' att-status-locked';
                }
                $lockTitle = $isBeforeAssignment
                    ? 'Dates before staff assignment are locked'
                    : 'Future dates are locked';
                if ($readOnlyMode && !$isLockedDate) {
                    $lockTitle = 'View attendance details';
                }
                ?>
                <button type="button"
                    class="att-grid-cell att-grid-day <?= h($cellClass) ?> <?= $date === $today ? 'att-day-today' : '' ?>"
                    <?= !$canOpenDay ? 'disabled title="' . h($lockTitle) . '"' : "onclick=\"openAttendanceEntry(" . (int) $student['id'] . ", '" . h($student['enquiry_snapshot_name']) . "', '" . h($date) . "', " . ($readOnlyMode ? 'true' : 'false') . ")\" title=\"" . h($lockTitle) . "\"" ?>>
                    <span class="att-day-no"><?= (int) $day ?></span>
                    <span class="att-day-text"><?= $isLockedDate ? 'Locked' : ($status === 'present' ? 'Present' : ($status === 'absent' ? 'Absent' : ($readOnlyMode ? 'View' : 'Mark'))) ?></span>
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([
            $registrationId,
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
$page = (int) ($_GET['p'] ?? 1);
if ($page < 1) {
    $page = 1;
}
$perPage = 12;
$offset = ($page - 1) * $perPage;

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

$whereSql = "WHERE " . implode(" AND ", $where);

$totalRows = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM registrations r LEFT JOIN registration_courses rc ON rc.registration_id = r.id {$whereSql}");
    $st->execute($params);
    $totalRows = (int) $st->fetchColumn();
} catch (Exception $e) {
    $totalRows = 0;
}

$totalPages = (int) ceil($totalRows / $perPage);
if ($totalPages < 1) {
    $totalPages = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
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
        LIMIT {$perPage} OFFSET {$offset}
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

$baseUrl = "index.php?page=attendance&q=" . urlencode($q);
?>

<h2 style="margin-bottom:20px;">Student Attendance</h2>

<div class="card">
    <div class="card-header">Filters</div>
    <form method="GET" action="index.php">
        <input type="hidden" name="page" value="attendance">
        <div class="att-filter-row">
            <div>
                <label>Search</label>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / name / phone / program">
            </div>
            <div class="att-filter-actions">
                <button class="btn btn-primary">Apply</button>
                <a href="index.php?page=attendance" class="btn" style="background:#f3f4f6;">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px;">
    <div class="card-header"><?= $attendanceReadOnlyViewer ? 'All Course Students Attendance (View Only) (' . (int) $totalRows . ')' : 'Assigned Course Students (' . (int) $totalRows . ')' ?></div>
    <div class="att-table-wrap">
        <table class="att-table">
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
                                title="Attendance Calendar">
                                <i class="fas fa-calendar-check"></i>
                            </button>
                            <a href="index.php?page=reports/student_schedule&registration_id=<?= (int) $r['id'] ?>" class="att-icon-btn att-icon-btn-report" title="Student Schedule Report">
                                <i class="fas fa-file-alt"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="att-pager">
        <a href="<?= $baseUrl ?>&p=1"><i class="fas fa-angle-double-left"></i></a>
        <a href="<?= $baseUrl ?>&p=<?= max(1, $page - 1) ?>"><i class="fas fa-angle-left"></i></a>
        <span class="att-page-info">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
        <a href="<?= $baseUrl ?>&p=<?= min($totalPages, $page + 1) ?>"><i class="fas fa-angle-right"></i></a>
        <a href="<?= $baseUrl ?>&p=<?= (int) $totalPages ?>"><i class="fas fa-angle-double-right"></i></a>
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
    .att-filter-row { display:flex; gap:16px; align-items:end; flex-wrap:wrap; }
    .att-filter-row input { min-width:280px; }
    .att-filter-actions { display:flex; gap:10px; align-items:center; }
    .att-table-wrap { padding:16px; }
    .att-table { width:100%; border-collapse:collapse; }
    .att-table th { background:#f5f6fa; padding:14px; text-align:left; font-weight:700; }
    .att-table td { padding:14px; border-bottom:1px solid #eee; }
    .att-primary { font-weight:700; color:#111827; }
    .att-sub { font-size:12px; color:#6b7280; }
    .att-reg-badge { font-weight:700; color:#2e7d32; }
    .att-summary-chip { display:inline-flex; align-items:center; justify-content:center; min-width:44px; padding:6px 10px; border-radius:999px; font-weight:800; font-size:12px; }
    .att-summary-present { background:#eaf7ee; color:#2e7d32; }
    .att-summary-absent { background:#fdecec; color:#c62828; }
    .att-icon-btn { width:38px; height:38px; border-radius:10px; border:none; background:#e8f4fd; color:#1565c0; cursor:pointer; }
    .att-icon-btn-report { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; background:#fff7ed; color:#c2410c; margin-left:6px; }
    .att-pager { display:flex; justify-content:center; align-items:center; gap:8px; padding:16px; }
    .att-pager a { width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:8px; border:1px solid #ddd; text-decoration:none; color:#333; }
    .att-page-info { padding:0 8px; font-weight:600; }
    .att-modal-backdrop { position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:9998; align-items:center; justify-content:center; padding:20px; }
    .att-modal, .att-entry-modal { width:min(980px, 96vw); max-height:90vh; overflow:auto; background:#fff; border-radius:18px; box-shadow:0 30px 80px rgba(0, 0, 0, .25); }
    .att-entry-modal { width:min(640px, 94vw); }
    .att-modal-header { display:flex; justify-content:space-between; align-items:center; padding:16px 18px; border-bottom:1px solid #eee; }
    .att-modal-body { padding:18px; }
    .att-close-btn { width:40px; height:40px; border:none; border-radius:10px; background:#f3f4f6; font-size:24px; cursor:pointer; }
    .att-calendar-toolbar { display:flex; justify-content:center; align-items:center; gap:12px; margin-bottom:16px; }
    .att-calendar-title { min-width:180px; text-align:center; font-size:20px; font-weight:800; color:#111827; }
    .att-month-btn { min-width:42px; }
    .att-month-btn-disabled { opacity:.45; cursor:not-allowed; }
    .att-student-meta { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; margin-bottom:16px; padding:14px; border-radius:14px; background:#f8fafc; border:1px solid #eef2f7; }
    .att-legend { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; }
    .att-legend-item { display:inline-flex; align-items:center; gap:8px; font-size:13px; font-weight:700; color:#374151; }
    .att-dot { width:12px; height:12px; border-radius:50%; display:inline-block; }
    .att-dot-present { background:#2e7d32; }
    .att-dot-absent { background:#e53935; }
    .att-dot-empty { background:#cbd5e1; }
    .att-dot-locked { background:#94a3b8; }
    .att-grid { display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); gap:10px; }
    .att-grid-head { padding:10px; text-align:center; font-weight:800; color:#475569; background:#f8fafc; border-radius:10px; }
    .att-grid-cell { min-height:88px; border-radius:14px; }
    .att-grid-cell-empty { background:transparent; }
    .att-grid-day { border:1px solid #e5e7eb; background:#fff; display:flex; flex-direction:column; align-items:flex-start; justify-content:space-between; padding:10px; cursor:pointer; text-align:left; }
    .att-grid-day:hover { transform:translateY(-1px); box-shadow:0 10px 24px rgba(15, 23, 42, .08); }
    .att-grid-day:disabled { cursor:not-allowed; transform:none; box-shadow:none; }
    .att-day-no { font-size:18px; font-weight:800; color:#111827; }
    .att-day-text { font-size:12px; font-weight:700; }
    .att-status-present { background:#eaf7ee; border-color:#9bd3a7; }
    .att-status-present .att-day-text { color:#2e7d32; }
    .att-status-absent { background:#fdecec; border-color:#f1a6a6; }
    .att-status-absent .att-day-text { color:#c62828; }
    .att-status-empty .att-day-text { color:#64748b; }
    .att-status-locked { background:#f8fafc; border-color:#e2e8f0; opacity:.72; }
    .att-status-locked .att-day-text { color:#94a3b8; }
    .att-day-today { outline:2px solid #1565c0; }
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
    .att-form-note { padding:12px 14px; border-radius:12px; background:#eff6ff; color:#1d4ed8; font-weight:700; margin-bottom:14px; }
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
    @keyframes attSpin {
        to { transform:rotate(360deg); }
    }

    @media (max-width: 900px) {
        .att-student-meta, .att-entry-grid { grid-template-columns:1fr; }
        .att-grid { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .att-grid-head { display:none; }
    }
</style>

<script>
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
            await loadAttendanceCalendar(activeAttendanceRegistrationId, activeAttendanceStudentName, activeAttendanceMonth);
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
