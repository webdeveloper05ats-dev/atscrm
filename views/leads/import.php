<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/lead.css">
<?php
// =====================================
// Leads - Excel Upload + Bulk Assign
// Slug: leads/import
// File: views/leads/import.php
// =====================================

if (!defined('APP_NAME')) {
    die("Unauthorized access.");
}

if (function_exists('requireView')) {
    requireView('leads/import');
}

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!function_exists('h')) {
    function h($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('toNull')) {
    function toNull($v)
    {
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }
}
if (!function_exists('sheetPick')) {
    function sheetPick(array $row, array $map, array $keys): ?string
    {
        foreach ($keys as $k) {
            $lk = strtolower(trim((string) $k));
            if (isset($map[$lk])) {
                $idx = $map[$lk];
                return isset($row[$idx]) ? trim((string) $row[$idx]) : null;
            }
        }
        return null;
    }
}

$success = '';
$error = '';

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

/* Staff list for assignment */
$staff = [];
try {
    if ($canAllBranches) {
        $st = $pdo->prepare("
            SELECT u.id, u.name, r.role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.status = 1
            ORDER BY r.role_name ASC, u.name ASC
        ");
        $st->execute();
    } else {
        $st = $pdo->prepare("
            SELECT u.id, u.name, r.role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.status = 1
              AND (u.branch_id = ? OR u.branch_id IS NULL OR u.branch_id = 0)
            ORDER BY r.role_name ASC, u.name ASC
        ");
        $st->execute([$branchId]);
    }
    $staff = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $staff = [];
}

/* Download sample Excel template */
if (isset($_GET['download']) && $_GET['download'] === 'template') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'name',
        'phone',
        'email',
        'source',
        'course_interest',
        'company_college_name',
        'department',
        'lead_year',
        'remarks'
    ];

    $sample = [
        'John Doe',
        '9876543210',
        'john@example.com',
        'Website',
        'Full Stack',
        'ABC College',
        'CSE',
        '2026',
        'Interested in weekend batch'
    ];

    $sheet->fromArray($headers, null, 'A1');
    $sheet->fromArray($sample, null, 'A2');

    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $fileName = 'lead_import_template.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/* Upload Excel */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_leads'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request.";
    } else {
        $assignTo = (int) ($_POST['assign_to'] ?? 0);
        $file = $_FILES['lead_file'] ?? null;
        $maxUploadSize = 10 * 1024 * 1024; // 10 MB

        if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $error = "Please choose an Excel/CSV file.";
        } elseif ((int)($file['size'] ?? 0) > $maxUploadSize) {
            $error = "File is too large. Maximum allowed size is 10 MB.";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
                $error = "Please upload a valid file (.xlsx, .xls or .csv).";
            } else {
                $uploadDir = __DIR__ . '/../../uploads/lead_imports';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                $savedName = 'leads_' . date('Ymd_His') . '_' . rand(1000, 9999) . '.' . $ext;
                $savedPath = $uploadDir . '/' . $savedName;

                if (!move_uploaded_file($file['tmp_name'], $savedPath)) {
                    $error = "File upload failed.";
                } else {
                    $batchId = 0;
                    $totalRows = 0;
                    $successRows = 0;
                    $failedRows = 0;
                    $rowErrors = [];

                    try {
                        $st = $pdo->prepare("
                            INSERT INTO lead_import_batches
                            (branch_id, created_by, file_name, total_rows, success_rows, failed_rows, status, created_at)
                            VALUES (?, ?, ?, 0, 0, 0, 'completed', NOW())
                        ");
                        $st->execute([$branchId, $userId, $savedName]);
                        $batchId = (int) $pdo->lastInsertId();

                        $spreadsheet = IOFactory::load($savedPath);
                        $sheet = $spreadsheet->getActiveSheet();
                        $rowsData = $sheet->toArray(null, true, true, false);

                        if (empty($rowsData) || empty($rowsData[0])) {
                            throw new Exception("Excel header missing.");
                        }

                        $header = $rowsData[0];
                        $map = [];
                        foreach ($header as $i => $col) {
                            $map[strtolower(trim((string) $col))] = $i;
                        }

                        for ($ri = 1; $ri < count($rowsData); $ri++) {
                            $row = $rowsData[$ri];

                            $empty = true;
                            foreach ($row as $cell) {
                                if (trim((string) $cell) !== '') {
                                    $empty = false;
                                    break;
                                }
                            }
                            if ($empty) {
                                continue;
                            }

                            $totalRows++;

                            $name = toNull(sheetPick($row, $map, ['name', 'lead_name', 'student_name']));
                            $phone = toNull(sheetPick($row, $map, ['phone', 'mobile', 'contact']));
                            $email = toNull(sheetPick($row, $map, ['email', 'mail']));
                            $source = toNull(sheetPick($row, $map, ['source', 'lead_source']));
                            $course = toNull(sheetPick($row, $map, ['course_interest', 'interest', 'course']));
                            $companyCollege = toNull(sheetPick($row, $map, ['company_college_name', 'company', 'college', 'college_name']));
                            $department = toNull(sheetPick($row, $map, ['department', 'dept']));
                            $leadYear = toNull(sheetPick($row, $map, ['lead_year', 'year']));
                            $remarks = toNull(sheetPick($row, $map, ['remarks', 'note', 'notes']));

                            if ($name === null) {
                                $failedRows++;
                                $rowErrors[] = "Row " . ($ri + 1) . ": Name is required.";
                                continue;
                            }

                            try {
                                $ins = $pdo->prepare("
                                    INSERT INTO leads
                                    (
                                        branch_id, name, phone, email, source,
                                        course_interest, company_college_name, department, lead_year,
                                        status, assigned_to, import_batch_id, remarks,
                                        created_by, ip_address, user_agent, created_at
                                    )
                                    VALUES
                                    (
                                        :branch, :name, :phone, :email, :source,
                                        :course, :company_college_name, :department, :lead_year,
                                        'new', :assigned_to, :import_batch_id, :remarks,
                                        :created_by, :ip, :ua, NOW()
                                    )
                                ");
                                $ins->execute([
                                    ':branch' => $branchId,
                                    ':name' => $name,
                                    ':phone' => $phone,
                                    ':email' => $email,
                                    ':source' => $source,
                                    ':course' => $course,
                                    ':company_college_name' => $companyCollege,
                                    ':department' => $department,
                                    ':lead_year' => $leadYear,
                                    ':assigned_to' => $assignTo > 0 ? $assignTo : null,
                                    ':import_batch_id' => $batchId,
                                    ':remarks' => $remarks,
                                    ':created_by' => $userId,
                                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                                    ':ua' => $_SERVER['HTTP_USER_AGENT'] ?? null
                                ]);
                                $successRows++;
                            } catch (Exception $e) {
                                $failedRows++;
                                $rowErrors[] = "Row " . ($ri + 1) . ": " . $e->getMessage();
                            }
                        }

                        $status = 'completed';
                        if ($successRows === 0 && $failedRows > 0) {
                            $status = 'failed';
                        } elseif ($successRows > 0 && $failedRows > 0) {
                            $status = 'partial';
                        }

                        $upd = $pdo->prepare("
                            UPDATE lead_import_batches
                            SET total_rows=?, success_rows=?, failed_rows=?, status=?
                            WHERE id=?
                        ");
                        $upd->execute([$totalRows, $successRows, $failedRows, $status, $batchId]);

                        if ($successRows > 0) {
                            $success = "Import finished. Success: {$successRows}, Failed: {$failedRows}. Batch #{$batchId}";
                        } else {
                            $error = "Import failed. No rows inserted. Batch #{$batchId}";
                        }

                        if (!empty($rowErrors)) {
                            $_SESSION['lead_import_errors'] = array_slice($rowErrors, 0, 10);
                        } else {
                            unset($_SESSION['lead_import_errors']);
                        }

                        $_GET['batch_id'] = $batchId;

                    } catch (Exception $e) {
                        $error = "Import failed. " . $e->getMessage();
                    }
                }
            }
        }
    }
}

/* Bulk assign selected imported leads */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_selected'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request.";
    } else {
        $assignTo = (int) ($_POST['assign_to_bulk'] ?? 0);
        $batchId = (int) ($_POST['batch_id'] ?? 0);
        $leadIds = $_POST['lead_ids'] ?? [];

        if ($assignTo <= 0 || $batchId <= 0 || empty($leadIds) || !is_array($leadIds)) {
            $error = "Select leads and staff, then try again.";
        } else {
            $cleanIds = [];
            foreach ($leadIds as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $cleanIds[] = $id;
                }
            }
            $cleanIds = array_values(array_unique($cleanIds));

            if (empty($cleanIds)) {
                $error = "No valid leads selected.";
            } else {
                try {
                    $ph = implode(',', array_fill(0, count($cleanIds), '?'));
                    $params = $cleanIds;
                    $params[] = $batchId;

                    $scopeSql = "SELECT id FROM leads WHERE id IN ($ph) AND import_batch_id = ?";
                    if (!$canAllBranches) {
                        $scopeSql .= " AND branch_id = ?";
                        $params[] = $branchId;
                    }

                    $st = $pdo->prepare($scopeSql);
                    $st->execute($params);
                    $validIds = array_map('intval', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'id'));

                    if (empty($validIds)) {
                        throw new Exception("Selected leads are not valid for this batch.");
                    }

                    $ph2 = implode(',', array_fill(0, count($validIds), '?'));
                    $updParams = [$assignTo];
                    foreach ($validIds as $id) {
                        $updParams[] = $id;
                    }

                    $upd = $pdo->prepare("UPDATE leads SET assigned_to = ? WHERE id IN ($ph2)");
                    $upd->execute($updParams);

                    $success = count($validIds) . " leads assigned successfully.";
                } catch (Exception $e) {
                    $error = "Bulk assign failed. " . $e->getMessage();
                }
            }
        }

        $_GET['batch_id'] = $batchId;
    }
}

