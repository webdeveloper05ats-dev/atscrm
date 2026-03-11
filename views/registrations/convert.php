<?php
// =====================================
// Registrations - Convert / Edit
// Slug: registrations/convert
// File: views/registrations/convert.php
// UI upgraded only - logic unchanged
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";
$redirectUrl = "";

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

function makeRegistrationNo(PDO $pdo): string {
    $prefix = 'REG-' . date('Ym') . '-';

    $st = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations
        WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    ");
    $st->execute();
    $count = (int)$st->fetchColumn();

    return $prefix . str_pad((string)($count + 1), 4, '0', STR_PAD_LEFT);
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
   Params
========================================================= */
$regId     = (int)($_GET['reg_id'] ?? 0);
$enquiryId = (int)($_GET['enquiry_id'] ?? 0);
$regType   = trim((string)($_GET['type'] ?? 'course'));

if (!in_array($regType, ['course','internship','workshop'], true)) {
    $regType = 'course';
}

/* =========================================================
   Load Data
========================================================= */
$registration = null;
$profile      = null;
$enquiry      = null;

try {
    if ($regId > 0) {
        if ($canAllBranches !== 1 && $branchId > 0) {
            $st = $pdo->prepare("SELECT * FROM registrations WHERE id=? AND branch_id=? LIMIT 1");
            $st->execute([$regId, $branchId]);
        } else {
            $st = $pdo->prepare("SELECT * FROM registrations WHERE id=? LIMIT 1");
            $st->execute([$regId]);
        }

        $registration = $st->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            throw new Exception("Registration not found or access denied.");
        }

        $enquiryId = (int)($registration['enquiry_id'] ?? 0);
        $regType   = (string)($registration['reg_type'] ?? $regType);

        $st = $pdo->prepare("SELECT * FROM registration_profiles WHERE registration_id=? LIMIT 1");
        $st->execute([$regId]);
        $profile = $st->fetch(PDO::FETCH_ASSOC);
    }

    if ($enquiryId > 0) {
        if ($canAllBranches !== 1 && $branchId > 0) {
            $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? AND branch_id=? LIMIT 1");
            $st->execute([$enquiryId, $branchId]);
        } else {
            $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? LIMIT 1");
            $st->execute([$enquiryId]);
        }

        $enquiry = $st->fetch(PDO::FETCH_ASSOC);

        if (!$enquiry) {
            throw new Exception("Enquiry not found or access denied.");
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

/* =========================================================
   Save
========================================================= */
if (isset($_POST['save_registration'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF).";
    } else {
        try {
            $regIdPost           = (int)($_POST['reg_id'] ?? 0);
            $enquiryIdPost       = (int)($_POST['enquiry_id'] ?? 0);
            $regTypePost         = trim((string)($_POST['reg_type'] ?? 'course'));
            $registration_status = trim((string)($_POST['registration_status'] ?? 'draft'));

            if (!in_array($regTypePost, ['course','internship','workshop'], true)) {
                throw new Exception("Invalid registration type.");
            }

            if (!in_array($registration_status, ['draft','active'], true)) {
                $registration_status = 'draft';
            }

            // Load enquiry
            if ($canAllBranches !== 1 && $branchId > 0) {
                $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? AND branch_id=? LIMIT 1");
                $st->execute([$enquiryIdPost, $branchId]);
            } else {
                $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? LIMIT 1");
                $st->execute([$enquiryIdPost]);
            }
            $enquiryRow = $st->fetch(PDO::FETCH_ASSOC);

            if (!$enquiryRow) {
                throw new Exception("Enquiry not found or access denied.");
            }

            $useBranchId = (int)($enquiryRow['branch_id'] ?? $branchId);
            $assignedTo  = (int)($enquiryRow['handled_by'] ?? 0);

            // If editing, first load existing registration again safely
            $existingReg = null;
            if ($regIdPost > 0) {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("SELECT * FROM registrations WHERE id=? AND branch_id=? LIMIT 1");
                    $st->execute([$regIdPost, $branchId]);
                } else {
                    $st = $pdo->prepare("SELECT * FROM registrations WHERE id=? LIMIT 1");
                    $st->execute([$regIdPost]);
                }
                $existingReg = $st->fetch(PDO::FETCH_ASSOC);

                if (!$existingReg) {
                    throw new Exception("Registration not found for update.");
                }
            }

            // Form values
            $registration_no   = regToNull($_POST['registration_no'] ?? '');
            $source_type       = regToNull($_POST['source_type'] ?? 'direct') ?: 'direct';
            $joined_on         = regDateOrNull($_POST['joined_on'] ?? '');
            $program_name      = regToNull($_POST['program_name'] ?? '');
            $batch_name        = regToNull($_POST['batch_name'] ?? '');
            $notes             = regToNull($_POST['notes'] ?? '');

            $student_name      = regToNull($_POST['student_name'] ?? '');
            $phone             = regToNull($_POST['phone'] ?? '');
            $email             = regToNull($_POST['email'] ?? '');
            $gender            = regToNull($_POST['gender'] ?? '');
            $dob               = regDateOrNull($_POST['dob'] ?? '');
            $address           = regToNull($_POST['address'] ?? '');
            $qualification     = regToNull($_POST['qualification'] ?? '');
            $college_name      = regToNull($_POST['college_name'] ?? '');
            $year_of_passout   = regToNull($_POST['year_of_passout'] ?? '');
            $parent_name       = regToNull($_POST['parent_name'] ?? '');
            $parent_phone      = regToNull($_POST['parent_phone'] ?? '');
            $parent_occupation = regToNull($_POST['parent_occupation'] ?? '');
            $emergency_contact = regToNull($_POST['emergency_contact'] ?? '');
            $aadhaar_no        = regToNull($_POST['aadhaar_no'] ?? '');
            $remarks           = regToNull($_POST['remarks'] ?? '');

            $total_fee         = regDec($_POST['total_fee'] ?? 0);
            $discount_amount   = regDec($_POST['discount_amount'] ?? 0);
            $final_fee         = regDec($_POST['final_fee'] ?? 0);

            if ($student_name === null) {
                $student_name = regToNull($existingReg['enquiry_snapshot_name'] ?? '') ?: regToNull($enquiryRow['name'] ?? '');
            }
            if ($phone === null) {
                $phone = regToNull($existingReg['enquiry_snapshot_phone'] ?? '') ?: regToNull($enquiryRow['phone'] ?? '');
            }
            if ($email === null) {
                $email = regToNull($existingReg['enquiry_snapshot_email'] ?? '') ?: regToNull($enquiryRow['email'] ?? '');
            }

            if ($student_name === null) {
                throw new Exception("Student name is required.");
            }

            // IMPORTANT FIX: preserve existing registration no on edit
            if ($regIdPost > 0) {
                $registration_no = regToNull($existingReg['registration_no'] ?? '') ?: $registration_no;
            }

            if ($registration_no === null) {
                $registration_no = makeRegistrationNo($pdo);
            }

            // Ensure registration_no unique only for other rows
            $st = $pdo->prepare("SELECT id FROM registrations WHERE registration_no=? AND id<>? LIMIT 1");
            $st->execute([$registration_no, $regIdPost]);
            if ($st->fetchColumn()) {
                throw new Exception("Registration number already exists. Please refresh and try again.");
            }

            if ($final_fee <= 0) {
                $final_fee = max(0, $total_fee - $discount_amount);
            }

            $paid_amount = 0;
            if ($existingReg && isset($existingReg['paid_amount'])) {
                $paid_amount = (float)$existingReg['paid_amount'];
            } elseif ($registration && isset($registration['paid_amount'])) {
                $paid_amount = (float)$registration['paid_amount'];
            }

            if ($paid_amount < 0) {
                $paid_amount = 0;
            }

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
                        enquiry_id=?,
                        branch_id=?,
                        reg_type=?,
                        source_type=?,
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
                    $enquiryIdPost,
                    $useBranchId,
                    $regTypePost,
                    $source_type,
                    ($assignedTo > 0 ? $assignedTo : null),
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
                        ?, ?, ?, ?, ?,
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
                    $enquiryIdPost,
                    $useBranchId,
                    $regTypePost,
                    $source_type,
                    ($assignedTo > 0 ? $assignedTo : null),
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
                    $paid_amount,
                    $balance_amount,
                    $payment_status,
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
                $redirectUrl = "index.php?page=registrations/list";
            } else {
                $success = "Registration saved for later successfully!";
                $redirectUrl = "index.php?page=registrations/drafts";
            }

            // refresh local values after save
            $regId = $realRegId;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Failed to save registration. " . $e->getMessage();
        }
    }
}

/* =========================================================
   Reload latest data after save or edit
========================================================= */
if ($regId > 0) {
    try {
        if ($canAllBranches !== 1 && $branchId > 0) {
            $st = $pdo->prepare("SELECT * FROM registrations WHERE id=? AND branch_id=? LIMIT 1");
            $st->execute([$regId, $branchId]);
        } else {
            $st = $pdo->prepare("SELECT * FROM registrations WHERE id=? LIMIT 1");
            $st->execute([$regId]);
        }
        $registration = $st->fetch(PDO::FETCH_ASSOC);

        if ($registration) {
            $enquiryId = (int)$registration['enquiry_id'];
            $regType   = (string)$registration['reg_type'];

            $st = $pdo->prepare("SELECT * FROM registration_profiles WHERE registration_id=? LIMIT 1");
            $st->execute([$regId]);
            $profile = $st->fetch(PDO::FETCH_ASSOC);

            if ($enquiryId > 0) {
                if ($canAllBranches !== 1 && $branchId > 0) {
                    $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? AND branch_id=? LIMIT 1");
                    $st->execute([$enquiryId, $branchId]);
                } else {
                    $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? LIMIT 1");
                    $st->execute([$enquiryId]);
                }
                $enquiry = $st->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {
        // ignore reload error
    }
}

/* =========================================================
   Prefill
========================================================= */
$registration_no   = $registration['registration_no'] ?? makeRegistrationNo($pdo);
$source_type       = $registration['source_type'] ?? 'direct';
$joined_on         = $registration['joined_on'] ?? date('Y-m-d');
$program_name      = $registration['program_name'] ?? ($enquiry['course_interest'] ?? '');
$batch_name        = $registration['batch_name'] ?? '';
$notes             = $registration['notes'] ?? '';

$student_name      = $profile['student_name']
                    ?? $registration['enquiry_snapshot_name']
                    ?? ($enquiry['name'] ?? '');

$phone             = $registration['enquiry_snapshot_phone']
                    ?? ($enquiry['phone'] ?? '');

$email             = $registration['enquiry_snapshot_email']
                    ?? ($enquiry['email'] ?? '');

$gender            = $profile['gender']
                    ?? ($enquiry['gender'] ?? '');

$dob               = $profile['dob']
                    ?? ($enquiry['dob'] ?? '');

$address           = $profile['address']
                    ?? ($enquiry['address'] ?? '');

$qualification     = $profile['qualification']
                    ?? ($enquiry['qualification'] ?? '');

$college_name      = $profile['college_name']
                    ?? ($enquiry['college'] ?? '');

$year_of_passout   = $profile['year_of_passout']
                    ?? ($enquiry['year_of_passout'] ?? '');

$parent_name       = $profile['parent_name']
                    ?? ($enquiry['father_name'] ?? '');

$parent_phone      = $profile['parent_phone']
                    ?? ($enquiry['father_contact_no'] ?? '');

$parent_occupation = $profile['parent_occupation']
                    ?? ($enquiry['father_occupation'] ?? '');

$emergency_contact = $profile['emergency_contact'] ?? '';
$aadhaar_no        = $profile['aadhaar_no'] ?? '';
$remarks           = $profile['remarks'] ?? '';

$total_fee         = $registration['total_fee'] ?? '0.00';
$discount_amount   = $registration['discount_amount'] ?? '0.00';
$final_fee         = $registration['final_fee'] ?? '0.00';
?>

<style>
:root{
  --reg-primary:#e91e63;
  --reg-primary-dark:#c2185b;
  --reg-soft:#fff5f8;
  --reg-soft-2:#fff9fb;
  --reg-border:#f1d7e2;
  --reg-text:#212529;
  --reg-muted:#6c757d;
  --reg-card-shadow:0 12px 32px rgba(17,17,26,.08);
  --reg-radius:18px;
}

.reg-page{
  padding:6px 0 14px;
}

.reg-hero{
  background:linear-gradient(135deg,#fff7fb 0%,#fff 45%,#f8f9ff 100%);
  border:1px solid var(--reg-border);
  border-radius:24px;
  padding:22px 24px;
  margin-bottom:20px;
  box-shadow:0 12px 30px rgba(233,30,99,.08);
}

.reg-hero-top{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:16px;
  flex-wrap:wrap;
}

.reg-hero-title{
  margin:0;
  font-size:1.65rem;
  font-weight:800;
  color:var(--reg-text);
  letter-spacing:.2px;
}

.reg-hero-subtitle{
  margin:8px 0 0;
  color:var(--reg-muted);
  font-size:.95rem;
  max-width:760px;
}

.reg-hero-badges{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  margin-top:14px;
}

.reg-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 14px;
  border-radius:999px;
  background:#fff;
  border:1px solid #f2d8e4;
  color:#444;
  font-size:.85rem;
  font-weight:600;
  box-shadow:0 6px 14px rgba(0,0,0,.04);
}

.reg-side-stat{
  min-width:180px;
  background:#fff;
  border:1px solid #f2d8e4;
  border-radius:18px;
  padding:16px 18px;
  box-shadow:0 8px 18px rgba(0,0,0,.04);
}

.reg-side-stat .small{
  font-size:.78rem;
  color:var(--reg-muted);
  text-transform:uppercase;
  letter-spacing:.5px;
  margin-bottom:5px;
  display:block;
}

.reg-side-stat .value{
  font-size:1.1rem;
  color:var(--reg-primary);
  font-weight:800;
  line-height:1.2;
  word-break:break-word;
}

.reg-layout{
  display:grid;
  grid-template-columns:330px 1fr;
  gap:18px;
  align-items:start;
}

.reg-card{
  background:#fff;
  border:none;
  border-radius:var(--reg-radius);
  margin-bottom:16px;
  box-shadow:var(--reg-card-shadow);
  overflow:hidden;
}

.reg-head{
  padding:15px 18px;
  font-weight:800;
  font-size:1rem;
  color:#fff;
  border-bottom:none;
  background:linear-gradient(135deg,var(--reg-primary) 0%,#ff5f8f 100%);
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}

.reg-head small{
  font-weight:500;
  opacity:.95;
  font-size:.82rem;
}

.reg-body{
  padding:18px;
}

.reg-summary-box{
  background:linear-gradient(180deg,#fff 0%,#fffafd 100%);
  border:1px solid #f4dbe6;
  border-radius:16px;
  padding:14px;
}

.reg-summary-list{
  display:grid;
  gap:12px;
}

.reg-summary-item{
  display:flex;
  align-items:flex-start;
  gap:10px;
  padding:12px;
  border-radius:14px;
  background:#fff;
  border:1px solid #f3edf0;
}

.reg-summary-icon{
  width:40px;
  height:40px;
  flex-shrink:0;
  border-radius:12px;
  display:flex;
  align-items:center;
  justify-content:center;
  background:#fff0f5;
  color:var(--reg-primary);
  font-size:1rem;
}

.reg-summary-content{
  min-width:0;
}

.reg-summary-label{
  font-size:.77rem;
  font-weight:700;
  color:var(--reg-muted);
  text-transform:uppercase;
  letter-spacing:.4px;
  margin-bottom:3px;
}

.reg-summary-value{
  font-size:.93rem;
  font-weight:700;
  color:#212529;
  line-height:1.35;
  word-break:break-word;
}

.reg-summary-note{
  margin-top:12px;
  padding:12px 13px;
  border-radius:14px;
  background:#fff7fb;
  border:1px dashed #edbfd0;
  font-size:.86rem;
  color:#6c5561;
  line-height:1.5;
}

.reg-section-title{
  font-size:1rem;
  font-weight:800;
  color:#222;
  margin:0 0 14px;
  padding-bottom:10px;
  border-bottom:1px solid #f3f3f3;
  display:flex;
  align-items:center;
  gap:8px;
}

.convert-grid{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}

.convert-grid .full{
  grid-column:1 / -1;
}

.convert-grid .span-3{
  grid-column:span 1;
}

.reg-field{
  display:flex;
  flex-direction:column;
}

.reg-label{
  display:block;
  font-size:12px;
  font-weight:800;
  margin-bottom:7px;
  color:#2f2f2f;
  letter-spacing:.2px;
}

.reg-input,
.reg-select,
.reg-textarea{
  width:100%;
  padding:11px 13px;
  border:1px solid #dedede;
  border-radius:12px;
  outline:none;
  background:#fff;
  transition:all .2s ease;
  font-size:.93rem;
  color:#212529;
}

.reg-input::placeholder,
.reg-textarea::placeholder{
  color:#adb5bd;
}

.reg-input[readonly]{
  background:#fbfbfb;
  color:#555;
  cursor:not-allowed;
}

.reg-input:focus,
.reg-select:focus,
.reg-textarea:focus{
  border-color:rgba(233,30,99,.55);
  box-shadow:0 0 0 4px rgba(233,30,99,.12);
  background:#fff;
}

.reg-textarea{
  resize:vertical;
  min-height:96px;
}

.reg-group-card{
  background:#fff;
  border:1px solid #f2f2f2;
  border-radius:16px;
  padding:16px;
  margin-bottom:16px;
}

.reg-group-card:last-child{
  margin-bottom:0;
}

.reg-inline-note{
  font-size:.8rem;
  color:#6c757d;
  margin-top:6px;
}

.reg-fee-strip{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:14px;
  margin-top:6px;
}

.reg-fee-box{
  border:1px solid #f1d7e2;
  background:linear-gradient(180deg,#fff 0%,#fff8fb 100%);
  border-radius:16px;
  padding:14px;
}

.reg-fee-box .fee-label{
  font-size:.8rem;
  color:#6c757d;
  font-weight:700;
  margin-bottom:4px;
  text-transform:uppercase;
}

.reg-fee-box .fee-value{
  font-size:1.05rem;
  font-weight:800;
  color:#212529;
}

.reg-actions{
  display:flex;
  justify-content:flex-end;
  gap:10px;
  flex-wrap:wrap;
  margin-top:18px;
  padding-top:16px;
  border-top:1px solid #f3f3f3;
}

.btn-reg{
  border:none;
  border-radius:12px;
  padding:11px 18px;
  font-weight:700;
  font-size:.92rem;
  transition:all .2s ease;
  text-decoration:none !important;
  display:inline-flex;
  align-items:center;
  gap:8px;
  cursor:pointer;
}

.btn-reg-primary{
  background:linear-gradient(135deg,var(--reg-primary) 0%,#ff5f8f 100%);
  color:#fff;
  box-shadow:0 10px 20px rgba(233,30,99,.18);
}

.btn-reg-primary:hover{
  color:#fff;
  transform:translateY(-1px);
}

.btn-reg-warning{
  background:linear-gradient(135deg,#ffb300 0%,#ffca28 100%);
  color:#4a3b00;
  box-shadow:0 10px 20px rgba(255,179,0,.18);
}

.btn-reg-warning:hover{
  color:#4a3b00;
  transform:translateY(-1px);
}

.btn-reg-light{
  background:#fff;
  color:#495057;
  border:1px solid #e7e7e7;
}

.btn-reg-light:hover{
  background:#f8f9fa;
  color:#212529;
}

.reg-top-note{
  font-size:.84rem;
  color:#6c757d;
}

@media (max-width: 1100px){
  .reg-layout{
    grid-template-columns:1fr;
  }
}

@media (max-width: 900px){
  .convert-grid{
    grid-template-columns:1fr;
  }
  .reg-fee-strip{
    grid-template-columns:1fr;
  }
}

@media (max-width: 576px){
  .reg-hero{
    padding:18px;
    border-radius:18px;
  }
  .reg-hero-title{
    font-size:1.35rem;
  }
  .reg-body{
    padding:14px;
  }
  .reg-group-card{
    padding:14px;
  }
  .reg-actions{
    flex-direction:column;
    align-items:stretch;
  }
  .btn-reg{
    width:100%;
    justify-content:center;
  }
}
</style>

<div class="reg-page">

  <div class="reg-hero">
    <div class="reg-hero-top">
      <div>
        <h2 class="reg-hero-title">
          <i class="fas fa-user-plus mr-2"></i>Convert Registration
        </h2>
        <p class="reg-hero-subtitle">
          Complete the registration details, review the student profile, and either confirm the registration or save it as a draft for later completion.
        </p>

        <div class="reg-hero-badges">
          <span class="reg-badge">
            <i class="fas fa-id-card"></i>
            <?= h($registration_no) ?>
          </span>
          <span class="reg-badge">
            <i class="fas fa-layer-group"></i>
            <?= h(ucfirst($regType)) ?>
          </span>
          <span class="reg-badge">
            <i class="fas fa-random"></i>
            <?= h(ucfirst($source_type)) ?>
          </span>
        </div>
      </div>

      <div class="reg-side-stat">
        <span class="small">Current Mode</span>
        <div class="value">
          <?= !empty($registration['id']) ? 'Edit Existing Registration' : 'New Registration Conversion' ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($success): ?>
  <script>
  Swal.fire({
    icon:'success',
    title:'Success',
    text:'<?= addslashes($success) ?>',
    confirmButtonColor:'#e91e63'
  }).then(()=> {
    window.location.href = "<?= h($redirectUrl) ?>";
  });
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

  <div class="reg-layout">

    <div>
      <div class="reg-card">
        <div class="reg-head">
          <span><i class="fas fa-clipboard-list mr-2"></i>Registration Summary</span>
          <small>Quick overview</small>
        </div>
        <div class="reg-body">
          <div class="reg-summary-box">
            <div class="reg-summary-list">
              <div class="reg-summary-item">
                <div class="reg-summary-icon"><i class="fas fa-hashtag"></i></div>
                <div class="reg-summary-content">
                  <div class="reg-summary-label">Registration No</div>
                  <div class="reg-summary-value"><?= h($registration_no) ?></div>
                </div>
              </div>

              <div class="reg-summary-item">
                <div class="reg-summary-icon"><i class="fas fa-user-tag"></i></div>
                <div class="reg-summary-content">
                  <div class="reg-summary-label">Source Type</div>
                  <div class="reg-summary-value"><?= h(ucfirst($source_type)) ?></div>
                </div>
              </div>

              <div class="reg-summary-item">
                <div class="reg-summary-icon"><i class="fas fa-file-signature"></i></div>
                <div class="reg-summary-content">
                  <div class="reg-summary-label">Registration ID</div>
                  <div class="reg-summary-value"><?= (int)($registration['id'] ?? 0) ?></div>
                </div>
              </div>

              <div class="reg-summary-item">
                <div class="reg-summary-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="reg-summary-content">
                  <div class="reg-summary-label">Student</div>
                  <div class="reg-summary-value"><?= h($student_name ?: '-') ?></div>
                </div>
              </div>

              <div class="reg-summary-item">
                <div class="reg-summary-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="reg-summary-content">
                  <div class="reg-summary-label">Phone</div>
                  <div class="reg-summary-value"><?= h($phone ?: '-') ?></div>
                </div>
              </div>
            </div>

            <div class="reg-summary-note">
              <i class="fas fa-info-circle mr-1"></i>
              Payments will be handled later in the Registration List page.
            </div>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="reg-card">
        <div class="reg-head">
          <span><i class="fas fa-edit mr-2"></i>Registration Form</span>
          <small>Fill the required student and course details</small>
        </div>

        <div class="reg-body">
          <form method="POST" id="registrationForm">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="save_registration" value="1">
            <input type="hidden" name="reg_id" value="<?= (int)($registration['id'] ?? 0) ?>">
            <input type="hidden" name="enquiry_id" value="<?= (int)$enquiryId ?>">
            <input type="hidden" name="reg_type" value="<?= h($regType) ?>">
            <input type="hidden" name="registration_status" id="registration_status_input" value="draft">

            <div class="reg-group-card">
              <h4 class="reg-section-title">
                <i class="fas fa-briefcase"></i> Registration Details
              </h4>

              <div class="convert-grid">
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
                  <input class="reg-input" type="text" value="<?= h(ucfirst($regType)) ?>" readonly>
                </div>

                <div class="reg-field">
                  <label class="reg-label">Source Type</label>
                  <select class="reg-select" name="source_type">
                    <option value="direct" <?= $source_type==='direct'?'selected':''; ?>>Direct</option>
                    <option value="walkin" <?= $source_type==='walkin'?'selected':''; ?>>Walk-in</option>
                    <option value="lead" <?= $source_type==='lead'?'selected':''; ?>>Lead</option>
                    <option value="reference" <?= $source_type==='reference'?'selected':''; ?>>Reference</option>
                    <option value="online" <?= $source_type==='online'?'selected':''; ?>>Online</option>
                    <option value="other" <?= $source_type==='other'?'selected':''; ?>>Other</option>
                  </select>
                </div>

                <div class="reg-field">
                  <label class="reg-label">Program Name</label>
                  <input class="reg-input" type="text" name="program_name" value="<?= h($program_name) ?>" placeholder="Enter program or course name">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Batch Name</label>
                  <input class="reg-input" type="text" name="batch_name" value="<?= h($batch_name) ?>" placeholder="Enter batch name">
                </div>
              </div>
            </div>

            <div class="reg-group-card">
              <h4 class="reg-section-title">
                <i class="fas fa-user"></i> Student Information
              </h4>

              <div class="convert-grid">
                <div class="reg-field">
                  <label class="reg-label">Student Name</label>
                  <input class="reg-input" type="text" name="student_name" value="<?= h($student_name) ?>" required placeholder="Enter student name">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Phone</label>
                  <input class="reg-input" type="text" name="phone" value="<?= h($phone) ?>" placeholder="Enter phone number">
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
                  <label class="reg-label">Qualification</label>
                  <input class="reg-input" type="text" name="qualification" value="<?= h($qualification) ?>" placeholder="Enter qualification">
                </div>

                <div class="reg-field">
                  <label class="reg-label">College Name</label>
                  <input class="reg-input" type="text" name="college_name" value="<?= h($college_name) ?>" placeholder="Enter college name">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Year of Passout</label>
                  <input class="reg-input" type="text" name="year_of_passout" value="<?= h($year_of_passout) ?>" placeholder="Enter year of passout">
                </div>

                <div class="full reg-field">
                  <label class="reg-label">Address</label>
                  <textarea class="reg-textarea" name="address" rows="3" placeholder="Enter complete address"><?= h($address) ?></textarea>
                </div>
              </div>
            </div>

            <div class="reg-group-card">
              <h4 class="reg-section-title">
                <i class="fas fa-users"></i> Parent / Emergency Information
              </h4>

              <div class="convert-grid">
                <div class="reg-field">
                  <label class="reg-label">Parent Name</label>
                  <input class="reg-input" type="text" name="parent_name" value="<?= h($parent_name) ?>" placeholder="Enter parent name">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Parent Phone</label>
                  <input class="reg-input" type="text" name="parent_phone" value="<?= h($parent_phone) ?>" placeholder="Enter parent phone">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Parent Occupation</label>
                  <input class="reg-input" type="text" name="parent_occupation" value="<?= h($parent_occupation) ?>" placeholder="Enter occupation">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Emergency Contact</label>
                  <input class="reg-input" type="text" name="emergency_contact" value="<?= h($emergency_contact) ?>" placeholder="Enter emergency contact">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Aadhaar No</label>
                  <input class="reg-input" type="text" name="aadhaar_no" value="<?= h($aadhaar_no) ?>" placeholder="Enter Aadhaar number">
                </div>
              </div>
            </div>

            <div class="reg-group-card">
              <h4 class="reg-section-title">
                <i class="fas fa-rupee-sign"></i> Fee Information
              </h4>

              <div class="convert-grid">
                <div class="reg-field">
                  <label class="reg-label">Total Fee</label>
                  <input class="reg-input fee-calc" type="number" step="0.01" name="total_fee" value="<?= h($total_fee) ?>">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Discount Amount</label>
                  <input class="reg-input fee-calc" type="number" step="0.01" name="discount_amount" value="<?= h($discount_amount) ?>">
                </div>

                <div class="reg-field">
                  <label class="reg-label">Final Fee</label>
                  <input class="reg-input" type="number" step="0.01" name="final_fee" value="<?= h($final_fee) ?>">
                  <div class="reg-inline-note">Auto-calculated from Total Fee - Discount Amount.</div>
                </div>
              </div>

              <div class="reg-fee-strip">
                <div class="reg-fee-box">
                  <div class="fee-label">Total Fee</div>
                  <div class="fee-value">₹ <span id="previewTotalFee"><?= h(number_format((float)$total_fee, 2, '.', '')) ?></span></div>
                </div>

                <div class="reg-fee-box">
                  <div class="fee-label">Discount</div>
                  <div class="fee-value">₹ <span id="previewDiscountFee"><?= h(number_format((float)$discount_amount, 2, '.', '')) ?></span></div>
                </div>

                <div class="reg-fee-box">
                  <div class="fee-label">Final Fee</div>
                  <div class="fee-value">₹ <span id="previewFinalFee"><?= h(number_format((float)$final_fee, 2, '.', '')) ?></span></div>
                </div>
              </div>
            </div>

            <div class="reg-group-card">
              <h4 class="reg-section-title">
                <i class="fas fa-sticky-note"></i> Notes & Remarks
              </h4>

              <div class="convert-grid">
                <div class="full reg-field">
                  <label class="reg-label">Registration Notes</label>
                  <textarea class="reg-textarea" name="notes" rows="3" placeholder="Enter registration notes"><?= h($notes) ?></textarea>
                </div>

                <div class="full reg-field">
                  <label class="reg-label">Profile Remarks</label>
                  <textarea class="reg-textarea" name="remarks" rows="3" placeholder="Enter profile remarks"><?= h($remarks) ?></textarea>
                </div>
              </div>
            </div>

            <div class="reg-actions">
              <button type="button" class="btn-reg btn-reg-primary" onclick="submitRegistrationForm('active')">
                <i class="fas fa-check-circle"></i> Confirm Registration
              </button>

              <button type="button" class="btn-reg btn-reg-warning" onclick="submitRegistrationForm('draft')">
                <i class="fas fa-save"></i> Save for Later
              </button>

              <a href="index.php?page=enquiries/followups&ui=list&tab=today" class="btn-reg btn-reg-light">
                <i class="fas fa-times-circle"></i> Cancel
              </a>
            </div>

          </form>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  const total = document.querySelector('input[name="total_fee"]');
  const discount = document.querySelector('input[name="discount_amount"]');
  const finalFee = document.querySelector('input[name="final_fee"]');

  const previewTotal = document.getElementById('previewTotalFee');
  const previewDiscount = document.getElementById('previewDiscountFee');
  const previewFinal = document.getElementById('previewFinalFee');

  function fmt(val){
    return parseFloat(val || 0).toFixed(2);
  }

  function calc(){
    if (!total || !discount || !finalFee) return;

    const t = parseFloat(total.value || 0);
    const d = parseFloat(discount.value || 0);

    let f = t - d;
    if (f < 0) f = 0;

    finalFee.value = f.toFixed(2);

    if (previewTotal) previewTotal.textContent = fmt(t);
    if (previewDiscount) previewDiscount.textContent = fmt(d);
    if (previewFinal) previewFinal.textContent = fmt(f);
  }

  function refreshPreviewOnly(){
    if (previewTotal) previewTotal.textContent = fmt(total ? total.value : 0);
    if (previewDiscount) previewDiscount.textContent = fmt(discount ? discount.value : 0);
    if (previewFinal) previewFinal.textContent = fmt(finalFee ? finalFee.value : 0);
  }

  [total, discount].forEach(el => {
    if (el) el.addEventListener('input', calc);
  });

  if (finalFee) {
    finalFee.addEventListener('input', refreshPreviewOnly);
  }

  refreshPreviewOnly();
})();

function submitRegistrationForm(status){
  document.getElementById('registration_status_input').value = status;

  Swal.fire({
    icon:'question',
    title: status === 'active' ? 'Confirm Registration?' : 'Save for Later?',
    text: status === 'active'
      ? 'This student will move to registration list.'
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
</script>