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
        $p = preg_replace('/\D+/', '', $phone);
        return (bool)preg_match('/^\d{10}$/', $p);
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_enquiry']) && empty($error)) {

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
        $parent_email      = toNull($_POST['parent_email'] ?? '');

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
        } elseif ($phone === null || $phone === '') {
            $error = "Phone number is required.";
        } elseif (!isValidPhone($phone)) {
            $error = "Phone number must be exactly 10 digits.";
        } elseif ($email === null || $email === '') {
            $error = "Email is required.";
        } elseif (!isValidEmail($email)) {
            $error = "Invalid email.";
        } elseif ($father_name === null || $father_name === '') {
            $error = "Parent name is required.";
        } elseif ($father_occupation === null || $father_occupation === '') {
            $error = "Parent occupation is required.";
        } elseif ($father_contact_no === null || $father_contact_no === '') {
            $error = "Parent number is required.";
        } elseif (!isValidPhone($father_contact_no)) {
            $error = "Parent number must be exactly 10 digits.";
        } elseif ($parent_email === null || $parent_email === '') {
            $error = "Parent email is required.";
        } elseif (!isValidEmail($parent_email)) {
            $error = "Parent email is invalid.";
        }

        // ===============================
        // FILE UPLOAD
        // ===============================

        $candidate_signature_path = null;
        $counselor_signature_path = null;

        if (empty($error)) {
            $cand = uploadSignature($_FILES['candidate_signature'] ?? null, 'enquiries');
            if ($cand === '__ERROR__SIZE__') {
                $error = "Candidate signature must be under 2 MB.";
            } elseif ($cand === '__ERROR__TYPE__') {
                $error = "Candidate signature must be JPG or PNG.";
            } elseif ($cand === '__ERROR__UPLOAD__') {
                $error = "Failed to upload candidate signature.";
            } elseif ($cand && !str_starts_with($cand, '__ERROR__')) {
                $candidate_signature_path = $cand;
            }
        }

        if (empty($error)) {
            $coun = uploadSignature($_FILES['counselor_signature'] ?? null, 'enquiries');
            if ($coun === '__ERROR__SIZE__') {
                $error = "Counselor signature must be under 2 MB.";
            } elseif ($coun === '__ERROR__TYPE__') {
                $error = "Counselor signature must be JPG or PNG.";
            } elseif ($coun === '__ERROR__UPLOAD__') {
                $error = "Failed to upload counselor signature.";
            } elseif ($coun && !str_starts_with($coun, '__ERROR__')) {
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
                        father_name, father_occupation, father_contact_no, parent_email,
                        technologies, interested_in, placements_required, know_about, know_about_other,
                        candidate_signature_path, counselor_signature_path,
                        status, handled_by, remarks,
                        created_by, ip_address, user_agent, created_at, updated_at
                    ) VALUES (
                        :enquiry_date, :enquiry_no,
                        :branch_id, :name, :phone, :email, :dob, :gender, :profession, :address, :instagram_id, :course_interest,
                        :qualification, :year_of_passout, :college, :percentage_marks, :software_languages_known,
                        :father_name, :father_occupation, :father_contact_no, :parent_email,
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
                    ':parent_email'=>$parent_email,

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

                if ($leadId > 0) {
                    $leadUpd = $pdo->prepare("
                        UPDATE leads
                        SET
                            status='converted',
                            updated_by=?,
                            updated_at=NOW()
                        WHERE id=?
                    ");
                    $leadUpd->execute([$userId, $leadId]);
                }

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
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/lead.css">

<style>
.enq-page-head{ margin-bottom:12px; }
.enq-page-title{ margin:0; color:#be185d; font-weight:800; }

.card{
  border:1px solid #f1d6e3;
  border-radius:14px;
  overflow:hidden;
  box-shadow:0 8px 18px rgba(0,0,0,.06);
}
.card-header{
  padding:12px 14px;
  border-bottom:1px solid #f1d6e3;
  background:#fff4fa;
  color:#be185d;
  font-weight:800;
}

.wizard { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.wstep {
  flex:1; min-width:180px;
  padding:12px 14px;
  border:1px solid #f1d6e3;
  border-radius:14px;
  background:#fff;
  cursor:pointer;
  display:flex; align-items:center; justify-content:space-between;
  transition:all .2s ease;
}
.wstep b { font-size:14px; }
.wstep small { color: var(--text-light); }
.wstep.active {
  border-color: rgba(233,30,99,.55);
  background:#fff9fc;
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

.enq-gender-group{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.enq-gender-option{
  position:relative;
  cursor:pointer;
}
.enq-gender-option input{
  position:absolute;
  opacity:0;
  pointer-events:none;
}
.enq-gender-pill{
  display:inline-flex;
  align-items:center;
  gap:6px;
  border:1px solid #f1d6e3;
  border-radius:10px;
  padding:9px 12px;
  background:#fff;
  color:#4b5563;
  font-weight:700;
  transition:all .2s ease;
}
.enq-gender-option input:checked + .enq-gender-pill{
  border-color:#e91e63;
  background:#fff1f7;
  color:#be185d;
  box-shadow:0 0 0 3px rgba(233,30,99,.12);
}

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
input::placeholder, textarea::placeholder{
  color:#9ca3af;
}

.multi-block{
  border:1px solid #f1d6e3;
  border-radius:14px;
  background:#fff;
  padding:12px;
}
.multi-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:8px;
  margin-bottom:8px;
}
.multi-title{
  font-size:13px;
  font-weight:800;
  color:#be185d;
}
.multi-tip{
  font-size:11px;
  color:#6b7280;
}
.multi-count{
  font-size:11px;
  font-weight:800;
  color:#be185d;
  background:#fff1f7;
  border:1px solid #f7c8dc;
  border-radius:999px;
  padding:3px 8px;
}
.multi-actions{
  display:flex;
  gap:8px;
  margin-bottom:8px;
}
.multi-link{
  border:1px solid #f1d6e3;
  background:#fff;
  color:#9d174d;
  font-size:12px;
  font-weight:700;
  border-radius:8px;
  padding:5px 8px;
  cursor:pointer;
}
.multi-link:hover{
  background:#fff1f7;
  border-color:#e91e63;
}
.multi-grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:8px;
}
.multi-option{
  position:relative;
  display:flex;
  align-items:center;
  gap:8px;
  border:1px solid #f1d6e3;
  border-radius:10px;
  padding:8px 10px;
  background:#fff8fc;
  cursor:pointer;
  transition:all .18s ease;
}
.multi-option input{
  position:absolute;
  opacity:0;
  pointer-events:none;
}
.multi-box{
  display:inline-block;
  width:18px;
  height:18px;
  border-radius:6px;
  border:2px solid #f1a7c5;
  background:#fff;
  flex:0 0 18px;
  position:relative;
  box-sizing:border-box;
  transition:all .2s ease;
}
.multi-box::after{
  content:"";
  position:absolute;
  left:4px;
  top:1px;
  width:5px;
  height:10px;
  border:2px solid #fff;
  border-top:0;
  border-left:0;
  transform:rotate(45deg);
  opacity:0;
}
.multi-text{
  font-size:13px;
  color:#374151;
  font-weight:700;
}
.multi-option:hover{
  border-color:#e91e63;
  background:#fff1f7;
}
.multi-option:has(input:checked){
  border-color:#e91e63;
  background:#ffeaf4;
  box-shadow:0 0 0 3px rgba(233,30,99,.09);
}
.multi-option input:checked + .multi-box{
  background:linear-gradient(135deg,#ff4d8d,#e91e63);
  border-color:#e91e63;
}
.multi-option input:checked + .multi-box::after{
  opacity:1;
}
.step3-note{
  margin-bottom:12px;
  border:1px solid #f1d6e3;
  background:#fff7fb;
  border-radius:12px;
  padding:10px 12px;
  font-size:12px;
  color:#6b7280;
}
.step3-note b{ color:#be185d; }

.placement-card{
  border:1px solid #f1d6e3;
  border-radius:14px;
  background:linear-gradient(180deg,#fff 0%,#fff7fb 100%);
  padding:12px;
}
.placement-head{
  margin-bottom:10px;
}
.placement-head b{
  display:block;
  color:#111827;
}
.placement-head small{
  color:var(--text-light);
}
.placement-pill-group{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
}
.placement-input{
  position:absolute;
  opacity:0;
  pointer-events:none;
}
.placement-pill{
  border:1px solid #f1d6e3;
  border-radius:999px;
  padding:9px 16px;
  background:#fff;
  color:#6b7280;
  font-weight:800;
  cursor:pointer;
  transition:all .2s ease;
  min-width:84px;
  text-align:center;
}
.placement-pill:hover{
  border-color:#e91e63;
  color:#be185d;
  background:#fff1f7;
}
.placement-pill.active{
  border-color:#e91e63;
  color:#be185d;
  background:linear-gradient(135deg,#fff1f7,#ffe4f0);
  box-shadow:0 0 0 3px rgba(233,30,99,.12);
}

.wbtns { display:flex; gap:10px; justify-content:flex-end; margin-top:14px; flex-wrap:wrap; }
.wbtn { padding:10px 14px; border-radius:12px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
.wbtn.primary { background: linear-gradient(135deg,#ff4d8d,#e91e63); color:#fff; border-color: transparent; }
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
@media(max-width:768px){
  .wbtns .wbtn{ width:100%; }
  .multi-grid{ grid-template-columns:1fr; }
}
</style>

<div class="enq-page-head">
  <h2 class="enq-page-title">Add Enquiry</h2>
</div>

<?php if (!empty($error) && $error === "Access denied for this role."): ?>
  <div class="alert alert-danger" style="border-radius:12px;">
    <?= htmlspecialchars($error) ?>
  </div>
  <?php return; ?>
<?php endif; ?>

<?php if ($success): ?>
<script>
if (window.Swal && Swal.fire) {
  Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= addslashes($success) ?>',
    confirmButtonColor: '#e91e63'
  }).then(()=> {
    const enquiryId = <?= (int)$newEnquiryId ?>;
    window.location.href = "index.php?page=enquiries/followups&ui=add&enquiry_id=" + enquiryId;
  });
} else {
  alert('<?= addslashes($success) ?>');
  const enquiryId = <?= (int)$newEnquiryId ?>;
  window.location.href = "index.php?page=enquiries/followups&ui=add&enquiry_id=" + enquiryId;
}
</script>
<?php endif; ?>

<?php if ($error && $error !== "Access denied for this role."): ?>
<script>
if (window.Swal && Swal.fire) {
  Swal.fire({
    icon: 'error',
    title: 'Error',
    text: '<?= addslashes($error) ?>',
    confirmButtonColor: '#e91e63'
  });
} else {
  alert('<?= addslashes($error) ?>');
}
</script>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="fas fa-layer-group"></i> Enquiry Wizard</div>

  <form method="POST" enctype="multipart/form-data" style="padding:14px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRF()) ?>">
    <input type="hidden" name="save_enquiry" value="1">
    <?php if ($leadId > 0): ?>
      <input type="hidden" name="lead_id" value="<?= (int)$leadId ?>">
    <?php endif; ?>

    <?php if ($leadRow): ?>
      <div class="prefill-banner">
        <div class="ico"><i class="fas fa-link"></i></div>
        <div>
          <div class="ttl">Lead conversion mode</div>
          <div class="txt">
            This enquiry is being created from lead:
            <b><?= htmlspecialchars($leadRow['name'] ?? '') ?></b>
            <?php if (!empty($leadRow['source'])): ?> &bull; Source: <?= htmlspecialchars($leadRow['source']) ?><?php endif; ?>
            <?php if (!empty($leadRow['assigned_to'])): ?> &bull; Assigned staff will be used as enquiry owner<?php endif; ?>
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
          <input type="text" name="enquiry_no" readonly value="<?= htmlspecialchars($_POST['enquiry_no'] ?? $defaultEnqNo) ?>">
          <span class="hint">Auto generated and locked for consistency.</span>
        </div>

        <div class="form-group">
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
          <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? $prefill['name']) ?>" placeholder="Enter full name, e.g. Rahul Kumar">
        </div>

        <div class="form-group">
          <label>Phone Number <span style="color:red;">*</span></label>
          <input type="tel" name="phone" class="js-phone-10" required maxlength="10" inputmode="numeric" pattern="[0-9]{10}" value="<?= htmlspecialchars($_POST['phone'] ?? $prefill['phone']) ?>" placeholder="Enter 10-digit phone number">
          <span class="hint">Digits only. Example: 9876543210</span>
        </div>

        <div class="form-group">
          <label>Email <span style="color:red;">*</span></label>
          <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $prefill['email']) ?>" placeholder="Enter email, e.g. student@gmail.com">
        </div>

        <div class="form-group">
          <label>DOB</label>
          <input type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label>Gender</label>
          <?php $selectedGender = (string)($_POST['gender'] ?? ''); ?>
          <div class="enq-gender-group">
            <label class="enq-gender-option">
              <input type="radio" name="gender" value="male" <?= $selectedGender==='male'?'checked':''; ?>>
              <span class="enq-gender-pill"><i class="fas fa-mars"></i> Male</span>
            </label>
            <label class="enq-gender-option">
              <input type="radio" name="gender" value="female" <?= $selectedGender==='female'?'checked':''; ?>>
              <span class="enq-gender-pill"><i class="fas fa-venus"></i> Female</span>
            </label>
            <label class="enq-gender-option">
              <input type="radio" name="gender" value="other" <?= $selectedGender==='other'?'checked':''; ?>>
              <span class="enq-gender-pill"><i class="fas fa-user"></i> Other</span>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label>Profession</label>
          <input type="text" name="profession" value="<?= htmlspecialchars($_POST['profession'] ?? '') ?>" placeholder="Enter student profession, e.g. Student">
        </div>

        <div class="form-group">
          <label>Address</label>
          <textarea name="address" rows="2" placeholder="Enter full address"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Instagram ID</label>
          <input type="text" name="instagram_id" value="<?= htmlspecialchars($_POST['instagram_id'] ?? '') ?>" placeholder="Enter Instagram ID, e.g. ats_student">
        </div>

        <div class="form-group">
          <label>Course Interest</label>
          <input type="text" name="course_interest" value="<?= htmlspecialchars($_POST['course_interest'] ?? $prefill['course_interest']) ?>" placeholder="Enter interested course, e.g. Full Stack Development">
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
          <input type="text" name="qualification" value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>" placeholder="Enter qualification, e.g. BCA">
        </div>

        <div class="form-group">
          <label>Year of Passout</label>
          <input type="number" name="year_of_passout" min="1990" max="2100" value="<?= htmlspecialchars($_POST['year_of_passout'] ?? '') ?>" placeholder="Enter passout year, e.g. 2025">
        </div>

        <div class="form-group">
          <label>% Marks</label>
          <input type="text" name="percentage_marks" value="<?= htmlspecialchars($_POST['percentage_marks'] ?? '') ?>" placeholder="Enter marks percentage, e.g. 78.5">
        </div>

        <div class="form-group">
          <label>College</label>
          <input type="text" name="college" value="<?= htmlspecialchars($_POST['college'] ?? '') ?>" placeholder="Enter college or institution name">
        </div>

        <div class="form-group">
          <div class="placement-card">
            <div class="placement-head">
              <b>Placements Required?</b>
              <small>Select whether placement support is needed</small>
            </div>
            <input class="placement-input" type="checkbox" name="placements_required" value="1" <?= isset($_POST['placements_required'])?'checked':''; ?>>
            <div class="placement-pill-group" data-placement-group>
              <button type="button" class="placement-pill <?= isset($_POST['placements_required']) ? 'active' : '' ?>" data-placement-value="yes">Yes</button>
              <button type="button" class="placement-pill <?= !isset($_POST['placements_required']) ? 'active' : '' ?>" data-placement-value="no">No</button>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Software Languages Known</label>
          <textarea name="software_languages_known" rows="2" placeholder="Example: C, C++, Java, Python"><?= htmlspecialchars($_POST['software_languages_known'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Parent Name <span style="color:red;">*</span></label>
          <input type="text" name="father_name" value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>" placeholder="Enter parent name, e.g. Suresh Kumar">
        </div>

        <div class="form-group">
          <label>Parent Occupation <span style="color:red;">*</span></label>
          <input type="text" name="father_occupation" value="<?= htmlspecialchars($_POST['father_occupation'] ?? '') ?>" placeholder="Enter occupation, e.g. Business">
        </div>

        <div class="form-group">
          <label>Parent Number <span style="color:red;">*</span></label>
          <input type="tel" name="father_contact_no" class="js-phone-10" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" value="<?= htmlspecialchars($_POST['father_contact_no'] ?? '') ?>" placeholder="Enter 10-digit parent number">
        </div>

        <div class="form-group">
          <label>Parent Email ID <span style="color:red;">*</span></label>
          <input type="email" name="parent_email" value="<?= htmlspecialchars($_POST['parent_email'] ?? '') ?>" placeholder="Enter parent email, e.g. parent@gmail.com">
        </div>
      </div>

      <div class="wbtns">
        <button type="button" class="wbtn primary" data-next="1"><i class="fas fa-arrow-left"></i> Back</button>
        <button type="button" class="wbtn primary" data-next="3">Next <i class="fas fa-arrow-right"></i></button>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="wpanel" data-panel="3">
      <div class="step3-note">
        <b>Multi-select Tip:</b> You can pick multiple options. Use quick actions to select all or clear instantly.
      </div>
      <div class="form-grid">

        <div class="form-group">
          <label>Technologies (multi)</label>
          <div class="multi-block">
            <div class="multi-head">
              <span class="multi-title">Select One or More</span>
              <span class="multi-count" data-count-for="technologies">0 selected</span>
            </div>
            <div class="multi-actions">
              <button type="button" class="multi-link" data-multi-action="all" data-multi-name="technologies">Select all</button>
              <button type="button" class="multi-link" data-multi-action="none" data-multi-name="technologies">Clear</button>
            </div>
            <div class="multi-grid">
              <?php
              $techOptions = ['Artificial Intelligence','Data Science','Full Stack Web Development','Web Designing','Python','Java','PHP & MySQL','Tally','MS Office','Digital Marketing'];
              foreach ($techOptions as $op):
              ?>
                <label class="multi-option">
                  <input type="checkbox" name="technologies[]" value="<?= htmlspecialchars($op) ?>" <?= in_array($op, $techSelected, true) ? 'checked' : '' ?>>
                  <span class="multi-box"></span>
                  <span class="multi-text"><?= htmlspecialchars($op) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Interested In (multi)</label>
          <div class="multi-block">
            <div class="multi-head">
              <span class="multi-title">Select One or More</span>
              <span class="multi-count" data-count-for="interested_in">0 selected</span>
            </div>
            <div class="multi-actions">
              <button type="button" class="multi-link" data-multi-action="all" data-multi-name="interested_in">Select all</button>
              <button type="button" class="multi-link" data-multi-action="none" data-multi-name="interested_in">Clear</button>
            </div>
            <div class="multi-grid">
              <?php
              $intOptions = ['Technology Training','Internship','Placement Assistance','Project Development'];
              foreach ($intOptions as $op):
              ?>
                <label class="multi-option">
                  <input type="checkbox" name="interested_in[]" value="<?= htmlspecialchars($op) ?>" <?= in_array($op, $intSelected, true) ? 'checked' : '' ?>>
                  <span class="multi-box"></span>
                  <span class="multi-text"><?= htmlspecialchars($op) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="form-group full">
          <label>How did you know about ATS? (multi)</label>
          <div class="multi-block">
            <div class="multi-head">
              <span class="multi-title">Select One or More</span>
              <span class="multi-count" data-count-for="know_about">0 selected</span>
            </div>
            <div class="multi-actions">
              <button type="button" class="multi-link" data-multi-action="all" data-multi-name="know_about">Select all</button>
              <button type="button" class="multi-link" data-multi-action="none" data-multi-name="know_about">Clear</button>
            </div>
            <div class="multi-grid">
              <?php
              $kaOptions = ['Website','Google Search','Instagram','Facebook','Friends/Reference','Walk-in','Other'];
              foreach ($kaOptions as $op):
              ?>
                <label class="multi-option">
                  <input type="checkbox" name="know_about[]" value="<?= htmlspecialchars($op) ?>" <?= in_array($op, $kaSelected, true) ? 'checked' : '' ?>>
                  <span class="multi-box"></span>
                  <span class="multi-text"><?= htmlspecialchars($op) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="form-group full">
          <label>Other Source</label>
          <input type="text" name="know_about_other" value="<?= htmlspecialchars($_POST['know_about_other'] ?? $prefill['know_about_other']) ?>" placeholder="Type other source if selected above">
        </div>

        <div class="form-group">
          <label>Candidate Signature (optional)</label>

          <label class="upload-box">
            <div class="upload-ico"><i class="fas fa-upload"></i></div>
            <div class="upload-text">
              <b>Upload Candidate Signature</b>
              <small>JPG/PNG only &bull; Max 2MB</small>
            </div>
            <input class="upload-file" type="file" name="candidate_signature" accept=".jpg,.jpeg,.png"
                   onchange="document.getElementById('candFileName').innerText=this.files[0]?this.files[0].name:'No file selected';">
          </label>
          <div class="file-name" id="candFileName">No file selected</div>
        </div>

        <div class="form-group">
          <label>Counselor Signature (optional)</label>

          <label class="upload-box">
            <div class="upload-ico"><i class="fas fa-upload"></i></div>
            <div class="upload-text">
              <b>Upload Counselor Signature</b>
              <small>JPG/PNG only &bull; Max 2MB</small>
            </div>
            <input class="upload-file" type="file" name="counselor_signature" accept=".jpg,.jpeg,.png"
                   onchange="document.getElementById('counFileName').innerText=this.files[0]?this.files[0].name:'No file selected';">
          </label>
          <div class="file-name" id="counFileName">No file selected</div>
        </div>

        <div class="form-group full">
          <label>Remarks</label>
          <textarea name="remarks" rows="4" placeholder="Optional note about this enquiry"><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>
        </div>

      </div>

      <div class="wbtns">
        <button type="button" class="wbtn primary" data-next="2"><i class="fas fa-arrow-left"></i> Back</button>
        <button type="submit" name="save_enquiry" class="wbtn primary">Save Enquiry</button>
      </div>
    </div>

  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", function(){

  function showSwal(type, title, htmlText){
    if (window.Swal && Swal.fire){
      return Swal.fire({
        icon: type,
        title: title,
        html: htmlText,
        confirmButtonColor: '#e91e63'
      });
    }
    const plain = (htmlText || '').replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]*>/g, '');
    alert((title ? title + "\n" : "") + plain);
    return Promise.resolve();
  }

  function showErrors(errors){
    return showSwal('error', 'Please fix these', '<div style="text-align:left;">&bull; ' + errors.join('<br>&bull; ') + '</div>');
  }

  function clean(v){ return (v || '').toString().trim(); }

  function isValidPhone(phone){
    phone = clean(phone).replace(/\D+/g,'');
    return /^\d{10}$/.test(phone);
  }

  function isValidEmail(email){
    email = clean(email);
    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email);
  }

  function getVal(name){
    return clean(document.querySelector(`[name="${name}"]`)?.value);
  }

  function getMulti(name){
    const selectEl = document.querySelector(`select[name="${name}[]"]`);
    if (selectEl) {
      return Array.from(selectEl.selectedOptions).map(o => o.value).filter(Boolean);
    }
    const checks = document.querySelectorAll(`input[name="${name}[]"]:checked`);
    return Array.from(checks).map(c => c.value).filter(Boolean);
  }

  function getFile(name){
    const el = document.querySelector(`[name="${name}"]`);
    return el && el.files && el.files[0] ? el.files[0] : null;
  }

  function updateMultiCount(name){
    const checks = document.querySelectorAll(`input[name="${name}[]"]:checked`);
    const badge = document.querySelector(`[data-count-for="${name}"]`);
    if (badge) badge.textContent = `${checks.length} selected`;
  }

  const rules = {
    name_required: true,
    phone_required: true,
    email_required: true,
    parent_name_required: true,
    parent_occupation_required: true,
    parent_phone_required: true,
    parent_email_required: true,
    qualification_required: false,
    year_required: false,
    marks_required: false,
    technologies_required: false,
    interested_in_required: false,
    know_about_required: false,
    signature_max_mb: 2,
    signature_allowed: ['jpg','jpeg','png']
  };

  ['technologies','interested_in','know_about'].forEach(function(name){
    updateMultiCount(name);
    document.querySelectorAll(`input[name="${name}[]"]`).forEach(function(cb){
      cb.addEventListener('change', function(){ updateMultiCount(name); });
    });
  });

  document.querySelectorAll('[data-multi-action]').forEach(function(btn){
    btn.addEventListener('click', function(){
      const action = this.getAttribute('data-multi-action');
      const name = this.getAttribute('data-multi-name');
      document.querySelectorAll(`input[name="${name}[]"]`).forEach(function(cb){
        cb.checked = action === 'all';
      });
      updateMultiCount(name);
    });
  });

  function collectErrors(scope){
    const errors = [];

    const name  = getVal('name');
    const phone = getVal('phone');
    const email = getVal('email');

    if (scope === 'all' || scope === '1'){
      if (rules.name_required && !name) errors.push("Name is required.");
      if (rules.phone_required && !phone) errors.push("Phone is required.");
      if (rules.email_required && !email) errors.push("Email is required.");
      if (phone && !isValidPhone(phone)) errors.push("Phone format invalid (example: +919876543210).");
      if (email && !isValidEmail(email)) errors.push("Email format invalid (example: name@company.com).");
    }

    const qualification = getVal('qualification');
    const year = getVal('year_of_passout');
    const marks = getVal('percentage_marks');
    const parentName = getVal('father_name');
    const parentOccupation = getVal('father_occupation');
    const fatherPhone = getVal('father_contact_no');
    const parentEmail = getVal('parent_email');

    if (scope === 'all' || scope === '2'){
      if (rules.parent_name_required && !parentName) errors.push("Parent name is required.");
      if (rules.parent_occupation_required && !parentOccupation) errors.push("Parent occupation is required.");
      if (rules.parent_phone_required && !fatherPhone) errors.push("Parent number is required.");
      if (rules.parent_email_required && !parentEmail) errors.push("Parent email is required.");
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
      if (fatherPhone && !isValidPhone(fatherPhone)) errors.push("Parent number must be exactly 10 digits.");
      if (parentEmail && !isValidEmail(parentEmail)) errors.push("Parent email format invalid (example: parent@gmail.com).");
    }

    const technologies = getMulti('technologies');
    const interestedIn = getMulti('interested_in');
    const knowAbout = getMulti('know_about');
    const knowOther = getVal('know_about_other');

    if (scope === 'all' || scope === '3'){
      if (rules.technologies_required && technologies.length === 0) errors.push("Please select at least one Technology.");
      if (rules.interested_in_required && interestedIn.length === 0) errors.push("Please select at least one Interested In option.");
      if (rules.know_about_required && knowAbout.length === 0) errors.push("Please select how you came to know about ATS.");
      if (knowAbout.includes('Other') && !knowOther) errors.push("Please type 'Other Source' because you selected Other.");
    }

    function validateSignature(file, label){
      if (!file) return;
      const ext = file.name.split('.').pop().toLowerCase();
      if (!rules.signature_allowed.includes(ext)) errors.push(`${label} must be JPG/PNG only.`);
      const sizeMb = file.size / (1024*1024);
      if (sizeMb > rules.signature_max_mb) errors.push(`${label} must be under ${rules.signature_max_mb} MB.`);
    }

    if (scope === 'all' || scope === '3'){
      validateSignature(getFile('candidate_signature'), 'Candidate signature');
      validateSignature(getFile('counselor_signature'), 'Counselor signature');
    }

    return errors;
  }

  function validateAll(){ return collectErrors('all'); }
  function validateStep(stepNo){ return collectErrors(String(stepNo)); }

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
        const errors = validateStep(current);
        if (errors.length){
          showErrors(errors);
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
        const errors = validateStep(current);
        if (errors.length){
          showErrors(errors);
          return;
        }
      }

      goStep(target);
    });
  });

  const form = document.querySelector('form');
  document.querySelectorAll('[data-placement-group]').forEach(function(group){
    const hiddenInput = group.parentElement.querySelector('.placement-input');
    const yesBtn = group.querySelector('[data-placement-value="yes"]');
    const noBtn = group.querySelector('[data-placement-value="no"]');

    function syncPlacementUI(isChecked){
      if (hiddenInput) hiddenInput.checked = !!isChecked;
      if (yesBtn) yesBtn.classList.toggle('active', !!isChecked);
      if (noBtn) noBtn.classList.toggle('active', !isChecked);
    }

    if (yesBtn){
      yesBtn.addEventListener('click', function(){
        syncPlacementUI(true);
      });
    }
    if (noBtn){
      noBtn.addEventListener('click', function(){
        syncPlacementUI(false);
      });
    }

    syncPlacementUI(hiddenInput && hiddenInput.checked);
  });

  document.querySelectorAll('.js-phone-10').forEach(function(input){
    input.addEventListener('input', function(){
      this.value = this.value.replace(/\D+/g, '').slice(0, 10);
    });
  });
  if (form){
    form.addEventListener('submit', function(e){
      const errors = validateAll();
      if (errors.length){
        e.preventDefault();
        showErrors(errors);
        return;
      }

      const saveBtn = form.querySelector('button[type="submit"][name="save_enquiry"]');
      if (saveBtn){
        saveBtn.disabled = true;
        saveBtn.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Saving...";
      }
    });
  }

});
</script>


