<?php
if (!defined('APP_NAME')) {
    die('Unauthorized access.');
}

if (!function_exists('crmIsSuperAdminRole') || !crmIsSuperAdminRole()) {
    die('Access denied. Super Admin only.');
}

if (!function_exists('backupHealthSqlValue')) {
    function backupHealthSqlValue(PDO $pdo, $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return $pdo->quote((string)$value);
    }
}

if (!function_exists('backupHealthRunReport')) {
    function backupHealthRunReport(PDO $pdo): array
    {
        $healthRanAt = date('d/m/Y h:ia');
        $healthSummary = [
            'total' => 0,
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
        ];
        $healthRows = [];

        $buildAction = static function (string $area, string $label, string $status, string $detail): string {
            $areaLc = strtolower(trim($area));
            $labelLc = strtolower(trim($label));
            $detailLc = strtolower(trim($detail));
            $statusLc = strtolower(trim($status));

            if ($statusLc === 'pass') {
                return 'No action needed. Keep monitoring in regular health runs.';
            }

            if ($areaLc === 'database' && strpos($labelLc, 'connection') !== false) {
                return "Check DB credentials in config/database.php and confirm MySQL service is running. Test with SQL: SELECT DATABASE();";
            }

            if ($areaLc === 'table' && strpos($detailLc, 'missing') !== false) {
                return 'Restore missing table from latest DB backup SQL and re-run health check.';
            }

            if ($areaLc === 'table') {
                return 'Verify table structure and permissions. If corrupted, restore table from backup.';
            }

            if ($areaLc === 'index' && strpos($detailLc, 'missing index') !== false) {
                return 'Add missing index via ALTER TABLE ... ADD INDEX ... based on expected schema, then re-run health check.';
            }

            if ($areaLc === 'storage' && strpos($detailLc, 'uploads') !== false) {
                return "Create/fix uploads folder permissions (e.g., 755/775). Ensure path exists and is readable by PHP process.";
            }

            return 'Review check detail, apply corresponding schema/config fix, and re-run health check.';
        };

        $addCheck = static function (string $area, string $label, string $status, string $detail) use (&$healthRows, &$healthSummary, $buildAction): void {
            $status = strtolower($status);
            if (!in_array($status, ['pass', 'warn', 'fail'], true)) {
                $status = 'warn';
            }
            $healthRows[] = [
                'area' => $area,
                'label' => $label,
                'status' => $status,
                'detail' => $detail,
                'action' => $buildAction($area, $label, $status, $detail),
            ];
            $healthSummary['total']++;
            $healthSummary[$status]++;
        };

        try {
            $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            $addCheck('Database', 'Connection', 'pass', 'Connected to database: ' . ($dbName !== '' ? $dbName : 'unknown'));
        } catch (Throwable $e) {
            $addCheck('Database', 'Connection', 'fail', 'Database connection failed while running health check.');
        }

        $coreTables = ['audit_logs', 'leads', 'enquiries', 'enquiry_followups', 'registrations', 'registration_payments', 'monthly_targets', 'users', 'user_notifications'];
        $expectedIndexes = [
            'audit_logs' => ['PRIMARY', 'idx_audit_created_at', 'idx_audit_table_name', 'idx_audit_user_id', 'idx_audit_created_id', 'idx_audit_table_created'],
            'leads' => ['PRIMARY', 'idx_leads_branch', 'idx_leads_status', 'idx_leads_assigned'],
            'enquiries' => ['PRIMARY', 'idx_enquiries_branch', 'idx_enquiries_status', 'idx_enquiries_handled_by', 'idx_enquiries_date'],
            'enquiry_followups' => ['PRIMARY', 'enquiry_id', 'branch_id', 'followup_date', 'status', 'idx_ef_date_user_branch'],
            'registrations' => ['PRIMARY', 'uk_registration_no', 'idx_branch_id', 'idx_registration_status', 'idx_assigned_to'],
            'registration_payments' => ['PRIMARY', 'idx_registration_id', 'idx_branch_id', 'idx_payment_date', 'idx_approval_status'],
            'monthly_targets' => ['PRIMARY', 'uk_target_user_month_branch', 'idx_target_period'],
            'users' => ['PRIMARY', 'email', 'branch_id', 'role_id'],
            'user_notifications' => ['PRIMARY', 'uniq_user_notif', 'idx_user_read_created'],
        ];

        foreach ($coreTables as $table) {
            try {
                $st = $pdo->prepare("
                    SELECT table_rows, ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                      AND table_name = :t
                    LIMIT 1
                ");
                $st->execute([':t' => $table]);
                $info = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$info) {
                    $addCheck('Table', $table, 'fail', 'Table missing in current database.');
                    continue;
                }

                $rows = (int)($info['table_rows'] ?? 0);
                $sizeMb = (float)($info['size_mb'] ?? 0);
                $addCheck('Table', $table, 'pass', 'Rows: ' . number_format($rows) . ' | Size: ' . number_format($sizeMb, 2) . ' MB');

                $idxSt = $pdo->prepare("
                    SELECT DISTINCT index_name
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = :t
                ");
                $idxSt->execute([':t' => $table]);
                $actualIndexes = array_map('strval', $idxSt->fetchAll(PDO::FETCH_COLUMN) ?: []);
                $required = $expectedIndexes[$table] ?? [];
                $missing = [];
                foreach ($required as $idx) {
                    if (!in_array($idx, $actualIndexes, true)) {
                        $missing[] = $idx;
                    }
                }
                if (!empty($missing)) {
                    $addCheck('Index', $table, 'warn', 'Missing index(es): ' . implode(', ', $missing));
                } else {
                    $addCheck('Index', $table, 'pass', 'Required indexes available.');
                }
            } catch (Throwable $e) {
                $addCheck('Table', $table, 'fail', 'Failed to inspect table metadata.');
            }
        }

        try {
            $uploadsPath = ROOT_PATH . '/uploads';
            $uploadOk = is_dir($uploadsPath) && is_readable($uploadsPath);
            $addCheck('Storage', 'Uploads directory', $uploadOk ? 'pass' : 'warn', $uploadOk ? 'Uploads directory is readable.' : 'Uploads directory is missing or not readable.');
        } catch (Throwable $e) {
            $addCheck('Storage', 'Uploads directory', 'warn', 'Unable to verify uploads directory.');
        }

        return [
            'ran_at' => $healthRanAt,
            'summary' => $healthSummary,
            'rows' => $healthRows,
        ];
    }
}

if (!function_exists('backupHealthStoreReport')) {
    function backupHealthStoreReport(array $report, bool $incrementRunCount = true): void
    {
        $_SESSION['backup_health_last_report'] = $report;
        if ($incrementRunCount) {
            $prev = (int)($_SESSION['backup_health_run_count'] ?? 0);
            $_SESSION['backup_health_run_count'] = $prev + 1;
        }
    }
}

$backupDir = ROOT_PATH . '/uploads/backups/db';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

$flash = $_SESSION['backup_health_flash'] ?? null;
if (isset($_SESSION['backup_health_flash'])) {
    unset($_SESSION['backup_health_flash']);
}

$downloadBackup = trim((string)($_GET['download_backup'] ?? ''));
if ($downloadBackup !== '') {
    $safeName = basename($downloadBackup);
    if (!preg_match('/^db-backup-\d{8}-\d{6}\.sql$/', $safeName)) {
        $_SESSION['backup_health_flash'] = ['type' => 'error', 'text' => 'Invalid backup file request.'];
        header('Location: index.php?page=system/backup_health');
        exit;
    }

    $filePath = $backupDir . '/' . $safeName;
    if (!is_file($filePath) || !is_readable($filePath)) {
        $_SESSION['backup_health_flash'] = ['type' => 'error', 'text' => 'Requested backup file is not available.'];
        header('Location: index.php?page=system/backup_health');
        exit;
    }

    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Content-Length: ' . (string)filesize($filePath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($filePath);
    exit;
}

$clearHealth = isset($_GET['clear_health']) && (string)$_GET['clear_health'] === '1';
if ($clearHealth) {
    unset($_SESSION['backup_health_last_report']);
    $_SESSION['backup_health_flash'] = ['type' => 'success', 'text' => 'Health result cleared.'];
    header('Location: index.php?page=system/backup_health');
    exit;
}

$runExportDb = isset($_GET['export_db']) && (string)$_GET['export_db'] === '1';
if ($runExportDb) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $_SESSION['backup_health_flash'] = ['type' => 'error', 'text' => 'Database connection not available for backup.'];
        header('Location: index.php?page=system/backup_health');
        exit;
    }

    try {
        @set_time_limit(180);
        @ignore_user_abort(true);

        if (!is_dir($backupDir) || !is_writable($backupDir)) {
            throw new RuntimeException('Backup folder is not writable.');
        }

        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $fileName = 'db-backup-' . date('Ymd-His') . '.sql';
        $filePath = $backupDir . '/' . $fileName;
        $fh = fopen($filePath, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Unable to create backup file.');
        }

        fwrite($fh, "-- ATS CRM Database Backup\n");
        fwrite($fh, "-- Database: " . $dbName . "\n");
        fwrite($fh, "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($tables as $tableNameRaw) {
            $tableName = (string)$tableNameRaw;
            if ($tableName === '') {
                continue;
            }
            $escapedTable = '`' . str_replace('`', '``', $tableName) . '`';

            $createRow = $pdo->query('SHOW CREATE TABLE ' . $escapedTable)->fetch(PDO::FETCH_ASSOC) ?: [];
            $createSql = '';
            foreach ($createRow as $k => $v) {
                if (stripos((string)$k, 'Create Table') !== false) {
                    $createSql = (string)$v;
                    break;
                }
            }

            fwrite($fh, "-- --------------------------------------------------------\n");
            fwrite($fh, "-- Table: " . $tableName . "\n\n");
            fwrite($fh, "DROP TABLE IF EXISTS " . $escapedTable . ";\n");
            if ($createSql !== '') {
                fwrite($fh, $createSql . ";\n\n");
            }

            $count = (int)$pdo->query('SELECT COUNT(*) FROM ' . $escapedTable)->fetchColumn();
            if ($count <= 0) {
                fwrite($fh, "\n");
                continue;
            }

            $cols = $pdo->query('SHOW COLUMNS FROM ' . $escapedTable)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $colNames = [];
            foreach ($cols as $col) {
                $colNames[] = '`' . str_replace('`', '``', (string)($col['Field'] ?? '')) . '`';
            }
            $columnsSql = implode(',', $colNames);

            $stRows = $pdo->query('SELECT * FROM ' . $escapedTable);
            while ($row = $stRows->fetch(PDO::FETCH_ASSOC)) {
                $vals = [];
                foreach ($row as $val) {
                    $vals[] = backupHealthSqlValue($pdo, $val);
                }
                fwrite($fh, "INSERT INTO " . $escapedTable . " (" . $columnsSql . ") VALUES (" . implode(',', $vals) . ");\n");
            }
            fwrite($fh, "\n");
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($fh);

        // Keep only latest 25 db backup files.
        $existing = glob($backupDir . '/db-backup-*.sql') ?: [];
        usort($existing, static function ($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        if (count($existing) > 25) {
            foreach (array_slice($existing, 25) as $oldFile) {
                @unlink($oldFile);
            }
        }

        $_SESSION['backup_health_flash'] = [
            'type' => 'success',
            'text' => 'Database backup created: ' . $fileName . '. You can download it from Backup History.'
        ];
        header('Location: index.php?page=system/backup_health');
        exit;
    } catch (Throwable $e) {
        $_SESSION['backup_health_flash'] = ['type' => 'error', 'text' => 'Database backup failed: ' . $e->getMessage()];
        header('Location: index.php?page=system/backup_health');
        exit;
    }
}

$backupHistory = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . '/db-backup-*.sql') ?: [];
    usort($files, static function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    foreach (array_slice($files, 0, 10) as $f) {
        $backupHistory[] = [
            'name' => basename($f),
            'size_mb' => round((float)filesize($f) / 1024 / 1024, 2),
            'time' => date('d/m/Y h:ia', (int)filemtime($f)),
        ];
    }
}

$runExportUploads = isset($_GET['export_uploads']) && (string)$_GET['export_uploads'] === '1';
if ($runExportUploads) {
    $uploadsPath = ROOT_PATH . '/uploads';

    if (!class_exists('ZipArchive')) {
        $_SESSION['backup_health_flash'] = ['type' => 'error', 'text' => 'ZipArchive extension is not enabled on server.'];
        header('Location: index.php?page=system/backup_health');
        exit;
    }

    if (!is_dir($uploadsPath) || !is_readable($uploadsPath)) {
        $_SESSION['backup_health_flash'] = ['type' => 'error', 'text' => 'Uploads directory is not readable.'];
        header('Location: index.php?page=system/backup_health');
        exit;
    }

    try {
        $tmpBase = tempnam(sys_get_temp_dir(), 'crm_up_');
        if ($tmpBase === false) {
            throw new RuntimeException('Unable to allocate temporary file.');
        }
        $tmpZip = $tmpBase . '.zip';
        @rename($tmpBase, $tmpZip);

        $zip = new ZipArchive();
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create zip archive.');
        }

        $uploadsReal = realpath($uploadsPath);
        if ($uploadsReal === false) {
            throw new RuntimeException('Uploads path resolution failed.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadsReal, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            $localPath = ltrim(str_replace('\\', '/', substr($itemPath, strlen($uploadsReal))), '/');
            if ($localPath === '') {
                continue;
            }

            if ($item->isDir()) {
                $zip->addEmptyDir($localPath);
            } else {
                $zip->addFile($itemPath, $localPath);
            }
        }

        $zip->close();

        $downloadName = 'uploads-backup-' . date('Ymd-His') . '.zip';
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string)filesize($tmpZip));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    } catch (Throwable $e) {
        $_SESSION['backup_health_flash'] = ['type' => 'error', 'text' => 'Uploads export failed: ' . $e->getMessage()];
        header('Location: index.php?page=system/backup_health');
        exit;
    }
}

