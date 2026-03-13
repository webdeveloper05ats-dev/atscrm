<?php
// =====================================
// Enquiries - List (with Edit/Delete)
// Slug: enquiries/list
// File: views/enquiries/list.php
// =====================================

requireView('enquiries/list');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

// Session scope
$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$roleName = $_SESSION['role_name'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// Branch access (roles.can_access_all_branches)
$canAllBranches = 0;
try {
    $r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $r->execute([$roleId]);
    $canAllBranches = (int)($r->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

// --------------------------
// DELETE (POST + CSRF)
// --------------------------
// ✅ IMPORTANT FIX: we check delete_enquiry from hidden input also
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_enquiry'])) {

    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF). Please refresh and try again.";
    } else {

        $delId = (int)($_POST['id'] ?? 0);

        if ($delId <= 0) {
            $error = "Invalid enquiry selected.";
        } else {
            try {
                // Branch scope check (if not all branches)
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM enquiries WHERE id=? AND branch_id=?");
                    $chk->execute([$delId, $branchId]);
                    if ((int)$chk->fetchColumn() === 0) {
                        throw new Exception("You cannot delete this enquiry (branch restriction).");
                    }
                }

                $st = $pdo->prepare("DELETE FROM enquiries WHERE id=?");
                $st->execute([$delId]);

                $success = "Enquiry deleted successfully!";
            } catch (Exception $e) {
                $error = "Delete failed. " . $e->getMessage();
            }
        }
    }
}

// --------------------------
// Filters
// --------------------------
$q       = trim($_GET['q'] ?? '');
$status  = trim($_GET['status'] ?? '');
$handled = (int)($_GET['handled_by'] ?? 0);
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');

// Pagination
$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;

$perPage = 10;
$offset  = ($page - 1) * $perPage;

// --------------------------
// Handled-by dropdown users (Front Office)
// --------------------------
$frontOfficeUsers = [];
try {
    $st = $pdo->prepare("
        SELECT u.id, u.name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.status=1 AND r.role_name='Front Office'
        ORDER BY u.name ASC
    ");
    $st->execute();
    $frontOfficeUsers = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $frontOfficeUsers = [];
}

// --------------------------
// Build WHERE dynamically
// --------------------------
$where  = [];
$params = [];

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[]  = "e.branch_id = ?";
    $params[] = $branchId;
}

if ($status !== '') {
    $where[]  = "e.status = ?";
    $params[] = $status;
}

// If user is Front Office, show only their enquiries
if ($roleName === 'Front Office') {

    $where[]  = "e.handled_by = ?";
    $params[] = $userId;

} else {

    // For Admin / other roles allow filter dropdown
    if ($handled > 0) {
        $where[]  = "e.handled_by = ?";
        $params[] = $handled;
    }

}
// Date range (enquiry_date preferred, else created_at)
if ($from !== '') {
    $where[]  = "(DATE(e.enquiry_date) >= ? OR (e.enquiry_date IS NULL AND DATE(e.created_at) >= ?))";
    $params[] = $from;
    $params[] = $from;
}
if ($to !== '') {
    $where[]  = "(DATE(e.enquiry_date) <= ? OR (e.enquiry_date IS NULL AND DATE(e.created_at) <= ?))";
    $params[] = $to;
    $params[] = $to;
}

// Keyword search (name/phone/email/enquiry_no/course_interest)
if ($q !== '') {
    $where[] = "(
        e.name LIKE ?
        OR e.phone LIKE ?
        OR e.email LIKE ?
        OR e.enquiry_no LIKE ?
        OR e.course_interest LIKE ?
    )";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// --------------------------
// Count total
// --------------------------
$totalRows = 0;
try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM enquiries e $whereSql");
    $cnt->execute($params);
    $totalRows = (int)$cnt->fetchColumn();
} catch (Exception $e) {
    $totalRows = 0;
}

