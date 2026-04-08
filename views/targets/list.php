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
$pageNo       = 1;
$perPage      = 5000;
$offset       = 0;

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
              AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'hr', 'marketing', 'corporate')
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
        ";

        $stmtData = $pdo->prepare($sqlData);

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmtData->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmtData->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        $stmtData->execute();

        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load target list. ' . $e->getMessage();
    }
}

$totalPages = 1;

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
    padding: 8px 12px;
    background: #1f2940;
    color: white;
    font-size: 0.74rem;
    font-weight: 700;
    white-space: nowrap;
    border-radius: 10px;
    margin-bottom: 10px;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    box-shadow: 0 10px 24px rgba(15,23,42,.18);
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
    border-top-color: #1f2940;
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
    max-width:1400px;
    margin:0 auto;
    padding:12px;
}

.dashboard-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.dashboard-header h2{
    margin:0;
    font-size:32px;
    font-weight:800;
    color:#1f2940;
}

.header-stats{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.stat-item{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:10px 14px;
    background:#fff;
    border:1px solid #f2d8e4;
    border-radius:999px;
    box-shadow:0 4px 12px rgba(0,0,0,.04);
    font-size:14px;
    font-weight:600;
    color:#4c566a;
}
.targets-btn{
    border-radius:12px;
    padding:9px 16px;
    font-weight:700;
    font-size:.84rem;
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
    border: none;
    background: linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 1.1rem;
}

.targets-btn-icon:hover {
    background: linear-gradient(135deg,#d41463 0%,#b11152 100%);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(233,30,99,.2);
}

.targets-btn-icon.reset:hover {
    background: #6c757d;
    color: #fff;
}

.targets-filter-card,
.targets-table-card,
.targets-view-card{
    background:#fff;
    border:1px solid #f1d6e3;
    border-radius:16px;
    box-shadow:0 8px 20px rgba(0,0,0,.05);
    overflow:hidden;
    margin-bottom:14px;
}
.targets-filter-card{
    overflow:visible;
}
.targets-card-head{
    background:linear-gradient(180deg, rgba(255,255,255,.95), rgba(255,249,252,.9));
    color:#24324a;
    padding:18px 20px;
    font-size:.96rem;
    font-weight:700;
    border-bottom:1px solid #f6e6ee;
}
.targets-card-body{
    padding:20px;
}
.targets-filter-card .targets-card-body{
    overflow:visible;
}
.targets-table-card .targets-card-body{
    padding:0;
}

.targets-table-card .table-container{
    padding:18px 20px 20px;
    overflow-x:auto;
}

.table-header-flex{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
}

.table-title{
    display:flex;
    align-items:center;
    gap:10px;
    font-weight:700;
    color:#24324a;
}

#datatableControls{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:12px;
    margin-left:auto;
}

.targets-filter-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
    position: relative;
    overflow: visible;
}

.targets-filter-grid .ms-select{
    position: relative;
}

.targets-filter-grid .ms-select.open{
    z-index: 25;
}

.targets-filter-grid > div {
    flex: 1;
    min-width: 180px;
}

.targets-filter-grid > div:first-child {
    flex: 2;
    min-width: 240px;
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
    min-height: 40px;
    border-radius: 12px;
    border: 1px solid #f0c2d4;
    box-shadow: none;
    font-size: 0.84rem;
}

.targets-filter-grid .form-label {
    font-size: 0.72rem;
    margin-bottom: 4px;
    white-space: nowrap;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#6b7280;
}

/* Responsive table styles */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 14px;
}

.targets-table{
    margin-bottom:0;
    vertical-align:middle;
    min-width: 1200px;
    width: 100%;
    border-collapse:collapse;
    border:none;
    background:#fff;
}
.targets-table thead th{
    background:#fff;
    color:#7b8798;
    font-weight:700;
    border-bottom:1px solid #f0d9e5;
    white-space:nowrap;
    padding: 15px 12px;
    font-size: 0.78rem;
    text-transform:uppercase;
    letter-spacing:.04em;
}
.targets-table td{
    vertical-align:middle;
    padding: 15px 12px;
    font-size: 0.84rem;
    border-bottom:1px solid #f0d9e5;
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
    font-size: 0.9rem;
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
    width:34px;
    height:34px;
    border-radius:8px;
    border:none;
    background:#f4f6fb;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#536273;
    text-decoration:none;
    font-size:0.85rem;
    position:relative;
    transition:all .2s ease;
}
.targets-icon-btn:hover{
    transform:translateY(-1px);
}

