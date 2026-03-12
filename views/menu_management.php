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
}
}

/* ===============================
DELETE MENU
=============================== */

if(isset($_GET['delete'])){

$deleteId=(int)$_GET['delete'];

$stmt=$pdo->prepare("SELECT menu_slug FROM menus WHERE id=?");
$stmt->execute([$deleteId]);
$row=$stmt->fetch(PDO::FETCH_ASSOC);

if($row){

$slug=$row['menu_slug'];

if(!in_array($slug,$protectedSlugs,true)){

$pdo->prepare("DELETE FROM role_permissions WHERE menu_id=?")->execute([$deleteId]);
$pdo->prepare("DELETE FROM menus WHERE id=?")->execute([$deleteId]);

$success="Menu Deleted Successfully!";
}
}
}

/* ===============================
PAGINATION
=============================== */

$limit=10;
$page=max(1,(int)($_GET['p']??1));
$offset=($page-1)*$limit;

$total=$pdo->query("SELECT COUNT(*) FROM menus")->fetchColumn();
$totalPages=ceil($total/$limit);

/* ===============================
FETCH MENUS
=============================== */

$stmt=$pdo->prepare("
SELECT m.*,p.menu_name AS parent_name
FROM menus m
LEFT JOIN menus p ON m.parent_id=p.id
ORDER BY m.parent_id IS NOT NULL,m.sort_order ASC
LIMIT $limit OFFSET $offset
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

<style>

/* ===== LAYOUT ===== */

.menu-page{
display:flex;
gap:25px;
align-items:flex-start;
flex-wrap:wrap;
}

.menu-left{width:330px;}

.menu-right{flex:1;}

.menu-card{
background:#fff;
border:1px solid #eee;
border-radius:10px;
padding:18px;
box-shadow:0 4px 12px rgba(0,0,0,.05);
}

.menu-title{
font-weight:600;
margin-bottom:15px;
font-size:15px;
}

/* ===== FORM ===== */

.menu-form-group{margin-bottom:12px;}

.menu-form-group label{
font-size:13px;
display:block;
margin-bottom:4px;
}

.menu-form-group input,
.menu-form-group select{
width:100%;
padding:8px;
border-radius:6px;
border:1px solid #ccc;
font-size:13px;
}

.menu-btn{
background:#e91e63;
color:#fff;
border:none;
padding:10px;
border-radius:6px;
width:100%;
cursor:pointer;
}

/* ===== TABLE ===== */

.menu-table-wrapper{
overflow-x:auto;
}

.menu-table{
width:100%;
border-collapse:collapse;
min-width:700px;
}

.menu-table th,
.menu-table td{
padding:10px;
font-size:13px;
border:1px solid #e6e6e6;
}

.menu-table th{
background:#fafafa;
font-weight:600;
}

/* ===== ACTION ===== */

.menu-actions{
display:flex;
gap:8px;
}

.menu-edit{
background:#e91e63;
color:#fff;
padding:6px 10px;
border-radius:6px;
text-decoration:none;
}

.menu-delete{
background:#dc3545;
color:#fff;
padding:6px 10px;
border-radius:6px;
text-decoration:none;
}

/* ===== PAGINATION ===== */

.menu-pagination{
display:flex;
justify-content:center;
flex-wrap:wrap;
margin-top:15px;
gap:6px;
}

.menu-page-btn{
padding:6px 10px;
border:1px solid #ddd;
border-radius:6px;
font-size:12px;
text-decoration:none;
color:#333;
}

.menu-page-btn.active{
background:#e91e63;
color:#fff;
border-color:#e91e63;
}

/* ===== MOBILE ===== */

@media(max-width:900px){

.menu-page{
flex-direction:column;
}

.menu-left,
.menu-right{
width:100%;
}

.menu-table{
min-width:600px;
}

}


/* Loading state */

#menuTableArea.loading{
position:relative;
opacity:0.5;
pointer-events:none;
}

