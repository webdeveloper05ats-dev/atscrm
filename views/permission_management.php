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

if($roleId===1 && empty($perms)){
$perms=[];
foreach($allMenus as $m){
$perms[$m['id']]=['view'=>1,'add'=>1,'edit'=>1,'delete'=>1];
}
}

echo json_encode(['ok'=>true,'superadmin'=>$roleId===1,'perms'=>$perms]);
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

$perm=$_POST['perm'] ?? [];

try{

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

}catch(Throwable $e){
if($pdo->inTransaction()){
$pdo->rollBack();
}
echo json_encode(['ok'=>false,'message'=>'Failed to save permissions. Please try again.']);
exit;
}
}
?>

<div class="perm-page-head">
  <h2 class="perm-page-title">Permission Management</h2>
</div>

<div class="perm-layout">
<div class="perm-sidebar">
<div class="card perm-card perm-sidebar-card">
<div class="card-header perm-card-head">Select Role</div>

<div class="perm-card-body">

<select id="role_select" class="perm-role-select" data-modern-select="on">

<option value="">Select Role</option>

<?php foreach($roles as $r): ?>

<option value="<?= $r['id'] ?>">
<?= htmlspecialchars($r['role_name']) ?>
</option>

<?php endforeach; ?>

</select>

</div>
</div>
</div>

<div class="perm-content">
<div class="card perm-card" id="perm_card" style="display:none">
<div class="card-header perm-card-head perm-main-head">
<div class="perm-main-title"><i class="fas fa-shield-alt"></i> Role Permissions</div>
<div class="perm-main-meta">
<span class="perm-role-chip" id="perm_role_chip">No role selected</span>
<span class="perm-count-badge" id="perm_count_badge">Enabled 0 / 0</span>
<span class="perm-unsaved" id="perm_unsaved_badge">Unsaved changes</span>
<span class="perm-saved" id="perm_saved_badge">Not saved yet</span>
</div>
</div>

<form id="perm_form">

<input type="hidden" name="role_id" id="role_id_hidden">
<input type="hidden" name="ajax_save" value="1">

<div class="perm-loading" id="perm_loading">
<span class="perm-spinner"></span>
<span class="perm-loading-text">Loading permissions...</span>
</div>

<div class="perm-toolbar-wrap">
<div class="perm-toolbar permission-actions">
<div class="perm-toolbar-left">
<input type="text" id="perm_search" class="perm-search" placeholder="Search menu or slug...">
<select id="perm_filter" class="perm-filter" data-modern-select="off">
<option value="all">All Rows</option>
<option value="main">Main Menus</option>
<option value="sub">Sub Menus</option>
<option value="enabled">Only Enabled</option>
</select>
<select id="perm_density" class="perm-filter" data-modern-select="off">
<option value="comfortable">Comfortable</option>
<option value="compact">Compact</option>
</select>
</div>
<div class="perm-toolbar-right">
<button type="button" class="perm-btn-primary" onclick="toggleAll('view',true)">View All</button>
<button type="button" class="perm-btn-primary" onclick="toggleAll('add',true)">Add All</button>
<button type="button" class="perm-btn-primary" onclick="toggleAll('edit',true)">Edit All</button>
<button type="button" class="perm-btn-primary" onclick="toggleAll('delete',true)">Delete All</button>
<button type="button" class="perm-btn" onclick="toggleAll('view',false)">Clear All</button>
</div>
</div>
</div>

<div class="perm-table-wrap">

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

<tr class="perm-parent-row" data-menu-id="<?= (int)$parent['id'] ?>">

<td class="perm-menu-col">

<div class="perm-menu-cell perm-parent-cell">
<span class="perm-row-type">Main Menu</span>
<div class="perm-menu-name"><?= $parent['menu_name']?></div>
<div class="perm-menu-slug"><?= $parent['menu_slug']?></div>
</div>

</td>

<?php foreach(['view','add','edit','delete'] as $p): ?>

<td class="perm-center perm-perm-cell" data-label="<?= strtoupper($p) ?>">

<label class="perm-switch">

