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

function regIsTenDigitPhone(?string $phone): bool {
    if ($phone === null) return false;
    return (bool)preg_match('/^\d{10}$/', trim($phone));
}

function regIsValidEmail(?string $email): bool {
    if ($email === null) return false;
    return (bool)filter_var(trim($email), FILTER_VALIDATE_EMAIL);
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
            $parent_email      = regToNull($_POST['parent_email'] ?? '');
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

            if (!regIsTenDigitPhone($phone)) {
                throw new Exception("Student phone number must be exactly 10 digits.");
            }

            if (!regIsValidEmail($email)) {
                throw new Exception("Please enter a valid student email address.");
            }

            if ($gender === null) {
                throw new Exception("Please select gender.");
            }

            if ($dob === null) {
                throw new Exception("Date of birth is required.");
            }

            if ($aadhaar_no === null || !preg_match('/^\d{12}$/', $aadhaar_no)) {
                throw new Exception("Aadhaar number must be exactly 12 digits.");
            }

            if ($program_name === null) {
                throw new Exception("Program name is required.");
            }

            if ($batch_name === null) {
                throw new Exception("Batch name is required.");
            }

            if ($qualification === null) {
                throw new Exception("Qualification is required.");
            }

            if ($college_name === null) {
                throw new Exception("College/University is required.");
            }

            if ($year_of_passout === null || !preg_match('/^\d{4}$/', $year_of_passout)) {
                throw new Exception("Year of passing must be a valid 4-digit year.");
            }

            if ($parent_email === null) {
                throw new Exception("Parent email is required.");
            }

            if (!regIsValidEmail($parent_email)) {
                throw new Exception("Please enter a valid parent email address.");
            }

            if ($total_fee <= 0) {
                throw new Exception("Total fee is required.");
            }

            // IMPORTANT FIX: preserve existing registration no on edit
            $previousRegistrationStatus = strtolower(trim((string) ($existingReg['registration_status'] ?? 'draft')));

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
                        parent_email=?,
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
                    $parent_email,
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
                        parent_email,
                        emergency_contact,
                        aadhaar_no,
                        remarks,
                        created_at,
                        updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
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
                    $parent_email,
                    $emergency_contact,
                    $aadhaar_no,
                    $remarks
                ]);
            }

            $pdo->commit();

            $mailWarning = '';
            if ($registration_status === 'active' && $previousRegistrationStatus !== 'active') {
                $registrationDate = $joined_on ?: date('Y-m-d');
                $studentDisplayName = trim((string) $student_name);
                $parentDisplayName = trim((string) $parent_name) !== '' ? trim((string) $parent_name) : 'Parent';
                $recipients = [
                    ['email' => $email, 'name' => $studentDisplayName],
                    ['email' => $parent_email, 'name' => $parentDisplayName],
                ];
                $subject = 'Registration completed for ' . ($studentDisplayName !== '' ? $studentDisplayName : 'student');
                $htmlBody = '
                    <p>Dear Student and Parent,</p>
                    <p>The registration has been completed successfully.</p>
                    <p><strong>Student:</strong> ' . h($studentDisplayName) . '<br>
                    <strong>Registration No:</strong> ' . h($registration_no) . '<br>
                    <strong>Program:</strong> ' . h((string) $program_name) . '<br>
                    <strong>Batch:</strong> ' . h((string) $batch_name) . '<br>
                    <strong>Joined On:</strong> ' . h($registrationDate) . '</p>
                    <p>Please keep this email for your records.</p>
                    <p>Regards,<br>' . h(APP_NAME) . '</p>';
                $textBody = "Dear Student and Parent,\n\n"
                    . "The registration has been completed successfully.\n"
                    . "Student: {$studentDisplayName}\n"
                    . "Registration No: {$registration_no}\n"
                    . "Program: {$program_name}\n"
                    . "Batch: {$batch_name}\n"
                    . "Joined On: {$registrationDate}\n\n"
                    . "Regards,\n" . APP_NAME;
                $mailError = null;
                if (!crmSendEmail($recipients, $subject, $htmlBody, $textBody, $mailError)) {
                    $mailWarning = ' Registration completed, but email delivery failed';
                    if ($mailError) {
                        $mailWarning .= ': ' . $mailError;
                    }
                    $mailWarning .= '.';
                }
            }

            if ($registration_status === 'active') {
                $success = "Registration confirmed successfully!" . $mailWarning;
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

$parent_email      = $profile['parent_email']
                    ?? ($enquiry['parent_email'] ?? '');

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
  --reg-card-shadow:0 8px 20px rgba(0,0,0,.05);
  --reg-radius:16px;
}

