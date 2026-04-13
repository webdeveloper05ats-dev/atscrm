<?php
// =====================================
// Targets - Setup
// Slug: targets/setup
// File: views/targets/setup.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = '';
$error   = '';

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('targetToNull')) {
    function targetToNull($value)
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }
}

if (!function_exists('targetInt')) {
    function targetInt($value)
    {
        return (int) trim((string)$value);
    }
}

if (!function_exists('targetDec')) {
    function targetDec($value)
    {
        $value = trim((string)$value);
        return $value === '' ? 0 : (float) $value;
    }
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = trim((string)($_SESSION['role_name'] ?? ''));

$allowedRoles = ['Super Admin', 'HR'];

if (!$userId || !$branchId) {
    $error = 'Invalid session. Please login again.';
}

if (!$error && !in_array($roleName, $allowedRoles, true)) {
    $error = 'Access denied. Only HR and Super Admin can manage targets.';
}

$isEdit = false;
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$form = [
    'id'                => 0,
    'user_id'           => '',
    'role_id'           => '',
    'target_year'       => date('Y'),
    'target_month'      => date('n'),
    'target_amount'     => '',
    'incentive_percent' => '10.00',
    'remarks'           => '',
    'status'            => 'active',
];

$monthNames = [
    1  => 'January',
    2  => 'February',
    3  => 'March',
    4  => 'April',
    5  => 'May',
    6  => 'June',
    7  => 'July',
    8  => 'August',
    9  => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];

$eligibleUsers = [];

// --------------------------------------------------
// Load eligible users
// --------------------------------------------------
if (!$error) {
    try {
        $sqlEligible = "
            SELECT
                u.id,
                u.name,
                u.email,
                u.role_id,
                r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.branch_id = :branch_id
              AND u.status = 1
              AND r.status = 1
              AND r.is_target_applicable = 1
              AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'hr', 'marketing')
            ORDER BY u.name ASC
        ";
        $stmtEligible = $pdo->prepare($sqlEligible);
        $stmtEligible->execute([
            ':branch_id' => $branchId
        ]);
        $eligibleUsers = $stmtEligible->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load eligible users. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Edit mode
// --------------------------------------------------
if (!$error && $editId > 0) {
    try {
        $sqlEdit = "
            SELECT *
            FROM monthly_targets
            WHERE id = :id
              AND branch_id = :branch_id
            LIMIT 1
        ";
        $stmtEdit = $pdo->prepare($sqlEdit);
        $stmtEdit->execute([
            ':id'        => $editId,
            ':branch_id' => $branchId
        ]);
        $row = $stmtEdit->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $isEdit = true;
            $form = [
                'id'                => (int)$row['id'],
                'user_id'           => (int)$row['user_id'],
                'role_id'           => (int)$row['role_id'],
                'target_year'       => (int)$row['target_year'],
                'target_month'      => (int)$row['target_month'],
                'target_amount'     => $row['target_amount'],
                'incentive_percent' => $row['incentive_percent'],
                'remarks'           => $row['remarks'],
                'status'            => $row['status'],
            ];
        } else {
            $error = 'Target record not found.';
        }
    } catch (Throwable $e) {
        $error = 'Unable to load target record. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// POST - Save / Update
// --------------------------------------------------
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (function_exists('verifyCsrfToken') && !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    }

    $postedId              = targetInt($_POST['id'] ?? 0);
    $postedUserId          = targetInt($_POST['user_id'] ?? 0);
    $postedTargetYear      = targetInt($_POST['target_year'] ?? 0);
    $postedTargetMonth     = targetInt($_POST['target_month'] ?? 0);
    $postedTargetAmount    = targetDec($_POST['target_amount'] ?? 0);
    $postedIncentivePct    = targetDec($_POST['incentive_percent'] ?? 0);
    $postedRemarks         = targetToNull($_POST['remarks'] ?? null);
    $postedStatus          = trim((string)($_POST['status'] ?? 'active'));

    $form['id']                = $postedId;
    $form['user_id']           = $postedUserId;
    $form['target_year']       = $postedTargetYear;
    $form['target_month']      = $postedTargetMonth;
    $form['target_amount']     = $postedTargetAmount;
    $form['incentive_percent'] = $postedIncentivePct;
    $form['remarks']           = $postedRemarks;
    $form['status']            = $postedStatus;

    if (!$postedUserId) {
        $error = 'Please select a user.';
    } elseif ($postedTargetYear < 2000 || $postedTargetYear > 2100) {
        $error = 'Please enter a valid year.';
    } elseif ($postedTargetMonth < 1 || $postedTargetMonth > 12) {
        $error = 'Please select a valid month.';
    } elseif ($postedTargetAmount <= 0) {
        $error = 'Target amount must be greater than 0.';
    } elseif ($postedIncentivePct < 0 || $postedIncentivePct > 100) {
        $error = 'Incentive % must be between 0 and 100.';
    } elseif (!in_array($postedStatus, ['active', 'inactive'], true)) {
        $error = 'Invalid status selected.';
    }

    $selectedUser = null;

    if (!$error) {
        try {
            $sqlUser = "
                SELECT
                    u.id,
                    u.name,
                    u.role_id,
                    r.role_name,
                    r.is_target_applicable
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.id = :user_id
                  AND u.branch_id = :branch_id
                  AND u.status = 1
                  AND r.status = 1
                LIMIT 1
            ";
            $stmtUser = $pdo->prepare($sqlUser);
            $stmtUser->execute([
                ':user_id'   => $postedUserId,
                ':branch_id' => $branchId
            ]);
            $selectedUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$selectedUser) {
                $error = 'Selected user not found in this branch.';
            } elseif ((int)$selectedUser['is_target_applicable'] !== 1) {
                $error = 'Selected role is not target applicable.';
            } elseif (!in_array(strtolower(trim((string)($selectedUser['role_name'] ?? ''))), ['front office', 'hr', 'marketing'], true)) {
                $error = 'Targets can be assigned only for Front Office, HR, and Marketing roles.';
            } else {
                $form['role_id'] = (int)$selectedUser['role_id'];
            }
        } catch (Throwable $e) {
            $error = 'Unable to validate selected user. ' . $e->getMessage();
        }
    }

    if (!$error) {
        try {
            $sqlDup = "
                SELECT id
                FROM monthly_targets
                WHERE branch_id = :branch_id
                  AND user_id = :user_id
                  AND target_year = :target_year
                  AND target_month = :target_month
            ";
            $paramsDup = [
                ':branch_id'    => $branchId,
                ':user_id'      => $postedUserId,
                ':target_year'  => $postedTargetYear,
                ':target_month' => $postedTargetMonth,
            ];

            if ($postedId > 0) {
                $sqlDup .= " AND id != :id";
                $paramsDup[':id'] = $postedId;
            }

            $sqlDup .= " LIMIT 1";

            $stmtDup = $pdo->prepare($sqlDup);
            $stmtDup->execute($paramsDup);
            $dup = $stmtDup->fetch(PDO::FETCH_ASSOC);

            if ($dup) {
                $error = 'Target already exists for this user in the selected month.';
            }
        } catch (Throwable $e) {
            $error = 'Unable to check duplicate target. ' . $e->getMessage();
        }
    }

    if (!$error) {
        try {
            if ($postedId > 0) {
                $sqlUpdate = "
                    UPDATE monthly_targets
                    SET
                        user_id = :user_id,
                        role_id = :role_id,
                        target_year = :target_year,
                        target_month = :target_month,
                        target_amount = :target_amount,
                        incentive_percent = :incentive_percent,
                        remarks = :remarks,
                        status = :status,
                        updated_at = NOW()
                    WHERE id = :id
                      AND branch_id = :branch_id
                    LIMIT 1
                ";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    ':user_id'           => $postedUserId,
                    ':role_id'           => (int)$selectedUser['role_id'],
                    ':target_year'       => $postedTargetYear,
                    ':target_month'      => $postedTargetMonth,
                    ':target_amount'     => $postedTargetAmount,
                    ':incentive_percent' => $postedIncentivePct,
                    ':remarks'           => $postedRemarks,
                    ':status'            => $postedStatus,
                    ':id'                => $postedId,
                    ':branch_id'         => $branchId,
                ]);

                if (function_exists('setFlash')) {
                    setFlash('success', 'Monthly target updated successfully.');
                }
                echo '<script>
                document.addEventListener("DOMContentLoaded", function () {
                    if (window.Swal && Swal.fire) {
                        Swal.fire({
                            icon: "success",
                            title: "Updated Successfully",
                            text: "Monthly target updated successfully.",
                            confirmButtonColor: "#e91e63"
                        }).then(function () {
                            window.location.href = "index.php?page=targets/list";
                        });
                    } else {
                        window.location.href = "index.php?page=targets/list";
                    }
                });
                </script>';
                exit;
            } else {
                $sqlInsert = "
                    INSERT INTO monthly_targets (
                        branch_id,
                        user_id,
                        role_id,
                        target_year,
                        target_month,
                        target_amount,
                        incentive_percent,
                        remarks,
                        status,
                        assigned_by,
                        created_at,
                        updated_at
                    ) VALUES (
                        :branch_id,
                        :user_id,
                        :role_id,
                        :target_year,
                        :target_month,
                        :target_amount,
                        :incentive_percent,
                        :remarks,
                        :status,
                        :assigned_by,
                        NOW(),
                        NOW()
                    )
                ";
                $stmtInsert = $pdo->prepare($sqlInsert);
                $stmtInsert->execute([
                    ':branch_id'         => $branchId,
                    ':user_id'           => $postedUserId,
                    ':role_id'           => (int)$selectedUser['role_id'],
                    ':target_year'       => $postedTargetYear,
                    ':target_month'      => $postedTargetMonth,
                    ':target_amount'     => $postedTargetAmount,
                    ':incentive_percent' => $postedIncentivePct,
                    ':remarks'           => $postedRemarks,
                    ':status'            => $postedStatus,
                    ':assigned_by'       => $userId,
                ]);

                if (function_exists('setFlash')) {
                    setFlash('success', 'Monthly target created successfully.');
                }
                echo '<script>
                document.addEventListener("DOMContentLoaded", function () {
                    if (window.Swal && Swal.fire) {
                        Swal.fire({
                            icon: "success",
                            title: "Saved Successfully",
                            text: "Monthly target created successfully.",
                            confirmButtonColor: "#e91e63"
                        }).then(function () {
                            window.location.href = "index.php?page=targets/list";
                        });
                    } else {
                        window.location.href = "index.php?page=targets/list";
                    }
                });
                </script>';
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Save failed. ' . $e->getMessage();
        }
    }
}

