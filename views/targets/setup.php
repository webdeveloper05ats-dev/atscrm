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
              AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'hr', 'marketing', 'corporate')
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

<style>

.targets-setup-wrap{
background:
radial-gradient(circle at top left, rgba(255,236,244,.9), rgba(255,248,252,0) 38%),
linear-gradient(180deg,#fffafd 0%,#fff6fa 100%);
border:1px solid #f6dce8;
border-radius:24px;
padding:18px;
box-shadow:0 12px 30px rgba(233,30,99,.06);
}

.targets-topbar{
background:linear-gradient(135deg,#ffffff 0%,#fff6fb 100%);
border:1px solid #f0d9e5;
border-radius:18px;
padding:14px 18px;
box-shadow:0 8px 20px rgba(233,30,99,.06);
display:flex;
align-items:center;
justify-content:space-between;
gap:16px;
flex-wrap:wrap;
margin-bottom:16px;
}

.targets-topbar-title{
font-size:1.05rem;
font-weight:800;
color:#1f2940;
}

.targets-topbar-sub{
margin-top:4px;
font-size:.74rem;
font-weight:600;
color:#8b6b7d;
}

.targets-topbar-sub{
margin-top:4px;
font-size:.74rem;
font-weight:600;
color:#8b6b7d;
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
border-radius:18px;
overflow:hidden;
box-shadow:0 14px 34px rgba(15,23,42,.05);
}

.targets-main-head{
background:linear-gradient(135deg,#ec1670 0%,#c8135b 100%);
color:#fff;
padding:12px 18px;
font-weight:800;
letter-spacing:.02em;
font-size:.88rem;
}

.targets-main-body{
padding:16px 18px 18px;
}

.targets-section-tag{
display:inline-flex;
align-items:center;
gap:8px;
padding:7px 12px;
border-radius:999px;
border:1px solid #f2d9e5;
background:#fff7fb;
color:#a23f6d;
font-size:.72rem;
font-weight:800;
letter-spacing:.03em;
text-transform:uppercase;
margin-bottom:14px;
}

.targets-section-tag{
display:inline-flex;
align-items:center;
gap:8px;
padding:7px 12px;
border-radius:999px;
border:1px solid #f2d9e5;
background:#fff7fb;
color:#a23f6d;
font-size:.72rem;
font-weight:800;
letter-spacing:.03em;
text-transform:uppercase;
margin-bottom:14px;
}

.targets-form-grid{
display:grid;
grid-template-columns:repeat(2,minmax(240px,1fr));
gap:14px 16px;
max-width:920px;
}

.targets-field-full{
grid-column:1/-1;
}

.targets-label{
font-weight:700;
font-size:.76rem;
letter-spacing:.03em;
text-transform:uppercase;
color:#8f4f6d;
margin-bottom:8px;
display:block;
}

.targets-label .required-mark{
color:#e91e63;
margin-left:4px;
}

.targets-help{
font-size:.82rem;
color:#777;
margin-top:6px;
}

.targets-form-grid .form-control,
.targets-form-grid .form-select{
width:100%;
min-height:38px;
border:1px solid #efcada;
border-radius:10px;
background:#fff;
box-shadow:none;
padding:8px 10px;
font-size:.84rem;
font-weight:600;
color:#27364c;
transition:border-color .18s ease, box-shadow .18s ease;
}

.targets-form-grid .form-control:focus,
.targets-form-grid .form-select:focus{
border-color:#e91e63;
box-shadow:0 0 0 4px rgba(233,30,99,.12);
outline:none;
}

.targets-form-grid textarea.form-control{
min-height:92px;
resize:vertical;
}

.targets-input-shell{
position:relative;
}

.targets-input-icon{
position:absolute;
left:11px;
top:50%;
transform:translateY(-50%);
color:#e91e63;
font-size:.84rem;
pointer-events:none;
}

.targets-input-shell .form-control,
.targets-input-shell .form-select{
padding-left:30px;
}

.targets-readonly{
background:linear-gradient(180deg,#fff7fb 0%,#fff2f8 100%) !important;
border-color:#f1d8e4 !important;
color:#6f4b60 !important;
cursor:not-allowed;
}

.targets-readonly:focus{
box-shadow:none !important;
}

.targets-input-shell.textarea-shell .targets-input-icon{
top:14px;
transform:none;
}

.targets-inline-note{
margin-top:5px;
font-size:.68rem;
font-weight:600;
color:#9b6a82;
line-height:1.35;
}

.targets-inline-note.is-muted{
opacity:.82;
}

.targets-inline-note.is-muted{
opacity:.82;
}

.status-pills{
display:flex;
align-items:center;
gap:0;
margin-top:0;
border:1px solid #efcada;
border-radius:10px;
background:#fff;
overflow:hidden;
}

.status-pill{
flex:1;
border:0;
background:transparent;
color:#6b7280;
padding:10px 14px;
font-size:.8rem;
font-weight:700;
line-height:1;
cursor:pointer;
transition:all .18s ease;
}

.status-pill.is-active{
box-shadow:none;
}

.status-pill[data-status="active"].is-active{
background:linear-gradient(135deg, #e91e63 0%, #ff4f9c 100%);
color:#fff;
}

.status-pill[data-status="inactive"].is-active{
background:linear-gradient(135deg, #e91e63 0%, #ff4f9c 100%);
color:#fff;
}

.status-pill + .status-pill{
border-left:1px solid #f3d8e5;
}

/* amount slider */

.amount-slider{
margin-top:10px;
padding:8px 10px;
border:1px solid #f1d8e4;
border-radius:10px;
background:#fff9fc;
}

.amount-combo{
padding:10px;
border:1px solid #f1d8e4;
border-radius:12px;
background:linear-gradient(180deg,#fffdfd 0%,#fff8fb 100%);
}

.amount-combo .targets-input-shell{
margin-bottom:8px;
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
margin-top:8px;
font-weight:700;
color:#8d1246;
font-size:.78rem;
}

.targets-btn{
border-radius:10px;
padding:9px 16px;
font-weight:700;
text-decoration:none;
display:inline-flex;
align-items:center;
gap:8px;
font-size:.84rem;
}

.targets-btn:disabled{
opacity:.58;
cursor:not-allowed;
transform:none;
box-shadow:none;
}

.targets-btn-primary{
background:linear-gradient(135deg,#ec1670,#c8135b);
color:#fff;
border:none;
}

.targets-btn-outline{
border:1px solid #e4cfd9;
background:#fff;
color:#6e4b60;
}

.targets-form-actions{
margin-top:16px;
display:flex;
gap:10px;
flex-wrap:wrap;
padding-top:14px;
border-top:1px solid #f4dfe8;
}

@media(max-width:900px){
.targets-form-grid{
grid-template-columns:1fr;
max-width:none;
}
}

</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


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
<div class="targets-input-shell">
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
<div class="targets-input-shell">
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
<div class="targets-input-shell">
<i class="fas fa-rupee-sign targets-input-icon"></i>
<input
type="number"
id="target_amount"
name="target_amount"
class="form-control"
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

₹ <span id="amount_value">
<?= $form['target_amount'] ?: 0 ?>
</span>

</div>

</div>
</div>

</div>


<div>
<label class="targets-label">Incentive %</label>
<div class="targets-input-shell">
<i class="fas fa-percent targets-input-icon"></i>
<input
type="number"
name="incentive_percent"
class="form-control"
value="<?= h($form['incentive_percent']) ?>">
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
<i class="fas fa-note-sticky targets-input-icon"></i>
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
