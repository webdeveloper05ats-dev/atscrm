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

// Load lead for editing
$lead = [];

if ($isEdit) {
    $st = $pdo->prepare("SELECT * FROM leads WHERE id=? LIMIT 1");
    $st->execute([$leadId]);
    $lead = $st->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        $error = "Lead not found.";
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

        if($name==='' || $phone===null || $email===null){
    $error="Please fill Name, Phone and Email.";
}else{

            try{

                if($isEdit){

                    $st=$pdo->prepare("
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
                        ");

                    $st->execute([
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
                    ]);

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

<h2 style="margin-bottom:16px;">
<?= $isEdit ? "Edit Lead" : "Add New Lead" ?>
</h2>

<?php if($success): ?>
<script>
Swal.fire({
icon:'success',
title:'Success',
text:'<?= addslashes($success) ?>',
confirmButtonColor:'#e91e63'
}).then(()=>{
window.location.href="index.php?page=leads/list";
});
</script>
<?php endif; ?>

<?php if($error): ?>
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
<div class="card-header">
Lead Information
</div>

<form method="POST" style="padding:16px;" novalidate>

<input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">

<div class="form-grid">

<div class="form-group">
<label>Name *</label>
<input type="text" name="name" required
value="<?= h($_POST['name'] ?? $lead['name'] ?? '') ?>">
</div>

<div class="form-group">
<label>Phone *</label>
<input type="number" name="phone" 
value="<?= h($_POST['phone'] ?? $lead['phone'] ?? '') ?>">
</div>

<div class="form-group">
<label>Email *</label>
<input type="email" name="email"
value="<?= h($_POST['email'] ?? $lead['email'] ?? '') ?>">
</div>

<div class="form-group">
<label>Lead Source</label>
<select name="source">
<option value="">Select</option>
<option value="Website">Website</option>
<option value="Facebook">Facebook</option>
<option value="Instagram">Instagram</option>
<option value="Google Ads">Google Ads</option>
<option value="Walk-in">Walk-in</option>
<option value="Reference">Reference</option>
</select>
</div>

<div class="form-group">
<label>Interest</label>
<input type="text" name="course_interest"
value="<?= h($_POST['course_interest'] ?? $lead['course_interest'] ?? '') ?>">
</div>

<div class="form-group">
<label>Company / College Name</label>
<input type="text" name="company_college_name"
value="<?= h($_POST['company_college_name'] ?? $lead['company_college_name'] ?? '') ?>">
</div>

<div class="form-group">
<label>Department</label>
<input type="text" name="department"
value="<?= h($_POST['department'] ?? $lead['department'] ?? '') ?>">
</div>

<div class="form-group">
<label>Year</label>
<input type="text" name="lead_year"
placeholder="Ex: 2026 or Final Year"
value="<?= h($_POST['lead_year'] ?? $lead['lead_year'] ?? '') ?>">
</div>

<div class="form-group">
<label>Assign To</label>
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
<textarea name="remarks" rows="4"><?= h($_POST['remarks'] ?? $lead['remarks'] ?? '') ?></textarea>
</div>

</div>

<div class="form-actions">
<button type="submit" name="save_lead" class="btn btn-primary">
<?= $isEdit ? "Update Lead" : "Save Lead" ?>
</button>

<a href="index.php?page=leads/list" class="btn-light">
Cancel
</a>
</div>

</form>
</div>

<style>

.form-grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:16px;
}

.form-grid .full{
grid-column:1/-1;
}

.form-group label{
font-weight:700;
margin-bottom:6px;
display:block;
}

input,select,textarea{
width:100%;
padding:10px 12px;
border-radius:10px;
border:1px solid #e5e7eb;
}

input:focus,select:focus,textarea:focus{
border-color:#e91e63;
box-shadow:0 0 0 3px rgba(233,30,99,.15);
}

.form-actions{
margin-top:16px;
display:flex;
gap:10px;
}

.btn-primary{
background:#e91e63;
color:#fff;
padding:10px 16px;
border-radius:10px;
border:none;
cursor:pointer;
}

.btn-light{
background:#fff;
border:1px solid #ddd;
padding:10px 16px;
border-radius:10px;
text-decoration:none;
}

</style>

<script>
document.addEventListener("DOMContentLoaded", function(){

    const form = document.querySelector("form");

    form.addEventListener("submit", function(e){

        let name = form.querySelector('[name="name"]').value.trim();
        let phone = form.querySelector('[name="phone"]').value.trim();
        let email = form.querySelector('[name="email"]').value.trim();

        // Name check
        if(name === ""){
            e.preventDefault();
            Swal.fire({
                icon:'warning',
                title:'Validation Error',
                text:'Name is required',
                confirmButtonColor:'#e91e63'
            });
            return;
        }

        // Phone check
        if(phone === ""){
            e.preventDefault();
            Swal.fire({
                icon:'warning',
                title:'Validation Error',
                text:'Phone number is required',
                confirmButtonColor:'#e91e63'
            });
            return;
        }

        // Phone format (10 digits basic)
        if(!/^[0-9]{10}$/.test(phone)){
            e.preventDefault();
            Swal.fire({
                icon:'warning',
                title:'Invalid Phone',
                text:'Enter valid 10 digit phone number',
                confirmButtonColor:'#e91e63'
            });
            return;
        }

        // Email check
        if(email === ""){
            e.preventDefault();
            Swal.fire({
                icon:'warning',
                title:'Validation Error',
                text:'Email is required',
                confirmButtonColor:'#e91e63'
            });
            return;
        }

        // Email format
        let emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if(!emailPattern.test(email)){
            e.preventDefault();
            Swal.fire({
                icon:'warning',
                title:'Invalid Email',
                text:'Enter valid email address',
                confirmButtonColor:'#e91e63'
            });
            return;
        }

    });

});
</script>