<input type="checkbox"
class="perm-<?= $p ?>"
name="perm[<?= $parent['id']?>][<?= $p ?>]"
data-menu-id="<?= (int)$parent['id'] ?>"
data-level="parent"
data-perm="<?= $p ?>">

<span class="perm-slider"></span>

</label>

</td>

<?php endforeach; ?>

</tr>

<?php foreach($parent['children'] as $child): ?>

<tr class="perm-child-row" data-menu-id="<?= (int)$child['id'] ?>" data-parent-id="<?= (int)$parent['id'] ?>">

<td class="perm-child perm-menu-col">

<div class="perm-menu-cell perm-child-cell">
<span class="perm-row-type">Sub Menu</span>
<div class="perm-menu-name"><?= $child['menu_name']?></div>
<div class="perm-menu-slug"><?= $child['menu_slug']?></div>
</div>

</td>

<?php foreach(['view','add','edit','delete'] as $p): ?>

<td class="perm-center perm-perm-cell" data-label="<?= strtoupper($p) ?>">

<label class="perm-switch">

<input type="checkbox"
class="perm-<?= $p ?>"
name="perm[<?= $child['id']?>][<?= $p ?>]"
data-menu-id="<?= (int)$child['id'] ?>"
data-parent-id="<?= (int)$parent['id'] ?>"
data-level="child"
data-perm="<?= $p ?>">

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
<div class="permission-page">
<button type="submit" class="perm-btn-primary">
<i class="fas fa-save"></i>
Save Permissions
</button>
</div>
</form>

</div>
</div>
</div>

<script>

/* ===============================
LOAD PERMISSIONS
=============================== */

const roleSelect=document.getElementById("role_select");
const permCard=document.getElementById("perm_card");
const roleHidden=document.getElementById("role_id_hidden");
const permLoading=document.getElementById("perm_loading");
const permActions=document.querySelector(".permission-actions");
const permTableWrap=document.querySelector(".perm-table-wrap");
const permFooter=document.querySelector(".permission-page");
const permRoleChip=document.getElementById("perm_role_chip");
const permCountBadge=document.getElementById("perm_count_badge");
const permUnsavedBadge=document.getElementById("perm_unsaved_badge");
const permSavedBadge=document.getElementById("perm_saved_badge");
const permSearch=document.getElementById("perm_search");
const permFilter=document.getElementById("perm_filter");
const permDensity=document.getElementById("perm_density");
const PERM_TYPES=["view","add","edit","delete"];
let unsavedChanges=false;
let lastSelectedRole=roleSelect.value || "";

function getPermissionCheckboxes(){
return document.querySelectorAll(".perm-view,.perm-add,.perm-edit,.perm-delete");
}

function updateRoleChip(){
if(!permRoleChip) return;
const selected=roleSelect.options[roleSelect.selectedIndex];
permRoleChip.textContent=selected && selected.value ? selected.text : "No role selected";
}

function updatePermissionCount(){
if(!permCountBadge) return;
if(!roleSelect.value){
permCountBadge.textContent="Enabled 0 / 0";
return;
}
const all=getPermissionCheckboxes();
let checked=0;
all.forEach(function(cb){
if(cb.checked) checked++;
});
permCountBadge.textContent=`Enabled ${checked} / ${all.length}`;
}

function setUnsaved(flag){
unsavedChanges=!!flag;
if(!permUnsavedBadge) return;
permUnsavedBadge.classList.toggle("show",!!flag);
}

function markSavedNow(){
if(!permSavedBadge) return;
const now=new Date();
const h=String(now.getHours()).padStart(2,"0");
const m=String(now.getMinutes()).padStart(2,"0");
permSavedBadge.textContent=`Saved ${h}:${m}`;
}

function setPermissionBusyState(isBusy,message){
if(permLoading){
const textNode=permLoading.querySelector(".perm-loading-text");
if(textNode && message){
textNode.textContent=message;
}
permLoading.classList.toggle("show",!!isBusy);
}
if(permActions) permActions.style.display=isBusy ? "none" : "flex";
if(permTableWrap) permTableWrap.style.display=isBusy ? "none" : "block";
if(permFooter) permFooter.style.display=isBusy ? "none" : "flex";
}