.reg-page{
  padding:6px 0 14px;
  font-size:14px;
}

.reg-hero{
  background:#fff;
  border:1px solid var(--reg-border);
  border-radius:16px;
  padding:16px 18px;
  margin-bottom:14px;
  box-shadow:var(--reg-card-shadow);
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
  font-size:1.35rem;
  font-weight:800;
  color:var(--reg-text);
  letter-spacing:.2px;
}

.reg-hero-subtitle{
  margin:6px 0 0;
  color:var(--reg-muted);
  font-size:.9rem;
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
  font-size:.8rem;
  font-weight:600;
  box-shadow:none;
}

.reg-side-stat{
  min-width:180px;
  background:#fff;
  border:1px solid #f2d8e4;
  border-radius:14px;
  padding:12px 14px;
  box-shadow:none;
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
  gap:14px;
  align-items:start;
}

.reg-card{
  background:#fff;
  border:1px solid var(--reg-border);
  border-radius:var(--reg-radius);
  margin-bottom:16px;
  box-shadow:var(--reg-card-shadow);
  overflow:hidden;
}

.reg-head{
  padding:12px 14px;
  font-weight:800;
  font-size:.95rem;
  color:#be185d;
  border-bottom:1px solid var(--reg-border);
  background:var(--reg-soft);
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}

.reg-head small{
  font-weight:600;
  color:#9d174d;
  font-size:.82rem;
}

/* Match registrations/add.php style header + step pills */
.reg-main-header{
  padding:14px 16px;
  background:var(--reg-soft);
  border-bottom:1px solid var(--reg-border);
}
.reg-main-header h2{
  margin:0;
  color:#be185d;
  font-weight:800;
  font-size:22px;
  line-height:1.15;
}
.reg-main-header p{
  margin:6px 0 0;
  color:#6b7280;
  font-size:14px;
}
.reg-progress{
  padding:12px;
  border-bottom:1px solid var(--reg-border);
  background:#fff;
}
.reg-steps-container{
  display:grid;
  grid-template-columns:repeat(6,minmax(0,1fr));
  gap:10px;
}
.reg-step-item{
  min-width:0;
  width:100%;
  border:1px solid #f3d8e5;
  border-radius:14px;
  background:#fff;
  padding:10px 12px;
  display:flex;
  align-items:center;
  justify-content:flex-start;
  gap:10px;
  text-align:left;
}
.reg-step-circle{
  width:24px;
  height:24px;
  min-width:24px;
  flex:0 0 24px;
  border-radius:999px;
  background:#ffe4ef;
  color:#c2185b;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:12px;
  font-weight:800;
}
.reg-step-label{
  font-size:12px;
  font-weight:700;
  color:#374151;
  line-height:1.1;
  white-space:nowrap;
}
.reg-step-item.active{
  border-color:#e91e63;
  background:#fff6fb;
}
.reg-step-item.active .reg-step-label{
  color:#be185d;
}
.reg-step-panel{
  display:none;
}
.reg-step-panel.active{
  display:block;
}
.reg-step-nav{
  display:flex;
  justify-content:space-between;
  gap:10px;
  margin-top:12px;
}
.reg-step-nav .right{
  margin-left:auto;
  display:flex;
  gap:10px;
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
  font-size:.95rem;
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
  border:1px solid #e5e7eb;
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

.reg-gender-group{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}

.reg-gender-option{
  position:relative;
  margin:0;
}

.reg-gender-option input{
  position:absolute;
  opacity:0;
  pointer-events:none;
}

.reg-gender-pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:10px 14px;
  border-radius:999px;
  border:1px solid #e5e7eb;
  background:#fff;
  color:#374151;
  font-size:13px;
  font-weight:700;
  cursor:pointer;
  transition:all .2s ease;
}

.reg-gender-option input:checked + .reg-gender-pill{
  border-color:#e91e63;
  background:#fff0f7;
  color:#be185d;
  box-shadow:0 0 0 3px rgba(233, 30, 99, .12);
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
  font-size:.86rem;
  transition:all .2s ease;
  text-decoration:none !important;
  display:inline-flex;
  align-items:center;
  gap:8px;
  cursor:pointer;
}