$runHealthApi = isset($_GET['health_api']) && (string)$_GET['health_api'] === '1';
if ($runHealthApi) {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Database connection is not available.']);
        exit;
    }

    try {
        @set_time_limit(120);
        $report = backupHealthRunReport($pdo);
        backupHealthStoreReport($report, true);
        $runCountNow = (int)($_SESSION['backup_health_run_count'] ?? 0);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'report' => $report, 'run_count' => $runCountNow]);
        exit;
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'Health check failed: ' . $e->getMessage()]);
        exit;
    }
}

$runHealthRequested = isset($_GET['run_health']) && (string)$_GET['run_health'] === '1';
$runHealth = $runHealthRequested;
$autoRunHealth = false;
$isAjaxRequest = (
    (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || isset($_GET['ajax'])
);
$autoRunHealth = $runHealthRequested && !$isAjaxRequest;
if ($autoRunHealth) {
    $runHealth = false;
}
$healthRanAt = null;
$healthSummary = [
    'total' => 0,
    'pass' => 0,
    'warn' => 0,
    'fail' => 0,
];
$healthRows = [];
$tableRows = [];
$healthRunCount = (int)($_SESSION['backup_health_run_count'] ?? 0);
$lastStoredReport = $_SESSION['backup_health_last_report'] ?? null;
$hasStoredReport = is_array($lastStoredReport)
    && isset($lastStoredReport['summary'], $lastStoredReport['rows']);

if ($runHealth && isset($pdo) && $pdo instanceof PDO) {
    $report = backupHealthRunReport($pdo);
    backupHealthStoreReport($report, true);
    $healthRanAt = (string)($report['ran_at'] ?? '');
    $healthSummary = (array)($report['summary'] ?? $healthSummary);
    $healthRows = (array)($report['rows'] ?? []);
} elseif ($hasStoredReport) {
    $healthRanAt = (string)($lastStoredReport['ran_at'] ?? '');
    $healthSummary = (array)($lastStoredReport['summary'] ?? $healthSummary);
    $healthRows = (array)($lastStoredReport['rows'] ?? []);
}

$backupCountUi = count($backupHistory);
$latestBackupUi = $backupHistory[0] ?? null;
$latestBackupTimeUi = is_array($latestBackupUi) ? (string)($latestBackupUi['time'] ?? 'Not available') : 'Not available';
$latestBackupSizeUi = is_array($latestBackupUi) ? number_format((float)($latestBackupUi['size_mb'] ?? 0), 2) . ' MB' : '--';
$healthLastRunUi = (($runHealth || $autoRunHealth || $hasStoredReport) && (string)$healthRanAt !== '') ? (string)$healthRanAt : 'Not run yet';
$healthChecksUi = ($runHealth || $autoRunHealth || $hasStoredReport) ? (int)($healthSummary['total'] ?? 0) : 0;
?>

<style>
.backup-health-page{
  --bh-bg-soft:#fff7fb;
  --bh-bg-soft-2:#fff1f8;
  --bh-border:#f2d7e3;
  --bh-border-strong:#e9bfd3;
  --bh-text:#1f2a44;
  --bh-muted:#667085;
  --bh-brand:#d81b60;
  --bh-brand-2:#eb1f73;
  --bh-shadow:0 14px 36px rgba(15,23,42,.08);
  max-width:1480px;
  margin:0 auto;
}
.backup-health-page,.backup-health-page *{box-sizing:border-box;}
.bh-wrap{display:grid;gap:16px;}
.bh-card{
  background:#fff;
  border:1px solid var(--bh-border);
  border-radius:16px;
  box-shadow:var(--bh-shadow);
}
.bh-hero .card-body{
  padding:22px 24px !important;
}
.bh-hero{
  background:
    radial-gradient(1250px 220px at -10% -40%, #ffe1ef 0%, rgba(255,225,239,0) 62%),
    radial-gradient(900px 230px at 110% -40%, #ffe8f3 0%, rgba(255,232,243,0) 67%),
    linear-gradient(135deg,#fff8fc 0%,#fff 58%);
  border-color:var(--bh-border-strong);
}
.bh-hero-layout{
  display:grid;
  grid-template-columns:1fr;
  gap:16px;
  align-items:start;
}
.bh-hero-title{
  margin:0;
  font-size:2rem !important;
  font-weight:800;
  color:var(--bh-text);
  display:flex;
  align-items:center;
  gap:10px;
  line-height:1.2;
}
.bh-hero-title i{
  color:var(--bh-brand);
  font-size:1.78rem;
  line-height:1;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  transform:translateY(1px);
}
.bh-hero-sub{margin:10px 0 0;color:#5b6478;font-weight:600;font-size:1.02rem !important;}
.bh-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;}
.bh-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  border-radius:12px;
  padding:10px 16px;
  font-weight:800;
  font-size:.93rem !important;
  text-decoration:none;
  border:1px solid #efcade;
  background:#fff;
  color:#a91c5f;
  transition:background-color .2s ease,color .2s ease,border-color .2s ease,box-shadow .2s ease,opacity .2s ease;
  cursor:pointer;
}
.bh-btn i{font-size:.84rem;}
.bh-btn.primary{
  background:linear-gradient(135deg,var(--bh-brand-2) 0%,var(--bh-brand) 100%);
  color:#fff;
  border-color:#db2468;
  box-shadow:0 8px 22px rgba(219,36,104,.3);
}
.bh-btn.primary:hover{
  color:#fff;
  background:linear-gradient(135deg,#e41d6d 0%,#ca1659 100%);
  border-color:#ca1659;
  box-shadow:0 10px 24px rgba(202,22,89,.28);
}
.bh-btn:hover{
  background:#fff5fa;
  color:#a91c5f;
  border-color:#eeb6ce;
  box-shadow:0 6px 16px rgba(233,30,99,.14);
}
.bh-btn:focus-visible{
  outline:2px solid rgba(216,27,96,.35);
  outline-offset:2px;
}
.bh-btn.is-loading{opacity:.68;pointer-events:none;}
.bh-summary-grid{
  display:grid;
  grid-template-columns:repeat(3,minmax(180px,1fr));
  gap:10px;
}
.bh-summary{
  border:1px solid var(--bh-border);
  border-radius:14px;
  background:linear-gradient(180deg,#fff 0%,#fff9fc 100%);
  padding:12px 14px;
}
.bh-summary .label{
  margin:0;
  font-size:.78rem !important;
  color:#8a5270;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.05em;
}
.bh-summary .value{
  margin:4px 0 0;
  color:var(--bh-text);
  font-weight:800;
  font-size:1.06rem !important;
}
.bh-summary .sub{
  margin:2px 0 0;
  color:var(--bh-muted);
  font-size:.85rem !important;
  font-weight:600;
}
.bh-grid{display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:12px;}
.bh-feature{
  padding:18px;
  background:linear-gradient(180deg,#fff 0%,#fffafd 100%);
}
.bh-feature h5{margin:0 0 7px;font-weight:800;color:var(--bh-text);font-size:1.06rem !important;}
.bh-feature p{margin:0;color:#657189;font-size:.95rem !important;line-height:1.45;}
.bh-feature .ico{width:38px;height:38px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:#ffe7f1;color:#c2185b;margin-bottom:12px;}
.bh-alert{
  border-radius:12px;
  padding:12px 14px;
  font-weight:700;
  border:1px solid;
  font-size:.95rem !important;
}
.bh-alert.success{background:#f2fff6;border-color:#c9efd8;color:#146c43;}
.bh-alert.error{background:#fff1f1;border-color:#f4c8c8;color:#9f1d1d;}
.bh-title{margin:0 0 12px;font-weight:800;color:var(--bh-text);font-size:1.2rem !important;}
.bh-title-inline{margin:0 !important;line-height:1.2;}
.bh-section-head{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
  margin-bottom:14px;
  padding-bottom:10px;
  border-bottom:1px solid var(--bh-border);
}
.bh-history-head{
  display:grid;
  grid-template-columns:minmax(0,1fr) auto;
  align-items:baseline;
  column-gap:12px;
  padding-bottom:12px;
}
.bh-history-meta{
  margin:0;
  line-height:1.2;
  display:inline-flex;
  align-items:baseline;
  justify-content:flex-end;
  text-align:right;
  white-space:nowrap;
}
.bh-head-inline{display:flex;align-items:center;gap:10px;}
.bh-head-dot{width:10px;height:10px;border-radius:999px;background:var(--bh-brand);box-shadow:0 0 0 5px #ffe4f0;}
.bh-title-caption{margin:0;color:#677087;font-size:.88rem !important;font-weight:600;}
.bh-progress-note{margin:8px 0 0;color:#8a5270;font-size:.86rem !important;font-weight:700;}
.bh-kpis{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:8px;margin-bottom:12px;}
.bh-kpi{
  border:1px solid #f0d7e4;
  border-radius:12px;
  padding:10px 12px;
  font-weight:800;
  font-size:.92rem !important;
}
.bh-kpi.pass{color:#0f7b46;border-color:#d7f2e0;background:#f6fff9;}
.bh-kpi.warn{color:#926100;border-color:#f7e7c8;background:#fffcf2;}
.bh-kpi.fail{color:#b3261e;border-color:#f4d4d4;background:#fff8f8;}
.bh-kpi.total{background:#fff8fc;}
.bh-table-wrap{
  border:1px solid var(--bh-border);
  border-radius:12px;
  overflow:auto;
  max-width:100%;
  background:#fff;
}
.bh-health-card .card-body{
  padding:18px 18px 20px !important;
}
.bh-health-card .bh-section-head{
  padding:0 10px 12px;
  margin-bottom:10px;
}
.bh-health-card .bh-progress-note{
  padding:0 10px;
}
.bh-health-card .bh-kpis{
  margin:0 8px 12px;
}
.bh-health-card .bh-table-wrap{
  margin:0 8px;
  border-radius:14px;
}
.bh-health-card .bh-table thead th,
.bh-health-card .bh-table td{
  padding-left:16px;
  padding-right:16px;
}
.bh-table{width:100%;margin:0;border-collapse:collapse;}
.bh-table thead th{
  background:#fff4f9;
  color:#9a2f60;
  font-weight:800;
  font-size:.82rem !important;
  letter-spacing:.04em;
  text-transform:uppercase;
  padding:11px 10px;
  border-bottom:1px solid #efcada;
  position:sticky;
  top:0;
  z-index:1;
}
.bh-table td{
  padding:11px 10px;
  border-bottom:1px solid #f7e3ec;
  color:#2f3d57;
  vertical-align:top;
  font-size:.94rem !important;
  line-height:1.35;
}
.bh-table tbody tr:nth-child(even){background:#fffafd;}
.bh-table tr:last-child td{border-bottom:0;}
.bh-health-table{min-width:1240px;}
.bh-history-table{min-width:760px;}
.bh-pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.74rem;font-weight:800;text-transform:uppercase;}
.bh-pill.pass{background:#e8f8ef;color:#0f7b46;}
.bh-pill.warn{background:#fff6df;color:#926100;}
.bh-pill.fail{background:#ffe9e9;color:#b3261e;}
.bh-pill.testing{background:#eef3ff;color:#3157a3;}
.bh-test-wrap{
  display:grid;
  gap:6px;
}
.bh-test-line{
  font-size:.86rem;
  color:#6a748c;
  font-weight:700;
}
.bh-test-track{
  width:100%;
  height:9px;
  border-radius:999px;
  background:#edf1fb;
  border:1px solid #d8e0f0;
  overflow:hidden;
  box-shadow:inset 0 1px 2px rgba(15,23,42,.08);
}
.bh-test-fill{
  height:100%;
  width:0%;
  border-radius:999px;
  background:linear-gradient(90deg,#d81b60 0%,#f06292 45%,#ff8ab7 100%);
  transition:width linear;
  position:relative;
  overflow:hidden;
}
.bh-test-fill::after{
  content:'';
  position:absolute;
  inset:0;
  background:repeating-linear-gradient(
    -45deg,
    rgba(255,255,255,.34) 0 8px,
    rgba(255,255,255,0) 8px 16px
  );
  animation:bhBarShift .7s linear infinite;
}
.bh-overall{
  margin-top:8px;
  max-width:420px;
}
.bh-overall-track{
  width:100%;
  height:10px;
  border-radius:999px;
  background:#edf1fb;
  border:1px solid #d8e0f0;
  overflow:hidden;
}
.bh-overall-fill{
  height:100%;
  width:0%;
  border-radius:999px;
  background:linear-gradient(90deg,#7c3aed 0%,#d81b60 50%,#f43f5e 100%);
  transition:width .35s ease;
}
.bh-overall-label{
  margin-top:4px;
  font-size:.82rem;
  color:#64718b;
  font-weight:700;
}
.bh-muted{color:#6b7280;font-size:.9rem !important;font-weight:600;}
.bh-empty{
  display:flex;
  align-items:center;
  gap:10px;
  padding:14px;
  border:1px dashed #f1cadb;
  border-radius:12px;
  background:#fff9fc;
}
.bh-empty i{color:#cc2e70;}
.bh-history-dl{padding:6px 11px !important;font-size:.8rem !important;}
.bh-history-card .card-body{
  padding:18px 18px 20px !important;
}
.bh-history-card .bh-section-head{
  padding:0 10px 12px;
  margin-bottom:10px;
}
.bh-history-card .bh-table-wrap{
  margin:0 8px;
  border-radius:14px;
}
.bh-history-card .bh-table thead th,
.bh-history-card .bh-table td{
  padding-left:16px;
  padding-right:16px;
}
.bh-table-wrap .dt-top{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
  padding:12px 16px;
  border-bottom:1px solid #f3d9e6;
  background:#fffbfd;
}
.bh-table-wrap .dataTables_length,
.bh-table-wrap .dataTables_filter{
  margin:0;
}
.bh-table-wrap .dataTables_length label,
.bh-table-wrap .dataTables_filter label{
  margin:0;
  display:inline-flex;
  align-items:center;
  gap:8px;
  color:#48556f;
  font-weight:700;
  font-size:.9rem !important;
}
.bh-table-wrap .dataTables_length select{
  min-width:72px;
  height:36px;
  border-radius:10px;
  border:1px solid #f0cadb;
  background:#fff;
  color:#1f2a44;
  font-weight:700;
  padding:0 28px 0 10px;
}
.bh-table-wrap .dataTables_filter input{
  width:220px;
  max-width:100%;
  height:36px;
  border-radius:999px;
  border:1px solid #ead0dd;
  background:#fff;
  color:#1f2a44;
  padding:0 12px;
}
.bh-table-wrap .dt-bottom{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  flex-wrap:wrap;
  padding:12px 16px 0;
}
.bh-table-wrap .dataTables_info{
  color:#5e6b84;
  font-weight:600;
  font-size:.89rem !important;
}
.bh-table-wrap .dataTables_paginate{
  display:flex;
  align-items:center;
  gap:6px;
}
.bh-table-wrap .dataTables_paginate .paginate_button{
  border-radius:10px !important;
  border:1px solid #efcade !important;
  padding:4px 10px !important;
  min-width:38px;
  text-align:center;
  font-weight:700;
  color:#a91c5f !important;
  background:#fff !important;
}
.bh-table-wrap .dataTables_paginate .paginate_button.current{
  color:#fff !important;
  border-color:#d81b60 !important;
  background:#d81b60 !important;
}
.bh-table-wrap .dataTables_paginate .paginate_button.disabled{
  opacity:.5;
  cursor:not-allowed !important;
}
.bh-anim-row{
  opacity:0;
  transform:translateY(8px);
  animation:bhRowIn .36s ease forwards;
}
@keyframes bhRowIn{
  to{
    opacity:1;
    transform:translateY(0);
  }
}
@keyframes bhBarShift{
  from{background-position:0 0;}
  to{background-position:24px 0;}
}
@media (prefers-reduced-motion: reduce){
  .bh-anim-row{
    opacity:1;
    transform:none;
    animation:none;
  }
}
@media (max-width:1150px){
  .bh-summary-grid{grid-template-columns:1fr 1fr;}
}
@media (max-width:1050px){
  .bh-grid{grid-template-columns:1fr 1fr;}
  .bh-kpis{grid-template-columns:1fr 1fr;}
}
@media (max-width:700px){
  .bh-health-card .card-body{
    padding:14px !important;
  }
  .bh-health-card .bh-section-head{
    padding:0 4px 10px;
  }
  .bh-health-card .bh-progress-note{
    padding:0 4px;
  }
  .bh-health-card .bh-kpis{
    margin:0 0 10px;
  }
  .bh-health-card .bh-table-wrap{
    margin:0;
  }
  .bh-health-card .bh-table thead th,
  .bh-health-card .bh-table td{
    padding-left:12px;
    padding-right:12px;
  }
  .bh-history-head{
    grid-template-columns:1fr;
    row-gap:6px;
  }
  .bh-history-meta{
    justify-content:flex-start;
    text-align:left;
  }
  .bh-history-card .card-body{
    padding:14px !important;
  }
  .bh-history-card .bh-section-head{
    padding:0 4px 10px;
  }
  .bh-history-card .bh-table-wrap{
    margin:0;
  }
  .bh-history-card .bh-table thead th,
  .bh-history-card .bh-table td{
    padding-left:12px;
    padding-right:12px;
  }
  .bh-table-wrap .dt-top,
  .bh-table-wrap .dt-bottom{
    align-items:flex-start;
    flex-direction:column;
  }
  .bh-table-wrap .dataTables_filter input{
    width:100%;
  }
  .bh-hero .card-body{padding:16px !important;}
  .bh-summary-grid{grid-template-columns:1fr;}
  .bh-grid{grid-template-columns:1fr;}
  .bh-kpis{grid-template-columns:1fr;}
  .bh-actions{display:grid;grid-template-columns:1fr;}
  .bh-btn{width:100%;}
  .bh-hero-title{font-size:1.52rem !important;}
  .bh-section-head{align-items:flex-start;}
  .bh-health-table,.bh-history-table{min-width:680px;}
}
</style>

<div class="container-fluid py-3 backup-health-page" id="backupHealthRoot" data-bh-auto-run="<?= $autoRunHealth ? '1' : '0' ?>">
    <div class="bh-wrap">
        <div class="bh-card bh-hero">
            <div class="card-body p-4">
                <div class="bh-hero-layout">
                    <div>
                        <h3 class="bh-hero-title">
                            <i class="fas fa-shield-alt"></i>
                            Backup &amp; Health
                        </h3>
                        <p class="bh-hero-sub">Production controls for backup reliability, file export safety, and live system health visibility.</p>
                        <div class="bh-actions">
                            <a
                                href="index.php?page=system/backup_health&run_health=1"
                                class="bh-btn primary js-bh-health-run"
                                data-bh-loading="Running health check..."
                            >
                                <i class="fas fa-stethoscope"></i>Run Health Check
                            </a>
                            <a href="index.php?page=system/backup_health&export_db=1" class="bh-btn js-bh-iframe-nav" data-bh-loading="Creating database backup..." data-bh-after-refresh="1" data-bh-notice="Database backup completed and list refreshed.">
                                <i class="fas fa-database"></i>Database Backup
                            </a>
                            <a href="index.php?page=system/backup_health&export_uploads=1" class="bh-btn js-bh-iframe-nav" data-bh-loading="Preparing uploads archive..." data-bh-notice="Uploads export started in background.">
                                <i class="fas fa-file-archive"></i>Export Uploads
                            </a>
                            <a
                                href="index.php?page=system/backup_health&clear_health=1"
                                class="bh-btn js-bh-ajax-nav"
                                id="bhClearResultBtn"
                                data-bh-loading="Clearing health result..."
                                style="<?= ($runHealth || $autoRunHealth || $hasStoredReport) ? '' : 'display:none;' ?>"
                            >
                                <i class="fas fa-eraser"></i>Clear Result
                            </a>
                        </div>
                    </div>
                    <div class="bh-summary-grid">
                        <div class="bh-summary">
                            <p class="label">Backup Files</p>
                            <p class="value"><?= (int)$backupCountUi ?></p>
                            <p class="sub">Latest kept in secure history list.</p>
                        </div>
                        <div class="bh-summary">
                            <p class="label">Latest Backup</p>
                            <p class="value"><?= htmlspecialchars((string)$latestBackupTimeUi) ?></p>
                            <p class="sub">Size: <?= htmlspecialchars((string)$latestBackupSizeUi) ?></p>
                        </div>
                        <div class="bh-summary">
                            <p class="label">Health Status</p>
                            <p class="value" id="bhTopHealthStatus"><?= htmlspecialchars((string)$healthLastRunUi) ?></p>
                            <p class="sub" id="bhTopHealthChecks">Checks in last run: <?= (int)$healthChecksUi ?> | Runs completed: <span id="bhTopHealthRunCount"><?= (int)$healthRunCount ?></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="bhInlineNotice" class="bh-alert" style="display:none;"></div>

        <div class="bh-grid">
            <div class="bh-card bh-feature">
                <div class="ico"><i class="fas fa-database"></i></div>
                <h5>Database Backup</h5>
                <p>Create full SQL backup snapshots of all CRM data.</p>
            </div>
            <div class="bh-card bh-feature">
                <div class="ico"><i class="fas fa-file-archive"></i></div>
                <h5>Uploads Export</h5>
                <p>Export uploads/signatures/files as archival package.</p>
            </div>
            <div class="bh-card bh-feature">
                <div class="ico"><i class="fas fa-heartbeat"></i></div>
                <h5>Health Check</h5>
                <p>Run index, storage, and consistency checks.</p>
            </div>
        </div>

    <?php if (is_array($flash) && !empty($flash['text'])): ?>
        <?php $isError = (($flash['type'] ?? '') === 'error'); ?>
        <div class="bh-alert <?= $isError ? 'error' : 'success' ?>">
            <?= htmlspecialchars((string)$flash['text']) ?>
        </div>
    <?php endif; ?>

    <div id="bhHealthContainer">
    <?php if ($runHealth || $autoRunHealth || $hasStoredReport): ?>
        <div class="bh-card bh-health-card">
            <div class="card-body p-4">
                <div class="bh-section-head">
                    <div>
                        <div class="bh-head-inline">
                            <span class="bh-head-dot"></span>
                            <h5 class="bh-title" style="margin:0;">Health Result</h5>
                        </div>
                        <p class="bh-title-caption">Checks are listed in sequence with quick status visibility.</p>
                    </div>
                    <small class="bh-muted">Last run: <?= htmlspecialchars((string)$healthRanAt) ?></small>
                </div>

                <div class="bh-kpis">
                    <div class="bh-kpi total">Total Checks: <b><?= (int)$healthSummary['total'] ?></b></div>
                    <div class="bh-kpi pass">Pass: <b><?= (int)$healthSummary['pass'] ?></b></div>
                    <div class="bh-kpi warn">Warn: <b><?= (int)$healthSummary['warn'] ?></b></div>
                    <div class="bh-kpi fail">Fail: <b><?= (int)$healthSummary['fail'] ?></b></div>
                </div>

                <div class="bh-table-wrap">
                    <table class="bh-table bh-health-table">
                        <thead>
                            <tr>
                                <th>Area</th>
                                <th>Check</th>
                                <th>Status</th>
                                <th>Detail</th>
                                <th>Recommended Action</th>
                            </tr>
                        </thead>
                        <tbody id="<?= $autoRunHealth ? 'bhHealthTbody' : '' ?>">
                            <?php foreach ($healthRows as $i => $row): ?>
                                <?php
                                $status = (string)$row['status'];
                                $delay = max(0, min(90, (int)$i)) * 30;
                                ?>
                                <tr class="bh-anim-row" style="animation-delay: <?= (int)$delay ?>ms;">
                                    <td><?= htmlspecialchars((string)$row['area']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['label']) ?></td>
                                    <td><span class="bh-pill <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span></td>
                                    <td><?= htmlspecialchars((string)$row['detail']) ?></td>
                                    <td><?= htmlspecialchars((string)($row['action'] ?? 'Review detail and re-run health check.')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($autoRunHealth): ?>
                                <tr>
                                    <td colspan="5" class="bh-muted">Starting health checks...</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </div>

    <div class="bh-card bh-history-card">
        <div class="card-body p-4">
            <div class="bh-section-head bh-history-head">
                <h5 class="bh-title bh-title-inline">Database Backup History</h5>
                <small class="bh-muted bh-history-meta">Latest 10 backups</small>
            </div>
            <?php if (empty($backupHistory)): ?>
                <div class="bh-empty">
                    <i class="fas fa-inbox"></i>
                    <div class="bh-muted">No database backups found yet. Run a backup once to start history tracking.</div>
                </div>
            <?php else: ?>
                <div class="bh-table-wrap">
                    <table class="bh-table bh-history-table" id="bhBackupHistoryTable">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Size</th>
                                <th>Created</th>
                                <th>Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupHistory as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$row['name']) ?></td>
                                    <td><?= htmlspecialchars(number_format((float)$row['size_mb'], 2)) ?> MB</td>
                                    <td><?= htmlspecialchars((string)$row['time']) ?></td>
                                    <td>
                                        <a href="index.php?page=system/backup_health&download_backup=<?= urlencode((string)$row['name']) ?>" class="bh-btn bh-history-dl">
                                            Download
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<script>
(function () {
    let root = document.getElementById('backupHealthRoot');
    if (!root) return;

    function initBackupHistoryTable(scopeRoot) {
        const scope = scopeRoot || document;
        const table = scope.querySelector('#bhBackupHistoryTable');
        if (!table) return;
        if (table.getAttribute('data-bh-dt-init') === '1') return;
        const tbodyRows = table.querySelectorAll('tbody tr').length;
        if (!tbodyRows) return;

        const runInit = function () {
            if (table.getAttribute('data-bh-dt-init') === '1') return true;
            try {
                if (typeof window.crmDataTable === 'function') {
                    window.crmDataTable('#bhBackupHistoryTable', {
                        pageLength: 10,
                        lengthMenu: [5, 10, 20, 50],
                        ordering: true,
                        order: [[2, 'desc']],
                        searchPlaceholder: 'Search backup file...',
                        dom:
                            "<'dt-top'lf>" +
                            "rt" +
                            "<'dt-bottom'ip>"
                    });
                    table.setAttribute('data-bh-dt-init', '1');
                    return true;
                }
                if (window.jQuery && typeof window.jQuery.fn.DataTable === 'function') {
                    window.jQuery('#bhBackupHistoryTable').DataTable({
                        pageLength: 10,
                        lengthMenu: [5, 10, 20, 50],
                        order: [[2, 'desc']]
                    });
                    table.setAttribute('data-bh-dt-init', '1');
                    return true;
                }
            } catch (e) {
                return false;
            }
            return false;
        };

        if (runInit()) return;

        let tries = 0;
        const maxTries = 12;
        const timer = window.setInterval(function () {
            tries += 1;
            if (runInit() || tries >= maxTries) {
                window.clearInterval(timer);
            }
        }, 250);
    }

    function getRoot() {
        root = document.getElementById('backupHealthRoot');
        return root;
    }

    function setBtnLoading(btn, loading) {
        if (!btn) return;
        if (loading) {
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
        } else {
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
        }
    }

    function showNotice(message, isError) {
        const pageRoot = getRoot();
        if (!pageRoot) return;
        const notice = pageRoot.querySelector('#bhInlineNotice');
        if (!notice) return;
        notice.className = 'bh-alert ' + (isError ? 'error' : 'success');
        notice.textContent = message;
        notice.style.display = 'block';
    }

    function escHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function updateTopHealthSummary(report, runCount) {
        const pageRoot = getRoot();
        if (!pageRoot) return;
        const statusEl = pageRoot.querySelector('#bhTopHealthStatus');
        const checksEl = pageRoot.querySelector('#bhTopHealthChecks');
        const runCountEl = pageRoot.querySelector('#bhTopHealthRunCount');
        if (!statusEl || !checksEl) return;

        const ranAt = report && report.ran_at ? String(report.ran_at) : '-';
        const totalChecks = report && report.summary ? Number(report.summary.total || 0) : 0;
        statusEl.textContent = ranAt;
        if (runCountEl) {
            const safeRunCount = Number(runCount || runCountEl.textContent || 0);
            runCountEl.textContent = String(safeRunCount);
            checksEl.innerHTML = 'Checks in last run: ' + totalChecks + ' | Runs completed: <span id="bhTopHealthRunCount">' + String(safeRunCount) + '</span>';
        } else {
            checksEl.textContent = 'Checks in last run: ' + totalChecks;
        }
    }

    async function refreshPanel(url, successMsg) {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const html = await response.text();
        const holder = document.createElement('div');
        holder.innerHTML = html;
        const newRoot = holder.querySelector('#backupHealthRoot');
        const oldRoot = getRoot();
        if (!newRoot || !oldRoot) {
            window.location.href = 'index.php?page=system/backup_health';
            return;
        }
        oldRoot.replaceWith(newRoot);
        root = newRoot;
        initBackupHistoryTable(newRoot);
        if (successMsg) {
            showNotice(successMsg, false);
        }
    }

    function renderHealthFrame(report) {
        const pageRoot = getRoot();
        if (!pageRoot) return null;
        const clearBtn = pageRoot.querySelector('#bhClearResultBtn');
        if (clearBtn) clearBtn.style.display = '';
        const container = pageRoot.querySelector('#bhHealthContainer');
        if (!container) return null;

        const summary = report && report.summary ? report.summary : { total: 0, pass: 0, warn: 0, fail: 0 };
        const ranAt = report && report.ran_at ? report.ran_at : '-';
        const totalChecks = Number(summary.total || 0);

        container.innerHTML = '' +
            '<div class="bh-card bh-health-card">' +
                '<div class="card-body p-4">' +
                    '<div class="bh-section-head">' +
                        '<div>' +
                            '<div class="bh-head-inline">' +
                                '<span class="bh-head-dot"></span>' +
                                '<h5 class="bh-title" style="margin:0;">Health Result</h5>' +
                            '</div>' +
                            '<p class="bh-title-caption">Checks are listed in sequence with quick status visibility.</p>' +
                            '<p class="bh-progress-note" id="bhProgressNote">Initializing checks...</p>' +
                            '<div class="bh-overall">' +
                                '<div class="bh-overall-track"><div class="bh-overall-fill" id="bhOverallFill"></div></div>' +
                                '<div class="bh-overall-label" id="bhOverallLabel">0% completed (0/' + totalChecks + ')</div>' +
                            '</div>' +
                        '</div>' +
                        '<small class="bh-muted">Last run: ' + escHtml(ranAt) + '</small>' +
                    '</div>' +
                    '<div class="bh-kpis">' +
                        '<div class="bh-kpi total">Total Checks: <b id="bhKpiTotalDone">0</b> / <b id="bhKpiTotalAll">' + totalChecks + '</b></div>' +
                        '<div class="bh-kpi pass">Pass: <b id="bhKpiPass">0</b></div>' +
                        '<div class="bh-kpi warn">Warn: <b id="bhKpiWarn">0</b></div>' +
                        '<div class="bh-kpi fail">Fail: <b id="bhKpiFail">0</b></div>' +
                    '</div>' +
                    '<div class="bh-table-wrap">' +
                        '<table class="bh-table bh-health-table">' +
                            '<thead><tr><th>Area</th><th>Check</th><th>Status</th><th>Detail</th><th>Recommended Action</th></tr></thead>' +
                            '<tbody id="bhHealthTbody"></tbody>' +
                        '</table>' +
                    '</div>' +
                '</div>' +
            '</div>';

        return container.querySelector('#bhHealthTbody');
    }

    function sleep(ms) {
        return new Promise(function (resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    async function renderHealthRowsSequential(rows, tbody) {
        if (!tbody) return Promise.resolve();
        const list = Array.isArray(rows) ? rows : [];
        const pageRoot = getRoot();
        const progressNote = pageRoot ? pageRoot.querySelector('#bhProgressNote') : null;
        const overallFill = pageRoot ? pageRoot.querySelector('#bhOverallFill') : null;
        const overallLabel = pageRoot ? pageRoot.querySelector('#bhOverallLabel') : null;
        const kpiTotalDone = pageRoot ? pageRoot.querySelector('#bhKpiTotalDone') : null;
        const kpiPass = pageRoot ? pageRoot.querySelector('#bhKpiPass') : null;
        const kpiWarn = pageRoot ? pageRoot.querySelector('#bhKpiWarn') : null;
        const kpiFail = pageRoot ? pageRoot.querySelector('#bhKpiFail') : null;
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="bh-muted">No health checks found.</td></tr>';
            if (progressNote) progressNote.textContent = 'No checks returned.';
            return Promise.resolve();
        }

        const testDurationMs = 3000;
        const settleGapMs = 240;
        const counts = { pass: 0, warn: 0, fail: 0, done: 0 };

        function updateProgressUi() {
            const pct = Math.max(0, Math.min(100, Math.round((counts.done / list.length) * 100)));
            if (overallFill) {
                overallFill.style.width = pct + '%';
            }
            if (overallLabel) {
                overallLabel.textContent = pct + '% completed (' + counts.done + '/' + list.length + ')';
            }
            if (kpiTotalDone) kpiTotalDone.textContent = String(counts.done);
            if (kpiPass) kpiPass.textContent = String(counts.pass);
            if (kpiWarn) kpiWarn.textContent = String(counts.warn);
            if (kpiFail) kpiFail.textContent = String(counts.fail);
        }

        updateProgressUi();

        for (let idx = 0; idx < list.length; idx += 1) {
            const row = list[idx];
            const current = idx + 1;
            const status = String(row && row.status ? row.status : 'warn').toLowerCase();

            if (progressNote) {
                progressNote.textContent = 'Testing ' + current + ' of ' + list.length + '...';
            }

            const tr = document.createElement('tr');
            tr.className = 'bh-anim-row';
            tr.innerHTML =
                '<td>' + escHtml(row && row.area ? row.area : '-') + '</td>' +
                '<td>' + escHtml(row && row.label ? row.label : '-') + '</td>' +
                '<td><span class="bh-pill testing">testing</span></td>' +
                '<td>' +
                    '<div class="bh-test-wrap">' +
                        '<div class="bh-test-line">Testing in progress...</div>' +
                        '<div class="bh-test-track"><div class="bh-test-fill"></div></div>' +
                    '</div>' +
                '</td>' +
                '<td>Preparing recommendation...</td>';
            tbody.appendChild(tr);

            const fill = tr.querySelector('.bh-test-fill');
            if (fill) {
                fill.style.transitionDuration = testDurationMs + 'ms';
                fill.style.width = '100%';
            }

            await sleep(testDurationMs);

            const statusCell = tr.children[2];
            const detailCell = tr.children[3];
            const actionCell = tr.children[4];
            if (statusCell) {
                statusCell.innerHTML = '<span class="bh-pill ' + escHtml(status) + '">' + escHtml(status) + '</span>';
            }
            if (detailCell) {
                detailCell.textContent = String(row && row.detail ? row.detail : '-');
            }
            if (actionCell) {
                actionCell.textContent = String(row && row.action ? row.action : 'Review detail and re-run health check.');
            }

            counts.done += 1;
            if (status === 'pass') counts.pass += 1;
            else if (status === 'fail') counts.fail += 1;
            else counts.warn += 1;
            updateProgressUi();

            await sleep(settleGapMs);
        }

        if (progressNote) {
            progressNote.textContent = 'All checks completed.';
        }
        return Promise.resolve();
    }

    async function runHealthCheck(actionLink) {
        setBtnLoading(actionLink, true);
        try {
            showNotice('Running health check...', false);
            const pageRoot = getRoot();
            const currentRunCount = pageRoot ? Number((pageRoot.querySelector('#bhTopHealthRunCount') || {}).textContent || 0) : 0;
            updateTopHealthSummary({ ran_at: 'Running...', summary: { total: 0 } }, currentRunCount);
            const apiUrl = 'index.php?page=system/backup_health&health_api=1&_ts=' + Date.now();
            const response = await fetch(apiUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const payload = await response.json();
            if (!payload || !payload.ok || !payload.report) {
                throw new Error(payload && payload.message ? payload.message : 'Health API failed.');
            }
            const tbody = renderHealthFrame(payload.report);
            await renderHealthRowsSequential(payload.report.rows || [], tbody);
            updateTopHealthSummary(payload.report, Number(payload.run_count || 0));
            showNotice('Health check completed successfully.', false);
        } catch (err) {
            showNotice('Live health check failed. Please try once again.', true);
        } finally {
            setBtnLoading(actionLink, false);
        }
    }

    let downloadFrame = document.getElementById('bhDownloadFrame');
    if (!downloadFrame) {
        downloadFrame = document.createElement('iframe');
        downloadFrame.id = 'bhDownloadFrame';
        downloadFrame.name = 'bhDownloadFrame';
        downloadFrame.style.display = 'none';
        document.body.appendChild(downloadFrame);
    }

    document.addEventListener('click', async function (e) {
        const actionLink = e.target.closest('.js-bh-health-run, a.js-bh-ajax-nav, a.js-bh-iframe-nav');
        if (!actionLink) return;

        const href = actionLink.getAttribute('href') || '';
        e.preventDefault();

        if (actionLink.classList.contains('js-bh-health-run')) {
            await runHealthCheck(actionLink);
            return;
        }

        if (!href) return;

        if (actionLink.classList.contains('js-bh-ajax-nav')) {
            const loadingText = actionLink.getAttribute('data-bh-loading') || 'Processing...';
            showNotice(loadingText, false);
            setBtnLoading(actionLink, true);
            try {
                const ajaxUrl = href + (href.indexOf('?') === -1 ? '?' : '&') + 'ajax=1&_ts=' + Date.now();
                await refreshPanel(ajaxUrl, 'Updated without full page reload.');
            } catch (err) {
                window.location.href = href;
            } finally {
                setBtnLoading(actionLink, false);
            }
            return;
        }

        if (actionLink.classList.contains('js-bh-iframe-nav')) {
            const loadingText = actionLink.getAttribute('data-bh-loading') || 'Processing...';
            showNotice(loadingText, false);
            setBtnLoading(actionLink, true);
            const needsRefresh = actionLink.getAttribute('data-bh-after-refresh') === '1';
            const notice = actionLink.getAttribute('data-bh-notice') || 'Action completed.';

            let settled = false;
            const done = async function () {
                if (settled) return;
                settled = true;
                try {
                    if (needsRefresh) {
                        await refreshPanel('index.php?page=system/backup_health&ajax=1&_ts=' + Date.now(), notice);
                    } else {
                        showNotice(notice, false);
                    }
                } catch (err) {
                    showNotice('Action finished, but live refresh failed. Please refresh page once.', true);
                } finally {
                    setBtnLoading(actionLink, false);
                }
            };
            const safetyTimer = window.setTimeout(done, 4500);
            downloadFrame.onload = async function () {
                window.clearTimeout(safetyTimer);
                await done();
            };
            downloadFrame.src = href;
        }
    });

    const pageRoot = getRoot();
    initBackupHistoryTable(pageRoot);
    if (pageRoot && pageRoot.getAttribute('data-bh-auto-run') === '1') {
        const autoBtn = pageRoot.querySelector('.js-bh-health-run');
        if (autoBtn) {
            runHealthCheck(autoBtn);
        }
        if (window.history && typeof window.history.replaceState === 'function') {
            const cleanUrl = 'index.php?page=system/backup_health';
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }
})();
</script>
