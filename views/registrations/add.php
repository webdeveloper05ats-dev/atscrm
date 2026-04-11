<?php
// =====================================
// Registrations - Add Direct / Walk-in
// Slug: registrations/add
// File: views/registrations/add.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

requireView('registrations/add');

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

function regIsTenDigitPhone(?string $phone): bool {
    if ($phone === null) return false;
    return (bool)preg_match('/^\d{10}$/', trim($phone));
}

function regIsValidEmail(?string $email): bool {
    if ($email === null) return false;
    return (bool)filter_var(trim($email), FILTER_VALIDATE_EMAIL);
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
                if (window.Swal && Swal.fire) {
                  Swal.fire({
                    icon:'success',
                    title:'Success',
                    text:'<?= addslashes($success) ?>',
                    confirmButtonColor:'#e91e63'
                  }).then(()=> window.location.href = "index.php?page=registrations/drafts");
                } else {
                  alert('<?= addslashes($success) ?>');
                  window.location.href = "index.php?page=registrations/drafts";
                }
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
            $previousRegistrationStatus = 'draft';

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

                $previousRegistrationStatus = strtolower(trim((string) ($existingReg['registration_status'] ?? 'draft')));
            }

            $registration_no      = regToNull($_POST['registration_no'] ?? '');
            $joined_on            = regDateOrNull($_POST['joined_on'] ?? '');
            $reg_type             = regToNull($_POST['reg_type'] ?? '');
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
            $parent_email         = regToNull($_POST['parent_email'] ?? '');
            $emergency_contact    = regToNull($_POST['emergency_contact'] ?? '');
            $aadhaar_no           = regToNull($_POST['aadhaar_no'] ?? '');
            $remarks              = regToNull($_POST['remarks'] ?? '');

            $total_fee            = regDec($_POST['total_fee'] ?? 0);
            $discount_amount      = regDec($_POST['discount_amount'] ?? 0);
            $final_fee            = regDec($_POST['final_fee'] ?? 0);

            if ($reg_type === null || !in_array($reg_type, ['course','internship','workshop'], true)) {
                throw new Exception("Invalid registration type.");
            }

            if (!in_array($registration_status, ['draft','active','completed','cancelled'], true)) {
                $registration_status = 'draft';
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

            if ($assigned_to <= 0) {
                throw new Exception("Please select Front Office owner.");
            }

            if ($total_fee <= 0) {
                throw new Exception("Total fee is required.");
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
            $isNewRegistration = $regIdPost <= 0;
            $isFreshActivation = $registration_status === 'active' && $previousRegistrationStatus !== 'active';

            if ($isNewRegistration || $isFreshActivation || $registration_status === 'active') {
                $registrationDate = $joined_on ?: date('Y-m-d');
                $studentDisplayName = trim((string) $student_name);
                $parentDisplayName = trim((string) $parent_name) !== '' ? trim((string) $parent_name) : 'Parent';
                $recipients = [
                    ['email' => $email, 'name' => $studentDisplayName],
                    ['email' => $parent_email, 'name' => $parentDisplayName],
                ];
                $statusLabel = $registration_status === 'active' ? 'confirmed' : 'saved';
                $statusCopy = $registration_status === 'active'
                    ? 'The registration has been completed successfully.'
                    : 'A new registration has been created successfully and is currently saved as draft.';
                $subject = ($registration_status === 'active' ? 'Registration completed for ' : 'New registration created for ')
                    . ($studentDisplayName !== '' ? $studentDisplayName : 'student');
                $htmlBody = '
                    <p>Dear Student and Parent,</p>
                    <p>' . h($statusCopy) . '</p>
                    <p><strong>Student:</strong> ' . h($studentDisplayName) . '<br>
                    <strong>Registration No:</strong> ' . h($registration_no) . '<br>
                    <strong>Status:</strong> ' . h(ucfirst($statusLabel)) . '<br>
                    <strong>Program:</strong> ' . h((string) $program_name) . '<br>
                    <strong>Batch:</strong> ' . h((string) $batch_name) . '<br>
                    <strong>Joined On:</strong> ' . h($registrationDate) . '</p>
                    <p>Please keep this email for your records.</p>
                    <p>Regards,<br>' . h(APP_NAME) . '</p>';
                $textBody = "Dear Student and Parent,\n\n"
                    . $statusCopy . "\n"
                    . "Student: {$studentDisplayName}\n"
                    . "Registration No: {$registration_no}\n"
                    . "Status: " . ucfirst($statusLabel) . "\n"
                    . "Program: {$program_name}\n"
                    . "Batch: {$batch_name}\n"
                    . "Joined On: {$registrationDate}\n\n"
                    . "Regards,\n" . APP_NAME;
                $mailError = null;
                crmLogMailEvent('Attempting registration mail from manual registration for registration_id=' . (int) $realRegId . ', status=' . $registration_status . ', student_email=' . (string) $email . ', parent_email=' . (string) $parent_email);
                if (!crmSendEmail($recipients, $subject, $htmlBody, $textBody, $mailError)) {
                    $mailWarning = ' Registration saved, but email delivery failed';
                    if ($mailError) {
                        $mailWarning .= ': ' . $mailError;
                    }
                    $mailWarning .= '.';
                }
            }

            if ($registration_status === 'active') {
                $success = "Registration confirmed successfully!" . $mailWarning;
                ?>
                <script>
                if (window.Swal && Swal.fire) {
                  Swal.fire({
                    icon:'success',
                    title:'Success',
                    text:'<?= addslashes($success) ?>',
                    confirmButtonColor:'#e91e63'
                  }).then(()=> window.location.href = "index.php?page=registrations/list");
                } else {
                  alert('<?= addslashes($success) ?>');
                  window.location.href = "index.php?page=registrations/list";
                }
                </script>
                <?php
                return;
            } else {
                $success = "Registration saved as draft successfully!";
                ?>
                <script>
                if (window.Swal && Swal.fire) {
                  Swal.fire({
                    icon:'success',
                    title:'Success',
                    text:'<?= addslashes($success) ?>',
                    confirmButtonColor:'#e91e63'
                  }).then(()=> window.location.href = "index.php?page=registrations/drafts");
                } else {
                  alert('<?= addslashes($success) ?>');
                  window.location.href = "index.php?page=registrations/drafts";
                }
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
$reg_type            = $registration['reg_type'] ?? '';
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
$parent_email        = $profile['parent_email'] ?? '';
$emergency_contact   = $profile['emergency_contact'] ?? '';
$aadhaar_no          = $profile['aadhaar_no'] ?? '';
$remarks             = $profile['remarks'] ?? '';

$total_fee           = $registration['total_fee'] ?? '0.00';
$discount_amount     = $registration['discount_amount'] ?? '0.00';
$final_fee           = $registration['final_fee'] ?? '0.00';

$isEditMode = !empty($registration['id']);
?>

<!-- Professional UI - White, Pink & Light Pink Theme -->

<!-- UI Override: Match Enquiry/Add Wizard Look (Logic unchanged) -->

<!-- Main Application - White & Pink Theme -->
<div class="reg-portal">
    
    <!-- Header -->
    <div class="reg-header">
        <div class="reg-header-left">
            <div class="reg-header-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="reg-header-content">
                <h1><?= $isEditMode ? 'Edit Registration' : 'New Student Registration' ?></h1>
                <p>Complete the registration in 6 simple steps</p>
            </div>
        </div>
        <div class="reg-header-badge">
            <i class="fas fa-store-alt"></i>
            Direct / Walk-in � <?= h($registration_no) ?>
        </div>
    </div>
    
    <script>
    // SweetAlert safety shim for this page
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
    
    <!-- Main Grid -->
    <div class="reg-grid">
        
        <!-- Sidebar -->
        <aside class="reg-sidebar">
            
            <!-- Registration Summary Card -->
            <div class="reg-card">
                <div class="reg-card-header">
                    <div class="reg-card-header-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div>
                        <h3>Registration Summary</h3>
                        <p>Key information</p>
                    </div>
                </div>
                <div class="reg-card-content">
                    <div class="reg-summary-items">
                        <div class="reg-summary-item">
                            <div class="reg-summary-icon">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div class="reg-summary-details">
                                <div class="reg-summary-label">Registration No.</div>
                                <div class="reg-summary-value"><?= h($registration_no) ?></div>
                            </div>
                        </div>
                        
                        <div class="reg-summary-item">
                            <div class="reg-summary-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="reg-summary-details">
                                <div class="reg-summary-label">Joined On</div>
                                <div class="reg-summary-value"><?= date('d M Y', strtotime($joined_on)) ?></div>
                            </div>
                        </div>
                        
                        <div class="reg-summary-item">
                            <div class="reg-summary-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="reg-summary-details">
                                <div class="reg-summary-label">Registration ID</div>
                                <div class="reg-summary-value">#<?= (int)($registration['id'] ?? 'New') ?></div>
                            </div>
                        </div>
                        
                        <div class="reg-summary-item">
                            <div class="reg-summary-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="reg-summary-details">
                                <div class="reg-summary-label">Source Type</div>
                                <div class="reg-summary-value">Direct / Walk-in</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="reg-note-card">
                        <strong>Note:</strong> Payment collection can be managed from the Registration List page.
                    </div>
                </div>
            </div>

        </aside>
        
        <!-- Main Form -->
        <main>
            <!-- Form Card -->
            <div class="reg-main-card">
                <div class="reg-main-header">
                    <h2>Registration Form</h2>
                    <p>Fill in the details below to complete the registration</p>
                </div>

                <!-- Progress Steps -->
                <div class="reg-progress">
                    <div class="reg-steps-container">
                        <div class="reg-step-item active" data-step="1">
                            <div class="reg-step-circle">1</div>
                            <div class="reg-step-label">Basic</div>
                        </div>
                        <div class="reg-step-item" data-step="2">
                            <div class="reg-step-circle">2</div>
                            <div class="reg-step-label">Personal</div>
                        </div>
                        <div class="reg-step-item" data-step="3">
                            <div class="reg-step-circle">3</div>
                            <div class="reg-step-label">Academic</div>
                        </div>
                        <div class="reg-step-item" data-step="4">
                            <div class="reg-step-circle">4</div>
                            <div class="reg-step-label">Parent</div>
                        </div>
                        <div class="reg-step-item" data-step="5">
                            <div class="reg-step-circle">5</div>
                            <div class="reg-step-label">Fee</div>
                        </div>
                        <div class="reg-step-item" data-step="6">
                            <div class="reg-step-circle">6</div>
                            <div class="reg-step-label">Notes</div>
                        </div>
                    </div>
                </div>
                
                <div class="reg-main-body">
                    <form method="POST" id="registrationForm">
                        <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                        <input type="hidden" name="save_registration" value="1">
                        <input type="hidden" name="reg_id" value="<?= (int)($registration['id'] ?? 0) ?>">
                        <input type="hidden" name="registration_status" id="registration_status_input" value="<?= h($registration_status) ?>">
                        
                        <!-- Step 1: Basic Registration Details -->
                        <div class="reg-step-container active" id="step1">
                            <div class="reg-section">
                                <div class="reg-section-header">
                                    <div class="reg-section-step">01</div>
                                    <div class="reg-section-title">
                                        <h4>Basic Registration Details</h4>
                                        <p>Core registration information</p>
                                    </div>
                                </div>
                                <div class="reg-section-content">
                                    <div class="reg-form-grid">
                                        <div class="reg-field">
                                            <label class="reg-field-label">Registration No.</label>
                                            <input class="reg-field-input" type="text" name="registration_no" value="<?= h($registration_no) ?>" readonly>
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Joined On</label>
                                            <input class="reg-field-input" type="date" name="joined_on" value="<?= h($joined_on) ?>">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Registration Type <span class="required">*</span></label>
                                            <select class="reg-field-select" name="reg_type" id="reg_type" required>
                                                <option value="">-- Select Registration Type --</option>
                                                <option value="course" <?= $reg_type==='course'?'selected':''; ?>>Course</option>
                                                <option value="internship" <?= $reg_type==='internship'?'selected':''; ?>>Internship</option>
                                                <option value="workshop" <?= $reg_type==='workshop'?'selected':''; ?>>Workshop</option>
                                            </select>
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Front Office Owner <span class="required">*</span></label>
                                            <select class="reg-field-select" name="assigned_to" id="assigned_to" required>
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
                            
                            <div class="reg-nav">
                                <div></div>
                                <button type="button" class="reg-btn reg-btn-primary" onclick="validateAndNext(1, 2)">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 2: Student Personal Details -->
                        <div class="reg-step-container" id="step2">
                            <div class="reg-section">
                                <div class="reg-section-header">
                                    <div class="reg-section-step">02</div>
                                    <div class="reg-section-title">
                                        <h4>Personal Details</h4>
                                        <p>Student's personal information</p>
                                    </div>
                                </div>
                                <div class="reg-section-content">
                                    <div class="reg-form-grid">
                                        <div class="reg-field">
                                            <label class="reg-field-label">Full Name <span class="required">*</span></label>
                                            <input class="reg-field-input capitalize-input" type="text" name="student_name" id="student_name" value="<?= h($student_name) ?>" required placeholder="Enter student's full name" oninput="capitalizeFirstLetter(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Phone <span class="required">*</span></label>
                                            <input class="reg-field-input" type="tel" name="phone" id="phone" value="<?= h($phone) ?>" required placeholder="10-digit mobile number" maxlength="10" oninput="validatePhone(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Email <span class="required">*</span></label>
                                            <input class="reg-field-input" type="email" name="email" id="email" value="<?= h($email) ?>" required placeholder="student@example.com" onblur="validateEmail(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Gender <span class="required">*</span></label>
                                            <div class="reg-gender-group">
                                                <label class="reg-gender-option">
                                                    <input type="radio" name="gender" value="male" <?= $gender==='male'?'checked':''; ?>>
                                                    <span class="reg-gender-pill"><i class="fas fa-mars"></i> Male</span>
                                                </label>
                                                <label class="reg-gender-option">
                                                    <input type="radio" name="gender" value="female" <?= $gender==='female'?'checked':''; ?>>
                                                    <span class="reg-gender-pill"><i class="fas fa-venus"></i> Female</span>
                                                </label>
                                                <label class="reg-gender-option">
                                                    <input type="radio" name="gender" value="other" <?= $gender==='other'?'checked':''; ?>>
                                                    <span class="reg-gender-pill"><i class="fas fa-genderless"></i> Other</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Date of Birth <span class="required">*</span></label>
                                            <input class="reg-field-input" type="date" name="dob" id="dob" value="<?= h($dob) ?>">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Aadhaar Number <span class="required">*</span></label>
                                            <input class="reg-field-input" type="text" name="aadhaar_no" value="<?= h($aadhaar_no) ?>" placeholder="12-digit Aadhaar number" maxlength="12" oninput="validateAadhaar(this)">
                                        </div>
                                        
                                        <div class="reg-field full-width">
                                            <label class="reg-field-label">Address</label>
                                            <textarea class="reg-field-textarea capitalize-text" name="address" rows="3" placeholder="Enter complete address" oninput="capitalizeSentences(this)"><?= h($address) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="reg-nav">
                                <button type="button" class="reg-btn reg-btn-outline" onclick="prevStep(1)">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="reg-btn reg-btn-primary" onclick="validateStep2()">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 3: Academic & Program Details -->
                        <div class="reg-step-container" id="step3">
                            <div class="reg-section">
                                <div class="reg-section-header">
                                    <div class="reg-section-step">03</div>
                                    <div class="reg-section-title">
                                        <h4>Academic Information</h4>
                                        <p>Program and education details</p>
                                    </div>
                                </div>
                                <div class="reg-section-content">
                                    <div class="reg-form-grid">
                                        <div class="reg-field">
                                            <label class="reg-field-label">Program Name <span class="required">*</span></label>
                                            <input class="reg-field-input capitalize-input" type="text" name="program_name" id="program_name" value="<?= h($program_name) ?>" placeholder="e.g., Full Stack Development" oninput="capitalizeFirstLetter(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Batch Name <span class="required">*</span></label>
                                            <input class="reg-field-input" type="text" name="batch_name" id="batch_name" value="<?= h($batch_name) ?>" placeholder="e.g., Batch 2024-A">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Qualification <span class="required">*</span></label>
                                            <input class="reg-field-input capitalize-input" type="text" name="qualification" id="qualification" value="<?= h($qualification) ?>" placeholder="e.g., B.Tech Computer Science" oninput="capitalizeFirstLetter(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">College/University <span class="required">*</span></label>
                                            <input class="reg-field-input capitalize-input" type="text" name="college_name" id="college_name" value="<?= h($college_name) ?>" placeholder="Enter college name" oninput="capitalizeFirstLetter(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Year of Passing <span class="required">*</span></label>
                                            <input class="reg-field-input" type="text" name="year_of_passout" id="year_of_passout" value="<?= h($year_of_passout) ?>" placeholder="e.g., 2024" maxlength="4" oninput="validateYear(this)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="reg-nav">
                                <button type="button" class="reg-btn reg-btn-outline" onclick="prevStep(2)">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="reg-btn reg-btn-primary" onclick="validateStep3()">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 4: Parent & Emergency Details -->
                        <div class="reg-step-container" id="step4">
                            <div class="reg-section">
                                <div class="reg-section-header">
                                    <div class="reg-section-step">04</div>
                                    <div class="reg-section-title">
                                        <h4>Parent & Emergency</h4>
                                        <p>Secondary contact information</p>
                                    </div>
                                </div>
                                <div class="reg-section-content">
                                    <div class="reg-form-grid">
                                        <div class="reg-field">
                                            <label class="reg-field-label">Parent/Guardian Name</label>
                                            <input class="reg-field-input capitalize-input" type="text" name="parent_name" value="<?= h($parent_name) ?>" placeholder="Enter parent name" oninput="capitalizeFirstLetter(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Parent Phone</label>
                                            <input class="reg-field-input" type="tel" name="parent_phone" value="<?= h($parent_phone) ?>" placeholder="10-digit mobile number" maxlength="10" oninput="validatePhone(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Parent Occupation</label>
                                            <input class="reg-field-input capitalize-input" type="text" name="parent_occupation" value="<?= h($parent_occupation) ?>" placeholder="e.g., Business, Teacher" oninput="capitalizeFirstLetter(this)">
                                        </div>

                                        <div class="reg-field">
                                            <label class="reg-field-label">Parent Email ID <span class="required">*</span></label>
                                            <input class="reg-field-input" type="email" name="parent_email" value="<?= h($parent_email) ?>" placeholder="Enter parent email, e.g. parent@gmail.com" onblur="validateEmail(this)">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Emergency Contact</label>
                                            <input class="reg-field-input" type="tel" name="emergency_contact" value="<?= h($emergency_contact) ?>" placeholder="Alternate contact number" maxlength="10" oninput="validatePhone(this)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="reg-nav">
                                <button type="button" class="reg-btn reg-btn-outline" onclick="prevStep(3)">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="reg-btn reg-btn-primary" onclick="validateStep4()">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 5: Fee Details -->
                        <div class="reg-step-container" id="step5">
                            <div class="reg-section">
                                <div class="reg-section-header">
                                    <div class="reg-section-step">05</div>
                                    <div class="reg-section-title">
                                        <h4>Fee Structure</h4>
                                        <p>Program fee details</p>
                                    </div>
                                </div>
                                <div class="reg-section-content">
                                    <div class="reg-form-grid">
                                        <div class="reg-field">
                                            <label class="reg-field-label">Total Fee (?)</label>
                                            <input class="reg-field-input fee-calc js-decimal-input" type="text" inputmode="decimal" name="total_fee" id="total_fee" value="<?= h($total_fee) ?>" placeholder="0.00">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Discount (?)</label>
                                            <input class="reg-field-input fee-calc js-decimal-input" type="text" inputmode="decimal" name="discount_amount" value="<?= h($discount_amount) ?>" placeholder="0.00">
                                        </div>
                                        
                                        <div class="reg-field">
                                            <label class="reg-field-label">Final Fee (?)</label>
                                            <input class="reg-field-input" type="text" inputmode="decimal" name="final_fee" id="final_fee" value="<?= h($final_fee) ?>" placeholder="0.00" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="reg-nav">
                                <button type="button" class="reg-btn reg-btn-outline" onclick="prevStep(4)">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <button type="button" class="reg-btn reg-btn-primary" onclick="validateStep5()">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 6: Additional Notes -->
                        <div class="reg-step-container" id="step6">
                            <div class="reg-section">
                                <div class="reg-section-header">
                                    <div class="reg-section-step">06</div>
                                    <div class="reg-section-title">
                                        <h4>Additional Information</h4>
                                        <p>Notes and remarks</p>
                                    </div>
                                </div>
                                <div class="reg-section-content">
                                    <div class="reg-form-grid">
                                        <div class="reg-field full-width">
                                            <label class="reg-field-label">Registration Notes</label>
                                            <textarea class="reg-field-textarea" name="notes" rows="4" placeholder="Add any notes about this registration..."><?= h($notes) ?></textarea>
                                        </div>
                                        
                                        <div class="reg-field full-width">
                                            <label class="reg-field-label">Internal Remarks</label>
                                            <textarea class="reg-field-textarea" name="remarks" rows="4" placeholder="Private remarks (visible only to staff)..."><?= h($remarks) ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="reg-nav">
                                <button type="button" class="reg-btn reg-btn-outline" onclick="prevStep(5)">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                                <div class="reg-actions">
                                    <button type="button" class="reg-btn reg-btn-primary" onclick="submitRegistrationForm('active')">
                                        <i class="fas fa-check-circle"></i> Confirm
                                    </button>
                                    <button type="button" class="reg-btn reg-btn-secondary" onclick="submitRegistrationForm('draft')">
                                        <i class="fas fa-save"></i> Draft
                                    </button>
                                    <?php if (!empty($registration['id'])): ?>
                                        <button type="submit" name="delete_registration" class="reg-btn reg-btn-danger" onclick="return confirmDeleteReg(event)">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    </form>
                </div>
            </div>
            
        </main>
    </div>
</div>

<script>
// Step Navigation
let currentStep = 1;

function nextStep(step) {
    document.querySelectorAll('.reg-step-container').forEach(el => el.classList.remove('active'));
    document.getElementById(`step${step}`).classList.add('active');
    
    document.querySelectorAll('.reg-step-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`.reg-step-item[data-step="${step}"]`).classList.add('active');
    
    for (let i = 1; i < step; i++) {
        document.querySelector(`.reg-step-item[data-step="${i}"]`).classList.add('completed');
    }
    
    currentStep = step;
}

function prevStep(step) {
    document.querySelectorAll('.reg-step-container').forEach(el => el.classList.remove('active'));
    document.getElementById(`step${step}`).classList.add('active');
    
    document.querySelectorAll('.reg-step-item').forEach(el => el.classList.remove('active'));
    document.querySelector(`.reg-step-item[data-step="${step}"]`).classList.add('active');
    
    currentStep = step;
}

// Click on step circles
document.querySelectorAll('.reg-step-item').forEach(step => {
    step.addEventListener('click', function() {
        const stepNum = parseInt(this.dataset.step);
        if (stepNum <= currentStep) {
            nextStep(stepNum);
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

// Validation Functions
function validateAndNext(current, next) {
    if (current === 1) {
        const regType = document.getElementById('reg_type')?.value || '';
        const assignedTo = document.getElementById('assigned_to').value;
        if (!regType) {
            Swal.fire({
                icon: 'warning',
                title: 'Required Field',
                text: 'Please select registration type',
                confirmButtonColor: '#e91e63'
            });
            return false;
        }
        if (!assignedTo) {
            Swal.fire({
                icon: 'warning',
                title: 'Required Field',
                text: 'Please select Front Office Owner',
                confirmButtonColor: '#e91e63'
            });
            return false;
        }
    }
    nextStep(next);
}

function validateStep2() {
    const name = document.getElementById('student_name').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const email = document.getElementById('email').value.trim();
    const gender = document.querySelector('input[name="gender"]:checked')?.value || '';
    const dob = document.getElementById('dob')?.value.trim() || '';
    const aadhaarInput = document.querySelector('input[name="aadhaar_no"]');
    const aadhaar = aadhaarInput ? aadhaarInput.value.trim() : '';
    
    if (!name) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Field',
            text: 'Please enter student name',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }
    
    if (!phone) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Field',
            text: 'Please enter phone number',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }
    
    if (phone.length !== 10 || !/^\d+$/.test(phone)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Phone',
            text: 'Please enter a valid 10-digit phone number',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }
    
    if (!email) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Field',
            text: 'Please enter email address',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Email',
            text: 'Please enter a valid email address',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }

    if (!gender) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Field',
            text: 'Please select gender',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }

    if (!dob) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Field',
            text: 'Please select date of birth',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }

    if (!aadhaar) {
        Swal.fire({
            icon: 'warning',
            title: 'Required Field',
            text: 'Please enter Aadhaar number',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }

    if (!/^\d{12}$/.test(aadhaar)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Aadhaar',
            text: 'Aadhaar number must be exactly 12 digits.',
            confirmButtonColor: '#e91e63'
        });
        return false;
    }
    
    nextStep(3);
}

function validateStep3() {
    const programName = document.getElementById('program_name')?.value.trim() || '';
    const batchName = document.getElementById('batch_name')?.value.trim() || '';
    const qualification = document.getElementById('qualification')?.value.trim() || '';
    const collegeName = document.getElementById('college_name')?.value.trim() || '';
    const yearOfPassout = document.getElementById('year_of_passout')?.value.trim() || '';

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
    if (!yearOfPassout) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter year of passing', confirmButtonColor:'#e91e63' });
        return false;
    }
    if (!/^\d{4}$/.test(yearOfPassout)) {
        Swal.fire({ icon:'warning', title:'Invalid Year', text:'Please enter a valid 4-digit year of passing', confirmButtonColor:'#e91e63' });
        return false;
    }

    nextStep(4);
}

function validateStep4() {
    const parentEmail = document.querySelector('input[name="parent_email"]')?.value.trim() || '';
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!parentEmail) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter parent email address', confirmButtonColor:'#e91e63' });
        return false;
    }

    if (!emailRegex.test(parentEmail)) {
        Swal.fire({ icon:'warning', title:'Invalid Email', text:'Please enter a valid parent email address', confirmButtonColor:'#e91e63' });
        return false;
    }

    nextStep(5);
}

function validateStep5() {
    const totalFee = document.getElementById('total_fee')?.value.trim() || '';

    if (!totalFee) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter total fee', confirmButtonColor:'#e91e63' });
        return false;
    }

    const totalFeeNumber = parseFloat(totalFee);
    if (isNaN(totalFeeNumber) || totalFeeNumber <= 0) {
        Swal.fire({ icon:'warning', title:'Invalid Fee', text:'Please enter a valid total fee amount', confirmButtonColor:'#e91e63' });
        return false;
    }

    nextStep(6);
}

