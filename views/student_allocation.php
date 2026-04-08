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

    <div class="pagination">
        <a href="<?= $baseUrl ?>&p=1"><i class="fas fa-angle-double-left"></i></a>
        <a href="<?= $baseUrl ?>&p=<?= max(1, $page-1) ?>"><i class="fas fa-angle-left"></i></a>
        <span class="page-info">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
        <a href="<?= $baseUrl ?>&p=<?= min($totalPages, $page+1) ?>"><i class="fas fa-angle-right"></i></a>
        <a href="<?= $baseUrl ?>&p=<?= (int)$totalPages ?>"><i class="fas fa-angle-double-right"></i></a>
    </div>
</div>

<style>
.filter-row{
display:flex;
gap:16px;
align-items:flex-end;
flex-wrap:wrap;
}

.filter-row input,
.filter-row select{
padding:10px;
border-radius:8px;
border:1px solid #ddd;
min-width:250px;
}

.filter-actions{
display:flex;
gap:10px;
align-items:center;
}

.table-wrap{
padding:16px;
}

.lead-table{
width:100%;
border-collapse:collapse;
}

.lead-table th{
background:#f5f6fa;
padding:14px;
text-align:left;
font-weight:700;
}

.lead-table td{
padding:14px;
border-bottom:1px solid #eee;
}

.lead-name{
font-weight:700;
}

.lead-sub{
font-size:12px;
color:#777;
}

.action-col{
white-space:nowrap;
}

.btn-icon{
width:36px;
height:36px;
border-radius:8px;
display:inline-flex;
align-items:center;
justify-content:center;
margin:0 2px;
border:none;
cursor:pointer;
text-decoration:none;
}

.edit{background:#e8f4fd;color:#1565c0;}

.pagination{
display:flex;
justify-content:center;
align-items:center;
gap:8px;
padding:16px;
}

.pagination a{
width:36px;
height:36px;
display:flex;
align-items:center;
justify-content:center;
border-radius:8px;
border:1px solid #ddd;
text-decoration:none;
color:#333;
}

.page-info{
padding:0 8px;
font-weight:600;
}

/* =====================================================
GLOBAL TYPOGRAPHY STYLECSS SYNC
font-family + font-size + font-weight only
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){font-size:clamp(2rem, 2.5vw, 2.4rem) !important;font-weight:700 !important;}
:where(h2,.h2,.section-title){font-size:clamp(1.6rem, 2vw, 2rem) !important;font-weight:600 !important;}
:where(h3,.h3,.card-header,.table-title){font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;font-weight:600 !important;}
:where(h4,.h4){font-size:1.2rem !important;font-weight:500 !important;}
:where(h5,.h5){font-size:1rem !important;font-weight:500 !important;}
:where(h6,.h6){font-size:0.9rem !important;font-weight:500 !important;}
:where(body){font-size:1rem !important;}
:where(p,.text-body,li,td,.text-muted,.help-text,.form-text,.small,small,.secondary-text){font-size:0.95rem !important;font-weight:400 !important;}
:where(.small,small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){font-size:0.85rem !important;font-weight:400 !important;}
:where(label,.form-label){font-size:0.85rem !important;font-weight:500 !important;}
:where(input,select,textarea,.form-control,.form-select){font-size:0.95rem !important;font-weight:400 !important;}
:where(input::placeholder,textarea::placeholder){font-weight:400 !important;}
:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){font-size:0.9rem !important;font-weight:600 !important;}
:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{font-size:0.75rem !important;font-weight:600 !important;}
:where(.table th,.crm-table th,.dataTables_wrapper th,th){font-size:0.75rem !important;font-weight:600 !important;}
:where(.table td,.dataTables_wrapper tbody td){font-size:0.9rem !important;}
:where(.dataTables_wrapper .dataTables_info){font-size:0.85rem !important;font-weight:400 !important;}
:where(.dataTables_wrapper .paginate_button){font-size:0.9rem !important;font-weight:600 !important;}
:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){font-weight:600 !important;}

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>