/* Delete import batch */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_batch'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($token)) {
        $error = "Invalid request.";
    } else {
        $batchIdToDelete = (int) ($_POST['batch_id'] ?? 0);

        if ($batchIdToDelete <= 0) {
            $error = "Invalid batch selected.";
        } else {
            try {
                $params = [$batchIdToDelete];
                $sql = "SELECT id, file_name FROM lead_import_batches WHERE id = ?";
                if (!$canAllBranches) {
                    $sql .= " AND branch_id = ?";
                    $params[] = $branchId;
                }
                $sql .= " LIMIT 1";

                $st = $pdo->prepare($sql);
                $st->execute($params);
                $batchRow = $st->fetch(PDO::FETCH_ASSOC);

                if (!$batchRow) {
                    throw new Exception("Batch not found or access denied.");
                }

                $pdo->beginTransaction();

                $params = [$batchIdToDelete];
                $sql = "DELETE FROM leads WHERE import_batch_id = ?";
                if (!$canAllBranches) {
                    $sql .= " AND branch_id = ?";
                    $params[] = $branchId;
                }
                $st = $pdo->prepare($sql);
                $st->execute($params);

                $params = [$batchIdToDelete];
                $sql = "DELETE FROM lead_import_batches WHERE id = ?";
                if (!$canAllBranches) {
                    $sql .= " AND branch_id = ?";
                    $params[] = $branchId;
                }
                $sql .= " LIMIT 1";

                $st = $pdo->prepare($sql);
                $st->execute($params);

                $pdo->commit();

                $filePath = __DIR__ . '/../../uploads/lead_imports/' . $batchRow['file_name'];
                if (!empty($batchRow['file_name']) && is_file($filePath)) {
                    @unlink($filePath);
                }

                if ($batchId === $batchIdToDelete) {
                    $batchId = 0;
                    $batchLeads = [];
                }

                $success = "Batch deleted successfully.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Failed to delete batch. " . $e->getMessage();
            }
        }
    }
}


