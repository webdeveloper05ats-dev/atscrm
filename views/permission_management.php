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
:root {
  --perm-primary: #e91e63;
  --perm-primary-dark: #c2185b;
  --perm-primary-soft: #fff3f8;
  --perm-border: #f1d6e3;
  --perm-text: #374151;
  --perm-muted: #6b7280;
  --perm-bg: #fff7fb;
  --perm-shadow: 0 8px 18px rgba(0, 0, 0, .06);
}

.perm-page-head {
  margin-bottom: 12px;
}

.perm-page-title {
  margin: 0;
  color: #be185d;
  font-weight: 800;
  letter-spacing: .2px;
}

.perm-layout {
  display: grid;
  grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
  gap: 14px;
  align-items: start;
}

.perm-card {
  border: 1px solid var(--perm-border);
  border-radius: 14px;
  background: #fff;
  box-shadow: var(--perm-shadow);
  overflow: hidden;
  margin-bottom: 14px;
}

.perm-sidebar-card {
  position: sticky;
  top: 14px;
}

.perm-card-head {
  padding: 12px 14px;
  border-bottom: 1px solid var(--perm-border);
  background: var(--perm-primary-soft);
  color: var(--perm-primary-dark);
  font-weight: 700;
}

.perm-main-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.perm-main-title {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-weight: 800;
}

