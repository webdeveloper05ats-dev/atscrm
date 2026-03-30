<?php
// =====================================
// ATS CRM - Delete Single Payment
// Slug: payments/delete
// File: views/payments/delete.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// Must be POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responseJson('error', 'Method not allowed.');
}

// CSRF verification
$token = $_POST['csrf_token'] ?? '';
if (!verifyCSRF($token)) {
    responseJson('error', 'Invalid request (CSRF). Please refresh and try again.');
}

// Permission check
if (!canDelete('payments/index') && !isSuperAdmin()) {
    http_response_code(403);
    responseJson('error', 'Access denied. You do not have permission to delete payments.');
}

$paymentId = (int)($_POST['payment_id'] ?? 0);

if ($paymentId <= 0) {
    responseJson('error', 'Invalid payment selected.');
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// Check branch access
$canAllBranches = 0;
try {
    $roleStmt = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $roleStmt->execute([$roleId]);
    $canAllBranches = (int)($roleStmt->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

try {
    // Fetch the payment with branch scope
    if ($canAllBranches !== 1 && $branchId > 0) {
        $st = $pdo->prepare("
            SELECT rp.id, rp.registration_id, rp.branch_id
            FROM registration_payments rp
            WHERE rp.id = ? AND rp.branch_id = ?
            LIMIT 1
        ");
        $st->execute([$paymentId, $branchId]);
    } else {
        $st = $pdo->prepare("
            SELECT rp.id, rp.registration_id, rp.branch_id
            FROM registration_payments rp
            WHERE rp.id = ?
            LIMIT 1
        ");
        $st->execute([$paymentId]);
    }

    $payment = $st->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        responseJson('error', 'Payment not found or access denied.');
    }

    $regId = (int)$payment['registration_id'];

    // Delete the single payment
    $pdo->beginTransaction();

    $del = $pdo->prepare("DELETE FROM registration_payments WHERE id = ?");
    $del->execute([$paymentId]);

    // Recalculate registration payment summary
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

    $pdo->commit();

    responseJson('success', 'Payment deleted and totals recalculated.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responseJson('error', 'Delete failed. ' . $e->getMessage());
}