<?php
if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('students/course');
}

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('courseStudentFormatDate')) {
    function courseStudentFormatDate($value, bool $withTime = false): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date($withTime ? 'd M Y, g:i A' : 'd M Y', $timestamp);
    }
}

if (($_SESSION['role_name'] ?? '') !== 'HR') {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:var(--crm-font-family)'>
            <h2 style='margin:0 0 8px;color:var(--crm-primary)'>Access Denied</h2>
            <p style='margin:0;color:var(--crm-text-muted)'>This page is available only for HR users.</p>
          </div>";
    return;
}

$roleId = (int) ($_SESSION['role_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);
$canAllBranches = 0;
$csrfToken = generateCSRF();

try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

$search = trim((string) ($_GET['q'] ?? ''));
$certificateFilter = trim((string) ($_GET['certificate'] ?? ''));
if (!in_array($certificateFilter, ['given', 'not_given'], true)) {
    $certificateFilter = '';
}
$courseStatusFilter = trim((string) ($_GET['course_status'] ?? ''));
if (!in_array($courseStatusFilter, ['completed'], true)) {
    $courseStatusFilter = '';
}

$rows = [];
$certificateSnapshotIds = [];
$certificateSnapshotTimes = [];

try {
    $snapshotDir = UPLOAD_PATH . 'course_certificates/generated/';
    foreach (glob($snapshotDir . 'course_certificate_*.json') ?: [] as $snapshotPath) {
        if (preg_match('/course_certificate_(\d+)\.json$/', (string) $snapshotPath, $matches)) {
            $snapshotId = (int) $matches[1];
            $certificateSnapshotIds[$snapshotId] = true;
            $certificateSnapshotTimes[$snapshotId] = (int) (@filemtime($snapshotPath) ?: 0);
        }
    }
} catch (Exception $e) {
    $certificateSnapshotIds = [];
    $certificateSnapshotTimes = [];
}

try {
    $params = [];
    $where = [
        "r.reg_type = 'course'",
        "r.payment_status = 'paid'",
        "shi.registration_id IS NOT NULL",
    ];

    if ($canAllBranches !== 1 && $branchId > 0) {
        $where[] = "r.branch_id = ?";
        $params[] = $branchId;
    }

    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = "(
            r.registration_no LIKE ?
            OR COALESCE(rp.student_name, r.enquiry_snapshot_name, '') LIKE ?
            OR COALESCE(r.program_name, '') LIKE ?
            OR COALESCE(r.batch_name, '') LIKE ?
        )";
        array_push($params, $like, $like, $like, $like);
    }

    if ($certificateFilter === 'given') {
        $where[] = "r.internship_certificate_status = 'given'";
    } elseif ($certificateFilter === 'not_given') {
        $where[] = "COALESCE(r.internship_certificate_status, 'not_given') <> 'given'";
    }

    if ($courseStatusFilter === 'completed') {
        $where[] = "r.registration_status = 'completed'";
    }

    $sql = "
        SELECT
            r.id,
            r.registration_no,
            r.joined_on,
            r.enquiry_snapshot_name,
            r.program_name,
            r.batch_name,
            r.registration_status,
            r.payment_status,
            r.internship_completion_status,
            r.internship_certificate_status,
            r.internship_certificate_issued_at,
            rp.student_name,
            shi.interview_status,
            shi.sent_to_hr_at
        FROM registrations r
        INNER JOIN student_hr_interviews shi ON shi.registration_id = r.id
        LEFT JOIN registration_profiles rp ON rp.registration_id = r.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(r.internship_certificate_issued_at, shi.sent_to_hr_at, r.joined_on) DESC, r.id DESC
    ";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $registrationId = (int) ($row['id'] ?? 0);
        $row['_certificate_snapshot_exists'] = isset($certificateSnapshotIds[$registrationId]);
        $row['_certificate_snapshot_mtime'] = $certificateSnapshotTimes[$registrationId] ?? 0;
    }
    unset($row);
} catch (Exception $e) {
    setFlash('error', 'Unable to load course students: ' . $e->getMessage());
}

