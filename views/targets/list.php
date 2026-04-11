<?php
// =====================================
// Targets - List
// Slug: targets/list
// File: views/targets/list.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

$success = '';
$error   = '';

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('targetInt')) {
    function targetInt($value)
    {
        return (int) trim((string)$value);
    }
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = trim((string)($_SESSION['role_name'] ?? ''));

$allowedRoles = ['Super Admin', 'HR'];

if (!$userId || !$branchId) {
    $error = 'Invalid session. Please login again.';
}

if (!$error && !in_array($roleName, $allowedRoles, true)) {
    $error = 'Access denied. Only HR and Super Admin can access target list.';
}

// --------------------------------------------------
// Delete
// --------------------------------------------------
if (!$error && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_target') {
    if (function_exists('verifyCsrfToken') && !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $deleteId = targetInt($_POST['delete_id'] ?? 0);

        if ($deleteId <= 0) {
            $error = 'Invalid target selected for deletion.';
        } else {
            try {
                $stmtCheck = $pdo->prepare("
                    SELECT id
                    FROM monthly_targets
                    WHERE id = :id
                      AND branch_id = :branch_id
                    LIMIT 1
                ");
                $stmtCheck->execute([
                    ':id' => $deleteId,
                    ':branch_id' => $branchId
                ]);
                $targetRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$targetRow) {
                    $error = 'Target record not found or access denied.';
                } else {
                    $stmtDelete = $pdo->prepare("
                        DELETE FROM monthly_targets
                        WHERE id = :id
                          AND branch_id = :branch_id
                        LIMIT 1
                    ");
                    $stmtDelete->execute([
                        ':id' => $deleteId,
                        ':branch_id' => $branchId
                    ]);

                    if (function_exists('setFlash')) {
                        setFlash('success', 'Target deleted successfully.');
                        echo '<script>window.location.href="index.php?page=targets/list";</script>';
exit;
                    } else {
                        $success = 'Target deleted successfully.';
                    }
                }
            } catch (Throwable $e) {
                $error = 'Delete failed. ' . $e->getMessage();
            }
        }
    }
}

// --------------------------------------------------
// Flash fallback
// --------------------------------------------------
if (!$success && function_exists('getFlash')) {
    $flashSuccess = getFlash('success');
    if ($flashSuccess) {
        $success = $flashSuccess;
    }
}

// --------------------------------------------------
// Filters
// --------------------------------------------------
$search       = trim((string)($_GET['search'] ?? ''));
$fYear        = targetInt($_GET['year'] ?? date('Y'));
$fMonth       = trim((string)($_GET['month'] ?? ''));
$fStatus      = trim((string)($_GET['status'] ?? ''));
$fUserId      = targetInt($_GET['user_id'] ?? 0);
$pageNo       = 1;
$perPage      = 5000;
$offset       = 0;

$monthNames = [
    1  => 'January',
    2  => 'February',
    3  => 'March',
    4  => 'April',
    5  => 'May',
    6  => 'June',
    7  => 'July',
    8  => 'August',
    9  => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December',
];

// --------------------------------------------------
// Eligible users for filter
// --------------------------------------------------
$targetUsers = [];
if (!$error) {
    try {
        $stmtUsers = $pdo->prepare("
            SELECT u.id, u.name, r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.branch_id = :branch_id
              AND u.status = 1
              AND r.status = 1
              AND r.is_target_applicable = 1
              AND LOWER(COALESCE(r.role_name, '')) IN ('front office', 'hr', 'marketing', 'corporate')
            ORDER BY u.name ASC
        ");
        $stmtUsers->execute([
            ':branch_id' => $branchId
        ]);
        $targetUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load users filter. ' . $e->getMessage();
    }
}

// --------------------------------------------------
// Build query
// --------------------------------------------------
$rows = [];
$totalRows = 0;

if (!$error) {
    try {
        $where = ["mt.branch_id = :branch_id"];
        $params = [':branch_id' => $branchId];

        if ($search !== '') {
            $where[] = "(u.name LIKE :search OR u.email LIKE :search OR r.role_name LIKE :search OR mt.remarks LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($fYear > 0) {
            $where[] = "mt.target_year = :target_year";
            $params[':target_year'] = $fYear;
        }

        if ($fMonth !== '' && ctype_digit((string)$fMonth) && (int)$fMonth >= 1 && (int)$fMonth <= 12) {
            $where[] = "mt.target_month = :target_month";
            $params[':target_month'] = (int)$fMonth;
        }

        if ($fStatus !== '' && in_array($fStatus, ['active', 'inactive'], true)) {
            $where[] = "mt.status = :status";
            $params[':status'] = $fStatus;
        }

        if ($fUserId > 0) {
            $where[] = "mt.user_id = :user_id";
            $params[':user_id'] = $fUserId;
        }

        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $sqlCount = "
            SELECT COUNT(*) AS total
            FROM monthly_targets mt
            INNER JOIN users u ON u.id = mt.user_id
            INNER JOIN roles r ON r.id = mt.role_id
            LEFT JOIN users ab ON ab.id = mt.assigned_by
            $whereSql
        ";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRows = (int)($stmtCount->fetchColumn() ?: 0);

        $sqlData = "
            SELECT
                mt.*,
                u.name AS user_name,
                u.email AS user_email,
                r.role_name,
                ab.name AS assigned_by_name
            FROM monthly_targets mt
            INNER JOIN users u ON u.id = mt.user_id
            INNER JOIN roles r ON r.id = mt.role_id
            LEFT JOIN users ab ON ab.id = mt.assigned_by
            $whereSql
            ORDER BY mt.target_year DESC, mt.target_month DESC, mt.id DESC
        ";

        $stmtData = $pdo->prepare($sqlData);

        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmtData->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmtData->bindValue($key, $value, PDO::PARAM_STR);
            }
        }

        $stmtData->execute();

        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $error = 'Unable to load target list. ' . $e->getMessage();
    }
}

$totalPages = 1;

// --------------------------------------------------
// Query string helper
// --------------------------------------------------
function buildTargetListUrl(array $overrides = [])
{
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $params['page'] = 'targets/list';
    return 'index.php?' . http_build_query($params);
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container-fluid py-3">
    <div class="targets-list-wrap">

        <div class="dashboard-header">
            <h2><i class="fas fa-bullseye" style="margin-right: 12px; color: #e91e63;"></i>Target Management</h2>
            <div class="header-stats">
                <span class="stat-item"><i class="fas fa-database"></i> Total: <?= $totalRows ?></span>
                <a href="index.php?page=targets/setup" class="btn targets-btn targets-btn-primary">
                    <i class="fas fa-plus"></i> New Target
                </a>
            </div>
        </div>

        <!-- Filter Targets Section -->
        <div class="targets-filter-card">
            <div class="targets-card-head">
                <i class="fas fa-filter me-2"></i>Filter Targets
            </div>
            <div class="targets-card-body">
                <form method="get" action="">
                    <input type="hidden" name="page" value="targets/list">

                    <div class="targets-filter-grid">
                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-search me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Search
                            </label>
                            <input type="text" name="search" class="form-control" value="<?= h($search) ?>" placeholder="Search by user, email, role, remarks">
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-calendar me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Year
                            </label>
                            <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= h($fYear) ?>">
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-calendar-alt me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Month
                            </label>
                            <select name="month" class="form-select">
                                <option value="">All Months</option>
                                <?php foreach ($monthNames as $num => $name): ?>
                                    <option value="<?= $num ?>" <?= ((string)$fMonth === (string)$num) ? 'selected' : '' ?>>
                                        <?= h($name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-circle me-1" style="color: #ec1670; font-size: 0.8rem;"></i>Status
                            </label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <option value="active" <?= ($fStatus === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($fStatus === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label fw-semibold">
                                <i class="fas fa-user me-1" style="color: #ec1670; font-size: 0.8rem;"></i>User
                            </label>
                            <select name="user_id" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($targetUsers as $tu): ?>
                                    <option value="<?= (int)$tu['id'] ?>" <?= ($fUserId === (int)$tu['id']) ? 'selected' : '' ?>>
                                        <?= h($tu['name']) ?> | <?= h($tu['role_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="targets-btn-icon" data-tooltip="Apply Filters" data-mobile-label="Apply">
                                <i class="fas fa-check"></i>
                            </button>
                            <a href="index.php?page=targets/list" class="targets-btn-icon reset" data-tooltip="Reset Filters" data-mobile-label="Reset">
                                <i class="fas fa-undo-alt"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Monthly Targets Section with extra spacing -->
        <div class="targets-table-card">
            <div class="targets-card-head">
                <div class="table-header-flex">
                    <div class="table-title">
                        <i class="fas fa-bullseye"></i> Monthly Targets
                    </div>
                    <div id="datatableControls"></div>
                </div>
            </div>
            <div class="targets-card-body">
                <div class="table-container">
                <div class="table-responsive">
                    <table id="targetsTable" class="table targets-table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Period</th>
                                <th>Target</th>
                                <th>Incentive</th>
                                <th>Status</th>
                                <th>Assigned By</th>
                                <th>Created</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="10" class="targets-empty">
                                        <i class="fas fa-bullseye fa-2x mb-2 d-block text-muted"></i>
                                        No target records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rows as $index => $row): ?>
                                    <?php
                                        $serial = $offset + $index + 1;
                                        $monthText = $monthNames[(int)$row['target_month']] ?? ('Month ' . (int)$row['target_month']);
                                        $statusClass = $row['status'] === 'active' ? 'targets-badge-active' : 'targets-badge-inactive';
                                    ?>
                                    <tr>
                                        <td><?= $serial ?></td>

                                        <td>
                                            <div class="targets-user-block">
                                                <div class="targets-user-name"><?= h($row['user_name']) ?></div>
                                                <div class="targets-user-meta"><?= h($row['user_email'] ?: '-') ?></div>
                                            </div>
                                        </td>

                                        <td><?= h($row['role_name']) ?></td>

                                        <td>
                                            <strong><?= h($monthText) ?></strong><br>
                                            <small class="text-muted"><?= h($row['target_year']) ?></small>
                                        </td>

                                        <td class="targets-amount"><?= inr_symbol() ?> <?= number_format((float)$row['target_amount'], 2) ?></td>

                                        <td><?= number_format((float)$row['incentive_percent'], 2) ?>%</td>

                                        <td>
                                            <span class="targets-badge <?= $statusClass ?>">
                                                <?= ucfirst(h($row['status'])) ?>
                                            </span>
                                        </td>

                                        <td><?= h($row['assigned_by_name'] ?: '-') ?></td>

                                        <td>
                                            <?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '-' ?>
                                        </td>

                                        <td class="text-center">
                                            <div class="targets-actions justify-content-center">
                                                <button
                                                    type="button"
                                                    class="targets-icon-btn view-btn js-view-target"
                                                    data-tooltip="View Details"
                                                    data-id="<?= (int)$row['id'] ?>"
                                                    data-user="<?= h($row['user_name']) ?>"
                                                    data-email="<?= h($row['user_email']) ?>"
                                                    data-role="<?= h($row['role_name']) ?>"
                                                    data-month="<?= h($monthText) ?>"
                                                    data-year="<?= h($row['target_year']) ?>"
                                                    data-target="<?= number_format((float)$row['target_amount'], 2) ?>"
                                                    data-incentive="<?= number_format((float)$row['incentive_percent'], 2) ?>"
                                                    data-status="<?= ucfirst(h($row['status'])) ?>"
                                                    data-assigned="<?= h($row['assigned_by_name'] ?: '-') ?>"
                                                    data-created="<?= !empty($row['created_at']) ? date('d M Y h:i A', strtotime($row['created_at'])) : '-' ?>"
                                                    data-updated="<?= !empty($row['updated_at']) ? date('d M Y h:i A', strtotime($row['updated_at'])) : '-' ?>"
                                                    data-remarks="<?= h($row['remarks'] ?: '-') ?>"
                                                >
                                                    <i class="fas fa-eye"></i>
                                                </button>

                                                <a
                                                    href="index.php?page=targets/setup&id=<?= (int)$row['id'] ?>"
                                                    class="targets-icon-btn edit-btn"
                                                    data-tooltip="Edit Target"
                                                >
                                                    <i class="fas fa-pen"></i>
                                                </a>

                                                <form method="post" action="" class="d-inline delete-target-form">
                                                    <?php if (function_exists('csrfField')): ?>
                                                        <?= csrfField(); ?>
                                                    <?php else: ?>
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                                                    <?php endif; ?>
                                                    <input type="hidden" name="action" value="delete_target">
                                                    <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                                                    <button type="submit" class="targets-icon-btn delete-btn" data-tooltip="Delete Target">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- View Modal -->
<div class="targets-modal-overlay" id="targetViewModal">
    <div class="targets-modal-box">
        <div class="targets-modal-head">
            <div class="targets-modal-headline">
                <span class="targets-modal-kicker"><i class="fas fa-chart-line"></i> Monthly Target Snapshot</span>
                <h5 class="targets-modal-title">Target Details</h5>
                <div class="targets-modal-subtitle">Premium overview of assignment, incentive, ownership, and timeline details.</div>
            </div>
            <div class="targets-modal-head-actions">
                <div class="targets-modal-status-chip" id="view_status_chip">Status</div>
                <button type="button" class="targets-close-btn" id="closeTargetModal" aria-label="Close target details">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="targets-modal-body">
            <div class="targets-modal-summary">
                <div class="targets-summary-card is-highlight">
                    <div class="targets-summary-label">Assigned To</div>
                    <div class="targets-summary-value" id="view_user_summary">-</div>
                    <div class="targets-summary-meta" id="view_role_summary">-</div>
                </div>
                <div class="targets-summary-card">
                    <div class="targets-summary-label">Target Amount</div>
                    <div class="targets-summary-value" id="view_target_summary">-</div>
                    <div class="targets-summary-meta">Monthly performance commitment</div>
                </div>
                <div class="targets-summary-stack">
                    <div class="targets-summary-card">
                        <div class="targets-summary-label">Period</div>
                        <div class="targets-summary-value" id="view_period_summary">-</div>
                    </div>
                    <div class="targets-summary-card">
                        <div class="targets-summary-label">Incentive</div>
                        <div class="targets-summary-value" id="view_incentive_summary">-</div>
                    </div>
                </div>
            </div>
            <div class="targets-view-grid">
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-user"></i> User</div>
                    <div class="targets-view-value" id="view_user">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="targets-view-value" id="view_email">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-id-badge"></i> Role</div>
                    <div class="targets-view-value" id="view_role">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-calendar-alt"></i> Period</div>
                    <div class="targets-view-value" id="view_period">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-wallet"></i> Target Amount</div>
                    <div class="targets-view-value" id="view_target">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-percent"></i> Incentive %</div>
                    <div class="targets-view-value" id="view_incentive">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-signal"></i> Status</div>
                    <div class="targets-view-value" id="view_status">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-user-check"></i> Assigned By</div>
                    <div class="targets-view-value" id="view_assigned">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-clock"></i> Created At</div>
                    <div class="targets-view-value" id="view_created">-</div>
                </div>
                <div class="targets-view-item">
                    <div class="targets-view-label"><i class="fas fa-history"></i> Updated At</div>
                    <div class="targets-view-value" id="view_updated">-</div>
                </div>
                <div class="targets-view-item targets-view-full remarks-card">
                    <div class="targets-view-label"><i class="fas fa-note-sticky"></i> Remarks</div>
                    <div class="targets-view-value targets-remarks-value" id="view_remarks">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($success): ?>
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: <?= json_encode($success, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            confirmButtonColor: '#e91e63'
        });
    }
    <?php endif; ?>

    <?php if ($error): ?>
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: 'error',
            title: 'Something went wrong',
            text: <?= json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
            confirmButtonColor: '#e91e63'
        });
    }
    <?php endif; ?>

    if (typeof crmDataTable === 'function') {
        crmDataTable('#targetsTable', {
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            ordering: true,
            order: [[3, 'desc']],
            searchPlaceholder: 'Search targets...',
            dom:
                "<'dt-top'lf>" +
                "rt" +
                "<'dt-bottom'ip>",
            columnDefs: [
                { targets: [9], orderable: false }
            ]
        });

        setTimeout(function () {
            var controls = document.querySelector('#targetsTable_wrapper .dt-top');
            var target = document.getElementById('datatableControls');
            if (controls && target) {
                target.appendChild(controls);
            }
        }, 100);
    }

    // View modal
    const modal = document.getElementById('targetViewModal');
    const closeBtn = document.getElementById('closeTargetModal');
    const viewButtons = document.querySelectorAll('.js-view-target');
    const deleteForms = document.querySelectorAll('.delete-target-form');

    function openModal() {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    viewButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const user = this.dataset.user || '-';
            const role = this.dataset.role || '-';
            const period = ((this.dataset.month || '-') + ' ' + (this.dataset.year || '')).trim();
            const targetAmount = 'Rs ' + (this.dataset.target || '0.00');
            const incentive = (this.dataset.incentive || '0.00') + '%';
            const status = this.dataset.status || '-';
            const statusChip = document.getElementById('view_status_chip');

            document.getElementById('view_user').textContent      = user;
            document.getElementById('view_email').textContent     = this.dataset.email || '-';
            document.getElementById('view_role').textContent      = role;
            document.getElementById('view_period').textContent    = period || '-';
            document.getElementById('view_target').textContent    = targetAmount;
            document.getElementById('view_incentive').textContent = incentive;
            document.getElementById('view_status').textContent    = status;
            document.getElementById('view_assigned').textContent  = this.dataset.assigned || '-';
            document.getElementById('view_created').textContent   = this.dataset.created || '-';
            document.getElementById('view_updated').textContent   = this.dataset.updated || '-';
            document.getElementById('view_remarks').textContent   = this.dataset.remarks || '-';
            document.getElementById('view_user_summary').textContent = user;
            document.getElementById('view_role_summary').textContent = role;
            document.getElementById('view_target_summary').textContent = targetAmount;
            document.getElementById('view_period_summary').textContent = period || '-';
            document.getElementById('view_incentive_summary').textContent = incentive;

            if (statusChip) {
                statusChip.textContent = status;
                statusChip.classList.remove('is-active', 'is-inactive');
                if (String(status).toLowerCase() === 'active') {
                    statusChip.classList.add('is-active');
                } else if (String(status).toLowerCase() === 'inactive') {
                    statusChip.classList.add('is-inactive');
                }
            }
            openModal();
        });
    });

    deleteForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (typeof Swal === 'undefined') {
                form.submit();
                return;
            }

            Swal.fire({
                title: 'Delete target?',
                text: 'This target record will be permanently removed.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e91e63',
                cancelButtonColor: '#6c757d',
                reverseButtons: true
            }).then(function (result) {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
});
</script>