.targets-icon-btn.view-btn{
    background:#e91e63;
    color:#fff;
}

.targets-icon-btn.edit-btn{
    background:#e91e63;
    color:#fff;
}

.targets-icon-btn.delete-btn{
    background:#e91e63;
    color:#fff;
}

.targets-icon-btn.view-btn:hover{
    background:#c2185b;
    color:#fff;
}

.targets-icon-btn.edit-btn:hover,
.targets-icon-btn.delete-btn:hover{
    background:#c2185b;
    color:#fff;
}
.targets-empty{
    text-align:center;
    padding:40px 20px;
    color:#777;
}

.dt-top,
.dt-bottom{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    width:100%;
}

.dt-top{
    flex-wrap:wrap;
}

.dt-bottom{
    margin-top:14px;
    flex-wrap:wrap;
}

.dataTables_length,
.dataTables_filter,
.dataTables_info,
.dataTables_paginate{
    float:none !important;
    margin:0 !important;
}

.dt-top .dataTables_length,
.dt-top .dataTables_filter{
    display:flex;
    align-items:center;
    gap:8px;
}

.dt-top .dataTables_length label,
.dt-top .dataTables_filter label{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:13px;
    font-weight:600;
    color:#24324a;
    margin:0;
}

.dt-top .dataTables_filter input{
    width:210px;
    height:38px;
    margin:0 !important;
    border:1px solid #f0c2d4;
    border-radius:12px;
    padding:0 12px;
}

.dt-top .dataTables_length select{
    height:38px;
    border:1px solid #f0c2d4;
    border-radius:10px;
    padding:0 10px;
    background:#fff;
}

.dt-bottom .dataTables_info{
    font-size:13px;
    color:#616b7c;
}