.perm-main-meta {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.perm-role-chip,
.perm-count-badge,
.perm-unsaved,
.perm-saved {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 5px 10px;
  font-size: .76rem;
  font-weight: 700;
  border: 1px solid #f3d8e5;
  background: #fff;
  color: #7c2d5a;
}

.perm-count-badge {
  color: #be185d;
}

.perm-unsaved {
  display: none;
  color: #9a3412;
  border-color: #fdba74;
  background: #fff7ed;
}

.perm-unsaved.show {
  display: inline-flex;
}

.perm-saved {
  color: #166534;
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.perm-card-body {
  padding: 14px;
}

.perm-role-select {
  width: 100%;
  max-width: 360px;
  min-height: 40px;
  border: 1px solid var(--perm-border);
  border-radius: 10px;
  padding: 8px 10px;
  font-size: .9rem;
  color: var(--perm-text);
  outline: none;
  background: #fff;
}

.perm-role-select:focus {
  border-color: var(--perm-primary);
  box-shadow: 0 0 0 3px rgba(233, 30, 99, .12);
}

.perm-toolbar-wrap {
  overflow-x: auto;
  padding-bottom: 4px;
}

.perm-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
  margin-bottom: 12px;
  overflow: visible;
  min-width: max-content;
}

.perm-toolbar-left {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex: 1 1 auto;
}

.perm-toolbar-right {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.perm-search,
.perm-filter {
  min-height: 36px;
  border: 1px solid var(--perm-border);
  border-radius: 9px;
  padding: 7px 10px;
  font-size: 13px;
  background: #fff;
  color: var(--perm-text);
}

.perm-search {
  min-width: 210px;
  flex: 1;
}

.perm-filter {
  min-width: 130px;
}

.perm-toolbar-right button {
  border: none;
  border-radius: 10px;
  min-height: 36px;
  padding: 8px 12px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: all .2s ease;
}

.perm-btn-primary {
  background: linear-gradient(135deg, #ff4d8d, var(--perm-primary));
  color: #fff;
  box-shadow: 0 6px 14px rgba(233, 30, 99, .25);
}

.perm-btn-primary:hover {
  background: linear-gradient(135deg, #ff3b82, #d81b60);
  transform: translateY(-1px);
}

.perm-btn-primary:active {
  transform: scale(.98);
}

.perm-btn {
  background: #f3f4f6;
  color: #374151;
}

.perm-btn:hover {
  background: #e5e7eb;
}

.perm-table-wrap {
  overflow: auto;
  border: 1px solid var(--perm-border);
  border-radius: 10px;
  max-height: 62vh;
}

.perm-table {
  width: 100%;
  min-width: 720px;
  border-collapse: collapse;
  background: #fff;
  font-size: 14px;
}

.perm-table th {
  background: #fff0f7;
  color: var(--perm-primary-dark);
  padding: var(--perm-cell-pad, 11px);
  border-bottom: 2px solid var(--perm-border);
  text-transform: uppercase;
  font-size: .73rem;
  letter-spacing: .4px;
  position: sticky;
  top: 0;
  z-index: 2;
}

.perm-table td {
  padding: var(--perm-cell-pad, 11px);
  border-bottom: 1px solid #f7e2eb;
}

.perm-table tbody tr:nth-child(even) {
  background: #fffafd;
}

.perm-table tbody tr:hover {
  background: #fff2f8;
}

.perm-parent-row {
  background: #fff4fa !important;
}

.perm-child-row {
  background: #fff !important;
}

.perm-menu-name {
  font-weight: 700;
  color: var(--perm-text);
}

.perm-menu-slug {
  font-size: 12px;
  color: var(--perm-muted);
}

.perm-menu-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.perm-row-type {
  display: inline-flex;
  align-self: flex-start;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .3px;
  text-transform: uppercase;
  margin-bottom: 3px;
}

.perm-parent-cell .perm-row-type {
  background: #fde4ef;
  color: #be185d;
  border: 1px solid #f8cfe1;
}

.perm-child-cell .perm-row-type {
  background: #f6f7fb;
  color: #4b5563;
  border: 1px solid #e5e7eb;
}

.perm-child {
  padding-left: 40px !important;
  position: relative;
}

.perm-child::before {
  content: "";
  position: absolute;
  left: 20px;
  top: 16px;
  bottom: 16px;
  width: 2px;
  background: #f4d6e4;
  border-radius: 2px;
}

.perm-center {
  text-align: center;
}

.perm-switch {
  position: relative;
  display: inline-block;
  width: 42px;
  height: 22px;
}

.perm-switch.disabled {
  opacity: .45;
}

.perm-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.perm-slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background: #d1d5db;
  border-radius: 30px;
  transition: .25s;
}

.perm-slider:before {
  content: "";
  position: absolute;
  height: 18px;
  width: 18px;
  left: 2px;
  top: 2px;
  background: #fff;
  border-radius: 50%;
  transition: .25s;
  box-shadow: 0 2px 5px rgba(0, 0, 0, .2);
}

.perm-switch input:checked + .perm-slider {
  background: var(--perm-primary);
}

.perm-switch input:checked + .perm-slider:before {
  transform: translateX(20px);
}

.permission-page {
  margin-top: 14px;
  display: flex;
  justify-content: flex-end;
  padding-top: 12px;
  border-top: 1px solid #f2d7e4;
  position: sticky;
  bottom: 0;
  background: #fff;
  z-index: 3;
}

.permission-page button {
  padding: 10px 16px;
}

.permission-page .perm-btn-primary {
  min-height: 42px;
  min-width: 170px;
  border-radius: 12px;
  font-weight: 700;
  letter-spacing: .2px;
  box-shadow: 0 8px 16px rgba(233, 30, 99, .24);
}

.perm-loading {
  display: none;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 18px 10px;
  color: var(--perm-muted);
  font-size: .9rem;
}

.perm-loading.show {
  display: inline-flex;
}

.perm-spinner {
  width: 18px;
  height: 18px;
  border: 2px solid #f4cddd;
  border-top-color: var(--perm-primary);
  border-radius: 50%;
  animation: perm-spin .7s linear infinite;
}

@keyframes perm-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
  .perm-layout {
    grid-template-columns: 1fr;
  }
  .perm-sidebar-card {
    position: static;
    top: auto;
  }
  .perm-role-select {
    max-width: 100%;
  }
  .perm-table th,
  .perm-table td {
    font-size: 12px;
    padding: 8px;
  }
  .perm-main-head {
    align-items: stretch;
  }
  .perm-main-meta {
    width: 100%;
  }
  .perm-toolbar {
    gap: 6px;
  }
  .perm-filter {
    min-width: 116px;
  }
  .perm-search { min-width: 170px; }
  .perm-menu-slug {
    display: none;
  }
}
</style>

<div class="perm-page-head">
  <h2 class="perm-page-title">Permission Management</h2>
</div>

<div class="perm-layout">
<div class="perm-sidebar">
<div class="card perm-card perm-sidebar-card">
<div class="card-header perm-card-head">Select Role</div>

<div class="perm-card-body">

<select id="role_select" class="perm-role-select">

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
Loading permissions...
</div>

<div class="perm-toolbar-wrap">
<div class="perm-toolbar permission-actions">
<div class="perm-toolbar-left">
<input type="text" id="perm_search" class="perm-search" placeholder="Search menu or slug...">
<select id="perm_filter" class="perm-filter">
<option value="all">All Rows</option>
<option value="main">Main Menus</option>
<option value="sub">Sub Menus</option>
<option value="enabled">Only Enabled</option>
</select>
<select id="perm_density" class="perm-filter">
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

<td>

<div class="perm-menu-cell perm-parent-cell">
<span class="perm-row-type">Main Menu</span>
<div class="perm-menu-name"><?= $parent['menu_name']?></div>
<div class="perm-menu-slug"><?= $parent['menu_slug']?></div>
</div>

</td>

<?php foreach(['view','add','edit','delete'] as $p): ?>

<td class="perm-center">

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

<td class="perm-child">

<div class="perm-menu-cell perm-child-cell">
<span class="perm-row-type">Sub Menu</span>
<div class="perm-menu-name"><?= $child['menu_name']?></div>
<div class="perm-menu-slug"><?= $child['menu_slug']?></div>
</div>

</td>

<?php foreach(['view','add','edit','delete'] as $p): ?>

<td class="perm-center">

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
}else if(checked===childCbs.length){
parentCb.checked=true;
parentCb.indeterminate=false;
}else{
parentCb.checked=false;
parentCb.indeterminate=true;
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
if(permActions) permActions.style.display="none";
if(permTableWrap) permTableWrap.style.display="none";
if(permFooter) permFooter.style.display="none";
if(permLoading) permLoading.classList.add("show");

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
if(permLoading) permLoading.classList.remove("show");
if(permActions) permActions.style.display="flex";
if(permTableWrap) permTableWrap.style.display="block";
if(permFooter) permFooter.style.display="flex";
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
});
setUnsaved(false);
markSavedNow();

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
