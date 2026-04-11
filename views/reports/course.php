<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$roleName = $_SESSION['role_name'] ?? '';
$canFilterStaff = in_array($roleName, ['Super Admin', 'HR'], true);
$staffOptions = [];
if ($canFilterStaff) {
    try {
        $staffStmt = $pdo->query("
            SELECT u.id, u.name, COALESCE(r.role_name, '-') AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.status = 1
              AND LOWER(COALESCE(r.role_name, '')) = 'staff'
            ORDER BY r.role_name ASC, u.name ASC
        ");
        $staffOptions = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $staffOptions = [];
    }
}
?>

<?php
$programs = $pdo->query("
SELECT DISTINCT program_name
FROM registrations
WHERE program_name IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="reports-dashboard">

<div class="dashboard-header">
<h2><i class="fas fa-book-open" style="margin-right:12px;color:#e91e63;"></i>Course Report</h2>
<div class="header-stats">
<span class="stat-item"><i class="fas fa-file-alt"></i> Report View</span>
</div>
</div>

<div class="card">
<div class="card-header">
<i class="fas fa-sliders-h" style="margin-right:8px;"></i> Filter Course Report
</div>

<form id="reportFilter" class="filter-form">
<div class="filter-grid">

<div class="filter-item">
<label><i class="fas fa-calendar-alt"></i> Date From</label>
<input type="date" name="date_from" class="form-control">
</div>

<div class="filter-item">
<label><i class="fas fa-calendar-alt"></i> Date To</label>
<input type="date" name="date_to" class="form-control">
</div>

<div class="filter-item">
<label><i class="fas fa-graduation-cap"></i> Program</label>
<select name="program" class="form-control">
<option value="">All Programs</option>
<?php foreach($programs as $p): ?>
<option value="<?= htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$p, ENT_QUOTES, 'UTF-8') ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="filter-item">
<label><i class="fas fa-wallet"></i> Payment Status</label>
<select name="payment_status" class="form-control">
<option value="">All</option>
<option value="paid">Paid</option>
<option value="partial">Partial</option>
<option value="unpaid">Unpaid</option>
</select>
</div>

<?php if ($canFilterStaff): ?>
<div class="filter-item">
<label><i class="fas fa-user"></i> Staff</label>
<select name="staff_id" class="form-control">
<option value="">All Staff</option>
<?php foreach($staffOptions as $staff): ?>
<option value="<?= (int)$staff['id'] ?>"><?= htmlspecialchars((string)$staff['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)($staff['role_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>)</option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<div class="filter-actions">
<button type="submit" class="btn-icon-only apply" title="Apply filters" data-mobile-label="Apply">
<i class="fas fa-filter"></i>
</button>

<a href="index.php?page=reports/course" class="btn-icon-only reset" title="Reset filters" data-mobile-label="Reset">
<i class="fas fa-undo-alt"></i>
</a>

<a id="exportReport" class="btn-icon-only export" title="Export report" data-mobile-label="Export">
<i class="fas fa-file-excel"></i>
</a>
</div>

</div>
</form>
</div>

<div class="crm-card">
<div class="card-header">
<div class="table-header-flex">
<div class="table-title">
<i class="fas fa-list"></i> Course List
</div>
<div id="datatableControls"></div>
</div>
</div>

<div class="report-table-wrap">
<div id="reportLoader" class="report-loader" aria-hidden="true">
<div class="report-loader-card">
<span class="report-loader-spinner" aria-hidden="true"></span>
<span>Loading course report...</span>
</div>
</div>

<div class="table-container">
<table id="usersTable" class="crm-table">
<thead>
<tr>
<th>S.No</th>
<th>Student Details</th>
<th>Program</th>
<th>Fees</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>
<tbody></tbody>
</table>
</div>
</div>
</div>

</div>

<script>

let table;
const reportLoader = document.getElementById('reportLoader');

function setReportLoading(isLoading) {
if (!reportLoader) return;
reportLoader.classList.toggle('is-active', !!isLoading);
reportLoader.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
}

document.addEventListener("DOMContentLoaded", function(){

table = crmDataTable('#usersTable',{
pageLength:5,
lengthMenu:[5,10,20,50],
ordering:true,
order:[[0,'asc']],
export:false,
processing:true,
searchPlaceholder:"Search course report...",
dom:
"<'dt-top'lf>" +
"rt" +
"<'dt-bottom'ip>",
createdRow:function(row){
const labels=['S.No','Student Details','Program','Fees','Status','Action'];
const cells=row.querySelectorAll('td');
cells.forEach(function(td,idx){
td.setAttribute('data-label', labels[idx] || ('Column ' + (idx + 1)));
});
},
drawCallback:function(){
const labels=['S.No','Student Details','Program','Fees','Status','Action'];
document.querySelectorAll('#usersTable tbody tr').forEach(function(tr){
tr.querySelectorAll('td').forEach(function(td,idx){
if(!td.getAttribute('data-label')){
td.setAttribute('data-label', labels[idx] || ('Column ' + (idx + 1)));
}
});
});
},

ajax:{
url:'index.php?page=reports/ajax_course&ajax=1',
type:'POST',
data:function(d){
d.date_from = document.querySelector('[name="date_from"]').value;
d.date_to = document.querySelector('[name="date_to"]').value;
d.program = document.querySelector('[name="program"]').value;
d.payment_status = document.querySelector('[name="payment_status"]').value;
const staffField = document.querySelector('[name="staff_id"]');
d.staff_id = staffField ? staffField.value : '';
}
},

columnDefs:[
{ targets:[1,2,3], orderable:false }
]
});

setReportLoading(true);

$('#usersTable').on('preXhr.dt', function(){
setReportLoading(true);
});

$('#usersTable').on('xhr.dt', function(){
setReportLoading(false);
});

$('#usersTable').on('processing.dt', function(e, settings, processing){
setReportLoading(processing);
});

$('#usersTable').on('error.dt', function(){
setReportLoading(false);
});

setTimeout(() => {
const controls = document.querySelector('.dt-top');
const target = document.getElementById('datatableControls');
if (controls && target) {
target.appendChild(controls);
}
}, 100);

});

document.getElementById('reportFilter')
.addEventListener('submit',function(e){
e.preventDefault();
setReportLoading(true);
table.ajax.reload();
});

document.getElementById("exportReport")
.addEventListener("click",function(){
let date_from=document.querySelector('[name="date_from"]').value;
let date_to=document.querySelector('[name="date_to"]').value;
let program=document.querySelector('[name="program"]').value;
let payment_status=document.querySelector('[name="payment_status"]').value;
let staffField=document.querySelector('[name="staff_id"]');
let staff_id=staffField ? staffField.value : '';

let url="index.php?page=reports/export_course"
+"&date_from="+encodeURIComponent(date_from)
+"&date_to="+encodeURIComponent(date_to)
+"&program="+encodeURIComponent(program)
+"&payment_status="+encodeURIComponent(payment_status)
+"&staff_id="+encodeURIComponent(staff_id);

window.location.href=url;
});
</script>


