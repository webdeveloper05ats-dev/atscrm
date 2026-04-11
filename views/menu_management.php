<?php
requireView('menu_management');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success="";
$error="";

$protectedSlugs=['menu_management'];

/* ===============================
ADD MENU
=============================== */

if(isset($_POST['add_menu'])){

$menu_name=trim($_POST['menu_name']??'');
$menu_slug=trim($_POST['menu_slug']??'');
$parent_id=($_POST['parent_id']??'')!==''?(int)$_POST['parent_id']:null;
$icon=trim($_POST['icon']??'');
$sort_order=(int)($_POST['sort_order']??0);
$status=(int)($_POST['status']??1);

if($menu_name=='' || $menu_slug==''){
$error="Menu Name and Menu Slug are required.";
}else{
try{

$chk=$pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_slug=?");
$chk->execute([$menu_slug]);

if($chk->fetchColumn()>0){
$error="This Menu Slug already exists.";
}else{

$stmt=$pdo->prepare("
INSERT INTO menus
(menu_name,menu_slug,parent_id,icon,sort_order,status,created_at,updated_at)
VALUES(?,?,?,?,?,?,NOW(),NOW())
");

$stmt->execute([$menu_name,$menu_slug,$parent_id,$icon,$sort_order,$status]);

$newMenuId=(int)$pdo->lastInsertId();

$stmtPerm=$pdo->prepare("
INSERT INTO role_permissions
(role_id,menu_id,can_view,can_add,can_edit,can_delete,created_at,updated_at)
VALUES(1,?,1,1,1,1,NOW(),NOW())
");

$stmtPerm->execute([$newMenuId]);

$success="Menu Added Successfully!";
}
}catch(Throwable $e){
$error="Unable to add menu right now. Please try again.";
}
}
}

/* ===============================
DELETE MENU
=============================== */

if(isset($_GET['delete'])){

$deleteId=(int)$_GET['delete'];

if($deleteId<=0){
$error="Invalid menu selected for deletion.";
}else{
try{
$stmt=$pdo->prepare("SELECT menu_slug FROM menus WHERE id=?");
$stmt->execute([$deleteId]);
$row=$stmt->fetch(PDO::FETCH_ASSOC);

if($row){

$slug=$row['menu_slug'];

if(!in_array($slug,$protectedSlugs,true)){

$pdo->prepare("DELETE FROM role_permissions WHERE menu_id=?")->execute([$deleteId]);
$pdo->prepare("DELETE FROM menus WHERE id=?")->execute([$deleteId]);

$success="Menu Deleted Successfully!";
}else{
$error="This menu is protected and cannot be deleted.";
}
}else{
$error="Menu not found or already deleted.";
}
}catch(Throwable $e){
$error="Unable to delete menu right now. Please try again.";
}
}
}

/* ===============================
TOTAL MENUS
=============================== */

$total=$pdo->query("SELECT COUNT(*) FROM menus")->fetchColumn();

/* ===============================
FETCH MENUS
=============================== */

$stmt=$pdo->prepare("
SELECT m.*,p.menu_name AS parent_name
FROM menus m
LEFT JOIN menus p ON m.parent_id=p.id
ORDER BY m.parent_id IS NOT NULL,m.sort_order ASC
");

$stmt->execute();
$menus=$stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
PARENT MENUS
=============================== */

$parentMenus=$pdo->query("
SELECT id,menu_name
FROM menus
WHERE parent_id IS NULL
ORDER BY sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="menu-page-head">
  <h2 style="margin:0;">Menu Management</h2>
  <div class="menu-total-badge">
    <i class="fas fa-database"></i>
    Total Menus: <?= (int)$total ?>
  </div>
</div>

<?php if($success): ?>
<script>
Swal.fire({
icon:'success',
title:'Success',
text:'<?=addslashes($success)?>'
}).then(()=>{
window.location.href="index.php?page=menu_management";
});
</script>
<?php endif; ?>
<?php if($error): ?>
<script>
Swal.fire({
icon:'error',
title:'Error',
text:'<?=addslashes($error)?>',
confirmButtonColor:'#e91e63'
});
</script>
<?php endif; ?>

<div class="menu-page">

<!-- LEFT FORM -->

<div class="menu-left">

<div class="menu-card">

<div class="menu-title">Add New Menu</div>

<form method="POST" id="menuForm" novalidate>

<div class="menu-form-group">
<label>Menu Name</label>
<input type="text" name="menu_name" placeholder="e.g. Role Management" required>
</div>

<div class="menu-form-group">
<label>Menu Slug</label>
<input type="text" name="menu_slug" placeholder="e.g. role_management" required>
</div>

<div class="menu-form-group">
<label>Parent Menu</label>
<select name="parent_id">
<option value="">Main Menu</option>
<?php foreach($parentMenus as $parent): ?>
<option value="<?=$parent['id']?>"><?=htmlspecialchars($parent['menu_name'])?></option>
<?php endforeach; ?>
</select>
<small>Keep as Main Menu if this is a top-level item.</small>
</div>

<div class="menu-form-group">
<label>Icon</label>
<input type="text" name="icon" placeholder="e.g. fas fa-users">
</div>

<div class="menu-form-group">
<label>Sort Order</label>
<input type="number" name="sort_order" value="1" placeholder="e.g. 1">
</div>

<div class="menu-form-group">
<label>Status</label>
<input type="hidden" name="status" id="menuStatusInput" value="1">
<div class="menu-segment" role="tablist" aria-label="Menu status">
  <button type="button" class="menu-segment-btn active" data-status="1" id="statusActiveBtn">Active</button>
  <button type="button" class="menu-segment-btn" data-status="0" id="statusInactiveBtn">Inactive</button>
</div>
<small>Active menus are visible in navigation.</small>
</div>

<button type="submit" name="add_menu" class="menu-btn" id="addMenuBtn" disabled>Add Menu</button>

</form>

</div>

</div>

<!-- RIGHT TABLE -->

<div class="menu-right">

<div class="menu-card" id="menuTableArea">

<div class="menu-card-head">
  <div class="menu-title">
    Existing Menus
    <span class="menu-total-badge" style="padding:4px 10px;font-size:0.74rem;box-shadow:none;"><?= (int)$total ?></span>
  </div>
  <div id="menuTableControls" class="menu-table-controls"></div>
</div>

<div class="menu-table-wrapper">

<table id="menuTable" class="menu-table">

<thead>
<tr>
<th width="60">#</th>
<th>Menu</th>
<th>Slug</th>
<th>Parent</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php
foreach($menus as $menu):

$isProtected=in_array($menu['menu_slug'],$protectedSlugs,true);
?>

<tr>

<td data-order="<?= (int)$menu['id'] ?>"><strong class="menu-row-index"></strong></td>

<td>
<i class="<?=htmlspecialchars($menu['icon']?:'fas fa-circle')?>"></i>
<?=htmlspecialchars($menu['menu_name'])?>
</td>

<td><?=htmlspecialchars($menu['menu_slug'])?></td>

<td><?=htmlspecialchars($menu['parent_name']??'Main')?></td>

<td align="center">

<?php if((int)$menu['status']===1): ?>
<i class="fas fa-check-circle" style="color:green;"></i>
<?php else: ?>
<i class="fas fa-times-circle" style="color:red;"></i>
<?php endif; ?>

</td>

<td>

<?php if(!$isProtected): ?>

<div class="menu-actions">

<a href="index.php?page=menu_edit&id=<?=$menu['id']?>" class="menu-edit tooltip" data-tooltip="Edit Menu">
<i class="fas fa-pen"></i>
</a>

<a href="index.php?page=menu_management&delete=<?=$menu['id']?>" class="menu-delete tooltip" data-tooltip="Delete Menu">
<i class="fas fa-trash"></i>
</a>

</div>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<div id="menuTableFooter" class="menu-table-footer"></div>

</div>

</div>

</div>

<script>

document.addEventListener("DOMContentLoaded", function () {
if (typeof crmDataTable === "function") {
crmDataTable('#menuTable',{
pageLength:10,
lengthMenu:[5,10,20,50,100],
ordering:true,
order:[[0,'asc']],
scrollX:false,
scrollY:false,
scrollCollapse:false,
searchPlaceholder:"Search menus...",
dom:"<'dt-top'lfB>rt<'dt-bottom'ip>"
});

function syncMenuRowIndex() {
if (!window.jQuery || !window.jQuery.fn.DataTable) return;
const $table = window.jQuery('#menuTable');
if (!$table.length || !window.jQuery.fn.DataTable.isDataTable($table)) return;
const dt = $table.DataTable();
const start = dt.page.info().start;
dt.column(0, { search: 'applied', order: 'applied', page: 'current' })
  .nodes()
  .each(function (cell, i) {
    const el = cell.querySelector('.menu-row-index');
    if (el) el.textContent = String(start + i + 1);
  });
}

setTimeout(function () {
const wrapper=document.querySelector('#menuTable_wrapper');
const controlsTarget=document.getElementById('menuTableControls');
const footerTarget=document.getElementById('menuTableFooter');
if(!wrapper) return;
const top=wrapper.querySelector('.dt-top');
const bottom=wrapper.querySelector('.dt-bottom');
if(top && controlsTarget){ controlsTarget.appendChild(top); }
if(bottom && footerTarget){ footerTarget.appendChild(bottom); }
},120);

if (window.jQuery) {
window.jQuery('#menuTable').on('draw.dt order.dt search.dt', syncMenuRowIndex);
}
setTimeout(syncMenuRowIndex, 80);
}

const statusInput=document.getElementById('menuStatusInput');
const statusButtons=document.querySelectorAll('.menu-segment-btn');
if(statusInput && statusButtons.length){
statusButtons.forEach(function(btn){
btn.addEventListener('click',function(){
const selected=this.getAttribute('data-status') || '1';
statusInput.value=selected;
statusButtons.forEach(function(x){ x.classList.remove('active'); });
this.classList.add('active');
});
});
}

const addMenuBtn=document.getElementById('addMenuBtn');
const reqInputs=[
document.querySelector('[name="menu_name"]'),
document.querySelector('[name="menu_slug"]')
];
const syncAddMenuState=function(){
if(!addMenuBtn) return;
const ok=reqInputs.every(function(inp){ return inp && inp.value.trim()!==''; });
addMenuBtn.disabled=!ok;
};
reqInputs.forEach(function(inp){
if(inp){ inp.addEventListener('input',syncAddMenuState); }
});
syncAddMenuState();
});

document.getElementById("menuForm").addEventListener("submit",function(e){

let menuName=document.querySelector('[name="menu_name"]').value.trim();
let menuSlug=document.querySelector('[name="menu_slug"]').value.trim();

if(menuName=="" || menuSlug==""){

e.preventDefault();

Swal.fire({
icon:"warning",
title:"Missing Information",
text:"Menu Name and Menu Slug are required.",
confirmButtonColor:"#e91e63"
});

return false;

}

});

document.addEventListener("click",function(e){

let deleteLink=e.target.closest(".menu-delete");

if(deleteLink){
e.preventDefault();

Swal.fire({
icon:"warning",
title:"Delete Menu?",
text:"This action cannot be undone.",
showCancelButton:true,
confirmButtonText:"Yes, Delete",
cancelButtonText:"Cancel",
confirmButtonColor:"#e91e63",
cancelButtonColor:"#6c757d"
}).then((result)=>{
if(result.isConfirmed){
window.location.href=deleteLink.href;
}
});

return;
}

});

/* MOBILE TOOLTIP SUPPORT */

document.addEventListener("click",function(e){

if(e.target.closest(".menu-delete")){
return;
}

let tooltip=e.target.closest(".tooltip");

document.querySelectorAll(".tooltip").forEach(el=>{
if(el!==tooltip){
el.classList.remove("show");
}
});

if(tooltip){
tooltip.classList.toggle("show");
}

});
</script>


