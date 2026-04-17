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
$requestedPage = isset($_GET['page']) ? trim((string)$_GET['page']) : '';
$page = $requestedPage !== '' ? $requestedPage : $defaultPage;
$page = trim((string)$page, " \t\n\r\0\x0B/");

// ===============================
// Security: Prevent Directory Traversal
// ===============================
$page = str_replace(['../', '..\\'], '', $page);
$page = str_replace('\\', '/', $page);

// Allow only a-z A-Z 0-9 / _ -
if (!preg_match('/^[a-zA-Z0-9\/_-]+$/', $page)) {
    die('Invalid page request.');
}

// Demo Reset page is retired and must not be routable.
if ($page === 'system/demo_reset') {
    http_response_code(404);
    die('Page not found.');
}

// ===============================
// Build View Path
// ===============================
$viewPath = __DIR__ . '/views/' . $page . '.php';

// If requested/default page is missing, auto-fallback to valid dashboard page
if (!file_exists($viewPath)) {
    $fallbackPages = [
        $defaultPage,
        'dashboard/superadmin',
        'dashboard/staff',
        'dashboard/marketing',
        'dashboard/frontoffice',
        'dashboard/hr',
        'dashboard/test'
    ];

    $fallbackFound = false;
    foreach (array_unique($fallbackPages) as $candidate) {
        $candidate = trim((string)$candidate, " \t\n\r\0\x0B/");
        $candidate = str_replace(['../', '..\\'], '', $candidate);
        $candidate = str_replace('\\', '/', $candidate);
        if ($candidate === '' || !preg_match('/^[a-zA-Z0-9\/_-]+$/', $candidate)) {
            continue;
        }

        $candidateView = __DIR__ . '/views/' . $candidate . '.php';
        if (file_exists($candidateView)) {
            $page = $candidate;
            $viewPath = $candidateView;
            $fallbackFound = true;
            break;
        }
    }

    if (!$fallbackFound) {
        die('Page not found.');
    }
}

// ===============================
// Audit Logging (Mutation Requests)
// ===============================
if (
    isset($pdo) && $pdo instanceof PDO
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && $page !== 'system/audit_logs'
) {
    crmAuditLogPageMutation($pdo, $page);
}

// ===============================
// RAW Pages (no layout)
// Used for CSV/Excel/PDF/API style responses
// ===============================
$rawPages = [
    'targets/export',
    'targets/export-user-details',
    'reports/export_course',        // ⭐ ADD THIS

    'reports/export_internship'   // ⭐ ADD THIS
    ,
    'reports/export_student_schedule',
    'reports/export_student_overall',
    'students/course_certificate'
];

if ($page === 'leads/import' && isset($_GET['download']) && $_GET['download'] === 'template') {
    $rawPages[] = 'leads/import';
}

if ($page === 'dailyreports/export' && isset($_GET['action']) && in_array($_GET['action'], ['export','export_xlsx'], true)) {
    $rawPages[] = 'dailyreports/export';
}

if ($page === 'dailyreports/view' && isset($_GET['action']) && $_GET['action'] === 'download') {
    $rawPages[] = 'dailyreports/view';
}

if ($page === 'system/backup_health' && isset($_GET['export_uploads']) && (string)$_GET['export_uploads'] === '1') {
    $rawPages[] = 'system/backup_health';
}
if ($page === 'system/backup_health' && isset($_GET['export_db']) && (string)$_GET['export_db'] === '1') {
    $rawPages[] = 'system/backup_health';
}
if ($page === 'system/backup_health' && isset($_GET['download_backup']) && $_GET['download_backup'] !== '') {
    $rawPages[] = 'system/backup_health';
}
if ($page === 'system/backup_health' && isset($_GET['health_api']) && (string)$_GET['health_api'] === '1') {
    $rawPages[] = 'system/backup_health';
}
if ($page === 'system/onboarding_export') {
    $rawPages[] = 'system/onboarding_export';
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