$selectedRoleName = '';
if (!empty($form['user_id'])) {
    foreach ($eligibleUsers as $u) {
        if ((int)$u['id'] === (int)$form['user_id']) {
            $selectedRoleName = $u['role_name'];
            break;
        }
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
#targetsSetupForm .targets-input-shell.has-prefix .targets-input-prefix{
left:16px;
font-size:1rem;
z-index:3;
}
#targetsSetupForm .targets-input-shell.has-prefix #target_amount.form-control.has-prefix-input{
padding-left:56px !important;
}
#targetsSetupForm .targets-input-shell.has-prefix #target_amount.form-control.has-prefix-input::placeholder{
color:#96778b;
opacity:1;
}
#targetsSetupForm .targets-input-shell .targets-input-icon{
left:13px;
z-index:2;
width:14px;
text-align:center;
}
#targetsSetupForm .targets-input-shell .form-control,
#targetsSetupForm .targets-input-shell .form-select{
padding-left:42px !important;
}
#targetsSetupForm .targets-input-shell.has-suffix .form-control.has-suffix-input{
padding-left:12px !important;
padding-right:34px !important;
}
#targetsSetupForm .targets-input-shell.textarea-shell .form-control{
padding-left:12px !important;
}
#targetsSetupForm input[name="target_year"].form-control,
#targetsSetupForm #role_name_display.form-control{
padding-left:44px !important;
}
</style>

