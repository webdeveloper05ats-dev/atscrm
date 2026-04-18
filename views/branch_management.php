<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

requireView('branch_management');

$menuSlug = 'branch_management';
$canAddBranch = canAdd($menuSlug);
$canEditBranch = canEdit($menuSlug);
$canDeleteBranch = canDelete($menuSlug);

$flashSuccess = getFlash('branch_success');
$flashError = getFlash('branch_error');

$error = '';
$success = '';
if ($flashSuccess) {
    $success = (string)$flashSuccess;
}
if ($flashError) {
    $error = (string)$flashError;
}

$editId = (int)($_GET['edit'] ?? 0);
$editBranch = null;

if ($editId > 0) {
    $stEdit = $pdo->prepare("SELECT * FROM branches WHERE id = ? LIMIT 1");
    $stEdit->execute([$editId]);
    $editBranch = $stEdit->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$editBranch) {
        $editId = 0;
        $error = 'Selected branch was not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!verifyCSRF($csrfToken)) {
        $error = 'Invalid session token. Please refresh and try again.';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));

        if ($action === 'add') {
            if (!$canAddBranch) {
                $error = 'You do not have permission to add branches.';
            } else {
                $branchName = trim((string)($_POST['branch_name'] ?? ''));
                $location = trim((string)($_POST['location'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $status = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;

                if ($branchName === '') {
                    $error = 'Branch name is required.';
                } elseif (mb_strlen($branchName) > 120) {
                    $error = 'Branch name is too long.';
                } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
                    $error = 'Phone format is invalid.';
                } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Email format is invalid.';
                } else {
                    $stChk = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE LOWER(branch_name) = LOWER(?)");
                    $stChk->execute([$branchName]);
                    if ((int)$stChk->fetchColumn() > 0) {
                        $error = 'A branch with this name already exists.';
                    } else {
                        $stIns = $pdo->prepare("
                            INSERT INTO branches (branch_name, location, phone, email, status, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        $stIns->execute([
                            $branchName,
                            $location !== '' ? $location : null,
                            $phone !== '' ? $phone : null,
                            $email !== '' ? $email : null,
                            $status
                        ]);
                        setFlash('branch_success', 'Branch added successfully.');
                        redirect('index.php?page=branch_management');
                    }
                }
            }
        } elseif ($action === 'update') {
            if (!$canEditBranch) {
                $error = 'You do not have permission to edit branches.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $branchName = trim((string)($_POST['branch_name'] ?? ''));
                $location = trim((string)($_POST['location'] ?? ''));
                $phone = trim((string)($_POST['phone'] ?? ''));
                $email = trim((string)($_POST['email'] ?? ''));
                $status = (int)($_POST['status'] ?? 1) === 1 ? 1 : 0;

                if ($id <= 0) {
                    $error = 'Invalid branch selected for update.';
                } elseif ($branchName === '') {
                    $error = 'Branch name is required.';
                } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
                    $error = 'Phone format is invalid.';
                } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Email format is invalid.';
                } else {
                    $stChk = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE LOWER(branch_name) = LOWER(?) AND id <> ?");
                    $stChk->execute([$branchName, $id]);
                    if ((int)$stChk->fetchColumn() > 0) {
                        $error = 'A branch with this name already exists.';
                    } else {
                        $stUpd = $pdo->prepare("
                            UPDATE branches
                            SET branch_name = ?, location = ?, phone = ?, email = ?, status = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stUpd->execute([
                            $branchName,
                            $location !== '' ? $location : null,
                            $phone !== '' ? $phone : null,
                            $email !== '' ? $email : null,
                            $status,
                            $id
                        ]);
                        setFlash('branch_success', 'Branch updated successfully.');
                        redirect('index.php?page=branch_management');
                    }
                }
            }
        } elseif ($action === 'delete') {
            if (!$canDeleteBranch) {
                $error = 'You do not have permission to delete branches.';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $sessionBranchId = (int)($_SESSION['branch_id'] ?? 0);

                if ($id <= 0) {
                    $error = 'Invalid branch selected for deletion.';
                } elseif ($id === 1) {
                    $error = 'Main Branch cannot be deleted.';
                } elseif ($sessionBranchId > 0 && $id === $sessionBranchId) {
                    $error = 'You cannot delete your current working branch.';
                } else {
                    $references = [];
                    $refTables = [
                        ['users', 'Users'],
                        ['enquiries', 'Enquiries'],
                        ['leads', 'Leads'],
                        ['registrations', 'Registrations'],
                        ['registration_payments', 'Payments'],
                        ['enquiry_followups', 'Followups'],
                        ['monthly_targets', 'Monthly Targets'],
                        ['monthly_target_results', 'Target Results'],
                    ];
                    foreach ($refTables as $pair) {
                        $table = $pair[0];
                        $label = $pair[1];
                        if (function_exists('crmTableExists') && crmTableExists($pdo, $table)) {
                            $stCount = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE branch_id = ?");
                            $stCount->execute([$id]);
                            $count = (int)$stCount->fetchColumn();
                            if ($count > 0) {
                                $references[] = $label . ': ' . $count;
                            }
                        }
                    }

                    if (!empty($references)) {
                        $error = 'Cannot delete branch because related records exist (' . implode(', ', $references) . ').';
                    } else {
                        $stDel = $pdo->prepare("DELETE FROM branches WHERE id = ?");
                        $stDel->execute([$id]);
                        setFlash('branch_success', 'Branch deleted successfully.');
                        redirect('index.php?page=branch_management');
                    }
                }
            }
        }
    }
}