.btn-reg-primary{
  background:linear-gradient(135deg,#ff4d8d 0%,var(--reg-primary) 100%);
  color:#fff;
  box-shadow:0 10px 20px rgba(233,30,99,.24);
}

.btn-reg-primary:hover{
  color:#fff;
  transform:translateY(-1px);
}

.btn-reg-warning{
  background:#f3f4f6;
  color:#374151;
  border:1px solid #e5e7eb;
  box-shadow:none;
}

.btn-reg-warning:hover{
  color:#111827;
  background:#e5e7eb;
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
  .reg-steps-container{
    grid-template-columns:repeat(3,minmax(0,1fr));
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
  .reg-steps-container{
    grid-template-columns:repeat(2,minmax(0,1fr));
  }
  .reg-main-header h2{
    font-size:20px;
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

  <script>
  // SweetAlert safety shim (same behavior style as registrations/add.php)
  (function () {
    if (window.Swal && typeof window.Swal.fire === 'function') return;
    window.Swal = window.Swal || {};
    window.Swal.fire = function (opts) {
      opts = opts || {};
      return new Promise(function(resolve){
        var title = opts.title || 'Notice';
        var text = opts.text || '';
        if (opts.showCancelButton) {
          var ok = window.confirm(title + (text ? '\n' + text : ''));
          resolve({ isConfirmed: ok });
        } else {
          window.alert(title + (text ? '\n' + text : ''));
          resolve({ isConfirmed: true });
        }
      });
    };
  })();
  </script>

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
        <div class="reg-main-header">
          <h2>Registration Form</h2>
          <p>Fill in the details below to complete the registration</p>
        </div>
        <div class="reg-progress">
          <div class="reg-steps-container">
            <div class="reg-step-item active" data-step="1"><div class="reg-step-circle">1</div><div class="reg-step-label">Basic</div></div>
            <div class="reg-step-item" data-step="2"><div class="reg-step-circle">2</div><div class="reg-step-label">Personal</div></div>
            <div class="reg-step-item" data-step="3"><div class="reg-step-circle">3</div><div class="reg-step-label">Academic</div></div>
            <div class="reg-step-item" data-step="4"><div class="reg-step-circle">4</div><div class="reg-step-label">Parent</div></div>
            <div class="reg-step-item" data-step="5"><div class="reg-step-circle">5</div><div class="reg-step-label">Fee</div></div>
            <div class="reg-step-item" data-step="6"><div class="reg-step-circle">6</div><div class="reg-step-label">Notes</div></div>
          </div>
        </div>

        <div class="reg-body">
          <form method="POST" id="registrationForm">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="save_registration" value="1">
            <input type="hidden" name="reg_id" value="<?= (int)($registration['id'] ?? 0) ?>">
            <input type="hidden" name="enquiry_id" value="<?= (int)$enquiryId ?>">
            <input type="hidden" name="reg_type" value="<?= h($regType) ?>">
            <input type="hidden" name="registration_status" id="registration_status_input" value="draft">

            <div class="reg-group-card reg-step-panel active" id="regStep1" data-step="1">
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
              <div class="reg-step-nav">
                <span></span>
                <div class="right">
                  <button type="button" class="btn-reg btn-reg-primary" onclick="goRegStep(2)">Next <i class="fas fa-arrow-right"></i></button>
                </div>
              </div>
            </div>

            <div class="reg-group-card reg-step-panel" id="regStep2" data-step="2">
              <h4 class="reg-section-title">
                <i class="fas fa-user"></i> Personal Information
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
                  <div class="reg-gender-group">
                    <label class="reg-gender-option">
                      <input type="radio" name="gender" value="male" <?= strtolower((string)$gender)==='male'?'checked':''; ?>>
                      <span class="reg-gender-pill"><i class="fas fa-mars"></i> Male</span>
                    </label>
                    <label class="reg-gender-option">
                      <input type="radio" name="gender" value="female" <?= strtolower((string)$gender)==='female'?'checked':''; ?>>
                      <span class="reg-gender-pill"><i class="fas fa-venus"></i> Female</span>
                    </label>
                    <label class="reg-gender-option">
                      <input type="radio" name="gender" value="other" <?= strtolower((string)$gender)==='other'?'checked':''; ?>>
                      <span class="reg-gender-pill"><i class="fas fa-genderless"></i> Other</span>
                    </label>
                  </div>
                </div>

                <div class="reg-field">
                  <label class="reg-label">DOB</label>
                  <input class="reg-input" type="date" name="dob" value="<?= h($dob) ?>">
                </div>

                <div class="full reg-field">
                  <label class="reg-label">Address</label>
                  <textarea class="reg-textarea" name="address" rows="3" placeholder="Enter complete address"><?= h($address) ?></textarea>
                </div>
              </div>
              <div class="reg-step-nav">
                <button type="button" class="btn-reg btn-reg-light" onclick="goRegStep(1)"><i class="fas fa-arrow-left"></i> Back</button>
                <div class="right">
                  <button type="button" class="btn-reg btn-reg-primary" onclick="validateStep2AndNext()">Next <i class="fas fa-arrow-right"></i></button>
                </div>
              </div>
            </div>

            <div class="reg-group-card reg-step-panel" id="regStep3" data-step="3">
              <h4 class="reg-section-title">
                <i class="fas fa-user-graduate"></i> Academic Information
              </h4>

              <div class="convert-grid">
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
              </div>
              <div class="reg-step-nav">
                <button type="button" class="btn-reg btn-reg-light" onclick="goRegStep(2)"><i class="fas fa-arrow-left"></i> Back</button>
                <div class="right">
                  <button type="button" class="btn-reg btn-reg-primary" onclick="validateStep3AndNext()">Next <i class="fas fa-arrow-right"></i></button>
                </div>
              </div>
            </div>

            <div class="reg-group-card reg-step-panel" id="regStep4" data-step="4">
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
                  <label class="reg-label">Parent Email ID <span style="color:#e91e63;">*</span></label>
                  <input class="reg-input" type="email" name="parent_email" value="<?= h($parent_email) ?>" placeholder="Enter parent email, e.g. parent@gmail.com">
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
              <div class="reg-step-nav">
                <button type="button" class="btn-reg btn-reg-light" onclick="goRegStep(3)"><i class="fas fa-arrow-left"></i> Back</button>
                <div class="right">
                  <button type="button" class="btn-reg btn-reg-primary" onclick="validateStep4AndNext()">Next <i class="fas fa-arrow-right"></i></button>
                </div>
              </div>
            </div>

            <div class="reg-group-card reg-step-panel" id="regStep5" data-step="5">
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
                  <input class="reg-input" type="number" step="0.01" name="final_fee" value="<?= h($final_fee) ?>" readonly>
                  <div class="reg-inline-note">Auto-calculated from Total Fee - Discount Amount.</div>
                </div>
              </div>

              <div class="reg-fee-strip">
                <div class="reg-fee-box">
                  <div class="fee-label">Total Fee</div>
                  <div class="fee-value">? <span id="previewTotalFee"><?= h(number_format((float)$total_fee, 2, '.', '')) ?></span></div>
                </div>

                <div class="reg-fee-box">
                  <div class="fee-label">Discount</div>
                  <div class="fee-value">? <span id="previewDiscountFee"><?= h(number_format((float)$discount_amount, 2, '.', '')) ?></span></div>
                </div>

                <div class="reg-fee-box">
                  <div class="fee-label">Final Fee</div>
                  <div class="fee-value">? <span id="previewFinalFee"><?= h(number_format((float)$final_fee, 2, '.', '')) ?></span></div>
                </div>
              </div>
              <div class="reg-step-nav">
                <button type="button" class="btn-reg btn-reg-light" onclick="goRegStep(4)"><i class="fas fa-arrow-left"></i> Back</button>
                <div class="right">
                  <button type="button" class="btn-reg btn-reg-primary" onclick="validateStep5AndNext()">Next <i class="fas fa-arrow-right"></i></button>
                </div>
              </div>
            </div>

            <div class="reg-group-card reg-step-panel" id="regStep6" data-step="6">
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
              <div class="reg-actions">
                <button type="button" class="btn-reg btn-reg-light" onclick="goRegStep(5)">
                  <i class="fas fa-arrow-left"></i> Back
                </button>

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
            </div>

          </form>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
  let currentRegStep = 1;
  const totalRegSteps = 6;

  function setRegStep(step){
    const target = Number(step);
    if (!target || target < 1 || target > totalRegSteps) return;

    currentRegStep = target;

    document.querySelectorAll('.reg-step-panel').forEach(panel => {
      panel.classList.remove('active');
    });
    document.querySelectorAll('.reg-step-item').forEach(item => {
      item.classList.remove('active');
    });

    document.getElementById('regStep' + target)?.classList.add('active');
    document.querySelector('.reg-step-item[data-step="' + target + '"]')?.classList.add('active');
  }

  window.goRegStep = function(step){
    setRegStep(step);
  };

  document.querySelectorAll('.reg-step-item[data-step]').forEach(item => {
    item.addEventListener('click', function(){
      const step = Number(this.getAttribute('data-step'));
      if (!step) return;
      if (step <= currentRegStep) {
        setRegStep(step);
      } else {
        Swal.fire({
          icon: 'warning',
          title: 'Cannot Skip Steps',
          text: 'Please complete the current step first.',
          confirmButtonColor: '#e91e63'
        });
      }
    });
  });

  setRegStep(1);

  // Input sanitizers similar to registrations/add.php
  const phoneFields = [
    document.querySelector('input[name="phone"]'),
    document.querySelector('input[name="parent_phone"]'),
    document.querySelector('input[name="emergency_contact"]')
  ].filter(Boolean);

  phoneFields.forEach(function(el){
    el.addEventListener('input', function(){
      this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
    });
  });

  const aadhaarField = document.querySelector('input[name="aadhaar_no"]');
  if (aadhaarField) {
    aadhaarField.addEventListener('input', function(){
      this.value = this.value.replace(/[^0-9]/g, '').substring(0, 12);
    });
  }

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

function validateStep2AndNext() {
  const name = (document.querySelector('input[name="student_name"]')?.value || '').trim();
  const phone = (document.querySelector('input[name="phone"]')?.value || '').trim();
  const email = (document.querySelector('input[name="email"]')?.value || '').trim();
  const gender = document.querySelector('input[name="gender"]:checked')?.value || '';
  const dob = (document.querySelector('input[name="dob"]')?.value || '').trim();
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (!name) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter student name', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!phone || phone.length !== 10 || !/^\d+$/.test(phone)) {
    Swal.fire({ icon:'warning', title:'Invalid Phone', text:'Please enter a valid 10-digit phone number', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!email || !emailRegex.test(email)) {
    Swal.fire({ icon:'warning', title:'Invalid Email', text:'Please enter a valid email address', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!gender) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please select gender', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!dob) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please select date of birth', confirmButtonColor:'#e91e63' });
    return false;
  }
  goRegStep(3);
  return true;
}

function validateStep3AndNext() {
  const programName = (document.querySelector('input[name="program_name"]')?.value || '').trim();
  const batchName = (document.querySelector('input[name="batch_name"]')?.value || '').trim();
  const qualification = (document.querySelector('input[name="qualification"]')?.value || '').trim();
  const collegeName = (document.querySelector('input[name="college_name"]')?.value || '').trim();
  const yearOfPassout = (document.querySelector('input[name="year_of_passout"]')?.value || '').trim();

  if (!programName) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter program name', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!batchName) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter batch name', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!qualification) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter qualification', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!collegeName) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter college or university name', confirmButtonColor:'#e91e63' });
    return false;
  }
  if (!yearOfPassout || !/^\d{4}$/.test(yearOfPassout)) {
    Swal.fire({ icon:'warning', title:'Invalid Year', text:'Please enter a valid 4-digit year of passing', confirmButtonColor:'#e91e63' });
    return false;
  }

  goRegStep(4);
  return true;
}

