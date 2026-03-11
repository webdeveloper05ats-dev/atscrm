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
    } elseif ($postedTargetAmount < 0) {
        $error = 'Target amount cannot be negative.';
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
                echo '<script>window.location.href="index.php?page=targets/list";</script>';
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
                echo '<script>window.location.href="index.php?page=targets/list";</script>';
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

<style>

.targets-setup-wrap{
background:#fcf8fb;
border-radius:22px;
padding:20px;
}

.targets-topbar{
background:linear-gradient(135deg,#ffffff 0%,#fff6fb 100%);
border:1px solid #f0d9e5;
border-radius:20px;
padding:18px 20px;
box-shadow:0 8px 24px rgba(233,30,99,.06);
display:flex;
align-items:center;
justify-content:space-between;
gap:16px;
flex-wrap:wrap;
margin-bottom:18px;
}

.targets-topbar-title{
font-size:1.45rem;
font-weight:700;
color:#202020;
}

.targets-summary-row{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:14px;
margin-bottom:18px;
}

.targets-summary-card{
background:#fff;
border:1px solid #f0d9e5;
border-radius:18px;
padding:16px 18px;
}

.targets-main-card{
background:#fff;
border:1px solid #f0d9e5;
border-radius:22px;
overflow:hidden;
}

.targets-main-head{
background:linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
color:#fff;
padding:16px 20px;
font-weight:700;
}

.targets-main-body{
padding:22px;
}

.targets-form-grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:18px;
}

.targets-field-full{
grid-column:1/-1;
}

.targets-label{
font-weight:600;
margin-bottom:8px;
display:block;
}

.targets-help{
font-size:.82rem;
color:#777;
margin-top:6px;
}

/* modern toggle */

.status-toggle{
display:flex;
align-items:center;
gap:12px;
margin-top:10px;
}

.switch{
position:relative;
display:inline-block;
width:52px;
height:28px;
}

.switch input{
opacity:0;
width:0;
height:0;
}

.slider{
position:absolute;
cursor:pointer;
top:0;
left:0;
right:0;
bottom:0;
background:#ccc;
transition:.3s;
border-radius:34px;
}

.slider:before{
position:absolute;
content:"";
height:22px;
width:22px;
left:3px;
bottom:3px;
background:white;
transition:.3s;
border-radius:50%;
}

.switch input:checked + .slider{
background:#e91e63;
}

.switch input:checked + .slider:before{
transform:translateX(24px);
}

/* amount slider */

.amount-slider{
margin-top:10px;
}

.amount-slider input[type=range]{
width:100%;
height:8px;
background:#f0d9e5;
border-radius:10px;
appearance:none;
}

.amount-slider input[type=range]::-webkit-slider-thumb{
appearance:none;
width:20px;
height:20px;
background:#e91e63;
border-radius:50%;
cursor:pointer;
}

.amount-display{
margin-top:6px;
font-weight:600;
}

.targets-btn{
border-radius:12px;
padding:9px 16px;
font-weight:600;
text-decoration:none;
display:inline-flex;
align-items:center;
gap:8px;
}

.targets-btn-primary{
background:linear-gradient(135deg,#ec1670,#c8135b);
color:#fff;
border:none;
}

.targets-btn-outline{
border:1px solid #e4cfd9;
background:#fff;
}

.targets-form-actions{
margin-top:25px;
display:flex;
gap:10px;
}

</style>


<div class="container-fluid py-3">
<div class="targets-setup-wrap">

<div class="targets-topbar">

<div class="targets-topbar-title">
<i class="fas fa-bullseye text-danger me-2"></i>
<?= $isEdit ? 'Edit Monthly Target' : 'Setup Monthly Target' ?>
</div>

</div>


<div class="targets-main-card">

<div class="targets-main-head">
<?= $isEdit ? 'Update Target Details' : 'Enter Target Details' ?>
</div>

<div class="targets-main-body">

<form method="post">

<?php if (function_exists('csrfField')): ?>
<?= csrfField(); ?>
<?php endif; ?>

<input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
<input type="hidden" name="role_id" id="role_id" value="<?= h($form['role_id']) ?>">

<div class="targets-form-grid">

<div>
<label class="targets-label">Target Year</label>

<input type="number"
name="target_year"
class="form-control"
value="<?= h($form['target_year']) ?>">
</div>


<div>
<label class="targets-label">Target Month</label>

<select name="target_month" class="form-select">

<?php foreach ($monthNames as $num => $name): ?>

<option value="<?= $num ?>"
<?= ((int)$form['target_month'] === $num) ? 'selected' : '' ?>>

<?= $name ?>

</option>

<?php endforeach; ?>

</select>
</div>


<div>
<label class="targets-label">Staff / User</label>

<select name="user_id" id="user_id" class="form-select">

<?php foreach ($eligibleUsers as $user): ?>

<option
value="<?= $user['id'] ?>"
data-role-id="<?= $user['role_id'] ?>"
data-role-name="<?= h($user['role_name']) ?>">

<?= h($user['name']) ?> | <?= h($user['role_name']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div>
<label class="targets-label">Role</label>

<input type="text"
id="role_name_display"
class="form-control"
readonly
value="<?= h($selectedRoleName) ?>">

</div>



<div>
<label class="targets-label">Target Amount</label>

<input
type="number"
id="target_amount"
name="target_amount"
class="form-control"
value="<?= h($form['target_amount']) ?>">

<div class="amount-slider">

<input type="range"
min="0"
max="500000"
step="1000"
id="amount_range"
value="<?= $form['target_amount'] ?: 0 ?>">

<div class="amount-display">

₹ <span id="amount_value">
<?= $form['target_amount'] ?: 0 ?>
</span>

</div>

</div>

</div>


<div>
<label class="targets-label">Incentive %</label>

<input
type="number"
name="incentive_percent"
class="form-control"
value="<?= h($form['incentive_percent']) ?>">

</div>



<div>

<label class="targets-label">Target Status</label>

<div class="status-toggle">

<label class="switch">

<input type="checkbox"
id="status_switch"
<?= ($form['status'] === 'active') ? 'checked' : '' ?>>

<span class="slider"></span>

</label>

<span id="status_label">
<?= ($form['status'] === 'active') ? 'Active' : 'Inactive' ?>
</span>

</div>

<input type="hidden"
name="status"
id="status_hidden"
value="<?= h($form['status']) ?>">

</div>


<div class="targets-field-full">

<label class="targets-label">Remarks</label>

<textarea
name="remarks"
class="form-control">

<?= h($form['remarks']) ?>

</textarea>

</div>


</div>


<div class="targets-form-actions">

<button
type="submit"
class="btn targets-btn targets-btn-primary">

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

if(userSelect){

userSelect.addEventListener("change",function(){

let opt=this.options[this.selectedIndex];

roleInput.value=opt.getAttribute("data-role-name");

});

}


/* STATUS SWITCH */

const statusSwitch=document.getElementById("status_switch");
const statusHidden=document.getElementById("status_hidden");
const statusLabel=document.getElementById("status_label");

if(statusSwitch){

statusSwitch.addEventListener("change",function(){

if(this.checked){

statusHidden.value="active";
statusLabel.textContent="Active";

}else{

statusHidden.value="inactive";
statusLabel.textContent="Inactive";

}

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

});

}

</script>
