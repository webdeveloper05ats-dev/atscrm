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
            remember_issue($pdo, (int)$user['id'], 30);
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

<link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/logo.png">
<link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>assets/images/logo.png">

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
            --ats-pink:#ec3d8f;
            --ats-pink-dark:#cf2f78;
            --ats-pink-soft:#fff2f8;
            --ats-ink:#172033;
            --ats-text:#24324a;
            --ats-muted:#6d7788;
            --ats-line:#f2dbe6;
            --ats-bg:#fff8fb;
            --radius:28px;
        }
        html, body{
            height:100%;
            margin:0;
            background: var(--ats-bg);
        }
        body{
            color:var(--ats-text);
        }
        .auth-wrap{
            min-height:100%;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:28px;
            position:relative;
            overflow:hidden;
            background:
                radial-gradient(820px 420px at 12% 14%, rgba(255,196,222,.72) 0%, transparent 58%),
                radial-gradient(760px 420px at 88% 10%, rgba(255,210,229,.8) 0%, transparent 55%),
                radial-gradient(720px 420px at 50% 100%, rgba(255,231,240,.88) 0%, transparent 58%),
                linear-gradient(180deg, #fff7fb 0%, #fffefe 60%, #fff5f9 100%);
        }
        .auth-wrap::before,
        .auth-wrap::after{
            content:"";
            position:absolute;
            border-radius:999px;
            pointer-events:none;
        }
        .auth-wrap::before{
            width:340px;
            height:340px;
            top:-90px;
            right:-90px;
            background:linear-gradient(135deg, rgba(236,61,143,.18), rgba(255,173,208,.04));
        }
        .auth-wrap::after{
            width:260px;
            height:260px;
            left:-70px;
            bottom:-60px;
            background:linear-gradient(135deg, rgba(255,194,220,.28), rgba(236,61,143,.06));
        }
        .auth-card{
            width:100%;
            max-width:1020px;
            min-height:620px;
            border-radius: var(--radius);
            box-shadow: 0 34px 90px rgba(44, 26, 39, 0.14);
            overflow:hidden;
            background:rgba(255,255,255,.84);
            border:1px solid rgba(255,255,255,.72);
            backdrop-filter: blur(16px);
            display:grid;
            grid-template-columns: 1.05fr .95fr;
            position:relative;
            z-index:1;
        }
        .auth-header{
            position:relative;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            gap:24px;
            padding:34px 32px;
            background:
                radial-gradient(220px 180px at 10% 20%, rgba(255,255,255,.18) 0%, transparent 80%),
                radial-gradient(260px 220px at 90% 10%, rgba(255,255,255,.15) 0%, transparent 78%),
                linear-gradient(155deg, #ff67ab 0%, var(--ats-pink) 42%, var(--ats-pink-dark) 100%);
            color:#fff;
        }
        .auth-brand{
            display:flex;
            align-items:flex-start;
            gap:14px;
        }
        .auth-logo{
            width:58px;
            height:58px;
            border-radius:16px;
            background:#fff;
            padding:8px;
            object-fit:contain;
            box-shadow: 0 16px 30px rgba(79, 15, 46, 0.26);
            flex:0 0 58px;
        }
        .auth-header h4{
            margin:4px 0 6px;
            font-weight:800;
            font-size:28px;
            line-height:1.08;
            letter-spacing:-.02em;
        }
        .auth-header p{
            margin:0;
            font-size:14px;
            line-height:1.6;
            color:rgba(255,255,255,.92);
            max-width:380px;
        }
        .auth-kicker{
            display:inline-flex;
            align-items:center;
            gap:8px;
            width:max-content;
            padding:9px 14px;
            border-radius:999px;
            background:rgba(255,255,255,.16);
            border:1px solid rgba(255,255,255,.18);
            font-size:11px;
            font-weight:800;
            letter-spacing:.12em;
            text-transform:uppercase;
            backdrop-filter: blur(8px);
        }
        .auth-side-copy{
            display:grid;
            gap:18px;
        }
        .auth-points{
            display:grid;
            gap:12px;
        }
        .auth-point{
            display:flex;
            align-items:flex-start;
            gap:10px;
            padding:14px 16px;
            border-radius:18px;
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.12);
        }
        .auth-point-icon{
            width:36px;
            height:36px;
            border-radius:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:rgba(255,255,255,.18);
            color:#fff;
            flex:0 0 36px;
        }
        .auth-point strong{
            display:block;
            font-size:13px;
            font-weight:700;
            color:#fff;
            margin-bottom:2px;
        }
        .auth-point span{
            display:block;
            font-size:12px;
            line-height:1.5;
            color:rgba(255,255,255,.86);
        }
        .auth-body{
            padding:36px 34px 24px;
            display:flex;
            flex-direction:column;
            justify-content:center;
            background:linear-gradient(180deg, rgba(255,255,255,.74) 0%, #ffffff 100%);
        }
        .auth-title{
            font-weight:800;
            color:var(--ats-ink);
            margin-bottom:6px;
            font-size:34px;
            letter-spacing:-.03em;
        }
        .auth-sub{
            color:var(--ats-muted);
            font-size:14px;
            margin-bottom:24px;
            line-height:1.6;
        }
        .form-group{ margin-bottom:16px; }
        .auth-field{
            width:100%;
            display:flex;
            align-items:center;
            gap:12px;
            padding:13px 15px;
            border:1px solid var(--ats-line);
            border-radius:18px;
            background:#fff;
            transition:.22s ease;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.85);
        }
        .auth-field:hover{
            border-color:#f1a9c7;
        }
        .auth-field:focus-within{
            border-color:#ff77ad;
            box-shadow:0 0 0 4px rgba(233,30,99,.10), 0 10px 24px rgba(236,61,143,.08);
        }
        .auth-icon{
            width:40px;
            height:40px;
            border-radius:14px;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(180deg,#fff2f8 0%,#ffe7f2 100%);
            color:var(--ats-pink);
            flex:0 0 40px;
        }
        .auth-input{
            flex:1; min-width:0;
            border:0; outline:none;
            font-size:15px;
            color:var(--ats-text);
            background:transparent;
        }
        .auth-input::placeholder{ color:#99a1b2; }
        
        /* Password toggle styles */
        .password-toggle {
            background: transparent;
            border: none;
            color: var(--ats-pink);
            cursor: pointer;
            padding: 0 6px;
            font-size: 16px;
            opacity: 0.72;
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
            margin: 14px 0 20px;
            font-size:13px;
        }
        .auth-links a{ color:var(--ats-ink); text-decoration:none; font-weight:700; }
        .auth-links a:hover{ text-decoration:underline; cursor:pointer; }
        .auth-btn{
            width:100%;
            height:52px;
            border:0;
            border-radius:18px;
            background: linear-gradient(135deg, var(--ats-pink-dark) 0%, var(--ats-pink) 45%, #ff79b7 100%);
            color:#fff;
            font-weight:800;
            font-size:16px;
            cursor:pointer;
            box-shadow: 0 18px 34px rgba(233,30,99,.24);
            transition:.2s ease;
        }
        .auth-btn:hover{
            transform: translateY(-2px);
            box-shadow: 0 22px 38px rgba(233,30,99,.30);
        }
        .auth-footer{
            padding:18px 0 0;
            text-align:left;
            font-size:12px;
            color:var(--ats-muted);
            border-top:1px solid #f3f4f6;
            margin-top:22px;
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

        @media (max-width: 991.98px){
            .auth-card{
                max-width:520px;
                min-height:auto;
                grid-template-columns:1fr;
            }
            .auth-header{
                padding:28px 24px;
            }
            .auth-header h4{
                font-size:24px;
            }
            .auth-body{
                padding:28px 24px 22px;
            }
            .auth-title{
                font-size:30px;
            }
        }
        @media (max-width: 575.98px){
            .auth-wrap{
                padding:16px;
            }
            .auth-card{
                border-radius:22px;
            }
            .auth-header{
                padding:22px 18px;
            }
            .auth-body{
                padding:22px 18px 18px;
            }
            .auth-title{
                font-size:28px;
            }
            .auth-links{
                gap:10px;
                align-items:flex-start;
                flex-direction:column;
            }
        }
        @media (max-height: 720px){
            .auth-wrap{ align-items:flex-start; padding-top:22px; padding-bottom:22px; }
            .auth-card{ min-height:auto; }
        }
    </style>
</head>
<body>

<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-side-copy">
                <div class="auth-kicker">
                    <i class="fas fa-shield-alt"></i>
                    ATS CRM Workspace
                </div>
                <div class="auth-brand">
                    <img class="auth-logo" src="<?= BASE_URL ?>assets/images/logo.png" alt="ATS Logo">
                    <div>
                        <h4>Welcome to <?= htmlspecialchars(APP_NAME) ?></h4>
                        <p>Centralize admissions, collections, team activity, and branch operations in one focused workspace.</p>
                    </div>
                </div>
            </div>
            <div class="auth-points">
                <div class="auth-point">
                    <div class="auth-point-icon"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <strong>Daily performance visibility</strong>
                        <span>Track targets, collections, and branch activity from a single product dashboard.</span>
                    </div>
                </div>
                <div class="auth-point">
                    <div class="auth-point-icon"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <strong>Role-based secure access</strong>
                        <span>Each team signs in to the same CRM with the right permissions for their work.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-body">
            <h5 class="auth-title">Sign in</h5>
            <div class="auth-sub">Use your registered credentials to continue to your CRM workspace.</div>

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
            &copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>. All rights reserved.
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

      const raw = await res.text();   // âœ… read raw text first
      let data = null;

      try {
        data = JSON.parse(raw);       // âœ… parse JSON safely
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
