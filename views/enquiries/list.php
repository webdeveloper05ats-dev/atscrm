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
$isSuperAdmin = ($roleName === 'Super Admin');

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

    // RBAC permission check
    if (!canDelete('enquiries/list') && !$isSuperAdmin) {
        $error = "Access denied. You do not have delete permission.";
    }

    elseif (!verifyCSRF($_POST['csrf_token'] ?? '')) {
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

                // Converted enquiries are locked from delete
                $cv = $pdo->prepare("SELECT status FROM enquiries WHERE id=? LIMIT 1");
                $cv->execute([$delId]);
                $curStatus = (string)($cv->fetchColumn() ?? '');
                if ($curStatus === 'converted') {
                    throw new Exception("Converted enquiries are locked and cannot be deleted.");
                }

                // Only creator or Super Admin can delete
                if (!$isSuperAdmin) {
                    $own = $pdo->prepare("SELECT COUNT(*) FROM enquiries WHERE id=? AND created_by=?");
                    $own->execute([$delId, $userId]);
                    if ((int)$own->fetchColumn() === 0) {
                        throw new Exception("Only enquiry creator or Super Admin can delete.");
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

    // Front Office should see enquiries assigned to them OR created by them
    $where[]  = "(e.handled_by = ? OR e.created_by = ?)";
    $params[] = $userId;
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
            e.status, e.created_at, e.created_by,
            u.name AS handled_name
        FROM enquiries e
        LEFT JOIN users u ON u.id = e.handled_by
        $whereSql
        ORDER BY e.id DESC
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

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/lead.css">

<div class="leads-dashboard">
  <div class="dashboard-header">
    <h2><i class="fas fa-address-book" style="margin-right: 12px; color: #e91e63;"></i>Enquiry Management</h2>
    <div class="header-stats">
      <span class="stat-item"><i class="fas fa-database"></i> Total: <?= (int)$totalRows ?></span>
    </div>
  </div>

<?php if ($success): ?>
<script>
(function(){
  const msg = '<?= addslashes($success) ?>';
  if (window.Swal && Swal.fire) {
    Swal.fire({
      icon:'success',
      title:'Success',
      text: msg,
      confirmButtonColor:'#e91e63'
    }).then(()=>{ window.location.href = "<?= $baseUrl ?>"; });
  } else {
    alert(msg);
    window.location.href = "<?= $baseUrl ?>";
  }
})();
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
(function(){
  const msg = '<?= addslashes($error) ?>';
  if (window.Swal && Swal.fire) {
    Swal.fire({
      icon:'error',
      title:'Error',
      text: msg,
      confirmButtonColor:'#e91e63'
    });
  } else {
    alert(msg);
  }
})();
</script>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <i class="fas fa-sliders-h" style="margin-right: 8px;"></i> Filter Enquiries
  </div>

  <form method="GET" action="index.php" class="filter-form">
    <input type="hidden" name="page" value="enquiries/list">

    <div class="filter-grid">
      <div class="filter-item">
        <label><i class="fas fa-search"></i> Search</label>
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Name / Phone / Email / Enquiry No">
      </div>

      <div class="filter-item">
        <label><i class="fas fa-tag"></i> Status</label>
        <select name="status">
          <option value="">All Status</option>
          <option value="new" <?= $status==='new'?'selected':''; ?>>New</option>
          <option value="followup" <?= $status==='followup'?'selected':''; ?>>Follow-up</option>
          <option value="converted" <?= $status==='converted'?'selected':''; ?>>Converted</option>
          <option value="closed" <?= $status==='closed'?'selected':''; ?>>Closed</option>
        </select>
      </div>

      <div class="filter-item">
        <label><i class="fas fa-user-check"></i> Handled By</label>
        <select name="handled_by">
          <option value="">All</option>
          <?php foreach ($frontOfficeUsers as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= ($handled === (int)$u['id'])?'selected':''; ?>>
              <?= h($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-item">
        <label><i class="fas fa-calendar-alt"></i> Date From</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>

      <div class="filter-item">
        <label><i class="fas fa-calendar-check"></i> Date To</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>

      <div class="filter-actions">
        <button type="submit" class="btn-icon-only apply" title="Apply filters"><i class="fas fa-filter"></i></button>
        <a href="index.php?page=enquiries/list" class="btn-icon-only reset" title="Reset filters"><i class="fas fa-undo-alt"></i></a>
        <a href="index.php?page=enquiries/add" class="btn-icon-only add" title="Add Enquiry"><i class="fas fa-plus"></i></a>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header">
    <div class="table-header-flex">
      <div class="table-title"><i class="fas fa-list"></i> Enquiry List</div>
      <div id="datatableControls"></div>
    </div>
  </div>

  <div class="table-container">
    <table id="enquiriesTable" class="leads-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Enquiry</th>
          <th>Student</th>
          <th>Contact</th>
          <th>Status</th>
          <th>Handled By</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $r): ?>
        <?php
          $isConverted = (($r['status'] ?? '') === 'converted');
          $canManage = !$isConverted && ($isSuperAdmin || ((int)($r['created_by'] ?? 0) === $userId));
        ?>
        <tr>
          <td class="id-col">#<?= (int)$r['id'] ?></td>

          <td>
            <div class="enquiry-meta">
              <div class="lead-name"><?= h($r['enquiry_no'] ?: ('ENQ-' . $r['id'])) ?></div>
              <div class="lead-interest"><?= h($r['enquiry_date'] ?: date('Y-m-d', strtotime($r['created_at'] ?? 'now'))) ?></div>
            </div>
          </td>

          <td>
            <div class="lead-name"><?= h($r['name']) ?></div>
            <div class="lead-interest"><?= h($r['course_interest'] ?? '-') ?></div>
          </td>

          <td>
            <div class="contact-phone"><i class="fas fa-phone-alt"></i> <?= h($r['phone'] ?? '-') ?></div>
            <div class="contact-email"><i class="fas fa-envelope"></i> <?= h($r['email'] ?? '-') ?></div>
          </td>

          <td><?= badgeStatus($r['status'] ?? '') ?></td>

          <td><span class="assigned-badge"><i class="fas fa-user-circle"></i> <?= h($r['handled_name'] ?? '-') ?></span></td>

          <td class="actions-col">
            <div class="action-buttons">
              <?php if ($canManage): ?>
              <a href="index.php?page=enquiries/edit&id=<?= (int)$r['id'] ?>" class="action-btn edit" title="Edit Enquiry">
                <i class="fas fa-pen"></i>
              </a>

              <form method="POST" class="delete-form" data-id="<?= (int)$r['id'] ?>" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="delete_enquiry" value="1">
                <button type="submit" class="action-btn delete" title="Delete Enquiry">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
              <?php else: ?>
              <?php if ($isConverted): ?>
              <span class="assigned-badge" style="padding:4px 8px;"><i class="fas fa-lock"></i> Locked</span>
              <?php else: ?>
              <span class="assigned-badge" style="padding:4px 8px;"><i class="fas fa-eye"></i> View only</span>
              <?php endif; ?>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<style>
#datatableControls .dt-top{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
}

@media (max-width: 768px){
  .filter-actions{
    display:grid !important;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    align-items:stretch;
    gap:8px;
    width:100%;
  }

  .filter-actions .btn-icon-only{
    width:100% !important;
    min-width:0 !important;
    height:auto !important;
    min-height:40px !important;
    padding:6px 8px !important;
    display:inline-flex !important;
    flex-direction:column !important;
    align-items:center !important;
    justify-content:center !important;
    gap:3px !important;
  }

  .filter-actions .btn-icon-only[data-mobile-label]::before{
    content:none !important;
    display:none !important;
  }

  .filter-actions .btn-icon-only[data-mobile-label]::after{
    content:attr(data-mobile-label) !important;
    position:static !important;
    display:block !important;
    opacity:1 !important;
    visibility:visible !important;
    transform:none !important;
    background:none !important;
    border:0 !important;
    box-shadow:none !important;
    padding:0 !important;
    margin:0 !important;
    font-size:10px !important;
    line-height:1.1 !important;
    font-weight:700 !important;
    letter-spacing:.1px !important;
    color:currentColor !important;
    white-space:nowrap !important;
  }

  /* Mobile card readability: keep right-side values stacked and clean */
  #enquiriesTable tbody td{
    align-items:flex-start !important;
  }

  #enquiriesTable tbody td .lead-name,
  #enquiriesTable tbody td .lead-interest,
  #enquiriesTable tbody td .contact-phone,
  #enquiriesTable tbody td .contact-email{
    display:block;
    width:100%;
    text-align:right;
    line-height:1.3;
  }

  #enquiriesTable tbody td .contact-email{
    margin-top:4px;
    overflow-wrap:anywhere;
    word-break:break-word;
  }

  #enquiriesTable tbody td .contact-phone i,
  #enquiriesTable tbody td .contact-email i{
    margin-right:6px;
  }

  /* ENQUIRY row: label stays left, value block stays right in 2 lines */
  #enquiriesTable tbody td:nth-child(2) .enquiry-meta,
  #enquiriesTable tbody td[data-label="Enquiry"] .enquiry-meta{
    margin-left:auto;
    width:auto;
    max-width:58%;
  }

  #enquiriesTable tbody td:nth-child(2) .enquiry-meta .lead-name,
  #enquiriesTable tbody td[data-label="Enquiry"] .enquiry-meta .lead-name{
    display:block;
    width:100%;
    text-align:right;
    white-space:nowrap;
    word-break:normal;
    overflow-wrap:normal;
    line-height:1.25;
  }

  #enquiriesTable tbody td:nth-child(2) .enquiry-meta .lead-interest,
  #enquiriesTable tbody td[data-label="Enquiry"] .enquiry-meta .lead-interest{
    display:block;
    width:100%;
    text-align:right;
    white-space:nowrap;
    font-size:12px;
    color:#7b8794;
    line-height:1.25;
  }
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function(){
  function confirmDeleteDialog(){
    if (window.Swal && Swal.fire) {
      return Swal.fire({
        icon: 'warning',
        title: 'Delete Enquiry?',
        text: 'This action cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e91e63'
      });
    }
    return Promise.resolve({ isConfirmed: window.confirm('Delete enquiry? This action cannot be undone.') });
  }

  document.querySelectorAll('.btn-icon-only[title], .action-btn[title]').forEach(el => {
    const tip = (el.getAttribute('title') || '').trim();
    if (!tip) return;
    el.setAttribute('data-tooltip', tip);
    el.setAttribute('aria-label', tip);
    el.removeAttribute('title');
  });

  document.querySelectorAll('.delete-form').forEach(form => {
    form.addEventListener('submit', function(e){
      if (this.dataset.confirmed === '1') return;
      e.preventDefault();

      confirmDeleteDialog().then((r)=>{
        if (r.isConfirmed) {
          this.dataset.confirmed = '1';
          this.submit();
        }
      });
    });
  });

  crmDataTable('#enquiriesTable',{
    pageLength:10,
    lengthMenu:[5,10,20,50,100],
    ordering:true,
    order:[[0,'desc']],
    searchPlaceholder:"Search enquiries...",
    dom:"<'dt-top'lfB>rt<'dt-bottom'ip>",
    language:{
      emptyTable:'No enquiries found.'
    }
  });

  setTimeout(function(){
    const wrapper=document.querySelector('#enquiriesTable_wrapper');
    const target=document.getElementById('datatableControls');
    if(!wrapper || !target) return;
    const top=wrapper.querySelector('.dt-top');
    if(top) target.appendChild(top);
  },100);
});
</script>
