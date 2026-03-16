<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}
?>

<style>

/* ===============================
FILTER
=============================== */

.filter-grid{
display:flex;
flex-wrap:wrap;
gap:14px;
margin-bottom:20px;
}

.filter-grid > div{
flex:1;
min-width:200px;
}

.filter-actions{
display:flex;
align-items:flex-end;
gap:10px;
}

@media(max-width:900px){

.filter-grid{
flex-direction:column;
}

.filter-actions{
width:100%;
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
border-radius:14px;
padding:20px;
box-shadow:0 8px 20px rgba(0,0,0,.05);
border:1px solid #f1d6e3;
}

.crm-card h3{
margin-bottom:16px;
}

/* ===============================
TABLE
=============================== */

.crm-table-wrapper{
width:100%;
overflow-x:auto;
}

.crm-table{
width:100%;
border-collapse:collapse;
border:1px solid #f1d6e3;
}

.crm-table th,
.crm-table td{
border:1px solid #f1d6e3;
padding:10px;
font-size:13px;
white-space:nowrap;
}

.crm-table th{
background:#fff0f5;
font-weight:600;
}

/* ===============================
DATATABLE HEADER
=============================== */

.crm-table-header{
display:flex !important;
align-items:center;
justify-content:space-between;
flex-wrap:nowrap;
gap:20px;
width:100%;
}

.crm-table-header .dataTables_filter input{
width:220px;
margin-left:6px;
}

@media(max-width:768px){

.crm-table-header{
flex-wrap:wrap;
}

.crm-table-header .dataTables_filter input{
width:100%;
}

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

</style>



<!-- ===============================
FILTER FORM
=============================== -->

<form id="reportFilter">

<div class="filter-grid">

<div>
<label>Date From</label>
<input type="date" name="date_from" class="form-control">
</div>

<div>
<label>Date To</label>
<input type="date" name="date_to" class="form-control">
</div>

<div>
<label>Program</label>

<select name="program" class="form-control">

<option value="">All Programs</option>

<?php
$programs = $pdo->query("
SELECT DISTINCT program_name
FROM registrations
WHERE program_name IS NOT NULL
")->fetchAll(PDO::FETCH_COLUMN);

foreach($programs as $p){
echo "<option value='$p'>$p</option>";
}
?>

</select>

</div>

<div>

<label>Payment Status</label>

<select name="payment_status" class="form-control">

<option value="">All</option>
<option value="paid">Paid</option>
<option value="partial">Partial</option>
<option value="unpaid">Unpaid</option>

</select>

</div>

<div class="filter-actions">

<button type="submit" class="btn btn-primary">
Apply Filter
</button>

<a href="index.php?page=reports/internship" class="btn btn-secondary">
Reset
</a>

</div>

</div>

</form>



<!-- ===============================
REPORT TABLE
=============================== -->

<div class="crm-card">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">

<h3>Internship List</h3>

<a id="exportReport" class="crm-export-btn">
<i class="fa fa-file-excel"></i> Export Report
</a>

</div>

<div class="crm-table-wrapper">

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



<script>

let table;

document.addEventListener("DOMContentLoaded", function(){

table = crmDataTable('#usersTable',{

pageLength:5,
lengthMenu:[5,10,20,50],
ordering:true,
order:[[0,'asc']],
export:false,  

ajax:{
url:'index.php?page=reports/ajax_internship&ajax=1',
type:'POST',
data:function(d){

d.date_from = document.querySelector('[name="date_from"]').value;
d.date_to = document.querySelector('[name="date_to"]').value;
d.program = document.querySelector('[name="program"]').value;
d.payment_status = document.querySelector('[name="payment_status"]').value;

}
},

columnDefs:[
{ targets:[1,2,3], orderable:false }
]

});

});


/* ===============================
FILTER SUBMIT
=============================== */

document.getElementById('reportFilter')
.addEventListener('submit',function(e){

e.preventDefault();

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

let url="index.php?page=reports/export_internship"
+"&date_from="+encodeURIComponent(date_from)
+"&date_to="+encodeURIComponent(date_to)
+"&program="+encodeURIComponent(program)
+"&payment_status="+encodeURIComponent(payment_status);

window.location.href=url;

});
</script>

