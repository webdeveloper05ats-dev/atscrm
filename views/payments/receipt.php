<?php
// =====================================
// Payments - Receipt View / Print
// Slug: payments/receipt
// File: views/payments/receipt.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$error = "";

if (!function_exists('h')) {
    function h($v){
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

/* =========================================================
   Session / Scope
========================================================= */
$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$roleName = (string)($_SESSION['role_name'] ?? '');
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$canAllBranches = 0;
try {
    $r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $r->execute([$roleId]);
    $canAllBranches = (int)($r->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

/* =========================================================
   Get Payment ID
========================================================= */
$paymentId = (int)($_GET['payment_id'] ?? 0);

$payment = null;

if ($paymentId <= 0) {
    $error = "Invalid payment ID.";
} else {
    try {
        if ($canAllBranches !== 1 && $branchId > 0) {
            $st = $pdo->prepare("
                SELECT
                    p.*,
                    r.id AS registration_id,
                    r.registration_no,
                    r.reg_type,
                    r.program_name,
                    r.batch_name,
                    r.total_fee,
                    r.discount_amount,
                    r.final_fee,
                    r.paid_amount,
                    r.balance_amount,
                    r.payment_status,
                    r.registration_status,
                    r.enquiry_snapshot_name,
                    r.enquiry_snapshot_phone,
                    r.enquiry_snapshot_email,
                    r.joined_on,

                    e.enquiry_no,
                    e.name AS enquiry_name,
                    e.phone AS enquiry_phone,
                    e.email AS enquiry_email,

                    u1.name AS owner_name,
                    u2.name AS collected_by_name,
                    u3.name AS approved_by_name

                FROM registration_payments p
                INNER JOIN registrations r ON r.id = p.registration_id
                LEFT JOIN enquiries e ON e.id = r.enquiry_id
                LEFT JOIN users u1 ON u1.id = r.assigned_to
                LEFT JOIN users u2 ON u2.id = p.collected_by
                LEFT JOIN users u3 ON u3.id = p.approved_by
                WHERE p.id=? AND p.branch_id=?
                LIMIT 1
            ");
            $st->execute([$paymentId, $branchId]);
        } else {
            $st = $pdo->prepare("
                SELECT
                    p.*,
                    r.id AS registration_id,
                    r.registration_no,
                    r.reg_type,
                    r.program_name,
                    r.batch_name,
                    r.total_fee,
                    r.discount_amount,
                    r.final_fee,
                    r.paid_amount,
                    r.balance_amount,
                    r.payment_status,
                    r.registration_status,
                    r.enquiry_snapshot_name,
                    r.enquiry_snapshot_phone,
                    r.enquiry_snapshot_email,
                    r.joined_on,

                    e.enquiry_no,
                    e.name AS enquiry_name,
                    e.phone AS enquiry_phone,
                    e.email AS enquiry_email,

                    u1.name AS owner_name,
                    u2.name AS collected_by_name,
                    u3.name AS approved_by_name

                FROM registration_payments p
                INNER JOIN registrations r ON r.id = p.registration_id
                LEFT JOIN enquiries e ON e.id = r.enquiry_id
                LEFT JOIN users u1 ON u1.id = r.assigned_to
                LEFT JOIN users u2 ON u2.id = p.collected_by
                LEFT JOIN users u3 ON u3.id = p.approved_by
                WHERE p.id=?
                LIMIT 1
            ");
            $st->execute([$paymentId]);
        }

        $payment = $st->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception("Receipt not found or access denied.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<style>
.receipt-page{
  max-width: 950px;
  margin: 0 auto;
}
.receipt-toolbar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  flex-wrap:wrap;
  margin-bottom:16px;
}
.receipt-card{
  background:#fff;
  border:1px solid rgba(0,0,0,.08);
  border-radius:18px;
  box-shadow:0 12px 28px rgba(0,0,0,.05);
  overflow:hidden;
}
.receipt-head{
  padding:20px 22px;
  border-bottom:1px solid #f1f1f1;
  background:linear-gradient(135deg, rgba(233,30,99,.06), rgba(3,169,244,.04));
}
.receipt-title{
  font-size:24px;
  font-weight:900;
  color:#111;
  margin-bottom:6px;
}
.receipt-sub{
  color:#666;
  font-size:13px;
}
.receipt-body{
  padding:22px;
}
.receipt-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
}
.receipt-box{
  border:1px solid #ececec;
  border-radius:14px;
  padding:14px;
  background:#fff;
}
.receipt-box.full{
  grid-column:1 / -1;
}
.receipt-box-title{
  font-size:14px;
  font-weight:900;
  margin-bottom:10px;
  color:#111;
}
.receipt-row{
  display:flex;
  justify-content:space-between;
  gap:16px;
  padding:7px 0;
  border-bottom:1px dashed #f1f1f1;
}
.receipt-row:last-child{
  border-bottom:none;
}
.receipt-label{
  font-size:13px;
  color:#666;
  font-weight:700;
}
.receipt-value{
  font-size:13px;
  color:#111;
  font-weight:800;
  text-align:right;
}
.money-big{
  font-size:28px;
  font-weight:900;
  color:#111;
}
.money-sub{
  margin-top:6px;
  font-size:13px;
  color:#666;
}
.receipt-footer{
  margin-top:20px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
}
.sign-box{
  border:1px dashed #ddd;
  border-radius:14px;
  min-height:90px;
  padding:14px;
  display:flex;
  flex-direction:column;
  justify-content:flex-end;
  background:#fff;
}
.sign-label{
  font-size:13px;
  color:#777;
  font-weight:700;
}
.print-hide{
  display:block;
}
@media (max-width: 900px){
  .receipt-grid, .receipt-footer{
    grid-template-columns:1fr;
  }
}
@media print{
  body{
    background:#fff !important;
  }
  .print-hide{
    display:none !important;
  }
  .receipt-page{
    max-width:100%;
    margin:0;
  }
  .receipt-card{
    box-shadow:none;
    border:1px solid #ccc;
  }
}
</style>

<div class="receipt-page">
  <div class="receipt-toolbar print-hide">
    <h2 style="margin:0;">Payment Receipt</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
      </button>
      <a href="index.php?page=registrations/list" class="btn btn-light">
        Back to Registrations
      </a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="receipt-card">
      <div class="receipt-body">
        <div style="color:#d32f2f;font-weight:800;"><?= h($error) ?></div>
      </div>
    </div>
  <?php else: ?>

    <?php
      $studentName = $payment['enquiry_snapshot_name'] ?: $payment['enquiry_name'] ?: '-';
      $studentPhone = $payment['enquiry_snapshot_phone'] ?: $payment['enquiry_phone'] ?: '-';
      $studentEmail = $payment['enquiry_snapshot_email'] ?: $payment['enquiry_email'] ?: '-';
      $receiptNo = $payment['receipt_no'] ?: ('RCPT-' . (int)$payment['id']);
      $paymentAmount = (float)($payment['amount'] ?? 0);
      $finalFee = (float)($payment['final_fee'] ?? 0);
      $paidAmount = (float)($payment['paid_amount'] ?? 0);
      $balanceAmount = (float)($payment['balance_amount'] ?? 0);
    ?>

    <div class="receipt-card">
      <div class="receipt-head">
        <div class="receipt-title">Student Payment Receipt</div>
        <div class="receipt-sub">
          Receipt No: <b><?= h($receiptNo) ?></b> &nbsp;|&nbsp;
          Payment Date: <b><?= h($payment['payment_date'] ?? '-') ?></b>
        </div>
      </div>

      <div class="receipt-body">
        <div class="receipt-grid">
          <div class="receipt-box">
            <div class="receipt-box-title">Student Details</div>
            <div class="receipt-row">
              <div class="receipt-label">Student Name</div>
              <div class="receipt-value"><?= h($studentName) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Phone</div>
              <div class="receipt-value"><?= h($studentPhone) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Email</div>
              <div class="receipt-value"><?= h($studentEmail) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Enquiry No</div>
              <div class="receipt-value"><?= h($payment['enquiry_no'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Registration No</div>
              <div class="receipt-value"><?= h($payment['registration_no'] ?: '-') ?></div>
            </div>
          </div>

          <div class="receipt-box">
            <div class="receipt-box-title">Course / Registration Details</div>
            <div class="receipt-row">
              <div class="receipt-label">Type</div>
              <div class="receipt-value"><?= h(ucfirst($payment['reg_type'] ?: '-')) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Program</div>
              <div class="receipt-value"><?= h($payment['program_name'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Batch</div>
              <div class="receipt-value"><?= h($payment['batch_name'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Joined On</div>
              <div class="receipt-value"><?= h($payment['joined_on'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Registration Status</div>
              <div class="receipt-value"><?= h(ucfirst($payment['registration_status'] ?: '-')) ?></div>
            </div>
          </div>

          <div class="receipt-box">
            <div class="receipt-box-title">Payment Details</div>
            <div class="receipt-row">
              <div class="receipt-label">Amount Received</div>
              <div class="receipt-value">₹ <?= h(number_format($paymentAmount, 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Payment Mode</div>
              <div class="receipt-value"><?= h($payment['payment_mode'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Payment Type</div>
              <div class="receipt-value"><?= h(ucfirst($payment['payment_type'] ?: '-')) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Reference No</div>
              <div class="receipt-value"><?= h($payment['reference_no'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Approval Status</div>
              <div class="receipt-value"><?= h(ucfirst($payment['approval_status'] ?: '-')) ?></div>
            </div>
          </div>

          <div class="receipt-box">
            <div class="receipt-box-title">Fee Summary</div>
            <div class="receipt-row">
              <div class="receipt-label">Total Fee</div>
              <div class="receipt-value">₹ <?= h(number_format((float)($payment['total_fee'] ?? 0), 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Discount</div>
              <div class="receipt-value">₹ <?= h(number_format((float)($payment['discount_amount'] ?? 0), 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Final Fee</div>
              <div class="receipt-value">₹ <?= h(number_format($finalFee, 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Total Paid</div>
              <div class="receipt-value">₹ <?= h(number_format($paidAmount, 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Balance</div>
              <div class="receipt-value">₹ <?= h(number_format($balanceAmount, 2)) ?></div>
            </div>
          </div>

          <div class="receipt-box full" style="text-align:center;background:#fff7fb;">
            <div class="money-big">₹ <?= h(number_format($paymentAmount, 2)) ?></div>
            <div class="money-sub">Received from the student toward the above registration.</div>
          </div>

          <div class="receipt-box full">
            <div class="receipt-box-title">Collection Information</div>
            <div class="receipt-row">
              <div class="receipt-label">Front Office Owner</div>
              <div class="receipt-value"><?= h($payment['owner_name'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Collected By</div>
              <div class="receipt-value"><?= h($payment['collected_by_name'] ?: '-') ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Approved By</div>
              <div class="receipt-value"><?= h($payment['approved_by_name'] ?: '-') ?></div>
            </div>
            <?php if (!empty($payment['remarks'])): ?>
            <div class="receipt-row">
              <div class="receipt-label">Remarks</div>
              <div class="receipt-value"><?= nl2br(h($payment['remarks'])) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="receipt-footer">
          <div class="sign-box">
            <div class="sign-label">Authorized Signature</div>
          </div>
          <div class="sign-box">
            <div class="sign-label">Student Signature</div>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>