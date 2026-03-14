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

  <form method="GET" action="index.php">
    <input type="hidden" name="page" value="enquiries/list">

    <div class="filter-row">

      <div>
        <label>Search</label>
        <input type="text" name="q"
               value="<?= h($q) ?>"
               placeholder="Name / Phone / Email / Enquiry No">
      </div>

      <div>
        <label>Status</label>
        <select name="status">
          <option value="">All</option>
          <option value="new" <?= $status==='new'?'selected':''; ?>>New</option>
          <option value="followup" <?= $status==='followup'?'selected':''; ?>>Follow-up</option>
          <option value="converted" <?= $status==='converted'?'selected':''; ?>>Converted</option>
          <option value="closed" <?= $status==='closed'?'selected':''; ?>>Closed</option>
        </select>
      </div>

      <div>
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

      <div>
        <label>Date From</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>

      <div>
        <label>Date To</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>

      <div class="filter-actions">
        <!-- Apply button with icon -->
    <button class="btn-icon" title="Apply filters">
        <i class="fas fa-filter"></i>
    </button>

       <!-- Reset button with icon -->
    <a href="index.php?page=leads/list" class="btn-icon" title="Reset filters">
        <i class="fas fa-undo-alt"></i>
    </a>

        
      </div>

    </div>
  </form>
</div>

<div class="card" style="margin-top:16px;">
  <div class="card" style="margin-top:16px;">

<div class="card-header">Enquiries (<?=$totalRows?>)</div>

<div class="crm-card">
    <h3> <i class="fas fa-list" style="margin-right: 8px;"></i>
          Enquiry List</h3>
           <div class="crm-table-wrapper">

<table id="usersTable" class="crm-table">

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
   class="btn-icon edit" title="Edit Registration">
    <i class="fas fa-pen"></i>
</a>

                <!-- ✅ DELETE FIXED -->
                <form method="POST" class="deleteForm" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

                    <!-- ✅ IMPORTANT: Always send this even when JS uses form.submit() -->
                    <input type="hidden" name="delete_enquiry" value="1">

                    <button type="submit"
                            class="btn-icon delete"
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
        </div>
        </div>
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
/* Enhanced UI for Lead Management - Maintains existing class names */

:root {
  --primary: #e91e63;
  --primary-light: #f8bbd0;
  --primary-dark: #c2185b;
  --secondary: #6c757d;
  --success: #28a745;
  --danger: #dc3545;
  --warning: #ffc107;
  --white: #ffffff;
  --gray-100: #f8f9fa;
  --gray-200: #e9ecef;
  --gray-300: #dee2e6;
  --gray-400: #ced4da;
  --gray-500: #adb5bd;
  --gray-600: #6c757d;
  --gray-700: #495057;
  --gray-800: #343a40;
  --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
  --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
  --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
  --radius-sm: 6px;
  --radius-md: 8px;
  --radius-lg: 12px;
  --transition: all 0.2s ease;
}



/* Force all icons to be visible */
.filter-actions .btn-icon {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    background: #e91e63 !important;  /* Pink background */
    color: white !important;  /* White icons */
    border-radius: 8px;
    margin: 0 4px;
    text-decoration: none;
    font-size: 16px;
}

/* Reset button different color */
.filter-actions .btn-icon[title="Reset filters"] {
    background: #6c757d !important;
}

/* Make sure Font Awesome icons are visible */
.filter-actions .btn-icon i {
    font-family: "Font Awesome 6 Free" !important;
    font-weight: 900 !important;
    font-style: normal !important;
    color: white !important;
    font-size: 16px !important;
    display: inline-block !important;
}

/* Tooltip styling */
.filter-actions .btn-icon {
    position: relative;
}

.filter-actions .btn-icon:hover::before {
    content: attr(title);
    position: absolute;
    bottom: 120%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
}

/* Card Styling */
.card {
  background: var(--white);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--gray-200);
  margin-bottom: 24px;
  overflow: hidden;
}

.card-header {
  background: var(--gray-100);
  padding: 16px 20px;
  font-weight: 600;
  color: var(--gray-700);
  border-bottom: 1px solid var(--gray-200);
  font-size: 16px;
  letter-spacing: 0.3px;
}

/* Filter Section Enhancement */
.filter-row {
  display: flex;
 /* align-items: flex-end;*/
  flex-wrap: wrap;
  
}

.filter-row > div {
  flex: 1 1 auto;
  min-width: 180px;
}

.filter-row label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--gray-600);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.filter-row input,
.filter-row select {
  width:75%;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  border: 1px solid var(--gray-300);
  background: var(--white);
  font-size: 14px;
  transition: var(--transition);
}

