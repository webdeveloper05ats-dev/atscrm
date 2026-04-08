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

<style>
:root {
  --primary: #e91e63;
  --primary-light: #fce4ec;
  --primary-dark: #c2185b;
  --danger: #dc3545;
  --dark: #343a40;
  --light: #f8f9fa;
  --border: #ead1df;
  --text: #495057;
  --text-light: #6c757d;
  --white: #ffffff;
  --shadow: 0 6px 16px rgba(0,0,0,.06);
  --radius: 12px;
  --radius-sm: 8px;
}

.menu-page {
  display: grid;
  grid-template-columns: minmax(300px, 340px) minmax(520px, 1fr);
  gap: 16px;
  align-items: start;
  background: #fff7fb;
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 14px;
}

.menu-page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 12px;
}

.menu-total-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 12px;
  border-radius: 999px;
  border: 1px solid #f3d2e1;
  background: #fff;
  color: #be185d;
  font-size: 0.82rem;
  font-weight: 800;
  box-shadow: var(--shadow);
}

.menu-left,
.menu-right { min-width: 0; }

.menu-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
}

.menu-right .menu-card {
  overflow: visible;
}

.menu-card-head {
  padding: 14px 16px;
  background: linear-gradient(180deg, #fff8fc 0%, #fff2f8 100%);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.menu-title {
  font-weight: 800;
  margin: 0;
  font-size: 1rem;
  color: var(--dark);
  padding: 12px 14px;
  background: var(--light);
  border-bottom: 1px solid var(--border);
}

.menu-card-head .menu-title {
  padding: 0;
  background: transparent;
  border-bottom: none;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #3f2a36;
  font-weight: 800;
  letter-spacing: .2px;
}

.menu-table-controls {
  flex: 1;
  min-width: 0;
  display: flex;
  justify-content: flex-end;
  padding-left: 8px;
}

.menu-table-footer {
  margin: 12px 16px 14px;
  padding-top: 10px;
  border-top: 1px solid #f3dbe7;
}

.menu-table-controls .dt-top {
  width: 100%;
  margin: 0 !important;
  justify-content: flex-end !important;
  gap: 12px;
  margin-bottom: 2px !important;
}

.menu-table-footer .dt-bottom {
  margin: 0 !important;
}

.menu-card > form,
.menu-card > .menu-table-wrapper,
.menu-card > .menu-pagination {
  margin-left: 16px;
  margin-right: 16px;
}

.menu-card > form {
  margin-top: 14px;
  margin-bottom: 14px;
}

.menu-card > .menu-table-wrapper {
  margin-top: 14px;
  margin-bottom: 0;
}

#menuTableArea > .menu-table-wrapper {
  margin-top: 0;
}

.menu-card > .menu-pagination {
  margin-top: 12px;
  margin-bottom: 14px;
}

.menu-form-group { margin-bottom: 12px; }

.menu-form-group label {
  font-size: 0.74rem;
  font-weight: 700;
  color: var(--text-light);
  text-transform: uppercase;
  letter-spacing: 0.3px;
  display: block;
  margin-bottom: 5px;
}

.menu-form-group small {
  display: block;
  margin-top: 5px;
  font-size: 0.72rem;
  color: #9b8a94;
  line-height: 1.35;
}

.menu-segment {
  display: inline-flex;
  width: 100%;
  border: 1px solid var(--border);
  border-radius: 999px;
  overflow: hidden;
  background: #fff;
  box-shadow: inset 0 1px 2px rgba(233, 30, 99, 0.08);
}

.menu-segment-btn {
  flex: 1;
  border: 0;
  background: transparent;
  color: #6b7280;
  min-height: 42px;
  font-size: 0.86rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
}

.menu-segment-btn + .menu-segment-btn {
  border-left: 1px solid #f3d8e5;
}

.menu-segment-btn.active {
  background: linear-gradient(135deg, var(--primary) 0%, #ff4f9c 100%);
  color: #fff;
}

.menu-form-group input,
.menu-form-group select {
  width: 100%;
  min-height: 38px;
  padding: 8px 10px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  font-size: 0.88rem;
  outline: none;
  background: #fff;
  transition: border-color .2s ease, box-shadow .2s ease;
}

.menu-form-group input::placeholder {
  color: #a3929d;
}

.menu-form-group input:focus,
.menu-form-group select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(233,30,99,.12);
}

.menu-form-group input[name="menu_name"]:not(:placeholder-shown):valid,
.menu-form-group input[name="menu_slug"]:not(:placeholder-shown):valid {
  border-color: #9ee2b8;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
}

