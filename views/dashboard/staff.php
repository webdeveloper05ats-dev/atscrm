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

function safeRows(PDO $pdo, string $sql, array $params = []): array {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        return [];
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

$todayMissingAttendance = max(0, $totalStudents - $todayAttendance);
$assessmentCoveragePct = $totalStudents > 0 ? round(($totalAssessment / $totalStudents) * 100, 1) : 0.0;
$mockCoveragePct = $totalStudents > 0 ? round(($totalMockInterviews / $totalStudents) * 100, 1) : 0.0;
$weekAttendance = safeCount(
    $pdo,
    "SELECT COUNT(*) FROM attendance WHERE DATE(attendance_date) >= CURDATE() - INTERVAL 6 DAY AND $attendanceWhere",
    $attendanceParams
);

$healthCards = [
    [
        'title' => 'Today Attendance',
        'value' => number_format($todayAttendance),
        'meta' => 'Week total: ' . number_format($weekAttendance),
        'insight' => 'Daily attendance consistency improves student outcomes.',
        'tone' => 'tone-rose',
        'icon' => 'fas fa-calendar-check',
    ],
    [
        'title' => 'Missing Today',
        'value' => number_format($todayMissingAttendance),
        'meta' => 'Students not marked today',
        'insight' => 'Close these entries first to keep records clean.',
        'tone' => 'tone-amber',
        'icon' => 'fas fa-user-clock',
    ],
    [
        'title' => 'Assessment Coverage',
        'value' => number_format($assessmentCoveragePct, 1) . '%',
        'meta' => number_format($totalAssessment) . ' assessments logged',
        'insight' => 'Try to maintain steady assessment updates weekly.',
        'tone' => 'tone-plum',
        'icon' => 'fas fa-clipboard-check',
    ],
    [
        'title' => 'Mock Coverage',
        'value' => number_format($mockCoveragePct, 1) . '%',
        'meta' => number_format($totalMockInterviews) . ' mock interviews',
        'insight' => 'Mocks are strong predictors for interview readiness.',
        'tone' => 'tone-violet',
        'icon' => 'fas fa-comments',
    ],
];

$chartLabelMap = [];
$chartValueMap = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartLabelMap[$d] = date('d M', strtotime($d));
    $chartValueMap[$d] = 0;
}

$attendanceTrendRows = safeRows(
    $pdo,
    "SELECT DATE(attendance_date) AS mark_day, COUNT(*) AS cnt
     FROM attendance
     WHERE DATE(attendance_date) >= CURDATE() - INTERVAL 13 DAY
       AND $attendanceWhere
     GROUP BY DATE(attendance_date)",
    $attendanceParams
);
foreach ($attendanceTrendRows as $row) {
    $day = (string)($row['mark_day'] ?? '');
    if (isset($chartValueMap[$day])) {
        $chartValueMap[$day] = (int)($row['cnt'] ?? 0);
    }
}
$attendanceChartLabels = array_values($chartLabelMap);
$attendanceChartData = array_values($chartValueMap);
?>

