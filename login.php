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
$brandAppName = (string) crm_brand('app.name', APP_NAME);
$brandLogo = (string) crm_brand('assets.logo', 'assets/images/logo.png');
$brandFavicon = (string) crm_brand('assets.favicon', $brandLogo);
$brandFontUrl = trim((string) crm_brand('theme.font.google_url', ''));
$brandVars = [
    '--crm-font-family' => (string) crm_brand('theme.font.family', "'Poppins', sans-serif"),
    '--crm-primary' => (string) crm_brand('theme.colors.primary', '#e91e63'),
    '--crm-primary-dark' => (string) crm_brand('theme.colors.primary_dark', '#c2185b'),
    '--crm-primary-light' => (string) crm_brand('theme.colors.primary_light', '#fce4ec'),
    '--crm-accent' => (string) crm_brand('theme.colors.accent', '#ff4d8d'),
    '--crm-text' => (string) crm_brand('theme.colors.text', '#333333'),
    '--crm-text-muted' => (string) crm_brand('theme.colors.text_muted', '#6b7280'),
    '--crm-bg-light' => (string) crm_brand('theme.colors.bg_light', '#fff7fa'),
    '--crm-border' => (string) crm_brand('theme.colors.border', '#f3c6d3'),
    '--crm-surface' => (string) crm_brand('theme.colors.surface', '#ffffff'),
    '--crm-link' => (string) crm_brand('theme.colors.link', '#be185d'),
];
$brandCssParts = [];
foreach ($brandVars as $key => $value) {
    $clean = preg_replace('/[^#(),.%+\\-\\w\\s\'"]/', '', (string) $value);
    $brandCssParts[] = $key . ':' . trim($clean);
}
$brandCssInline = implode(';', $brandCssParts);

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
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($brandAppName) ?></title>

<!-- Bootstrap -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<link rel="icon" type="image/png" href="<?= BASE_URL . htmlspecialchars($brandFavicon, ENT_QUOTES, 'UTF-8') ?>">
<link rel="shortcut icon" type="image/png" href="<?= BASE_URL . htmlspecialchars($brandFavicon, ENT_QUOTES, 'UTF-8') ?>">

