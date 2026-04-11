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