/* Recent import batches */
$batches = [];
try {
    if ($canAllBranches) {
        $st = $pdo->prepare("
            SELECT b.*, u.name AS created_by_name
            FROM lead_import_batches b
            LEFT JOIN users u ON u.id = b.created_by
            ORDER BY b.id DESC
            LIMIT 20
        ");
        $st->execute();
    } else {
        $st = $pdo->prepare("
            SELECT b.*, u.name AS created_by_name
            FROM lead_import_batches b
            LEFT JOIN users u ON u.id = b.created_by
            WHERE b.branch_id = ?
            ORDER BY b.id DESC
            LIMIT 20
        ");
        $st->execute([$branchId]);
    }
    $batches = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $batches = [];
}

/* Imported leads in selected batch */
$batchId = (int) ($_GET['batch_id'] ?? 0);
$batchLeads = [];
$batchTotal = 0;
$batchAssigned = 0;
$batchUnassigned = 0;
$batchConverted = 0;
$batchNotConverted = 0;

if ($batchId > 0) {
    try {
        $params = [$batchId];
        $sql = "
            SELECT l.*, u.name AS assigned_name
            FROM leads l
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE l.import_batch_id = ?
        ";
        if (!$canAllBranches) {
            $sql .= " AND l.branch_id = ?";
            $params[] = $branchId;
        }
        $sql .= " ORDER BY CASE WHEN l.assigned_to IS NULL OR l.assigned_to = 0 THEN 0 ELSE 1 END ASC, l.id DESC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $batchLeads = $st->fetchAll(PDO::FETCH_ASSOC);

        $batchTotal = count($batchLeads);
        foreach ($batchLeads as $row) {
            $isAssigned = !empty($row['assigned_to']);
            if ($isAssigned) {
                $batchAssigned++;
            } else {
                $batchUnassigned++;
            }

            if (($row['status'] ?? '') === 'converted') {
                $batchConverted++;
            } else {
                $batchNotConverted++;
            }
        }
    } catch (Exception $e) {
        $batchLeads = [];
    }
}
?>

<div class="leads-dashboard lead-import-page">
    <div class="dashboard-header">
        <h2><i class="fas fa-file-excel" style="margin-right: 12px; color: #e91e63;"></i>Lead Excel Upload</h2>
        <div class="header-stats">
            <span class="stat-item"><i class="fas fa-layer-group"></i> Batches: <?= (int) count($batches) ?></span>
        </div>
    </div>

<?php if ($success): ?>
    <script>
        if (window.Swal && Swal.fire) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= addslashes($success) ?>',
                confirmButtonColor: '#e91e63'
            });
        } else {
            alert('<?= addslashes($success) ?>');
        }
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <script>
        if (window.Swal && Swal.fire) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($error) ?>',
                confirmButtonColor: '#e91e63'
            });
        } else {
            alert('<?= addslashes($error) ?>');
        }
    </script>
