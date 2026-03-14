<?php
if (!hasPermission('roles/permissions', 'view')) {
    die("Access Denied");
}

// Fetch all roles
$roles = $pdo->query("SELECT * FROM roles WHERE status=1")->fetchAll(PDO::FETCH_ASSOC);

// Get selected role
$selectedRoleId = $_GET['role_id'] ?? $roles[0]['id'] ?? null;

// Fetch all menus
$menus = $pdo->query("SELECT * FROM menus WHERE status=1 ORDER BY parent_id, sort_order")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing permissions
$permissions = [];
if ($selectedRoleId) {
    $stmt = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id=?");
    $stmt->execute([$selectedRoleId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $perm) {
        $permissions[$perm['menu_id']] = $perm;
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $roleId = $_POST['role_id'];

    // Remove old permissions
    $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$roleId]);

    if (!empty($_POST['perm'])) {
        foreach ($_POST['perm'] as $menuId => $actions) {

            $pdo->prepare("
                INSERT INTO role_permissions 
                (role_id, menu_id, can_view, can_add, can_edit, can_delete)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $roleId,
                $menuId,
                isset($actions['view']) ? 1 : 0,
                isset($actions['add']) ? 1 : 0,
                isset($actions['edit']) ? 1 : 0,
                isset($actions['delete']) ? 1 : 0
            ]);
        }
    }

    setFlash('success', 'Permissions updated successfully.');
    redirect('index.php?page=roles/permissions&role_id=' . urlencode((string) $roleId));
}
?>

<div class="content">
<div class="main-content">

<h2>Role Permission Management</h2>

<form method="GET">
    <label>Select Role:</label>
    <select name="role_id" onchange="this.form.submit()">
        <?php foreach ($roles as $role): ?>
            <option value="<?= $role['id'] ?>" <?= ($role['id']==$selectedRoleId)?'selected':'' ?>>
                <?= htmlspecialchars($role['role_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<hr>

<form method="POST">
<input type="hidden" name="role_id" value="<?= $selectedRoleId ?>">

<table border="1" cellpadding="8" cellspacing="0" width="100%">
    <tr style="background:#f2f2f2;">
        <th>Menu</th>
        <th>View</th>
        <th>Add</th>
        <th>Edit</th>
        <th>Delete</th>
    </tr>

    <?php foreach ($menus as $menu): ?>
        <?php
            $perm = $permissions[$menu['id']] ?? [];
        ?>
        <tr>
            <td><?= htmlspecialchars($menu['menu_name']) ?></td>

            <td align="center">
                <input type="checkbox" name="perm[<?= $menu['id'] ?>][view]"
                    <?= isset($perm['can_view']) && $perm['can_view'] ? 'checked' : '' ?>>
            </td>

            <td align="center">
                <input type="checkbox" name="perm[<?= $menu['id'] ?>][add]"
                    <?= isset($perm['can_add']) && $perm['can_add'] ? 'checked' : '' ?>>
            </td>

            <td align="center">
                <input type="checkbox" name="perm[<?= $menu['id'] ?>][edit]"
                    <?= isset($perm['can_edit']) && $perm['can_edit'] ? 'checked' : '' ?>>
            </td>

            <td align="center">
                <input type="checkbox" name="perm[<?= $menu['id'] ?>][delete]"
                    <?= isset($perm['can_delete']) && $perm['can_delete'] ? 'checked' : '' ?>>
            </td>

        </tr>
    <?php endforeach; ?>

</table>

<br>
<button type="submit" class="btn btn-primary">Save Permissions</button>

</form>

</div>
</div>
