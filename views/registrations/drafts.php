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
        overflow: hidden;
        box-shadow: 0 12px 30px rgba(17, 17, 26, 0.08);
        background: #fff;
    }

    .draft-card .card-header {
        background: linear-gradient(135deg, #e91e63 0%, #ff5f8f 100%);
        color: #fff;
        border: none;
        padding: 16px 20px;
        font-size: 1rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .draft-card .card-header small {
        font-weight: 400;
        opacity: 0.95;
    }

    .draft-card .card-body {
        padding: 18px;
    }

    .draft-table-wrap {
        overflow-x: auto;
        border-radius: 16px;
        border: 1px solid #f1f1f1;
    }

    .draft-table {
        margin-bottom: 0;
        min-width: 900px;
        border-collapse: separate;
        border-spacing: 0;
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

    .draft-table tbody tr:hover td {
        background: #fffafd;
        transition: 0.2s ease;
    }

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
        gap: 8px;
        flex-wrap: wrap;
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
            flex-direction: column;
            align-items: stretch;
        }

        .btn-continue-draft,
        .btn-delete-draft {
            width: 100%;
            text-align: center;
        }
    }
</style>

<div class="draft-page">

    <div class="draft-hero">
        <div class="draft-hero-top">
            <div class="draft-title-wrap">
                <h2><i class="fas fa-file-alt mr-2"></i>Draft Registrations</h2>
                <p class="draft-subtitle">
                    Review incomplete registration conversions, continue where you left off, or remove unwanted drafts.
                </p>
            </div>

            <div class="draft-stat">
                <span class="count"><?= count($rows) ?></span>
                <span class="label">Total Drafts</span>
            </div>
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
            <span><i class="fas fa-layer-group mr-2"></i>Draft Registration Queue</span>
            <small>Manage all saved draft conversions from one place</small>
        </div>

        <div class="card-body">
            <div class="draft-table-wrap">
                <table class="table draft-table">
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

                    <?php if(!$rows): ?>
                        <tr>
                            <td colspan="6" class="draft-empty">
                                <div class="draft-empty-icon">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <div class="draft-empty-title">No draft registrations found</div>
                                <p class="draft-empty-text">Any incomplete registration conversion will appear here.</p>
                            </td>
                        </tr>
                    <?php endif; ?>

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
                                    <?= h($r['enquiry_snapshot_phone']) ?>
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
                                        href="index.php?page=registrations/convert & reg_id=<?=$r['id']?>"
                                        class="btn-continue-draft"
                                    >
                                        <i class="fas fa-arrow-right mr-1"></i> Continue
                                    </a>

                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRF() ?>">
                                        <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">

                                        <button
                                            type="submit"
                                            name="delete_draft"
                                            class="btn-delete-draft deleteDraftBtn"
                                        >
                                            <i class="fas fa-trash-alt mr-1"></i> Delete
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
document.querySelectorAll(".deleteDraftBtn").forEach(btn=>{

    btn.addEventListener("click",function(e){

        e.preventDefault();

        let form=this.closest("form");

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

});
</script>