<div class="container-fluid py-3">
<div class="targets-setup-wrap">

<?php if ($error): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.Swal && Swal.fire) {
        Swal.fire({
            icon: "error",
            title: "Error",
            text: <?= json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            confirmButtonColor: "#e91e63"
        });
    }
});
</script>
<?php endif; ?>

<div class="targets-topbar">

<div>
<div class="targets-topbar-title">
<i class="fas fa-bullseye text-danger me-2"></i>
<?= $isEdit ? 'Edit Monthly Target' : 'Setup Monthly Target' ?>
</div>
<div class="targets-topbar-sub">Set monthly target, incentive, and status for the selected staff member.</div>
</div>

</div>

<div class="targets-main-card">

<div class="targets-main-head">
<?= $isEdit ? 'Update Target Details' : 'Enter Target Details' ?>
</div>

<div class="targets-main-body">

<div class="targets-section-tag">
<i class="fas fa-sliders-h"></i>
Target Configuration
</div>

<form method="post" id="targetsSetupForm" novalidate>

<?php if (function_exists('csrfField')): ?>
<?= csrfField(); ?>
<?php endif; ?>

<input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
<input type="hidden" name="role_id" id="role_id" value="<?= h($form['role_id']) ?>">

<div class="targets-form-grid">

<div>
<label class="targets-label">Target Year</label>
<div class="targets-input-shell">
<i class="fas fa-lock targets-input-icon"></i>
<input type="number"
name="target_year"
class="form-control targets-readonly"
placeholder="Enter target year"
readonly
value="<?= h($form['target_year']) ?>">
</div>
<div class="targets-inline-note is-muted">Auto-filled year. It will still be saved with this target.</div>
</div>

