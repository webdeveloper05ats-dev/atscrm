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

<div class="menu-edit-shell">

<div class="menu-edit-head">
<h2>Menu Management</h2>
<a href="index.php?page=menu_management" class="menu-head-back">
<i class="fas fa-arrow-left"></i> Back to Menus
</a>
</div>

<div class="menu-card">

<div class="menu-card-head">
<div class="menu-card-title">Edit Menu</div>
<?php if($isProtected): ?>
<span class="menu-protect-badge"><i class="fas fa-lock"></i> System Protected</span>
<?php endif; ?>
</div>

<div class="menu-form-wrap">

<?php if($isProtected): ?>
<div class="alert-warning">
<i class="fas fa-lock"></i>
This is a <strong>System Menu</strong>. Slug / Status / Parent cannot be modified.
</div>
<?php endif; ?>

<form method="POST" id="menuEditForm" novalidate>

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
<div class="icon-preview">
<span>Preview</span>
<i id="iconPreview" class="<?=htmlspecialchars($menu['icon'] ?: 'fas fa-circle')?>"></i>
</div>
</div>

<div class="menu-field">
<label>Sort Order</label>
<input type="number" name="sort_order"
value="<?= (int)$menu['sort_order'] ?>" min="0">
</div>

<div class="menu-field">
<label>Status</label>
<?php if($isProtected): ?>
<div class="menu-status-lock"><i class="fas fa-lock"></i> Always Active for system menu</div>
<input type="hidden" name="status" value="1">
<?php else: ?>
<input type="hidden" name="status" id="menuStatusInput" value="<?= (int)$menu['status'] ?>">
<div class="menu-segment" role="tablist" aria-label="Menu status">
<button type="button" class="menu-segment-btn<?= ((int)$menu['status']===1)?' active':'' ?>" data-status="1">Active</button>
<button type="button" class="menu-segment-btn<?= ((int)$menu['status']===0)?' active':'' ?>" data-status="0">Inactive</button>
</div>
<?php endif; ?>

</div>

</div>

<div class="menu-actions">

<button type="submit" name="update_menu" class="menu-btn menu-save" id="saveMenuBtn">
<i class="fas fa-save"></i> Save Changes
</button>

<a href="index.php?page=menu_management" class="menu-back">
<i class="fas fa-arrow-left"></i>&nbsp; Back
</a>

</div>

</form>

</div>
</div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
const statusInput=document.getElementById("menuStatusInput");
const statusButtons=document.querySelectorAll(".menu-segment-btn");
if(statusInput && statusButtons.length){
statusButtons.forEach(function(btn){
btn.addEventListener("click",function(){
statusInput.value=this.getAttribute("data-status") || "1";
statusButtons.forEach(function(x){ x.classList.remove("active"); });
this.classList.add("active");
});
});
}

const saveBtn=document.getElementById("saveMenuBtn");
const reqInputs=[
document.querySelector('[name="menu_name"]'),
document.querySelector('[name="menu_slug"]')
];
const syncSaveState=function(){
if(!saveBtn) return;
const ok=reqInputs.every(function(inp){ return inp && inp.value.trim()!==""; });
saveBtn.disabled=!ok;
};
reqInputs.forEach(function(inp){
if(inp){ inp.addEventListener("input",syncSaveState); }
});
syncSaveState();

const iconInput=document.querySelector('[name="icon"]');
const iconPreview=document.getElementById("iconPreview");
if(iconInput && iconPreview){
iconInput.addEventListener("input",function(){
const cls=this.value.trim() || "fas fa-circle";
iconPreview.className=cls;
});
}
});
</script>