<?php endif; ?>

<div class="card">
    <div class="card-header"><i class="fas fa-upload" style="margin-right:8px;"></i>Upload Excel File</div>
    <form method="POST" enctype="multipart/form-data" class="filter-form" id="uploadLeadsForm" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
        <input type="hidden" name="upload_leads" value="1">

        <div class="filter-grid import-grid">
            <div class="filter-item">
                <label><i class="fas fa-file-excel"></i> Excel File</label>
                <input type="file" id="lead_file_input" class="import-file-input" name="lead_file" accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv,application/csv" required>
                <label for="lead_file_input" class="import-file-box" id="import_file_box">
                    <span class="import-file-icon"><i class="fas fa-file-upload"></i></span>
                    <span class="import-file-meta">
                        <span class="import-file-title">Choose or Drop Import File</span>
                        <span class="import-file-name" id="import_file_name">No file selected</span>
                    </span>
                </label>
                <small class="lead-import-note">Supports `.xlsx`, `.xls`, `.csv` | Max file size: 10 MB | Drag & drop enabled</small>
            </div>

            <div class="filter-item">
                <label><i class="fas fa-user-check"></i> Assign To (optional)</label>
                <select name="assign_to">
                    <option value="">Do not assign now</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int) $s['id'] ?>">
                            <?= h($s['name']) ?> (<?= h($s['role_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn-filter" id="uploadLeadsBtn" type="submit">
                    <i class="fas fa-upload"></i> Upload Leads
                </button>

                <a href="index.php?page=leads/import&download=template" class="btn-reset">
                    <i class="fas fa-download"></i> Download Template
                </a>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($_SESSION['lead_import_errors'])): ?>
    <div class="card" style="margin-top:16px;">
        <div class="card-header"><i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>Import Warnings</div>
        <div class="import-warn-wrap">
            <?php foreach ($_SESSION['lead_import_errors'] as $e): ?>
                <div class="import-warn-item"><?= h($e) ?></div>
            <?php endforeach;
            unset($_SESSION['lead_import_errors']); ?>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="margin-top:16px;">
    <div class="card-header">
        <div class="table-header-flex">
            <div class="table-title"><i class="fas fa-history"></i> Recent Import Batches</div>
            <div id="importTableControls"></div>
        </div>
    </div>
    <div class="table-container">
        <table class="leads-table import-table" id="importBatchesTable">
            <thead>
                <tr>
                    <th>Batch</th>
                    <th>File</th>
                    <th>Total</th>
                    <th>Success</th>
                    <th>Failed</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$batches): ?>
                    <tr>
                        <td colspan="9" style="text-align:center;">No batches found.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($batches as $b): ?>
                    <tr>
                        <td>#<?= (int) $b['id'] ?></td>
                        <td><?= h($b['file_name']) ?></td>
                        <td><?= (int) $b['total_rows'] ?></td>
                        <td style="color:#2e7d32;font-weight:700;"><?= (int) $b['success_rows'] ?></td>
                        <td style="color:#e53935;font-weight:700;"><?= (int) $b['failed_rows'] ?></td>
                        <td><?= h(ucfirst($b['status'])) ?></td>
                        <td><?= h($b['created_by_name'] ?? '-') ?></td>
                        <td><?= h($b['created_at']) ?></td>
                        <td class="action-col">
                            <a class="action-btn edit" href="index.php?page=leads/import&batch_id=<?= (int) $b['id'] ?>"
                                data-tooltip="View Batch">
                                <i class="fas fa-eye"></i>
                            </a>

                            <form method="POST" class="deleteBatchForm" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                                <input type="hidden" name="delete_batch" value="1">
                                <input type="hidden" name="batch_id" value="<?= (int) $b['id'] ?>">
                                <button type="submit" class="action-btn delete" data-tooltip="Delete Batch">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($batchId > 0): ?>
    <div class="card" style="margin-top:16px;">
        <div class="card-header">
            <div class="table-header-flex">
                <div class="table-title"><i class="fas fa-list"></i> Batch #<?= (int) $batchId ?> Leads</div>
                <div id="batchLeadsTableControls"></div>
            </div>
        </div>

        <form method="POST" class="filter-form" id="bulkAssignForm">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="assign_selected" value="1">
            <input type="hidden" name="batch_id" value="<?= (int) $batchId ?>">

            <div class="batch-topbar">
                <div class="batch-summary">
                    <span class="batch-chip">Total: <?= (int)$batchTotal ?></span>
                    <span class="batch-chip assigned">Assigned: <?= (int)$batchAssigned ?></span>
                    <span class="batch-chip unassigned">Unassigned: <?= (int)$batchUnassigned ?></span>
                    <span class="batch-chip converted">Converted: <?= (int)$batchConverted ?></span>
                    <span class="batch-chip not-converted">Not Converted: <?= (int)$batchNotConverted ?></span>
                </div>

                <div class="batch-actions-wrap">
                    <div class="assign-filter-wrap">
                        <label>Show</label>
                        <select id="assign_state_filter">
                            <option value="all">All</option>
                            <option value="assigned">Assigned</option>
                            <option value="unassigned">Unassigned</option>
                        </select>
                    </div>

                    <div class="bulk-assign-wrap">
                    <div>
                        <label>Assign selected to</label>
                        <select name="assign_to_bulk" required>
                            <option value="">Select Staff</option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?= (int) $s['id'] ?>">
                                    <?= h($s['name']) ?> (<?= h($s['role_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="align-self:flex-end;">
                        <button class="btn-filter" type="submit">
                            <i class="fas fa-user-check"></i> Assign Selected
                        </button>
                    </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="leads-table import-table" id="batchLeadsTable">
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="chkAll"></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Interest</th>
                            <th>Org / Dept / Year</th>
                            <th>Assigned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$batchLeads): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;">No leads in this batch.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($batchLeads as $r): ?>
                            <tr>
                                <td><input type="checkbox" class="leadChk" name="lead_ids[]" value="<?= (int) $r['id'] ?>"></td>
                                <td><?= (int) $r['id'] ?></td>
                                <td><?= h($r['name']) ?></td>
                                <td>
                                    <div><?= h($r['phone']) ?></div>
                                    <div class="lead-sub"><?= h($r['email']) ?></div>
                                </td>
                                <td><?= h($r['course_interest']) ?></td>
                                <td>
                                    <div><?= h($r['company_college_name']) ?></div>
                                    <div class="lead-sub">
                                        <?= h($r['department']) ?>
                                        <?= !empty($r['lead_year']) ? ' | ' . h($r['lead_year']) : '' ?>
                                    </div>
                                </td>
                                <td><?= h($r['assigned_name'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
<?php endif; ?>

</div>

<style>
    .lead-import-page {
        max-width: 100%;
        overflow-x: hidden;
    }

    .import-grid {
        display: grid !important;
        grid-template-columns: minmax(240px, 1.2fr) minmax(220px, 1fr) auto;
        gap: 12px;
        align-items: end;
        width: 100%;
    }

    .import-grid .filter-item {
        min-width: 0;
    }

    .import-grid .filter-actions {
        margin-left: 0;
        align-self: end;
        flex-wrap: nowrap;
    }

    .import-file-input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .import-file-box {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        min-height: 50px;
        padding: 10px 12px;
        border: 1px dashed #f1b5cc;
        border-radius: 10px;
        background: #fff7fb;
        cursor: pointer;
        transition: all .2s ease;
    }

    .import-file-box:hover {
        border-color: #e91e63;
        background: #fff1f7;
    }

    .import-file-box.dragover {
        border-color: #e91e63;
        background: #ffeaf3;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .12);
    }

    .import-file-input:focus + .import-file-box {
        border-color: #e91e63;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .12);
    }

    .import-file-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #ff4d8d, #e91e63);
        color: #fff;
        flex: 0 0 auto;
    }

    .import-file-meta {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .import-file-title {
        color: #be185d;
        font-weight: 700;
        font-size: 13px;
        line-height: 1.2;
    }

    .import-file-name {
        color: #6b7280;
        font-size: 12px;
        line-height: 1.2;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }

    #uploadLeadsBtn[disabled] {
        opacity: .7;
        cursor: not-allowed;
        pointer-events: none;
    }

    .lead-import-note {
        display: block;
        margin-top: 6px;
        color: #6b7280;
        font-size: 12px;
    }

    .import-warn-wrap {
        padding: 14px 16px;
        color: #b91c1c;
    }

    .import-warn-item {
        margin-bottom: 6px;
        font-size: 13px;
    }

    .action-col {
        white-space: nowrap;
    }

    .batch-topbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding-bottom: 8px;
    }

    .batch-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        flex: 1 1 auto;
    }

    .bulk-assign-wrap {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: nowrap;
        justify-content: flex-end;
        margin-left: auto;
    }

    .batch-actions-wrap {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        margin-left: auto;
    }

    .assign-filter-wrap label {
        display: block;
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .3px;
        color: #6b7280;
        margin-bottom: 6px;
    }

    .assign-filter-wrap select {
        min-width: 140px;
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
    }

    .assign-filter-wrap select:focus {
        outline: none;
        border-color: #e91e63;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .12);
    }

    .batch-chip {
        display: inline-flex;
        align-items: center;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        color: #334155;
    }

    .batch-chip.assigned {
        background: #ecfdf5;
        border-color: #bbf7d0;
        color: #166534;
    }

    .batch-chip.unassigned {
        background: #fff7ed;
        border-color: #fed7aa;
        color: #9a3412;
    }

    .batch-chip.converted {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    .batch-chip.not-converted {
        background: #fdf2f8;
        border-color: #fbcfe8;
        color: #9d174d;
    }

    .bulk-assign-wrap label {
        display: block;
        font-size: .75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .3px;
        color: #6b7280;
        margin-bottom: 6px;
    }

    .bulk-assign-wrap select,
    .filter-item input:not(.import-file-input),
    .filter-item select {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #fff;
    }

    .bulk-assign-wrap select:focus,
    .filter-item input:not(.import-file-input):focus,
    .filter-item select:focus {
        outline: none;
        border-color: #e91e63;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .12);
    }

    .import-table th,
    .import-table td {
        white-space: normal !important;
        word-break: break-word;
    }

    .import-table .action-col {
        text-align: right;
    }

    #importTableControls .dt-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    #batchLeadsTableControls .dt-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    @media(max-width:768px) {
        .batch-topbar {
            align-items: stretch;
        }

        .batch-summary {
            width: 100%;
        }

        .batch-actions-wrap {
            width: 100%;
            flex-wrap: wrap;
            margin-left: 0;
        }

        .bulk-assign-wrap {
            width: 100%;
            flex-wrap: wrap;
            justify-content: flex-start;
            margin-left: 0;
        }

        .import-grid {
            grid-template-columns: 1fr;
            align-items: stretch;
        }

        .filter-actions {
            width: 100%;
            margin-left: 0;
            flex-wrap: wrap;
        }

        .filter-actions .btn-filter,
        .filter-actions .btn-reset {
            flex: 1;
            justify-content: center;
        }
    }

    @media(max-width:1100px) {
        .import-grid {
            grid-template-columns: 1fr 1fr;
            align-items: end;
        }

        .import-grid .filter-actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
    }
</style>

<script>
    const chkAll = document.getElementById('chkAll');
    if (chkAll) {
        chkAll.addEventListener('change', function () {
            document.querySelectorAll('.leadChk').forEach(cb => cb.checked = chkAll.checked);
        });
    }

    function showAlert(options) {
        const icon = options && options.icon ? options.icon : 'info';
        const title = options && options.title ? options.title : '';
        const text = options && options.text ? options.text : '';

        if (window.Swal && Swal.fire) {
            return Swal.fire({
                icon: icon,
                title: title,
                text: text,
                confirmButtonColor: '#e91e63'
            });
        }

        alert((title ? title + "\n" : "") + text);
        return Promise.resolve({ isConfirmed: true });
    }

    document.addEventListener("DOMContentLoaded", function () {
        const leadFileInput = document.getElementById('lead_file_input');
        const leadFileName = document.getElementById('import_file_name');
        const importFileBox = document.getElementById('import_file_box');
        const assignStateFilter = document.getElementById('assign_state_filter');
        const uploadLeadsForm = document.getElementById('uploadLeadsForm');
        const uploadLeadsBtn = document.getElementById('uploadLeadsBtn');
        const bulkAssignForm = document.getElementById('bulkAssignForm');
        const MAX_UPLOAD_SIZE = 10 * 1024 * 1024; // 10 MB
        const formatMB = function (bytes) {
            return (bytes / (1024 * 1024)).toFixed(1) + " MB";
        };

        const setSelectedFile = function (file) {
            if (!leadFileName) return;
            leadFileName.textContent = file && file.name ? file.name : 'No file selected';
        };

        if (leadFileInput && leadFileName) {
            leadFileInput.addEventListener('change', function () {
                const file = this.files && this.files.length ? this.files[0].name : '';
                leadFileName.textContent = file || 'No file selected';
            });
        }

        if (importFileBox && leadFileInput) {
            ['dragenter', 'dragover'].forEach(function (eventName) {
                importFileBox.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    importFileBox.classList.add('dragover');
                });
            });

            ['dragleave', 'drop'].forEach(function (eventName) {
                importFileBox.addEventListener(eventName, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    importFileBox.classList.remove('dragover');
                });
            });

            importFileBox.addEventListener('drop', function (e) {
                const files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
                if (!files || !files.length) return;

                try {
                    leadFileInput.files = files;
                    setSelectedFile(files[0]);
                } catch (err) {
                    setSelectedFile(files[0]);
                }
            });
        }

        if (typeof crmDataTable === "function" && document.querySelector('#importBatchesTable')) {
            crmDataTable('#importBatchesTable', {
                pageLength: 10,
                lengthMenu: [5, 10, 20, 50],
                ordering: true,
                order: [[0, 'desc']],
                searchPlaceholder: "Search batches...",
                dom: "<'dt-top'lfB>rt<'dt-bottom'ip>",
                autoWidth: false,
                scrollX: false,
                scrollY: false
            });

            setTimeout(function () {
                const controls = document.querySelector('#importBatchesTable_wrapper .dt-top');
                const target = document.getElementById('importTableControls');
                if (controls && target) {
                    target.appendChild(controls);
                }
            }, 100);
        }

        if (typeof crmDataTable === "function" && document.querySelector('#batchLeadsTable')) {
            let selectedAssignState = 'all';
            if (assignStateFilter) {
                selectedAssignState = assignStateFilter.value || 'all';
            }

            if (window.jQuery && jQuery.fn && jQuery.fn.dataTable) {
                jQuery.fn.dataTable.ext.search.push(function (settings, searchData) {
                    if (!settings || !settings.nTable || settings.nTable.id !== 'batchLeadsTable') {
                        return true;
                    }

                    if (selectedAssignState === 'all') {
                        return true;
                    }

                    const assignedCell = ((searchData && searchData[6]) || '').trim();
                    const isUnassigned = assignedCell === '-' || assignedCell === '';

                    if (selectedAssignState === 'unassigned') {
                        return isUnassigned;
                    }
                    if (selectedAssignState === 'assigned') {
                        return !isUnassigned;
                    }
                    return true;
                });
            }

            crmDataTable('#batchLeadsTable', {
                pageLength: 10,
                lengthMenu: [5, 10, 20, 50],
                ordering: true,
                order: [[1, 'desc']],
                searchPlaceholder: "Search imported leads...",
                dom: "<'dt-top'lfB>rt<'dt-bottom'ip>",
                autoWidth: false,
                scrollX: false,
                scrollY: false
            });

            setTimeout(function () {
                const controls = document.querySelector('#batchLeadsTable_wrapper .dt-top');
                const target = document.getElementById('batchLeadsTableControls');
                if (controls && target) {
                    target.appendChild(controls);
                }
            }, 100);

            if (assignStateFilter && window.jQuery) {
                assignStateFilter.addEventListener('change', function () {
                    selectedAssignState = this.value || 'all';
                    const dt = jQuery('#batchLeadsTable').DataTable();
                    dt.draw();
                });
            }
        }

        if (uploadLeadsForm && leadFileInput) {
            uploadLeadsForm.addEventListener('submit', function (e) {
                const hasFile = !!(leadFileInput.files && leadFileInput.files.length);
                if (!hasFile) {
                    e.preventDefault();
                    showAlert({
                        icon: 'warning',
                        title: 'Validation Error',
                        text: 'Please choose an Excel/CSV file to upload.'
                    });
                    return;
                }

                const fileName = leadFileInput.files[0].name || '';
                const ext = fileName.split('.').pop().toLowerCase();
                if (['xlsx', 'xls', 'csv'].indexOf(ext) === -1) {
                    e.preventDefault();
                    showAlert({
                        icon: 'warning',
                        title: 'Invalid File',
                        text: 'Please upload only .xlsx, .xls or .csv file.'
                    });
                    return;
                }

                const fileSize = Number(leadFileInput.files[0].size || 0);
                if (fileSize > MAX_UPLOAD_SIZE) {
                    e.preventDefault();
                    showAlert({
                        icon: 'warning',
                        title: 'File Too Large',
                        text: 'Maximum upload size is 10 MB. Selected file: ' + formatMB(fileSize)
                    });
                    return;
                }

                if (uploadLeadsBtn) {
                    uploadLeadsBtn.disabled = true;
                    uploadLeadsBtn.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Uploading...";
                }
            });
        }

        if (bulkAssignForm) {
            bulkAssignForm.addEventListener('submit', function (e) {
                const assignSelect = bulkAssignForm.querySelector('select[name="assign_to_bulk"]');
                const checked = bulkAssignForm.querySelectorAll('.leadChk:checked').length;

                if (!assignSelect || !assignSelect.value) {
                    e.preventDefault();
                    showAlert({
                        icon: 'warning',
                        title: 'Validation Error',
                        text: 'Please select a staff member for assignment.'
                    });
                    return;
                }

                if (checked === 0) {
                    e.preventDefault();
                    showAlert({
                        icon: 'warning',
                        title: 'No Leads Selected',
                        text: 'Please select at least one lead to assign.'
                    });
                }
            });
        }
    });

    document.querySelectorAll('.deleteBatchForm').forEach(form => {
    form.addEventListener('submit', function(e){
        if (this.dataset.confirmed) return;

        e.preventDefault();

        if (window.Swal && Swal.fire) {
            Swal.fire({
                title:'Delete Batch?',
                text:'This will delete the uploaded file and all leads imported in this batch.',
                icon:'warning',
                showCancelButton:true,
                confirmButtonText:'Delete',
                confirmButtonColor:'#e53935'
            }).then(r => {
                if (r.isConfirmed) {
                    this.dataset.confirmed = '1';
                    this.submit();
                }
            });
        } else {
            const ok = window.confirm('Delete Batch?\nThis will delete the uploaded file and all leads imported in this batch.');
            if (ok) {
                this.dataset.confirmed = '1';
                this.submit();
            }
        }
    });
});
</script>