function validateStep4AndNext() {
  const parentEmail = (document.querySelector('input[name="parent_email"]')?.value || '').trim();
  const aadhaar = (document.querySelector('input[name="aadhaar_no"]')?.value || '').trim();
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (!parentEmail) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter parent email address', confirmButtonColor:'#e91e63' });
    return false;
  }

  if (!emailRegex.test(parentEmail)) {
    Swal.fire({ icon:'warning', title:'Invalid Email', text:'Please enter a valid parent email address', confirmButtonColor:'#e91e63' });
    return false;
  }

  if (!aadhaar || !/^\d{12}$/.test(aadhaar)) {
    Swal.fire({ icon:'warning', title:'Invalid Aadhaar', text:'Aadhaar number must be exactly 12 digits.', confirmButtonColor:'#e91e63' });
    return false;
  }

  goRegStep(5);
  return true;
}

function validateStep5AndNext() {
  const totalFee = (document.querySelector('input[name="total_fee"]')?.value || '').trim();
  const totalFeeNumber = parseFloat(totalFee);

  if (!totalFee || isNaN(totalFeeNumber) || totalFeeNumber <= 0) {
    Swal.fire({ icon:'warning', title:'Invalid Fee', text:'Please enter a valid total fee amount', confirmButtonColor:'#e91e63' });
    return false;
  }

  goRegStep(6);
  return true;
}