<div>
<label class="targets-label">Target Month</label>
<div class="targets-input-shell select-shell">
<i class="fas fa-calendar-day targets-input-icon"></i>
<select name="target_month" class="form-select">

<?php foreach ($monthNames as $num => $name): ?>

<option value="<?= $num ?>"
<?= ((int)$form['target_month'] === $num) ? 'selected' : '' ?>>

<?= $name ?>

</option>

<?php endforeach; ?>

</select>
</div>
<div class="targets-inline-note is-muted">Choose the month for which this target should apply.</div>
</div>

<div>
<label class="targets-label">Staff / User <span class="required-mark">*</span></label>
<div class="targets-input-shell select-shell">
<i class="fas fa-user targets-input-icon"></i>
<select name="user_id" id="user_id" class="form-select" required>

<option value="">Select staff / user</option>

<?php foreach ($eligibleUsers as $user): ?>

<option
value="<?= $user['id'] ?>"
data-role-id="<?= $user['role_id'] ?>"
data-role-name="<?= h($user['role_name']) ?>"
<?= ((int)$form['user_id'] === (int)$user['id']) ? 'selected' : '' ?>>

<?= h($user['name']) ?> | <?= h($user['role_name']) ?>

</option>

<?php endforeach; ?>

</select>
</div>
<div class="targets-inline-note">Only active target-applicable staff are shown here.</div>
</div>

<div>
<label class="targets-label">Role</label>
<div class="targets-input-shell">
<i class="fas fa-lock targets-input-icon"></i>
<input type="text"
id="role_name_display"
class="form-control targets-readonly"
readonly
value="<?= h($selectedRoleName) ?>">
</div>
<div class="targets-inline-note is-muted">Auto-filled from staff selection. Role ID still goes to the database.</div>
</div>

<div>
<label class="targets-label">Target Amount <span class="required-mark">*</span></label>
<div class="amount-combo">
<div class="targets-input-shell has-prefix">
<span class="targets-input-prefix"><?= inr_symbol() ?></span>
<input
type="number"
id="target_amount"
name="target_amount"
class="form-control has-prefix-input"
placeholder="Enter target amount"
min="0"
step="0.01"
required
value="<?= h($form['target_amount']) ?>">
</div>

<div class="amount-slider">

<input type="range"
min="0"
max="500000"
step="1000"
id="amount_range"
value="<?= $form['target_amount'] ?: 0 ?>">

<div class="amount-display">

<?= inr_symbol() ?> <span id="amount_value">
<?= $form['target_amount'] ?: 0 ?>
</span>

</div>

</div>
</div>

</div>

<div>
<label class="targets-label">Incentive %</label>
<div class="targets-input-shell has-suffix">
<input
type="number"
name="incentive_percent"
class="form-control has-suffix-input"
value="<?= h($form['incentive_percent']) ?>">
<span class="targets-input-suffix">%</span>
</div>
<div class="targets-inline-note is-muted">Set the incentive percentage linked to achievement.</div>
</div>

<div>

<label class="targets-label">Target Status</label>

