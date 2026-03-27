<?php
// ============================================
// ATS CRM - Dynamic Header Layout (Final)
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// Protect all pages except login
if (!isset($noAuth)) {
    requireLogin();
}

// User info
$user_name   = $_SESSION['user_name'] ?? 'User';
$user_role   = $_SESSION['role_name'] ?? 'Role';
$branch_name = $_SESSION['branch_name'] ?? 'Branch';

// Page title
$pageTitle = $pageTitle ?? APP_NAME;

// If login page sets $hideSidebar = true, we hide topbar also
$hideTopbar = (isset($hideSidebar) && $hideSidebar === true);
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars(APP_NAME) ?></title>
<!-- Google Font: Poppins -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>assets/images/logo.png">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<?php if (!$hideTopbar): ?>
<!-- TOPBAR -->
<div class="topbar">
    <div class="toggle-btn" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </div>

    <div style="display:flex; align-items:center; gap:12px;">
        <div style="text-align:right;">
            <div style="font-weight:700; color:var(--text-dark);">
                <?= htmlspecialchars($user_name) ?>
            </div>
            <div style="font-size:12px; color:var(--text-light);">
                <?= htmlspecialchars($user_role) ?> • <?= htmlspecialchars($branch_name) ?>
            </div>
        </div>

        <a href="<?= BASE_URL ?>logout.php" class="btn btn-primary" style="padding:8px 12px;">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
<?php endif; ?>
