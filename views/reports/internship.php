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
              AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'hr', 'marketing', 'corporate')
            ORDER BY r.role_name ASC, u.name ASC
        ");
        $staffOptions = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $staffOptions = [];
    }
}
?>

<style>

.reports-dashboard{
max-width:1400px;
margin:0 auto;
padding:12px;
}

.dashboard-header{
display:flex;
align-items:center;
justify-content:space-between;
gap:16px;
margin-bottom:14px;
}

.dashboard-header h2{
margin:0;
font-size:32px;
font-weight:800;
color:#1f2940;
}

.header-stats{
display:flex;
align-items:center;
gap:10px;
}

.stat-item{
display:inline-flex;
align-items:center;
gap:8px;
padding:10px 14px;
background:#fff;
border:1px solid #f2d8e4;
border-radius:999px;
box-shadow:0 4px 12px rgba(0,0,0,.04);
font-size:14px;
font-weight:600;
color:#4c566a;
}

/* ===============================
FILTER
=============================== */

.card{
background:#fff;
border:1px solid #f1d6e3;
border-radius:16px;
box-shadow:0 8px 20px rgba(0,0,0,.05);
margin-bottom:14px;
}

.card-header{
padding:18px 20px;
border-bottom:1px solid #f6e6ee;
font-weight:700;
color:#24324a;
background:linear-gradient(180deg, rgba(255,255,255,.95), rgba(255,249,252,.9));
}

.filter-form{
padding:18px 20px 20px;
}

.filter-grid{
display:flex;
flex-wrap:wrap;
gap:14px;
}

.filter-item{
flex:1;
min-width:200px;
}

.filter-item label{
display:flex;
align-items:center;
gap:8px;
margin-bottom:8px;
font-size:12px;
font-weight:700;
letter-spacing:.04em;
text-transform:uppercase;
color:#6b7280;
}

.filter-item label i{
color:#e91e63;
}

.filter-actions{
display:flex;
align-items:flex-end;
gap:10px;
margin-left:auto;
}

.btn-icon-only{
width:44px;
height:44px;
border:none;
border-radius:12px;
display:inline-flex;
align-items:center;
justify-content:center;
text-decoration:none;
cursor:pointer;
transition:transform .18s ease, box-shadow .18s ease, background .18s ease;
box-shadow:0 8px 18px rgba(233,30,99,.18);
}

.btn-icon-only:hover{
transform:translateY(-1px);
}

.btn-icon-only.apply{
background:#e91e63;
color:#fff;
}

.btn-icon-only.reset{
background:#fff;
color:#e91e63;
border:1px solid #f2bfd2;
box-shadow:none;
}

.btn-icon-only.export{
background:#2e7d32;
color:#fff;
box-shadow:0 8px 18px rgba(46,125,50,.18);
}

@media(max-width:900px){

.filter-grid{
flex-direction:column;
}

.filter-actions{
width:100%;
margin-left:0;
}

.filter-actions button,
.filter-actions a{
flex:1;
text-align:center;
}

}

/* ===============================
CARD
=============================== */

.crm-card {
background:#fff;
border-radius:16px;
padding:0;
box-shadow:0 8px 20px rgba(0,0,0,.05);
border:1px solid #f1d6e3;
}

.table-header-flex{
display:flex;
align-items:center;
justify-content:space-between;
gap:16px;
flex-wrap:wrap;
}

.table-title{
display:flex;
align-items:center;
gap:10px;
font-weight:700;
color:#24324a;
}

#datatableControls{
display:flex;
align-items:center;
justify-content:flex-end;
gap:12px;
margin-left:auto;
}

.table-container{
padding:18px 20px 20px;
overflow-x:auto;
}

.report-table-wrap{
position:relative;
}

.report-loader{
position:absolute;
top:16px;
left:50%;
transform:translateX(-50%) translateY(-8px);
display:flex;
align-items:center;
justify-content:center;
z-index:5;
opacity:0;
visibility:hidden;
pointer-events:none;
transition:opacity .18s ease, visibility .18s ease, transform .18s ease;
}

.report-loader.is-active{
opacity:1;
visibility:visible;
transform:translateX(-50%) translateY(0);
}

.report-loader-card{
display:flex;
align-items:center;
gap:12px;
padding:14px 18px;
background:#fff;
border:1px solid #f3c9d8;
border-radius:14px;
box-shadow:0 10px 24px rgba(233,30,99,.12);
color:#24324a;
font-size:14px;
font-weight:700;
}

.report-loader-spinner{
width:20px;
height:20px;
border:3px solid #f7cada;
border-top-color:#e91e63;
border-radius:50%;
animation:internshipReportSpin .8s linear infinite;
}

@keyframes internshipReportSpin{
to{
transform:rotate(360deg);
}
}

.crm-table{
width:100%;
border-collapse:separate;
border-spacing:0;
border:1px solid #f1d6e3;
border-radius:14px;
overflow:hidden;
background:#fff;
}

.crm-table th,
.crm-table td{
border:1px solid #f1d6e3;
padding:12px 14px;
font-size:13px;
white-space:nowrap;
}

.crm-table th{
background:#fff0f5;
font-weight:700;
color:#24324a;
}

/* ===============================
DATATABLE HEADER
=============================== */

.dt-top,
.dt-bottom{
display:flex;
align-items:center;
justify-content:space-between;
gap:14px;
width:100%;
}

