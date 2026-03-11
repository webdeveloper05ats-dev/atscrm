<?php
requireView('user_add');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

// Only Super Admin (recommended)
if (($_SESSION['role_name'] ?? '') !== 'Super Admin') {
    redirect('index.php');
    exit;
}

$loggedInUserId = (int)($_SESSION['user_id'] ?? 0);

// -------------------------------
// Fetch roles + branches
// -------------------------------
$roles = $pdo->query("SELECT id, role_name FROM roles WHERE status=1 ORDER BY role_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query("SELECT id, branch_name FROM branches WHERE status=1 ORDER BY branch_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------
// Edit mode
// -------------------------------
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

// -------------------------------
// ADD USER
// -------------------------------
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

// -------------------------------
// UPDATE USER
// -------------------------------
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

// -------------------------------
// DELETE USER
// -------------------------------
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

// -------------------------------
// Fetch users
// -------------------------------
$users = $pdo->query("
    SELECT u.*, r.role_name, b.branch_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN branches b ON u.branch_id = b.id
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2 style="margin-bottom:16px;">User Management</h2>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= addslashes($success) ?>',
    confirmButtonColor: '#e91e63'
}).then(() => {
    window.location.href = "index.php?page=user_add";
});
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?= addslashes($error) ?>',
    confirmButtonColor: '#e91e63'
});
</script>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert" style="background:#fff0f0;border:1px solid #ffc1c1;color:#b30000;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<style>
.action-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:6px 10px; border-radius:10px; text-decoration:none; }
.btn-edit { background: var(--primary); color:#fff; }
.btn-edit:hover { background: var(--primary-dark); color:#fff; }
</style>

<div class="crm-row">

    <!-- LEFT: ADD / EDIT FORM -->
    <div class="crm-col-4">

        <div class="card">
            <div class="card-header">
                <?= $editUser ? 'Edit User' : 'Add New User' ?>
            </div>

            <form method="POST">

                <?php if ($editUser): ?>
                    <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($editUser['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Password <?= $editUser ? '(leave blank to keep same)' : '' ?></label>
                    <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role_id" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"
                                <?= (isset($editUser['role_id']) && (int)$editUser['role_id'] === (int)$r['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">All / Not Assigned</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int)$b['id'] ?>"
                                <?= (isset($editUser['branch_id']) && (int)$editUser['branch_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['branch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="1" <?= (!isset($editUser['status']) || (int)$editUser['status'] === 1) ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= (isset($editUser['status']) && (int)$editUser['status'] === 0) ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <?php if ($editUser): ?>
                    <button type="submit" name="update_user" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-save"></i> Update User
                    </button>

                    <a href="index.php?page=user_add" class="btn-danger" style="display:block;text-align:center;margin-top:10px;padding:10px;border-radius:10px;">
                        Cancel
                    </a>
                <?php else: ?>
                    <button type="submit" name="add_user" class="btn btn-primary" style="width:100%;">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                <?php endif; ?>

            </form>
        </div>

    </div>

    <!-- RIGHT: LIST -->
    <div class="crm-col-8">

        <div class="card">
            <div class="card-header">Users List</div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="60">ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th width="70">Status</th>
                            <th width="140">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= (int)$u['id'] ?></td>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['role_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($u['branch_name'] ?? 'All') ?></td>
                                <td style="text-align:center;">
                                    <?= ((int)$u['status'] === 1)
                                        ? '<i class="fas fa-check-circle" style="color:green;"></i>'
                                        : '<i class="fas fa-times-circle" style="color:red;"></i>' ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <a class="action-btn btn-edit" href="index.php?page=user_add&edit=<?= (int)$u['id'] ?>">
                                        <i class="fas fa-pen"></i>
                                    </a>

                                    <?php if ((int)$u['id'] !== $loggedInUserId): ?>
                                        <a class="action-btn btn-danger" href="index.php?page=user_add&delete=<?= (int)$u['id'] ?>"
                                           onclick="return confirm('Delete this user?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="action-btn" title="You" style="background:#fff3cd;border:1px solid #ffe69c;">
                                            <i class="fas fa-user-lock"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (count($users) === 0): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;color:var(--text-light);padding:18px;">
                                    No users found.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>