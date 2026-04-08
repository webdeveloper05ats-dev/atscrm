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

<style>
    .filter-form input[type="text"],
    .filter-form select,
    .stu-assign-form select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-color: #fff;
        background-image:
            linear-gradient(45deg, transparent 50%, #9f1239 50%),
            linear-gradient(135deg, #9f1239 50%, transparent 50%),
            linear-gradient(to right, #f8d7e5, #f8d7e5);
        background-position:
            calc(100% - 18px) calc(50% - 3px),
            calc(100% - 12px) calc(50% - 3px),
            calc(100% - 40px) 50%;
        background-size: 6px 6px, 6px 6px, 1px 24px;
        background-repeat: no-repeat;
        border: 1px solid #f3bfd4;
        border-radius: 14px;
        min-height: 44px;
        padding: 10px 44px 10px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        box-shadow: 0 6px 18px rgba(233, 30, 99, 0.04);
        transition: all .18s ease;
    }

    .filter-form input[type="text"]{
        appearance: auto;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none;
        padding-right: 14px;
    }

    .filter-form input[type="text"]:hover,
    .filter-form select:hover,
    .stu-assign-form select:hover {
        border-color: #ec6a9a;
        box-shadow: 0 10px 20px rgba(233, 30, 99, 0.08);
    }

    .filter-form input[type="text"]:focus,
    .filter-form select:focus,
    .stu-assign-form select:focus {
        border-color: #e91e63;
        box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.12);
        outline: none;
    }

    .filter-form input[type="text"]::placeholder{
        color: #9ca3af;
        font-weight: 500;
    }

    .filter-form label,
    .stu-assign-form label{
        font-size: 12px;
        font-weight: 800;
        color: #6b7280;
        letter-spacing: .02em;
    }

    .dashboard-header .header-stats{
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:10px;
        flex-wrap:wrap;
    }

    .dashboard-header .header-stats .stat-item{
        display:inline-flex;
        align-items:center;
        gap:8px;
        white-space:nowrap;
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

    .stu-table{
        table-layout: fixed;
    }

    .stu-table tbody td{
        padding-top: 10px !important;
        padding-bottom: 10px !important;
        vertical-align: top !important;
    }

    .stu-table th:nth-child(1),
    .stu-table td:nth-child(1){
        width: 4%;
    }

    .stu-table th:nth-child(2),
    .stu-table td:nth-child(2){
        width: 16%;
    }

    .stu-table th:nth-child(3),
    .stu-table td:nth-child(3){
        width: 18%;
    }

    .stu-table th:nth-child(4),
    .stu-table td:nth-child(4){
        width: 16%;
    }

    .stu-table th:nth-child(5),
    .stu-table td:nth-child(5){
        width: 10%;
    }

    .stu-table th:nth-child(6),
    .stu-table td:nth-child(6){
        width: 10%;
    }

    .stu-table th:nth-child(7),
    .stu-table td:nth-child(7){
        width: 26%;
    }

    .table-header-flex{
        width:100%;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        flex-wrap:wrap;
    }

    .table-title{
        display:inline-flex;
        align-items:center;
        gap:8px;
        font-weight:700;
    }

    #datatableControls,
    #datatableFooter{
        display:flex;
        align-items:center;
    }

    #datatableControls{
        justify-content:flex-end;
        margin-left:auto;
        min-width:0;
        flex:0 0 auto;
    }

    #datatableFooter{
        margin-top:12px;
        padding:0 4px;
        width:100%;
    }

    #datatableControls .dt-top,
    #datatableFooter .dt-bottom{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        width:100%;
        flex-wrap:wrap;
    }

    #datatableControls .dataTables_length,
    #datatableControls .dataTables_filter,
    #datatableFooter .dataTables_info,
    #datatableFooter .dataTables_paginate{
        margin:0 !important;
    }

    #datatableControls .dataTables_filter{
        margin-left:auto !important;
    }

    #datatableControls .dataTables_filter label,
    #datatableControls .dataTables_length label{
        margin:0;
    }

    #datatableControls .dataTables_length select,
    #datatableControls .dataTables_filter input{
        border:1px solid #f1d7e6;
        border-radius:8px;
        min-height:34px;
        padding:6px 10px;
        font-size:.82rem;
        background:#fff;
        outline:none;
    }

    #datatableControls .dataTables_filter input{
        width:240px;
        max-width:100%;
    }

    #datatableFooter .dataTables_paginate{
        margin-left:auto !important;
    }

    .stu-table-wrap{
        padding:14px;
    }

    .stu-name {
        font-weight: 800;
        color: #111827;
        line-height: 1.2;
        margin-bottom: 2px;
    }

    .stu-sub {
        font-size: 11px;
        color: #6b7280;
        line-height: 1.25;
    }

    .stu-current-staff{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:5px 10px;
        border:1px solid #f3d5e2;
        border-radius:999px;
        background:#fff7fb;
        font-size:12px;
        font-weight:700;
        color:#7c2d5a;
        max-width:100%;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .stu-assign-form {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        padding: 8px;
        border: 1px solid #f5d8e5;
        border-radius: 14px;
        background: linear-gradient(180deg, #fff 0%, #fff8fb 100%);
        box-shadow: 0 6px 16px rgba(233, 30, 99, 0.05);
    }

    .stu-assign-form select,
    .stu-assign-form .stu-save-btn{
        min-height: 38px;
        border-radius: 12px;
    }

    .stu-assign-form select {
        min-width: 140px;
        font-size: 12px;
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .stu-save-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width: 38px;
        min-width: 38px;
        height: 38px;
        padding: 0;
        border: none;
        background: linear-gradient(135deg, #ff4d8d, #e91e63);
        color: #fff;
        font-size: 14px;
        font-weight: 800;
        box-shadow: 0 8px 18px rgba(233, 30, 99, 0.18);
        transition: all .2s ease;
        border-radius: 12px;
    }

    .stu-save-btn:hover{
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(233, 30, 99, 0.22);
    }

    .stu-assign-main{
        flex: 1 1 230px;
        min-width: 230px !important;
    }

    .stu-assign-mini{
        min-width: 104px !important;
        flex: 0 0 104px;
    }

    .stu-assign-form .ms-select{
        width: auto;
        flex: 0 0 auto;
    }

    .stu-assign-form .ms-select:has(> select.stu-assign-main){
        flex: 1 1 230px;
        min-width: 230px;
    }

    .stu-assign-form .ms-select:has(> select.stu-assign-mini){
        flex: 0 0 104px;
        min-width: 104px;
    }

    .stu-type-block{
        line-height:1.2;
    }

    @media (max-width: 900px) {
        .stu-filter-row {
            grid-template-columns: 1fr;
        }

        .stu-assign-form {
            flex-direction: column;
            align-items: stretch;
        }

        .stu-assign-form select,
        .stu-save-btn {
            min-width: unset;
        }

        .stu-assign-form .ms-select{
            width: 100%;
            flex: 1 1 auto;
            min-width: 0;
        }

        .stu-assign-main,
        .stu-assign-mini{
            flex: 1 1 auto;
        }
    }

    @media (max-width: 768px){
        .dashboard-header .header-stats{
            justify-content:flex-start;
        }

        #datatableControls,
        #datatableFooter{
            width:100%;
            margin-left:0;
            justify-content:flex-start;
        }

        #datatableControls{
            flex:1 1 100%;
        }

        #datatableControls .dt-top,
        #datatableFooter .dt-bottom{
            justify-content:flex-start;
        }

        #datatableControls .dataTables_filter,
        #datatableFooter .dataTables_paginate{
            margin-left:0 !important;
        }

        #datatableControls .dataTables_filter input{
            width:100%;
            max-width:100%;
        }

        .stu-table-wrap{
            padding:0;
        }

        /* Mobile card row fix: keep Assign Staff control compact and stable */
        #assignStudentsTable tbody td:nth-child(7),
        #assignStudentsTable tbody td[data-label="Assign Staff"]{
            display:block !important;
            text-align:left !important;
        }

        #assignStudentsTable tbody td:nth-child(7)::before,
        #assignStudentsTable tbody td[data-label="Assign Staff"]::before{
            display:block !important;
            flex:none !important;
            max-width:100% !important;
            margin-bottom:6px;
            text-align:left !important;
        }

        #assignStudentsTable tbody td:nth-child(7) .crm-card-value,
        #assignStudentsTable tbody td[data-label="Assign Staff"] .crm-card-value{
            max-width:100% !important;
            width:100% !important;
            margin-left:0 !important;
            align-items:stretch !important;
            text-align:left !important;
        }

        .stu-assign-form{
            width:100%;
            display:grid;
            grid-template-columns:1fr;
            gap:8px;
            align-items:stretch;
            justify-items:stretch;
            padding:8px;
        }

        .stu-assign-form .stu-assign-main,
        .stu-assign-form .stu-assign-mini,
        .stu-assign-form .stu-save-btn{
            width:100% !important;
            min-width:0 !important;
            flex:1 1 auto !important;
            margin:0 !important;
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