function validateCoreBeforeSubmit() {
    const regType = document.getElementById('reg_type')?.value || '';
    const assignedTo = document.getElementById('assigned_to')?.value || '';
    const name = document.getElementById('student_name')?.value.trim() || '';
    const phone = document.getElementById('phone')?.value.trim() || '';
    const email = document.getElementById('email')?.value.trim() || '';
    const gender = document.querySelector('input[name="gender"]:checked')?.value || '';
    const dob = document.getElementById('dob')?.value.trim() || '';
    const aadhaarInput = document.querySelector('input[name="aadhaar_no"]');
    const aadhaar = aadhaarInput ? aadhaarInput.value.trim() : '';
    const programName = document.getElementById('program_name')?.value.trim() || '';
    const batchName = document.getElementById('batch_name')?.value.trim() || '';
    const qualification = document.getElementById('qualification')?.value.trim() || '';
    const collegeName = document.getElementById('college_name')?.value.trim() || '';
    const yearOfPassout = document.getElementById('year_of_passout')?.value.trim() || '';
    const parentEmail = document.querySelector('input[name="parent_email"]')?.value.trim() || '';
    const totalFee = document.getElementById('total_fee')?.value.trim() || '';

    if (!regType) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please select registration type', confirmButtonColor:'#e91e63' });
        nextStep(1);
        return false;
    }
    if (!assignedTo) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please select Front Office Owner', confirmButtonColor:'#e91e63' });
        nextStep(1);
        return false;
    }
    if (!name) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter student name', confirmButtonColor:'#e91e63' });
        nextStep(2);
        return false;
    }
    if (!phone || phone.length !== 10 || !/^\d+$/.test(phone)) {
        Swal.fire({ icon:'warning', title:'Invalid Phone', text:'Please enter a valid 10-digit phone number', confirmButtonColor:'#e91e63' });
        nextStep(2);
        return false;
    }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
        Swal.fire({ icon:'warning', title:'Invalid Email', text:'Please enter a valid email address', confirmButtonColor:'#e91e63' });
        nextStep(2);
        return false;
    }
    if (!gender) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please select gender', confirmButtonColor:'#e91e63' });
        nextStep(2);
        return false;
    }
    if (!dob) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please select date of birth', confirmButtonColor:'#e91e63' });
        nextStep(2);
        return false;
    }
    if (!aadhaar || !/^\d{12}$/.test(aadhaar)) {
        Swal.fire({ icon:'warning', title:'Invalid Aadhaar', text:'Aadhaar number must be exactly 12 digits.', confirmButtonColor:'#e91e63' });
        nextStep(2);
        return false;
    }
    if (!programName) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter program name', confirmButtonColor:'#e91e63' });
        nextStep(3);
        return false;
    }
    if (!batchName) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter batch name', confirmButtonColor:'#e91e63' });
        nextStep(3);
        return false;
    }
    if (!qualification) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter qualification', confirmButtonColor:'#e91e63' });
        nextStep(3);
        return false;
    }
    if (!collegeName) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter college or university name', confirmButtonColor:'#e91e63' });
        nextStep(3);
        return false;
    }
    if (!yearOfPassout || !/^\d{4}$/.test(yearOfPassout)) {
        Swal.fire({ icon:'warning', title:'Invalid Year', text:'Please enter a valid 4-digit year of passing', confirmButtonColor:'#e91e63' });
        nextStep(3);
        return false;
    }
    if (!parentEmail) {
        Swal.fire({ icon:'warning', title:'Required Field', text:'Please enter parent email address', confirmButtonColor:'#e91e63' });
        nextStep(4);
        return false;
    }
    if (!emailRegex.test(parentEmail)) {
        Swal.fire({ icon:'warning', title:'Invalid Email', text:'Please enter a valid parent email address', confirmButtonColor:'#e91e63' });
        nextStep(4);
        return false;
    }
    if (!totalFee || isNaN(parseFloat(totalFee)) || parseFloat(totalFee) <= 0) {
        Swal.fire({ icon:'warning', title:'Invalid Fee', text:'Please enter a valid total fee amount', confirmButtonColor:'#e91e63' });
        nextStep(5);
        return false;
    }
    return true;
}

