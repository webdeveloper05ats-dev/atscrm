<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/lead.css">
<?php
// =====================================
// Students - Internship Management
// Slug: students/internships
// File: views/students/internships.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('students/internships');
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
$roleName = trim((string) ($_SESSION['role_name'] ?? ''));
$isStaffRole = ($roleName === 'Staff');

$canAllBranches = 0;
try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

/* Save internship details */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_internship'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid CSRF token.');
        redirect('index.php?page=students/internships');
        exit;
    }

    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $startDate = trim((string) ($_POST['internship_start_date'] ?? ''));
    $endDate = trim((string) ($_POST['internship_end_date'] ?? ''));
    $completionStatus = trim((string) ($_POST['internship_completion_status'] ?? 'pending'));
    $certificateStatus = trim((string) ($_POST['internship_certificate_status'] ?? 'not_given'));
    $reportStatus = trim((string) ($_POST['internship_report_status'] ?? 'not_provided'));
    $certificateIssuedAt = trim((string) ($_POST['internship_certificate_issued_at'] ?? ''));
    $reportIssuedAt = trim((string) ($_POST['internship_report_issued_at'] ?? ''));
    $reportDueDays = trim((string) ($_POST['internship_report_due_days'] ?? ''));
    $reportDueDays = $reportDueDays === '' ? null : (int) $reportDueDays;

    try {
        if (!in_array($completionStatus, ['pending', 'in_progress', 'completed'], true)) {
            throw new Exception('Invalid completion status.');
        }

        if (!in_array($certificateStatus, ['not_given', 'given'], true)) {
            throw new Exception('Invalid certificate status.');
        }

        if (!in_array($reportStatus, ['not_provided', 'provided'], true)) {
            throw new Exception('Invalid report status.');
        }

        if ($reportStatus === 'provided' && ($reportDueDays === null || $reportDueDays < 0)) {
            throw new Exception('Please enter valid report days.');
        }

        if ($reportStatus === 'not_provided') {
            $reportDueDays = null;
        }
        if ($certificateStatus === 'given' && $certificateIssuedAt === '') {
    throw new Exception('Please select certificate issued date and time.');
}

if ($certificateStatus === 'not_given') {
    $certificateIssuedAt = null;
}

if ($reportStatus === 'provided' && $reportIssuedAt === '') {
    throw new Exception('Please select report issued date and time.');
}

if ($reportStatus === 'not_provided') {
    $reportIssuedAt = null;
}

        $params = [$registrationId];
        $sql = "
    SELECT
        r.id,
        ri.completion_status AS internship_completion_status,
        ri.report_status AS internship_report_status,
        ri.certificate_status AS internship_certificate_status
    FROM registrations r
    INNER JOIN registration_internships ri ON ri.registration_id = r.id
    WHERE r.id = ?
      AND r.reg_type = 'internship'
      AND r.registration_status IN ('active','completed')
";

        if (!$canAllBranches) {
            $sql .= " AND r.branch_id = ?";
            $params[] = $branchId;
        }

        if ($isStaffRole) {
            $sql .= " AND ri.guide_staff_id = ?";
            $params[] = $userId;
        } elseif (strtolower($roleName) === 'front office') {
            $sql .= " AND r.created_by = ?";
            $params[] = $userId;
        } elseif (!in_array(strtolower($roleName), ['super admin', 'hr'], true)) {
            $sql .= " AND r.assigned_to = ?";
            $params[] = $userId;
        }

        $sql .= " LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $internRow = $st->fetch(PDO::FETCH_ASSOC);

        if (!$internRow) {
            throw new Exception('Intern student not found or access denied.');
        }

        if (
            ($internRow['internship_completion_status'] ?? '') === 'completed'
            && ($internRow['internship_report_status'] ?? '') === 'provided'
        ) {
            throw new Exception('Completed internship with submitted report is view only.');
        }

        $upd = $pdo->prepare("
    UPDATE registration_internships
    SET internship_start_date = ?,
        internship_end_date = ?,
        completion_status = ?,
        certificate_status = ?,
        certificate_issued_at = ?,
        report_status = ?,
        report_issued_at = ?,
        report_due_days = ?,
        updated_at = NOW()
    WHERE registration_id = ?
    LIMIT 1
");
$upd->execute([
    $startDate !== '' ? $startDate : null,
    $endDate !== '' ? $endDate : null,
    $completionStatus,
    $certificateStatus,
    $certificateIssuedAt !== '' ? date('Y-m-d H:i:s', strtotime($certificateIssuedAt)) : null,
    $reportStatus,
    $reportIssuedAt !== '' ? date('Y-m-d H:i:s', strtotime($reportIssuedAt)) : null,
    $reportDueDays,
    $registrationId
]);

        setFlash('success', 'Internship details updated successfully.');
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect('index.php?page=students/internships');
    exit;
}

/* Filters */
$q = trim($_GET['q'] ?? '');
$paymentStatus = trim($_GET['payment_status'] ?? '');

$perPage = 12;

$where = [
    "r.reg_type = 'internship'",
    "r.registration_status IN ('active','completed')"
];
$params = [];

if (!$canAllBranches && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($isStaffRole) {
    $where[] = "ri.guide_staff_id = ?";
    $params[] = $userId;
} elseif (strtolower($roleName) === 'front office') {
    $where[] = "r.created_by = ?";
    $params[] = $userId;
} elseif (!in_array(strtolower($roleName), ['super admin', 'hr'], true)) {
    $where[] = "r.assigned_to = ?";
    $params[] = $userId;
}

if ($paymentStatus !== '' && in_array($paymentStatus, ['paid', 'partial', 'unpaid'], true)) {
    $where[] = "r.payment_status = ?";
    $params[] = $paymentStatus;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(
        r.registration_no LIKE ?
        OR r.enquiry_snapshot_name LIKE ?
        OR r.enquiry_snapshot_phone LIKE ?
        OR r.program_name LIKE ?
    )";
    array_push($params, $like, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRows = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM registrations r INNER JOIN registration_internships ri ON ri.registration_id = r.id $whereSql");
    $st->execute($params);
    $totalRows = (int) $st->fetchColumn();
} catch (Exception $e) {
}

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
        r.notes,
        ri.guide_staff_id,
        ri.internship_days,
        ri.internship_batch,
        ri.internship_start_date,
        ri.internship_end_date,
        ri.completion_status AS internship_completion_status,
        ri.certificate_status AS internship_certificate_status,
        ri.report_status AS internship_report_status,
        ri.report_due_days AS internship_report_due_days,
        r.payment_status,
        r.final_fee,
        r.paid_amount,
        ri.certificate_issued_at AS internship_certificate_issued_at,
        ri.report_issued_at AS internship_report_issued_at,
        r.balance_amount
    FROM registrations r
    INNER JOIN registration_internships ri ON ri.registration_id = r.id
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

function payBadgeIntern($status)
{
    $map = [
        'paid' => '#2e7d32',
        'partial' => '#fb8c00',
        'unpaid' => '#e53935'
    ];
    $c = $map[$status] ?? '#607d8b';
    return "<span style='font-weight:700;color:$c'>" . ucfirst((string) $status) . "</span>";
}
?>

<style>
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

    .filter-form input[type="text"],
    .filter-form select,
    .intern-modal-grid input,
    .intern-modal-grid select{
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

    .filter-form input[type="text"],
    .intern-modal-grid input{
        background-image:none;
        padding-right:14px;
    }

    .filter-form input[type="text"]:focus,
    .filter-form select:focus,
    .intern-modal-grid input:focus,
    .intern-modal-grid select:focus{
        border-color:#e91e63;
        box-shadow:0 0 0 4px rgba(233,30,99,.12);
        outline:none;
    }

    .intern-filter-row {
        display: grid;
        grid-template-columns: 2fr 1fr auto;
        gap: 12px;
        align-items: end;
    }

    .intern-filter-row .filter-item.fee-status-filter{
        max-width: 240px;
    }

    .intern-modern-select{
        position:relative;
    }

    .intern-modern-select .ims-trigger{
        width:100%;
        min-height:44px;
        border:1px solid #efbfd3;
        border-radius:14px;
        background:#fff;
        box-shadow:0 8px 20px rgba(233,30,99,.05);
        padding:10px 42px 10px 14px;
        text-align:left;
        font-size:13px;
        font-weight:700;
        color:#374151;
        cursor:pointer;
        transition:all .18s ease;
        position:relative;
    }

    .intern-modern-select .ims-trigger:hover{
        border-color:#eaa4c2;
        box-shadow:0 10px 24px rgba(233,30,99,.08);
    }

    .intern-modern-select.open .ims-trigger{
        border-color:#e91e63;
        box-shadow:0 0 0 4px rgba(233,30,99,.12),0 10px 24px rgba(233,30,99,.08);
    }

    .intern-modern-select .ims-trigger::after{
        content:"";
        position:absolute;
        right:14px;
        top:50%;
        width:8px;
        height:8px;
        border-right:2px solid #be185d;
        border-bottom:2px solid #be185d;
        transform:translateY(-60%) rotate(45deg);
        transition:transform .16s ease;
    }

    .intern-modern-select.open .ims-trigger::after{
        transform:translateY(-35%) rotate(-135deg);
    }

    .intern-modern-select .ims-panel{
        position:absolute;
        top:calc(100% + 8px);
        left:0;
        right:0;
        background:#fff;
        border:1px solid #f1c9da;
        border-radius:12px;
        box-shadow:0 16px 36px rgba(17,24,39,.16);
        z-index:50;
        overflow:hidden;
    }

    .intern-modern-select .ims-search-wrap{
        padding:8px;
        border-bottom:1px solid #f6ddea;
        background:#fff9fc;
    }

    .intern-modern-select .ims-search{
        width:100%;
        min-height:34px;
        border:1px solid #efbfd3;
        border-radius:10px;
        padding:6px 10px;
        font-size:12px;
        font-weight:600;
        outline:none;
    }

    .intern-modern-select .ims-search:focus{
        border-color:#e91e63;
        box-shadow:0 0 0 3px rgba(233,30,99,.12);
    }

    .intern-modern-select .ims-options{
        max-height:180px;
        overflow-y:auto;
        padding:6px;
    }

    .intern-modern-select .ims-option{
        width:100%;
        text-align:left;
        border:1px solid transparent;
        background:transparent;
        border-radius:10px;
        min-height:34px;
        padding:6px 10px;
        font-size:12px;
        font-weight:700;
        color:#4b5563;
        cursor:pointer;
        transition:all .16s ease;
    }

    .intern-modern-select .ims-option:hover{
        background:#fff3f8;
        color:#9f1239;
    }

    .intern-modern-select .ims-option.active{
        background:linear-gradient(135deg,#ff4d8d,#e91e63);
        color:#fff;
        border-color:#e91e63;
    }

    .intern-table thead th {
        white-space: nowrap;
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

    .intern-name {
        font-weight: 800;
        color: #111827;
    }

    .intern-sub {
        font-size: 12px;
        color: #6b7280;
    }

    .intern-table-wrap{
        padding:14px;
    }

    .intern-table{
        table-layout: fixed;
    }

    .intern-table tbody td{
        vertical-align: top !important;
        padding-top: 10px !important;
        padding-bottom: 10px !important;
    }

    .intern-table th:nth-child(1),
    .intern-table td:nth-child(1){ width: 5%; }
    .intern-table th:nth-child(2),
    .intern-table td:nth-child(2){ width: 21%; }
    .intern-table th:nth-child(3),
    .intern-table td:nth-child(3){ width: 19%; }
    .intern-table th:nth-child(4),
    .intern-table td:nth-child(4){ width: 13%; }
    .intern-table th:nth-child(5),
    .intern-table td:nth-child(5){ width: 10%; }
    .intern-table th:nth-child(6),
    .intern-table td:nth-child(6){ width: 10%; }
    .intern-table th:nth-child(7),
    .intern-table td:nth-child(7){ width: 11%; }
    .intern-table th:nth-child(8),
    .intern-table td:nth-child(8){ width: 11%; }

    .intern-action-wrap{
        display:flex;
        justify-content:center;
        gap:8px;
        flex-wrap:wrap;
    }

    .intern-action-btn{
        width:36px;
        height:36px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        border:1px solid transparent;
        cursor:pointer;
        transition:all .2s ease;
        box-shadow:none;
        padding:0;
    }

    .intern-action-btn:hover{
        transform:translateY(-1px);
    }

    .intern-action-btn.intern-view-btn,
    .intern-action-btn.intern-view-btn.crm-action-btn{
        background:#2563eb !important;
        color:#fff !important;
        border-color:#2563eb !important;
        box-shadow:0 8px 16px rgba(37,99,235,.18) !important;
    }

    .intern-action-btn.intern-manage-btn,
    .intern-action-btn.intern-manage-btn.crm-action-btn{
        background:linear-gradient(135deg,#ff4d8d,#e91e63) !important;
        color:#fff !important;
        border-color:#e91e63 !important;
        box-shadow:0 8px 16px rgba(233,30,99,.18) !important;
    }

    .intern-pay-badge{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:5px 10px;
        border-radius:999px;
        background:#fff7fb;
        border:1px solid #f3d5e2;
        font-size:12px;
        font-weight:700;
    }

    .intern-sub{
        font-size:11px;
        line-height:1.25;
    }

    @media (max-width: 1200px) {
        .intern-table{ table-layout:auto; }
    }

    @media (max-width: 900px) {
        .intern-filter-row {
            grid-template-columns: 1fr;
        }
    }

    .intern-modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    @media (max-width: 768px) {
        .intern-modal-grid {
            grid-template-columns: 1fr;
        }
    }

    .intern-modal-close {
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 12px;
        background: #fff;
        color: #1f2937;
        font-size: 28px;
        font-weight: 700;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .08);
        transition: .2s ease;
        padding: 0;
        flex: 0 0 42px;
    }

    .intern-modal-close:hover {
        background: #ffe9f1;
        color: #e91e63;
        transform: translateY(-1px);
    }

    .intern-modal-close:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(233, 30, 99, .14);
    }

    .intern-action-wrap{
display:flex;
justify-content:center;
gap:8px;
flex-wrap:wrap;
}

.intern-action-btn.intern-view-btn,
.intern-action-btn.intern-view-btn.crm-action-btn{
background:#2563eb !important;
color:#fff !important;
border-color:#2563eb !important;
box-shadow:0 8px 16px rgba(37,99,235,.18) !important;
}

.intern-view-grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:14px;
}

.intern-view-box{
background:#fff7fb;
border:1px solid rgba(233,30,99,.12);
border-radius:14px;
padding:12px;
display:flex;
flex-direction:column;
gap:6px;
}

.intern-view-box b{
font-size:12px;
text-transform:uppercase;
color:#6b7280;
}

.intern-view-box span{
font-weight:700;
color:#111827;
word-break:break-word;
}

.intern-view-box-full{
grid-column:1/-1;
}

@media (max-width: 768px){
  .intern-view-grid{
    grid-template-columns:1fr;
  }

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

  .intern-table-wrap{
    padding:0;
  }

  .intern-action-wrap{
    display:flex;
    flex-wrap:wrap;
    justify-content:flex-end;
    gap:6px;
  }

  .intern-action-btn{
    width:auto !important;
    min-width:56px !important;
    height:auto !important;
    min-height:38px !important;
    padding:6px 8px !important;
    display:inline-flex !important;
    flex-direction:column !important;
    align-items:center !important;
    justify-content:center !important;
    gap:3px !important;
    border-radius:10px !important;
  }

  .intern-action-btn[data-mobile-label]::before{
    content:none !important;
    display:none !important;
  }

  .intern-action-btn[data-mobile-label]::after{
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
    <h2><i class="fas fa-user-graduate" style="margin-right: 12px; color: #e91e63;"></i>Internship Students</h2>
    <div class="header-stats">
        <span class="stat-item"><i class="fas fa-database"></i> Total: <?= (int) $totalRows ?></span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="fas fa-sliders-h" style="margin-right: 8px;"></i> Filter Internship Students
    </div>
    <form method="GET" action="index.php" class="filter-form">
        <input type="hidden" name="page" value="students/internships">
        <div class="intern-filter-row">
            <div class="filter-item">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / student / phone / program">
            </div>
            <div class="filter-item fee-status-filter">
                <label><i class="fas fa-wallet"></i> Fee Status</label>
                <input type="hidden" name="payment_status" id="paymentStatusInput" value="<?= h($paymentStatus) ?>">
                <div class="intern-modern-select" id="paymentStatusSelect">
                    <button type="button" class="ims-trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="ims-label">All</span>
                    </button>
                    <div class="ims-panel" hidden>
                        <div class="ims-search-wrap">
                            <input type="text" class="ims-search" placeholder="Search status...">
                        </div>
                        <div class="ims-options" role="listbox">
                            <button type="button" class="ims-option" data-value="">All</button>
                            <button type="button" class="ims-option" data-value="paid">Paid</button>
                            <button type="button" class="ims-option" data-value="partial">Partial</button>
                            <button type="button" class="ims-option" data-value="unpaid">Unpaid</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-icon-only apply" title="Apply filters">
                    <i class="fas fa-filter"></i>
                </button>
                <a href="index.php?page=students/internships" class="btn-icon-only reset" title="Reset filters">
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
                <i class="fas fa-list"></i> Internship Management Queue
            </div>
            <div id="datatableControls"></div>
        </div>
    </div>
    <div class="table-container intern-table-wrap">
        <table class="leads-table intern-table" id="internshipsTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Internship</th>
                    <th>Fee Status</th>
                    <th>Completion</th>
                    <th>Certificate</th>
                    <th>Report</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= (int) ($i + 1) ?></td>

                        <td>
                            <div class="intern-name"><?= h($r['enquiry_snapshot_name']) ?></div>
                            <div class="intern-sub"><?= h($r['registration_no']) ?> | <?= h(visibleStudentContactValue($r['enquiry_snapshot_phone'] ?? '')) ?>
                            </div>
                        </td>

                        <td>
                            <div><?= h($r['program_name']) ?></div>
                            <?php if (!empty($r['internship_days']) || !empty($r['internship_batch'])): ?>
                                <div class="intern-sub">
                                    <?php if (!empty($r['internship_days'])): ?>
                                        <?= (int) $r['internship_days'] ?> Days
                                    <?php endif; ?>

                                    <?php if (!empty($r['internship_days']) && !empty($r['internship_batch'])): ?>
                                        |
                                    <?php endif; ?>

                                    <?php if (!empty($r['internship_batch'])): ?>
                                        <?= h($r['internship_batch']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($r['internship_start_date']) || !empty($r['internship_end_date'])): ?>
                                <div class="intern-sub">
                                    <?= h($r['internship_start_date'] ?: '-') ?>
                                    <?= !empty($r['internship_end_date']) ? ' to ' . h($r['internship_end_date']) : '' ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div class="intern-pay-badge"><?= payBadgeIntern((string) $r['payment_status']) ?></div>
                            <div class="intern-sub">Final: <?= h(number_format((float) $r['final_fee'], 2)) ?></div>
                            <div class="intern-sub">Paid: <?= h(number_format((float) $r['paid_amount'], 2)) ?></div>
                            <div class="intern-sub">Balance: <?= h(number_format((float) $r['balance_amount'], 2)) ?></div>
                        </td>

                        <td><?= h(ucwords(str_replace('_', ' ', (string) $r['internship_completion_status']))) ?></td>
                        <td><?= h($r['internship_certificate_status'] === 'given' ? 'Given' : 'Not Given') ?></td>
                        <td>
                            <?= h($r['internship_report_status'] === 'provided' ? 'Provided' : 'Not Provided') ?>
                            <?php if (!empty($r['internship_report_due_days'])): ?>
                                <div class="intern-sub"><?= (int) $r['internship_report_due_days'] ?> Days</div>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <div class="intern-action-wrap">
                                <button type="button" class="intern-action-btn intern-view-btn viewInternBtn" title="View Internship" data-mobile-label="View"
                                    data-name="<?= h($r['enquiry_snapshot_name']) ?>"
                                    data-regno="<?= h($r['registration_no']) ?>"
                                    data-phone="<?= h(visibleStudentContactValue($r['enquiry_snapshot_phone'] ?? '')) ?>"
                                    data-email="<?= h(visibleStudentContactValue($r['enquiry_snapshot_email'] ?? '')) ?>"
                                    data-program="<?= h($r['program_name']) ?>" data-batch="<?= h($r['batch_name']) ?>"
                                    data-joined="<?= h($r['joined_on']) ?>" data-notes="<?= h($r['notes']) ?>"
                                    data-days="<?= h($r['internship_days']) ?>"
                                    data-ibatch="<?= h($r['internship_batch']) ?>"
                                    data-start="<?= h($r['internship_start_date']) ?>"
                                    data-end="<?= h($r['internship_end_date']) ?>"
                                    data-fee="<?= h(number_format((float) $r['final_fee'], 2)) ?>"
                                    data-paid="<?= h(number_format((float) $r['paid_amount'], 2)) ?>"
                                    data-balance="<?= h(number_format((float) $r['balance_amount'], 2)) ?>"
                                    data-paystatus="<?= h($r['payment_status']) ?>">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <button type="button" class="intern-action-btn intern-manage-btn manageInternBtn" title="Manage Internship" data-id="<?= (int) $r['id'] ?>" data-mobile-label="Manage"
                                    data-name="<?= h($r['enquiry_snapshot_name']) ?>"
                                    data-start="<?= h($r['internship_start_date']) ?>"
                                    data-end="<?= h($r['internship_end_date']) ?>"
                                    data-completion="<?= h($r['internship_completion_status']) ?>"
                                    data-certificate="<?= h($r['internship_certificate_status']) ?>"
                                    data-report="<?= h($r['internship_report_status']) ?>"
                                    data-reportdays="<?= h($r['internship_report_due_days']) ?>"
                                    data-original-completion="<?= h($r['internship_completion_status']) ?>"
                                    data-original-report="<?= h($r['internship_report_status']) ?>"
                                    data-certificateissuedat="<?= h(!empty($r['internship_certificate_issued_at']) ? date('Y-m-d\TH:i', strtotime($r['internship_certificate_issued_at'])) : '') ?>"
                                    data-reportissuedat="<?= h(!empty($r['internship_report_issued_at']) ? date('Y-m-d\TH:i', strtotime($r['internship_report_issued_at'])) : '') ?>"
                                    data-paymentstatus="<?= h($r['payment_status']) ?>"
                                    >
                                    <i class="fas fa-pen"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div id="datatableFooter"></div>

    <div id="internModalBackdrop"
        style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;"></div>

    <div id="internModal"
        style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:min(680px,92vw);background:#fff;border-radius:18px;box-shadow:0 30px 80px rgba(0,0,0,.25);z-index:9999;overflow:hidden;">
        <div
            style="padding:16px 18px;background:#fff4f8;border-bottom:1px solid rgba(233,30,99,.12);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:18px;font-weight:800;color:#111827;">Manage Internship</div>
                <div id="internModalStudent" style="font-size:13px;color:#6b7280;margin-top:4px;"></div>
            </div>
            <button type="button" id="internModalClose" class="intern-modal-close" aria-label="Close">&times;</button>
        </div>

        <form method="POST" style="padding:18px;">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="save_internship" value="1">
            <input type="hidden" name="registration_id" id="modal_registration_id">

            <div class="intern-modal-grid">
                <div>
                    <label>Start Date</label>
                    <input type="date" name="internship_start_date" id="modal_start_date">
                </div>

                <div>
                    <label>End Date</label>
                    <input type="date" name="internship_end_date" id="modal_end_date">
                </div>

                <div>
                    <label>Completion</label>
                    <select name="internship_completion_status" id="modal_completion">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div>
                    <label>Certificate</label>
                    <select name="internship_certificate_status" id="modal_certificate">
                        <option value="not_given">Not Given</option>
                        <option value="given">Given</option>
                    </select>
                </div>

                <div>
    <label>Certificate Issued At</label>
    <input type="datetime-local" name="internship_certificate_issued_at" id="modal_certificate_issued_at">
</div>

                <div>
                    <label>Report</label>
                    <select name="internship_report_status" id="modal_report">
                        <option value="not_provided">Not Provided</option>
                        <option value="provided">Provided</option>
                    </select>
                </div>

                <div>
    <label>Report Issued At</label>
    <input type="datetime-local" name="internship_report_issued_at" id="modal_report_issued_at">
</div>

                <div>
                    <label>Report Days</label>
                    <input type="number" min="0" name="internship_report_due_days" id="modal_report_days"
                        placeholder="Days">
                </div>
            </div>

            <div style="margin-top:18px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="internModalCancel" class="btn" style="background:#f3f4f6;">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <div id="internViewBackdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;"></div>

<div id="internViewModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:min(760px,94vw);background:#fff;border-radius:18px;box-shadow:0 30px 80px rgba(0,0,0,.25);z-index:9999;overflow:hidden;">
    <div style="padding:16px 18px;background:#fff4f8;border-bottom:1px solid rgba(233,30,99,.12);display:flex;justify-content:space-between;align-items:center;">
        <div>
            <div style="font-size:18px;font-weight:800;color:#111827;">Student Details</div>
            <div id="view_student_name" style="font-size:13px;color:#6b7280;margin-top:4px;"></div>
        </div>
        <button type="button" id="internViewClose" class="intern-modal-close" aria-label="Close">&times;</button>
    </div>

    <div style="padding:18px;">
        <div class="intern-view-grid">
            <div class="intern-view-box"><b>Registration No</b><span id="view_regno"></span></div>
            <div class="intern-view-box"><b>Phone</b><span id="view_phone"></span></div>
            <div class="intern-view-box"><b>Email</b><span id="view_email"></span></div>
            <div class="intern-view-box"><b>Joined On</b><span id="view_joined"></span></div>
            <div class="intern-view-box"><b>Program</b><span id="view_program"></span></div>
            <div class="intern-view-box"><b>Batch</b><span id="view_batch"></span></div>
            <div class="intern-view-box"><b>Internship Days</b><span id="view_days"></span></div>
            <div class="intern-view-box"><b>Internship Batch</b><span id="view_ibatch"></span></div>
            <div class="intern-view-box"><b>Start Date</b><span id="view_start"></span></div>
            <div class="intern-view-box"><b>End Date</b><span id="view_end"></span></div>
            <div class="intern-view-box"><b>Fee</b><span id="view_fee"></span></div>
            <div class="intern-view-box"><b>Paid</b><span id="view_paid"></span></div>
            <div class="intern-view-box"><b>Balance</b><span id="view_balance"></span></div>
            <div class="intern-view-box"><b>Payment Status</b><span id="view_paystatus"></span></div>
            <div class="intern-view-box intern-view-box-full"><b>Notes</b><span id="view_notes"></span></div>
        </div>
    </div>
</div>

</div>
</div>

<script>
(function(){
    document.addEventListener("DOMContentLoaded", function(){
        if (typeof crmDataTable === "function" && document.querySelector('#internshipsTable')) {
            crmDataTable('#internshipsTable', {
                pageLength: <?= (int) $perPage ?>,
                lengthMenu: [5, 10, 12, 20, 50, 100],
                ordering: true,
                searchPlaceholder: "Search internship students...",
                language: {
                    emptyTable: "No intern students found"
                },
                dom: "<'dt-top'lf>rt<'dt-bottom'ip>"
            });
        }

        setTimeout(() => {
            const controls = document.querySelector('.intern-table-wrap .dt-top');
            const footer = document.querySelector('.intern-table-wrap .dt-bottom');
            const topTarget = document.getElementById('datatableControls');
            const bottomTarget = document.getElementById('datatableFooter');
            if (controls && topTarget) topTarget.appendChild(controls);
            if (footer && bottomTarget) bottomTarget.appendChild(footer);
        }, 100);

        const paymentStatusInput = document.getElementById('paymentStatusInput');
        const paymentStatusSelect = document.getElementById('paymentStatusSelect');
        if (paymentStatusInput && paymentStatusSelect) {
            const trigger = paymentStatusSelect.querySelector('.ims-trigger');
            const label = paymentStatusSelect.querySelector('.ims-label');
            const panel = paymentStatusSelect.querySelector('.ims-panel');
            const searchWrap = paymentStatusSelect.querySelector('.ims-search-wrap');
            const search = paymentStatusSelect.querySelector('.ims-search');
            const options = Array.from(paymentStatusSelect.querySelectorAll('.ims-option'));

            const syncSearchVisibility = function(){
                const showSearch = options.length > 5;
                if (searchWrap) searchWrap.hidden = !showSearch;
                if (!showSearch && search) search.value = '';
                return showSearch;
            };

            const closePanel = function(){
                paymentStatusSelect.classList.remove('open');
                if (panel) panel.hidden = true;
                if (trigger) trigger.setAttribute('aria-expanded', 'false');
                if (search) search.value = '';
                options.forEach(function(opt){ opt.style.display = ''; });
            };

            const setValue = function(value){
                paymentStatusInput.value = value;
                let selected = options.find(function(opt){
                    return (opt.getAttribute('data-value') || '') === value;
                });
                if (!selected) selected = options[0] || null;
                options.forEach(function(opt){ opt.classList.remove('active'); });
                if (selected) {
                    selected.classList.add('active');
                    if (label) label.textContent = selected.textContent.trim();
                }
            };

            const openPanel = function(){
                paymentStatusSelect.classList.add('open');
                if (panel) panel.hidden = false;
                if (trigger) trigger.setAttribute('aria-expanded', 'true');
                if (syncSearchVisibility() && search) search.focus();
            };

            if (trigger) {
                trigger.addEventListener('click', function(){
                    if (paymentStatusSelect.classList.contains('open')) {
                        closePanel();
                    } else {
                        openPanel();
                    }
                });
            }

            options.forEach(function(opt){
                opt.addEventListener('click', function(){
                    setValue(this.getAttribute('data-value') || '');
                    closePanel();
                });
            });

            if (search) {
                search.addEventListener('input', function(){
                    if (!syncSearchVisibility()) return;
                    const q = (this.value || '').trim().toLowerCase();
                    options.forEach(function(opt){
                        const text = (opt.textContent || '').toLowerCase();
                        opt.style.display = (q === '' || text.includes(q)) ? '' : 'none';
                    });
                });
            }

            document.addEventListener('click', function(e){
                if (!paymentStatusSelect.contains(e.target)) closePanel();
            });

            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape') closePanel();
            });

            setValue(paymentStatusInput.value || '');
            syncSearchVisibility();
        }
    });
})();

(function(){
    const modal = document.getElementById('internModal');
    const backdrop = document.getElementById('internModalBackdrop');
    const closeBtn = document.getElementById('internModalClose');
    const cancelBtn = document.getElementById('internModalCancel');

    const student = document.getElementById('internModalStudent');
    const regId = document.getElementById('modal_registration_id');
    const start = document.getElementById('modal_start_date');
    const end = document.getElementById('modal_end_date');
    const completion = document.getElementById('modal_completion');
    const certificate = document.getElementById('modal_certificate');
    const report = document.getElementById('modal_report');
    const reportDays = document.getElementById('modal_report_days');
    const certificateIssuedAt = document.getElementById('modal_certificate_issued_at');
    const reportIssuedAt = document.getElementById('modal_report_issued_at');
    let isSavedLocked = false;
    let isUnpaidLocked = false;

    const viewModal = document.getElementById('internViewModal');
    const viewBackdrop = document.getElementById('internViewBackdrop');
    const viewClose = document.getElementById('internViewClose');

    function syncInternModalState() {
    const completionValue = completion.value || 'pending';
    const reportValue = report.value || 'not_provided';
    const disableReportControls = completionValue === 'in_progress';
    const fullyLocked = isSavedLocked || isUnpaidLocked;

    start.disabled = fullyLocked;
    end.disabled = fullyLocked;
    completion.disabled = fullyLocked;
    certificate.disabled = fullyLocked || disableReportControls;
    report.disabled = fullyLocked || disableReportControls;
    certificateIssuedAt.disabled = fullyLocked || certificate.value !== 'given';
    reportIssuedAt.disabled = fullyLocked || disableReportControls || reportValue !== 'provided';
    reportDays.disabled = fullyLocked || disableReportControls || reportValue !== 'provided';

    if (!fullyLocked && certificate.value !== 'given') {
        certificateIssuedAt.value = '';
    }

    if (!fullyLocked && reportValue !== 'provided') {
        reportIssuedAt.value = '';
        reportDays.value = '';
    }
}
    function openModal(btn){
        regId.value = btn.dataset.id || '';
        student.textContent = btn.dataset.name || '';
        start.value = btn.dataset.start || '';
        end.value = btn.dataset.end || '';
        completion.value = btn.dataset.completion || 'pending';
        certificate.value = btn.dataset.certificate || 'not_given';
        report.value = btn.dataset.report || 'not_provided';
        reportDays.value = btn.dataset.reportdays || '';
        certificateIssuedAt.value = btn.dataset.certificateissuedat || '';
        reportIssuedAt.value = btn.dataset.reportissuedat || '';
        isSavedLocked = (btn.dataset.originalCompletion === 'completed' && btn.dataset.originalReport === 'provided');
        isUnpaidLocked = (btn.dataset.paymentstatus === 'unpaid');

        syncInternModalState();

        backdrop.style.display = 'block';
        modal.style.display = 'block';
    }

    function closeModal(){
        backdrop.style.display = 'none';
        modal.style.display = 'none';
    }

    function openViewModal(btn){
        document.getElementById('view_student_name').textContent = btn.dataset.name || '-';
        document.getElementById('view_regno').textContent = btn.dataset.regno || '-';
        document.getElementById('view_phone').textContent = btn.dataset.phone || '-';
        document.getElementById('view_email').textContent = btn.dataset.email || '-';
        document.getElementById('view_program').textContent = btn.dataset.program || '-';
        document.getElementById('view_batch').textContent = btn.dataset.batch || '-';
        document.getElementById('view_joined').textContent = btn.dataset.joined || '-';
        document.getElementById('view_notes').textContent = btn.dataset.notes || '-';
        document.getElementById('view_days').textContent = btn.dataset.days || '-';
        document.getElementById('view_ibatch').textContent = btn.dataset.ibatch || '-';
        document.getElementById('view_start').textContent = btn.dataset.start || '-';
        document.getElementById('view_end').textContent = btn.dataset.end || '-';
        document.getElementById('view_fee').textContent = btn.dataset.fee || '-';
        document.getElementById('view_paid').textContent = btn.dataset.paid || '-';
        document.getElementById('view_balance').textContent = btn.dataset.balance || '-';
        document.getElementById('view_paystatus').textContent = btn.dataset.paystatus || '-';

        viewBackdrop.style.display = 'block';
        viewModal.style.display = 'block';
    }

    function closeViewModal(){
        viewBackdrop.style.display = 'none';
        viewModal.style.display = 'none';
    }

    document.querySelectorAll('.manageInternBtn').forEach(btn => {
        btn.addEventListener('click', function(){
            openModal(this);
        });
    });

    document.querySelectorAll('.viewInternBtn').forEach(btn => {
        btn.addEventListener('click', function(){
            openViewModal(this);
        });
    });

    if (completion) {
    completion.addEventListener('change', syncInternModalState);
}

if (report) {
    report.addEventListener('change', syncInternModalState);
}

if (certificate) {
    certificate.addEventListener('change', syncInternModalState);
}

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    if (viewClose) viewClose.addEventListener('click', closeViewModal);
    if (viewBackdrop) viewBackdrop.addEventListener('click', closeViewModal);

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            closeModal();
            closeViewModal();
        }
    });
})();
</script>

