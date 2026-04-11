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

if (($_SESSION['role_name'] ?? '') !== 'HR') {
    http_response_code(403);
    echo "<div style='padding:20px;font-family:Poppins,sans-serif'>
            <h2 style='margin:0 0 8px;color:#e91e63'>Access Denied</h2>
            <p style='margin:0;color:#666'>This page is available only for HR users.</p>
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

$rows = [];

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
} catch (Exception $e) {
    setFlash('error', 'Unable to load course students: ' . $e->getMessage());
}
?>

<div class="handlink-page">
    <div class="handlink-header">
        <div>
            <h2><i class="fas fa-clipboard"></i> Course Students</h2>
            <p>HR can manage only the paid course students already handed over by staff, view student details, and generate completion certificates.</p>
        </div>
        <div class="handlink-stat">
            <span><i class="fas fa-database"></i> Total: <?= (int) count($rows) ?></span>
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
                    <div class="filter-field">
                        <label><i class="fas fa-certificate"></i> Certificate</label>
                        <select name="certificate">
                            <option value="">All</option>
                            <option value="given" <?= $certificateFilter === 'given' ? 'selected' : '' ?>>Issued</option>
                            <option value="not_given" <?= $certificateFilter === 'not_given' ? 'selected' : '' ?>>Not Issued</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="crm-icon-btn is-primary" data-modern-tooltip="Apply filters" aria-label="Apply filters">
                            <i class="fas fa-filter"></i>
                        </button>
                        <a href="index.php?page=students/course" class="crm-icon-btn is-muted" data-modern-tooltip="Reset filters" aria-label="Reset filters">
                            <i class="fas fa-undo-alt"></i>
                        </a>
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
                        $certificateSnapshotExists = crmCourseCertificateSnapshotExists((int) $row['id']);
                        $certificateIsViewOnly = $certificateSnapshotExists;
                        ?>
                        <tr>
                            <td>
                                <div class="handlink-primary"><?= h($row['registration_no'] ?: ('REG-' . $row['id'])) ?></div>
                                <div class="handlink-sub">Joined: <?= h($row['joined_on'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div class="handlink-primary"><?= h($studentName) ?></div>
                                <div class="handlink-sub">Moved to HR: <?= h($row['sent_to_hr_at'] ?: '-') ?></div>
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
                                <span class="status-pill <?= $certificateIsViewOnly || $certificateStatus === 'given' ? 'status-success' : 'status-muted' ?>">
                                    <?= h($certificateIsViewOnly || $certificateStatus === 'given' ? 'Issued' : 'Not Issued') ?>
                                </span>
                                <div class="handlink-sub">
                                    <?= h($certificateIssuedAt !== '' && $certificateIssuedAt !== '0000-00-00 00:00:00' ? $certificateIssuedAt : '-') ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="crm-icon-actions">
                                    <a
                                        href="index.php?page=reports/student_profile&id=<?= (int) $row['id'] ?>"
                                        class="crm-icon-btn is-info"
                                        data-modern-tooltip="View student details"
                                        aria-label="View student details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($certificateIsViewOnly): ?>
                                        <a
                                            href="index.php?page=students/course_certificate&id=<?= (int) $row['id'] ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="crm-icon-btn is-success"
                                            data-modern-tooltip="Open certificate"
                                            aria-label="Open certificate">
                                            <i class="fas fa-certificate"></i>
                                        </a>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="crm-icon-btn is-success js-open-certificate-modal"
                                            data-registration-id="<?= (int) $row['id'] ?>"
                                            data-student-name="<?= h($studentName) ?>"
                                            data-registration-no="<?= h($row['registration_no'] ?: ('REG-' . $row['id'])) ?>"
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
                <p class="handlink-modal-copy">Upload the final signature along with the performance remark before opening the certificate.</p>
            </div>
            <button type="button" class="crm-modal-close" data-close-modal aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" action="index.php?page=students/course_certificate" target="_blank" enctype="multipart/form-data" class="handlink-certificate-form">
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
                <button type="submit" class="crm-btn primary">
                    <i class="fas fa-certificate"></i> Open Certificate
                </button>
            </div>
        </form>
    </div>
</div>

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

    function openModal(button) {
        registrationIdInput.value = button.getAttribute('data-registration-id') || '';
        studentNameNode.textContent = button.getAttribute('data-student-name') || '-';
        registrationNoNode.textContent = button.getAttribute('data-registration-no') || '-';
        if (!remarksInput.value.trim()) {
            remarksInput.value = 'Excellent';
        }
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.js-open-certificate-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            openModal(button);
        });
    });

    modal.querySelectorAll('[data-close-modal]').forEach(function (node) {
        node.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
});

</script>


