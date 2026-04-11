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
$userId   = (int)($_SESSION['user_id'] ?? 0);
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

// -------------------------------------------------------
// Staff Monitoring Stats
// -------------------------------------------------------
$ownedRegistrationSql = "
    (
        r.assigned_to = ?
        OR (COALESCE(r.source_type, '') = 'direct' AND r.created_by = ?)
        OR COALESCE(rc.guide_staff_id, ri.guide_staff_id) = ?
    )
";
$ownedRegistrationParams = [$userId, $userId, $userId];
if ($canAllBranches !== 1 && $branchId > 0) {
    $ownedRegistrationSql .= " AND r.branch_id = ? ";
    $ownedRegistrationParams[] = $branchId;
}

$totalStudents = safeCount(
    $pdo,
    "
    SELECT COUNT(DISTINCT r.id)
    FROM registrations r
    LEFT JOIN registration_courses rc ON rc.registration_id = r.id AND r.reg_type = 'course'
    LEFT JOIN registration_internships ri ON ri.registration_id = r.id AND r.reg_type = 'internship'
    WHERE r.registration_status = 'active'
      AND $ownedRegistrationSql
    ",
    $ownedRegistrationParams
);

$courseStudents = safeCount(
    $pdo,
    "
    SELECT COUNT(DISTINCT r.id)
    FROM registrations r
    LEFT JOIN registration_courses rc ON rc.registration_id = r.id
    LEFT JOIN registration_internships ri ON ri.registration_id = r.id
    WHERE r.reg_type = 'course'
      AND r.registration_status IN ('active', 'completed')
      AND $ownedRegistrationSql
    ",
    $ownedRegistrationParams
);

$internshipStudents = safeCount(
    $pdo,
    "
    SELECT COUNT(DISTINCT r.id)
    FROM registrations r
    LEFT JOIN registration_courses rc ON rc.registration_id = r.id
    LEFT JOIN registration_internships ri ON ri.registration_id = r.id
    WHERE r.reg_type = 'internship'
      AND r.registration_status IN ('active', 'completed')
      AND $ownedRegistrationSql
    ",
    $ownedRegistrationParams
);

$attendanceWhere = "marked_by = ?";
$attendanceParams = [$userId];
if ($canAllBranches !== 1 && $branchId > 0) {
    $attendanceWhere .= " AND branch_id = ?";
    $attendanceParams[] = $branchId;
}

$totalAttendance = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM attendance WHERE $attendanceWhere",
    $attendanceParams
);

$todayAttendance = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM attendance WHERE DATE(attendance_date) = CURDATE() AND $attendanceWhere",
    $attendanceParams
);

$assessmentWhere = "staff_user_id = ?";
$assessmentParams = [$userId];
if ($canAllBranches !== 1 && $branchId > 0) {
    $assessmentWhere .= " AND branch_id = ?";
    $assessmentParams[] = $branchId;
}

$totalAssessment = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM assessment WHERE $assessmentWhere",
    $assessmentParams
);

$mockWhere = "staff_user_id = ?";
$mockParams = [$userId];
if ($canAllBranches !== 1 && $branchId > 0) {
    $mockWhere .= " AND branch_id = ?";
    $mockParams[] = $branchId;
}

$totalMockInterviews = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM mock_interviews WHERE $mockWhere",
    $mockParams
);

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

<div class="stf-dashboard">
    <section class="stf-card stf-hero">
        <span class="stf-kicker"><i class="fas fa-chalkboard-teacher"></i> Staff Dashboard</span>
        <h1 class="stf-title">Welcome, <?= htmlspecialchars($userName) ?></h1>
        <p class="stf-copy">
            You are handling student operations for <strong><?= htmlspecialchars($branchName) ?></strong>.
            Monitor attendance, classes, assessments, and interviews from one focused workspace.
        </p>
        <div class="stf-chip-row">
            <span class="stf-chip"><i class="fas fa-calendar-check"></i> Today Attendance: <?= (int)$todayAttendance ?></span>
            <span class="stf-chip"><i class="fas fa-user-graduate"></i> Students: <?= (int)$totalStudents ?></span>
            <span class="stf-chip"><i class="fas fa-microscope"></i> Assessments: <?= (int)$totalAssessment ?></span>
        </div>
    </section>

    <section class="stf-stats">
        <article class="stf-card stf-stat">
            <p class="stf-stat-label">Today's Attendance</p>
            <p class="stf-stat-value"><?= (int)$todayAttendance ?></p>
        </article>
        <article class="stf-card stf-stat">
            <p class="stf-stat-label">Total Students</p>
            <p class="stf-stat-value"><?= (int)$totalStudents ?></p>
            <p class="stf-stat-sub">Course: <?= (int)$courseStudents ?> | Internship: <?= (int)$internshipStudents ?></p>
        </article>
        <article class="stf-card stf-stat">
            <p class="stf-stat-label">Total Attendance</p>
            <p class="stf-stat-value"><?= (int)$totalAttendance ?></p>
        </article>
        <article class="stf-card stf-stat">
            <p class="stf-stat-label">Total Assessments</p>
            <p class="stf-stat-value"><?= (int)$totalAssessment ?></p>
        </article>
        <article class="stf-card stf-stat">
            <p class="stf-stat-label">Mock Interviews</p>
            <p class="stf-stat-value"><?= (int)$totalMockInterviews ?></p>
        </article>
    </section>

    <section class="stf-card stf-links">
        <div class="stf-links-head">
            <h2 class="stf-links-title">Quick Access</h2>
            <span class="stf-links-sub"><?= (int)count($quickLinks) ?> menus</span>
        </div>

        <?php if (empty($quickLinks)): ?>
            <div class="stf-empty">
                No menus assigned to this role. Please contact Super Admin.
            </div>
        <?php else: ?>
            <div class="stf-link-grid">
                <?php foreach ($quickLinks as $m): ?>
                    <?php
                        $slug = $m['menu_slug'];
                        $name = $m['menu_name'];
                        $icon = $m['icon'] ?: 'fas fa-circle';
                    ?>
                    <a class="stf-link" href="index.php?page=<?= htmlspecialchars($slug) ?>">
                        <span class="stf-link-icon"><i class="<?= htmlspecialchars($icon) ?>"></i></span>
                        <span>
                            <span class="stf-link-name"><?= htmlspecialchars($name) ?></span>
                            <span class="stf-link-slug"><?= htmlspecialchars($slug) ?></span>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>