.paginate_button{
    border-radius:10px !important;
}
.targets-modal-overlay{
    position:fixed;
    inset:0;
    background:radial-gradient(circle at top, rgba(236,22,112,.18), rgba(15,23,42,.62));
    backdrop-filter:blur(10px);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:1050;
    padding:24px;
    overflow-y:auto;
}
.targets-modal-overlay.show{
    display:flex;
}
.targets-modal-box{
    background:linear-gradient(180deg,#fff 0%,#fff8fb 100%);
    border:1px solid rgba(255,255,255,.7);
    border-radius:28px;
    width:100%;
    max-width:800px;
    max-height:calc(100vh - 48px);
    box-shadow:0 30px 80px rgba(15,23,42,.28);
    overflow:hidden;
    display:flex;
    flex-direction:column;
}
.targets-modal-head{
    background:
        radial-gradient(circle at top right, rgba(236,22,112,.14), transparent 40%),
        linear-gradient(180deg,#ffffff 0%,#fff5f9 100%);
    color:#24324a;
    padding:18px 20px 14px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    border-bottom:1px solid #f5dbe7;
}
.targets-modal-title{
    font-size:1.05rem;
    font-weight:800;
    margin:0;
}
.targets-modal-headline{
    display:flex;
    flex-direction:column;
    gap:6px;
}
.targets-modal-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font-size:.74rem;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#e31b6d;
}
.targets-modal-kicker i{
    width:26px;
    height:26px;
    border-radius:9px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#ffebf3,#ffd9e8);
}
.targets-modal-subtitle{
    font-size:.8rem;
    color:#7b8798;
    font-weight:500;
}
.targets-modal-head-actions{
    display:flex;
    align-items:center;
    gap:10px;
    flex-shrink:0;
}
.targets-modal-status-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    font-size:.74rem;
    font-weight:800;
    border:1px solid #f0d9e5;
    background:#fff;
    color:#24324a;
    box-shadow:0 10px 25px rgba(233,30,99,.08);
}
.targets-modal-status-chip::before{
    content:'';
    width:9px;
    height:9px;
    border-radius:50%;
    background:currentColor;
    box-shadow:0 0 0 5px rgba(21,115,71,.08);
}
.targets-modal-status-chip.is-active{
    background:#ecfbf3;
    color:#157347;
    border-color:#ccefdc;
}
.targets-modal-status-chip.is-inactive{
    background:#fff1f3;
    color:#c82355;
    border-color:#f5c8d5;
}
.targets-close-btn{
    background:#ffffff;
    border:1px solid #f3d8e4;
    color:#c41b61;
    width:38px;
    height:38px;
    border-radius:12px;
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    transition:all .2s ease;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:.95rem;
    cursor:pointer;
}
.targets-close-btn:hover{
    transform:translateY(-1px);
    background:#fff5f9;
}
.targets-modal-body{
    padding:18px 20px 20px;
    overflow-y:auto;
}
.targets-modal-summary{
    display:grid;
    grid-template-columns:1.4fr .9fr .9fr;
    gap:12px;
    margin-bottom:14px;
}
.targets-summary-card{
    position:relative;
    background:linear-gradient(180deg,#ffffff 0%,#fff8fb 100%);
    border:1px solid #f3d8e4;
    border-radius:18px;
    padding:14px 16px;
    overflow:hidden;
}
.targets-summary-card::after{
    content:'';
    position:absolute;
    inset:auto -40px -50px auto;
    width:120px;
    height:120px;
    border-radius:50%;
    background:radial-gradient(circle, rgba(236,22,112,.12), transparent 70%);
}
.targets-summary-card.is-highlight{
    background:linear-gradient(135deg,#fff1f7 0%,#ffe6f0 100%);
}
.targets-summary-label{
    font-size:.7rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.08em;
    color:#8a97aa;
    margin-bottom:8px;
}
.targets-summary-value{
    font-size:1.28rem;
    font-weight:800;
    color:#172b4d;
    line-height:1.15;
}
.targets-summary-meta{
    margin-top:6px;
    font-size:.76rem;
    color:#6f7d90;
    font-weight:600;
}
.targets-summary-stack{
    display:flex;
    flex-direction:column;
    gap:12px;
}
.targets-view-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px 14px;
}
.targets-view-item{
    position:relative;
    background:linear-gradient(180deg,#ffffff 0%,#fff9fc 100%);
    border:1px solid #f2dce7;
    border-radius:16px;
    padding:13px 14px;
    box-shadow:0 10px 30px rgba(15,23,42,.04);
}
.targets-view-label{
    display:flex;
    align-items:center;
    gap:8px;
    font-size:.68rem;
    color:#7d8aa0;
    margin-bottom:8px;
    font-weight:800;
    letter-spacing:.08em;
    text-transform:uppercase;
}
.targets-view-label i{
    width:24px;
    height:24px;
    border-radius:9px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:#fff0f6;
    color:#e31b6d;
    font-size:.78rem;
}
.targets-view-value{
    font-size:.9rem;
    font-weight:800;
    color:#1f2940;
    word-break:break-word;
    line-height:1.45;
}
.targets-view-full{
    grid-column:1 / -1;
}
.targets-view-item.remarks-card{
    background:linear-gradient(135deg,#fff7fb 0%,#ffffff 100%);
}
.targets-remarks-value{
    font-weight:700;
    font-size:.86rem;
    min-height:56px;
    white-space:pre-wrap;
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

    .dashboard-header{
        flex-direction:column;
        align-items:flex-start;
    }
    
    .targets-filter-grid > div {
        flex: 1 1 100%;
    }
    
    .filter-actions {
        justify-content: flex-start;
    }

    .filter-actions .targets-btn-icon[data-mobile-label]{
        width:auto !important;
        min-width:64px !important;
        height:auto !important;
        min-height:40px !important;
        padding:6px 8px !important;
        display:inline-flex !important;
        flex-direction:column !important;
        align-items:center !important;
        justify-content:center !important;
        gap:3px !important;
        border-radius:10px !important;
        font-size:13px !important;
    }

    .filter-actions .targets-btn-icon[data-mobile-label]::before{
        content:none !important;
        display:none !important;
    }

    .filter-actions .targets-btn-icon[data-mobile-label]::after{
        content:attr(data-mobile-label) !important;
        position:static !important;
        display:block !important;
        opacity:1 !important;
        visibility:visible !important;
        transform:none !important;
        background:none !important;
        border:0 !important;
        box-shadow:none !important;
        padding:0 !important;
        margin:0 !important;
        font-size:10px !important;
        line-height:1.1 !important;
        font-weight:700 !important;
        letter-spacing:.1px !important;
        color:currentColor !important;
        white-space:nowrap !important;
    }
    
    .targets-view-grid {
        grid-template-columns: 1fr;
    }

    .targets-modal-summary{
        grid-template-columns:1fr;
    }

    .targets-modal-head{
        flex-direction:column;
        align-items:flex-start;
    }

    .targets-modal-head-actions{
        width:100%;
        justify-content:space-between;
    }

    .targets-modal-overlay{
        align-items:flex-start;
        padding:14px;
    }

    .targets-modal-box{
        max-height:calc(100vh - 28px);
    }

    #datatableControls,
    .dt-top,
    .dt-bottom{
        width:100%;
    }
}

@media (hover: none), (pointer: coarse){
    .filter-actions .targets-btn-icon[data-mobile-label]{
        width:auto !important;
        min-width:64px !important;
        height:auto !important;
        min-height:40px !important;
        padding:6px 8px !important;
        display:inline-flex !important;
        flex-direction:column !important;
        align-items:center !important;
        justify-content:center !important;
        gap:3px !important;
        border-radius:10px !important;
        font-size:13px !important;
    }

    .filter-actions .targets-btn-icon[data-mobile-label]::before{
        content:none !important;
        display:none !important;
    }

    .filter-actions .targets-btn-icon[data-mobile-label]::after{
        content:attr(data-mobile-label) !important;
        position:static !important;
        display:block !important;
        opacity:1 !important;
        visibility:visible !important;
        transform:none !important;
        background:none !important;
        border:0 !important;
        box-shadow:none !important;
        padding:0 !important;
        margin:0 !important;
        font-size:10px !important;
        line-height:1.1 !important;
        font-weight:700 !important;
        letter-spacing:.1px !important;
        color:currentColor !important;
        white-space:nowrap !important;
    }
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
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid py-3">
    <div class="targets-list-wrap">

        <div class="dashboard-header">
            <h2><i class="fas fa-bullseye" style="margin-right: 12px; color: #e91e63;"></i>Target Management</h2>
            <div class="header-stats">
                <span class="stat-item"><i class="fas fa-database"></i> Total: <?= $totalRows ?></span>
                <a href="index.php?page=targets/setup" class="btn targets-btn targets-btn-primary">
                    <i class="fas fa-plus"></i> New Target
                </a>
            </div>
        </div>

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
                            <button type="submit" class="targets-btn-icon" data-tooltip="Apply Filters" data-mobile-label="Apply">
                                <i class="fas fa-check"></i>
                            </button>
                            <a href="index.php?page=targets/list" class="targets-btn-icon reset" data-tooltip="Reset Filters" data-mobile-label="Reset">
                                <i class="fas fa-undo-alt"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Monthly Targets Section with extra spacing -->
        <div class="targets-table-card">
            <div class="targets-card-head">
                <div class="table-header-flex">
                    <div class="table-title">
                        <i class="fas fa-bullseye"></i> Monthly Targets
                    </div>
                    <div id="datatableControls"></div>
                </div>
            </div>
            <div class="targets-card-body">
                <div class="table-container">
                <div class="table-responsive">
                    <table id="targetsTable" class="table targets-table align-middle">
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

                                        <td class="targets-amount">?<?= number_format((float)$row['target_amount'], 2) ?></td>

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
                                                    class="targets-icon-btn view-btn js-view-target"
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
                                                    class="targets-icon-btn edit-btn"
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
                                                    <button type="submit" class="targets-icon-btn delete-btn" data-tooltip="Delete Target">
                                                        <i class="fas fa-trash"></i>
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
            <div class="targets-modal-headline">
                <span class="targets-modal-kicker"><i class="fas fa-chart-line"></i> Monthly Target Snapshot</span>
                <h5 class="targets-modal-title">Target Details</h5>
                <div class="targets-modal-subtitle">Premium overview of assignment, incentive, ownership, and timeline details.</div>
            </div>
            <div class="targets-modal-head-actions">
                <div class="targets-modal-status-chip" id="view_status_chip">Status</div>
                <button type="button" class="targets-close-btn" id="closeTargetModal" aria-label="Close target details">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="targets-modal-body">
            <div class="targets-modal-summary">
                <div class="targets-summary-card is-highlight">
                    <div class="targets-summary-label">Assigned To</div>
                    <div class="targets-summary-value" id="view_user_summary">-</div>
                    <div class="targets-summary-meta" id="view_role_summary">-</div>
                </div>
                <div class="targets-summary-card">
                    <div class="targets-summary-label">Target Amount</div>
                    <div class="targets-summary-value" id="view_target_summary">-</div>
                    <div class="targets-summary-meta">Monthly performance commitment</div>
                </div>
                <div class="targets-summary-stack">
                    <div class="targets-summary-card">
                        <div class="targets-summary-label">Period</div>
                        <div class="targets-summary-value" id="view_period_summary">-</div>
                    </div>
                    <div class="targets-summary-card">
                        <div class="targets-summary-label">Incentive</div>
                        <div class="targets-summary-value" id="view_incentive_summary">-</div>
                    </div>
                </div>
            </div>
            <div class="targets-view-grid">
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-user"></i> User</div>
                    <div class="targets-view-value" id="view_user">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="targets-view-value" id="view_email">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-id-badge"></i> Role</div>
                    <div class="targets-view-value" id="view_role">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-calendar-alt"></i> Period</div>
                    <div class="targets-view-value" id="view_period">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-wallet"></i> Target Amount</div>
                    <div class="targets-view-value" id="view_target">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-percent"></i> Incentive %</div>
                    <div class="targets-view-value" id="view_incentive">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-signal"></i> Status</div>
                    <div class="targets-view-value" id="view_status">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-user-check"></i> Assigned By</div>
                    <div class="targets-view-value" id="view_assigned">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-clock"></i> Created At</div>
                    <div class="targets-view-value" id="view_created">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-history"></i> Updated At</div>
                    <div class="targets-view-value" id="view_updated">-</div>
                </div>
                <div class="targets-view-item targets-view-full remarks-card">
                    <div class="targets-view-label"><i class="fas fa-note-sticky"></i> Remarks</div>
                    <div class="targets-view-value targets-remarks-value" id="view_remarks">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($success): ?>
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: <?= json_encode($success, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            confirmButtonColor: '#e91e63'
        });
    }
    <?php endif; ?>

    <?php if ($error): ?>
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Something went wrong',
            text: <?= json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            confirmButtonColor: '#e91e63'
        });
    }
    <?php endif; ?>

    if (typeof crmDataTable === 'function') {
        crmDataTable('#targetsTable', {
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            order: [[3, 'desc']],
            searchPlaceholder: 'Search targets...',
            dom:
                "<'dt-top'lf>" +
                "rt" +
                "<'dt-bottom'ip>",
            columnDefs: [
                { targets: [9], orderable: false }
            ]
        });

        setTimeout(function () {
            var controls = document.querySelector('#targetsTable_wrapper .dt-top');
            var target = document.getElementById('datatableControls');
            if (controls && target) {
                target.appendChild(controls);
            }
        }, 100);
    }

    // View modal
    const modal = document.getElementById('targetViewModal');
    const closeBtn = document.getElementById('closeTargetModal');
    const viewButtons = document.querySelectorAll('.js-view-target');
    const deleteForms = document.querySelectorAll('.delete-target-form');

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
            const user = this.dataset.user || '-';
            const role = this.dataset.role || '-';
            const period = ((this.dataset.month || '-') + ' ' + (this.dataset.year || '')).trim();
            const targetAmount = 'Rs ' + (this.dataset.target || '0.00');
            const incentive = (this.dataset.incentive || '0.00') + '%';
            const status = this.dataset.status || '-';
            const statusChip = document.getElementById('view_status_chip');

            document.getElementById('view_user').textContent      = user;
            document.getElementById('view_email').textContent     = this.dataset.email || '-';
            document.getElementById('view_role').textContent      = role;
            document.getElementById('view_period').textContent    = period || '-';
            document.getElementById('view_target').textContent    = targetAmount;
            document.getElementById('view_incentive').textContent = incentive;
            document.getElementById('view_status').textContent    = status;
            document.getElementById('view_assigned').textContent  = this.dataset.assigned || '-';
            document.getElementById('view_created').textContent   = this.dataset.created || '-';
            document.getElementById('view_updated').textContent   = this.dataset.updated || '-';
            document.getElementById('view_remarks').textContent   = this.dataset.remarks || '-';
            document.getElementById('view_user_summary').textContent = user;
            document.getElementById('view_role_summary').textContent = role;
            document.getElementById('view_target_summary').textContent = targetAmount;
            document.getElementById('view_period_summary').textContent = period || '-';
            document.getElementById('view_incentive_summary').textContent = incentive;

            if (statusChip) {
                statusChip.textContent = status;
                statusChip.classList.remove('is-active', 'is-inactive');
                if (String(status).toLowerCase() === 'active') {
                    statusChip.classList.add('is-active');
                } else if (String(status).toLowerCase() === 'inactive') {
                    statusChip.classList.add('is-inactive');
                }
            }
            openModal();
        });
    });

    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (typeof Swal === 'undefined') {
                form.submit();
                return;
            }

            Swal.fire({
                title: 'Delete target?',
                text: 'This target record will be permanently removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e91e63',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
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
