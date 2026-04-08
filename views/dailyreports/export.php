<?php
if (!defined('APP_NAME')) die('Unauthorized access.');
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$userId = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = strtolower(trim((string)($_SESSION['role_name'] ?? '')));
$isSuperAdmin = ($roleName === 'super admin');
$canAdvancedFilters = ($isSuperAdmin || $roleName === 'hr');

$today = date('Y-m-d');
$period = strtolower(trim((string)($_GET['period'] ?? 'custom')));
if (!in_array($period, ['custom','week','month'], true)) $period = 'custom';

$weekValue = trim((string)($_GET['week'] ?? date('o-\WW')));
if (!preg_match('/^\d{4}-W\d{2}$/', $weekValue)) $weekValue = date('o-\WW');

$monthValue = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $monthValue)) $monthValue = date('Y-m');

$dateFrom = trim((string)($_GET['date_from'] ?? $today));
$dateTo = trim((string)($_GET['date_to'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = $today;

if ($period === 'week' && preg_match('/^(\d{4})-W(\d{2})$/', $weekValue, $m)) {
    $y = (int)$m[1];
    $w = (int)$m[2];
    $dt = new DateTime();
    $dt->setISODate($y, $w);
    $dateFrom = $dt->format('Y-m-d');
    $dt->modify('+6 day');
    $dateTo = $dt->format('Y-m-d');
} elseif ($period === 'month' && preg_match('/^(\d{4})-(\d{2})$/', $monthValue, $m)) {
    $y = (int)$m[1];
    $mo = (int)$m[2];
    $dateFrom = sprintf('%04d-%02d-01', $y, $mo);
    $dateTo = date('Y-m-t', strtotime($dateFrom));
} else {
    if ($dateFrom > $dateTo) { $tmp = $dateFrom; $dateFrom = $dateTo; $dateTo = $tmp; }
}

$roleTypeMap = ['front office' => 'frontoffice', 'marketing' => 'marketing', 'hr' => 'hr'];
$lockedType = $roleTypeMap[$roleName] ?? 'frontoffice';
$reportType = strtolower(trim((string)($_GET['report_type'] ?? $lockedType)));
if (!in_array($reportType, ['frontoffice','marketing','hr'], true)) $reportType = $lockedType;
if (!$canAdvancedFilters) $reportType = $lockedType;

$userFilter = (int)($_GET['user_id'] ?? 0);
$exportAction = (string)($_GET['action'] ?? '');
$doExport = in_array($exportAction, ['export', 'export_xlsx'], true);

$users = [];
$rows = [];

if ($canAdvancedFilters) {
    $uq = "SELECT u.id, u.name, COALESCE(r.role_name,'-') AS role_name
           FROM users u
           LEFT JOIN roles r ON r.id = u.role_id
           WHERE u.status = 1";
    $up = [];
    if (!$isSuperAdmin && $branchId > 0) { $uq .= " AND u.branch_id = ?"; $up[] = $branchId; }
    $uq .= " ORDER BY u.name ASC";
    $st = $pdo->prepare($uq);
    $st->execute($up);
    $users = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$where = ["dm.report_date BETWEEN ? AND ?"];
$params = [$dateFrom, $dateTo];

if ($canAdvancedFilters) {
    $where[] = "dm.report_type = ?";
    $params[] = $reportType;
    if ($userFilter > 0) { $where[] = "dm.user_id = ?"; $params[] = $userFilter; }
} else {
    $where[] = "dm.user_id = ?";
    $params[] = $userId;
}
if (!$isSuperAdmin && $branchId > 0) { $where[] = "dm.branch_id = ?"; $params[] = $branchId; }

$sql = "SELECT dm.id, dm.report_date, dm.report_type, dm.status, dm.created_at,
               u.name AS user_name, COALESCE(r.role_name,'-') AS role_label, COALESCE(b.branch_name,'-') AS branch_name,
               COALESCE(act.total_collection, 0) AS total_collection_day,
               (
                 SELECT COUNT(*)
                 FROM enquiry_followups ef
                 WHERE ef.followup_date = dm.report_date
                   AND ef.created_by = dm.user_id
                   AND ef.branch_id = dm.branch_id
               ) AS total_followups_day
        FROM dailyreport_master dm
        LEFT JOIN users u ON u.id = dm.user_id
        LEFT JOIN roles r ON r.id = dm.role_id
        LEFT JOIN branches b ON b.id = dm.branch_id
        LEFT JOIN dailyreport_frontoffice_activity act ON act.master_id = dm.id
        WHERE ".implode(' AND ', $where)."
        ORDER BY dm.report_date DESC, dm.id DESC";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($doExport) {
    $isXlsx = ($exportAction === 'export_xlsx');
    $fileSafe = function($s){
        $s = strtolower(trim((string)$s));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = trim((string)$s, '_');
        return $s !== '' ? $s : 'report';
    };

    $staffPart = 'allstaff';
    if (!$canAdvancedFilters) {
        $staffPart = $fileSafe($_SESSION['user_name'] ?? ('user_' . $userId));
    } elseif ($userFilter > 0) {
        $staffPart = 'staff_' . (int)$userFilter;
        foreach ($users as $u) {
            if ((int)($u['id'] ?? 0) === $userFilter) {
                $staffPart = $fileSafe($u['name'] ?? $staffPart);
                break;
            }
        }
    }

    $filterPart = $fileSafe($period);
    $fileName = $staffPart . '_' . $filterPart . ($isXlsx ? '.xlsx' : '.csv');
    if (!$isXlsx) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
    }
    $exportRows = [];
    $csv = function(array $cells) use (&$isXlsx, &$exportRows){
        if ($isXlsx) {
            $exportRows[] = array_values($cells);
            return;
        }
        $escaped = array_map(function($v){
            $s = str_replace('"', '""', (string)$v);
            return '"' . $s . '"';
        }, $cells);
        echo implode(',', $escaped) . "\r\n";
    };

    $masterIds = array_map('intval', array_column($rows, 'id'));
    if (empty($masterIds)) {
        $csv(['No report data found for selected period.']);
        exit;
    }
    $ph = implode(',', array_fill(0, count($masterIds), '?'));

    $byDate = [];
    foreach ($rows as $r) {
        $d = (string)$r['report_date'];
        if (!isset($byDate[$d])) {
            $byDate[$d] = [
                'hourly' => [],
                'activity' => [],
                'registration' => [],
                'college' => [],
                'database' => [],
                'total_calls' => 0,
                'total_followups' => 0,
                'total_sms' => 0,
                'total_collection' => 0.0,
                'total_reg' => 0,
                'total_leave' => 0,
                'total_permission' => 0
            ];
        }
    }

    // Activity (details + totals)
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, u.name AS user_name, a.*
            FROM dailyreport_frontoffice_activity a
            INNER JOIN dailyreport_master dm ON dm.id = a.master_id
            LEFT JOIN users u ON u.id = dm.user_id
            WHERE a.master_id IN ($ph)
            ORDER BY dm.report_date ASC, u.name ASC, a.id ASC
        ");
        $q->execute($masterIds);
        $acts = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($acts as $a) {
            $d = (string)$a['report_date'];
            if (!isset($byDate[$d])) continue;
            $staff = (string)($a['user_name'] ?? '-');
            $byDate[$d]['activity'][] = $staff . ': Fresh ' . (int)$a['fresh_calls'] . ', Follow ' . (int)$a['follow_calls'] . ', SMS ' . (int)$a['messages_sent'] . ', Calls ' . (int)$a['total_calls'] . ', Reg ' . (int)$a['registration_total'] . ', Collection ' . number_format((float)$a['total_collection'],2,'.','');
            $byDate[$d]['total_calls'] += (int)($a['total_calls'] ?? 0);
            $byDate[$d]['total_followups'] += (int)($a['follow_calls'] ?? 0);
            $byDate[$d]['total_sms'] += (int)($a['messages_sent'] ?? 0);
            $byDate[$d]['total_collection'] += (float)($a['total_collection'] ?? 0);
            $byDate[$d]['total_reg'] += (int)($a['registration_total'] ?? 0);
        }
    } catch (Exception $e) {}

    // Hourly (details + leave/permission)
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, u.name AS user_name, hr.*
            FROM dailyreport_frontoffice_hourly_rows hr
            INNER JOIN dailyreport_master dm ON dm.id = hr.master_id
            LEFT JOIN users u ON u.id = dm.user_id
            WHERE hr.master_id IN ($ph)
            ORDER BY dm.report_date ASC, u.name ASC, hr.sort_order ASC, hr.id ASC
        ");
        $q->execute($masterIds);
        $hrs = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($hrs as $h) {
            $d = (string)$h['report_date'];
            if (!isset($byDate[$d])) continue;
            $staff = (string)($h['user_name'] ?? '-');
            $from = trim((string)($h['time_from'] ?? ''));
            $to = trim((string)($h['time_to'] ?? ''));
            $part = trim((string)($h['particulars'] ?? ''));
            $rem = trim((string)($h['remarks'] ?? ''));
            $txt = trim($part . ' ' . $rem);
            $byDate[$d]['hourly'][] = $staff . ': ' . $from . '-' . $to . ' ' . $part . ($rem !== '' ? ' (' . $rem . ')' : '');
            if (stripos($txt, 'leave') !== false) $byDate[$d]['total_leave']++;
            if (stripos($txt, 'permission') !== false) $byDate[$d]['total_permission']++;
        }
    } catch (Exception $e) {}

    // Registration details
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, u.name AS user_name, rr.*
            FROM dailyreport_frontoffice_registration_rows rr
            INNER JOIN dailyreport_master dm ON dm.id = rr.master_id
            LEFT JOIN users u ON u.id = dm.user_id
            WHERE rr.master_id IN ($ph)
            ORDER BY dm.report_date ASC, u.name ASC, rr.id ASC
        ");
        $q->execute($masterIds);
        $regs = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($regs as $r) {
            $d = (string)$r['report_date'];
            if (!isset($byDate[$d])) continue;
            $staff = (string)($r['user_name'] ?? '-');
            $byDate[$d]['registration'][] = $staff . ': ' . (string)$r['name'] . ' / ' . (string)$r['course'] . ' / Billing ' . number_format((float)$r['billing'],2,'.','') . ' / Collection ' . number_format((float)$r['collection_amount'],2,'.','');
        }
    } catch (Exception $e) {}

    // College follow-up details
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, u.name AS user_name, cr.*,
                   (SELECT s.status_text FROM dailyreport_frontoffice_college_followup_status s WHERE s.followup_row_id = cr.id ORDER BY s.id DESC LIMIT 1) AS status_text
            FROM dailyreport_frontoffice_college_followup_rows cr
            INNER JOIN dailyreport_master dm ON dm.id = cr.master_id
            LEFT JOIN users u ON u.id = dm.user_id
            WHERE cr.master_id IN ($ph)
            ORDER BY dm.report_date ASC, u.name ASC, cr.sort_order ASC, cr.id ASC
        ");
        $q->execute($masterIds);
        $cols = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($cols as $c) {
            $d = (string)$c['report_date'];
            if (!isset($byDate[$d])) continue;
            $staff = (string)($c['user_name'] ?? '-');
            $byDate[$d]['college'][] = $staff . ': ' . (string)$c['contact_name'] . ' / ' . (string)$c['college_name'] . ' / ' . trim((string)($c['status_text'] ?? ''));
        }
    } catch (Exception $e) {}

    // Database follow-up details
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, u.name AS user_name, dr.*,
                   (SELECT s.status_text FROM dailyreport_frontoffice_database_followup_status s WHERE s.database_row_id = dr.id ORDER BY s.id DESC LIMIT 1) AS status_text
            FROM dailyreport_frontoffice_database_followup_rows dr
            INNER JOIN dailyreport_master dm ON dm.id = dr.master_id
            LEFT JOIN users u ON u.id = dm.user_id
            WHERE dr.master_id IN ($ph)
            ORDER BY dm.report_date ASC, u.name ASC, dr.sort_order ASC, dr.id ASC
        ");
        $q->execute($masterIds);
        $dbs = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($dbs as $r) {
            $d = (string)$r['report_date'];
            if (!isset($byDate[$d])) continue;
            $staff = (string)($r['user_name'] ?? '-');
            $byDate[$d]['database'][] = $staff . ': ' . (string)$r['name'] . ' / ' . (string)$r['college'] . ' / ' . trim((string)($r['status_text'] ?? ''));
        }
    } catch (Exception $e) {}

    ksort($byDate);

    // report meta
    $reportFromDisplay = date('d-m-Y', strtotime($dateFrom));
    $reportToDisplay = date('d-m-Y', strtotime($dateTo));
    $generatedOnDisplay = date('d-m-Y H:i:s');
    $csv(['Report Period', $reportFromDisplay . ' to ' . $reportToDisplay]);
    $csv(['Report From', $reportFromDisplay]);
    $csv(['Report To', $reportToDisplay]);
    $csv(['Generated On', $generatedOnDisplay]);
    $csv([]);

    // Section 1: detailed rows
    $csv(['Date','Hourly Report','Activity Summary','Registration','College Follow Up','Database Follow Up','Total Calls','Total Followups','Total SMS','Total Collection','Total Registrations']);
    $grandCalls = 0;
    $grandFollow = 0;
    $grandSms = 0;
    $grandCollection = 0.0;
    $grandReg = 0;
    $grandLeave = 0;
    $grandPermission = 0;
    foreach ($byDate as $d => $v) {
        $dayCalls = (int)$v['total_calls'];
        $dayFollowups = (int)$v['total_followups'];
        $daySms = (int)$v['total_sms'];
        $dayCollection = (float)$v['total_collection'];
        $dayRegs = (int)$v['total_reg'];
        $dayLeave = (int)$v['total_leave'];
        $dayPermission = (int)$v['total_permission'];

        $grandCalls += (int)$v['total_calls'];
        $grandFollow += (int)$v['total_followups'];
        $grandSms += (int)$v['total_sms'];
        $grandCollection += (float)$v['total_collection'];
        $grandReg += (int)$v['total_reg'];
        $grandLeave += (int)$v['total_leave'];
        $grandPermission += (int)$v['total_permission'];

        $csv([
            date('d-m-Y', strtotime($d)),
            !empty($v['hourly']) ? implode(', ', $v['hourly']) : '-',
            !empty($v['activity']) ? implode(', ', $v['activity']) : '-',
            !empty($v['registration']) ? implode(', ', $v['registration']) : '-',
            !empty($v['college']) ? implode(', ', $v['college']) : '-',
            !empty($v['database']) ? implode(', ', $v['database']) : '-',
            $dayCalls,
            $dayFollowups,
            $daySms,
            number_format($dayCollection, 2, '.', ''),
            $dayRegs
        ]);
    }

    // Section 2: grand totals
    $csv([]);
    $csv(['Grand Totals']);
    $csv(['Metric', 'Value']);
    $csv(['Total Calls', $grandCalls]);
    $csv(['Total Followups', $grandFollow]);
    $csv(['Total SMS', $grandSms]);
    $csv(['Total Collection', number_format($grandCollection, 2, '.', '')]);
    $csv(['Total Registrations', $grandReg]);
    $csv(['Total Leave', $grandLeave > 0 ? $grandLeave : 'NILL']);
    $csv(['Total Permission', $grandPermission > 0 ? $grandPermission : 'NILL']);

    if ($isXlsx) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        // Meta section
        $sheet->setCellValue('A1', 'Report Period');
        $sheet->setCellValue('B1', $reportFromDisplay . ' to ' . $reportToDisplay);
        $sheet->setCellValue('A2', 'Report From');
        $sheet->setCellValue('B2', $reportFromDisplay);
        $sheet->setCellValue('A3', 'Report To');
        $sheet->setCellValue('B3', $reportToDisplay);
        $sheet->setCellValue('A4', 'Generated On');
        $sheet->setCellValue('B4', $generatedOnDisplay);
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);

        // Section 1: details
        $detailHeader = ['Date','Hourly Report','Activity Summary','Registration','College Follow Up','Database Follow Up','Total Calls','Total Followups','Total SMS','Total Collection','Total Registrations'];
        $sheet->fromArray($detailHeader, null, 'A6');
        $detailRow = 7;
        foreach ($byDate as $d => $v) {
            $dayCalls = (int)$v['total_calls'];
            $dayFollowups = (int)$v['total_followups'];
            $daySms = (int)$v['total_sms'];
            $dayCollection = (float)$v['total_collection'];
            $dayRegs = (int)$v['total_reg'];
            $sheet->fromArray([
                date('d-m-Y', strtotime($d)),
                !empty($v['hourly']) ? implode(', ', $v['hourly']) : '-',
                !empty($v['activity']) ? implode(', ', $v['activity']) : '-',
                !empty($v['registration']) ? implode(', ', $v['registration']) : '-',
                !empty($v['college']) ? implode(', ', $v['college']) : '-',
                !empty($v['database']) ? implode(', ', $v['database']) : '-',
                $dayCalls,
                $dayFollowups,
                $daySms,
                number_format($dayCollection, 2, '.', ''),
                $dayRegs
            ], null, 'A' . $detailRow);
            $detailRow++;
        }
        $detailLastRow = max(6, $detailRow - 1);
        $detailRange = 'A6:K' . $detailLastRow;
        $sheet->getStyle('A6:K6')->getFont()->setBold(true);
        $sheet->getStyle('A6:K6')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FDECF4');
        $sheet->getStyle($detailRange)->getAlignment()->setWrapText(true);
        $sheet->getStyle($detailRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);
        $sheet->getStyle($detailRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle($detailRange)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getColumnDimension('A')->setWidth(14);
        foreach (['B','C','D','E','F'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(45);
        }
        foreach (['G','H','I','J','K'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(18);
        }

        // Section 2: grand totals
        $summaryStart = $detailLastRow + 2;
        $sheet->setCellValue('A' . $summaryStart, 'Grand Totals');
        $sheet->getStyle('A' . $summaryStart)->getFont()->setBold(true);
        $sheet->fromArray(['Metric','Value'], null, 'A' . ($summaryStart + 1));
        $sheet->getStyle('A' . ($summaryStart + 1) . ':B' . ($summaryStart + 1))->getFont()->setBold(true);
        $sheet->fromArray(['Total Calls', $grandCalls], null, 'A' . ($summaryStart + 2));
        $sheet->fromArray(['Total Followups', $grandFollow], null, 'A' . ($summaryStart + 3));
        $sheet->fromArray(['Total SMS', $grandSms], null, 'A' . ($summaryStart + 4));
        $sheet->fromArray(['Total Collection', number_format($grandCollection, 2, '.', '')], null, 'A' . ($summaryStart + 5));
        $sheet->fromArray(['Total Registrations', $grandReg], null, 'A' . ($summaryStart + 6));
        $sheet->fromArray(['Total Leave', $grandLeave > 0 ? $grandLeave : 'NILL'], null, 'A' . ($summaryStart + 7));
        $sheet->fromArray(['Total Permission', $grandPermission > 0 ? $grandPermission : 'NILL'], null, 'A' . ($summaryStart + 8));
        $summaryRange = 'A' . ($summaryStart + 1) . ':B' . ($summaryStart + 8);
        $sheet->getStyle($summaryRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle($summaryRange)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }
    exit;
}
?>

<style>
.dre-wrap{padding:8px 0}
.dre-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.dre-title{margin:0;color:#be185d;font-size:1.5rem;font-weight:800}
.dre-note{margin:0;color:#6b7280;font-size:.9rem}
.dre-card{background:#fff;border:1px solid #f1d6e3;border-radius:14px;box-shadow:0 8px 18px rgba(0,0,0,.06);overflow:hidden;margin-bottom:12px}
.dre-body{padding:14px}
.dre-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}
.dre-grid label{display:block;font-size:.82rem;color:#6b7280;font-weight:700;margin-bottom:6px}
.dre-grid input,.dre-grid select{width:100%;border:1px solid #ecd3df;border-radius:10px;padding:8px 10px}
.dre-btn{border:none;border-radius:10px;height:38px;padding:0 14px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff}
.dre-btn-muted{background:#64748b;color:#fff;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
.dre-icon-btn{width:38px;min-width:38px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:12px}
.dre-icon-btn[data-mobile-label]{padding:0 !important;min-width:38px !important;width:38px !important}
.dre-icon-btn[data-mobile-label]::before,
.dre-icon-btn[data-mobile-label]::after{content:none !important;display:none !important}
.dre-status-icon{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:10px;border:1px solid #f1d6e3}
.dre-status-icon.is-submitted{color:#16a34a;background:#ecfdf3;border-color:#bbf7d0}
.dre-status-icon.is-draft{color:#d97706;background:#fff7ed;border-color:#fed7aa}
.dre-status-icon.is-locked{color:#475569;background:#f1f5f9;border-color:#cbd5e1}
.dre-cell-center{text-align:center;vertical-align:middle}
.dre-table{width:100%;border-collapse:collapse}
.dre-table th,.dre-table td{border:1px solid #f1d6e3;padding:8px;vertical-align:top}
.dre-table th{background:#fff4fa;color:#9d174d;font-size:.82rem}
@media(max-width:1100px){.dre-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:640px){.dre-grid{grid-template-columns:1fr;}}
</style>

<div class="dre-wrap">
  <div class="dre-head">
    <div>
      <h2 class="dre-title">Daily Report Export</h2>
      <p class="dre-note">Export daily reports as CSV or XLSX</p>
    </div>
  </div>

  <div class="dre-card">
    <div class="dre-body">
      <form method="GET" action="index.php">
        <input type="hidden" name="page" value="dailyreports/export">
        <div class="dre-grid">
          <div>
            <label>Period</label>
            <select name="period" id="drePeriod">
              <option value="custom" <?= $period==='custom'?'selected':'' ?>>Custom (From-To)</option>
              <option value="week" <?= $period==='week'?'selected':'' ?>>Week Wise</option>
              <option value="month" <?= $period==='month'?'selected':'' ?>>Month Wise</option>
            </select>
          </div>
          <div class="dre-period-custom"><label>Date From</label><input type="date" name="date_from" value="<?= h($dateFrom) ?>"></div>
          <div class="dre-period-custom"><label>Date To</label><input type="date" name="date_to" value="<?= h($dateTo) ?>"></div>
          <div class="dre-period-week"><label>Week</label><input type="week" name="week" value="<?= h($weekValue) ?>"></div>
          <div class="dre-period-month"><label>Month</label><input type="month" name="month" value="<?= h($monthValue) ?>"></div>
          <?php if ($canAdvancedFilters): ?>
          <div>
            <label>Report Type</label>
            <select name="report_type">
              <option value="frontoffice" <?= $reportType==='frontoffice'?'selected':'' ?>>Front Office</option>
              <option value="marketing" <?= $reportType==='marketing'?'selected':'' ?>>Marketing</option>
              <option value="hr" <?= $reportType==='hr'?'selected':'' ?>>HR</option>
            </select>
          </div>
          <div>
            <label>Filter by Staff</label>
            <select name="user_id">
              <option value="0">All Staff</option>
              <?php foreach($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= $userFilter===(int)$u['id']?'selected':'' ?>><?= h($u['name'].' ('.$u['role_name'].')') ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php else: ?>
            <input type="hidden" name="report_type" value="<?= h($reportType) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$userId ?>">
          <?php endif; ?>
          <div style="display:flex;align-items:flex-end;gap:8px">
            <button type="submit" class="dre-btn dre-icon-btn ui-tooltip" data-modern-tooltip="Load Reports"><i class="fas fa-filter"></i></button>
            <a class="dre-btn-muted dre-icon-btn ui-tooltip" data-modern-tooltip="Export CSV" href="index.php?page=dailyreports/export&period=<?= urlencode($period) ?>&week=<?= urlencode($weekValue) ?>&month=<?= urlencode($monthValue) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&report_type=<?= urlencode($reportType) ?>&user_id=<?= (int)$userFilter ?>&action=export"><i class="fas fa-download"></i></a>
            <a class="dre-btn dre-icon-btn ui-tooltip" data-modern-tooltip="Export Excel (XLSX)" href="index.php?page=dailyreports/export&period=<?= urlencode($period) ?>&week=<?= urlencode($weekValue) ?>&month=<?= urlencode($monthValue) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&report_type=<?= urlencode($reportType) ?>&user_id=<?= (int)$userFilter ?>&action=export_xlsx"><i class="fas fa-file-excel"></i></a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="dre-card">
    <div class="dre-body">
      <table class="dre-table" id="dailyExportTable">
        <thead>
          <tr>
            <th>S.No</th><th>Date</th><th>Day</th><th>Role</th><th>Name</th><th>Type</th><th>Status</th><th>Total Collection</th><th>Total Followups</th><th>Branch</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $idx => $r): ?>
            <?php
              $st = strtolower((string)($r['status'] ?? 'draft'));
              $statusClass = $st === 'submitted' ? 'is-submitted' : ($st === 'locked' ? 'is-locked' : 'is-draft');
              $statusIcon = $st === 'submitted' ? 'fa-check-circle' : ($st === 'locked' ? 'fa-lock' : 'fa-pen');
              $statusLabel = ucfirst($st);
            ?>
            <tr>
              <td><?= (int)($idx + 1) ?></td>
              <td><?= h($r['report_date']) ?></td>
              <td><?= h(date('l', strtotime((string)$r['report_date']))) ?></td>
              <td><?= h($r['role_label']) ?></td>
              <td><?= h($r['user_name']) ?></td>
              <td><?= h(ucfirst((string)$r['report_type'])) ?></td>
              <td class="dre-cell-center"><span class="dre-status-icon <?= h($statusClass) ?> ui-tooltip" data-modern-tooltip="<?= h($statusLabel) ?>"><i class="fas <?= h($statusIcon) ?>"></i></span></td>
              <td><?= h(number_format((float)$r['total_collection_day'], 2, '.', '')) ?></td>
              <td><?= (int)$r['total_followups_day'] ?></td>
              <td><?= h($r['branch_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const periodEl = document.getElementById('drePeriod');
  const applyPeriodUI = function(){
    const val = periodEl ? periodEl.value : 'custom';
    document.querySelectorAll('.dre-period-custom').forEach(el => el.style.display = (val === 'custom') ? '' : 'none');
    document.querySelectorAll('.dre-period-week').forEach(el => el.style.display = (val === 'week') ? '' : 'none');
    document.querySelectorAll('.dre-period-month').forEach(el => el.style.display = (val === 'month') ? '' : 'none');
  };
  if (periodEl) {
    periodEl.addEventListener('change', applyPeriodUI);
    applyPeriodUI();
  }

  if (typeof crmDataTable === 'function' && document.getElementById('dailyExportTable')) {
    crmDataTable('#dailyExportTable', {
      pageLength: 10,
      lengthMenu: [5, 10, 20, 50, 100],
      ordering: true,
      order: [[1, 'desc']],
      searchPlaceholder: 'Search export rows...',
      dom: "<'dt-top'lfB>rt<'dt-bottom'ip>"
    });
  }
});
</script>