function validateCoreBeforeSubmit() {
  const programName = (document.querySelector('input[name="program_name"]')?.value || '').trim();
  const batchName = (document.querySelector('input[name="batch_name"]')?.value || '').trim();
  const qualification = (document.querySelector('input[name="qualification"]')?.value || '').trim();
  const collegeName = (document.querySelector('input[name="college_name"]')?.value || '').trim();
  const yearOfPassout = (document.querySelector('input[name="year_of_passout"]')?.value || '').trim();
  const name = (document.querySelector('input[name="student_name"]')?.value || '').trim();
  const phone = (document.querySelector('input[name="phone"]')?.value || '').trim();
  const email = (document.querySelector('input[name="email"]')?.value || '').trim();
  const gender = document.querySelector('input[name="gender"]:checked')?.value || '';
  const dob = (document.querySelector('input[name="dob"]')?.value || '').trim();
  const parentEmail = (document.querySelector('input[name="parent_email"]')?.value || '').trim();
  const aadhaar = (document.querySelector('input[name="aadhaar_no"]')?.value || '').trim();
  const totalFee = (document.querySelector('input[name="total_fee"]')?.value || '').trim();
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  if (!programName) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter program name', confirmButtonColor:'#e91e63' });
    goRegStep(1);
    return false;
  }
  if (!batchName) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter batch name', confirmButtonColor:'#e91e63' });
    goRegStep(1);
    return false;
  }

  if (!name) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter student name', confirmButtonColor:'#e91e63' });
    goRegStep(2);
    return false;
  }
  if (!phone || phone.length !== 10 || !/^\d+$/.test(phone)) {
    Swal.fire({ icon:'warning', title:'Invalid Phone', text:'Please enter a valid 10-digit phone number', confirmButtonColor:'#e91e63' });
    goRegStep(2);
    return false;
  }
  if (!email || !emailRegex.test(email)) {
    Swal.fire({ icon:'warning', title:'Invalid Email', text:'Please enter a valid email address', confirmButtonColor:'#e91e63' });
    goRegStep(2);
    return false;
  }
  if (!gender) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please select gender', confirmButtonColor:'#e91e63' });
    goRegStep(2);
    return false;
  }
  if (!dob) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please select date of birth', confirmButtonColor:'#e91e63' });
    goRegStep(2);
    return false;
  }
  if (!parentEmail) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter parent email address', confirmButtonColor:'#e91e63' });
    goRegStep(4);
    return false;
  }
  if (!emailRegex.test(parentEmail)) {
    Swal.fire({ icon:'warning', title:'Invalid Email', text:'Please enter a valid parent email address', confirmButtonColor:'#e91e63' });
    goRegStep(4);
    return false;
  }
  if (!aadhaar || !/^\d{12}$/.test(aadhaar)) {
    Swal.fire({ icon:'warning', title:'Invalid Aadhaar', text:'Aadhaar number must be exactly 12 digits.', confirmButtonColor:'#e91e63' });
    goRegStep(4);
    return false;
  }
  if (!qualification) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter qualification', confirmButtonColor:'#e91e63' });
    goRegStep(3);
    return false;
  }
  if (!collegeName) {
    Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter college or university name', confirmButtonColor:'#e91e63' });
    goRegStep(3);
    return false;
  }
  if (!yearOfPassout || !/^\d{4}$/.test(yearOfPassout)) {
    Swal.fire({ icon:'warning', title:'Invalid Year', text:'Please enter a valid 4-digit year of passing', confirmButtonColor:'#e91e63' });
    goRegStep(3);
    return false;
  }
  if (!totalFee || isNaN(parseFloat(totalFee)) || parseFloat(totalFee) <= 0) {
    Swal.fire({ icon:'warning', title:'Invalid Fee', text:'Please enter a valid total fee amount', confirmButtonColor:'#e91e63' });
    goRegStep(5);
    return false;
  }

  return true;
}

function submitRegistrationForm(status){
  if (!validateCoreBeforeSubmit()) return;
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
