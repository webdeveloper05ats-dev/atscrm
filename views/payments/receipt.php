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
$orgName = defined('APP_NAME') ? APP_NAME : 'ATS CRM';
$orgBranch = (string)($_SESSION['branch_name'] ?? 'Main Branch');
$orgAddress = '';
$orgPhone = '';
$orgEmail = '';

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

        // Optional branding details from branches table (if columns exist)
        try {
            $branchForReceipt = (int)($payment['branch_id'] ?? $branchId);
            if ($branchForReceipt > 0) {
                $columns = [];
                $colStmt = $pdo->query("SHOW COLUMNS FROM branches");
                foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
                    $columns[] = (string)($col['Field'] ?? '');
                }

                $nameCol = in_array('branch_name', $columns, true) ? 'branch_name' : null;
                $addressCol = in_array('address', $columns, true) ? 'address' : (in_array('branch_address', $columns, true) ? 'branch_address' : null);
                $phoneCol = in_array('phone', $columns, true) ? 'phone' : (in_array('contact_no', $columns, true) ? 'contact_no' : null);
                $emailCol = in_array('email', $columns, true) ? 'email' : null;

                $selectParts = ['id'];
                if ($nameCol) $selectParts[] = $nameCol . ' AS branch_name';
                if ($addressCol) $selectParts[] = $addressCol . ' AS branch_address';
                if ($phoneCol) $selectParts[] = $phoneCol . ' AS branch_phone';
                if ($emailCol) $selectParts[] = $emailCol . ' AS branch_email';

                $sqlBrand = "SELECT " . implode(', ', $selectParts) . " FROM branches WHERE id=? LIMIT 1";
                $bStmt = $pdo->prepare($sqlBrand);
                $bStmt->execute([$branchForReceipt]);
                $branchRow = $bStmt->fetch(PDO::FETCH_ASSOC);

                if ($branchRow) {
                    $orgBranch = (string)($branchRow['branch_name'] ?? $orgBranch);
                    $orgAddress = trim((string)($branchRow['branch_address'] ?? ''));
                    $orgPhone = trim((string)($branchRow['branch_phone'] ?? ''));
                    $orgEmail = trim((string)($branchRow['branch_email'] ?? ''));
                }
            }
        } catch (Exception $e) {
            // Keep defaults if branding details are unavailable.
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<style>
.receipt-page{
  max-width: 1040px;
  margin: 0 auto;
  padding: 8px 0 14px;
}
.receipt-toolbar{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:14px;
  flex-wrap:wrap;
  margin-bottom:18px;
  background:#fff;
  border:1px solid #f2d8e5;
  border-radius:16px;
  padding:12px 14px;
  box-shadow:0 8px 22px rgba(0,0,0,.05);
}
.receipt-card{
  background:linear-gradient(180deg,#fff 0%,#fffbfd 100%);
  border:1px solid #efd7e4;
  border-radius:20px;
  box-shadow:0 14px 30px rgba(233,30,99,.08);
  overflow:hidden;
}
.receipt-head{
  padding:18px 20px;
  border-bottom:1px solid #f3d9e6;
  background:linear-gradient(135deg,#fff4fa 0%,#fff 70%);
}
.receipt-brand{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:14px;
  flex-wrap:wrap;
  margin-bottom:12px;
  padding-bottom:12px;
  border-bottom:1px solid #f3d9e6;
}
.receipt-brand-left{
  display:flex;
  align-items:center;
  gap:12px;
}
.receipt-brand-logo{
  width:58px;
  height:58px;
  object-fit:contain;
  border-radius:10px;
  background:#fff;
  border:1px solid #f3d9e6;
  padding:6px;
}
.receipt-brand-title{
  margin:0;
  font-size:20px;
  font-weight:900;
  color:#be185d;
  line-height:1.1;
}
.receipt-brand-sub{
  margin-top:3px;
  font-size:12px;
  color:#6b7280;
  font-weight:700;
}
.receipt-brand-contact{
  text-align:right;
  font-size:12px;
  color:#6b7280;
  font-weight:700;
  line-height:1.45;
}
.receipt-title{
  font-size:22px;
  font-weight:800;
  color:#be185d;
  margin-bottom:7px;
  display:flex;
  align-items:center;
  gap:10px;
}
.receipt-sub{
  color:#6b7280;
  font-size:13px;
  font-weight:600;
}
.receipt-body{
  padding:22px;
}
.receipt-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}
.receipt-box{
  border:1px solid #f0dbe5;
  border-radius:16px;
  padding:14px;
  background:#fff;
  transition:all .2s ease;
}
.print-essential{}
.print-hide-box{}
.receipt-box:hover{
  transform:translateY(-1px);
  box-shadow:0 8px 16px rgba(233,30,99,.07);
}
.receipt-box.full{
  grid-column:1 / -1;
}
.receipt-box-title{
  font-size:13px;
  font-weight:800;
  margin-bottom:10px;
  color:#be185d;
  text-transform:uppercase;
  letter-spacing:.4px;
  border-bottom:1px solid #f5e4ec;
  padding-bottom:8px;
}
.receipt-row{
  display:flex;
  justify-content:space-between;
  gap:16px;
  padding:8px 0;
  border-bottom:1px dashed #f3dfe9;
}
.receipt-row:last-child{
  border-bottom:none;
}
.receipt-label{
  font-size:12px;
  color:#6b7280;
  font-weight:700;
}
.receipt-value{
  font-size:13px;
  color:#1f2937;
  font-weight:800;
  text-align:right;
}
.money-big{
  font-size:30px;
  font-weight:900;
  color:#be185d;
}
.money-sub{
  margin-top:6px;
  font-size:13px;
  color:#6b7280;
}
.receipt-footer{
  margin-top:20px;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:16px;
}
.receipt-print-footnote{
  margin-top:14px;
  border-top:1px dashed #efc8da;
  padding-top:10px;
  text-align:center;
  font-size:11px;
  color:#7a5a69;
  line-height:1.5;
}
.sign-box{
  border:1px dashed #efc8da;
  border-radius:14px;
  min-height:90px;
  padding:14px;
  display:flex;
  flex-direction:column;
  justify-content:flex-end;
  background:#fff;
}
.sign-label{
  font-size:12px;
  color:#7a5a69;
  font-weight:700;
}
.receipt-tool-title{
  margin:0;
  font-size:18px;
  font-weight:800;
  color:#be185d;
  display:flex;
  align-items:center;
  gap:8px;
}
.receipt-btn{
  border:none;
  border-radius:11px;
  padding:9px 13px;
  font-size:13px;
  font-weight:700;
  text-decoration:none !important;
  display:inline-flex;
  align-items:center;
  gap:8px;
  transition:all .2s ease;
}
.receipt-btn-primary{
  background:linear-gradient(135deg,#ff4d8d 0%,#e91e63 100%);
  color:#fff !important;
  box-shadow:0 8px 14px rgba(233,30,99,.22);
}
.receipt-btn-primary:hover{
  transform:translateY(-1px);
  color:#fff !important;
}
.receipt-btn-light{
  background:#fff;
  border:1px solid #e7cddb;
  color:#4b5563 !important;
}
.receipt-btn-light:hover{
  background:#fff6fa;
  color:#374151 !important;
}
.print-hide{
  display:block;
}
@media (max-width: 900px){
  .receipt-grid, .receipt-footer{
    grid-template-columns:1fr;
  }
}
@media (max-width: 640px){
  .receipt-toolbar{
    padding:10px 12px;
  }
  .receipt-body{
    padding:14px;
  }
  .receipt-title{
    font-size:18px;
  }
}
@media print{
  @page{
    size: A4;
    margin: 10mm;
  }
  html, body{
    width:100% !important;
    margin:0 !important;
    padding:0 !important;
    background:#fff !important;
  }
  body{
    background:#fff !important;
  }
  .sidebar,
  .topbar,
  .toggle-btn,
  .menu-toggle,
  .dashboard-header,
  .header,
  .navbar{
    display:none !important;
  }
  .wrapper,
  .content,
  .content.expanded,
  .main-content{
    display:block !important;
    width:100% !important;
    max-width:100% !important;
    margin:0 !important;
    padding:0 !important;
    transform:none !important;
  }
  .print-hide{
    display:none !important;
  }
  .receipt-page{
    width:100% !important;
    max-width:190mm !important;
    margin:0 auto !important;
    padding:0 !important;
  }
  .receipt-card{
    box-shadow:none;
    border:1px solid #ccc;
    break-inside: avoid;
    page-break-inside: avoid;
  }
  .receipt-head{
    padding:12px 14px !important;
  }
  .receipt-title{
    font-size:18px !important;
    margin-bottom:4px !important;
  }
  .receipt-sub{
    font-size:11px !important;
  }
  .receipt-body{
    padding:12px 14px !important;
  }
  .receipt-grid{
    grid-template-columns:1fr !important;
    gap:10px !important;
  }
  .receipt-box{
    border:1px solid #d9d9d9 !important;
    border-radius:10px !important;
    padding:10px !important;
  }
  .receipt-box-title{
    font-size:11px !important;
    padding-bottom:6px !important;
    margin-bottom:6px !important;
  }
  .receipt-row{
    padding:5px 0 !important;
  }
  .receipt-label,
  .receipt-value{
    font-size:11px !important;
  }
  .receipt-footer{
    margin-top:10px !important;
    gap:10px !important;
  }
  .sign-box{
    min-height:64px !important;
    padding:10px !important;
  }
  .print-hide-box{
    display:none !important;
  }
  .receipt-brand-logo{
    border:1px solid #ddd;
  }
  .receipt-print-footnote{
    margin-top:8px !important;
    padding-top:8px !important;
    font-size:10px !important;
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

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>

<div class="receipt-page">
  <div class="receipt-toolbar print-hide">
    <h2 class="receipt-tool-title"><i class="fas fa-receipt"></i> Payment Receipt</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button type="button" class="receipt-btn receipt-btn-primary" onclick="window.print()">
        <i class="fas fa-print"></i> Print Receipt
      </button>
      <a href="index.php?page=registrations/list" class="receipt-btn receipt-btn-light">
        <i class="fas fa-arrow-left"></i> Back to Registrations
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
      $studentPhone = visibleStudentContactValue($payment['enquiry_snapshot_phone'] ?: $payment['enquiry_phone'] ?: '-');
      $studentEmail = visibleStudentContactValue($payment['enquiry_snapshot_email'] ?: $payment['enquiry_email'] ?: '-');
      $receiptNo = $payment['receipt_no'] ?: ('RCPT-' . (int)$payment['id']);
      $paymentAmount = (float)($payment['amount'] ?? 0);
      $finalFee = (float)($payment['final_fee'] ?? 0);
      $paidAmount = (float)($payment['paid_amount'] ?? 0);
      $balanceAmount = (float)($payment['balance_amount'] ?? 0);
    ?>

    <div class="receipt-card">
      <div class="receipt-head">
        <div class="receipt-brand">
          <div class="receipt-brand-left">
            <img src="<?= h(BASE_URL) ?>assets/images/logo.png" alt="ATS Logo" class="receipt-brand-logo">
            <div>
              <h3 class="receipt-brand-title"><?= h($orgName) ?></h3>
              <div class="receipt-brand-sub"><?= h($orgBranch) ?></div>
            </div>
          </div>
          <div class="receipt-brand-contact">
            <?php if ($orgPhone !== ''): ?>Phone: <?= h($orgPhone) ?><br><?php endif; ?>
            <?php if ($orgEmail !== ''): ?>Email: <?= h($orgEmail) ?><br><?php endif; ?>
            <?php if ($orgAddress !== ''): ?><?= h($orgAddress) ?><?php endif; ?>
          </div>
        </div>

        <div class="receipt-title"><i class="fas fa-file-invoice-dollar"></i> Student Payment Receipt</div>
        <div class="receipt-sub">
          Receipt No: <b><?= h($receiptNo) ?></b> &nbsp;|&nbsp;
          Payment Date: <b><?= h($payment['payment_date'] ?? '-') ?></b>
        </div>
      </div>

      <div class="receipt-body">
        <div class="receipt-grid">
          <div class="receipt-box print-essential">
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

          <div class="receipt-box print-hide-box">
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

          <div class="receipt-box print-essential">
            <div class="receipt-box-title">Payment Details</div>
            <div class="receipt-row">
              <div class="receipt-label">Amount Received</div>
              <div class="receipt-value">? <?= h(number_format($paymentAmount, 2)) ?></div>
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
            <div class="receipt-row">
              <div class="receipt-label">Balance Amount</div>
              <div class="receipt-value">? <?= h(number_format($balanceAmount, 2)) ?></div>
            </div>
          </div>

          <div class="receipt-box print-hide-box">
            <div class="receipt-box-title">Fee Summary</div>
            <div class="receipt-row">
              <div class="receipt-label">Total Fee</div>
              <div class="receipt-value">? <?= h(number_format((float)($payment['total_fee'] ?? 0), 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Discount</div>
              <div class="receipt-value">? <?= h(number_format((float)($payment['discount_amount'] ?? 0), 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Final Fee</div>
              <div class="receipt-value">? <?= h(number_format($finalFee, 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Total Paid</div>
              <div class="receipt-value">? <?= h(number_format($paidAmount, 2)) ?></div>
            </div>
            <div class="receipt-row">
              <div class="receipt-label">Balance</div>
              <div class="receipt-value">? <?= h(number_format($balanceAmount, 2)) ?></div>
            </div>
          </div>

          <div class="receipt-box full print-hide-box" style="text-align:center;background:#fff7fb;">
            <div class="money-big">? <?= h(number_format($paymentAmount, 2)) ?></div>
            <div class="money-sub">Received from the student toward the above registration.</div>
          </div>

          <div class="receipt-box full print-essential">
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

        <div class="receipt-print-footnote">
          This is a computer-generated receipt from <?= h($orgName) ?>.
          <?php if ($orgAddress !== ''): ?><br><?= h($orgAddress) ?><?php endif; ?>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

