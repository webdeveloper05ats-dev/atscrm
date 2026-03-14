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
:root {
  --primary: #e91e63;
  --primary-light: #f8bbd0;
  --primary-dark: #c2185b;
  --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
  --gray-200: #e9ecef;
  --gray-300: #dee2e6;
  --gray-400: #ced4da;
  --gray-700: #495057;
  --gray-800: #343a40;
  --gray-900: #212529;
  --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
  --radius-sm: 6px;
  --radius-md: 8px;
  --transition: all 0.2s ease;
  --reg-text: #343a40;
  --reg-muted: #6c757d;
  --reg-primary: #e91e63;
  --reg-primary-soft: #fff0f5;
  --reg-shadow-soft: 0 8px 20px rgba(0,0,0,.05);
}

[data-tooltip] {
  position: relative;
  cursor: pointer;
}

[data-tooltip]:before {
  content: attr(data-tooltip);
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(-5px);
  background: var(--gray-800);
  color: white;
  padding: 6px 12px;
  border-radius: var(--radius-sm);
  font-size: 12px;
  white-space: nowrap;
  z-index: 10;
  opacity: 0;
  visibility: hidden;
  transition: var(--transition);
  box-shadow: var(--shadow-sm);
  pointer-events: none;
  font-weight: normal;
  letter-spacing: 0.3px;
}

[data-tooltip]:after {
  content: '';
  position: absolute;
  bottom: 100%;
  left: 50%;
  transform: translateX(-50%) translateY(5px);
  border-width: 5px;
  border-style: solid;
  border-color: var(--gray-800) transparent transparent transparent;
  opacity: 0;
  visibility: hidden;
  transition: var(--transition);
  pointer-events: none;
}

[data-tooltip]:hover:before,
[data-tooltip]:hover:after {
  opacity: 1;
  visibility: visible;
  transform: translateX(-50%) translateY(0);
}

.payments-page-title {
  margin: 0 0 10px;
  line-height: 1.2;
}

.payments-wrapper {
  padding: 0;
  width: 100%;
  overflow-x: hidden;
}

.crm-right {
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow-x: hidden;
}

.crm-card {
  background: #fff;
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 8px 20px rgba(0,0,0,.05);
  border: 1px solid #f1d6e3;
  width: 100%;
  max-width: 100%;
  box-sizing: border-box;
  overflow: hidden;
}

.crm-card h3 {
  margin: 0 0 10px;
  font-size: 20px;
  font-weight: 700;
  color: var(--gray-800);
  display: flex;
  align-items: center;
  gap: 8px;
}

.crm-table-wrapper {
  width: 100%;
  max-width: 100%;
  overflow-x: visible;
  overflow-y: visible;
  box-sizing: border-box;
}

#userTable.crm-table {
  width: 100%;
  border-collapse: collapse;
  border: 1px solid #f1d6e3;
  table-layout: auto;
}

#userTable.crm-table th,
#userTable.crm-table td {
  border: 1px solid #f1d6e3;
  padding: 12px 10px;
  font-size: 13px;
  white-space: normal;
  word-break: break-word;
  vertical-align: middle;
}

#userTable.crm-table th {
  background: #fff0f5;
  font-weight: 600;
  text-align: left;
  color: var(--gray-800);
}

#userTable.crm-table tbody tr:hover {
  background: #fff9fc;
}

.crm-table td:nth-child(1),
.crm-table td:nth-child(2) {
  font-weight: 700;
}

.crm-table td:nth-child(2) {
  color: var(--primary-dark);
}

.crm-table td:nth-child(3),
.crm-table td:nth-child(4),
.crm-table td:nth-child(5) {
  font-weight: 700;
  color: var(--gray-900);
}

.pay-student {
  font-weight: 700;
  color: var(--gray-900);
}

.pay-reg {
  font-weight: 700;
  color: var(--primary-dark);
}

.pay-money {
  font-weight: 700;
  color: var(--gray-900);
}

.status-paid,
.status-partial,
.status-unpaid {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 84px;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  border: 1px solid transparent;
}

.status-paid {
  background: #eaf7ee;
  color: #2e7d32;
  border-color: rgba(46,125,50,.18);
}

.status-partial {
  background: #fff8e8;
  color: #fb8c00;
  border-color: rgba(251,140,0,.18);
}

.status-unpaid {
  background: #fdecec;
  color: #d32f2f;
  border-color: rgba(211,47,47,.18);
}

.pay-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
}

.btn-add-payment {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 9px 14px;
  border-radius: var(--radius-md);
  cursor: pointer;
  font-size: 13px;
  font-weight: 600;
  transition: var(--transition);
}

.btn-add-payment:hover {
  background: var(--primary-dark);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.btn-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: var(--transition);
}

.btn-view {
  background: #e8f4fd;
  color: #1565c0;
}

.btn-view:hover {
  background: #d9edf9;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
  margin-bottom: 8px;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dt-buttons {
  display: flex;
  align-items: center;
  box-sizing: border-box;
}

.crm-table-header,
.crm-table-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  width: 100%;
  box-sizing: border-box;
}

.crm-table-header {
  margin-bottom: 10px;
}

