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

<style>
:root{
  --stu-primary:#e91e63;
  --stu-primary-dark:#c2185b;
  --stu-text:#1f2937;
  --stu-muted:#6b7280;
  --stu-border:#e8edf3;
  --stu-card:#ffffff;
  --stu-bg:#f6f8fc;
  --stu-shadow:0 16px 40px rgba(15,23,42,.06);
}
.stu-page{ background:linear-gradient(180deg,#fff 0%,#fff7fb 18%,#f7f9fd 100%); border-radius:24px; padding:18px; }
.stu-page-top{ display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
.stu-page-title h2{ margin:0; font-size:28px; font-weight:900; color:var(--stu-text); }
.stu-page-title p{ margin:6px 0 0; color:var(--stu-muted); font-size:14px; }
.stu-chip{ display:inline-flex; align-items:center; gap:8px; background:#fff; color:var(--stu-primary-dark); border:1px solid rgba(233,30,99,.12); border-radius:999px; padding:10px 14px; font-size:13px; font-weight:800; }
.stu-summary{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:14px; margin-bottom:18px; }
.stu-summary-card{ position:relative; background:var(--stu-card); border:1px solid rgba(15,23,42,.06); border-radius:18px; padding:16px; box-shadow:var(--stu-shadow); }
.stu-summary-card:before{ content:""; position:absolute; top:0; left:0; width:100%; height:4px; background:linear-gradient(90deg,var(--stu-primary),#ff6ba6); }
.stu-summary-title{ font-size:12px; color:var(--stu-muted); font-weight:800; text-transform:uppercase; letter-spacing:.5px; }
.stu-summary-value{ margin-top:8px; font-size:26px; font-weight:900; color:var(--stu-text); }
.stu-filter-row{ display:grid; grid-template-columns:2fr 1fr auto; gap:12px; align-items:end; }
.badge-pill-custom{ display:inline-flex; align-items:center; padding:6px 11px; border-radius:999px; border:1px solid transparent; font-size:12px; font-weight:700; }
.stu-table thead th{ white-space:nowrap; }
.stu-name{ font-weight:800; color:#111827; }
.stu-sub{ font-size:12px; color:#6b7280; }
.stu-actions{ display:flex; justify-content:center; gap:6px; }
.stu-btn-icon{ width:34px; height:34px; border:none; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; }
.stu-view{ background:#e8f4fd; color:#0277bd; }
.stu-pager{ display:flex; justify-content:center; align-items:center; gap:8px; margin-top:14px; flex-wrap:wrap; }
.stu-pager a{ text-decoration:none; padding:7px 10px; border:1px solid #e2e8f0; border-radius:10px; color:#334155; font-weight:700; background:#fff; }
.stu-pager a:hover{ border-color:#f3b4cd; color:var(--stu-primary-dark); }
@media (max-width: 900px){ .stu-filter-row{ grid-template-columns:1fr; } }
</style>

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
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                    <a href="index.php?page=students/registered" class="btn" style="background:#f3f4f6;"><i class="fas fa-undo"></i> Reset</a>
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
                            <div class="stu-sub"><?= h($r['enquiry_snapshot_phone'] ?: '-') ?><?= $r['enquiry_snapshot_email'] ? ' | ' . h($r['enquiry_snapshot_email']) : '' ?></div>
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
