<?php
// =====================================
// Permission Management (RBAC) - AJAX (Stable)
// File: views/permission_management.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// Page protection (if you use it)
if (function_exists('requireView')) {
    requireView('permission_management');
}

// ------------------------------
// Fetch Roles
// ------------------------------
$roles = $pdo->query("SELECT id, role_name FROM roles WHERE status=1 ORDER BY id ASC")
             ->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------
// Fetch Menus (All active)
// ------------------------------
$allMenus = $pdo->query("
    SELECT id, menu_name, menu_slug, parent_id, sort_order
    FROM menus
    WHERE status=1
    ORDER BY parent_id IS NOT NULL, parent_id ASC, sort_order ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Build Tree
$menuTree = [];
foreach ($allMenus as $m) {
    if ($m['parent_id'] === null) {
        $menuTree[$m['id']] = $m;
        $menuTree[$m['id']]['children'] = [];
    }
}
foreach ($allMenus as $m) {
    if ($m['parent_id'] !== null && isset($menuTree[$m['parent_id']])) {
        $menuTree[$m['parent_id']]['children'][] = $m;
    }
}

// ------------------------------
// AJAX: Fetch permissions for selected role
// GET: ?page=permission_management&ajax=perms&role_id=2
// ------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'perms') {
    header('Content-Type: application/json; charset=utf-8');

    $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : 0;

    if ($roleId <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Invalid role.']);
        exit;
    }

    // Super Admin always full access (role_id = 1)
    if ($roleId === 1) {
        $perms = [];
        foreach ($allMenus as $m) {
            $mid = (int)$m['id'];
            $perms[$mid] = ['view'=>1,'add'=>1,'edit'=>1,'delete'=>1];
        }
        echo json_encode(['ok' => true, 'superadmin' => true, 'perms' => $perms]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT menu_id, can_view, can_add, can_edit, can_delete
        FROM role_permissions
        WHERE role_id = ?
    ");
    $stmt->execute([$roleId]);

    $perms = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $mid = (int)$p['menu_id'];
        $perms[$mid] = [
            'view'   => (int)$p['can_view'],
            'add'    => (int)$p['can_add'],
            'edit'   => (int)$p['can_edit'],
            'delete' => (int)$p['can_delete'],
        ];
    }

    echo json_encode(['ok' => true, 'superadmin' => false, 'perms' => $perms]);
    exit;
}

// ------------------------------
// AJAX: Save permissions
// POST: ajax_save=1 + role_id + perm[menu_id][view/add/edit/delete]
// ------------------------------
if (isset($_POST['ajax_save']) && $_POST['ajax_save'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $role_id = (int)($_POST['role_id'] ?? 0);

    if ($role_id <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Please select a role.']);
        exit;
    }

    if ($role_id === 1) {
        echo json_encode(['ok' => false, 'message' => 'Super Admin permissions cannot be restricted.']);
        exit;
    }

    $perm = $_POST['perm'] ?? [];

    try {
        $pdo->beginTransaction();

        // Remove old permissions
        $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?");
        $del->execute([$role_id]);

        // Insert new permissions
        $ins = $pdo->prepare("
            INSERT INTO role_permissions
            (role_id, menu_id, can_view, can_add, can_edit, can_delete, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        // Loop all menus so missing checkbox becomes 0
        foreach ($allMenus as $m) {
            $menu_id = (int)$m['id'];

            $can_view   = isset($perm[$menu_id]['view']) ? 1 : 0;
            $can_add    = isset($perm[$menu_id]['add']) ? 1 : 0;
            $can_edit   = isset($perm[$menu_id]['edit']) ? 1 : 0;
            $can_delete = isset($perm[$menu_id]['delete']) ? 1 : 0;

            // Rule: if view OFF -> everything OFF
            if ($can_view === 0) {
                $can_add = $can_edit = $can_delete = 0;
            }

            $ins->execute([$role_id, $menu_id, $can_view, $can_add, $can_edit, $can_delete]);
        }

        $pdo->commit();

        echo json_encode(['ok' => true, 'message' => 'Permissions updated successfully!']);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'message' => 'Failed to save permissions. ' . $e->getMessage()]);
        exit;
    }
}
?>

<h2 style="margin-bottom:16px;">Permission Management</h2>

<div class="card">
    <div class="card-header">Select Role</div>

    <div style="padding:14px;">
        <div class="form-group">
            <label>Role</label>
            <select id="role_select">
                <option value="">-- Select Role --</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>">
                        <?= htmlspecialchars($r['role_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="perm_loading_note" style="display:none;margin-top:10px;color:var(--text-light);">
            Loading permissions...
        </div>
    </div>
</div>

<!-- Super Admin info -->
<div class="card" id="superadmin_card" style="display:none;">
    <div class="card-header">Super Admin</div>
    <p style="padding:14px;color:var(--text-light);line-height:1.6;">
        Super Admin always has full permissions. You cannot edit Super Admin permissions.
    </p>
</div>

<!-- Permissions table -->
<div class="card" id="perm_card" style="display:none;">
    <div class="card-header">Set Permissions</div>

    <form id="perm_form" method="POST" style="padding:14px;">
        <input type="hidden" name="role_id" id="role_id_hidden" value="">
        <input type="hidden" name="ajax_save" value="1">

       <div class="permission-actions">
<button class="btn btn-primary">View All</button>
<button class="btn btn-primary">Add All</button>
<button class="btn btn-primary">Edit All</button>
<button class="btn btn-primary">Delete All</button>
<button class="btn">Clear All</button>
</div>

        <div class="perm-table-wrap">
            <div class="table-responsive">
                <table class="table perm-table">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th class="perm-center">View</th>
                            <th class="perm-center">Add</th>
                            <th class="perm-center">Edit</th>
                            <th class="perm-center">Delete</th>
                        </tr>
                    </thead>
                    <tbody>

                    <?php foreach ($menuTree as $parent): ?>
                        <?php $pid = (int)$parent['id']; ?>
                        <tr class="perm-parent-row">
                            <td>
                                <div class="perm-menu-name"><?= htmlspecialchars($parent['menu_name']) ?></div>
                                <div class="perm-menu-slug"><?= htmlspecialchars($parent['menu_slug']) ?></div>
                            </td>

                            <td class="perm-center">
                                <label class="perm-switch">
                                    <input type="checkbox" class="perm-view"
                                           name="perm[<?= $pid ?>][view]"
                                           onchange="enforceViewRule(<?= $pid ?>)">
                                </label>
                            </td>
                            <td class="perm-center">
                                <label class="perm-switch">
                                    <input type="checkbox" class="perm-add"
                                           name="perm[<?= $pid ?>][add]"
                                           onchange="enforceViewRule(<?= $pid ?>)">
                                </label>
                            </td>
                            <td class="perm-center">
                                <label class="perm-switch">
                                    <input type="checkbox" class="perm-edit"
                                           name="perm[<?= $pid ?>][edit]"
                                           onchange="enforceViewRule(<?= $pid ?>)">
                                </label>
                            </td>
                            <td class="perm-center">
                                <label class="perm-switch">
                                    <input type="checkbox" class="perm-delete"
                                           name="perm[<?= $pid ?>][delete]"
                                           onchange="enforceViewRule(<?= $pid ?>)">
                                </label>
                            </td>
                        </tr>

                        <?php if (!empty($parent['children'])): ?>
                            <?php foreach ($parent['children'] as $child): ?>
                                <?php $cid = (int)$child['id']; ?>
                                <tr>
                                    <td class="perm-child">
                                        <div class="perm-menu-name"><?= htmlspecialchars($child['menu_name']) ?></div>
                                        <div class="perm-menu-slug"><?= htmlspecialchars($child['menu_slug']) ?></div>
                                    </td>

                                    <td class="perm-center">
                                        <label class="perm-switch">
                                            <input type="checkbox" class="perm-view"
                                                   name="perm[<?= $cid ?>][view]"
                                                   onchange="enforceViewRule(<?= $cid ?>)">
                                        </label>
                                    </td>
                                    <td class="perm-center">
                                        <label class="perm-switch">
                                            <input type="checkbox" class="perm-add"
                                                   name="perm[<?= $cid ?>][add]"
                                                   onchange="enforceViewRule(<?= $cid ?>)">
                                        </label>
                                    </td>
                                    <td class="perm-center">
                                        <label class="perm-switch">
                                            <input type="checkbox" class="perm-edit"
                                                   name="perm[<?= $cid ?>][edit]"
                                                   onchange="enforceViewRule(<?= $cid ?>)">
                                        </label>
                                    </td>
                                    <td class="perm-center">
                                        <label class="perm-switch">
                                            <input type="checkbox" class="perm-delete"
                                                   name="perm[<?= $cid ?>][delete]"
                                                   onchange="enforceViewRule(<?= $cid ?>)">
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    <?php endforeach; ?>

                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top:14px;">
            <button type="submit" class="btn btn-primary" style="width:220px;">
                Save Permissions
            </button>
        </div>

    </form>
</div>

<script>
const roleSelect = document.getElementById('role_select');
const permCard = document.getElementById('perm_card');
const superAdminCard = document.getElementById('superadmin_card');
const roleHidden = document.getElementById('role_id_hidden');
const permForm = document.getElementById('perm_form');
const loadingNote = document.getElementById('perm_loading_note');

function swalMsg(icon, title, text) {
    if (typeof Swal === "undefined") { alert(title + ": " + text); return; }
    Swal.fire({ icon, title, text, confirmButtonColor:'#e91e63' });
}

function clearAllChecks() {
    document.querySelectorAll(".perm-view,.perm-add,.perm-edit,.perm-delete")
        .forEach(cb => cb.checked = false);
}

function setPerms(permsObj) {
    clearAllChecks();

    for (const mid in permsObj) {
        const p = permsObj[mid];

        const v = document.querySelector(`input[name="perm[${mid}][view]"]`);
        const a = document.querySelector(`input[name="perm[${mid}][add]"]`);
        const e = document.querySelector(`input[name="perm[${mid}][edit]"]`);
        const d = document.querySelector(`input[name="perm[${mid}][delete]"]`);

        if (v) v.checked = (parseInt(p.view) === 1);
        if (a) a.checked = (parseInt(p.add) === 1);
        if (e) e.checked = (parseInt(p.edit) === 1);
        if (d) d.checked = (parseInt(p.delete) === 1);
    }
}

async function loadRolePerms(roleId) {
    // reset UI
    permCard.style.display = "none";
    superAdminCard.style.display = "none";
    clearAllChecks();
    roleHidden.value = "";

    if (!roleId) {
        if (loadingNote) loadingNote.style.display = "none";
        return;
    }

    if (loadingNote) loadingNote.style.display = "block";

    try {
        const url = `index.php?page=permission_management&ajax=perms&role_id=${encodeURIComponent(roleId)}`;
        const res = await fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" }});
        const data = await res.json();

        if (loadingNote) loadingNote.style.display = "none";

        if (!data.ok) {
            swalMsg('error', 'Error', data.message || 'Failed to load permissions');
            return;
        }

        roleHidden.value = roleId;

        if (data.superadmin) {
            superAdminCard.style.display = "block";
            permCard.style.display = "none";
            setPerms(data.perms || {});
        } else {
            superAdminCard.style.display = "none";
            permCard.style.display = "block";
            setPerms(data.perms || {});
        }

    } catch (e) {
        if (loadingNote) loadingNote.style.display = "none";
        swalMsg('error', 'Error', 'Network/JSON error while loading permissions');
        console.error(e);
    }
}

// Toggle buttons
function toggleAll(type, state) {
    if (state === false) {
        clearAllChecks();
        return;
    }

    document.querySelectorAll(".perm-" + type).forEach(cb => cb.checked = true);

    if (type !== "view") {
        document.querySelectorAll(".perm-view").forEach(cb => cb.checked = true);
    }
}

// View rule: if view unchecked => add/edit/delete must be unchecked
function enforceViewRule(menuId) {
    const view = document.querySelector(`input[name="perm[${menuId}][view]"]`);
    const add  = document.querySelector(`input[name="perm[${menuId}][add]"]`);
    const edit = document.querySelector(`input[name="perm[${menuId}][edit]"]`);
    const del  = document.querySelector(`input[name="perm[${menuId}][delete]"]`);

    if (!view) return;

    if (view.checked === false) {
        if (add) add.checked = false;
        if (edit) edit.checked = false;
        if (del) del.checked = false;
    } else {
        if (add && add.checked) view.checked = true;
        if (edit && edit.checked) view.checked = true;
        if (del && del.checked) view.checked = true;
    }
}

roleSelect.addEventListener('change', () => {
    loadRolePerms(roleSelect.value);
});

// Save (AJAX)
permForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const roleId = roleHidden.value;
    if (!roleId) {
        swalMsg('error', 'Error', 'Please select a role first.');
        return;
    }

    const fd = new FormData(permForm);

    try {
        const res = await fetch(`index.php?page=permission_management`, {
            method: 'POST',
            body: fd,
            headers: { "X-Requested-With": "XMLHttpRequest" }
        });
        const data = await res.json();

        if (!data.ok) {
            swalMsg('error', 'Error', data.message || 'Save failed');
            return;
        }

        if (typeof Swal !== "undefined") {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: data.message || 'Permissions updated successfully!',
                confirmButtonColor:'#e91e63'
            }).then(() => {
                loadRolePerms(roleId);
            });
        } else {
            alert('Saved!');
            loadRolePerms(roleId);
        }

    } catch (err) {
        swalMsg('error', 'Error', 'Network/JSON error while saving');
        console.error(err);
    }
});
</script>