<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success="";
$error="";

$userId=(int)($_SESSION['user_id']??0);
$roleId=(int)($_SESSION['role_id']??0);
$branchId=(int)($_SESSION['branch_id']??0);

/* Branch Access */
$canAllBranches=0;
try{
$st=$pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=?");
$st->execute([$roleId]);
$canAllBranches=(int)$st->fetchColumn();
}catch(Exception $e){}

/* DELETE */
/* DELETE */
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_lead'])){

$token=$_POST['csrf_token']??'';

if(!verifyCSRF($token)){
$error="Invalid request";
}else{

$id=(int)($_POST['id']??0);

try{

/* ✅ Only creator + not converted */
$chk=$pdo->prepare("
SELECT COUNT(*) FROM leads 
WHERE id=? 
AND created_by=? 
AND status!='converted'
");
$chk->execute([$id,$userId]);

if(!$chk->fetchColumn()){
throw new Exception("Access denied or already converted");
}

$st=$pdo->prepare("DELETE FROM leads WHERE id=?");
$st->execute([$id]);

$success="Lead deleted";

}catch(Exception $e){
$error=$e->getMessage();
}

}
}

/* FILTERS */
$q=trim($_GET['q']??'');
$status=trim($_GET['status']??'');
$assigned=(int)($_GET['assigned_to']??0);

/* Pagination */
$page=(int)($_GET['p']??1);
if($page<1)$page=1;

$perPage=10;
$offset=($page-1)*$perPage;