.menu-btn {
  background: linear-gradient(135deg, var(--primary) 0%, #ff4f9c 100%);
  color: #fff;
  border: none;
  min-height: 42px;
  border-radius: 10px;
  width: 100%;
  cursor: pointer;
  font-size: 0.92rem;
  font-weight: 800;
  transition: transform .2s ease, opacity .2s ease, box-shadow .2s ease;
}

.menu-btn:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
}

.menu-btn:disabled {
  opacity: .58;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.menu-table-wrapper {
  overflow-x: auto;
  overflow-y: visible;
  border: 1px solid #f0d2e1;
  border-radius: 12px;
  background: #fff;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.55);
  margin-top: 10px;
  position: relative;
  z-index: 1;
}

.menu-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 100%;
  table-layout: fixed;
}

.menu-table th,
.menu-table td {
  padding: 11px;
  font-size: 0.9rem;
  border: none;
  border-bottom: 1px solid #f3e4eb;
  vertical-align: middle;
  white-space: normal;
  word-break: break-word;
}

.menu-table th:nth-child(1),
.menu-table td:nth-child(1) { width: 56px; }
.menu-table th:nth-child(6),
.menu-table td:nth-child(6) { width: 120px; }

.menu-table th {
  background: #fff1f8;
  font-size: 0.72rem;
  color: #7b4361;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.45px;
  border-bottom: 2px solid var(--border);
}

.menu-table tbody tr:nth-child(even) { background: #fffafd; }
.menu-table tbody tr:hover { background: #fff2f8; }

.menu-actions {
  display: inline-flex;
  gap: 8px;
  align-items: center;
}

.menu-edit,
.menu-delete {
  width: 32px;
  height: 32px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  color: #fff;
  text-decoration: none;
  transition: transform .14s ease, box-shadow .14s ease, filter .14s ease;
}

.menu-edit:hover,
.menu-delete:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 14px rgba(17,24,39,.12);
}

.menu-edit { background: var(--primary); }
.menu-edit:hover { background: var(--primary-dark); }
.menu-delete { background: var(--danger); }
.menu-delete:hover { background: #b91c1c; }

.menu-pagination {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 6px;
}

.menu-page-btn {
  min-width: 30px;
  height: 30px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 0.8rem;
  text-decoration: none;
  color: #be185d;
  background: #fff;
}

.menu-page-btn.active {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}

/* DataTable Controls */
#menuTableControls .dt-top,
#menuTableFooter .dt-bottom {
  display: flex !important;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin: 0;
  width: 100%;
}

#menuTableArea .dataTables_length,
#menuTableArea .dataTables_filter,
#menuTableArea .dt-buttons {
  margin: 0;
}

#menuTableArea .dataTables_length label,
#menuTableArea .dataTables_filter label {
  margin: 0;
  font-size: 0.78rem;
  color: #7b6472;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-weight: 600;
  white-space: nowrap;
}

#menuTableArea .dataTables_length select,
#menuTableArea .dataTables_filter input {
  border: 1px solid #eecfe0;
  border-radius: 9px;
  min-height: 34px;
  padding: 6px 10px;
  font-size: 0.82rem;
  color: #4d3a45;
  background: #fff;
}

#menuTableArea .dataTables_length select {
  width: auto;
  min-width: 56px;
}

#menuTableArea .dataTables_filter input {
  min-width: 200px;
  width: 220px;
  max-width: 100%;
}

#menuTableArea .dataTables_length select:focus,
#menuTableArea .dataTables_filter input:focus {
  outline: none;
  border-color: #e91e63;
  box-shadow: 0 0 0 3px rgba(233,30,99,.12);
}

.dt-buttons .buttons-csv {
  background: var(--primary) !important;
  color: #fff !important;
  border: none !important;
  border-radius: 8px !important;
  padding: 6px 12px !important;
  font-size: 0.78rem !important;
  font-weight: 700 !important;
}

#menuTableArea .dataTables_info {
  font-size: 0.79rem;
  color: #7b6472;
  font-weight: 500;
  margin: 0;
}

#menuTableArea .dataTables_paginate {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

#menuTableArea .dataTables_paginate .paginate_button {
  border: 1px solid #efcfdf !important;
  border-radius: 9px !important;
  padding: 6px 11px !important;
  font-size: 0.78rem;
  font-weight: 700;
  color: #be185d !important;
  background: #fff9fc !important;
}

