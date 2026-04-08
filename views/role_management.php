<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/rolmanagement.css">
<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

requireView('role_management');

$success="";
$error="";

if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
    redirect('index.php');
    exit;
}

$protectedRoleName="Super Admin";

$st=$pdo->prepare("SELECT id FROM roles WHERE role_name=? LIMIT 1");
$st->execute([$protectedRoleName]);
$protectedRoleId=(int)($st->fetchColumn() ?: 0);

/* ================= PAGINATION ================= */

$limit=10;
$page=max(1,(int)($_GET['p'] ?? 1));
$offset=($page-1)*$limit;

$total=$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();

$stmt=$pdo->prepare("SELECT * FROM roles ORDER BY id ASC LIMIT $limit OFFSET $offset");
$stmt->execute();

$roles=$stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages=ceil($total/$limit);

/* ================= EDIT MODE ================= */

$editId=(int)($_GET['edit'] ?? 0);
$editRole=null;

if($editId){

$st=$pdo->prepare("SELECT * FROM roles WHERE id=? LIMIT 1");
$st->execute([$editId]);
$editRole=$st->fetch(PDO::FETCH_ASSOC);

if(!$editRole){
$error="Role not found";
$editId=0;
}

}

/* ================= ADD ROLE ================= */

if(isset($_POST['add_role'])){

$role_name=trim($_POST['role_name'] ?? '');
$default_slug=trim($_POST['default_dashboard_slug'] ?? '');

$can_all=(int)($_POST['can_access_all_branches'] ?? 0);
$status=(int)($_POST['status'] ?? 1);

if($role_name=='' || $default_slug==''){
$error="Role Name and Default Dashboard are required.";
}else{

$chk=$pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_name=?");
$chk->execute([$role_name]);

if($chk->fetchColumn()>0){
$error="Role already exists";
}else{

$default_slug=ltrim($default_slug,'/');

$ins=$pdo->prepare("
INSERT INTO roles
(role_name,default_dashboard_slug,can_access_all_branches,status,created_at,updated_at)
VALUES(?,?,?,?,NOW(),NOW())
");

$ins->execute([
$role_name,
$default_slug,
$can_all,
$status
]);

header("Location:index.php?page=role_management");
exit;

}

}

}

/* ================= UPDATE ROLE ================= */

if(isset($_POST['update_role'])){

$id=(int)$_POST['id'];

$role_name=trim($_POST['role_name']);
$default_slug=trim($_POST['default_dashboard_slug']);

$can_all=(int)($_POST['can_access_all_branches'] ?? 0);
$status=(int)($_POST['status'] ?? 1);

if($role_name=='' || $default_slug==''){
$error="Role Name and Default Dashboard are required.";
}else{

if($protectedRoleId && $id==$protectedRoleId){

$role_name=$protectedRoleName;
$status=1;
$can_all=1;
$default_slug="dashboard/superadmin";

}

$upd=$pdo->prepare("
UPDATE roles
SET role_name=?,default_dashboard_slug=?,can_access_all_branches=?,status=?,updated_at=NOW()
WHERE id=?
");

$upd->execute([
$role_name,
$default_slug,
$can_all,
$status,
$id
]);

header("Location:index.php?page=role_management");
exit;
}

}

/* ================= DELETE ROLE ================= */

if(isset($_GET['delete'])){

$deleteId=(int)$_GET['delete'];

if($deleteId==$protectedRoleId){
$error="Super Admin role cannot be deleted";
}else{

$chk=$pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id=?");
$chk->execute([$deleteId]);

if($chk->fetchColumn()>0){
$error="Role assigned to users";
}else{

$pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$deleteId]);
$pdo->prepare("DELETE FROM roles WHERE id=?")->execute([$deleteId]);

header("Location:index.php?page=role_management");
exit;

}

}

}

$canAllValue=isset($_POST['can_access_all_branches'])
?(int)$_POST['can_access_all_branches']
:(isset($editRole['can_access_all_branches'])?(int)$editRole['can_access_all_branches']:0);

$statusValue=isset($_POST['status'])
?(int)$_POST['status']
:(isset($editRole['status'])?(int)$editRole['status']:1);
?>