function applyDensityMode(){
if(!permTableWrap || !permDensity) return;
if(permDensity.value==="compact"){
permTableWrap.style.setProperty("--perm-cell-pad","7px");
permTableWrap.classList.add("compact");
}else{
permTableWrap.style.removeProperty("--perm-cell-pad");
permTableWrap.classList.remove("compact");
}
}

function applyViewDependency(menuId){
const viewCb=document.querySelector(`input[data-menu-id="${menuId}"][data-perm="view"]`);
if(!viewCb) return;
["add","edit","delete"].forEach(function(type){
const cb=document.querySelector(`input[data-menu-id="${menuId}"][data-perm="${type}"]`);
if(!cb) return;
if(!viewCb.checked){
cb.checked=false;
cb.disabled=true;
cb.closest(".perm-switch")?.classList.add("disabled");
}else{
cb.disabled=false;
cb.closest(".perm-switch")?.classList.remove("disabled");
}
});
}

function applyViewDependencyAll(){
const seen={};
document.querySelectorAll("input[data-menu-id]").forEach(function(cb){
const id=cb.getAttribute("data-menu-id");
if(id && !seen[id]){
seen[id]=1;
applyViewDependency(id);
}
});
}

function syncParentStates(){
document.querySelectorAll('tr.perm-parent-row[data-menu-id]').forEach(function(row){
const parentId=row.getAttribute("data-menu-id");
PERM_TYPES.forEach(function(type){
const parentCb=document.querySelector(`input[data-level="parent"][data-menu-id="${parentId}"][data-perm="${type}"]`);
const childCbs=document.querySelectorAll(`input[data-level="child"][data-parent-id="${parentId}"][data-perm="${type}"]`);
if(!parentCb || !childCbs.length){
if(parentCb){ parentCb.indeterminate=false; }
return;
}
let checked=0;
childCbs.forEach(function(cb){ if(cb.checked) checked++; });
if(checked===0){
parentCb.checked=false;
parentCb.indeterminate=false;
}else{
parentCb.checked=false;
 parentCb.indeterminate=false;
}
if(checked>0){
 parentCb.checked=true;
 parentCb.indeterminate=false;
}
});
});
}

function rowMatchesText(row,q){
if(!q) return true;
const text=(row.querySelector(".perm-menu-name")?.textContent || "").toLowerCase();
const slug=(row.querySelector(".perm-menu-slug")?.textContent || "").toLowerCase();
return text.includes(q) || slug.includes(q);
}

function rowHasAnyEnabled(row){
return !!row.querySelector('input[type="checkbox"]:checked');
}

function applyRowFilters(){
const q=(permSearch?.value || "").trim().toLowerCase();
const mode=permFilter?.value || "all";
document.querySelectorAll('tr.perm-parent-row[data-menu-id]').forEach(function(parentRow){
const pid=parentRow.getAttribute("data-menu-id");
const childRows=[...document.querySelectorAll(`tr.perm-child-row[data-parent-id="${pid}"]`)];
const parentMatchText=rowMatchesText(parentRow,q);
const parentEnabled=rowHasAnyEnabled(parentRow);
const childVis=childRows.map(function(cr){
const t=rowMatchesText(cr,q);
const en=rowHasAnyEnabled(cr);
if(mode==="main") return false;
if(mode==="sub") return t;
if(mode==="enabled") return t && en;
return t;
});
childRows.forEach(function(cr,idx){
cr.style.display=childVis[idx] ? "" : "none";
});
let parentShow=false;
if(mode==="main"){
parentShow=parentMatchText;
}else if(mode==="sub"){
parentShow=childVis.some(Boolean);
}else if(mode==="enabled"){
parentShow=(parentMatchText && parentEnabled) || childVis.some(Boolean);
}else{
parentShow=parentMatchText || childVis.some(Boolean);
}
parentRow.style.display=parentShow ? "" : "none";
});
}