$totalStudents = count($rows);
$issuedCertificates = 0;
$pendingCertificates = 0;
$completedCourses = 0;

foreach ($rows as $row) {
    $certificateStatus = strtolower(trim((string) ($row['internship_certificate_status'] ?? 'not_given')));
    $certificateSaved = !empty($row['_certificate_snapshot_exists']);
    if ($certificateSaved || $certificateStatus === 'given') {
        $issuedCertificates++;
    } else {
        $pendingCertificates++;
    }

    if (strtolower((string) ($row['registration_status'] ?? '')) === 'completed') {
        $completedCourses++;
    }
}

$branchVisibilityLabel = $canAllBranches === 1 ? 'Showing all branches' : 'Showing your branch only';
$courseStudentsBaseUrl = 'index.php?page=students/course';
$quickFilterParams = [
    'q' => $search,
    'certificate' => $certificateFilter,
    'course_status' => $courseStatusFilter,
];
$buildCourseStudentsUrl = static function (array $overrides = []) use ($courseStudentsBaseUrl, $quickFilterParams): string {
    $params = array_merge($quickFilterParams, $overrides);
    $query = ['page' => 'students/course'];
    foreach ($params as $key => $value) {
        if (trim((string) $value) !== '') {
            $query[$key] = $value;
        }
    }

    return 'index.php?' . http_build_query($query);
};
?>