<div class="status-pills">
<button type="button"
class="status-pill <?= ($form['status'] === 'active') ? 'is-active' : '' ?>"
data-status="active">
Active
</button>
<button type="button"
class="status-pill <?= ($form['status'] === 'inactive') ? 'is-active' : '' ?>"
data-status="inactive">
Inactive
</button>
</div>
<div class="targets-inline-note is-muted">Use inactive to keep the target record without applying it currently.</div>

<input type="hidden"
name="status"
id="status_hidden"
value="<?= h($form['status']) ?>">

</div>

<div>

<label class="targets-label">Remarks</label>
<div class="targets-input-shell textarea-shell">
<textarea
name="remarks"
class="form-control">

<?= h($form['remarks']) ?>

</textarea>
</div>
<div class="targets-inline-note is-muted">Add any internal note about the monthly target or incentive plan.</div>
</div>

</div>

<div class="targets-form-actions">

<button
type="submit"
id="saveTargetBtn"
class="btn targets-btn targets-btn-primary"
disabled>

<i class="fas fa-save"></i>
<?= $isEdit ? 'Update Target' : 'Save Target' ?>

</button>

<a href="index.php?page=targets/list"
class="btn targets-btn targets-btn-outline">

View Target List

</a>

</div>

</form>

</div>
</div>

</div>
</div>

<script>

/* ROLE AUTO DISPLAY */

const userSelect=document.getElementById("user_id");
const roleInput=document.getElementById("role_name_display");
const saveTargetBtn=document.getElementById("saveTargetBtn");

if(userSelect){

userSelect.addEventListener("change",function(){

let opt=this.options[this.selectedIndex];

roleInput.value=opt ? (opt.getAttribute("data-role-name") || "") : "";

});

}

const syncSaveTargetState=function(){
if(!saveTargetBtn) return;
const selectedUser=(userSelect?.value || "").trim();
const targetAmount=(amountInput?.value || "").trim();
saveTargetBtn.disabled = (selectedUser === "" || targetAmount === "");
};

/* STATUS PILLS */

const statusHidden=document.getElementById("status_hidden");
const statusPills=document.querySelectorAll(".status-pill");

if(statusPills.length && statusHidden){

statusPills.forEach(function(pill){

pill.addEventListener("click",function(){

const selectedStatus=this.getAttribute("data-status") || "active";
statusHidden.value=selectedStatus;

statusPills.forEach(function(btn){
btn.classList.remove("is-active");
});

this.classList.add("is-active");

});

});

}

/* TARGET AMOUNT SLIDER */

const range=document.getElementById("amount_range");
const amountInput=document.getElementById("target_amount");
const amountValue=document.getElementById("amount_value");

if(range){

range.addEventListener("input",function(){

amountInput.value=this.value;
amountValue.textContent=this.value;

});

}

if(amountInput){

amountInput.addEventListener("input",function(){

range.value=this.value;
amountValue.textContent=this.value;

syncSaveTargetState();

});

}

/* REQUIRED FIELD VALIDATION */

const targetsSetupForm=document.getElementById("targetsSetupForm");

if(targetsSetupForm){

targetsSetupForm.addEventListener("submit",function(e){

const selectedUser=(userSelect?.value || "").trim();
const targetAmount=(amountInput?.value || "").trim();

if(selectedUser === "" || targetAmount === ""){
e.preventDefault();

if(window.Swal && Swal.fire){
Swal.fire({
icon:'warning',
title:'Missing Information',
text:'Staff / User and Target Amount are required.',
confirmButtonColor:'#e91e63'
});
}else{
alert('Staff / User and Target Amount are required.');
}

if(selectedUser === "" && userSelect){
userSelect.focus();
}else if(targetAmount === "" && amountInput){
amountInput.focus();
}

return;
}

if(Number(targetAmount) <= 0){
e.preventDefault();

if(window.Swal && Swal.fire){
Swal.fire({
icon:'warning',
title:'Invalid Amount',
text:'Target Amount must be greater than 0.',
confirmButtonColor:'#e91e63'
});
}else{
alert('Target Amount must be greater than 0.');
}

amountInput?.focus();

}

});

}

if(userSelect){
userSelect.addEventListener("change",syncSaveTargetState);
}

syncSaveTargetState();

</script>


