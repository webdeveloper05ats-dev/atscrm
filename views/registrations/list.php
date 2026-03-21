 <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/reglist.css">
<?php
// =====================================
// Registrations - List
// Slug: registrations/list
// File: views/registrations/list.php
// =====================================

if (!defined('APP_NAME')) {
  die("Unauthorized access.");
}

$success = "";
$error = "";

if (!function_exists('h')) {
  function h($v)
  {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
  }
}
if (!function_exists('regNull')) {
  function regNull($v)
  {
    $v = trim((string) $v);
    return $v === '' ? null : $v;
  }
}
if (!function_exists('regDecVal')) {
  function regDecVal($v)
  {
    $v = trim((string) $v);
    return $v === '' ? 0 : (float) $v;
  }
}
if (!function_exists('makeReceiptNo')) {
  function makeReceiptNo(PDO $pdo): string
  {
    $prefix = 'RCPT-' . date('Ym') . '-';
    $st = $pdo->prepare("SELECT COUNT(*) FROM registration_payments WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
    $st->execute();
    $count = (int) $st->fetchColumn();
    return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
  }
}
if (!function_exists('recalcRegistrationPaymentsSummary')) {
  function recalcRegistrationPaymentsSummary(PDO $pdo, int $regId): void
  {
    $st = $pdo->prepare("SELECT final_fee FROM registrations WHERE id=? LIMIT 1");
    $st->execute([$regId]);
    $finalFee = (float) ($st->fetchColumn() ?? 0);

    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM registration_payments WHERE registration_id=? AND approval_status='approved'");
    $st->execute([$regId]);
    $paidSum = (float) $st->fetchColumn();

    $balance = max(0, $finalFee - $paidSum);

    $paymentStatus = 'unpaid';
    if ($paidSum > 0 && $paidSum < $finalFee) {
      $paymentStatus = 'partial';
    } elseif ($paidSum >= $finalFee && $finalFee > 0) {
      $paymentStatus = 'paid';
      $balance = 0;
    }

    $upd = $pdo->prepare("
            UPDATE registrations
            SET paid_amount=?, balance_amount=?, payment_status=?, updated_at=NOW()
            WHERE id=?
        ");
    $upd->execute([$paidSum, $balance, $paymentStatus, $regId]);
  }
}
if (!function_exists('isRegistrationCertificateIssued')) {
  function isRegistrationCertificateIssued(array $row): bool
  {
    $boolLikeKeys = ['certificate_issued', 'is_certificate_issued', 'certificate_given', 'is_certificate_given'];
    foreach ($boolLikeKeys as $k) {
      if (array_key_exists($k, $row)) {
        $v = $row[$k];
        if ((int) $v === 1) {
          return true;
        }
        $s = strtolower(trim((string) $v));
        if (in_array($s, ['1', 'true', 'yes', 'issued', 'given'], true)) {
          return true;
        }
      }
    }

    $statusKeys = ['certificate_status', 'cert_status', 'certificate_state'];
    foreach ($statusKeys as $k) {
      if (array_key_exists($k, $row)) {
        $s = strtolower(trim((string) ($row[$k] ?? '')));
        if (in_array($s, ['issued', 'given', 'completed', 'done'], true)) {
          return true;
        }
      }
    }

    return false;
  }
}
if (!function_exists('canDeleteRegistrationRecord')) {
  function canDeleteRegistrationRecord(array $row): bool
  {
    $isCompleted = strtolower(trim((string) ($row['registration_status'] ?? ''))) === 'completed';
    $isPaid = strtolower(trim((string) ($row['payment_status'] ?? ''))) === 'paid';
    $isCertIssued = isRegistrationCertificateIssued($row);
    return $isCompleted && $isPaid && $isCertIssued;
  }
}

/* =========================================================
   Session / Scope
========================================================= */
$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);

$roleName = '';

try {
    $r = $pdo->prepare("SELECT name FROM roles WHERE id=? LIMIT 1");
    $r->execute([$roleId]);
    $roleName = strtolower(trim((string)$r->fetchColumn()));
} catch (Exception $e) {
    $roleName = '';
}

$canAllBranches = 0;
try {
  $r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
  $r->execute([$roleId]);
  $canAllBranches = (int) ($r->fetchColumn() ?? 0);
} catch (Exception $e) {
  $canAllBranches = 0;
}

/* =========================================================
   AJAX Modal Actions
========================================================= */
$isAjax = isset($_GET['ajax']) && (int) $_GET['ajax'] === 1;

if ($isAjax) {
  $action = trim((string) ($_GET['action'] ?? ''));

  // ---------------------------------
  // 1) View History
  // ---------------------------------
  if ($action === 'view_history') {
    $regId = (int) ($_GET['reg_id'] ?? 0);

    if ($regId <= 0) {
      echo "<div class='empty-note'>Invalid registration.</div>";
      exit;
    }

    try {
      if ($canAllBranches !== 1 && $branchId > 0) {
        $st = $pdo->prepare("
                    SELECT r.*, e.enquiry_no, e.name AS enquiry_name, e.phone AS enquiry_phone, e.email AS enquiry_email, u.name AS owner_name
                    FROM registrations r
                    LEFT JOIN enquiries e ON e.id = r.enquiry_id
                    LEFT JOIN users u ON u.id = r.assigned_to
                    WHERE r.id=? AND r.branch_id=? AND r.registration_status IN ('active','completed')
                    LIMIT 1
                ");
        $st->execute([$regId, $branchId]);
      } else {
        $st = $pdo->prepare("
                    SELECT r.*, e.enquiry_no, e.name AS enquiry_name, e.phone AS enquiry_phone, e.email AS enquiry_email, u.name AS owner_name
                    FROM registrations r
                    LEFT JOIN enquiries e ON e.id = r.enquiry_id
                    LEFT JOIN users u ON u.id = r.assigned_to
                    WHERE r.id=? AND r.registration_status IN ('active','completed')
                    LIMIT 1
                ");
        $st->execute([$regId]);
      }
      $reg = $st->fetch(PDO::FETCH_ASSOC);

      if (!$reg) {
        throw new Exception("Registration not found or access denied.");
      }

      $profile = null;
      $st = $pdo->prepare("SELECT * FROM registration_profiles WHERE registration_id=? LIMIT 1");
      $st->execute([$regId]);
      $profile = $st->fetch(PDO::FETCH_ASSOC);

      $followups = [];
      if (!empty($reg['enquiry_id'])) {
        $st = $pdo->prepare("
                    SELECT f.*, u.name AS created_by_name
                    FROM enquiry_followups f
                    LEFT JOIN users u ON u.id = f.created_by
                    WHERE f.enquiry_id=?
                    ORDER BY f.followup_date DESC, f.followup_time DESC, f.id DESC
                ");
        $st->execute([(int) $reg['enquiry_id']]);
        $followups = $st->fetchAll(PDO::FETCH_ASSOC);
      }

      $payments = [];
      $st = $pdo->prepare("
                SELECT p.*, u1.name AS collected_by_name
                FROM registration_payments p
                LEFT JOIN users u1 ON u1.id = p.collected_by
                WHERE p.registration_id=?
                ORDER BY p.payment_date DESC, p.id DESC
            ");
      $st->execute([$regId]);
      $payments = $st->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <div class="pro-modal-wrap payment-modal-modern">
        <div class="pro-payment-two-col">
        <div class="pro-modal-section">
          <div class="pro-modal-head">
            <div class="pro-modal-head-icon"><i class="fas fa-user-graduate"></i></div>
            <div>
              <div class="pro-modal-title">Student & Registration</div>
              <div class="pro-modal-subtitle">Complete registration overview</div>
            </div>
          </div>

          <div class="pro-info-grid">
            <div class="pro-info-card">
              <span class="label">Registration No</span>
              <span class="value"><?= h($reg['registration_no'] ?: ('REG-' . $reg['id'])) ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Student</span>
              <span class="value"><?= h($reg['enquiry_snapshot_name'] ?: $reg['enquiry_name'] ?: '-') ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Phone</span>
              <span class="value"><?= h(visibleStudentContactValue($reg['enquiry_snapshot_phone'] ?: $reg['enquiry_phone'] ?: '-')) ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Email</span>
              <span class="value"><?= h(visibleStudentContactValue($reg['enquiry_snapshot_email'] ?: $reg['enquiry_email'] ?: '-')) ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Program</span>
              <span class="value"><?= h($reg['program_name'] ?: '-') ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Batch</span>
              <span class="value"><?= h($reg['batch_name'] ?: '-') ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Type</span>
              <span class="value"><?= h(ucfirst($reg['reg_type'] ?: '-')) ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Status</span>
              <span class="value"><?= h(ucfirst($reg['registration_status'] ?: '-')) ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Payment Status</span>
              <span class="value"><?= h(ucfirst($reg['payment_status'] ?: '-')) ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Owner</span>
              <span class="value"><?= h($reg['owner_name'] ?: '-') ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Final Fee</span>
              <span class="value">₹ <?= h(number_format((float) ($reg['final_fee'] ?? 0), 2)) ?></span>
            </div>
            <div class="pro-info-card">
              <span class="label">Balance</span>
              <span class="value">₹ <?= h(number_format((float) ($reg['balance_amount'] ?? 0), 2)) ?></span>
            </div>
          </div>

          <?php if (!empty($reg['notes'])): ?>
            <div class="pro-note-box">
              <div class="pro-note-title"><i class="fas fa-sticky-note"></i> Notes</div>
              <div><?= nl2br(h($reg['notes'])) ?></div>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($profile): ?>
          <div class="pro-modal-section">
            <div class="pro-modal-head">
              <div class="pro-modal-head-icon"><i class="fas fa-id-card"></i></div>
              <div>
                <div class="pro-modal-title">Profile Details</div>
                <div class="pro-modal-subtitle">Academic and guardian information</div>
              </div>
            </div>

            <div class="pro-info-grid">
              <div class="pro-info-card">
                <span class="label">Gender</span>
                <span class="value"><?= h($profile['gender'] ?? '-') ?></span>
              </div>
              <div class="pro-info-card">
                <span class="label">DOB</span>
                <span class="value"><?= h($profile['dob'] ?? '-') ?></span>
              </div>
              <div class="pro-info-card">
                <span class="label">Qualification</span>
                <span class="value"><?= h($profile['qualification'] ?? '-') ?></span>
              </div>
              <div class="pro-info-card">
                <span class="label">College</span>
                <span class="value"><?= h($profile['college_name'] ?? '-') ?></span>
              </div>
              <div class="pro-info-card">
                <span class="label">Passout Year</span>
                <span class="value"><?= h($profile['year_of_passout'] ?? '-') ?></span>
              </div>
              <div class="pro-info-card">
                <span class="label">Parent Name</span>
                <span class="value"><?= h($profile['parent_name'] ?? '-') ?></span>
              </div>
              <div class="pro-info-card">
                <span class="label">Parent Phone</span>
                <span class="value"><?= h(visibleStudentContactValue($profile['parent_phone'] ?? '-')) ?></span>
              </div>
              <div class="pro-info-card">
                <span class="label">Emergency Contact</span>
                <span class="value"><?= h(visibleStudentContactValue($profile['emergency_contact'] ?? '-')) ?></span>
              </div>
            </div>

            <div class="pro-note-box">
              <div class="pro-note-title"><i class="fas fa-map-marker-alt"></i> Address</div>
              <div><?= nl2br(h($profile['address'] ?? '-')) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <div class="pro-modal-section">
          <div class="pro-modal-head">
            <div class="pro-modal-head-icon"><i class="fas fa-history"></i></div>
            <div>
              <div class="pro-modal-title">Follow-up History</div>
              <div class="pro-modal-subtitle">All enquiry follow-up records</div>
            </div>
          </div>

          <?php if (empty($followups)): ?>
            <div class="empty-note">No follow-ups found.</div>
          <?php else: ?>
            <div class="pro-timeline">
              <?php foreach ($followups as $f): ?>
                <div class="pro-timeline-item">
                  <div class="pro-timeline-dot"></div>
                  <div class="pro-timeline-content">
                    <div class="pro-timeline-title">
                      <?= h($f['followup_date']) ?>           <?= h($f['followup_time'] ?? '') ?>
                      <span class="pro-mini-badge"><?= h(ucfirst($f['followup_type'] ?? '-')) ?></span>
                    </div>
                    <div class="pro-timeline-sub">
                      Status: <?= h(ucfirst($f['status'] ?? 'pending')) ?> • By: <?= h($f['created_by_name'] ?? '-') ?>
                    </div>
                    <?php if (!empty($f['notes'])): ?>
                      <div class="pro-timeline-note"><?= nl2br(h($f['notes'])) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="pro-payment-two-col">
        <div class="pro-modal-section">
          <div class="pro-modal-head">
            <div class="pro-modal-head-icon"><i class="fas fa-wallet"></i></div>
            <div>
              <div class="pro-modal-title">Payments History</div>
              <div class="pro-modal-subtitle">All payment entries for this registration</div>
            </div>
          </div>

          <?php if (empty($payments)): ?>
            <div class="empty-note">No payments added yet.</div>
          <?php else: ?>
            <div class="pro-table-scroll">
              <table class="pro-mini-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Receipt No</th>
                    <th>Amount</th>
                    <th>Mode</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($payments as $p): ?>
                    <tr>
                      <td><?= h($p['payment_date']) ?></td>
                      <td><?= h($p['receipt_no'] ?: '-') ?></td>
                      <td>₹ <?= h(number_format((float) $p['amount'], 2)) ?></td>
                      <td><?= h($p['payment_mode']) ?></td>
                      <td><?= h($p['payment_type']) ?></td>
                      <td><?= h($p['approval_status']) ?></td>
                      <td>
                        <a href="index.php?page=payments/receipt&payment_id=<?= (int) $p['id'] ?>" target="_blank"
                          class="receipt-link mtip" title="Open Receipt">
                          <i class="fas fa-receipt"></i> <span>Receipt</span>
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
        </div>
        </div>
      </div>

      <?php
    } catch (Exception $e) {
      echo "<div class='empty-note error-note'>" . h($e->getMessage()) . "</div>";
    }
    exit;
  }

  // ---------------------------------
  // 2) Payment Modal
  // ---------------------------------
  if ($action === 'payment_modal') {
    $regId = (int) ($_GET['reg_id'] ?? 0);

    if ($regId <= 0) {
      echo "<div class='empty-note'>Invalid registration.</div>";
      exit;
    }

    try {
      if ($canAllBranches !== 1 && $branchId > 0) {
        $st = $pdo->prepare("
                    SELECT r.*, u.name AS owner_name
                    FROM registrations r
                    LEFT JOIN users u ON u.id = r.assigned_to
                    WHERE r.id=? AND r.branch_id=? AND r.registration_status IN ('active','completed')
                    LIMIT 1
                ");
        $st->execute([$regId, $branchId]);
      } else {
        $st = $pdo->prepare("
                    SELECT r.*, u.name AS owner_name
                    FROM registrations r
                    LEFT JOIN users u ON u.id = r.assigned_to
                    WHERE r.id=? AND r.registration_status IN ('active','completed')
                    LIMIT 1
                ");
        $st->execute([$regId]);
      }
      $reg = $st->fetch(PDO::FETCH_ASSOC);

      if (!$reg) {
        throw new Exception("Registration not found or access denied.");
      }

      $payments = [];
      $st = $pdo->prepare("
                SELECT *
                FROM registration_payments
                WHERE registration_id=?
                ORDER BY payment_date DESC, id DESC
            ");
      $st->execute([$regId]);
      $payments = $st->fetchAll(PDO::FETCH_ASSOC);

      $finalFee = (float) ($reg['final_fee'] ?? 0);
      $paidAmt = (float) ($reg['paid_amount'] ?? 0);
      $balance = max(0, $finalFee - $paidAmt);
      ?>

      <div class="pro-modal-wrap payment-modal-modern payment-entry-layout">
        <div class="pro-payment-summary">
          <div class="pro-pay-card pm-reg">
            <span class="label"><i class="fas fa-id-badge"></i> Registration No</span>
            <span class="value"><?= h($reg['registration_no'] ?: ('REG-' . $reg['id'])) ?></span>
          </div>
          <div class="pro-pay-card pm-student">
            <span class="label"><i class="fas fa-user-graduate"></i> Student</span>
            <span class="value"><?= h($reg['enquiry_snapshot_name'] ?: '-') ?></span>
          </div>
          <div class="pro-pay-card pm-program">
            <span class="label"><i class="fas fa-book-open"></i> Program</span>
            <span class="value"><?= h($reg['program_name'] ?: '-') ?></span>
          </div>
          <div class="pro-pay-card pm-owner">
            <span class="label"><i class="fas fa-user-tie"></i> Owner</span>
            <span class="value"><?= h($reg['owner_name'] ?: '-') ?></span>
          </div>
          <div class="pro-pay-card pm-fee">
            <span class="label"><i class="fas fa-coins"></i> Final Fee</span>
            <span class="value">₹ <?= h(number_format($finalFee, 2)) ?></span>
          </div>
          <div class="pro-pay-card pm-paid">
            <span class="label"><i class="fas fa-wallet"></i> Paid</span>
            <span class="value">₹ <?= h(number_format($paidAmt, 2)) ?></span>
          </div>
          <div class="pro-pay-card highlight pm-balance">
            <span class="label"><i class="fas fa-hourglass-half"></i> Balance</span>
            <span class="value">₹ <?= h(number_format($balance, 2)) ?></span>
          </div>
        </div>

        <div class="pro-modal-section payment-entry-col">
          <div class="pro-modal-head">
            <div class="pro-modal-head-icon"><i class="fas fa-plus-circle"></i></div>
            <div>
              <div class="pro-modal-title">Add Payment</div>
              <div class="pro-modal-subtitle">Enter payment details carefully</div>
            </div>
          </div>

          <form method="POST" id="paymentEntryForm">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="reg_id" value="<?= (int) $reg['id'] ?>">

            <div class="pro-payment-grid">
              <div>
                <label class="m-label"><i class="fas fa-rupee-sign"></i> Amount</label>
                <input type="number" step="0.01" max="<?= h($balance) ?>" name="amount" class="m-input" placeholder="Enter payment amount" required>
              </div>
              <div>
                <label class="m-label"><i class="fas fa-calendar-day"></i> Payment Date</label>
                <input type="date" name="payment_date" class="m-input" value="<?= h(date('Y-m-d')) ?>" required>
              </div>
              <div>
                <label class="m-label"><i class="fas fa-credit-card"></i> Payment Mode</label>
                <select name="payment_mode" class="m-input">
                  <option value="cash">Cash</option>
                  <option value="upi">UPI</option>
                  <option value="card">Card</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="cheque">Cheque</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="m-label"><i class="fas fa-tags"></i> Payment Type</label>
                <select name="payment_type" class="m-input">
                  <option value="advance">Advance</option>
                  <option value="partial" selected>Partial</option>
                  <option value="full">Full</option>
                  <option value="refund">Refund</option>
                </select>
              </div>
              <div>
                <label class="m-label"><i class="fas fa-hashtag"></i> Reference No</label>
                <input type="text" name="reference_no" class="m-input" placeholder="Transaction / Ref number">
              </div>
              <div>
                <label class="m-label"><i class="fas fa-check-circle"></i> Approval Status</label>
                <select name="approval_status" class="m-input">
                  <option value="approved" selected>Approved</option>
                  <option value="pending">Pending</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
              <div class="full">
                <label class="m-label"><i class="fas fa-comment-dots"></i> Remarks</label>
                <textarea name="remarks_payment" class="m-input" rows="3" placeholder="Add payment notes (optional)"></textarea>
              </div>
            </div>

            <div class="modal-actions">
              <button type="submit" name="add_payment" class="pro-btn pro-btn-primary">
                <i class="fas fa-save"></i> Save Payment
              </button>
            </div>
          </form>
        </div>

        <div class="pro-modal-section payment-entry-col">
          <div class="pro-modal-head">
            <div class="pro-modal-head-icon"><i class="fas fa-receipt"></i></div>
            <div>
              <div class="pro-modal-title">Payment History</div>
              <div class="pro-modal-subtitle">Previous payment transactions</div>
            </div>
          </div>

          <?php if (empty($payments)): ?>
            <div class="empty-note">No payments added yet.</div>
          <?php else: ?>
            <div class="pro-table-scroll">
              <table class="pro-mini-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Receipt No</th>
                    <th>Amount</th>
                    <th>Mode</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($payments as $p): ?>
                    <tr>
                      <td><?= h($p['payment_date']) ?></td>
                      <td><?= h($p['receipt_no'] ?: '-') ?></td>
                      <td>₹ <?= h(number_format((float) $p['amount'], 2)) ?></td>
                      <td><?= h($p['payment_mode']) ?></td>
                      <td><?= h($p['payment_type']) ?></td>
                      <td><?= h($p['approval_status']) ?></td>
                      <td>
                        <a href="index.php?page=payments/receipt&payment_id=<?= (int) $p['id'] ?>" target="_blank"
                          class="receipt-link">
                          <i class="fas fa-receipt"></i> Receipt
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
      </div>

      <?php
    } catch (Exception $e) {
      echo "<div class='empty-note error-note'>" . h($e->getMessage()) . "</div>";
    }
    exit;
  }

  // ---------------------------------
  // 2) ID Card Modal
  // ---------------------------------
  if ($action === 'id_card_modal') {
    $regId = (int) ($_GET['reg_id'] ?? 0);

    if ($regId <= 0) {
      echo "<div class='empty-note'>Invalid registration.</div>";
      exit;
    }

    try {
      if ($canAllBranches !== 1 && $branchId > 0) {
        $st = $pdo->prepare("
                    SELECT
                        r.*,
                        e.name AS enquiry_name,
                        e.phone AS enquiry_phone,
                        e.email AS enquiry_email,
                        rp.student_name,
                        rp.gender,
                        rp.dob,
                        rp.address,
                        rp.qualification,
                        rp.college_name,
                        rp.year_of_passout,
                        rp.parent_name,
                        rp.parent_phone,
                        rp.parent_occupation,
                        rp.emergency_contact,
                        rp.photo_path,
                        rp.signature_path
                    FROM registrations r
                    LEFT JOIN enquiries e ON e.id = r.enquiry_id
                    LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
                    WHERE r.id = ? AND r.branch_id = ? AND r.registration_status IN ('active','completed')
                    LIMIT 1
                ");
        $st->execute([$regId, $branchId]);
      } else {
        $st = $pdo->prepare("
                    SELECT
                        r.*,
                        e.name AS enquiry_name,
                        e.phone AS enquiry_phone,
                        e.email AS enquiry_email,
                        rp.student_name,
                        rp.gender,
                        rp.dob,
                        rp.address,
                        rp.qualification,
                        rp.college_name,
                        rp.year_of_passout,
                        rp.parent_name,
                        rp.parent_phone,
                        rp.parent_occupation,
                        rp.emergency_contact,
                        rp.photo_path,
                        rp.signature_path
                    FROM registrations r
                    LEFT JOIN enquiries e ON e.id = r.enquiry_id
                    LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
                    WHERE r.id = ? AND r.registration_status IN ('active','completed')
                    LIMIT 1
                ");
        $st->execute([$regId]);
      }

      $student = $st->fetch(PDO::FETCH_ASSOC);

      if (!$student) {
        throw new Exception("Student not found or access denied.");
      }

      $studentName = $student['student_name'] ?: $student['enquiry_snapshot_name'] ?: $student['enquiry_name'] ?: '-';
      $studentPhone = visibleStudentContactValue($student['enquiry_snapshot_phone'] ?: $student['enquiry_phone'] ?: ($student['emergency_contact'] ?: '-'));
      $studentEmail = visibleStudentContactValue($student['enquiry_snapshot_email'] ?: $student['enquiry_email'] ?: '-');
      $studentAddress = $student['address'] ?: '-';
      $cardNo = $student['registration_no'] ?: ('REG-' . $student['id']);
      $programName = $student['program_name'] ?: ucfirst((string) ($student['reg_type'] ?: 'Student'));
      $photoPath = !empty($student['photo_path']) ? ('uploads/registrations/' . ltrim((string) $student['photo_path'], '/\\')) : '';
      ?>
      <div class="idcard-builder" data-student-name="<?= h($studentName) ?>" data-card-no="<?= h($cardNo) ?>"
        data-program="<?= h($programName) ?>" data-phone="<?= h($studentPhone) ?>" data-email="<?= h($studentEmail) ?>"
        data-address="<?= h($studentAddress) ?>" data-photo="<?= h($photoPath) ?>">

        

        <div class="idc-wrap">
          <div class="idc-panel">
            <div class="idc-title">Card Details</div>

            <div class="idc-field">
              <label class="idc-label">Student Name</label>
              <input type="text" class="idc-input" id="idcStudentName" value="<?= h($studentName) ?>">
            </div>

            <div class="idc-field">
              <label class="idc-label">Registration No</label>
              <input type="text" class="idc-input" id="idcCardNo" value="<?= h($cardNo) ?>">
            </div>

            <div class="idc-field">
              <label class="idc-label">Program / Role</label>
              <input type="text" class="idc-input" id="idcProgram" value="<?= h($programName) ?>">
            </div>

            <div class="idc-field">
              <label class="idc-label">Contact Number</label>
              <input type="text" class="idc-input" id="idcPhone" value="<?= h($studentPhone) ?>">
            </div>

            <div class="idc-field">
              <label class="idc-label">Email</label>
              <input type="text" class="idc-input" id="idcEmail" value="<?= h($studentEmail) ?>">
            </div>

            <div class="idc-field">
              <label class="idc-label">Address</label>
              <textarea class="idc-textarea" id="idcAddress"><?= h($studentAddress) ?></textarea>
            </div>

            <div class="idc-field">
              <label class="idc-label">Student Photo</label>
              <input type="file" id="idcPhotoUpload" accept="image/*" class="idc-input">
              <div class="idc-photo-actions">
                <button type="button" class="idc-btn idc-btn-light" id="idcStartCamera">Use Webcam</button>
                <button type="button" class="idc-btn idc-btn-dark" id="idcCapturePhoto">Capture</button>
                <button type="button" class="idc-btn idc-btn-light" id="idcStopCamera">Stop Camera</button>
              </div>
              <div class="idc-helper">You can upload an image or capture one from webcam.</div>

              <div class="idc-video-wrap" id="idcVideoWrap">
                <video id="idcVideo" class="idc-video" autoplay playsinline></video>
                <canvas id="idcCanvas" style="display:none;"></canvas>
              </div>
            </div>

            <div class="idc-sign-upload-grid">
              <div class="idc-field">
                <label class="idc-label">Authority Signature (PNG only)</label>
                <input type="file" id="idcAuthoritySignUpload" accept=".png,image/png" class="idc-input">
              </div>

              <div class="idc-field">
                <label class="idc-label">Student Signature (PNG only)</label>
                <input type="file" id="idcStudentSignUpload" accept=".png,image/png" class="idc-input">
              </div>
            </div>

            <div class="idc-photo-actions" style="margin-top:18px;">
              <button type="button" class="idc-btn idc-btn-primary" id="idcDownloadFront">Download Front PNG</button>
              <button type="button" class="idc-btn idc-btn-primary" id="idcDownloadBack">Download Back PNG</button>
            </div>
          </div>

          <div class="idc-preview-area">
            <div class="idc-card" id="idCardFront">
              <div class="idc-card-inner">
                <div class="idc-brand">
                  <img src="assets/images/logo.png" alt="ATS Logo" class="idc-brand-logo">
                  <div class="idc-brand-name">ACCENT TECHNO SOFT</div>
                  <div class="idc-brand-tag">Quality Matters...</div>
                </div>

                <div class="idc-photo" id="idcPreviewPhotoBox">
                  <?php if (!empty($photoPath)): ?>
                    <img src="<?= h($photoPath) ?>" id="idcPreviewPhoto" alt="Student Photo">
                  <?php else: ?>
                    <div class="idc-photo-placeholder" id="idcPreviewPlaceholder">PHOTO</div>
                    <img src="" id="idcPreviewPhoto" alt="Student Photo" style="display:none;">
                  <?php endif; ?>
                </div>

                <div class="idc-student-name" id="idcPreviewName"><?= h($studentName) ?></div>
                <div class="idc-program" id="idcPreviewProgram"><?= h($programName) ?></div>
                <div class="idc-cardno" id="idcPreviewCardNo"><?= h($cardNo) ?></div>
                <div class="idc-website">www.accenttechnosoft.com</div>
              </div>
            </div>

            <div class="idc-card" id="idCardBack">
              <div class="idc-card-inner">
                <div class="idc-back-title">Student Information</div>

                <div class="idc-back-lines">
                  <div><b>Address</b>: <span id="idcPreviewAddress"><?= nl2br(h($studentAddress)) ?></span></div>
                  <div><b>Contact No</b>: <span id="idcPreviewPhone"><?= h($studentPhone) ?></span></div>
                  <div><b>Email</b>: <span id="idcPreviewEmail"><?= h($studentEmail) ?></span></div>
                </div>

                <div class="idc-sign-row">
                  <div class="idc-sign-box">
                    <div class="idc-sign-preview"><img src="" id="idcAuthoritySignPreview" alt="Authority Signature"
                        style="display:none;"></div>
                    <div class="idc-sign-line"></div>
                    Issuing Authority
                  </div>
                  <div class="idc-sign-box">
                    <div class="idc-sign-preview"><img src="" id="idcStudentSignPreview" alt="Student Signature"
                        style="display:none;"></div>
                    <div class="idc-sign-line"></div>
                    Student Signature
                  </div>
                </div>

                <div class="idc-office">
                  <div><b>Office Address :</b></div>
                  <div>No.202, Nehru Street, Ramnagar,</div>
                  <div>Coimbatore - 641009</div>
                  <div>Ph : 0422-4212232 &nbsp; Mob : 9786978525</div>
                  <div>Email : info@accenttechnosoft.com</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php
    } catch (Exception $e) {
      echo "<div class='empty-note error-note'>" . h($e->getMessage()) . "</div>";
    }
    exit;
  }

  echo "<div class='empty-note'>Invalid request.</div>";
  exit;
}


/* =========================================================
   Delete Registration
========================================================= */
if (isset($_POST['delete_registration'])) {
  $token = $_POST['csrf_token'] ?? '';
  if (!verifyCSRF($token)) {
    $error = "Invalid request (CSRF). Please refresh and try again.";
  } else {
    $regId = (int) ($_POST['reg_id'] ?? 0);

    if ($regId <= 0) {
      $error = "Invalid registration selected.";
    } else {
      try {
        if ($canAllBranches !== 1 && $branchId > 0) {
          $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND branch_id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
          $st->execute([$regId, $branchId]);
        } else {
          $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
          $st->execute([$regId]);
        }

        $regForDelete = $st->fetch(PDO::FETCH_ASSOC);
        if (!$regForDelete) {
          throw new Exception("Registration not found or access denied.");
        }
        if (!canDeleteRegistrationRecord($regForDelete)) {
          throw new Exception("Delete allowed only after course/internship completion, full payment, and certificate issued.");
        }

        $del = $pdo->prepare("DELETE FROM registrations WHERE id=?");
        $del->execute([$regId]);

        $success = "Record deleted successfully.";
      } catch (Exception $e) {
        $error = "Delete failed. " . $e->getMessage();
      }
    }
  }
}

/* =========================================================
   Add Payment
========================================================= */
if (isset($_POST['add_payment'])) {
  $token = $_POST['csrf_token'] ?? '';
  if (!verifyCSRF($token)) {
    $error = "Invalid request (CSRF). Please refresh and try again.";
  } else {
    $regId = (int) ($_POST['reg_id'] ?? 0);
    $amount = regDecVal($_POST['amount'] ?? 0);
    $payment_date = regNull($_POST['payment_date'] ?? '');
    $payment_mode = regNull($_POST['payment_mode'] ?? 'cash') ?: 'cash';
    $payment_type = regNull($_POST['payment_type'] ?? 'partial') ?: 'partial';
    $reference_no = regNull($_POST['reference_no'] ?? '');
    $approval_status = regNull($_POST['approval_status'] ?? 'approved') ?: 'approved';
    $remarksPay = regNull($_POST['remarks_payment'] ?? '');

    if ($regId <= 0) {
      $error = "Invalid registration selected.";
    } elseif ($amount <= 0) {
      $error = "Amount must be greater than zero.";
    } elseif ($payment_date === null) {
      $error = "Payment date is required.";
    } else {
      try {
        if ($canAllBranches !== 1 && $branchId > 0) {
          $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND branch_id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
          $st->execute([$regId, $branchId]);
        } else {
          $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
          $st->execute([$regId]);
        }
        $reg = $st->fetch(PDO::FETCH_ASSOC);

        if (!$reg) {
          throw new Exception("Registration not found or access denied.");
        }

        $finalFee = (float) ($reg['final_fee'] ?? 0);
        $paidAmt = (float) ($reg['paid_amount'] ?? 0);
        $balance = max(0, $finalFee - $paidAmt);

        if ($payment_type !== 'refund' && $amount > $balance && $balance > 0) {
          throw new Exception("Amount cannot be greater than balance.");
        }

        $staffId = (int) ($reg['assigned_to'] ?? 0);
        if ($staffId <= 0) {
          throw new Exception("Front office owner missing in registration.");
        }

        $receiptNo = makeReceiptNo($pdo);

        $pdo->beginTransaction();

        $ins = $pdo->prepare("
                    INSERT INTO registration_payments (
                        registration_id, branch_id, staff_id, collected_by, approved_by,
                        amount, payment_date, payment_mode, payment_type,
                        reference_no, receipt_no, approval_status, remarks,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?, ?,
                        NOW(), NOW()
                    )
                ");
        $ins->execute([
          $regId,
          (int) $reg['branch_id'],
          $staffId,
          $userId,
          ($approval_status === 'approved' ? $userId : null),
          $amount,
          $payment_date,
          $payment_mode,
          $payment_type,
          $reference_no,
          $receiptNo,
          $approval_status,
          $remarksPay
        ]);

        recalcRegistrationPaymentsSummary($pdo, $regId);

        $pdo->commit();
        $success = "Payment saved successfully! Receipt No: " . $receiptNo;
      } catch (Exception $e) {
        if ($pdo->inTransaction())
          $pdo->rollBack();
        $error = "Failed to add payment. " . $e->getMessage();
      }
    }
  }
}

/* =========================================================
   Filters
========================================================= */
$q = trim((string) ($_GET['q'] ?? ''));
$regType = trim((string) ($_GET['reg_type'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$payStat = trim((string) ($_GET['payment_status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$page = (int) ($_GET['p'] ?? 1);
if ($page < 1)
  $page = 1;

$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = ["r.registration_status IN ('active','completed')"];
$params = [];

if (in_array($roleName, ['front office', 'corporate executive', 'corporte excutive'], true)) {
    $where[] = "r.assigned_to = ?";
    $params[] = $userId;
}
if ($canAllBranches !== 1 && $branchId > 0) {
  $where[] = "r.branch_id = ?";
  $params[] = $branchId;
}

if ($regType !== '' && in_array($regType, ['course', 'internship', 'workshop'], true)) {
  $where[] = "r.reg_type = ?";
  $params[] = $regType;
}

if ($status !== '' && in_array($status, ['active', 'completed'], true)) {
  $where[] = "r.registration_status = ?";
  $params[] = $status;
}

if ($payStat !== '' && in_array($payStat, ['unpaid', 'partial', 'paid'], true)) {
  $where[] = "r.payment_status = ?";
  $params[] = $payStat;
}

if ($from !== '') {
  $where[] = "DATE(COALESCE(r.joined_on, r.created_at)) >= ?";
  $params[] = $from;
}
if ($to !== '') {
  $where[] = "DATE(COALESCE(r.joined_on, r.created_at)) <= ?";
  $params[] = $to;
}

if ($q !== '') {
  $like = '%' . $q . '%';
  $where[] = "(
        r.registration_no LIKE ?
        OR r.enquiry_snapshot_name LIKE ?
        OR r.enquiry_snapshot_phone LIKE ?
        OR r.enquiry_snapshot_email LIKE ?
        OR r.program_name LIKE ?
        OR r.batch_name LIKE ?
        OR e.enquiry_no LIKE ?
        OR e.name LIKE ?
        OR e.phone LIKE ?
        OR u.name LIKE ?
    )";
  array_push($params, $like, $like, $like, $like, $like, $like, $like, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* =========================================================
   Count
========================================================= */
$totalRows = 0;
try {
  $cnt = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations r
        LEFT JOIN enquiries e ON e.id = r.enquiry_id
        LEFT JOIN users u ON u.id = r.assigned_to
        $whereSql
    ");
  $cnt->execute($params);
  $totalRows = (int) $cnt->fetchColumn();
} catch (Exception $e) {
  $totalRows = 0;
}

$totalPages = (int) ceil($totalRows / $perPage);
if ($totalPages < 1)
  $totalPages = 1;
if ($page > $totalPages)
  $page = $totalPages;

/* =========================================================
   Summary
========================================================= */
$summary = ['active' => 0, 'completed' => 0, 'paid' => 0, 'partial' => 0, 'unpaid' => 0];

try {
  $sumWhere = ["r.registration_status IN ('active','completed')"];
  $sumParams = [];

  // Restrict summary for front office
if (in_array($roleName, ['front office', 'corporate executive', 'corporte excutive'], true)) {
    $sumWhere[] = "r.assigned_to = ?";
    $sumParams[] = $userId;
}

  if ($canAllBranches !== 1 && $branchId > 0) {
    $sumWhere[] = "r.branch_id = ?";
    $sumParams[] = $branchId;
  }

  $sumSql = 'WHERE ' . implode(' AND ', $sumWhere);

  $st = $pdo->prepare("
        SELECT
            SUM(CASE WHEN r.registration_status='active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN r.registration_status='completed' THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN r.payment_status='paid' THEN 1 ELSE 0 END) AS paid_count,
            SUM(CASE WHEN r.payment_status='partial' THEN 1 ELSE 0 END) AS partial_count,
            SUM(CASE WHEN r.payment_status='unpaid' THEN 1 ELSE 0 END) AS unpaid_count
        FROM registrations r
        $sumSql
    ");
  $st->execute($sumParams);
  $x = $st->fetch(PDO::FETCH_ASSOC);

  if ($x) {
    $summary['active'] = (int) ($x['active_count'] ?? 0);
    $summary['completed'] = (int) ($x['completed_count'] ?? 0);
    $summary['paid'] = (int) ($x['paid_count'] ?? 0);
    $summary['partial'] = (int) ($x['partial_count'] ?? 0);
    $summary['unpaid'] = (int) ($x['unpaid_count'] ?? 0);
  }
} catch (Exception $e) {
}

/* =========================================================
   Fetch Rows (all rows for client-side table)
========================================================= */
$rows = [];
try {
  $sql = "
        SELECT
            r.*,
            e.enquiry_no,
            e.name AS enquiry_name,
            e.phone AS enquiry_phone,
            u.name AS owner_name
        FROM registrations r
        LEFT JOIN enquiries e ON e.id = r.enquiry_id
        LEFT JOIN users u ON u.id = r.assigned_to
        $whereSql
        ORDER BY r.id DESC
    ";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  $totalRows = count($rows);
  $totalPages = 1;
  $page = 1;
} catch (Exception $e) {
  $rows = [];
}

$baseUrl = "index.php?page=registrations/list"
  . "&q=" . urlencode($q)
  . "&reg_type=" . urlencode($regType)
  . "&status=" . urlencode($status)
  . "&payment_status=" . urlencode($payStat)
  . "&from=" . urlencode($from)
  . "&to=" . urlencode($to);

function regTypeBadgeList($type)
{
  $map = [
    'course' => ['#e91e63', '#ffe9f0', 'Course'],
    'internship' => ['#0288d1', '#e8f4fd', 'Internship'],
    'workshop' => ['#ff9800', '#fff4e5', 'Workshop'],
  ];
  $c = $map[$type][0] ?? '#607d8b';
  $bg = $map[$type][1] ?? '#eef2f5';
  $t = $map[$type][2] ?? ucfirst((string) $type);
  return '<span class="badge-pill-custom" style="color:' . $c . ';background:' . $bg . ';border-color:' . $bg . ';">' . $t . '</span>';
}
function regStatusBadge($status)
{
  $map = [
    'active' => ['#2e7d32', '#e8f5e9', 'Active'],
    'completed' => ['#6a1b9a', '#f3e5f5', 'Completed'],
  ];
  $c = $map[$status][0] ?? '#607d8b';
  $bg = $map[$status][1] ?? '#eef2f5';
  $t = $map[$status][2] ?? ucfirst((string) $status);
  return '<span class="badge-pill-custom" style="color:' . $c . ';background:' . $bg . ';border-color:' . $bg . ';">' . $t . '</span>';
}
function payStatusBadgeList($type)
{
  $map = [
    'unpaid' => ['#d32f2f', '#ffebee', 'Unpaid'],
    'partial' => ['#ff9800', '#fff4e5', 'Partial'],
    'paid' => ['#2e7d32', '#e8f5e9', 'Paid'],
  ];
  $c = $map[$type][0] ?? '#607d8b';
  $bg = $map[$type][1] ?? '#eef2f5';
  $t = $map[$type][2] ?? ucfirst((string) $type);
  return '<span class="badge-pill-custom" style="color:' . $c . ';background:' . $bg . ';border-color:' . $bg . ';">' . $t . '</span>';
}
?>



<h2 style="display:none;">Registrations List</h2>

<?php if ($success): ?>
  <script>
    Swal.fire({
      icon: 'success',
      title: 'Success',
      text: '<?= addslashes($success) ?>',
      confirmButtonColor: '#e91e63'
    }).then(() => window.location.href = "<?= $baseUrl ?>");
  </script>
<?php endif; ?>

<?php if ($error): ?>
  <script>
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: '<?= addslashes($error) ?>',
      confirmButtonColor: '#e91e63'
    });
  </script>
<?php endif; ?>

<div class="leads-dashboard">
  <div class="dashboard-header">
    <h2><i class="fas fa-user-graduate" style="margin-right: 12px; color: #e91e63;"></i>Registration Management</h2>
    <div class="header-stats">
      <span class="stat-item"><i class="fas fa-database"></i> Total: <?= (int) $totalRows ?></span>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <i class="fas fa-sliders-h" style="margin-right: 8px;"></i> Filter Registrations
    </div>
    <form method="GET" action="index.php" class="filter-form">
      <input type="hidden" name="page" value="registrations/list">

      <div class="filter-grid">
        <div class="filter-item search">
          <label><i class="fas fa-search"></i> Search</label>
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Student / Reg No / Phone / Program / Owner">
        </div>

        <div class="filter-item">
          <label><i class="fas fa-layer-group"></i> Type</label>
          <select name="reg_type">
            <option value="">All</option>
            <option value="course" <?= $regType === 'course' ? 'selected' : ''; ?>>Course</option>
            <option value="internship" <?= $regType === 'internship' ? 'selected' : ''; ?>>Internship</option>
            <option value="workshop" <?= $regType === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
          </select>
        </div>

        <div class="filter-item">
          <label><i class="fas fa-tag"></i> Reg Status</label>
          <select name="status">
            <option value="">All</option>
            <option value="active" <?= $status === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="completed" <?= $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
          </select>
        </div>

        <div class="filter-item">
          <label><i class="fas fa-wallet"></i> Payment</label>
          <select name="payment_status">
            <option value="">All</option>
            <option value="unpaid" <?= $payStat === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
            <option value="partial" <?= $payStat === 'partial' ? 'selected' : ''; ?>>Partial</option>
            <option value="paid" <?= $payStat === 'paid' ? 'selected' : ''; ?>>Paid</option>
          </select>
        </div>

        <div class="filter-item date">
          <label><i class="fas fa-calendar-alt"></i> From</label>
          <input type="date" name="from" value="<?= h($from) ?>">
        </div>

        <div class="filter-item date">
          <label><i class="fas fa-calendar-check"></i> To</label>
          <input type="date" name="to" value="<?= h($to) ?>">
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn-icon-only apply" title="Apply filters">
            <i class="fas fa-filter"></i>
          </button>
          <a href="index.php?page=registrations/list" class="btn-icon-only reset" title="Reset filters">
            <i class="fas fa-undo-alt"></i>
          </a>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="table-header-flex">
        <div class="table-title">
          <i class="fas fa-list"></i> Confirmed Registrations (<?= (int) $totalRows ?>)
        </div>
        <div id="datatableControls"></div>
      </div>
    </div>

    <div class="table-container">
      <table class="leads-table" id="registrationsTable">
          <thead>
            <tr>
              <th>Registration</th>
              <th>Student</th>
              <th>Program</th>
              <th>Fees</th>
              <th>Owner</th>
              <th style="text-align:center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="6" class="no-data">
                  <i class="fas fa-inbox"></i>
                  No active or completed registrations found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td>
                    <div class="primary-text"><?= h($r['registration_no'] ?: ('REG-' . $r['id'])) ?></div>
                    <div class="secondary-text"><?= regTypeBadgeList($r['reg_type'] ?? '') ?></div>
                  </td>

                  <td>
                    <div class="primary-text"><?= h($r['enquiry_snapshot_name'] ?: $r['enquiry_name'] ?: '-') ?></div>
                    <div class="secondary-text"><i class="fas fa-phone-alt"></i>
                      <?= h(visibleStudentContactValue($r['enquiry_snapshot_phone'] ?: $r['enquiry_phone'] ?: '-')) ?></div>
                  </td>

                  <td>
                    <div class="primary-text"><?= h($r['program_name'] ?: '-') ?></div>
                    <div class="secondary-text"><?= h($r['batch_name'] ?: '-') ?></div>
                    <div class="secondary-text"><?= regStatusBadge($r['registration_status'] ?? '') ?>
                      <?= payStatusBadgeList($r['payment_status'] ?? '') ?></div>
                  </td>

                  <td>
                    <div class="primary-text">₹ <?= h(number_format((float) ($r['final_fee'] ?? 0), 2)) ?></div>
                    <div class="secondary-text">Paid: ₹ <?= h(number_format((float) ($r['paid_amount'] ?? 0), 2)) ?></div>
                    <div class="secondary-text">Bal: ₹ <?= h(number_format((float) ($r['balance_amount'] ?? 0), 2)) ?></div>
                  </td>

                  <td>
                    <div class="primary-text"><?= h($r['owner_name'] ?: '-') ?></div>
                    <div class="secondary-text">Front Office Credit</div>
                  </td>

                  <td>
                    <div class="action-buttons">
                      <button type="button" class="action-btn view" onclick="openHistoryModal(<?= (int) $r['id'] ?>)"
                        title="View History">
                        <i class="fas fa-eye"></i>
                      </button>

                      <a class="action-btn edit" href="index.php?page=registrations/convert & reg_id=<?= (int) $r['id'] ?>"
                        title="Edit Registration">
                        <i class="fas fa-pen"></i>
                      </a>

                      <button type="button" class="action-btn payment" onclick="openPaymentModal(<?= (int) $r['id'] ?>)"
                        title="Add Payment">
                        <i class="fas fa-wallet"></i>
                      </button>

                      <button type="button" class="action-btn idcard" onclick="openIdCardModal(<?= (int) $r['id'] ?>)"
                        title="Generate ID Card">
                        <i class="fas fa-id-card"></i>
                      </button>

                      <?php if (canDeleteRegistrationRecord($r)): ?>
                        <form method="POST" class="deleteRegistrationForm" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                          <input type="hidden" name="reg_id" value="<?= (int) $r['id'] ?>">
                          <button type="submit" name="delete_registration" class="action-btn delete"
                            title="Delete Registration">
                            <i class="fas fa-trash-alt"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
    </div>

    <div class="pagination-wrapper server-pagination">
      <div class="pagination-info">
          Page <?= (int) $page ?> of <?= (int) $totalPages ?>
      </div>

        <div class="pagination">
          <?php $prev = max(1, $page - 1);
          $next = min($totalPages, $page + 1); ?>
          <a class="page-link" href="<?= $baseUrl ?>&p=1"><i class="fas fa-angle-double-left"></i> First</a>
          <a class="page-link" style="<?= $page <= 1 ? 'pointer-events:none;opacity:.5;' : '' ?>"
            href="<?= $baseUrl ?>&p=<?= (int) $prev ?>"><i class="fas fa-angle-left"></i>&nbsp; Prev</a>
          <a class="page-link" style="<?= $page >= $totalPages ? 'pointer-events:none;opacity:.5;' : '' ?>"
            href="<?= $baseUrl ?>&p=<?= (int) $next ?>">Next &nbsp;<i class="fas fa-angle-right"></i></a>
          <a class="page-link" href="<?= $baseUrl ?>&p=<?= (int) $totalPages ?>">Last <i
              class="fas fa-angle-double-right"></i></a>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="crm-modal-backdrop" id="crmModalBackdrop">
  <div class="crm-modal">
    <div class="crm-modal-header">
      <div class="crm-modal-title" id="crmModalTitle">
        <i class="fas fa-layer-group"></i> Details
      </div>
      <button type="button" class="crm-modal-close" onclick="closeCrmModal()">×</button>
    </div>
    <div class="crm-modal-body" id="crmModalBody">
      <div class="empty-note">Loading...</div>
    </div>
  </div>
</div>

<script>
  function showSweetAlert(icon, title, text) {
    if (typeof Swal !== 'undefined' && Swal.fire) {
      Swal.fire({
        icon: icon,
        title: title,
        text: text,
        confirmButtonColor: '#e91e63'
      });
    } else {
      alert(text);
    }
  }

  function applyModernIconTooltips(root) {
    const scope = root || document;
    const selector = '.btn-icon-only[title], .action-btn[title], .icon-btn[title], .source-icon[title]';
    const targets = scope.querySelectorAll(selector);

    targets.forEach(el => {
      const t = (el.getAttribute('title') || '').trim();
      if (!t) return;
      el.setAttribute('data-tooltip', t);
      el.setAttribute('aria-label', t);
      el.classList.add('ui-tooltip');
      el.removeAttribute('title');
    });
  }

  function openCrmModal(title) {
    document.getElementById('crmModalTitle').innerHTML = '<i class="fas fa-layer-group"></i> ' + (title || 'Details');
    document.getElementById('crmModalBody').innerHTML = '<div class="empty-note">Loading...</div>';

    const wrapper = document.querySelector('.wrapper');
    if (wrapper) wrapper.removeAttribute('aria-hidden');

    document.getElementById('crmModalBackdrop').style.display = 'flex';
  }

  function closeCrmModal() {
    if (window.__stopIdCardCamera) {
      window.__stopIdCardCamera();
      window.__stopIdCardCamera = null;
    }

    document.getElementById('crmModalBackdrop').style.display = 'none';
    document.getElementById('crmModalBody').innerHTML = '';

    const wrapper = document.querySelector('.wrapper');
    if (wrapper) wrapper.removeAttribute('aria-hidden');
  }

  document.getElementById('crmModalBackdrop').addEventListener('click', function (e) {
    if (e.target === this) closeCrmModal();
  });

  async function loadModalHtml(url) {
    const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    return await res.text();
  }

  async function openHistoryModal(regId) {
    openCrmModal('Student History');
    const url =`index.php?page=registrations/list&ajax=1&action=view_history&reg_id=${regId}`;
    const html = await loadModalHtml(url);
    document.getElementById('crmModalBody').innerHTML = html;
    applyModernIconTooltips(document.getElementById('crmModalBody'));
  }

  async function openIdCardModal(regId) {
    openCrmModal('Student ID Card');
    const url = `index.php?page=registrations/list&ajax=1&action=id_card_modal&reg_id=${regId}`;
    const html = await loadModalHtml(url);
    document.getElementById('crmModalBody').innerHTML = html;
    applyModernIconTooltips(document.getElementById('crmModalBody'));

    const wrapper = document.querySelector('.wrapper');
    if (wrapper) wrapper.removeAttribute('aria-hidden');

    initIdCardBuilder();
  }

  async function openPaymentModal(regId) {

console.log("Registration ID:", regId);   // DEBUG

openCrmModal('Payment Entry');

const url =
`index.php?page=registrations/list&ajax=1&action=payment_modal&reg_id=${regId}`;

const html = await loadModalHtml(url);

document.getElementById('crmModalBody').innerHTML = html;
applyModernIconTooltips(document.getElementById('crmModalBody'));

const wrapper = document.querySelector('.wrapper');
if (wrapper) wrapper.removeAttribute('aria-hidden');

}
  document.addEventListener('submit', function (e) {
    if (e.target && e.target.id === 'paymentEntryForm') {
      const wrapper = document.querySelector('.wrapper');
      if (wrapper) wrapper.removeAttribute('aria-hidden');
    }
  });

  document.querySelectorAll('.deleteRegistrationForm').forEach(f => {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Delete Registration?',
        text: 'This will delete the registration and linked profile/payments.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e91e63'
      }).then((r) => {
        if (r.isConfirmed) f.submit();
      });
    });
  });

  document.addEventListener("DOMContentLoaded", function(){
    applyModernIconTooltips(document);

    if (typeof crmDataTable !== 'function') return;

    try {
      crmDataTable('#registrationsTable', {
        pageLength: 10,
        lengthMenu: [5, 10, 20, 50, 100],
        ordering: true,
        order: [[0, 'desc']],
        searchPlaceholder: "Search registrations...",
        dom:
          "<'dt-top'lfB>" +
          "rt" +
          "<'dt-bottom'ip>"
      });

      setTimeout(() => {
        const controls = document.querySelector('.dt-top');
        const target = document.getElementById('datatableControls');
        const serverPagination = document.querySelector('.server-pagination');

        if (controls && target) {
          target.appendChild(controls);
        }
        if (serverPagination) {
          serverPagination.style.display = 'none';
        }
      }, 100);
    } catch (e) {}
  });

  function initIdCardBuilder() {
    const builder = document.querySelector('.idcard-builder');
    if (!builder) return;

    const studentName = document.getElementById('idcStudentName');
    const cardNo = document.getElementById('idcCardNo');
    const program = document.getElementById('idcProgram');
    const phone = document.getElementById('idcPhone');
    const email = document.getElementById('idcEmail');
    const address = document.getElementById('idcAddress');
    const upload = document.getElementById('idcPhotoUpload');
    const authoritySignUpload = document.getElementById('idcAuthoritySignUpload');
    const studentSignUpload = document.getElementById('idcStudentSignUpload');

    const previewName = document.getElementById('idcPreviewName');
    const previewCardNo = document.getElementById('idcPreviewCardNo');
    const previewProgram = document.getElementById('idcPreviewProgram');
    const previewPhone = document.getElementById('idcPreviewPhone');
    const previewEmail = document.getElementById('idcPreviewEmail');
    const previewAddress = document.getElementById('idcPreviewAddress');
    const previewPhoto = document.getElementById('idcPreviewPhoto');
    const previewPlaceholder = document.getElementById('idcPreviewPlaceholder');
    const authoritySignPreview = document.getElementById('idcAuthoritySignPreview');
    const studentSignPreview = document.getElementById('idcStudentSignPreview');
    const downloadFrontBtn = document.getElementById('idcDownloadFront');
    const downloadBackBtn = document.getElementById('idcDownloadBack');

    const startCameraBtn = document.getElementById('idcStartCamera');
    const capturePhotoBtn = document.getElementById('idcCapturePhoto');
    const stopCameraBtn = document.getElementById('idcStopCamera');
    const videoWrap = document.getElementById('idcVideoWrap');
    const video = document.getElementById('idcVideo');
    const canvas = document.getElementById('idcCanvas');

    let mediaStream = null;

    function syncPreview() {
      previewName.textContent = studentName.value || '-';
      previewCardNo.textContent = cardNo.value || '-';
      previewProgram.textContent = program.value || '-';
      previewPhone.textContent = phone.value || '-';
      previewEmail.textContent = email.value || '-';
      previewAddress.innerHTML = (address.value || '-').replace(/\n/g, '<br>');
    }

    [studentName, cardNo, program, phone, email, address].forEach(el => {
      if (el) el.addEventListener('input', syncPreview);
    });

    if (upload) {
      upload.addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (evt) {
          previewPhoto.src = evt.target.result;
          previewPhoto.style.display = 'block';
          if (previewPlaceholder) previewPlaceholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
      });
    }

    function bindPngSignatureUpload(input, previewImg, label) {
      if (!input || !previewImg) return;

      input.addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0];
        if (!file) return;

        const isPng = (file.type === 'image/png') || /\.png$/i.test(file.name);
        if (!isPng) {
          showSweetAlert('error', 'Invalid File', label + ' must be a PNG file.');
          input.value = '';
          previewImg.src = '';
          previewImg.style.display = 'none';
          return;
        }

      const reader = new FileReader();
      reader.onload = function (evt) {
        previewImg.src = evt.target.result;
        previewImg.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }

  bindPngSignatureUpload(authoritySignUpload, authoritySignPreview, 'Authority signature');
  bindPngSignatureUpload(studentSignUpload, studentSignPreview, 'Student signature');

  function roundedRect(ctx, x, y, width, height, radius) {
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + width - radius, y);
    ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
    ctx.lineTo(x + width, y + height - radius);
    ctx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
    ctx.lineTo(x + radius, y + height);
    ctx.quadraticCurveTo(x, y + height, x, y + height - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
  }

  function drawWrappedText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
    const words = String(text || '-').split(/\s+/);
    const lines = [];
    let line = '';

    words.forEach(word => {
      const test = line ? line + ' ' + word : word;
      if (ctx.measureText(test).width > maxWidth && line) {
        lines.push(line);
        line = word;
      } else {
        line = test;
      }
    });

    if (line) lines.push(line);

    const safeLines = typeof maxLines === 'number' ? lines.slice(0, maxLines) : lines;
    safeLines.forEach((item, index) => {
      ctx.fillText(item, x, y + (index * lineHeight));
    });
  }

  function loadImageSafe(src) {
    return new Promise(resolve => {
      if (!src) {
        resolve(null);
        return;
      }

      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = src.startsWith('data:') ? src : new URL(src, window.location.href).href;
    });
  }

  async function renderFrontCardCanvas() {
    const canvas = document.createElement('canvas');
    const width = 640;
    const height = 1020;
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f5f5f5';
    ctx.fillRect(0, 0, width, height);

    roundedRect(ctx, 18, 18, width - 36, height - 36, 34);
    ctx.fillStyle = '#ffffff';
    ctx.fill();
    ctx.strokeStyle = '#f0d3e1';
    ctx.lineWidth = 4;
    ctx.stroke();

    ctx.fillStyle = 'rgba(233,30,99,0.16)';
    ctx.beginPath();
    ctx.arc(75, 80, 115, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(width - 65, height - 70, 95, 0, Math.PI * 2);
    ctx.fill();

    const logo = await loadImageSafe('assets/images/logo.png');
    if (logo) {
      ctx.drawImage(logo, (width - 220) / 2, 38, 220, 95);
    }

    ctx.fillStyle = '#111827';
    ctx.textAlign = 'center';
    ctx.font = 'bold 26px Georgia, serif';
    ctx.fillText('ACCENT TECHNO SOFT', width / 2, 170);

    ctx.fillStyle = '#d946ef';
    ctx.font = 'italic 20px Georgia, serif';
    ctx.fillText('Quality Matters...', width / 2, 205);

    const photoSrc = previewPhoto && previewPhoto.style.display !== 'none' ? previewPhoto.src : '';
    const photo = await loadImageSafe(photoSrc);

    ctx.save();
    ctx.beginPath();
    ctx.arc(width / 2, 370, 150, 0, Math.PI * 2);
    ctx.closePath();
    ctx.fillStyle = '#f8d7ea';
    ctx.fill();
    ctx.lineWidth = 10;
    ctx.strokeStyle = '#ef5bb5';
    ctx.stroke();
    ctx.clip();

    if (photo) {
      ctx.drawImage(photo, width / 2 - 150, 220, 300, 300);
    } else {
      ctx.fillStyle = '#fce7f3';
      ctx.fillRect(width / 2 - 150, 220, 300, 300);
      ctx.fillStyle = '#9d174d';
      ctx.font = 'bold 28px Arial';
      ctx.fillText('PHOTO', width / 2, 385);
    }
    ctx.restore();

    ctx.fillStyle = '#ec4899';
    ctx.font = 'bold 36px Arial';
    drawWrappedText(ctx, studentName.value || '-', width / 2, 590, 470, 42, 2);

    ctx.fillStyle = '#111827';
    ctx.font = 'bold 32px Arial';
    drawWrappedText(ctx, program.value || '-', width / 2, 690, 460, 38, 2);

    ctx.font = 'bold 40px Arial';
    ctx.fillText(cardNo.value || '-', width / 2, 800);

    ctx.fillStyle = '#ec4899';
    ctx.font = 'bold 24px Arial';
    ctx.fillText('www.accenttechnosoft.com', width / 2, 900);

    return canvas;
  }

  async function renderBackCardCanvas() {
    const canvas = document.createElement('canvas');
    const width = 640;
    const height = 1020;
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f5f5f5';
    ctx.fillRect(0, 0, width, height);

    roundedRect(ctx, 18, 18, width - 36, height - 36, 34);
    ctx.fillStyle = '#ffffff';
    ctx.fill();
    ctx.strokeStyle = '#f0d3e1';
    ctx.lineWidth = 4;
    ctx.stroke();

    ctx.fillStyle = 'rgba(233,30,99,0.16)';
    ctx.beginPath();
    ctx.arc(75, 80, 115, 0, Math.PI * 2);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(width - 65, height - 70, 95, 0, Math.PI * 2);
    ctx.fill();

    ctx.textAlign = 'left';
    ctx.fillStyle = '#111827';
    ctx.font = 'bold 24px Arial';
    ctx.fillText('Student Information', 56, 90);

    ctx.font = 'bold 22px Arial';
    ctx.fillText('Address', 56, 160);
    ctx.font = '22px Arial';
    ctx.fillText(':', 170, 160);
    drawWrappedText(ctx, address.value || '-', 195, 160, 380, 32, 4);

    ctx.font = 'bold 22px Arial';
    ctx.fillText('Contact No', 56, 340);
    ctx.font = '22px Arial';
    ctx.fillText(':', 170, 340);
    ctx.fillText(phone.value || '-', 195, 340);

    ctx.font = 'bold 22px Arial';
    ctx.fillText('Email', 56, 390);
    ctx.font = '22px Arial';
    ctx.fillText(':', 170, 390);
    drawWrappedText(ctx, email.value || '-', 195, 390, 380, 30, 2);

    const authoritySign = await loadImageSafe(authoritySignPreview && authoritySignPreview.style.display !== 'none' ? authoritySignPreview.src : '');
    const studentSign = await loadImageSafe(studentSignPreview && studentSignPreview.style.display !== 'none' ? studentSignPreview.src : '');

    if (authoritySign) {
      ctx.drawImage(authoritySign, 70, 540, 180, 70);
    }
    if (studentSign) {
      ctx.drawImage(studentSign, 385, 540, 180, 70);
    }

    ctx.strokeStyle = '#111827';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(60, 620);
    ctx.lineTo(260, 620);
    ctx.moveTo(380, 620);
    ctx.lineTo(580, 620);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = '#374151';
    ctx.font = 'bold 20px Arial';
    ctx.fillText('Issuing Authority', 160, 665);
    ctx.fillText('Student Signature', 480, 665);

    ctx.textAlign = 'center';
    ctx.fillStyle = '#111827';
    ctx.font = 'bold 22px Arial';
    ctx.fillText('Office Address :', width / 2, 760);

    ctx.font = 'bold 20px Arial';
    ctx.fillText('No.202, Nehru Street, Ramnagar,', width / 2, 812);
    ctx.fillText('Coimbatore - 641009', width / 2, 848);
    ctx.fillText('Ph : 0422-4212232   Mob : 9786978525', width / 2, 884);
    ctx.fillText('Email : info@accenttechnosoft.com', width / 2, 920);

    return canvas;
  }

  async function downloadCardAsPng(cardId, fileName) {
    try {
      const canvas = cardId === 'idCardBack'
        ? await renderBackCardCanvas()
        : await renderFrontCardCanvas();

      const pngUrl = canvas.toDataURL('image/png');
      const a = document.createElement('a');
      a.href = pngUrl;
      a.download = fileName;
      document.body.appendChild(a);
      a.click();
      a.remove();
    } catch (err) {
      showSweetAlert('error', 'Download Failed', 'Unable to generate PNG for this card.');
    }
  }

  if (downloadFrontBtn) {
    downloadFrontBtn.addEventListener('click', function () {
      downloadCardAsPng('idCardFront', (cardNo.value || 'student-id') + '-front.png');
    });
  }

  if (downloadBackBtn) {
    downloadBackBtn.addEventListener('click', function () {
      downloadCardAsPng('idCardBack', (cardNo.value || 'student-id') + '-back.png');
    });
  }

  if (startCameraBtn) {
    startCameraBtn.addEventListener('click', async function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showSweetAlert('warning', 'Webcam Not Supported', 'Webcam is not supported in this browser or this page is not running in a secure context.');
        return;
      }

      try {
        stopCamera();

        mediaStream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: 'user',
            width: { ideal: 1280 },
            height: { ideal: 720 }
          },
          audio: false
        });
        video.srcObject = mediaStream;
        videoWrap.style.display = 'block';
      } catch (err) {
        let message = 'Unable to access webcam.';

        if (err && err.name === 'NotAllowedError') {
          message = 'Camera permission was denied. Allow camera access in the browser and try again.';
        } else if (err && err.name === 'NotFoundError') {
          message = 'No webcam was found on this device.';
        } else if (err && err.name === 'NotReadableError') {
          message = 'Webcam is already in use by another application.';
        } else if (err && err.name === 'SecurityError') {
          message = 'Camera access is blocked due to browser security settings.';
        } else if (err && err.name === 'OverconstrainedError') {
          message = 'Requested webcam settings are not supported on this device.';
        }

        showSweetAlert('error', 'Camera Error', message);
      }
    });
  }

    if (capturePhotoBtn) {
      capturePhotoBtn.addEventListener('click', function () {
        if (!video.srcObject) {
          showSweetAlert('info', 'Camera Required', 'Start the camera first.');
          return;
        }

        const width = video.videoWidth || 320;
        const height = video.videoHeight || 320;

        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, width, height);

        const dataUrl = canvas.toDataURL('image/png');
        previewPhoto.src = dataUrl;
        previewPhoto.style.display = 'block';
        if (previewPlaceholder) previewPlaceholder.style.display = 'none';
      });
    }

    function stopCamera() {
      if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
      }
      if (video) video.srcObject = null;
      if (videoWrap) videoWrap.style.display = 'none';
    }

    if (stopCameraBtn) {
      stopCameraBtn.addEventListener('click', stopCamera);
    }

    syncPreview();

    window.__stopIdCardCamera = stopCamera;
  }
</script>

