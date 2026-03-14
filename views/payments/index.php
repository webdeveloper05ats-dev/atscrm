<?php
// =====================================
// ATS CRM - Payments Module
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$pageTitle = "Payments";

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleName = $_SESSION['role_name'] ?? '';

/* ===============================
FETCH DATA
=============================== */

if ($roleName === "Front Office") {

$stmt = $pdo->prepare("
SELECT 
r.id,
r.registration_no,
r.enquiry_snapshot_name,
r.final_fee,
r.balance_amount,
r.payment_status,
IFNULL(SUM(p.amount),0) total_paid,
MAX(p.payment_date) last_payment_date
FROM registrations r
LEFT JOIN registration_payments p 
ON p.registration_id = r.id
WHERE r.assigned_to = ?
GROUP BY r.id
ORDER BY last_payment_date DESC
");

$stmt->execute([$userId]);

} else {

$stmt = $pdo->query("
SELECT 
r.id,
r.registration_no,
r.enquiry_snapshot_name,
r.final_fee,
r.balance_amount,
r.payment_status,
IFNULL(SUM(p.amount),0) total_paid,
MAX(p.payment_date) last_payment_date
FROM registrations r
LEFT JOIN registration_payments p 
ON p.registration_id = r.id
GROUP BY r.id
ORDER BY last_payment_date DESC
");

}

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<style>
/* ===============================
   MODERN UI - ATS CRM PAYMENTS
   =============================== */

/* Import Google Fonts */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* ===============================
   PAGE CONTAINER
   =============================== */
.crm-page {
    background: #f8fafc;
    padding: 30px;
    border-radius: 24px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.crm-page h2 {
    margin-bottom: 24px;
    font-size: 28px;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
}

/* ===============================
   DATATABLE MODERN
   =============================== */
#paymentsTable {
    border-collapse: separate;
    border-spacing: 0 8px;
    margin-top: -8px;
    width: 100%;
}

/* Header Styling */
#paymentsTable thead th {
    background: transparent;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    padding: 16px 12px;
    border: none;
}

/* Row Styling */
#paymentsTable tbody tr {
    background: white;
    border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
}

#paymentsTable tbody tr:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    transform: translateY(-2px);
}

/* Cell Styling */
#paymentsTable tbody td {
    padding: 18px 12px;
    font-size: 14px;
    color: #334155;
    border: none;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
}

#paymentsTable tbody td:first-child {
    border-left: 1px solid #f1f5f9;
    border-radius: 16px 0 0 16px;
}

#paymentsTable tbody td:last-child {
    border-right: 1px solid #f1f5f9;
    border-radius: 0 16px 16px 0;
}

/* Amount Styling */
#paymentsTable tbody td:nth-child(3),
#paymentsTable tbody td:nth-child(4),
#paymentsTable tbody td:nth-child(5) {
    font-weight: 600;
    color: #0f172a;
}

/* ===============================
   STATUS BADGES
   =============================== */
.status-paid {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    color: #059669;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 30px;
    display: inline-block;
    font-size: 12px;
    letter-spacing: 0.02em;
    border: 1px solid rgba(5, 150, 105, 0.15);
}

.status-partial {
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    color: #d97706;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 30px;
    display: inline-block;
    font-size: 12px;
    letter-spacing: 0.02em;
    border: 1px solid rgba(217, 119, 6, 0.15);
}

.status-unpaid {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    color: #dc2626;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 30px;
    display: inline-block;
    font-size: 12px;
    letter-spacing: 0.02em;
    border: 1px solid rgba(220, 38, 38, 0.15);
}

/* ===============================
   ACTION BUTTONS
   =============================== */
.pay-action {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-add-payment {
    background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    letter-spacing: 0.02em;
}

.btn-add-payment:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(99, 102, 241, 0.3);
}

.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 12px;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: #475569;
}

.btn-icon:hover {
    background: #e2e8f0;
    transform: scale(1.05);
    color: #0f172a;
}

.btn-view {
    color: #6366f1;
    font-size: 14px;
}

/* ===============================
   DATATABLE CONTROLS
   =============================== */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin-bottom: 25px;
}

.dataTables_wrapper .dataTables_length {
    float: left;
}

.dataTables_wrapper .dataTables_filter {
    float: right;
}

.dataTables_filter label {
    font-weight: 500;
    color: #475569;
    font-size: 13px;
}

.dataTables_filter input {
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    padding: 8px 16px;
    margin-left: 8px;
    font-size: 13px;
    width: 240px;
    transition: all 0.2s ease;
    background: white;
}

.dataTables_filter input:focus {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    outline: none;
}

.dataTables_length select {
    border: 1px solid #e2e8f0;
    border-radius: 30px;
    padding: 8px 24px 8px 12px;
    margin: 0 8px;
    font-size: 13px;
    background: white;
    cursor: pointer;
}

/* Pagination */
.dataTables_wrapper .dataTables_paginate {
    margin-top: 25px;
    display: flex;
    justify-content: flex-end;
    gap: 4px;
}