/* Staff */
$staff=[];
try{
$st=$pdo->query("
SELECT u.id,u.name,r.role_name
FROM users u
LEFT JOIN roles r ON r.id=u.role_id
WHERE u.status=1
ORDER BY r.role_name,u.name
");
$staff=$st->fetchAll(PDO::FETCH_ASSOC);
}catch(Exception $e){}

/* WHERE */
/* WHERE */
$where=[];
$params=[];

/* Branch Filter */
if(!$canAllBranches){
    $where[]="l.branch_id=?";
    $params[]=$branchId;
}

/* Assigned Lead Visibility */
$allowedRolesToSeeAll = ['Super Admin','HR','Marketing'];

if(!in_array($_SESSION['role_name'],$allowedRolesToSeeAll)){
    $where[]="l.assigned_to=?";
    $params[]=$userId;
}

if($status!=''){
$where[]="l.status=?";
$params[]=$status;
}

if($assigned>0){
$where[]="l.assigned_to=?";
$params[]=$assigned;
}

if($q != ''){
    $where[] = "(l.name LIKE ? OR l.phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereSql='';
if($where)$whereSql="WHERE ".implode(" AND ",$where);

/* COUNT */
$totalRows=0;
try{
$st=$pdo->prepare("SELECT COUNT(*) FROM leads l $whereSql");
$st->execute($params);
$totalRows=$st->fetchColumn();
}catch(Exception $e){}

$totalPages=max(1,ceil($totalRows/$perPage));

/* FETCH */
$rows=[];
try{

$sql="
SELECT 
l.id,
l.name,
l.phone,
l.email,
l.company_college_name,
l.department,
l.lead_year,
l.status,
l.assigned_to,
l.created_by,
l.source,
l.course_interest,
u.name assigned_name
FROM leads l
LEFT JOIN users u ON u.id=l.assigned_to
$whereSql
ORDER BY l.id DESC
LIMIT $perPage OFFSET $offset
";

$st=$pdo->prepare($sql);
$st->execute($params);
$rows=$st->fetchAll(PDO::FETCH_ASSOC);

}catch(Exception $e){}

function h($v){
return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function badge($s){

$map=[
'new'=>'#2196f3',
'contacted'=>'#ff9800',
'qualified'=>'#9c27b0',
'converted'=>'#2e7d32',
'closed'=>'#607d8b'
];

$c=$map[$s]??'#999';

return "<span class='status-badge' style='--badge-color:$c'>".ucfirst($s)."</span>";
}

$baseUrl="index.php?page=leads/list&q=$q&status=$status&assigned_to=$assigned";

?>

<!-- Display success/error messages if any -->
<?php if($success): ?>
<div class="alert-success"><?=h($success)?></div>
<?php endif; ?>
<?php if($error): ?>
<div class="alert-error"><?=h($error)?></div>
<?php endif; ?>

<div class="leads-dashboard">
    <!-- Header with title and stats -->
    <div class="dashboard-header">
        <h2><i class="fas fa-users" style="margin-right: 12px; color: #e91e63;"></i>Lead Management</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-database"></i> Total: <?=$totalRows?></span>
        </div>
    </div>

    <!-- Main Card -->
 <!-- Filter Section - Icons only with tooltips -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-sliders-h" style="margin-right: 8px;"></i> Filter Leads
    </div>
    
    <form method="GET" action="index.php" class="filter-form">
        <input type="hidden" name="page" value="leads/list">
        
        <div class="filter-grid">
            <div class="filter-item">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="q" value="<?=h($q)?>" placeholder="Name or phone...">
            </div>
            
            <div class="filter-item">
                <label><i class="fas fa-tag"></i> Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="new" <?=$status=='new'?'selected':''?>>New</option>
                    <option value="contacted" <?=$status=='contacted'?'selected':''?>>Contacted</option>
                    <option value="qualified" <?=$status=='qualified'?'selected':''?>>Qualified</option>
                    <option value="converted" <?=$status=='converted'?'selected':''?>>Converted</option>
                    <option value="closed" <?=$status=='closed'?'selected':''?>>Closed</option>
                </select>
            </div>
            
            <div class="filter-item">
                <label><i class="fas fa-user-check"></i> Assigned</label>
                <select name="assigned_to">
                    <option value="">All Assignees</option>
                    <?php foreach($staff as $s): ?>
                    <option value="<?=$s['id']?>" <?=$assigned==$s['id']?'selected':''?>><?=$s['name']?> (<?=$s['role_name']?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn-icon-only apply" title="Apply filters">
                    <i class="fas fa-filter"></i>
                </button>
                <a href="index.php?page=leads/list" class="btn-icon-only reset" title="Reset filters">
                    <i class="fas fa-undo-alt"></i>
                </a>
                <a href="index.php?page=leads/add" class="btn-icon-only add" title="Add new lead">
                    <i class="fas fa-user-plus"></i>
                </a>
                <a href="index.php?page=leads/import" class="btn-icon-only import" title="Excel import">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
        </div>
    </form>
</div>

    <!-- Leads Table Card -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <div>
                    <i class="fas fa-list" style="margin-right: 8px;"></i> Lead List
                </div>
                <div class="entries-control">
                    <span>Show</span>
                    <select class="entries-select" onchange="changePerPage(this.value)">
                        <option value="5" <?=$perPage==5?'selected':''?>>5</option>
                        <option value="10" <?=$perPage==10?'selected':''?>>10</option>
                        <option value="20" <?=$perPage==20?'selected':''?>>20</option>
                        <option value="50" <?=$perPage==50?'selected':''?>>50</option>
                    </select>
                    <span>entries</span>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <table class="leads-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Lead</th>
                        <th>Contact</th>
                        <th>Academic / Org</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Source</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!$rows): ?>
                    <tr>
                        <td colspan="8" class="no-data">
                            <i class="fas fa-inbox"></i> No leads found
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php 
                    $sn = $offset + 1;
                    foreach($rows as $r): 
                    ?>
                    
                    <?php
                    /* =========================
                    LEAD PERMISSIONS
                    ========================= */
                    $roleName = $_SESSION['role_name'] ?? '';
                    $canConvert = false;
                    $canEdit = false;
                    $canDelete = false;

                    /* Convert Permission */
                    if($roleName === 'Front Office' && $r['assigned_to'] == $userId && $r['status']!='converted'){
                        $canConvert = true;
                    }

                    /* Edit/Delete Permission */
                    if($r['created_by'] == $userId && $r['status']!='converted'){
                        $canEdit = true;
                        $canDelete = true;
                    }
                    ?>
                    
                    <tr>
                        <td class="id-col">#<?=$sn?></td>
                        
                        <td class="lead-col">
                            <div class="lead-name"><?=h($r['name'])?></div>
                            <div class="lead-interest"><?=h($r['course_interest'])?></div>
                        </td>
                        
                        <td class="contact-col">
                            <div class="contact-phone">
                                <i class="fas fa-phone-alt"></i> <?=h($r['phone'])?>
                            </div>
                            <div class="contact-email">
                                <i class="fas fa-envelope"></i> <?=h($r['email'])?>
                            </div>
                        </td>
                        
                        <td class="academic-col">
                            <div class="org-name"><?=h($r['company_college_name'])?></div>
                            <div class="dept-year">
                                <?=h($r['department'])?>
                                <?= !empty($r['lead_year']) ? ' | ' . h($r['lead_year']) : '' ?>
                            </div>
                        </td>
                        
                        <td class="status-col"><?=badge($r['status'])?></td>
                        
                        <td class="assigned-col">
                            <span class="assigned-badge">
                                <i class="fas fa-user-circle"></i> <?=h($r['assigned_name'])?>
                            </span>
                        </td>
                        
                        <td class="source-col">
                            <span class="source-badge">
                                <?php
                                $sourceIcon = 'fa-link';
                                if($r['source'] == 'Instagram') $sourceIcon = 'fa-instagram';
                                if($r['source'] == 'Facebook') $sourceIcon = 'fa-facebook';
                                if($r['source'] == 'Google Ads') $sourceIcon = 'fa-google';
                                if($r['source'] == 'Walk-in') $sourceIcon = 'fa-walking';
                                ?>
                                <i class="fab <?=$sourceIcon?>"></i> <?=h($r['source'])?>
                            </span>
                        </td>
                        
                        <td class="actions-col">
                            <div class="action-buttons">
                                <?php if($canEdit): ?>
                                <a href="index.php?page=leads/add&id=<?=$r['id']?>" class="action-btn edit" title="Edit Lead">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php if($r['status']=='converted'): ?>
                                <span class="action-btn done" title="Already Converted">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php else: ?>
                                    <?php if($canConvert): ?>
                                    <a href="index.php?page=enquiries/add&lead_id=<?=$r['id']?>" class="action-btn convert" title="Convert to Enquiry">
                                        <i class="fas fa-exchange-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if($canDelete): ?>
                                <form method="POST" class="delete-form" data-id="<?=$r['id']?>" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?=generateCSRF()?>">
                                    <input type="hidden" name="id" value="<?=$r['id']?>">
                                    <input type="hidden" name="delete_lead" value="1">
                                    <button type="submit" class="action-btn delete" title="Delete Lead">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php $sn++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if($totalPages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Showing <?=($offset+1)?> to <?=min($offset+$perPage, $totalRows)?> of <?=$totalRows?> entries
            </div>
            <div class="pagination">
                <?php if($page > 1): ?>
                <a href="<?=$baseUrl?>&p=<?=($page-1)?>" class="page-link prev">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for($i = $start; $i <= $end; $i++):
                ?>
                <a href="<?=$baseUrl?>&p=<?=$i?>" class="page-link <?=$i==$page?'active':''?>"><?=$i?></a>
                <?php endfor; ?>
                
                <?php if($page < $totalPages): ?>
                <a href="<?=$baseUrl?>&p=<?=($page+1)?>" class="page-link next">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Professional UI Redesign - Pink Theme */
:root {
    --primary: #e91e63;
    --primary-light: #fce4ec;
    --primary-dark: #c2185b;
    --secondary: #6c757d;
    --success: #2e7d32;
    --danger: #dc3545;
    --warning: #ff9800;
    --info: #2196f3;
    --dark: #343a40;
    --light: #f8f9fa;
    --border: #e9ecef;
    --text: #495057;
    --text-light: #6c757d;
    --white: #ffffff;
    --shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-hover: 0 10px 15px rgba(0, 0, 0, 0.1);
    --radius: 12px;
    --radius-sm: 8px;
}

* {
    box-sizing: border-box;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    background: #f4f7fc;
    color: var(--text);
    line-height: 1.5;
}

.leads-dashboard {
    max-width: 1600px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.dashboard-header h2 {
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--dark);
    display: flex;
    align-items: center;
}

.header-stats {
    background: white;
    padding: 10px 20px;
    border-radius: 40px;
    box-shadow: var(--shadow);
    font-weight: 500;
}

.stat-item {
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-item i {
    color: var(--primary);
}

/* Card Styles */
.card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.card-header {
    padding: 16px 20px;
    background: var(--light);
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    color: var(--dark);
    font-size: 1rem;
}

/* Filter Grid - Single Row */
.filter-form {
    padding: 20px;
}

.filter-grid {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-item {
    flex: 1 1 180px;
    min-width: 160px;
}

.filter-item label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: var(--text-light);
    margin-bottom: 6px;
}

.filter-item label i {
    color: var(--primary);
    font-size: 0.8rem;
}

.filter-item input,
.filter-item select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: 0.9rem;
    transition: all 0.2s;
    background: white;
}

.filter-item input:hover,
.filter-item select:hover {
    border-color: var(--primary);
}

.filter-item input:focus,
.filter-item select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-left: auto;
}

.btn-filter, .btn-reset, .btn-add, .btn-excel {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border-radius: var(--radius-sm);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.btn-filter {
    background: var(--primary);
    color: white;
    box-shadow: 0 2px 8px rgba(233, 30, 99, 0.3);
}

.btn-filter:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(233, 30, 99, 0.4);
}

.btn-reset {
    background: var(--secondary);
    color: white;
}

.btn-reset:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.btn-add {
    background: var(--success);
    color: white;
}

.btn-add:hover {
    background: #1e5f23;
    transform: translateY(-2px);
}

.btn-excel {
    background: #1e7e34;
    color: white;
}

.btn-excel:hover {
    background: #146c28;
    transform: translateY(-2px);
}

/* Entries Control */
.entries-control {
    display: flex;
    align-items: center;
    gap: 8px;
    background: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
}

.entries-select {
    padding: 4px 8px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.85rem;
    cursor: pointer;
}

/* Table Styles */
.table-container {
    padding: 20px;
    overflow-x: auto;
}

.leads-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.leads-table th {
    text-align: left;
    padding: 14px 16px;
    background: #fafbfc;
    color: var(--text-light);
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border-bottom: 2px solid var(--border);
}

.leads-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.leads-table tbody tr:hover {
    background: #fafbfc;
}

/* Lead Column */
.lead-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
}

.lead-interest {
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Contact Column */
.contact-phone, .contact-email {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
}

.contact-phone i, .contact-email i {
    color: var(--primary);
    width: 16px;
    font-size: 0.75rem;
}

.contact-email {
    color: var(--text-light);
    margin-top: 4px;
}

/* Academic Column */
.org-name {
    font-weight: 500;
    margin-bottom: 4px;
}

.dept-year {
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    background: rgba(33, 150, 243, 0.1);
    color: var(--info);
}

/* Dynamic status colors via inline style */
.status-badge[style*="2196f3"] {
    background: rgba(33, 150, 243, 0.1);
    color: #1976d2;
}
.status-badge[style*="ff9800"] {
    background: rgba(255, 152, 0, 0.1);
    color: #f57c00;
}
.status-badge[style*="9c27b0"] {
    background: rgba(156, 39, 176, 0.1);
    color: #7b1fa2;
}
.status-badge[style*="2e7d32"] {
    background: rgba(46, 125, 50, 0.1);
    color: #2e7d32;
}
.status-badge[style*="607d8b"] {
    background: rgba(96, 125, 139, 0.1);
    color: #546e7a;
}

/* Assigned Badge */
.assigned-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: #e9ecef;
    border-radius: 30px;
    font-size: 0.8rem;
    color: var(--dark);
}

/* Source Badge */
.source-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: #f1f3f5;
    border-radius: 30px;
    font-size: 0.8rem;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    color: white;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}

.action-btn.edit {
    background: var(--primary);
}
.action-btn.edit:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
}

