<?php
if (!defined('APP_NAME')) die("Unauthorized");

$reportId = (int)($_GET['report_id'] ?? 0);
?>

<div class="crm-page">

<h2>📌 Follow-ups</h2>

<input type="text" id="searchCollege" placeholder="Search name..." class="form-control">

<div id="searchResults" class="list-group mt-2"></div>

<hr>

<form id="followupForm">

<input type="hidden" name="report_id" value="<?= $reportId ?>">

<table class="table table-bordered no-mobile-cards">
<thead>
<tr>
<th>Name</th>
<th>Status</th>
<th>Remarks</th>
<th>Next Date</th>
<th>Action</th>
</tr>
</thead>

<tbody id="followupTable"></tbody>
</table>

<button class="btn btn-success">💾 Save All</button>

</form>

</div>

