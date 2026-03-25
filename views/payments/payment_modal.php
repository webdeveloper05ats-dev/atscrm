<?php

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$regId = (int)($_GET['reg_id'] ?? 0);

if ($regId <= 0) {
    echo "<div class='empty-note'>Invalid registration.</div>";
    exit;
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$canAllBranches = 0;

try {

$r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
$r->execute([$roleId]);
$canAllBranches = (int)$r->fetchColumn();

} catch(Exception $e){}

try {

/* ===============================
FETCH REGISTRATION
=============================== */

if ($canAllBranches !== 1 && $branchId > 0) {

$st = $pdo->prepare("
SELECT r.*, u.name AS owner_name
FROM registrations r
LEFT JOIN users u ON u.id = r.assigned_to
WHERE r.id=? AND r.branch_id=?
LIMIT 1
");

$st->execute([$regId,$branchId]);

} else {

$st = $pdo->prepare("
SELECT r.*, u.name AS owner_name
FROM registrations r
LEFT JOIN users u ON u.id = r.assigned_to
WHERE r.id=?
LIMIT 1
");

$st->execute([$regId]);

}

$reg = $st->fetch(PDO::FETCH_ASSOC);

if(!$reg){
throw new Exception("Registration not found.");
}

/* ===============================
FETCH PAYMENTS
=============================== */

$st = $pdo->prepare("
SELECT *
FROM registration_payments
WHERE registration_id=?
ORDER BY id DESC
");

$st->execute([$regId]);

$payments = $st->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
CALCULATIONS
=============================== */

$finalFee = (float)$reg['final_fee'];
$paidAmt  = (float)$reg['paid_amount'];
$balance  = max(0,$finalFee-$paidAmt);

?>

<div class="pro-modal-wrap">

<!-- ===============================
PAYMENT SUMMARY
=============================== -->

<div class="pro-payment-summary">

<div class="pro-pay-card">
<span class="label">Registration</span>
<span class="value"><?= htmlspecialchars($reg['registration_no'] ?: 'REG-'.$reg['id']) ?></span>
</div>

<div class="pro-pay-card">
<span class="label">Student</span>
<span class="value"><?= htmlspecialchars($reg['enquiry_snapshot_name']) ?></span>
</div>

<div class="pro-pay-card">
<span class="label">Final Fee</span>
<span class="value">₹ <?= number_format($finalFee,2) ?></span>
</div>

<div class="pro-pay-card">
<span class="label">Paid</span>
<span class="value">₹ <?= number_format($paidAmt,2) ?></span>
</div>

<div class="pro-pay-card highlight">
<span class="label">Balance</span>
<span class="value">₹ <?= number_format($balance,2) ?></span>
</div>

</div>


<!-- ===============================
ADD PAYMENT
=============================== -->

<div class="pro-modal-section">

<div class="pro-modal-head">

<div class="pro-modal-head-icon">
<i class="fas fa-plus"></i>
</div>

<div>
<div class="pro-modal-title">Add Payment</div>
<div class="pro-modal-subtitle">Enter payment details carefully</div>
</div>

</div>

<form method="post" action="index.php?page=payments/index">

<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF(), ENT_QUOTES, 'UTF-8') ?>">
<input type="hidden" name="save_payment" value="1">

<input type="hidden" name="registration_id" value="<?= $regId ?>">

<div class="pro-payment-grid">

<div>
<label class="m-label">Amount</label>
<input type="number" step="0.01" name="amount" class="m-input" required>
</div>

<div>
<label class="m-label">Payment Date</label>
<input type="date" name="payment_date" class="m-input" value="<?= date('Y-m-d') ?>">
</div>

<div>
<label class="m-label">Payment Mode</label>
<select name="payment_mode" class="m-input">
<option value="Cash">Cash</option>
<option value="UPI">UPI</option>
<option value="Card">Card</option>
<option value="Bank Transfer">Bank Transfer</option>
</select>
</div>

<div>
<label class="m-label">Payment Type</label>
<select name="payment_type" class="m-input">
<option value="partial">Partial</option>
<option value="full">Full</option>
</select>
</div>

<div>
<label class="m-label">Reference No</label>
<input type="text" name="reference_no" class="m-input">
</div>

<div class="full">
<label class="m-label">Remarks</label>
<textarea name="remarks" class="m-input" rows="3"></textarea>
</div>

</div>

<div class="modal-actions">
<button type="submit" class="btn-add-payment">
Save Payment
</button>
</div>

</form>

</div>


<!-- ===============================
PAYMENT HISTORY
=============================== -->

<div class="pro-modal-section">

<div class="pro-modal-head">

<div class="pro-modal-head-icon">
<i class="fas fa-history"></i>
</div>

<div>
<div class="pro-modal-title">Payment History</div>
</div>

</div>

<?php if(!$payments): ?>

<div class="empty-note">
No payments recorded yet.
</div>

<?php else: ?>

<div class="pro-table-scroll">

<table class="pro-mini-table">

<thead>
<tr>
<th>Date</th>
<th>Amount</th>
<th>Mode</th>
<th>Type</th>
<th>Reference</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php foreach($payments as $p): ?>

<tr>

<td><?= htmlspecialchars($p['payment_date']) ?></td>

<td>₹ <?= number_format($p['amount'],2) ?></td>

<td><?= htmlspecialchars($p['payment_mode']) ?></td>

<td><?= htmlspecialchars($p['payment_type']) ?></td>

<td><?= htmlspecialchars($p['reference_no'] ?: '-') ?></td>

<td><?= htmlspecialchars($p['approval_status']) ?></td>
<td>

<a
class="receipt-link"
target="_blank"
href="index.php?page=payments/receipt&payment_id=<?= (int) $p['id'] ?>">

<i class="fas fa-print"></i>
Receipt

</a>

</td>
</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>

<?php

} catch(Exception $e){

echo "<div class='empty-note error-note'>".$e->getMessage()."</div>";

}
?>
