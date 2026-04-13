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
    $sessionRoleId = (int)($_SESSION['role_id'] ?? 0);
    $sessionBranchId = (int)($_SESSION['branch_id'] ?? 0);
    $roleNameLower = strtolower(trim((string)($_SESSION['role_name'] ?? '')));
    $canDismissTargetAssigned = true;
    $notifCacheKey = 'notif_cache_' . $sessionUserId;
    $notifLoadedFromCache = false;
    if (empty($_GET['notif_ajax']) && empty($_GET['notif_read']) && empty($_GET['notif_read_all'])) {
        $cachedNotif = $_SESSION[$notifCacheKey] ?? null;
        if (
            is_array($cachedNotif)
            && isset($cachedNotif['ts'], $cachedNotif['items'])
            && (time() - (int)$cachedNotif['ts']) <= 30
            && (int)($cachedNotif['branch_id'] ?? 0) === $sessionBranchId
            && (int)($cachedNotif['role_id'] ?? 0) === $sessionRoleId
        ) {
            $topNotifications = is_array($cachedNotif['items']) ? $cachedNotif['items'] : [];
            $notifLoadedFromCache = true;
        }
    }

    if (!$notifLoadedFromCache) try {
        $currentYearStrict = (int)date('Y');
        $currentMonthStrict = (int)date('n');
        $stmtStrictTarget = $pdo->prepare("
            SELECT target_amount
            FROM monthly_targets
            WHERE user_id = :user_id
              AND branch_id = :branch_id
              AND target_year = :target_year
              AND target_month = :target_month
              AND status = 'active'
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ");
        $stmtStrictTarget->execute([
            ':user_id' => $sessionUserId,
            ':branch_id' => $sessionBranchId,
            ':target_year' => $currentYearStrict,
            ':target_month' => $currentMonthStrict,
        ]);
        $strictTargetAmount = (float)($stmtStrictTarget->fetchColumn() ?: 0);
        if ($strictTargetAmount > 0) {
            $stmtStrictAch = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM registration_payments
                WHERE branch_id = :branch_id
                  AND collected_by = :user_id
                  AND approval_status = 'approved'
                  AND YEAR(payment_date) = :target_year
                  AND MONTH(payment_date) = :target_month
            ");
            $stmtStrictAch->execute([
                ':branch_id' => $sessionBranchId,
                ':user_id' => $sessionUserId,
                ':target_year' => $currentYearStrict,
                ':target_month' => $currentMonthStrict,
            ]);
            $strictAchievedAmount = (float)($stmtStrictAch->fetchColumn() ?: 0);
            $canDismissTargetAssigned = ($strictAchievedAmount >= $strictTargetAmount);
        }
    } catch (Throwable $e) {
        $canDismissTargetAssigned = false;
    }
    $canAllBranchesHeader = 0;
    try {
        $stmtRoleAccess = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id = ? LIMIT 1");
        $stmtRoleAccess->execute([$sessionRoleId]);
        $canAllBranchesHeader = (int)($stmtRoleAccess->fetchColumn() ?? 0);
    } catch (Throwable $e) {
        $canAllBranchesHeader = 0;
    }

    if (isset($_GET['notif_ajax']) && (string)$_GET['notif_ajax'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        $action = strtolower(trim((string)($_GET['notif_action'] ?? '')));
        if ($action === 'read_all') {
            try {
                $stmtReadAllAjax = $pdo->prepare("
                    UPDATE user_notifications
                    SET is_read = 1, read_at = NOW()
                    WHERE user_id = :user_id
                      AND is_read = 0
                      AND (
                            type <> 'target_assigned'
                            OR :can_dismiss_target_assigned = 1
                      )
                ");
                $stmtReadAllAjax->execute([
                    ':user_id' => $sessionUserId,
                    ':can_dismiss_target_assigned' => $canDismissTargetAssigned ? 1 : 0,
                ]);
                unset($_SESSION['notif_cache_' . $sessionUserId]);
                echo json_encode(['ok' => true, 'updated' => (int)$stmtReadAllAjax->rowCount()]);
            } catch (Throwable $e) {
                echo json_encode(['ok' => false, 'error' => 'read_all_failed']);
            }
            exit;
        }
        if ($action === 'read') {
            $readIdAjax = (int)($_GET['id'] ?? 0);
            if ($readIdAjax <= 0) {
                echo json_encode(['ok' => false, 'error' => 'invalid_id']);
                exit;
            }
            try {
                $stmtReadAjax = $pdo->prepare("
                    UPDATE user_notifications
                    SET is_read = 1, read_at = NOW()
                    WHERE id = :id
                      AND user_id = :user_id
                      AND is_read = 0
                      AND (
                            type <> 'target_assigned'
                            OR :can_dismiss_target_assigned = 1
                      )
                    LIMIT 1
                ");
                $stmtReadAjax->execute([
                    ':id' => $readIdAjax,
                    ':user_id' => $sessionUserId,
                    ':can_dismiss_target_assigned' => $canDismissTargetAssigned ? 1 : 0,
                ]);
                unset($_SESSION['notif_cache_' . $sessionUserId]);
                echo json_encode(['ok' => true, 'updated' => (int)$stmtReadAjax->rowCount()]);
            } catch (Throwable $e) {
                echo json_encode(['ok' => false, 'error' => 'read_failed']);
            }
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'invalid_action']);
        exit;
    }

    // Mark all persistent notifications as read.
    if (isset($_GET['notif_read_all']) && (string)$_GET['notif_read_all'] === '1') {
        try {
            $stmtReadAll = $pdo->prepare("
                UPDATE user_notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = :user_id
                  AND is_read = 0
                  AND (
                        type <> 'target_assigned'
                        OR :can_dismiss_target_assigned = 1
                  )
            ");
            $stmtReadAll->execute([
                ':user_id' => $sessionUserId,
                ':can_dismiss_target_assigned' => $canDismissTargetAssigned ? 1 : 0,
            ]);
            unset($_SESSION['notif_cache_' . $sessionUserId]);
        } catch (Throwable $e) {
            // Ignore failures to keep navigation safe.
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $parsed = parse_url($requestUri);
        $path = $parsed['path'] ?? (BASE_URL . 'index.php');
        $queryParams = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
        }
        unset($queryParams['notif_read_all']);
        $newQuery = http_build_query($queryParams);
        $redirectTo = $path . ($newQuery !== '' ? ('?' . $newQuery) : '');
        header('Location: ' . $redirectTo);
        exit;
    }

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
                      AND (
                            type <> 'target_assigned'
                            OR :can_dismiss_target_assigned = 1
                      )
                    LIMIT 1
                ");
                $stmtRead->execute([
                    ':id' => $readId,
                    ':user_id' => $sessionUserId,
                    ':can_dismiss_target_assigned' => $canDismissTargetAssigned ? 1 : 0,
                ]);
                unset($_SESSION['notif_cache_' . $sessionUserId]);
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
        // Persistent notifications storage (optional; bell must still work even if this fails).
        $notifStorageAvailable = false;
        try {
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
            $notifStorageAvailable = true;
        } catch (Throwable $e) {
            $notifStorageAvailable = false;
        }

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
                    'level' => 'p1',
                    'impact' => count($missingRoles),
                    'created_ts' => time(),
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
                if ($notifStorageAvailable) {
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
                }

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
                        'level' => 'p2',
                        'impact' => $shortfall,
                        'created_ts' => time(),
                        'title' => 'Target Deadline Alert',
                        'message' => 'You still need Rs ' . number_format($shortfall, 2) . ' to finish your ' . $currentMonthLabel . ' target.',
                        'link' => BASE_URL . 'index.php?page=targets/my-target',
                        'link_label' => 'View my target',
                    ];
                }
            }
        }

        // -----------------------------------------
        // Bell module: Follow-up / Enquiry / Lead alerts
        // Requested set: 1,2,3,4
        // -----------------------------------------
        if (in_array($roleNameLower, ['super admin', 'hr'], true)) {
            $paymentScopeSql = '';
            $paymentScopeParams = [];
            if ($canAllBranchesHeader !== 1 && $sessionBranchId > 0) {
                $paymentScopeSql = ' AND branch_id = :pay_branch_id';
                $paymentScopeParams[':pay_branch_id'] = $sessionBranchId;
            }

            // 1) Today collection summary
            $stmtTodayCollection = $pdo->prepare("
                SELECT
                    COALESCE(SUM(amount), 0) AS total_amount,
                    COUNT(*) AS total_count
                FROM registration_payments
                WHERE payment_date = CURDATE()
                  AND approval_status = 'approved'
                  $paymentScopeSql
            ");
            $stmtTodayCollection->execute($paymentScopeParams);
            $todayCollectionRow = $stmtTodayCollection->fetch(PDO::FETCH_ASSOC) ?: [];
            $todayCollectionAmount = (float)($todayCollectionRow['total_amount'] ?? 0);
            $todayCollectionCount = (int)($todayCollectionRow['total_count'] ?? 0);

            if ($todayCollectionCount > 0 || $todayCollectionAmount > 0) {
                $topNotifications[] = [
                    'priority' => 'medium',
                    'level' => 'p3',
                    'impact' => $todayCollectionAmount,
                    'created_ts' => time(),
                    'title' => 'Today Collection',
                    'message' => 'Collected Rs ' . number_format($todayCollectionAmount, 2) . ' from ' . $todayCollectionCount . ' payment(s) today.',
                    'link' => BASE_URL . 'index.php?page=payments/index',
                    'link_label' => 'View payments',
                ];
            }

            // 2) Collection drop alert (today vs yesterday)
            $stmtYesterdayCollection = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) AS total_amount
                FROM registration_payments
                WHERE payment_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                  AND approval_status = 'approved'
                  $paymentScopeSql
            ");
            $stmtYesterdayCollection->execute($paymentScopeParams);
            $yesterdayCollectionAmount = (float)($stmtYesterdayCollection->fetchColumn() ?: 0);

            if ($yesterdayCollectionAmount > 0 && $todayCollectionAmount < $yesterdayCollectionAmount) {
                $dropAmount = $yesterdayCollectionAmount - $todayCollectionAmount;
                $dropPct = ($dropAmount / $yesterdayCollectionAmount) * 100;
                if ($dropPct >= 20) {
                    $topNotifications[] = [
                        'priority' => 'high',
                        'level' => 'p3',
                        'impact' => $dropAmount,
                        'created_ts' => time(),
                        'title' => 'Collection Drop Alert',
                        'message' => 'Today is down by ' . number_format($dropPct, 1) . '% (Rs ' . number_format($dropAmount, 2) . ') vs yesterday.',
                        'link' => BASE_URL . 'index.php?page=payments/index',
                        'link_label' => 'Review collection',
                    ];
                }
            }

            // 3) Pending dues due today (if due_date exists), else pending dues queue fallback.
            $hasDueDateColumn = false;
            try {
                $stmtDueCol = $pdo->query("
                    SELECT COUNT(*)
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'registrations'
                      AND COLUMN_NAME = 'due_date'
                ");
                $hasDueDateColumn = ((int)($stmtDueCol->fetchColumn() ?? 0) > 0);
            } catch (Throwable $e) {
                $hasDueDateColumn = false;
            }

            $dueScopeSql = '';
            $dueScopeParams = [];
            if ($canAllBranchesHeader !== 1 && $sessionBranchId > 0) {
                $dueScopeSql = ' AND branch_id = :due_branch_id';
                $dueScopeParams[':due_branch_id'] = $sessionBranchId;
            }

            if ($hasDueDateColumn) {
                $stmtDueToday = $pdo->prepare("
                    SELECT
                        COUNT(*) AS due_count,
                        COALESCE(SUM(balance_amount),0) AS due_amount
                    FROM registrations
                    WHERE registration_status = 'active'
                      AND balance_amount > 0
                      AND payment_status IN ('unpaid','partial')
                      AND DATE(due_date) = CURDATE()
                      $dueScopeSql
                ");
                $stmtDueToday->execute($dueScopeParams);
                $dueTodayRow = $stmtDueToday->fetch(PDO::FETCH_ASSOC) ?: [];
                $dueTodayCount = (int)($dueTodayRow['due_count'] ?? 0);
                $dueTodayAmount = (float)($dueTodayRow['due_amount'] ?? 0);

                if ($dueTodayCount > 0) {
                    $topNotifications[] = [
                        'priority' => 'high',
                        'level' => 'p2',
                        'impact' => $dueTodayAmount,
                        'created_ts' => time(),
                        'title' => 'Pending Dues Due Today',
                        'message' => $dueTodayCount . ' student due(s) today totaling Rs ' . number_format($dueTodayAmount, 2) . '.',
                        'link' => BASE_URL . 'index.php?page=payments/index&payment_status=partial',
                        'link_label' => 'Open dues',
                    ];
                }
            } else {
                $stmtPendingDue = $pdo->prepare("
                    SELECT
                        COUNT(*) AS due_count,
                        COALESCE(SUM(balance_amount),0) AS due_amount
                    FROM registrations
                    WHERE registration_status = 'active'
                      AND balance_amount > 0
                      AND payment_status IN ('unpaid','partial')
                      $dueScopeSql
                ");
                $stmtPendingDue->execute($dueScopeParams);
                $pendingDueRow = $stmtPendingDue->fetch(PDO::FETCH_ASSOC) ?: [];
                $pendingDueCount = (int)($pendingDueRow['due_count'] ?? 0);
                $pendingDueAmount = (float)($pendingDueRow['due_amount'] ?? 0);

                if ($pendingDueCount > 0) {
                    $topNotifications[] = [
                        'priority' => 'medium',
                        'level' => 'p2',
                        'impact' => $pendingDueAmount,
                        'created_ts' => time(),
                        'title' => 'Pending Dues Queue',
                        'message' => $pendingDueCount . ' active student due(s) totaling Rs ' . number_format($pendingDueAmount, 2) . '.',
                        'link' => BASE_URL . 'index.php?page=payments/index&payment_status=partial',
                        'link_label' => 'Open dues',
                    ];
                }
            }
        }

        $isFrontOfficeScope = ($roleNameLower === 'front office');

        $followupScopeSql = "";
        $followupScopeParams = [];
        if ($canAllBranchesHeader !== 1 && $sessionBranchId > 0) {
            $followupScopeSql .= " AND f.branch_id = :scope_branch_id";
            $followupScopeParams[':scope_branch_id'] = $sessionBranchId;
        }
        if ($isFrontOfficeScope) {
            $followupScopeSql .= " AND (e.handled_by = :scope_user_1 OR f.created_by = :scope_user_2)";
            $followupScopeParams[':scope_user_1'] = $sessionUserId;
            $followupScopeParams[':scope_user_2'] = $sessionUserId;
        }

        // 1) Missed follow-ups
        $sqlMissed = "
            SELECT COUNT(*) 
            FROM enquiry_followups f
            INNER JOIN enquiries e ON e.id = f.enquiry_id
            WHERE 1=1
              AND LOWER(TRIM(COALESCE(f.status, 'pending'))) = 'pending'
              AND DATE(f.followup_date) < CURDATE()
              $followupScopeSql
        ";
        $paramsMissed = $followupScopeParams;
        $stmtMissed = $pdo->prepare($sqlMissed);
        $stmtMissed->execute($paramsMissed);
        $missedCount = (int)($stmtMissed->fetchColumn() ?: 0);
        if ($missedCount > 0) {
            $topNotifications[] = [
                'priority' => 'high',
                'level' => 'p1',
                'impact' => $missedCount,
                'created_ts' => time(),
                'title' => 'Missed Follow-ups',
                'message' => $missedCount . ' follow-up(s) are pending past due date.',
                'link' => BASE_URL . 'index.php?page=enquiries/followups',
                'link_label' => 'Open follow-ups',
            ];
        }

        // Additional pending overview (branch scoped, matches follow-up list visibility)
        $sqlPending = "
            SELECT COUNT(*)
            FROM enquiry_followups f
            INNER JOIN enquiries e ON e.id = f.enquiry_id
            WHERE 1=1
              AND LOWER(TRIM(COALESCE(f.status, 'pending'))) = 'pending'
              $followupScopeSql
        ";
        $stmtPending = $pdo->prepare($sqlPending);
        $stmtPending->execute($followupScopeParams);
        $pendingCount = (int)($stmtPending->fetchColumn() ?: 0);
        if ($pendingCount > 0) {
            $topNotifications[] = [
                'priority' => 'medium',
                'level' => 'p2',
                'impact' => $pendingCount,
                'created_ts' => time(),
                'title' => 'Pending Follow-ups',
                'message' => $pendingCount . ' follow-up(s) are pending.',
                'link' => BASE_URL . 'index.php?page=enquiries/followups&tab=pending',
                'link_label' => 'View pending',
            ];
        }

        // 2) Follow-ups due soon (next 2 hours today)
        $sqlSoon = "
            SELECT COUNT(*)
            FROM enquiry_followups f
            INNER JOIN enquiries e ON e.id = f.enquiry_id
            WHERE 1=1
              AND LOWER(TRIM(COALESCE(f.status, 'pending'))) = 'pending'
              AND DATE(f.followup_date) = CURDATE()
              AND f.followup_time IS NOT NULL
              AND TIME(f.followup_time) BETWEEN CURTIME() AND ADDTIME(CURTIME(), '02:00:00')
              $followupScopeSql
        ";
        $paramsSoon = $followupScopeParams;
        $stmtSoon = $pdo->prepare($sqlSoon);
        $stmtSoon->execute($paramsSoon);
        $dueSoonCount = (int)($stmtSoon->fetchColumn() ?: 0);
        if ($dueSoonCount > 0) {
            $topNotifications[] = [
                'priority' => 'medium',
                'level' => 'p2',
                'impact' => $dueSoonCount,
                'created_ts' => time(),
                'title' => 'Follow-ups Due Soon',
                'message' => $dueSoonCount . ' follow-up(s) are scheduled in the next 2 hours.',
                'link' => BASE_URL . 'index.php?page=enquiries/followups',
                'link_label' => 'Review now',
            ];
        }

        // 3) Enquiry SLA not contacted:
        // enquiries older than 24h with no follow-up record yet.
        $sqlSla = "
            SELECT COUNT(*)
            FROM enquiries e
            LEFT JOIN enquiry_followups f ON f.enquiry_id = e.id
            WHERE 1=1
              AND e.created_at <= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              AND f.id IS NULL
        ";
        $paramsSla = [];
        if ($canAllBranchesHeader !== 1 && $sessionBranchId > 0) {
            $sqlSla .= " AND e.branch_id = :sla_branch_id";
            $paramsSla[':sla_branch_id'] = $sessionBranchId;
        }
        if ($isFrontOfficeScope) {
            $sqlSla .= " AND (e.handled_by = :sla_user_1 OR e.created_by = :sla_user_2)";
            $paramsSla[':sla_user_1'] = $sessionUserId;
            $paramsSla[':sla_user_2'] = $sessionUserId;
        }
        $stmtSla = $pdo->prepare($sqlSla);
        $stmtSla->execute($paramsSla);
        $slaCount = (int)($stmtSla->fetchColumn() ?: 0);
        if ($slaCount > 0) {
            $topNotifications[] = [
                'priority' => 'high',
                'level' => 'p1',
                'impact' => $slaCount,
                'created_ts' => time(),
                'title' => 'Enquiry Contact SLA Pending',
                'message' => $slaCount . ' enquiry(ies) have no follow-up recorded beyond 24 hours.',
                'link' => BASE_URL . 'index.php?page=enquiries/list',
                'link_label' => 'Check enquiries',
            ];
        }

        // 4) New lead assigned to me (one-time, read-based)
        $stmtLeadAssigned = $pdo->prepare("
            SELECT id, COALESCE(name, 'Lead') AS lead_name, created_at
            FROM leads
            WHERE assigned_to = :user_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              " . (($canAllBranchesHeader !== 1 && $sessionBranchId > 0) ? " AND branch_id = :lead_branch_id " : "") . "
            ORDER BY created_at DESC, id DESC
            LIMIT 20
        ");
        $leadParams = [':user_id' => $sessionUserId];
        if ($canAllBranchesHeader !== 1 && $sessionBranchId > 0) {
            $leadParams[':lead_branch_id'] = $sessionBranchId;
        }
        $stmtLeadAssigned->execute($leadParams);
        $leadRows = $stmtLeadAssigned->fetchAll(PDO::FETCH_ASSOC);
        if ($notifStorageAvailable && !empty($leadRows)) {
            $stmtInsertLeadNotif = $pdo->prepare("
                INSERT INTO user_notifications
                    (user_id, notif_key, type, priority, title, message, link, expires_at)
                VALUES
                    (:user_id, :notif_key, 'lead_assigned', 'medium', :title, :message, :link, :expires_at)
                ON DUPLICATE KEY UPDATE id = id
            ");
            foreach ($leadRows as $leadRow) {
                $leadId = (int)($leadRow['id'] ?? 0);
                if ($leadId <= 0) continue;
                $leadName = (string)($leadRow['lead_name'] ?? 'Lead');
                $notifKey = 'lead_assigned_' . $leadId;
                $stmtInsertLeadNotif->execute([
                    ':user_id' => $sessionUserId,
                    ':notif_key' => $notifKey,
                    ':title' => 'New Lead Assigned',
                    ':message' => 'A new lead "' . $leadName . '" has been assigned to you.',
                    ':link' => BASE_URL . 'index.php?page=leads/list',
                    ':expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                ]);
            }
        }

        // Load unread persistent notifications for this user.
        if ($notifStorageAvailable) {
            $stmtUnread = $pdo->prepare("
                SELECT id, type, priority, title, message, link, created_at
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
                $notifType = (string)($unread['type'] ?? '');
                $createdTs = strtotime((string)($unread['created_at'] ?? '')) ?: time();
                $level = 'p3';
                if ($notifType === 'target_assigned') {
                    $level = $canDismissTargetAssigned ? 'p3' : 'p2';
                } elseif ($notifType === 'lead_assigned') {
                    $level = 'p4';
                }
                $canMarkRead = in_array($level, ['p3', 'p4'], true);
                $topNotifications[] = [
                    'id' => (int)($unread['id'] ?? 0),
                    'priority' => (string)($unread['priority'] ?? 'medium'),
                    'type' => $notifType,
                    'level' => $level,
                    'impact' => 0,
                    'created_ts' => $createdTs,
                    'title' => (string)($unread['title'] ?? 'Notification'),
                    'message' => (string)($unread['message'] ?? ''),
                    'link' => (string)($unread['link'] ?? (BASE_URL . 'index.php')),
                    'link_label' => 'Open',
                    'can_mark_read' => $canMarkRead,
                ];
            }
        }
        $_SESSION[$notifCacheKey] = [
            'ts' => time(),
            'branch_id' => $sessionBranchId,
            'role_id' => $sessionRoleId,
            'items' => $topNotifications,
        ];
    } catch (Throwable $e) {
        // Keep header safe even if one section fails; preserve already built notifications.
    }
}
$priorityRank = ['p1' => 1, 'p2' => 2, 'p3' => 3, 'p4' => 4];
foreach ($topNotifications as &$topNotifRow) {
    $lvl = strtolower(trim((string)($topNotifRow['level'] ?? '')));
    if (!isset($priorityRank[$lvl])) {
        $lvl = 'p3';
    }
    $topNotifRow['level'] = $lvl;
    if (!isset($topNotifRow['impact'])) {
        $topNotifRow['impact'] = 0;
    }
    if (!isset($topNotifRow['created_ts'])) {
        $topNotifRow['created_ts'] = time();
    }
    if (!isset($topNotifRow['priority']) || $topNotifRow['priority'] === '') {
        $topNotifRow['priority'] = ($lvl === 'p1') ? 'high' : 'medium';
    }
    if (!isset($topNotifRow['can_mark_read'])) {
        $topNotifRow['can_mark_read'] = false;
    }
}
unset($topNotifRow);

usort($topNotifications, static function (array $a, array $b) use ($priorityRank): int {
    $ra = $priorityRank[$a['level']] ?? 99;
    $rb = $priorityRank[$b['level']] ?? 99;
    if ($ra !== $rb) return $ra <=> $rb;

    $ia = (float)($a['impact'] ?? 0);
    $ib = (float)($b['impact'] ?? 0);
    if ($ia !== $ib) return ($ib <=> $ia);

    $ta = (int)($a['created_ts'] ?? 0);
    $tb = (int)($b['created_ts'] ?? 0);
    return ($tb <=> $ta);
});

$topNotificationTotal = count($topNotifications);
$topMarkableCount = 0;
foreach ($topNotifications as $topNotifRow) {
    if (!empty($topNotifRow['can_mark_read']) && !empty($topNotifRow['id'])) {
        $topMarkableCount++;
    }
}
$topNotificationCount = $topNotificationTotal;
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
    .crm-notif-head{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:13px;font-weight:700;color:#1f2937;padding:4px 6px 8px;border-bottom:1px solid #f5e2eb;margin-bottom:6px;}
    .crm-notif-head .mark-all-link{font-size:11px;font-weight:700;color:#64748b;text-decoration:none;}
    .crm-notif-head .mark-all-link:hover{color:var(--crm-primary);}
    .crm-notif-list{max-height:420px;overflow-y:auto;overflow-x:hidden;padding-right:2px;}
    .crm-notif-list::-webkit-scrollbar{width:8px;}
    .crm-notif-list::-webkit-scrollbar-thumb{background:#e9c5d6;border-radius:10px;}
    .crm-notif-list::-webkit-scrollbar-track{background:#faf4f7;border-radius:10px;}
    .crm-notif-item{border:1px solid #f3e2eb;border-radius:10px;padding:8px 10px;margin-bottom:8px;background:#fff;}
    .crm-notif-item.is-hidden{display:none;}
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
                <div class="crm-notif-head">
                    <span>Notifications</span>
                    <span style="display:inline-flex;align-items:center;gap:10px;">
                        <?php if (($topNotificationTotal ?? 0) > 5): ?>
                            <a class="mark-all-link" href="#" id="crmNotifToggleAll" data-expanded="0">Show all</a>
                        <?php endif; ?>
                        <?php if ($topMarkableCount > 0): ?>
                            <a class="mark-all-link" href="#" id="crmNotifMarkAll">
                                Mark all as read
                            </a>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="crm-notif-list">
                    <?php if ($topNotificationCount > 0): ?>
                        <?php foreach ($topNotifications as $idx => $notif): ?>
                            <?php
                                $notifLink = (string)($notif['link'] ?? BASE_URL);
                                if (!empty($notif['can_mark_read']) && !empty($notif['id'])) {
                                    $notifLink = crmAppendQueryParam($notifLink, 'notif_read', (int)$notif['id']);
                                }
                            ?>
                            <div
                                class="crm-notif-item <?= htmlspecialchars((string)($notif['priority'] ?? 'medium'), ENT_QUOTES, 'UTF-8') ?> <?= ($idx >= 5) ? 'is-hidden' : '' ?>"
                                data-markable="<?= !empty($notif['can_mark_read']) && !empty($notif['id']) ? '1' : '0' ?>"
                                data-notif-id="<?= (int)($notif['id'] ?? 0) ?>"
                            >
                                <div class="title"><?= htmlspecialchars((string)($notif['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="msg"><?= htmlspecialchars((string)($notif['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="meta">
                                    <a class="link" href="<?= htmlspecialchars($notifLink, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string)($notif['link_label'] ?? 'Open'), ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                    <?php if (!empty($notif['can_mark_read']) && !empty($notif['id'])): ?>
                                        <a class="read-link" href="#" data-notif-read-id="<?= (int)$notif['id'] ?>">
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
                <?php if (($topNotificationTotal ?? 0) > 5): ?>
                    <div class="crm-notif-empty" id="crmNotifSummary" style="padding-top:10px;border-top:1px solid #f5e2eb;margin-top:8px;">
                        Showing top 5 of <?= (int)$topNotificationTotal ?> alerts by priority.
                    </div>
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
    var toggleAll = document.getElementById('crmNotifToggleAll');
    var markAll = document.getElementById('crmNotifMarkAll');
    var summary = document.getElementById('crmNotifSummary');
    var notifBadge = bell ? bell.querySelector('.crm-notif-badge') : null;
    if (!bell || !dropdown) return;

    function getItems() {
        return Array.prototype.slice.call(dropdown.querySelectorAll('.crm-notif-item'));
    }
    function refreshBadge() {
        var cnt = getItems().length;
        if (cnt > 0) {
            if (!notifBadge) {
                notifBadge = document.createElement('span');
                notifBadge.className = 'crm-notif-badge';
                bell.appendChild(notifBadge);
            }
            notifBadge.textContent = String(cnt);
        } else if (notifBadge) {
            notifBadge.remove();
            notifBadge = null;
        }
    }
    function applyTopFiveLimit(expanded) {
        var items = getItems();
        items.forEach(function (item, idx) {
            var hide = !expanded && idx >= 5;
            item.classList.toggle('is-hidden', hide);
        });
        if (summary) {
            if (items.length > 5) {
                summary.textContent = expanded
                    ? ('Showing all ' + items.length + ' alerts.')
                    : ('Showing top 5 of ' + items.length + ' alerts by priority.');
                summary.style.display = '';
            } else {
                summary.style.display = 'none';
            }
        }
        if (toggleAll) {
            toggleAll.dataset.expanded = expanded ? '1' : '0';
            toggleAll.textContent = expanded ? 'Show top 5' : 'Show all';
        }
    }
    async function readNotification(id) {
        var res = await fetch('index.php?notif_ajax=1&notif_action=read&id=' + encodeURIComponent(String(id)), {
            credentials: 'same-origin'
        });
        return res.json();
    }
    async function readAllNotifications() {
        var res = await fetch('index.php?notif_ajax=1&notif_action=read_all', {
            credentials: 'same-origin'
        });
        return res.json();
    }

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

    if (toggleAll) {
        toggleAll.addEventListener('click', function (event) {
            event.preventDefault();
            var expanded = this.dataset.expanded === '1';
            applyTopFiveLimit(!expanded);
        });
    }
    if (markAll) {
        markAll.addEventListener('click', async function (event) {
            event.preventDefault();
            try {
                var result = await readAllNotifications();
                if (result && result.ok) {
                    getItems().forEach(function (item) {
                        if (item.getAttribute('data-markable') === '1') item.remove();
                    });
                    refreshBadge();
                    applyTopFiveLimit(false);
                }
            } catch (e) {}
        });
    }
    dropdown.addEventListener('click', async function (event) {
        var readLink = event.target.closest('.read-link[data-notif-read-id]');
        if (!readLink) return;
        event.preventDefault();
        var id = parseInt(readLink.getAttribute('data-notif-read-id') || '0', 10);
        if (!id) return;
        try {
            var result = await readNotification(id);
            if (result && result.ok) {
                var card = readLink.closest('.crm-notif-item');
                if (card) card.remove();
                refreshBadge();
                applyTopFiveLimit(false);
            }
        } catch (e) {}
    });

    refreshBadge();
    applyTopFiveLimit(false);
});
</script>
<?php endif; ?>
