<?php
// =====================================
// Students - Registered List
// Slug: students/registered
// File: views/students/registered.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('students/registered');
}

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = trim((string)($_SESSION['role_name'] ?? ''));

$canAllBranches = 0;
try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int)($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

/* Filters */
$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? ''); // active/completed or empty

$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

/* Where */
$where = ["r.registration_status IN ('active','completed')"];
$params = [];

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if (strtolower($roleName) === 'front office') {
    $where[] = "r.created_by = ?";
    $params[] = $userId;
} elseif ($roleName === 'Staff') {
    $where[] = "(
        EXISTS (SELECT 1 FROM registration_courses rc WHERE rc.registration_id = r.id AND rc.guide_staff_id = ?)
        OR
        EXISTS (SELECT 1 FROM registration_internships ri WHERE ri.registration_id = r.id AND ri.guide_staff_id = ?)
    )";
    $params[] = $userId;
    $params[] = $userId;
} elseif (!in_array(strtolower($roleName), ['super admin', 'hr'], true)) {
    $where[] = "r.assigned_to = ?";
    $params[] = $userId;
}

if ($status !== '' && in_array($status, ['active', 'completed'], true)) {
    $where[] = "r.registration_status = ?";
    $params[] = $status;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(
        r.registration_no LIKE ?
        OR r.enquiry_snapshot_name LIKE ?
        OR r.enquiry_snapshot_phone LIKE ?
        OR r.enquiry_snapshot_email LIKE ?
        OR r.program_name LIKE ?
        OR r.batch_name LIKE ?
        OR u.name LIKE ?
    )";
    array_push($params, $like, $like, $like, $like, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* Count */
$totalRows = 0;
try {
    $cnt = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations r
        LEFT JOIN users u ON u.id = r.assigned_to
        $whereSql
    ");
    $cnt->execute($params);
    $totalRows = (int)$cnt->fetchColumn();
} catch (Exception $e) {
    $totalRows = 0;
}

$totalPages = (int)ceil($totalRows / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

/* Summary */
$summary = ['active'=>0, 'completed'=>0];
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
            SUM(CASE WHEN registration_status='active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN registration_status='completed' THEN 1 ELSE 0 END) AS completed_count
        FROM registrations
        $sumSql
    ");
    $st->execute($sumParams);
    $x = $st->fetch(PDO::FETCH_ASSOC);

    if ($x) {
        $summary['active'] = (int)($x['active_count'] ?? 0);
        $summary['completed'] = (int)($x['completed_count'] ?? 0);
    }
} catch (Exception $e) {}

/* Rows */
$rows = [];
try {
    $sql = "
        SELECT
            r.id,
            r.registration_no,
            r.joined_on,
            r.enquiry_snapshot_name,
            r.enquiry_snapshot_phone,
            r.enquiry_snapshot_email,
            r.program_name,
            r.batch_name,
            r.registration_status,
            r.payment_status,
            u.name AS owner_name
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

$baseUrl = "index.php?page=students/registered"
    . "&q=" . urlencode($q)
    . "&status=" . urlencode($status);

function regStatusBadgeStud($status){
    $map = [
        'active'    => ['#2e7d32', '#e8f5e9', 'Active'],
        'completed' => ['#6a1b9a', '#f3e5f5', 'Completed'],
    ];
    $c = $map[$status][0] ?? '#607d8b';
    $bg= $map[$status][1] ?? '#eef2f5';
    $t = $map[$status][2] ?? ucfirst((string)$status);
    return '<span class="badge-pill-custom" style="color:'.$c.';background:'.$bg.';border-color:'.$bg.';">'.$t.'</span>';
}
function payStatusBadgeStud($type){
    $map = [
        'unpaid'  => ['#d32f2f', '#ffebee', 'Unpaid'],
        'partial' => ['#ff9800', '#fff4e5', 'Partial'],
        'paid'    => ['#2e7d32', '#e8f5e9', 'Paid'],
    ];
    $c = $map[$type][0] ?? '#607d8b';
    $bg= $map[$type][1] ?? '#eef2f5';
    $t = $map[$type][2] ?? ucfirst((string)$type);
    return '<span class="badge-pill-custom" style="color:'.$c.';background:'.$bg.';border-color:'.$bg.';">'.$t.'</span>';
}
?>

<div class="stu-page">
    <div class="stu-page-top">
        <div class="stu-page-title">
            <h2><i class="fas fa-user-graduate" style="color:#e91e63;"></i> Registered Students</h2>
            <p>View only confirmed student registrations in your branch scope.</p>
        </div>
        <div class="stu-chip">
            <i class="fas fa-list"></i>
            Total: <?= (int)$totalRows ?>
        </div>
    </div>

    <div class="stu-summary">
        <div class="stu-summary-card">
            <div class="stu-summary-title">Active</div>
            <div class="stu-summary-value"><?= (int)$summary['active'] ?></div>
        </div>
        <div class="stu-summary-card">
            <div class="stu-summary-title">Completed</div>
            <div class="stu-summary-value"><?= (int)$summary['completed'] ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Filters</div>
        <form method="GET" action="index.php" style="padding:14px;">
            <input type="hidden" name="page" value="students/registered">
            <div class="stu-filter-row">
                <div>
                    <label>Search</label>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / name / phone / email / program">
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">All</option>
                        <option value="active" <?= $status==='active' ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $status==='completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="crm-icon-actions">
                    <button type="submit" class="crm-icon-btn is-primary" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="index.php?page=students/registered" class="crm-icon-btn is-muted" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:16px;">
        <div class="card-header">Students List (<?= (int)$totalRows ?>)</div>
        <div class="table-responsive" style="padding:14px;">
            <table class="table stu-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Registration</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>Status</th>
                        <th>Owner</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" style="text-align:center;">No registered students found.</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= (int)($offset + $i + 1) ?></td>
                        <td>
                            <div class="stu-name"><?= h($r['registration_no'] ?: '-') ?></div>
                            <div class="stu-sub"><i class="fas fa-calendar-alt"></i> <?= h($r['joined_on'] ?: '-') ?></div>
                        </td>
                        <td>
                            <div class="stu-name"><?= h($r['enquiry_snapshot_name'] ?: '-') ?></div>
                            <div class="stu-sub"><?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                        </td>
                        <td>
                            <div><?= h($r['program_name'] ?: '-') ?></div>
                            <div class="stu-sub"><?= h($r['batch_name'] ?: '-') ?></div>
                        </td>
                        <td>
                            <?= regStatusBadgeStud((string)$r['registration_status']) ?><br>
                            <div style="margin-top:6px;"><?= payStatusBadgeStud((string)$r['payment_status']) ?></div>
                        </td>
                        <td><?= h($r['owner_name'] ?: '-') ?></td>
                        <td class="text-center">
                            <div class="stu-actions">
                                <a href="index.php?page=registrations/list&q=<?= urlencode((string)$r['registration_no']) ?>" class="stu-btn-icon stu-view" title="Open in Registrations">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="stu-pager">
            <a href="<?= $baseUrl ?>&p=1"><i class="fas fa-angle-double-left"></i></a>
            <a href="<?= $baseUrl ?>&p=<?= max(1, $page - 1) ?>"><i class="fas fa-angle-left"></i></a>
            <span style="font-weight:700;">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
            <a href="<?= $baseUrl ?>&p=<?= min($totalPages, $page + 1) ?>"><i class="fas fa-angle-right"></i></a>
            <a href="<?= $baseUrl ?>&p=<?= (int)$totalPages ?>"><i class="fas fa-angle-double-right"></i></a>
        </div>
    </div>
</div>


