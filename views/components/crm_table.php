<?php
/*
Reusable CRM Table Component

Required Variables:

$tableId
$columns
$dataUrl
*/

?>

<div class="crm-table-card">

<div class="crm-table-header">

<input type="text"
class="crm-search"
placeholder="Search..."
onkeyup="crmTableSearch('<?= $tableId ?>',this.value)">

</div>

<div class="crm-table-wrapper">

<table class="crm-table" id="<?= $tableId ?>">

<thead>
<tr>

<?php foreach($columns as $col): ?>

<th><?= $col ?></th>

<?php endforeach; ?>

</tr>
</thead>

<tbody>

<tr>
<td colspan="<?= count($columns) ?>" style="text-align:center;padding:20px">
<i class="fas fa-spinner fa-spin"></i> Loading...
</td>
</tr>

</tbody>

</table>

</div>

<div class="crm-pagination" id="<?= $tableId ?>_pagination"></div>

</div>


<script>

function crmLoadTable(tableId,url,page=1,search=""){

let table=document.querySelector("#"+tableId+" tbody");

table.innerHTML=
`<tr>
<td colspan="10" style="text-align:center;padding:20px">
<i class="fas fa-spinner fa-spin"></i> Loading...
</td>
</tr>`;

fetch(url+"&page="+page+"&search="+search)

.then(res=>res.json())

.then(data=>{

table.innerHTML=data.rows;

document.getElementById(tableId+"_pagination").innerHTML=data.pagination;

});

}


function crmTableSearch(tableId,value){

let url=document.getElementById(tableId).dataset.url;

crmLoadTable(tableId,url,1,value);

}


function crmTablePage(tableId,page){

let url=document.getElementById(tableId).dataset.url;

let search=document.querySelector(".crm-search").value;

crmLoadTable(tableId,url,page,search);

}

</script>