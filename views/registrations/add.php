<?php
// =====================================
// Registrations - Add Direct / Walk-in
// Slug: registrations/add
// File: views/registrations/add.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

if (!function_exists('h')) {
    function h($v){
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

function regToNull($v){
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

function regDec($v){
    $v = trim((string)$v);
    return $v === '' ? 0 : (float)$v;
}

function regDateOrNull($v){
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

function registrationNoExists(PDO $pdo, string $registrationNo, int $excludeId = 0): bool {
    $st = $pdo->prepare("SELECT id FROM registrations WHERE registration_no = ? AND id <> ? LIMIT 1");
    $st->execute([$registrationNo, $excludeId]);
    return (bool)$st->fetchColumn();
}

function makeRegistrationNo(PDO $pdo, int $excludeId = 0): string {
    $prefix = 'REG-' . date('Ym') . '-';

    $st = $pdo->prepare("
        SELECT registration_no
        FROM registrations
        WHERE registration_no LIKE ?
        ORDER BY id DESC
    ");
    $st->execute([$prefix . '%']);
    $rows = $st->fetchAll(PDO::FETCH_COLUMN);

    $max = 0;
    foreach ($rows as $rowNo) {
        if (preg_match('/^REG-\d{6}-(\d{4})$/', (string)$rowNo, $m)) {
            $num = (int)$m[1];
            if ($num > $max) $max = $num;
        }
    }

    do {
        $max++;
        $newNo = $prefix . str_pad((string)$max, 4, '0', STR_PAD_LEFT);
    } while (registrationNoExists($pdo, $newNo, $excludeId));

    return $newNo;
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
   Front Office Owners
========================================================= */
$frontOfficeUsers = [];
try {
    if ($canAllBranches !== 1 && $branchId > 0) {
        $st = $pdo->prepare("
            SELECT u.id, u.name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.status = 1
              AND r.role_name = 'Front Office'
              AND (u.branch_id = ? OR u.branch_id IS NULL OR u.branch_id = 0)
            ORDER BY u.name ASC
        ");
        $st->execute([$branchId]);
    } else {
        $st = $pdo->prepare("
            SELECT u.id, u.name
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.status = 1
              AND r.role_name = 'Front Office'
            ORDER BY u.name ASC
        ");
        $st->execute();
    }
    $frontOfficeUsers = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $frontOfficeUsers = [];
}

/* =========================================================
   Edit Existing Manual Registration
========================================================= */
$regId = (int)($_GET['reg_id'] ?? 0);
$registration = null;
$profile = null;

try {
    if ($regId > 0) {
        if ($canAllBranches !== 1 && $branchId > 0) {
            $st = $pdo->prepare("
                SELECT *
                FROM registrations
                WHERE id=? AND branch_id=? AND enquiry_id IS NULL
                LIMIT 1
            ");
            $st->execute([$regId, $branchId]);
        } else {
            $st = $pdo->prepare("
                SELECT *
                FROM registrations
                WHERE id=? AND enquiry_id IS NULL
                LIMIT 1
            ");
            $st->execute([$regId]);
        }

        $registration = $st->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            throw new Exception("Manual registration not found or access denied.");
        }

        $st = $pdo->prepare("SELECT * FROM registration_profiles WHERE registration_id=? LIMIT 1");
        $st->execute([$regId]);
        $profile = $st->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

/* =========================================================
   Delete Manual Registration
========================================================= */
if (isset($_POST['delete_registration'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF).";
    } else {
        $regIdPost = (int)($_POST['reg_id'] ?? 0);

        if ($regIdPost <= 0) {
            $error = "Invalid registration selected.";
        } else {
            try {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("
                        SELECT id
                        FROM registrations
                        WHERE id=? AND branch_id=? AND enquiry_id IS NULL
                        LIMIT 1
                    ");
                    $st->execute([$regIdPost, $branchId]);
                } else {
                    $st = $pdo->prepare("
                        SELECT id
                        FROM registrations
                        WHERE id=? AND enquiry_id IS NULL
                        LIMIT 1
                    ");
                    $st->execute([$regIdPost]);
                }

                if (!(int)$st->fetchColumn()) {
                    throw new Exception("Registration not found or access denied.");
                }

                $del = $pdo->prepare("DELETE FROM registrations WHERE id=?");
                $del->execute([$regIdPost]);

                $success = "Registration deleted successfully!";
                ?>
                <script>
                Swal.fire({
                  icon:'success',
                  title:'Success',
                  text:'<?= addslashes($success) ?>',
                  confirmButtonColor:'#e91e63'
                }).then(()=> window.location.href = "index.php?page=registrations/drafts");
                </script>
                <?php
                return;
            } catch (Exception $e) {
                $error = "Delete failed. " . $e->getMessage();
            }
        }
    }
}

/* =========================================================
   Save / Update Manual Registration
========================================================= */
if (isset($_POST['save_registration'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF).";
    } else {
        try {
            $regIdPost = (int)($_POST['reg_id'] ?? 0);

            $existingReg = null;
            if ($regIdPost > 0) {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND branch_id=? AND enquiry_id IS NULL
                        LIMIT 1
                    ");
                    $st->execute([$regIdPost, $branchId]);
                } else {
                    $st = $pdo->prepare("
                        SELECT *
                        FROM registrations
                        WHERE id=? AND enquiry_id IS NULL
                        LIMIT 1
                    ");
                    $st->execute([$regIdPost]);
                }
                $existingReg = $st->fetch(PDO::FETCH_ASSOC);

                if (!$existingReg) {
                    throw new Exception("Manual registration not found for update.");
                }
            }

            $registration_no      = regToNull($_POST['registration_no'] ?? '');
            $joined_on            = regDateOrNull($_POST['joined_on'] ?? '');
            $reg_type             = regToNull($_POST['reg_type'] ?? 'course') ?: 'course';
            $program_name         = regToNull($_POST['program_name'] ?? '');
            $batch_name           = regToNull($_POST['batch_name'] ?? '');
            $registration_status  = regToNull($_POST['registration_status'] ?? 'draft') ?: 'draft';
            $notes                = regToNull($_POST['notes'] ?? '');

            $assigned_to          = (int)($_POST['assigned_to'] ?? 0);

            $student_name         = regToNull($_POST['student_name'] ?? '');
            $phone                = regToNull($_POST['phone'] ?? '');
            $email                = regToNull($_POST['email'] ?? '');
            $gender               = regToNull($_POST['gender'] ?? '');
            $dob                  = regDateOrNull($_POST['dob'] ?? '');
            $address              = regToNull($_POST['address'] ?? '');
            $qualification        = regToNull($_POST['qualification'] ?? '');
            $college_name         = regToNull($_POST['college_name'] ?? '');
            $year_of_passout      = regToNull($_POST['year_of_passout'] ?? '');
            $parent_name          = regToNull($_POST['parent_name'] ?? '');
            $parent_phone         = regToNull($_POST['parent_phone'] ?? '');
            $parent_occupation    = regToNull($_POST['parent_occupation'] ?? '');
            $emergency_contact    = regToNull($_POST['emergency_contact'] ?? '');
            $aadhaar_no           = regToNull($_POST['aadhaar_no'] ?? '');
            $remarks              = regToNull($_POST['remarks'] ?? '');

            $total_fee            = regDec($_POST['total_fee'] ?? 0);
            $discount_amount      = regDec($_POST['discount_amount'] ?? 0);
            $final_fee            = regDec($_POST['final_fee'] ?? 0);

            if (!in_array($reg_type, ['course','internship','workshop'], true)) {
                throw new Exception("Invalid registration type.");
            }

            if (!in_array($registration_status, ['draft','active','completed','cancelled'], true)) {
                $registration_status = 'draft';
            }

            if ($student_name === null) {
                throw new Exception("Student name is required.");
            }

            if ($assigned_to <= 0) {
                throw new Exception("Please select Front Office owner.");
            }

            // Preserve old registration no while editing
            if ($regIdPost > 0) {
                $registration_no = regToNull($existingReg['registration_no'] ?? '') ?: $registration_no;
            }

            if ($registration_no === null) {
                $registration_no = makeRegistrationNo($pdo, $regIdPost);
            }

            // extra unique safety
            if (registrationNoExists($pdo, $registration_no, $regIdPost)) {
                $registration_no = makeRegistrationNo($pdo, $regIdPost);
            }

            if ($final_fee <= 0) {
                $final_fee = max(0, $total_fee - $discount_amount);
            }

            // Preserve payment summary if already any payment added later from list.php
            $paid_amount = (float)($existingReg['paid_amount'] ?? 0);
            if ($paid_amount < 0) $paid_amount = 0;

            $balance_amount = max(0, $final_fee - $paid_amount);

            $payment_status = 'unpaid';
            if ($paid_amount > 0 && $paid_amount < $final_fee) {
                $payment_status = 'partial';
            } elseif ($paid_amount >= $final_fee && $final_fee > 0) {
                $payment_status = 'paid';
                $balance_amount = 0;
            }

            $pdo->beginTransaction();

            if ($regIdPost > 0) {
                $upd = $pdo->prepare("
                    UPDATE registrations
                    SET
                        registration_no=?,
                        branch_id=?,
                        reg_type=?,
                        source_type='direct',
                        assigned_to=?,
                        joined_on=?,
                        enquiry_snapshot_name=?,
                        enquiry_snapshot_phone=?,
                        enquiry_snapshot_email=?,
                        program_name=?,
                        batch_name=?,
                        total_fee=?,
                        discount_amount=?,
                        final_fee=?,
                        paid_amount=?,
                        balance_amount=?,
                        payment_status=?,
                        registration_status=?,
                        notes=?,
                        updated_at=NOW()
                    WHERE id=?
                ");
                $upd->execute([
                    $registration_no,
                    $branchId,
                    $reg_type,
                    $assigned_to,
                    $joined_on,
                    $student_name,
                    $phone,
                    $email,
                    $program_name,
                    $batch_name,
                    $total_fee,
                    $discount_amount,
                    $final_fee,
                    $paid_amount,
                    $balance_amount,
                    $payment_status,
                    $registration_status,
                    $notes,
                    $regIdPost
                ]);

                $realRegId = $regIdPost;
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO registrations (
                        registration_no,
                        enquiry_id,
                        branch_id,
                        reg_type,
                        source_type,
                        assigned_to,
                        created_by,
                        joined_on,
                        enquiry_snapshot_name,
                        enquiry_snapshot_phone,
                        enquiry_snapshot_email,
                        program_name,
                        batch_name,
                        total_fee,
                        discount_amount,
                        final_fee,
                        paid_amount,
                        balance_amount,
                        payment_status,
                        registration_status,
                        notes,
                        created_at,
                        updated_at
                    ) VALUES (
                        ?, NULL, ?, ?, 'direct',
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        NOW(), NOW()
                    )
                ");
                $ins->execute([
                    $registration_no,
                    $branchId,
                    $reg_type,
                    $assigned_to,
                    $userId,
                    $joined_on,
                    $student_name,
                    $phone,
                    $email,
                    $program_name,
                    $batch_name,
                    $total_fee,
                    $discount_amount,
                    $final_fee,
                    0,
                    $final_fee,
                    'unpaid',
                    $registration_status,
                    $notes
                ]);

                $realRegId = (int)$pdo->lastInsertId();
            }

            // Upsert profile
            $st = $pdo->prepare("SELECT id FROM registration_profiles WHERE registration_id=? LIMIT 1");
            $st->execute([$realRegId]);
            $profileId = (int)($st->fetchColumn() ?? 0);

            if ($profileId > 0) {
                $upd = $pdo->prepare("
                    UPDATE registration_profiles
                    SET
                        student_name=?,
                        gender=?,
                        dob=?,
                        address=?,
                        qualification=?,
                        college_name=?,
                        year_of_passout=?,
                        parent_name=?,
                        parent_phone=?,
                        parent_occupation=?,
                        emergency_contact=?,
                        aadhaar_no=?,
                        remarks=?,
                        updated_at=NOW()
                    WHERE registration_id=?
                ");
                $upd->execute([
                    $student_name,
                    $gender,
                    $dob,
                    $address,
                    $qualification,
                    $college_name,
                    $year_of_passout,
                    $parent_name,
                    $parent_phone,
                    $parent_occupation,
                    $emergency_contact,
                    $aadhaar_no,
                    $remarks,
                    $realRegId
                ]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO registration_profiles (
                        registration_id,
                        student_name,
                        gender,
                        dob,
                        address,
                        qualification,
                        college_name,
                        year_of_passout,
                        parent_name,
                        parent_phone,
                        parent_occupation,
                        emergency_contact,
                        aadhaar_no,
                        remarks,
                        created_at,
                        updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                    )
                ");
                $ins->execute([
                    $realRegId,
                    $student_name,
                    $gender,
                    $dob,
                    $address,
                    $qualification,
                    $college_name,
                    $year_of_passout,
                    $parent_name,
                    $parent_phone,
                    $parent_occupation,
                    $emergency_contact,
                    $aadhaar_no,
                    $remarks
                ]);
            }

            $pdo->commit();

            if ($registration_status === 'active') {
                $success = "Registration confirmed successfully!";
                ?>
                <script>
                Swal.fire({
                  icon:'success',
                  title:'Success',
                  text:'<?= addslashes($success) ?>',
                  confirmButtonColor:'#e91e63'
                }).then(()=> window.location.href = "index.php?page=registrations/list");
                </script>
                <?php
                return;
            } else {
                $success = "Registration saved as draft successfully!";
                ?>
                <script>
                Swal.fire({
                  icon:'success',
                  title:'Success',
                  text:'<?= addslashes($success) ?>',
                  confirmButtonColor:'#e91e63'
                }).then(()=> window.location.href = "index.php?page=registrations/drafts");
                </script>
                <?php
                return;
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Failed to save registration. " . $e->getMessage();
        }
    }
}

/* =========================================================
   Prefill
========================================================= */
$registration_no     = $registration['registration_no'] ?? makeRegistrationNo($pdo, (int)($registration['id'] ?? 0));
$joined_on           = $registration['joined_on'] ?? date('Y-m-d');
$reg_type            = $registration['reg_type'] ?? 'course';
$program_name        = $registration['program_name'] ?? '';
$batch_name          = $registration['batch_name'] ?? '';
$registration_status = $registration['registration_status'] ?? 'draft';
$notes               = $registration['notes'] ?? '';

$assigned_to         = (int)($registration['assigned_to'] ?? 0);

$student_name        = $profile['student_name'] ?? ($registration['enquiry_snapshot_name'] ?? '');
$phone               = $registration['enquiry_snapshot_phone'] ?? '';
$email               = $registration['enquiry_snapshot_email'] ?? '';
$gender              = $profile['gender'] ?? '';
$dob                 = $profile['dob'] ?? '';
$address             = $profile['address'] ?? '';
$qualification       = $profile['qualification'] ?? '';
$college_name        = $profile['college_name'] ?? '';
$year_of_passout     = $profile['year_of_passout'] ?? '';
$parent_name         = $profile['parent_name'] ?? '';
$parent_phone        = $profile['parent_phone'] ?? '';
$parent_occupation   = $profile['parent_occupation'] ?? '';
$emergency_contact   = $profile['emergency_contact'] ?? '';
$aadhaar_no          = $profile['aadhaar_no'] ?? '';
$remarks             = $profile['remarks'] ?? '';

$total_fee           = $registration['total_fee'] ?? '0.00';
$discount_amount     = $registration['discount_amount'] ?? '0.00';
$final_fee           = $registration['final_fee'] ?? '0.00';

$isEditMode = !empty($registration['id']);
?>

<style>
:root{
  --reg-primary:#e91e63;
  --reg-primary-dark:#c2185b;
  --reg-primary-soft:#fff4f8;
  --reg-border:#ececf2;
  --reg-text:#202437;
  --reg-muted:#6b7280;
  --reg-bg:#f6f7fb;
  --reg-card:#ffffff;
  --reg-success:#16a34a;
  --reg-warning:#f59e0b;
  --reg-danger:#dc2626;
  --reg-shadow:0 20px 50px rgba(15, 23, 42, 0.08);
  --reg-shadow-soft:0 10px 30px rgba(15, 23, 42, 0.05);
}
.reg-page{
  background:linear-gradient(180deg, #fff 0%, #fff6fa 18%, #f8f9fd 100%);
  padding:18px;
  border-radius:24px;
}
.reg-topbar{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:16px;
  flex-wrap:wrap;
  margin-bottom:18px;
}
.reg-title-wrap h2{
  margin:0;
  font-size:28px;
  font-weight:900;
  color:var(--reg-text);
  letter-spacing:.2px;
}
.reg-subtitle{
  margin-top:6px;
  color:var(--reg-muted);
  font-size:14px;
  line-height:1.6;
}
.reg-badges{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.reg-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 14px;
  border-radius:999px;
  font-size:13px;
  font-weight:800;
  background:#fff;
  border:1px solid rgba(233,30,99,.12);
  color:var(--reg-primary-dark);
  box-shadow:var(--reg-shadow-soft);
}
.reg-badge i{ font-size:12px; }
.reg-layout{
  display:grid;
  grid-template-columns:340px minmax(0, 1fr);
  gap:18px;
  align-items:start;
}
.reg-sidebar{ position:sticky; top:14px; }
.reg-panel{
  background:var(--reg-card);
  border:1px solid rgba(15,23,42,.06);
  border-radius:22px;
  box-shadow:var(--reg-shadow);
  overflow:hidden;
}
.reg-panel + .reg-panel{ margin-top:16px; }
.reg-panel-head{
  padding:18px 20px;
  border-bottom:1px solid #f1f3f7;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  background:linear-gradient(180deg, #fff, #fffafb);
}
.reg-panel-title{
  margin:0;
  font-size:16px;
  font-weight:900;
  color:var(--reg-text);
  display:flex;
  align-items:center;
  gap:10px;
}
.reg-panel-title i{
  width:34px;
  height:34px;
  border-radius:12px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:var(--reg-primary-soft);
  color:var(--reg-primary);
  font-size:14px;
}
.reg-panel-body{ padding:18px 20px 20px; }
.reg-summary-card{ background:linear-gradient(135deg, #fff 0%, #fff7fb 100%); }
.reg-summary-list{ display:grid; gap:12px; }
.reg-summary-item{
  display:flex;
  align-items:flex-start;
  gap:12px;
  padding:14px;
  border:1px solid #f1e3ea;
  border-radius:16px;
  background:#fff;
}
.reg-summary-item .icon{
  width:40px;
  height:40px;
  border-radius:14px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:var(--reg-primary-soft);
  color:var(--reg-primary);
  font-size:15px;
  flex-shrink:0;
}
.reg-summary-item .meta .label{
  display:block;
  font-size:12px;
  font-weight:800;
  color:var(--reg-muted);
  text-transform:uppercase;
  letter-spacing:.5px;
  margin-bottom:4px;
}
.reg-summary-item .meta .value{
  display:block;
  font-size:14px;
  font-weight:800;
  color:var(--reg-text);
  word-break:break-word;
}
.reg-note{
  margin-top:14px;
  padding:14px 16px;
  border-radius:16px;
  background:#fff8ec;
  border:1px solid #fde7b0;
  color:#8a5a00;
  font-size:13px;
  line-height:1.7;
}
.reg-form-shell{ display:grid; gap:18px; }
.reg-section-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}
.reg-section-grid .full{ grid-column:1 / -1; }
.reg-field{
  display:flex;
  flex-direction:column;
  gap:7px;
}
.reg-label{
  font-size:13px;
  font-weight:800;
  color:var(--reg-text);
  margin:0;
}
.reg-label .req{ color:var(--reg-danger); margin-left:4px; }
.reg-input,.reg-select,.reg-textarea{
  width:100%;
  min-height:46px;
  padding:11px 14px;
  border:1px solid #dfe3ea;
  border-radius:14px;
  background:#fff;
  color:var(--reg-text);
  font-size:14px;
  transition:.2s ease;
  outline:none;
}
.reg-textarea{ min-height:auto; resize:vertical; }
.reg-input:focus,.reg-select:focus,.reg-textarea:focus{
  border-color:rgba(233,30,99,.55);
  box-shadow:0 0 0 4px rgba(233,30,99,.10);
}
.reg-input[readonly]{
  background:#f9fafc;
  color:#6b7280;
  font-weight:700;
}
.reg-form-main{
  background:var(--reg-card);
  border:1px solid rgba(15,23,42,.06);
  border-radius:22px;
  box-shadow:var(--reg-shadow);
  overflow:hidden;
}
.reg-form-head{
  padding:20px 22px;
  border-bottom:1px solid #eef1f5;
  background:linear-gradient(180deg, #fff 0%, #fffafe 100%);
}
.reg-form-head h3{
  margin:0;
  font-size:18px;
  font-weight:900;
  color:var(--reg-text);
}
.reg-form-head p{
  margin:6px 0 0;
  color:var(--reg-muted);
  font-size:13px;
}
.reg-form-body{ padding:22px; }
.reg-block{
  border:1px solid #eef1f5;
  border-radius:20px;
  background:#fff;
  overflow:hidden;
}
.reg-block + .reg-block{ margin-top:18px; }
.reg-block-head{
  padding:16px 18px;
  border-bottom:1px solid #eef1f5;
  background:#fcfcfe;
  display:flex;
  align-items:center;
  gap:12px;
}
.reg-block-head .step{
  width:34px;
  height:34px;
  border-radius:12px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:var(--reg-primary-soft);
  color:var(--reg-primary);
  font-size:14px;
  font-weight:900;
  flex-shrink:0;
}
.reg-block-head .titles h4{
  margin:0;
  font-size:15px;
  font-weight:900;
  color:var(--reg-text);
}
.reg-block-head .titles p{
  margin:4px 0 0;
  font-size:12px;
  color:var(--reg-muted);
}
.reg-block-body{ padding:18px; }
.reg-quick-stats{
  display:grid;
  grid-template-columns:repeat(3, 1fr);
  gap:12px;
  margin-bottom:18px;
}
.reg-stat{
  padding:14px;
  border-radius:18px;
  border:1px solid #eef1f5;
  background:linear-gradient(180deg, #fff, #fbfcff);
}
.reg-stat .label{
  display:block;
  font-size:12px;
  font-weight:800;
  color:var(--reg-muted);
  margin-bottom:6px;
}
.reg-stat .value{
  display:block;
  font-size:18px;
  font-weight:900;
  color:var(--reg-text);
}
.reg-actions-wrap{
  position:sticky;
  bottom:0;
  margin-top:20px;
  padding-top:4px;
}
.reg-actions{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:14px;
  flex-wrap:wrap;
  padding:16px 18px;
  border:1px solid #eef1f5;
  border-radius:20px;
  background:rgba(255,255,255,.88);
  backdrop-filter:blur(8px);
  box-shadow:var(--reg-shadow-soft);
}
.reg-actions-left,.reg-actions-right{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
}
.reg-btn{
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
  min-height:46px;
}
.reg-btn:hover{ transform:translateY(-1px); }
.reg-btn-primary{
  background:linear-gradient(135deg, var(--reg-primary), var(--reg-primary-dark));
  color:#fff;
  box-shadow:0 14px 26px rgba(233,30,99,.22);
}
.reg-btn-warning{
  background:linear-gradient(135deg, #f59e0b, #d97706);
  color:#fff;
  box-shadow:0 14px 26px rgba(245,158,11,.22);
}
.reg-btn-danger{
  background:linear-gradient(135deg, #ef4444, #dc2626);
  color:#fff;
  box-shadow:0 14px 26px rgba(239,68,68,.20);
}
.reg-btn-light{
  background:#fff;
  color:var(--reg-text);
  border:1px solid #dfe3ea;
}
@media (max-width: 1200px){
  .reg-layout{ grid-template-columns:1fr; }
  .reg-sidebar{ position:static; }
}
@media (max-width: 992px){
  .reg-section-grid,.reg-quick-stats{ grid-template-columns:1fr; }
  .reg-form-body,.reg-panel-body{ padding:16px; }
  .reg-form-head,.reg-panel-head{ padding:16px; }
  .reg-page{ padding:12px; border-radius:18px; }
}
@media (max-width: 576px){
  .reg-title-wrap h2{ font-size:22px; }
  .reg-actions{ padding:14px; }
  .reg-btn{ width:100%; }
  .reg-actions-left,.reg-actions-right{ width:100%; }
}
</style>

<div class="reg-page">
  <div class="reg-topbar">
    <div class="reg-title-wrap">
      <h2><?= $isEditMode ? 'Edit Direct / Walk-in Registration' : 'Add Direct / Walk-in Registration' ?></h2>
      <div class="reg-subtitle">
        Create clean, complete, and professional registration entries for direct or walk-in students.
      </div>
    </div>

    <div class="reg-badges">
      <span class="reg-badge"><i class="fas fa-user-check"></i> User Friendly Form</span>
      <span class="reg-badge"><i class="fas fa-file-signature"></i> Sales Ready UI</span>
      <span class="reg-badge"><i class="fas fa-shield-alt"></i> Secure Entry</span>
    </div>
  </div>

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

  <div class="reg-layout">
    <aside class="reg-sidebar">
      <div class="reg-panel reg-summary-card">
        <div class="reg-panel-head">
          <h3 class="reg-panel-title">
            <i class="fas fa-clipboard-list"></i>
            Registration Summary
          </h3>
        </div>
        <div class="reg-panel-body">
          <div class="reg-summary-list">
            <div class="reg-summary-item">
              <div class="icon"><i class="fas fa-hashtag"></i></div>
              <div class="meta">
                <span class="label">Registration No</span>
                <span class="value"><?= h($registration_no) ?></span>
              </div>
            </div>

            <div class="reg-summary-item">
              <div class="icon"><i class="fas fa-sign-in-alt"></i></div>
              <div class="meta">
                <span class="label">Source Type</span>
                <span class="value">Direct / Walk-in</span>
              </div>
            </div>

            <div class="reg-summary-item">
              <div class="icon"><i class="fas fa-id-badge"></i></div>
              <div class="meta">
                <span class="label">Registration ID</span>
                <span class="value"><?= (int)($registration['id'] ?? 0) ?></span>
              </div>
            </div>

            <div class="reg-summary-item">
              <div class="icon"><i class="fas fa-credit-card"></i></div>
              <div class="meta">
                <span class="label">Payments</span>
                <span class="value">Handled later in Registration List</span>
              </div>
            </div>
          </div>

          <div class="reg-note">
            <b>Note:</b> This page is only for registration entry. Payment collection and follow-up payment updates can be managed later from the registration list page.
          </div>
        </div>
      </div>

      <div class="reg-panel">
        <div class="reg-panel-head">
          <h3 class="reg-panel-title">
            <i class="fas fa-bolt"></i>
            Quick Overview
          </h3>
        </div>
        <div class="reg-panel-body">
          <div class="reg-quick-stats">
            <div class="reg-stat">
              <span class="label">Mode</span>
              <span class="value"><?= $isEditMode ? 'Edit' : 'New' ?></span>
            </div>
            <div class="reg-stat">
              <span class="label">Status</span>
              <span class="value"><?= h(ucfirst($registration_status)) ?></span>
            </div>
            <div class="reg-stat">
              <span class="label">Type</span>
              <span class="value"><?= h(ucfirst($reg_type)) ?></span>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <section class="reg-form-main">
      <div class="reg-form-head">
        <h3>Registration Form</h3>
        <p>Fill the student details, academic details, and fee details below.</p>
      </div>

      <div class="reg-form-body">
        <form method="POST" id="registrationForm">
          <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
          <input type="hidden" name="save_registration" value="1">
          <input type="hidden" name="reg_id" value="<?= (int)($registration['id'] ?? 0) ?>">
          <input type="hidden" name="registration_status" id="registration_status_input" value="<?= h($registration_status) ?>">

          <div class="reg-form-shell">
            <div class="reg-block">
              <div class="reg-block-head">
                <div class="step">1</div>
                <div class="titles">
                  <h4>Basic Registration Details</h4>
                  <p>Core registration information for the student entry.</p>
                </div>
              </div>
              <div class="reg-block-body">
                <div class="reg-section-grid">
                  <div class="reg-field">
                    <label class="reg-label">Registration No</label>
                    <input class="reg-input" type="text" name="registration_no" value="<?= h($registration_no) ?>" readonly>
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Joined On</label>
                    <input class="reg-input" type="date" name="joined_on" value="<?= h($joined_on) ?>">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Registration Type</label>
                    <select class="reg-select" name="reg_type">
                      <option value="course" <?= $reg_type==='course'?'selected':''; ?>>Course</option>
                      <option value="internship" <?= $reg_type==='internship'?'selected':''; ?>>Internship</option>
                      <option value="workshop" <?= $reg_type==='workshop'?'selected':''; ?>>Workshop</option>
                    </select>
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Front Office Owner <span class="req">*</span></label>
                    <select class="reg-select" name="assigned_to" required>
                      <option value="">-- Select Front Office --</option>
                      <?php foreach ($frontOfficeUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>" <?= $assigned_to === (int)$u['id'] ? 'selected' : '' ?>>
                          <?= h($u['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div class="reg-block">
              <div class="reg-block-head">
                <div class="step">2</div>
                <div class="titles">
                  <h4>Student Personal Details</h4>
                  <p>Main student information for contact and profile records.</p>
                </div>
              </div>
              <div class="reg-block-body">
                <div class="reg-section-grid">
                  <div class="reg-field">
                    <label class="reg-label">Student Name <span class="req">*</span></label>
                    <input class="reg-input" type="text" name="student_name" value="<?= h($student_name) ?>" required placeholder="Enter student full name">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Phone</label>
                    <input class="reg-input" type="text" name="phone" value="<?= h($phone) ?>" placeholder="Enter mobile number">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Email</label>
                    <input class="reg-input" type="email" name="email" value="<?= h($email) ?>" placeholder="Enter email address">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Gender</label>
                    <select class="reg-select" name="gender">
                      <option value="">-- Select --</option>
                      <option value="male" <?= $gender==='male'?'selected':''; ?>>Male</option>
                      <option value="female" <?= $gender==='female'?'selected':''; ?>>Female</option>
                      <option value="other" <?= $gender==='other'?'selected':''; ?>>Other</option>
                    </select>
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">DOB</label>
                    <input class="reg-input" type="date" name="dob" value="<?= h($dob) ?>">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Aadhaar No</label>
                    <input class="reg-input" type="text" name="aadhaar_no" value="<?= h($aadhaar_no) ?>" placeholder="Enter Aadhaar number">
                  </div>

                  <div class="reg-field full">
                    <label class="reg-label">Address</label>
                    <textarea class="reg-textarea" name="address" rows="3" placeholder="Enter full address"><?= h($address) ?></textarea>
                  </div>
                </div>
              </div>
            </div>

            <div class="reg-block">
              <div class="reg-block-head">
                <div class="step">3</div>
                <div class="titles">
                  <h4>Academic & Program Details</h4>
                  <p>Program, batch, and education details of the student.</p>
                </div>
              </div>
              <div class="reg-block-body">
                <div class="reg-section-grid">
                  <div class="reg-field">
                    <label class="reg-label">Program Name</label>
                    <input class="reg-input" type="text" name="program_name" value="<?= h($program_name) ?>" placeholder="Enter program name">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Batch Name</label>
                    <input class="reg-input" type="text" name="batch_name" value="<?= h($batch_name) ?>" placeholder="Enter batch name">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Qualification</label>
                    <input class="reg-input" type="text" name="qualification" value="<?= h($qualification) ?>" placeholder="Enter qualification">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">College Name</label>
                    <input class="reg-input" type="text" name="college_name" value="<?= h($college_name) ?>" placeholder="Enter college name">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Year of Passout</label>
                    <input class="reg-input" type="text" name="year_of_passout" value="<?= h($year_of_passout) ?>" placeholder="Enter passout year">
                  </div>
                </div>
              </div>
            </div>

            <div class="reg-block">
              <div class="reg-block-head">
                <div class="step">4</div>
                <div class="titles">
                  <h4>Parent & Emergency Details</h4>
                  <p>Useful for contact, follow-up, and support communication.</p>
                </div>
              </div>
              <div class="reg-block-body">
                <div class="reg-section-grid">
                  <div class="reg-field">
                    <label class="reg-label">Parent Name</label>
                    <input class="reg-input" type="text" name="parent_name" value="<?= h($parent_name) ?>" placeholder="Enter parent name">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Parent Phone</label>
                    <input class="reg-input" type="text" name="parent_phone" value="<?= h($parent_phone) ?>" placeholder="Enter parent phone number">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Parent Occupation</label>
                    <input class="reg-input" type="text" name="parent_occupation" value="<?= h($parent_occupation) ?>" placeholder="Enter parent occupation">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Emergency Contact</label>
                    <input class="reg-input" type="text" name="emergency_contact" value="<?= h($emergency_contact) ?>" placeholder="Enter emergency contact number">
                  </div>
                </div>
              </div>
            </div>

            <div class="reg-block">
              <div class="reg-block-head">
                <div class="step">5</div>
                <div class="titles">
                  <h4>Fee Details</h4>
                  <p>Enter fee breakdown clearly for easy calculation and confirmation.</p>
                </div>
              </div>
              <div class="reg-block-body">
                <div class="reg-section-grid">
                  <div class="reg-field">
                    <label class="reg-label">Total Fee</label>
                    <input class="reg-input fee-calc" type="number" step="0.01" name="total_fee" value="<?= h($total_fee) ?>" placeholder="0.00">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Discount Amount</label>
                    <input class="reg-input fee-calc" type="number" step="0.01" name="discount_amount" value="<?= h($discount_amount) ?>" placeholder="0.00">
                  </div>

                  <div class="reg-field">
                    <label class="reg-label">Final Fee</label>
                    <input class="reg-input" type="number" step="0.01" name="final_fee" value="<?= h($final_fee) ?>" placeholder="0.00">
                  </div>
                </div>
              </div>
            </div>

            <div class="reg-block">
              <div class="reg-block-head">
                <div class="step">6</div>
                <div class="titles">
                  <h4>Additional Notes</h4>
                  <p>Keep internal notes and remarks for future reference.</p>
                </div>
              </div>
              <div class="reg-block-body">
                <div class="reg-section-grid">
                  <div class="reg-field full">
                    <label class="reg-label">Registration Notes</label>
                    <textarea class="reg-textarea" name="notes" rows="3" placeholder="Enter registration notes"><?= h($notes) ?></textarea>
                  </div>

                  <div class="reg-field full">
                    <label class="reg-label">Profile Remarks</label>
                    <textarea class="reg-textarea" name="remarks" rows="3" placeholder="Enter internal remarks"><?= h($remarks) ?></textarea>
                  </div>
                </div>
              </div>
            </div>

            <div class="reg-actions-wrap">
              <div class="reg-actions">
                <div class="reg-actions-left">
                  <button type="button" class="reg-btn reg-btn-primary" onclick="submitRegistrationForm('active')">
                    <i class="fas fa-check-circle"></i>
                    Confirm Registration
                  </button>

                  <button type="button" class="reg-btn reg-btn-warning" onclick="submitRegistrationForm('draft')">
                    <i class="fas fa-save"></i>
                    Save for Later
                  </button>

                  <?php if (!empty($registration['id'])): ?>
                    <button type="submit" name="delete_registration" class="reg-btn reg-btn-danger" onclick="return confirmDeleteReg(event)">
                      <i class="fas fa-trash-alt"></i>
                      Delete
                    </button>
                  <?php endif; ?>
                </div>

                <div class="reg-actions-right">
                  <a href="index.php?page=registrations/drafts" class="reg-btn reg-btn-light">
                    <i class="fas fa-arrow-left"></i>
                    Back
                  </a>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
    </section>
  </div>
</div>

<script>
(function(){
  const total = document.querySelector('input[name="total_fee"]');
  const discount = document.querySelector('input[name="discount_amount"]');
  const finalFee = document.querySelector('input[name="final_fee"]');

  function calc(){
    if (!total || !discount || !finalFee) return;
    const t = parseFloat(total.value || 0);
    const d = parseFloat(discount.value || 0);
    let f = t - d;
    if (f < 0) f = 0;
    finalFee.value = f.toFixed(2);
  }

  [total, discount].forEach(el => {
    if (el) el.addEventListener('input', calc);
  });
})();

function submitRegistrationForm(status){
  document.getElementById('registration_status_input').value = status;

  Swal.fire({
    icon:'question',
    title: status === 'active' ? 'Confirm Registration?' : 'Save as Draft?',
    text: status === 'active'
      ? 'This student will move to confirmed registrations list.'
      : 'This student will be saved under drafts.',
    showCancelButton:true,
    confirmButtonText: status === 'active' ? 'Confirm' : 'Save Draft',
    cancelButtonText:'Cancel',
    confirmButtonColor:'#e91e63'
  }).then((r)=>{
    if (r.isConfirmed) {
      document.getElementById('registrationForm').submit();
    }
  });
}

function confirmDeleteReg(e){
  e.preventDefault();

  Swal.fire({
    icon:'warning',
    title:'Delete Registration?',
    text:'This will delete the registration permanently.',
    showCancelButton:true,
    confirmButtonText:'Yes, Delete',
    cancelButtonText:'Cancel',
    confirmButtonColor:'#d32f2f'
  }).then((r)=>{
    if (r.isConfirmed) {
      const btn = document.querySelector('button[name="delete_registration"]');
      if (btn) {
        btn.removeAttribute('onclick');
        btn.click();
      }
    }
  });

  return false;
}
</script>