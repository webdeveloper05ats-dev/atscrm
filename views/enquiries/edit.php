<?php
// =====================================
// Enquiries - Edit (Wizard UI)
// Slug: enquiries/edit
// File: views/enquiries/edit.php
// =====================================

requireView('enquiries/list');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

// Session
$userId   = (int)($_SESSION['user_id'] ?? 0);
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$roleName = $_SESSION['role_name'] ?? '';
$branchId = (int)($_SESSION['branch_id'] ?? 0);

// Optional role restriction (edit allowed)
$allowedRoles = ['Front Office', 'Super Admin'];
if (!in_array($roleName, $allowedRoles, true)) {
    $error = "Access denied for this role.";
}

// Branch access (roles.can_access_all_branches)
$canAllBranches = 0;
try {
    $r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $r->execute([$roleId]);
    $canAllBranches = (int)($r->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

// ----------------------
// Helpers
// ----------------------
function toNull(string $v) { $v = trim($v); return ($v === '') ? null : $v; }
function toIntOrNull($v) { $v = trim((string)$v); return ($v === '') ? null : (int)$v; }
function toFloatOrNull($v) { $v = trim((string)$v); return ($v === '') ? null : (float)$v; }
function joinCsv($arr): ?string {
    if (!is_array($arr) || empty($arr)) return null;
    $clean = [];
    foreach ($arr as $a) { $a = trim((string)$a); if ($a !== '') $clean[] = $a; }
    return empty($clean) ? null : implode(',', $clean);
}
function splitCsv(?string $s): array {
    $s = trim((string)$s);
    if ($s === '') return [];
    $parts = array_map('trim', explode(',', $s));
    return array_values(array_filter($parts, fn($x)=>$x!==''));
}

// ----------------------
// ID
// ----------------------
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid enquiry ID.");
}

// ----------------------
// Load Front Office Users for dropdown
// ----------------------
$frontOfficeUsers = [];
try {
    $st = $pdo->prepare("
        SELECT u.id, u.name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.status = 1
          AND r.role_name = 'Front Office'
        ORDER BY u.name ASC
    ");
    $st->execute();
    $frontOfficeUsers = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $frontOfficeUsers = [];
}

// ----------------------
// Fetch Enquiry
// ----------------------
$enq = null;
try {
    if ($canAllBranches !== 1 && $branchId > 0) {
        $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? AND branch_id=? LIMIT 1");
        $st->execute([$id, $branchId]);
    } else {
        $st = $pdo->prepare("SELECT * FROM enquiries WHERE id=? LIMIT 1");
        $st->execute([$id]);
    }
    $enq = $st->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $enq = null;
}

if (!$enq) {
    die("Enquiry not found or access restricted.");
}

// ----------------------
// POST: Update
// ----------------------
if (isset($_POST['update_enquiry']) && empty($error)) {

    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF). Please refresh and try again.";
    } else {

        // Basic
        $enquiry_date = $_POST['enquiry_date'] ?? ($enq['enquiry_date'] ?? date('Y-m-d'));
        $enquiry_no   = trim($_POST['enquiry_no'] ?? ($enq['enquiry_no'] ?? ''));

        $handledBy = (int)($_POST['handled_by'] ?? 0);

        $name       = trim($_POST['name'] ?? '');
        $phone      = toNull($_POST['phone'] ?? '');
        $email      = toNull($_POST['email'] ?? '');
        $dob        = toNull($_POST['dob'] ?? '');
        $gender     = toNull($_POST['gender'] ?? '');
        $profession = toNull($_POST['profession'] ?? '');
        $address    = toNull($_POST['address'] ?? '');
        $instagram_id = toNull($_POST['instagram_id'] ?? '');
        $course_interest = toNull($_POST['course_interest'] ?? '');

        // Education
        $qualification    = toNull($_POST['qualification'] ?? '');
        $year_of_passout  = toIntOrNull($_POST['year_of_passout'] ?? '');
        $college          = toNull($_POST['college'] ?? '');
        $percentage_marks = toFloatOrNull($_POST['percentage_marks'] ?? '');
        $software_languages_known = toNull($_POST['software_languages_known'] ?? '');

        // Parent
        $father_name       = toNull($_POST['father_name'] ?? '');
        $father_occupation = toNull($_POST['father_occupation'] ?? '');
        $father_contact_no = toNull($_POST['father_contact_no'] ?? '');

        // Tech + source
        $technologies  = joinCsv($_POST['technologies'] ?? []);
        $interested_in = joinCsv($_POST['interested_in'] ?? []);
        $placements_required = isset($_POST['placements_required']) ? 1 : 0;

        $know_about = joinCsv($_POST['know_about'] ?? []);
        $know_about_other = toNull($_POST['know_about_other'] ?? '');

        $status = trim($_POST['status'] ?? ($enq['status'] ?? 'new'));
        $allowedStatus = ['new','followup','converted','closed'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'new';
        }

        $remarks = toNull($_POST['remarks'] ?? '');

        // Validate
        if ($name === '') {
            $error = "Name is required.";
        } else {

            // Auto assign handled_by if not selected (product-safe)
            if ($handledBy <= 0) {
                try {
                    $auto = $pdo->prepare("
                        SELECT u.id
                        FROM users u
                        JOIN roles r ON r.id = u.role_id
                        WHERE u.status = 1
                          AND r.role_name = 'Front Office'
                          AND (u.branch_id = ? OR u.branch_id IS NULL OR u.branch_id = 0)
                        ORDER BY u.id ASC
                        LIMIT 1
                    ");
                    $auto->execute([(int)($enq['branch_id'] ?? $branchId)]);
                    $autoUser = (int)($auto->fetchColumn() ?? 0);
                } catch (Exception $e) {
                    $autoUser = 0;
                }

                if ($autoUser > 0) {
                    $handledBy = $autoUser;
                } else {
                    try {
                        $super = $pdo->prepare("
                            SELECT u.id
                            FROM users u
                            JOIN roles r ON r.id = u.role_id
                            WHERE u.status=1 AND r.role_name='Super Admin'
                            ORDER BY u.id ASC
                            LIMIT 1
                        ");
                        $super->execute();
                        $superUser = (int)($super->fetchColumn() ?? 0);
                    } catch (Exception $e) {
                        $superUser = 0;
                    }
                    $handledBy = $superUser > 0 ? $superUser : $userId;
                }
            }

            // Signature uploads (optional) - keep existing if not uploaded
            $candidate_signature_path = $enq['candidate_signature_path'] ?? null;
            $counselor_signature_path = $enq['counselor_signature_path'] ?? null;

            if (!empty($_FILES['candidate_signature']['name'])) {
                $f = uploadFile($_FILES['candidate_signature'], 'enquiries');
                if ($f === false) {
                    $error = "Candidate signature upload failed.";
                } else {
                    $candidate_signature_path = 'uploads/enquiries/' . $f;
                }
            }

            if (empty($error) && !empty($_FILES['counselor_signature']['name'])) {
                $f2 = uploadFile($_FILES['counselor_signature'], 'enquiries');
                if ($f2 === false) {
                    $error = "Counselor signature upload failed.";
                } else {
                    $counselor_signature_path = 'uploads/enquiries/' . $f2;
                }
            }

            // Update
            if (empty($error)) {
                try {
                    // Branch restriction update
                    if ($canAllBranches !== 1 && $branchId > 0) {
                        $sql = "
                            UPDATE enquiries SET
                                enquiry_date=:enquiry_date,
                                enquiry_no=:enquiry_no,
                                name=:name,
                                phone=:phone,
                                email=:email,
                                dob=:dob,
                                gender=:gender,
                                profession=:profession,
                                address=:address,
                                instagram_id=:instagram_id,
                                course_interest=:course_interest,

                                qualification=:qualification,
                                year_of_passout=:year_of_passout,
                                college=:college,
                                percentage_marks=:percentage_marks,
                                software_languages_known=:software_languages_known,

                                father_name=:father_name,
                                father_occupation=:father_occupation,
                                father_contact_no=:father_contact_no,

                                technologies=:technologies,
                                interested_in=:interested_in,
                                placements_required=:placements_required,
                                know_about=:know_about,
                                know_about_other=:know_about_other,

                                candidate_signature_path=:candidate_signature_path,
                                counselor_signature_path=:counselor_signature_path,

                                status=:status,
                                handled_by=:handled_by,
                                remarks=:remarks,

                                updated_by=:updated_by,
                                ip_address=:ip_address,
                                user_agent=:user_agent,
                                updated_at=NOW()
                            WHERE id=:id AND branch_id=:branch_id
                            LIMIT 1
                        ";
                    } else {
                        $sql = "
                            UPDATE enquiries SET
                                enquiry_date=:enquiry_date,
                                enquiry_no=:enquiry_no,
                                name=:name,
                                phone=:phone,
                                email=:email,
                                dob=:dob,
                                gender=:gender,
                                profession=:profession,
                                address=:address,
                                instagram_id=:instagram_id,
                                course_interest=:course_interest,

                                qualification=:qualification,
                                year_of_passout=:year_of_passout,
                                college=:college,
                                percentage_marks=:percentage_marks,
                                software_languages_known=:software_languages_known,

                                father_name=:father_name,
                                father_occupation=:father_occupation,
                                father_contact_no=:father_contact_no,

                                technologies=:technologies,
                                interested_in=:interested_in,
                                placements_required=:placements_required,
                                know_about=:know_about,
                                know_about_other=:know_about_other,

                                candidate_signature_path=:candidate_signature_path,
                                counselor_signature_path=:counselor_signature_path,

                                status=:status,
                                handled_by=:handled_by,
                                remarks=:remarks,

                                updated_by=:updated_by,
                                ip_address=:ip_address,
                                user_agent=:user_agent,
                                updated_at=NOW()
                            WHERE id=:id
                            LIMIT 1
                        ";
                    }

                    $st = $pdo->prepare($sql);

                    $data = [
                        ':enquiry_date' => $enquiry_date,
                        ':enquiry_no'   => $enquiry_no,

                        ':name'  => $name,
                        ':phone' => $phone,
                        ':email' => $email,
                        ':dob'   => $dob,
                        ':gender'=> $gender,
                        ':profession'=> $profession,
                        ':address'=> $address,
                        ':instagram_id'=> $instagram_id,
                        ':course_interest'=> $course_interest,

                        ':qualification'=> $qualification,
                        ':year_of_passout'=> $year_of_passout,
                        ':college'=> $college,
                        ':percentage_marks'=> $percentage_marks,
                        ':software_languages_known'=> $software_languages_known,

                        ':father_name'=> $father_name,
                        ':father_occupation'=> $father_occupation,
                        ':father_contact_no'=> $father_contact_no,

                        ':technologies'=> $technologies,
                        ':interested_in'=> $interested_in,
                        ':placements_required'=> $placements_required,
                        ':know_about'=> $know_about,
                        ':know_about_other'=> $know_about_other,

                        ':candidate_signature_path'=> $candidate_signature_path,
                        ':counselor_signature_path'=> $counselor_signature_path,

                        ':status'=> $status,
                        ':handled_by'=> $handledBy,
                        ':remarks'=> $remarks,

                        ':updated_by'=> $userId,
                        ':ip_address'=> $_SERVER['REMOTE_ADDR'] ?? null,
                        ':user_agent'=> $_SERVER['HTTP_USER_AGENT'] ?? null,
                        ':id'=> $id
                    ];

                    if ($canAllBranches !== 1 && $branchId > 0) {
                        $data[':branch_id'] = $branchId;
                    }

                    $st->execute($data);

                    $success = "Enquiry updated successfully!";

                    // reload latest data for form display
                    if ($canAllBranches !== 1 && $branchId > 0) {
                        $st2 = $pdo->prepare("SELECT * FROM enquiries WHERE id=? AND branch_id=? LIMIT 1");
                        $st2->execute([$id, $branchId]);
                    } else {
                        $st2 = $pdo->prepare("SELECT * FROM enquiries WHERE id=? LIMIT 1");
                        $st2->execute([$id]);
                    }
                    $enq = $st2->fetch(PDO::FETCH_ASSOC);

                } catch (Exception $e) {
                    $error = "Update failed. " . $e->getMessage();
                }
            }
        }
    }
}

// prefill for multi selects
$techSelected = splitCsv($enq['technologies'] ?? '');
$intSelected  = splitCsv($enq['interested_in'] ?? '');
$kaSelected   = splitCsv($enq['know_about'] ?? '');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>

<style>
/* ===== Wizard + Modern Form UI ===== */
.wizard { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.wstep {
  flex:1; min-width:180px;
  padding:12px 14px; border:1px solid #eee; border-radius:14px;
  background:#fff; cursor:pointer;
  display:flex; align-items:center; justify-content:space-between;
}
.wstep b { font-size:14px; }
.wstep small { color: var(--text-light); }
.wstep.active { border-color: rgba(233,30,99,.35); box-shadow: 0 8px 24px rgba(233,30,99,.08); }
.wstep .num {
  width:32px; height:32px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  background: rgba(233,30,99,.12);
  color: var(--primary);
  font-weight:800;
}
.wpanel { display:none; }
.wpanel.active { display:block; }

.form-group label { font-weight:700; margin-bottom:6px; display:block; }
input[type="text"], input[type="email"], input[type="date"], input[type="number"], textarea, select {
  width:100%; padding:10px 12px;
  border:1px solid #e5e7eb; border-radius:12px; outline:none; background:#fff;
}
input:focus, textarea:focus, select:focus {
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.12);
}
select[multiple] { min-height: 140px; }
.hint { color: var(--text-light); font-size:12px; margin-top:6px; display:block; }

.toggle { display:flex; align-items:center; gap:10px; user-select:none; }
.toggle input { display:none; }
.toggle span {
  width:46px; height:26px; border-radius:999px; background:#e5e7eb;
  position:relative; display:inline-block; transition:.2s;
}
.toggle span::after{
  content:""; width:22px; height:22px; border-radius:50%; background:#fff;
  position:absolute; top:2px; left:2px; box-shadow:0 4px 10px rgba(0,0,0,.12); transition:.2s;
}
.toggle input:checked + span { background: rgba(233,30,99,.55); }
.toggle input:checked + span::after { left:22px; }

.wbtns { display:flex; gap:10px; justify-content:flex-end; margin-top:14px; flex-wrap:wrap; }
.wbtn { padding:10px 14px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
.wbtn.primary { background: var(--primary); color:#fff; border-color: transparent; }
.wbtn.primary:hover { background: var(--primary-dark); }
.wbtn:hover { box-shadow: 0 8px 22px rgba(0,0,0,.06); }
</style>

<h2 style="margin-bottom:16px;">Edit Enquiry #<?= (int)$id ?></h2>

<?php if ($success): ?>
<script>
Swal.fire({
  icon:'success',
  title:'Success',
  text:'<?= addslashes($success) ?>',
  confirmButtonColor:'#e91e63'
}).then(()=> window.location.href="index.php?page=enquiries/list");
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

<div class="card">
  <div class="card-header">Enquiry Wizard</div>

  <form method="POST" enctype="multipart/form-data" style="padding:14px;">
    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">

    <!-- Wizard -->
    <div class="wizard">
      <div class="wstep active" data-step="1"><div><b>Step 1</b><br><small>Basic Details</small></div><div class="num">1</div></div>
      <div class="wstep" data-step="2"><div><b>Step 2</b><br><small>Education + Parent</small></div><div class="num">2</div></div>
      <div class="wstep" data-step="3"><div><b>Step 3</b><br><small>Tech + Source</small></div><div class="num">3</div></div>
    </div>

    <!-- Step 1 -->
    <div class="wpanel active" data-panel="1">
      <div class="crm-row">
        <div class="crm-col-4">
          <div class="form-group">
            <label>Enquiry Date</label>
            <input type="date" name="enquiry_date" value="<?= h($enq['enquiry_date'] ?? date('Y-m-d')) ?>">
          </div>
        </div>
        <div class="crm-col-4">
          <div class="form-group">
            <label>Enquiry No</label>
            <input type="text" name="enquiry_no" value="<?= h($enq['enquiry_no'] ?? '') ?>">
          </div>
        </div>
        <div class="crm-col-4">
          <div class="form-group">
            <label>Handled By (Front Office)</label>
            <select name="handled_by">
              <option value="">-- Auto Assign --</option>
              <?php foreach ($frontOfficeUsers as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= ((int)($enq['handled_by'] ?? 0) === (int)$u['id']) ? 'selected' : '' ?>>
                  <?= h($u['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="crm-row">
        <div class="crm-col-6">
          <div class="form-group">
            <label>Name <span style="color:red;">*</span></label>
            <input type="text" name="name" required value="<?= h($enq['name'] ?? '') ?>">
          </div>
        </div>
        <div class="crm-col-3">
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" value="<?= h($enq['phone'] ?? '') ?>">
          </div>
        </div>
        <div class="crm-col-3">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?= h($enq['email'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="crm-row">
        <div class="crm-col-3">
          <div class="form-group">
            <label>DOB</label>
            <input type="date" name="dob" value="<?= h($enq['dob'] ?? '') ?>">
          </div>
        </div>
        <div class="crm-col-3">
          <div class="form-group">
            <label>Gender</label>
            <select name="gender">
              <option value="">-- Select --</option>
              <option value="male" <?= (($enq['gender'] ?? '')==='male')?'selected':''; ?>>Male</option>
              <option value="female" <?= (($enq['gender'] ?? '')==='female')?'selected':''; ?>>Female</option>
              <option value="other" <?= (($enq['gender'] ?? '')==='other')?'selected':''; ?>>Other</option>
            </select>
          </div>
        </div>
        <div class="crm-col-6">
          <div class="form-group">
            <label>Profession</label>
            <input type="text" name="profession" value="<?= h($enq['profession'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="crm-row">
        <div class="crm-col-8">
          <div class="form-group">
            <label>Address</label>
            <textarea name="address" rows="3"><?= h($enq['address'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="crm-col-4">
          <div class="form-group">
            <label>Instagram ID</label>
            <input type="text" name="instagram_id" value="<?= h($enq['instagram_id'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Course Interest</label>
            <input type="text" name="course_interest" value="<?= h($enq['course_interest'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="wbtns">
        <a class="wbtn" href="index.php?page=enquiries/list" style="text-decoration:none;">← Back to List</a>
        <button type="button" class="wbtn primary" onclick="goStep(2)">Next →</button>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="wpanel" data-panel="2">
      <div class="crm-row">
        <div class="crm-col-6">
          <div class="form-group">
            <label>Qualification</label>
            <input type="text" name="qualification" value="<?= h($enq['qualification'] ?? '') ?>">
          </div>
        </div>
        <div class="crm-col-3">
          <div class="form-group">
            <label>Year of Passout</label>
            <input type="number" name="year_of_passout" min="1990" max="2100" value="<?= h($enq['year_of_passout'] ?? '') ?>">
          </div>
        </div>
        <div class="crm-col-3">
          <div class="form-group">
            <label>% Marks</label>
            <input type="text" name="percentage_marks" value="<?= h($enq['percentage_marks'] ?? '') ?>">
          </div>
        </div>
      </div>

      <div class="crm-row">
        <div class="crm-col-8">
          <div class="form-group">
            <label>College</label>
            <input type="text" name="college" value="<?= h($enq['college'] ?? '') ?>">
          </div>
        </div>
        <div class="crm-col-4">
          <div class="form-group">
            <label class="toggle">
              <input type="checkbox" name="placements_required" value="1" <?= ((int)($enq['placements_required'] ?? 0) === 1) ? 'checked' : '' ?>>
              <span></span>
              <div><b>Placements Required?</b><br><small style="color:var(--text-light);">Enable if needed</small></div>
            </label>
          </div>
        </div>
      </div>

      <div class="form-group">
        <label>Software Languages Known</label>
        <textarea name="software_languages_known" rows="3"><?= h($enq['software_languages_known'] ?? '') ?></textarea>
      </div>

      <div class="card" style="margin-top:12px;">
        <div class="card-header">Parent Details</div>
        <div style="padding:14px;">
          <div class="crm-row">
            <div class="crm-col-4">
              <div class="form-group">
                <label>Father Name</label>
                <input type="text" name="father_name" value="<?= h($enq['father_name'] ?? '') ?>">
              </div>
            </div>
            <div class="crm-col-4">
              <div class="form-group">
                <label>Father Occupation</label>
                <input type="text" name="father_occupation" value="<?= h($enq['father_occupation'] ?? '') ?>">
              </div>
            </div>
            <div class="crm-col-4">
              <div class="form-group">
                <label>Father Contact No</label>
                <input type="text" name="father_contact_no" value="<?= h($enq['father_contact_no'] ?? '') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="wbtns">
        <button type="button" class="wbtn" onclick="goStep(1)">← Back</button>
        <button type="button" class="wbtn primary" onclick="goStep(3)">Next →</button>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="wpanel" data-panel="3">

      <div class="crm-row">
        <div class="crm-col-6">
          <div class="form-group">
            <label>Technologies (multi)</label>
            <select name="technologies[]" multiple>
              <?php
              $techOptions = ['Artificial Intelligence','Data Science','Full Stack Web Development','Web Designing','Python','Java','PHP & MySQL','Tally','MS Office','Digital Marketing'];
              foreach ($techOptions as $op):
              ?>
                <option value="<?= h($op) ?>" <?= in_array($op, $techSelected, true) ? 'selected' : '' ?>><?= h($op) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Hold Ctrl/Cmd for multi select</span>
          </div>
        </div>

        <div class="crm-col-6">
          <div class="form-group">
            <label>Interested In (multi)</label>
            <select name="interested_in[]" multiple>
              <?php
              $intOptions = ['Technology Training','Internship','Placement Assistance','Project Development'];
              foreach ($intOptions as $op):
              ?>
                <option value="<?= h($op) ?>" <?= in_array($op, $intSelected, true) ? 'selected' : '' ?>><?= h($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="crm-row">
        <div class="crm-col-4">
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option value="new" <?= (($enq['status'] ?? '')==='new')?'selected':''; ?>>New</option>
              <option value="followup" <?= (($enq['status'] ?? '')==='followup')?'selected':''; ?>>Follow-up</option>
              <option value="converted" <?= (($enq['status'] ?? '')==='converted')?'selected':''; ?>>Converted</option>
              <option value="closed" <?= (($enq['status'] ?? '')==='closed')?'selected':''; ?>>Closed</option>
            </select>
          </div>
        </div>

        <div class="crm-col-8">
          <div class="form-group">
            <label>How did you know about ATS? (multi)</label>
            <select name="know_about[]" multiple>
              <?php
              $kaOptions = ['Website','Google Search','Instagram','Facebook','Friends/Reference','Walk-in','Other'];
              foreach ($kaOptions as $op):
              ?>
                <option value="<?= h($op) ?>" <?= in_array($op, $kaSelected, true) ? 'selected' : '' ?>><?= h($op) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="crm-row">
        <div class="crm-col-6">
          <div class="form-group">
            <label>Other Source</label>
            <input type="text" name="know_about_other" value="<?= h($enq['know_about_other'] ?? '') ?>">
          </div>
        </div>

        <div class="crm-col-6">
          <div class="form-group">
            <label>Candidate Signature (optional)</label>
            <input type="file" name="candidate_signature" accept=".jpg,.jpeg,.png">
            <?php if (!empty($enq['candidate_signature_path'])): ?>
              <span class="hint">Existing: <?= h($enq['candidate_signature_path']) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="crm-row">
        <div class="crm-col-6">
          <div class="form-group">
            <label>Counselor Signature (optional)</label>
            <input type="file" name="counselor_signature" accept=".jpg,.jpeg,.png">
            <?php if (!empty($enq['counselor_signature_path'])): ?>
              <span class="hint">Existing: <?= h($enq['counselor_signature_path']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="crm-col-6">
          <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" rows="4"><?= h($enq['remarks'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <div class="wbtns">
        <button type="button" class="wbtn" onclick="goStep(2)">← Back</button>
        <button type="submit" name="update_enquiry" class="wbtn primary">Save Changes</button>
      </div>
    </div>

  </form>
</div>

<script>
function goStep(n){
  document.querySelectorAll('.wstep').forEach(s=>s.classList.remove('active'));
  document.querySelectorAll('.wpanel').forEach(p=>p.classList.remove('active'));
  document.querySelector('.wstep[data-step="'+n+'"]').classList.add('active');
  document.querySelector('.wpanel[data-panel="'+n+'"]').classList.add('active');
  window.scrollTo({top:0, behavior:'smooth'});
}
document.querySelectorAll('.wstep').forEach(el=>{
  el.addEventListener('click', ()=> goStep(el.getAttribute('data-step')));
});
</script>