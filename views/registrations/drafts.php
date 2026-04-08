<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/lead.css">
<?php
// =====================================
// Registrations - Draft Conversions
// Slug: registrations/drafts
// UI upgraded only - logic unchanged
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* =============================================
   Session
============================================= */

$userId   = $_SESSION['user_id'] ?? 0;
$branchId = $_SESSION['branch_id'] ?? 0;
$roleId   = $_SESSION['role_id'] ?? 0;

/* =============================================
   Branch Access
============================================= */

$canAllBranches = 0;

$stmt = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=?");
$stmt->execute([$roleId]);
$canAllBranches = (int)$stmt->fetchColumn();

/* =============================================
   Delete Draft
============================================= */

if(isset($_POST['delete_draft'])){

    if(!verifyCSRF($_POST['csrf_token'])){

        $error="Invalid request.";

    }else{

        $regId = (int)$_POST['reg_id'];

        if($regId > 0){

            if($canAllBranches){

                $sql="DELETE FROM registrations WHERE id=? AND registration_status='draft'";

                $stmt=$pdo->prepare($sql);
                $stmt->execute([$regId]);

            }else{

                $sql="DELETE FROM registrations 
                      WHERE id=? 
                      AND branch_id=? 
                      AND registration_status='draft'";

                $stmt=$pdo->prepare($sql);
                $stmt->execute([$regId,$branchId]);

            }

            $success="Draft deleted successfully";

        }

    }

}

/* =============================================
   Load Draft Registrations
============================================= */

$params=[];

$where=" WHERE r.registration_status='draft' ";

if(!$canAllBranches){

    $where.=" AND r.branch_id=? ";
    $params[]=$branchId;

}

$sql="

SELECT
r.id,
r.registration_no,
r.reg_type,
r.program_name,
r.batch_name,
r.enquiry_snapshot_name,
r.enquiry_snapshot_phone,
r.created_at

FROM registrations r

$where

ORDER BY r.id DESC

";

$stmt=$pdo->prepare($sql);
$stmt->execute($params);