<style>
.role-form-group { margin-bottom: 12px; }
.role-form-group label {
  font-size: 0.74rem;
  font-weight: 700;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  display: block;
  margin-bottom: 5px;
}
.role-form-group input {
  width: 100%;
  min-height: 38px;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid #ead1df;
  font-size: 0.88rem;
  outline: none;
  background: #fff;
  transition: border-color .2s ease, box-shadow .2s ease;
}
.role-form-group input::placeholder { color: #a3929d; }
.role-form-group input:focus {
  border-color: #e91e63;
  box-shadow: 0 0 0 3px rgba(233,30,99,.12);
}
.role-form-group input[name="role_name"]:not(:placeholder-shown):valid,
.role-form-group input[name="default_dashboard_slug"]:not(:placeholder-shown):valid {
  border-color: #9ee2b8;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
}
.role-form-group small {
  display: block;
  margin-top: 5px;
  font-size: 0.72rem;
  color: #9b8a94;
  line-height: 1.35;
}
.role-segment {
  display: inline-flex;
  width: 100%;
  border: 1px solid #ead1df;
  border-radius: 999px;
  overflow: hidden;
  background: #fff;
  box-shadow: inset 0 1px 2px rgba(233, 30, 99, 0.08);
}
.role-segment-btn {
  flex: 1;
  border: 0;
  background: transparent;
  color: #6b7280;
  min-height: 40px;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s ease;
}
.role-segment-btn + .role-segment-btn { border-left: 1px solid #f3d8e5; }
.role-segment-btn.active {
  background: linear-gradient(135deg, #e91e63 0%, #ff4f9c 100%);
  color: #fff;
}
.atsrm-role-ui-btn:disabled {
  opacity: .58;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
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

<div class="role-page-head">
  <h2 class="page-title">Role Management</h2>
  <div class="role-total-badge">
    <i class="fas fa-users-cog"></i>
    Total Roles: <?= (int)$total ?>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

<div class="atsrm-role-wrapper">
  <div class="atsrm-role-ui-wrap">

    <!-- LEFT FORM CARD -->
    <div class="atsrm-role-ui-left">
      <div class="atsrm-role-ui-card">
        <div class="atsrm-role-ui-header">
          <i class="fas fa-<?= $editRole ? 'pen' : 'plus-circle' ?>" style="margin-right: 8px;"></i>
          <?= $editRole ? 'Edit Role' : 'Add New Role' ?>
        </div>
        <div class="atsrm-role-ui-body">
          <form method="POST" id="roleForm" novalidate>
            <?php if($editRole): ?>
            <input type="hidden" name="id" value="<?= $editRole['id'] ?>">
            <?php endif; ?>

            <div class="atsrm-role-ui-form">
              <!-- Role Name -->
              <div class="atsrm-role-ui-field role-form-group" data-tooltip="Enter unique role name">
                <label>Role Name</label>
                <input type="text" name="role_name" 
                       value="<?= htmlspecialchars($editRole['role_name'] ?? '') ?>" 
                       placeholder="e.g. HR Manager" required>
                <small>Example: HR Manager</small>
              </div>

              <!-- Default Dashboard -->
              <div class="atsrm-role-ui-field role-form-group" data-tooltip="Dashboard slug (e.g. dashboard/hr)">
                <label>Default Dashboard</label>
                <input type="text" name="default_dashboard_slug"
                       value="<?= htmlspecialchars($editRole['default_dashboard_slug'] ?? '') ?>"
                       placeholder="e.g. dashboard/hr" required>
                <small>Example: dashboard/hr</small>
              </div>

              <!-- All Branch -->
              <div class="atsrm-role-ui-field role-form-group" data-tooltip="Access to all branches?">
                <label>All Branch Access</label>
                <input type="hidden" name="can_access_all_branches" id="branchAccessInput" value="<?= $canAllValue ?>">
                <div class="role-segment" role="tablist" aria-label="Branch access">
                  <button type="button" class="role-segment-btn<?= $canAllValue===1?' active':'' ?>" data-target-input="branchAccessInput" data-value="1">All Branches</button>
                  <button type="button" class="role-segment-btn<?= $canAllValue===0?' active':'' ?>" data-target-input="branchAccessInput" data-value="0">Restricted</button>
                </div>
                <small>Choose whether this role can access all branches.</small>
              </div>

              <!-- Status Toggle -->
              <div class="atsrm-role-ui-field role-form-group" data-tooltip="Enable or disable role">
                <label>Status</label>
                <input type="hidden" name="status" id="roleStatusInput" value="<?= $statusValue ?>">
                <div class="role-segment" role="tablist" aria-label="Role status">
                  <button type="button" class="role-segment-btn<?= $statusValue===1?' active':'' ?>" data-target-input="roleStatusInput" data-value="1">Active</button>
                  <button type="button" class="role-segment-btn<?= $statusValue===0?' active':'' ?>" data-target-input="roleStatusInput" data-value="0">Inactive</button>
                </div>
                <small>Active roles can be assigned and used in the system.</small>
              </div>

              <!-- Submit Button -->
              <div class="atsrm-role-ui-field role-form-group">
                <button class="atsrm-role-ui-btn" id="saveRoleBtn" name="<?= $editRole?'update_role':'add_role' ?>" disabled>
                  <i class="fas fa-save"></i>
                  <?= $editRole?'Update Role':'Add Role' ?>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- RIGHT TABLE CARD -->
    <div class="crm-right">

    <div class="crm-card">
      <div class="crm-card-header">
        <div class="role-table-title">
          Roles List
          <span class="role-table-count"><?= (int)$total ?></span>
        </div>
        <div id="rolesTableControls" class="roles-table-controls"></div>
      </div>
        
         
        
        <div class="crm-table-wrapper">
          <table  id="usersTable" class="crm-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Role</th>
                <th>Dashboard</th>
                <th>Branch</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($roles as $r): ?>
              <tr>
                <td data-order="<?= (int)$r['id'] ?>"><strong class="role-row-index"></strong></td>
                <td><?= htmlspecialchars($r['role_name']) ?></td>
                <td><code><?= htmlspecialchars($r['default_dashboard_slug']) ?></code></td>
               <td align="center">
  <?php if($r['can_access_all_branches']): ?>
    <span class="atsrm-role-state-icon ok" data-tooltip="Has access to all branches"><i class="fas fa-check"></i></span>
  <?php else: ?>
    <span class="atsrm-role-state-icon no" data-tooltip="Restricted branches"><i class="fas fa-times"></i></span>
  <?php endif; ?>
</td>
<td align="center">
  <?php if($r['status']): ?>
    <span class="atsrm-role-state-icon ok" data-tooltip="Active"><i class="fas fa-check"></i></span>
  <?php else: ?>
    <span class="atsrm-role-state-icon locked" data-tooltip="Inactive"><i class="fas fa-lock"></i></span>
  <?php endif; ?>
</td>
                <td>
                  <div class="atsrm-role-ui-actions">
                    <a class="action-btn edit" href="index.php?page=role_management&edit=<?= $r['id'] ?>" data-tooltip="Edit role">
                      <i class="fas fa-pen"></i>
                    </a>
                    <a class="action-btn delete delete-role" data-id="<?= $r['id'] ?>" data-tooltip="Delete role" >
                      <i class="fas fa-trash"></i>
  </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="rolesTableFooter" class="roles-table-footer"></div>
  </div>

        <!-- Pagination -->
        <?php if($totalPages>1): ?>
        <div class="atsrm-role-ui-pagination">
          <?php for($i=1;$i<=$totalPages;$i++): ?>
          <a href="index.php?page=role_management&p=<?= $i ?>" class="<?= ($i==$page)?'active':'' ?>">
            <?= $i ?>
          </a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Hidden duplicate pagination (kept for compatibility) -->
  <div class="atsrm-role-pagination">
    <?php for($i=1;$i<=$totalPages;$i++): ?>
    <a href="index.php?page=role_management&p=<?= $i ?>" class="<?= ($i==$page)?'active':'' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
</div>

<!-- SweetAlert2 & Delete Confirmation (unchanged) -->
<script>
const roleForm = document.getElementById('roleForm');
if (roleForm) {
  roleForm.addEventListener('submit', function (e) {
    const roleName = this.querySelector('input[name="role_name"]');
    const dashboardSlug = this.querySelector('input[name="default_dashboard_slug"]');
    const roleValue = (roleName?.value || '').trim();
    const dashboardValue = (dashboardSlug?.value || '').trim();

    if (!roleValue || !dashboardValue) {
      e.preventDefault();
      if (window.Swal && Swal.fire) {
        Swal.fire({
          icon: 'warning',
          title: 'Missing Information',
          text: 'Role Name and Default Dashboard are required.',
          confirmButtonColor: '#e91e63'
        });
      } else {
        alert('Role Name and Default Dashboard are required.');
      }
      if (!roleValue && roleName) roleName.focus();
      if (roleValue && !dashboardValue && dashboardSlug) dashboardSlug.focus();
      return;
    }
  });
}

document.querySelectorAll('.role-segment-btn').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const inputId = this.getAttribute('data-target-input');
    const value = this.getAttribute('data-value') || '0';
    const hiddenInput = document.getElementById(inputId);
    if (!hiddenInput) return;
    hiddenInput.value = value;

    const group = this.closest('.role-segment');
    if (group) {
      group.querySelectorAll('.role-segment-btn').forEach(function (x) {
        x.classList.remove('active');
      });
    }
    this.classList.add('active');
  });
});

