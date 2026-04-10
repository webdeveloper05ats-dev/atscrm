<?php
if (!defined('APP_NAME')) die('Unauthorized access.');
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('drFmtDateTime')) {
    function drFmtDateTime($v){
        $s = trim((string)$v);
        if ($s === '' || $s === '0000-00-00 00:00:00') return '';
        $ts = strtotime($s);
        if ($ts === false) return $s;
        return date('d-m-Y h:i A', $ts);
    }
}
if (!function_exists('drFmtTime12')) {
    function drFmtTime12($v){
        $s = trim((string)$v);
        if ($s === '') return '';
        $ts = strtotime($s);
        if ($ts === false) return $s;
        return date('h:i A', $ts);
    }
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = strtolower(trim((string)($_SESSION['role_name'] ?? '')));
$isSuperAdmin = ($roleName === 'super admin');
$isFrontOffice = ($roleName === 'front office');
$canDownload = ($isSuperAdmin || $roleName === 'hr');
$canAdvancedFilters = ($isSuperAdmin || $roleName === 'hr');

$today = date('Y-m-d');
$reportDate = trim((string)($_GET['report_date'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportDate)) $reportDate = $today;

$roleTypeMap = [
    'front office' => 'frontoffice',
    'marketing' => 'marketing',
    'hr' => 'hr'
];
$lockedType = $roleTypeMap[$roleName] ?? 'frontoffice';

$typeFilter = strtolower(trim((string)($_GET['report_type'] ?? $lockedType)));
if (!in_array($typeFilter, ['frontoffice','marketing','hr'], true)) $typeFilter = $lockedType;
if (!$canAdvancedFilters) $typeFilter = $lockedType;

$userFilter = (int)($_GET['user_id'] ?? 0);
$masterIdFilter = (int)($_GET['master_id'] ?? 0);

$hasMasterTable = function_exists('crmTableExists') ? crmTableExists($pdo, 'dailyreport_master') : false;
$reportRows = [];
$selectedMaster = null;
$users = [];

function drViewCsvRow(array $cells): string {
    $escaped = array_map(function($v){
        $s = (string)$v;
        $s = str_replace('"', '""', $s);
        return '"' . $s . '"';
    }, $cells);
    return implode(',', $escaped) . "\r\n";
}

function drRenderViewDetails($selectedMaster, $activity, $registrationRows, $plannerRows, $hourlyRows, $collegeRows, $dbRows): string {
    ob_start();
    if (!$selectedMaster) {
        echo '<div class="drv-card"><div class="drv-body"><div class="drv-blank">Click <b>View</b> in the table to load report sections.</div></div></div>';
        return ob_get_clean();
    }
    $viewDate = (string)($selectedMaster['report_date'] ?? '');
    $viewDay = $viewDate !== '' ? date('l', strtotime($viewDate)) : '-';
    ?>
    <div class="drv-card"><div class="drv-body"><div class="drv-context">
      <span class="drv-chip"><i class="fas fa-calendar-day"></i> Date: <?= h($viewDate) ?></span>
      <span class="drv-chip"><i class="fas fa-clock"></i> Day: <?= h($viewDay) ?></span>
    </div></div></div>
    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">Activity Summary</h4>
    <?php if (!$activity): ?><div class="drv-blank">No activity data.</div><?php else: ?>
    <div class="drv-table-wrap"><table class="drv-table"><tbody>
      <tr><th>Fresh Calls</th><td><?= h($activity['fresh_calls']) ?></td><th>Follow Calls</th><td><?= h($activity['follow_calls']) ?></td><th>Messages</th><td><?= h($activity['messages_sent']) ?></td></tr>
      <tr><th>Mails</th><td><?= h($activity['mails_sent']) ?></td><th>Total Calls</th><td><?= h($activity['total_calls']) ?></td><th>Promotions</th><td><?= h($activity['promotions']) ?></td></tr>
      <tr><th>Reference</th><td><?= h($activity['reference_count']) ?></td><th>DB Calls</th><td><?= h($activity['db_calls']) ?></td><th>Registration Total</th><td><?= h($activity['registration_total']) ?></td></tr>
      <tr><th>Billing</th><td><?= h($activity['billing']) ?></td><th>Fresh Collection</th><td><?= h($activity['fresh_collection']) ?></td><th>Old Collection</th><td><?= h($activity['old_collection']) ?></td></tr>
      <tr><th>Total Collection</th><td><?= h($activity['total_collection']) ?></td><th>Walkins</th><td><?= h($activity['walkins']) ?></td><th>Conversion Ratio</th><td><?= h($activity['conversion_ratio']) ?>%</td></tr>
    </tbody></table></div>
    <?php endif; ?></div></div>

    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">Registration</h4>
    <?php if (empty($registrationRows)): ?><div class="drv-blank">No registration rows.</div><?php else: ?>
      <div class="drv-table-wrap"><table class="drv-table"><thead><tr><th>S.No</th><th>Name</th><th>Department</th><th>Contact</th><th>College</th><th>Date</th><th>Course</th><th>Billing</th><th>Collection</th><th>Balance</th><th>Mode</th></tr></thead><tbody>
        <?php foreach($registrationRows as $r): ?><tr><td><?= h($r['serial_no']) ?></td><td><?= h($r['name']) ?></td><td><?= h($r['department']) ?></td><td><?= h($r['contact_no']) ?></td><td><?= h($r['college']) ?></td><td><?= h($r['date_of_registration']) ?></td><td><?= h($r['course']) ?></td><td><?= h($r['billing']) ?></td><td><?= h($r['collection_amount']) ?></td><td><?= h($r['balance_amount']) ?></td><td><?= h($r['payment_mode']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?></div></div>

    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">Planner</h4>
    <?php if (empty($plannerRows)): ?><div class="drv-blank">No planner rows.</div><?php else: ?>
      <div class="drv-table-wrap"><table class="drv-table"><thead><tr><th>Time Slot</th><th>Activity</th><th>Description</th></tr></thead><tbody><?php foreach($plannerRows as $r): ?><tr><td><?= h($r['time_slot']) ?></td><td><?= h($r['activity']) ?></td><td><?= h($r['description']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?></div></div>

    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">Hourly Report</h4>
    <?php if (empty($hourlyRows)): ?><div class="drv-blank">No hourly rows.</div><?php else: ?>
      <div class="drv-table-wrap"><table class="drv-table"><thead><tr><th>From</th><th>To</th><th>Particulars</th><th>Remarks</th></tr></thead><tbody><?php foreach($hourlyRows as $r): ?><tr><td><?= h($r['time_from']) ?></td><td><?= h($r['time_to']) ?></td><td><?= h($r['particulars']) ?></td><td><?= h($r['remarks']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?></div></div>

    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">College Follow Up</h4>
    <?php if (empty($collegeRows)): ?><div class="drv-blank">No college follow up rows.</div><?php else: ?>
      <div class="drv-table-wrap"><table class="drv-table"><thead><tr><th>S.No</th><th>Name</th><th>Designation</th><th>Email</th><th>Contact</th><th>College</th><th>Location</th><th>Status Date</th><th>Status</th></tr></thead><tbody><?php foreach($collegeRows as $r): ?><tr><td><?= h($r['serial_no']) ?></td><td><?= h($r['contact_name']) ?></td><td><?= h($r['designation']) ?></td><td><?= h($r['email']) ?></td><td><?= h($r['contact_no']) ?></td><td><?= h($r['college_name']) ?></td><td><?= h($r['location']) ?></td><td><?= h($r['status_date']) ?></td><td><?= h($r['status_text']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?></div></div>

    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">Database Follow Up</h4>
    <?php if (empty($dbRows)): ?><div class="drv-blank">No database follow up rows.</div><?php else: ?>
      <div class="drv-table-wrap"><table class="drv-table"><thead><tr><th>S.No</th><th>Name</th><th>Department</th><th>College</th><th>Mobile</th><th>Status Date</th><th>Status</th></tr></thead><tbody><?php foreach($dbRows as $r): ?><tr><td><?= h($r['serial_no']) ?></td><td><?= h($r['name']) ?></td><td><?= h($r['department']) ?></td><td><?= h($r['college']) ?></td><td><?= h($r['mobile']) ?></td><td><?= h($r['status_date']) ?></td><td><?= h($r['status_text']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?></div></div>
    <?php
    return ob_get_clean();
}

function drRenderHrViewDetails($selectedMaster, $activity, $hourlyRows, $internRows, $interviewRows, $placementRows, $oldClientRows, $newClientRows, $collegeDataRows, $collegeFollowRows): string {
    ob_start();
    if (!$selectedMaster) {
        echo '<div class="drv-card"><div class="drv-body"><div class="drv-blank">Click <b>View</b> in the table to load report sections.</div></div></div>';
        return ob_get_clean();
    }
    $viewDate = (string)($selectedMaster['report_date'] ?? '');
    $viewDay = $viewDate !== '' ? date('l', strtotime($viewDate)) : '-';
    ?>
    <div class="drv-card"><div class="drv-body"><div class="drv-context">
      <span class="drv-chip"><i class="fas fa-calendar-day"></i> Date: <?= h($viewDate) ?></span>
      <span class="drv-chip"><i class="fas fa-clock"></i> Day: <?= h($viewDay) ?></span>
      <span class="drv-chip"><i class="fas fa-user"></i> HR Report</span>
    </div></div></div>
    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">Activity Summary</h4>
      <div class="drv-table-wrap"><table class="drv-table"><tbody>
        <tr><th>Fresh Calls</th><td><?= h($activity['fresh_calls'] ?? 0) ?></td><th>Follow Calls</th><td><?= h($activity['follow_calls'] ?? 0) ?></td><th>Messages</th><td><?= h($activity['messages_sent'] ?? 0) ?></td></tr>
        <tr><th>Mails</th><td><?= h($activity['mails_sent'] ?? 0) ?></td><th>Forum Posting</th><td><?= h($activity['forum_posting'] ?? 0) ?></td><th>Total Calls</th><td><?= h($activity['total_calls'] ?? 0) ?></td></tr>
        <tr><th>Promotions</th><td><?= h($activity['promotions'] ?? 0) ?></td><th>Reference</th><td><?= h($activity['reference_count'] ?? 0) ?></td><th>DB Calls</th><td><?= h($activity['db_calls'] ?? 0) ?></td></tr>
        <tr><th>Total Collection</th><td><?= h($activity['total_collection'] ?? '0.00') ?></td><th>Walkins</th><td><?= h($activity['walkins'] ?? 0) ?></td><th>Conversion Ratio</th><td><?= h($activity['conversion_ratio'] ?? '0.00') ?>%</td></tr>
      </tbody></table></div>
    </div></div>
    <?php
    $sections = [
        'Hourly Report' => $hourlyRows,
        'Internship' => $internRows,
        'Interview Data' => $interviewRows,
        'Placement Calls' => $placementRows,
        'Old Clients' => $oldClientRows,
        'New Clients' => $newClientRows,
        'College Data' => $collegeDataRows,
        'College Follow Up' => $collegeFollowRows
    ];
    foreach ($sections as $title => $rows): ?>
      <div class="drv-card"><div class="drv-body"><h4 class="drv-sec"><?= h($title) ?></h4>
      <?php if (empty($rows)): ?><div class="drv-blank">No rows.</div>
      <?php else:
        $ignoreKeys = ['id','master_id','sort_order','serial_no'];
        for($i=1;$i<=12;$i++){
            $ignoreKeys[] = 'day_'.$i;
            $ignoreKeys[] = 'date_'.$i;
            $ignoreKeys[] = 'topic_'.$i;
        }
        $visibleKeys = [];
        foreach(array_keys($rows[0]) as $k){
            if (!in_array($k, $ignoreKeys, true)) $visibleKeys[] = $k;
        }
      ?><div class="drv-table-wrap"><table class="drv-table"><thead><tr><th>S.No</th><?php foreach($visibleKeys as $k): ?><th><?= h(ucwords(str_replace('_',' ',$k))) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach($rows as $idx => $r): ?><tr><td><?= (int)$idx + 1 ?></td><?php foreach($visibleKeys as $k): ?><td><?php $cell = (string)($r[$k] ?? ''); if ($k === 'created_at' || $k === 'updated_at') $cell = drFmtDateTime($cell); if ($k === 'time_from' || $k === 'time_to') $cell = drFmtTime12($cell); ?><?= h($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
      </div></div>
    <?php endforeach;
    return ob_get_clean();
}

function drRenderMarketingViewDetails($selectedMaster, $activity, $hourlyRows, $collegeRows, $prospectRows, $actRows, $amountRows, $programRows, $artsCollegeRows, $artsPcRows, $enggCollegeRows, $enggPcRows, $polytechRows): string {
    ob_start();
    if (!$selectedMaster) {
        echo '<div class="drv-card"><div class="drv-body"><div class="drv-blank">Click <b>View</b> in the table to load report sections.</div></div></div>';
        return ob_get_clean();
    }
    $viewDate = (string)($selectedMaster['report_date'] ?? '');
    $viewDay = $viewDate !== '' ? date('l', strtotime($viewDate)) : '-';
    ?>
    <div class="drv-card"><div class="drv-body"><div class="drv-context">
      <span class="drv-chip"><i class="fas fa-calendar-day"></i> Date: <?= h($viewDate) ?></span>
      <span class="drv-chip"><i class="fas fa-clock"></i> Day: <?= h($viewDay) ?></span>
      <span class="drv-chip"><i class="fas fa-bullhorn"></i> Marketing Report</span>
    </div></div></div>
    <div class="drv-card"><div class="drv-body"><h4 class="drv-sec">Activity Summary</h4>
      <div class="drv-table-wrap"><table class="drv-table"><tbody>
        <tr><th>Fresh Calls</th><td><?= h($activity['fresh_calls'] ?? 0) ?></td><th>Follow Calls</th><td><?= h($activity['follow_calls'] ?? 0) ?></td><th>Messages</th><td><?= h($activity['messages_sent'] ?? 0) ?></td></tr>
        <tr><th>Mails</th><td><?= h($activity['mails_sent'] ?? 0) ?></td><th>Forum Posting</th><td><?= h($activity['forum_posting'] ?? 0) ?></td><th>Total Calls</th><td><?= h($activity['total_calls'] ?? 0) ?></td></tr>
        <tr><th>Promotions</th><td><?= h($activity['promotions'] ?? 0) ?></td><th>Reference</th><td><?= h($activity['reference_count'] ?? 0) ?></td><th>DB Calls</th><td><?= h($activity['db_calls'] ?? 0) ?></td></tr>
        <tr><th>Total Collection</th><td><?= h($activity['total_collection'] ?? '0.00') ?></td><th>Walkins</th><td><?= h($activity['walkins'] ?? 0) ?></td><th>Conversion Ratio</th><td><?= h($activity['conversion_ratio'] ?? '0.00') ?>%</td></tr>
      </tbody></table></div>
    </div></div>
    <?php
    $sections = [
        'Hourly Report' => $hourlyRows,
        'Colleges' => $collegeRows,
        'Prospect' => $prospectRows,
        'Act Report' => $actRows,
        'Amount' => $amountRows,
        'Programs' => $programRows,
        'Arts Colleges' => $artsCollegeRows,
        'Arts PC' => $artsPcRows,
        'Engg Colleges' => $enggCollegeRows,
        'Engg PC' => $enggPcRows,
        'Polytech Colleges' => $polytechRows
    ];
    foreach ($sections as $title => $rows): ?>
      <div class="drv-card"><div class="drv-body"><h4 class="drv-sec"><?= h($title) ?></h4>
      <?php if (empty($rows)): ?><div class="drv-blank">No rows.</div>
      <?php else:
        $ignoreKeys = ['id','master_id','sort_order'];
        $visibleKeys = [];
        foreach(array_keys($rows[0]) as $k){
            if (!in_array($k, $ignoreKeys, true)) $visibleKeys[] = $k;
        }
      ?><div class="drv-table-wrap"><table class="drv-table"><thead><tr><th>S.No</th><?php foreach($visibleKeys as $k): ?><th><?= h(ucwords(str_replace('_',' ',$k))) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach($rows as $idx => $r): ?><tr><td><?= (int)$idx + 1 ?></td><?php foreach($visibleKeys as $k): ?><td><?php $cell = (string)($r[$k] ?? ''); if ($k === 'created_at' || $k === 'updated_at') $cell = drFmtDateTime($cell); if ($k === 'time_from' || $k === 'time_to') $cell = drFmtTime12($cell); ?><?= h($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
      </div></div>
    <?php endforeach;
    return ob_get_clean();
}

if ($hasMasterTable) {
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

    $where = [];
    $params = [];
    if ($canAdvancedFilters) {
        $where[] = "dm.report_date = ?";
        $params[] = $reportDate;
        $where[] = "dm.report_type = ?";
        $params[] = $typeFilter;
        if ($userFilter > 0) { $where[] = "dm.user_id = ?"; $params[] = $userFilter; }
    } else {
        $where[] = "dm.user_id = ?";
        $params[] = $userId;
    }

    // Hide unsaved drafts from view list.
    $where[] = "dm.status IN ('submitted','locked')";

    if (!$isSuperAdmin && $branchId > 0) { $where[] = "dm.branch_id = ?"; $params[] = $branchId; }

    $sql = "SELECT dm.*, u.name AS user_name, COALESCE(r.role_name,'-') AS role_label, COALESCE(b.branch_name,'-') AS branch_name,
                   COALESCE(act.total_collection, hr_act.total_collection, mk_act.total_collection, 0) AS total_collection_day,
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
            LEFT JOIN dailyreport_hr_activity hr_act ON hr_act.master_id = dm.id
            LEFT JOIN dailyreport_marketing_activity mk_act ON mk_act.master_id = dm.id
            WHERE ".implode(' AND ', $where)."
            ORDER BY dm.id DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $reportRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($masterIdFilter > 0) {
        foreach ($reportRows as $r) {
            if ((int)$r['id'] === $masterIdFilter) { $selectedMaster = $r; break; }
        }
    }
}

$activity = null;
$registrationRows = [];
$plannerRows = [];
$hourlyRows = [];
$collegeRows = [];
$dbRows = [];
$hrInternRows = [];
$hrInterviewRows = [];
$hrPlacementRows = [];
$hrOldClientRows = [];
$hrNewClientRows = [];
$hrCollegeDataRows = [];
$hrCollegeFollowRows = [];
$mkCollegeRows = [];
$mkProspectRows = [];
$mkActRows = [];
$mkAmountRows = [];
$mkProgramRows = [];
$mkArtsCollegeRows = [];
$mkArtsPcRows = [];
$mkEnggCollegeRows = [];
$mkEnggPcRows = [];
$mkPolytechRows = [];

if ($selectedMaster) {
    $masterId = (int)$selectedMaster['id'];
    $isHrReport = strtolower((string)($selectedMaster['report_type'] ?? '')) === 'hr';
    $isMarketingReport = strtolower((string)($selectedMaster['report_type'] ?? '')) === 'marketing';

    if ($isHrReport && function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_hr_activity')) {
        $q = $pdo->prepare("SELECT * FROM dailyreport_hr_activity WHERE master_id = ? LIMIT 1");
        $q->execute([$masterId]);
        $activity = $q->fetch(PDO::FETCH_ASSOC) ?: null;

        $map = [
            'dailyreport_hr_hourly_rows' => ['hourlyRows', 'sort_order ASC, id ASC'],
            'dailyreport_hr_internship_rows' => ['hrInternRows', 'id ASC'],
            'dailyreport_hr_interview_rows' => ['hrInterviewRows', 'sort_order ASC, id ASC'],
            'dailyreport_hr_placement_call_rows' => ['hrPlacementRows', 'sort_order ASC, id ASC'],
            'dailyreport_hr_old_client_rows' => ['hrOldClientRows', 'id ASC'],
            'dailyreport_hr_new_client_rows' => ['hrNewClientRows', 'sort_order ASC, id ASC'],
            'dailyreport_hr_college_data_rows' => ['hrCollegeDataRows', 'id ASC'],
            'dailyreport_hr_college_followup_rows' => ['hrCollegeFollowRows', 'sort_order ASC, id ASC']
        ];
        foreach ($map as $table => $cfg) {
            if (function_exists('crmTableExists') && crmTableExists($pdo, $table)) {
                $q = $pdo->prepare("SELECT * FROM {$table} WHERE master_id = ? ORDER BY {$cfg[1]}");
                $q->execute([$masterId]);
                ${$cfg[0]} = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } elseif ($isMarketingReport && function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_marketing_activity')) {
        $q = $pdo->prepare("SELECT * FROM dailyreport_marketing_activity WHERE master_id = ? LIMIT 1");
        $q->execute([$masterId]);
        $activity = $q->fetch(PDO::FETCH_ASSOC) ?: null;

        $mkMap = [
            'dailyreport_marketing_hourly_rows' => ['hourlyRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_colleges_rows' => ['mkCollegeRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_prospect_rows' => ['mkProspectRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_act_report_rows' => ['mkActRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_amount_rows' => ['mkAmountRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_program_rows' => ['mkProgramRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_arts_college_rows' => ['mkArtsCollegeRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_arts_pc_rows' => ['mkArtsPcRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_engg_college_rows' => ['mkEnggCollegeRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_engg_pc_rows' => ['mkEnggPcRows', 'sort_order ASC, id ASC'],
            'dailyreport_marketing_polytech_college_rows' => ['mkPolytechRows', 'sort_order ASC, id ASC']
        ];
        foreach ($mkMap as $table => $cfg) {
            if (function_exists('crmTableExists') && crmTableExists($pdo, $table)) {
                $q = $pdo->prepare("SELECT * FROM {$table} WHERE master_id = ? ORDER BY {$cfg[1]}");
                $q->execute([$masterId]);
                ${$cfg[0]} = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
        if (!empty($mkProspectRows) && function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_marketing_prospect_status_rows')) {
            $ids = array_map(function($r){ return (int)($r['id'] ?? 0); }, $mkProspectRows);
            $ids = array_values(array_filter($ids));
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $q = $pdo->prepare("SELECT prospect_row_id, status_date, status_text, remarks FROM dailyreport_marketing_prospect_status_rows WHERE prospect_row_id IN ($ph) ORDER BY prospect_row_id ASC, sort_order ASC, id ASC");
                $q->execute($ids);
                $tmp = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $map = [];
                foreach($tmp as $r){ $map[(int)$r['prospect_row_id']][] = trim((string)$r['status_date']).' '.trim((string)$r['status_text']).' '.trim((string)$r['remarks']); }
                foreach($mkProspectRows as &$r){
                    $id=(int)($r['id'] ?? 0);
                    $r['status_timeline'] = isset($map[$id]) ? implode(' | ', array_filter($map[$id])) : '';
                }
                unset($r);
            }
        }
    } elseif (function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_frontoffice_activity')) {
        $q = $pdo->prepare("SELECT * FROM dailyreport_frontoffice_activity WHERE master_id = ? LIMIT 1");
        $q->execute([$masterId]);
        $activity = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$isHrReport && !$isMarketingReport) {
        if (function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_frontoffice_registration_rows')) {
            $q = $pdo->prepare("SELECT * FROM dailyreport_frontoffice_registration_rows WHERE master_id = ? ORDER BY id ASC");
            $q->execute([$masterId]);
            $registrationRows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_frontoffice_planner_rows')) {
            $q = $pdo->prepare("SELECT * FROM dailyreport_frontoffice_planner_rows WHERE master_id = ? ORDER BY sort_order ASC, id ASC");
            $q->execute([$masterId]);
            $plannerRows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_frontoffice_hourly_rows')) {
            $q = $pdo->prepare("SELECT * FROM dailyreport_frontoffice_hourly_rows WHERE master_id = ? ORDER BY sort_order ASC, id ASC");
            $q->execute([$masterId]);
            $hourlyRows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_frontoffice_college_followup_rows')) {
            $q = $pdo->prepare("
                SELECT c.*,
                       (SELECT s.status_date FROM dailyreport_frontoffice_college_followup_status s WHERE s.followup_row_id = c.id ORDER BY s.id DESC LIMIT 1) AS status_date,
                       (SELECT s.status_text FROM dailyreport_frontoffice_college_followup_status s WHERE s.followup_row_id = c.id ORDER BY s.id DESC LIMIT 1) AS status_text
                FROM dailyreport_frontoffice_college_followup_rows c
                WHERE c.master_id = ?
                ORDER BY c.sort_order ASC, c.id ASC
            ");
            $q->execute([$masterId]);
            $collegeRows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        if (function_exists('crmTableExists') && crmTableExists($pdo, 'dailyreport_frontoffice_database_followup_rows')) {
            $q = $pdo->prepare("
                SELECT d.*,
                       (SELECT s.status_date FROM dailyreport_frontoffice_database_followup_status s WHERE s.database_row_id = d.id ORDER BY s.id DESC LIMIT 1) AS status_date,
                       (SELECT s.status_text FROM dailyreport_frontoffice_database_followup_status s WHERE s.database_row_id = d.id ORDER BY s.id DESC LIMIT 1) AS status_text
                FROM dailyreport_frontoffice_database_followup_rows d
                WHERE d.master_id = ?
                ORDER BY d.sort_order ASC, d.id ASC
            ");
            $q->execute([$masterId]);
            $dbRows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
}

if ($canDownload && isset($_GET['action']) && $_GET['action'] === 'download' && $selectedMaster) {
    $fname = 'daily_report_' . preg_replace('/[^0-9\-]/', '', (string)$selectedMaster['report_date']) . '_id' . (int)$selectedMaster['id'] . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');

    $out = '';
    $out .= drViewCsvRow(['Daily Report Export']);
    $out .= drViewCsvRow(['Report ID', $selectedMaster['id']]);
    $out .= drViewCsvRow(['Date', $selectedMaster['report_date']]);
    $out .= drViewCsvRow(['User', $selectedMaster['user_name'] ?? '-']);
    $out .= drViewCsvRow(['Role', $selectedMaster['role_label'] ?? '-']);
    $out .= drViewCsvRow(['Type', $selectedMaster['report_type'] ?? '-']);
    $out .= drViewCsvRow(['Status', $selectedMaster['status'] ?? '-']);
    $out .= drViewCsvRow([]);

    $out .= drViewCsvRow(['Activity Summary']);
    if ($activity) {
        foreach ($activity as $k => $v) $out .= drViewCsvRow([$k, $v]);
    } else {
        $out .= drViewCsvRow(['No activity data']);
    }
    $out .= drViewCsvRow([]);

    $isHrDownload = strtolower((string)($selectedMaster['report_type'] ?? '')) === 'hr';
    $isMarketingDownload = strtolower((string)($selectedMaster['report_type'] ?? '')) === 'marketing';
    if ($isHrDownload) {
        $hrSets = [
            'Hourly Report' => $hourlyRows,
            'Internship' => $hrInternRows,
            'Interview Data' => $hrInterviewRows,
            'Placement Calls' => $hrPlacementRows,
            'Old Clients' => $hrOldClientRows,
            'New Clients' => $hrNewClientRows,
            'College Data' => $hrCollegeDataRows,
            'College Follow Up' => $hrCollegeFollowRows
        ];
        foreach ($hrSets as $title => $rows) {
            $out .= drViewCsvRow([$title]);
            if ($rows) {
                $out .= drViewCsvRow(array_keys($rows[0]));
                foreach ($rows as $r) $out .= drViewCsvRow(array_values($r));
            } else {
                $out .= drViewCsvRow(['No rows']);
            }
            $out .= drViewCsvRow([]);
        }
        echo $out;
        exit;
    }
    if ($isMarketingDownload) {
        $mkSets = [
            'Hourly Report' => $hourlyRows,
            'Colleges' => $mkCollegeRows,
            'Prospect' => $mkProspectRows,
            'Act Report' => $mkActRows,
            'Amount' => $mkAmountRows,
            'Programs' => $mkProgramRows,
            'Arts Colleges' => $mkArtsCollegeRows,
            'Arts PC' => $mkArtsPcRows,
            'Engg Colleges' => $mkEnggCollegeRows,
            'Engg PC' => $mkEnggPcRows,
            'Polytech Colleges' => $mkPolytechRows
        ];
        foreach ($mkSets as $title => $rows) {
            $out .= drViewCsvRow([$title]);
            if ($rows) {
                $out .= drViewCsvRow(array_keys($rows[0]));
                foreach ($rows as $r) $out .= drViewCsvRow(array_values($r));
            } else {
                $out .= drViewCsvRow(['No rows']);
            }
            $out .= drViewCsvRow([]);
        }
        echo $out;
        exit;
    }

    $out .= drViewCsvRow(['Registration']);
    $out .= drViewCsvRow(['S.No','Name','Department','Contact','College','Date','Course','Billing','Collection','Balance','Mode']);
    if ($registrationRows) {
        foreach ($registrationRows as $r) {
            $out .= drViewCsvRow([$r['serial_no'],$r['name'],$r['department'],$r['contact_no'],$r['college'],$r['date_of_registration'],$r['course'],$r['billing'],$r['collection_amount'],$r['balance_amount'],$r['payment_mode']]);
        }
    } else {
        $out .= drViewCsvRow(['No registration rows']);
    }
    $out .= drViewCsvRow([]);

    $out .= drViewCsvRow(['Planner']);
    $out .= drViewCsvRow(['Time Slot','Activity','Description']);
    if ($plannerRows) {
        foreach ($plannerRows as $r) $out .= drViewCsvRow([$r['time_slot'],$r['activity'],$r['description']]);
    } else $out .= drViewCsvRow(['No planner rows']);
    $out .= drViewCsvRow([]);

    $out .= drViewCsvRow(['Hourly Report']);
    $out .= drViewCsvRow(['From','To','Particulars','Remarks']);
    if ($hourlyRows) {
        foreach ($hourlyRows as $r) $out .= drViewCsvRow([$r['time_from'],$r['time_to'],$r['particulars'],$r['remarks']]);
    } else $out .= drViewCsvRow(['No hourly rows']);
    $out .= drViewCsvRow([]);

    $out .= drViewCsvRow(['College Follow Up']);
    $out .= drViewCsvRow(['S.No','Name','Designation','Email','Contact','College','Location','Status Date','Status']);
    if ($collegeRows) {
        foreach ($collegeRows as $r) $out .= drViewCsvRow([$r['serial_no'],$r['contact_name'],$r['designation'],$r['email'],$r['contact_no'],$r['college_name'],$r['location'],$r['status_date'],$r['status_text']]);
    } else $out .= drViewCsvRow(['No college follow up rows']);
    $out .= drViewCsvRow([]);

    $out .= drViewCsvRow(['Database Follow Up']);
    $out .= drViewCsvRow(['S.No','Name','Department','College','Mobile','Status Date','Status']);
    if ($dbRows) {
        foreach ($dbRows as $r) $out .= drViewCsvRow([$r['serial_no'],$r['name'],$r['department'],$r['college'],$r['mobile'],$r['status_date'],$r['status_text']]);
    } else $out .= drViewCsvRow(['No database follow up rows']);

    echo $out;
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'details') {
    $isHrAjax = $selectedMaster && strtolower((string)($selectedMaster['report_type'] ?? '')) === 'hr';
    $isMkAjax = $selectedMaster && strtolower((string)($selectedMaster['report_type'] ?? '')) === 'marketing';
    echo $isHrAjax
        ? drRenderHrViewDetails($selectedMaster, $activity, $hourlyRows, $hrInternRows, $hrInterviewRows, $hrPlacementRows, $hrOldClientRows, $hrNewClientRows, $hrCollegeDataRows, $hrCollegeFollowRows)
        : ($isMkAjax
            ? drRenderMarketingViewDetails($selectedMaster, $activity, $hourlyRows, $mkCollegeRows, $mkProspectRows, $mkActRows, $mkAmountRows, $mkProgramRows, $mkArtsCollegeRows, $mkArtsPcRows, $mkEnggCollegeRows, $mkEnggPcRows, $mkPolytechRows)
            : drRenderViewDetails($selectedMaster, $activity, $registrationRows, $plannerRows, $hourlyRows, $collegeRows, $dbRows));
    exit;
}
?>

<style>
.drv-wrap{padding:8px 0}
.drv-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.drv-title{margin:0;color:#be185d;font-size:1.5rem;font-weight:800}
.drv-note{margin:0;color:#6b7280;font-size:.9rem}
.drv-card{background:#fff;border:1px solid #f1d6e3;border-radius:14px;box-shadow:0 8px 18px rgba(0,0,0,.06);overflow:hidden;margin-bottom:12px}
.drv-body{padding:14px}
.drv-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
.drv-grid input,.drv-grid select{width:100%;border:1px solid #ecd3df;border-radius:10px;padding:8px 10px}
.drv-grid label{display:block;font-size:.82rem;color:#6b7280;font-weight:700;margin-bottom:6px}
.drv-btn{border:none;border-radius:10px;height:38px;padding:0 14px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff}
.drv-btn-muted{background:#64748b;color:#fff}
.drv-icon-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:10px;text-decoration:none}
.drv-status-icon{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;border:1px solid #f1d6e3;background:#fff;cursor:default}
.drv-status-icon.is-submitted{color:#16a34a;background:#ecfdf3;border-color:#bbf7d0}
.drv-status-icon.is-draft{color:#d97706;background:#fff7ed;border-color:#fed7aa}
.drv-status-icon.is-locked{color:#475569;background:#f1f5f9;border-color:#cbd5e1}
.drv-status-cell,.drv-action-cell{text-align:center;vertical-align:middle}
.drv-action-wrap{display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:nowrap}
.drv-table-wrap{overflow:auto}
.drv-table{width:100%;border-collapse:collapse}
.drv-table th,.drv-table td{border:1px solid #f1d6e3;padding:8px;vertical-align:top}
.drv-table th{background:#fff4fa;color:#9d174d;font-size:.82rem}
.drv-sec{font-weight:800;color:#be185d;margin:0 0 8px 0}
.drv-blank{padding:14px;border:1px dashed #e9b8cf;border-radius:12px;background:#fff8fc;color:#9d174d}
.drv-context{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.drv-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid #f1d6e3;background:#fff7fb;color:#9d174d;font-size:.82rem;font-weight:700}
@media(max-width:1100px){.drv-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:640px){.drv-grid{grid-template-columns:1fr;}}
</style>

<div class="drv-wrap">
  <div class="drv-head">
    <div>
      <h2 class="drv-title">Daily Report View</h2>
      <p class="drv-note">Filter and review saved daily reports</p>
    </div>
  </div>

  <?php if (!$hasMasterTable): ?>
    <div class="drv-card"><div class="drv-body"><div class="drv-blank">Daily report tables are not available in database.</div></div></div>
  <?php else: ?>
    <?php if ($canAdvancedFilters): ?>
    <div class="drv-card">
      <div class="drv-body">
        <form method="GET" action="index.php">
          <input type="hidden" name="page" value="dailyreports/view">
          <div class="drv-grid">
            <div><label>Report Date</label><input type="date" name="report_date" value="<?= h($reportDate) ?>"></div>
            <div>
              <label>Report Type</label>
                <select name="report_type">
                  <option value="frontoffice" <?= $typeFilter==='frontoffice'?'selected':'' ?>>Front Office</option>
                  <option value="marketing" <?= $typeFilter==='marketing'?'selected':'' ?>>Marketing</option>
                  <option value="hr" <?= $typeFilter==='hr'?'selected':'' ?>>HR</option>
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
            <div style="display:flex;align-items:flex-end"><button type="submit" class="drv-btn">Load Reports</button></div>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="drv-card">
      <div class="drv-body">
        <h4 class="drv-sec">Saved Reports</h4>
        <?php if (empty($reportRows)): ?>
          <div class="drv-blank">No report found for selected filters.</div>
        <?php else: ?>
          <div class="drv-table-wrap">
            <table class="drv-table" id="savedReportsTable">
              <thead><tr><th>S.No</th><th>Date</th><th>Role</th><th>Name</th><th>Total Collection</th><th>Total Followups</th><th>Status</th><th>Action</th></tr></thead>
              <tbody>
              <?php foreach($reportRows as $idx => $r): ?>
                <?php
                  $st = strtolower((string)($r['status'] ?? 'draft'));
                  $statusClass = $st === 'submitted' ? 'is-submitted' : ($st === 'locked' ? 'is-locked' : 'is-draft');
                  $statusIcon = $st === 'submitted' ? 'fa-check-circle' : ($st === 'locked' ? 'fa-lock' : 'fa-pen');
                  $statusLabel = ucfirst($st);
                ?>
                <tr>
                  <td><?= (int)($idx + 1) ?></td>
                  <td><?= h($r['report_date']) ?></td>
                  <td><?= h($r['role_label'] ?? '-') ?></td>
                  <td><?= h($r['user_name'] ?? '-') ?></td>
                  <td><?= h(number_format((float)($r['total_collection_day'] ?? 0), 2, '.', '')) ?></td>
                  <td><?= (int)($r['total_followups_day'] ?? 0) ?></td>
                  <td class="drv-status-cell"><span class="drv-status-icon <?= h($statusClass) ?> ui-tooltip" data-modern-tooltip="<?= h($statusLabel) ?>"><i class="fas <?= h($statusIcon) ?>"></i></span></td>
                  <td class="drv-action-cell">
                    <div class="drv-action-wrap">
                      <a class="drv-btn drv-icon-btn ui-tooltip js-view-report" data-modern-tooltip="View Report" href="index.php?page=dailyreports/view&report_date=<?= urlencode($reportDate) ?>&report_type=<?= urlencode($typeFilter) ?>&user_id=<?= (int)$userFilter ?>&master_id=<?= (int)$r['id'] ?>"><i class="fas fa-eye"></i></a>
                      <?php if ($canDownload): ?>
                        <a class="drv-btn drv-btn-muted drv-icon-btn ui-tooltip" data-modern-tooltip="Download Report" href="index.php?page=dailyreports/view&report_date=<?= urlencode($reportDate) ?>&report_type=<?= urlencode($typeFilter) ?>&user_id=<?= (int)$userFilter ?>&master_id=<?= (int)$r['id'] ?>&action=download"><i class="fas fa-download"></i></a>
                      <?php else: ?>
                        <a class="drv-btn drv-btn-muted drv-icon-btn ui-tooltip" data-modern-tooltip="Send Email" href="mailto:?subject=Daily%20Report%20<?= rawurlencode((string)$r['report_date']) ?>%20-%20<?= rawurlencode((string)($r['user_name'] ?? '')) ?>&body=Please%20find%20the%20daily%20report.%0A%0AView%20Link:%20<?= rawurlencode('index.php?page=dailyreports/view&report_date='.$reportDate.'&report_type='.$typeFilter.'&user_id='.$userFilter.'&master_id='.(int)$r['id']) ?>"><i class="fas fa-paper-plane"></i></a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div id="reportDetailContainer">
      <?php
        $isHrRender = $selectedMaster && strtolower((string)($selectedMaster['report_type'] ?? '')) === 'hr';
        $isMkRender = $selectedMaster && strtolower((string)($selectedMaster['report_type'] ?? '')) === 'marketing';
      ?>
      <?= $isHrRender
        ? drRenderHrViewDetails($selectedMaster, $activity, $hourlyRows, $hrInternRows, $hrInterviewRows, $hrPlacementRows, $hrOldClientRows, $hrNewClientRows, $hrCollegeDataRows, $hrCollegeFollowRows)
        : ($isMkRender
          ? drRenderMarketingViewDetails($selectedMaster, $activity, $hourlyRows, $mkCollegeRows, $mkProspectRows, $mkActRows, $mkAmountRows, $mkProgramRows, $mkArtsCollegeRows, $mkArtsPcRows, $mkEnggCollegeRows, $mkEnggPcRows, $mkPolytechRows)
          : drRenderViewDetails($selectedMaster, $activity, $registrationRows, $plannerRows, $hourlyRows, $collegeRows, $dbRows)) ?>
    </div>
  <?php endif; ?>
</div>

<script>
(function(){
function init(){
  const root = document.querySelector('.drv-wrap') || document;
  function drAjaxSwap(url){
    const main = document.querySelector('.main-content');
    if(!main){ window.location.href = url; return; }
    const u = new URL(url, window.location.href);
    u.searchParams.set('ajax', '1');
    main.innerHTML = '<div class="drv-card"><div class="drv-body"><div class="drv-blank">Loading...</div></div></div>';
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
  const filterForm = document.querySelector('.drv-card form[action="index.php"]');
  if (filterForm) {
    filterForm.addEventListener('submit', function(e){
      e.preventDefault();
      const fd = new FormData(filterForm);
      const p = new URLSearchParams();
      fd.forEach(function(v,k){ p.append(k, v); });
      drAjaxSwap('index.php?' + p.toString());
    });
  }
  if (typeof crmDataTable === 'function' && document.getElementById('savedReportsTable')) {
    crmDataTable('#savedReportsTable', {
      pageLength: 10,
      lengthMenu: [5, 10, 20, 50, 100],
      ordering: true,
      order: [[1, 'desc']],
      columnDefs: [
        { targets: 0, orderable: false, searchable: false }
      ],
      searchPlaceholder: 'Search daily reports...',
      dom: "<'dt-top'lfB>rt<'dt-bottom'ip>",
      language: {
        emptyTable: 'No report found.'
      }
    });

    if (window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#savedReportsTable')) {
      const dt = jQuery('#savedReportsTable').DataTable();
      const renumber = function () {
        const info = dt.page.info();
        dt.column(0, { search: 'applied', order: 'applied', page: 'current' }).nodes().each(function (cell, i) {
          cell.innerHTML = info.start + i + 1;
        });
      };
      dt.on('draw.dt order.dt search.dt page.dt', renumber);
      renumber();
    }
  }

  root.addEventListener('click', function(e){
    const link = e.target.closest('.js-view-report');
    if (!link) return;
    e.preventDefault();
    const container = document.getElementById('reportDetailContainer');
    if (!container) {
      window.location.href = link.href;
      return;
    }
    const u = new URL(link.href, window.location.href);
    u.searchParams.set('ajax', 'details');
    container.innerHTML = '<div class="drv-card"><div class="drv-body"><div class="drv-blank">Loading report...</div></div></div>';
    fetch(u.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r){ return r.text(); })
      .then(function(html){
        container.innerHTML = html;
        if (window.initializeFloatingTooltips) window.initializeFloatingTooltips(container);
        window.history.replaceState({}, '', link.href);
      })
      .catch(function(){
        window.location.href = link.href;
      });
  });
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
})();
</script>
