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
$isLoaded = ((string)($_GET['load'] ?? '') === '1');
$exportAction = (string)($_GET['action'] ?? '');
$doExport = in_array($exportAction, ['export', 'export_xlsx'], true);
$exportValidationMessage = '';
if ($doExport && !$isLoaded) {
    $doExport = false;
    $exportValidationMessage = 'Please click Load Reports before export.';
}

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

$sql = "SELECT dm.id, dm.user_id, dm.report_date, dm.report_type, dm.status, dm.created_at,
               u.name AS user_name, COALESCE(r.role_name,'-') AS role_label, COALESCE(b.branch_name,'-') AS branch_name,
               COALESCE(act.total_collection, hr_act.total_collection, mk_act.total_collection, 0) AS total_collection_day,
               COALESCE(efagg.followup_count, 0) AS total_followups_day
        FROM dailyreport_master dm
        LEFT JOIN users u ON u.id = dm.user_id
        LEFT JOIN roles r ON r.id = dm.role_id
        LEFT JOIN branches b ON b.id = dm.branch_id
        LEFT JOIN dailyreport_frontoffice_activity act ON act.master_id = dm.id
        LEFT JOIN dailyreport_hr_activity hr_act ON hr_act.master_id = dm.id
        LEFT JOIN dailyreport_marketing_activity mk_act ON mk_act.master_id = dm.id
        LEFT JOIN (
          SELECT followup_date, created_by, branch_id, COUNT(*) AS followup_count
          FROM enquiry_followups
          GROUP BY followup_date, created_by, branch_id
        ) efagg ON efagg.followup_date = dm.report_date
              AND efagg.created_by = dm.user_id
              AND efagg.branch_id = dm.branch_id
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

    $masterIds = array_map('intval', array_column($rows, 'id'));
    if (empty($masterIds)) {
        if ($isXlsx) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'No report data found for selected period.';
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            echo "\"No report data found for selected period.\"\r\n";
        }
        exit;
    }
    $ph = implode(',', array_fill(0, count($masterIds), '?'));

    $newBucket = function() {
        return [
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
    };

    $byDate = [];
    $byStaff = [];
    $staffLabelById = [];
    foreach ($rows as $r) {
        $sid = (int)($r['user_id'] ?? 0);
        $staffLabel = trim((string)($r['user_name'] ?? 'Staff ' . $sid));
        if ($staffLabel === '') $staffLabel = 'Staff ' . $sid;
        $staffLabelById[$sid] = $staffLabel;
        $d = (string)$r['report_date'];
        if (!isset($byDate[$d])) {
            $byDate[$d] = $newBucket();
        }
        if (!isset($byStaff[$sid])) {
            $byStaff[$sid] = [];
        }
        if (!isset($byStaff[$sid][$d])) {
            $byStaff[$sid][$d] = $newBucket();
        }
    }

    if ($reportType === 'hr') {
    // Activity (details + totals) - HR
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, dm.user_id, u.name AS user_name, a.*
            FROM dailyreport_hr_activity a
            INNER JOIN dailyreport_master dm ON dm.id = a.master_id
            LEFT JOIN users u ON u.id = dm.user_id
            WHERE a.master_id IN ($ph)
            ORDER BY dm.report_date ASC, u.name ASC, a.id ASC
        ");
        $q->execute($masterIds);
        $acts = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($acts as $a) {
            $d = (string)$a['report_date']; $sid = (int)($a['user_id'] ?? 0);
            if (!isset($byDate[$d])) continue;
            if (!isset($byStaff[$sid])) $byStaff[$sid] = [];
            if (!isset($byStaff[$sid][$d])) $byStaff[$sid][$d] = $newBucket();
            $staff = (string)($a['user_name'] ?? '-');
            if (!isset($staffLabelById[$sid])) $staffLabelById[$sid] = $staff !== '' ? $staff : ('Staff ' . $sid);
            $line = 'Fresh '.(int)$a['fresh_calls'].', Follow '.(int)$a['follow_calls'].', SMS '.(int)$a['messages_sent'].', Calls '.(int)$a['total_calls'].', Reg '.(int)$a['registration_total'].', Collection '.number_format((float)$a['total_collection'],2,'.','');
            $byDate[$d]['activity'][] = $staff . ': ' . $line;
            $byStaff[$sid][$d]['activity'][] = $line;
            $byDate[$d]['total_calls'] += (int)($a['total_calls'] ?? 0);
            $byDate[$d]['total_followups'] += (int)($a['follow_calls'] ?? 0);
            $byDate[$d]['total_sms'] += (int)($a['messages_sent'] ?? 0);
            $byDate[$d]['total_collection'] += (float)($a['total_collection'] ?? 0);
            $byDate[$d]['total_reg'] += (int)($a['registration_total'] ?? 0);
            $byStaff[$sid][$d]['total_calls'] += (int)($a['total_calls'] ?? 0);
            $byStaff[$sid][$d]['total_followups'] += (int)($a['follow_calls'] ?? 0);
            $byStaff[$sid][$d]['total_sms'] += (int)($a['messages_sent'] ?? 0);
            $byStaff[$sid][$d]['total_collection'] += (float)($a['total_collection'] ?? 0);
            $byStaff[$sid][$d]['total_reg'] += (int)($a['registration_total'] ?? 0);
        }
    } catch (Exception $e) {}

    try {
        $q = $pdo->prepare("SELECT dm.report_date, dm.user_id, u.name AS user_name, hr.* FROM dailyreport_hr_hourly_rows hr INNER JOIN dailyreport_master dm ON dm.id = hr.master_id LEFT JOIN users u ON u.id = dm.user_id WHERE hr.master_id IN ($ph) ORDER BY dm.report_date ASC, u.name ASC, hr.sort_order ASC, hr.id ASC");
        $q->execute($masterIds); $hrs = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($hrs as $h) {
            $d=(string)$h['report_date']; $sid=(int)($h['user_id']??0); if(!isset($byDate[$d])) continue; if(!isset($byStaff[$sid])) $byStaff[$sid]=[]; if(!isset($byStaff[$sid][$d])) $byStaff[$sid][$d]=$newBucket();
            $staff=(string)($h['user_name']??'-'); if(!isset($staffLabelById[$sid])) $staffLabelById[$sid]=$staff!==''?$staff:('Staff '.$sid);
            $from=trim((string)($h['time_from']??'')); $to=trim((string)($h['time_to']??'')); $part=trim((string)($h['particulars']??'')); $act=trim((string)($h['activities_undergone']??''));
            $txt = $from.'-'.$to.' '.$part.($act!==''?' ('.$act.')':'');
            $byDate[$d]['hourly'][] = $staff . ': ' . $txt; $byStaff[$sid][$d]['hourly'][] = $txt;
            $low = strtolower($txt); if(strpos($low,'leave')!==false){$byDate[$d]['total_leave']++; $byStaff[$sid][$d]['total_leave']++;} if(strpos($low,'permission')!==false){$byDate[$d]['total_permission']++; $byStaff[$sid][$d]['total_permission']++;}
        }
    } catch (Exception $e) {}

    $hrTableMap = [
      ['dailyreport_hr_internship_rows','registration','staff_name','topic'],
      ['dailyreport_hr_interview_rows','registration','candidate_name','interview_status'],
      ['dailyreport_hr_college_data_rows','college','contact_name','college_name'],
      ['dailyreport_hr_college_followup_rows','college','name','college'],
      ['dailyreport_hr_placement_call_rows','database','company_name','status_text'],
      ['dailyreport_hr_old_client_rows','database','client_company','followup_report'],
      ['dailyreport_hr_new_client_rows','database','company_name','status_text']
    ];
    foreach ($hrTableMap as $cfg) {
      try {
        $q = $pdo->prepare("SELECT dm.report_date, dm.user_id, u.name AS user_name, t.* FROM {$cfg[0]} t INNER JOIN dailyreport_master dm ON dm.id=t.master_id LEFT JOIN users u ON u.id=dm.user_id WHERE t.master_id IN ($ph) ORDER BY dm.report_date ASC, u.name ASC, t.id ASC");
        $q->execute($masterIds); $rows2 = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows2 as $r2) {
          $d=(string)$r2['report_date']; $sid=(int)($r2['user_id']??0); if(!isset($byDate[$d])) continue; if(!isset($byStaff[$sid])) $byStaff[$sid]=[]; if(!isset($byStaff[$sid][$d])) $byStaff[$sid][$d]=$newBucket();
          $staff=(string)($r2['user_name']??'-'); if(!isset($staffLabelById[$sid])) $staffLabelById[$sid]=$staff!==''?$staff:('Staff '.$sid);
          $left=trim((string)($r2[$cfg[2]]??'')); $right=trim((string)($r2[$cfg[3]]??''));
          if($left==='' && $right==='') continue;
          $txt = $left . ($right!=='' ? ' / '.$right : '');
          $byDate[$d][$cfg[1]][] = $staff . ': ' . $txt; $byStaff[$sid][$d][$cfg[1]][] = $txt;
        }
      } catch (Exception $e) {}
    }
    } else {
    // Activity (details + totals)
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, dm.user_id, u.name AS user_name, a.*
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
            $sid = (int)($a['user_id'] ?? 0);
            if (!isset($byDate[$d])) continue;
            if (!isset($byStaff[$sid])) $byStaff[$sid] = [];
            if (!isset($byStaff[$sid][$d])) $byStaff[$sid][$d] = $newBucket();
            $staff = (string)($a['user_name'] ?? '-');
            if (!isset($staffLabelById[$sid])) $staffLabelById[$sid] = $staff !== '' ? $staff : ('Staff ' . $sid);
            $byDate[$d]['activity'][] = $staff . ': Fresh ' . (int)$a['fresh_calls'] . ', Follow ' . (int)$a['follow_calls'] . ', SMS ' . (int)$a['messages_sent'] . ', Calls ' . (int)$a['total_calls'] . ', Reg ' . (int)$a['registration_total'] . ', Collection ' . number_format((float)$a['total_collection'],2,'.','');
            $byDate[$d]['total_calls'] += (int)($a['total_calls'] ?? 0);
            $byDate[$d]['total_followups'] += (int)($a['follow_calls'] ?? 0);
            $byDate[$d]['total_sms'] += (int)($a['messages_sent'] ?? 0);
            $byDate[$d]['total_collection'] += (float)($a['total_collection'] ?? 0);
            $byDate[$d]['total_reg'] += (int)($a['registration_total'] ?? 0);

            $byStaff[$sid][$d]['activity'][] = 'Fresh ' . (int)$a['fresh_calls'] . ', Follow ' . (int)$a['follow_calls'] . ', SMS ' . (int)$a['messages_sent'] . ', Calls ' . (int)$a['total_calls'] . ', Reg ' . (int)$a['registration_total'] . ', Collection ' . number_format((float)$a['total_collection'],2,'.','');
            $byStaff[$sid][$d]['total_calls'] += (int)($a['total_calls'] ?? 0);
            $byStaff[$sid][$d]['total_followups'] += (int)($a['follow_calls'] ?? 0);
            $byStaff[$sid][$d]['total_sms'] += (int)($a['messages_sent'] ?? 0);
            $byStaff[$sid][$d]['total_collection'] += (float)($a['total_collection'] ?? 0);
            $byStaff[$sid][$d]['total_reg'] += (int)($a['registration_total'] ?? 0);
        }
    } catch (Exception $e) {}

    // Hourly (details + leave/permission)
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, dm.user_id, u.name AS user_name, hr.*
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
            $sid = (int)($h['user_id'] ?? 0);
            if (!isset($byDate[$d])) continue;
            if (!isset($byStaff[$sid])) $byStaff[$sid] = [];
            if (!isset($byStaff[$sid][$d])) $byStaff[$sid][$d] = $newBucket();
            $staff = (string)($h['user_name'] ?? '-');
            if (!isset($staffLabelById[$sid])) $staffLabelById[$sid] = $staff !== '' ? $staff : ('Staff ' . $sid);
            $from = trim((string)($h['time_from'] ?? ''));
            $to = trim((string)($h['time_to'] ?? ''));
            $part = trim((string)($h['particulars'] ?? ''));
            $rem = trim((string)($h['remarks'] ?? ''));
            $txt = trim($part . ' ' . $rem);
            $byDate[$d]['hourly'][] = $staff . ': ' . $from . '-' . $to . ' ' . $part . ($rem !== '' ? ' (' . $rem . ')' : '');
            $byStaff[$sid][$d]['hourly'][] = $from . '-' . $to . ' ' . $part . ($rem !== '' ? ' (' . $rem . ')' : '');
            if (stripos($txt, 'leave') !== false) $byDate[$d]['total_leave']++;
            if (stripos($txt, 'permission') !== false) $byDate[$d]['total_permission']++;
            if (stripos($txt, 'leave') !== false) $byStaff[$sid][$d]['total_leave']++;
            if (stripos($txt, 'permission') !== false) $byStaff[$sid][$d]['total_permission']++;
        }
    } catch (Exception $e) {}

    // Registration details
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, dm.user_id, u.name AS user_name, rr.*
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
            $sid = (int)($r['user_id'] ?? 0);
            if (!isset($byDate[$d])) continue;
            if (!isset($byStaff[$sid])) $byStaff[$sid] = [];
            if (!isset($byStaff[$sid][$d])) $byStaff[$sid][$d] = $newBucket();
            $staff = (string)($r['user_name'] ?? '-');
            if (!isset($staffLabelById[$sid])) $staffLabelById[$sid] = $staff !== '' ? $staff : ('Staff ' . $sid);
            $byDate[$d]['registration'][] = $staff . ': ' . (string)$r['name'] . ' / ' . (string)$r['course'] . ' / Billing ' . number_format((float)$r['billing'],2,'.','') . ' / Collection ' . number_format((float)$r['collection_amount'],2,'.','');
            $byStaff[$sid][$d]['registration'][] = (string)$r['name'] . ' / ' . (string)$r['course'] . ' / Billing ' . number_format((float)$r['billing'],2,'.','') . ' / Collection ' . number_format((float)$r['collection_amount'],2,'.','');
        }
    } catch (Exception $e) {}

    // College follow-up details
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, dm.user_id, u.name AS user_name, cr.*,
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
            $sid = (int)($c['user_id'] ?? 0);
            if (!isset($byDate[$d])) continue;
            if (!isset($byStaff[$sid])) $byStaff[$sid] = [];
            if (!isset($byStaff[$sid][$d])) $byStaff[$sid][$d] = $newBucket();
            $staff = (string)($c['user_name'] ?? '-');
            if (!isset($staffLabelById[$sid])) $staffLabelById[$sid] = $staff !== '' ? $staff : ('Staff ' . $sid);
            $byDate[$d]['college'][] = $staff . ': ' . (string)$c['contact_name'] . ' / ' . (string)$c['college_name'] . ' / ' . trim((string)($c['status_text'] ?? ''));
            $byStaff[$sid][$d]['college'][] = (string)$c['contact_name'] . ' / ' . (string)$c['college_name'] . ' / ' . trim((string)($c['status_text'] ?? ''));
        }
    } catch (Exception $e) {}

    // Database follow-up details
    try {
        $q = $pdo->prepare("
            SELECT dm.report_date, dm.user_id, u.name AS user_name, dr.*,
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
            $sid = (int)($r['user_id'] ?? 0);
            if (!isset($byDate[$d])) continue;
            if (!isset($byStaff[$sid])) $byStaff[$sid] = [];
            if (!isset($byStaff[$sid][$d])) $byStaff[$sid][$d] = $newBucket();
            $staff = (string)($r['user_name'] ?? '-');
            if (!isset($staffLabelById[$sid])) $staffLabelById[$sid] = $staff !== '' ? $staff : ('Staff ' . $sid);
            $byDate[$d]['database'][] = $staff . ': ' . (string)$r['name'] . ' / ' . (string)$r['college'] . ' / ' . trim((string)($r['status_text'] ?? ''));
            $byStaff[$sid][$d]['database'][] = (string)$r['name'] . ' / ' . (string)$r['college'] . ' / ' . trim((string)($r['status_text'] ?? ''));
        }
    } catch (Exception $e) {}
    }

    ksort($byDate);
    foreach ($byStaff as $sid => $staffDates) {
        ksort($staffDates);
        $byStaff[$sid] = $staffDates;
    }

    // report meta
    $reportFromDisplay = date('d-m-Y', strtotime($dateFrom));
    $reportToDisplay = date('d-m-Y', strtotime($dateTo));
    $generatedOnDisplay = date('d-m-Y H:i:s');

    $buildCsvRows = function(array $dateBuckets) use ($reportFromDisplay, $reportToDisplay, $generatedOnDisplay): array {
        ksort($dateBuckets);
        $rowsOut = [];
        $rowsOut[] = ['Report Period', $reportFromDisplay . ' to ' . $reportToDisplay];
        $rowsOut[] = ['Report From', $reportFromDisplay];
        $rowsOut[] = ['Report To', $reportToDisplay];
        $rowsOut[] = ['Generated On', $generatedOnDisplay];
        $rowsOut[] = [];
        $rowsOut[] = ['Date','Hourly Report','Activity Summary','Registration','College Follow Up','Database Follow Up','Total Calls','Total Followups','Total SMS','Total Collection','Total Registrations'];

        $grandCalls = 0;
        $grandFollow = 0;
        $grandSms = 0;
        $grandCollection = 0.0;
        $grandReg = 0;
        $grandLeave = 0;
        $grandPermission = 0;

        foreach ($dateBuckets as $d => $v) {
            $dayCalls = (int)($v['total_calls'] ?? 0);
            $dayFollowups = (int)($v['total_followups'] ?? 0);
            $daySms = (int)($v['total_sms'] ?? 0);
            $dayCollection = (float)($v['total_collection'] ?? 0);
            $dayRegs = (int)($v['total_reg'] ?? 0);
            $dayLeave = (int)($v['total_leave'] ?? 0);
            $dayPermission = (int)($v['total_permission'] ?? 0);

            $grandCalls += $dayCalls;
            $grandFollow += $dayFollowups;
            $grandSms += $daySms;
            $grandCollection += $dayCollection;
            $grandReg += $dayRegs;
            $grandLeave += $dayLeave;
            $grandPermission += $dayPermission;

            $rowsOut[] = [
                date('d-m-Y', strtotime((string)$d)),
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
            ];
        }

        $rowsOut[] = [];
        $rowsOut[] = ['Grand Totals'];
        $rowsOut[] = ['Metric', 'Value'];
        $rowsOut[] = ['Total Calls', $grandCalls];
        $rowsOut[] = ['Total Followups', $grandFollow];
        $rowsOut[] = ['Total SMS', $grandSms];
        $rowsOut[] = ['Total Collection', number_format($grandCollection, 2, '.', '')];
        $rowsOut[] = ['Total Registrations', $grandReg];
        $rowsOut[] = ['Total Leave', $grandLeave > 0 ? $grandLeave : 'NILL'];
        $rowsOut[] = ['Total Permission', $grandPermission > 0 ? $grandPermission : 'NILL'];
        return $rowsOut;
    };

    $csvLine = function(array $cells): string {
        $escaped = array_map(function($v){
            $s = str_replace('"', '""', (string)$v);
            return '"' . $s . '"';
        }, $cells);
        return implode(',', $escaped) . "\r\n";
    };

    $csvStringFromRows = function(array $rowsData) use ($csvLine): string {
        $out = '';
        foreach ($rowsData as $line) $out .= $csvLine($line);
        return $out;
    };

    if (!$isXlsx) {
        $staffIdsForCsv = array_keys(array_filter($byStaff, function($d){ return !empty($d); }));
        if (count($staffIdsForCsv) > 1 && class_exists('ZipArchive')) {
            $zipPath = tempnam(sys_get_temp_dir(), 'dre_zip_');
            $zip = new ZipArchive();
            if ($zipPath !== false && $zip->open($zipPath, ZipArchive::OVERWRITE) === true) {
                $usedNames = [];
                $safeFileName = function($name) use (&$usedNames, $fileSafe) {
                    $base = $fileSafe($name);
                    $n = $base !== '' ? $base : 'staff';
                    $i = 2;
                    while (isset($usedNames[$n])) {
                        $n = $base . '_' . $i;
                        $i++;
                    }
                    $usedNames[$n] = true;
                    return $n;
                };

                foreach ($staffIdsForCsv as $sid) {
                    $staffLabel = (string)($staffLabelById[$sid] ?? ('Staff ' . $sid));
                    $rowsData = $buildCsvRows($byStaff[$sid]);
                    $zip->addFromString($safeFileName($staffLabel) . '_' . $filterPart . '.csv', $csvStringFromRows($rowsData));
                }
                $zip->close();

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $staffPart . '_' . $filterPart . '.zip"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                @unlink($zipPath);
                exit;
            }
        }

        $targetDates = $byDate;
        if (count($staffIdsForCsv) === 1) {
            $singleSid = (int)$staffIdsForCsv[0];
            $targetDates = $byStaff[$singleSid] ?? $byDate;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $csvStringFromRows($buildCsvRows($targetDates));
        exit;
    }

    if ($isXlsx) {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheetNameSanitize = function(string $name): string {
            $name = trim($name);
            if ($name === '') $name = 'Staff';
            $name = preg_replace('/[\\\\\\/?*:\\[\\]]+/', '_', $name);
            $name = trim((string)$name);
            if ($name === '') $name = 'Staff';
            if (strlen($name) > 31) $name = substr($name, 0, 31);
            return $name;
        };
        $usedSheetNames = [];
        $uniqueSheetName = function(string $base) use (&$usedSheetNames, $sheetNameSanitize): string {
            $base = $sheetNameSanitize($base);
            $name = $base;
            $i = 2;
            while (isset($usedSheetNames[strtolower($name)])) {
                $suffix = ' ' . $i;
                $cut = 31 - strlen($suffix);
                $name = substr($base, 0, max(1, $cut)) . $suffix;
                $i++;
            }
            $usedSheetNames[strtolower($name)] = true;
            return $name;
        };

        $staffIds = array_keys($byStaff);
        usort($staffIds, function($a, $b) use ($staffLabelById){
            $la = strtolower((string)($staffLabelById[$a] ?? ('staff '.$a)));
            $lb = strtolower((string)($staffLabelById[$b] ?? ('staff '.$b)));
            return $la <=> $lb;
        });
        if (empty($staffIds)) $staffIds = [0];

        $detailHeader = ['Date','Hourly Report','Activity Summary','Registration','College Follow Up','Database Follow Up','Total Calls','Total Followups','Total SMS','Total Collection','Total Registrations'];

        foreach ($staffIds as $sheetIdx => $sid) {
            $sheet = ($sheetIdx === 0) ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $staffLabel = (string)($staffLabelById[$sid] ?? ('Staff ' . $sid));
            $sheet->setTitle($uniqueSheetName($staffLabel));

            $staffDates = $byStaff[$sid] ?? [];
            ksort($staffDates);

            $sheet->setCellValue('A1', 'Staff Name');
            $sheet->setCellValue('B1', $staffLabel);
            $sheet->setCellValue('A2', 'Report Period');
            $sheet->setCellValue('B2', $reportFromDisplay . ' to ' . $reportToDisplay);
            $sheet->setCellValue('A3', 'Report From');
            $sheet->setCellValue('B3', $reportFromDisplay);
            $sheet->setCellValue('A4', 'Report To');
            $sheet->setCellValue('B4', $reportToDisplay);
            $sheet->setCellValue('A5', 'Generated On');
            $sheet->setCellValue('B5', $generatedOnDisplay);
            $sheet->getStyle('A1:A5')->getFont()->setBold(true);

            $sheet->fromArray($detailHeader, null, 'A7');
            $detailRow = 8;
            $sGrandCalls = 0;
            $sGrandFollow = 0;
            $sGrandSms = 0;
            $sGrandCollection = 0.0;
            $sGrandReg = 0;
            $sGrandLeave = 0;
            $sGrandPermission = 0;

            foreach ($staffDates as $d => $v) {
                $dayCalls = (int)$v['total_calls'];
                $dayFollowups = (int)$v['total_followups'];
                $daySms = (int)$v['total_sms'];
                $dayCollection = (float)$v['total_collection'];
                $dayRegs = (int)$v['total_reg'];
                $dayLeave = (int)$v['total_leave'];
                $dayPermission = (int)$v['total_permission'];

                $sGrandCalls += $dayCalls;
                $sGrandFollow += $dayFollowups;
                $sGrandSms += $daySms;
                $sGrandCollection += $dayCollection;
                $sGrandReg += $dayRegs;
                $sGrandLeave += $dayLeave;
                $sGrandPermission += $dayPermission;

                $sheet->fromArray([
                    date('d-m-Y', strtotime((string)$d)),
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

            $detailLastRow = max(7, $detailRow - 1);
            $detailRange = 'A7:K' . $detailLastRow;
            $sheet->getStyle('A7:K7')->getFont()->setBold(true);
            $sheet->getStyle('A7:K7')->getFill()
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

            $summaryStart = $detailLastRow + 2;
            $sheet->setCellValue('A' . $summaryStart, 'Grand Totals');
            $sheet->getStyle('A' . $summaryStart)->getFont()->setBold(true);
            $sheet->fromArray(['Metric','Value'], null, 'A' . ($summaryStart + 1));
            $sheet->getStyle('A' . ($summaryStart + 1) . ':B' . ($summaryStart + 1))->getFont()->setBold(true);
            $sheet->fromArray(['Total Calls', $sGrandCalls], null, 'A' . ($summaryStart + 2));
            $sheet->fromArray(['Total Followups', $sGrandFollow], null, 'A' . ($summaryStart + 3));
            $sheet->fromArray(['Total SMS', $sGrandSms], null, 'A' . ($summaryStart + 4));
            $sheet->fromArray(['Total Collection', number_format($sGrandCollection, 2, '.', '')], null, 'A' . ($summaryStart + 5));
            $sheet->fromArray(['Total Registrations', $sGrandReg], null, 'A' . ($summaryStart + 6));
            $sheet->fromArray(['Total Leave', $sGrandLeave > 0 ? $sGrandLeave : 'NILL'], null, 'A' . ($summaryStart + 7));
            $sheet->fromArray(['Total Permission', $sGrandPermission > 0 ? $sGrandPermission : 'NILL'], null, 'A' . ($summaryStart + 8));
            $summaryRange = 'A' . ($summaryStart + 1) . ':B' . ($summaryStart + 8);
            $sheet->getStyle($summaryRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            $sheet->getStyle($summaryRange)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        }

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
        <input type="hidden" name="load" value="1">
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
            <a id="exportCsvBtn" class="dre-btn-muted dre-icon-btn ui-tooltip" data-modern-tooltip="Export CSV" href="index.php?page=dailyreports/export&period=<?= urlencode($period) ?>&week=<?= urlencode($weekValue) ?>&month=<?= urlencode($monthValue) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&report_type=<?= urlencode($reportType) ?>&user_id=<?= (int)$userFilter ?>&load=<?= $isLoaded ? '1' : '0' ?>&action=export"><i class="fas fa-download"></i></a>
            <a id="exportXlsxBtn" class="dre-btn dre-icon-btn ui-tooltip" data-modern-tooltip="Export Excel (XLSX)" href="index.php?page=dailyreports/export&period=<?= urlencode($period) ?>&week=<?= urlencode($weekValue) ?>&month=<?= urlencode($monthValue) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>&report_type=<?= urlencode($reportType) ?>&user_id=<?= (int)$userFilter ?>&load=<?= $isLoaded ? '1' : '0' ?>&action=export_xlsx"><i class="fas fa-file-excel"></i></a>
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
(function(){
function init(){
  function drAjaxSwap(url){
    const main = document.querySelector('.main-content');
    if(!main){ window.location.href = url; return; }
    const u = new URL(url, window.location.href);
    u.searchParams.set('ajax', '1');
    main.innerHTML = '<div class="dre-card"><div class="dre-body"><div class="dre-note">Loading...</div></div></div>';
    fetch(u.toString(), { headers: { 'X-Requested-With':'XMLHttpRequest' } })
      .then(function(r){ return r.text(); })
      .then(function(html){
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        main.innerHTML = tmp.innerHTML;
        const scripts = Array.from(main.querySelectorAll('script'));
        scripts.forEach(function(old){
          const s = document.createElement('script');
          if (old.src) { s.src = old.src; s.async = false; } else { s.textContent = old.textContent; }
          document.body.appendChild(s);
          old.remove();
          setTimeout(function(){ try { s.remove(); } catch(e) {} }, 0);
        });
        window.history.replaceState({}, '', url);
      })
      .catch(function(){ window.location.href = url; });
  }

  const isLoaded = <?= $isLoaded ? 'true' : 'false' ?>;
  const hasRows = <?= !empty($rows) ? 'true' : 'false' ?>;
  const showExportAlert = function(message){
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'warning',
        title: 'Validation',
        text: message || 'Please click Load Reports before export.',
        confirmButtonText: 'OK'
      });
    } else {
      alert(message || 'Please click Load Reports before export.');
    }
  };

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

  const filterForm = document.querySelector('.dre-card form[action="index.php"]');
  if (filterForm) {
    filterForm.addEventListener('submit', function(e){
      e.preventDefault();
      const fd = new FormData(filterForm);
      const p = new URLSearchParams();
      fd.forEach(function(v,k){ p.append(k, v); });
      drAjaxSwap('index.php?' + p.toString());
    });
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

  const bindExportValidation = function(id){
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('click', function(ev){
      if (!isLoaded) {
        ev.preventDefault();
        showExportAlert('Please click Load Reports before export.');
        return;
      }
      if (!hasRows) {
        ev.preventDefault();
        showExportAlert('No report data found. Please load valid records before export.');
      }
    });
  };

  bindExportValidation('exportCsvBtn');
  bindExportValidation('exportXlsxBtn');

  <?php if ($exportValidationMessage !== ''): ?>
  showExportAlert(<?= json_encode($exportValidationMessage) ?>);
  <?php endif; ?>
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
})();
</script>
