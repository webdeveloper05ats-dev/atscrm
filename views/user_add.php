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

    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role_id   = (int)($_POST['role_id'] ?? 0);
    $branch_id = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : null;
    $status    = (int)($_POST['status'] ?? 1);

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

            $success = "User created successfully!";
        }
    }
}

/* UPDATE USER */

if (isset($_POST['update_user'])) {

    $id        = (int)($_POST['id'] ?? 0);
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role_id   = (int)($_POST['role_id'] ?? 0);
    $branch_id = ($_POST['branch_id'] ?? '') !== '' ? (int)$_POST['branch_id'] : null;
    $status    = (int)($_POST['status'] ?? 1);

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

            $success = "User updated successfully!";
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

<h2 style="margin-bottom:20px;">User Management</h2>
<style>
/* =====================================================
ATS CRM - GLOBAL TABLE + DATATABLE STYLE
Reusable across all CRM pages
===================================================== */


/* =====================================================
CRM LAYOUT
===================================================== */

.crm-container{
display:flex;
gap:20px;
flex-wrap:wrap;
width:100%;
max-width:100%;
}

.crm-left{
flex:1;
min-width:300px;
max-width:100%;
}

.crm-right{
flex:2;
min-width:450px;
max-width:100%;
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

.crm-card h3{
margin-bottom:16px;
}


/* =====================================================
FORM ELEMENTS
===================================================== */

.crm-form-group{
margin-bottom:14px;
}

.crm-form-group label{
font-weight:600;
font-size:13px;
display:block;
margin-bottom:5px;
}

.crm-form-group input,
.crm-form-group select{
width:100%;
padding:10px;
border-radius:8px;
border:1px solid #ddd;
box-sizing:border-box;
}

.crm-form-group input::placeholder{
font-size:12px;
color:#aaa;
}


/* =====================================================
TOGGLE SWITCH
===================================================== */

.crm-switch{
position:relative;
display:inline-block;
width:46px;
height:24px;
}

.crm-switch input{
display:none;
}

.crm-slider{
position:absolute;
cursor:pointer;
top:0;
left:0;
right:0;
bottom:0;
background:#ccc;
border-radius:20px;
}

.crm-slider:before{
position:absolute;
content:"";
height:18px;
width:18px;
left:3px;
bottom:3px;
background:white;
border-radius:50%;
transition:.3s;
}

.crm-switch input:checked + .crm-slider{
background:#e91e63;
}

.crm-switch input:checked + .crm-slider:before{
transform:translateX(22px);
}


/* =====================================================
CRM TABLE
===================================================== */

.crm-table{
width:100%;
border-collapse:collapse;
border:1px solid #f1d6e3;
}

.crm-table th,
.crm-table td{
border:1px solid #f1d6e3;
padding:10px;
font-size:13px;
white-space:nowrap;
}

.crm-table th{
background:#fff0f5;
font-weight:600;
text-align:left;
}


/* =====================================================
TABLE WRAPPER (SCROLL ON MOBILE)
===================================================== */

.crm-table-wrapper{
width:100%;
max-width:100%;
overflow-x:auto;
}


/* =====================================================
ACTION BUTTONS
===================================================== */

.crm-btn{
display:inline-flex;
align-items:center;
justify-content:center;
width:34px;
height:34px;
border-radius:8px;
color:#fff;
margin-right:5px;
}

.crm-edit{
background:#e91e63;
}

.crm-delete{
background:#dc3545;
}


/* =====================================================
DATATABLE HEADER AREA
===================================================== */

.crm-table-header{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:15px;
flex-wrap:wrap;
gap:10px;
}

.crm-table-footer{
display:flex;
justify-content:space-between;
align-items:center;
margin-top:15px;
flex-wrap:wrap;
gap:10px;
}


/* =====================================================
MOBILE RESPONSIVE
===================================================== */

@media(max-width:768px){

.crm-container{
flex-direction:column;
width:100%;
}

.crm-left,
.crm-right{
width:100%;
min-width:100%;
}

.crm-card{
width:100%;
max-width:100%;
}

.crm-table{
min-width:600px;
}

.crm-table-wrapper{
overflow-x:auto;
}

}
</style>
<?php if($success): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

Swal.fire({
icon:"success",
title:"Success",
text:"<?= $success ?>",
confirmButtonColor:"#e91e63"
});

</script>

<?php endif; ?>
<div class="crm-container">

<div class="crm-left">

<div class="crm-card">

<h3><?= $editUser ? 'Edit User' : 'Add New User' ?></h3>

<form method="POST" id="userForm" novalidate>

<?php if ($editUser): ?>
<input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
<?php endif; ?>

<div class="crm-form-group">
<label>Name</label>
<input type="text" name="name" placeholder="Example: John Smith" required value="<?= htmlspecialchars($editUser['name'] ?? '') ?>">
</div>

<div class="crm-form-group">
<label>Email</label>
<input type="email" name="email" placeholder="Example: john@gmail.com" required value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
</div>

<div class="crm-form-group">
<label>Phone</label>
<input type="text" name="phone" placeholder="Example: 9876543210" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
</div>

<div class="crm-form-group">
<label>Password <?= $editUser ? '(leave blank to keep same)' : '' ?></label>
<input type="password" name="password" placeholder="Enter password">
</div>

<div class="crm-form-group">
<label>Role</label>
<select name="role_id" required>

<option value="">Select Role</option>

<?php foreach ($roles as $r): ?>

<option value="<?= $r['id'] ?>" <?= (isset($editUser['role_id']) && $editUser['role_id']==$r['id'])?'selected':'' ?>>

<?= htmlspecialchars($r['role_name']) ?>

</option>

<?php endforeach; ?>

</select>
</div>

<div class="crm-form-group">
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

<div class="crm-form-group">
<label>Status</label>

<label class="crm-switch">
<input type="checkbox" name="status" value="1" <?= (!isset($editUser['status']) || $editUser['status']==1)?'checked':'' ?>>
<span class="crm-slider"></span>
</label>

</div>

<button type="submit" name="<?= $editUser ? 'update_user':'add_user' ?>" style="width:100%;background:#e91e63;color:#fff;border:none;padding:10px;border-radius:10px;">

<?= $editUser ? 'Update User':'Add User' ?>

</button>

</form>

</div>

</div>

<div class="crm-right">

<div class="crm-card">

<h3>Users List</h3>
<div class="crm-table-wrapper">
<table  id="usersTable" class="crm-table">

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

<a class="crm-btn crm-edit" title="Edit User"

href="index.php?page=user_add&edit=<?= $u['id'] ?>">

<i class="fas fa-pen"></i>

</a>

<?php if ($u['id'] != $loggedInUserId): ?>

<a class="crm-btn crm-delete" title="Delete User"

href="index.php?page=user_add&delete=<?= $u['id'] ?>"

onclick="return confirm('Delete this user?')">

<i class="fas fa-trash"></i>

</a>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>



<script>

/* ==============================
FORM VALIDATION
==============================*/

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
pageLength:3
});

});

</script>
