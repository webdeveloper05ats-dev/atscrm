<?php
// =====================================
// Students - Internship Management
// Slug: students/internships
// File: views/students/internships.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('students/internships');
}

if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleId = (int) ($_SESSION['role_id'] ?? 0);
$branchId = (int) ($_SESSION['branch_id'] ?? 0);

$canAllBranches = 0;
try {
    $st = $pdo->prepare("SELECT can_access_all_branches FROM roles WHERE id=? LIMIT 1");
    $st->execute([$roleId]);
    $canAllBranches = (int) ($st->fetchColumn() ?? 0);
} catch (Exception $e) {
    $canAllBranches = 0;
}

/* Save internship details */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_internship'])) {
    $token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($token)) {
        setFlash('error', 'Invalid CSRF token.');
        redirect('index.php?page=students/internships');
        exit;
    }

    $registrationId = (int) ($_POST['registration_id'] ?? 0);
    $startDate = trim((string) ($_POST['internship_start_date'] ?? ''));
    $endDate = trim((string) ($_POST['internship_end_date'] ?? ''));
    $completionStatus = trim((string) ($_POST['internship_completion_status'] ?? 'pending'));
    $certificateStatus = trim((string) ($_POST['internship_certificate_status'] ?? 'not_given'));
    $reportStatus = trim((string) ($_POST['internship_report_status'] ?? 'not_provided'));
    $certificateIssuedAt = trim((string) ($_POST['internship_certificate_issued_at'] ?? ''));
    $reportIssuedAt = trim((string) ($_POST['internship_report_issued_at'] ?? ''));
    $reportDueDays = trim((string) ($_POST['internship_report_due_days'] ?? ''));
    $reportDueDays = $reportDueDays === '' ? null : (int) $reportDueDays;

    try {
        if (!in_array($completionStatus, ['pending', 'in_progress', 'completed'], true)) {
            throw new Exception('Invalid completion status.');
        }

        if (!in_array($certificateStatus, ['not_given', 'given'], true)) {
            throw new Exception('Invalid certificate status.');
        }

        if (!in_array($reportStatus, ['not_provided', 'provided'], true)) {
            throw new Exception('Invalid report status.');
        }

        if ($reportStatus === 'provided' && ($reportDueDays === null || $reportDueDays < 0)) {
            throw new Exception('Please enter valid report days.');
        }

        if ($reportStatus === 'not_provided') {
            $reportDueDays = null;
        }
        if ($certificateStatus === 'given' && $certificateIssuedAt === '') {
    throw new Exception('Please select certificate issued date and time.');
}

if ($certificateStatus === 'not_given') {
    $certificateIssuedAt = null;
}

if ($reportStatus === 'provided' && $reportIssuedAt === '') {
    throw new Exception('Please select report issued date and time.');
}

if ($reportStatus === 'not_provided') {
    $reportIssuedAt = null;
}

        $params = [$registrationId];
        $sql = "
    SELECT id, internship_completion_status, internship_report_status, internship_certificate_status
    FROM registrations
    WHERE id = ?
      AND reg_type = 'internship'
      AND registration_status IN ('active','completed')
";

        if (!$canAllBranches) {
            $sql .= " AND branch_id = ?";
            $params[] = $branchId;
        }

        $sql .= " LIMIT 1";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $internRow = $st->fetch(PDO::FETCH_ASSOC);

        if (!$internRow) {
            throw new Exception('Intern student not found or access denied.');
        }

        if (
            ($internRow['internship_completion_status'] ?? '') === 'completed'
            && ($internRow['internship_report_status'] ?? '') === 'provided'
        ) {
            throw new Exception('Completed internship with submitted report is view only.');
        }

        $upd = $pdo->prepare("
    UPDATE registrations
    SET internship_start_date = ?,
        internship_end_date = ?,
        internship_completion_status = ?,
        internship_certificate_status = ?,
        internship_certificate_issued_at = ?,
        internship_report_status = ?,
        internship_report_issued_at = ?,
        internship_report_due_days = ?,
        updated_at = NOW()
    WHERE id = ?
    LIMIT 1
");
$upd->execute([
    $startDate !== '' ? $startDate : null,
    $endDate !== '' ? $endDate : null,
    $completionStatus,
    $certificateStatus,
    $certificateIssuedAt !== '' ? date('Y-m-d H:i:s', strtotime($certificateIssuedAt)) : null,
    $reportStatus,
    $reportIssuedAt !== '' ? date('Y-m-d H:i:s', strtotime($reportIssuedAt)) : null,
    $reportDueDays,
    $registrationId
]);

        setFlash('success', 'Internship details updated successfully.');
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect('index.php?page=students/internships');
    exit;
}

/* Filters */
$q = trim($_GET['q'] ?? '');
$paymentStatus = trim($_GET['payment_status'] ?? '');

$page = (int) ($_GET['p'] ?? 1);
if ($page < 1)
    $page = 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = [
    "r.reg_type = 'internship'",
    "r.registration_status IN ('active','completed')"
];
$params = [];

if (!$canAllBranches && $branchId > 0) {
    $where[] = "r.branch_id = ?";
    $params[] = $branchId;
}

if ($paymentStatus !== '' && in_array($paymentStatus, ['paid', 'partial', 'unpaid'], true)) {
    $where[] = "r.payment_status = ?";
    $params[] = $paymentStatus;
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(
        r.registration_no LIKE ?
        OR r.enquiry_snapshot_name LIKE ?
        OR r.enquiry_snapshot_phone LIKE ?
        OR r.program_name LIKE ?
    )";
    array_push($params, $like, $like, $like, $like);
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$totalRows = 0;
try {
    $st = $pdo->prepare("SELECT COUNT(*) FROM registrations r $whereSql");
    $st->execute($params);
    $totalRows = (int) $st->fetchColumn();
} catch (Exception $e) {
}

$totalPages = (int) ceil($totalRows / $perPage);
if ($totalPages < 1)
    $totalPages = 1;
if ($page > $totalPages)
    $page = $totalPages;

$rows = [];
try {
    $sql = "
    SELECT
        r.id,
        r.registration_no,
        r.joined_on,
        r.enquiry_snapshot_name,
        r.enquiry_snapshot_phone,
        r.enquiry_snapshot_email,
        r.program_name,
        r.batch_name,
        r.notes,
        r.internship_days,
        r.internship_batch,
        r.internship_start_date,
        r.internship_end_date,
        r.internship_completion_status,
        r.internship_certificate_status,
        r.internship_report_status,
        r.internship_report_due_days,
        r.payment_status,
        r.final_fee,
        r.paid_amount,
        r.internship_certificate_issued_at,
        r.internship_report_issued_at,
        r.balance_amount
    FROM registrations r
    $whereSql
    ORDER BY r.id DESC
    LIMIT $perPage OFFSET $offset
";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

$baseUrl = "index.php?page=students/internships"
    . "&q=" . urlencode($q)
    . "&payment_status=" . urlencode($paymentStatus);

function payBadgeIntern($status)
{
    $map = [
        'paid' => '#2e7d32',
        'partial' => '#fb8c00',
        'unpaid' => '#e53935'
    ];
    $c = $map[$status] ?? '#607d8b';
    return "<span style='font-weight:700;color:$c'>" . ucfirst((string) $status) . "</span>";
}
?>

<style>
    .intern-filter-row {
        display: grid;
        grid-template-columns: 2fr 1fr auto;
        gap: 12px;
        align-items: end;
    }

    .intern-table thead th {
        white-space: nowrap;
    }

    .intern-name {
        font-weight: 800;
        color: #111827;
    }

    .intern-sub {
        font-size: 12px;
        color: #6b7280;
    }

    .intern-form {
        display: grid;
        grid-template-columns: repeat(6, minmax(120px, 1fr)) auto;
        gap: 8px;
        align-items: end;
    }

    .intern-pager {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        margin-top: 14px;
        flex-wrap: wrap;
    }

    .intern-pager a {
        text-decoration: none;
        padding: 7px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        color: #334155;
        font-weight: 700;
        background: #fff;
    }

    @media (max-width: 1200px) {
        .intern-form {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 900px) {
        .intern-filter-row {
            grid-template-columns: 1fr;
        }

        .intern-form {
            grid-template-columns: 1fr;
        }
    }

    .intern-modal-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    @media (max-width: 768px) {
        .intern-modal-grid {
            grid-template-columns: 1fr;
        }
    }

    .intern-modal-close {
        width: 42px;
        height: 42px;
        border: none;
        border-radius: 12px;
        background: #fff;
        color: #1f2937;
        font-size: 28px;
        font-weight: 700;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .08);
        transition: .2s ease;
        padding: 0;
        flex: 0 0 42px;
    }

    .intern-modal-close:hover {
        background: #ffe9f1;
        color: #e91e63;
        transform: translateY(-1px);
    }

    .intern-modal-close:focus {
        outline: none;
        box-shadow: 0 0 0 4px rgba(233, 30, 99, .14);
    }

    .intern-action-wrap{
display:flex;
justify-content:center;
gap:8px;
flex-wrap:wrap;
}

.intern-view-btn{
background:#e8f4fd;
color:#1565c0;
}

.intern-view-grid{
display:grid;
grid-template-columns:1fr 1fr;
gap:14px;
}

.intern-view-box{
background:#fff7fb;
border:1px solid rgba(233,30,99,.12);
border-radius:14px;
padding:12px;
display:flex;
flex-direction:column;
gap:6px;
}

.intern-view-box b{
font-size:12px;
text-transform:uppercase;
color:#6b7280;
}

.intern-view-box span{
font-weight:700;
color:#111827;
word-break:break-word;
}

.intern-view-box-full{
grid-column:1/-1;
}

@media (max-width: 768px){
  .intern-view-grid{
    grid-template-columns:1fr;
  }
}
</style>

<h2 style="margin-bottom:20px;">Intern Students</h2>

<div class="card">
    <div class="card-header">Filters</div>
    <form method="GET" action="index.php" style="padding:14px;">
        <input type="hidden" name="page" value="students/internships">
        <div class="intern-filter-row">
            <div>
                <label>Search</label>
                <input type="text" name="q" value="<?= h($q) ?>" placeholder="Reg no / student / phone / program">
            </div>
            <div>
                <label>Fee Status</label>
                <select name="payment_status">
                    <option value="">All</option>
                    <option value="paid" <?= $paymentStatus === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="partial" <?= $paymentStatus === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="unpaid" <?= $paymentStatus === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="index.php?page=students/internships" class="btn" style="background:#f3f4f6;"><i
                        class="fas fa-undo"></i> Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="card" style="margin-top:16px;">
    <div class="card-header">Intern Students (<?= (int) $totalRows ?>)</div>
    <div class="table-responsive" style="padding:14px;">
        <table class="table intern-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Internship</th>
                    <th>Fee Status</th>
                    <th>Completion</th>
                    <th>Certificate</th>
                    <th>Report</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;">No intern students found.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td><?= (int) ($offset + $i + 1) ?></td>

                        <td>
                            <div class="intern-name"><?= h($r['enquiry_snapshot_name']) ?></div>
                            <div class="intern-sub"><?= h($r['registration_no']) ?> | <?= h(visibleStudentContactValue($r['enquiry_snapshot_phone'] ?? '')) ?>
                            </div>
                        </td>

                        <td>
                            <div><?= h($r['program_name']) ?></div>
                            <?php if (!empty($r['internship_days']) || !empty($r['internship_batch'])): ?>
                                <div class="intern-sub">
                                    <?php if (!empty($r['internship_days'])): ?>
                                        <?= (int) $r['internship_days'] ?> Days
                                    <?php endif; ?>

                                    <?php if (!empty($r['internship_days']) && !empty($r['internship_batch'])): ?>
                                        |
                                    <?php endif; ?>

                                    <?php if (!empty($r['internship_batch'])): ?>
                                        <?= h($r['internship_batch']) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($r['internship_start_date']) || !empty($r['internship_end_date'])): ?>
                                <div class="intern-sub">
                                    <?= h($r['internship_start_date'] ?: '-') ?>
                                    <?= !empty($r['internship_end_date']) ? ' to ' . h($r['internship_end_date']) : '' ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td>
                            <div><?= payBadgeIntern((string) $r['payment_status']) ?></div>
                            <div class="intern-sub">Final: <?= h(number_format((float) $r['final_fee'], 2)) ?></div>
                            <div class="intern-sub">Paid: <?= h(number_format((float) $r['paid_amount'], 2)) ?></div>
                            <div class="intern-sub">Balance: <?= h(number_format((float) $r['balance_amount'], 2)) ?></div>
                        </td>

                        <td><?= h(ucwords(str_replace('_', ' ', (string) $r['internship_completion_status']))) ?></td>
                        <td><?= h($r['internship_certificate_status'] === 'given' ? 'Given' : 'Not Given') ?></td>
                        <td>
                            <?= h($r['internship_report_status'] === 'provided' ? 'Provided' : 'Not Provided') ?>
                            <?php if (!empty($r['internship_report_due_days'])): ?>
                                <div class="intern-sub"><?= (int) $r['internship_report_due_days'] ?> Days</div>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <div class="intern-action-wrap">
                                <button type="button" class="btn intern-view-btn viewInternBtn"
                                    data-name="<?= h($r['enquiry_snapshot_name']) ?>"
                                    data-regno="<?= h($r['registration_no']) ?>"
                                    data-phone="<?= h(visibleStudentContactValue($r['enquiry_snapshot_phone'] ?? '')) ?>"
                                    data-email="<?= h(visibleStudentContactValue($r['enquiry_snapshot_email'] ?? '')) ?>"
                                    data-program="<?= h($r['program_name']) ?>" data-batch="<?= h($r['batch_name']) ?>"
                                    data-joined="<?= h($r['joined_on']) ?>" data-notes="<?= h($r['notes']) ?>"
                                    data-days="<?= h($r['internship_days']) ?>"
                                    data-ibatch="<?= h($r['internship_batch']) ?>"
                                    data-start="<?= h($r['internship_start_date']) ?>"
                                    data-end="<?= h($r['internship_end_date']) ?>"
                                    data-fee="<?= h(number_format((float) $r['final_fee'], 2)) ?>"
                                    data-paid="<?= h(number_format((float) $r['paid_amount'], 2)) ?>"
                                    data-balance="<?= h(number_format((float) $r['balance_amount'], 2)) ?>"
                                    data-paystatus="<?= h($r['payment_status']) ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>

                                <button type="button" class="btn btn-primary manageInternBtn" data-id="<?= (int) $r['id'] ?>"
                                    data-name="<?= h($r['enquiry_snapshot_name']) ?>"
                                    data-start="<?= h($r['internship_start_date']) ?>"
                                    data-end="<?= h($r['internship_end_date']) ?>"
                                    data-completion="<?= h($r['internship_completion_status']) ?>"
                                    data-certificate="<?= h($r['internship_certificate_status']) ?>"
                                    data-report="<?= h($r['internship_report_status']) ?>"
                                    data-reportdays="<?= h($r['internship_report_due_days']) ?>"
                                    data-original-completion="<?= h($r['internship_completion_status']) ?>"
                                    data-original-report="<?= h($r['internship_report_status']) ?>"
                                    data-certificateissuedat="<?= h(!empty($r['internship_certificate_issued_at']) ? date('Y-m-d\TH:i', strtotime($r['internship_certificate_issued_at'])) : '') ?>"
                                    data-reportissuedat="<?= h(!empty($r['internship_report_issued_at']) ? date('Y-m-d\TH:i', strtotime($r['internship_report_issued_at'])) : '') ?>"
                                    data-paymentstatus="<?= h($r['payment_status']) ?>"
                                    >
                                    <i class="fas fa-pen"></i> Manage
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="internModalBackdrop"
        style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;"></div>

    <div id="internModal"
        style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:min(680px,92vw);background:#fff;border-radius:18px;box-shadow:0 30px 80px rgba(0,0,0,.25);z-index:9999;overflow:hidden;">
        <div
            style="padding:16px 18px;background:#fff4f8;border-bottom:1px solid rgba(233,30,99,.12);display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:18px;font-weight:800;color:#111827;">Manage Internship</div>
                <div id="internModalStudent" style="font-size:13px;color:#6b7280;margin-top:4px;"></div>
            </div>
            <button type="button" id="internModalClose" class="intern-modal-close" aria-label="Close">&times;</button>
        </div>

        <form method="POST" style="padding:18px;">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="save_internship" value="1">
            <input type="hidden" name="registration_id" id="modal_registration_id">

            <div class="intern-modal-grid">
                <div>
                    <label>Start Date</label>
                    <input type="date" name="internship_start_date" id="modal_start_date">
                </div>

                <div>
                    <label>End Date</label>
                    <input type="date" name="internship_end_date" id="modal_end_date">
                </div>

                <div>
                    <label>Completion</label>
                    <select name="internship_completion_status" id="modal_completion">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div>
                    <label>Certificate</label>
                    <select name="internship_certificate_status" id="modal_certificate">
                        <option value="not_given">Not Given</option>
                        <option value="given">Given</option>
                    </select>
                </div>

                <div>
    <label>Certificate Issued At</label>
    <input type="datetime-local" name="internship_certificate_issued_at" id="modal_certificate_issued_at">
</div>

                <div>
                    <label>Report</label>
                    <select name="internship_report_status" id="modal_report">
                        <option value="not_provided">Not Provided</option>
                        <option value="provided">Provided</option>
                    </select>
                </div>

                <div>
    <label>Report Issued At</label>
    <input type="datetime-local" name="internship_report_issued_at" id="modal_report_issued_at">
</div>

                <div>
                    <label>Report Days</label>
                    <input type="number" min="0" name="internship_report_due_days" id="modal_report_days"
                        placeholder="Days">
                </div>
            </div>

            <div style="margin-top:18px;display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" id="internModalCancel" class="btn" style="background:#f3f4f6;">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <div id="internViewBackdrop" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;"></div>

<div id="internViewModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:min(760px,94vw);background:#fff;border-radius:18px;box-shadow:0 30px 80px rgba(0,0,0,.25);z-index:9999;overflow:hidden;">
    <div style="padding:16px 18px;background:#fff4f8;border-bottom:1px solid rgba(233,30,99,.12);display:flex;justify-content:space-between;align-items:center;">
        <div>
            <div style="font-size:18px;font-weight:800;color:#111827;">Student Details</div>
            <div id="view_student_name" style="font-size:13px;color:#6b7280;margin-top:4px;"></div>
        </div>
        <button type="button" id="internViewClose" class="intern-modal-close" aria-label="Close">&times;</button>
    </div>

    <div style="padding:18px;">
        <div class="intern-view-grid">
            <div class="intern-view-box"><b>Registration No</b><span id="view_regno"></span></div>
            <div class="intern-view-box"><b>Phone</b><span id="view_phone"></span></div>
            <div class="intern-view-box"><b>Email</b><span id="view_email"></span></div>
            <div class="intern-view-box"><b>Joined On</b><span id="view_joined"></span></div>
            <div class="intern-view-box"><b>Program</b><span id="view_program"></span></div>
            <div class="intern-view-box"><b>Batch</b><span id="view_batch"></span></div>
            <div class="intern-view-box"><b>Internship Days</b><span id="view_days"></span></div>
            <div class="intern-view-box"><b>Internship Batch</b><span id="view_ibatch"></span></div>
            <div class="intern-view-box"><b>Start Date</b><span id="view_start"></span></div>
            <div class="intern-view-box"><b>End Date</b><span id="view_end"></span></div>
            <div class="intern-view-box"><b>Fee</b><span id="view_fee"></span></div>
            <div class="intern-view-box"><b>Paid</b><span id="view_paid"></span></div>
            <div class="intern-view-box"><b>Balance</b><span id="view_balance"></span></div>
            <div class="intern-view-box"><b>Payment Status</b><span id="view_paystatus"></span></div>
            <div class="intern-view-box intern-view-box-full"><b>Notes</b><span id="view_notes"></span></div>
        </div>
    </div>
</div>

    <div class="intern-pager">
        <a href="<?= $baseUrl ?>&p=1"><i class="fas fa-angle-double-left"></i></a>
        <a href="<?= $baseUrl ?>&p=<?= max(1, $page - 1) ?>"><i class="fas fa-angle-left"></i></a>
        <span style="font-weight:700;">Page <?= (int) $page ?> / <?= (int) $totalPages ?></span>
        <a href="<?= $baseUrl ?>&p=<?= min($totalPages, $page + 1) ?>"><i class="fas fa-angle-right"></i></a>
        <a href="<?= $baseUrl ?>&p=<?= (int) $totalPages ?>"><i class="fas fa-angle-double-right"></i></a>
    </div>
</div>

<script>
(function(){
    const modal = document.getElementById('internModal');
    const backdrop = document.getElementById('internModalBackdrop');
    const closeBtn = document.getElementById('internModalClose');
    const cancelBtn = document.getElementById('internModalCancel');

    const student = document.getElementById('internModalStudent');
    const regId = document.getElementById('modal_registration_id');
    const start = document.getElementById('modal_start_date');
    const end = document.getElementById('modal_end_date');
    const completion = document.getElementById('modal_completion');
    const certificate = document.getElementById('modal_certificate');
    const report = document.getElementById('modal_report');
    const reportDays = document.getElementById('modal_report_days');
    const certificateIssuedAt = document.getElementById('modal_certificate_issued_at');
    const reportIssuedAt = document.getElementById('modal_report_issued_at');
    let isSavedLocked = false;
    let isUnpaidLocked = false;

    const viewModal = document.getElementById('internViewModal');
    const viewBackdrop = document.getElementById('internViewBackdrop');
    const viewClose = document.getElementById('internViewClose');

    function syncInternModalState() {
    const completionValue = completion.value || 'pending';
    const reportValue = report.value || 'not_provided';
    const disableReportControls = completionValue === 'in_progress';
    const fullyLocked = isSavedLocked || isUnpaidLocked;

    start.disabled = fullyLocked;
    end.disabled = fullyLocked;
    completion.disabled = fullyLocked;
    certificate.disabled = fullyLocked || disableReportControls;
    report.disabled = fullyLocked || disableReportControls;
    certificateIssuedAt.disabled = fullyLocked || certificate.value !== 'given';
    reportIssuedAt.disabled = fullyLocked || disableReportControls || reportValue !== 'provided';
    reportDays.disabled = fullyLocked || disableReportControls || reportValue !== 'provided';

    if (!fullyLocked && certificate.value !== 'given') {
        certificateIssuedAt.value = '';
    }

    if (!fullyLocked && reportValue !== 'provided') {
        reportIssuedAt.value = '';
        reportDays.value = '';
    }
}
    function openModal(btn){
        regId.value = btn.dataset.id || '';
        student.textContent = btn.dataset.name || '';
        start.value = btn.dataset.start || '';
        end.value = btn.dataset.end || '';
        completion.value = btn.dataset.completion || 'pending';
        certificate.value = btn.dataset.certificate || 'not_given';
        report.value = btn.dataset.report || 'not_provided';
        reportDays.value = btn.dataset.reportdays || '';
        certificateIssuedAt.value = btn.dataset.certificateissuedat || '';
        reportIssuedAt.value = btn.dataset.reportissuedat || '';
        isSavedLocked = (btn.dataset.originalCompletion === 'completed' && btn.dataset.originalReport === 'provided');
        isUnpaidLocked = (btn.dataset.paymentstatus === 'unpaid');

        syncInternModalState();

        backdrop.style.display = 'block';
        modal.style.display = 'block';
    }

    function closeModal(){
        backdrop.style.display = 'none';
        modal.style.display = 'none';
    }

    function openViewModal(btn){
        document.getElementById('view_student_name').textContent = btn.dataset.name || '-';
        document.getElementById('view_regno').textContent = btn.dataset.regno || '-';
        document.getElementById('view_phone').textContent = btn.dataset.phone || '-';
        document.getElementById('view_email').textContent = btn.dataset.email || '-';
        document.getElementById('view_program').textContent = btn.dataset.program || '-';
        document.getElementById('view_batch').textContent = btn.dataset.batch || '-';
        document.getElementById('view_joined').textContent = btn.dataset.joined || '-';
        document.getElementById('view_notes').textContent = btn.dataset.notes || '-';
        document.getElementById('view_days').textContent = btn.dataset.days || '-';
        document.getElementById('view_ibatch').textContent = btn.dataset.ibatch || '-';
        document.getElementById('view_start').textContent = btn.dataset.start || '-';
        document.getElementById('view_end').textContent = btn.dataset.end || '-';
        document.getElementById('view_fee').textContent = btn.dataset.fee || '-';
        document.getElementById('view_paid').textContent = btn.dataset.paid || '-';
        document.getElementById('view_balance').textContent = btn.dataset.balance || '-';
        document.getElementById('view_paystatus').textContent = btn.dataset.paystatus || '-';

        viewBackdrop.style.display = 'block';
        viewModal.style.display = 'block';
    }

    function closeViewModal(){
        viewBackdrop.style.display = 'none';
        viewModal.style.display = 'none';
    }

    document.querySelectorAll('.manageInternBtn').forEach(btn => {
        btn.addEventListener('click', function(){
            openModal(this);
        });
    });

    document.querySelectorAll('.viewInternBtn').forEach(btn => {
        btn.addEventListener('click', function(){
            openViewModal(this);
        });
    });

    if (completion) {
    completion.addEventListener('change', syncInternModalState);
}

if (report) {
    report.addEventListener('change', syncInternModalState);
}

if (certificate) {
    certificate.addEventListener('change', syncInternModalState);
}

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    if (viewClose) viewClose.addEventListener('click', closeViewModal);
    if (viewBackdrop) viewBackdrop.addEventListener('click', closeViewModal);

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            closeModal();
            closeViewModal();
        }
    });
})();
</script>
