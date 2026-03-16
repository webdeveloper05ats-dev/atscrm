<?php
// =======================================================
// Staff Dashboard
// File: views/dashboard/staff.php
// =======================================================

requireView('dashboard/staff');

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

// Extra role safety (optional but professional)
if (($_SESSION['role_name'] ?? '') !== 'Staff') {
    redirect('index.php');
    exit;
}

$pageTitle = "Staff Dashboard";

// -------------------------------------------------------
// Branch Scope Logic
// -------------------------------------------------------
$roleId   = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);

$canAllBranches = 0;
try {
    $r = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $r->execute([$roleId]);
    $canAllBranches = (int)($r->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

function safeCount(PDO $pdo, string $sql, array $params = []): int {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

$params = [];
$branchWhere = "";

if ($canAllBranches !== 1 && $branchId > 0) {
    $branchWhere = " WHERE branch_id = ? ";
    $params = [$branchId];
}

// -------------------------------------------------------
// Staff Monitoring Stats
// -------------------------------------------------------
$totalStudents   = safeCount($pdo, "SELECT COUNT(*) FROM students" . $branchWhere, $params);
$totalAttendance = safeCount($pdo, "SELECT COUNT(*) FROM attendance" . $branchWhere, $params);
$totalClasses    = safeCount($pdo, "SELECT COUNT(*) FROM classes" . $branchWhere, $params);
$totalAssessment = safeCount($pdo, "SELECT COUNT(*) FROM assessment" . $branchWhere, $params);
$totalMockInterviews = safeCount($pdo, "SELECT COUNT(*) FROM mock_interviews" . $branchWhere, $params);

// Optional: Today's attendance
$todayAttendance = 0;
try {
    if ($canAllBranches !== 1 && $branchId > 0) {
        $todayAttendance = safeCount(
            $pdo,
            "SELECT COUNT(*) FROM attendance WHERE DATE(attendance_date)=CURDATE() AND branch_id=?",
            [$branchId]
        );
    } else {
        $todayAttendance = safeCount(
            $pdo,
            "SELECT COUNT(*) FROM attendance WHERE DATE(attendance_date)=CURDATE()",
            []
        );
    }
} catch (Exception $e) {
    $todayAttendance = 0;
}

// -------------------------------------------------------
// Quick Access Menus for Staff
// -------------------------------------------------------
$menus = [];
try {
    $st = $pdo->prepare("
        SELECT m.*
        FROM menus m
        JOIN role_permissions rp ON rp.menu_id = m.id
        WHERE rp.role_id = ?
          AND rp.can_view = 1
          AND m.status = 1
        ORDER BY m.parent_id IS NOT NULL, m.parent_id ASC, m.sort_order ASC
    ");
    $st->execute([$roleId]);
    $menus = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $menus = [];
}

// Build Tree
$tree = [];
foreach ($menus as $m) {
    if ($m['parent_id'] === null) {
        $tree[(int)$m['id']] = $m;
        $tree[(int)$m['id']]['children'] = [];
    }
}
foreach ($menus as $m) {
    if ($m['parent_id'] !== null) {
        $pid = (int)$m['parent_id'];
        if (isset($tree[$pid])) {
            $tree[$pid]['children'][] = $m;
        }
    }
}

// Flatten quick links
$quickLinks = [];
foreach ($tree as $parent) {
    if (!empty($parent['children'])) {
        foreach ($parent['children'] as $child) {
            $quickLinks[] = $child;
        }
    } else {
        $quickLinks[] = $parent;
    }
}

// Remove dashboard from tiles
$skipSlugs = ['dashboard/staff'];
$quickLinks = array_values(array_filter($quickLinks, function($m) use ($skipSlugs) {
    return !in_array($m['menu_slug'], $skipSlugs, true);
}));

$userName   = $_SESSION['user_name'] ?? 'Staff';
$branchName = $_SESSION['branch_name'] ?? 'Branch';
?>

<div class="card">
    <div class="card-header">Welcome Staff 👨‍🏫</div>
    <p style="line-height:1.7; color: var(--text-dark);">
        Hello <strong><?= htmlspecialchars($userName) ?></strong> —
        You are managing students under <strong><?= htmlspecialchars($branchName) ?></strong>.
        Track attendance, classes, assessments, mock interviews, and progress here.
    </p>
</div>

<div class="dashboard-grid">

    <div class="card stat-card">
        <h3>Today's Attendance</h3>
        <h2><?= (int)$todayAttendance ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Students</h3>
        <h2><?= (int)$totalStudents ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Attendance</h3>
        <h2><?= (int)$totalAttendance ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Classes</h3>
        <h2><?= (int)$totalClasses ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Total Assessments</h3>
        <h2><?= (int)$totalAssessment ?></h2>
    </div>

    <div class="card stat-card">
        <h3>Mock Interviews</h3>
        <h2><?= (int)$totalMockInterviews ?></h2>
    </div>

</div>

<div class="card" style="margin-top:18px;">
    <div class="card-header">Quick Access</div>

    <?php if (empty($quickLinks)): ?>
        <p style="color:var(--text-light);">
            No menus assigned to this role. Please contact Super Admin.
        </p>
    <?php else: ?>

        <div class="dashboard-grid" style="margin-top:0;">
            <?php foreach ($quickLinks as $m): ?>
                <?php
                    $slug = $m['menu_slug'];
                    $name = $m['menu_name'];
                    $icon = $m['icon'] ?: 'fas fa-circle';
                ?>
                <a href="index.php?page=<?= htmlspecialchars($slug) ?>"
                   style="text-decoration:none;">
                    <div class="card stat-card" style="text-align:left;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div style="font-size:22px; color: var(--primary); width:32px;">
                                <i class="<?= htmlspecialchars($icon) ?>"></i>
                            </div>
                            <div>
                                <div style="font-weight:800; color: var(--text-dark);">
                                    <?= htmlspecialchars($name) ?>
                                </div>
                                <div style="font-size:12px; color: var(--text-light); margin-top:2px;">
                                    <?= htmlspecialchars($slug) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>
