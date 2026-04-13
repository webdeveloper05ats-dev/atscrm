<?php
// ============================================
// ATS CRM - Dynamic Header Layout (Final)
// ============================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (!function_exists('crmAppendQueryParam')) {
    function crmAppendQueryParam($url, $key, $value)
    {
        $url = (string)$url;
        $joiner = (strpos($url, '?') !== false) ? '&' : '?';
        return $url . $joiner . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }
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

// Target notifications (phase 1 for bell system)
$topNotifications = [];
if (!$hideTopbar && isset($pdo) && $pdo instanceof PDO && !empty($_SESSION['user_id']) && !empty($_SESSION['branch_id'])) {
    $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
    $sessionBranchId = (int)($_SESSION['branch_id'] ?? 0);
    $roleNameLower = strtolower(trim((string)($_SESSION['role_name'] ?? '')));

    // Mark a persistent notification as read.
    if (isset($_GET['notif_read']) && ctype_digit((string)$_GET['notif_read'])) {
        $readId = (int)$_GET['notif_read'];
        if ($readId > 0) {
            try {
                $stmtRead = $pdo->prepare("
                    UPDATE user_notifications
                    SET is_read = 1, read_at = NOW()
                    WHERE id = :id
                      AND user_id = :user_id
                      AND is_read = 0
                    LIMIT 1
                ");
                $stmtRead->execute([
                    ':id' => $readId,
                    ':user_id' => $sessionUserId,
                ]);
            } catch (Throwable $e) {
                // Ignore read failures to keep navigation safe.
            }

            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $parsed = parse_url($requestUri);
            $path = $parsed['path'] ?? (BASE_URL . 'index.php');
            $queryParams = [];
            if (!empty($parsed['query'])) {
                parse_str($parsed['query'], $queryParams);
            }
            unset($queryParams['notif_read']);
            $newQuery = http_build_query($queryParams);
            $redirectTo = $path . ($newQuery !== '' ? ('?' . $newQuery) : '');
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    try {
        // Persistent notifications table (extendable for leads/enquiries later).
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                notif_key VARCHAR(191) NOT NULL,
                type VARCHAR(80) NOT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'medium',
                title VARCHAR(191) NOT NULL,
                message TEXT NOT NULL,
                link VARCHAR(255) NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                read_at DATETIME NULL,
                expires_at DATETIME NULL,
                UNIQUE KEY uniq_user_notif (user_id, notif_key),
                KEY idx_user_read_created (user_id, is_read, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Admin reminder:
        // - day 1 to 26: check current month coverage
        // - day 27 onward: check next month coverage
        if (in_array($roleNameLower, ['super admin', 'hr'], true)) {
            $todayDay = (int) date('j');
            $targetPeriodDate = $todayDay >= 27
                ? new DateTimeImmutable('first day of next month')
                : new DateTimeImmutable('first day of this month');
            $targetYear = (int) $targetPeriodDate->format('Y');
            $targetMonthNo = (int) $targetPeriodDate->format('n');
            $targetPeriodLabel = $targetPeriodDate->format('F Y');

            $stmtRoles = $pdo->prepare("
                SELECT
                    r.id,
                    r.role_name,
                    COUNT(DISTINCT u.id) AS active_staff_count,
                    COUNT(DISTINCT mt.user_id) AS covered_target_staff_count
                FROM roles r
                LEFT JOIN users u
                    ON u.role_id = r.id
                   AND u.branch_id = :branch_id_users
                   AND u.status = 1
                LEFT JOIN monthly_targets mt
                    ON mt.role_id = r.id
                   AND mt.user_id = u.id
                   AND mt.branch_id = :branch_id_targets
                   AND mt.target_year = :target_year
                   AND mt.target_month = :target_month
                   AND mt.status = 'active'
                WHERE r.status = 1
                  AND r.is_target_applicable = 1
                  AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'hr', 'marketing')
                GROUP BY r.id, r.role_name
                ORDER BY r.role_name ASC
            ");
            $stmtRoles->execute([
                ':branch_id_users' => $sessionBranchId,
                ':branch_id_targets' => $sessionBranchId,
                ':target_year' => $targetYear,
                ':target_month' => $targetMonthNo,
            ]);
            $roleRows = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

            $missingRoles = [];
            foreach ($roleRows as $roleRow) {
                $activeCount = (int)($roleRow['active_staff_count'] ?? 0);
                $coveredCount = (int)($roleRow['covered_target_staff_count'] ?? 0);
                // Skip role if no active staff in this branch.
                if ($activeCount <= 0) {
                    continue;
                }
                if ($coveredCount < 1) {
                    $missingRoles[] = (string)($roleRow['role_name'] ?? 'Role');
                }
            }

            if (!empty($missingRoles)) {
                $topNotifications[] = [
                    'priority' => 'high',
                    'title' => 'Target Setup Pending',
                    'message' => $targetPeriodLabel . ' target missing for roles: ' . implode(', ', $missingRoles) . '.',
                    'link' => BASE_URL . 'index.php?page=targets/setup',
                    'link_label' => 'Go to setup',
                ];
            }
        }

        // Target-applicable user checks (includes HR personal stream).
        $stmtUserRole = $pdo->prepare("
            SELECT r.is_target_applicable, LOWER(COALESCE(r.role_name, '')) AS role_name_lc
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = :user_id
              AND u.branch_id = :branch_id
            LIMIT 1
        ");
        $stmtUserRole->execute([
            ':user_id' => $sessionUserId,
            ':branch_id' => $sessionBranchId,
        ]);
        $roleRow = $stmtUserRole->fetch(PDO::FETCH_ASSOC);
        $isTargetApplicableUser = (int)($roleRow['is_target_applicable'] ?? 0) === 1
            && in_array((string)($roleRow['role_name_lc'] ?? ''), ['front office', 'hr', 'marketing'], true);

        if ($isTargetApplicableUser) {
            $currentYear = (int) date('Y');
            $currentMonthNo = (int) date('n');
            $currentMonthLabel = date('F Y');

            $stmtMyTarget = $pdo->prepare("
                SELECT id, target_year, target_month, target_amount, created_at, updated_at
                FROM monthly_targets
                WHERE branch_id = :branch_id
                  AND user_id = :user_id
                  AND target_year = :target_year
                  AND target_month = :target_month
                  AND status = 'active'
                ORDER BY updated_at DESC, id DESC
                LIMIT 1
            ");
            $stmtMyTarget->execute([
                ':branch_id' => $sessionBranchId,
                ':user_id' => $sessionUserId,
                ':target_year' => $currentYear,
                ':target_month' => $currentMonthNo,
            ]);
            $myTarget = $stmtMyTarget->fetch(PDO::FETCH_ASSOC);

            if ($myTarget && (float)($myTarget['target_amount'] ?? 0) > 0) {
                $targetAmount = (float)$myTarget['target_amount'];
                $targetId = (int)($myTarget['id'] ?? 0);
                $targetStampRaw = (string)($myTarget['updated_at'] ?? $myTarget['created_at'] ?? '');
                $targetStamp = $targetStampRaw !== '' ? strtotime($targetStampRaw) : time();
                $notifKey = 'target_assigned_' . $targetId . '_' . (int)$targetStamp;

                // One-time notification for newly assigned/updated target.
                $stmtInsertNotif = $pdo->prepare("
                    INSERT INTO user_notifications
                        (user_id, notif_key, type, priority, title, message, link, expires_at)
                    VALUES
                        (:user_id, :notif_key, 'target_assigned', 'medium', :title, :message, :link, :expires_at)
                    ON DUPLICATE KEY UPDATE id = id
                ");
                $stmtInsertNotif->execute([
                    ':user_id' => $sessionUserId,
                    ':notif_key' => $notifKey,
                    ':title' => 'New Target Assigned',
                    ':message' => 'Your target for ' . $currentMonthLabel . ' is Rs ' . number_format($targetAmount, 2) . '.',
                    ':link' => BASE_URL . 'index.php?page=targets/my-target',
                    ':expires_at' => date('Y-m-t 23:59:59'),
                ]);

                $stmtAch = $pdo->prepare("
                    SELECT COALESCE(SUM(amount), 0) AS achieved_amount
                    FROM registration_payments
                    WHERE branch_id = :branch_id
                      AND collected_by = :user_id
                      AND approval_status = 'approved'
                      AND YEAR(payment_date) = :target_year
                      AND MONTH(payment_date) = :target_month
                ");
                $stmtAch->execute([
                    ':branch_id' => $sessionBranchId,
                    ':user_id' => $sessionUserId,
                    ':target_year' => $currentYear,
                    ':target_month' => $currentMonthNo,
                ]);
                $achievedAmount = (float)($stmtAch->fetchColumn() ?: 0);
                $shortfall = max($targetAmount - $achievedAmount, 0);
                $dayNow = (int) date('j');
                $daysInCurrentMonth = (int) date('t');
                $isLastTwoDays = $dayNow >= max(1, $daysInCurrentMonth - 1);

                // Urgent reminder only in last 2 days and only if target still pending.
                if ($isLastTwoDays && $shortfall > 0) {
                    $topNotifications[] = [
                        'priority' => 'high',
                        'title' => 'Target Deadline Alert',
                        'message' => 'You still need Rs ' . number_format($shortfall, 2) . ' to finish your ' . $currentMonthLabel . ' target.',
                        'link' => BASE_URL . 'index.php?page=targets/my-target',
                        'link_label' => 'View my target',
                    ];
                }
            }
        }

        // Load unread persistent notifications for this user.
        $stmtUnread = $pdo->prepare("
            SELECT id, priority, title, message, link
            FROM user_notifications
            WHERE user_id = :user_id
              AND is_read = 0
              AND (expires_at IS NULL OR expires_at >= NOW())
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmtUnread->execute([':user_id' => $sessionUserId]);
        $unreadRows = $stmtUnread->fetchAll(PDO::FETCH_ASSOC);
        foreach ($unreadRows as $unread) {
            $topNotifications[] = [
                'id' => (int)($unread['id'] ?? 0),
                'priority' => (string)($unread['priority'] ?? 'medium'),
                'title' => (string)($unread['title'] ?? 'Notification'),
                'message' => (string)($unread['message'] ?? ''),
                'link' => (string)($unread['link'] ?? (BASE_URL . 'index.php')),
                'link_label' => 'Open',
                'can_mark_read' => true,
            ];
        }
    } catch (Throwable $e) {
        // Keep header safe even if notification queries fail.
        $topNotifications = [];
    }
}
$topNotificationCount = count($topNotifications);
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
    <style>
    .crm-notif-wrap{position:relative;}
    .crm-notif-btn{
        width:38px;height:38px;border:1px solid var(--crm-border);border-radius:10px;background:#fff;
        display:inline-flex;align-items:center;justify-content:center;color:#4b5563;position:relative;
    }
    .crm-notif-badge{
        position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;border-radius:999px;
        background:var(--crm-primary);color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 5px;
    }
    .crm-notif-dropdown{
        position:absolute;top:46px;right:0;width:340px;max-width:90vw;background:#fff;border:1px solid #f1d6e3;border-radius:12px;
        box-shadow:0 12px 28px rgba(15,23,42,.16);padding:10px;display:none;z-index:1200;
    }
    .crm-notif-dropdown.show{display:block;}
    .crm-notif-head{font-size:13px;font-weight:700;color:#1f2937;padding:4px 6px 8px;border-bottom:1px solid #f5e2eb;margin-bottom:6px;}
    .crm-notif-item{border:1px solid #f3e2eb;border-radius:10px;padding:8px 10px;margin-bottom:8px;background:#fff;}
    .crm-notif-item:last-child{margin-bottom:0;}
    .crm-notif-item .title{font-size:12px;font-weight:700;color:#1f2937;margin-bottom:3px;}
    .crm-notif-item .msg{font-size:12px;line-height:1.4;color:#6b7280;margin-bottom:6px;}
    .crm-notif-item .link{font-size:11px;font-weight:700;color:var(--crm-primary);text-decoration:none;}
    .crm-notif-item .meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
    .crm-notif-item .read-link{font-size:11px;font-weight:700;color:#64748b;text-decoration:none;}
    .crm-notif-item.high{border-left:4px solid #ef4444;}
    .crm-notif-item.medium{border-left:4px solid #e91e63;}
    .crm-notif-empty{font-size:12px;color:#6b7280;padding:8px 6px;}
    </style>

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
        <div class="crm-notif-wrap">
            <button type="button" class="crm-notif-btn" id="crmNotifBell" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($topNotificationCount > 0): ?>
                    <span class="crm-notif-badge"><?= (int)$topNotificationCount ?></span>
                <?php endif; ?>
            </button>
            <div class="crm-notif-dropdown" id="crmNotifDropdown" aria-live="polite">
                <div class="crm-notif-head">Notifications</div>
                <?php if ($topNotificationCount > 0): ?>
                    <?php foreach ($topNotifications as $notif): ?>
                        <?php
                            $notifLink = (string)($notif['link'] ?? BASE_URL);
                            if (!empty($notif['can_mark_read']) && !empty($notif['id'])) {
                                $notifLink = crmAppendQueryParam($notifLink, 'notif_read', (int)$notif['id']);
                            }
                        ?>
                        <div class="crm-notif-item <?= htmlspecialchars((string)($notif['priority'] ?? 'medium'), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="title"><?= htmlspecialchars((string)($notif['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="msg"><?= htmlspecialchars((string)($notif['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="meta">
                                <a class="link" href="<?= htmlspecialchars($notifLink, ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string)($notif['link_label'] ?? 'Open'), ENT_QUOTES, 'UTF-8') ?>
                                </a>
                                <?php if (!empty($notif['can_mark_read']) && !empty($notif['id'])): ?>
                                    <a class="read-link" href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['notif_read' => (int)$notif['id']])), ENT_QUOTES, 'UTF-8') ?>">
                                        Mark as read
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="crm-notif-empty">No notifications right now.</div>
                <?php endif; ?>
            </div>
        </div>
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
<?php if (!$hideTopbar): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var bell = document.getElementById('crmNotifBell');
    var dropdown = document.getElementById('crmNotifDropdown');
    if (!bell || !dropdown) return;

    bell.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        dropdown.classList.toggle('show');
    });

    document.addEventListener('click', function (event) {
        if (!dropdown.classList.contains('show')) return;
        if (dropdown.contains(event.target) || bell.contains(event.target)) return;
        dropdown.classList.remove('show');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') dropdown.classList.remove('show');
    });
});
</script>
<?php endif; ?>