#menuTableArea .dataTables_paginate .paginate_button.current {
  background: var(--primary) !important;
  border-color: var(--primary) !important;
  color: #fff !important;
  box-shadow: 0 6px 12px rgba(233,30,99,.26);
}

#menuTableArea .dataTables_paginate .paginate_button:hover {
  background: #fff0f6 !important;
  border-color: var(--primary) !important;
}

#menuTableArea .dataTables_paginate .paginate_button.disabled {
  opacity: .5;
  cursor: not-allowed !important;
}

#menuTable_wrapper .dataTables_scroll,
#menuTable_wrapper .dataTables_scrollHead,
#menuTable_wrapper .dataTables_scrollBody,
#menuTable_wrapper .dataTables_scrollFoot {
  width: 100% !important;
}

#menuTable_wrapper .dataTables_scroll {
  overflow: visible !important;
}

#menuTable_wrapper .dataTables_scrollBody {
  max-height: none !important;
  height: auto !important;
  overflow-y: visible !important;
  overflow-x: auto !important;
  border: none !important;
}

#menuTable_wrapper .dataTables_scrollHead,
#menuTable_wrapper .dataTables_scrollFoot {
  overflow: visible !important;
}

/* DataTables v2 wrappers */
#menuTable_wrapper .dt-scroll,
#menuTable_wrapper .dt-scroll-head,
#menuTable_wrapper .dt-scroll-body,
#menuTable_wrapper .dt-scroll-foot {
  width: 100% !important;
}

#menuTable_wrapper .dt-scroll {
  overflow: visible !important;
}

#menuTable_wrapper .dt-scroll-body {
  max-height: none !important;
  height: auto !important;
  overflow-y: visible !important;
  overflow-x: auto !important;
  border: none !important;
}

#menuTable_wrapper .dt-scroll-head,
#menuTable_wrapper .dt-scroll-foot {
  overflow: visible !important;
}

#menuTableArea.loading {
  position: relative;
  opacity: 0.55;
  pointer-events: none;
}

#menuTableArea.loading::after {
  content: "";
  width: 30px;
  height: 30px;
  border: 3px solid #f3d8e5;
  border-top: 3px solid var(--primary);
  border-radius: 50%;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to { transform: translate(-50%, -50%) rotate(360deg); }
}

.tooltip { position: relative; }

.tooltip::after,
.tooltip::before {
  opacity: 0;
  visibility: hidden;
  pointer-events: none;
  transition: 0.18s ease;
}

.tooltip::after {
  content: attr(data-tooltip);
  position: absolute;
  top: calc(100% + 8px);
  left: 50%;
  transform: translateX(-50%) translateY(4px);
  background: #1f2937;
  color: #fff;
  padding: 6px 10px;
  border-radius: 7px;
  font-size: 11px;
  white-space: nowrap;
  z-index: 9999;
}

.tooltip::before {
  content: "";
  position: absolute;
  top: calc(100% + 2px);
  left: 50%;
  transform: translateX(-50%) translateY(4px);
  border-left: 5px solid transparent;
  border-right: 5px solid transparent;
  border-bottom: 6px solid #1f2937;
  z-index: 9999;
}

.tooltip:hover::after,
.tooltip:hover::before,
.tooltip.show::after,
.tooltip.show::before {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}

.menu-edit.tooltip::after,
.menu-delete.tooltip::after {
  top: calc(100% + 8px);
  bottom: auto;
}

.menu-edit.tooltip::before,
.menu-delete.tooltip::before {
  top: calc(100% + 2px);
  bottom: auto;
  border-top: 0;
  border-bottom: 6px solid #1f2937;
}

.swal2-popup {
  border-radius: 14px !important;
  border: 1px solid var(--border) !important;
}

.swal2-title { color: #be185d !important; }
.swal2-styled.swal2-confirm { background: var(--primary) !important; }

@media (max-width: 992px) {
  .menu-page { grid-template-columns: 1fr; }
  .menu-page-head { align-items: flex-start; }
}

@media (max-width: 768px) {
  .menu-table { min-width: 680px; }
  .menu-card-head {
    flex-direction: column;
    align-items: stretch;
  }
  .menu-table-controls {
    width: 100%;
  }
  #menuTableControls .dt-top,
  #menuTableFooter .dt-bottom {
    flex-direction: column;
    align-items: stretch;
  }
  #menuTableArea .dataTables_filter input {
    width: 100% !important;
    min-width: 0;
  }
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

