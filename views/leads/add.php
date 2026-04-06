<?php
// =====================================
// Leads - Add / Edit (Product Level UI)
// Slug: leads/add
// File: views/leads/add.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = "";
$error   = "";

// Session
$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = (string)($_SESSION['role_name'] ?? '');

// Allowed roles
$allowedRoles = ['Super Admin','HR','Front Office','Staff','Marketing'];
if (!in_array($roleName, $allowedRoles, true)) {
    $error = "Access denied.";
}

// Detect Edit Mode
$leadId = (int)($_GET['id'] ?? 0);
$isEdit = $leadId > 0;

// Helpers
function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function toNull($v){ $v=trim((string)$v); return $v===''?null:$v; }

// Load lead for editing (with branch + ownership check)
$lead = [];
$roleId = (int)($_SESSION['role_id'] ?? 0);

// Check branch access
$canAllBranches = 0;
try {
    $br = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $br->execute([$roleId]);
    $canAllBranches = (int)($br->fetchColumn() ?? 0);
} catch(Exception $e) {}

if ($isEdit) {
    // Build secure query with branch + creator/assigned check
    $editSql = "SELECT * FROM leads WHERE id=?";
    $editParams = [$leadId];

    if (!$canAllBranches && $branchId > 0) {
        $editSql .= " AND branch_id=?";
        $editParams[] = $branchId;
    }

    // Non-admin roles can only edit leads they created or are assigned to
    $allowedToEditAll = ['Super Admin','HR','Marketing'];
    if (!in_array($roleName, $allowedToEditAll, true)) {
        $editSql .= " AND (created_by=? OR assigned_to=?)";
        $editParams[] = $userId;
        $editParams[] = $userId;
    }

    $editSql .= " LIMIT 1";
    $st = $pdo->prepare($editSql);
    $st->execute($editParams);
    $lead = $st->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        $error = "Lead not found or access denied.";
    }
}

// Staff list for assignment
$staff = [];
try {
    $st = $pdo->prepare("
        SELECT u.id, u.name, r.role_name
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.status = 1
          AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'corporate', 'corporate executive', 'corporte excutive', 'marketing', 'hr')
        ORDER BY r.role_name ASC, u.name ASC
    ");
    $st->execute();
    $staff = $st->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){
    $staff=[];
}

