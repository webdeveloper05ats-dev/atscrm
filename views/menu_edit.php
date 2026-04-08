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
:root{
--me-primary:#e91e63;
--me-primary-dark:#c2185b;
--me-border:#f1d6e3;
--me-soft:#fff4f9;
--me-text:#374151;
--me-muted:#6b7280;
--me-shadow:0 8px 18px rgba(0,0,0,.06);
}

.menu-edit-shell{
max-width:1100px;
margin:auto;
}

.menu-edit-head{
display:flex;
align-items:center;
justify-content:space-between;
gap:12px;
margin-bottom:12px;
flex-wrap:wrap;
}

.menu-edit-head h2{
margin:0;
color:#be185d;
font-size:1.2rem;
font-weight:800;
}

.menu-head-back{
text-decoration:none;
display:inline-flex;
align-items:center;
gap:7px;
padding:9px 14px;
border-radius:10px;
border:1px solid var(--me-border);
background:#fff;
color:#be185d;
font-size:.85rem;
font-weight:700;
}

.menu-card{
background:#fff;
border-radius:14px;
border:1px solid var(--me-border);
box-shadow:var(--me-shadow);
overflow:hidden;
}

.menu-card-head{
padding:12px 14px;
display:flex;
align-items:center;
justify-content:space-between;
gap:10px;
background:var(--me-soft);
border-bottom:1px solid var(--me-border);
flex-wrap:wrap;
}

.menu-card-title{
margin:0;
font-size:1rem;
font-weight:800;
color:#be185d;
}

.menu-protect-badge{
display:inline-flex;
align-items:center;
gap:6px;
padding:5px 10px;
border-radius:999px;
border:1px solid #f5d6a4;
background:#fff7e6;
color:#92400e;
font-size:.74rem;
font-weight:700;
}

.menu-form-wrap{
padding:14px;
}

.alert-warning{
background:#fff7e6;
border:1px solid #f7d9ac;
padding:10px 12px;
border-radius:10px;
margin-bottom:14px;
font-size:.86rem;
color:#7c2d12;
}

.menu-grid{
display:grid;
grid-template-columns:repeat(2,minmax(0,1fr));
gap:14px;
}

.menu-field{
min-width:0;
}

.menu-field label{
font-size:.74rem;
font-weight:700;
color:var(--me-muted);
text-transform:uppercase;
letter-spacing:.3px;
display:block;
margin-bottom:5px;
}

.menu-field input,
.menu-field select{
width:100%;
min-height:40px;
padding:9px 10px;
border:1px solid var(--me-border);
border-radius:9px;
font-size:.88rem;
outline:none;
background:#fff;
transition:border-color .2s ease, box-shadow .2s ease;
}

.menu-field input:focus,
.menu-field select:focus{
border-color:var(--me-primary);
box-shadow:0 0 0 3px rgba(233,30,99,.12);
}

.menu-field small{
display:block;
margin-top:5px;
font-size:.72rem;
color:#8b7280;
}

.icon-preview{
display:inline-flex;
align-items:center;
gap:7px;
padding:5px 9px;
border:1px solid #f1d6e3;
background:#fff9fc;
border-radius:8px;
margin-top:5px;
font-size:.78rem;
color:#7c2d5a;
}

.menu-segment{
display:inline-flex;
width:100%;
border:1px solid var(--me-border);
border-radius:999px;
overflow:hidden;
background:#fff;
box-shadow:inset 0 1px 2px rgba(233, 30, 99, 0.08);
}

.menu-segment-btn{
flex:1;
border:0;
background:transparent;
color:#6b7280;
min-height:40px;
font-size:.85rem;
font-weight:700;
cursor:pointer;
transition:all .2s ease;
}

.menu-segment-btn + .menu-segment-btn{
border-left:1px solid #f3d8e5;
}

.menu-segment-btn.active{
background:linear-gradient(135deg,var(--me-primary) 0%,#ff4f9c 100%);
color:#fff;
}

.menu-status-lock{
display:inline-flex;
align-items:center;
gap:7px;
padding:10px 12px;
border-radius:9px;
border:1px solid #f5d6a4;
background:#fff7e6;
color:#92400e;
font-size:.84rem;
font-weight:700;
}

.menu-actions{
margin-top:16px;
display:flex;
gap:10px;
flex-wrap:wrap;
justify-content:flex-end;
}

.menu-btn{
padding:10px 16px;
border-radius:10px;
border:none;
cursor:pointer;
font-size:.86rem;
font-weight:700;
min-height:40px;
display:inline-flex;
align-items:center;
gap:7px;
}

.menu-save{
background:linear-gradient(135deg,#ff4d8d,#e91e63);
color:#fff;
box-shadow:0 6px 14px rgba(233,30,99,.24);
}

.menu-save:hover{
transform:translateY(-1px);
background:linear-gradient(135deg,#ff3b82,#d81b60);
}

.menu-save:disabled{
opacity:.58;
cursor:not-allowed;
transform:none;
box-shadow:none;
}

.menu-back{
background:#f3f4f6;
color:#374151;
text-decoration:none;
display:inline-flex;
align-items:center;
padding:10px 16px;
border-radius:10px;
font-size:.86rem;
font-weight:700;
}

@media(max-width:900px){
.menu-grid{ grid-template-columns:1fr; }
.menu-actions{ justify-content:stretch; }
.menu-actions .menu-btn,
.menu-actions .menu-back{ width:100%; justify-content:center; }
}


/* =====================================================
GLOBAL TYPOGRAPHY STYLECSS SYNC
font-family + font-size + font-weight only
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){font-size:clamp(2rem, 2.5vw, 2.4rem) !important;font-weight:700 !important;}
:where(h2,.h2,.section-title){font-size:clamp(1.6rem, 2vw, 2rem) !important;font-weight:600 !important;}
:where(h3,.h3,.card-header,.table-title){font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;font-weight:600 !important;}
:where(h4,.h4){font-size:1.2rem !important;font-weight:500 !important;}
:where(h5,.h5){font-size:1rem !important;font-weight:500 !important;}
:where(h6,.h6){font-size:0.9rem !important;font-weight:500 !important;}
:where(body){font-size:1rem !important;}
:where(p,.text-body,li,td,.text-muted,.help-text,.form-text,.small,small,.secondary-text){font-size:0.95rem !important;font-weight:400 !important;}
:where(.small,small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){font-size:0.85rem !important;font-weight:400 !important;}
:where(label,.form-label){font-size:0.85rem !important;font-weight:500 !important;}
:where(input,select,textarea,.form-control,.form-select){font-size:0.95rem !important;font-weight:400 !important;}
:where(input::placeholder,textarea::placeholder){font-weight:400 !important;}
:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){font-size:0.9rem !important;font-weight:600 !important;}
:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{font-size:0.75rem !important;font-weight:600 !important;}
:where(.table th,.crm-table th,.dataTables_wrapper th,th){font-size:0.75rem !important;font-weight:600 !important;}
:where(.table td,.dataTables_wrapper tbody td){font-size:0.9rem !important;}
:where(.dataTables_wrapper .dataTables_info){font-size:0.85rem !important;font-weight:400 !important;}
:where(.dataTables_wrapper .paginate_button){font-size:0.9rem !important;font-weight:600 !important;}
:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){font-weight:600 !important;}

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>


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