// Capitalization Functions
function capitalizeFirstLetter(input) {
    let value = input.value;
    if (value.length > 0) {
        value = value.toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
        input.value = value;
    }
}

function capitalizeSentences(textarea) {
    let value = textarea.value;
    if (value.length > 0) {
        value = value.toLowerCase().replace(/(^\s*\w|[.!?]\s*\w)/g, char => char.toUpperCase());
        textarea.value = value;
    }
}

// Validation Functions for Inputs
function validatePhone(input) {
    input.value = input.value.replace(/[^0-9]/g, '').substring(0, 10);
}

function validateDecimalInput(input) {
    let value = input.value.replace(/[^0-9.]/g, '');
    const parts = value.split('.');
    if (parts.length > 2) {
        value = parts[0] + '.' + parts.slice(1).join('');
    }
    input.value = value;
}

function validateAadhaar(input) {
    input.value = input.value.replace(/[^0-9]/g, '').substring(0, 12);
}

function validateYear(input) {
    input.value = input.value.replace(/[^0-9]/g, '').substring(0, 4);
}

function validateEmail(input) {
    const email = input.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Email',
            text: 'Please enter a valid email address',
            confirmButtonColor: '#e91e63',
            timer: 2000
        });
    }
}

// Fee Calculator
(function(){
    const total = document.querySelector('input[name="total_fee"]');
    const discount = document.querySelector('input[name="discount_amount"]');
    const finalFee = document.getElementById('final_fee');
    
    function calc(){
        if (!total || !discount || !finalFee) return;
        const t = parseFloat(total.value || 0);
        const d = parseFloat(discount.value || 0);
        let f = t - d;
        if (f < 0) f = 0;
        finalFee.value = f.toFixed(2);
    }
    
    if (total && discount) {
        [total, discount].forEach(el => {
            if (el) el.addEventListener('input', calc);
        });
    }
})();

