<?php
// ============================================
// ATS CRM - Reset Password Page (Token Link)
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/helper.php';

// If already logged in, go to dashboard
if (!empty($_SESSION['user_id'])) {
    redirect('index.php');
    exit;
}

$pageTitle = "Reset Password";

$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');

$email = filter_var($email, FILTER_SANITIZE_EMAIL);

// Basic validation
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || $token === '' || strlen($token) < 20) {
    setFlash('error', 'Invalid or expired reset link.');
    redirect('login.php');
    exit;
}

// Fetch user reset fields
$stmt = $pdo->prepare("
    SELECT id, name, email, reset_token_hash, reset_expires, status
    FROM users
    WHERE email = :email
    LIMIT 1
");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$valid = false;

if ($user && (int)$user['status'] === 1 && !empty($user['reset_token_hash']) && !empty($user['reset_expires'])) {
    // Check expiry
    $now = new DateTime();
    $exp = new DateTime($user['reset_expires']);

    if ($exp >= $now) {
        // Verify token against stored hash
        if (password_verify($token, $user['reset_token_hash'])) {
            $valid = true;
        }
    }
}

if (!$valid) {
    setFlash('error', 'Invalid or expired reset link.');
    redirect('login.php');
    exit;
}

// -------------------------------
// Handle new password submit
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8) {
        setFlash('error', 'Password must be at least 8 characters.');
        redirect('reset_password.php?email=' . urlencode($email) . '&token=' . urlencode($token));
        exit;
    }

    if ($newPass !== $confirm) {
        setFlash('error', 'Passwords do not match.');
        redirect('reset_password.php?email=' . urlencode($email) . '&token=' . urlencode($token));
        exit;
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);

    // Update password + clear token
    $upd = $pdo->prepare("
        UPDATE users
        SET password = :p,
            reset_token_hash = NULL,
            reset_expires = NULL,
            must_change_password = 0
        WHERE id = :id
        LIMIT 1
    ");
    $upd->execute([
        'p'  => $hash,
        'id' => (int)$user['id']
    ]);

    setFlash('success', 'Password updated successfully. Please login.');
    redirect('login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(APP_NAME) ?></title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <style>
        :root{
            --ats-pink:#e91e63;
            --ats-bg:#fff5f9;
            --ats-text:#111827;
            --ats-muted:#6b7280;
        }
        html,body{height:100%;margin:0;font-family:'Poppins',sans-serif;background:var(--ats-bg);}
        .wrap{
            min-height:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:16px;
        }
        .cardx{
            width:100%;
            max-width:420px;
            background:#fff;
            border-radius:16px;
            box-shadow:0 18px 55px rgba(31,41,55,.14);
            overflow:hidden;
        }
        .head{
            padding:16px 18px;
            background:linear-gradient(135deg,var(--ats-pink) 0%, #ff5aa5 60%, #ff8fc2 100%);
            color:#fff;
            font-weight:800;
            font-size:18px;
        }
        .body{padding:18px;}
        label{font-weight:700;font-size:13px;color:var(--ats-text);}
        .btnpink{
            width:100%;
            height:44px;
            border:0;
            border-radius:12px;
            background:var(--ats-pink);
            color:#fff;
            font-weight:800;
        }
        .muted{font-size:13px;color:var(--ats-muted);}
    
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

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>
</head>
<body>

<div class="wrap">
    <div class="cardx">
        <div class="head">Reset Password</div>
        <div class="body">

            <div class="muted mb-3">
                Create a new password for <b><?= htmlspecialchars($email) ?></b>
            </div>

            <?php if ($err = getFlash('error')): ?>
                <div class="alert alert-danger" style="border-radius:12px;">
                    <?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           style="border-radius:12px;height:44px;"
                           placeholder="Minimum 8 characters" required>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           style="border-radius:12px;height:44px;"
                           placeholder="Re-enter password" required>
                </div>

                <button type="submit" class="btnpink">Update Password</button>
            </form>

            <div class="text-center mt-3">
                <a href="<?= BASE_URL ?>login.php" style="color:var(--ats-pink);font-weight:700;text-decoration:none;">
                    Back to Login
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>

