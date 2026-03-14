<?php
// ===============================
// Menu Edit - Modern UI Version
// ===============================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$protectedSlugs = ['menu_management'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid menu id.");
}

$stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
$stmt->execute([$id]);
$menu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$menu) {
    die("Menu not found.");
}

$isProtected = in_array($menu['menu_slug'], $protectedSlugs, true);

$parentMenus = $pdo->prepare("
SELECT id,menu_name
FROM menus
WHERE parent_id IS NULL
AND id != ?
ORDER BY sort_order ASC
");

$parentMenus->execute([$id]);
$parentMenus = $parentMenus->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
UPDATE MENU
=============================== */

if (isset($_POST['update_menu'])) {

$menu_name=trim($_POST['menu_name']??'');
$menu_slug=trim($_POST['menu_slug']??'');
$parent_id=($_POST['parent_id']??'')!==''?(int)$_POST['parent_id']:null;
$icon=trim($_POST['icon']??'');
$sort_order=(int)($_POST['sort_order']??0);
$status=(int)($_POST['status']??1);

if($isProtected){
$menu_slug=$menu['menu_slug'];
$status=1;
$parent_id=null;
}

if($menu_name=='' || $menu_slug==''){
setFlash('error', 'Menu Name and Menu Slug are required.');
redirect('index.php?page=menu_edit&id=' . $id);
}else{

$chk=$pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_slug=? AND id!=?");
$chk->execute([$menu_slug,$id]);

if($chk->fetchColumn()>0){
setFlash('error', 'This Menu Slug already exists.');
redirect('index.php?page=menu_edit&id=' . $id);
}else{

$upd=$pdo->prepare("
UPDATE menus
SET menu_name=?,
menu_slug=?,
parent_id=?,
icon=?,
sort_order=?,
status=?,
updated_at=NOW()
WHERE id=?
");

$upd->execute([
$menu_name,
$menu_slug,
$parent_id,
$icon,
$sort_order,
$status,
$id
]);

setFlash('success', 'Menu updated successfully.');
redirect('index.php?page=menu_edit&id=' . $id);

$stmt=$pdo->prepare("SELECT * FROM menus WHERE id=?");
$stmt->execute([$id]);
$menu=$stmt->fetch(PDO::FETCH_ASSOC);

$isProtected=in_array($menu['menu_slug'],$protectedSlugs,true);

}
}

}
?>

<style>

/* ===== PAGE ===== */

.menu-edit-page{
max-width:1100px;
margin:auto;
}

/* ===== CARD ===== */

.menu-card{
background:#fff;
border-radius:12px;
border:1px solid #eee;
padding:25px;
box-shadow:0 6px 18px rgba(0,0,0,.05);
}

/* ===== TITLE ===== */

.menu-title{
font-size:20px;
font-weight:600;
margin-bottom:18px;
}

/* ===== GRID ===== */

.menu-grid{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:18px;
}

/* ===== FORM ===== */

.menu-field label{
font-size:13px;
font-weight:600;
margin-bottom:4px;
display:block;
}

.menu-field input,
.menu-field select{
width:100%;
padding:10px;
border:1px solid #ddd;
border-radius:6px;
font-size:13px;
}

.menu-field small{
font-size:11px;
color:#777;
}

/* ===== BUTTONS ===== */

.menu-actions{
margin-top:20px;
display:flex;
gap:10px;
flex-wrap:wrap;
}

.menu-btn{
padding:10px 16px;
border-radius:6px;
border:none;
cursor:pointer;
font-size:13px;
}

.menu-save{
background:#e91e63;
color:#fff;
}

.menu-back{
background:#444;
color:#fff;
text-decoration:none;
display:inline-flex;
align-items:center;
padding:10px 16px;
border-radius:6px;
}

.alert-warning{
background:#fff3cd;
border:1px solid #ffe69c;
padding:10px;
border-radius:6px;
margin-bottom:15px;
}

/* ===== MOBILE ===== */

@media(max-width:900px){

.menu-grid{
grid-template-columns:1fr;
}

.menu-actions{
flex-direction:column;
}

.menu-back{
justify-content:center;
}

}

</style>


<div class="menu-edit-page">

<div class="menu-card">

<div class="menu-title">Edit Menu</div>

<?php if($isProtected): ?>
<div class="alert-warning">
<i class="fas fa-lock"></i>
This is a <strong>System Menu</strong>. Slug / Status / Parent cannot be modified.
</div>
<?php endif; ?>


<form method="POST">

<div class="menu-grid">

<div class="menu-field">
<label>Menu Name</label>
<input type="text" name="menu_name"
value="<?=htmlspecialchars($menu['menu_name'])?>" required>
</div>

<div class="menu-field">
<label>Menu Slug</label>
<input type="text" name="menu_slug"
value="<?=htmlspecialchars($menu['menu_slug'])?>"
<?= $isProtected ? 'readonly' : '' ?> required>
<small>Example: users/user_management</small>
</div>

<div class="menu-field">
<label>Parent Menu</label>
<select name="parent_id" <?= $isProtected ? 'disabled' : '' ?>>
<option value="">Main Menu</option>

<?php foreach($parentMenus as $p): ?>

<option value="<?=$p['id']?>"
<?= ((int)$menu['parent_id']==(int)$p['id'])?'selected':'' ?>>

<?=htmlspecialchars($p['menu_name'])?>

</option>

<?php endforeach; ?>

</select>

<?php if($isProtected): ?>
<input type="hidden" name="parent_id" value="">
<?php endif; ?>

</div>

<div class="menu-field">
<label>Icon Class</label>
<input type="text" name="icon"
value="<?=htmlspecialchars($menu['icon'] ?? '')?>"
placeholder="fas fa-users">
<small>
Preview:
<i class="<?=htmlspecialchars($menu['icon'] ?: 'fas fa-circle')?>"></i>
</small>
</div>

<div class="menu-field">
<label>Sort Order</label>
<input type="number" name="sort_order"
value="<?= (int)$menu['sort_order'] ?>" min="0">
</div>

<div class="menu-field">
<label>Status</label>
<select name="status" <?= $isProtected ? 'disabled' : '' ?>>
<option value="1" <?=((int)$menu['status']===1)?'selected':''?>>Active</option>
<option value="0" <?=((int)$menu['status']===0)?'selected':''?>>Inactive</option>
</select>

<?php if($isProtected): ?>
<input type="hidden" name="status" value="1">
<?php endif; ?>

</div>

</div>

<div class="menu-actions">

<button type="submit" name="update_menu" class="menu-btn menu-save">
<i class="fas fa-save"></i> Save Changes
</button>

<a href="index.php?page=menu_management" class="menu-back">
<i class="fas fa-arrow-left"></i>&nbsp; Back
</a>

</div>

</form>

</div>

</div>
