<?php
// ===============================
// Menu Edit - Full Working (No Bootstrap)
// File: views/menu_edit.php
// ===============================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

// System protected slug (cannot be deleted, cannot be disabled, slug cannot change)
$protectedSlugs = ['menu_management'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid menu id.");
}

// Fetch menu
$stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
$stmt->execute([$id]);
$menu = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$menu) {
    die("Menu not found.");
}

$isProtected = in_array($menu['menu_slug'], $protectedSlugs, true);

// Parent menus for dropdown (only main menus, exclude itself)
$parentMenus = $pdo->prepare("
    SELECT id, menu_name
    FROM menus
    WHERE parent_id IS NULL
      AND id != ?
    ORDER BY sort_order ASC
");
$parentMenus->execute([$id]);
$parentMenus = $parentMenus->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// UPDATE MENU
// ===============================
if (isset($_POST['update_menu'])) {

    $menu_name  = trim($_POST['menu_name'] ?? '');
    $menu_slug  = trim($_POST['menu_slug'] ?? '');
    $parent_id  = ($_POST['parent_id'] ?? '') !== '' ? (int)$_POST['parent_id'] : null;
    $icon       = trim($_POST['icon'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $status     = (int)($_POST['status'] ?? 1);

    // Protected menu rules
    if ($isProtected) {
        // slug must not change
        $menu_slug = $menu['menu_slug'];
        // status must remain active
        $status = 1;
        // parent_id must remain NULL (system menu always main)
        $parent_id = null;
    }

    if ($menu_name === '' || $menu_slug === '') {
        $error = "Menu Name and Menu Slug are required.";
    } else {

        // Check duplicate slug (except current id)
        $chk = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE menu_slug = ? AND id != ?");
        $chk->execute([$menu_slug, $id]);
        if ($chk->fetchColumn() > 0) {
            $error = "This Menu Slug already exists. Please use a unique slug.";
        } else {

            $upd = $pdo->prepare("
                UPDATE menus
                SET menu_name = ?,
                    menu_slug = ?,
                    parent_id = ?,
                    icon = ?,
                    sort_order = ?,
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([
                $menu_name,
                $menu_slug,
                $parent_id,
                $icon,
                $sort_order,
                $status,
                $id
            ]);

            $success = "Menu Updated Successfully!";

            // Refresh menu data
            $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
            $stmt->execute([$id]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);
            $isProtected = in_array($menu['menu_slug'], $protectedSlugs, true);
        }
    }
}
?>

<h2 style="margin-bottom:16px;">Edit Menu</h2>

<?php if ($success): ?>
    <div class="alert" style="background:#e8fff0;border:1px solid #b9f2cf;color:#1f7a3f;">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert" style="background:#fff0f0;border:1px solid #ffc1c1;color:#b30000;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="crm-row">

    <div class="crm-col-12">

        <div class="card">
            <div class="card-header">Update Menu Details</div>

            <?php if ($isProtected): ?>
                <div class="alert" style="background:#fff3cd;border:1px solid #ffe69c;color:#7a5b00;">
                    <i class="fas fa-lock"></i>
                    This is a <strong>System Menu</strong>. Slug/Status/Parent are locked.
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="crm-row">

                    <div class="crm-col-4">
                        <div class="form-group">
                            <label>Menu Name</label>
                            <input type="text" name="menu_name" value="<?= htmlspecialchars($menu['menu_name']) ?>" required>
                        </div>
                    </div>

                    <div class="crm-col-4">
                        <div class="form-group">
                            <label>Menu Slug</label>
                            <input type="text" name="menu_slug"
                                   value="<?= htmlspecialchars($menu['menu_slug']) ?>"
                                   <?= $isProtected ? 'readonly' : '' ?>
                                   required>
                            <small style="color:var(--text-light);">
                                Example: users/user_management
                            </small>
                        </div>
                    </div>

                    <div class="crm-col-4">
                        <div class="form-group">
                            <label>Parent Menu</label>
                            <select name="parent_id" <?= $isProtected ? 'disabled' : '' ?>>
                                <option value="">Main Menu</option>
                                <?php foreach ($parentMenus as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"
                                        <?= ((int)$menu['parent_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['menu_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isProtected): ?>
                                <!-- Keep value posted even if disabled -->
                                <input type="hidden" name="parent_id" value="">
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <div class="crm-row">

                    <div class="crm-col-4">
                        <div class="form-group">
                            <label>Icon Class</label>
                            <input type="text" name="icon" value="<?= htmlspecialchars($menu['icon'] ?? '') ?>" placeholder="fas fa-users">
                            <small style="color:var(--text-light);">
                                Preview:
                                <i class="<?= htmlspecialchars($menu['icon'] ?: 'fas fa-circle') ?>"></i>
                            </small>
                        </div>
                    </div>

                    <div class="crm-col-4">
                        <div class="form-group">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" value="<?= (int)$menu['sort_order'] ?>" min="0">
                        </div>
                    </div>

                    <div class="crm-col-4">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" <?= $isProtected ? 'disabled' : '' ?>>
                                <option value="1" <?= ((int)$menu['status'] === 1) ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= ((int)$menu['status'] === 0) ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <?php if ($isProtected): ?>
                                <input type="hidden" name="status" value="1">
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button type="submit" name="update_menu" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>

                    <a href="index.php?page=menu_management" class="btn-danger" style="text-decoration:none;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>

            </form>
        </div>

    </div>

</div>