.action-btn.convert {
    background: var(--warning);
    color: white;
}
.action-btn.convert:hover {
    background: #e68900;
    transform: translateY(-2px);
}

.action-btn.done {
    background: var(--success);
    opacity: 0.8;
    cursor: default;
}

.action-btn.delete {
    background: var(--danger);
}
.action-btn.delete:hover {
    background: #bd2130;
    transform: translateY(-2px);
}

/* Pagination */
.pagination-wrapper {
    padding: 20px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.pagination-info {
    color: var(--text-light);
    font-size: 0.85rem;
}

.pagination {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.page-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    background: white;
    color: var(--text);
    text-decoration: none;
    border: 1px solid var(--border);
    transition: all 0.2s;
    font-size: 0.9rem;
}

.page-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-link.prev, .page-link.next {
    background: #f8f9fa;
}

/* No Data */
.no-data {
    text-align: center;
    padding: 60px !important;
    color: var(--text-light);
    font-size: 1rem;
}

.no-data i {
    font-size: 2rem;
    display: block;
    margin-bottom: 12px;
    color: var(--border);
}

/* Alerts */
.alert-success, .alert-error {
    padding: 16px 20px;
    border-radius: var(--radius);
    margin-bottom: 20px;
    font-weight: 500;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive */
@media (max-width: 1200px) {
    .filter-grid {
        gap: 12px;
    }
    
    .filter-item {
        flex: 1 1 150px;
    }
}

@media (max-width: 992px) {
    .filter-actions {
        margin-left: 0;
        width: 100%;
        justify-content: flex-end;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .filter-grid {
        flex-direction: column;
    }
    
    .filter-item {
        width: 100%;
    }
    
    .filter-actions {
        justify-content: stretch;
    }
    
    .btn-filter, .btn-reset, .btn-add, .btn-excel {
        flex: 1;
        justify-content: center;
    }
    
    .pagination-wrapper {
        flex-direction: column;
        align-items: center;
    }
}


/* Icon-only buttons with modern tooltips */
.filter-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-left: auto;
}

.btn-icon-only {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    font-size: 1.1rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    color: white;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

/* Individual button colors */
.btn-icon-only.apply {
    background: #e91e63;
}
.btn-icon-only.apply:hover {
    background: #c2185b;
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(233, 30, 99, 0.3);
}

.btn-icon-only.reset {
    background: #6c757d;
}
.btn-icon-only.reset:hover {
    background: #5a6268;
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(108, 117, 125, 0.3);
}

.btn-icon-only.add {
    background: #2e7d32;
}
.btn-icon-only.add:hover {
    background: #1e5f23;
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(46, 125, 50, 0.3);
}

.btn-icon-only.import {
    background: #1e7e34;
}
.btn-icon-only.import:hover {
    background: #146c28;
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(30, 126, 52, 0.3);
}

/* Modern Tooltip */
.btn-icon-only::before {
    content: attr(title);
    position: absolute;
    bottom: 120%;
    left: 50%;
    transform: translateX(-50%) translateY(5px);
    background: #1e293b;
    color: white;
    font-size: 0.75rem;
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 8px;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    letter-spacing: 0.3px;
    pointer-events: none;
    z-index: 1000;
}

.btn-icon-only::after {
    content: '';
    position: absolute;
    bottom: 120%;
    left: 50%;
    transform: translateX(-50%) translateY(13px);
    border-width: 5px;
    border-style: solid;
    border-color: #1e293b transparent transparent transparent;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s ease;
    pointer-events: none;
    z-index: 1000;
}

.btn-icon-only:hover::before {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.btn-icon-only:hover::after {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(8px);
}

/* Active state for current page */
.btn-icon-only:active {
    transform: scale(0.95);
}

/* Responsive */
@media (max-width: 768px) {
    .filter-actions {
        width: 100%;
        justify-content: stretch;
    }
    
    .btn-icon-only {
        flex: 1;
        width: auto;
        height: 46px;
    }
}
</style>

<script>
// Per page change
function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('p', 1);
    window.location.href = url.toString();
}

document.addEventListener("DOMContentLoaded", function(){
    // Delete confirmation
    document.querySelectorAll('.delete-form').forEach(form=>{
        form.addEventListener('submit', function(e){
            e.preventDefault();
            
            let id = this.dataset.id;
            let csrf = this.querySelector('[name="csrf_token"]').value;
            
            Swal.fire({
                title: 'Delete Lead?',
                text: 'This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                confirmButtonColor: '#e91e63'
            }).then(result => {
                if(result.isConfirmed){
                    fetch('index.php?page=leads/list', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `delete_lead=1&id=${id}&csrf_token=${csrf}`
                    })
                    .then(res => res.text())
                    .then(data => {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Lead deleted successfully',
                            confirmButtonColor: '#e91e63'
                        }).then(() => {
                            location.reload();
                        });
                    })
                    .catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Something went wrong',
                            confirmButtonColor: '#e91e63'
                        });
                    });
                }
            });
        });
    });
});
</script>