async function confirmDiscardChanges(){
if(!unsavedChanges) return true;
if(window.Swal && Swal.fire){
const result=await Swal.fire({
icon:"warning",
title:"Discard unsaved changes?",
text:"You have unsaved permission changes for this role.",
showCancelButton:true,
confirmButtonText:"Discard",
cancelButtonText:"Stay",
confirmButtonColor:"#e91e63",
cancelButtonColor:"#6b7280"
});
return !!result.isConfirmed;
}
return window.confirm("Discard unsaved changes?");
}

roleSelect.addEventListener("change",async function(){
const nextRole=roleSelect.value;
if(unsavedChanges && nextRole!==lastSelectedRole){
const ok=await confirmDiscardChanges();
if(!ok){
roleSelect.value=lastSelectedRole;
return;
}
}
await loadPerms();
lastSelectedRole=roleSelect.value || "";
});

async function loadPerms(){

let role=roleSelect.value;

if(!role){
permCard.style.display="none";
roleHidden.value="";
updateRoleChip();
updatePermissionCount();
setUnsaved(false);
return;
}

permCard.style.display="block";
roleHidden.value=role;
updateRoleChip();
roleSelect.disabled=true;
setPermissionBusyState(true,"Loading permissions...");

try{
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
applyViewDependencyAll();
syncParentStates();
applyRowFilters();
updatePermissionCount();
setUnsaved(false);
}catch(err){
Swal.fire({
icon:'error',
title:'Load Failed',
text:'Unable to load permissions right now.',
confirmButtonColor:'#e91e63'
});
}finally{
setPermissionBusyState(false,"Loading permissions...");
roleSelect.disabled=false;
}

}

/* ===============================
TOGGLE BUTTONS
=============================== */

function toggleAll(type,state){

if(!state){

document.querySelectorAll(".perm-view,.perm-add,.perm-edit,.perm-delete")
.forEach(cb=>cb.checked=false);
applyViewDependencyAll();
syncParentStates();
updatePermissionCount();
setUnsaved(true);
return;
}

document.querySelectorAll(".perm-"+type)
.forEach(cb=>cb.checked=true);

if(type!=="view"){
document.querySelectorAll(".perm-view")
.forEach(cb=>cb.checked=true);
}

applyViewDependencyAll();
syncParentStates();
updatePermissionCount();
setUnsaved(true);

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
const defaultSaveHtml=saveBtn.innerHTML;

/* Disable button */
saveBtn.disabled = true;
saveBtn.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Saving...";
roleSelect.disabled = true;
setPermissionBusyState(true,"Saving permissions...");

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
});
setUnsaved(false);
markSavedNow();

}else{

Swal.fire({
icon: 'error',
title: 'Error',
text: data.message || 'Failed to save permissions',
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
setPermissionBusyState(false,"Loading permissions...");
roleSelect.disabled = false;
saveBtn.disabled = false;
saveBtn.innerHTML = defaultSaveHtml;

});

document.getElementById("perm_form").addEventListener("change",function(e){
if(e.target && e.target.matches('input[type="checkbox"]')){
const menuId=e.target.getAttribute("data-menu-id");
const level=e.target.getAttribute("data-level");
const parentId=e.target.getAttribute("data-parent-id");
const permType=e.target.getAttribute("data-perm");

if(level==="parent" && menuId && permType){
document.querySelectorAll(`input[data-level="child"][data-parent-id="${menuId}"][data-perm="${permType}"]`).forEach(function(cb){
cb.checked=e.target.checked;
});
}

if(permType==="view" && menuId){
applyViewDependency(menuId);
if(level==="parent"){
document.querySelectorAll(`input[data-level="child"][data-parent-id="${menuId}"][data-perm="view"]`).forEach(function(cb){
applyViewDependency(cb.getAttribute("data-menu-id"));
});
}
}

if(level==="child" && parentId){
syncParentStates();
}

if(level==="parent"){
syncParentStates();
}

applyRowFilters();
updatePermissionCount();
setUnsaved(true);
}
});

permSearch?.addEventListener("input",applyRowFilters);
permFilter?.addEventListener("change",applyRowFilters);
permDensity?.addEventListener("change",applyDensityMode);

applyDensityMode();
updateRoleChip();
updatePermissionCount();

</script>


