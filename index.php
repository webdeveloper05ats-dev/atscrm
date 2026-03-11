<?php
// ===========================================
// ATS CRM - Main Router (UI + Security Fixed)
// ===========================================

// Start session only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===============================
// Load Required Files
// ===============================
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/helper.php';
require_once __DIR__ . '/core/permission.php';

// ===============================
// Authentication Check
// ===============================
if (!isLoggedIn()) {
    redirect('login.php');
    exit;
}

// ===============================
// Role-Based Default Dashboard
// ===============================
$defaultPage = 'dashboard/superadmin'; // fallback

$roleId = (int)($_SESSION['role_id'] ?? 0);
if ($roleId > 0) {
    try {
        $st = $pdo->prepare("SELECT default_dashboard_slug FROM roles WHERE id=? LIMIT 1");
        $st->execute([$roleId]);
        $slug = trim((string)$st->fetchColumn());

        if ($slug !== '') {
            $defaultPage = $slug;
        }
    } catch (Exception $e) {
        // keep fallback
    }
}

// ===============================
// Get Requested Page
// ===============================
$page = $_GET['page'] ?? $defaultPage;
$page = trim($page);

// ===============================
// Security: Prevent Directory Traversal
// ===============================
$page = str_replace(['../', '..\\'], '', $page);

// Allow only a-z A-Z 0-9 / _ -
if (!preg_match('/^[a-zA-Z0-9\/_-]+$/', $page)) {
    die('Invalid page request.');
}

// ===============================
// Build View Path
// ===============================
$viewPath = __DIR__ . '/views/' . $page . '.php';

// Check if file exists
if (!file_exists($viewPath)) {
    die('Page not found.');
}

// ===============================
// RAW Pages (no layout)
// Used for CSV/Excel/PDF/API style responses
// ===============================
$rawPages = [
    'targets/export',
    'targets/export-user-details',
];

if ($page === 'leads/import' && isset($_GET['download']) && $_GET['download'] === 'template') {
    $rawPages[] = 'leads/import';
}

if (in_array($page, $rawPages, true)) {
    require_once $viewPath;
    exit;
}

// ===============================
// AJAX: If request is AJAX, load only view (no layout)
// ===============================
$isAjax =
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_GET['ajax'])
    || isset($_POST['ajax_save']);

if ($isAjax) {
    require_once $viewPath;
    exit;
}

// ===============================
// Page Title
// ===============================
$pageTitle = ucwords(str_replace(['-', '_', '/'], ' ', $page));

// ===============================
// Load Layout + View
// ===============================
require_once __DIR__ . '/views/layout/header.php';
?>
<div class="wrapper">
    <?php require_once __DIR__ . '/views/layout/sidebar.php'; ?>

    <div class="content">
        <div class="main-content">
            <?php require_once $viewPath; ?>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/views/layout/footer.php';