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
        SELECT id, reg_type
        FROM registrations
        WHERE id = ?
          AND registration_status IN ('active','completed')
        LIMIT 1
    ");
            $st->execute([$registrationId]);
        } else {
            $st = $pdo->prepare("
        SELECT id, reg_type
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

        $upd = $pdo->prepare("
    UPDATE registrations
    SET assigned_to = ?,
        internship_days = ?,
        internship_batch = ?,
        updated_at = NOW()
    WHERE id = ?
    LIMIT 1
");
        $upd->execute([
            $staffId,
            $internshipDays,
            $internshipBatch,
            $registrationId
        ]);

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

$page = (int) ($_GET['p'] ?? 1);
if ($page < 1)
    $page = 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

/* Where */
$where = ["r.registration_status IN ('active','completed')"];
$params = [];

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($staffFilter > 0) {
    $where[] = "r.assigned_to = ?";
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
        $whereSql
    ");
    $cnt->execute($params);
    $totalRows = (int) $cnt->fetchColumn();
} catch (Exception $e) {
    $totalRows = 0;
}

$totalPages = (int) ceil($totalRows / $perPage);
if ($totalPages < 1)
    $totalPages = 1;
if ($page > $totalPages)
    $page = $totalPages;

/* Summary */
$summary = ['assigned' => 0, 'unassigned' => 0];
try {
    $sumWhere = ["registration_status IN ('active','completed')"];
    $sumParams = [];

    if ($canAllBranches !== 1 && $branchId > 0) {
        $sumWhere[] = "branch_id = ?";
        $sumParams[] = $branchId;
    }

    $sumSql = 'WHERE ' . implode(' AND ', $sumWhere);

    $st = $pdo->prepare("
        SELECT
            SUM(CASE WHEN assigned_to IS NOT NULL AND assigned_to > 0 THEN 1 ELSE 0 END) AS assigned_count,
            SUM(CASE WHEN assigned_to IS NULL OR assigned_to = 0 THEN 1 ELSE 0 END) AS unassigned_count
        FROM registrations
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
    r.internship_days,
    r.internship_batch,
    r.assigned_to,
    u.name AS assigned_staff
FROM registrations r
LEFT JOIN users u ON u.id = r.assigned_to
        $whereSql
        ORDER BY r.id DESC
        LIMIT $perPage OFFSET $offset
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

$baseUrl = "index.php?page=students/assign"
    . "&q=" . urlencode($q)
    . "&staff_id=" . urlencode((string) $staffFilter);
?>

<style>
    :root {
        --stu-primary: #e91e63;
        --stu-primary-dark: #c2185b;
        --stu-text: #1f2937;
        --stu-muted: #6b7280;
        --stu-card: #ffffff;
        --stu-shadow: 0 16px 40px rgba(15, 23, 42, .06);
    }

    .stu-page {
        background: linear-gradient(180deg, #fff 0%, #fff7fb 18%, #f7f9fd 100%);
        border-radius: 24px;
        padding: 18px;
    }

    .stu-page-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 14px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }

    .stu-page-title h2 {
        margin: 0;
        font-size: 28px;
        font-weight: 900;
        color: var(--stu-text);
    }

    .stu-page-title p {
        margin: 6px 0 0;
        color: var(--stu-muted);
        font-size: 14px;
    }

    .stu-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        color: var(--stu-primary-dark);
        border: 1px solid rgba(233, 30, 99, .12);
        border-radius: 999px;
        padding: 10px 14px;
        font-size: 13px;
        font-weight: 800;
    }

    .stu-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .stu-summary-card {
        position: relative;
        background: var(--stu-card);
        border: 1px solid rgba(15, 23, 42, .06);
        border-radius: 18px;
        padding: 16px;
        box-shadow: var(--stu-shadow);
    }

    .stu-summary-card:before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, var(--stu-primary), #ff6ba6);
    }

    .stu-summary-title {
        font-size: 12px;
        color: var(--stu-muted);
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .5px;
    }

    .stu-summary-value {
        margin-top: 8px;
        font-size: 26px;
        font-weight: 900;
        color: var(--stu-text);
    }

    .stu-filter-row {
        display: grid;
        grid-template-columns: 2fr 1fr auto;
        gap: 12px;
        align-items: end;
    }

    .stu-table thead th {
        white-space: nowrap;
    }

    .stu-name {
        font-weight: 800;
        color: #111827;
    }

    .stu-sub {
        font-size: 12px;
        color: #6b7280;
    }

    .stu-assign-form {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
    }

    .stu-assign-form select {
        min-width: 170px;
    }

    .stu-pager {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
        flex-wrap: wrap;
    }

    .stu-pager a {
        text-decoration: none;
        padding: 7px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #334155;
        font-weight: 700;
        background: #fff;
    }

    .stu-pager a:hover {
        border-color: #f3b4cd;
        color: var(--stu-primary-dark);
    }

    @media (max-width: 900px) {
        .stu-filter-row {
            grid-template-columns: 1fr;
        }

        .stu-assign-form {
            flex-direction: column;
            align-items: stretch;
        }

        .stu-assign-form select {
            min-width: unset;
        }
    }
</style>

<div class="stu-page">
    <div class="stu-page-top">
        <div class="stu-page-title">
            <h2><i class="fas fa-user-tag" style="color:#e91e63;"></i> Assign Students</h2>
            <p>View registered students and assign them to staff.</p>
        </div>
        <div class="stu-chip">
            <i class="fas fa-list"></i>
            Total: <?= (int) $totalRows ?>
        </div>
    </div>

    <div class="stu-summary">
        <div class="stu-summary-card">
            <div class="stu-summary-title">Assigned</div>
            <div class="stu-summary-value"><?= (int) $summary['assigned'] ?></div>
        </div>
        <div class="stu-summary-card">
            <div class="stu-summary-title">Unassigned</div>
            <div class="stu-summary-value"><?= (int) $summary['unassigned'] ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Filters</div>
        <form method="GET" action="index.php" style="padding:14px;">
            <input type="hidden" name="page" value="students/assign">
            <div class="stu-filter-row">
                <div>
                    <label>Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / student / phone / program">
                </div>
                <div>
                    <label>Assigned Staff</label>
                    <select name="staff_id">
                        <option value="">All</option>
                        <?php foreach ($staffUsers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $staffFilter === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= h($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                    <a href="index.php?page=students/assign" class="btn" style="background:#f3f4f6;"><i
                            class="fas fa-undo"></i> Reset</a>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header">Registered Students (<?= (int) $totalRows ?>)</div>
        <div class="table-responsive" style="padding:14px;">
            <table class="table stu-table">
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
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No registered students found.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?= (int) ($offset + $i + 1) ?></td>
                            <td><strong><?= h($r['registration_no'] ?: '-') ?></strong></td>
                            <td>
                                <div class="stu-name"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                                <div class="stu-sub"><?= h($r['enquiry_snapshot_phone'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div><?= h($r['program_name'] ?: '-') ?></div>
                                <div class="stu-sub"><?= h($r['batch_name'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div><?= h(ucfirst($r['reg_type'] ?: '-')) ?></div>
                                <?php if (($r['reg_type'] ?? '') === 'internship'): ?>
                                    <div class="stu-sub">
                                        <?= h($r['internship_days'] ?: '-') ?> Days
                                        <?= !empty($r['internship_batch']) ? ' | ' . h($r['internship_batch']) : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= h($r['assigned_staff'] ?: '-') ?></td>

                            <!-- <td><?= h($r['assigned_staff'] ?: '-') ?></td> -->

                            <td class="text-center">
                                <form method="POST" class="stu-assign-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                                    <input type="hidden" name="assign_student" value="1">
                                    <input type="hidden" name="registration_id" value="<?= (int) $r['id'] ?>">

                                    <select name="staff_id" required>
                                        <option value="">Select Staff</option>
                                        <?php foreach ($staffUsers as $s): ?>
                                            <option value="<?= (int) $s['id'] ?>" <?= ((int) $r['assigned_to'] === (int) $s['id']) ? 'selected' : '' ?>>
                                                <?= h($s['name']) ?>        <?= !empty($s['branch_name']) ? ' (' . h($s['branch_name']) . ')' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if (($r['reg_type'] ?? '') === 'internship'): ?>
                                        <select name="internship_days" required>
                                            <option value="">Days</option>
                                            <option value="7" <?= ((int) $r['internship_days'] === 7) ? 'selected' : '' ?>>7 Days
                                            </option>
                                            <option value="15" <?= ((int) $r['internship_days'] === 15) ? 'selected' : '' ?>>15 Days
                                            </option>
                                            <option value="21" <?= ((int) $r['internship_days'] === 21) ? 'selected' : '' ?>>21 Days
                                            </option>
                                            <option value="30" <?= ((int) $r['internship_days'] === 30) ? 'selected' : '' ?>>30 Days
                                            </option>
                                        </select>

                                        <select name="internship_batch" required>
                                            <option value="">Batch</option>
                                            <option value="Morning" <?= (($r['internship_batch'] ?? '') === 'Morning') ? 'selected' : '' ?>>Morning</option>
                                            <option value="Evening" <?= (($r['internship_batch'] ?? '') === 'Evening') ? 'selected' : '' ?>>Evening</option>
                                            <option value="Afternoon" <?= (($r['internship_batch'] ?? '') === 'Afternoon') ? 'selected' : '' ?>>Afternoon</option>
                                        </select>
                                    <?php endif; ?>

                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="stu-pager">
            <a href="<?= $baseUrl ?>&p=1"><i class="fas fa-angle-double-left"></i></a>
            <a href="<?= $baseUrl ?>&p=<?= max(1, $page - 1) ?>"><i class="fas fa-angle-left"></i></a>
            <span style="font-weight:700;">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
            <a href="<?= $baseUrl ?>&p=<?= min($totalPages, $page + 1) ?>"><i class="fas fa-angle-right"></i></a>
            <a href="<?= $baseUrl ?>&p=<?= (int) $totalPages ?>"><i class="fas fa-angle-double-right"></i></a>
        </div>
    </div>
</div>