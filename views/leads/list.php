 <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/lead.css">
<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success="";
$error="";

$userId=(int)($_SESSION['user_id']??0);
$roleId=(int)($_SESSION['role_id']??0);
$branchId=(int)($_SESSION['branch_id']??0);

/* Branch Access */
$canAllBranches=0;
try{
$st=$pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=?");
$st->execute([$roleId]);
$canAllBranches=(int)$st->fetchColumn();
}catch(Exception $e){}

/* DELETE */
/* DELETE */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_lead'])){

$token=$_POST['csrf_token']??'';

if(!verifyCSRF($token)){
$error="Invalid request";
}else{

$id=(int)($_POST['id']??0);

try{

/* ✅ Only creator + not converted */
$chk=$pdo->prepare("
SELECT COUNT(*) FROM leads 
WHERE id=? 
AND created_by=? 
AND status!='converted'
");
$chk->execute([$id,$userId]);

if(!$chk->fetchColumn()){
throw new Exception("Access denied or already converted");
}

$st=$pdo->prepare("DELETE FROM leads WHERE id=?");
$st->execute([$id]);

$success="Lead deleted";

}catch(Exception $e){
$error=$e->getMessage();
}

}
}

/* FILTERS */
$q=trim($_GET['q']??'');
$status=trim($_GET['status']??'');
$assigned=(int)($_GET['assigned_to']??0);

/* Pagination */
$page=(int)($_GET['p']??1);
if($page<1)$page=1;

$perPage=10;
$offset=($page-1)*$perPage;

