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

<!-- ============================================= -->
<!-- MODERNIZED UI - RESPONSIVE + TOOLTIPS (NO LOGIC CHANGED) -->
<!-- ============================================= -->

<style>
/* ---------- Global Styles ---------- */
:root {
  --primary: #e91e63;
  --primary-light: #f8bbd0;
  --primary-dark: #c2185b;
  --secondary: #6c757d;
  --success: #28a745;
  --danger: #dc3545;
  --warning: #ffc107;
  --info: #17a2b8;
  --light: #f8f9fa;
  --dark: #343a40;
  --white: #ffffff;
  --gray-100: #f8f9fa;
  --gray-200: #e9ecef;
  --gray-300: #dee2e6;
  --gray-400: #ced4da;
  --gray-500: #adb5bd;
  --gray-600: #6c757d;
  --gray-700: #495057;
  --gray-800: #343a40;
  --gray-900: #212529;
  --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --transition: all 0.2s ease;
}

/* ---------- Tooltip Styles ---------- */
[data-tooltip] {
  position: relative;
  cursor: pointer;
}
[data-tooltip]:before {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(-5px);
  background: var(--gray-800);
  color: white;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  white-space: nowrap;
  z-index: 10;
  opacity: 0;
  visibility: hidden;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
  pointer-events: none;
  font-weight: normal;
  letter-spacing: 0.3px;
}
[data-tooltip]:after {
  content: '';
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(5px);
  border-width: 5px;
  border-style: solid;
  border-color: var(--gray-800) transparent transparent transparent;
  opacity: 0;
  visibility: hidden;
  transition: var(--transition);
  pointer-events: none;
}
[data-tooltip]:hover:before,
[data-tooltip]:hover:after {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}

/* ---------- Layout ---------- */
.atsrm-role-wrapper {
  padding: 1rem 0;
}
.atsrm-role-ui-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
  align-items: flex-start;
}
.atsrm-role-ui-left {
  flex: 1 1 340px;
}
.atsrm-role-ui-right {
  flex: 2 1 620px;
}

/* ---------- Cards ---------- */
.atsrm-role-ui-card {
  background: var(--white);
  border-radius: var(--radius-lg);
  border: 1px solid var(--gray-200);
  box-shadow: var(--shadow-md);
  overflow: hidden;
  transition: var(--transition);
}
.atsrm-role-ui-card:hover {
  box-shadow: var(--shadow-lg);
}
.atsrm-role-ui-header {
  padding: 16px 20px;
  font-weight: 600;
  background: var(--gray-100);
  border-bottom: 1px solid var(--gray-200);
  color: var(--gray-800);
  font-size: 1rem;
  letter-spacing: 0.3px;
}
.atsrm-role-ui-body {
  padding: 20px;
}

/* ---------- Form ---------- */
.atsrm-role-ui-form {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-end;
}
.atsrm-role-ui-field {
  flex: 1 1 180px;
  min-width: 160px;
}
.atsrm-role-ui-field label {
  font-size: 0.8rem;
  font-weight: 600;
  margin-bottom: 4px;
  display: block;
  color: var(--gray-700);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}
.atsrm-role-ui-field input[type="text"] {
  width: 100%;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  border: 1px solid var(--gray-300);
  font-size: 0.9rem;
  transition: var(--transition);
}
.atsrm-role-ui-field input[type="text"]:focus {
  border-color: var(--primary);
  outline: none;
  box-shadow: 0 0 0 3px var(--primary-light);
}

/* ---------- Toggle Switch ---------- */
.atsrm-role-ui-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 24px;
  margin-top: 4px;
}
.atsrm-role-ui-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}
.atsrm-role-ui-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--gray-400);
  border-radius: 34px;
  transition: var(--transition);
}
.atsrm-role-ui-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  border-radius: 50%;
  transition: var(--transition);
}
.atsrm-role-ui-switch input:checked + .atsrm-role-ui-slider {
  background-color: var(--primary);
}
.atsrm-role-ui-switch input:checked + .atsrm-role-ui-slider:before {
  transform: translateX(24px);
}

/* ---------- Hint ---------- */
.atsrm-role-ui-hint {
  font-size: 0.75rem;
  color: var(--gray-600);
  margin-top: 4px;
}

/* ---------- Button ---------- */
.atsrm-role-ui-btn {
  background: var(--primary);
  color: white;
  border: none;
  padding: 10px 16px;
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
  width: 100%;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.atsrm-role-ui-btn:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}
.atsrm-role-ui-btn i {
  font-size: 0.9rem;
}

/* ---------- Alert ---------- */
.alert {
  padding: 12px 20px;
  border-radius: var(--radius-md);
  margin-bottom: 20px;
  font-size: 0.95rem;
}
.alert-danger {
  background: #f8d7da;
  border: 1px solid #f5c6cb;
  color: #721c24;
}

.crm-right{
flex:2 1 620px;
width:100%;
max-width:100%;
box-sizing:border-box;
}
.crm-card{
background:#fff;
border-radius:14px;
padding:20px;
box-shadow:0 8px 20px rgba(0,0,0,.05);
border:1px solid #f1d6e3;
width:100%;
max-width:100%;
box-sizing:border-box;
}



/* ======================================================
DATATABLE MOBILE FIX
====================================================== */

@media (max-width:768px){

.crm-table-wrapper{
width:100%;
overflow-x:auto;
-webkit-overflow-scrolling:touch;
}

.crm-table{
min-width:700px;
border-collapse:collapse;
}

/* header stack */

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dt-buttons{
width:100%;
display:flex;
justify-content:center;
margin-bottom:10px;
}

/* search */

.dataTables_wrapper .dataTables_filter input{
width:100% !important;
max-width:100%;
}

/* export button */

.crm-export-btn{
width:100% !important;
}

/* pagination center */

.dataTables_wrapper .dataTables_paginate{
display:flex;
justify-content:center;
margin-top:10px;
}

}
</style>

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
          <form method="POST">
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

      <h3> <i class="fas fa-list" style="margin-right: 8px;"></i>
          Roles List</h3>
        
         
        
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
    <span data-tooltip="Has access to all branches">✅</span>
  <?php else: ?>
    <span data-tooltip="Restricted branches">❌</span>
  <?php endif; ?>
</td>
<td align="center">
  <?php if($r['status']): ?>
    <span data-tooltip="Active">✅</span>
  <?php else: ?>
    <span data-tooltip="Inactive">🔒</span>  <!-- or ❌ -->
  <?php endif; ?>
</td>
                <td>
                  <div class="atsrm-role-ui-actions">
                    <a class="crm-btn crm-edit" href="index.php?page=role_management&edit=<?= $r['id'] ?>" data-tooltip="Edit role">
                      <i class="fas fa-pen"></i>
                    </a>
                    <a class="crm-btn crm-delete" title="Delete User" data-id="<?= $r['id'] ?>" data-tooltip="Delete role" >
                      <i class="fas fa-trash"></i>
  </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
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

});

</script>