#menuTableArea.loading::after{
content:"";
width:28px;
height:28px;
border:3px solid #ddd;
border-top:3px solid #e91e63;
border-radius:50%;
position:absolute;
top:50%;
left:50%;
transform:translate(-50%,-50%);
animation:spin 0.7s linear infinite;
}

@keyframes spin{
to{transform:translate(-50%,-50%) rotate(360deg);}
}


/* =========================
MODERN TOOLTIP
========================= */

.tooltip{
position:relative;
cursor:pointer;
}

.tooltip::after{
content:attr(data-tooltip);
position:absolute;
bottom:120%;
left:50%;
transform:translateX(-50%);
background:#222;
color:#fff;
padding:6px 10px;
border-radius:6px;
font-size:12px;
white-space:nowrap;
opacity:0;
pointer-events:none;
transition:opacity .18s ease;
box-shadow:0 4px 10px rgba(0,0,0,.2);
}

.tooltip::before{
content:"";
position:absolute;
bottom:105%;
left:50%;
transform:translateX(-50%);
border:6px solid transparent;
border-top-color:#222;
opacity:0;
transition:opacity .18s ease;
}

/* Desktop hover */

.tooltip:hover::after,
.tooltip:hover::before{
opacity:1;
}

/* Mobile active */

.tooltip.show::after,
.tooltip.show::before{
opacity:1;
}
</style>

<h2 style="margin-bottom:16px;">Menu Management</h2>

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
<input type="text" name="menu_name" required>
</div>

<div class="menu-form-group">
<label>Menu Slug</label>
<input type="text" name="menu_slug" required>
</div>

<div class="menu-form-group">
<label>Parent Menu</label>
<select name="parent_id">
<option value="">Main Menu</option>
<?php foreach($parentMenus as $parent): ?>
<option value="<?=$parent['id']?>"><?=htmlspecialchars($parent['menu_name'])?></option>
<?php endforeach; ?>
</select>
</div>

<div class="menu-form-group">
<label>Icon</label>
<input type="text" name="icon" placeholder="fas fa-users">
</div>

<div class="menu-form-group">
<label>Sort Order</label>
<input type="number" name="sort_order" value="1">
</div>

<div class="menu-form-group">
<label>Status</label>
<select name="status">
<option value="1">Active</option>
<option value="0">Inactive</option>
</select>
</div>

<button type="submit" name="add_menu" class="menu-btn">Add Menu</button>

</form>

</div>

</div>


<!-- RIGHT TABLE -->

<div class="menu-right">

<div class="menu-card" id="menuTableArea">

<div class="menu-title">Existing Menus</div>

<div class="menu-table-wrapper">

<table class="menu-table">

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
$serial=$offset+1;
foreach($menus as $menu):

$isProtected=in_array($menu['menu_slug'],$protectedSlugs,true);
?>

<tr>

<td><?=$serial++?></td>

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

<!-- PAGINATION -->

<div class="menu-pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<a href="?page=menu_management&p=<?=$i?>"
class="menu-page-btn pagination-link <?=($page==$i?'active':'')?>"><?=$i?></a>

<?php endfor; ?>

</div>

</div>

</div>

</div>

<script>

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

let link=e.target.closest(".pagination-link");

if(link){

e.preventDefault();

let url=link.href;

/* show loading */

document.querySelector("#menuTableArea").classList.add("loading");

/* update active button */

document.querySelectorAll(".menu-page-btn").forEach(btn=>{
btn.classList.remove("active");
});

link.classList.add("active");

fetch(url)
.then(res=>res.text())
.then(html=>{

let parser=new DOMParser();
let doc=parser.parseFromString(html,"text/html");

let newContent=doc.querySelector("#menuTableArea");

document.querySelector("#menuTableArea").innerHTML=newContent.innerHTML;

document.querySelector("#menuTableArea").classList.remove("loading");

});

}

});


/* MOBILE TOOLTIP SUPPORT */

document.addEventListener("click",function(e){

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