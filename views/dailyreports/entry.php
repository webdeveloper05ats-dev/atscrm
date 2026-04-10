
<?php
if (!defined('APP_NAME')) die('Unauthorized access.');
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$__drRoleName = strtolower(trim((string)($_SESSION['role_name'] ?? '')));
$__drRequestedType = strtolower(trim((string)($_GET['report_type'] ?? '')));
if ($__drRoleName === 'hr' || ($__drRoleName === 'super admin' && $__drRequestedType === 'hr')) {
  require __DIR__ . '/entry_hr.php';
  return;
}
if ($__drRoleName === 'marketing' || ($__drRoleName === 'super admin' && $__drRequestedType === 'marketing')) {
  require __DIR__ . '/entry_marketing.php';
  return;
}

function drInt($v){ return max(0, (int)$v); }
function drDec($v){ return number_format((float)$v, 2, '.', ''); }
function drText($v){ return trim((string)$v); }
function drDateOrNull($v){ $v=trim((string)$v); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:null; }

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0);
$branchId = (int)($_SESSION['branch_id'] ?? 0);
$roleName = strtolower(trim((string)($_SESSION['role_name'] ?? '')));
$canUseFrontOfficeForm = ($roleName === 'front office' || $roleName === 'super admin');

$requiredTables = [
  'dailyreport_master','dailyreport_frontoffice_activity','dailyreport_frontoffice_registration_rows','dailyreport_frontoffice_planner_rows',
  'dailyreport_frontoffice_hourly_rows','dailyreport_frontoffice_college_followup_rows','dailyreport_frontoffice_college_followup_status',
  'dailyreport_frontoffice_database_followup_rows','dailyreport_frontoffice_database_followup_status'
];
$missingTables = [];
foreach($requiredTables as $t){ if(!function_exists('crmTableExists') || !crmTableExists($pdo,$t)) $missingTables[]=$t; }
$drWarningMessage = '';
$drSuccessMessage = '';
if (function_exists('getFlash')) {
  $flashSuccess = getFlash('success');
  if ($flashSuccess !== null) $drSuccessMessage = (string)$flashSuccess;
}
if (!empty($missingTables)) {
  $drWarningMessage = 'Missing table(s): '.implode(', ', $missingTables).'.';
} elseif (!$canUseFrontOfficeForm) {
  $drWarningMessage = 'Only Front Office (or Super Admin for testing) can use this form.';
}

$today = date('Y-m-d');
$reportDate = trim((string)($_GET['report_date'] ?? $today));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportDate)) $reportDate = $today;
$isToday = ($reportDate === $today);
$isSaveRequest = ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_all_report']));
$todayTs = strtotime($today . ' 00:00:00');
$reportTs = strtotime($reportDate . ' 00:00:00');
$dayDiff = (int)(($todayTs - $reportTs) / 86400); // 0=today, 1=yesterday, 2=two days back
$isBackdateWithin2 = ($dayDiff >= 1 && $dayDiff <= 2);
$isAllowedEntryDate = ($dayDiff === 0 || $isBackdateWithin2);

$activity = [
  'fresh_calls'=>0,'follow_calls'=>0,'messages_sent'=>0,'mails_sent'=>0,'total_calls'=>0,
  'promotions'=>0,'reference_count'=>0,'db_calls'=>0,'registration_total'=>0,
  'billing'=>'0.00','fresh_collection'=>'0.00','old_collection'=>'0.00','total_collection'=>'0.00','walkins'=>0,'conversion_ratio'=>'0.00'
];
$registrationRows = [['serial_no'=>'','name'=>'','department'=>'','contact_no'=>'','college'=>'','date_of_registration'=>'','course'=>'','billing'=>'0.00','collection_amount'=>'0.00','balance_amount'=>'0.00','payment_mode'=>'']];
$registrationRowsLoadedFromSaved = false;
$plannerRows = [['time_slot'=>'09:30 - 10:30','activity'=>'','description'=>'']];
$hourlyRows = [['time_from'=>'09:30','time_to'=>'10:30','particulars'=>'','remarks'=>'']];
$collegeRows = [['serial_no'=>'','contact_name'=>'','designation'=>'','email'=>'','contact_no'=>'','college_name'=>'','location'=>'','status_date'=>$reportDate,'status_text'=>'']];
$dbRows = [['serial_no'=>'','name'=>'','department'=>'','college'=>'','mobile'=>'','status_date'=>$reportDate,'status_text'=>'']];
$dbRowsLoadedFromSaved = false;