<?php if ($brandFontUrl !== ''): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="<?= htmlspecialchars($brandFontUrl) ?>" rel="stylesheet">
<?php endif; ?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/brand.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/form-system.css">
<style>:root{<?= htmlspecialchars($brandCssInline, ENT_QUOTES, 'UTF-8') ?>}</style>


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
    font-family: var(--crm-font-family, 'Poppins', sans-serif) !important;
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
            --ats-pink:var(--crm-primary);
            --ats-pink-dark:var(--crm-primary-dark);
            --ats-pink-soft:var(--crm-primary-light);
            --ats-ink:var(--crm-text);
            --ats-text:var(--crm-text);
            --ats-muted:var(--crm-text-muted);
            --ats-line:var(--crm-border);
            --ats-bg:var(--crm-bg-light);
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
            overflow-x:hidden;
            overflow-y:auto;
            -webkit-overflow-scrolling:touch;
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
            font-size:clamp(1.6rem, 2vw, 2rem);
            line-height:1.08;
            letter-spacing:-.02em;
        }
        .auth-header p{
            margin:0;
            font-size:0.95rem;
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
            font-size:0.75rem;
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
            font-size:0.9rem;
            font-weight:700;
            color:#fff;
            margin-bottom:2px;
        }
        .auth-point span{
            display:block;
            font-size:0.85rem;
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
            font-size:clamp(2rem, 2.5vw, 2.4rem);
            letter-spacing:-.03em;
        }
        .auth-sub{
            color:var(--ats-muted);
            font-size:0.95rem;
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
            font-size:0.95rem;
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
            font-size: 0.9rem;
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
            font-size:0.85rem;
            flex-wrap:nowrap;
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
            font-size:0.95rem;
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
            text-align:right;
            font-size:0.85rem;
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
        .ats-modal-header h5{ margin:0; font-weight:800; font-size:1rem; }
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
                font-size:clamp(1.6rem, 2vw, 2rem);
            }
            .auth-body{
                padding:28px 24px 22px;
            }
            .auth-title{
                font-size:clamp(2rem, 2.5vw, 2.4rem);
            }
            .auth-points{
                display:none;
            }
            .auth-wrap{
                align-items:center;
                overflow-y:hidden;
            }
            .auth-footer{
                margin-top:10px;
                padding-top:12px;
            }
        }
        @media (max-width: 767.98px){
            .auth-wrap{
                align-items:center;
                padding:14px;
            }
            .auth-card{
                max-width:100%;
                border-radius:20px;
                box-shadow:0 18px 48px rgba(44, 26, 39, 0.12);
            }
            .auth-header{
                padding:18px 16px;
                gap:14px;
            }
            .auth-brand{
                gap:10px;
            }
            .auth-logo{
                width:46px;
                height:46px;
                border-radius:12px;
                flex:0 0 46px;
                padding:6px;
            }
            .auth-header h4{
                font-size:1.3rem;
                line-height:1.16;
            }
            .auth-header p{
                font-size:0.95rem;
                line-height:1.5;
                max-width:none;
            }
            .auth-kicker{
                font-size:0.75rem;
                letter-spacing:.08em;
                padding:7px 10px;
            }
            .auth-points{
                display:none;
            }
            .auth-body{
                padding:20px 16px 16px;
            }
            .auth-title{
                font-size:clamp(2rem, 2.5vw, 2.4rem);
                margin-bottom:4px;
            }
            .auth-sub{
                font-size:0.95rem;
                margin-bottom:18px;
            }
            .auth-field{
                padding:11px 12px;
                border-radius:14px;
                gap:10px;
            }
            .auth-icon{
                width:36px;
                height:36px;
                border-radius:12px;
                flex:0 0 36px;
            }
            .auth-input{
                font-size:0.95rem;
            }
            .password-toggle{
                font-size:0.9rem;
            }
            .auth-links{
                margin:12px 0 16px;
            }
            .auth-btn{
                height:48px;
                border-radius:14px;
                font-size:0.95rem;
            }
            .auth-footer{
                margin-top:0;
                padding:12px 16px 16px;
                font-size:0.75rem;
                border-top:1px solid #f3f4f6;
            }
        }
        @media (max-width: 575.98px){
            .auth-wrap{
                padding:8px;
                overflow-y:hidden;
            }
            .auth-card{
                border-radius:16px;
            }
            .auth-header{
                padding:14px 14px;
                gap:10px;
            }
            .auth-body{
                padding:14px 14px 12px;
            }
            .auth-title{
                font-size:clamp(2rem, 2.5vw, 2.4rem);
                line-height:1;
            }
            .auth-links{
                gap:8px;
                align-items:center;
                justify-content:space-between;
                flex-direction:row;
                flex-wrap:nowrap;
            }
            .auth-footer{
                text-align:center;
                margin-top:0;
                padding:8px 14px 10px;
                font-size:0.75rem;
            }
            .auth-header h4{ font-size:1.3rem; line-height:1.2; margin:0 0 2px; }
            .auth-header p{ font-size:0.85rem; line-height:1.35; }
            .auth-kicker{ font-size:0.75rem; padding:6px 9px; }
            .auth-logo{ width:40px; height:40px; flex:0 0 40px; }
            .auth-sub{ margin-bottom:12px; font-size:0.95rem; line-height:1.4; }
            .form-group{ margin-bottom:10px; }
            .auth-field{ padding:8px 10px; border-radius:12px; }
            .auth-icon{ width:32px; height:32px; flex:0 0 32px; border-radius:10px; }
            .auth-input{ font-size:0.95rem; }
            .auth-links{ margin:8px 0 10px; font-size:0.85rem; }
            .auth-btn{ height:46px; border-radius:12px; font-size:0.95rem; }
        }
        @media (max-width: 575.98px) and (max-height: 700px){
            .auth-wrap{ padding:6px; align-items:flex-start; }
            .auth-header{ padding:12px; }
            .auth-body{ padding:12px 12px 10px; }
            .auth-sub{ margin-bottom:10px; }
            .form-group{ margin-bottom:8px; }
            .auth-links{ margin:6px 0 8px; }
            .auth-btn{ height:44px; }
            .auth-footer{ padding:6px 12px 8px; }
        }
        @media (max-height: 720px) and (max-width: 767.98px){
            .auth-wrap{ align-items:flex-start; padding-top:14px; padding-bottom:14px; }
            .auth-card{ min-height:auto; }
            .auth-points{ display:none; }
        }
    

/* =====================================================
TYPOGRAPHY SYNC WITH assets/css/style.css
===================================================== */
:where(body,button,input,select,textarea,label,span,p,h1,h2,h3,h4,h5,h6,a,div){
  font-family:'Poppins',sans-serif !important;
}

:where(h1,.h1,.page-title,.crm-page-title){
  font-size:clamp(2rem, 2.5vw, 2.4rem) !important;
}

:where(h2,.h2,.section-title){
  font-size:clamp(1.6rem, 2vw, 2rem) !important;
}

:where(h3,.h3,.card-header,.table-title){
  font-size:clamp(1.3rem, 1.6vw, 1.5rem) !important;
}