const saveRoleBtn=document.getElementById('saveRoleBtn');
const roleReqInputs=[
document.querySelector('[name="role_name"]'),
document.querySelector('[name="default_dashboard_slug"]')
];
const syncSaveRoleState=function(){
if(!saveRoleBtn) return;
const ok=roleReqInputs.every(function(inp){ return inp && inp.value.trim()!==''; });
saveRoleBtn.disabled=!ok;
};
roleReqInputs.forEach(function(inp){
if(inp){ inp.addEventListener('input',syncSaveRoleState); }
});
syncSaveRoleState();

document.querySelectorAll('.delete-role').forEach(btn=>{
  btn.addEventListener('click',function(e){
    e.preventDefault();
    let id=this.dataset.id;
    Swal.fire({
      title: 'Delete role?',
      text: 'This action cannot be undone.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e91e63',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Yes, delete'
    }).then((result)=>{
      if(result.isConfirmed){
        window.location='index.php?page=role_management&delete='+id;
      }
    });
  });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function(){

crmDataTable('#usersTable',{
pageLength:5,
lengthMenu:[5,10,20,50],
ordering:true,
order:[[1,'asc']],
scrollX:false,
scrollY:false,
scrollCollapse:false
});

function syncRoleRowIndex() {
  if (!window.jQuery || !window.jQuery.fn.DataTable) return;
  const $table = window.jQuery('#usersTable');
  if (!$table.length || !window.jQuery.fn.DataTable.isDataTable($table)) return;

  const dt = $table.DataTable();
  const start = dt.page.info().start;
  dt.column(0, { search: 'applied', order: 'applied', page: 'current' })
    .nodes()
    .each(function (cell, i) {
      const el = cell.querySelector('.role-row-index');
      if (el) el.textContent = String(start + i + 1);
    });
}

if (window.jQuery) {
  window.jQuery('#usersTable').on('draw.dt order.dt search.dt', syncRoleRowIndex);
}
setTimeout(syncRoleRowIndex, 80);

setTimeout(function () {
  function relocateRoleTableControls() {
    var wrapper = document.getElementById('usersTable_wrapper');
    var controlsTarget = document.getElementById('rolesTableControls');
    var footerTarget = document.getElementById('rolesTableFooter');
    if (!wrapper || !controlsTarget || !footerTarget) return false;

    var top = wrapper.querySelector('.dt-top');
    var bottom = wrapper.querySelector('.dt-bottom');

    if (!top) {
      var length = wrapper.querySelector('.dataTables_length');
      var filter = wrapper.querySelector('.dataTables_filter');
      var buttons = wrapper.querySelector('.dt-buttons');
      if (length || filter || buttons) {
        top = document.createElement('div');
        top.className = 'dt-top';
        if (length) top.appendChild(length);
        if (filter) top.appendChild(filter);
        if (buttons) top.appendChild(buttons);
      }
    }

    if (!bottom) {
      var info = wrapper.querySelector('.dataTables_info');
      var paginate = wrapper.querySelector('.dataTables_paginate');
      if (info || paginate) {
        bottom = document.createElement('div');
        bottom.className = 'dt-bottom';
        if (info) bottom.appendChild(info);
        if (paginate) bottom.appendChild(paginate);
      }
    }

    if (top) controlsTarget.appendChild(top);
    if (bottom) footerTarget.appendChild(bottom);

    return !!(top && bottom);
  }

  relocateRoleTableControls();
  setTimeout(relocateRoleTableControls, 200);
  setTimeout(relocateRoleTableControls, 600);
  setTimeout(relocateRoleTableControls, 1200);

  if (window.jQuery) {
    window.jQuery('#usersTable').on('draw.dt', relocateRoleTableControls);
  }
}, 50);

});

</script>

