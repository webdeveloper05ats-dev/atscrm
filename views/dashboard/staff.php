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

<style>
.stf-dashboard{
    --stf-primary:#d9468b;
    --stf-primary-dark:#b83273;
    --stf-soft:#fff0f7;
    --stf-ink:#4a1f39;
    --stf-muted:#8a6177;
    --stf-border:#efd7e5;
    --stf-bg:#f7f4f8;
    --stf-card:#ffffff;
    --stf-shadow:0 10px 24px rgba(38, 24, 45, 0.06);

    display:grid;
    gap:18px;
    padding:8px 0 20px;
}

.stf-card{
    background:var(--stf-card);
    border:1px solid var(--stf-border);
    border-radius:20px;
    box-shadow:var(--stf-shadow);
}

.stf-hero{
    padding:22px;
    background:
        linear-gradient(135deg, rgba(217,70,139,0.12), rgba(255,255,255,0.95)),
        linear-gradient(180deg, #fff 0%, #fff7fb 100%);
}

.stf-kicker{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    border:1px solid rgba(217,70,139,0.2);
    background:#fff;
    color:var(--stf-primary-dark);
    font-size:11px;
    letter-spacing:.08em;
    text-transform:uppercase;
    font-weight:800;
    margin-bottom:12px;
}

.stf-title{
    margin:0;
    color:var(--stf-ink);
    font-size:1.55rem;
    line-height:1.15;
    font-weight:800;
}

.stf-copy{
    margin:10px 0 0;
    max-width:900px;
    line-height:1.7;
    color:#5a4660;
    font-size:.93rem;
}

.stf-chip-row{
    margin-top:14px;
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.stf-chip{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border-radius:999px;
    border:1px solid var(--stf-border);
    padding:8px 12px;
    background:#fff;
    color:#6b4b5e;
    font-size:.8rem;
    font-weight:700;
}

.stf-stats{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:14px;
}

.stf-stat{
    padding:15px;
    position:relative;
    overflow:hidden;
}

.stf-stat::after{
    content:'';
    position:absolute;
    right:-18px;
    bottom:-18px;
    width:84px;
    height:84px;
    border-radius:50%;
    background:rgba(217,70,139,0.11);
}

.stf-stat-label{
    margin:0;
    color:var(--stf-muted);
    font-size:.77rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.stf-stat-value{
    margin:7px 0 0;
    font-size:1.7rem;
    color:var(--stf-ink);
    font-weight:800;
    line-height:1.1;
}

.stf-stat-sub{
    margin-top:6px;
    color:var(--stf-muted);
    font-size:.78rem;
    font-weight:700;
}

.stf-links{
    padding:16px;
}

.stf-links-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:12px;
}

.stf-links-title{
    margin:0;
    color:var(--stf-ink);
    font-size:1.05rem;
    font-weight:800;
}

.stf-links-sub{
    color:var(--stf-muted);
    font-size:.82rem;
    font-weight:700;
}

.stf-link-grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:12px;
}

.stf-link{
    text-decoration:none;
    border:1px solid var(--stf-border);
    border-radius:14px;
    background:#fff;
    padding:12px;
    display:flex;
    align-items:flex-start;
    gap:10px;
    transition:all .2s ease;
}

.stf-link:hover{
    transform:translateY(-2px);
    border-color:#ddb7ca;
    box-shadow:0 10px 20px rgba(195, 80, 140, 0.12);
}

.stf-link-icon{
    width:34px;
    height:34px;
    border-radius:10px;
    background:var(--stf-soft);
    border:1px solid var(--stf-border);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    color:var(--stf-primary-dark);
    font-size:16px;
    flex-shrink:0;
}

.stf-link-name{
    margin:0;
    color:#2f2330;
    font-size:.92rem;
    font-weight:800;
    line-height:1.3;
    display:block;
}

.stf-link-slug{
    margin-top:3px;
    color:var(--stf-muted);
    font-size:.72rem;
    font-weight:600;
    line-height:1.35;
    word-break:break-word;
    display:block;
}

.stf-empty{
    border:1px dashed var(--stf-border);
    border-radius:14px;
    padding:14px;
    color:var(--stf-muted);
    background:#fff;
    font-size:.88rem;
}

@media (max-width: 1100px){
    .stf-stats{ grid-template-columns:repeat(2, minmax(0, 1fr)); }
    .stf-link-grid{ grid-template-columns:repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 700px){
    .stf-title{ font-size:1.28rem; }
    .stf-stats{ grid-template-columns:1fr; }
    .stf-link-grid{ grid-template-columns:1fr; }
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
</style>

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