:where(h4,.h4){
  font-size:1.2rem !important;
}

:where(h5,.h5){
  font-size:1rem !important;
}

:where(h6,.h6){
  font-size:0.9rem !important;
}

:where(body){
  font-size:1rem !important;
}

:where(p,.text-body,li){
  font-size:0.95rem !important;
}

:where(small,.small,.text-muted,.help-text,.form-text,.att-sub,.crm-note){
  font-size:0.85rem !important;
}

:where(label,.form-label,th,.table thead th,.dataTables_wrapper .dataTables_length label,.dataTables_wrapper .dataTables_filter label){
  font-size:0.85rem !important;
}

:where(input,select,textarea,.form-control,.form-select,.dataTables_wrapper .dataTables_filter input,.dataTables_wrapper .dataTables_length select){
  font-size:0.95rem !important;
}

:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){
  font-size:0.9rem !important;
}

:where(.btn[data-mobile-label],.btn-icon-only[data-mobile-label],.action-btn[data-mobile-label],.crm-icon-btn[data-mobile-label],.targets-btn-icon[data-mobile-label],.iso-report-icon-btn[data-mobile-label],.iso-report-action-btn[data-mobile-label])::after{
  font-size:0.75rem !important;
}

:where(.table th,.crm-table th,.dataTables_wrapper th){
  font-size:0.75rem !important;
}

:where(td,.table td,.dataTables_wrapper tbody td){
  font-size:0.9rem !important;
}

:where(.dataTables_wrapper .dataTables_info){
  font-size:0.85rem !important;
}

:where(.dataTables_wrapper .paginate_button){
  font-size:0.9rem !important;
}
/* =====================================================
FONT-WEIGHT STANDARDIZATION
===================================================== */
:where(h1,.h1,.page-title,.crm-page-title,.dashboard-header h2){
  font-weight:700 !important;
}

:where(h2,.h2){
  font-weight:600 !important;
}

:where(h3,.h3,.card-header,.table-title){
  font-weight:600 !important;
}

:where(h4,.h4){
  font-weight:500 !important;
}

:where(h5,.h5){
  font-weight:500 !important;
}

:where(h6,.h6){
  font-weight:500 !important;
}

:where(th,.table thead th,.crm-table th,.dataTables_wrapper th){
  font-weight:600 !important;
}

:where(p,li,td,.text-body,.text-muted,.help-text,.form-text,.small,small,.secondary-text){
  font-weight:400 !important;
}

:where(label,.form-label){
  font-weight:500 !important;
}

:where(input,select,textarea,.form-control,.form-select){
  font-weight:400 !important;
}

:where(input::placeholder,textarea::placeholder){
  font-weight:400 !important;
}

:where(button,.btn,.dt-button,.crm-action-btn,.crm-icon-btn,.btn-icon-only,.action-btn,.targets-btn-icon,.iso-report-btn,.iso-report-action-btn){
  font-weight:600 !important;
}

:where(.badge,.status-badge,.crm-status-badge,.status-pill,.badge-status,[data-status],.tooltip,.ui-tooltip,.floating-ui-tooltip__bubble){
  font-weight:600 !important;
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

<div class="auth-wrap">
    <div class="auth-card">

        <div class="auth-header">
            <div class="auth-side-copy">
                <div class="auth-kicker">
                    <i class="fas fa-shield-alt"></i>
                    ATS CRM Workspace
                </div>
                <div class="auth-brand">
                    <img class="auth-logo" src="<?= BASE_URL . htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandAppName) ?> Logo">
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

            <form method="POST" id="loginForm" autocomplete="off" data-focus-start="on" data-focus-target="input[name='email']">
                <input type="text" style="display:none">
                <input type="password" style="display:none">

                <div class="form-group">
                    <label class="mb-1" style="font-weight:700;font-size:0.85rem;">Email</label>
                    <div class="auth-field">
                        <div class="auth-icon"><i class="fa fa-envelope"></i></div>
                        <input type="text" name="email" class="auth-input"
                               placeholder="name@company.com"
                               autocomplete="off" autocapitalize="off" spellcheck="false"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="mb-1" style="font-weight:700;font-size:0.85rem;">Password</label>
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
        <div style="font-size:0.85rem;color:#6b7280;margin-bottom:10px;">
            Enter your registered email. We'll generate a new password and send it to you.
        </div>

        <div class="form-group">
            <label class="mb-1" style="font-weight:700;font-size:0.85rem;">Email</label>
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

<script src="assets/js/crm-focus-start.js"></script>
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
    msg.style.fontSize = '0.85rem';
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



