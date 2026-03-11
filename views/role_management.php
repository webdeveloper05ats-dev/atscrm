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


<div class="crm-row">


<!-- LEFT FORM -->

<div class="crm-col-4">

<div class="card">

<div class="card-header">
<?= $editRole ? 'Edit Role' : 'Add Role' ?>
</div>

<form method="POST">

<?php if($editRole): ?>
<input type="hidden" name="id" value="<?= $editRole['id'] ?>">
<?php endif; ?>

<div class="crm-form-grid">

<div class="form-group">
<label>Role Name</label>
<input type="text" name="role_name"
value="<?= htmlspecialchars($editRole['role_name'] ?? '') ?>" required>
</div>

<div class="form-group">
<label>Default Dashboard</label>
<input type="text"
name="default_dashboard_slug"
value="<?= htmlspecialchars($editRole['default_dashboard_slug'] ?? '') ?>"
placeholder="dashboard/hr">
</div>


<div class="form-group form-switch-row">

<label>All Branch Access</label>

<label class="crm-switch">
<input type="checkbox" name="can_access_all_branches">
<span>Allow</span>
</label>

</div>


<div class="form-group">

<label>Status</label>

<label class="crm-switch">

<input type="checkbox"
name="status"
value="1"
<?= (!isset($editRole['status']) || $editRole['status']==1)?'checked':'' ?>>

<span>Active</span>

</label>

</div>


<div class="crm-full">

<button class="btn btn-primary"
name="<?= $editRole?'update_role':'add_role' ?>"
style="width:100%;">

<i class="fas fa-save"></i>

<?= $editRole?'Update Role':'Add Role' ?>

</button>

</div>

</div>
</form>

</div>
</div>



<!-- RIGHT TABLE -->

<div class="crm-col-8">

<div class="card">

<div class="card-header">Roles List</div>

<div class="table-responsive">

<table class="table">

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

<td><?= $r['id'] ?></td>

<td><?= htmlspecialchars($r['role_name']) ?></td>

<td><?= htmlspecialchars($r['default_dashboard_slug']) ?></td>

<td style="text-align:center;">

<?= $r['can_access_all_branches']
? '<i class="fas fa-check-circle" style="color:green;"></i>'
: '<i class="fas fa-times-circle" style="color:#b30000;"></i>'
?>

</td>

<td style="text-align:center;">

<?= $r['status']
? '<i class="fas fa-check-circle" style="color:green;"></i>'
: '<i class="fas fa-times-circle" style="color:#b30000;"></i>'
?>

</td>


<td style="white-space:nowrap;">


<a class="action-btn btn-edit crm-tooltip"
data-tip="Edit Role"
href="index.php?page=role_management&edit=<?= $r['id'] ?>">

<i class="fas fa-pen"></i>

</a>


<button class="action-btn btn-danger crm-tooltip delete-role"
data-id="<?= $r['id'] ?>"
data-tip="Delete Role">

<i class="fas fa-trash"></i>

</button>


</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<div class="crm-pagination">

<?php for($i=1;$i<=$totalPages;$i++): ?>

<a href="index.php?page=role_management&p=<?= $i ?>"
class="<?= ($i==$page)?'active':'' ?>">

<?= $i ?>

</a>

<?php endfor; ?>

</div>

</div>
</div>

</div>



<script>

document.querySelectorAll('.delete-role').forEach(btn=>{

btn.addEventListener('click',function(){

let id=this.dataset.id;

Swal.fire({
title:'Delete role?',
text:'This action cannot be undone.',
icon:'warning',
showCancelButton:true,
confirmButtonColor:'#e91e63',
cancelButtonColor:'#6c757d',
confirmButtonText:'Yes delete'
}).then((result)=>{

if(result.isConfirmed){

window.location='index.php?page=role_management&delete='+id;

}

});

});

});

</script>