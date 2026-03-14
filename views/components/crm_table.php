<?php
// ===============================
// ATS CRM - Reusable Data Table
// File: components/crm_table.php
// ===============================

if (!isset($rows)) {
    $rows = [];
}

$columns       = $columns ?? [];
$action_links  = $action_links ?? function($row){ return ''; };
$empty_message = $empty_message ?? 'No records found.';
$offset        = $offset ?? 0;

$start = $offset + 1;
?>

<div class="crm-table-wrapper">

<table class="crm-table">

<thead>
<tr>

<?php foreach ($columns as $col): ?>

<th>

<?php
$label    = is_array($col) ? ($col['label'] ?? '') : $col;
$key      = is_array($col) ? ($col['key'] ?? '') : '';
$sortable = is_array($col) ? ($col['sortable'] ?? false) : false;

if ($sortable && isset($_GET['page'])) {

    $currentSort = $_GET['sort'] ?? '';
    $dir         = ($_GET['dir'] ?? 'asc') === 'asc' ? 'desc' : 'asc';

    $url = "index.php?page=".$_GET['page']."&sort=$key&dir=$dir";

    echo '<a href="'.$url.'" style="text-decoration:none;color:#333;">'.$label.'</a>';

} else {

    echo htmlspecialchars($label);

}
?>

</th>

<?php endforeach; ?>

</tr>
</thead>

<tbody>

<?php if (empty($rows)): ?>

<tr>
<td colspan="<?= count($columns) ?>" style="text-align:center;padding:30px;color:#777;">
<?= htmlspecialchars($empty_message) ?>
</td>
</tr>

<?php else: ?>

<?php $i = $start; ?>

<?php foreach ($rows as $row): ?>

<tr>

<?php foreach ($columns as $col):

$key  = is_array($col) ? ($col['key'] ?? '') : $col;
$type = is_array($col) ? ($col['type'] ?? '') : '';

?>

<?php if ($key === '#'): ?>

<td><?= $i++ ?></td>

<?php elseif ($key === 'actions'): ?>

<td style="white-space:nowrap;">
<?= $action_links($row) ?>
</td>

<?php elseif ($type === 'status'): ?>

<td>

<?php if ((int)($row['status'] ?? 0) === 1): ?>

<span style="color:green;font-weight:600;">Active</span>

<?php else: ?>

<span style="color:red;font-weight:600;">Inactive</span>

<?php endif; ?>

</td>

<?php else: ?>

<td><?= htmlspecialchars($row[$key] ?? '-') ?></td>

<?php endif; ?>

<?php endforeach; ?>

</tr>

<?php endforeach; ?>

<?php endif; ?>

</tbody>

</table>

</div>
