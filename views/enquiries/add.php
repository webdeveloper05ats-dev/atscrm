<?php
// =====================================
// Enquiries - Add (3-Step Wizard UI)
// Slug: enquiries/add
// File: views/enquiries/add.php
// =====================================

requireView('enquiries/add');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = (string)($_SESSION['role_name'] ?? '');

// Only these roles can add enquiries
$allowedRoles = ['Front Office', 'Super Admin'];
if (!in_array($roleName, $allowedRoles, true)) {
    $error = "Access denied for this role.";
}

// ---------- Helpers ----------
if (!function_exists('toNull')) {
    function toNull(string $v) { $v = trim($v); return ($v === '') ? null : $v; }
}
if (!function_exists('toIntOrNull')) {
    function toIntOrNull($v) { $v = trim((string)$v); return ($v === '') ? null : (int)$v; }
}
if (!function_exists('toFloatOrNull')) {
    function toFloatOrNull($v) { $v = trim((string)$v); return ($v === '') ? null : (float)$v; }
}
if (!function_exists('joinCsv')) {
    function joinCsv($arr): ?string {
        if (!is_array($arr) || empty($arr)) return null;
        $clean = [];
        foreach ($arr as $a) {
            $a = trim((string)$a);
            if ($a !== '') $clean[] = $a;
        }
        return empty($clean) ? null : implode(',', $clean);
    }
}