if (isset($_GET['ajax']) && $_GET['ajax'] === 'fo_college_lookup') {
  header('Content-Type: application/json; charset=utf-8');
  if (!$canUseFrontOfficeForm) { echo json_encode(['ok'=>false,'rows'=>[]]); exit; }
  $q = trim((string)($_GET['q'] ?? ''));
  if ($q === '') { echo json_encode(['ok'=>true,'rows'=>[]]); exit; }
  $like = '%'.$q.'%';
  $stmt = $pdo->prepare("
    SELECT
      c.id AS row_id, dm.report_date, c.serial_no, c.contact_name, c.designation, c.email, c.contact_no, c.college_name, c.location,
      (SELECT status_date FROM dailyreport_frontoffice_college_followup_status s WHERE s.followup_row_id=c.id ORDER BY s.id DESC LIMIT 1) AS status_date,
      (SELECT status_text FROM dailyreport_frontoffice_college_followup_status s WHERE s.followup_row_id=c.id ORDER BY s.id DESC LIMIT 1) AS status_text
    FROM dailyreport_frontoffice_college_followup_rows c
    INNER JOIN dailyreport_master dm ON dm.id = c.master_id
    WHERE dm.report_type='frontoffice'
      AND dm.user_id=?
      AND dm.branch_id=?
      AND (c.contact_name LIKE ? OR c.college_name LIKE ? OR c.contact_no LIKE ? OR c.location LIKE ?)
    ORDER BY dm.report_date DESC, c.id DESC
    LIMIT 30
  ");
  $stmt->execute([$userId,$branchId,$like,$like,$like,$like]);
  echo json_encode(['ok'=>true,'rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []], JSON_UNESCAPED_UNICODE);
  exit;
}
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fo_db_lookup') {
  header('Content-Type: application/json; charset=utf-8');
  if (!$canUseFrontOfficeForm) { echo json_encode(['ok'=>false,'rows'=>[]]); exit; }
  $q = trim((string)($_GET['q'] ?? ''));
  if ($q === '') { echo json_encode(['ok'=>true,'rows'=>[]]); exit; }
  $like = '%'.$q.'%';
  $stmt = $pdo->prepare("
    SELECT
      d.id AS row_id, dm.report_date, d.serial_no, d.name, d.department, d.college, d.mobile,
      (SELECT status_date FROM dailyreport_frontoffice_database_followup_status s WHERE s.database_row_id=d.id ORDER BY s.id DESC LIMIT 1) AS status_date,
      (SELECT status_text FROM dailyreport_frontoffice_database_followup_status s WHERE s.database_row_id=d.id ORDER BY s.id DESC LIMIT 1) AS status_text
    FROM dailyreport_frontoffice_database_followup_rows d
    INNER JOIN dailyreport_master dm ON dm.id = d.master_id
    WHERE dm.report_type='frontoffice'
      AND dm.user_id=?
      AND dm.branch_id=?
      AND (d.name LIKE ? OR d.department LIKE ? OR d.college LIKE ? OR d.mobile LIKE ?)
    ORDER BY dm.report_date DESC, d.id DESC
    LIMIT 30
  ");
  $stmt->execute([$userId,$branchId,$like,$like,$like,$like]);
  echo json_encode(['ok'=>true,'rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []], JSON_UNESCAPED_UNICODE);
  exit;
}

$master = null; $isEditable = false;
if(empty($missingTables) && $canUseFrontOfficeForm){
  $st = $pdo->prepare("SELECT * FROM dailyreport_master WHERE report_date=? AND user_id=? AND report_type='frontoffice' LIMIT 1");
  $st->execute([$reportDate,$userId]);
  $master = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  if(!$master && $isAllowedEntryDate){
    $ins = $pdo->prepare("INSERT INTO dailyreport_master(report_date,role_id,user_id,branch_id,report_type,status,created_at,updated_at) VALUES(?,?,?,?,'frontoffice','draft',NOW(),NOW())");
    $ins->execute([$reportDate,$roleId,$userId,$branchId]);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare("SELECT * FROM dailyreport_master WHERE id=? LIMIT 1");
    $st->execute([$id]);
    $master = $st->fetch(PDO::FETCH_ASSOC) ?: null;
  }
  if($master){
    $masterId = (int)$master['id'];
    $statusLower = strtolower((string)($master['status'] ?? 'draft'));
    if($isToday){
      // Same-day reports can be edited multiple times until manually locked.
      $isEditable = ($statusLower !== 'locked');
    }elseif($isBackdateWithin2){
      // Backdate (up to 2 days) is one-time entry: editable only while draft.
      $isEditable = ($statusLower === 'draft');
    }else{
      $isEditable = false;
    }

    // Performance optimization:
    // on save requests, skip heavy read queries because we immediately write and redirect.
    if(!$isSaveRequest){
      $q=$pdo->prepare("SELECT * FROM dailyreport_frontoffice_activity WHERE master_id=? LIMIT 1");$q->execute([$masterId]);$r=$q->fetch(PDO::FETCH_ASSOC);
      if($r){ foreach($activity as $k=>$v){ if(array_key_exists($k,$r)) $activity[$k]=$r[$k]; } }

      $q=$pdo->prepare("SELECT * FROM dailyreport_frontoffice_registration_rows WHERE master_id=? ORDER BY id ASC");$q->execute([$masterId]);$tmp=$q->fetchAll(PDO::FETCH_ASSOC);
      if($tmp){ $registrationRows=[]; $registrationRowsLoadedFromSaved = true; foreach($tmp as $r){ $registrationRows[]=['serial_no'=>(string)$r['serial_no'],'name'=>(string)$r['name'],'department'=>(string)$r['department'],'contact_no'=>(string)$r['contact_no'],'college'=>(string)$r['college'],'date_of_registration'=>(string)$r['date_of_registration'],'course'=>(string)$r['course'],'billing'=>(string)$r['billing'],'collection_amount'=>(string)$r['collection_amount'],'balance_amount'=>(string)$r['balance_amount'],'payment_mode'=>(string)$r['payment_mode']]; } }

      $q=$pdo->prepare("SELECT * FROM dailyreport_frontoffice_planner_rows WHERE master_id=? ORDER BY sort_order,id ASC");$q->execute([$masterId]);$tmp=$q->fetchAll(PDO::FETCH_ASSOC);
      if($tmp){ $plannerRows=[]; foreach($tmp as $r){ $plannerRows[]=['time_slot'=>(string)$r['time_slot'],'activity'=>(string)$r['activity'],'description'=>(string)$r['description']]; } }

      $q=$pdo->prepare("SELECT * FROM dailyreport_frontoffice_hourly_rows WHERE master_id=? ORDER BY sort_order,id ASC");$q->execute([$masterId]);$tmp=$q->fetchAll(PDO::FETCH_ASSOC);
      if($tmp){ $hourlyRows=[]; foreach($tmp as $r){ $hourlyRows[]=['time_from'=>(string)$r['time_from'],'time_to'=>(string)$r['time_to'],'particulars'=>(string)$r['particulars'],'remarks'=>(string)$r['remarks']]; } }

      $q=$pdo->prepare("SELECT c.*, (SELECT status_date FROM dailyreport_frontoffice_college_followup_status s WHERE s.followup_row_id=c.id ORDER BY s.id DESC LIMIT 1) AS status_date, (SELECT status_text FROM dailyreport_frontoffice_college_followup_status s WHERE s.followup_row_id=c.id ORDER BY s.id DESC LIMIT 1) AS status_text FROM dailyreport_frontoffice_college_followup_rows c WHERE c.master_id=? ORDER BY c.sort_order,c.id ASC");$q->execute([$masterId]);$tmp=$q->fetchAll(PDO::FETCH_ASSOC);
      if($tmp){ $collegeRows=[]; foreach($tmp as $r){ $collegeRows[]=['serial_no'=>(string)$r['serial_no'],'contact_name'=>(string)$r['contact_name'],'designation'=>(string)$r['designation'],'email'=>(string)$r['email'],'contact_no'=>(string)$r['contact_no'],'college_name'=>(string)$r['college_name'],'location'=>(string)$r['location'],'status_date'=>(string)($r['status_date'] ?? $reportDate),'status_text'=>(string)($r['status_text'] ?? '')]; } }

      $q=$pdo->prepare("SELECT d.*, (SELECT status_date FROM dailyreport_frontoffice_database_followup_status s WHERE s.database_row_id=d.id ORDER BY s.id DESC LIMIT 1) AS status_date, (SELECT status_text FROM dailyreport_frontoffice_database_followup_status s WHERE s.database_row_id=d.id ORDER BY s.id DESC LIMIT 1) AS status_text FROM dailyreport_frontoffice_database_followup_rows d WHERE d.master_id=? ORDER BY d.sort_order,d.id ASC");$q->execute([$masterId]);$tmp=$q->fetchAll(PDO::FETCH_ASSOC);
      if($tmp){ $dbRowsLoadedFromSaved = true; $dbRows=[]; foreach($tmp as $r){ $dbRows[]=['serial_no'=>(string)$r['serial_no'],'name'=>(string)$r['name'],'department'=>(string)$r['department'],'college'=>(string)$r['college'],'mobile'=>(string)$r['mobile'],'status_date'=>(string)($r['status_date'] ?? $reportDate),'status_text'=>(string)($r['status_text'] ?? '')]; } }

      // Auto-fill Registration tab from CRM registrations for this staff/date
      // only when daily report registration rows are not already saved.
      if(!$registrationRowsLoadedFromSaved){
      // Ownership rule (sensitive):
      // 1) If assigned_to exists, only assigned staff sees it.
      // 2) If assigned_to is NULL, creator sees it.
      $auto = $pdo->prepare("
        SELECT
          r.registration_no,
          r.joined_on,
          r.created_at,
          r.enquiry_snapshot_name,
          r.enquiry_snapshot_phone,
          r.program_name,
          r.final_fee,
          r.paid_amount,
          r.balance_amount,
          e.profession AS enquiry_department,
          e.college AS enquiry_college
        FROM registrations r
        LEFT JOIN enquiries e ON e.id = r.enquiry_id
        WHERE DATE(COALESCE(r.joined_on, DATE(r.created_at))) = ?
          AND r.branch_id = ?
          AND (
            (r.assigned_to IS NOT NULL AND r.assigned_to = ?)
            OR
            (r.assigned_to IS NULL AND r.created_by = ?)
          )
        ORDER BY r.id ASC
      ");
      $auto->execute([$reportDate, $branchId, $userId, $userId]);
      $autoRows = $auto->fetchAll(PDO::FETCH_ASSOC);
      if($autoRows){
        $registrationRows = [];
        foreach($autoRows as $idx => $ar){
          $dateReg = drDateOrNull($ar['joined_on'] ?? '');
          if($dateReg === null){
            $dateReg = substr((string)($ar['created_at'] ?? ''), 0, 10);
            if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateReg)) $dateReg = $reportDate;
          }
          $registrationRows[] = [
            'serial_no' => (string)($idx + 1),
            'name' => (string)($ar['enquiry_snapshot_name'] ?? ''),
            'department' => (string)($ar['enquiry_department'] ?? ''),
            'contact_no' => (string)($ar['enquiry_snapshot_phone'] ?? ''),
            'college' => (string)($ar['enquiry_college'] ?? ''),
            'date_of_registration' => (string)$dateReg,
            'course' => (string)($ar['program_name'] ?? ''),
            'billing' => drDec($ar['final_fee'] ?? 0),
            'collection_amount' => drDec($ar['paid_amount'] ?? 0),
            'balance_amount' => drDec($ar['balance_amount'] ?? 0),
            'payment_mode' => ''
          ];
        }
      }
      }

      // Auto-fill Database Follow Up from leads for this staff/date
      // only when daily report database rows are not already saved.
      if(!$dbRowsLoadedFromSaved){
      $autoDb = $pdo->prepare("
        SELECT
          l.id,
          l.name,
          l.department,
          l.company_college_name,
          l.phone,
          l.status,
          l.remarks
        FROM leads l
        WHERE DATE(l.created_at) = ?
          AND l.branch_id = ?
          AND (
            (l.assigned_to IS NOT NULL AND l.assigned_to = ?)
            OR
            (l.assigned_to IS NULL AND l.created_by = ?)
          )
        ORDER BY l.id ASC
      ");
      $autoDb->execute([$reportDate, $branchId, $userId, $userId]);
      $autoDbRows = $autoDb->fetchAll(PDO::FETCH_ASSOC);
      if($autoDbRows){
        $dbRows = [];
        foreach($autoDbRows as $idx => $ar){
          $statusBits = [];
          if(trim((string)($ar['status'] ?? '')) !== '') $statusBits[] = 'Lead Status: '.trim((string)$ar['status']);
          if(trim((string)($ar['remarks'] ?? '')) !== '') $statusBits[] = trim((string)$ar['remarks']);
          $dbRows[] = [
            'serial_no' => (string)($idx + 1),
            'name' => (string)($ar['name'] ?? ''),
            'department' => (string)($ar['department'] ?? ''),
            'college' => (string)($ar['company_college_name'] ?? ''),
            'mobile' => (string)($ar['phone'] ?? ''),
            'status_date' => (string)$reportDate,
            'status_text' => implode(' | ', $statusBits)
          ];
      }
    }
  }
}
  }
}
if(empty($missingTables) && $canUseFrontOfficeForm && !$master && !$isAllowedEntryDate){
  $drWarningMessage = 'Only today and last 2 days are allowed for daily report entry.';
}
$editModeLabel = 'Read Only';
if($isToday){
  $editModeLabel = $isEditable ? 'Editable (Today)' : 'Read Only (Locked)';
}elseif($isBackdateWithin2){
  $editModeLabel = $isEditable ? 'Editable (Backdate One-time Save)' : 'Read Only (Backdate Saved)';
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_all_report']) && empty($missingTables) && $canUseFrontOfficeForm){
  $token=$_POST['csrf_token'] ?? '';
  if(!verifyCSRF($token)){ setFlash('error','Invalid request (CSRF).'); redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate)); }
  if(!$master){ setFlash('error','Daily report master not found.'); redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate)); }
  if(!$isEditable){ setFlash('error','This report is read only. Today can be edited; backdate (last 2 days) is one-time save only.'); redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate)); }

  $masterId=(int)$master['id'];
  $p=[];
  $p['fresh_calls']=drInt($_POST['fresh_calls'] ?? 0);
  $p['follow_calls']=drInt($_POST['follow_calls'] ?? 0);
  $p['messages_sent']=drInt($_POST['messages_sent'] ?? 0);
  $p['mails_sent']=drInt($_POST['mails_sent'] ?? 0);
  $p['promotions']=drInt($_POST['promotions'] ?? 0);
  $p['reference_count']=drInt($_POST['reference_count'] ?? 0);
  $p['db_calls']=drInt($_POST['db_calls'] ?? 0);
  $p['walkins']=drInt($_POST['walkins'] ?? 0);
  $p['billing']=drDec($_POST['billing'] ?? 0);
  $p['fresh_collection']=drDec($_POST['fresh_collection'] ?? 0);
  $p['old_collection']=drDec($_POST['old_collection'] ?? 0);
  $p['total_calls']=$p['fresh_calls']+$p['follow_calls']+$p['messages_sent']+$p['mails_sent'];
  $p['registration_total']=$p['promotions']+$p['reference_count']+$p['db_calls'];
  $p['total_collection']=drDec(((float)$p['fresh_collection'])+((float)$p['old_collection']));
  $p['conversion_ratio']=$p['total_calls']>0?drDec(($p['registration_total']/$p['total_calls'])*100):'0.00';

  try{
    $pdo->beginTransaction();

    $up=$pdo->prepare("INSERT INTO dailyreport_frontoffice_activity(master_id,fresh_calls,follow_calls,messages_sent,mails_sent,total_calls,promotions,reference_count,db_calls,registration_total,billing,fresh_collection,old_collection,total_collection,walkins,conversion_ratio,created_at,updated_at) VALUES(:master_id,:fresh_calls,:follow_calls,:messages_sent,:mails_sent,:total_calls,:promotions,:reference_count,:db_calls,:registration_total,:billing,:fresh_collection,:old_collection,:total_collection,:walkins,:conversion_ratio,NOW(),NOW()) ON DUPLICATE KEY UPDATE fresh_calls=VALUES(fresh_calls),follow_calls=VALUES(follow_calls),messages_sent=VALUES(messages_sent),mails_sent=VALUES(mails_sent),total_calls=VALUES(total_calls),promotions=VALUES(promotions),reference_count=VALUES(reference_count),db_calls=VALUES(db_calls),registration_total=VALUES(registration_total),billing=VALUES(billing),fresh_collection=VALUES(fresh_collection),old_collection=VALUES(old_collection),total_collection=VALUES(total_collection),walkins=VALUES(walkins),conversion_ratio=VALUES(conversion_ratio),updated_at=NOW()");
    $up->execute(array_merge(['master_id'=>$masterId],$p));

    $pdo->prepare("DELETE FROM dailyreport_frontoffice_registration_rows WHERE master_id=?")->execute([$masterId]);
    $pdo->prepare("DELETE FROM dailyreport_frontoffice_planner_rows WHERE master_id=?")->execute([$masterId]);
    $pdo->prepare("DELETE FROM dailyreport_frontoffice_hourly_rows WHERE master_id=?")->execute([$masterId]);

    $x=$pdo->prepare("SELECT id FROM dailyreport_frontoffice_college_followup_rows WHERE master_id=?");$x->execute([$masterId]);$ids=array_map('intval',array_column($x->fetchAll(PDO::FETCH_ASSOC),'id')); if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$pdo->prepare("DELETE FROM dailyreport_frontoffice_college_followup_status WHERE followup_row_id IN ($ph)")->execute($ids);} $pdo->prepare("DELETE FROM dailyreport_frontoffice_college_followup_rows WHERE master_id=?")->execute([$masterId]);
    $x=$pdo->prepare("SELECT id FROM dailyreport_frontoffice_database_followup_rows WHERE master_id=?");$x->execute([$masterId]);$ids=array_map('intval',array_column($x->fetchAll(PDO::FETCH_ASSOC),'id')); if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$pdo->prepare("DELETE FROM dailyreport_frontoffice_database_followup_status WHERE database_row_id IN ($ph)")->execute($ids);} $pdo->prepare("DELETE FROM dailyreport_frontoffice_database_followup_rows WHERE master_id=?")->execute([$masterId]);

    $insReg=$pdo->prepare("INSERT INTO dailyreport_frontoffice_registration_rows(master_id,serial_no,name,department,contact_no,college,date_of_registration,course,billing,collection_amount,balance_amount,payment_mode,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $a=$_POST['reg_serial_no']??[]; $b=$_POST['reg_name']??[]; $c=$_POST['reg_department']??[]; $d=$_POST['reg_contact_no']??[]; $e=$_POST['reg_college']??[]; $f=$_POST['reg_date_of_registration']??[]; $g=$_POST['reg_course']??[]; $h=$_POST['reg_billing']??[]; $i=$_POST['reg_collection_amount']??[]; $j=$_POST['reg_balance_amount']??[]; $k=$_POST['reg_payment_mode']??[];
    $m=max(count($b),count($d),count($e));
    for($n=0;$n<$m;$n++){ if(drText($b[$n]??'')==='' && drText($d[$n]??'')==='' && drText($e[$n]??'')==='') continue; $billingVal=(float)($h[$n]??0); $collectionVal=(float)($i[$n]??0); $balanceVal=max(0, $billingVal - $collectionVal); $insReg->execute([ $masterId, drText($a[$n]??''), drText($b[$n]??''), drText($c[$n]??''), drText($d[$n]??''), drText($e[$n]??''), drDateOrNull($f[$n]??''), drText($g[$n]??''), drDec($billingVal), drDec($collectionVal), drDec($balanceVal), drText($k[$n]??'') ]); }

    $insPlan=$pdo->prepare("INSERT INTO dailyreport_frontoffice_planner_rows(master_id,sort_order,time_slot,activity,description,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())");
    $a=$_POST['plan_time_slot']??[]; $b=$_POST['plan_activity']??[]; $c=$_POST['plan_description']??[]; $m=max(count($a),count($b),count($c));
    for($n=0;$n<$m;$n++){ if(drText($a[$n]??'')==='' && drText($b[$n]??'')==='' && drText($c[$n]??'')==='') continue; $insPlan->execute([$masterId,$n+1,drText($a[$n]??''),drText($b[$n]??''),drText($c[$n]??'')]); }

  $insHour=$pdo->prepare("INSERT INTO dailyreport_frontoffice_hourly_rows(master_id,sort_order,time_from,time_to,particulars,remarks,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())");
    $a=$_POST['hour_time_from']??[]; $b=$_POST['hour_time_to']??[]; $c=$_POST['hour_particulars']??[]; $d=$_POST['hour_remarks']??[]; $m=max(count($a),count($b),count($c),count($d));
    $hourlyValidCount = 0;
    for($n=0;$n<$m;$n++){
      $from = drText($a[$n]??'');
      $to = drText($b[$n]??'');
      $particulars = drText($c[$n]??'');
      $remarks = drText($d[$n]??'');
      if($from==='' && $to==='' && $particulars==='' && $remarks==='') continue;
      if($from !== '' && $to !== '' && $particulars !== ''){
        $insHour->execute([$masterId,$n+1,$from,$to,$particulars,$remarks]);
        $hourlyValidCount++;
      }
    }
    if($hourlyValidCount === 0){
      throw new Exception('Hourly Report is mandatory. Please fill at least one hourly row (From, To, Particulars).');
    }

    $insCol=$pdo->prepare("INSERT INTO dailyreport_frontoffice_college_followup_rows(master_id,sort_order,serial_no,contact_name,designation,email,contact_no,college_name,location,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NOW(),NOW())");
    $insColSt=$pdo->prepare("INSERT INTO dailyreport_frontoffice_college_followup_status(followup_row_id,status_date,status_text,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())");
    $a=$_POST['college_serial_no']??[]; $b=$_POST['college_contact_name']??[]; $c=$_POST['college_designation']??[]; $d=$_POST['college_email']??[]; $e=$_POST['college_contact_no']??[]; $f=$_POST['college_name']??[]; $g=$_POST['college_location']??[]; $h=$_POST['college_status_date']??[]; $i=$_POST['college_status_text']??[]; $m=max(count($b),count($e),count($f));
    for($n=0;$n<$m;$n++){ if(drText($b[$n]??'')==='' && drText($e[$n]??'')==='' && drText($f[$n]??'')==='') continue; $insCol->execute([$masterId,$n+1,drText($a[$n]??''),drText($b[$n]??''),drText($c[$n]??''),drText($d[$n]??''),drText($e[$n]??''),drText($f[$n]??''),drText($g[$n]??'')]); $rowId=(int)$pdo->lastInsertId(); $sd=drDateOrNull($h[$n]??''); $st=drText($i[$n]??''); if($sd!==null || $st!==''){ if($sd===null) $sd=$reportDate; $insColSt->execute([$rowId,$sd,$st]); }}

    $insDb=$pdo->prepare("INSERT INTO dailyreport_frontoffice_database_followup_rows(master_id,sort_order,serial_no,name,department,college,mobile,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())");
    $insDbSt=$pdo->prepare("INSERT INTO dailyreport_frontoffice_database_followup_status(database_row_id,status_date,status_text,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())");
    $a=$_POST['db_serial_no']??[]; $b=$_POST['db_name']??[]; $c=$_POST['db_department']??[]; $d=$_POST['db_college']??[]; $e=$_POST['db_mobile']??[]; $f=$_POST['db_status_date']??[]; $g=$_POST['db_status_text']??[]; $m=max(count($b),count($d),count($e));
    for($n=0;$n<$m;$n++){ if(drText($b[$n]??'')==='' && drText($d[$n]??'')==='' && drText($e[$n]??'')==='') continue; $insDb->execute([$masterId,$n+1,drText($a[$n]??''),drText($b[$n]??''),drText($c[$n]??''),drText($d[$n]??''),drText($e[$n]??'')]); $rowId=(int)$pdo->lastInsertId(); $sd=drDateOrNull($f[$n]??''); $st=drText($g[$n]??''); if($sd!==null || $st!==''){ if($sd===null) $sd=$reportDate; $insDbSt->execute([$rowId,$sd,$st]); }}

    // Auto-finalize on every save as requested.
    $pdo->prepare("UPDATE dailyreport_master SET status='submitted', submitted_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$masterId]);

    $pdo->commit(); setFlash('success','Daily report saved successfully.');
  }catch(Exception $ex){ if($pdo->inTransaction()) $pdo->rollBack(); setFlash('error','Save failed: '.$ex->getMessage()); }

  redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate));
}
?>
<style>
.dr-wrap{padding:8px 0}.dr-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:14px}.dr-title{margin:0;color:#be185d;font-size:1.5rem;font-weight:800}.dr-note{margin:0;color:#6b7280;font-size:.9rem}.dr-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;border:1px solid #f1d6e3;background:#fff7fb;color:#9d174d;font-size:.82rem;font-weight:700}.dr-alert{border-radius:12px;padding:12px;margin-bottom:12px;border:1px solid transparent}.dr-alert-warn{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.dr-card{background:#fff;border:1px solid #f1d6e3;border-radius:14px;box-shadow:0 8px 18px rgba(0,0,0,.06);overflow:hidden}.dr-card-head{padding:12px 14px;border-bottom:1px solid #f1d6e3;background:#fff4fa;color:#be185d;font-weight:800}.dr-card-body{padding:14px}.dr-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.dr-field label{display:block;font-size:.82rem;color:#6b7280;font-weight:700;margin-bottom:6px}.dr-field input,.dr-field textarea{width:100%;border:1px solid #ecd3df;border-radius:10px;padding:8px 10px}.dr-field textarea{min-height:72px;resize:vertical}.dr-field input[readonly]{background:#f8fafc;color:#64748b}.dr-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}.dr-tab{border:1px solid #f2d3e2;background:#fff;border-radius:10px;padding:8px 12px;font-weight:700;color:#9d174d;cursor:pointer}.dr-tab.active{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff;border-color:#e91e63}.dr-step{display:none}.dr-step.active{display:block}.dr-step-nav{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:12px}.dr-btn{border:none;border-radius:10px;height:38px;padding:0 14px;font-weight:700;cursor:pointer}.dr-btn-primary{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff}.dr-btn-muted{background:#64748b;color:#fff}.dr-btn-success{background:#15803d;color:#fff}.dr-activity-board{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.dr-block{border:1px solid #f1d6e3;border-radius:12px;overflow:hidden;background:#fff}.dr-block-head{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff;font-weight:800;text-align:center;padding:8px 10px;font-size:.95rem}.dr-block-body{padding:12px;display:grid;gap:10px}.dr-table-wrap{overflow:auto}.dr-table{width:100%;border-collapse:collapse}.dr-table th,.dr-table td{border:1px solid #f1d6e3;padding:8px;vertical-align:top}.dr-table th{background:#fff4fa;color:#9d174d;font-size:.82rem}.dr-mini-btn{border:none;background:#e2e8f0;color:#334155;border-radius:8px;padding:6px 8px;font-size:.78rem;font-weight:700;cursor:pointer}.dr-mini-btn.add{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff;height:34px;min-width:140px;display:inline-flex;align-items:center;justify-content:center;padding:0 12px}.dr-mini-btn.del{background:#fee2e2;color:#991b1b}@media(max-width:1100px){.dr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dr-activity-board{grid-template-columns:1fr}}@media(max-width:640px){.dr-grid{grid-template-columns:1fr}}
</style>

<div class="dr-wrap">
  <div class="dr-head">
    <div><h2 class="dr-title">Daily Report Entry</h2><p class="dr-note">Front Office - 6 section flow (final save only)</p></div>
    <div class="dr-chip"><i class="fas fa-calendar-day"></i> Date: <?= h($reportDate) ?></div>
  </div>

  <?php if (empty($missingTables) && $canUseFrontOfficeForm && $master): ?>
  <div class="dr-card" style="margin-bottom:12px;"><div class="dr-card-head">Report Context</div><div class="dr-card-body"><div class="dr-grid"><div class="dr-field"><label>Report Date</label><form method="GET" action="index.php"><input type="hidden" name="page" value="dailyreports/entry"><input type="date" class="js-dr-report-date" name="report_date" value="<?= h($reportDate) ?>"></form></div><div class="dr-field"><label>Type</label><input type="text" value="Front Office" readonly></div><div class="dr-field"><label>Status</label><input type="text" value="<?= h(ucfirst((string)($master['status'] ?? 'draft'))) ?>" readonly></div><div class="dr-field"><label>Edit Mode</label><input type="text" value="<?= h($editModeLabel) ?>" readonly></div></div></div></div>

  <form method="POST" id="dailyReportForm">
    <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>"><input type="hidden" name="save_all_report" value="1">
    <div class="dr-tabs" id="drTabs"><button type="button" class="dr-tab active" data-step="1">Activity Report</button><button type="button" class="dr-tab" data-step="2">Registration</button><button type="button" class="dr-tab" data-step="3">Planner</button><button type="button" class="dr-tab" data-step="4">Hourly Report</button><button type="button" class="dr-tab" data-step="5">College Follow Up</button><button type="button" class="dr-tab" data-step="6">Database Following</button></div>
    <div class="dr-card"><div class="dr-card-body">

      <div class="dr-step active" data-step="1"><div class="dr-activity-board"><div class="dr-block"><div class="dr-block-head">Datas</div><div class="dr-block-body"><div class="dr-field"><label>No of fresh calls</label><input type="number" min="0" id="fresh_calls" name="fresh_calls" value="<?= h($activity['fresh_calls']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>follow calls</label><input type="number" min="0" id="follow_calls" name="follow_calls" value="<?= h($activity['follow_calls']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Msg sent</label><input type="number" min="0" id="messages_sent" name="messages_sent" value="<?= h($activity['messages_sent']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Mail Sent</label><input type="number" min="0" id="mails_sent" name="mails_sent" value="<?= h($activity['mails_sent']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Total Calls</label><input id="total_calls" name="total_calls" value="<?= h($activity['total_calls']) ?>" readonly></div></div></div><div class="dr-block"><div class="dr-block-head">Registration</div><div class="dr-block-body"><div class="dr-field"><label>Promotions</label><input type="number" min="0" id="promotions" name="promotions" value="<?= h($activity['promotions']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Reference</label><input type="number" min="0" id="reference_count" name="reference_count" value="<?= h($activity['reference_count']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>DB Calls</label><input type="number" min="0" id="db_calls" name="db_calls" value="<?= h($activity['db_calls']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Total</label><input id="registration_total" name="registration_total" value="<?= h($activity['registration_total']) ?>" readonly></div></div></div><div class="dr-block"><div class="dr-block-head">Contents</div><div class="dr-block-body"><div class="dr-field"><label>Billing</label><input type="number" min="0" step="0.01" id="billing" name="billing" value="<?= h($activity['billing']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Fresh Collection</label><input type="number" min="0" step="0.01" id="fresh_collection" name="fresh_collection" value="<?= h($activity['fresh_collection']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Old collection</label><input type="number" min="0" step="0.01" id="old_collection" name="old_collection" value="<?= h($activity['old_collection']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Total Collection</label><input id="total_collection" name="total_collection" value="<?= h($activity['total_collection']) ?>" readonly></div><div class="dr-field"><label>Walkins</label><input type="number" min="0" id="walkins" name="walkins" value="<?= h($activity['walkins']) ?>" <?= $isEditable ? '' : 'readonly' ?>></div><div class="dr-field"><label>Conversion Ratio (%)</label><input id="conversion_ratio" name="conversion_ratio" value="<?= h($activity['conversion_ratio']) ?>" readonly></div></div></div></div><div class="dr-step-nav"><span></span><button type="button" class="dr-btn dr-btn-primary" data-next="2">Next</button></div></div>

      <div class="dr-step" data-step="2"><div class="dr-table-wrap"><table class="dr-table" id="regTable"><thead><tr><th>S.No</th><th>Name</th><th>Department</th><th>Contact</th><th>College</th><th>Date</th><th>Course</th><th>Billing</th><th>Collection</th><th>Balance</th><th>Mode</th><th>Action</th></tr></thead><tbody id="regBody"><?php foreach($registrationRows as $r): ?><tr><td><input name="reg_serial_no[]" value="<?= h($r['serial_no']) ?>"></td><td><input name="reg_name[]" value="<?= h($r['name']) ?>"></td><td><input name="reg_department[]" value="<?= h($r['department']) ?>"></td><td><input name="reg_contact_no[]" value="<?= h($r['contact_no']) ?>"></td><td><input name="reg_college[]" value="<?= h($r['college']) ?>"></td><td><input type="date" name="reg_date_of_registration[]" value="<?= h($r['date_of_registration']) ?>"></td><td><input name="reg_course[]" value="<?= h($r['course']) ?>"></td><td><input type="number" step="0.01" name="reg_billing[]" value="<?= h($r['billing']) ?>"></td><td><input type="number" step="0.01" name="reg_collection_amount[]" value="<?= h($r['collection_amount']) ?>"></td><td><input type="number" step="0.01" name="reg_balance_amount[]" value="<?= h($r['balance_amount']) ?>" readonly></td><td><input name="reg_payment_mode[]" value="<?= h($r['payment_mode']) ?>"></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td></tr><?php endforeach; ?></tbody></table></div><div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addRegRow">+ Add Row</button></div><div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="1">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="3">Next</button></div></div>

      <div class="dr-step" data-step="3"><div class="dr-table-wrap"><table class="dr-table" id="planTable"><thead><tr><th>Time Slot</th><th>Activity</th><th>Description</th><th>Action</th></tr></thead><tbody id="planBody"><?php foreach($plannerRows as $r): ?><tr><td><input name="plan_time_slot[]" value="<?= h($r['time_slot']) ?>"></td><td><input name="plan_activity[]" value="<?= h($r['activity']) ?>"></td><td><textarea name="plan_description[]"><?= h($r['description']) ?></textarea></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td></tr><?php endforeach; ?></tbody></table></div><div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addPlanRow">+ Add Row</button></div><div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="2">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="4">Next</button></div></div>

      <div class="dr-step" data-step="4"><div class="dr-table-wrap"><table class="dr-table" id="hourTable"><thead><tr><th>From</th><th>To</th><th>Particulars</th><th>Remarks</th><th>Action</th></tr></thead><tbody id="hourBody"><?php foreach($hourlyRows as $r): ?><tr><td><input type="time" name="hour_time_from[]" value="<?= h($r['time_from']) ?>"></td><td><input type="time" name="hour_time_to[]" value="<?= h($r['time_to']) ?>"></td><td><input name="hour_particulars[]" value="<?= h($r['particulars']) ?>"></td><td><textarea name="hour_remarks[]"><?= h($r['remarks']) ?></textarea></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td></tr><?php endforeach; ?></tbody></table></div><div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHourRow">+ Add Row</button></div><div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="3">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="5">Next</button></div></div>

      <div class="dr-step" data-step="5"><div class="dr-table-wrap"><table class="dr-table" id="collegeTable"><thead><tr><th>S.No</th><th>Name</th><th>Designation</th><th>Email</th><th>Contact</th><th>College</th><th>Location</th><th>Status Date</th><th>Status</th><th>Action</th></tr></thead><tbody id="collegeBody"><?php foreach($collegeRows as $r): ?><tr><td><input name="college_serial_no[]" value="<?= h($r['serial_no']) ?>"></td><td><input name="college_contact_name[]" value="<?= h($r['contact_name']) ?>"></td><td><input name="college_designation[]" value="<?= h($r['designation']) ?>"></td><td><input name="college_email[]" value="<?= h($r['email']) ?>"></td><td><input name="college_contact_no[]" value="<?= h($r['contact_no']) ?>"></td><td><input name="college_name[]" value="<?= h($r['college_name']) ?>"></td><td><input name="college_location[]" value="<?= h($r['location']) ?>"></td><td><input type="date" name="college_status_date[]" value="<?= h($r['status_date']) ?>"></td><td><textarea name="college_status_text[]"><?= h($r['status_text']) ?></textarea></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td></tr><?php endforeach; ?></tbody></table></div><div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addCollegeRow">+ Add Row</button></div><div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="4">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="6">Next</button></div></div>

      <div class="dr-step" data-step="6"><div class="dr-table-wrap"><table class="dr-table" id="dbTable"><thead><tr><th>S.No</th><th>Name</th><th>Department</th><th>College</th><th>Mobile</th><th>Status Date</th><th>Status</th><th>Action</th></tr></thead><tbody id="dbBody"><?php foreach($dbRows as $r): ?><tr><td><input name="db_serial_no[]" value="<?= h($r['serial_no']) ?>"></td><td><input name="db_name[]" value="<?= h($r['name']) ?>"></td><td><input name="db_department[]" value="<?= h($r['department']) ?>"></td><td><input name="db_college[]" value="<?= h($r['college']) ?>"></td><td><input name="db_mobile[]" value="<?= h($r['mobile']) ?>"></td><td><input type="date" name="db_status_date[]" value="<?= h($r['status_date']) ?>"></td><td><textarea name="db_status_text[]"><?= h($r['status_text']) ?></textarea></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td></tr><?php endforeach; ?></tbody></table></div><div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addDbRow">+ Add Row</button></div><div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="5">Back</button><?php if($isEditable): ?><button type="submit" class="dr-btn dr-btn-success" id="saveAllBtn">Save All Sections</button><?php endif; ?></div></div>

    </div></div>
  </form>
  <?php endif; ?>
</div>

<script>
(function(){
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
  const root = document.querySelector('.dr-wrap') || document;
  root.addEventListener('change', function(e){
    if(!e.target.classList.contains('js-dr-report-date')) return;
    const dt = (e.target.value || '').trim();
    if(!dt) return;
    drAjaxSwap('index.php?page=dailyreports/entry&report_date=' + encodeURIComponent(dt));
  });
  const tabs=[...document.querySelectorAll('.dr-tab')], steps=[...document.querySelectorAll('.dr-step')];
  function showStep(n){tabs.forEach(t=>t.classList.toggle('active',Number(t.dataset.step)===n));steps.forEach(s=>s.classList.toggle('active',Number(s.dataset.step)===n));window.scrollTo({top:0,behavior:'smooth'});}  
  tabs.forEach(t=>t.addEventListener('click',()=>showStep(Number(t.dataset.step))));
  document.querySelectorAll('[data-next]').forEach(b=>b.addEventListener('click',()=>showStep(Number(b.dataset.next))));
  document.querySelectorAll('[data-prev]').forEach(b=>b.addEventListener('click',()=>showStep(Number(b.dataset.prev))));

  function n(id){const e=document.getElementById(id); if(!e) return 0; const v=parseFloat(e.value||'0'); return isNaN(v)?0:v;}
  function set(id,val,d){const e=document.getElementById(id); if(!e) return; e.value=(typeof d==='number')?Number(val).toFixed(d):String(Math.max(0,Math.round(val)));}
  function calc(){const tc=n('fresh_calls')+n('follow_calls')+n('messages_sent')+n('mails_sent'); set('total_calls',tc); const rt=n('promotions')+n('reference_count')+n('db_calls'); set('registration_total',rt); set('total_collection',n('fresh_collection')+n('old_collection'),2); set('conversion_ratio',tc>0?(rt/tc)*100:0,2);}
  ['fresh_calls','follow_calls','messages_sent','mails_sent','promotions','reference_count','db_calls','fresh_collection','old_collection','walkins'].forEach(id=>{const e=document.getElementById(id); if(e){e.addEventListener('input',calc);e.addEventListener('change',calc);}}); calc();

  function renumberSerialColumn(tbodyId, inputName){
    const rows = Array.from(document.querySelectorAll('#' + tbodyId + ' tr'));
    rows.forEach(function(tr, idx){
      const input = tr.querySelector('input[name="' + inputName + '"]');
      if(input){
        input.value = String(idx + 1);
        input.setAttribute('readonly', 'readonly');
      }
    });
  }
  function renumberAllSerials(){
    renumberSerialColumn('regBody', 'reg_serial_no[]');
    renumberSerialColumn('collegeBody', 'college_serial_no[]');
    renumberSerialColumn('dbBody', 'db_serial_no[]');
  }
  root.addEventListener('click',e=>{
    const b=e.target.closest('.js-del-row');
    if(b){
      const tr=b.closest('tr');
      if(tr) tr.remove();
      renumberAllSerials();
    }
  });
  function addRow(tbodyId,html){const tb=document.getElementById(tbodyId); if(!tb) return; const tr=document.createElement('tr'); tr.innerHTML=html; tb.appendChild(tr);} 
  function recalcRegRow(tr){
    if(!tr) return;
    const b = parseFloat((tr.querySelector('input[name=\"reg_billing[]\"]')||{}).value || '0');
    const c = parseFloat((tr.querySelector('input[name=\"reg_collection_amount[]\"]')||{}).value || '0');
    const billing = isNaN(b) ? 0 : b;
    const collection = isNaN(c) ? 0 : c;
    const bal = Math.max(0, billing - collection);
    const balInput = tr.querySelector('input[name=\"reg_balance_amount[]\"]');
    if (balInput) balInput.value = bal.toFixed(2);
  }
  function recalcAllRegRows(){
    document.querySelectorAll('#regBody tr').forEach(recalcRegRow);
  }
  document.getElementById('addRegRow')?.addEventListener('click',()=>{ addRow('regBody','<td><input name=\"reg_serial_no[]\"></td><td><input name=\"reg_name[]\"></td><td><input name=\"reg_department[]\"></td><td><input name=\"reg_contact_no[]\"></td><td><input name=\"reg_college[]\"></td><td><input type=\"date\" name=\"reg_date_of_registration[]\"></td><td><input name=\"reg_course[]\"></td><td><input type=\"number\" step=\"0.01\" name=\"reg_billing[]\" value=\"0.00\"></td><td><input type=\"number\" step=\"0.01\" name=\"reg_collection_amount[]\" value=\"0.00\"></td><td><input type=\"number\" step=\"0.01\" name=\"reg_balance_amount[]\" value=\"0.00\" readonly></td><td><input name=\"reg_payment_mode[]\"></td><td><button type=\"button\" class=\"dr-mini-btn del js-del-row\">Delete</button></td>'); recalcAllRegRows(); renumberAllSerials(); });
  document.getElementById('regBody')?.addEventListener('input', function(e){
    const t = e.target;
    if (!t) return;
    if (t.name === 'reg_billing[]' || t.name === 'reg_collection_amount[]') {
      recalcRegRow(t.closest('tr'));
    }
  });
  recalcAllRegRows();
  function nextPlannerSlot(){
    const rows = Array.from(document.querySelectorAll('#planBody tr'));
    if(!rows.length) return '09:30 - 10:30';
    const last = rows[rows.length - 1];
    const slotInput = last.querySelector('input[name="plan_time_slot[]"]');
    const raw = slotInput ? String(slotInput.value || '').trim() : '';
    const m = raw.match(/^(\d{1,2}:\d{2})(?:\s*[Tt][Oo]\s*|\s*-\s*)(\d{1,2}:\d{2})$/);
    if(m){
      const from = m[2];
      const to = addMinutes(from, 60) || '10:30';
      return from + ' - ' + to;
    }
    return '09:30 - 10:30';
  }
  document.getElementById('addPlanRow')?.addEventListener('click',()=>{
    const slot = nextPlannerSlot();
    addRow('planBody','<td><input name="plan_time_slot[]" value="'+slot+'"></td><td><input name="plan_activity[]"></td><td><textarea name="plan_description[]"></textarea></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td>');
  });
  function parseTimeToMinutes(val){
    const raw = String(val || '').trim();
    let m = raw.match(/^(\d{2}):(\d{2})$/);
    if (m) {
      const h = parseInt(m[1], 10);
      const mi = parseInt(m[2], 10);
      if (!isNaN(h) && !isNaN(mi)) return (h * 60) + mi;
    }
    m = raw.match(/^(\d{1,2}):(\d{2})\s*([AaPp][Mm])$/);
    if (m) {
      let h = parseInt(m[1], 10);
      const mi = parseInt(m[2], 10);
      const ampm = m[3].toUpperCase();
      if (isNaN(h) || isNaN(mi)) return null;
      if (ampm === 'PM' && h < 12) h += 12;
      if (ampm === 'AM' && h === 12) h = 0;
      return (h * 60) + mi;
    }
    return null;
  }
  function minutesToTime(total){
    if(total < 0) total = 0;
    const nh = Math.floor(total/60) % 24;
    const nm = total % 60;
    return String(nh).padStart(2,'0') + ':' + String(nm).padStart(2,'0');
  }
  function addMinutes(hhmm, mins){
    const base = parseTimeToMinutes(hhmm);
    if (base === null) return null;
    return minutesToTime(base + mins);
  }
  function nextHourlySlot(){
    const rows = Array.from(document.querySelectorAll('#hourBody tr'));
    if(!rows.length) return {from:'09:30', to:'10:30'};
    const last = rows[rows.length - 1];
    const lastToInput = last.querySelector('input[name=\"hour_time_to[]\"]');
    const lastTo = lastToInput ? (lastToInput.value || '').trim() : '';
    const baseMinutes = parseTimeToMinutes(lastTo);
    if(baseMinutes !== null){
      const from = minutesToTime(baseMinutes);
      const to = addMinutes(from, 60) || '10:30';
      return {from: from, to: to};
    }
    return {from:'09:30', to:'10:30'};
  }
  document.getElementById('addHourRow')?.addEventListener('click',()=>{
    const slot = nextHourlySlot();
    addRow('hourBody','<td><input type=\"time\" name=\"hour_time_from[]\" value=\"'+slot.from+'\"></td><td><input type=\"time\" name=\"hour_time_to[]\" value=\"'+slot.to+'\"></td><td><input name=\"hour_particulars[]\"></td><td><textarea name=\"hour_remarks[]\"></textarea></td><td><button type=\"button\" class=\"dr-mini-btn del js-del-row\">Delete</button></td>');
  });
  document.getElementById('addCollegeRow')?.addEventListener('click',()=>{ addRow('collegeBody','<td><input name="college_serial_no[]"></td><td><input name="college_contact_name[]"></td><td><input name="college_designation[]"></td><td><input name="college_email[]"></td><td><input name="college_contact_no[]"></td><td><input name="college_name[]"></td><td><input name="college_location[]"></td><td><input type="date" name="college_status_date[]" value="<?= h($reportDate) ?>"></td><td><textarea name="college_status_text[]"></textarea></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td>'); renumberAllSerials(); });
  document.getElementById('addDbRow')?.addEventListener('click',()=>{ addRow('dbBody','<td><input name="db_serial_no[]"></td><td><input name="db_name[]"></td><td><input name="db_department[]"></td><td><input name="db_college[]"></td><td><input name="db_mobile[]"></td><td><input type="date" name="db_status_date[]" value="<?= h($reportDate) ?>"></td><td><textarea name="db_status_text[]"></textarea></td><td><button type="button" class="dr-mini-btn del js-del-row">Delete</button></td>'); renumberAllSerials(); });
  renumberAllSerials();
  function enableEnterNavigation(formEl){
    if(!formEl) return;
    formEl.addEventListener('keydown', function(e){
      if (e.key !== 'Enter') return;
      const target = e.target;
      if (!target || !target.matches('input, select, textarea')) return;
      if (target.matches('button, [type="submit"], [type="button"], [type="file"], [type="checkbox"], [type="radio"]')) return;
      e.preventDefault();
      const fields = Array.from(formEl.querySelectorAll('input, select, textarea')).filter(function(el){
        if (!el) return false;
        if (el.disabled || el.readOnly) return false;
        if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button' || el.type === 'file' || el.type === 'checkbox' || el.type === 'radio') return false;
        if (el.offsetParent === null) return false;
        return true;
      });
      const idx = fields.indexOf(target);
      if (idx >= 0 && idx < fields.length - 1) {
        const next = fields[idx + 1];
        next.focus();
        if (typeof next.select === 'function' && next.tagName === 'INPUT') next.select();
      }
    });
  }
  function enableTablePaste(formEl){
    if(!formEl) return;
    formEl.addEventListener('paste', function(e){
      const target = e.target;
      if(!target || !target.matches('input, textarea, select')) return;
      const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
      if(!text || (text.indexOf('\t') === -1 && text.indexOf('\n') === -1 && text.indexOf('\r') === -1)) return;
      const tr = target.closest('tr');
      if(!tr) return;
      const table = tr.closest('table');
      if(!table) return;
      const tableRows = Array.from(table.querySelectorAll('tbody tr'));
      const startRow = tableRows.indexOf(tr);
      if(startRow < 0) return;
      const rowFields = Array.from(tr.querySelectorAll('input, textarea, select')).filter(function(el){
        if(el.disabled || el.readOnly) return false;
        if(el.type === 'hidden' || el.type === 'button' || el.type === 'submit' || el.type === 'file') return false;
        return true;
      });
      const startCol = rowFields.indexOf(target);
      if(startCol < 0) return;
      const matrix = text.replace(/\r/g,'').split('\n').filter(function(line){ return line !== ''; }).map(function(line){ return line.split('\t'); });
      if(!matrix.length) return;
      e.preventDefault();
      matrix.forEach(function(cols, rIdx){
        const row = tableRows[startRow + rIdx];
        if(!row) return;
        const fields = Array.from(row.querySelectorAll('input, textarea, select')).filter(function(el){
          if(el.disabled || el.readOnly) return false;
          if(el.type === 'hidden' || el.type === 'button' || el.type === 'submit' || el.type === 'file') return false;
          return true;
        });
        cols.forEach(function(val, cIdx){
          const cell = fields[startCol + cIdx];
          if(!cell) return;
          cell.value = val;
          cell.dispatchEvent(new Event('input', { bubbles:true }));
          cell.dispatchEvent(new Event('change', { bubbles:true }));
        });
      });
    });
  }
  function attachStatusAutoFill(cfg){
    const tbody = document.getElementById(cfg.tbodyId);
    if(!tbody) return;
    const table = tbody.closest('table');
    const wrap = table?.parentElement;
    if(!wrap) return;
    if(wrap.querySelector('[data-status-search-for="'+cfg.tbodyId+'"]')) return;
    const box = document.createElement('div');
    box.setAttribute('data-status-search-for', cfg.tbodyId);
    box.style.cssText = 'display:flex;align-items:center;gap:8px;margin:0 0 8px 0;flex-wrap:wrap;';
    const input = document.createElement('input');
    input.type='text';
    input.placeholder=cfg.placeholder || 'Search...';
    input.style.cssText='max-width:360px;';
    const btn = document.createElement('button');
    btn.type='button';
    btn.className='dr-mini-btn add';
    btn.textContent='Search & Auto Fill';
    box.appendChild(input); box.appendChild(btn);
    wrap.parentNode.insertBefore(box, wrap);
    function ensureTargetRow(){
      const rows = Array.from(tbody.querySelectorAll('tr'));
      for(const tr of rows){ if(cfg.isRowEmpty(tr)) return tr; }
      const addBtn = document.getElementById(cfg.addBtnId);
      if(addBtn) addBtn.click();
      const latest = tbody.querySelectorAll('tr');
      return latest.length ? latest[latest.length-1] : null;
    }
    btn.addEventListener('click', async function(){
      const q=(input.value||'').trim();
      if(!q){ if(window.Swal) Swal.fire({icon:'warning',title:'Search Required',text:'Type search text first.'}); return; }
      try{
        const url='index.php?page=dailyreports/entry&ajax='+encodeURIComponent(cfg.ajax)+'&q='+encodeURIComponent(q);
        const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data=await res.json();
        const rows=Array.isArray(data.rows)?data.rows:[];
        if(!rows.length){ if(window.Swal) Swal.fire({icon:'info',title:'No Match',text:'No previous records found.'}); return; }
        let picked=rows[0];
        if(rows.length>1 && window.Swal){
          const opts={}; rows.forEach((r,i)=>{ opts[String(i)] = cfg.optionLabel(r); });
          const pick = await Swal.fire({title:'Select Record',input:'select',inputOptions:opts,inputValue:'0',showCancelButton:true,confirmButtonColor:'#e91e63'});
          if(!pick.isConfirmed) return;
          picked = rows[parseInt(pick.value||'0',10)] || rows[0];
        }
        const tr = ensureTargetRow();
        if(!tr) return;
        cfg.fillRow(tr,picked);
        if(window.Swal) Swal.fire({icon:'success',title:'Loaded',text:'Previous data loaded. Update and save.'});
      }catch(err){
        if(window.Swal) Swal.fire({icon:'error',title:'Error',text:'Search failed. Try again.'});
      }
    });
  }

  const form=document.getElementById('dailyReportForm');
  if(form){
    function hasValidHourlyRow(){
      const rows = Array.from(document.querySelectorAll('#hourBody tr'));
      return rows.some(function(row){
        const from = (row.querySelector('input[name="hour_time_from[]"]') || {}).value || '';
        const to = (row.querySelector('input[name="hour_time_to[]"]') || {}).value || '';
        const particulars = (row.querySelector('input[name="hour_particulars[]"]') || {}).value || '';
        return String(from).trim() !== '' && String(to).trim() !== '' && String(particulars).trim() !== '';
      });
    }
    enableEnterNavigation(form);
    enableTablePaste(form);
    attachStatusAutoFill({
      tbodyId:'collegeBody',
      addBtnId:'addCollegeRow',
      ajax:'fo_college_lookup',
      placeholder:'Search old college followup (name/college/contact/location)',
      optionLabel:function(r){ return (r.contact_name||'-')+' | '+(r.college_name||'-')+' | '+(r.contact_no||'-')+' | '+(r.report_date||'-'); },
      isRowEmpty:function(tr){
        return !(tr.querySelector('input[name="college_contact_name[]"]')?.value||'').trim()
          && !(tr.querySelector('input[name="college_contact_no[]"]')?.value||'').trim()
          && !(tr.querySelector('input[name="college_name[]"]')?.value||'').trim();
      },
      fillRow:function(tr,r){
        const set=(name,val)=>{ const el=tr.querySelector('input[name="'+name+'[]"], textarea[name="'+name+'[]"]'); if(el) el.value=(val||'').toString(); };
        set('college_serial_no', r.serial_no);
        set('college_contact_name', r.contact_name);
        set('college_designation', r.designation);
        set('college_email', r.email);
        set('college_contact_no', r.contact_no);
        set('college_name', r.college_name);
        set('college_location', r.location);
        set('college_status_date', r.status_date || '<?= h($reportDate) ?>');
        set('college_status_text', r.status_text);
      }
    });
    attachStatusAutoFill({
      tbodyId:'dbBody',
      addBtnId:'addDbRow',
      ajax:'fo_db_lookup',
      placeholder:'Search old database followup (name/college/mobile)',
      optionLabel:function(r){ return (r.name||'-')+' | '+(r.college||'-')+' | '+(r.mobile||'-')+' | '+(r.report_date||'-'); },
      isRowEmpty:function(tr){
        return !(tr.querySelector('input[name="db_name[]"]')?.value||'').trim()
          && !(tr.querySelector('input[name="db_college[]"]')?.value||'').trim()
          && !(tr.querySelector('input[name="db_mobile[]"]')?.value||'').trim();
      },
      fillRow:function(tr,r){
        const set=(name,val)=>{ const el=tr.querySelector('input[name="'+name+'[]"], textarea[name="'+name+'[]"]'); if(el) el.value=(val||'').toString(); };
        set('db_serial_no', r.serial_no);
        set('db_name', r.name);
        set('db_department', r.department);
        set('db_college', r.college);
        set('db_mobile', r.mobile);
        set('db_status_date', r.status_date || '<?= h($reportDate) ?>');
        set('db_status_text', r.status_text);
      }
    });

    form.addEventListener('submit',function(e){
      e.preventDefault();
      if(!hasValidHourlyRow()){
        if(window.Swal){
          Swal.fire({
            icon:'error',
            title:'Hourly Report Required',
            text:'Please fill at least one row in Hourly Report (From, To, Particulars) before saving.'
          }).then(function(){ showStep(4); });
        }
        return;
      }
      if(window.Swal){
        Swal.fire({icon:'question',title:'Save Daily Report?',text:'This will save all 6 sections to database.',showCancelButton:true,confirmButtonColor:'#e91e63',cancelButtonColor:'#6b7280',confirmButtonText:'Yes, Save All'}).then(r=>{if(r.isConfirmed) form.submit();});
      }else{
        form.submit();
      }
    });
  }
})();
</script>

<?php if ($drWarningMessage !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if(window.Swal){
    Swal.fire({
      icon: 'warning',
      title: 'Attention',
      text: <?= json_encode($drWarningMessage) ?>,
      confirmButtonColor: '#e91e63'
    });
  }
});
</script>
<?php endif; ?>

<?php if ($drSuccessMessage !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  if(window.Swal){
    Swal.fire({
      icon: 'success',
      title: 'Saved',
      text: <?= json_encode($drSuccessMessage) ?>,
      confirmButtonColor: '#e91e63'
    });
  }
});
</script>
<?php endif; ?>