$branches = $pdo->query("SELECT * FROM branches ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$csrf = generateCSRF();
?>

<?php if ($success): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success',
  text: <?= json_encode($success) ?>,
  confirmButtonColor: '#e91e63'
});
</script>
<?php endif; ?>

<?php if ($error): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
Swal.fire({
  icon: 'error',
  title: 'Error',
  text: <?= json_encode($error) ?>,
  confirmButtonColor: '#e91e63'
});
</script>
<?php endif; ?>

<div class="bm-page-head">
  <h2 class="page-title">Branch Management</h2>
  <div class="bm-total-badge">
    <i class="fas fa-code-branch"></i>
    Total Branches: <?= (int)count($branches) ?>
  </div>
</div>

<div class="bm-page">
  <section class="bm-form-card">
    <div class="bm-card-head">
      <h3><?= $editBranch ? 'Edit Branch' : 'Add New Branch' ?></h3>
      <?php if ($editBranch): ?>
      <a href="index.php?page=branch_management" class="bm-link-reset">Cancel Edit</a>
      <?php endif; ?>
    </div>

    <form method="POST" class="bm-form" data-focus-start="on" data-focus-target="input[name='branch_name']" data-form-assist="on">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="action" value="<?= $editBranch ? 'update' : 'add' ?>">
      <?php if ($editBranch): ?>
      <input type="hidden" name="id" value="<?= (int)$editBranch['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label>Branch Name <span class="bm-required">*</span></label>
        <input type="text" name="branch_name" maxlength="120" required
               placeholder="e.g. Chennai Main Branch"
               value="<?= htmlspecialchars($editBranch['branch_name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label>Location</label>
        <input type="text" name="location" maxlength="255"
               placeholder="e.g. T Nagar, Chennai"
               value="<?= htmlspecialchars($editBranch['location'] ?? '') ?>">
      </div>

      <div class="bm-grid-2">
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" maxlength="20"
                 placeholder="e.g. +91 9876543210"
                 value="<?= htmlspecialchars($editBranch['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" maxlength="160"
                 placeholder="e.g. branch@company.com"
                 value="<?= htmlspecialchars($editBranch['email'] ?? '') ?>">
        </div>
      </div>

      <?php $statusVal = isset($editBranch['status']) ? (int)$editBranch['status'] : 1; ?>
      <div class="form-group">
        <label>Status</label>
        <input type="hidden" name="status" id="bmStatusInput" value="<?= $statusVal === 1 ? 1 : 0 ?>">
        <div class="bm-segment" role="tablist" aria-label="Branch status">
          <button type="button" class="bm-segment-btn<?= $statusVal === 1 ? ' active' : '' ?>" data-value="1">Active</button>
          <button type="button" class="bm-segment-btn<?= $statusVal !== 1 ? ' active' : '' ?>" data-value="0">Inactive</button>
        </div>
      </div>

      <div class="bm-actions">
        <button type="submit" class="btn btn-primary" <?= (!$editBranch && !$canAddBranch) || ($editBranch && !$canEditBranch) ? 'disabled' : '' ?>>
          <i class="fas fa-save"></i>
          <?= $editBranch ? 'Update Branch' : 'Add Branch' ?>
        </button>
      </div>
    </form>
  </section>

  <section class="bm-table-card">
    <div class="bm-card-head">
      <h3>Branch List</h3>
    </div>
    <div class="bm-table-wrap">
      <table class="bm-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Branch</th>
            <th>Location</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($branches)): ?>
          <tr>
            <td colspan="7" class="bm-empty">No branches found.</td>
          </tr>
          <?php else: ?>
            <?php foreach ($branches as $idx => $b): ?>
            <tr>
              <td><?= (int)($idx + 1) ?></td>
              <td>
                <strong><?= htmlspecialchars((string)$b['branch_name']) ?></strong>
                <?php if ((int)$b['id'] === 1): ?>
                <span class="bm-tag">Main</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)($b['location'] ?? '-')) ?></td>
              <td><?= htmlspecialchars((string)($b['phone'] ?? '-')) ?></td>
              <td><?= htmlspecialchars((string)($b['email'] ?? '-')) ?></td>
              <td>
                <?php if ((int)$b['status'] === 1): ?>
                <span class="bm-pill is-active">Active</span>
                <?php else: ?>
                <span class="bm-pill is-inactive">Inactive</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="bm-row-actions">
                  <?php if ($canEditBranch): ?>
                  <a class="bm-icon-btn edit" href="index.php?page=branch_management&edit=<?= (int)$b['id'] ?>" title="Edit">
                    <i class="fas fa-pen"></i>
                  </a>
                  <?php else: ?>
                  <button type="button" class="bm-icon-btn edit" disabled title="No edit permission">
                    <i class="fas fa-pen"></i>
                  </button>
                  <?php endif; ?>
                  <form method="POST" onsubmit="return confirm('Delete this branch?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <button type="submit" class="bm-icon-btn delete"
                            title="Delete"
                            <?= (!$canDeleteBranch || (int)$b['id'] === 1) ? 'disabled' : '' ?>>
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<script>
(function(){
  var statusInput = document.getElementById('bmStatusInput');
  var segBtns = document.querySelectorAll('.bm-segment-btn');
  if (!statusInput || !segBtns.length) return;
  segBtns.forEach(function(btn){
    btn.addEventListener('click', function(){
      var val = btn.getAttribute('data-value') === '1' ? '1' : '0';
      statusInput.value = val;
      segBtns.forEach(function(x){ x.classList.remove('active'); });
      btn.classList.add('active');
    });
  });
})();
</script>