.crm-table-footer {
  margin-top: 10px;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
  font-size: 13px;
  color: var(--gray-700);
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}

.dataTables_wrapper {
  width: 100%;
  overflow-x: hidden;
  /*overflow-y: hidden;*/
}

.dataTables_wrapper .dataTables_scroll,
.dataTables_wrapper .dataTables_scrollHead,
.dataTables_wrapper .dataTables_scrollHeadInner,
.dataTables_wrapper .dataTables_scrollBody {
  width: 100% !important;
  max-width: 100% !important;
}

.dataTables_wrapper .dataTables_scrollBody {
  overflow-x: hidden !important;
}

.dataTables_wrapper .dataTables_length {
  flex: 0 0 auto;
}

.dataTables_wrapper .dataTables_filter {
  flex: 1 1 280px;
  justify-content: center;
  min-width: 0;
}

.dataTables_wrapper .dt-buttons {
  flex: 0 0 auto;
  margin-left: auto;
}

.dataTables_wrapper .dataTables_length label,
.dataTables_wrapper .dataTables_filter label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  color: var(--gray-800);
}

.dataTables_wrapper .dataTables_filter label {
  min-width: 0;
  width: min(100%, 320px);
  position: relative;
}

.dataTables_wrapper .dataTables_filter input,
.dataTables_wrapper .dataTables_length select {
  border: 1px solid var(--gray-300);
  border-radius: var(--radius-md);
  padding: 8px 12px;
  background: #fff;
  outline: none;
}

.dataTables_wrapper .dataTables_filter input:focus,
.dataTables_wrapper .dataTables_length select:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--primary-light);
}

.dataTables_wrapper .dataTables_filter label:before {
  content: '\f002';
  font-family: 'Font Awesome 6 Free';
  font-weight: 900;
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  pointer-events: none;
}

.dataTables_wrapper .dataTables_filter input {
  min-width: 0;
  width: 100% !important;
  padding-left: 38px;
}

.crm-export-btn {
  background: var(--primary) !important;
  color: #fff !important;
  border: none !important;
  padding: 10px 16px !important;
  border-radius: var(--radius-md) !important;
  font-size: 13px !important;
  font-weight: 700 !important;
  box-shadow: none !important;
}

.crm-export-btn:hover {
  background: var(--primary-dark) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
  border: 1px solid #f1d6e3 !important;
  background: #fff !important;
  border-radius: 8px !important;
  color: var(--gray-700) !important;
  padding: 6px 12px !important;
  margin-left: 4px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
  background: var(--primary) !important;
  color: #fff !important;
  border-color: var(--primary) !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
  background: #fff5f9 !important;
  color: var(--primary-dark) !important;
  border-color: #f1d6e3 !important;
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

@media(max-width:768px){
  .crm-table-header,
  .crm-table-footer {
    flex-direction: column;
    align-items: stretch;
  }

  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dt-buttons {
    flex: 1 1 100%;
    width: 100%;
    margin-left: 0;
  }

  .crm-table-wrapper{
    width:100%;
    overflow-x: visible;
    -webkit-overflow-scrolling: touch;
  }

  #userTable.crm-table{
    min-width: 0;
  }

  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dt-buttons {
    width:100%;
    display:flex;
    justify-content:center;
    margin-bottom:10px;
  }

  .dataTables_wrapper .dataTables_length label,
  .dataTables_wrapper .dataTables_filter label {
    width: 100%;
  }

  .dataTables_wrapper .dataTables_filter input{
    width:100% !important;
    max-width:100%;
    min-width:0;
  }

  .dataTables_wrapper .dataTables_paginate{
    display:flex;
    justify-content:center;
    margin-top:10px;
  }

  .crm-export-btn {
    width: 100% !important;
  }

  #userTable.crm-table th,
  #userTable.crm-table td {
    padding: 10px 8px;
    font-size: 12px;
  }

  .pay-action {
    gap: 6px;
  }

  .btn-add-payment {
    padding: 8px 12px;
    font-size: 12px;
  }

  .crm-card {
    padding: 12px;
  }
}
</style>

<h2 class="page-title payments-page-title">Payments</h2>

<div class="payments-wrapper">
<div class="crm-right">
<div class="crm-card">

<h3><i class="fas fa-list"></i> Payments List</h3>
<div class="crm-table-wrapper">

<table id="userTable" class="crm-table display" style="width:100%">

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
data-tooltip="Add payment"
onclick="openPaymentModal(<?= (int)$p['id'] ?>)">
+ Add
</button>

<a
class="btn-icon btn-view"
href="index.php?page=students/profile&id=<?= $p['id'] ?>"
title="Student Profile"
data-tooltip="Student profile">

<i class="fas fa-eye"></i>

</a>

</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>
</div>
</div>
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
if (window.jQuery && $.fn.DataTable) {
$('#userTable').DataTable({
pageLength:10,
lengthMenu:[5,10,20,50],
autoWidth:false,
responsive:true,
order:[[6,'desc']],
dom:'<"crm-table-header"lfB>rt<"crm-table-footer"ip>',
buttons:[{
extend:'csvHtml5',
text:'Export CSV',
className:'crm-export-btn'
}],
language:{
search:"",
searchPlaceholder:"Search..."
}
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
