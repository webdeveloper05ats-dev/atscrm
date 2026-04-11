<?php
requireView('user_add');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
    redirect('index.php');
    exit;
}

$loggedInUserId = (int)($_SESSION['user_id'] ?? 0);

$roles = $pdo->query("SELECT id, role_name FROM roles WHERE status=1 ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query("SELECT id, branch_name FROM branches WHERE status=1 ORDER BY branch_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$editId = (int)($_GET['edit'] ?? 0);
$editUser = null;

if ($editId > 0) {
    $st = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
    $st->execute([$editId]);
    $editUser = $st->fetch(PDO::FETCH_ASSOC);

    if (!$editUser) {
        $error = "User not found.";
        $editId = 0;
    }
}

/* ADD USER */

if (isset($_POST['add_user'])) {

    $branchAutoAssigned = false;
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role_id   = (int)($_POST['role_id'] ?? 0);
    $branch_id = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : 1;
    $status    = (int)($_POST['status'] ?? 1);

    if (($_POST['branch_id'] ?? '') === '') {
        $branchAutoAssigned = true;
    }

    if ($name === '' || $email === '' || $password === '' || $role_id <= 0) {
        $error = "Name, Email, Password, Role are required.";
    } else {

        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
        $chk->execute([$email]);

        if ($chk->fetchColumn() > 0) {
            $error = "Email already exists.";
        } else {

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $ins = $pdo->prepare("
                INSERT INTO users
                (branch_id, role_id, name, email, phone, password, status, created_at, updated_at, created_by, updated_by, ip_address, user_agent)
                VALUES
                (:branch_id, :role_id, :name, :email, :phone, :password, :status, NOW(), NOW(), :created_by, :updated_by, :ip, :ua)
            ");

            $ins->execute([
                ':branch_id'  => $branch_id,
                ':role_id'    => $role_id,
                ':name'       => $name,
                ':email'      => $email,
                ':phone'      => $phone ?: null,
                ':password'   => $hash,
                ':status'     => $status,
                ':created_by' => $loggedInUserId ?: null,
                ':updated_by' => $loggedInUserId ?: null,
                ':ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            $success = $branchAutoAssigned
                ? "User created successfully! Branch was not selected, so we set this user to Main Branch. You can update it later."
                : "User created successfully!";
        }
    }
}

/* UPDATE USER */

if (isset($_POST['update_user'])) {

    $branchAutoAssigned = false;
    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role_id   = (int)($_POST['role_id'] ?? 0);
    $branch_id = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : 1;
    $status    = (int)($_POST['status'] ?? 1);

    if (($_POST['branch_id'] ?? '') === '') {
        $branchAutoAssigned = true;
    }

    if ($id <= 0 || $name === '' || $email === '' || $role_id <= 0) {
        $error = "Name, Email, Role are required.";
    } else {

        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=? AND id!=?");
        $chk->execute([$email, $id]);

        if ($chk->fetchColumn() > 0) {
            $error = "Email already exists.";
        } else {

            if ($password !== '') {

                $hash = password_hash($password, PASSWORD_DEFAULT);

                $sql = "
                    UPDATE users SET
                        branch_id=:branch_id,
                        role_id=:role_id,
                        name=:name,
                        email=:email,
                        phone=:phone,
                        password=:password,
                        status=:status,
                        updated_at=NOW(),
                        updated_by=:updated_by,
                        ip_address=:ip,
                        user_agent=:ua
                    WHERE id=:id
                ";

            } else {

                $sql = "
                    UPDATE users SET
                        branch_id=:branch_id,
                        role_id=:role_id,
                        name=:name,
                        email=:email,
                        phone=:phone,
                        status=:status,
                        updated_at=NOW(),
                        updated_by=:updated_by,
                        ip_address=:ip,
                        user_agent=:ua
                    WHERE id=:id
                ";

            }

            $upd = $pdo->prepare($sql);

            $params = [
                ':branch_id'  => $branch_id,
                ':role_id'    => $role_id,
                ':name'       => $name,
                ':email'      => $email,
                ':phone'      => $phone ?: null,
                ':status'     => $status,
                ':updated_by' => $loggedInUserId ?: null,
                ':ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ':id'         => $id,
            ];

            if ($password !== '') {
                $params[':password'] = $hash;
            }

            $upd->execute($params);

            $success = $branchAutoAssigned
                ? "User updated successfully! Branch was not selected, so we set this user to Main Branch. You can update it later."
                : "User updated successfully!";
        }
    }
}

/* DELETE USER */

if (isset($_GET['delete'])) {

    $deleteId = (int)$_GET['delete'];

    if ($deleteId === $loggedInUserId) {
        $error = "You cannot delete your own account.";
    } else {

        $del = $pdo->prepare("DELETE FROM users WHERE id=?");
        $del->execute([$deleteId]);

        $success = "User deleted successfully!";
    }
}

$users = $pdo->query("
    SELECT u.*, r.role_name, b.branch_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN branches b ON u.branch_id = b.id
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if($success): ?>
<script>
Swal.fire({
icon:"success",
title:"Success",
text:"<?= addslashes($success) ?>",
confirmButtonColor:"#e91e63"
});
</script>
<?php endif; ?>
<?php if($error): ?>
<script>
Swal.fire({
icon:"error",
title:"Error",
text:"<?= addslashes($error) ?>",
confirmButtonColor:"#e91e63"
});
</script>
<?php endif; ?>

<div class="user-page-head">
<h2 class="user-page-title">User Management</h2>
<div class="user-total-badge">
<i class="fas fa-users"></i>
Total Users: <?= count($users) ?>
</div>
</div>

<div class="user-page">
<div class="user-left">
<div class="user-card">
<div class="user-card-head">
<h3 class="user-card-title"><?= $editUser ? 'Edit User' : 'Add New User' ?></h3>
<?php if ($editUser): ?>
<span class="user-card-badge"><i class="fas fa-pen"></i> Editing #<?= (int)$editUser['id'] ?></span>
<?php endif; ?>
</div>
<div class="user-card-body">
<form method="POST" id="userForm" novalidate>
<?php if ($editUser): ?>
<input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
<?php endif; ?>

<div class="user-form-group">
<label>Name <span style="color:red;">*</span></label>
<input type="text" name="name" placeholder="Example: John Smith" required value="<?= htmlspecialchars($editUser['name'] ?? '') ?>">
</div>

<div class="user-form-group">
<label>Email <span style="color:red;">*</span></label>
<input type="email" name="email" placeholder="Example: john@gmail.com" required value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
</div>

<div class="user-form-group">
<label>Phone</label>
<input type="tel" name="phone" placeholder="Example: 9876543210" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
</div>

<div class="user-form-group">
<label>Password<?= $editUser ? ' <span style="color:#6b7280;font-weight:600;">(leave blank to keep same)</span>' : ' <span style="color:red;">*</span>' ?></label>
<input type="password" name="password" placeholder="Enter password">
</div>

<div class="user-form-group">
<label>Role <span style="color:red;">*</span></label>
<select name="role_id" required>
<option value="">Select Role</option>
<?php foreach ($roles as $r): ?>
<option value="<?= $r['id'] ?>" <?= (isset($editUser['role_id']) && $editUser['role_id']==$r['id'])?'selected':'' ?>>
<?= htmlspecialchars($r['role_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="user-form-group">
<label>Branch</label>
<select name="branch_id">
<option value="">All / Not Assigned</option>
<?php foreach ($branches as $b): ?>
<option value="<?= $b['id'] ?>" <?= (isset($editUser['branch_id']) && $editUser['branch_id']==$b['id'])?'selected':'' ?>>
<?= htmlspecialchars($b['branch_name']) ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="user-form-group">
<label>Status</label>
<?php $userStatus = (!isset($editUser['status']) || (int)$editUser['status']===1) ? 1 : 0; ?>
<input type="hidden" name="status" id="userStatusInput" value="<?= $userStatus ?>">
<div class="user-segment" role="tablist" aria-label="User status">
<button type="button" class="user-segment-btn<?= $userStatus===1 ? ' active' : '' ?>" data-status="1">Active</button>
<button type="button" class="user-segment-btn<?= $userStatus===0 ? ' active' : '' ?>" data-status="0">Inactive</button>
</div>
</div>

<button type="submit" id="saveUserBtn" name="<?= $editUser ? 'update_user':'add_user' ?>" class="user-submit" disabled>
<?= $editUser ? 'Update User':'Add User' ?>
</button>
</form>
</div>
</div>
</div>

<div class="user-right">
<div class="user-card" id="usersTableArea">
<div class="user-card-head">
<div class="user-card-title-wrap">
<h3 class="user-card-title">Users List</h3>
<span class="user-card-badge"><i class="fas fa-users"></i> <?= count($users) ?></span>
</div>
<div id="userTableControls" class="user-table-controls"></div>
</div>
<div class="user-card-body">
<div class="user-table-wrapper">
<table id="usersTable" class="user-table">
<thead>
<tr>
<th>#</th>
<th>Name</th>
<th>Email</th>
<th>Role</th>
<th>Branch</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php $i=1; foreach($users as $u): ?>
<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($u['name']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= htmlspecialchars($u['role_name'] ?? '-') ?></td>
<td><?= htmlspecialchars($u['branch_name'] ?? 'All') ?></td>
<td>
<?= ((int)$u['status']==1)
? '<i class="fas fa-check-circle" style="color:green;"></i>'
: '<i class="fas fa-times-circle" style="color:red;"></i>' ?>
</td>
<td>
<div class="user-actions">
<a class="user-btn user-edit" title="Edit User" href="index.php?page=user_add&edit=<?= $u['id'] ?>">
<i class="fas fa-pen"></i>
</a>
<?php if ($u['id'] != $loggedInUserId): ?>
<a class="user-btn user-delete" title="Delete User" href="index.php?page=user_add&delete=<?= $u['id'] ?>" onclick="return confirm('Delete this user?')">
<i class="fas fa-trash"></i>
</a>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div id="userTableFooter" class="user-table-footer"></div>
</div>
</div>
</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){
const statusInput=document.getElementById("userStatusInput");
const statusBtns=document.querySelectorAll(".user-segment-btn");
if(statusInput && statusBtns.length){
statusBtns.forEach(function(btn){
btn.addEventListener("click",function(){
statusInput.value=this.getAttribute("data-status") || "1";
statusBtns.forEach(function(x){ x.classList.remove("active"); });
this.classList.add("active");
});
});
}

const saveBtn=document.getElementById("saveUserBtn");
const reqInputs=[
document.querySelector("input[name='name']"),
document.querySelector("input[name='email']"),
document.querySelector("select[name='role_id']")
];
const syncState=function(){
if(!saveBtn) return;
const nameOk=!!(reqInputs[0] && reqInputs[0].value.trim()!=="");
const emailOk=!!(reqInputs[1] && reqInputs[1].value.trim()!=="");
const roleOk=!!(reqInputs[2] && reqInputs[2].value!=="");
saveBtn.disabled=!(nameOk && emailOk && roleOk);
};
reqInputs.forEach(function(inp){
if(inp){ inp.addEventListener("input",syncState); inp.addEventListener("change",syncState); }
});
syncState();
});
</script>
<script>
document.getElementById("userForm").addEventListener("submit", function(e){
let name  = document.querySelector("input[name='name']").value.trim();
let email = document.querySelector("input[name='email']").value.trim();
let role  = document.querySelector("select[name='role_id']").value;
if(name === "" || email === "" || role === ""){
e.preventDefault();
Swal.fire({
icon: "warning",
title: "Missing Fields",
text: "Name, Email and Role are required.",
confirmButtonColor:"#e91e63"
});
}
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function(){
crmDataTable('#usersTable',{
pageLength:5,
lengthMenu:[5,10,20,50],
ordering:true,
order:[[1,'asc']],
searchPlaceholder:"Search users...",
dom:"<'dt-top'lfB>rt<'dt-bottom'ip>"
});

setTimeout(function () {
const wrapper=document.querySelector('#usersTable_wrapper');
const controlsTarget=document.getElementById('userTableControls');
const footerTarget=document.getElementById('userTableFooter');
if(!wrapper) return;
const top=wrapper.querySelector('.dt-top');
const bottom=wrapper.querySelector('.dt-bottom');
if(top && controlsTarget){ controlsTarget.appendChild(top); }
if(bottom && footerTarget){ footerTarget.appendChild(bottom); }
},120);
});
</script>


