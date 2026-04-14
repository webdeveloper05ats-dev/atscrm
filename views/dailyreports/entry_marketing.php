<?php
if (!defined('APP_NAME')) die('Unauthorized access.');
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('drInt')) { function drInt($v){ return max(0, (int)$v); } }
if (!function_exists('drDec')) { function drDec($v){ return number_format((float)$v, 2, '.', ''); } }
if (!function_exists('drText')) { function drText($v){ return trim((string)$v); } }
if (!function_exists('drDateOrNull')) { function drDateOrNull($v){ $v=trim((string)$v); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:null; } }

$userId=(int)($_SESSION['user_id']??0);
$roleId=(int)($_SESSION['role_id']??0);
$branchId=(int)($_SESSION['branch_id']??0);
$roleName=strtolower(trim((string)($_SESSION['role_name']??'')));
$canUseMarketingForm = ($roleName==='marketing' || $roleName==='super admin');
$hideActivityTab = ($roleName === 'marketing');
$isSaveRequest = ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_all_report']));

$requiredTables=[
  'dailyreport_master','dailyreport_marketing_activity','dailyreport_marketing_hourly_rows','dailyreport_marketing_colleges_rows',
  'dailyreport_marketing_prospect_rows','dailyreport_marketing_act_report_rows','dailyreport_marketing_amount_rows','dailyreport_marketing_program_rows',
  'dailyreport_marketing_arts_college_rows','dailyreport_marketing_arts_pc_rows','dailyreport_marketing_engg_college_rows','dailyreport_marketing_engg_pc_rows','dailyreport_marketing_polytech_college_rows',
  'dailyreport_marketing_prospect_status_rows'
];
$missingTables=[]; foreach($requiredTables as $t){ if(!function_exists('crmTableExists') || !crmTableExists($pdo,$t)) $missingTables[]=$t; }

$today=date('Y-m-d');
$minSelectableDate = date('Y-m-d', strtotime($today.' -2 day'));
$reportDate=trim((string)($_GET['report_date']??$today));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$reportDate)) $reportDate=$today;
$dayDiff=(int)((strtotime($today.' 00:00:00')-strtotime($reportDate.' 00:00:00'))/86400);
$isToday=($dayDiff===0); $isBackdateWithin2=($dayDiff>=1&&$dayDiff<=2); $isAllowedEntryDate=($isToday||$isBackdateWithin2);