// Basic validators
if (!function_exists('isValidPhone')) {
    function isValidPhone(?string $phone): bool {
        if ($phone === null) return true;
        $p = preg_replace('/\s+/', '', $phone);
        return (bool)preg_match('/^\+?[0-9]{7,15}$/', $p);
    }
}
if (!function_exists('isValidEmail')) {
    function isValidEmail(?string $email): bool {
        if ($email === null) return true;
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
if (!function_exists('generateEnquiryNo')) {
    function generateEnquiryNo(PDO $pdo): string {

    // Insert dummy row to get unique ID
    $pdo->exec("INSERT INTO enquiry_sequence VALUES ()");

    $seqId = (int)$pdo->lastInsertId();

    // Format: ENQ-YYYYMMDD-0001
    return 'ENQ-' . date('Ymd') . '-' . str_pad($seqId, 4, '0', STR_PAD_LEFT);
}
}

// Safe upload (signature)
if (!function_exists('uploadSignature')) {
    function uploadSignature($file, string $folder): ?string {
        if (!isset($file) || empty($file['name'])) return null;
        if ($file['error'] !== UPLOAD_ERR_OK) return null;

        if (!empty($file['size']) && (int)$file['size'] > (2 * 1024 * 1024)) {
            return '__ERROR__SIZE__';
        }

        $allowedExt = ['jpg','jpeg','png'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            return '__ERROR__TYPE__';
        }

        $saved = uploadFile($file, $folder);
        if ($saved === false) return '__ERROR__UPLOAD__';

        return 'uploads/' . $folder . '/' . $saved;
    }
}

// ---------- Lead Prefill Support ----------
$leadId = (int)($_GET['lead_id'] ?? ($_POST['lead_id'] ?? 0));
$leadRow = null;

if ($leadId > 0) {
    try {
        $leadSql = "
            SELECT id, branch_id, name, phone, email, source, course_interest, assigned_to, status
            FROM leads
            WHERE id = ?
            LIMIT 1
        ";
        $st = $pdo->prepare($leadSql);
        $st->execute([$leadId]);
        $leadRow = $st->fetch(PDO::FETCH_ASSOC);

        if (!$leadRow) {
            $error = "Lead not found.";
        } else {
            $leadBranchId = (int)($leadRow['branch_id'] ?? 0);

            // Branch-safe for non-all-branch users
            $canAllBranches = 0;
            try {
                $rb = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE role_name = ? LIMIT 1");
                $rb->execute([$roleName]);
                $canAllBranches = (int)($rb->fetchColumn() ?? 0);
            } catch (Exception $e) {
                $canAllBranches = 0;
            }

            if ($canAllBranches !== 1 && $branchId > 0 && $leadBranchId > 0 && $leadBranchId !== $branchId) {
                $error = "You cannot access this lead (branch restriction).";
                $leadRow = null;
            }
        }
    } catch (Exception $e) {
        $error = "Unable to load lead details.";
        $leadRow = null;
    }
}

// ---------- Front Office Dropdown ----------
$frontOfficeUsers = [];
try {
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
    $frontOfficeUsers = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $frontOfficeUsers = [];
}

// ---------- Submit ----------
if (isset($_POST['save_enquiry']) && empty($error)) {

    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request (CSRF). Please refresh and try again.";
    } else {

        // ===============================
        // COLLECT FORM DATA
        // ===============================

        $enquiry_date = $_POST['enquiry_date'] ?? date('Y-m-d');
        $handledBy    = (int)($_POST['handled_by'] ?? 0);

        $name  = trim($_POST['name'] ?? '');
        $phone = toNull($_POST['phone'] ?? '');
        $email = toNull($_POST['email'] ?? '');

        $dob        = toNull($_POST['dob'] ?? '');
        $gender     = toNull($_POST['gender'] ?? '');
        $profession = toNull($_POST['profession'] ?? '');
        $address    = toNull($_POST['address'] ?? '');
        $instagram_id = toNull($_POST['instagram_id'] ?? '');
        $course_interest = toNull($_POST['course_interest'] ?? '');

        $qualification    = toNull($_POST['qualification'] ?? '');
        $year_of_passout  = toIntOrNull($_POST['year_of_passout'] ?? '');
        $college          = toNull($_POST['college'] ?? '');
        $percentage_marks = toFloatOrNull($_POST['percentage_marks'] ?? '');
        $software_languages_known = toNull($_POST['software_languages_known'] ?? '');

        $father_name       = toNull($_POST['father_name'] ?? '');
        $father_occupation = toNull($_POST['father_occupation'] ?? '');
        $father_contact_no = toNull($_POST['father_contact_no'] ?? '');

        $technologies  = joinCsv($_POST['technologies'] ?? []);
        $interested_in = joinCsv($_POST['interested_in'] ?? []);
        $placements_required = isset($_POST['placements_required']) ? 1 : 0;
        $know_about = joinCsv($_POST['know_about'] ?? []);
        $know_about_other = toNull($_POST['know_about_other'] ?? '');
        $remarks = toNull($_POST['remarks'] ?? '');

        $status = 'new';

        // ===============================
        // VALIDATION
        // ===============================

        if ($branchId <= 0) {
            $error = "Branch missing.";
        } elseif ($name === '') {
            $error = "Name is required.";
        } elseif (!isValidPhone($phone)) {
            $error = "Invalid phone.";
        } elseif (!isValidEmail($email)) {
            $error = "Invalid email.";
        }

        // ===============================
        // FILE UPLOAD
        // ===============================

        $candidate_signature_path = null;
        $counselor_signature_path = null;

        if (empty($error)) {
            $cand = uploadSignature($_FILES['candidate_signature'] ?? null, 'enquiries');
            if ($cand && !str_starts_with($cand, '__ERROR__')) {
                $candidate_signature_path = $cand;
            }
        }

        if (empty($error)) {
            $coun = uploadSignature($_FILES['counselor_signature'] ?? null, 'enquiries');
            if ($coun && !str_starts_with($coun, '__ERROR__')) {
                $counselor_signature_path = $coun;
            }
        }

        // ===============================
        // FINAL INSERT (NO DUPLICATE 🔥)
        // ===============================

        if (empty($error)) {
            try {

                $pdo->beginTransaction();

                // ✅ UNIQUE NUMBER (SAFE)
                $enquiry_no = generateEnquiryNo($pdo);

                // handled_by fallback
                if ($handledBy <= 0) {
                    $handledBy = $userId;
                }

                $st = $pdo->prepare("
                    INSERT INTO enquiries (
                        enquiry_date, enquiry_no,
                        branch_id, name, phone, email, dob, gender, profession, address, instagram_id, course_interest,
                        qualification, year_of_passout, college, percentage_marks, software_languages_known,
                        father_name, father_occupation, father_contact_no,
                        technologies, interested_in, placements_required, know_about, know_about_other,
                        candidate_signature_path, counselor_signature_path,
                        status, handled_by, remarks,
                        created_by, ip_address, user_agent, created_at, updated_at
                    ) VALUES (
                        :enquiry_date, :enquiry_no,
                        :branch_id, :name, :phone, :email, :dob, :gender, :profession, :address, :instagram_id, :course_interest,
                        :qualification, :year_of_passout, :college, :percentage_marks, :software_languages_known,
                        :father_name, :father_occupation, :father_contact_no,
                        :technologies, :interested_in, :placements_required, :know_about, :know_about_other,
                        :candidate_signature_path, :counselor_signature_path,
                        :status, :handled_by, :remarks,
                        :created_by, :ip_address, :user_agent, NOW(), NOW()
                    )
                ");

                $st->execute([
                    ':enquiry_date'=>$enquiry_date,
                    ':enquiry_no'=>$enquiry_no,
                    ':branch_id'=>$branchId,
                    ':name'=>$name,
                    ':phone'=>$phone,
                    ':email'=>$email,
                    ':dob'=>$dob,
                    ':gender'=>$gender,
                    ':profession'=>$profession,
                    ':address'=>$address,
                    ':instagram_id'=>$instagram_id,
                    ':course_interest'=>$course_interest,

                    ':qualification'=>$qualification,
                    ':year_of_passout'=>$year_of_passout,
                    ':college'=>$college,
                    ':percentage_marks'=>$percentage_marks,
                    ':software_languages_known'=>$software_languages_known,

                    ':father_name'=>$father_name,
                    ':father_occupation'=>$father_occupation,
                    ':father_contact_no'=>$father_contact_no,

                    ':technologies'=>$technologies,
                    ':interested_in'=>$interested_in,
                    ':placements_required'=>$placements_required,
                    ':know_about'=>$know_about,
                    ':know_about_other'=>$know_about_other,

                    ':candidate_signature_path'=>$candidate_signature_path,
                    ':counselor_signature_path'=>$counselor_signature_path,

                    ':status'=>$status,
                    ':handled_by'=>$handledBy,
                    ':remarks'=>$remarks,

                    ':created_by'=>$userId,
                    ':ip_address'=>$_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent'=>$_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);

                $newEnquiryId = $pdo->lastInsertId();

                $pdo->commit();

                $success = "Enquiry saved successfully!";

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$defaultEnqNo = '';
try { $defaultEnqNo = generateEnquiryNo($pdo); } catch (Exception $e) { $defaultEnqNo=''; }

// ---------- Prefill Values ----------
$prefill = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'course_interest' => '',
    'handled_by' => 0,
    'know_about_other' => '',
];

if ($leadRow) {
    $prefill['name'] = (string)($leadRow['name'] ?? '');
    $prefill['phone'] = (string)($leadRow['phone'] ?? '');
    $prefill['email'] = (string)($leadRow['email'] ?? '');
    $prefill['course_interest'] = (string)($leadRow['course_interest'] ?? '');
    $prefill['handled_by'] = (int)($leadRow['assigned_to'] ?? 0);
    $prefill['know_about_other'] = (string)($leadRow['source'] ?? '');
}

$techSelected = isset($_POST['technologies']) && is_array($_POST['technologies']) ? $_POST['technologies'] : [];
$intSelected  = isset($_POST['interested_in']) && is_array($_POST['interested_in']) ? $_POST['interested_in'] : [];
$kaSelected   = isset($_POST['know_about']) && is_array($_POST['know_about']) ? $_POST['know_about'] : [];

// If opened from lead and no POST yet, preselect "Other" for source text
if ($leadRow && empty($_POST)) {
    $kaSelected = ['Other'];
}
?>

<style>
.wizard { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.wstep {
  flex:1; min-width:180px;
  padding:12px 14px;
  border:1px solid #eee;
  border-radius:14px;
  background:#fff;
  cursor:pointer;
  display:flex; align-items:center; justify-content:space-between;
}
.wstep b { font-size:14px; }
.wstep small { color: var(--text-light); }
.wstep.active {
  border-color: rgba(233,30,99,.35);
  box-shadow: 0 8px 24px rgba(233,30,99,.08);
}
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
  width:100%;
  padding:10px 12px;
  border:1px solid #e5e7eb;
  border-radius:12px;
  outline:none;
  background:#fff;
}
input:focus, textarea:focus, select:focus {
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.12);
}
.hint { color: var(--text-light); font-size:12px; margin-top:6px; display:block; }

.form-grid{
  display:grid;
  grid-template-columns: 1fr 1fr;
  gap:14px;
}
.form-grid .full{ grid-column: 1 / -1; }
@media (max-width: 992px){
  .form-grid{ grid-template-columns: 1fr; }
}

select[multiple]{
  min-height: 150px;
  padding: 10px 12px;
  background: linear-gradient(180deg, #ffffff 0%, #fff7fb 100%);
  border: 1px solid #f1c2d4;
  border-radius: 14px;
}
select[multiple]:focus{
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.12);
}

.toggle { display:flex; align-items:center; gap:10px; user-select:none; }
.toggle input { display:none; }
.toggle span {
  width:46px; height:26px; border-radius:999px;
  background:#e5e7eb; position:relative; display:inline-block;
  transition:.2s;
}
.toggle span::after{
  content:""; width:22px; height:22px; border-radius:50%;
  background:#fff; position:absolute; top:2px; left:2px;
  box-shadow:0 4px 10px rgba(0,0,0,.12);
  transition:.2s;
}
.toggle input:checked + span{ background: rgba(233,30,99,.55); }
.toggle input:checked + span::after{ left:22px; }

.wbtns { display:flex; gap:10px; justify-content:flex-end; margin-top:14px; flex-wrap:wrap; }
.wbtn { padding:10px 14px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
.wbtn.primary { background: var(--primary); color:#fff; border-color: transparent; }
.wbtn.primary:hover { background: var(--primary-dark); }
.wbtn:hover { box-shadow: 0 8px 22px rgba(0,0,0,.06); }

.upload-box{
  position:relative;
  border:1.5px dashed #f1c2d4;
  background:#fff7fb;
  border-radius:14px;
  padding:14px;
  display:flex;
  align-items:center;
  gap:12px;
  transition:.2s;
  cursor:pointer;
}
.upload-box:hover{
  border-color: rgba(233,30,99,.55);
  box-shadow: 0 0 0 4px rgba(233,30,99,.10);
}
.upload-ico{
  width:44px; height:44px;
  border-radius:14px;
  background: rgba(233,30,99,.12);
  color: var(--primary);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:18px;
  font-weight:900;
  flex:0 0 44px;
}
.upload-text b{ display:block; font-size:13px; }
.upload-text small{ display:block; color: var(--text-light); font-size:12px; margin-top:2px; }
.upload-file{
  position:absolute;
  inset:0;
  opacity:0;
  cursor:pointer;
}
.file-name{
  margin-top:6px;
  font-size:12px;
  color: var(--text-light);
}

.prefill-banner{
  margin-bottom:14px;
  border:1px solid rgba(46,125,50,.18);
  background:linear-gradient(135deg, rgba(46,125,50,.08), rgba(3,169,244,.05));
  border-radius:16px;
  padding:12px 14px;
  display:flex;
  align-items:flex-start;
  gap:12px;
}
.prefill-banner .ico{
  width:42px; height:42px;
  border-radius:12px;
  background:rgba(46,125,50,.12);
  color:#2e7d32;
  display:flex;
  align-items:center;
  justify-content:center;
  flex:0 0 42px;
  font-weight:900;
}
.prefill-banner .ttl{
  font-weight:900;
  color:#111;
}
.prefill-banner .txt{
  font-size:12px;
  color:var(--text-light);
  margin-top:3px;
}
</style>

<h2 style="margin-bottom:16px;">Add Enquiry</h2>

<?php if (!empty($error) && $error === "Access denied for this role."): ?>
  <div class="alert alert-danger" style="border-radius:12px;">
    <?= htmlspecialchars($error) ?>
  </div>
  <?php return; ?>
<?php endif; ?>

<?php if ($success): ?>
<?php if ($success): ?>
<script>
Swal.fire({
  icon: 'success',
  title: 'Success',
  text: '<?= addslashes($success) ?>',
  confirmButtonColor: '#e91e63'
}).then(()=> {

  const enquiryId = <?= (int)$newEnquiryId ?>;

  window.location.href =
    "index.php?page=enquiries/followups&ui=add&enquiry_id=" + enquiryId;

});
</script>
<?php endif; ?>
<?php endif; ?>

<?php if ($error && $error !== "Access denied for this role."): ?>
<script>
Swal.fire({
  icon: 'error',
  title: 'Error',
  text: '<?= addslashes($error) ?>',
  confirmButtonColor: '#e91e63'
});
</script>
<?php endif; ?>

<div class="card">
  <div class="card-header">Enquiry Wizard</div>

  <form method="POST" enctype="multipart/form-data" style="padding:14px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
    <?php if ($leadId > 0): ?>
      <input type="hidden" name="lead_id" value="<?= (int)$leadId ?>">
    <?php endif; ?>

    <?php if ($leadRow): ?>
      <div class="prefill-banner">
        <div class="ico">↗</div>
        <div>
          <div class="ttl">Lead conversion mode</div>
          <div class="txt">
            This enquiry is being created from lead:
            <b><?= htmlspecialchars($leadRow['name'] ?? '') ?></b>
            <?php if (!empty($leadRow['source'])): ?> • Source: <?= htmlspecialchars($leadRow['source']) ?><?php endif; ?>
            <?php if (!empty($leadRow['assigned_to'])): ?> • Assigned staff will be used as enquiry owner<?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="wizard">
      <div class="wstep active" data-step="1">
        <div><b>Step 1</b><br><small>Basic Details</small></div>
        <div class="num">1</div>
      </div>
      <div class="wstep" data-step="2">
        <div><b>Step 2</b><br><small>Education + Parent</small></div>
        <div class="num">2</div>
      </div>
      <div class="wstep" data-step="3">
        <div><b>Step 3</b><br><small>Tech + Source</small></div>
        <div class="num">3</div>
      </div>
    </div>

    <!-- Step 1 -->
    <div class="wpanel active" data-panel="1">
      <div class="form-grid">
        <div class="form-group">
          <label>Enquiry Date</label>
          <input type="date" name="enquiry_date" value="<?= htmlspecialchars($_POST['enquiry_date'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="form-group">
          <label>Enquiry No</label>
          <input type="text" name="enquiry_no" value="<?= htmlspecialchars($_POST['enquiry_no'] ?? $defaultEnqNo) ?>">
          <span class="hint">Auto generated, you can edit.</span>
        </div>

        <div class="form-group full">
          <label>Handled By (Front Office)</label>
          <select name="handled_by">
            <option value="">-- Auto Assign --</option>
            <?php foreach ($frontOfficeUsers as $u): ?>
              <?php
                $selectedHandled = (int)($_POST['handled_by'] ?? ($prefill['handled_by'] ?? 0));
              ?>
              <option value="<?= (int)$u['id'] ?>" <?= ($selectedHandled === (int)$u['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Name <span style="color:red;">*</span></label>
          <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $prefill['name']) ?>">
        </div>

        <div class="form-group">
          <label>Phone<span style="color:red;">*</span></label>
          <input type="text" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? $prefill['phone']) ?>" placeholder="+919876543210">
          <span class="hint">Digits only — example: +91XXXXXXXXXX</span>
        </div>

        <div class="form-group">
          <label>Email<span style="color:red;">*</span></label>
          <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $prefill['email']) ?>" placeholder="name@company.com">
        </div>

        <div class="form-group">
          <label>DOB</label>
          <input type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Gender</label>
          <select name="gender">
            <option value="">-- Select --</option>
            <option value="male" <?= (($_POST['gender'] ?? '')==='male')?'selected':''; ?>>Male</option>
            <option value="female" <?= (($_POST['gender'] ?? '')==='female')?'selected':''; ?>>Female</option>
            <option value="other" <?= (($_POST['gender'] ?? '')==='other')?'selected':''; ?>>Other</option>
          </select>
        </div>

        <div class="form-group full">
          <label>Profession</label>
          <input type="text" name="profession" value="<?= htmlspecialchars($_POST['profession'] ?? '') ?>">
        </div>

        <div class="form-group full">
          <label>Address</label>
          <textarea name="address" rows="3"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Instagram ID</label>
          <input type="text" name="instagram_id" value="<?= htmlspecialchars($_POST['instagram_id'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Course Interest</label>
          <input type="text" name="course_interest" value="<?= htmlspecialchars($_POST['course_interest'] ?? $prefill['course_interest']) ?>">
        </div>
      </div>

      <div class="wbtns">
        <button type="button" class="wbtn primary" data-next="2">Next</button>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="wpanel" data-panel="2">
      <div class="form-grid">
        <div class="form-group">
          <label>Qualification</label>
          <input type="text" name="qualification" value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Year of Passout</label>
          <input type="number" name="year_of_passout" min="1990" max="2100" value="<?= htmlspecialchars($_POST['year_of_passout'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>% Marks</label>
          <input type="text" name="percentage_marks" value="<?= htmlspecialchars($_POST['percentage_marks'] ?? '') ?>">
        </div>

        <div class="form-group full">
          <label>College</label>
          <input type="text" name="college" value="<?= htmlspecialchars($_POST['college'] ?? '') ?>">
        </div>

        <div class="form-group full">
          <label class="toggle">
            <input type="checkbox" name="placements_required" value="1" <?= isset($_POST['placements_required'])?'checked':''; ?>>
            <span></span>
            <div><b>Placements Required?</b><br><small style="color:var(--text-light);">Enable if needed</small></div>
          </label>
        </div>

        <div class="form-group full">
          <label>Software Languages Known</label>
          <textarea name="software_languages_known" rows="3"><?= htmlspecialchars($_POST['software_languages_known'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Father Name</label>
          <input type="text" name="father_name" value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Father Occupation</label>
          <input type="text" name="father_occupation" value="<?= htmlspecialchars($_POST['father_occupation'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Father Contact No</label>
          <input type="text" name="father_contact_no" value="<?= htmlspecialchars($_POST['father_contact_no'] ?? '') ?>">
        </div>
      </div>

      <div class="wbtns">
        <button type="button" class="wbtn primary" data-next="1">← Back</button>
        <button type="button" class="wbtn primary" data-next="3">Next →</button>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="wpanel" data-panel="3">
      <div class="form-grid">

        <div class="form-group">
          <label>Technologies (multi)</label>
          <select name="technologies[]" multiple>
            <?php
            $techOptions = ['Artificial Intelligence','Data Science','Full Stack Web Development','Web Designing','Python','Java','PHP & MySQL','Tally','MS Office','Digital Marketing'];
            foreach ($techOptions as $op):
            ?>
              <option value="<?= htmlspecialchars($op) ?>" <?= in_array($op, $techSelected, true) ? 'selected' : '' ?>>
                <?= htmlspecialchars($op) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="hint">Hold Ctrl/Cmd for multi select</span>
        </div>

        <div class="form-group">
          <label>Interested In (multi)</label>
          <select name="interested_in[]" multiple>
            <?php
            $intOptions = ['Technology Training','Internship','Placement Assistance','Project Development'];
            foreach ($intOptions as $op):
            ?>
              <option value="<?= htmlspecialchars($op) ?>" <?= in_array($op, $intSelected, true) ? 'selected' : '' ?>>
                <?= htmlspecialchars($op) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group full">
          <label>How did you know about ATS? (multi)</label>
          <select name="know_about[]" multiple>
            <?php
            $kaOptions = ['Website','Google Search','Instagram','Facebook','Friends/Reference','Walk-in','Other'];
            foreach ($kaOptions as $op):
            ?>
              <option value="<?= htmlspecialchars($op) ?>" <?= in_array($op, $kaSelected, true) ? 'selected' : '' ?>>
                <?= htmlspecialchars($op) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group full">
          <label>Other Source</label>
          <input type="text" name="know_about_other" value="<?= htmlspecialchars($_POST['know_about_other'] ?? $prefill['know_about_other']) ?>">
        </div>

        <div class="form-group">
          <label>Candidate Signature (optional)</label>

          <label class="upload-box">
            <div class="upload-ico">↑</div>
            <div class="upload-text">
              <b>Upload Candidate Signature</b>
              <small>JPG/PNG only • Max 2MB</small>
            </div>
            <input class="upload-file" type="file" name="candidate_signature" accept=".jpg,.jpeg,.png"
                   onchange="document.getElementById('candFileName').innerText=this.files[0]?this.files[0].name:'No file selected';">
          </label>
          <div class="file-name" id="candFileName">No file selected</div>
        </div>

        <div class="form-group">
          <label>Counselor Signature (optional)</label>

          <label class="upload-box">
            <div class="upload-ico">↑</div>
            <div class="upload-text">
              <b>Upload Counselor Signature</b>
              <small>JPG/PNG only • Max 2MB</small>
            </div>
            <input class="upload-file" type="file" name="counselor_signature" accept=".jpg,.jpeg,.png"
                   onchange="document.getElementById('counFileName').innerText=this.files[0]?this.files[0].name:'No file selected';">
          </label>
          <div class="file-name" id="counFileName">No file selected</div>
        </div>

        <div class="form-group full">
          <label>Remarks</label>
          <textarea name="remarks" rows="4"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
        </div>

      </div>

      <div class="wbtns">
        <button type="button" class="wbtn primary" data-next="1">← Back</button>
        <button type="submit" name="save_enquiry" class="wbtn primary">Save Enquiry</button>
      </div>
    </div>

  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){

  function clean(v){ return (v || '').toString().trim(); }

  function isValidPhone(phone){
    phone = clean(phone).replace(/\s+/g,'');
    return /^\+?[0-9]{7,15}$/.test(phone);
  }

  function isValidEmail(email){
    email = clean(email);
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  }

  function getVal(name){
    return clean(document.querySelector(`[name="${name}"]`)?.value);
  }

  function getMulti(name){
    const el = document.querySelector(`[name="${name}[]"]`);
    if (!el) return [];
    return Array.from(el.selectedOptions).map(o => o.value).filter(Boolean);
  }

  function getFile(name){
    const el = document.querySelector(`[name="${name}"]`);
    return el && el.files && el.files[0] ? el.files[0] : null;
  }

  const rules = {
    name_required: true,
    phone_required: true,
    email_required: true,
    qualification_required: false,
    year_required: false,
    marks_required: false,
    technologies_required: false,
    interested_in_required: false,
    know_about_required: false,
    signature_max_mb: 2,
    signature_allowed: ['jpg','jpeg','png']
  };

  function validateAll(){
    const errors = [];

    const name  = getVal('name');
    const phone = getVal('phone');
    const email = getVal('email');

    if (rules.name_required && !name) errors.push("Name is required.");
    if (rules.phone_required && !phone) errors.push("Phone is required.");
    if (rules.email_required && !email) errors.push("Email is required.");

    if (phone && !isValidPhone(phone)) errors.push("Phone format invalid (example: +919876543210).");
    if (email && !isValidEmail(email)) errors.push("Email format invalid (example: name@company.com).");

    const qualification = getVal('qualification');
    const year = getVal('year_of_passout');
    const marks = getVal('percentage_marks');
    const fatherPhone = getVal('father_contact_no');

    if (rules.qualification_required && !qualification) errors.push("Qualification is required.");

    if (rules.year_required && !year) errors.push("Year of passout is required.");
    if (year){
      const y = parseInt(year,10);
      if (isNaN(y) || y < 1990 || y > 2100) errors.push("Year of passout must be between 1990 and 2100.");
    }

    if (rules.marks_required && !marks) errors.push("% Marks is required.");
    if (marks){
      const m = parseFloat(marks);
      if (isNaN(m) || m < 0 || m > 100) errors.push("Percentage marks must be between 0 and 100.");
    }

    if (fatherPhone && !isValidPhone(fatherPhone)) errors.push("Father contact number format invalid.");

    const technologies = getMulti('technologies');
    const interestedIn = getMulti('interested_in');
    const knowAbout = getMulti('know_about');
    const knowOther = getVal('know_about_other');

    if (rules.technologies_required && technologies.length === 0) errors.push("Please select at least one Technology.");
    if (rules.interested_in_required && interestedIn.length === 0) errors.push("Please select at least one Interested In option.");
    if (rules.know_about_required && knowAbout.length === 0) errors.push("Please select how you came to know about ATS.");

    if (knowAbout.includes('Other') && !knowOther) errors.push("Please type 'Other Source' because you selected Other.");

    function validateSignature(file, label){
      if (!file) return;
      const ext = file.name.split('.').pop().toLowerCase();
      if (!rules.signature_allowed.includes(ext)) errors.push(`${label} must be JPG/PNG only.`);
      const sizeMb = file.size / (1024*1024);
      if (sizeMb > rules.signature_max_mb) errors.push(`${label} must be under ${rules.signature_max_mb} MB.`);
    }

    validateSignature(getFile('candidate_signature'), 'Candidate signature');
    validateSignature(getFile('counselor_signature'), 'Counselor signature');

    return errors;
  }

  function goStep(n){
    document.querySelectorAll('.wstep').forEach(s=>s.classList.remove('active'));
    document.querySelectorAll('.wpanel').forEach(p=>p.classList.remove('active'));

    const step = document.querySelector('.wstep[data-step="'+n+'"]');
    const panel = document.querySelector('.wpanel[data-panel="'+n+'"]');

    if (step) step.classList.add('active');
    if (panel) panel.classList.add('active');

    window.scrollTo({top:0, behavior:'smooth'});
  }

  document.querySelectorAll('[data-next]').forEach(btn=>{
    btn.addEventListener('click', function(){
      const target = this.getAttribute('data-next');
      const currentPanel = document.querySelector('.wpanel.active');
      const current = currentPanel ? parseInt(currentPanel.getAttribute('data-panel'),10) : 1;
      const next = parseInt(target,10);

      if (next > current){
        const errors = validateAll();
        if (errors.length){
          Swal.fire({
            icon: 'error',
            title: 'Please fix these',
            html: '<div style="text-align:left;">• ' + errors.join('<br>• ') + '</div>',
            confirmButtonColor: '#e91e63'
          });
          return;
        }
      }

      goStep(target);
    });
  });

  document.querySelectorAll('.wstep').forEach(el=>{
    el.addEventListener('click', function(){
      const target = this.getAttribute('data-step');
      const currentPanel = document.querySelector('.wpanel.active');
      const current = currentPanel ? parseInt(currentPanel.getAttribute('data-panel'),10) : 1;
      const next = parseInt(target,10);

      if (next > current){
        const errors = validateAll();
        if (errors.length){
          Swal.fire({
            icon: 'error',
            title: 'Please fix these',
            html: '<div style="text-align:left;">• ' + errors.join('<br>• ') + '</div>',
            confirmButtonColor: '#e91e63'
          });
          return;
        }
      }

      goStep(target);
    });
  });

  const form = document.querySelector('form');
  if (form){
    form.addEventListener('submit', function(e){
      const errors = validateAll();
      if (errors.length){
        e.preventDefault();
        Swal.fire({
          icon: 'error',
          title: 'Please fix these',
          html: '<div style="text-align:left;">• ' + errors.join('<br>• ') + '</div>',
          confirmButtonColor: '#e91e63'
        });
      }
    });
  }

});
</script>