// ===========================
// Save Lead
// ===========================
if(isset($_POST['save_lead']) && empty($error)){

    $token = $_POST['csrf_token'] ?? '';
    if(!verifyCSRF($token)){
        $error="Invalid request.";
    }else{

        $name   = trim($_POST['name'] ?? '');
        $phone  = toNull($_POST['phone'] ?? '');
        $email  = toNull($_POST['email'] ?? '');
        $source = toNull($_POST['source'] ?? '');
        $course = toNull($_POST['course_interest'] ?? '');
        $companyCollege = toNull($_POST['company_college_name'] ?? '');
        $department = toNull($_POST['department'] ?? '');
        $leadYear = toNull($_POST['lead_year'] ?? '');
        $assign = (int)($_POST['assigned_to'] ?? 0);
        $remarks= toNull($_POST['remarks'] ?? '');

        if(
            $name === '' ||
            $phone === null ||
            $email === null ||
            $source === null ||
            $course === null ||
            $companyCollege === null ||
            $department === null ||
            $leadYear === null ||
            $assign <= 0
        ){
    $error="Please fill all required fields.";
}else{

            try{

                if($isEdit){

                    // Add branch restriction to UPDATE
                    $updateSql = "
                            UPDATE leads SET
                            name=:name,
                            phone=:phone,
                            email=:email,
                            source=:source,
                            course_interest=:course,
                            company_college_name=:company_college_name,
                            department=:department,
                            lead_year=:lead_year,
                            assigned_to=:assigned,
                            remarks=:remarks,
                            updated_by=:uid,
                            updated_at=NOW()
                            WHERE id=:id
                        ";

                    $updateParams = [
                        ':name'=>$name,
                        ':phone'=>$phone,
                        ':email'=>$email,
                        ':source'=>$source,
                        ':course'=>$course,
                        ':company_college_name'=>$companyCollege,
                        ':department'=>$department,
                        ':lead_year'=>$leadYear,
                        ':assigned'=>$assign,
                        ':remarks'=>$remarks,
                        ':uid'=>$userId,
                        ':id'=>$leadId
                    ];

                    if (!$canAllBranches && $branchId > 0) {
                        $updateSql .= " AND branch_id=:branch_id";
                        $updateParams[':branch_id'] = $branchId;
                    }

                    $st=$pdo->prepare($updateSql);
                    $st->execute($updateParams);

                    $success="Lead updated successfully.";

                }else{

                    $st=$pdo->prepare("
                            INSERT INTO leads
                            (branch_id,name,phone,email,source,
                            course_interest,company_college_name,department,lead_year,
                            status,assigned_to,remarks,
                            created_by,ip_address,user_agent,created_at)
                            VALUES
                            (:branch,:name,:phone,:email,:source,
                            :course,:company_college_name,:department,:lead_year,
                            'new',:assigned,:remarks,
                            :uid,:ip,:ua,NOW())
                        ");

                    $st->execute([
                        ':branch'=>$branchId,
                        ':name'=>$name,
                        ':phone'=>$phone,
                        ':email'=>$email,
                        ':source'=>$source,
                        ':course'=>$course,
                        ':company_college_name'=>$companyCollege,
                        ':department'=>$department,
                        ':lead_year'=>$leadYear,
                        ':assigned'=>$assign,
                        ':remarks'=>$remarks,
                        ':uid'=>$userId,
                        ':ip'=>$_SERVER['REMOTE_ADDR'] ?? null,
                        ':ua'=>$_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);

                    $success="Lead created successfully.";
                }

            }catch(Exception $e){
                $error="Failed to save lead. ".$e->getMessage();
            }

        }
    }
}
?>

<div class="lead-page-head">
<h2><?= $isEdit ? "Edit Lead" : "Add New Lead" ?></h2>
</div>

<?php if($success): ?>
<script>
if (window.Swal && Swal.fire) {
Swal.fire({
icon:'success',
title:'Success',
text:'<?= addslashes($success) ?>',
confirmButtonColor:'#e91e63'
}).then(()=>{
window.location.href="index.php?page=leads/list";
});
} else {
alert('<?= addslashes($success) ?>');
window.location.href="index.php?page=leads/list";
}
</script>
<?php endif; ?>

<?php if($error): ?>
<script>
if (window.Swal && Swal.fire) {
Swal.fire({
icon:'error',
title:'Error',
text:'<?= addslashes($error) ?>',
confirmButtonColor:'#e91e63'
});
} else {
alert('<?= addslashes($error) ?>');
}
</script>
<?php endif; ?>

<div class="lead-card">
<div class="lead-card-head">
<div class="lead-card-title">
<i class="fas fa-<?= $isEdit ? 'pen' : 'plus-circle' ?>" style="margin-right:8px;"></i>
<?= $isEdit ? "Edit Lead Information" : "Add Lead Information" ?>
</div>
</div>

<div class="lead-card-body">
<form method="POST" id="leadForm" novalidate>

<input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">

<div class="form-grid">

<div class="form-group">
<label>Name *</label>
<input type="text" name="name" required
placeholder="Example: John Smith"
value="<?= h($_POST['name'] ?? $lead['name'] ?? '') ?>">
</div>

<div class="form-group">
<label>Phone <span style="color:red;">*</span></label>
<input type="tel" name="phone" required maxlength="10" inputmode="numeric" pattern="[0-9]{10}" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)"
placeholder="Example: 9876543210"
value="<?= h($_POST['phone'] ?? $lead['phone'] ?? '') ?>">
</div>

<div class="form-group">
<label>Email <span style="color:red;">*</span></label>
<input type="email" name="email" required
placeholder="Example: john@gmail.com"
value="<?= h($_POST['email'] ?? $lead['email'] ?? '') ?>">
</div>

<div class="form-group">
<label>Lead Source <span style="color:red;">*</span></label>
<?php $selectedSource = (string)($_POST['source'] ?? $lead['source'] ?? ''); ?>
<select name="source">
<option value="">Select</option>
<option value="Website" <?= $selectedSource==='Website'?'selected':'' ?>>Website</option>
<option value="Facebook" <?= $selectedSource==='Facebook'?'selected':'' ?>>Facebook</option>
<option value="Instagram" <?= $selectedSource==='Instagram'?'selected':'' ?>>Instagram</option>
<option value="Google Ads" <?= $selectedSource==='Google Ads'?'selected':'' ?>>Google Ads</option>
<option value="Walk-in" <?= $selectedSource==='Walk-in'?'selected':'' ?>>Walk-in</option>
<option value="Reference" <?= $selectedSource==='Reference'?'selected':'' ?>>Reference</option>
</select>
</div>

<div class="form-group">
<label>Interest <span style="color:red;">*</span></label>
<input type="text" name="course_interest"
placeholder="Example: Data Science"
value="<?= h($_POST['course_interest'] ?? $lead['course_interest'] ?? '') ?>">
</div>

<div class="form-group">
<label>Company / College Name <span style="color:red;">*</span></label>
<input type="text" name="company_college_name"
placeholder="Example: XYZ College"
value="<?= h($_POST['company_college_name'] ?? $lead['company_college_name'] ?? '') ?>">
</div>

<div class="form-group">
<label>Department <span style="color:red;">*</span></label>
<input type="text" name="department"
placeholder="Example: Computer Science"
value="<?= h($_POST['department'] ?? $lead['department'] ?? '') ?>">
</div>

<div class="form-group">
<label>Year <span style="color:red;">*</span></label>
<input type="text" name="lead_year"
placeholder="Ex: 2026 or Final Year"
value="<?= h($_POST['lead_year'] ?? $lead['lead_year'] ?? '') ?>">
</div>

<div class="form-group">
<label>Assign To <span style="color:red;">*</span></label>
<select name="assigned_to">
<option value="">Select Staff</option>

<?php foreach($staff as $s): ?>

<option value="<?= $s['id'] ?>"
<?= (($lead['assigned_to'] ?? '')==$s['id'])?'selected':'' ?>>

<?= h($s['name']) ?> (<?= h($s['role_name']) ?>)

</option>

<?php endforeach; ?>

</select>
</div>

<div class="form-group full">
<label>Remarks</label>
<textarea name="remarks" rows="4" placeholder="Write short notes about this lead..."><?= h($_POST['remarks'] ?? $lead['remarks'] ?? '') ?></textarea>
</div>

</div>

<div class="form-actions">
<button type="submit" name="save_lead" id="saveLeadBtn" class="lead-btn-primary" disabled>
<?= $isEdit ? "Update Lead" : "Save Lead" ?>
</button>

<a href="index.php?page=leads/list" class="lead-btn-light">
Cancel
</a>
</div>

</form>
</div>
</div>

<style>
:root{
--lead-primary:#e91e63;
--lead-primary-dark:#c2185b;
--lead-border:#ead1df;
--lead-soft:#fff4fa;
--lead-muted:#6b7280;
--lead-shadow:0 8px 18px rgba(0,0,0,.06);
}

.lead-page-head{
margin-bottom:12px;
}

.lead-page-head h2{
margin:0;
color:#be185d;
font-weight:800;
}

.lead-card{
background:#fff;
border:1px solid var(--lead-border);
border-radius:14px;
box-shadow:var(--lead-shadow);
overflow:hidden;
}

.lead-card-head{
padding:12px 14px;
border-bottom:1px solid var(--lead-border);
background:var(--lead-soft);
}

.lead-card-title{
margin:0;
font-size:1rem;
font-weight:800;
color:#be185d;
display:inline-flex;
align-items:center;
}

.lead-card-body{
padding:14px;
}

.form-grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:16px;
}

