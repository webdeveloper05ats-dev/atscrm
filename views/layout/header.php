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
$brandAppName = (string) crm_brand('app.name', APP_NAME);
$brandLogo = (string) crm_brand('assets.logo', 'assets/images/logo.png');
$brandFavicon = (string) crm_brand('assets.favicon', $brandLogo);
$brandFontUrl = trim((string) crm_brand('theme.font.google_url', ''));
$brandFontFamily = (string) crm_brand('theme.font.family', "'Poppins', sans-serif");
$brandVars = [
    '--crm-font-family' => $brandFontFamily,
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
$pageCssRel = '';
if (isset($page) && is_string($page) && preg_match('/^[a-zA-Z0-9\/_-]+$/', $page)) {
    $candidateRel = 'assets/css/pages/' . $page . '.css';
    $candidateAbs = ROOT_PATH . '/' . $candidateRel;
    if (is_file($candidateAbs)) {
        $pageCssRel = $candidateRel;
    }
}

// If login page sets $hideSidebar = true, we hide topbar also
$hideTopbar = (isset($hideSidebar) && $hideSidebar === true);
?>
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($brandAppName) ?></title>
<?php if ($brandFontUrl !== ''): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php endif; ?>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

<?php if ($brandFontUrl !== ''): ?>
<link href="<?= htmlspecialchars($brandFontUrl) ?>" rel="stylesheet">
<?php endif; ?>
    <?php
    $styleCssVer = @filemtime(ROOT_PATH . '/assets/css/style.css') ?: time();
    $modernSelectCssVer = @filemtime(ROOT_PATH . '/assets/css/modern-select.css') ?: $styleCssVer;
    $modernDatepickerCssVer = @filemtime(ROOT_PATH . '/assets/css/modern-datepicker.css') ?: $styleCssVer;
    $brandCssVer = @filemtime(ROOT_PATH . '/assets/css/brand.css') ?: $styleCssVer;
    $formSystemCssVer = @filemtime(ROOT_PATH . '/assets/css/form-system.css') ?: $styleCssVer;
    ?>
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= urlencode((string) $styleCssVer) ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modern-select.css?v=<?= urlencode((string) $modernSelectCssVer) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modern-datepicker.css?v=<?= urlencode((string) $modernDatepickerCssVer) ?>">
    <?php if ($pageCssRel !== ''): ?>
    <link rel="stylesheet" href="<?= BASE_URL . $pageCssRel ?>?v=<?= urlencode((string) filemtime(ROOT_PATH . '/' . $pageCssRel)) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/brand.css?v=<?= urlencode((string) $brandCssVer) ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/form-system.css?v=<?= urlencode((string) $formSystemCssVer) ?>">
    <style>:root{<?= htmlspecialchars($brandCssInline, ENT_QUOTES, 'UTF-8') ?>}</style>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="icon" type="image/png" href="<?= BASE_URL . htmlspecialchars($brandFavicon, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="shortcut icon" type="image/png" href="<?= BASE_URL . htmlspecialchars($brandFavicon, ENT_QUOTES, 'UTF-8') ?>">
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

        <a
            href="<?= BASE_URL ?>logout.php"
            class="btn btn-primary"
            style="padding:8px 12px;"
            data-modern-tooltip="Logout"
            data-mobile-label="Logout"
            aria-label="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
<?php endif; ?>
