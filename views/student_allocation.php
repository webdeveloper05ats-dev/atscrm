<?php
// =====================================
// Staff Panel - Student Allocation
// Slug: student_allocation
// File: views/student_allocation.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('student_allocation');
}

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = (string)($_SESSION['role_name'] ?? '');
$hideActionColumn = ($roleName === 'Staff');

/* Branch access flag */
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

$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

/*
  Core rule requested:
  - show only students assigned to current staff user
  - show only course students
  - show only active/completed registrations
*/
$where = [];
$params = [];

$where[] = "rc.guide_staff_id = ?";
$params[] = $userId;

$where[] = "r.reg_type = 'course'";
$where[] = "r.registration_status IN ('active','completed')";

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

/* Count */
$totalRows = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM registrations r INNER JOIN registration_courses rc ON rc.registration_id = r.id {$whereSql}");
    $st->execute($params);
    $totalRows = (int)$st->fetchColumn();
} catch (Exception $e) {}

$totalPages = (int)ceil($totalRows / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
$startRow = $totalRows > 0 ? (($page - 1) * $perPage) + 1 : 0;
$endRow   = $totalRows > 0 ? min($totalRows, $page * $perPage) : 0;

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
            r.payment_status
        FROM registrations r
        INNER JOIN registration_courses rc ON rc.registration_id = r.id
        {$whereSql}
        ORDER BY r.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

function statusBadge($s){
    $map = [
        'active'    => '#2e7d32',
        'completed' => '#6a1b9a'
    ];
    $c = $map[$s] ?? '#607d8b';
    return "<span style='font-weight:700;color:{$c};'>".ucfirst((string)$s)."</span>";
}

function payBadge($s){
    $map = [
        'paid'    => '#2e7d32',
        'partial' => '#fb8c00',
        'unpaid'  => '#e53935'
    ];
    $c = $map[$s] ?? '#607d8b';
    return "<span style='font-weight:700;color:{$c};'>".ucfirst((string)$s)."</span>";
}

$baseUrl = "index.php?page=student_allocation&q=" . urlencode($q);
?>

<section class="student-allocation-page">
<h2 style="margin-bottom:20px;">Student Allocation</h2>

<div class="card">
    <div class="card-header">Filters</div>

    <form method="GET" action="index.php">
        <input type="hidden" name="page" value="student_allocation">

        <div class="filter-row">
            <div>
                <label>Search</label>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / name / phone / email / program">
            </div>

            <div class="filter-actions">
                <div class="crm-icon-actions">
                    <button type="submit" class="crm-icon-btn is-primary" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="index.php?page=student_allocation" class="crm-icon-btn is-muted" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                        <i class="fas fa-rotate-left"></i>
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px;">
    <div class="card-header">Assigned Course Students (<?= (int)$totalRows ?>)</div>

    <div class="table-wrap">
        <table class="lead-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Registration</th>
                <th>Student</th>
                <th>Program</th>
                <th>Status</th>
                <th>Payment</th>
                <?php if (!$hideActionColumn): ?>
                    <th class="text-center">Action</th>
                <?php endif; ?>
            </tr>
            </thead>

            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="<?= $hideActionColumn ? '6' : '7' ?>" style="text-align:center;">No assigned course students found</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>

                    <td>
                        <div class="lead-name"><?= h($r['registration_no']) ?></div>
                        <div class="lead-sub"><?= h($r['joined_on']) ?></div>
                    </td>

                    <td>
                        <div class="lead-name"><?= h($r['enquiry_snapshot_name']) ?></div>
                        <div class="lead-sub"><?= h(visibleStudentContactPair($r['enquiry_snapshot_phone'] ?? '', $r['enquiry_snapshot_email'] ?? '')) ?></div>
                    </td>

                    <td>
                        <div><?= h($r['program_name']) ?></div>
                        <div class="lead-sub"><?= h($r['batch_name']) ?></div>
                    </td>

                    <td><?= statusBadge($r['registration_status']) ?></td>
                    <td><?= payBadge($r['payment_status']) ?></td>

                    <?php if (!$hideActionColumn): ?>
                        <td class="text-center action-col">
                            <a href="index.php?page=registrations/list&q=<?= urlencode((string)$r['registration_no']) ?>" class="btn-icon edit" title="View in Registrations">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="crm-table-footer">
        <div class="crm-table-info">
            <?php if ($totalRows > 0): ?>
                Showing <?= (int)$startRow ?> to <?= (int)$endRow ?> of <?= (int)$totalRows ?> entries
            <?php else: ?>
                Showing 0 to 0 of 0 entries
            <?php endif; ?>
        </div>
        <div class="pagination">
            <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page <= 1 ? '#' : ($baseUrl . '&p=1') ?>" aria-label="First page"><i class="fas fa-angle-double-left"></i></a>
            <a class="page-btn <?= $page <= 1 ? 'is-disabled' : '' ?>" href="<?= $page <= 1 ? '#' : ($baseUrl . '&p=' . max(1, $page - 1)) ?>" aria-label="Previous page"><i class="fas fa-angle-left"></i></a>
            <span class="page-info"><?= (int)$page ?></span>
            <a class="page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : ($baseUrl . '&p=' . min($totalPages, $page + 1)) ?>" aria-label="Next page"><i class="fas fa-angle-right"></i></a>
            <a class="page-btn <?= $page >= $totalPages ? 'is-disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : ($baseUrl . '&p=' . (int)$totalPages) ?>" aria-label="Last page"><i class="fas fa-angle-double-right"></i></a>
        </div>
    </div>
</div>
</section>


