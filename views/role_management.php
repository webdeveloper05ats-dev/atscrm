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

$can_all=isset($_POST['can_access_all_branches'])?1:0;
$status=isset($_POST['status'])?1:0;

if($role_name==''){
$error="Role name required";
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

$can_all=isset($_POST['can_access_all_branches'])?1:0;
$status=isset($_POST['status'])?1:0;

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
?>



<h2 class="page-title">Role Management</h2>

<?php if($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
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
              <div class="atsrm-role-ui-field" data-tooltip="Enter unique role name">
                <label>Role Name</label>
                <input type="text" name="role_name" 
                       value="<?= htmlspecialchars($editRole['role_name'] ?? '') ?>" 
                       placeholder="e.g. Manager" required>
                       <div class="atsrm-role-ui-hint">Example: Manager</div>
              </div>

              <!-- Default Dashboard -->
              <div class="atsrm-role-ui-field" data-tooltip="Dashboard slug (e.g. dashboard/hr)">
                <label>Default Dashboard</label>
                <input type="text" name="default_dashboard_slug"
                       value="<?= htmlspecialchars($editRole['default_dashboard_slug'] ?? '') ?>"
                       placeholder="dashboard/hr">
                <div class="atsrm-role-ui-hint">Example: dashboard/test</div>
              </div>

              <!-- All Branch Toggle -->
            <div class="atsrm-role-ui-field" data-tooltip="Access to all branches?">
<label>All Branch</label>
<label class="atsrm-role-ui-switch">
<input type="checkbox" name="can_access_all_branches" value="1"
<?= (isset($editRole['can_access_all_branches']) && $editRole['can_access_all_branches']==1) ? 'checked' : '' ?>>
<span class="atsrm-role-ui-slider"></span>
</label>
</div>

              <!-- Status Toggle -->
              <div class="atsrm-role-ui-field" data-tooltip="Enable or disable role">
<label>Status</label>
<label class="atsrm-role-ui-switch">
<input type="checkbox" name="status" value="1"
<?= (isset($editRole['status']) && $editRole['status']==1) ? 'checked' : 'checked' ?>>
<!-- Note: The default is checked, but you might want to use the actual value -->
<span class="atsrm-role-ui-slider"></span>
</label>
</div>

              <!-- Submit Button -->
              <div class="atsrm-role-ui-field">
                <button class="atsrm-role-ui-btn" name="<?= $editRole?'update_role':'add_role' ?>">
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
        <h3><i class="fas fa-list" style="margin-right: 8px;"></i> Roles List</h3>
        <div id="rolesTableControls" class="roles-table-controls"></div>
      </div>
        
         
        
        <div class="crm-table-wrapper">
          <table  id="usersTable" class="crm-table">
            <thead>
              <tr>
                <th>ID</th>
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
                <td><strong><?= $r['id'] ?></strong></td>
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
                    <a class="crm-btn crm-edit" href="index.php?page=role_management&edit=<?= $r['id'] ?>" data-tooltip="Edit role">
                      <i class="fas fa-pen"></i>
                    </a>
                    <a class="crm-btn crm-delete delete-role" data-id="<?= $r['id'] ?>" data-tooltip="Delete role" >
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const roleForm = document.getElementById('roleForm');
if (roleForm) {
  roleForm.addEventListener('submit', function (e) {
    const roleName = this.querySelector('input[name="role_name"]');
    const roleValue = (roleName?.value || '').trim();

    if (!roleValue) {
      e.preventDefault();
      if (window.Swal && Swal.fire) {
        Swal.fire({
          icon: 'warning',
          title: 'Role Name Required',
          text: 'Please enter role name before saving.',
          confirmButtonColor: '#e91e63'
        });
      } else {
        alert('Please enter role name before saving.');
      }
      if (roleName) roleName.focus();
      return;
    }
  });
}

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
order:[[1,'asc']]
});

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