$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
    .draft-page {
        padding: 6px 0 12px;
    }

    .draft-hero {
        background: linear-gradient(135deg, #fff7fb 0%, #f8f9ff 55%, #fff 100%);
        border: 1px solid #f1d7e6;
        border-radius: 20px;
        padding: 22px 24px;
        margin-bottom: 20px;
        box-shadow: 0 10px 25px rgba(233, 30, 99, 0.08);
    }

    .draft-hero-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .draft-title-wrap h2 {
        margin: 0;
        font-size: 1.55rem;
        font-weight: 700;
        color: #222;
    }

    .draft-subtitle {
        margin: 6px 0 0;
        color: #6c757d;
        font-size: 0.95rem;
    }

    .draft-stat {
        min-width: 140px;
        background: #fff;
        border: 1px solid #f3d8e5;
        border-radius: 16px;
        padding: 14px 18px;
        text-align: center;
        box-shadow: 0 8px 18px rgba(0,0,0,0.04);
    }

    .draft-stat .count {
        display: block;
        font-size: 1.6rem;
        font-weight: 700;
        color: #e91e63;
        line-height: 1.1;
    }

    .draft-stat .label {
        display: block;
        margin-top: 4px;
        font-size: 0.82rem;
        color: #6c757d;
        letter-spacing: 0.2px;
    }

    .draft-card {
        border: none;
        border-radius: 20px;
        overflow: visible;
        box-shadow: 0 12px 30px rgba(17, 17, 26, 0.08);
        background: #fff;
    }

    .draft-card .card-header {
        background: var(--light);
        color: var(--dark);
        border-bottom: 1px solid var(--border);
        padding: 16px 20px;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .table-header-flex {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .table-title {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
    }

    #datatableControls {
        margin-left: auto;
    }

    #datatableControls .dt-top { width: 100%; }

    .draft-card .card-header small {
        font-weight: 400;
        opacity: 0.95;
    }

    .draft-card .card-body {
        padding: 18px;
    }

    .draft-table-wrap {
        overflow-x: auto;
        overflow-y: visible;
        border-radius: 16px;
        border: 1px solid #f1f1f1;
    }

    .draft-table {
        margin-bottom: 0;
        min-width: 900px;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
    }

    .draft-table thead th {
        background: #fff5f9;
        color: #444;
        font-size: 0.88rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        border-bottom: 1px solid #f0dbe4;
        border-top: none;
        white-space: nowrap;
        padding: 14px 12px;
        vertical-align: middle;
    }

    .draft-table tbody td {
        vertical-align: middle;
        padding: 14px 12px;
        border-top: 1px solid #f5f5f5;
        background: #fff;
    }

    .draft-table th:nth-child(1),
    .draft-table td:nth-child(1) {
        width: 18%;
    }

    .draft-table th:nth-child(2),
    .draft-table td:nth-child(2) {
        width: 24%;
    }

    .draft-table th:nth-child(3),
    .draft-table td:nth-child(3) {
        width: 15%;
    }

    .draft-table th:nth-child(4),
    .draft-table td:nth-child(4) {
        width: 26%;
    }

    .draft-table th:nth-child(5),
    .draft-table td:nth-child(5) {
        width: 14%;
    }

    .draft-table th:nth-child(6),
    .draft-table td:nth-child(6) {
        width: 130px;
        min-width: 130px;
    }

    .draft-table tbody tr:hover td {
        background: #fffafd;
        transition: 0.2s ease;
    }

    /* Use universal DataTable styles from assets/css/style.css */

    .draft-reg-no {
        font-weight: 700;
        color: #222;
    }

    .draft-student {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .draft-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #e91e63, #ff8fab);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.95rem;
        flex-shrink: 0;
        box-shadow: 0 6px 15px rgba(233, 30, 99, 0.18);
    }

    .draft-student-info {
        min-width: 0;
    }

    .draft-student-name {
        font-weight: 600;
        color: #212529;
        line-height: 1.2;
        margin-bottom: 3px;
    }

    .draft-student-meta {
        font-size: 0.83rem;
        color: #6c757d;
        line-height: 1.2;
    }

    .draft-phone {
        font-weight: 500;
        color: #495057;
    }

    .draft-program {
        display: inline-block;
        background: #fdf0f5;
        color: #c2185b;
        border: 1px solid #f5cddd;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 0.84rem;
        font-weight: 600;
        max-width: 220px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .draft-date {
        color: #495057;
        font-weight: 500;
        white-space: nowrap;
    }

    .draft-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex-wrap: nowrap;
    }

    .draft-icon-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        text-decoration: none !important;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all .2s ease;
        position: relative;
    }

    .draft-icon-btn i {
        font-size: 13px;
    }

    .draft-icon-primary {
        color: #fff !important;
        background: linear-gradient(135deg, #e91e63 0%, #ff5f8f 100%);
        box-shadow: 0 8px 16px rgba(233, 30, 99, .20);
    }

    .draft-icon-danger {
        color: #d6336c;
        background: #fff;
        border-color: #f1c8d6;
    }

    .draft-icon-btn:hover {
        transform: translateY(-1px);
    }

    .draft-tip {
        position: relative;
    }

    .draft-tip::after,
    .draft-tip::before {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: .18s ease;
        position: absolute;
        left: 50%;
        transform: translateX(-50%) translateY(4px);
        z-index: 9999;
    }

    .draft-tip::after {
        content: attr(data-tip);
        top: calc(100% + 8px);
        background: #1f2937;
        color: #fff;
        padding: 6px 9px;
        border-radius: 7px;
        font-size: 11px;
        white-space: nowrap;
    }

    .draft-tip::before {
        content: "";
        top: calc(100% + 2px);
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-bottom: 6px solid #1f2937;
    }

    .draft-tip:hover::after,
    .draft-tip:hover::before {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }

    .btn-continue-draft {
        background: linear-gradient(135deg, #e91e63 0%, #ff5f8f 100%);
        border: none;
        color: #fff !important;
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.87rem;
        font-weight: 600;
        text-decoration: none !important;
        box-shadow: 0 8px 18px rgba(233, 30, 99, 0.18);
        transition: all 0.2s ease;
    }

    .btn-continue-draft:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(233, 30, 99, 0.22);
        color: #fff !important;
    }

    .btn-delete-draft {
        border-radius: 10px;
        padding: 8px 14px;
        font-size: 0.87rem;
        font-weight: 600;
        border: 1px solid #f1c8d6;
        background: #fff;
        color: #d6336c;
        transition: all 0.2s ease;
    }

    .btn-delete-draft:hover {
        background: #fff1f5;
        color: #c2185b;
        border-color: #e9a9c0;
    }

    .draft-empty {
        text-align: center;
        padding: 42px 20px !important;
        background: linear-gradient(180deg, #fff 0%, #fffafd 100%) !important;
    }

    .draft-empty-icon {
        width: 70px;
        height: 70px;
        margin: 0 auto 14px;
        border-radius: 50%;
        background: #fff1f6;
        color: #e91e63;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }

    .draft-empty-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #343a40;
        margin-bottom: 4px;
    }

    .draft-empty-text {
        color: #6c757d;
        margin: 0;
        font-size: 0.92rem;
    }

    @media (max-width: 768px) {
        .draft-hero {
            padding: 18px;
        }

        .draft-title-wrap h2 {
            font-size: 1.3rem;
        }

        .draft-card .card-body {
            padding: 14px;
        }

        .draft-actions {
            flex-direction: row;
            align-items: center;
            justify-content: center;
            flex-wrap: nowrap;
        }

        .btn-continue-draft,
        .btn-delete-draft {
            width: 100%;
            text-align: center;
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

<div class="leads-dashboard draft-page">

    <div class="dashboard-header">
        <h2><i class="fas fa-file-alt" style="margin-right: 12px; color: #e91e63;"></i>Draft Registrations</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-database"></i> Total: <?= count($rows) ?></span>
        </div>
    </div>

    <?php if($success): ?>
    <script>
    Swal.fire({
        icon:'success',
        title:'Success',
        text:'<?=addslashes($success)?>'
    }).then(()=>location.reload());
    </script>
    <?php endif; ?>

    <?php if($error): ?>
    <script>
    Swal.fire({
        icon:'error',
        title:'Error',
        text:'<?=addslashes($error)?>'
    });
    </script>
    <?php endif; ?>

    <div class="card draft-card">
        <div class="card-header">
            <div class="table-header-flex">
                <div class="table-title">
                    <i class="fas fa-layer-group"></i> Draft Registration Queue
                </div>
                <div id="datatableControls"></div>
            </div>
        </div>

        <div class="card-body">
            <div class="draft-table-wrap" id="draftsTableArea">
                <table class="table leads-table draft-table" id="draftsTable">
                    <thead>
                        <tr>
                            <th>Registration No</th>
                            <th>Student</th>
                            <th>Phone</th>
                            <th>Program</th>
                            <th>Created Date</th>
                            <th width="170">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach($rows as $r): ?>
                        <?php
                            $studentName = trim((string)($r['enquiry_snapshot_name'] ?? 'Student'));
                            $initial = strtoupper(substr($studentName, 0, 1));
                            $programName = trim((string)($r['program_name'] ?? ''));
                            $regType = trim((string)($r['reg_type'] ?? ''));
                        ?>
                        <tr>
                            <td>
                                <div class="draft-reg-no">
                                    <?= h($r['registration_no']) ?>
                                </div>
                            </td>

                            <td>
                                <div class="draft-student">
                                    <div class="draft-avatar"><?= h($initial ?: 'S') ?></div>
                                    <div class="draft-student-info">
                                        <div class="draft-student-name"><?= h($studentName ?: '-') ?></div>
                                        <div class="draft-student-meta">
                                            <?= h($regType ?: 'Draft Registration') ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="draft-phone">
                                    <i class="fas fa-phone-alt mr-1 text-muted"></i>
                                    <?= h(visibleStudentContactValue($r['enquiry_snapshot_phone'])) ?>
                                </span>
                            </td>

                            <td>
                                <span class="draft-program" title="<?= h($programName ?: '-') ?>">
                                    <?= h($programName ?: '-') ?>
                                </span>
                            </td>

                            <td>
                                <span class="draft-date">
                                    <i class="far fa-calendar-alt mr-1 text-muted"></i>
                                    <?= date("d-m-Y", strtotime($r['created_at'])) ?>
                                </span>
                            </td>

                            <td>
                                <div class="draft-actions">
                                    <a
href="index.php?page=registrations/convert & reg_id=<?= $r['id'] ?>&type=<?= h($r['reg_type']) ?>"
class="draft-icon-btn draft-icon-primary draft-tip"
data-tip="Convert Registration"
title="Convert Registration"
>
<i class="fas fa-arrow-right"></i>
</a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                        <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">

                                        <button
                                            type="submit"
                                            name="delete_draft"
                                            class="draft-icon-btn draft-icon-danger deleteDraftBtn draft-tip"
                                            data-tip="Delete Draft"
                                            title="Delete Draft"
                                        >
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
    if (typeof crmDataTable === "function") {
        crmDataTable('#draftsTable', {
            pageLength: 10,
            lengthMenu: [5, 10, 20, 50, 100],
            autoWidth: false,
            ordering: true,
            order: [[4, 'desc']],
            searchPlaceholder: "Search drafts...",
            language: {
                emptyTable: "No draft registrations found"
            },
            dom:
                "<'dt-top'lf>" +
                "rt" +
                "<'dt-bottom'ip>"
        });
    }

    setTimeout(() => {
        const controls = document.querySelector('#draftsTableArea .dt-top');
        const target = document.getElementById('datatableControls');
        if (controls && target) {
            target.appendChild(controls);
        }
    }, 100);

    document.querySelectorAll("[title]").forEach(function(el){
        const t = (el.getAttribute("title") || "").trim();
        if (!t) return;
        if (!el.classList.contains("draft-tip")) {
            el.classList.add("draft-tip");
        }
        if (!el.getAttribute("data-tip")) {
            el.setAttribute("data-tip", t);
        }
        el.removeAttribute("title");
    });
});

document.addEventListener("click", function(e){
    const btn = e.target.closest(".deleteDraftBtn");
    if(!btn) return;

    e.preventDefault();

    const form = btn.closest("form");
    if(!form) return;

    Swal.fire({
        title:"Delete Draft?",
        text:"This draft will be permanently deleted.",
        icon:"warning",
        showCancelButton:true,
        confirmButtonText:"Delete",
        confirmButtonColor:"#e91e63"
    }).then((r)=>{
        if(r.isConfirmed){
            form.submit();
        }
    });
});
</script>