.form-grid .full{
grid-column:1/-1;
}

.form-group label{
font-size:.74rem;
font-weight:700;
text-transform:uppercase;
letter-spacing:.3px;
color:var(--lead-muted);
margin-bottom:6px;
display:block;
}

input,select,textarea{
width:100%;
min-height:40px;
padding:9px 10px;
border-radius:9px;
border:1px solid var(--lead-border);
font-size:.88rem;
outline:none;
background:#fff;
transition:border-color .2s ease, box-shadow .2s ease;
}

input:focus,select:focus,textarea:focus{
border-color:var(--lead-primary);
box-shadow:0 0 0 3px rgba(233,30,99,.15);
}

.form-actions{
margin-top:16px;
display:flex;
gap:10px;
flex-wrap:wrap;
}

.lead-btn-primary{
background:linear-gradient(135deg,#ff4d8d,#e91e63);
color:#fff;
padding:10px 16px;
border-radius:10px;
border:none;
cursor:pointer;
font-weight:700;
min-height:40px;
display:inline-flex;
align-items:center;
justify-content:center;
text-decoration:none;
transition:all .2s ease;
}

.lead-btn-primary:hover{
background:var(--lead-primary-dark);
transform:translateY(-1px);
}

.lead-btn-primary:disabled{
opacity:.58;
cursor:not-allowed;
transform:none;
box-shadow:none;
}

.lead-btn-light{
background:#fff;
border:1px solid var(--lead-border);
padding:10px 16px;
border-radius:10px;
text-decoration:none;
color:#374151;
font-weight:700;
min-height:40px;
display:inline-flex;
align-items:center;
justify-content:center;
}

@media(max-width:900px){
.form-grid{ grid-template-columns:1fr; }
.form-actions{ flex-direction:column; }
.lead-btn-primary,.lead-btn-light{ width:100%; }
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

<script>
document.addEventListener("DOMContentLoaded", function(){

    const form = document.getElementById("leadForm");
    if(!form) return;
    const saveBtn = document.getElementById("saveLeadBtn");
    const reqName = form.querySelector('[name="name"]');
    const reqPhone = form.querySelector('[name="phone"]');
    const reqEmail = form.querySelector('[name="email"]');
    const reqSource = form.querySelector('[name="source"]');
    const reqInterest = form.querySelector('[name="course_interest"]');
    const reqCompanyCollege = form.querySelector('[name="company_college_name"]');
    const reqDepartment = form.querySelector('[name="department"]');
    const reqLeadYear = form.querySelector('[name="lead_year"]');
    const reqAssignedTo = form.querySelector('[name="assigned_to"]');
    const showWarn = function(title, text){
        if(window.Swal && Swal.fire){
            Swal.fire({
                icon:'warning',
                title:title,
                text:text,
                confirmButtonColor:'#e91e63'
            });
        }else{
            alert(text);
        }
    };

    const syncSaveState = function(){
        if(!saveBtn) return;
        const nameOk = !!(reqName && reqName.value.trim()!=="");
        const phoneOk = !!(reqPhone && reqPhone.value.trim()!=="");
        const emailOk = !!(reqEmail && reqEmail.value.trim()!=="");
        const sourceOk = !!(reqSource && reqSource.value.trim()!=="");
        const interestOk = !!(reqInterest && reqInterest.value.trim()!=="");
        const companyCollegeOk = !!(reqCompanyCollege && reqCompanyCollege.value.trim()!=="");
        const departmentOk = !!(reqDepartment && reqDepartment.value.trim()!=="");
        const leadYearOk = !!(reqLeadYear && reqLeadYear.value.trim()!=="");
        const assignedOk = !!(reqAssignedTo && reqAssignedTo.value.trim()!=="");
        saveBtn.disabled = !(nameOk && phoneOk && emailOk && sourceOk && interestOk && companyCollegeOk && departmentOk && leadYearOk && assignedOk);
    };

    [reqName, reqPhone, reqEmail, reqSource, reqInterest, reqCompanyCollege, reqDepartment, reqLeadYear, reqAssignedTo].forEach(function(inp){
        if(inp){
            inp.addEventListener("input", syncSaveState);
            inp.addEventListener("change", syncSaveState);
        }
    });
    syncSaveState();

    form.addEventListener("submit", function(e){

        let name = form.querySelector('[name="name"]').value.trim();
        let phone = form.querySelector('[name="phone"]').value.trim();
        let email = form.querySelector('[name="email"]').value.trim();
        let source = form.querySelector('[name="source"]').value.trim();
        let interest = form.querySelector('[name="course_interest"]').value.trim();
        let companyCollege = form.querySelector('[name="company_college_name"]').value.trim();
        let department = form.querySelector('[name="department"]').value.trim();
        let leadYear = form.querySelector('[name="lead_year"]').value.trim();
        let assignedTo = form.querySelector('[name="assigned_to"]').value.trim();

        // Name check
        if(name === ""){
            e.preventDefault();
            showWarn('Validation Error','Name is required');
            return;
        }

        // Phone check
        if(phone === ""){
            e.preventDefault();
            showWarn('Validation Error','Phone number is required');
            return;
        }

        // Phone format (10 digits basic)
        if(!/^[0-9]{10}$/.test(phone)){
            e.preventDefault();
            showWarn('Invalid Phone','Enter valid 10 digit phone number');
            return;
        }

        // Email check
        if(email === ""){
            e.preventDefault();
            showWarn('Validation Error','Email is required');
            return;
        }

        // Email format
        let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if(!emailPattern.test(email)){
            e.preventDefault();
            showWarn('Invalid Email','Enter valid email address');
            return;
        }

        if(source === ""){
            e.preventDefault();
            showWarn('Validation Error','Lead Source is required');
            return;
        }

        if(interest === ""){
            e.preventDefault();
            showWarn('Validation Error','Interest is required');
            return;
        }

        if(companyCollege === ""){
            e.preventDefault();
            showWarn('Validation Error','Company / College Name is required');
            return;
        }

        if(department === ""){
            e.preventDefault();
            showWarn('Validation Error','Department is required');
            return;
        }

        if(leadYear === ""){
            e.preventDefault();
            showWarn('Validation Error','Year is required');
            return;
        }

        if(assignedTo === ""){
            e.preventDefault();
            showWarn('Validation Error','Assign To is required');
            return;
        }

    });

});
</script>