$activity=['fresh_calls'=>0,'follow_calls'=>0,'messages_sent'=>0,'mails_sent'=>0,'forum_posting'=>0,'promotions'=>0,'reference_count'=>0,'db_calls'=>0,'billing'=>'0.00','fresh_collection'=>'0.00','old_collection'=>'0.00','walkins'=>0];
$sections=[
  'hourly'=>[['time_from'=>'09:30','time_to'=>'10:30','particulars'=>'','activities_undergone'=>'']],
  'colleges'=>[['serial_no'=>'','entry_date'=>$reportDate,'college_name'=>'','address'=>'','city'=>'','department'=>'','contact_person'=>'','designation'=>'','mobile_no'=>'','mail_id'=>'','status_1'=>'','status_2'=>'']],
  'prospect'=>[['serial_no'=>'','staff_name'=>'','college'=>'','department'=>'','designation'=>'','mobile_number'=>'','email'=>'']],
  'act_report'=>[],
  'amount'=>[['serial_no'=>'','entry_date'=>$reportDate,'college_name'=>'','dept_or_name'=>'','particulars'=>'','bank'=>'','cash'=>'0.00','amount'=>'0.00']],
  'program'=>[['serial_no'=>'','college'=>'','department'=>'','class_name'=>'','program_given_by'=>'','designation'=>'','program_type'=>'','domain'=>'','trainer'=>'','topics'=>'','no_days'=>'','day_start'=>'','end_day'=>'','hours'=>'','no_of_students'=>'0','amount'=>'0.00','collection'=>'0.00']],
  'arts_college'=>[['serial_no'=>'','college_name'=>'','address'=>'','city'=>'','department'=>'','contact_person'=>'','designation'=>'','phone_number'=>'','email_id'=>'']],
  'arts_pc'=>[['serial_no'=>'','place_name'=>'','college_name'=>'','department'=>'','name'=>'','designation'=>'','contact_number'=>'']],
  'engg_college'=>[['serial_no'=>'','college_name'=>'','address'=>'','city'=>'','department'=>'','contact_person'=>'','designation'=>'','phone_number'=>'','email_id'=>'','dob'=>'','doa'=>'']],
  'engg_pc'=>[['serial_no'=>'','place_name'=>'','college_name'=>'','department'=>'','name'=>'','contact_number'=>'','email_id'=>'']],
  'polytech_college'=>[['serial_no'=>'','college_name'=>'','address'=>'','city'=>'','department'=>'','contact_person'=>'','designation'=>'','phone_number'=>'','email_id'=>'','dob'=>'','doa'=>'']]
];
$tables=[
  'hourly'=>'dailyreport_marketing_hourly_rows','colleges'=>'dailyreport_marketing_colleges_rows','prospect'=>'dailyreport_marketing_prospect_rows',
  'act_report'=>'dailyreport_marketing_act_report_rows','amount'=>'dailyreport_marketing_amount_rows','program'=>'dailyreport_marketing_program_rows',
  'arts_college'=>'dailyreport_marketing_arts_college_rows','arts_pc'=>'dailyreport_marketing_arts_pc_rows','engg_college'=>'dailyreport_marketing_engg_college_rows',
  'engg_pc'=>'dailyreport_marketing_engg_pc_rows','polytech_college'=>'dailyreport_marketing_polytech_college_rows'
];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'college_lookup') {
  header('Content-Type: application/json; charset=utf-8');
  if (!$canUseMarketingForm) {
    echo json_encode(['ok'=>false,'message'=>'Access denied','rows'=>[]]);
    exit;
  }
  $q = trim((string)($_GET['q'] ?? ''));
  if ($q === '') {
    echo json_encode(['ok'=>true,'rows'=>[]]);
    exit;
  }
  $like = '%'.$q.'%';
  $stmt = $pdo->prepare("
    SELECT
      c.id AS row_id, dm.report_date, c.college_name, c.address, c.city, c.department, c.contact_person, c.designation, c.mobile_no, c.mail_id, c.status_1, c.status_2
    FROM dailyreport_marketing_colleges_rows c
    INNER JOIN dailyreport_master dm ON dm.id = c.master_id
    WHERE dm.report_type = 'marketing'
      AND dm.user_id = ?
      AND dm.branch_id = ?
      AND (
        c.college_name LIKE ?
        OR c.contact_person LIKE ?
        OR c.mobile_no LIKE ?
        OR c.city LIKE ?
      )
    ORDER BY dm.report_date DESC, c.id DESC
    LIMIT 50
  ");
  $stmt->execute([$userId, $branchId, $like, $like, $like, $like]);
  $raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $seen = [];
  $rows = [];
  foreach($raw as $r){
    $key = strtolower(trim((string)$r['college_name'])) . '|' . trim((string)$r['mobile_no']);
    if (isset($seen[$key])) continue;
    $seen[$key] = 1;
    $rows[] = $r;
    if (count($rows) >= 20) break;
  }
  echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
  exit;
}
if (isset($_GET['ajax']) && $_GET['ajax'] === 'prospect_lookup') {
  header('Content-Type: application/json; charset=utf-8');
  if (!$canUseMarketingForm) {
    echo json_encode(['ok'=>false,'message'=>'Access denied','rows'=>[]]);
    exit;
  }
  $q = trim((string)($_GET['q'] ?? ''));
  if ($q === '') {
    echo json_encode(['ok'=>true,'rows'=>[]]);
    exit;
  }
  $like = '%'.$q.'%';
  $stmt = $pdo->prepare("
    SELECT
      p.id AS row_id, dm.report_date, p.staff_name, p.college, p.department, p.designation, p.mobile_number, p.email
    FROM dailyreport_marketing_prospect_rows p
    INNER JOIN dailyreport_master dm ON dm.id = p.master_id
    WHERE dm.report_type = 'marketing'
      AND dm.user_id = ?
      AND dm.branch_id = ?
      AND (
        p.staff_name LIKE ?
        OR p.college LIKE ?
        OR p.mobile_number LIKE ?
        OR p.email LIKE ?
      )
    ORDER BY dm.report_date DESC, p.id DESC
    LIMIT 30
  ");
  $stmt->execute([$userId, $branchId, $like, $like, $like, $like]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  if(!empty($rows)){
    $ids = [];
    foreach($rows as $r){ $ids[] = (int)$r['row_id']; }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q2 = $pdo->prepare("SELECT prospect_row_id, sort_order, status_date, status_text, remarks FROM dailyreport_marketing_prospect_status_rows WHERE prospect_row_id IN ($in) ORDER BY prospect_row_id ASC, sort_order ASC, id ASC");
    $q2->execute($ids);
    $fw = $q2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $by = [];
    foreach($fw as $f){ $by[(int)$f['prospect_row_id']][] = $f; }
    foreach($rows as &$r){ $rid=(int)$r['row_id']; $r['followups']=$by[$rid] ?? []; }
    unset($r);
  }
  echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
  exit;
}

$master=null; $isEditable=false; $warning='';
$drSuccessMessage = (isset($_GET['saved']) && (string)$_GET['saved'] === '1') ? 'Marketing daily report saved successfully.' : '';
$drErrorMessage = trim((string)($_GET['save_error'] ?? ''));
if(!empty($missingTables)) $warning='Missing table(s): '.implode(', ',$missingTables).'.';
elseif(!$canUseMarketingForm) $warning='Only Marketing (or Super Admin) can use this form.';

if(empty($missingTables) && $canUseMarketingForm){
  $st=$pdo->prepare("SELECT * FROM dailyreport_master WHERE report_date=? AND user_id=? AND report_type='marketing' LIMIT 1");
  $st->execute([$reportDate,$userId]);
  $master=$st->fetch(PDO::FETCH_ASSOC)?:null;
  if(!$master && $isAllowedEntryDate){
    $ins=$pdo->prepare("INSERT INTO dailyreport_master(report_date,role_id,user_id,branch_id,report_type,status,created_at,updated_at) VALUES(?,?,?,?,'marketing','draft',NOW(),NOW())");
    $ins->execute([$reportDate,$roleId,$userId,$branchId]);
    $id=(int)$pdo->lastInsertId();
    $st=$pdo->prepare("SELECT * FROM dailyreport_master WHERE id=?");
    $st->execute([$id]);
    $master=$st->fetch(PDO::FETCH_ASSOC)?:null;
  }
  if($master){
    $statusLower=strtolower((string)($master['status']??'draft'));
    if($isToday) $isEditable=($statusLower!=='locked');
    elseif($isBackdateWithin2) $isEditable=($statusLower==='draft');
  }
}

if($master && empty($missingTables) && !$isSaveRequest){
  $mid=(int)$master['id'];
  $q=$pdo->prepare("SELECT * FROM dailyreport_marketing_activity WHERE master_id=? LIMIT 1");
  $q->execute([$mid]); $r=$q->fetch(PDO::FETCH_ASSOC);
  if($r){ foreach($activity as $k=>$v){ if(array_key_exists($k,$r)) $activity[$k]=$r[$k]; } }
  foreach($tables as $k=>$tbl){
    $q=$pdo->prepare("SELECT * FROM {$tbl} WHERE master_id=? ORDER BY sort_order ASC, id ASC");
    $q->execute([$mid]); $tmp=$q->fetchAll(PDO::FETCH_ASSOC);
    if($k==='act_report' && $tmp){
      $filtered=[];
      foreach($tmp as $r){
        $has = trim((string)($r['metric_name'] ?? '')) !== '' || trim((string)($r['total_value'] ?? '')) !== '';
        if(!$has){
          for($d=1;$d<=31;$d++){
            if(trim((string)($r['day_'.$d] ?? '')) !== ''){ $has=true; break; }
          }
        }
        if($has) $filtered[]=$r;
      }
      $tmp=$filtered;
    }
    if($tmp) $sections[$k]=$tmp;
  }
  if (!empty($sections['prospect'])) {
    $prospectIds = [];
    foreach($sections['prospect'] as $pr){ if(isset($pr['id'])) $prospectIds[] = (int)$pr['id']; }
    if (!empty($prospectIds)) {
      $in = implode(',', array_fill(0, count($prospectIds), '?'));
      $q = $pdo->prepare("SELECT * FROM dailyreport_marketing_prospect_status_rows WHERE prospect_row_id IN ($in) ORDER BY prospect_row_id ASC, sort_order ASC, id ASC");
      $q->execute($prospectIds);
      $stRows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $byProspect = [];
      foreach($stRows as $sr){ $byProspect[(int)$sr['prospect_row_id']][] = $sr; }
      foreach($sections['prospect'] as &$pr){
        $pid = (int)($pr['id'] ?? 0);
        $pr['followups'] = $byProspect[$pid] ?? [];
        if (empty($pr['followups'])) {
          $legacy = [];
          for($i=1;$i<=3;$i++){
            $txt = trim((string)($pr['status_'.$i] ?? ''));
            $dt  = trim((string)($pr['date_'.$i] ?? ''));
            if($txt !== '' || $dt !== '') $legacy[] = ['status_text'=>$txt,'status_date'=>$dt,'remarks'=>''];
          }
          $pr['followups'] = $legacy;
        }
      }
      unset($pr);
    }
  }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_all_report']) && empty($missingTables) && $canUseMarketingForm){
  if(!verifyCSRF($_POST['csrf_token']??'')){ redirect('index.php?page=dailyreports/entry&report_type=marketing&report_date='.urlencode($reportDate).'&save_error='.urlencode('Invalid request (CSRF).')); }
  if(!$master || !$isEditable){ redirect('index.php?page=dailyreports/entry&report_type=marketing&report_date='.urlencode($reportDate).'&save_error='.urlencode('This report is read only.')); }

  $mid=(int)$master['id'];
  $p=[]; foreach(['fresh_calls','follow_calls','messages_sent','mails_sent','forum_posting','promotions','reference_count','db_calls','walkins'] as $k){ $p[$k]=drInt($_POST[$k]??0); }
  foreach(['billing','fresh_collection','old_collection'] as $k){ $p[$k]=drDec($_POST[$k]??0); }
  $p['remarks']='';
  $p['total_calls']=$p['fresh_calls']+$p['follow_calls']+$p['messages_sent']+$p['mails_sent'];
  $p['registration_total']=$p['promotions']+$p['reference_count']+$p['db_calls'];
  $p['total_collection']=drDec(((float)$p['fresh_collection'])+((float)$p['old_collection']));
  $p['conversion_ratio']=$p['total_calls']>0?drDec(($p['registration_total']/$p['total_calls'])*100):'0.00';
  try{
    $pdo->beginTransaction();
    $up=$pdo->prepare("INSERT INTO dailyreport_marketing_activity(master_id,fresh_calls,follow_calls,messages_sent,mails_sent,total_calls,forum_posting,promotions,reference_count,db_calls,registration_total,billing,fresh_collection,old_collection,total_collection,walkins,conversion_ratio,remarks,created_at,updated_at) VALUES(:master_id,:fresh_calls,:follow_calls,:messages_sent,:mails_sent,:total_calls,:forum_posting,:promotions,:reference_count,:db_calls,:registration_total,:billing,:fresh_collection,:old_collection,:total_collection,:walkins,:conversion_ratio,:remarks,NOW(),NOW()) ON DUPLICATE KEY UPDATE fresh_calls=VALUES(fresh_calls),follow_calls=VALUES(follow_calls),messages_sent=VALUES(messages_sent),mails_sent=VALUES(mails_sent),total_calls=VALUES(total_calls),forum_posting=VALUES(forum_posting),promotions=VALUES(promotions),reference_count=VALUES(reference_count),db_calls=VALUES(db_calls),registration_total=VALUES(registration_total),billing=VALUES(billing),fresh_collection=VALUES(fresh_collection),old_collection=VALUES(old_collection),total_collection=VALUES(total_collection),walkins=VALUES(walkins),conversion_ratio=VALUES(conversion_ratio),remarks=VALUES(remarks),updated_at=NOW()");
    $up->execute(array_merge(['master_id'=>$mid],$p));

    $pdo->prepare("DELETE s FROM dailyreport_marketing_prospect_status_rows s INNER JOIN dailyreport_marketing_prospect_rows p ON p.id=s.prospect_row_id WHERE p.master_id=?")->execute([$mid]);
    foreach($tables as $tbl){ $pdo->prepare("DELETE FROM {$tbl} WHERE master_id=?")->execute([$mid]); }
    $hourRows=json_decode((string)($_POST['hourly']??'[]'),true); if(!is_array($hourRows)) $hourRows=[];
    $insHour=$pdo->prepare("INSERT INTO dailyreport_marketing_hourly_rows(master_id,sort_order,time_from,time_to,particulars,activities_undergone,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())");
    $hv=0; foreach($hourRows as $i=>$r){ $from=drText($r['time_from']??''); $to=drText($r['time_to']??''); $part=drText($r['particulars']??''); $act=drText($r['activities_undergone']??''); if($from===''&&$to===''&&$part===''&&$act==='') continue; $insHour->execute([$mid,$i+1,$from,$to,$part,$act]); if($from!==''&&$to!==''&&$part!=='') $hv++; }
    if($hv===0) throw new Exception('Hourly Report is mandatory. Please fill at least one hourly row (From, To, Particulars).');

    foreach($tables as $k=>$tbl){
      if($k==='hourly') continue;
      $rows=json_decode((string)($_POST[$k]??'[]'),true); if(!is_array($rows)) $rows=[];
      if($k==='act_report'){ $fields=['metric_name']; for($d=1;$d<=31;$d++) $fields[]='day_'.$d; $fields[]='total_value'; }
      elseif($k==='prospect'){ $fields=['serial_no','staff_name','college','department','designation','mobile_number','email']; }
      else $fields=array_keys($sections[$k][0]);
      $cols='master_id,sort_order,'.implode(',',$fields); $place='?,?'; foreach($fields as $f){ $place.=',?'; }
      $stmt=$pdo->prepare("INSERT INTO {$tbl}({$cols},created_at,updated_at) VALUES({$place},NOW(),NOW())");
      $statusIns = null;
      if($k==='prospect'){
        $statusIns = $pdo->prepare("INSERT INTO dailyreport_marketing_prospect_status_rows(prospect_row_id,sort_order,status_date,status_text,remarks,created_at,updated_at) VALUES(?,?,?,?,?,NOW(),NOW())");
      }
      foreach($rows as $idx=>$r){
        $vals=[$mid,$idx+1]; $has=false;
        foreach($fields as $f){
          $v=$r[$f]??'';
          if(strpos($f,'date')!==false || in_array($f,['dob','doa','day_start','end_day'],true)) $v=drDateOrNull($v); else $v=drText($v);
          if($v!==''&&$v!==null&&$v!==0) $has=true;
          $vals[]=$v;
        }
        if(!$has) continue;
        $stmt->execute($vals);
        if($k==='prospect' && $statusIns){
          $pid = (int)$pdo->lastInsertId();
          $followups = (isset($r['followups']) && is_array($r['followups'])) ? $r['followups'] : [];
          $sidx = 0;
          foreach($followups as $fw){
            $dt = drDateOrNull($fw['status_date'] ?? '');
            $tx = drText($fw['status_text'] ?? '');
            $rm = drText($fw['remarks'] ?? '');
            if($dt===null && $tx==='' && $rm==='') continue;
            if($dt===null) $dt = $reportDate;
            $sidx++;
            $statusIns->execute([$pid,$sidx,$dt,$tx,$rm]);
          }
        }
      }
    }
    $pdo->prepare("UPDATE dailyreport_master SET status='submitted', submitted_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$mid]);
    $pdo->commit();
    redirect('index.php?page=dailyreports/entry&report_type=marketing&report_date='.urlencode($reportDate).'&saved=1');
  } catch(Exception $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    redirect('index.php?page=dailyreports/entry&report_type=marketing&report_date='.urlencode($reportDate).'&save_error='.urlencode($e->getMessage()));
  }
}

$totalCallsNow = (int)$activity['fresh_calls'] + (int)$activity['follow_calls'] + (int)$activity['messages_sent'] + (int)$activity['mails_sent'];
$regTotalNow = (int)$activity['promotions'] + (int)$activity['reference_count'] + (int)$activity['db_calls'];
$totalCollectionNow = number_format((float)$activity['fresh_collection'] + (float)$activity['old_collection'],2,'.','');
$convNow = ($totalCallsNow>0) ? number_format(($regTotalNow/$totalCallsNow)*100,2,'.','') : '0.00';
$editModeLabel='Read Only'; if($isToday) $editModeLabel=$isEditable?'Editable (Today)':'Read Only (Locked)'; elseif($isBackdateWithin2) $editModeLabel=$isEditable?'Editable (Backdate One-time Save)':'Read Only (Backdate Saved)';
$mkMonthPrefix = date('F', strtotime($reportDate));
$mkDayIndex = (int)date('j', strtotime($reportDate));
$mkDayLabel = date('d M Y', strtotime($reportDate));
$mkActMetricList = [
  'No of Hods Met',
  'No of Asst Professor Met',
  'Total Calls',
  'No of Colleges Visited',
  'No of Companies Visited',
  'Students Reference',
  'Workshop',
  'On Campus Training',
  'Project Taken',
  'Billing',
  'Fresh Collection',
  'Old Collection',
  'Total Collection',
  'Registration',
  'Walkins'
];
$mkActTodayKey = 'day_'.$mkDayIndex;
$mkActValues = [];
foreach(($sections['act_report'] ?? []) as $ar){
  $m = trim((string)($ar['metric_name'] ?? ''));
  if($m === '') continue;
  $v = trim((string)($ar[$mkActTodayKey] ?? ''));
  if($v === '') $v = trim((string)($ar['total_value'] ?? ''));
  $mkActValues[strtolower($m)] = $v;
}
function mkj($v){ return h(json_encode($v, JSON_UNESCAPED_UNICODE)); }
?>
<style>
.dr-wrap.mk-hide-activity .dr-tab[data-step="1"],
.dr-wrap.mk-hide-activity .dr-step[data-step="1"]{display:none;}
.dr-tabs{margin-top:8px;}
.mk-college-search{overflow:visible;}
.mk-college-search{position:relative;max-width:520px;}
.mk-college-searchbox{
  width:100%;
  max-width:520px;
  border:1px solid #f2cfe0;
  border-radius:12px;
  background:#fff;
  padding:10px 12px;
}
.mk-college-select-menu{
  position:absolute;
  left:0;
  right:0;
  top:calc(100% + 6px);
  z-index:2500;
  display:none;
  border:1px solid #f2cfe0;
  border-radius:12px;
  background:#fff;
  box-shadow:0 12px 30px rgba(15,23,42,.08);
  max-height:240px;
  overflow:auto;
}
.mk-college-option{
  width:100%;
  text-align:left;
  border:0;
  border-bottom:1px solid #f8e5ef;
  background:#fff;
  padding:10px 12px;
  cursor:pointer;
  transition:background-color .14s ease;
}
.mk-college-option:last-child{border-bottom:0;}
.mk-college-option:hover,
.mk-college-option.active{
  background:#fff5fa;
}
.mk-college-option-title{
  display:block;
  font-size:14px;
  line-height:1.2;
  font-weight:700;
  color:#9d174d;
  margin-bottom:2px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.mk-college-option-meta{
  display:block;
  font-size:12px;
  color:#64748b;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.mk-college-meta{
  display:none;
  margin:6px 0 8px 2px;
  font-size:12px;
  font-weight:600;
  color:#6b7280;
}
.mk-prospect-search{position:relative;max-width:520px;}
.mk-prospect-searchbox{
  width:100%;
  max-width:520px;
  border:1px solid #f2cfe0;
  border-radius:12px;
  background:#fff;
  padding:10px 12px;
}
.mk-prospect-select-menu{
  position:absolute;
  left:0;
  right:0;
  top:calc(100% + 6px);
  z-index:2500;
  display:none;
  border:1px solid #f2cfe0;
  border-radius:12px;
  background:#fff;
  box-shadow:0 12px 30px rgba(15,23,42,.08);
  max-height:240px;
  overflow:auto;
}
.mk-prospect-option{
  width:100%;
  text-align:left;
  border:0;
  border-bottom:1px solid #f8e5ef;
  background:#fff;
  padding:10px 12px;
  cursor:pointer;
  transition:background-color .14s ease;
}
.mk-prospect-option:last-child{border-bottom:0;}
.mk-prospect-option:hover,
.mk-prospect-option.active{background:#fff5fa;}
.mk-prospect-option-title{
  display:block;
  font-size:14px;
  line-height:1.2;
  font-weight:700;
  color:#9d174d;
  margin-bottom:2px;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.mk-prospect-option-meta{
  display:block;
  font-size:12px;
  color:#64748b;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
}
.mk-prospect-meta{
  display:none;
  margin:6px 0 8px 2px;
  font-size:12px;
  font-weight:600;
  color:#6b7280;
}
/* April Act Report - UI only (content/logic unchanged) */
.dr-step[data-step="5"] .mk-metric-cards{
  display:grid;
  grid-template-columns:repeat(3,minmax(0,1fr));
  gap:12px;
}
.dr-step[data-step="5"] .mk-metric-cards .dr-block{
  border:1px solid #f2cfe0;
  border-radius:14px;
  overflow:hidden;
  background:#fff;
}
.dr-step[data-step="5"] .mk-metric-cards .dr-block-head{
  background:linear-gradient(180deg,#f53e87 0%,#e91e63 100%);
  color:#fff;
  text-align:center;
  font-weight:800;
  padding:8px 10px;
}
.dr-step[data-step="5"] .mk-metric-cards .dr-block-body{
  padding:10px 12px;
}
.dr-step[data-step="5"] .mk-metric-row{
  display:grid;
  grid-template-columns:1fr;
  gap:6px;
  margin-bottom:10px;
}
.dr-step[data-step="5"] .mk-metric-row:last-child{margin-bottom:0;}
.dr-step[data-step="5"] .mk-metric-row > input:first-child{
  border:0;
  background:transparent;
  color:#475569;
  font-weight:700;
  padding:0;
  height:auto;
  pointer-events:none;
}
.dr-step[data-step="5"] .mk-metric-row > input.mk-act-card-value{
  border:1px solid #f2cfe0;
  border-radius:12px;
  background:#fff;
  min-height:40px;
}
@media (max-width: 1100px){
  .dr-step[data-step="5"] .mk-metric-cards{grid-template-columns:1fr 1fr;}
}
@media (max-width: 700px){
  .dr-step[data-step="5"] .mk-metric-cards{grid-template-columns:1fr;}
}
</style>
<div class="dr-wrap<?= $hideActivityTab ? ' mk-hide-activity' : '' ?>">
  <?php if($warning!==''): ?><div class="dr-card"><div class="dr-card-body" style="color:#9a3412"><?= h($warning) ?></div></div><?php endif; ?>
  <div class="dr-card"><div class="dr-card-body">
    <div class="dr-grid"><div class="dr-field"><label>Report Date</label><input type="date" class="js-dr-report-date" value="<?= h($reportDate) ?>" min="<?= h($minSelectableDate) ?>" max="<?= h($today) ?>"></div><div class="dr-field"><label>Edit Mode</label><input readonly value="<?= h($editModeLabel) ?>"></div><div class="dr-field"><label>Role</label><input readonly value="Marketing"></div><div class="dr-field"><label>Status</label><input readonly value="<?= h(ucfirst((string)($master['status'] ?? 'draft'))) ?>"></div></div>
    <?php if($master && empty($missingTables) && $canUseMarketingForm): ?>
    <form method="POST" id="mkDailyForm">
      <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>"><input type="hidden" name="save_all_report" value="1">
      <?php foreach($sections as $k=>$v): ?><input type="hidden" name="<?= h($k) ?>" id="mk_<?= h($k) ?>"><?php endforeach; ?>
      <div class="dr-tabs" id="drTabs"><button type="button" class="dr-tab<?= $hideActivityTab ? '' : ' active' ?>" data-step="1">Activity</button><button type="button" class="dr-tab<?= $hideActivityTab ? ' active' : '' ?>" data-step="2">Hourly</button><button type="button" class="dr-tab" data-step="3">Colleges</button><button type="button" class="dr-tab" data-step="4"><?= h($mkMonthPrefix) ?> Prospect</button><button type="button" class="dr-tab" data-step="5"><?= h($mkMonthPrefix) ?> Act Report</button><button type="button" class="dr-tab" data-step="6"><?= h($mkMonthPrefix) ?> Amount</button><button type="button" class="dr-tab" data-step="7"><?= h($mkMonthPrefix) ?> Programs</button><button type="button" class="dr-tab" data-step="8">Arts Colleges</button><button type="button" class="dr-tab" data-step="9">Arts PC</button><button type="button" class="dr-tab" data-step="10">Engg Colleges</button><button type="button" class="dr-tab" data-step="11">Engg PC</button><button type="button" class="dr-tab" data-step="12">Polytech Colleges</button></div>
      <div class="dr-step<?= $hideActivityTab ? '' : ' active' ?>" data-step="1">
        <div class="dr-activity-board">
          <div class="dr-block"><div class="dr-block-head">Datas</div><div class="dr-block-body">
            <div class="dr-field"><label>No Of Fresh Calls</label><input type="number" id="mk_fresh_calls" name="fresh_calls" value="<?= h($activity['fresh_calls']) ?>"></div>
            <div class="dr-field"><label>No Of Followup Calls</label><input type="number" id="mk_follow_calls" name="follow_calls" value="<?= h($activity['follow_calls']) ?>"></div>
            <div class="dr-field"><label>Msg Sent</label><input type="number" id="mk_messages_sent" name="messages_sent" value="<?= h($activity['messages_sent']) ?>"></div>
            <div class="dr-field"><label>Mail Sent</label><input type="number" id="mk_mails_sent" name="mails_sent" value="<?= h($activity['mails_sent']) ?>"></div>
            <div class="dr-field"><label>Total Calls</label><input type="number" id="mk_total_calls" readonly value="<?= h($totalCallsNow) ?>"></div>
            <div class="dr-field"><label>Forum Posting</label><input type="number" id="mk_forum_posting" name="forum_posting" value="<?= h($activity['forum_posting']) ?>"></div>
          </div></div>
          <div class="dr-block"><div class="dr-block-head">Registration</div><div class="dr-block-body">
            <div class="dr-field"><label>Promotions</label><input type="number" id="mk_promotions" name="promotions" value="<?= h($activity['promotions']) ?>"></div>
            <div class="dr-field"><label>Reference</label><input type="number" id="mk_reference_count" name="reference_count" value="<?= h($activity['reference_count']) ?>"></div>
            <div class="dr-field"><label>DB Calls</label><input type="number" id="mk_db_calls" name="db_calls" value="<?= h($activity['db_calls']) ?>"></div>
            <div class="dr-field"><label>Total</label><input type="number" id="mk_registration_total" readonly value="<?= h($regTotalNow) ?>"></div>
          </div></div>
          <div class="dr-block"><div class="dr-block-head">Contents</div><div class="dr-block-body">
            <div class="dr-field"><label>Billing</label><input type="number" step="0.01" id="mk_billing" name="billing" value="<?= h($activity['billing']) ?>"></div>
            <div class="dr-field"><label>Fresh Collection</label><input type="number" step="0.01" id="mk_fresh_collection" name="fresh_collection" value="<?= h($activity['fresh_collection']) ?>"></div>
            <div class="dr-field"><label>Old Collection</label><input type="number" step="0.01" id="mk_old_collection" name="old_collection" value="<?= h($activity['old_collection']) ?>"></div>
            <div class="dr-field"><label>Total Collection</label><input type="number" step="0.01" id="mk_total_collection" readonly value="<?= h($totalCollectionNow) ?>"></div>
            <div class="dr-field"><label>Registration</label><input type="number" id="mk_registration_total_dup" readonly value="<?= h($regTotalNow) ?>"></div>
            <div class="dr-field"><label>Walkins</label><input type="number" id="mk_walkins" name="walkins" value="<?= h($activity['walkins']) ?>"></div>
            <div class="dr-field"><label>Conversion Ratio</label><input type="text" id="mk_conversion_ratio" readonly value="<?= h($convNow) ?>%"></div>
          </div></div>
        </div>
        <div class="dr-step-nav"><span></span><button type="button" class="dr-btn dr-btn-primary" data-next="2">Next</button></div>
      </div>
      <div class="dr-step<?= $hideActivityTab ? ' active' : '' ?>" data-step="2">
        <div class="dr-field">
          <label>Hourly Report</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">From</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">To</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Particulars</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Activities Undergone</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkHourlyBody">
                <?php foreach(($sections['hourly'] ?? []) as $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="time" class="mk-hour-time-from" value="<?= h((string)($r['time_from'] ?? '09:30')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="time" class="mk-hour-time-to" value="<?= h((string)($r['time_to'] ?? '10:30')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-hour-particulars" value="<?= h((string)($r['particulars'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><textarea class="mk-hour-activities"><?= h((string)($r['activities_undergone'] ?? '')) ?></textarea></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-hour-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddHourRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><?php if(!$hideActivityTab): ?><button type="button" class="dr-btn dr-btn-muted" data-prev="1">Back</button><?php else: ?><span></span><?php endif; ?><button type="button" class="dr-btn dr-btn-primary" data-next="3">Next</button></div>
      </div>
      <div class="dr-step" data-step="3">
        <div class="dr-field">
          <label>Colleges</label>
          <div class="mk-college-search" style="margin-bottom:8px;">
            <input id="mkCollegeSearchInput" class="mk-college-searchbox" placeholder="Type at least 3 characters to search previous colleges" autocomplete="off">
            <div id="mkCollegeSelectMenu" class="mk-college-select-menu"></div>
          </div>
          <div id="mkCollegeResultMeta" class="mk-college-meta"></div>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">S.No</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Date</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Address</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">City</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Contact Person</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Designation</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Mobile No</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Mail Id</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Status 1</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Status 2</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkCollegesBody">
                <?php foreach(($sections['colleges'] ?? []) as $idx => $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-serial" readonly value="<?= h((string)($idx+1)) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-col-date" value="<?= h((string)($r['entry_date'] ?? $reportDate)) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-college_name" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-address" value="<?= h((string)($r['address'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-city" value="<?= h((string)($r['city'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-department" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-contact_person" value="<?= h((string)($r['contact_person'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-designation" value="<?= h((string)($r['designation'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-mobile_no" value="<?= h((string)($r['mobile_no'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-mail_id" value="<?= h((string)($r['mail_id'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-status_1" value="<?= h((string)($r['status_1'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-status_2" value="<?= h((string)($r['status_2'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-col-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddCollegeRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="2">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="4">Next</button></div>
      </div>
      <div class="dr-step" data-step="4">
        <div class="dr-field">
          <label><?= h($mkMonthPrefix) ?> Prospect</label>
          <div class="mk-prospect-search" style="margin-bottom:8px;">
            <input id="mkProspectSearchInput" class="mk-prospect-searchbox" placeholder="Type at least 3 characters to search previous prospect" autocomplete="off">
            <div id="mkProspectSelectMenu" class="mk-prospect-select-menu"></div>
          </div>
          <div id="mkProspectResultMeta" class="mk-prospect-meta"></div>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">S.No</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Staff Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Designation</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Mobile</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Email</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Status Timeline</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkProspectBody">
                <?php foreach(($sections['prospect'] ?? []) as $idx => $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-serial" readonly value="<?= h((string)($idx+1)) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-staff_name" value="<?= h((string)($r['staff_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-college" value="<?= h((string)($r['college'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-department" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-designation" value="<?= h((string)($r['designation'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-mobile_number" value="<?= h((string)($r['mobile_number'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-email" value="<?= h((string)($r['email'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;min-width:360px;">
                      <div class="mk-pro-followups">
                        <?php
                          $fw = (isset($r['followups']) && is_array($r['followups'])) ? $r['followups'] : [];
                          $f = ['status_date'=>'','status_text'=>'','remarks'=>''];
                          if (!empty($fw)) {
                            $last = end($fw);
                            if (is_array($last)) $f = array_merge($f, $last);
                          }
                        ?>
                        <div class="mk-pro-followup-item" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
                          <input class="mk-pro-f-text" placeholder="Status" value="<?= h((string)($f['status_text'] ?? '')) ?>">
                          <input class="mk-pro-f-remarks" placeholder="Remarks" value="<?= h((string)($f['remarks'] ?? '')) ?>">
                        </div>
                      </div>
                    </td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-pro-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddProspectRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="3">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="5">Next</button></div>
      </div>
      <div class="dr-step" data-step="5">
        <div class="dr-field">
          <label><?= h($mkMonthPrefix) ?> Act Report</label>
          <div class="mk-metric-cards">
            <div class="dr-block" data-act-col="1">
              <div class="dr-block-head">Datas</div>
              <div class="dr-block-body">
                <?php foreach(['No of Hods Met','No of Asst Professor Met','Total Calls','No of Colleges Visited','No of Companies Visited'] as $m): $k=strtolower($m); ?>
                  <div class="mk-metric-row"><input value="<?= h($m) ?>" readonly><input class="mk-act-card-value" data-metric="<?= h($m) ?>" placeholder="Enter <?= h($m) ?>" value="<?= h($mkActValues[$k] ?? '') ?>"></div>
                <?php endforeach; ?>
                <div class="mk-metric-row"><input value="Total Value" readonly><input id="mk_act_col1_total" placeholder="Auto calculated" readonly></div>
              </div>
            </div>
            <div class="dr-block" data-act-col="2">
              <div class="dr-block-head">Business Break Ups</div>
              <div class="dr-block-body">
                <?php foreach(['Students Reference','Workshop','On Campus Training','Project Taken'] as $m): $k=strtolower($m); ?>
                  <div class="mk-metric-row"><input value="<?= h($m) ?>" readonly><input class="mk-act-card-value" data-metric="<?= h($m) ?>" placeholder="Enter <?= h($m) ?>" value="<?= h($mkActValues[$k] ?? '') ?>"></div>
                <?php endforeach; ?>
                <div class="mk-metric-row"><input value="Total Value" readonly><input id="mk_act_col2_total" placeholder="Auto calculated" readonly></div>
              </div>
            </div>
            <div class="dr-block" data-act-col="3">
              <div class="dr-block-head">Contents</div>
              <div class="dr-block-body">
                <?php foreach(['Billing','Fresh Collection','Old Collection','Total Collection','Registration','Walkins'] as $m): $k=strtolower($m); ?>
                  <?php $mkReadOnly = ($k === 'total collection'); ?>
                  <div class="mk-metric-row"><input value="<?= h($m) ?>" readonly><input class="mk-act-card-value" data-metric="<?= h($m) ?>" placeholder="<?= $mkReadOnly ? 'Auto calculated' : ('Enter '.h($m)) ?>" value="<?= h($mkActValues[$k] ?? '') ?>"<?= $mkReadOnly ? ' readonly' : '' ?>></div>
                <?php endforeach; ?>
                <div class="mk-metric-row"><input value="Conversion Ratio (%)" readonly><input id="mk_act_col3_ratio" placeholder="Auto calculated" readonly></div>
              </div>
            </div>
          </div>
        </div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="4">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="6">Next</button></div>
      </div>
      <div class="dr-step" data-step="6">
        <div class="dr-field">
          <label><?= h($mkMonthPrefix) ?> Amount</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">S.No</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Date</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Dept / Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Particulars</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Bank Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Cash</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Amount</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkAmountBody">
                <?php foreach(($sections['amount'] ?? []) as $idx => $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-serial" readonly value="<?= h((string)($idx+1)) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-amt-entry_date" value="<?= h((string)($r['entry_date'] ?? $reportDate)) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-college_name" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-dept_or_name" value="<?= h((string)($r['dept_or_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-particulars" value="<?= h((string)($r['particulars'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-bank" value="<?= h((string)($r['bank'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-amt-cash" value="<?= h((string)($r['cash'] ?? '0.00')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-amt-amount" value="<?= h((string)($r['amount'] ?? '0.00')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-amt-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddAmountRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="5">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="7">Next</button></div>
      </div>
      <div class="dr-step" data-step="7">
        <div class="dr-field">
          <label><?= h($mkMonthPrefix) ?> Programs</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;min-width:2600px;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Class</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Program Given By</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Designation</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Program Type</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Domain</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Trainer</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Topics</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">No. Days</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Start Date</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">End Date</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Hours</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Students</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Amount</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Collection</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Pending</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkProgramBody">
                <?php foreach(($sections['program'] ?? []) as $idx => $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-college" style="min-width:170px" value="<?= h((string)($r['college'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-department" style="min-width:150px" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-class_name" style="min-width:140px" value="<?= h((string)($r['class_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-program_given_by" style="min-width:170px" value="<?= h((string)($r['program_given_by'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-designation" style="min-width:140px" value="<?= h((string)($r['designation'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-program_type" style="min-width:150px" value="<?= h((string)($r['program_type'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-domain" style="min-width:140px" value="<?= h((string)($r['domain'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-trainer" style="min-width:150px" value="<?= h((string)($r['trainer'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-topics" style="min-width:190px" value="<?= h((string)($r['topics'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-no_days" style="min-width:110px" value="<?= h((string)($r['no_days'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-prg-day_start" style="min-width:145px" value="<?= h((string)($r['day_start'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-prg-end_day" style="min-width:145px" value="<?= h((string)($r['end_day'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-hours" style="min-width:110px" value="<?= h((string)($r['hours'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" class="mk-prg-no_of_students" style="min-width:110px" value="<?= h((string)($r['no_of_students'] ?? '0')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-prg-amount" style="min-width:120px" value="<?= h((string)($r['amount'] ?? '0.00')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-prg-collection" style="min-width:120px" value="<?= h((string)($r['collection'] ?? '0.00')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-prg-pending" style="min-width:120px" readonly value="<?= h(number_format(max(0, (float)($r['amount'] ?? 0) - (float)($r['collection'] ?? 0)), 2, '.', '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-prg-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddProgramRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="6">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="8">Next</button></div>
      </div>
      <div class="dr-step" data-step="8">
        <div class="dr-field">
          <label>Arts Colleges</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;min-width:1900px;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Address</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">City</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Contact Person</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Designation</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Phone Number</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Email Id</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkArtsCollegeBody">
                <?php foreach(($sections['arts_college'] ?? []) as $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-college_name" style="min-width:220px" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-address" style="min-width:230px" value="<?= h((string)($r['address'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-city" style="min-width:160px" value="<?= h((string)($r['city'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-department" style="min-width:170px" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-contact_person" style="min-width:190px" value="<?= h((string)($r['contact_person'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-designation" style="min-width:160px" value="<?= h((string)($r['designation'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-phone_number" style="min-width:150px" value="<?= h((string)($r['phone_number'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-email_id" style="min-width:220px" value="<?= h((string)($r['email_id'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-ac-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddArtsCollegeRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="7">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="9">Next</button></div>
      </div>
      <div class="dr-step" data-step="9">
        <div class="dr-field">
          <label>Arts PC</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;min-width:1700px;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Place Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Designation</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Contact Number</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkArtsPcBody">
                <?php foreach(($sections['arts_pc'] ?? []) as $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-place_name" style="min-width:170px" value="<?= h((string)($r['place_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-college_name" style="min-width:220px" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-department" style="min-width:170px" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-name" style="min-width:180px" value="<?= h((string)($r['name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-designation" style="min-width:160px" value="<?= h((string)($r['designation'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-contact_number" style="min-width:160px" value="<?= h((string)($r['contact_number'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-apc-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddArtsPcRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="8">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="10">Next</button></div>
      </div>
      <div class="dr-step" data-step="10">
        <div class="dr-field">
          <label>Engg Colleges</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;min-width:2200px;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Address</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">City</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Contact Person</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Designation</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Phone Number</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Email Id</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">DOB</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">DOA</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkEnggCollegeBody">
                <?php foreach(($sections['engg_college'] ?? []) as $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-college_name" style="min-width:220px" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-address" style="min-width:230px" value="<?= h((string)($r['address'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-city" style="min-width:160px" value="<?= h((string)($r['city'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-department" style="min-width:170px" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-contact_person" style="min-width:190px" value="<?= h((string)($r['contact_person'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-designation" style="min-width:160px" value="<?= h((string)($r['designation'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-phone_number" style="min-width:150px" value="<?= h((string)($r['phone_number'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-email_id" style="min-width:220px" value="<?= h((string)($r['email_id'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-ec-dob" style="min-width:145px" value="<?= h((string)($r['dob'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-ec-doa" style="min-width:145px" value="<?= h((string)($r['doa'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-ec-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddEnggCollegeRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="9">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="11">Next</button></div>
      </div>
      <div class="dr-step" data-step="11">
        <div class="dr-field">
          <label>Engg PC</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;min-width:1700px;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Place Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Contact Number</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Email Id</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkEnggPcBody">
                <?php foreach(($sections['engg_pc'] ?? []) as $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-place_name" style="min-width:170px" value="<?= h((string)($r['place_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-college_name" style="min-width:220px" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-department" style="min-width:170px" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-name" style="min-width:180px" value="<?= h((string)($r['name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-contact_number" style="min-width:160px" value="<?= h((string)($r['contact_number'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-email_id" style="min-width:220px" value="<?= h((string)($r['email_id'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-epc-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddEnggPcRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="10">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="12">Next</button></div>
      </div>
      <div class="dr-step" data-step="12">
        <div class="dr-field">
          <label>Polytech Colleges</label>
          <div style="overflow:auto;border:1px solid #f1d6e3;border-radius:10px;">
            <table style="width:100%;min-width:2200px;border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">College Name</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Address</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">City</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Department</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Contact Person</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Designation</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Phone Number</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Email Id</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">DOB</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">DOA</th>
                  <th style="border:1px solid #f1d6e3;padding:8px;background:#fff4fa;color:#9d174d;">Action</th>
                </tr>
              </thead>
              <tbody id="mkPolytechCollegeBody">
                <?php foreach(($sections['polytech_college'] ?? []) as $r): ?>
                  <tr>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-college_name" style="min-width:220px" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-address" style="min-width:230px" value="<?= h((string)($r['address'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-city" style="min-width:160px" value="<?= h((string)($r['city'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-department" style="min-width:170px" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-contact_person" style="min-width:190px" value="<?= h((string)($r['contact_person'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-designation" style="min-width:160px" value="<?= h((string)($r['designation'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-phone_number" style="min-width:150px" value="<?= h((string)($r['phone_number'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-email_id" style="min-width:220px" value="<?= h((string)($r['email_id'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-pc-dob" style="min-width:145px" value="<?= h((string)($r['dob'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-pc-doa" style="min-width:145px" value="<?= h((string)($r['doa'] ?? '')) ?>"></td>
                    <td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-pc-row" style="height:32px;">Delete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-btn dr-btn-primary" id="mkAddPolytechCollegeRow" style="height:34px;">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="11">Back</button><button type="submit" class="dr-btn dr-btn-success">Save All Sections</button></div>
      </div>
    </form>
    <?php endif; ?>
  </div></div>
</div>
<script>
(function(){
function init(){
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
  root.querySelectorAll('.js-dr-report-date').forEach(function(el){
    el.setAttribute('min', '<?= h($minSelectableDate) ?>');
    el.setAttribute('max', '<?= h($today) ?>');
  });
  root.addEventListener('change', function(e){
    if(!e.target.classList.contains('js-dr-report-date')) return;
    const dt = (e.target.value || '').trim();
    if(!dt) return;
    const minDt = '<?= h($minSelectableDate) ?>';
    const maxDt = '<?= h($today) ?>';
    if (dt < minDt || dt > maxDt) {
      e.target.value = '<?= h($reportDate) ?>';
      return;
    }
    drAjaxSwap('index.php?page=dailyreports/entry&report_type=marketing&report_date=' + encodeURIComponent(dt));
  });
  const tabs=[...document.querySelectorAll('.dr-tab')],steps=[...document.querySelectorAll('.dr-step')];
  function show(n){tabs.forEach(t=>t.classList.toggle('active',+t.dataset.step===n));steps.forEach(s=>s.classList.toggle('active',+s.dataset.step===n));window.scrollTo({top:0,behavior:'smooth'});}
  tabs.forEach(t=>t.addEventListener('click',()=>show(+t.dataset.step)));
  document.querySelectorAll('[data-next]').forEach(b=>b.addEventListener('click',()=>show(+b.dataset.next)));
  document.querySelectorAll('[data-prev]').forEach(b=>b.addEventListener('click',()=>show(+b.dataset.prev)));
  const n=id=>{const el=document.getElementById(id); return el?parseFloat(el.value||0):0;};
  function calc(){ const tc=n('mk_fresh_calls')+n('mk_follow_calls')+n('mk_messages_sent')+n('mk_mails_sent'); const rt=n('mk_promotions')+n('mk_reference_count')+n('mk_db_calls'); const col=n('mk_fresh_collection')+n('mk_old_collection'); const cv=tc>0?(rt/tc)*100:0; const set=(id,v,d)=>{const el=document.getElementById(id); if(el) el.value=d!=null?Number(v||0).toFixed(d):String(Math.round(v||0));}; set('mk_total_calls',tc); set('mk_registration_total',rt); set('mk_registration_total_dup',rt); set('mk_total_collection',col,2); const c=document.getElementById('mk_conversion_ratio'); if(c) c.value=Number(cv).toFixed(2)+'%'; }
  ['mk_fresh_calls','mk_follow_calls','mk_messages_sent','mk_mails_sent','mk_promotions','mk_reference_count','mk_db_calls','mk_fresh_collection','mk_old_collection'].forEach(function(id){ const el=document.getElementById(id); if(el){ el.addEventListener('input',calc); el.addEventListener('change',calc); } }); calc();
  function addOneHour(hhmm){ const m=/^(\d{2}):(\d{2})$/.exec((hhmm||'').trim()); if(!m) return null; let h=parseInt(m[1],10); const mm=m[2]; h=(h+1)%24; return String(h).padStart(2,'0')+':'+mm; }
  function getNextHourlySlot(){
    const rows=document.querySelectorAll('#mkHourlyBody tr');
    if(!rows.length) return {from:'09:30',to:'10:30'};
    const last=rows[rows.length-1];
    const lastFrom=(last.querySelector('.mk-hour-time-from')?.value||'').trim();
    const lastTo=(last.querySelector('.mk-hour-time-to')?.value||'').trim();
    const nextFrom=/^\d{2}:\d{2}$/.test(lastTo)?lastTo:(addOneHour(lastFrom)||'09:30');
    const nextTo=addOneHour(nextFrom)||'10:30';
    return {from:nextFrom,to:nextTo};
  }
  document.getElementById('mkAddHourRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkHourlyBody'); if(!body) return;
    const slot=getNextHourlySlot();
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input type="time" class="mk-hour-time-from" value="'+slot.from+'"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="time" class="mk-hour-time-to" value="'+slot.to+'"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-hour-particulars"></td><td style="border:1px solid #f1d6e3;padding:8px;"><textarea class="mk-hour-activities"></textarea></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-hour-row" style="height:32px;">Delete</button></td>';
    body.appendChild(tr);
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-hour-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove();
  });
  function renumberCollegeRows(){
    document.querySelectorAll('#mkCollegesBody tr').forEach(function(tr,idx){ const el=tr.querySelector('.mk-col-serial'); if(el) el.value=String(idx+1); });
  }
  function createCollegeRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-serial" readonly value=""></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-col-date" value="<?= h($reportDate) ?>"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-college_name"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-address"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-city"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-department"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-contact_person"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-designation"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-mobile_no"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-mail_id"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-status_1"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-col-status_2"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-col-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddCollegeRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkCollegesBody'); if(!body) return;
    const tr=createCollegeRow();
    body.appendChild(tr); renumberCollegeRows();
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-col-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove(); renumberCollegeRows();
  });
  renumberCollegeRows();
  function renumberProspectRows(){
    document.querySelectorAll('#mkProspectBody tr').forEach(function(tr,idx){ const el=tr.querySelector('.mk-pro-serial'); if(el) el.value=String(idx+1); });
  }
  function createProspectRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-serial" readonly value=""></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-staff_name"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-college"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-department"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-designation"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-mobile_number"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pro-email"></td><td style="border:1px solid #f1d6e3;padding:8px;min-width:360px;"><div class="mk-pro-followups"><div class="mk-pro-followup-item" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;"><input class="mk-pro-f-text" placeholder="Status"><input class="mk-pro-f-remarks" placeholder="Remarks"></div></div></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-pro-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddProspectRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkProspectBody'); if(!body) return;
    body.appendChild(createProspectRow()); renumberProspectRows();
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-pro-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove(); renumberProspectRows();
  });
  renumberProspectRows();
  function renumberAmountRows(){
    document.querySelectorAll('#mkAmountBody tr').forEach(function(tr,idx){ const el=tr.querySelector('.mk-amt-serial'); if(el) el.value=String(idx+1); });
  }
  function createAmountRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-serial" readonly value=""></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-amt-entry_date" value="<?= h($reportDate) ?>"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-college_name"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-dept_or_name"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-particulars"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-amt-bank"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-amt-cash" value="0.00"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-amt-amount" value="0.00"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-amt-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddAmountRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkAmountBody'); if(!body) return;
    body.appendChild(createAmountRow()); renumberAmountRows();
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-amt-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove(); renumberAmountRows();
  });
  renumberAmountRows();
  function createProgramRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-college" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-department" style="min-width:150px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-class_name" style="min-width:140px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-program_given_by" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-designation" style="min-width:140px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-program_type" style="min-width:150px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-domain" style="min-width:140px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-trainer" style="min-width:150px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-topics" style="min-width:190px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-no_days" style="min-width:110px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-prg-day_start" style="min-width:145px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-prg-end_day" style="min-width:145px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-prg-hours" style="min-width:110px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" class="mk-prg-no_of_students" style="min-width:110px" value="0"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-prg-amount" style="min-width:120px" value="0.00"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-prg-collection" style="min-width:120px" value="0.00"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="number" step="0.01" class="mk-prg-pending" style="min-width:120px" readonly value="0.00"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-prg-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddProgramRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkProgramBody'); if(!body) return;
    body.appendChild(createProgramRow());
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-prg-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove();
  });
  function updateProgramPendingForRow(tr){
    if(!tr) return;
    const a=parseFloat((tr.querySelector('.mk-prg-amount')?.value||'0').toString()) || 0;
    const c=parseFloat((tr.querySelector('.mk-prg-collection')?.value||'0').toString()) || 0;
    const p=Math.max(0,a-c);
    const el=tr.querySelector('.mk-prg-pending');
    if(el) el.value=p.toFixed(2);
  }
  root.addEventListener('input', function(e){
    if(!(e.target.classList.contains('mk-prg-amount') || e.target.classList.contains('mk-prg-collection'))) return;
    updateProgramPendingForRow(e.target.closest('tr'));
  });
  document.querySelectorAll('#mkProgramBody tr').forEach(updateProgramPendingForRow);
  function createArtsCollegeRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-college_name" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-address" style="min-width:230px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-city" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-department" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-contact_person" style="min-width:190px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-designation" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-phone_number" style="min-width:150px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ac-email_id" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-ac-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddArtsCollegeRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkArtsCollegeBody'); if(!body) return;
    body.appendChild(createArtsCollegeRow());
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-ac-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove();
  });
  function createArtsPcRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-place_name" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-college_name" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-department" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-name" style="min-width:180px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-designation" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-apc-contact_number" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-apc-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddArtsPcRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkArtsPcBody'); if(!body) return;
    body.appendChild(createArtsPcRow());
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-apc-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove();
  });
  function createEnggCollegeRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-college_name" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-address" style="min-width:230px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-city" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-department" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-contact_person" style="min-width:190px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-designation" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-phone_number" style="min-width:150px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-ec-email_id" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-ec-dob" style="min-width:145px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-ec-doa" style="min-width:145px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-ec-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddEnggCollegeRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkEnggCollegeBody'); if(!body) return;
    body.appendChild(createEnggCollegeRow());
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-ec-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove();
  });
  function createEnggPcRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-place_name" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-college_name" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-department" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-name" style="min-width:180px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-contact_number" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-epc-email_id" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-epc-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddEnggPcRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkEnggPcBody'); if(!body) return;
    body.appendChild(createEnggPcRow());
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-epc-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove();
  });
  function createPolytechCollegeRow(){
    const tr=document.createElement('tr');
    tr.innerHTML='<td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-college_name" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-address" style="min-width:230px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-city" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-department" style="min-width:170px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-contact_person" style="min-width:190px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-designation" style="min-width:160px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-phone_number" style="min-width:150px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input class="mk-pc-email_id" style="min-width:220px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-pc-dob" style="min-width:145px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><input type="date" class="mk-pc-doa" style="min-width:145px"></td><td style="border:1px solid #f1d6e3;padding:8px;"><button type="button" class="dr-btn dr-btn-muted js-del-pc-row" style="height:32px;">Delete</button></td>';
    return tr;
  }
  document.getElementById('mkAddPolytechCollegeRow')?.addEventListener('click',function(){
    const body=document.getElementById('mkPolytechCollegeBody'); if(!body) return;
    body.appendChild(createPolytechCollegeRow());
  });
  root.addEventListener('click',function(e){
    if(!e.target.classList.contains('js-del-pc-row')) return;
    const tr=e.target.closest('tr'); if(tr) tr.remove();
  });
  function findProspectTargetRow(){
    let target=null;
    document.querySelectorAll('#mkProspectBody tr').forEach(function(tr){
      if(target) return;
      const staff=(tr.querySelector('.mk-pro-staff_name')?.value||'').trim();
      const college=(tr.querySelector('.mk-pro-college')?.value||'').trim();
      const mobile=(tr.querySelector('.mk-pro-mobile_number')?.value||'').trim();
      if(staff==='' && college==='' && mobile==='') target=tr;
    });
    if(target) return target;
    const body=document.getElementById('mkProspectBody');
    if(!body) return null;
    const tr=createProspectRow();
    body.appendChild(tr);
    renumberProspectRows();
    return tr;
  }
  function setProspectFollowups(tr, followups){
    const wrap=tr?.querySelector('.mk-pro-followups');
    if(!wrap) return;
    wrap.innerHTML='';
    const list = Array.isArray(followups) ? followups : [];
    const one = list.length ? list[list.length-1] : {};
    const t=((one && one.status_text) || '').toString();
    const r=((one && one.remarks) || '').toString();
    const div=document.createElement('div');
    div.className='mk-pro-followup-item';
    div.style.cssText='display:flex;gap:6px;align-items:center;margin-bottom:6px;';
    div.innerHTML='<input class="mk-pro-f-text" placeholder="Status" value="'+t.replace(/"/g,'&quot;')+'"><input class="mk-pro-f-remarks" placeholder="Remarks" value="'+r.replace(/"/g,'&quot;')+'">';
    wrap.appendChild(div);
  }
  function fillProspectRow(tr, row){
    if(!tr || !row) return;
    const set=(cls,val)=>{ const el=tr.querySelector(cls); if(el) el.value=(val||'').toString(); };
    set('.mk-pro-staff_name', row.staff_name);
    set('.mk-pro-college', row.college);
    set('.mk-pro-department', row.department);
    set('.mk-pro-designation', row.designation);
    set('.mk-pro-mobile_number', row.mobile_number);
    set('.mk-pro-email', row.email);
    setProspectFollowups(tr, row.followups || []);
    const f=tr.querySelector('.mk-pro-f-text'); if(f) f.focus();
  }
  const prospectSuggestInput = document.getElementById('mkProspectSearchInput');
  const prospectResultMeta = document.getElementById('mkProspectResultMeta');
  const prospectSelectMenu = document.getElementById('mkProspectSelectMenu');
  let prospectSuggestTimer = null;
  let lastProspectQuery = '';
  let lastProspectRows = [];
  let prospectActiveIndex = -1;
  function hideProspectSuggest(){
    if(prospectSelectMenu){
      prospectSelectMenu.style.display='none';
      prospectSelectMenu.innerHTML='';
    }
    prospectActiveIndex = -1;
    if(prospectResultMeta){
      prospectResultMeta.style.display='none';
      prospectResultMeta.textContent='';
    }
  }
  function renderProspectSuggest(rows){
    if(!rows.length){ hideProspectSuggest(); return; }
    if(prospectResultMeta){
      prospectResultMeta.style.display='block';
      prospectResultMeta.textContent='Found ' + rows.length + ' match(es). Click one to auto fill.';
      prospectResultMeta.style.color='#166534';
    }
    if(prospectSelectMenu){
      prospectSelectMenu.innerHTML = rows.map(function(r,idx){
        const title = (r.staff_name||'-') + ' | ' + (r.college||'-');
        const meta = (r.mobile_number||'-') + ' | ' + (r.email||'-') + ' | ' + (r.report_date||'-');
        return '<button type="button" class="mk-prospect-option'+(idx===0?' active':'')+'" data-idx="'+idx+'">'
          + '<span class="mk-prospect-option-title">'+title.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</span>'
          + '<span class="mk-prospect-option-meta">'+meta.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</span>'
          + '</button>';
      }).join('');
      prospectSelectMenu.style.display='block';
      prospectActiveIndex = rows.length ? 0 : -1;
    }
  }
  async function fetchProspectSuggest(q){
    const url='index.php?page=dailyreports/entry&report_type=marketing&ajax=prospect_lookup&q='+encodeURIComponent(q);
    const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
    const text=await res.text();
    let data=null;
    const cleaned=(text||'').replace(/^\uFEFF/,'').trim();
    try{
      data=JSON.parse(cleaned);
    }catch(e){
      const s=cleaned.indexOf('{');
      const eidx=cleaned.lastIndexOf('}');
      if(s!==-1 && eidx>s){
        try{ data=JSON.parse(cleaned.slice(s,eidx+1)); }catch(_){ data=null; }
      }else{
        data=null;
      }
    }
    if(!res.ok || !data || data.ok===false) throw new Error((text||'').trim() || ('HTTP '+res.status));
    return Array.isArray(data.rows)?data.rows:[];
  }
  function queueProspectSuggestLookup(){
    if(!prospectSuggestInput) return;
    const q=(prospectSuggestInput.value||'').trim();
    if(q.length < 3){ hideProspectSuggest(); lastProspectQuery=''; lastProspectRows=[]; return; }
    if(q === lastProspectQuery) return;
    if(prospectResultMeta){
      prospectResultMeta.style.display='block';
      prospectResultMeta.textContent='Searching...';
      prospectResultMeta.style.color='#6b7280';
    }
    if(prospectSuggestTimer) clearTimeout(prospectSuggestTimer);
    prospectSuggestTimer=setTimeout(async function(){
      lastProspectQuery=q;
      try{
        const rows=await fetchProspectSuggest(q);
        lastProspectRows=rows;
        renderProspectSuggest(rows.slice(0,10));
        if(prospectResultMeta && !rows.length){
          prospectResultMeta.style.display='block';
          prospectResultMeta.textContent='No matches found.';
          prospectResultMeta.style.color='#6b7280';
        }
      }catch(e){
        lastProspectQuery='';
        hideProspectSuggest();
        if(prospectResultMeta){
          prospectResultMeta.style.display='block';
          prospectResultMeta.textContent='Search error: ' + (e && e.message ? e.message : 'Unable to fetch matches.');
          prospectResultMeta.style.color='#b91c1c';
        }
      }
    },250);
  }
  function pickProspectByIndex(idx){
    const row=lastProspectRows[idx];
    if(!row) return;
    const tr=findProspectTargetRow();
    fillProspectRow(tr,row);
    hideProspectSuggest();
  }
  prospectSuggestInput?.addEventListener('keyup', queueProspectSuggestLookup);
  prospectSuggestInput?.addEventListener('input', queueProspectSuggestLookup);
  prospectSelectMenu?.addEventListener('click', function(e){
    const btn=e.target.closest('.mk-prospect-option');
    if(!btn) return;
    const idx=parseInt(btn.getAttribute('data-idx')||'',10);
    if(Number.isNaN(idx)) return;
    pickProspectByIndex(idx);
  });
  prospectSuggestInput?.addEventListener('keydown', function(e){
    if(!prospectSelectMenu || prospectSelectMenu.style.display!=='block') return;
    if(e.key!=='ArrowDown' && e.key!=='ArrowUp' && e.key!=='Enter' && e.key!=='Escape') return;
    if(e.key==='Escape'){ hideProspectSuggest(); return; }
    const options=Array.from(prospectSelectMenu.querySelectorAll('.mk-prospect-option'));
    if(!options.length) return;
    if(e.key==='Enter'){
      e.preventDefault();
      if(prospectActiveIndex<0) prospectActiveIndex=0;
      pickProspectByIndex(prospectActiveIndex);
      return;
    }
    e.preventDefault();
    if(prospectActiveIndex<0) prospectActiveIndex=0;
    if(e.key==='ArrowDown') prospectActiveIndex=Math.min(options.length-1, prospectActiveIndex+1);
    if(e.key==='ArrowUp') prospectActiveIndex=Math.max(0, prospectActiveIndex-1);
    options.forEach(function(el,i){ el.classList.toggle('active', i===prospectActiveIndex); });
    options[prospectActiveIndex].scrollIntoView({ block:'nearest' });
  });
  document.addEventListener('click', function(e){
    if(!prospectSuggestInput) return;
    if(prospectSelectMenu && prospectSelectMenu.contains(e.target)) return;
    if(prospectSuggestInput.contains(e.target)) return;
    hideProspectSuggest();
  });
  function findCollegeTargetRow(){
    let target=null;
    document.querySelectorAll('#mkCollegesBody tr').forEach(function(tr){
      if(target) return;
      const name=(tr.querySelector('.mk-col-college_name')?.value||'').trim();
      const cp=(tr.querySelector('.mk-col-contact_person')?.value||'').trim();
      const mob=(tr.querySelector('.mk-col-mobile_no')?.value||'').trim();
      if(name==='' && cp==='' && mob==='') target=tr;
    });
    if(target) return target;
    const body=document.getElementById('mkCollegesBody');
    if(!body) return null;
    const tr=createCollegeRow();
    body.appendChild(tr);
    renumberCollegeRows();
    return tr;
  }
  function fillCollegeRow(tr, row){
    if(!tr || !row) return;
    const set=(cls,val)=>{ const el=tr.querySelector(cls); if(el) el.value=(val||'').toString(); };
    set('.mk-col-college_name', row.college_name);
    set('.mk-col-address', row.address);
    set('.mk-col-city', row.city);
    set('.mk-col-department', row.department);
    set('.mk-col-contact_person', row.contact_person);
    set('.mk-col-designation', row.designation);
    set('.mk-col-mobile_no', row.mobile_no);
    set('.mk-col-mail_id', row.mail_id);
    const s1=tr.querySelector('.mk-col-status_1'); if(s1) s1.focus();
  }
  const collegeSuggestInput = document.getElementById('mkCollegeSearchInput');
  const collegeResultMeta = document.getElementById('mkCollegeResultMeta');
  const collegeSelectMenu = document.getElementById('mkCollegeSelectMenu');
  let collegeSuggestTimer = null;
  let lastCollegeQuery = '';
  let lastCollegeRows = [];
  let collegeActiveIndex = -1;
  function hideCollegeSuggest(){
    if (collegeSelectMenu) {
      collegeSelectMenu.style.display = 'none';
      collegeSelectMenu.innerHTML = '';
    }
    collegeActiveIndex = -1;
    if (collegeResultMeta) {
      collegeResultMeta.style.display = 'none';
      collegeResultMeta.textContent = '';
    }
  }
  function renderCollegeSuggest(rows){
    if(!rows.length){ hideCollegeSuggest(); return; }
    if (collegeResultMeta) {
      collegeResultMeta.style.display = 'block';
      collegeResultMeta.textContent = 'Found ' + rows.length + ' match(es). Click one to auto fill.';
      collegeResultMeta.style.color = '#166534';
    }
    if (collegeSelectMenu) {
      collegeSelectMenu.innerHTML = rows.map(function(r,idx){
        const title = (r.college_name||'-');
        const meta = (r.contact_person||'-') + ' | ' + (r.mobile_no||'-') + ' | ' + (r.city||'-') + ' | ' + (r.report_date||'-');
        return '<button type="button" class="mk-college-option'+(idx===0?' active':'')+'" data-idx="'+idx+'">'
          + '<span class="mk-college-option-title">'+title.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</span>'
          + '<span class="mk-college-option-meta">'+meta.replace(/</g,'&lt;').replace(/>/g,'&gt;')+'</span>'
          + '</button>';
      }).join('');
      collegeSelectMenu.style.display = 'block';
      collegeActiveIndex = rows.length ? 0 : -1;
    }
  }
  async function fetchCollegeSuggest(q){
    const url='index.php?page=dailyreports/entry&report_type=marketing&ajax=college_lookup&report_date=<?= h($reportDate) ?>&q='+encodeURIComponent(q);
    const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
    const text=await res.text();
    let data=null;
    const cleaned = (text || '').replace(/^\uFEFF/, '').trim();
    try{
      data=JSON.parse(cleaned);
    }catch(e){
      const s = cleaned.indexOf('{');
      const eidx = cleaned.lastIndexOf('}');
      if(s !== -1 && eidx > s){
        try{ data = JSON.parse(cleaned.slice(s, eidx + 1)); }catch(_){ data=null; }
      } else {
        data=null;
      }
    }
    if(!res.ok || !data || data.ok===false) throw new Error((text||'').trim() || ('HTTP '+res.status));
    return Array.isArray(data.rows)?data.rows:[];
  }
  function queueCollegeSuggestLookup(){
    if(!collegeSuggestInput) return;
    const q=(collegeSuggestInput.value||'').trim();
    if(q.length < 3){ hideCollegeSuggest(); lastCollegeQuery=''; lastCollegeRows=[]; return; }
    if(q === lastCollegeQuery) return;
    if(collegeResultMeta){
      collegeResultMeta.style.display='block';
      collegeResultMeta.textContent='Searching...';
      collegeResultMeta.style.color='#6b7280';
    }
    if(collegeSuggestTimer) clearTimeout(collegeSuggestTimer);
    collegeSuggestTimer = setTimeout(async function(){
      lastCollegeQuery = q;
      try{
        const rows = await fetchCollegeSuggest(q);
        lastCollegeRows = rows;
        renderCollegeSuggest(rows.slice(0, 10));
        if(collegeResultMeta && !rows.length){
          collegeResultMeta.style.display='block';
          collegeResultMeta.textContent='No matches found.';
          collegeResultMeta.style.color='#6b7280';
        }
      }catch(e){
        lastCollegeQuery = '';
        hideCollegeSuggest();
        if(collegeResultMeta){
          collegeResultMeta.style.display='block';
          collegeResultMeta.textContent='Search error: ' + (e && e.message ? e.message : 'Unable to fetch matches.');
          collegeResultMeta.style.color='#b91c1c';
        }
      }
    }, 250);
  }
  collegeSuggestInput?.addEventListener('keyup', queueCollegeSuggestLookup);
  collegeSuggestInput?.addEventListener('input', queueCollegeSuggestLookup);
  function pickCollegeByIndex(idx){
    const row = lastCollegeRows[idx];
    if(!row) return;
    const tr = findCollegeTargetRow();
    fillCollegeRow(tr,row);
    hideCollegeSuggest();
  }
  collegeSelectMenu?.addEventListener('click', function(e){
    const btn = e.target.closest('.mk-college-option');
    if(!btn) return;
    const idx = parseInt(btn.getAttribute('data-idx') || '', 10);
    if (Number.isNaN(idx)) return;
    pickCollegeByIndex(idx);
  });
  collegeSuggestInput?.addEventListener('keydown', function(e){
    if(!collegeSelectMenu || collegeSelectMenu.style.display !== 'block') return;
    if(e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'Enter' && e.key !== 'Escape') return;
    if(e.key === 'Escape'){ hideCollegeSuggest(); return; }
    const options = Array.from(collegeSelectMenu.querySelectorAll('.mk-college-option'));
    if(!options.length) return;
    if(e.key === 'Enter'){
      e.preventDefault();
      if(collegeActiveIndex < 0) collegeActiveIndex = 0;
      pickCollegeByIndex(collegeActiveIndex);
      return;
    }
    e.preventDefault();
    if(collegeActiveIndex < 0) collegeActiveIndex = 0;
    if(e.key === 'ArrowDown') collegeActiveIndex = Math.min(options.length - 1, collegeActiveIndex + 1);
    if(e.key === 'ArrowUp') collegeActiveIndex = Math.max(0, collegeActiveIndex - 1);
    options.forEach(function(el, i){ el.classList.toggle('active', i === collegeActiveIndex); });
    options[collegeActiveIndex].scrollIntoView({ block:'nearest' });
  });
  document.addEventListener('click', function(e){
    if(!collegeSuggestInput) return;
    if(collegeSelectMenu && collegeSelectMenu.contains(e.target)) return;
    if(collegeSuggestInput.contains(e.target)) return;
    hideCollegeSuggest();
  });
  function serializeHourly(){
    const rows=[]; let valid=0;
    document.querySelectorAll('#mkHourlyBody tr').forEach(function(tr){
      const one={time_from:(tr.querySelector('.mk-hour-time-from')?.value||'').trim(),time_to:(tr.querySelector('.mk-hour-time-to')?.value||'').trim(),particulars:(tr.querySelector('.mk-hour-particulars')?.value||'').trim(),activities_undergone:(tr.querySelector('.mk-hour-activities')?.value||'').trim()};
      if(one.time_from || one.time_to || one.particulars || one.activities_undergone) rows.push(one);
      if(one.time_from && one.time_to && one.particulars) valid++;
    });
    const target=document.getElementById('mk_hourly'); if(target) target.value=JSON.stringify(rows);
    return valid;
  }
  function serializeColleges(){
    const rows=[];
    document.querySelectorAll('#mkCollegesBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        entry_date:(tr.querySelector('.mk-col-date')?.value||'').trim(),
        college_name:(tr.querySelector('.mk-col-college_name')?.value||'').trim(),
        address:(tr.querySelector('.mk-col-address')?.value||'').trim(),
        city:(tr.querySelector('.mk-col-city')?.value||'').trim(),
        department:(tr.querySelector('.mk-col-department')?.value||'').trim(),
        contact_person:(tr.querySelector('.mk-col-contact_person')?.value||'').trim(),
        designation:(tr.querySelector('.mk-col-designation')?.value||'').trim(),
        mobile_no:(tr.querySelector('.mk-col-mobile_no')?.value||'').trim(),
        mail_id:(tr.querySelector('.mk-col-mail_id')?.value||'').trim(),
        status_1:(tr.querySelector('.mk-col-status_1')?.value||'').trim(),
        status_2:(tr.querySelector('.mk-col-status_2')?.value||'').trim()
      };
      const hasAny = one.entry_date||one.college_name||one.address||one.city||one.department||one.contact_person||one.designation||one.mobile_no||one.mail_id||one.status_1||one.status_2;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_colleges'); if(t) t.value=JSON.stringify(rows);
  }
  function serializeProspect(){
    const rows=[];
    document.querySelectorAll('#mkProspectBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        staff_name:(tr.querySelector('.mk-pro-staff_name')?.value||'').trim(),
        college:(tr.querySelector('.mk-pro-college')?.value||'').trim(),
        department:(tr.querySelector('.mk-pro-department')?.value||'').trim(),
        designation:(tr.querySelector('.mk-pro-designation')?.value||'').trim(),
        mobile_number:(tr.querySelector('.mk-pro-mobile_number')?.value||'').trim(),
        email:(tr.querySelector('.mk-pro-email')?.value||'').trim(),
        followups:[]
      };
      const fi = tr.querySelector('.mk-pro-followup-item');
      if(fi){
        const f={status_date:'',status_text:(fi.querySelector('.mk-pro-f-text')?.value||'').trim(),remarks:(fi.querySelector('.mk-pro-f-remarks')?.value||'').trim()};
        if(f.status_text || f.remarks) one.followups=[f];
      }
      const hasAny = one.staff_name||one.college||one.department||one.designation||one.mobile_number||one.email||one.followups.length>0;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_prospect'); if(t) t.value=JSON.stringify(rows);
  }
  function serializeActReport(){
    const rows=[];
    const activeDay = <?= (int)$mkDayIndex ?>;
    document.querySelectorAll('.mk-act-card-value').forEach(function(inp){
      const metric=(inp.getAttribute('data-metric')||'').trim();
      const todayVal=(inp.value||'').trim();
      if(metric==='') return;
      const one={metric_name:metric};
      let hasAny=todayVal!=='';
      for(let d=1; d<=31; d++) one['day_'+d]='';
      one['day_'+activeDay]=todayVal;
      one.total_value=todayVal;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_act_report'); if(t) t.value=JSON.stringify(rows);
  }
  function calcActReportSummaries(){
    const num=function(v){
      const n=parseFloat(String(v||'').replace(/,/g,'').trim());
      return Number.isFinite(n)?n:0;
    };
    let c1=0,c2=0,totalCalls=0,registration=0,freshCollection=0,oldCollection=0;
    document.querySelectorAll('.dr-step[data-step="5"] .dr-block[data-act-col="1"] .mk-act-card-value').forEach(function(inp){
      const m=(inp.getAttribute('data-metric')||'').toLowerCase().trim();
      const v=num(inp.value);
      c1 += v;
      if(m==='total calls') totalCalls=v;
    });
    document.querySelectorAll('.dr-step[data-step="5"] .dr-block[data-act-col="2"] .mk-act-card-value').forEach(function(inp){ c2 += num(inp.value); });
    document.querySelectorAll('.dr-step[data-step="5"] .dr-block[data-act-col="3"] .mk-act-card-value').forEach(function(inp){
      const m=(inp.getAttribute('data-metric')||'').toLowerCase().trim();
      if(m==='registration') registration=num(inp.value);
      if(m==='fresh collection') freshCollection=num(inp.value);
      if(m==='old collection') oldCollection=num(inp.value);
    });
    const totalCollection = freshCollection + oldCollection;
    const totalCollectionInput = document.querySelector('.dr-step[data-step="5"] .mk-act-card-value[data-metric="Total Collection"]');
    if(totalCollectionInput) totalCollectionInput.value = Number(totalCollection || 0).toFixed(2);
    const ratio = totalCalls>0 ? (registration/totalCalls)*100 : 0;
    const setVal=function(id,v,suffix){
      const el=document.getElementById(id);
      if(!el) return;
      el.value = Number(v||0).toFixed(2) + (suffix||'');
    };
    setVal('mk_act_col1_total', c1, '');
    setVal('mk_act_col2_total', c2, '');
    setVal('mk_act_col3_ratio', ratio, '%');
  }
  document.querySelectorAll('.mk-act-card-value').forEach(function(inp){
    inp.addEventListener('input', calcActReportSummaries);
    inp.addEventListener('change', calcActReportSummaries);
  });
  calcActReportSummaries();
  function serializeAmount(){
    const rows=[];
    document.querySelectorAll('#mkAmountBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        entry_date:(tr.querySelector('.mk-amt-entry_date')?.value||'').trim(),
        college_name:(tr.querySelector('.mk-amt-college_name')?.value||'').trim(),
        dept_or_name:(tr.querySelector('.mk-amt-dept_or_name')?.value||'').trim(),
        particulars:(tr.querySelector('.mk-amt-particulars')?.value||'').trim(),
        bank:(tr.querySelector('.mk-amt-bank')?.value||'0').trim(),
        cash:(tr.querySelector('.mk-amt-cash')?.value||'0').trim(),
        amount:(tr.querySelector('.mk-amt-amount')?.value||'0').trim()
      };
      const hasAny = one.entry_date||one.college_name||one.dept_or_name||one.particulars||one.bank||one.cash!=='0'||one.amount!=='0';
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_amount'); if(t) t.value=JSON.stringify(rows);
  }
  function serializeProgram(){
    const rows=[];
    document.querySelectorAll('#mkProgramBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        college:(tr.querySelector('.mk-prg-college')?.value||'').trim(),
        department:(tr.querySelector('.mk-prg-department')?.value||'').trim(),
        class_name:(tr.querySelector('.mk-prg-class_name')?.value||'').trim(),
        program_given_by:(tr.querySelector('.mk-prg-program_given_by')?.value||'').trim(),
        designation:(tr.querySelector('.mk-prg-designation')?.value||'').trim(),
        program_type:(tr.querySelector('.mk-prg-program_type')?.value||'').trim(),
        domain:(tr.querySelector('.mk-prg-domain')?.value||'').trim(),
        trainer:(tr.querySelector('.mk-prg-trainer')?.value||'').trim(),
        topics:(tr.querySelector('.mk-prg-topics')?.value||'').trim(),
        no_days:(tr.querySelector('.mk-prg-no_days')?.value||'').trim(),
        day_start:(tr.querySelector('.mk-prg-day_start')?.value||'').trim(),
        end_day:(tr.querySelector('.mk-prg-end_day')?.value||'').trim(),
        hours:(tr.querySelector('.mk-prg-hours')?.value||'').trim(),
        no_of_students:(tr.querySelector('.mk-prg-no_of_students')?.value||'0').trim(),
        amount:(tr.querySelector('.mk-prg-amount')?.value||'0').trim(),
        collection:(tr.querySelector('.mk-prg-collection')?.value||'0').trim()
      };
      const hasAny = one.college||one.department||one.class_name||one.program_given_by||one.designation||one.program_type||one.domain||one.trainer||one.topics||one.no_days||one.day_start||one.end_day||one.hours||one.no_of_students!=='0'||one.amount!=='0'||one.collection!=='0';
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_program'); if(t) t.value=JSON.stringify(rows);
  }
  function serializeArtsCollege(){
    const rows=[];
    document.querySelectorAll('#mkArtsCollegeBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        college_name:(tr.querySelector('.mk-ac-college_name')?.value||'').trim(),
        address:(tr.querySelector('.mk-ac-address')?.value||'').trim(),
        city:(tr.querySelector('.mk-ac-city')?.value||'').trim(),
        department:(tr.querySelector('.mk-ac-department')?.value||'').trim(),
        contact_person:(tr.querySelector('.mk-ac-contact_person')?.value||'').trim(),
        designation:(tr.querySelector('.mk-ac-designation')?.value||'').trim(),
        phone_number:(tr.querySelector('.mk-ac-phone_number')?.value||'').trim(),
        email_id:(tr.querySelector('.mk-ac-email_id')?.value||'').trim()
      };
      const hasAny = one.college_name||one.address||one.city||one.department||one.contact_person||one.designation||one.phone_number||one.email_id;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_arts_college'); if(t) t.value=JSON.stringify(rows);
  }
  function serializeArtsPc(){
    const rows=[];
    document.querySelectorAll('#mkArtsPcBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        place_name:(tr.querySelector('.mk-apc-place_name')?.value||'').trim(),
        college_name:(tr.querySelector('.mk-apc-college_name')?.value||'').trim(),
        department:(tr.querySelector('.mk-apc-department')?.value||'').trim(),
        name:(tr.querySelector('.mk-apc-name')?.value||'').trim(),
        designation:(tr.querySelector('.mk-apc-designation')?.value||'').trim(),
        contact_number:(tr.querySelector('.mk-apc-contact_number')?.value||'').trim()
      };
      const hasAny = one.place_name||one.college_name||one.department||one.name||one.designation||one.contact_number;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_arts_pc'); if(t) t.value=JSON.stringify(rows);
  }
  function serializeEnggCollege(){
    const rows=[];
    document.querySelectorAll('#mkEnggCollegeBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        college_name:(tr.querySelector('.mk-ec-college_name')?.value||'').trim(),
        address:(tr.querySelector('.mk-ec-address')?.value||'').trim(),
        city:(tr.querySelector('.mk-ec-city')?.value||'').trim(),
        department:(tr.querySelector('.mk-ec-department')?.value||'').trim(),
        contact_person:(tr.querySelector('.mk-ec-contact_person')?.value||'').trim(),
        designation:(tr.querySelector('.mk-ec-designation')?.value||'').trim(),
        phone_number:(tr.querySelector('.mk-ec-phone_number')?.value||'').trim(),
        email_id:(tr.querySelector('.mk-ec-email_id')?.value||'').trim(),
        dob:(tr.querySelector('.mk-ec-dob')?.value||'').trim(),
        doa:(tr.querySelector('.mk-ec-doa')?.value||'').trim()
      };
      const hasAny = one.college_name||one.address||one.city||one.department||one.contact_person||one.designation||one.phone_number||one.email_id||one.dob||one.doa;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_engg_college'); if(t) t.value=JSON.stringify(rows);
  }
  function serializeEnggPc(){
    const rows=[];
    document.querySelectorAll('#mkEnggPcBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        place_name:(tr.querySelector('.mk-epc-place_name')?.value||'').trim(),
        college_name:(tr.querySelector('.mk-epc-college_name')?.value||'').trim(),
        department:(tr.querySelector('.mk-epc-department')?.value||'').trim(),
        name:(tr.querySelector('.mk-epc-name')?.value||'').trim(),
        contact_number:(tr.querySelector('.mk-epc-contact_number')?.value||'').trim(),
        email_id:(tr.querySelector('.mk-epc-email_id')?.value||'').trim()
      };
      const hasAny = one.place_name||one.college_name||one.department||one.name||one.contact_number||one.email_id;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_engg_pc'); if(t) t.value=JSON.stringify(rows);
  }
  function serializePolytechCollege(){
    const rows=[];
    document.querySelectorAll('#mkPolytechCollegeBody tr').forEach(function(tr,idx){
      const one={
        serial_no:String(idx+1),
        college_name:(tr.querySelector('.mk-pc-college_name')?.value||'').trim(),
        address:(tr.querySelector('.mk-pc-address')?.value||'').trim(),
        city:(tr.querySelector('.mk-pc-city')?.value||'').trim(),
        department:(tr.querySelector('.mk-pc-department')?.value||'').trim(),
        contact_person:(tr.querySelector('.mk-pc-contact_person')?.value||'').trim(),
        designation:(tr.querySelector('.mk-pc-designation')?.value||'').trim(),
        phone_number:(tr.querySelector('.mk-pc-phone_number')?.value||'').trim(),
        email_id:(tr.querySelector('.mk-pc-email_id')?.value||'').trim(),
        dob:(tr.querySelector('.mk-pc-dob')?.value||'').trim(),
        doa:(tr.querySelector('.mk-pc-doa')?.value||'').trim()
      };
      const hasAny = one.college_name||one.address||one.city||one.department||one.contact_person||one.designation||one.phone_number||one.email_id||one.dob||one.doa;
      if(hasAny) rows.push(one);
    });
    const t=document.getElementById('mk_polytech_college'); if(t) t.value=JSON.stringify(rows);
  }
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
      if(target.tagName === 'TEXTAREA' && text.indexOf('\t') === -1) return;
      const tr = target.closest('tr');
      if(!tr) return;
      const table = tr.closest('table');
      if(!table) return;
      let tableRows = Array.from(table.querySelectorAll('tbody tr'));
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
      const tbody = tr.closest('tbody');
      if(tbody && tbody.id === 'mkHourlyBody'){
        const addBtn = document.getElementById('mkAddHourRow');
        const requiredRows = startRow + matrix.length;
        while(addBtn && tableRows.length < requiredRows){
          addBtn.click();
          tableRows = Array.from(table.querySelectorAll('tbody tr'));
          if(tableRows.length >= requiredRows) break;
        }
      }
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
  function attachStatusTableSearch(tbodyId, placeholderText){
    const tbody = document.getElementById(tbodyId);
    if(!tbody) return;
    const table = tbody.closest('table');
    if(!table) return;
    const wrap = table.parentElement;
    if(!wrap) return;
    if(wrap.querySelector('[data-status-search-for="'+tbodyId+'"]')) return;
    const box = document.createElement('div');
    box.setAttribute('data-status-search-for', tbodyId);
    box.style.cssText = 'display:flex;align-items:center;gap:8px;margin:0 0 8px 0;';
    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = placeholderText;
    input.style.cssText = 'max-width:360px;';
    box.appendChild(input);
    wrap.parentNode.insertBefore(box, wrap);
    input.addEventListener('input', function(){
      const q = (input.value || '').toLowerCase().trim();
      Array.from(tbody.querySelectorAll('tr')).forEach(function(tr){
        const txt = (tr.innerText || tr.textContent || '').toLowerCase();
        tr.style.display = (q === '' || txt.indexOf(q) !== -1) ? '' : 'none';
      });
    });
  }
  const form=document.getElementById('mkDailyForm');
  enableEnterNavigation(form);
  enableTablePaste(form);
  form?.addEventListener('submit',function(e){ e.preventDefault(); const hourlyValid=serializeHourly(); serializeColleges(); serializeProspect(); serializeActReport(); serializeAmount(); serializeProgram(); serializeArtsCollege(); serializeArtsPc(); serializeEnggCollege(); serializeEnggPc(); serializePolytechCollege(); if(hourlyValid===0){ show(2); if(typeof Swal!=='undefined') Swal.fire({icon:'error',title:'Hourly Report Required',text:'Please fill at least one hourly row (From, To, Particulars).'}); else alert('Hourly Report required.'); return; } if(typeof Swal!=='undefined'){ Swal.fire({icon:'question',title:'Save Marketing Daily Report?',text:'This will save all sections to database.',showCancelButton:true,confirmButtonColor:'#e91e63',cancelButtonColor:'#6b7280',confirmButtonText:'Yes, Save All'}).then(r=>{ if(!r.isConfirmed) return; Swal.fire({title:'Saving report...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()}); form.submit(); }); } else form.submit(); });
  const s=<?= json_encode($drSuccessMessage) ?>, er=<?= json_encode($drErrorMessage) ?>;
  if(typeof Swal!=='undefined' && s) Swal.fire({icon:'success',title:'Success',text:s,confirmButtonColor:'#e91e63'});
  else if(typeof Swal!=='undefined' && er) Swal.fire({icon:'error',title:'Error',text:er,confirmButtonColor:'#e91e63'});
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
})();
</script>

