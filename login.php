<?php
// ============================================
// ATS CRM - Login Page (Final + Remember Me + Custom Modal)
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/helper.php';
require_once __DIR__ . '/core/remember.php';

if (isset($_SESSION['user_id'])) {
    redirect('index.php');
    exit;
}

$pageTitle = "Login";

// -------------------------------
// Handle Login
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        setFlash('error', 'Please enter email and password.');
        redirect('login.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name, b.branch_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE u.email = :email
          AND u.status = 1
        LIMIT 1
    ");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id']     = (int)$user['id'];
        $_SESSION['user_name']   = $user['name'];
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['role_id']     = (int)$user['role_id'];
        $_SESSION['role_name']   = $user['role_name'] ?? '';
        $_SESSION['branch_id']   = (int)($user['branch_id'] ?? 0);
        $_SESSION['branch_name'] = $user['branch_name'] ?? '';

        $update = $pdo->prepare("
            UPDATE users
            SET last_login = NOW(),
                last_login_ip = :ip
            WHERE id = :id
        ");
        $update->execute([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'id' => (int)$user['id']
        ]);

        // Remember Me
        if (!empty($_POST['remember'])) {
            remember_issue($pdo, (int)$user['id'], 14);
        } else {
            remember_revoke($pdo, (int)$user['id']);
        }

        redirect('index.php');
        exit;

    } else {
        setFlash('error', 'Invalid email or password.');
        redirect('login.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars(APP_NAME) ?></title>

<!-- Bootstrap -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- Google Poppins -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">


    <style>
	
	/* FORCE POPPINS EVERYWHERE */
body,
button,
input,
select,
textarea,
label,
span,
p,
h1,h2,h3,h4,h5,h6,
a,
div{
    font-family: 'Poppins', sans-serif !important;
}

/* DO NOT override Font Awesome */
.fa,
.fas,
.far,
.fab{
    font-family: "Font Awesome 5 Free", "Font Awesome 5 Brands" !important;
}

        *{ box-sizing:border-box; }
        :root{
            --ats-pink:#e91e63;
            --ats-bg:#fff5f9;
            --ats-text:#111827;
            --ats-muted:#6b7280;
            --radius:18px;
        }
        html, body{
            height:100%;
            margin:0;
            background: var(--ats-bg);
          
        }
        .auth-wrap{
            min-height:100%;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:16px;
            background:
                radial-gradient(900px 500px at 10% 10%, #ffe0ec 0%, transparent 55%),
                radial-gradient(900px 500px at 90% 20%, #ffd1e2 0%, transparent 55%),
                linear-gradient(180deg, var(--ats-bg) 0%, #ffffff 70%);
        }
        .auth-card{
            width:100%;
            max-width:420px;
            border-radius: var(--radius);
            box-shadow: 0 18px 55px rgba(31,41,55,0.14);
            overflow:hidden;
            background:#fff;
        }
        .auth-header{
            display:flex;
            align-items:center;
            gap:12px;
            padding: 18px 22px;
            background: linear-gradient(135deg, var(--ats-pink) 0%, #ff5aa5 55%, #ff8fc2 100%);
            color:#fff;
        }
        .auth-logo{
            width:48px;height:48px;
            border-radius:12px;
            background:#fff;
            padding:6px;
            object-fit:contain;
            box-shadow: 0 10px 18px rgba(0,0,0,0.18);
        }
        .auth-header h4{ margin:0; font-weight:800; font-size:18px; }
        .auth-header p{ margin:2px 0 0; font-size:13px; opacity:.95; }
        .auth-body{ padding:22px; }
        .auth-title{ font-weight:800; color:var(--ats-text); margin-bottom:2px; }
        .auth-sub{ color:var(--ats-muted); font-size:13px; margin-bottom:14px; }
        .form-group{ margin-bottom:14px; }
        .auth-field{
            width:100%;
            display:flex;
            align-items:center;
            gap:10px;
            padding:12px 14px;
            border:1px solid #f1c2d4;
            border-radius:14px;
            background:#fff;
            transition:.2s ease;
        }
        .auth-field:focus-within{
            border-color:#ff77ad;
            box-shadow:0 0 0 4px rgba(233,30,99,.12);
        }
        .auth-icon{
            width:34px;height:34px;
            border-radius:10px;
            display:flex;align-items:center;justify-content:center;
            background:#fff0f6;
            color:var(--ats-pink);
            flex:0 0 34px;
        }
        .auth-input{
            flex:1; min-width:0;
            border:0; outline:none;
            font-size:14px;
            color:var(--ats-text);
            background:transparent;
        }
        .auth-input::placeholder{ color:#9ca3af; }
        
        /* Password toggle styles */
        .password-toggle {
            background: transparent;
            border: none;
            color: var(--ats-pink);
            cursor: pointer;
            padding: 0 8px;
            font-size: 16px;
            opacity: 0.7;
            transition: opacity 0.2s ease;
        }
        .password-toggle:hover {
            opacity: 1;
        }
        .password-toggle:focus {
            outline: none;
        }
        
        .auth-links{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin: 12px 0 16px;
            font-size:13px;
        }
        .auth-links a{ color:var(--ats-pink); text-decoration:none; font-weight:700; }
        .auth-links a:hover{ text-decoration:underline; cursor:pointer; }
        .auth-btn{
            width:100%;
            height:46px;
            border:0;
            border-radius:14px;
            background: linear-gradient(135deg, var(--ats-pink) 0%, #ff5aa5 60%, #ff8fc2 100%);
            color:#fff;
            font-weight:800;
            cursor:pointer;
            box-shadow: 0 12px 26px rgba(233,30,99,.22);
            transition:.2s ease;
        }
        .auth-btn:hover{
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(233,30,99,.28);
        }
        .auth-footer{
            padding:12px 18px;
            text-align:center;
            font-size:12px;
            color:var(--ats-muted);
            border-top:1px solid #f3f4f6;
        }

        /* Modal */
        .ats-modal-backdrop{ position:fixed; inset:0; background: rgba(17,24,39,.55); display:none; z-index:9998; }
        .ats-modal{
            position:fixed; top:50%; left:50%; transform: translate(-50%, -50%);
            width: 92%; max-width: 420px; background:#fff; border-radius:16px;
            box-shadow: 0 30px 80px rgba(0,0,0,.25); display:none; z-index:9999; overflow:hidden;
        }
        .ats-modal-header{ padding:14px 16px; background:#fff0f6; display:flex; justify-content:space-between; align-items:center; }
        .ats-modal-header h5{ margin:0; font-weight:800; font-size:16px; }
        .ats-close{ border:0; background:transparent; font-size:22px; line-height:1; cursor:pointer; }
        .ats-modal-body{ padding:16px; }
        .ats-modal-footer{ padding: 0 16px 16px; display:flex; justify-content:flex-end; gap:10px; }
        .ats-btn-light{ border:1px solid #e5e7eb; background:#fff; border-radius:12px; padding:10px 14px; cursor:pointer; font-weight:700; }
        .ats-btn-pink{ border:0; background: var(--ats-pink); color:#fff; border-radius:12px; padding:10px 14px; cursor:pointer; font-weight:800; }
        .ats-btn-pink:disabled{ opacity:.65; cursor:not-allowed; }

        @media (max-height: 650px){
            .auth-wrap{ align-items:flex-start; padding-top:22px; }
        }
    </style>
</head>
<body>

<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-header">
            <img class="auth-logo" src="<?= BASE_URL ?>assets/images/logo.png" alt="ATS Logo">
            <div>
                <h4>Welcome to <?= htmlspecialchars(APP_NAME) ?></h4>
                <p>Please login to continue</p>
            </div>
        </div>

        <div class="auth-body">
            <h5 class="auth-title">Sign in</h5>
            <div class="auth-sub">Enter your credentials to continue</div>

            <?php if ($error = getFlash('error')): ?>
                <div class="alert alert-danger" style="border-radius:12px;">
                    <i class="fa fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="text" style="display:none">
                <input type="password" style="display:none">

                <div class="form-group">
                    <label class="mb-1" style="font-weight:700;font-size:13px;">Email</label>
                    <div class="auth-field">
                        <div class="auth-icon"><i class="fa fa-envelope"></i></div>
                        <input type="text" name="email" class="auth-input"
                               placeholder="name@company.com"
                               autocomplete="off" autocapitalize="off" spellcheck="false"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="mb-1" style="font-weight:700;font-size:13px;">Password</label>
                    <div class="auth-field">
                        <div class="auth-icon"><i class="fa fa-lock"></i></div>
                        <input type="password" name="password" id="passwordInput" class="auth-input"
                               placeholder="Enter your password"
                               autocomplete="new-password"
                               required>
                        <button type="button" class="password-toggle" id="togglePassword" tabindex="-1">
                            <i class="fa fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="auth-links">
                    <label class="mb-0" style="user-select:none;">
                        <input type="checkbox" name="remember" value="1">
                        <span class="ml-1">Remember me</span>
                    </label>
                    <a id="forgotLink">Forgot password?</a>
                </div>

                <button type="submit" class="auth-btn">
                    <i class="fa fa-sign-in-alt"></i> Login
                </button>
            </form>
        </div>

        <div class="auth-footer">
            © <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>. All rights reserved.
        </div>

    </div>
</div>

<!-- Custom Modal -->
<div class="ats-modal-backdrop" id="fpBackdrop"></div>

<div class="ats-modal" id="forgotModal">
    <div class="ats-modal-header">
        <h5>Reset Password</h5>
        <button class="ats-close" id="fpClose">&times;</button>
    </div>

    <div class="ats-modal-body">
        <div style="font-size:13px;color:#6b7280;margin-bottom:10px;">
            Enter your registered email. We'll generate a new password and send it to you.
        </div>

        <div class="form-group">
            <label class="mb-1" style="font-weight:700;font-size:13px;">Email</label>
            <input type="email" id="fp_email" class="form-control"
                   style="border-radius:12px;height:44px;"
                   placeholder="name@company.com" />
        </div>

        <div id="fp_msg" style="display:none;"></div>
    </div>

    <div class="ats-modal-footer">
        <button class="ats-btn-light" id="fpCancel">Cancel</button>
        <button class="ats-btn-pink" id="fp_submit">Send New Password</button>
    </div>
</div>

<script>
// Password Toggle Functionality - Only this added, nothing else changed
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            // Toggle password visibility
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
});

// Forgot Password Modal Functionality - Completely unchanged
(function(){
  const link = document.getElementById('forgotLink');
  const modal = document.getElementById('forgotModal');
  const backdrop = document.getElementById('fpBackdrop');
  const closeBtn = document.getElementById('fpClose');
  const cancelBtn = document.getElementById('fpCancel');
  const submitBtn = document.getElementById('fp_submit');
  const emailInp = document.getElementById('fp_email');
  const msg = document.getElementById('fp_msg');

  function openModal(){
    emailInp.value = '';
    msg.style.display = 'none';
    msg.innerHTML = '';
    backdrop.style.display = 'block';
    modal.style.display = 'block';
  }

  function closeModal(){
    backdrop.style.display = 'none';
    modal.style.display = 'none';
  }

  function showMsg(ok, text){
    msg.style.display = 'block';
    msg.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
    msg.style.borderRadius = '12px';
    msg.style.fontSize = '13px';
    msg.style.whiteSpace = 'pre-wrap';
    msg.innerHTML = text;
  }

  link && link.addEventListener('click', function(e){ e.preventDefault(); openModal(); });
  closeBtn && closeBtn.addEventListener('click', closeModal);
  cancelBtn && cancelBtn.addEventListener('click', closeModal);
  backdrop && backdrop.addEventListener('click', closeModal);

  submitBtn && submitBtn.addEventListener('click', async function(){
    const email = (emailInp.value || '').trim();
    if (!email) { showMsg(false, 'Please enter your email.'); return; }

    submitBtn.disabled = true;
    submitBtn.innerText = 'Sending...';

    try{
      const res = await fetch('<?= BASE_URL ?>forgot_password.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ email })
      });

      const raw = await res.text();   // ✅ read raw text first
      let data = null;

      try {
        data = JSON.parse(raw);       // ✅ parse JSON safely
      } catch (e) {
        showMsg(false, 'Server output (not JSON):\n' + raw.substring(0, 400));
        submitBtn.disabled = false;
        submitBtn.innerText = 'Send New Password';
        return;
      }

      showMsg(data.status === 'success', data.message || 'Done');
	  

    }catch(e){
      showMsg(false, 'Network error. Please try again.');
    }

    submitBtn.disabled = false;
    submitBtn.innerText = 'Send New Password';
  });

})();
</script>

</body>
</html>