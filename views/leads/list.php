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
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_lead'])){

$token=$_POST['csrf_token']??'';

if(!verifyCSRF($token)){
$error="Invalid request";
}else{

$id=(int)($_POST['id']??0);

try{

if(!$canAllBranches){
$chk=$pdo->prepare("SELECT COUNT(*) FROM leads WHERE id=? AND branch_id=?");
$chk->execute([$id,$branchId]);
if(!$chk->fetchColumn()){
throw new Exception("Access denied");
}
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
    $where[]="l.assigned_to=?";
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

$where[]="(l.name LIKE ? OR l.phone LIKE ? OR l.email LIKE ? OR l.company_college_name LIKE ? OR l.department LIKE ? OR l.lead_year LIKE ?)";
$like="%$q%";
$params[]=$like;
$params[]=$like;
$params[]=$like;
$params[]=$like;
$params[]=$like;
$params[]=$like;

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

/* FETCH */
$rows=[];
try{

$sql="
SELECT
l.*,
u.name assigned_name
FROM leads l
LEFT JOIN users u ON u.id=l.assigned_to
$whereSql
ORDER BY l.id DESC
LIMIT $perPage OFFSET $offset
";

$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

}catch(Exception $e){}

function h($v){return htmlspecialchars($v);}

function badge($s){

$map=[
'new'=>'#2196f3',
'contacted'=>'#ff9800',
'qualified'=>'#9c27b0',
'converted'=>'#2e7d32',
'closed'=>'#607d8b'
];

$c=$map[$s]??'#999';

return "<span style='font-weight:700;color:$c'>".ucfirst($s)."</span>";
}

$baseUrl="index.php?page=leads/list&q=$q&status=$status&assigned_to=$assigned";

?>

<h2 style="margin-bottom:20px;">Lead Management</h2>

<div class="card">

<div class="card-header">Filters</div>

<form method="GET" action="index.php">

<input type="hidden" name="page" value="leads/list">

<div class="filter-row">

<div>
<label>Search</label>
<input type="text" name="q" value="<?=h($q)?>">
</div>

<div>
<label>Status</label>
<select name="status">
<option value="">All</option>
<option value="new">New</option>
<option value="contacted">Contacted</option>
<option value="qualified">Qualified</option>
<option value="converted">Converted</option>
<option value="closed">Closed</option>
</select>
</div>

<div>
<label>Assigned</label>
<select name="assigned_to">
<option value="">All</option>
<?php foreach($staff as $s): ?>
<option value="<?=$s['id']?>"><?=$s['name']?> (<?=$s['role_name']?>)</option>
<?php endforeach; ?>
</select>
</div>

<div class="filter-actions">

<button class="btn btn-primary">Apply</button>

<a href="index.php?page=leads/list" class="btn-reset">Reset</a>

<a href="index.php?page=leads/add" class="btn btn-primary">+ Add Lead</a>

<a href="index.php?page=leads/import" class="btn btn-primary">
    <i class="fas fa-file-excel"></i> Excel Upload
</a>

</div>

</div>

</form>

</div>

<div class="card" style="margin-top:16px;">

<div class="card-header">Leads (<?=$totalRows?>)</div>

<div class="table-wrap">

<table class="lead-table">

<thead>
<tr>
<th>ID</th>
<th>Lead</th>
<th>Contact</th>
<th>Academic / Org</th>
<th>Status</th>
<th>Assigned</th>
<th>Source</th>
<th class="text-center">Action</th>
</tr>
</thead>

<tbody>

<?php if(!$rows): ?>
<tr>
<td colspan="8" style="text-align:center;">No leads found</td>
</tr>
<?php endif; ?>

<?php foreach($rows as $r): ?>

<tr>

<td><?=$r['id']?></td>

<td>
<div class="lead-name"><?=h($r['name'])?></div>
<div class="lead-sub"><?=h($r['course_interest'])?></div>
</td>

<td>
<div><?=h($r['phone'])?></div>
<div class="lead-sub"><?=h($r['email'])?></div>
</td>

<td>
<div><?= h($r['company_college_name']) ?></div>
<div class="lead-sub">
    <?= h($r['department']) ?>
    <?= !empty($r['lead_year']) ? ' | ' . h($r['lead_year']) : '' ?>
</div>
</td>

<td><?=badge($r['status'])?></td>

<td><?=h($r['assigned_name'])?></td>

<td><?=h($r['source'])?></td>

<td class="text-center action-col">

<a href="index.php?page=leads/add&id=<?=$r['id']?>" class="btn-icon edit">
<i class="fas fa-pen"></i>
</a>

<?php if($r['status']=='converted'): ?>

<span class="btn-icon done">
<i class="fas fa-check"></i>
</span>

<?php else: ?>

<a href="index.php?page=enquiries/add&lead_id=<?=$r['id']?>" class="btn-icon convert">
<i class="fas fa-exchange-alt"></i>
</a>

<?php endif; ?>

<form method="POST" class="deleteForm" style="display:inline">
<input type="hidden" name="csrf_token" value="<?=generateCSRF()?>">
<input type="hidden" name="id" value="<?=$r['id']?>">
<input type="hidden" name="delete_lead" value="1">

<button class="btn-icon delete">
<i class="fas fa-trash"></i>
</button>

</form>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<div class="pagination">

<a href="<?=$baseUrl?>&p=1"><i class="fas fa-angle-double-left"></i></a>

<a href="<?=$baseUrl?>&p=<?=max(1,$page-1)?>">
<i class="fas fa-angle-left"></i>
</a>

<span class="page-info">Page <?=$page?> / <?=$totalPages?></span>

<a href="<?=$baseUrl?>&p=<?=min($totalPages,$page+1)?>">
<i class="fas fa-angle-right"></i>
</a>

<a href="<?=$baseUrl?>&p=<?=$totalPages?>">
<i class="fas fa-angle-double-right"></i>
</a>

</div>

</div>

<style>

/* FILTER */
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
min-width:200px;
}

.filter-actions{
display:flex;
gap:10px;
align-items:center;
}

/* TABLE */
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

/* ACTION BUTTONS */
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
}

.edit{background:#fff3e0;color:#fb8c00;}
.convert{background:#e8f5e9;color:#2e7d32;}
.delete{background:#ffebee;color:#e53935;}

/* PAGINATION */
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
.done{
background:#e3f2fd;
color:#1565c0;
cursor:default;
}
</style>

<script>

document.querySelectorAll('.deleteForm').forEach(form=>{
form.addEventListener('submit',function(e){

if(this.dataset.confirmed)return;

e.preventDefault();

Swal.fire({
title:'Delete Lead?',
icon:'warning',
showCancelButton:true,
confirmButtonText:'Delete'
}).then(r=>{
if(r.isConfirmed){
this.dataset.confirmed=1;
this.submit();
}
});

});
});

</script>