.dt-top{
flex-wrap:wrap;
}

.dt-bottom{
margin-top:14px;
flex-wrap:wrap;
}

.dataTables_length,
.dataTables_filter,
.dataTables_info,
.dataTables_paginate{
float:none !important;
margin:0 !important;
}

.dt-top .dataTables_length,
.dt-top .dataTables_filter{
display:flex;
align-items:center;
gap:8px;
}

.dt-top .dataTables_length label,
.dt-top .dataTables_filter label{
display:flex;
align-items:center;
gap:8px;
font-size:13px;
font-weight:600;
color:#24324a;
margin:0;
}

.dt-top .dataTables_filter input{
width:210px;
height:38px;
margin:0 !important;
border:1px solid #f0c2d4;
border-radius:12px;
padding:0 12px;
}

.dt-top .dataTables_length select{
height:38px;
border:1px solid #f0c2d4;
border-radius:10px;
padding:0 10px;
background:#fff;
}

.dt-bottom .dataTables_info{
font-size:13px;
color:#616b7c;
}

.paginate_button{
border-radius:10px !important;
}

/* Fees styling */

.fee-total{
font-weight:600;
color:#444;
}

.fee-paid{
color:#1b9e77;
font-weight:600;
}

.fee-balance{
color:#e53935;
font-weight:600;
}

/* Status badges */

.status-badge{
display:inline-flex;
align-items:center;
gap:5px;
padding:4px 10px;
border-radius:20px;
font-size:12px;
font-weight:600;
}

.status-paid{
background:#e8f8f1;
color:#1b9e77;
}

.status-unpaid{
background:#ffecec;
color:#e53935;
}

.status-partial{
background:#fff4e5;
color:#f57c00;
}

/* Action button */

.action-btn{
display:inline-flex;
align-items:center;
justify-content:center;
width:34px;
height:34px;
border-radius:8px;
background:#e91e63;
color:#fff;
text-decoration:none;
transition:0.2s;
}

.action-btn:hover{
background:#c2185b;
}

.action-group{
display:inline-flex;
align-items:center;
gap:8px;
}

.action-btn.download-report{
background:#2e7d32;
}

.action-btn.download-report:hover{
background:#25672a;
}

@media(max-width:768px){
.dashboard-header{
flex-direction:column;
align-items:flex-start;
}

#datatableControls,
.dt-top,
.dt-bottom{
width:100%;
}

.dt-top .dataTables_filter input{
width:100%;
}
}

</style>



<?php
$programs = $pdo->query("
SELECT DISTINCT program_name
FROM registrations
WHERE program_name IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="reports-dashboard">

<div class="dashboard-header">
<h2><i class="fas fa-briefcase" style="margin-right:12px;color:#e91e63;"></i>Internship Report</h2>
<div class="header-stats">
<span class="stat-item"><i class="fas fa-file-alt"></i> Report View</span>
</div>
</div>

<!-- ===============================
FILTER FORM
=============================== -->

<div class="card">
<div class="card-header">
<i class="fas fa-sliders-h" style="margin-right:8px;"></i> Filter Internship Report
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

<button type="submit" class="btn-icon-only apply" title="Apply filters">
<i class="fas fa-filter"></i>
</button>

<a href="index.php?page=reports/internship" class="btn-icon-only reset" title="Reset filters">
<i class="fas fa-undo-alt"></i>
</a>

<a id="exportReport" class="btn-icon-only export" title="Export report">
<i class="fas fa-file-excel"></i>
</a>

</div>

</div>

</form>

</div>



<!-- ===============================
REPORT TABLE
=============================== -->

<div class="crm-card">

<div class="card-header">
<div class="table-header-flex">
<div class="table-title">
<i class="fas fa-list"></i> Internship List
</div>
<div id="datatableControls"></div>
</div>
</div>

<div class="report-table-wrap">
<div id="reportLoader" class="report-loader" aria-hidden="true">
<div class="report-loader-card">
<span class="report-loader-spinner" aria-hidden="true"></span>
<span>Loading internship report...</span>
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
searchPlaceholder:"Search internship report...",
dom:
"<'dt-top'lf>" +
"rt" +
"<'dt-bottom'ip>",

ajax:{
url:'index.php?page=reports/ajax_internship&ajax=1',
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


/* ===============================
FILTER SUBMIT
=============================== */

document.getElementById('reportFilter')
.addEventListener('submit',function(e){

e.preventDefault();

setReportLoading(true);
table.ajax.reload();

});



/* ===============================
EXPORT REPORT
=============================== */

document.getElementById("exportReport")
.addEventListener("click",function(){

let date_from=document.querySelector('[name="date_from"]').value;
let date_to=document.querySelector('[name="date_to"]').value;
let program=document.querySelector('[name="program"]').value;
let payment_status=document.querySelector('[name="payment_status"]').value;
let staffField=document.querySelector('[name="staff_id"]');
let staff_id=staffField ? staffField.value : '';

let url="index.php?page=reports/export_internship"
+"&date_from="+encodeURIComponent(date_from)
+"&date_to="+encodeURIComponent(date_to)
+"&program="+encodeURIComponent(program)
+"&payment_status="+encodeURIComponent(payment_status)
+"&staff_id="+encodeURIComponent(staff_id);

window.location.href=url;

});
</script>

