<?php
// =====================================
// Targets - List
// Slug: targets/list
// File: views/targets/list.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = '';
$error   = '';

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('targetInt')) {
    function targetInt($value)
    {
        return (int) trim((string)$value);
    }
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = trim((string)($_SESSION['role_name'] ?? ''));

$allowedRoles = ['Super Admin', 'HR'];

if (!$userId || !$branchId) {
    $error = 'Invalid session. Please login again.';
}

if (!$error && !in_array($roleName, $allowedRoles, true)) {
    $error = 'Access denied. Only HR and Super Admin can access target list.';
}

// --------------------------------------------------
// Delete
// --------------------------------------------------
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_target') {
    if (function_exists('verifyCsrfToken') && !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $deleteId = targetInt($_POST['delete_id'] ?? 0);

        if ($deleteId <= 0) {
            $error = 'Invalid target selected for deletion.';
        } else {
            try {
                $stmtCheck = $pdo->prepare("
                    SELECT id
                    FROM monthly_targets
                    WHERE id = :id
                      AND branch_id = :branch_id
                    LIMIT 1
                ");
                $stmtCheck->execute([
                    ':id' => $deleteId,
                    ':branch_id' => $branchId
                ]);
                $targetRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$targetRow) {
                    $error = 'Target record not found or access denied.';
                } else {
                    $stmtDelete = $pdo->prepare("
                        DELETE FROM monthly_targets
                        WHERE id = :id
                          AND branch_id = :branch_id
                        LIMIT 1
                    ");
                    $stmtDelete->execute([
                        ':id' => $deleteId,
                        ':branch_id' => $branchId
                    ]);

                    if (function_exists('setFlash')) {
                        setFlash('success', 'Target deleted successfully.');
                        echo '<script>window.location.href="index.php?page=targets/list";</script>';
exit;
                    } else {
                        $success = 'Target deleted successfully.';
                    }
                }
            } catch (Throwable $e) {
                $error = 'Delete failed. ' . $e->getMessage();
            }
        }
    }
}

// --------------------------------------------------
// Flash fallback
// --------------------------------------------------
if (!$success && function_exists('getFlash')) {
    $flashSuccess = getFlash('success');
    if ($flashSuccess) {
        $success = $flashSuccess;
    }
}

// --------------------------------------------------
// Filters
// --------------------------------------------------
$search       = trim((string)($_GET['search'] ?? ''));
$fYear        = targetInt($_GET['year'] ?? date('Y'));
$fMonth       = trim((string)($_GET['month'] ?? ''));
$fStatus      = trim((string)($_GET['status'] ?? ''));
$fUserId      = targetInt($_GET['user_id'] ?? 0);
$pageNo       = max(1, targetInt($_GET['p'] ?? 1));
$perPage      = 10;
$offset       = ($pageNo - 1) * $perPage;

$monthNames = [
    1  => 'January',
    2  => 'February',
    3  => 'March',
    4  => 'April',
    5  => 'May',
    6  => 'June',
    7  => 'July',
    8  => 'August',
    9  => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];

// --------------------------------------------------
// Eligible users for filter
// --------------------------------------------------
$targetUsers = [];
if (!$error) {
    try {
        $stmtUsers = $pdo->prepare("
            SELECT u.id, u.name, r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.branch_id = :branch_id
              AND u.status = 1
              AND r.status = 1
              AND r.is_target_applicable = 1
            ORDER BY u.name ASC
        ");
        $stmtUsers->execute([
            ':branch_id' => $branchId
        ]);
        $targetUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load users filter. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Build query
// --------------------------------------------------
$rows = [];
$totalRows = 0;

if (!$error) {
    try {
        $where = ["mt.branch_id = :branch_id"];
        $params = [':branch_id' => $branchId];

        if ($search !== '') {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search OR r.role_name LIKE :search OR mt.remarks LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($fYear > 0) {
            $where[] = "mt.target_year = :target_year";
            $params[':target_year'] = $fYear;
        }

        if ($fMonth !== '' && ctype_digit((string)$fMonth) && (int)$fMonth >= 1 && (int)$fMonth <= 12) {
            $where[] = "mt.target_month = :target_month";
            $params[':target_month'] = (int)$fMonth;
        }

        if ($fStatus !== '' && in_array($fStatus, ['active', 'inactive'], true)) {
            $where[] = "mt.status = :status";
            $params[':status'] = $fStatus;
        }

        if ($fUserId > 0) {
            $where[] = "mt.user_id = :user_id";
            $params[':user_id'] = $fUserId;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $sqlCount = "
            SELECT COUNT(*) AS total
            FROM monthly_targets mt
            INNER JOIN users u ON u.id = mt.user_id
            INNER JOIN roles r ON r.id = mt.role_id
            LEFT JOIN users ab ON ab.id = mt.assigned_by
            $whereSql
        ";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRows = (int)($stmtCount->fetchColumn() ?: 0);

        $sqlData = "
            SELECT
                mt.*,
                u.name AS user_name,
                u.email AS user_email,
                r.role_name,
                ab.name AS assigned_by_name
            FROM monthly_targets mt
            INNER JOIN users u ON u.id = mt.user_id
            INNER JOIN roles r ON r.id = mt.role_id
            LEFT JOIN users ab ON ab.id = mt.assigned_by
            $whereSql
            ORDER BY mt.target_year DESC, mt.target_month DESC, mt.id DESC
            LIMIT :offset, :limit
        ";

        $stmtData = $pdo->prepare($sqlData);

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmtData->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmtData->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtData->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmtData->execute();

        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load target list. ' . $e->getMessage();
    }
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// --------------------------------------------------
// Query string helper
// --------------------------------------------------
function buildTargetListUrl(array $overrides = [])
{
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $params['page'] = 'targets/list';
    return 'index.php?' . http_build_query($params);
}
?>

<style>
/* Tooltip styles */
[data-tooltip] {
    position: relative;
    cursor: pointer;
}

[data-tooltip]:before {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 6px 12px;
    background: #333;
    color: white;
    font-size: 0.8rem;
    font-weight: 500;
    white-space: nowrap;
    border-radius: 6px;
    margin-bottom: 8px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    pointer-events: none;
}

[data-tooltip]:after {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #333;
    margin-bottom: -4px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    z-index: 1000;
    pointer-events: none;
}

[data-tooltip]:hover:before,
[data-tooltip]:hover:after {
    opacity: 1;
    visibility: visible;
}

.targets-list-wrap{
    background:#fcf8fb;
    border-radius:22px;
    padding:20px;
}
.targets-list-topbar{
    background:linear-gradient(135deg,#ffffff 0%,#fff6fb 100%);
    border:1px solid #f0d9e5;
    border-radius:20px;
    padding:18px 20px;
    box-shadow:0 8px 24px rgba(233,30,99,.06);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:18px;
}
.targets-list-title{
    font-size:1.45rem;
    font-weight:700;
    color:#202020;
    margin-bottom:4px;
}
.targets-list-text{
    margin:0;
    color:#6d6d6d;
    font-size:.95rem;
}
.targets-btn{
    border-radius:12px;
    padding:9px 16px;
    font-weight:600;
    font-size:.92rem;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:8px;
}
.targets-btn-primary{
    background:linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
    border:none;
    color:#fff;
}
.targets-btn-primary:hover{
    color:#fff;
    opacity:.95;
}
.targets-btn-outline{
    border:1px solid #e4cfd9;
    background:#fff;
    color:#444;
}
.targets-btn-outline:hover{
    background:#faf7f9;
    color:#222;
}
/* Icon only button style */
.targets-btn-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: 1px solid #e4cfd9;
    background: #fff;
    color: #444;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 1.1rem;
}

.targets-btn-icon:hover {
    background: linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
    border-color: #c8135b;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(233,30,99,.2);
}

.targets-btn-icon.reset:hover {
    background: #6c757d;
    border-color: #6c757d;
    color: #fff;
}

.targets-filter-card,
.targets-table-card,
.targets-view-card{
    background:#fff;
    border:1px solid #f0d9e5;
    border-radius:22px;
    box-shadow:0 12px 30px rgba(0,0,0,.05);
    overflow:hidden;
	margin-bottom: 24px;
}
.targets-card-head{
    background:linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
    color:#fff;
    padding:15px 20px;
    font-size:1rem;
    font-weight:700;
}
.targets-card-body{
    padding:20px;
}
/* Updated filter styles - single line with icons */
.targets-filter-grid {
    display: flex;
    flex-wrap: nowrap;
    gap: 12px;
    align-items: flex-end;
}

.targets-filter-grid > div {
    flex: 1;
    min-width: 160px;
}

.targets-filter-grid > div:first-child {
    flex: 2;
    min-width: 220px;
}

/* Filter actions with icons */
.filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-shrink: 0;
}

.targets-filter-grid .form-control,
.targets-filter-grid .form-select {
    min-height: 42px;
    border-radius: 12px;
    border: 1px solid #e7d8e0;
    box-shadow: none;
    font-size: 0.9rem;
}

.targets-filter-grid .form-label {
    font-size: 0.85rem;
    margin-bottom: 4px;
    white-space: nowrap;
}

/* Responsive table styles */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 16px;
}

.targets-table{
    margin-bottom:0;
    vertical-align:middle;
    min-width: 1200px;
    width: 100%;
}
.targets-table thead th{
    background:#fff7fb;
    color:#5a2340;
    font-weight:700;
    border-bottom:1px solid #f0d9e5;
    white-space:nowrap;
    padding: 15px 12px;
    font-size: 0.9rem;
}
.targets-table td{
    vertical-align:middle;
    padding: 15px 12px;
    font-size: 0.9rem;
}
.targets-user-block{
    display:flex;
    flex-direction:column;
    gap:2px;
    min-width: 150px;
}
.targets-user-name{
    font-weight:700;
    color:#222;
    font-size: 0.95rem;
}
.targets-user-meta{
    font-size:.75rem;
    color:#777;
    word-break: break-word;
}
.targets-amount{
    font-weight:700;
    color:#202020;
    white-space: nowrap;
}
.targets-badge{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    padding:6px 10px;
    font-size:.75rem;
    font-weight:700;
    white-space: nowrap;
}
.targets-badge-active{
    background:#e8f8ee;
    color:#157347;
}
.targets-badge-inactive{
    background:#fce9ea;
    color:#b02a37;
}
.targets-actions{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    justify-content: center;
    min-width: 100px;
}
.targets-icon-btn{
    width:32px;
    height:32px;
    border-radius:8px;
    border:1px solid #ead3de;
    background:#fff;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#444;
    text-decoration:none;
    font-size: 0.85rem;
    position: relative;
}
.targets-icon-btn:hover{
    background:#fff7fb;
    color:#c8135b;
}
.targets-empty{
    text-align:center;
    padding:40px 20px;
    color:#777;
}
.targets-pagination{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    margin-top:18px;
}
.targets-page-links{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
.targets-page-link{
    min-width:36px;
    height:36px;
    border-radius:8px;
    border:1px solid #ead3de;
    background:#fff;
    color:#444;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
    padding:0 10px;
    font-size: 0.9rem;
}
.targets-page-link:hover{
    background:#fff7fb;
    color:#c8135b;
}
.targets-page-link.active{
    background:linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
    border-color:#c8135b;
    color:#fff;
}
.targets-modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.45);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:1050;
    padding:20px;
}
.targets-modal-overlay.show{
    display:flex;
}
.targets-modal-box{
    background:#fff;
    border-radius:20px;
    width:100%;
    max-width:760px;
    box-shadow:0 25px 60px rgba(0,0,0,.2);
    overflow:hidden;
}
.targets-modal-head{
    background:linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
    color:#fff;
    padding:16px 20px;
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.targets-modal-title{
    font-size:1rem;
    font-weight:700;
    margin:0;
}
.targets-close-btn{
    background:rgba(255,255,255,.15);
    border:none;
    color:#fff;
    width:34px;
    height:34px;
    border-radius:10px;
}
.targets-modal-body{
    padding:22px;
}
.targets-view-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px 20px;
}
.targets-view-item{
    background:#fff8fb;
    border:1px solid #f0d9e5;
    border-radius:16px;
    padding:14px 16px;
}
.targets-view-label{
    font-size:.82rem;
    color:#7a7a7a;
    margin-bottom:5px;
}
.targets-view-value{
    font-size:.97rem;
    font-weight:700;
    color:#222;
    word-break:break-word;
}
.targets-view-full{
    grid-column:1 / -1;
}

/* Mobile responsive adjustments */
@media (max-width: 992px) {
    .targets-filter-grid {
        flex-wrap: wrap;
    }
    
    .targets-filter-grid > div {
        flex: 1 1 calc(50% - 12px);
        min-width: auto;
    }
    
    .targets-filter-grid > div:first-child {
        flex: 1 1 100%;
    }
    
    .filter-actions {
        flex: 1 1 100%;
        justify-content: flex-end;
    }
}

@media (max-width: 768px) {
    .targets-list-wrap {
        padding: 15px;
    }
    
    .targets-list-topbar {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .targets-filter-grid > div {
        flex: 1 1 100%;
    }
    
    .filter-actions {
        justify-content: flex-start;
    }
    
    .targets-pagination {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .targets-view-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container-fluid py-3">
    <div class="targets-list-wrap">

        <div class="targets-list-topbar">
            <div>
                <div class="targets-list-title">Target List</div>
                <p class="targets-list-text">View, manage, and review monthly target setup records for the current branch.</p>
            </div>
            <div class="d-flex gap-2 align-items-end">
                <a href="index.php?page=targets/setup" class="btn targets-btn targets-btn-primary">
                    <i class="fas fa-plus"></i> New Target
                </a>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success rounded-4"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger rounded-4"><?= h($error) ?></div>
        <?php endif; ?>

        <!-- Filter Targets Section -->
        <div class="targets-filter-card">
            <div class="targets-card-head">
                <i class="fas fa-filter me-2"></i>Filter Targets
            </div>
            <div class="targets-card-body">
                <form method="get" action="">
                    <input type="hidden" name="page" value="targets/list">

                    <div class="targets-filter-grid">
                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-search me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Search
                            </label>
                            <input type="text" name="search" class="form-control" value="<?= h($search) ?>" placeholder="Search by user, email, role, remarks">
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-calendar me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Year
                            </label>
                            <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= h($fYear) ?>">
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-calendar-alt me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Month
                            </label>
                            <select name="month" class="form-select">
                                <option value="">All Months</option>
                                <?php foreach ($monthNames as $num => $name): ?>
                                    <option value="<?= $num ?>" <?= ((string)$fMonth === (string)$num) ? 'selected' : '' ?>>
                                        <?= h($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-circle me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Status
                            </label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?= ($fStatus === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($fStatus === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-user me-1" style="color: #ec1670; font-size: 0.8rem;"></i>User
                            </label>
                            <select name="user_id" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($targetUsers as $tu): ?>
                                    <option value="<?= (int)$tu['id'] ?>" <?= ($fUserId === (int)$tu['id']) ? 'selected' : '' ?>>
                                        <?= h($tu['name']) ?> | <?= h($tu['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="targets-btn-icon" data-tooltip="Apply Filters">
                                <i class="fas fa-check"></i>
                            </button>
                            <a href="index.php?page=targets/list" class="targets-btn-icon reset" data-tooltip="Reset Filters">
                                <i class="fas fa-undo-alt"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Monthly Targets Section with extra spacing -->
        <div class="targets-table-card mt-5">
            <div class="targets-card-head">
                <i class="fas fa-bullseye me-2"></i>Monthly Targets
            </div>
            <div class="targets-card-body p-0">
                <div class="table-responsive">
                    <table class="table targets-table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Period</th>
                                <th>Target</th>
                                <th>Incentive</th>
                                <th>Status</th>
                                <th>Assigned By</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="10" class="targets-empty">
                                        <i class="fas fa-bullseye fa-2x mb-2 d-block text-muted"></i>
                                        No target records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $index => $row): ?>
                                    <?php
                                        $serial = $offset + $index + 1;
                                        $monthText = $monthNames[(int)$row['target_month']] ?? ('Month ' . (int)$row['target_month']);
                                        $statusClass = $row['status'] === 'active' ? 'targets-badge-active' : 'targets-badge-inactive';
                                    ?>
                                    <tr>
                                        <td><?= $serial ?></td>

                                        <td>
                                            <div class="targets-user-block">
                                                <div class="targets-user-name"><?= h($row['user_name']) ?></div>
                                                <div class="targets-user-meta"><?= h($row['user_email'] ?: '-') ?></div>
                                            </div>
                                        </td>

                                        <td><?= h($row['role_name']) ?></td>

                                        <td>
                                            <strong><?= h($monthText) ?></strong><br>
                                            <small class="text-muted"><?= h($row['target_year']) ?></small>
                                        </td>

                                        <td class="targets-amount">₹<?= number_format((float)$row['target_amount'], 2) ?></td>

                                        <td><?= number_format((float)$row['incentive_percent'], 2) ?>%</td>

                                        <td>
                                            <span class="targets-badge <?= $statusClass ?>">
                                                <?= ucfirst(h($row['status'])) ?>
                                            </span>
                                        </td>

                                        <td><?= h($row['assigned_by_name'] ?: '-') ?></td>

                                        <td>
                                            <?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '-' ?>
                                        </td>

                                        <td class="text-center">
                                            <div class="targets-actions justify-content-center">
                                                <button
                                                    type="button"
                                                    class="targets-icon-btn js-view-target"
                                                    data-tooltip="View Details"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-user="<?= h($row['user_name']) ?>"
                                                    data-email="<?= h($row['user_email']) ?>"
                                                    data-role="<?= h($row['role_name']) ?>"
                                                    data-month="<?= h($monthText) ?>"
                                                    data-year="<?= h($row['target_year']) ?>"
                                                    data-target="<?= number_format((float)$row['target_amount'], 2) ?>"
                                                    data-incentive="<?= number_format((float)$row['incentive_percent'], 2) ?>"
                                                    data-status="<?= ucfirst(h($row['status'])) ?>"
                                                    data-assigned="<?= h($row['assigned_by_name'] ?: '-') ?>"
                                                    data-created="<?= !empty($row['created_at']) ? date('d M Y h:i A', strtotime($row['created_at'])) : '-' ?>"
                                                    data-updated="<?= !empty($row['updated_at']) ? date('d M Y h:i A', strtotime($row['updated_at'])) : '-' ?>"
                                                    data-remarks="<?= h($row['remarks'] ?: '-') ?>"
                                                >
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <a
                                                    href="index.php?page=targets/setup&id=<?= (int)$row['id'] ?>"
                                                    class="targets-icon-btn"
                                                    data-tooltip="Edit Target"
                                                >
                                                    <i class="fas fa-pen"></i>
                                                </a>

                                                <form method="post" action="" class="d-inline delete-target-form">
                                                    <?php if (function_exists('csrfField')): ?>
                                                        <?= csrfField(); ?>
                                                    <?php else: ?>
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                                                    <?php endif; ?>
                                                    <input type="hidden" name="action" value="delete_target">
                                                    <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="targets-icon-btn" data-tooltip="Delete Target" onclick="return confirm('Are you sure you want to delete this target record?')">
                                                        <i class="fas fa-trash text-danger"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="targets-pagination px-3 pb-3">
                    <div class="text-muted small">
                        Showing <?= $totalRows > 0 ? ($offset + 1) : 0 ?> to <?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> entries
                    </div>

                    <div class="targets-page-links">
                        <?php if ($pageNo > 1): ?>
                            <a class="targets-page-link" href="<?= h(buildTargetListUrl(['p' => $pageNo - 1])) ?>" data-tooltip="Previous Page">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $pageNo - 2);
                        $end   = min($totalPages, $pageNo + 2);
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a class="targets-page-link <?= $i === $pageNo ? 'active' : '' ?>" href="<?= h(buildTargetListUrl(['p' => $i])) ?>" data-tooltip="Page <?= $i ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($pageNo < $totalPages): ?>
                            <a class="targets-page-link" href="<?= h(buildTargetListUrl(['p' => $pageNo + 1])) ?>" data-tooltip="Next Page">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<!-- View Modal -->
<div class="targets-modal-overlay" id="targetViewModal">
    <div class="targets-modal-box">
        <div class="targets-modal-head">
            <h5 class="targets-modal-title">Target Details</h5>
            <button type="button" class="targets-close-btn" id="closeTargetModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="targets-modal-body">
            <div class="targets-view-grid">
                <div class="targets-view-item">
                    <div class="targets-view-label">User</div>
                    <div class="targets-view-value" id="view_user">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Email</div>
                    <div class="targets-view-value" id="view_email">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Role</div>
                    <div class="targets-view-value" id="view_role">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Period</div>
                    <div class="targets-view-value" id="view_period">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Target Amount</div>
                    <div class="targets-view-value" id="view_target">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Incentive %</div>
                    <div class="targets-view-value" id="view_incentive">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Status</div>
                    <div class="targets-view-value" id="view_status">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Assigned By</div>
                    <div class="targets-view-value" id="view_assigned">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Created At</div>
                    <div class="targets-view-value" id="view_created">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label">Updated At</div>
                    <div class="targets-view-value" id="view_updated">-</div>
                </div>
                <div class="targets-view-item targets-view-full">
                    <div class="targets-view-label">Remarks</div>
                    <div class="targets-view-value" id="view_remarks">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // View modal
    const modal = document.getElementById('targetViewModal');
    const closeBtn = document.getElementById('closeTargetModal');
    const viewButtons = document.querySelectorAll('.js-view-target');

    function openModal() {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    viewButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('view_user').textContent      = this.dataset.user || '-';
            document.getElementById('view_email').textContent     = this.dataset.email || '-';
            document.getElementById('view_role').textContent      = this.dataset.role || '-';
            document.getElementById('view_period').textContent    = (this.dataset.month || '-') + ' ' + (this.dataset.year || '');
            document.getElementById('view_target').textContent    = '₹' + (this.dataset.target || '0.00');
            document.getElementById('view_incentive').textContent = (this.dataset.incentive || '0.00') + '%';
            document.getElementById('view_status').textContent    = this.dataset.status || '-';
            document.getElementById('view_assigned').textContent  = this.dataset.assigned || '-';
            document.getElementById('view_created').textContent   = this.dataset.created || '-';
            document.getElementById('view_updated').textContent   = this.dataset.updated || '-';
            document.getElementById('view_remarks').textContent   = this.dataset.remarks || '-';
            openModal();
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
});
</script>