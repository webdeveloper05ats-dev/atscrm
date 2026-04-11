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