$totalPages = (int)ceil($totalRows / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

// --------------------------
// Fetch rows
// --------------------------
$rows = [];
try {
    $sql = "
        SELECT
            e.id, e.enquiry_date, e.enquiry_no,
            e.name, e.phone, e.email, e.course_interest,
            e.status, e.created_at,
            u.name AS handled_name
        FROM enquiries e
        LEFT JOIN users u ON u.id = e.handled_by
        $whereSql
        ORDER BY e.id DESC
        LIMIT $perPage OFFSET $offset
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

// helpers
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function badgeStatus($s){
    $s = (string)$s;
    $map = [
        'new'       => ['#e91e63', 'New'],
        'followup'  => ['#ff9800', 'Follow-up'],
        'converted' => ['#2e7d32', 'Converted'],
        'closed'    => ['#607d8b', 'Closed'],
    ];
    $c = $map[$s][0] ?? '#999';
    $t = $map[$s][1] ?? ucfirst($s ?: 'unknown');
    return '<span style="display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(0,0,0,.04);border:1px solid rgba(0,0,0,.08);color:'.$c.';font-weight:800;font-size:12px;">'.$t.'</span>';
}

$baseUrl = "index.php?page=enquiries/list"
    . "&q=" . urlencode($q)
    . "&status=" . urlencode($status)
    . "&handled_by=" . urlencode((string)$handled)
    . "&from=" . urlencode($from)
    . "&to=" . urlencode($to);
?>

<h2 style="margin-bottom:16px;">Enquiry List</h2>

<?php if ($success): ?>
<script>
Swal.fire({
  icon:'success',
  title:'Success',
  text:'<?= addslashes($success) ?>',
  confirmButtonColor:'#e91e63'
}).then(()=>{ window.location.href = "<?= $baseUrl ?>"; });
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
Swal.fire({
  icon:'error',
  title:'Error',
  text:'<?= addslashes($error) ?>',
  confirmButtonColor:'#e91e63'
});
</script>
<?php endif; ?>

<div class="card">
  <div class="card-header">Filters</div>

  <form method="GET" action="index.php" class="filter-form">
    <input type="hidden" name="page" value="enquiries/list">

    <div class="filter-grid">

      <div class="f-group">
        <label>Search</label>
        <input type="text" name="q"
               value="<?= h($q) ?>"
               placeholder="Name / Phone / Email / Enquiry No">
      </div>

      <div class="f-group">
        <label>Status</label>
        <select name="status">
          <option value="">All</option>
          <option value="new" <?= $status==='new'?'selected':''; ?>>New</option>
          <option value="followup" <?= $status==='followup'?'selected':''; ?>>Follow-up</option>
          <option value="converted" <?= $status==='converted'?'selected':''; ?>>Converted</option>
          <option value="closed" <?= $status==='closed'?'selected':''; ?>>Closed</option>
        </select>
      </div>

      <div class="f-group">
        <label>Handled By</label>
        <select name="handled_by">
          <option value="">All</option>
          <?php foreach ($frontOfficeUsers as $u): ?>
            <option value="<?= (int)$u['id'] ?>"
              <?= ($handled === (int)$u['id'])?'selected':''; ?>>
              <?= h($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="f-group">
        <label>Date From</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>

      <div class="f-group">
        <label>Date To</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>

      <div class="f-actions">
        <button class="btn btn-primary" type="submit">Apply</button>

        <a class="btn-light"
           href="index.php?page=enquiries/list">
           Reset
        </a>

        <a class="btn btn-primary"
           href="index.php?page=enquiries/add">
           + Add
        </a>
      </div>

    </div>
  </form>
</div>

<div class="card" style="margin-top:16px;">
  <div class="card-header">
    Results (<?= (int)$totalRows ?>)
  </div>

  <div class="table-responsive" style="padding:14px;">
    <div class="modern-table-wrapper">

<table class="modern-table">
    <thead>
        <tr>
            <th class="col-id">ID</th>
            <th>Enquiry</th>
            <th>Student</th>
            <th>Contact</th>
            <th class="text-center">Status</th>
            <th>Handled By</th>
            <th class="text-center">Action</th>
        </tr>
    </thead>
    <tbody>

    <?php if (empty($rows)): ?>
        <tr>
            <td colspan="7" class="empty-row">
                No enquiries found.
            </td>
        </tr>
    <?php else: ?>

        <?php foreach ($rows as $r): ?>
        <tr>
            <td class="col-id"><?= (int)$r['id'] ?></td>

            <td>
                <div class="primary-text">
                    <?= h($r['enquiry_no'] ?: ('ENQ-' . $r['id'])) ?>
                </div>
                <div class="secondary-text">
                    <?= h($r['enquiry_date'] ?: date('Y-m-d', strtotime($r['created_at'] ?? 'now'))) ?>
                </div>
            </td>

            <td>
                <div class="primary-text"><?= h($r['name']) ?></div>
                <div class="secondary-text"><?= h($r['course_interest'] ?? '-') ?></div>
            </td>

            <td>
                <div><?= h($r['phone'] ?? '-') ?></div>
                <div class="secondary-text"><?= h($r['email'] ?? '-') ?></div>
            </td>

            <td class="text-center">
                <?= badgeStatus($r['status'] ?? '') ?>
            </td>

            <td><?= h($r['handled_name'] ?? '-') ?></td>

            <td class="text-center action-cell">
                <!-- ✅ EDIT LINK IS FINE -->
               <a href="index.php?page=registrations/convert&reg_id=<?= (int)$r['id'] ?>"
   class="icon-btn edit-btn" title="Edit Registration">
    <i class="fas fa-pen"></i>
</a>

                <!-- ✅ DELETE FIXED -->
                <form method="POST" class="deleteForm" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                    <!-- ✅ IMPORTANT: Always send this even when JS uses form.submit() -->
                    <input type="hidden" name="delete_enquiry" value="1">

                    <button type="submit"
                            class="icon-btn delete-btn"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>

    <?php endif; ?>

    </tbody>
</table>

</div>

    <!-- Pagination -->
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:10px;">
      <div style="color:var(--text-light);font-size:13px;">
        Page <?= (int)$page ?> of <?= (int)$totalPages ?>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php
        $prev = $page - 1;
        $next = $page + 1;
        ?>
        <a class="wbtn" style="text-decoration:none;" href="<?= $baseUrl ?>&p=1">First</a>
        <a class="wbtn" style="text-decoration:none;<?= ($page<=1?'pointer-events:none;opacity:.5;':'') ?>"
           href="<?= $baseUrl ?>&p=<?= (int)max(1,$prev) ?>">Prev</a>

        <a class="wbtn" style="text-decoration:none;<?= ($page>=$totalPages?'pointer-events:none;opacity:.5;':'') ?>"
           href="<?= $baseUrl ?>&p=<?= (int)min($totalPages,$next) ?>">Next</a>
        <a class="wbtn" style="text-decoration:none;" href="<?= $baseUrl ?>&p=<?= (int)$totalPages ?>">Last</a>
      </div>
    </div>

  </div>
</div>

<style>
.action-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:6px 10px; border-radius:10px; text-decoration:none; }
.btn-edit { background: var(--primary); color:#fff; }
.btn-edit:hover { background: var(--primary-dark); color:#fff; }
.wbtn { padding:10px 14px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
.wbtn:hover { box-shadow: 0 8px 22px rgba(0,0,0,.06); }

/* ===== Compact Filter UI ===== */
.filter-form { padding:16px; }
.filter-grid {
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap:16px;
  align-items:end;
}
.f-group label {
  font-weight:600;
  font-size:13px;
  margin-bottom:6px;
  display:block;
  color: var(--text-dark);
}
.f-group input, .f-group select {
  width:100%;
  padding:10px 12px;
  border-radius:10px;
  border:1px solid #e5e7eb;
  background:#fff;
  transition:all .2s ease;
}
.f-group input:focus, .f-group select:focus {
  border-color: rgba(233,30,99,.5);
  box-shadow: 0 0 0 3px rgba(233,30,99,.12);
}
.f-actions {
  display:flex;
  gap:10px;
  align-items:center;
  justify-content:flex-end;
}
.btn-light {
  padding:10px 14px;
  border-radius:10px;
  border:1px solid #e5e7eb;
  text-decoration:none;
  background:#fff;
  color:#444;
}
.btn-light:hover { background:#f8f9fa; }

/* ==============================
   PREMIUM SaaS TABLE UPGRADE
================================ */
.modern-table-wrapper{
  background:#fff;
  border-radius:16px;
  box-shadow:0 10px 35px rgba(0,0,0,.05);
  border:1px solid rgba(0,0,0,.04);
  overflow:hidden;
}
.modern-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  font-size:14px;
}
.modern-table thead{ background:#f7f8fb; }
.modern-table th{
  padding:14px 16px;
  font-weight:800;
  font-size:13px;
  letter-spacing:.2px;
  color:#333;
  border-bottom:1px solid #eee;
  white-space:nowrap;
}
.modern-table td{
  padding:14px 16px;
  border-bottom:1px solid #f2f2f2;
  vertical-align:middle;
}
.modern-table tbody tr{
  transition:all .15s ease;
  border-left:4px solid transparent;
}
.modern-table tbody tr:hover{
  background:#fcfcff;
  border-left:4px solid var(--primary);
}
.primary-text{ font-weight:800; color:#111; }
.secondary-text{ font-size:12px; color:#888; margin-top:4px; }
.col-id{ width:70px; text-align:center; font-weight:800; color:#555; }
.text-center{ text-align:center; }
.action-cell{ white-space:nowrap; }

/* Action buttons */
.icon-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:36px;
  height:36px;
  border-radius:10px;
  border:1px solid rgba(0,0,0,.06);
  cursor:pointer;
  text-decoration:none;
  transition:all .15s ease;
  background:#fff;
}
.icon-btn:hover{
  box-shadow:0 10px 22px rgba(0,0,0,.08);
  transform:translateY(-1px);
}
.edit-btn{
  background:rgba(233,30,99,.10);
  color:var(--primary);
  border-color:rgba(233,30,99,.20);
}
.edit-btn:hover{
  background:var(--primary);
  color:#fff;
}
.delete-btn{
  background:#f7f7f7;
  color:#666;
}
.delete-btn:hover{
  background:#e53935;
  color:#fff;
  border-color:#e53935;
}
</style>

<script>
// ✅ SweetAlert confirm for delete (FIXED)
document.querySelectorAll('.deleteForm').forEach(form => {
  form.addEventListener('submit', function(e){

    // If already confirmed, allow real submit
    if (this.dataset.confirmed === '1') return;

    e.preventDefault();

    Swal.fire({
      icon: 'warning',
      title: 'Delete Enquiry?',
      text: 'This action cannot be undone.',
      showCancelButton: true,
      confirmButtonText: 'Yes, Delete',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#e91e63'
    }).then((r)=>{
      if (r.isConfirmed) {
        this.dataset.confirmed = '1';
        this.submit();
      }
    });
  });
});
</script>