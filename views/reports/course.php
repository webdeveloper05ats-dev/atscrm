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

.crm-card{
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
animation:courseReportSpin .8s linear infinite;
}

@keyframes courseReportSpin{
to{
transform:rotate(360deg);
}
}

.table-container{
padding:18px 20px 20px;
overflow-x:auto;
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

.course-name{
font-weight:700;
color:#24324a;
}

.course-reg,
.course-phone,
.course-program-batch,
.course-program-date{
font-size:12px;
color:#616b7c;
line-height:1.35;
}

.course-program-name{
font-weight:700;
color:#24324a;
}

.course-fees{
display:flex;
flex-direction:column;
gap:2px;
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

.filter-actions .btn-icon-only[data-mobile-label]{
width:auto !important;
min-width:64px !important;
height:auto !important;
min-height:40px !important;
padding:6px 8px !important;
display:inline-flex !important;
flex-direction:column !important;
align-items:center !important;
justify-content:center !important;
gap:3px !important;
border-radius:10px !important;
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

#usersTable.crm-table tbody td[data-label="Action"] .action-group .action-btn[data-mobile-label]{
width:auto !important;
min-width:56px !important;
height:auto !important;
min-height:38px !important;
padding:6px 8px !important;
display:inline-flex !important;
flex-direction:column !important;
align-items:center !important;
justify-content:center !important;
gap:3px !important;
border-radius:10px !important;
}

#usersTable.crm-table tbody td[data-label="Action"] .action-group .action-btn[data-mobile-label]::before{
content:none !important;
display:none !important;
}

#usersTable.crm-table tbody td[data-label="Action"] .action-group .action-btn[data-mobile-label]::after{
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

@media (max-width:1024px){
#usersTable.crm-table{
width:100% !important;
table-layout:fixed !important;
border-collapse:separate !important;
border-spacing:0 !important;
}

#usersTable.crm-table thead{
display:none !important;
}

#usersTable.crm-table tbody{
display:block !important;
width:100% !important;
}

#usersTable.crm-table tbody tr{
display:block !important;
background:#fff !important;
border:1px solid #f0d6e2 !important;
border-radius:12px !important;
margin:0 0 12px 0 !important;
overflow:hidden !important;
}

#usersTable.crm-table tbody td{
display:flex !important;
align-items:flex-start !important;
justify-content:space-between !important;
gap:10px !important;
width:100% !important;
padding:10px 12px !important;
border-bottom:1px solid #f4e5ec !important;
white-space:normal !important;
word-break:normal !important;
overflow-wrap:normal !important;
text-align:right !important;
}

#usersTable.crm-table tbody td:last-child{
border-bottom:none !important;
}

#usersTable.crm-table tbody td::before{
content:attr(data-label) !important;
display:block !important;
flex:0 0 38% !important;
max-width:38% !important;
font-size:11px !important;
line-height:1.35 !important;
font-weight:700 !important;
letter-spacing:.25px !important;
text-transform:uppercase !important;
color:#7a6772 !important;
text-align:left !important;
}

#usersTable.crm-table tbody td .crm-card-value{
margin-left:auto !important;
display:flex !important;
flex-direction:column !important;
align-items:flex-end !important;
justify-content:center !important;
gap:2px !important;
min-width:0 !important;
max-width:62% !important;
text-align:right !important;
line-height:1.32 !important;
}

#usersTable.crm-table tbody td .crm-card-value,
#usersTable.crm-table tbody td .crm-card-value *{
word-break:keep-all !important;
overflow-wrap:break-word !important;
white-space:normal !important;
}

#usersTable.crm-table tbody td[data-label="S.No"] .crm-card-value,
#usersTable.crm-table tbody td[data-label="ID"] .crm-card-value{
font-weight:700 !important;
}

#usersTable.crm-table tbody td[data-label="Student Details"] .course-reg{
white-space:nowrap !important;
letter-spacing:.1px !important;
}

#usersTable.crm-table tbody td[data-label="Action"] .crm-card-value{
display:flex !important;
flex-direction:row !important;
justify-content:flex-end !important;
align-items:center !important;
gap:8px !important;
max-width:58% !important;
}

#usersTable.crm-table tbody td[data-label="Action"] .action-group{
display:inline-flex !important;
align-items:center !important;
gap:8px !important;
justify-content:flex-end !important;
}

#usersTable.crm-table tbody td[data-label="Action"] .action-group .action-btn[data-mobile-label]{
width:auto !important;
min-width:56px !important;
height:auto !important;
min-height:38px !important;
padding:6px 8px !important;
display:inline-flex !important;
flex-direction:column !important;
align-items:center !important;
justify-content:center !important;
gap:3px !important;
border-radius:10px !important;
}

#usersTable.crm-table tbody td[data-label="Action"] .action-group .action-btn[data-mobile-label]::before{
content:none !important;
display:none !important;
}

#usersTable.crm-table tbody td[data-label="Action"] .action-group .action-btn[data-mobile-label]::after{
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
}

@media (hover: none), (pointer: coarse), (any-pointer: coarse){
.filter-actions .btn-icon-only[data-mobile-label]{
width:auto !important;
min-width:64px !important;
height:auto !important;
min-height:40px !important;
padding:6px 8px !important;
display:inline-flex !important;
flex-direction:column !important;
align-items:center !important;
justify-content:center !important;
gap:3px !important;
border-radius:10px !important;
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
}


/* =====================================================
GLOBAL TYPOGRAPHY STYLECSS SYNC
font-family + font-size + font-weight only
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){font-size:clamp(2rem, 2.5vw, 2.4rem) !important;font-weight:700 !important;}
:where(h2,.h2,.section-title){font-size:clamp(1.6rem, 2vw, 2rem) !important;font-weight:600 !important;}
:where(h3,.h3,.card-header,.table-title){font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;font-weight:600 !important;}
:where(h4,.h4){font-size:1.2rem !important;font-weight:500 !important;}
:where(h5,.h5){font-size:1rem !important;font-weight:500 !important;}
:where(h6,.h6){font-size:0.9rem !important;font-weight:500 !important;}
:where(body){font-size:1rem !important;}
:where(p,.text-body,li,td,.text-muted,.help-text,.form-text,.small,small,.secondary-text){font-size:0.95rem !important;font-weight:400 !important;}
:where(.small,small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){font-size:0.85rem !important;font-weight:400 !important;}
:where(label,.form-label){font-size:0.85rem !important;font-weight:500 !important;}
:where(input,select,textarea,.form-control,.form-select){font-size:0.95rem !important;font-weight:400 !important;}
:where(input::placeholder,textarea::placeholder){font-weight:400 !important;}
:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){font-size:0.9rem !important;font-weight:600 !important;}
:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{font-size:0.75rem !important;font-weight:600 !important;}
:where(.table th,.crm-table th,.dataTables_wrapper th,th){font-size:0.75rem !important;font-weight:600 !important;}
:where(.table td,.dataTables_wrapper tbody td){font-size:0.9rem !important;}
:where(.dataTables_wrapper .dataTables_info){font-size:0.85rem !important;font-weight:400 !important;}
:where(.dataTables_wrapper .paginate_button){font-size:0.9rem !important;font-weight:600 !important;}
:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){font-weight:600 !important;}
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

