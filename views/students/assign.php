<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/lead.css">
<?php
// =====================================
// Students - Assign To Staff
// Slug: students/assign
// File: views/students/assign.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('students/assign');
}

if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$roleName = strtolower(trim((string) ($_SESSION['role_name'] ?? '')));
$canSeeAllStudentAssignments = in_array($roleName, ['super admin', 'hr'], true);

$canAllBranches = 0;
try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

/* Staff list */
$staffUsers = [];
try {
    if ($canAllBranches === 1) {
        $st = $pdo->query("
            SELECT u.id, u.name, b.branch_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN branches b ON b.id = u.branch_id
            WHERE u.status = 1
              AND r.role_name = 'Staff'
            ORDER BY u.name ASC
        ");
    } else {
        $st = $pdo->prepare("
            SELECT u.id, u.name, b.branch_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN branches b ON b.id = u.branch_id
            WHERE u.status = 1
              AND r.role_name = 'Staff'
              AND u.branch_id = ?
            ORDER BY u.name ASC
        ");
        $st->execute([$branchId]);
    }
    $staffUsers = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staffUsers = [];
}

/* Save assignment */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_student'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid CSRF token.');
        redirect('index.php?page=students/assign');
        exit;
    }

    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $staffId = (int) ($_POST['staff_id'] ?? 0);

    $internshipDays = isset($_POST['internship_days']) && $_POST['internship_days'] !== ''
        ? (int) ($_POST['internship_days'])
        : null;

    $internshipBatch = trim((string) ($_POST['internship_batch'] ?? ''));
    $internshipBatch = $internshipBatch === '' ? null : $internshipBatch;

    if ($registrationId <= 0 || $staffId <= 0) {
        setFlash('error', 'Invalid assignment request.');
        redirect('index.php?page=students/assign');
        exit;
    }

    try {
        if ($canAllBranches === 1) {
            $st = $pdo->prepare("
                SELECT u.id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.id = ? AND u.status = 1 AND r.role_name = 'Staff'
                LIMIT 1
            ");
            $st->execute([$staffId]);
        } else {
            $st = $pdo->prepare("
                SELECT u.id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.id = ? AND u.status = 1 AND r.role_name = 'Staff' AND u.branch_id = ?
                LIMIT 1
            ");
            $st->execute([$staffId, $branchId]);
        }

        if (!(int) $st->fetchColumn()) {
            throw new Exception("Selected staff is invalid for this branch.");
        }

        if ($canAllBranches === 1) {
            $st = $pdo->prepare("
        SELECT id, reg_type, created_by, registration_status
        FROM registrations
        WHERE id = ?
          AND registration_status IN ('active','completed')
        LIMIT 1
    ");
            $st->execute([$registrationId]);
        } else {
            $st = $pdo->prepare("
        SELECT id, reg_type, created_by, registration_status
        FROM registrations
        WHERE id = ?
          AND branch_id = ?
          AND registration_status IN ('active','completed')
        LIMIT 1
    ");
            $st->execute([$registrationId, $branchId]);
        }

        $registrationRow = $st->fetch(PDO::FETCH_ASSOC);

        if (!$registrationRow) {
            throw new Exception("Student record not found or access denied.");
        }

        if (!$canSeeAllStudentAssignments && (int) ($registrationRow['created_by'] ?? 0) !== $userId) {
            throw new Exception("You can only manage students converted by you.");
        }

        $regType = trim((string) ($registrationRow['reg_type'] ?? ''));

        if ($regType === 'internship') {
            if (!in_array((int) $internshipDays, [7, 15, 21, 30], true)) {
                throw new Exception("Please select valid internship days.");
            }

            if ($internshipBatch === null) {
                throw new Exception("Please select internship batch.");
            }
        } else {
            $internshipDays = null;
            $internshipBatch = null;
        }

        if ($regType === 'internship') {
            $upd = $pdo->prepare("
                INSERT INTO registration_internships (
                    registration_id,
                    guide_staff_id,
                    assigned_by,
                    assigned_at,
                    internship_days,
                    internship_batch,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    guide_staff_id = VALUES(guide_staff_id),
                    assigned_by = VALUES(assigned_by),
                    assigned_at = VALUES(assigned_at),
                    internship_days = VALUES(internship_days),
                    internship_batch = VALUES(internship_batch),
                    updated_at = NOW()
            ");
            $upd->execute([
                $registrationId,
                $staffId,
                $userId > 0 ? $userId : null,
                $internshipDays,
                $internshipBatch,
            ]);
        } else {
            $upd = $pdo->prepare("
                INSERT INTO registration_courses (
                    registration_id,
                    guide_staff_id,
                    assigned_by,
                    assigned_at,
                    course_status,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, NOW(), ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    guide_staff_id = VALUES(guide_staff_id),
                    assigned_by = VALUES(assigned_by),
                    assigned_at = VALUES(assigned_at),
                    course_status = VALUES(course_status),
                    updated_at = NOW()
            ");
            $upd->execute([
                $registrationId,
                $staffId,
                $userId > 0 ? $userId : null,
                (string) ($registrationRow['registration_status'] ?? 'active'),
            ]);
        }

        setFlash('success', 'Student assigned successfully.');
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect('index.php?page=students/assign');
    exit;
}

/* Filters */
$q = trim($_GET['q'] ?? '');
$staffFilter = (int) ($_GET['staff_id'] ?? 0);

$perPage = 15;

/* Where */
$where = ["r.registration_status IN ('active','completed')"];
$params = [];

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if (!$canSeeAllStudentAssignments) {
    $where[] = "r.created_by = ?";
    $params[] = $userId;
}

if ($staffFilter > 0) {
    $where[] = "COALESCE(rc.guide_staff_id, ri.guide_staff_id) = ?";
    $params[] = $staffFilter;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(
        r.registration_no LIKE ?
        OR r.enquiry_snapshot_name LIKE ?
        OR r.enquiry_snapshot_phone LIKE ?
        OR r.program_name LIKE ?
        OR r.batch_name LIKE ?
    )";
    array_push($params, $like, $like, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* Count */
$totalRows = 0;
try {
    $cnt = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations r
        LEFT JOIN registration_courses rc ON rc.registration_id = r.id AND r.reg_type = 'course'
        LEFT JOIN registration_internships ri ON ri.registration_id = r.id AND r.reg_type = 'internship'
        $whereSql
    ");
    $cnt->execute($params);
    $totalRows = (int) $cnt->fetchColumn();
} catch (Exception $e) {
    $totalRows = 0;
}

/* Summary */
$summary = ['assigned' => 0, 'unassigned' => 0];
try {
    $sumWhere = ["r.registration_status IN ('active','completed')"];
    $sumParams = [];

    if ($canAllBranches !== 1 && $branchId > 0) {
        $sumWhere[] = "r.branch_id = ?";
        $sumParams[] = $branchId;
    }

    if (!$canSeeAllStudentAssignments) {
        $sumWhere[] = "r.created_by = ?";
        $sumParams[] = $userId;
    }

    $sumSql = 'WHERE ' . implode(' AND ', $sumWhere);

    $st = $pdo->prepare("
        SELECT
            SUM(CASE WHEN COALESCE(rc.guide_staff_id, ri.guide_staff_id) IS NOT NULL THEN 1 ELSE 0 END) AS assigned_count,
            SUM(CASE WHEN COALESCE(rc.guide_staff_id, ri.guide_staff_id) IS NULL THEN 1 ELSE 0 END) AS unassigned_count
        FROM registrations r
        LEFT JOIN registration_courses rc ON rc.registration_id = r.id AND r.reg_type = 'course'
        LEFT JOIN registration_internships ri ON ri.registration_id = r.id AND r.reg_type = 'internship'
        $sumSql
    ");
    $st->execute($sumParams);
    $x = $st->fetch(PDO::FETCH_ASSOC);

    if ($x) {
        $summary['assigned'] = (int) ($x['assigned_count'] ?? 0);
        $summary['unassigned'] = (int) ($x['unassigned_count'] ?? 0);
    }
} catch (Exception $e) {
}

/* Rows */
$rows = [];
try {
    $sql = "
        SELECT
    r.id,
    r.registration_no,
    r.enquiry_snapshot_name,
    r.enquiry_snapshot_phone,
    r.program_name,
    r.batch_name,
    r.reg_type,
    ri.internship_days,
    ri.internship_batch,
    COALESCE(rc.guide_staff_id, ri.guide_staff_id) AS guide_staff_id,
    guide_u.name AS assigned_staff
FROM registrations r
LEFT JOIN registration_courses rc ON rc.registration_id = r.id AND r.reg_type = 'course'
LEFT JOIN registration_internships ri ON ri.registration_id = r.id AND r.reg_type = 'internship'
LEFT JOIN users guide_u ON guide_u.id = COALESCE(rc.guide_staff_id, ri.guide_staff_id)
        $whereSql
        ORDER BY r.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $totalRows = count($rows);
} catch (Exception $e) {
    $rows = [];
    $totalRows = 0;
}
?>

<div class="leads-dashboard">
    <div class="dashboard-header">
        <h2><i class="fas fa-user-tag" style="margin-right: 12px; color: #e91e63;"></i>Assign Students</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-list"></i> Total: <?= (int) $totalRows ?></span>
            <span class="stat-item"><i class="fas fa-user-check"></i> Assigned: <?= (int) $summary['assigned'] ?></span>
            <span class="stat-item"><i class="fas fa-user-clock"></i> Unassigned: <?= (int) $summary['unassigned'] ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-sliders-h" style="margin-right: 8px;"></i> Filter Students
        </div>
        <form method="GET" action="index.php" class="filter-form">
            <input type="hidden" name="page" value="students/assign">
            <div class="filter-grid">
                <div class="filter-item">
                    <label><i class="fas fa-search"></i> Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / student / phone / program">
                </div>

                <div class="filter-item">
                    <label><i class="fas fa-user-check"></i> Assigned Staff</label>
                    <select name="staff_id" data-modern-select="on">
                        <option value="">All Staff</option>
                        <?php foreach ($staffUsers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $staffFilter === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= h($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-icon-only apply" title="Apply filters">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="index.php?page=students/assign" class="btn-icon-only reset" title="Reset filters">
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
                    <i class="fas fa-list"></i> Student Assignment Queue
                </div>
                <div id="datatableControls"></div>
            </div>
        </div>

        <div class="table-container stu-table-wrap">
            <table class="leads-table stu-table" id="assignStudentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Registration</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Type</th>
                        <th>Current Staff</th>
                        <th class="text-center">Assign Staff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?= (int) ($i + 1) ?></td>
                            <td><strong><?= h($r['registration_no'] ?: '-') ?></strong></td>
                            <td>
                                <div class="stu-name"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                <div class="stu-sub"><?= h(visibleStudentContactValue($r['enquiry_snapshot_phone'] ?? '')) ?></div>
                            </td>
                            <td>
                                <div><?= h($r['program_name'] ?: '-') ?></div>
                                <div class="stu-sub"><?= h($r['batch_name'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div class="stu-type-block"><?= h(ucfirst($r['reg_type'] ?: '-')) ?></div>
                                <?php if (($r['reg_type'] ?? '') === 'internship'): ?>
                                    <div class="stu-sub">
                                        <?= h($r['internship_days'] ?: '-') ?> Days
                                        <?= !empty($r['internship_batch']) ? ' | ' . h($r['internship_batch']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="stu-current-staff" title="<?= h($r['assigned_staff'] ?: '-') ?>">
                                    <?= h($r['assigned_staff'] ?: '-') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <form method="POST" class="stu-assign-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                                    <input type="hidden" name="assign_student" value="1">
                                    <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">

                                    <select name="staff_id" class="stu-assign-main" data-modern-select="on" title="<?= h($r['assigned_staff'] ?: 'Select Staff') ?>" required>
                                        <option value="">Select Staff</option>
                                        <?php foreach ($staffUsers as $s): ?>
                                            <option value="<?= (int) $s['id'] ?>" <?= ((int) $r['guide_staff_id'] === (int) $s['id']) ? 'selected' : '' ?>>
                                                <?= h($s['name']) ?><?= !empty($s['branch_name']) ? ' (' . h($s['branch_name']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if (($r['reg_type'] ?? '') === 'internship'): ?>
                                        <select name="internship_days" class="stu-assign-mini" data-modern-select="on" required>
                                            <option value="">Days</option>
                                            <option value="7" <?= ((int) $r['internship_days'] === 7) ? 'selected' : '' ?>>7 Days</option>
                                            <option value="15" <?= ((int) $r['internship_days'] === 15) ? 'selected' : '' ?>>15 Days</option>
                                            <option value="21" <?= ((int) $r['internship_days'] === 21) ? 'selected' : '' ?>>21 Days</option>
                                            <option value="30" <?= ((int) $r['internship_days'] === 30) ? 'selected' : '' ?>>30 Days</option>
                                        </select>

                                        <select name="internship_batch" class="stu-assign-mini" data-modern-select="on" required>
                                            <option value="">Batch</option>
                                            <option value="Morning" <?= (($r['internship_batch'] ?? '') === 'Morning') ? 'selected' : '' ?>>Morning</option>
                                            <option value="Evening" <?= (($r['internship_batch'] ?? '') === 'Evening') ? 'selected' : '' ?>>Evening</option>
                                            <option value="Afternoon" <?= (($r['internship_batch'] ?? '') === 'Afternoon') ? 'selected' : '' ?>>Afternoon</option>
                                        </select>
                                    <?php endif; ?>

                                    <button
                                        class="stu-save-btn"
                                        type="submit"
                                        data-modern-tooltip="Save assignment"
                                        data-mobile-label="Save"
                                        aria-label="Save assignment"
                                    >
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="datatableFooter"></div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    if (typeof crmDataTable === "function" && document.querySelector('#assignStudentsTable')) {
        crmDataTable('#assignStudentsTable', {
            pageLength: <?= (int) $perPage ?>,
            lengthMenu: [5, 10, 15, 20, 50, 100],
            ordering: true,
            searchPlaceholder: "Search students...",
            language: {
                emptyTable: "No registered students found"
            },
            dom: "<'dt-top'lf>rt<'dt-bottom'ip>"
        });
    }

    setTimeout(() => {
        const controls = document.querySelector('.stu-table-wrap .dt-top');
        const footer = document.querySelector('.stu-table-wrap .dt-bottom');
        const topTarget = document.getElementById('datatableControls');
        const bottomTarget = document.getElementById('datatableFooter');
        if (controls && topTarget) {
            topTarget.appendChild(controls);
        }
        if (footer && bottomTarget) {
            bottomTarget.appendChild(footer);
        }
    }, 100);
});
</script>