<div class="handlink-page">
    <div class="handlink-header">
        <div>
            <h2><i class="fas fa-clipboard"></i> Course Students</h2>
            <p>HR can manage only the paid course students already handed over by staff, view student details, and generate completion certificates.</p>
        </div>
        <div class="handlink-stats">
            <span><i class="fas fa-database"></i> Total: <?= (int) $totalStudents ?></span>
            <span><i class="fas fa-certificate"></i> Issued: <?= (int) $issuedCertificates ?></span>
            <span><i class="fas fa-hourglass-half"></i> Pending: <?= (int) $pendingCertificates ?></span>
            <span><i class="fas fa-check-circle"></i> Completed: <?= (int) $completedCourses ?></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Filter Students</div>
        <div class="card-body">
            <form method="GET" action="index.php" class="handlink-filter-form">
                <input type="hidden" name="page" value="students/course">
                <div class="handlink-filter-grid">
                    <div class="filter-field">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Registration, student, program, batch">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="crm-icon-btn is-primary" data-mobile-label="Apply" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="index.php?page=students/course" class="crm-icon-btn is-muted" data-mobile-label="Reset" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                            <i class="fas fa-undo-alt"></i>
                        </a>
                    </div>
                </div>
                <div class="handlink-quick-row">
                    <div class="handlink-branch-scope">
                        <i class="fas fa-code-branch"></i> <?= h($branchVisibilityLabel) ?>
                    </div>
                    <div class="handlink-quick-filters" aria-label="Quick filters">
                        <a href="<?= h($buildCourseStudentsUrl(['certificate' => '', 'course_status' => ''])) ?>" class="<?= $certificateFilter === '' && $courseStatusFilter === '' ? 'is-active' : '' ?>">All</a>
                        <a href="<?= h($buildCourseStudentsUrl(['certificate' => 'not_given', 'course_status' => ''])) ?>" class="<?= $certificateFilter === 'not_given' ? 'is-active' : '' ?>">Certificate Pending</a>
                        <a href="<?= h($buildCourseStudentsUrl(['certificate' => 'given', 'course_status' => ''])) ?>" class="<?= $certificateFilter === 'given' ? 'is-active' : '' ?>">Certificate Issued</a>
                        <a href="<?= h($buildCourseStudentsUrl(['course_status' => 'completed', 'certificate' => ''])) ?>" class="<?= $courseStatusFilter === 'completed' ? 'is-active' : '' ?>">Course Completed</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header handlink-table-head">
            <span><i class="fas fa-list"></i> Paid Students Sent To HR</span>
            <div id="datatableControls"></div>
        </div>
        <div class="handlink-table-wrap">
            <table id="courseHandlinkTable" class="crm-table handlink-table display" style="width:100%;">
                <thead>
                    <tr>
                        <th>Registration</th>
                        <th>Student</th>
                        <th>Program</th>
                        <th>HR Status</th>
                        <th>Course Status</th>
                        <th>Certificate</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $studentName = $row['student_name'] ?: $row['enquiry_snapshot_name'] ?: '-';
                        $certificateIssuedAt = trim((string) ($row['internship_certificate_issued_at'] ?? ''));
                        $certificateStatus = strtolower(trim((string) ($row['internship_certificate_status'] ?? 'not_given')));
                        $certificateSnapshotExists = !empty($row['_certificate_snapshot_exists']);
                        $certificateSnapshotTime = (int) ($row['_certificate_snapshot_mtime'] ?? 0);
                        $certificateLabel = 'Pending';
                        $certificateClass = 'status-muted';
                        $certificateDateLabel = '-';
                        if ($certificateSnapshotExists) {
                            $certificateLabel = 'Saved';
                            $certificateClass = 'status-success';
                            $certificateDateLabel = courseStudentFormatDate($certificateIssuedAt, true);
                            if ($certificateDateLabel === '-' && $certificateSnapshotTime > 0) {
                                $certificateDateLabel = date('d M Y, g:i A', $certificateSnapshotTime);
                            }
                        } elseif ($certificateStatus === 'given') {
                            $certificateLabel = 'Issued';
                            $certificateClass = 'status-success';
                            $certificateDateLabel = courseStudentFormatDate($certificateIssuedAt, true);
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="handlink-primary"><?= h($row['registration_no'] ?: ('REG-' . $row['id'])) ?></div>
                                <div class="handlink-sub">Joined: <?= h(courseStudentFormatDate($row['joined_on'] ?? '')) ?></div>
                            </td>
                            <td>
                                <div class="handlink-primary"><?= h($studentName) ?></div>
                                <div class="handlink-sub">Moved to HR: <?= h(courseStudentFormatDate($row['sent_to_hr_at'] ?? '', true)) ?></div>
                            </td>
                            <td>
                                <div class="handlink-primary"><?= h($row['program_name'] ?: '-') ?></div>
                                <div class="handlink-sub"><?= h($row['batch_name'] ?: '-') ?></div>
                            </td>
                            <td>
                                <span class="status-pill status-hr"><?= h(ucwords(str_replace('_', ' ', (string) ($row['interview_status'] ?? 'pending')))) ?></span>
                            </td>
                            <td>
                                <span class="status-pill <?= strtolower((string) ($row['registration_status'] ?? '')) === 'completed' ? 'status-success' : 'status-warning' ?>">
                                    <?= h(ucfirst((string) ($row['registration_status'] ?? '-'))) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?= h($certificateClass) ?>">
                                    <?= h($certificateLabel) ?>
                                </span>
                                <div class="handlink-sub">
                                    <?= h($certificateDateLabel) ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="crm-icon-actions">
                                    <a
                                        href="index.php?page=reports/student_profile&id=<?= (int) $row['id'] ?>"
                                        class="crm-icon-btn is-info"
                                        data-mobile-label="View"
                                        data-modern-tooltip="View student details"
                                        aria-label="View student details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($certificateSnapshotExists): ?>
                                        <a
                                            href="index.php?page=students/course_certificate&id=<?= (int) $row['id'] ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="crm-icon-btn is-success"
                                            data-mobile-label="View Certificate"
                                            data-modern-tooltip="View/download saved certificate"
                                            aria-label="View/download saved certificate">
                                            <i class="fas fa-certificate"></i>
                                        </a>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="crm-icon-btn is-success js-open-certificate-modal"
                                            data-registration-id="<?= (int) $row['id'] ?>"
                                            data-student-name="<?= h($studentName) ?>"
                                            data-registration-no="<?= h($row['registration_no'] ?: ('REG-' . $row['id'])) ?>"
                                            data-mobile-label="Generate"
                                            data-modern-tooltip="Generate certificate"
                                            aria-label="Generate certificate">
                                            <i class="fas fa-certificate"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="crm-modal" id="courseCertificateModal" aria-hidden="true">
    <div class="crm-modal-backdrop" data-close-modal></div>
    <div class="crm-modal-dialog handlink-certificate-dialog" role="dialog" aria-modal="true" aria-labelledby="courseCertificateModalTitle">
        <div class="crm-modal-header">
            <div>
                <h3 id="courseCertificateModalTitle">Generate Course Certificate</h3>
                <p class="handlink-modal-copy">Upload the final signature along with the performance remark. The saved certificate becomes view-only and can be downloaded.</p>
            </div>
            <button type="button" class="crm-modal-close" data-close-modal aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="index.php?page=students/course_certificate" target="_blank" enctype="multipart/form-data" class="handlink-certificate-form" id="courseCertificateForm">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="registration_id" id="certificateRegistrationId" value="">

            <div class="crm-modal-body">
                <div class="handlink-modal-summary">
                    <div class="handlink-modal-label">Student</div>
                    <div class="handlink-modal-value" id="certificateStudentName">-</div>
                </div>
                <div class="handlink-modal-summary">
                    <div class="handlink-modal-label">Registration</div>
                    <div class="handlink-modal-value" id="certificateRegistrationNo">-</div>
                </div>

                <div class="filter-field">
                    <label for="certificateRemarks"><i class="fas fa-star"></i> Remarks</label>
                    <input type="text" name="certificate_remarks" id="certificateRemarks" value="Excellent" required placeholder="Example: Excellent">
                </div>

                <div class="filter-field">
                    <label for="certificateHrName"><i class="fas fa-user-tie"></i> HR Name</label>
                    <input
                        type="text"
                        name="hr_name"
                        id="certificateHrName"
                        value="<?= h((string) ($_SESSION['full_name'] ?? $_SESSION['name'] ?? $_SESSION['username'] ?? '')) ?>"
                        required
                        placeholder="Enter HR name">
                </div>

                <div class="filter-field">
                    <label for="certificateSignature"><i class="fas fa-file-signature"></i> Signature Image</label>
                    <input type="file" name="authority_signature" id="certificateSignature" accept=".png,.jpg,.jpeg" required>
                    <div class="handlink-upload-note">Accepted: JPG or PNG, up to 2 MB.</div>
                </div>
            </div>

            <div class="crm-modal-footer">
                <button type="button" class="crm-btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="crm-btn primary" id="courseCertificateSubmit">
                    <i class="fas fa-certificate"></i> Generate & Save Certificate
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .handlink-page {
        --handlink-primary: var(--crm-primary);
        --handlink-primary-dark: var(--crm-primary-dark);
        --handlink-primary-light: var(--crm-primary-light);
        --handlink-accent: var(--crm-accent);
        --handlink-text: var(--crm-text);
        --handlink-muted: var(--crm-text-muted);
        --handlink-bg-light: var(--crm-bg-light);
        --handlink-border: var(--crm-border);
        --handlink-surface: var(--crm-surface);
        --handlink-link: var(--crm-link);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .handlink-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
    }

    .handlink-header h2 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 28px;
        font-weight: 900;
        color: var(--handlink-text);
    }

    .handlink-header p {
        margin: 8px 0 0;
        color: var(--handlink-muted);
        font-weight: 600;
    }

    .handlink-page .card {
        background: var(--handlink-surface);
        border-color: var(--handlink-border);
    }

    .handlink-page .card-header {
        background: var(--handlink-bg-light);
        border-color: var(--handlink-border);
        color: var(--handlink-text);
    }

    .handlink-stats {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .handlink-stats span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 14px;
        border-radius: 999px;
        background: var(--handlink-bg-light);
        border: 1px solid var(--handlink-border);
        color: var(--handlink-primary-dark);
        font-weight: 800;
    }

    .handlink-filter-form {
        padding: 12px 14px;
    }

    .handlink-filter-grid {
        display: grid;
        grid-template-columns: minmax(260px, 1fr) auto;
        gap: 14px;
        align-items: end;
    }

    .handlink-quick-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 14px;
    }

    .handlink-branch-scope {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: var(--handlink-muted);
        font-size: 13px;
        font-weight: 700;
    }

    .handlink-quick-filters {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .handlink-quick-filters a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 7px 11px;
        border: 1px solid var(--handlink-border);
        border-radius: 8px;
        background: var(--handlink-surface);
        color: var(--handlink-muted);
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        transition: .15s ease;
    }

    .handlink-quick-filters a:hover,
    .handlink-quick-filters a.is-active {
        background: var(--handlink-primary-light);
        border-color: var(--handlink-primary);
        color: var(--handlink-primary-dark);
    }

    .filter-field label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 800;
        color: var(--handlink-muted);
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .filter-field input,
    .filter-field select {
        width: 100%;
        min-height: 42px;
        padding: 10px 12px;
        border: 1px solid var(--handlink-border);
        border-radius: 10px;
        background: var(--handlink-surface);
        color: var(--handlink-text);
        outline: none;
        transition: .15s ease;
    }

    .filter-field input:focus,
    .filter-field select:focus {
        border-color: var(--handlink-primary);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--handlink-primary) 14%, transparent);
    }

    .filter-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: flex-end;
    }

    .handlink-page .crm-icon-btn.is-primary,
    .handlink-page .crm-icon-btn.is-success,
    .handlink-page .crm-icon-btn.is-info {
        background: var(--handlink-primary) !important;
        border-color: var(--handlink-primary) !important;
        color: var(--handlink-surface) !important;
    }

    .handlink-page .crm-icon-btn.is-muted {
        background: var(--handlink-primary-light) !important;
        border-color: var(--handlink-border) !important;
        color: var(--handlink-primary-dark) !important;
    }

    .handlink-page .crm-icon-btn:hover,
    .handlink-page .crm-icon-btn:focus-visible {
        background: var(--handlink-primary-dark) !important;
        border-color: var(--handlink-primary-dark) !important;
        color: var(--handlink-surface) !important;
    }

    .handlink-table-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        font-weight: 900;
        font-size: 16px;
        color: var(--handlink-text);
    }

    .handlink-table-wrap {
        padding: 14px;
        overflow-x: auto;
    }

    #datatableControls {
        width: auto;
        margin-left: auto;
        display: flex;
        justify-content: flex-end;
    }

    #datatableControls .dt-top {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 12px;
        flex-wrap: nowrap;
    }

    .dataTables_wrapper .dt-top,
    .dataTables_wrapper .dt-bottom {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .dataTables_wrapper .dt-bottom {
        justify-content: space-between;
        margin-top: 12px;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin: 0;
        display: flex;
        align-items: center;
    }

    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-weight: 700;
        color: var(--handlink-text);
        white-space: nowrap;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid var(--handlink-border);
        border-radius: 10px;
        padding: 8px 12px;
        background: var(--handlink-surface);
        color: var(--handlink-text);
        min-height: 38px;
        outline: none;
    }

    .dataTables_wrapper .dataTables_filter input:focus,
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: var(--handlink-primary);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--handlink-primary) 14%, transparent);
    }

    .dataTables_wrapper .dataTables_filter input {
        min-width: 240px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border: 1px solid var(--handlink-border) !important;
        background: var(--handlink-surface) !important;
        color: var(--handlink-muted) !important;
        border-radius: 8px !important;
        padding: 6px 10px !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: var(--handlink-primary) !important;
        border-color: var(--handlink-primary) !important;
        color: var(--handlink-surface) !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: var(--handlink-primary-light) !important;
        border-color: var(--handlink-primary) !important;
        color: var(--handlink-primary-dark) !important;
    }

    .dataTables_wrapper .dataTables_info {
        color: var(--handlink-muted);
        font-weight: 600;
    }

    .crm-table.handlink-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 980px;
    }

    .crm-table.handlink-table th,
    .crm-table.handlink-table td {
        padding: 12px 12px;
        border-bottom: 1px solid var(--handlink-border);
        vertical-align: middle;
        font-size: 13px;
    }

    .crm-table.handlink-table td:nth-child(4),
    .crm-table.handlink-table td:nth-child(5),
    .crm-table.handlink-table td:nth-child(6),
    .crm-table.handlink-table td:nth-child(7) {
        text-align: center;
    }

    .crm-table.handlink-table th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .35px;
        font-weight: 800;
        color: var(--handlink-text);
        background: var(--handlink-bg-light);
        white-space: nowrap;
    }

    .crm-table.handlink-table tbody tr:hover {
        background: var(--handlink-bg-light);
    }

    .handlink-empty-state {
        padding: 20px 12px;
        color: var(--handlink-muted);
        font-weight: 700;
        text-align: center;
    }

    .handlink-empty-state a {
        display: inline-flex;
        margin-top: 8px;
        color: var(--handlink-link);
        font-weight: 800;
        text-decoration: none;
    }

    .handlink-primary {
        font-weight: 800;
        color: var(--handlink-text);
    }

    .handlink-sub {
        margin-top: 3px;
        font-size: 12px;
        color: var(--handlink-muted);
    }

    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 92px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        border: 1px solid transparent;
    }

    .status-success {
        background: var(--handlink-primary-light);
        color: var(--handlink-primary-dark);
        border-color: var(--handlink-primary);
    }

    .status-warning {
        background: color-mix(in srgb, var(--handlink-primary-light) 80%, var(--handlink-surface));
        color: var(--handlink-primary-dark);
        border-color: var(--handlink-border);
    }

    .status-muted {
        background: var(--handlink-surface);
        color: var(--handlink-muted);
        border-color: var(--handlink-border);
    }

    .status-hr {
        background: var(--handlink-bg-light);
        color: var(--handlink-primary-dark);
        border-color: var(--handlink-border);
    }

    .crm-icon-btn.is-disabled {
        background: color-mix(in srgb, var(--handlink-surface) 72%, var(--handlink-bg-light));
        border-color: var(--handlink-border);
        color: var(--handlink-muted);
        cursor: not-allowed;
        box-shadow: none;
        transform: none;
    }

    .crm-modal {
        position: fixed;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        z-index: 10030;
    }

    .crm-modal.show {
        display: flex;
    }

    .crm-modal-backdrop {
        position: absolute;
        inset: 0;
        background: color-mix(in srgb, var(--handlink-text) 42%, transparent);
        backdrop-filter: blur(2px);
    }

    .crm-modal-dialog {
        position: relative;
        z-index: 1;
        width: min(100%, 560px);
        background: linear-gradient(180deg, var(--handlink-surface) 0%, var(--handlink-bg-light) 100%);
        border: 1px solid var(--handlink-border);
        border-radius: 22px;
        box-shadow: 0 30px 60px color-mix(in srgb, var(--handlink-text) 24%, transparent);
        overflow: hidden;
    }

    .crm-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding: 20px 22px 14px;
        border-bottom: 1px solid var(--handlink-border);
    }

    .crm-modal-header h3 {
        margin: 0;
        font-size: 22px;
        font-weight: 900;
        color: var(--handlink-text);
    }

    .crm-modal-close {
        width: 38px;
        height: 38px;
        border: 1px solid var(--handlink-border);
        border-radius: 12px;
        background: var(--handlink-surface);
        color: var(--handlink-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: .15s ease;
    }

    .crm-modal-close:hover {
        background: var(--handlink-primary-light);
        color: var(--handlink-primary-dark);
        border-color: var(--handlink-primary);
    }

    .crm-modal-body {
        padding: 18px 22px;
    }

    .crm-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 0 22px 22px;
        flex-wrap: wrap;
    }

    .handlink-certificate-dialog {
        max-width: 560px;
    }

    .handlink-modal-copy {
        margin: 6px 0 0;
        color: var(--handlink-muted);
        font-size: 13px;
        font-weight: 600;
    }

    .handlink-certificate-form {
        margin: 0;
    }

    .handlink-modal-summary {
        padding: 12px 14px;
        border-radius: 14px;
        background: var(--handlink-bg-light);
        border: 1px solid var(--handlink-border);
        margin-bottom: 14px;
    }

    .handlink-modal-label {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .35px;
        color: var(--handlink-primary-dark);
    }

    .handlink-modal-value {
        margin-top: 4px;
        color: var(--handlink-text);
        font-size: 15px;
        font-weight: 800;
    }

    .handlink-upload-note {
        margin-top: 8px;
        font-size: 12px;
        color: var(--handlink-muted);
        font-weight: 600;
    }

    .crm-btn {
        border: none;
        border-radius: 10px;
        min-height: 44px;
        min-width: 140px;
        padding: 10px 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 800;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        flex: 0 0 auto;
    }

    .crm-btn.primary {
        background: var(--handlink-primary);
        color: var(--handlink-surface);
    }

    .crm-btn.primary:disabled {
        background: color-mix(in srgb, var(--handlink-primary) 45%, var(--handlink-surface));
        cursor: wait;
    }

    .crm-btn.ghost {
        background: var(--handlink-primary-light);
        color: var(--handlink-primary-dark);
    }

    @media (max-width: 900px) {
        .handlink-filter-grid {
            grid-template-columns: 1fr;
        }

        .handlink-quick-row,
        .handlink-quick-filters {
            align-items: stretch;
            justify-content: flex-start;
        }

        .handlink-quick-filters a {
            flex: 1 1 auto;
        }

        .filter-actions {
            justify-content: flex-start;
        }

        .crm-modal {
            padding: 12px;
        }

        .crm-modal-header,
        .crm-modal-body,
        .crm-modal-footer {
            padding-left: 16px;
            padding-right: 16px;
        }

        .crm-modal-footer {
            justify-content: stretch;
        }

        .crm-modal-footer .crm-btn {
            flex: 1 1 100%;
            width: 100%;
            min-width: 0;
        }

        #datatableControls {
            width: 100%;
            margin-left: 0;
            justify-content: flex-start;
        }

        #datatableControls .dt-top,
        .dataTables_wrapper .dt-bottom {
            justify-content: flex-start;
        }

        #datatableControls .dt-top {
            flex-wrap: wrap;
        }

        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_length {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter label {
            width: 100%;
        }

        .dataTables_wrapper .dataTables_filter input {
            width: 100% !important;
            min-width: 0;
        }
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

/* ===== GLOBAL BUTTON STANDARDIZATION ===== */
button,
.btn,
.crm-action-btn,
.btn-filter,
.btn-reset,
.btn-add,
.btn-excel,
.action-btn,
.btn-icon-only,
a.btn,
input[type="button"],
input[type="submit"],
input[type="reset"],
[role="button"] {
    font-size: 0.92rem;
    min-height: 38px;
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
}

.btn-icon-only,
.crm-action-btn,
.action-btn,
.btn-sm,
.btn-xs,
button.btn-icon,
a.btn-icon,
.btn i:only-child,
button i:only-child {
    font-size: 0.9rem;
    min-height: 34px;
    padding: 8px;
    border-radius: 10px;
    font-weight: 600;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof crmDataTable !== 'function') {
        return;
    }

    try {
        crmDataTable('#courseHandlinkTable', {
            pageLength: 10,
            lengthMenu: [5, 10, 20, 50, 100],
            ordering: true,
            scrollX: false,
            responsive: false,
            searchPlaceholder: 'Search course students...',
            language: {
                emptyTable: '<div class="handlink-empty-state">No course students found for these filters.<br><a href="index.php?page=students/course">Reset filters</a></div>',
                zeroRecords: '<div class="handlink-empty-state">No course students found for this search.<br><a href="index.php?page=students/course">Reset filters</a></div>'
            },
            columnDefs: [
                { orderable: false, targets: [6] }
            ],
            dom:
                "<'dt-top'lf>" +
                "rt" +
                "<'dt-bottom'ip>"
        });

        setTimeout(function () {
            const controls = document.querySelector('.dt-top');
            const target = document.getElementById('datatableControls');
            if (controls && target) {
                target.appendChild(controls);
            }
        }, 100);
    } catch (e) {}

    const modal = document.getElementById('courseCertificateModal');
    if (!modal) {
        return;
    }

    const registrationIdInput = document.getElementById('certificateRegistrationId');
    const studentNameNode = document.getElementById('certificateStudentName');
    const registrationNoNode = document.getElementById('certificateRegistrationNo');
    const remarksInput = document.getElementById('certificateRemarks');
    const signatureInput = document.getElementById('certificateSignature');
    const certificateForm = document.getElementById('courseCertificateForm');
    const submitButton = document.getElementById('courseCertificateSubmit');
    let lastFocusedButton = null;

    function setSubmitLoading(isLoading) {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = isLoading;
        submitButton.innerHTML = isLoading
            ? '<i class="fas fa-spinner fa-spin"></i> Generating...'
            : '<i class="fas fa-certificate"></i> Generate & Save Certificate';
    }

    function validateSignatureFile() {
        if (!signatureInput) {
            return true;
        }

        const file = signatureInput.files && signatureInput.files[0] ? signatureInput.files[0] : null;
        if (!file) {
            alert('Please choose a signature image.');
            signatureInput.focus();
            return false;
        }

        const allowedTypes = ['image/png', 'image/jpeg'];
        const allowedExtensions = ['png', 'jpg', 'jpeg'];
        const extension = (file.name.split('.').pop() || '').toLowerCase();
        if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(extension)) {
            alert('Signature image must be JPG or PNG.');
            signatureInput.value = '';
            signatureInput.focus();
            return false;
        }

        if (file.size > 2 * 1024 * 1024) {
            alert('Signature image must be under 2 MB.');
            signatureInput.value = '';
            signatureInput.focus();
            return false;
        }

        return true;
    }

    function openModal(button) {
        lastFocusedButton = button;
        registrationIdInput.value = button.getAttribute('data-registration-id') || '';
        studentNameNode.textContent = button.getAttribute('data-student-name') || '-';
        registrationNoNode.textContent = button.getAttribute('data-registration-no') || '-';
        if (!remarksInput.value.trim()) {
            remarksInput.value = 'Excellent';
        }
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        window.setTimeout(function () {
            remarksInput.focus();
            remarksInput.select();
        }, 50);
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        setSubmitLoading(false);
        if (lastFocusedButton) {
            lastFocusedButton.focus();
        }
    }

    document.querySelectorAll('.js-open-certificate-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button);
        });
    });

    modal.querySelectorAll('[data-close-modal]').forEach(function (node) {
        node.addEventListener('click', closeModal);
    });

    if (signatureInput) {
        signatureInput.addEventListener('change', validateSignatureFile);
    }

    if (certificateForm) {
        certificateForm.addEventListener('submit', function (event) {
            if (!validateSignatureFile()) {
                event.preventDefault();
                return;
            }

            setSubmitLoading(true);
            window.setTimeout(function () {
                setSubmitLoading(false);
            }, 3000);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
});

</script>