document.querySelectorAll('.js-decimal-input').forEach(function(input){
    input.addEventListener('input', function(){
        validateDecimalInput(this);
    });
    input.addEventListener('keydown', function(e){
        if (['e', 'E', '+', '-'].includes(e.key)) {
            e.preventDefault();
        }
    });
});

const totalFeeLabel = document.querySelector('input[name="total_fee"]')?.closest('.reg-field')?.querySelector('.reg-field-label');
if (totalFeeLabel && !totalFeeLabel.querySelector('.required')) {
    totalFeeLabel.insertAdjacentHTML('beforeend', ' <span class="required">*</span>');
}

// Form Submission
function submitRegistrationForm(status) {
    if (!validateCoreBeforeSubmit()) return;
    document.getElementById('registration_status_input').value = status;
    
    const titles = {
        'active': {
            title: 'Confirm Registration?',
            text: 'This student will be moved to active registrations.'
        },
        'draft': {
            title: 'Save as Draft?',
            text: 'This registration will be saved in drafts.'
        }
    };
    
    const config = titles[status] || titles.draft;
    
    Swal.fire({
        icon: 'question',
        title: config.title,
        text: config.text,
        showCancelButton: true,
        confirmButtonText: status === 'active' ? 'Confirm' : 'Save Draft',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e91e63'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('registrationForm').submit();
        }
    });
}

function confirmDeleteReg(e) {
    e.preventDefault();
    
    Swal.fire({
        icon: 'warning',
        title: 'Delete Registration?',
        text: 'This action cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Delete',
        confirmButtonColor: '#e91e63'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('registrationForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_registration';
            input.value = '1';
            form.appendChild(input);
            form.submit();
        }
    });
    
    return false;
}
</script>