.filter-row input:hover,
.filter-row select:hover {
  border-color: var(--primary-light);
}

.filter-row input:focus,
.filter-row select:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

/* Filter Actions */
.filter-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}

.filter-actions .btn,
.filter-actions .btn-primary {
  background: var(--primary);
  color: var(--white);
  border: none;
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  transition: var(--transition);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.filter-actions .btn-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.filter-actions .btn-reset {
  background: transparent;
  color: var(--gray-600);
  border: 1px solid var(--gray-300);
  padding: 10px 20px;
  border-radius: var(--radius-md);
  font-weight: 500;
  font-size: 14px;
  cursor: pointer;
  transition: var(--transition);
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.filter-actions .btn-reset:hover {
  background: var(--gray-100);
  color: var(--gray-800);
  border-color: var(--gray-400);
}

/* CRM Card */
.crm-card {
  background: var(--white);
  border-radius: 0;
  padding: 20px;
  width: 100%;
  box-sizing: border-box;
}

.crm-card h3 {
  margin: 0 0 20px 0;
  font-size: 18px;
  font-weight: 600;
  color: var(--gray-800);
  display: flex;
  align-items: center;
  gap: 8px;
}

.crm-card h3 i {
  color: var(--primary);
}

/* Table Wrapper */
.crm-table-wrapper {
  width: 100%;
  overflow-x: auto;
  border-radius: var(--radius-md);
  border: 1px solid var(--gray-200);
}

/* DataTable Customization */
.dataTables_wrapper {
  padding: 0;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
  padding: 16px 20px;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.dataTables_wrapper .dataTables_length {
  float: left;
}

.dataTables_wrapper .dataTables_filter {
  float: right;
}

.dataTables_wrapper .dataTables_length label,
.dataTables_wrapper .dataTables_filter label {
  font-size: 14px;
  color: var(--gray-600);
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.dataTables_wrapper .dataTables_length select {
  width: auto;
  padding: 8px 12px;
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-sm);
  background: var(--white);
  font-size: 13px;
  cursor: pointer;
  margin: 0 4px;
}

.dataTables_wrapper .dataTables_filter input {
  width: 260px !important;
  padding: 8px 14px;
  border: 1px solid var(--gray-300);
  border-radius: 20px;
  font-size: 13px;
  transition: var(--transition);
  background: var(--white);
}

.dataTables_wrapper .dataTables_filter input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
  outline: none;
  width: 300px !important;
}

/* Export Button */
.dt-buttons {
  float: left;
  padding: 16px 20px;
  background: var(--gray-50);
  border-bottom: 1px solid var(--gray-200);
}

.crm-export-btn {
  background: var(--success) !important;
  color: var(--white) !important;
  border: none !important;
  padding: 8px 16px !important;
  border-radius: var(--radius-md) !important;
  font-weight: 500 !important;
  font-size: 13px !important;
  transition: var(--transition) !important;
  box-shadow: none !important;
}

.crm-export-btn:hover {
  background: #218838 !important;
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm) !important;
}

/* Table Styles */
.crm-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--white);
}

.crm-table th {
  background: var(--gray-50);
  padding: 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--gray-700);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 2px solid var(--gray-200);
  white-space: nowrap;
}

.crm-table td {
  padding: 16px;
  font-size: 14px;
  color: var(--gray-700);
  border-bottom: 1px solid var(--gray-200);
  vertical-align: middle;
}

.crm-table tbody tr:hover td {
  background: var(--gray-50);
}

/* Lead Name */
.lead-name {
  font-weight: 600;
  color: var(--gray-800);
  margin-bottom: 4px;
}

.lead-sub {
  font-size: 12px;
  color: var(--gray-500);
}

/* Status Badge Enhancement */
td:contains("new") .badge,
td .badge[style*="2196f3"] {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  font-size: 12px;
  background: #e3f2fd !important;
  color: #1976d2 !important;
}

td:contains("contacted") .badge,
td .badge[style*="ff9800"] {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  font-size: 12px;
  background: #fff3e0 !important;
  color: #f57c00 !important;
}

td:contains("qualified") .badge,
td .badge[style*="9c27b0"] {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  font-size: 12px;
  background: #f3e5f5 !important;
  color: #7b1fa2 !important;
}

td:contains("converted") .badge,
td .badge[style*="2e7d32"] {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  font-size: 12px;
  background: #e8f5e8 !important;
  color: #2e7d32 !important;
}

td:contains("closed") .badge,
td .badge[style*="607d8b"] {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 20px;
  font-weight: 500;
  font-size: 12px;
  background: #eceff1 !important;
  color: #546e7a !important;
}