.dataTables_wrapper .paginate_button {
    border: none !important;
    background: white !important;
    margin: 0 2px;
    border-radius: 12px !important;
    padding: 8px 14px !important;
    font-size: 13px;
    font-weight: 500;
    color: #475569 !important;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

.dataTables_wrapper .paginate_button.current {
    background: linear-gradient(135deg, #8b5cf6, #6366f1) !important;
    color: white !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.dataTables_wrapper .paginate_button:hover {
    background: #f8fafc !important;
    transform: translateY(-1px);
}

.dataTables_info {
    margin-top: 25px;
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

.crm-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(5px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
    z-index: 99999;
  }

  .crm-modal {
    width: min(1180px, 96vw);
    background: #fff;
    border-radius: 24px;
    border: 1px solid rgba(0, 0, 0, .08);
    box-shadow: 0 20px 70px rgba(0, 0, 0, .22);
    overflow: hidden;
  }

  .crm-modal-header {
    padding: 18px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #edf1f5;
    background: linear-gradient(180deg, #fff 0%, #fffafe 100%);
  }

  .crm-modal-title {
    font-weight: 900;
    font-size: 20px;
    color: var(--reg-text);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .crm-modal-close {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    border: 1px solid rgba(0, 0, 0, .08);
    background: #fff;
    cursor: pointer;
    font-size: 20px;
    transition: .2s ease;
  }

  .crm-modal-close:hover {
    background: #f8fafc;
  }

  .crm-modal-body {
    padding: 20px;
    max-height: 82vh;
    overflow: auto;
    background: #fbfcff;
  }

  .pro-modal-wrap {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .pro-modal-section {
    border: 1px solid #edf1f5;
    border-radius: 20px;
    padding: 18px;
    background: #fff;
    box-shadow: var(--reg-shadow-soft);
  }

  .pro-modal-head {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
  }

  .pro-modal-head-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: var(--reg-primary-soft);
    color: var(--reg-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .pro-modal-title {
    font-size: 17px;
    font-weight: 900;
    color: var(--reg-text);
    line-height: 1.3;
  }

  .pro-modal-subtitle {
    font-size: 13px;
    color: var(--reg-muted);
    margin-top: 4px;
  }

  .pro-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 12px;
  }

  .pro-info-card {
    border: 1px solid #eef2f6;
    border-radius: 16px;
    padding: 14px;
    background: linear-gradient(180deg, #fff, #fcfdff);
  }

  .pro-info-card .label,
  .pro-pay-card .label {
    display: block;
    font-size: 12px;
    font-weight: 800;
    color: var(--reg-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 6px;
  }

  .pro-info-card .value,
  .pro-pay-card .value {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: var(--reg-text);
    line-height: 1.5;
    word-break: break-word;
  }

  .pro-note-box {
    margin-top: 14px;
    padding: 14px 16px;
    border-radius: 16px;
    background: #fff7fb;
    border: 1px solid #f5d6e3;
    color: #4b5563;
    line-height: 1.7;
  }

  .pro-note-title {
    font-size: 13px;
    font-weight: 900;
    color: #9d174d;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .pro-timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .pro-timeline-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }

  .pro-timeline-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--reg-primary);
    margin-top: 8px;
    box-shadow: 0 0 0 5px rgba(233, 30, 99, .12);
    flex-shrink: 0;
  }

  .pro-timeline-content {
    flex: 1;
    border: 1px solid #eef2f6;
    border-radius: 16px;
    padding: 14px;
    background: #fff;
  }

  .pro-timeline-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--reg-text);
  }

  .pro-mini-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #fce7f3;
    color: #9d174d;
    font-size: 11px;
    font-weight: 800;
    margin-left: 8px;
  }

  .pro-timeline-sub {
    font-size: 12px;
    color: var(--reg-muted);
    margin-top: 6px;
  }

  .pro-timeline-note {
    margin-top: 10px;
    color: #374151;
    line-height: 1.7;
  }

  .pro-table-scroll {
    overflow: auto;
  }

  .pro-mini-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
  }

  .pro-mini-table th,
  .pro-mini-table td {
    padding: 12px 12px;
    border-bottom: 1px solid #edf1f5;
    text-align: left;
    font-size: 13px;
  }

  .pro-mini-table th {
    background: #fafbfe;
    font-weight: 900;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: .4px;
    font-size: 12px;
  }

  .receipt-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid #ddd;
    text-decoration: none;
    color: #333;
    background: #fff;
    font-weight: 800;
    transition: .2s ease;
  }

  .receipt-link:hover {
    background: #f7f7f7;
  }

  .pro-payment-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
  }

  .pro-pay-card {
    border: 1px solid #f1d9e4;
    background: #fff;
    border-radius: 16px;
    padding: 14px;
  }

  .pro-pay-card.highlight {
    background: linear-gradient(135deg, #fff7fb, #fff);
    border-color: #f5cfe0;
  }

  .pro-payment-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .pro-payment-grid .full {
    grid-column: 1 / -1;
  }

  .m-label {
    display: block;
    font-size: 13px;
    font-weight: 800;
    margin-bottom: 6px;
    color: var(--reg-text);
  }

  .m-input {
    width: 100%;
    min-height: 46px;
    padding: 10px 12px;
    border: 1px solid #dfe5ec;
    border-radius: 12px;
    background: #fff;
    outline: none;
    transition: .2s ease;
  }

  .modal-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
  }

  .empty-note {
    color: var(--reg-muted);
    padding: 12px 0;
  }

  .error-note {
    color: #d32f2f;
  }

  @media (max-width: 1200px) {
    .reg-toolbar-item.actions {
      margin-left: 0;
    }
  }

  @media (max-width: 900px) {
    .pro-payment-grid {
      grid-template-columns: 1fr;
    }

    .reg-page {
      padding: 12px;
      border-radius: 18px;
    }

    .pro-card-body,
    .crm-modal-body {
      padding: 14px;
    }

    .pro-card-head,
    .crm-modal-header {
      padding: 16px;
    }

    .reg-toolbar-form {
      gap: 10px;
    }

    .reg-toolbar-item {
      min-width: calc(50% - 8px);
      flex: 1 1 calc(50% - 8px);
    }

    .reg-toolbar-item.search,
    .reg-toolbar-item.actions {
      min-width: 100%;
      flex: 1 1 100%;
    }
  }

  @media (max-width: 576px) {
    .reg-page-title h2 {
      font-size: 22px;
    }

    .reg-toolbar-item {
      min-width: 100%;
      flex: 1 1 100%;
    }

    .reg-toolbar-item.actions {
      flex-direction: column;
      align-items: stretch;
    }

    .pro-btn {
      width: 100%;
    }
  }
</style>

<div class="crm-page">

<h2 style="margin-bottom:20px;">Payments</h2>

<table id="paymentsTable" class="display" style="width:100%">

<thead>
<tr>
<th>Student</th>
<th>Registration</th>
<th>Total Fee</th>
<th>Paid</th>
<th>Balance</th>
<th>Status</th>
<th>Last Payment</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($payments as $p): ?>

<tr>

<td><?= htmlspecialchars($p['enquiry_snapshot_name'] ?? '-') ?></td>

<td><?= htmlspecialchars($p['registration_no'] ?? '-') ?></td>

<td>₹<?= number_format($p['final_fee'] ?? 0,2) ?></td>

<td>₹<?= number_format($p['total_paid'] ?? 0,2) ?></td>

<td>₹<?= number_format($p['balance_amount'] ?? 0,2) ?></td>

<td>

<?php
$status=$p['payment_status'] ?? 'unpaid';

if($status=="paid"){
echo "<span class='status-paid'>Paid</span>";
}
elseif($status=="partial"){
echo "<span class='status-partial'>Partial</span>";
}
else{
echo "<span class='status-unpaid'>Unpaid</span>";
}
?>

</td>

<td><?= htmlspecialchars($p['last_payment_date'] ?? '-') ?></td>

<td>

<div class="pay-action">

<button
class="btn-add-payment"
onclick="openPaymentModal(<?= (int)$p['id'] ?>)">
+ Add
</button>

<a
class="btn-icon btn-view"
href="index.php?page=students/profile&id=<?= $p['id'] ?>"
title="Student Profile">

<i class="fas fa-eye"></i>

</a>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<!-- MODAL -->

<div class="crm-modal-backdrop" id="crmModalBackdrop">

<div class="crm-modal">

<div class="crm-modal-header">

<div class="crm-modal-title" id="crmModalTitle">
Payment Entry
</div>

<button class="crm-modal-close" onclick="closeCrmModal()">×</button>

</div>

<div class="crm-modal-body" id="crmModalBody">

Loading...

</div>

</div>

</div>


<script>

document.addEventListener("DOMContentLoaded", function(){

if (window.DataTable) {

new DataTable('#paymentsTable',{
pageLength:10,
responsive:true,
order:[[6,'desc']]
});

}

});

/* ===============================
MODAL FUNCTIONS
=============================== */

function openCrmModal(title){

document.getElementById("crmModalTitle").innerHTML=title;

document.getElementById("crmModalBackdrop").style.display="flex";

}

function closeCrmModal(){

document.getElementById("crmModalBackdrop").style.display="none";

document.getElementById("crmModalBody").innerHTML="Loading...";

}


/* ===============================
OPEN PAYMENT FORM
=============================== */

async function openPaymentModal(regId){

openCrmModal("Payment Entry");

const url = `index.php?page=payments/payment_modal&ajax=1&reg_id=${regId}`;

try{

const res = await fetch(url);

const html = await res.text();

document.getElementById("crmModalBody").innerHTML = html;

}
catch(e){

document.getElementById("crmModalBody").innerHTML =
"<div style='color:red'>Failed to load payment form</div>";

}

}

</script>