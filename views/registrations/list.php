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
$error   = "";

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('regNull')) {
    function regNull($v){
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }
}
if (!function_exists('regDecVal')) {
    function regDecVal($v){
        $v = trim((string)$v);
        return $v === '' ? 0 : (float)$v;
    }
}
if (!function_exists('makeReceiptNo')) {
    function makeReceiptNo(PDO $pdo): string {
        $prefix = 'RCPT-' . date('Ym') . '-';
        $st = $pdo->prepare("SELECT COUNT(*) FROM registration_payments WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
        $st->execute();
        $count = (int)$st->fetchColumn();
        return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
if (!function_exists('recalcRegistrationPaymentsSummary')) {
    function recalcRegistrationPaymentsSummary(PDO $pdo, int $regId): void {
        $st = $pdo->prepare("SELECT final_fee FROM registrations WHERE id=? LIMIT 1");
        $st->execute([$regId]);
        $finalFee = (float)($st->fetchColumn() ?? 0);

        $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM registration_payments WHERE registration_id=? AND approval_status='approved'");
        $st->execute([$regId]);
        $paidSum = (float)$st->fetchColumn();

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

/* =========================================================
   Session / Scope
========================================================= */
$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
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
   AJAX Modal Actions
========================================================= */
$isAjax = isset($_GET['ajax']) && (int)$_GET['ajax'] === 1;

if ($isAjax) {
    $action = trim((string)($_GET['action'] ?? ''));

    // ---------------------------------
    // 1) View History
    // ---------------------------------
    if ($action === 'view_history') {
        $regId = (int)($_GET['reg_id'] ?? 0);

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
                $st->execute([(int)$reg['enquiry_id']]);
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

            <div class="pro-modal-wrap">
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
                            <span class="value"><?= h($reg['enquiry_snapshot_phone'] ?: $reg['enquiry_phone'] ?: '-') ?></span>
                        </div>
                        <div class="pro-info-card">
                            <span class="label">Email</span>
                            <span class="value"><?= h($reg['enquiry_snapshot_email'] ?: $reg['enquiry_email'] ?: '-') ?></span>
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
                            <span class="value">₹ <?= h(number_format((float)($reg['final_fee'] ?? 0), 2)) ?></span>
                        </div>
                        <div class="pro-info-card">
                            <span class="label">Balance</span>
                            <span class="value">₹ <?= h(number_format((float)($reg['balance_amount'] ?? 0), 2)) ?></span>
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
                            <span class="value"><?= h($profile['parent_phone'] ?? '-') ?></span>
                        </div>
                        <div class="pro-info-card">
                            <span class="label">Emergency Contact</span>
                            <span class="value"><?= h($profile['emergency_contact'] ?? '-') ?></span>
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
                                            <?= h($f['followup_date']) ?> <?= h($f['followup_time'] ?? '') ?>
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
                                        <td>₹ <?= h(number_format((float)$p['amount'], 2)) ?></td>
                                        <td><?= h($p['payment_mode']) ?></td>
                                        <td><?= h($p['payment_type']) ?></td>
                                        <td><?= h($p['approval_status']) ?></td>
                                        <td>
                                            <a href="index.php?page=payments/receipt&payment_id=<?= (int)$p['id'] ?>" target="_blank" class="receipt-link">
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
        $regId = (int)($_GET['reg_id'] ?? 0);

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

            $finalFee = (float)($reg['final_fee'] ?? 0);
            $paidAmt  = (float)($reg['paid_amount'] ?? 0);
            $balance  = max(0, $finalFee - $paidAmt);
            ?>

            <div class="pro-modal-wrap">
                <div class="pro-payment-summary">
                    <div class="pro-pay-card">
                        <span class="label">Registration No</span>
                        <span class="value"><?= h($reg['registration_no'] ?: ('REG-' . $reg['id'])) ?></span>
                    </div>
                    <div class="pro-pay-card">
                        <span class="label">Student</span>
                        <span class="value"><?= h($reg['enquiry_snapshot_name'] ?: '-') ?></span>
                    </div>
                    <div class="pro-pay-card">
                        <span class="label">Program</span>
                        <span class="value"><?= h($reg['program_name'] ?: '-') ?></span>
                    </div>
                    <div class="pro-pay-card">
                        <span class="label">Owner</span>
                        <span class="value"><?= h($reg['owner_name'] ?: '-') ?></span>
                    </div>
                    <div class="pro-pay-card">
                        <span class="label">Final Fee</span>
                        <span class="value">₹ <?= h(number_format($finalFee, 2)) ?></span>
                    </div>
                    <div class="pro-pay-card">
                        <span class="label">Paid</span>
                        <span class="value">₹ <?= h(number_format($paidAmt, 2)) ?></span>
                    </div>
                    <div class="pro-pay-card highlight">
                        <span class="label">Balance</span>
                        <span class="value">₹ <?= h(number_format($balance, 2)) ?></span>
                    </div>
                </div>

                <div class="pro-modal-section">
                    <div class="pro-modal-head">
                        <div class="pro-modal-head-icon"><i class="fas fa-plus-circle"></i></div>
                        <div>
                            <div class="pro-modal-title">Add Payment</div>
                            <div class="pro-modal-subtitle">Enter payment details carefully</div>
                        </div>
                    </div>

                    <form method="POST" id="paymentEntryForm">
                        <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                        <input type="hidden" name="reg_id" value="<?= (int)$reg['id'] ?>">

                        <div class="pro-payment-grid">
                            <div>
                                <label class="m-label">Amount</label>
                                <input type="number" step="0.01" max="<?= h($balance) ?>" name="amount" class="m-input" required>
                            </div>
                            <div>
                                <label class="m-label">Payment Date</label>
                                <input type="date" name="payment_date" class="m-input" value="<?= h(date('Y-m-d')) ?>" required>
                            </div>
                            <div>
                                <label class="m-label">Payment Mode</label>
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
                                <label class="m-label">Payment Type</label>
                                <select name="payment_type" class="m-input">
                                    <option value="advance">Advance</option>
                                    <option value="partial" selected>Partial</option>
                                    <option value="full">Full</option>
                                    <option value="refund">Refund</option>
                                </select>
                            </div>
                            <div>
                                <label class="m-label">Reference No</label>
                                <input type="text" name="reference_no" class="m-input">
                            </div>
                            <div>
                                <label class="m-label">Approval Status</label>
                                <select name="approval_status" class="m-input">
                                    <option value="approved" selected>Approved</option>
                                    <option value="pending">Pending</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="full">
                                <label class="m-label">Remarks</label>
                                <textarea name="remarks_payment" class="m-input" rows="3"></textarea>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button type="submit" name="add_payment" class="pro-btn pro-btn-primary">
                                <i class="fas fa-save"></i> Save Payment
                            </button>
                        </div>
                    </form>
                </div>

                <div class="pro-modal-section">
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
                                        <td>₹ <?= h(number_format((float)$p['amount'], 2)) ?></td>
                                        <td><?= h($p['payment_mode']) ?></td>
                                        <td><?= h($p['payment_type']) ?></td>
                                        <td><?= h($p['approval_status']) ?></td>
                                        <td>
                                            <a href="index.php?page=payments/receipt&payment_id=<?= (int)$p['id'] ?>" target="_blank" class="receipt-link">
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
        $regId = (int)($_POST['reg_id'] ?? 0);

        if ($regId <= 0) {
            $error = "Invalid registration selected.";
        } else {
            try {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("
                        SELECT id
                        FROM registrations
                        WHERE id=? AND branch_id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
                    $st->execute([$regId, $branchId]);
                } else {
                    $st = $pdo->prepare("
                        SELECT id
                        FROM registrations
                        WHERE id=? AND registration_status IN ('active','completed')
                        LIMIT 1
                    ");
                    $st->execute([$regId]);
                }

                if (!(int)$st->fetchColumn()) {
                    throw new Exception("Registration not found or access denied.");
                }

                $del = $pdo->prepare("DELETE FROM registrations WHERE id=?");
                $del->execute([$regId]);

                $success = "Registration deleted successfully!";
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
        $regId           = (int)($_POST['reg_id'] ?? 0);
        $amount          = regDecVal($_POST['amount'] ?? 0);
        $payment_date    = regNull($_POST['payment_date'] ?? '');
        $payment_mode    = regNull($_POST['payment_mode'] ?? 'cash') ?: 'cash';
        $payment_type    = regNull($_POST['payment_type'] ?? 'partial') ?: 'partial';
        $reference_no    = regNull($_POST['reference_no'] ?? '');
        $approval_status = regNull($_POST['approval_status'] ?? 'approved') ?: 'approved';
        $remarksPay      = regNull($_POST['remarks_payment'] ?? '');

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

                $finalFee = (float)($reg['final_fee'] ?? 0);
                $paidAmt  = (float)($reg['paid_amount'] ?? 0);
                $balance  = max(0, $finalFee - $paidAmt);

                if ($payment_type !== 'refund' && $amount > $balance && $balance > 0) {
                    throw new Exception("Amount cannot be greater than balance.");
                }

                $staffId = (int)($reg['assigned_to'] ?? 0);
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
                    (int)$reg['branch_id'],
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
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Failed to add payment. " . $e->getMessage();
            }
        }
    }
}

/* =========================================================
   Filters
========================================================= */
$q       = trim((string)($_GET['q'] ?? ''));
$regType = trim((string)($_GET['reg_type'] ?? ''));
$status  = trim((string)($_GET['status'] ?? ''));
$payStat = trim((string)($_GET['payment_status'] ?? ''));
$from    = trim((string)($_GET['from'] ?? ''));
$to      = trim((string)($_GET['to'] ?? ''));

$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;

$perPage = 10;
$offset  = ($page - 1) * $perPage;

$where  = ["r.registration_status IN ('active','completed')"];
$params = [];

if ($canAllBranches !== 1 && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($regType !== '' && in_array($regType, ['course','internship','workshop'], true)) {
    $where[] = "r.reg_type = ?";
    $params[] = $regType;
}

if ($status !== '' && in_array($status, ['active','completed'], true)) {
    $where[] = "r.registration_status = ?";
    $params[] = $status;
}

if ($payStat !== '' && in_array($payStat, ['unpaid','partial','paid'], true)) {
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
    $totalRows = (int)$cnt->fetchColumn();
} catch (Exception $e) {
    $totalRows = 0;
}

$totalPages = (int)ceil($totalRows / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;

/* =========================================================
   Summary
========================================================= */
$summary = ['active'=>0,'completed'=>0,'paid'=>0,'partial'=>0,'unpaid'=>0];

try {
    $sumWhere = ["r.registration_status IN ('active','completed')"];
    $sumParams = [];

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
        $summary['active']    = (int)($x['active_count'] ?? 0);
        $summary['completed'] = (int)($x['completed_count'] ?? 0);
        $summary['paid']      = (int)($x['paid_count'] ?? 0);
        $summary['partial']   = (int)($x['partial_count'] ?? 0);
        $summary['unpaid']    = (int)($x['unpaid_count'] ?? 0);
    }
} catch (Exception $e) {}

/* =========================================================
   Fetch Rows
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
        LIMIT $perPage OFFSET $offset
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
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

function regTypeBadgeList($type){
    $map = [
        'course'     => ['#e91e63', '#ffe9f0', 'Course'],
        'internship' => ['#0288d1', '#e8f4fd', 'Internship'],
        'workshop'   => ['#ff9800', '#fff4e5', 'Workshop'],
    ];
    $c = $map[$type][0] ?? '#607d8b';
    $bg= $map[$type][1] ?? '#eef2f5';
    $t = $map[$type][2] ?? ucfirst((string)$type);
    return '<span class="badge-pill-custom" style="color:'.$c.';background:'.$bg.';border-color:'.$bg.';">'.$t.'</span>';
}
function regStatusBadge($status){
    $map = [
        'active'    => ['#2e7d32', '#e8f5e9', 'Active'],
        'completed' => ['#6a1b9a', '#f3e5f5', 'Completed'],
    ];
    $c = $map[$status][0] ?? '#607d8b';
    $bg= $map[$status][1] ?? '#eef2f5';
    $t = $map[$status][2] ?? ucfirst((string)$status);
    return '<span class="badge-pill-custom" style="color:'.$c.';background:'.$bg.';border-color:'.$bg.';">'.$t.'</span>';
}
function payStatusBadgeList($type){
    $map = [
        'unpaid'  => ['#d32f2f', '#ffebee', 'Unpaid'],
        'partial' => ['#ff9800', '#fff4e5', 'Partial'],
        'paid'    => ['#2e7d32', '#e8f5e9', 'Paid'],
    ];
    $c = $map[$type][0] ?? '#607d8b';
    $bg= $map[$type][1] ?? '#eef2f5';
    $t = $map[$type][2] ?? ucfirst((string)$type);
    return '<span class="badge-pill-custom" style="color:'.$c.';background:'.$bg.';border-color:'.$bg.';">'.$t.'</span>';
}
?>

<style>
:root{
  --reg-primary:#e91e63;
  --reg-primary-dark:#c2185b;
  --reg-primary-soft:#fff4f8;
  --reg-text:#1f2937;
  --reg-muted:#6b7280;
  --reg-border:#e8edf3;
  --reg-card:#ffffff;
  --reg-bg:#f6f8fc;
  --reg-shadow:0 16px 40px rgba(15,23,42,.06);
  --reg-shadow-soft:0 10px 25px rgba(15,23,42,.04);
}

.reg-page{
  background:linear-gradient(180deg,#fff 0%,#fff7fb 18%,#f7f9fd 100%);
  border-radius:24px;
  padding:18px;
}

.reg-page-top{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:14px;
  flex-wrap:wrap;
  margin-bottom:18px;
}

.reg-page-title h2{
  margin:0;
  font-size:28px;
  font-weight:900;
  color:var(--reg-text);
}

.reg-page-title p{
  margin:6px 0 0;
  color:var(--reg-muted);
  font-size:14px;
}

.reg-page-chips{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}

.reg-chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:#fff;
  color:var(--reg-primary-dark);
  border:1px solid rgba(233,30,99,.12);
  border-radius:999px;
  padding:10px 14px;
  font-size:13px;
  font-weight:800;
  box-shadow:var(--reg-shadow-soft);
}

.summary-box{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap:14px;
  margin-bottom:18px;
}

.summary-card{
  position:relative;
  overflow:hidden;
  background:var(--reg-card);
  border:1px solid rgba(15,23,42,.06);
  border-radius:18px;
  padding:16px;
  box-shadow:var(--reg-shadow);
}

.summary-card:before{
  content:"";
  position:absolute;
  top:0;
  left:0;
  width:100%;
  height:4px;
  background:linear-gradient(90deg,var(--reg-primary),#ff6ba6);
}

.summary-title{
  font-size:12px;
  color:var(--reg-muted);
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.5px;
}

.summary-value{
  margin-top:8px;
  font-size:26px;
  font-weight:900;
  color:var(--reg-text);
}

.summary-icon{
  position:absolute;
  right:14px;
  bottom:12px;
  font-size:26px;
  color:rgba(233,30,99,.10);
}

.pro-card{
  background:var(--reg-card);
  border:1px solid rgba(15,23,42,.06);
  border-radius:22px;
  box-shadow:var(--reg-shadow);
  overflow:hidden;
}

.pro-card + .pro-card{
  margin-top:16px;
}

.pro-card-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  padding:18px 20px;
  border-bottom:1px solid #eef2f6;
  background:linear-gradient(180deg,#fff 0%,#fffafe 100%);
}

.pro-card-title{
  margin:0;
  font-size:16px;
  font-weight:900;
  color:var(--reg-text);
  display:flex;
  align-items:center;
  gap:10px;
}

.pro-card-title i{
  width:34px;
  height:34px;
  border-radius:12px;
  background:var(--reg-primary-soft);
  color:var(--reg-primary);
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:14px;
}

.pro-card-body{
  padding:18px;
}

/* compact single line filter toolbar */
.reg-toolbar-form{
  display:flex;
  align-items:flex-end;
  gap:12px;
  flex-wrap:wrap;
}

.reg-toolbar-item{
  min-width:140px;
  flex:0 0 auto;
}

.reg-toolbar-item.search{
  min-width:260px;
  flex:1 1 280px;
}

.reg-toolbar-item.date{
  min-width:150px;
}

.reg-toolbar-item.actions{
  display:flex;
  gap:10px;
  align-items:flex-end;
  margin-left:auto;
}

.reg-label{
  display:block;
  font-weight:800;
  font-size:12px;
  margin-bottom:6px;
  color:var(--reg-text);
}

.reg-input, .reg-select{
  width:100%;
  min-height:44px;
  padding:10px 12px;
  border:1px solid #dfe5ec;
  border-radius:14px;
  outline:none;
  background:#fff;
  transition:.2s ease;
  color:var(--reg-text);
  font-size:14px;
}

.reg-input:focus, .reg-select:focus,
.m-input:focus{
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.12);
}

.pro-btn{
  border:none;
  border-radius:14px;
  padding:12px 18px;
  font-size:14px;
  font-weight:800;
  line-height:1;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  text-decoration:none !important;
  cursor:pointer;
  transition:.2s ease;
  min-height:44px;
  white-space:nowrap;
}

.pro-btn:hover{
  transform:translateY(-1px);
}

.pro-btn-primary{
  background:linear-gradient(135deg,var(--reg-primary),var(--reg-primary-dark));
  color:#fff;
  box-shadow:0 14px 26px rgba(233,30,99,.22);
}

.pro-btn-light{
  background:#fff;
  color:var(--reg-text);
  border:1px solid #dfe5ec;
}

.reg-table-wrap{
  background:#fff;
  border:1px solid rgba(15,23,42,.06);
  border-radius:18px;
  box-shadow:var(--reg-shadow-soft);
  overflow:auto;
}

.reg-table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  min-width:1100px;
}

.reg-table th{
  background:#fcf6f9;
  padding:16px 14px;
  font-size:12px;
  font-weight:900;
  text-align:left;
  border-bottom:1px solid #eee;
  white-space:nowrap;
  color:#8a003f;
  text-transform:uppercase;
  letter-spacing:.5px;
}

.reg-table td{
  padding:16px 14px;
  border-bottom:1px solid #f2f4f8;
  vertical-align:top;
}

.reg-table tbody tr:hover{
  background:#fcfdff;
}

.primary-text{
  font-weight:800;
  color:var(--reg-text);
  line-height:1.5;
}

.secondary-text{
  font-size:12px;
  color:var(--reg-muted);
  margin-top:6px;
  line-height:1.5;
}

.badge-pill-custom{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:6px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:800;
  border:1px solid rgba(0,0,0,.06);
  margin-right:6px;
  margin-bottom:6px;
}

.action-group{
  display:flex;
  justify-content:center;
  gap:8px;
  flex-wrap:wrap;
}

.icon-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  width:40px;
  height:40px;
  border-radius:12px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff;
  cursor:pointer;
  text-decoration:none;
  transition:.18s ease;
  font-size:14px;
}

.icon-btn:hover{
  transform:translateY(-1px);
  box-shadow:0 10px 20px rgba(0,0,0,.08);
}

.btn-view{
  background:rgba(233,30,99,.10);
  color:#b0004f;
  border-color:rgba(233,30,99,.18);
}
.btn-view:hover{
  background:#b0004f;
  color:#fff;
}

.btn-edit{
  background:#f3edff;
  color:#5b2dab;
  border-color:#ded3f6;
}
.btn-edit:hover{
  background:#5b2dab;
  color:#fff;
}

.btn-payment{
  background:#fff4dc;
  color:#8a5a00;
  border-color:#ffe2a8;
}
.btn-payment:hover{
  background:#8a5a00;
  color:#fff;
}

.btn-delete{
  background:#fdf2f2;
  color:#b91c1c;
  border-color:#fecaca;
}
.btn-delete:hover{
  background:#dc2626;
  color:#fff;
  border-color:#dc2626;
}

.reg-footer{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
  margin-top:16px;
}

.page-btn{
  min-height:42px;
  padding:10px 14px;
  border-radius:12px;
  border:1px solid #e5e7eb;
  background:#fff;
  text-decoration:none;
  color:#333;
  font-weight:700;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}

.page-btn:hover{
  box-shadow:0 8px 20px rgba(0,0,0,.06);
  color:#111;
}

.crm-modal-backdrop{
  position:fixed;
  inset:0;
  background:rgba(15,23,42,.48);
  backdrop-filter:blur(5px);
  display:none;
  align-items:center;
  justify-content:center;
  padding:18px;
  z-index:99999;
}

.crm-modal{
  width:min(1180px, 96vw);
  background:#fff;
  border-radius:24px;
  border:1px solid rgba(0,0,0,.08);
  box-shadow:0 20px 70px rgba(0,0,0,.22);
  overflow:hidden;
}

.crm-modal-header{
  padding:18px 20px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:1px solid #edf1f5;
  background:linear-gradient(180deg,#fff 0%,#fffafe 100%);
}

.crm-modal-title{
  font-weight:900;
  font-size:20px;
  color:var(--reg-text);
  display:flex;
  align-items:center;
  gap:10px;
}

.crm-modal-close{
  width:42px;
  height:42px;
  border-radius:14px;
  border:1px solid rgba(0,0,0,.08);
  background:#fff;
  cursor:pointer;
  font-size:20px;
  transition:.2s ease;
}

.crm-modal-close:hover{
  background:#f8fafc;
}

.crm-modal-body{
  padding:20px;
  max-height:82vh;
  overflow:auto;
  background:#fbfcff;
}

.pro-modal-wrap{
  display:flex;
  flex-direction:column;
  gap:16px;
}

.pro-modal-section{
  border:1px solid #edf1f5;
  border-radius:20px;
  padding:18px;
  background:#fff;
  box-shadow:var(--reg-shadow-soft);
}

.pro-modal-head{
  display:flex;
  align-items:flex-start;
  gap:12px;
  margin-bottom:16px;
}

.pro-modal-head-icon{
  width:42px;
  height:42px;
  border-radius:14px;
  background:var(--reg-primary-soft);
  color:var(--reg-primary);
  display:flex;
  align-items:center;
  justify-content:center;
  flex-shrink:0;
}

.pro-modal-title{
  font-size:17px;
  font-weight:900;
  color:var(--reg-text);
  line-height:1.3;
}

.pro-modal-subtitle{
  font-size:13px;
  color:var(--reg-muted);
  margin-top:4px;
}

.pro-info-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(190px, 1fr));
  gap:12px;
}

.pro-info-card{
  border:1px solid #eef2f6;
  border-radius:16px;
  padding:14px;
  background:linear-gradient(180deg,#fff,#fcfdff);
}

.pro-info-card .label,
.pro-pay-card .label{
  display:block;
  font-size:12px;
  font-weight:800;
  color:var(--reg-muted);
  text-transform:uppercase;
  letter-spacing:.5px;
  margin-bottom:6px;
}

.pro-info-card .value,
.pro-pay-card .value{
  display:block;
  font-size:14px;
  font-weight:800;
  color:var(--reg-text);
  line-height:1.5;
  word-break:break-word;
}

.pro-note-box{
  margin-top:14px;
  padding:14px 16px;
  border-radius:16px;
  background:#fff7fb;
  border:1px solid #f5d6e3;
  color:#4b5563;
  line-height:1.7;
}

.pro-note-title{
  font-size:13px;
  font-weight:900;
  color:#9d174d;
  margin-bottom:8px;
  display:flex;
  align-items:center;
  gap:8px;
}

.pro-timeline{
  display:flex;
  flex-direction:column;
  gap:12px;
}

.pro-timeline-item{
  display:flex;
  gap:12px;
  align-items:flex-start;
}

.pro-timeline-dot{
  width:14px;
  height:14px;
  border-radius:50%;
  background:var(--reg-primary);
  margin-top:8px;
  box-shadow:0 0 0 5px rgba(233,30,99,.12);
  flex-shrink:0;
}

.pro-timeline-content{
  flex:1;
  border:1px solid #eef2f6;
  border-radius:16px;
  padding:14px;
  background:#fff;
}

.pro-timeline-title{
  font-size:14px;
  font-weight:800;
  color:var(--reg-text);
}

.pro-mini-badge{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:4px 10px;
  border-radius:999px;
  background:#fce7f3;
  color:#9d174d;
  font-size:11px;
  font-weight:800;
  margin-left:8px;
}

.pro-timeline-sub{
  font-size:12px;
  color:var(--reg-muted);
  margin-top:6px;
}

.pro-timeline-note{
  margin-top:10px;
  color:#374151;
  line-height:1.7;
}

.pro-table-scroll{
  overflow:auto;
}

.pro-mini-table{
  width:100%;
  border-collapse:collapse;
  min-width:760px;
}

.pro-mini-table th,
.pro-mini-table td{
  padding:12px 12px;
  border-bottom:1px solid #edf1f5;
  text-align:left;
  font-size:13px;
}

.pro-mini-table th{
  background:#fafbfe;
  font-weight:900;
  color:#374151;
  text-transform:uppercase;
  letter-spacing:.4px;
  font-size:12px;
}

.receipt-link{
  display:inline-flex;
  align-items:center;
  gap:7px;
  padding:8px 12px;
  border-radius:10px;
  border:1px solid #ddd;
  text-decoration:none;
  color:#333;
  background:#fff;
  font-weight:800;
  transition:.2s ease;
}

.receipt-link:hover{
  background:#f7f7f7;
}

.pro-payment-summary{
  display:grid;
  grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
  gap:12px;
}

.pro-pay-card{
  border:1px solid #f1d9e4;
  background:#fff;
  border-radius:16px;
  padding:14px;
}

.pro-pay-card.highlight{
  background:linear-gradient(135deg,#fff7fb,#fff);
  border-color:#f5cfe0;
}

.pro-payment-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:12px;
}

.pro-payment-grid .full{
  grid-column:1 / -1;
}

.m-label{
  display:block;
  font-size:13px;
  font-weight:800;
  margin-bottom:6px;
  color:var(--reg-text);
}

.m-input{
  width:100%;
  min-height:46px;
  padding:10px 12px;
  border:1px solid #dfe5ec;
  border-radius:12px;
  background:#fff;
  outline:none;
  transition:.2s ease;
}

.modal-actions{
  display:flex;
  justify-content:flex-end;
  margin-top:16px;
}

.empty-note{
  color:var(--reg-muted);
  padding:12px 0;
}

.error-note{
  color:#d32f2f;
}

@media (max-width: 1200px){
  .reg-toolbar-item.actions{
    margin-left:0;
  }
}

@media (max-width: 900px){
  .pro-payment-grid{
    grid-template-columns:1fr;
  }

  .reg-page{
    padding:12px;
    border-radius:18px;
  }

  .pro-card-body,
  .crm-modal-body{
    padding:14px;
  }

  .pro-card-head,
  .crm-modal-header{
    padding:16px;
  }

  .reg-toolbar-form{
    gap:10px;
  }

  .reg-toolbar-item{
    min-width:calc(50% - 8px);
    flex:1 1 calc(50% - 8px);
  }

  .reg-toolbar-item.search,
  .reg-toolbar-item.actions{
    min-width:100%;
    flex:1 1 100%;
  }
}

@media (max-width: 576px){
  .reg-page-title h2{
    font-size:22px;
  }

  .reg-toolbar-item{
    min-width:100%;
    flex:1 1 100%;
  }

  .reg-toolbar-item.actions{
    flex-direction:column;
    align-items:stretch;
  }

  .pro-btn{
    width:100%;
  }
}
</style>

<h2 style="display:none;">Registrations List</h2>

<?php if ($success): ?>
<script>
Swal.fire({
  icon:'success',
  title:'Success',
  text:'<?= addslashes($success) ?>',
  confirmButtonColor:'#e91e63'
}).then(()=> window.location.href = "<?= $baseUrl ?>");
</script>
<?php endif; ?>

<?php if ($error): ?>
<script>
Swal.fire({
  icon:'error',
  title:'Error',
  text:'<?= addslashes($error) ?>',
  confirmButtonColor:'#e91e63'
});
</script>
<?php endif; ?>

<div class="reg-page">
  <div class="reg-page-top">
    <div class="reg-page-title">
      <h2>Confirmed Registrations</h2>
      <p>Manage active and completed registrations, payments, and full student history from one clean screen.</p>
    </div>

    
  </div>

  <div class="summary-box">
    <div class="summary-card">
      <div class="summary-title">Total Active</div>
      <div class="summary-value"><?= (int)$summary['active'] ?></div>
      <div class="summary-icon"><i class="fas fa-user-check"></i></div>
    </div>
    <div class="summary-card">
      <div class="summary-title">Total Completed</div>
      <div class="summary-value"><?= (int)$summary['completed'] ?></div>
      <div class="summary-icon"><i class="fas fa-flag-checkered"></i></div>
    </div>
    <div class="summary-card">
      <div class="summary-title">Paid</div>
      <div class="summary-value"><?= (int)$summary['paid'] ?></div>
      <div class="summary-icon"><i class="fas fa-check-circle"></i></div>
    </div>
    <div class="summary-card">
      <div class="summary-title">Partial</div>
      <div class="summary-value"><?= (int)$summary['partial'] ?></div>
      <div class="summary-icon"><i class="fas fa-adjust"></i></div>
    </div>
    <div class="summary-card">
      <div class="summary-title">Unpaid</div>
      <div class="summary-value"><?= (int)$summary['unpaid'] ?></div>
      <div class="summary-icon"><i class="fas fa-exclamation-circle"></i></div>
    </div>
  </div>

  <div class="pro-card">
    <div class="pro-card-head">
      <div class="pro-card-title">
        <i class="fas fa-filter"></i>
        Compact Filters
      </div>
    </div>

    <div class="pro-card-body">
      <form method="GET" action="index.php" class="reg-toolbar-form">
        <input type="hidden" name="page" value="registrations/list">

        <div class="reg-toolbar-item search">
          <label class="reg-label">Search</label>
          <input class="reg-input" type="text" name="q" value="<?= h($q) ?>" placeholder="Student / Reg No / Phone / Program / Owner">
        </div>

        <div class="reg-toolbar-item">
          <label class="reg-label">Type</label>
          <select class="reg-select" name="reg_type">
            <option value="">All</option>
            <option value="course" <?= $regType==='course'?'selected':''; ?>>Course</option>
            <option value="internship" <?= $regType==='internship'?'selected':''; ?>>Internship</option>
            <option value="workshop" <?= $regType==='workshop'?'selected':''; ?>>Workshop</option>
          </select>
        </div>

        <div class="reg-toolbar-item">
          <label class="reg-label">Reg Status</label>
          <select class="reg-select" name="status">
            <option value="">All</option>
            <option value="active" <?= $status==='active'?'selected':''; ?>>Active</option>
            <option value="completed" <?= $status==='completed'?'selected':''; ?>>Completed</option>
          </select>
        </div>

        <div class="reg-toolbar-item">
          <label class="reg-label">Payment</label>
          <select class="reg-select" name="payment_status">
            <option value="">All</option>
            <option value="unpaid" <?= $payStat==='unpaid'?'selected':''; ?>>Unpaid</option>
            <option value="partial" <?= $payStat==='partial'?'selected':''; ?>>Partial</option>
            <option value="paid" <?= $payStat==='paid'?'selected':''; ?>>Paid</option>
          </select>
        </div>

        <div class="reg-toolbar-item date">
          <label class="reg-label">From</label>
          <input class="reg-input" type="date" name="from" value="<?= h($from) ?>">
        </div>

        <div class="reg-toolbar-item date">
          <label class="reg-label">To</label>
          <input class="reg-input" type="date" name="to" value="<?= h($to) ?>">
        </div>

        <div class="reg-toolbar-item actions">
          <button type="submit" class="pro-btn pro-btn-primary">
            <i class="fas fa-search"></i> Apply
          </button>
          <a href="index.php?page=registrations/list" class="pro-btn pro-btn-light">
            <i class="fas fa-undo"></i> Reset
          </a>
        </div>
      </form>
    </div>
  </div>

  <div class="pro-card" style="margin-top:16px;">
    <div class="pro-card-head">
      <div class="pro-card-title">
        <i class="fas fa-list-ul"></i>
        Confirmed Registrations (<?= (int)$totalRows ?>)
      </div>
    </div>

    <div class="pro-card-body">
      <div class="reg-table-wrap">
        <table class="reg-table">
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
              <td colspan="6" style="text-align:center;padding:34px;color:#888;">
                <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:10px;color:#d1d5db;"></i>
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
                <div class="secondary-text"><i class="fas fa-phone-alt"></i> <?= h($r['enquiry_snapshot_phone'] ?: $r['enquiry_phone'] ?: '-') ?></div>
              </td>

              <td>
                <div class="primary-text"><?= h($r['program_name'] ?: '-') ?></div>
                <div class="secondary-text"><?= h($r['batch_name'] ?: '-') ?></div>
                <div class="secondary-text"><?= regStatusBadge($r['registration_status'] ?? '') ?> <?= payStatusBadgeList($r['payment_status'] ?? '') ?></div>
              </td>

              <td>
                <div class="primary-text">₹ <?= h(number_format((float)($r['final_fee'] ?? 0), 2)) ?></div>
                <div class="secondary-text">Paid: ₹ <?= h(number_format((float)($r['paid_amount'] ?? 0), 2)) ?></div>
                <div class="secondary-text">Bal: ₹ <?= h(number_format((float)($r['balance_amount'] ?? 0), 2)) ?></div>
              </td>

              <td>
                <div class="primary-text"><?= h($r['owner_name'] ?: '-') ?></div>
                <div class="secondary-text">Front Office Credit</div>
              </td>

              <td style="text-align:center;">
                <div class="action-group">
                  <button type="button" class="icon-btn btn-view" onclick="openHistoryModal(<?= (int)$r['id'] ?>)" title="View History">
                    <i class="fas fa-eye"></i>
                  </button>

                  <a class="icon-btn btn-edit"
                     href="index.php?page=registrations/convert & reg_id=<?= (int)$r['id'] ?>"
                     title="Edit Registration">
                    <i class="fas fa-pen"></i>
                  </a>

                  <button type="button" class="icon-btn btn-payment" onclick="openPaymentModal(<?= (int)$r['id'] ?>)" title="Add Payment">
                    <i class="fas fa-wallet"></i>
                  </button>

                  <form method="POST" class="deleteRegistrationForm" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                    <input type="hidden" name="reg_id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" name="delete_registration" class="icon-btn btn-delete" title="Delete Registration">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="reg-footer">
        <div style="font-size:13px;color:#777;font-weight:700;">
          Page <?= (int)$page ?> of <?= (int)$totalPages ?>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php $prev = max(1, $page - 1); $next = min($totalPages, $page + 1); ?>
          <a class="page-btn" href="<?= $baseUrl ?>&p=1"><i class="fas fa-angle-double-left"></i>&nbsp; First</a>
          <a class="page-btn" style="<?= $page <= 1 ? 'pointer-events:none;opacity:.5;' : '' ?>" href="<?= $baseUrl ?>&p=<?= (int)$prev ?>"><i class="fas fa-angle-left"></i>&nbsp; Prev</a>
          <a class="page-btn" style="<?= $page >= $totalPages ? 'pointer-events:none;opacity:.5;' : '' ?>" href="<?= $baseUrl ?>&p=<?= (int)$next ?>">Next &nbsp;<i class="fas fa-angle-right"></i></a>
          <a class="page-btn" href="<?= $baseUrl ?>&p=<?= (int)$totalPages ?>">Last &nbsp;<i class="fas fa-angle-double-right"></i></a>
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
function openCrmModal(title){
  document.getElementById('crmModalTitle').innerHTML = '<i class="fas fa-layer-group"></i> ' + (title || 'Details');
  document.getElementById('crmModalBody').innerHTML = '<div class="empty-note">Loading...</div>';

  const wrapper = document.querySelector('.wrapper');
  if (wrapper) wrapper.removeAttribute('aria-hidden');

  document.getElementById('crmModalBackdrop').style.display = 'flex';
}

function closeCrmModal(){
  document.getElementById('crmModalBackdrop').style.display = 'none';
  document.getElementById('crmModalBody').innerHTML = '';

  const wrapper = document.querySelector('.wrapper');
  if (wrapper) wrapper.removeAttribute('aria-hidden');
}

document.getElementById('crmModalBackdrop').addEventListener('click', function(e){
  if(e.target === this) closeCrmModal();
});

async function loadModalHtml(url){
  const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  return await res.text();
}

async function openHistoryModal(regId){
  openCrmModal('Student History');
  const url = `index.php?page=registrations/list&ajax=1&action=view_history&reg_id=${regId}`;
  const html = await loadModalHtml(url);
  document.getElementById('crmModalBody').innerHTML = html;
}

async function openPaymentModal(regId){
  openCrmModal('Payment Entry');
  const url = `index.php?page=registrations/list&ajax=1&action=payment_modal&reg_id=${regId}`;
  const html = await loadModalHtml(url);
  document.getElementById('crmModalBody').innerHTML = html;

  const wrapper = document.querySelector('.wrapper');
  if (wrapper) wrapper.removeAttribute('aria-hidden');
}

document.addEventListener('submit', function(e){
  if (e.target && e.target.id === 'paymentEntryForm') {
    const wrapper = document.querySelector('.wrapper');
    if (wrapper) wrapper.removeAttribute('aria-hidden');
  }
});

document.querySelectorAll('.deleteRegistrationForm').forEach(f => {
  f.addEventListener('submit', function(e){
    e.preventDefault();
    Swal.fire({
      icon:'warning',
      title:'Delete Registration?',
      text:'This will delete the registration and linked profile/payments.',
      showCancelButton:true,
      confirmButtonText:'Yes, Delete',
      cancelButtonText:'Cancel',
      confirmButtonColor:'#e91e63'
    }).then((r)=>{
      if (r.isConfirmed) f.submit();
    });
  });
});
</script>