<div class="stf-dashboard">
    <section class="stf-card stf-hero">
        <div class="stf-hero-grid">
            <div>
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
            </div>
            <aside class="stf-hero-aside">
                <div class="stf-live-pill"><span class="stf-pulse"></span> Live Classroom Ops</div>
                <div class="stf-hero-kpi">
                    <span>Course Students</span>
                    <strong><?= (int)$courseStudents ?></strong>
                </div>
                <div class="stf-hero-kpi">
                    <span>Internship Students</span>
                    <strong><?= (int)$internshipStudents ?></strong>
                </div>
                <div class="stf-hero-actions">
                    <a href="index.php?page=attendance" class="stf-action-btn">Attendance</a>
                    <a href="index.php?page=assessment" class="stf-action-btn">Assessment</a>
                </div>
            </aside>
        </div>
    </section>

    <section class="stf-health-strip">
        <?php foreach ($healthCards as $hc): ?>
            <article class="stf-health-card <?= htmlspecialchars($hc['tone']) ?>">
                <div class="stf-health-flip">
                    <div class="stf-health-face stf-health-front">
                        <div class="stf-health-head">
                            <p class="stf-health-label"><?= htmlspecialchars($hc['title']) ?></p>
                            <span class="stf-health-icon"><i class="<?= htmlspecialchars($hc['icon']) ?>"></i></span>
                        </div>
                        <p class="stf-health-value"><?= htmlspecialchars($hc['value']) ?></p>
                        <p class="stf-health-meta"><?= htmlspecialchars($hc['meta']) ?></p>
                    </div>
                    <div class="stf-health-face stf-health-back">
                        <p class="stf-health-back-title"><?= htmlspecialchars($hc['title']) ?></p>
                        <p class="stf-health-back-copy"><?= htmlspecialchars($hc['insight']) ?></p>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="stf-stats">
        <article class="stf-card stf-stat">
            <div class="stf-stat-flip">
                <div class="stf-stat-face stf-stat-front">
                    <p class="stf-stat-label">Today's Attendance</p>
                    <p class="stf-stat-value"><?= (int)$todayAttendance ?></p>
                </div>
                <div class="stf-stat-face stf-stat-back">
                    <p class="stf-stat-back-title">Week Attendance</p>
                    <p class="stf-stat-back-value"><?= (int)$weekAttendance ?></p>
                    <p class="stf-stat-back-copy">Total marks from last 7 days.</p>
                </div>
            </div>
        </article>
        <article class="stf-card stf-stat">
            <div class="stf-stat-flip">
                <div class="stf-stat-face stf-stat-front">
                    <p class="stf-stat-label">Total Students</p>
                    <p class="stf-stat-value"><?= (int)$totalStudents ?></p>
                    <p class="stf-stat-sub">Course: <?= (int)$courseStudents ?> | Internship: <?= (int)$internshipStudents ?></p>
                </div>
                <div class="stf-stat-face stf-stat-back">
                    <p class="stf-stat-back-title">Class Mix</p>
                    <p class="stf-stat-back-value"><?= (int)$courseStudents ?> / <?= (int)$internshipStudents ?></p>
                    <p class="stf-stat-back-copy">Course vs internship distribution.</p>
                </div>
            </div>
        </article>
        <article class="stf-card stf-stat">
            <div class="stf-stat-flip">
                <div class="stf-stat-face stf-stat-front">
                    <p class="stf-stat-label">Total Attendance</p>
                    <p class="stf-stat-value"><?= (int)$totalAttendance ?></p>
                </div>
                <div class="stf-stat-face stf-stat-back">
                    <p class="stf-stat-back-title">Missing Today</p>
                    <p class="stf-stat-back-value"><?= (int)$todayMissingAttendance ?></p>
                    <p class="stf-stat-back-copy">Students not marked today.</p>
                </div>
            </div>
        </article>
        <article class="stf-card stf-stat">
            <div class="stf-stat-flip">
                <div class="stf-stat-face stf-stat-front">
                    <p class="stf-stat-label">Total Assessments</p>
                    <p class="stf-stat-value"><?= (int)$totalAssessment ?></p>
                </div>
                <div class="stf-stat-face stf-stat-back">
                    <p class="stf-stat-back-title">Coverage</p>
                    <p class="stf-stat-back-value"><?= number_format($assessmentCoveragePct, 1) ?>%</p>
                    <p class="stf-stat-back-copy">Assessment count vs active students.</p>
                </div>
            </div>
        </article>
        <article class="stf-card stf-stat">
            <div class="stf-stat-flip">
                <div class="stf-stat-face stf-stat-front">
                    <p class="stf-stat-label">Mock Interviews</p>
                    <p class="stf-stat-value"><?= (int)$totalMockInterviews ?></p>
                </div>
                <div class="stf-stat-face stf-stat-back">
                    <p class="stf-stat-back-title">Mock Coverage</p>
                    <p class="stf-stat-back-value"><?= number_format($mockCoveragePct, 1) ?>%</p>
                    <p class="stf-stat-back-copy">Mock count vs active students.</p>
                </div>
            </div>
        </article>
    </section>

    <section class="stf-chart-grid">
        <article class="stf-card stf-chart-card">
            <div class="stf-links-head">
                <h2 class="stf-links-title">Attendance Trend (Last 14 Days)</h2>
                <span class="stf-links-sub">Daily marks</span>
            </div>
            <div class="stf-chart-shell">
                <canvas id="stfAttendanceChart"></canvas>
            </div>
        </article>

        <article class="stf-card stf-chart-card">
            <div class="stf-links-head">
                <h2 class="stf-links-title">Student Mix</h2>
                <span class="stf-links-sub">Course vs Internship</span>
            </div>
            <div class="stf-chart-shell">
                <canvas id="stfMixChart"></canvas>
            </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
    if (typeof Chart === 'undefined') return;

    const trendEl = document.getElementById('stfAttendanceChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'line',
            data: {
                labels: <?= json_encode($attendanceChartLabels, JSON_UNESCAPED_SLASHES) ?>,
                datasets: [{
                    label: 'Attendance',
                    data: <?= json_encode($attendanceChartData, JSON_NUMERIC_CHECK) ?>,
                    borderColor: '#e11d74',
                    backgroundColor: 'rgba(225,29,116,0.14)',
                    pointBackgroundColor: '#e11d74',
                    pointBorderColor: '#fff',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.34,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#7f5b6d', maxRotation: 0, autoSkip: true, maxTicksLimit: 7 }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#7f5b6d' },
                        grid: { color: 'rgba(127, 91, 109, 0.14)' }
                    }
                }
            }
        });
    }

    const mixEl = document.getElementById('stfMixChart');
    if (mixEl) {
        new Chart(mixEl, {
            type: 'doughnut',
            data: {
                labels: ['Course', 'Internship'],
                datasets: [{
                    data: [<?= (int)$courseStudents ?>, <?= (int)$internshipStudents ?>],
                    backgroundColor: ['#e11d74', '#f9a8d4'],
                    borderColor: ['#e11d74', '#f9a8d4'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            color: '#7f5b6d',
                            padding: 12
                        }
                    }
                }
            }
        });
    }
})();
</script>


