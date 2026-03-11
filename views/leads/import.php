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

        if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $error = "Please choose an Excel file.";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                $error = "Please upload an Excel file (.xlsx or .xls).";
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
        $sql .= " ORDER BY l.id DESC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $batchLeads = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $batchLeads = [];
    }
}
?>

<h2 style="margin-bottom:20px;">Lead Excel Upload</h2>

<?php if ($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= addslashes($success) ?>',
            confirmButtonColor: '#e91e63'
        });
    </script>
<?php endif; ?>

<?php if ($error): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($error) ?>',
            confirmButtonColor: '#e91e63'
        });
    </script>
<?php endif; ?>

<div class="card">
    <div class="card-header">Upload Excel File</div>
    <form method="POST" enctype="multipart/form-data" style="padding:16px;">
        <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
        <input type="hidden" name="upload_leads" value="1">

        <div class="form-grid">
            <div class="form-group">
                <label>Excel File</label>
                <input type="file" name="lead_file" accept=".xlsx,.xls" required>
                <small style="color:#666;">Upload `.xlsx` or `.xls` file.</small>
            </div>

            <div class="form-group">
                <label>Assign To (optional)</label>
                <select name="assign_to">
                    <option value="">Do not assign now</option>
                    <?php foreach ($staff as $s): ?>
                        <option value="<?= (int) $s['id'] ?>">
                            <?= h($s['name']) ?> (<?= h($s['role_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Leads
            </button>

            <a href="index.php?page=leads/import&download=template" class="btn-light">
                <i class="fas fa-download"></i> Download Template
            </a>
        </div>
    </form>
</div>

<?php if (!empty($_SESSION['lead_import_errors'])): ?>
    <div class="card" style="margin-top:16px;">
        <div class="card-header">Import Warnings</div>
        <div style="padding:16px; color:#b91c1c;">
            <?php foreach ($_SESSION['lead_import_errors'] as $e): ?>
                <div style="margin-bottom:6px;"><?= h($e) ?></div>
            <?php endforeach;
            unset($_SESSION['lead_import_errors']); ?>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="margin-top:16px;">
    <div class="card-header">Recent Import Batches</div>
    <div class="table-wrap">
        <table class="lead-table">
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
                            <a class="btn-icon edit" href="index.php?page=leads/import&batch_id=<?= (int) $b['id'] ?>"
                                title="View Batch">
                                <i class="fas fa-eye"></i>
                            </a>

                            <form method="POST" class="deleteBatchForm" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
                                <input type="hidden" name="delete_batch" value="1">
                                <input type="hidden" name="batch_id" value="<?= (int) $b['id'] ?>">
                                <button type="submit" class="btn-icon delete" title="Delete Batch">
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
        <div class="card-header">Batch #<?= (int) $batchId ?> Leads</div>

        <form method="POST" style="padding:16px;">
            <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>">
            <input type="hidden" name="assign_selected" value="1">
            <input type="hidden" name="batch_id" value="<?= (int) $batchId ?>">

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
                    <button class="btn btn-primary">
                        <i class="fas fa-user-check"></i> Assign Selected
                    </button>
                </div>
            </div>

            <div class="table-wrap">
                <table class="lead-table">
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

<style>
    .action-col {
        white-space: nowrap;
    }

    .delete {
        background: #ffebee;
        color: #e53935;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .form-group label {
        font-weight: 700;
        margin-bottom: 6px;
        display: block;
    }

    input,
    select {
        width: 100%;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
    }

    input:focus,
    select:focus {
        border-color: #e91e63;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, .15);
    }

    .form-actions {
        margin-top: 16px;
        display: flex;
        gap: 10px;
    }

    .btn-light {
        background: #fff;
        border: 1px solid #ddd;
        padding: 10px 16px;
        border-radius: 10px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .table-wrap {
        padding: 16px;
    }

    .lead-table {
        width: 100%;
        border-collapse: collapse;
    }

    .lead-table th {
        background: #f5f6fa;
        padding: 12px;
        text-align: left;
        font-weight: 700;
    }

    .lead-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        vertical-align: top;
    }

    .lead-sub {
        font-size: 12px;
        color: #777;
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0 2px;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }

    .edit {
        background: #e8f4fd;
        color: #1565c0;
    }

    .bulk-assign-wrap {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
        padding-bottom: 8px;
    }

    @media(max-width:768px) {
        .form-grid {
            grid-template-columns: 1fr;
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

    document.querySelectorAll('.deleteBatchForm').forEach(form => {
    form.addEventListener('submit', function(e){
        if (this.dataset.confirmed) return;

        e.preventDefault();

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
    });
});
</script>