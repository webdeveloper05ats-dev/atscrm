<?php
// =====================================
// Permission Management (RBAC) - AJAX
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('permission_management');
}

/* ===============================
FETCH ROLES
=============================== */
$roles = $pdo->query("SELECT id, role_name FROM roles WHERE status=1 ORDER BY id ASC")
             ->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
FETCH MENUS
=============================== */
$allMenus = $pdo->query("
SELECT id, menu_name, menu_slug, parent_id, sort_order
FROM menus
WHERE status=1
ORDER BY parent_id IS NOT NULL, parent_id ASC, sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
BUILD TREE
=============================== */
$menuTree=[];

foreach($allMenus as $m){
    if($m['parent_id']===null){
        $menuTree[$m['id']]=$m;
        $menuTree[$m['id']]['children']=[];
    }
}

foreach($allMenus as $m){
    if($m['parent_id']!==null && isset($menuTree[$m['parent_id']])){
        $menuTree[$m['parent_id']]['children'][]=$m;
    }
}

/* ===============================
AJAX FETCH PERMISSIONS
=============================== */
if(isset($_GET['ajax']) && $_GET['ajax']==='perms'){

header('Content-Type: application/json');

$roleId=(int)($_GET['role_id'] ?? 0);

if($roleId<=0){
echo json_encode(['ok'=>false]);
exit;
}

if($roleId===1){

$perms=[];
foreach($allMenus as $m){
$perms[$m['id']]=['view'=>1,'add'=>1,'edit'=>1,'delete'=>1];
}

echo json_encode(['ok'=>true,'superadmin'=>true,'perms'=>$perms]);
exit;
}

$stmt=$pdo->prepare("
SELECT menu_id,can_view,can_add,can_edit,can_delete
FROM role_permissions
WHERE role_id=?
");

$stmt->execute([$roleId]);

$perms=[];

foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $p){

$perms[$p['menu_id']]=[
'view'=>(int)$p['can_view'],
'add'=>(int)$p['can_add'],
'edit'=>(int)$p['can_edit'],
'delete'=>(int)$p['can_delete']
];

}

echo json_encode(['ok'=>true,'superadmin'=>false,'perms'=>$perms]);
exit;
}

/* ===============================
AJAX SAVE
=============================== */

if(isset($_POST['ajax_save'])){

header('Content-Type: application/json');

$role_id=(int)($_POST['role_id'] ?? 0);

if($role_id<=0){
echo json_encode(['ok'=>false]);
exit;
}

if($role_id===1){
echo json_encode(['ok'=>false]);
exit;
}

$perm=$_POST['perm'] ?? [];

$pdo->beginTransaction();

$pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")
    ->execute([$role_id]);

$ins=$pdo->prepare("
INSERT INTO role_permissions
(role_id,menu_id,can_view,can_add,can_edit,can_delete,created_at,updated_at)
VALUES(?,?,?,?,?,?,NOW(),NOW())
");

foreach($allMenus as $m){

$mid=$m['id'];

$v=isset($perm[$mid]['view'])?1:0;
$a=isset($perm[$mid]['add'])?1:0;
$e=isset($perm[$mid]['edit'])?1:0;
$d=isset($perm[$mid]['delete'])?1:0;

if(!$v){$a=$e=$d=0;}

$ins->execute([$role_id,$mid,$v,$a,$e,$d]);

}

$pdo->commit();

echo json_encode(['ok'=>true]);
exit;
}
?>

<style>

/* ===== Permission Table ===== */

.perm-table{
width:100%;
border-collapse:collapse;
background:#fff;
font-size:14px;
}

.perm-table th{
background:#fce4ec;
color:#e91e63;
padding:12px;
border:1px solid #f3c6d3;
}

.perm-table td{
padding:12px;
border:1px solid #f3c6d3;
}

.perm-parent-row{
background:#fff6fa;
}

.perm-menu-name{
font-weight:600;
}

.perm-menu-slug{
font-size:12px;
color:#777;
}

.perm-child{
padding-left:30px;
}

.perm-center{
text-align:center;
}

/* ===== Toggle Switch ===== */

.perm-switch{
position:relative;
display:inline-block;
width:42px;
height:22px;
}

.perm-switch input{
opacity:0;
width:0;
height:0;
}

.perm-slider{
position:absolute;
cursor:pointer;
top:0;
left:0;
right:0;
bottom:0;
background:#d9d9d9;
border-radius:30px;
transition:.25s;
}

.perm-slider:before{
content:"";
position:absolute;
height:18px;
width:18px;
left:2px;
top:2px;
background:white;
border-radius:50%;
transition:.25s;
box-shadow:0 2px 5px rgba(0,0,0,.2);
}

.perm-switch input:checked + .perm-slider{
background:#e91e63;
}

.perm-switch input:checked + .perm-slider:before{
transform:translateX(20px);
}

/* ===== Buttons ===== */

.permission-actions{
display:flex;
gap:10px;
flex-wrap:wrap;
align-items:center;
margin-bottom:15px;
}

.permission-actions button{
width:auto !important;
display:inline-block;
padding:8px 16px;
border:none;
border-radius:6px;
cursor:pointer;
font-size:13px;
white-space:nowrap;
}

.btn-primary{
background:#e91e63;
color:#fff;
}

.btn{
background:#eee;
}

/* ===== Responsive ===== */

@media(max-width:768px){

.perm-table th,
.perm-table td{
font-size:12px;
padding:8px;
}

.perm-menu-slug{
display:none;
}

}

</style>

<h2>Permission Management</h2>

<div class="card">
<div class="card-header">Select Role</div>

<div style="padding:14px">

<select id="role_select">

<option value="">Select Role</option>

<?php foreach($roles as $r): ?>

<option value="<?= $r['id'] ?>">
<?= htmlspecialchars($r['role_name']) ?>
</option>

<?php endforeach; ?>

</select>

</div>
</div>

<div class="card" id="perm_card" style="display:none">

<form id="perm_form">

<input type="hidden" name="role_id" id="role_id_hidden">
<input type="hidden" name="ajax_save" value="1">

<div class="permission-actions">

<button type="button" class="btn-primary" onclick="toggleAll('view',true)">View All</button>
<button type="button" class="btn-primary" onclick="toggleAll('add',true)">Add All</button>
<button type="button" class="btn-primary" onclick="toggleAll('edit',true)">Edit All</button>
<button type="button" class="btn-primary" onclick="toggleAll('delete',true)">Delete All</button>
<button type="button" class="btn" onclick="toggleAll('view',false)">Clear All</button>

</div>

<div style="overflow:auto">

<table class="perm-table">

<thead>

<tr>
<th>Menu</th>
<th>View</th>
<th>Add</th>
<th>Edit</th>
<th>Delete</th>
</tr>

</thead>

<tbody>

<?php foreach($menuTree as $parent): ?>

<tr class="perm-parent-row">

<td>

<div class="perm-menu-name"><?= $parent['menu_name']?></div>
<div class="perm-menu-slug"><?= $parent['menu_slug']?></div>

</td>

<?php foreach(['view','add','edit','delete'] as $p): ?>

<td class="perm-center">

<label class="perm-switch">

<input type="checkbox"
class="perm-<?= $p ?>"
name="perm[<?= $parent['id']?>][<?= $p ?>]">

<span class="perm-slider"></span>

</label>

</td>

<?php endforeach; ?>

</tr>

<?php foreach($parent['children'] as $child): ?>

<tr>

<td class="perm-child">

<div class="perm-menu-name"><?= $child['menu_name']?></div>
<div class="perm-menu-slug"><?= $child['menu_slug']?></div>

</td>

<?php foreach(['view','add','edit','delete'] as $p): ?>

<td class="perm-center">

<label class="perm-switch">

<input type="checkbox"
class="perm-<?= $p ?>"
name="perm[<?= $child['id']?>][<?= $p ?>]">

<span class="perm-slider"></span>

</label>

</td>

<?php endforeach; ?>

</tr>

<?php endforeach; ?>

<?php endforeach; ?>

</tbody>
</table>

</div>

<button type="submit" class="btn-primary" style="margin-top:15px">
Save Permissions
</button>

</form>

</div>

<script>

/* ===============================
LOAD PERMISSIONS
=============================== */

const roleSelect=document.getElementById("role_select");
const permCard=document.getElementById("perm_card");
const roleHidden=document.getElementById("role_id_hidden");

roleSelect.addEventListener("change",loadPerms);

async function loadPerms(){

let role=roleSelect.value;

if(!role)return;

permCard.style.display="block";
roleHidden.value=role;

let res=await fetch(`index.php?page=permission_management&ajax=perms&role_id=${role}`);
let data=await res.json();

document.querySelectorAll(".perm-view,.perm-add,.perm-edit,.perm-delete")
.forEach(cb=>cb.checked=false);

for(let m in data.perms){

let p=data.perms[m];

let v=document.querySelector(`input[name="perm[${m}][view]"]`);
let a=document.querySelector(`input[name="perm[${m}][add]"]`);
let e=document.querySelector(`input[name="perm[${m}][edit]"]`);
let d=document.querySelector(`input[name="perm[${m}][delete]"]`);

if(v)v.checked=p.view;
if(a)a.checked=p.add;
if(e)e.checked=p.edit;
if(d)d.checked=p.delete;

}

}

/* ===============================
TOGGLE BUTTONS
=============================== */

function toggleAll(type,state){

if(!state){

document.querySelectorAll(".perm-view,.perm-add,.perm-edit,.perm-delete")
.forEach(cb=>cb.checked=false);

return;
}

document.querySelectorAll(".perm-"+type)
.forEach(cb=>cb.checked=true);

if(type!=="view"){
document.querySelectorAll(".perm-view")
.forEach(cb=>cb.checked=true);
}

}

/* ===============================
SAVE
=============================== */

/* ===============================
SAVE PERMISSIONS
=============================== */

/* ===============================
SAVE PERMISSIONS
=============================== */

document.getElementById("perm_form")
.addEventListener("submit",async function(e){

e.preventDefault();

let fd = new FormData(this);
let saveBtn = this.querySelector('button[type="submit"]');

/* Disable button */
saveBtn.disabled = true;
saveBtn.innerText = "Saving...";

/* Show loading popup */
Swal.fire({
title: 'Saving Permissions...',
text: 'Please wait',
allowOutsideClick: false,
didOpen: () => {
Swal.showLoading();
}
});

try{

let res = await fetch(`index.php?page=permission_management`,{
method:"POST",
body:fd
});

let data = await res.json();

if(data.ok){

Swal.fire({
icon: 'success',
title: 'Permissions Updated',
text: 'Permissions saved successfully!',
confirmButtonColor: '#e91e63'
}).then(() => {

location.reload();

});

}else{

Swal.fire({
icon: 'error',
title: 'Error',
text: 'Failed to save permissions',
confirmButtonColor: '#e91e63'
});

}

}catch(err){

Swal.fire({
icon: 'error',
title: 'Server Error',
text: 'Something went wrong!',
confirmButtonColor: '#e91e63'
});

}

/* Enable button again */
saveBtn.disabled = false;
saveBtn.innerText = "Save Permissions";

});

</script>