/* Staff */
$staff=[];
try{
$st=$pdo->query("
SELECT u.id,u.name,r.role_name
FROM users u
LEFT JOIN roles r ON r.id=u.role_id
WHERE u.status=1
ORDER BY r.role_name,u.name
");
$staff=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){}

/* WHERE */
/* WHERE */
$where=[];
$params=[];

/* Branch Filter */
if(!$canAllBranches){
    $where[]="l.branch_id=?";
    $params[]=$branchId;
}

/* Assigned Lead Visibility */
$allowedRolesToSeeAll = ['Super Admin','HR','Marketing'];

if(!in_array($_SESSION['role_name'],$allowedRolesToSeeAll)){
    $where[]="(l.assigned_to=? OR l.created_by=?)";
    $params[]=$userId;
    $params[]=$userId;
}

if($status!=''){
$where[]="l.status=?";
$params[]=$status;
}

if($assigned>0){
$where[]="l.assigned_to=?";
$params[]=$assigned;
}

if($q != ''){
    $where[] = "(l.name LIKE ? OR l.phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereSql='';
if($where)$whereSql="WHERE ".implode(" AND ",$where);

/* COUNT */
$totalRows=0;
try{
$st=$pdo->prepare("SELECT COUNT(*) FROM leads l $whereSql");
$st->execute($params);
$totalRows=$st->fetchColumn();
}catch(Exception $e){}

$totalPages=max(1,ceil($totalRows/$perPage));

/* FETCH - Get ALL records for DataTable */
$rows=[];
try{

$sql="
SELECT 
l.id,
l.name,
l.phone,
l.email,
l.company_college_name,
l.department,
l.lead_year,
l.status,
l.assigned_to,
l.created_by,
l.source,
l.course_interest,
u.name assigned_name
FROM leads l
LEFT JOIN users u ON u.id=l.assigned_to
$whereSql
ORDER BY l.id DESC
"; // Removed LIMIT and OFFSET

$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

// Update total rows to actual count
$totalRows = count($rows);

}catch(Exception $e){
    $totalRows = 0;
}

function h($v){
return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function badge($s){

$map=[
'new'=>'#2196f3',
'contacted'=>'#ff9800',
'qualified'=>'#9c27b0',
'converted'=>'#2e7d32',
'closed'=>'#607d8b'
];

$c=$map[$s]??'#999';

return "<span class='status-badge' style='--badge-color:$c'>".ucfirst($s)."</span>";
}

$baseUrl="index.php?page=leads/list&q=$q&status=$status&assigned_to=$assigned";

?>

<!-- Display success/error messages if any -->
<?php if($success): ?>
<div class="alert-success"><?=h($success)?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert-error"><?=h($error)?></div>
<?php endif; ?>

<div class="leads-dashboard">
    <!-- Header with title and stats -->
    <div class="dashboard-header">
        <h2><i class="fas fa-users" style="margin-right: 12px; color: #e91e63;"></i>Lead Management</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-database"></i> Total: <?=$totalRows?></span>
        </div>
    </div>

    <!-- Main Card -->
 <!-- Filter Section - Icons only with tooltips -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-sliders-h" style="margin-right: 8px;"></i> Filter Leads
    </div>
    
    <form method="GET" action="index.php" class="filter-form">
        <input type="hidden" name="page" value="leads/list">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="q" value="<?=h($q)?>" placeholder="Name or phone...">
            </div>
            
            <div class="filter-item">
                <label><i class="fas fa-tag"></i> Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="new" <?=$status=='new'?'selected':''?>>New</option>
                    <option value="contacted" <?=$status=='contacted'?'selected':''?>>Contacted</option>
                    <option value="qualified" <?=$status=='qualified'?'selected':''?>>Qualified</option>
                    <option value="converted" <?=$status=='converted'?'selected':''?>>Converted</option>
                    <option value="closed" <?=$status=='closed'?'selected':''?>>Closed</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label><i class="fas fa-user-check"></i> Assigned</label>
                <select name="assigned_to">
                    <option value="">All Assignees</option>
                    <?php foreach($staff as $s): ?>
                    <option value="<?=$s['id']?>" <?=$assigned==$s['id']?'selected':''?>><?=$s['name']?> (<?=$s['role_name']?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-icon-only apply" title="Apply filters">
                    <i class="fas fa-filter"></i>
                </button>
                <a href="index.php?page=leads/list" class="btn-icon-only reset" title="Reset filters">
                    <i class="fas fa-undo-alt"></i>
                </a>
                <a href="index.php?page=leads/add" class="btn-icon-only add" title="Add new lead">
                    <i class="fas fa-user-plus"></i>
                </a>
                <a href="index.php?page=leads/import" class="btn-icon-only import" title="Excel import">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
        </div>
    </form>
</div>

   <!-- Leads Table Card -->
<div class="card">
    <div class="card-header">
        <div class="table-header-flex">
         <div class="table-title">
            <i class="fas fa-list"></i> Lead List
         </div>

        <!-- ✅ DataTable controls will be injected here -->
         <div id="datatableControls"></div>
        </div>
    </div>
    
    <div class="table-container">
        <table class="leads-table" id="leadsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Lead</th>
                    <th>Contact</th>
                    <th>Academic / Org</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Source</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!$rows): ?>
                <tr>
                    <td colspan="8" class="no-data">
                        <i class="fas fa-inbox"></i> No leads found
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php 
                $sn = $offset + 1;
                foreach($rows as $r): 
                ?>
                
                <?php
                /* =========================
                LEAD PERMISSIONS
                ========================= */
                $roleName = $_SESSION['role_name'] ?? '';
                $canConvert = false;
                $canEdit = false;
                $canDelete = false;

                /* Convert Permission */
                if($roleName === 'Front Office' && $r['assigned_to'] == $userId && $r['status']!='converted'){
                    $canConvert = true;
                }

                /* Edit/Delete Permission */
                if($r['created_by'] == $userId && $r['status']!='converted'){
                    $canEdit = true;
                    $canDelete = true;
                }
                ?>
                
                <tr>
                    <td class="id-col">#<?=$sn?></td>
                    
                    <td class="lead-col">
                        <div class="lead-name"><?=h($r['name'])?></div>
                        <div class="lead-interest"><?=h($r['course_interest'])?></div>
                    </td>
                    
                    <td class="contact-col">
                        <div class="contact-phone">
                            <i class="fas fa-phone-alt"></i> <?=h($r['phone'])?>
                        </div>
                        <div class="contact-email">
                            <i class="fas fa-envelope"></i> <?=h($r['email'])?>
                        </div>
                    </td>
                    
                    <td class="academic-col">
                        <div class="org-name"><?=h($r['company_college_name'])?></div>
                        <div class="dept-year">
                            <?=h($r['department'])?>
                            <?= !empty($r['lead_year']) ? ' | ' . h($r['lead_year']) : '' ?>
                        </div>
                    </td>
                    
                    <td class="status-col"><?=badge($r['status'])?></td>
                    
                    <td class="assigned-col">
                        <span class="assigned-badge">
                            <i class="fas fa-user-circle"></i> <?=h($r['assigned_name'] ?: 'Unassigned')?>
                        </span>
                    </td>
                    
                    <td class="source-col">
    <?php
    $sourceIcon = 'fa-link';
    $iconType = 'fas';

    if($r['source'] == 'Instagram'){
        $sourceIcon = 'fa-instagram';
        $iconType = 'fab';
    }
    elseif($r['source'] == 'Facebook'){
        $sourceIcon = 'fa-facebook';
        $iconType = 'fab';
    }
    elseif($r['source'] == 'Google Ads'){
        $sourceIcon = 'fa-google';
        $iconType = 'fab';
    }
    elseif($r['source'] == 'Walk-in'){
        $sourceIcon = 'fa-walking';
        $iconType = 'fas';
    }
    elseif($r['source'] == 'Website'){
        $sourceIcon = 'fa-globe';
        $iconType = 'fas';
    }
    elseif($r['source'] == 'Reference'){
        $sourceIcon = 'fa-user-friends';
        $iconType = 'fas';
    }
    ?>

    <span class="source-icon" title="<?=h($r['source'])?>">
        <i class="<?=$iconType?> <?=$sourceIcon?>"></i>
    </span>
</td>
                    
                    <td class="actions-col">
                        <div class="action-buttons">
                            <?php if($canEdit): ?>
                            <a href="index.php?page=leads/add&id=<?=$r['id']?>" class="action-btn edit" title="Edit Lead">
                                <i class="fas fa-pen"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php if($r['status']=='converted'): ?>
                            <span class="action-btn done" title="Already Converted">
                                <i class="fas fa-check"></i>
                            </span>
                            <?php else: ?>
                                <?php if($canConvert): ?>
                                <a href="index.php?page=enquiries/add&lead_id=<?=$r['id']?>" class="action-btn convert" title="Convert to Enquiry">
                                    <i class="fas fa-exchange-alt"></i>
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if($canDelete): ?>
                            <form method="POST" class="delete-form" data-id="<?=$r['id']?>" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?=generateCSRF()?>">
                                <input type="hidden" name="id" value="<?=$r['id']?>">
                                <input type="hidden" name="delete_lead" value="1">
                                <button type="submit" class="action-btn delete" title="Delete Lead">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php $sn++; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Server-side pagination (hidden by default, shown only if DataTable fails) -->
    <?php if($totalPages > 1): ?>
    <div class="pagination-wrapper server-pagination" style="display: none;">
        <div class="pagination-info">
            Showing <?=($offset+1)?> to <?=min($offset+$perPage, $totalRows)?> of <?=$totalRows?> entries
        </div>
        <div class="pagination">
            <?php if($page > 1): ?>
            <a href="<?=$baseUrl?>&p=<?=($page-1)?>" class="page-link prev">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for($i = $start; $i <= $end; $i++):
            ?>
            <a href="<?=$baseUrl?>&p=<?=$i?>" class="page-link <?=$i==$page?'active':''?>"><?=$i?></a>
            <?php endfor; ?>
            
            <?php if($page < $totalPages): ?>
            <a href="<?=$baseUrl?>&p=<?=($page+1)?>" class="page-link next">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>


<script>
// Per page change
function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('p', 1);
    window.location.href = url.toString();
}

document.addEventListener("DOMContentLoaded", function(){
    document.querySelectorAll('.btn-icon-only[title], .action-btn[title], .source-icon[title]').forEach(el => {
        const tip = (el.getAttribute('title') || '').trim();
        if (!tip) return;
        el.setAttribute('data-tooltip', tip);
        el.setAttribute('aria-label', tip);
        el.removeAttribute('title');
    });

    // Delete confirmation
    document.querySelectorAll('.delete-form').forEach(form=>{
        form.addEventListener('submit', function(e){
            e.preventDefault();
            
            let id = this.dataset.id;
            let csrf = this.querySelector('[name="csrf_token"]').value;
            
            Swal.fire({
                title: 'Delete Lead?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                confirmButtonColor: '#e91e63'
            }).then(result => {
                if(result.isConfirmed){
                    fetch('index.php?page=leads/list', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `delete_lead=1&id=${id}&csrf_token=${csrf}`
                    })
                    .then(res => res.text())
                    .then(data => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Lead deleted successfully',
                            confirmButtonColor: '#e91e63'
                        }).then(() => {
                            location.reload();
                        });
                    })
                    .catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Something went wrong',
                            confirmButtonColor: '#e91e63'
                        });
                    });
                }
            });
        });
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function(){
    
    crmDataTable('#leadsTable', {
        pageLength: 10,
        lengthMenu: [5, 10, 20, 50, 100],
        ordering: true,
        order: [[0, 'desc']],
        searchPlaceholder: "Search leads...",
        
        dom:
            "<'dt-top'lfB>" +
            "rt" +
            "<'dt-bottom'ip>"
    });

    // ✅ STEP 3 (PASTE HERE ONLY)
    setTimeout(() => {
        const controls = document.querySelector('.dt-top');
        const target = document.getElementById('datatableControls');

        if (controls && target) {
            target.appendChild(controls);
        }
    }, 100);

});
</script>