/* Action Icons */
.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: var(--radius-sm);
  color: var(--white);
  margin: 0 3px;
  text-decoration: none;
  transition: var(--transition);
  border: none;
  cursor: pointer;
  font-size: 14px;
}

.btn-icon.edit {
  background: var(--primary);
}

.btn-icon.edit:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.btn-icon.convert {
  background: var(--warning);
  color: var(--gray-800);
}

.btn-icon.convert:hover {
  background: #e0a800;
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.btn-icon.done {
  background: var(--success);
  cursor: default;
  opacity: 0.8;
}

.btn-icon.delete {
  background: var(--danger);
}

.btn-icon.delete:hover {
  background: #c82333;
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

/* Action Column */
.action-col {
  white-space: nowrap;
  text-align: center;
}

/* Pagination Enhancement */
.dataTables_wrapper .dataTables_paginate {
  padding: 16px 20px;
  background: var(--gray-50);
  border-top: 1px solid var(--gray-200);
  display: flex;
  justify-content: center;
  gap: 4px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  border: 1px solid var(--gray-300) !important;
  background: var(--white) !important;
  border-radius: var(--radius-sm) !important;
  color: var(--gray-600) !important;
  padding: 8px 14px !important;
  margin: 0 2px !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  transition: var(--transition) !important;
  cursor: pointer !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
  background: var(--primary) !important;
  color: var(--white) !important;
  border-color: var(--primary) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  background: var(--gray-100) !important;
  color: var(--primary-dark) !important;
  border-color: var(--primary-light) !important;
  transform: translateY(-1px);
}

/* Info Text */
.dataTables_wrapper .dataTables_info {
  padding: 16px 20px;
  background: var(--gray-50);
  border-top: 1px solid var(--gray-200);
  font-size: 13px;
  color: var(--gray-600);
  float: left;
}

/* Responsive */
@media (max-width: 768px) {
  .filter-row {
    flex-direction: column;
    gap: 16px;
  }
  
  .filter-row > div {
    width: 100%;
  }
  
  .filter-actions {
    width: 100%;
    justify-content: stretch;
  }
  
  .filter-actions .btn,
  .filter-actions .btn-primary,
  .filter-actions .btn-reset {
    flex: 1;
    text-align: center;
    justify-content: center;
  }
  
  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter {
    float: none;
    width: 100%;
    padding: 12px 16px;
  }
  
  .dataTables_wrapper .dataTables_filter input {
    width: 100% !important;
  }
  
  .dataTables_wrapper .dataTables_filter input:focus {
    width: 100% !important;
  }
  
  .dt-buttons {
    float: none;
    width: 100%;
    text-align: center;
  }
  
  .crm-export-btn {
    width: 100% !important;
  }
  
  .dataTables_wrapper .dataTables_paginate {
    flex-wrap: wrap;
  }
  
  .dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 6px 10px !important;
  }
}

/* Animation for delete button */
@keyframes shake {
  0%, 100% { transform: translateX(0); }
  25% { transform: translateX(-2px); }
  75% { transform: translateX(2px); }
}

.btn-icon.delete:hover {
  animation: shake 0.3s ease-in-out;
}

/* Tooltip for action buttons */
.btn-icon {
  position: relative;
}

.btn-icon::before {
  content: attr(title);
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(-5px);
  background: var(--gray-800);
  color: var(--white);
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 4px;
  white-space: nowrap;
  opacity: 0;
  visibility: hidden;
  transition: all 0.2s;
  pointer-events: none;
  z-index: 1000;
}

.btn-icon:hover::before {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(-8px);
}

/* Table row highlight */
.crm-table tbody tr {
  transition: var(--transition);
}

.crm-table tbody tr:active {
  transform: scale(0.998);
}

/* Empty state enhancement */
.crm-table td[colspan="8"] {
  text-align: center;
  padding: 60px 20px !important;
  color: var(--gray-500);
  font-size: 15px;
  background: var(--gray-50);
}

/* Scrollbar styling */
.crm-table-wrapper::-webkit-scrollbar {
  height: 8px;
  width: 8px;
}

.crm-table-wrapper::-webkit-scrollbar-track {
  background: var(--gray-100);
  border-radius: 4px;
}

.crm-table-wrapper::-webkit-scrollbar-thumb {
  background: var(--gray-400);
  border-radius: 4px;
}

.crm-table-wrapper::-webkit-scrollbar-thumb:hover {
  background: var(--gray-500);
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

<script>
document.addEventListener("DOMContentLoaded", function(){

crmDataTable('#usersTable',{
pageLength:5,
lengthMenu:[5,10,20,50],
ordering:true,
order:[[1,'asc']]
});

});

</script>