<?php
requireView('menu_management');
// ===============================
// Menu Management - Full Working (No Bootstrap)
// ===============================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

// ===============================
// SYSTEM PROTECTED MENU SLUGS
// ===============================
$protectedSlugs = ['menu_management']; // cannot be deleted

// ===============================
// ADD MENU
// ===============================
if (isset($_POST['add_menu'])) {

    $menu_name  = trim($_POST['menu_name'] ?? '');
    $menu_slug  = trim($_POST['menu_slug'] ?? '');
    $parent_id  = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;
    $icon       = trim($_POST['icon'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $status     = (int)($_POST['status'] ?? 1);

    // Basic validation
    if ($menu_name === '' || $menu_slug === '') {
        $error = "Menu Name and Menu Slug are required.";
    } else {

        // Prevent duplicate slug
        $chk = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_slug = ?");
        $chk->execute([$menu_slug]);
        if ($chk->fetchColumn() > 0) {
            $error = "This Menu Slug already exists. Please use a unique slug.";
        } else {

            // Insert into menus
            $stmt = $pdo->prepare("
                INSERT INTO menus
                (menu_name, menu_slug, parent_id, icon, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$menu_name, $menu_slug, $parent_id, $icon, $sort_order, $status]);

            $newMenuId = (int)$pdo->lastInsertId();

            // Give full permissions to Super Admin (role_id = 1)
            $stmtPerm = $pdo->prepare("
                INSERT INTO role_permissions
                (role_id, menu_id, can_view, can_add, can_edit, can_delete, created_at, updated_at)
                VALUES (1, ?, 1, 1, 1, 1, NOW(), NOW())
            ");
            $stmtPerm->execute([$newMenuId]);

            $success = "Menu Added Successfully!";
        }
    }
}

// ===============================
// DELETE MENU (Protected Slug Rule)
// ===============================
if (isset($_GET['delete'])) {

    $deleteId = (int)$_GET['delete'];

    // Find slug for this menu
    $stmt = $pdo->prepare("SELECT menu_slug FROM menus WHERE id = ?");
    $stmt->execute([$deleteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $error = "Menu not found.";
    } else {

        $slug = $row['menu_slug'];

        if (in_array($slug, $protectedSlugs, true)) {
            $error = "This is a System Menu. You cannot delete it.";
        } else {

            // Delete permissions first
            $pdo->prepare("DELETE FROM role_permissions WHERE menu_id=?")
                ->execute([$deleteId]);

            // Delete menu
            $pdo->prepare("DELETE FROM menus WHERE id=?")
                ->execute([$deleteId]);

            $success = "Menu Deleted Successfully!";
        }
    }
}

// ===============================
// FETCH MENUS
// ===============================
$stmt = $pdo->query("
    SELECT m.*, p.menu_name AS parent_name
    FROM menus m
    LEFT JOIN menus p ON m.parent_id = p.id
    ORDER BY m.parent_id IS NOT NULL, m.sort_order ASC
");
$menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parent menus for dropdown (only main)
$parentMenus = $pdo->query("
    SELECT id, menu_name
    FROM menus
    WHERE parent_id IS NULL
    ORDER BY sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h2 style="margin-bottom:16px;">Menu Management</h2>

<?php if ($success): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= addslashes($success) ?>',
    confirmButtonColor: '#e91e63'
}).then(() => {
    window.location.href = "index.php?page=menu_management";
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

<div class="crm-row">

    <!-- LEFT SIDE - ADD MENU -->
    <div class="crm-col-4">

        <div class="card">
            <div class="card-header">Add New Menu</div>

            <form method="POST">

                <div class="form-group">
                    <label>Menu Name</label>
                    <input type="text" name="menu_name" required>
                </div>

                <div class="form-group">
                    <label>Menu Slug</label>
                    <input type="text" name="menu_slug" placeholder="ex: users/user_management" required>
                </div>

                <div class="form-group">
                    <label>Parent Menu</label>
                    <select name="parent_id">
                        <option value="">Main Menu</option>
                        <?php foreach ($parentMenus as $parent): ?>
                            <option value="<?= (int)$parent['id'] ?>">
                                <?= htmlspecialchars($parent['menu_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Icon</label>
                    <input type="text" name="icon" placeholder="fas fa-users">
                </div>

                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="1" min="0">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <button type="submit" name="add_menu" class="btn btn-primary" style="width:100%;">
                    Add Menu
                </button>

            </form>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header">Icon Examples</div>
            <p style="color:var(--text-light); line-height:1.6;">
                fas fa-users<br>
                fas fa-user-shield<br>
                fas fa-briefcase<br>
                fas fa-chart-line<br>
                fas fa-money-bill-wave<br>
                fas fa-file-alt
            </p>
        </div>

    </div>

    <!-- RIGHT SIDE - MENU LIST -->
    <div class="crm-col-8">

        <div class="card">
            <div class="card-header">Existing Menus</div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="60">ID</th>
                            <th>Menu</th>
                            <th>Slug</th>
                            <th>Parent</th>
                            <th width="80">Status</th>
                            <th width="140">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
						$i=1;
						foreach ($menus as $menu): ?>
                            <?php
                                $isProtected = in_array($menu['menu_slug'], $protectedSlugs, true);
                            ?>
                            <tr>
                                <td><?= $i ?></td>

                                <td>
                                    <i class="<?= htmlspecialchars($menu['icon'] ?: 'fas fa-circle') ?>"></i>
                                    <?= htmlspecialchars($menu['menu_name']) ?>
                                </td>

                                <td><?= htmlspecialchars($menu['menu_slug']) ?></td>
                                <td><?= htmlspecialchars($menu['parent_name'] ?? 'Main') ?></td>

                                <td style="text-align:center;">
                                    <?php if ((int)$menu['status'] === 1): ?>
                                        <i class="fas fa-check-circle" style="color:green;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle" style="color:red;"></i>
                                    <?php endif; ?>
                                </td>

                                <td style="white-space:nowrap;">
                                    <?php if ($isProtected): ?>
                                        <span title="System Protected" style="display:inline-block;padding:6px 10px;border-radius:8px;background:#fff3cd;border:1px solid #ffe69c;">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                    <?php else: ?>
                                        <a href="index.php?page=menu_edit&id=<?= (int)$menu['id'] ?>"
                                           class="btn btn-primary"
                                           style="padding:6px 10px; text-decoration:none;">
                                            <i class="fas fa-pen"></i>
                                        </a>

                                        <a href="index.php?page=menu_management&delete=<?= (int)$menu['id'] ?>"
                                           class="btn-danger"
                                           style="padding:6px 10px; margin-left:6px;"
                                           onclick="return confirm('Delete this menu?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
						$i++;
						endforeach; 
						?>

                        <?php if (count($menus) === 0): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--text-light); padding:20px;">
                                    No menus found.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </div>

    </div>

</div>