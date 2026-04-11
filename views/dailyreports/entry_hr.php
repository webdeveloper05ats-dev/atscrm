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
$canUseHrForm = ($roleName==='hr' || $roleName==='super admin');
$isSaveRequest = ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_all_report']));

$requiredTables=['dailyreport_master','dailyreport_hr_activity','dailyreport_hr_hourly_rows','dailyreport_hr_internship_rows','dailyreport_hr_interview_rows','dailyreport_hr_placement_call_rows','dailyreport_hr_old_client_rows','dailyreport_hr_new_client_rows','dailyreport_hr_college_data_rows','dailyreport_hr_college_followup_rows'];
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
  'internship'=>[['serial_no'=>'','staff_name'=>'','college_name'=>'','department'=>'','student_count'=>'0','platform'=>'','topic'=>'','mode_type'=>'','duration_text'=>'','start_date'=>'','finish_date'=>'','mini_project'=>'','topic_1'=>'']],
  'interview'=>[['candidate_name'=>'','company_name'=>'','interview_date'=>'','interview_status'=>'','remark'=>'']],
  'placement'=>[['entry_date'=>$reportDate,'company_name'=>'','poc_name'=>'','contact_no'=>'','status_text'=>'','remarks'=>'']],
  'old_client'=>[['serial_no'=>'','client_company'=>'','poc'=>'','contact_no'=>'','email_id'=>'','followup_date'=>$reportDate,'followup_report'=>'']],
  'new_client'=>[['company_name'=>'','address'=>'','city'=>'','hr_name'=>'','contact_number'=>'','status_text'=>'']],
  'college_data'=>[['serial_no'=>'','contact_name'=>'','contact_no'=>'','college_name'=>'','topic'=>'','days_text'=>'','resource_person'=>'','requirement'=>'','status_text'=>'']],
  'college_followup'=>[['name'=>'','position'=>'','mail_id'=>'','contact_number'=>'','report_text'=>'','college'=>'']]
];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'hr_lookup') {
  header('Content-Type: application/json; charset=utf-8');
  if (!$canUseHrForm) { echo json_encode(['ok'=>false,'rows'=>[]]); exit; }
  $section = trim((string)($_GET['section'] ?? ''));
  $q = trim((string)($_GET['q'] ?? ''));
  if ($q === '') { echo json_encode(['ok'=>true,'rows'=>[]]); exit; }
  $like = '%'.$q.'%';
  $cfg = [
    'interview' => [
      'table' => 'dailyreport_hr_interview_rows',
      'fields' => 'r.candidate_name,r.company_name,r.interview_date,r.interview_status,r.remark',
      'where' => '(r.candidate_name LIKE ? OR r.company_name LIKE ? OR r.interview_status LIKE ?)'
    ],
    'placement' => [
      'table' => 'dailyreport_hr_placement_call_rows',
      'fields' => 'r.entry_date,r.company_name,r.poc_name,r.contact_no,r.status_text,r.remarks',
      'where' => '(r.company_name LIKE ? OR r.poc_name LIKE ? OR r.contact_no LIKE ? OR r.status_text LIKE ?)'
    ],
    'old_client' => [
      'table' => 'dailyreport_hr_old_client_rows',
      'fields' => 'r.serial_no,r.client_company,r.poc,r.contact_no,r.email_id,r.followup_date,r.followup_report',
      'where' => '(r.client_company LIKE ? OR r.poc LIKE ? OR r.contact_no LIKE ? OR r.followup_report LIKE ?)'
    ],
    'new_client' => [
      'table' => 'dailyreport_hr_new_client_rows',
      'fields' => 'r.company_name,r.address,r.city,r.hr_name,r.contact_number,r.status_text',
      'where' => '(r.company_name LIKE ? OR r.hr_name LIKE ? OR r.contact_number LIKE ? OR r.status_text LIKE ?)'
    ],
    'college_data' => [
      'table' => 'dailyreport_hr_college_data_rows',
      'fields' => 'r.serial_no,r.contact_name,r.contact_no,r.college_name,r.topic,r.days_text,r.resource_person,r.requirement,r.status_text',
      'where' => '(r.contact_name LIKE ? OR r.college_name LIKE ? OR r.contact_no LIKE ? OR r.status_text LIKE ?)'
    ]
  ];
  if (!isset($cfg[$section])) { echo json_encode(['ok'=>false,'rows'=>[]]); exit; }
  $sql = "SELECT dm.report_date, {$cfg[$section]['fields']} FROM {$cfg[$section]['table']} r
          INNER JOIN dailyreport_master dm ON dm.id=r.master_id
          WHERE dm.report_type='hr' AND dm.user_id=? AND dm.branch_id=? AND {$cfg[$section]['where']}
          ORDER BY dm.report_date DESC, r.id DESC LIMIT 30";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId,$branchId,$like,$like,$like,$like]);
  echo json_encode(['ok'=>true,'rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []], JSON_UNESCAPED_UNICODE);
  exit;
}

$master=null; $isEditable=false; $warning='';
$drSuccessMessage = (isset($_GET['saved']) && (string)$_GET['saved'] === '1') ? 'HR daily report saved successfully.' : '';
$drErrorMessage = trim((string)($_GET['save_error'] ?? ''));
if (function_exists('getFlash')) {
  $flashSuccess = getFlash('success');
  if ($drSuccessMessage === '' && $flashSuccess !== null) $drSuccessMessage = (string)$flashSuccess;
  $flashError = getFlash('error');
  if ($drErrorMessage === '' && $flashError !== null) $drErrorMessage = (string)$flashError;
}
if(!empty($missingTables)) $warning='Missing table(s): '.implode(', ',$missingTables).'.';
elseif(!$canUseHrForm) $warning='Only HR (or Super Admin) can use this form.';

if(empty($missingTables) && $canUseHrForm){
  $st=$pdo->prepare("SELECT * FROM dailyreport_master WHERE report_date=? AND user_id=? AND report_type='hr' LIMIT 1");
  $st->execute([$reportDate,$userId]);
  $master=$st->fetch(PDO::FETCH_ASSOC)?:null;
  if(!$master && $isAllowedEntryDate){
    $ins=$pdo->prepare("INSERT INTO dailyreport_master(report_date,role_id,user_id,branch_id,report_type,status,created_at,updated_at) VALUES(?,?,?,?,'hr','draft',NOW(),NOW())");
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
  $q=$pdo->prepare("SELECT * FROM dailyreport_hr_activity WHERE master_id=? LIMIT 1");
  $q->execute([$mid]);
  $r=$q->fetch(PDO::FETCH_ASSOC);
  if($r){ foreach($activity as $k=>$v){ if(array_key_exists($k,$r)) $activity[$k]=$r[$k]; } }

  $map=[
    'hourly'=>['dailyreport_hr_hourly_rows','sort_order,id ASC'],
    'internship'=>['dailyreport_hr_internship_rows','id ASC'],
    'interview'=>['dailyreport_hr_interview_rows','sort_order,id ASC'],
    'placement'=>['dailyreport_hr_placement_call_rows','sort_order,id ASC'],
    'old_client'=>['dailyreport_hr_old_client_rows','id ASC'],
    'new_client'=>['dailyreport_hr_new_client_rows','sort_order,id ASC'],
    'college_data'=>['dailyreport_hr_college_data_rows','id ASC'],
    'college_followup'=>['dailyreport_hr_college_followup_rows','sort_order,id ASC']
  ];
  foreach($map as $k=>$v){
    $q=$pdo->prepare("SELECT * FROM {$v[0]} WHERE master_id=? ORDER BY {$v[1]}");
    $q->execute([$mid]);
    $tmp=$q->fetchAll(PDO::FETCH_ASSOC);
    if($tmp) $sections[$k]=$tmp;
  }
}

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_all_report']) && empty($missingTables) && $canUseHrForm){
  if(!verifyCSRF($_POST['csrf_token']??'')){ redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate).'&save_error='.urlencode('Invalid request (CSRF).')); }
  if(!$master || !$isEditable){ redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate).'&save_error='.urlencode('This report is read only.')); }

  $mid=(int)$master['id'];
  $p=[];
  foreach(['fresh_calls','follow_calls','messages_sent','mails_sent','forum_posting','promotions','reference_count','db_calls','walkins'] as $k){ $p[$k]=drInt($_POST[$k]??0); }
  foreach(['billing','fresh_collection','old_collection'] as $k){ $p[$k]=drDec($_POST[$k]??0); }
  $p['remarks']='';
  $p['total_calls']=$p['fresh_calls']+$p['follow_calls']+$p['messages_sent']+$p['mails_sent'];
  $p['registration_total']=$p['promotions']+$p['reference_count']+$p['db_calls'];
  $p['total_collection']=drDec(((float)$p['fresh_collection'])+((float)$p['old_collection']));
  $p['conversion_ratio']=$p['total_calls']>0?drDec(($p['registration_total']/$p['total_calls'])*100):'0.00';

  try{
    $pdo->beginTransaction();
    $up=$pdo->prepare("INSERT INTO dailyreport_hr_activity(master_id,fresh_calls,follow_calls,messages_sent,mails_sent,total_calls,forum_posting,promotions,reference_count,db_calls,registration_total,billing,fresh_collection,old_collection,total_collection,walkins,conversion_ratio,remarks,created_at,updated_at) VALUES(:master_id,:fresh_calls,:follow_calls,:messages_sent,:mails_sent,:total_calls,:forum_posting,:promotions,:reference_count,:db_calls,:registration_total,:billing,:fresh_collection,:old_collection,:total_collection,:walkins,:conversion_ratio,:remarks,NOW(),NOW()) ON DUPLICATE KEY UPDATE fresh_calls=VALUES(fresh_calls),follow_calls=VALUES(follow_calls),messages_sent=VALUES(messages_sent),mails_sent=VALUES(mails_sent),total_calls=VALUES(total_calls),forum_posting=VALUES(forum_posting),promotions=VALUES(promotions),reference_count=VALUES(reference_count),db_calls=VALUES(db_calls),registration_total=VALUES(registration_total),billing=VALUES(billing),fresh_collection=VALUES(fresh_collection),old_collection=VALUES(old_collection),total_collection=VALUES(total_collection),walkins=VALUES(walkins),conversion_ratio=VALUES(conversion_ratio),remarks=VALUES(remarks),updated_at=NOW()");
    $up->execute(array_merge(['master_id'=>$mid],$p));

    $deleteTables=['dailyreport_hr_hourly_rows','dailyreport_hr_internship_rows','dailyreport_hr_interview_rows','dailyreport_hr_placement_call_rows','dailyreport_hr_old_client_rows','dailyreport_hr_new_client_rows','dailyreport_hr_college_data_rows','dailyreport_hr_college_followup_rows'];
    $deleteStmts=[];
    foreach($deleteTables as $t){ $deleteStmts[$t] = $pdo->prepare("DELETE FROM {$t} WHERE master_id=?"); }
    foreach($deleteStmts as $stmt){ $stmt->execute([$mid]); }

    $hourRows=json_decode((string)($_POST['hour']??'[]'),true); if(!is_array($hourRows)) $hourRows=[];
    $ins=$pdo->prepare("INSERT INTO dailyreport_hr_hourly_rows(master_id,sort_order,time_from,time_to,particulars,activities_undergone,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),NOW())");
    $hv=0;
    foreach($hourRows as $i=>$r){
      $from=drText($r['time_from']??''); $to=drText($r['time_to']??''); $part=drText($r['particulars']??''); $act=drText($r['activities_undergone']??'');
      if($from===''&&$to===''&&$part===''&&$act==='') continue;
      $ins->execute([$mid,$i+1,$from,$to,$part,$act]);
      if($from!=='' && $to!=='' && $part!=='') $hv++;
    }
    if($hv===0) throw new Exception('Hourly Report is mandatory. Please fill at least one hourly row (From, To, Particulars).');

    $jsonTables=[
      'internship'=>['dailyreport_hr_internship_rows',['serial_no','staff_name','college_name','department','student_count','platform','topic','mode_type','duration_text','start_date','finish_date','mini_project','topic_1']],
      'interview'=>['dailyreport_hr_interview_rows',['candidate_name','company_name','interview_date','interview_status','remark']],
      'placement'=>['dailyreport_hr_placement_call_rows',['entry_date','company_name','poc_name','contact_no','status_text','remarks']],
      'old_client'=>['dailyreport_hr_old_client_rows',['serial_no','client_company','poc','contact_no','email_id','followup_date','followup_report']],
      'new_client'=>['dailyreport_hr_new_client_rows',['company_name','address','city','hr_name','contact_number','status_text']],
      'college_data'=>['dailyreport_hr_college_data_rows',['serial_no','contact_name','contact_no','college_name','topic','days_text','resource_person','requirement','status_text']],
      'college_followup'=>['dailyreport_hr_college_followup_rows',['name','position','mail_id','contact_number','report_text','college']]
    ];

    foreach($jsonTables as $key=>$cfg){
      $rows=json_decode((string)($_POST[$key]??'[]'),true); if(!is_array($rows) || empty($rows)) continue;
      $fields=$cfg[1];
      $withSort=in_array($cfg[0],['dailyreport_hr_interview_rows','dailyreport_hr_placement_call_rows','dailyreport_hr_new_client_rows','dailyreport_hr_college_followup_rows'],true);
      $cols='master_id'.($withSort?',sort_order':'').','.implode(',',$fields);
      $place='?'.($withSort?',?':''); foreach($fields as $f){ $place.=',?'; }
      $stmt=$pdo->prepare("INSERT INTO {$cfg[0]}({$cols},created_at,updated_at) VALUES({$place},NOW(),NOW())");

      foreach($rows as $idx=>$r){
        $values=[$mid]; if($withSort) $values[]=$idx+1;
        $has=false;
        foreach($fields as $f){
          $v=$r[$f]??'';
          if(strpos($f,'date')!==false) $v=drDateOrNull($v);
          elseif(in_array($f,['serial_no','student_count'],true)) $v=drInt($v);
          else $v=drText($v);
          if($v!=='' && $v!==null && $v!==0) $has=true;
          $values[]=$v;
        }
        if(!$has) continue;
        $stmt->execute($values);
      }
    }

    $pdo->prepare("UPDATE dailyreport_master SET status='submitted', submitted_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$mid]);
    $pdo->commit();
    redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate).'&saved=1');
  } catch(Exception $e){
    if($pdo->inTransaction()) $pdo->rollBack();
    redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate).'&save_error='.urlencode($e->getMessage()));
  }
  redirect('index.php?page=dailyreports/entry&report_date='.urlencode($reportDate));
}

function hrJson($v){ return h(json_encode($v, JSON_UNESCAPED_UNICODE)); }
$totalCallsNow = (int)$activity['fresh_calls'] + (int)$activity['follow_calls'] + (int)$activity['messages_sent'] + (int)$activity['mails_sent'];
$regTotalNow = (int)$activity['promotions'] + (int)$activity['reference_count'] + (int)$activity['db_calls'];
$totalCollectionNow = number_format((float)$activity['fresh_collection'] + (float)$activity['old_collection'],2,'.','');
$convNow = ($totalCallsNow>0) ? number_format(($regTotalNow/$totalCallsNow)*100,2,'.','') : '0.00';
$editModeLabel='Read Only'; if($isToday) $editModeLabel=$isEditable?'Editable (Today)':'Read Only (Locked)'; elseif($isBackdateWithin2) $editModeLabel=$isEditable?'Editable (Backdate One-time Save)':'Read Only (Backdate Saved)';
?>
<style>
.dr-wrap{padding:8px 0}.dr-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}.dr-title{margin:0;color:#be185d;font-size:1.5rem;font-weight:800}.dr-note{margin:0;color:#6b7280}
.dr-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}.dr-tab{border:1px solid #f2d3e2;background:#fff;border-radius:10px;padding:8px 12px;font-weight:700;color:#9d174d;cursor:pointer}.dr-tab.active{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff}
.dr-step{display:none}.dr-step.active{display:block}.dr-card{background:#fff;border:1px solid #f1d6e3;border-radius:14px;box-shadow:0 8px 18px rgba(0,0,0,.06)}.dr-card-body{padding:14px}
.dr-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.dr-field label{display:block;font-size:.82rem;color:#6b7280;font-weight:700;margin-bottom:6px}.dr-field input,.dr-field textarea{width:100%;border:1px solid #ecd3df;border-radius:10px;padding:8px 10px}.dr-field textarea{min-height:90px}
.dr-btn{border:none;border-radius:10px;height:38px;padding:0 14px;font-weight:700;cursor:pointer}.dr-btn-primary{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff}.dr-btn-muted{background:#64748b;color:#fff}.dr-btn-success{background:#15803d;color:#fff}.dr-step-nav{display:flex;justify-content:space-between;margin-top:10px}
.dr-activity-board{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.dr-block{border:1px solid #f1d6e3;border-radius:12px;overflow:hidden;background:#fff}.dr-block-head{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff;font-weight:800;text-align:center;padding:8px 10px;font-size:.95rem}.dr-block-body{padding:12px;display:grid;gap:10px}
.dr-table-wrap{overflow:auto}.dr-table{width:100%;border-collapse:collapse}.dr-table th,.dr-table td{border:1px solid #f1d6e3;padding:8px;vertical-align:top}.dr-table th{background:#fff4fa;color:#9d174d;font-size:.82rem}.dr-mini-btn{border:none;background:#e2e8f0;color:#334155;border-radius:8px;padding:6px 8px;font-size:.78rem;font-weight:700;cursor:pointer}.dr-mini-btn.add{background:linear-gradient(135deg,#ff4d8d,#e91e63);color:#fff;height:34px;min-width:140px;display:inline-flex;align-items:center;justify-content:center;padding:0 12px}.dr-mini-btn.del{background:#fee2e2;color:#991b1b}
@media(max-width:1100px){.dr-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dr-activity-board{grid-template-columns:1fr}}
</style>
<div class="dr-wrap">
  <div class="dr-head"><div><h2 class="dr-title">Daily Report Entry</h2><p class="dr-note">HR - same flow (final save only)</p></div></div>
  <?php if($warning!==''): ?><div class="dr-card"><div class="dr-card-body" style="color:#9a3412"><?= h($warning) ?></div></div><?php endif; ?>
  <div class="dr-card"><div class="dr-card-body">
    <div class="dr-grid"><div class="dr-field"><label>Report Date</label><input type="date" class="js-dr-report-date" value="<?= h($reportDate) ?>" min="<?= h($minSelectableDate) ?>" max="<?= h($today) ?>"></div><div class="dr-field"><label>Edit Mode</label><input readonly value="<?= h($editModeLabel) ?>"></div><div class="dr-field"><label>Role</label><input readonly value="HR"></div><div class="dr-field"><label>Status</label><input readonly value="<?= h(ucfirst((string)($master['status'] ?? 'draft'))) ?>"></div></div>
    <?php if($master && empty($missingTables) && $canUseHrForm): ?>
    <form method="POST" id="hrDailyForm">
      <input type="hidden" name="csrf_token" value="<?= h(generateCSRF()) ?>"><input type="hidden" name="save_all_report" value="1">
      <input type="hidden" name="hour" id="hrHour"><input type="hidden" name="internship" id="hrIntern"><input type="hidden" name="interview" id="hrIntv"><input type="hidden" name="placement" id="hrPlacement"><input type="hidden" name="old_client" id="hrOld"><input type="hidden" name="new_client" id="hrNew"><input type="hidden" name="college_data" id="hrCd"><input type="hidden" name="college_followup" id="hrCf">
      <div class="dr-tabs" id="drTabs"><button type="button" class="dr-tab active" data-step="1">Activity</button><button type="button" class="dr-tab" data-step="2">Hourly</button><button type="button" class="dr-tab" data-step="3">Internship</button><button type="button" class="dr-tab" data-step="4">Interview</button><button type="button" class="dr-tab" data-step="5">Placement</button><button type="button" class="dr-tab" data-step="6">Old Clients</button><button type="button" class="dr-tab" data-step="7">New Clients</button><button type="button" class="dr-tab" data-step="8">College Data</button><button type="button" class="dr-tab" data-step="9">College Follow</button></div>

      <div class="dr-step active" data-step="1">
        <div class="dr-activity-board">
          <div class="dr-block"><div class="dr-block-head">Datas</div><div class="dr-block-body">
            <div class="dr-field"><label>No Of Fresh Calls</label><input type="number" id="hr_fresh_calls" name="fresh_calls" value="<?= h($activity['fresh_calls']) ?>"></div>
            <div class="dr-field"><label>No Of Followup Calls</label><input type="number" id="hr_follow_calls" name="follow_calls" value="<?= h($activity['follow_calls']) ?>"></div>
            <div class="dr-field"><label>Msg Sent</label><input type="number" id="hr_messages_sent" name="messages_sent" value="<?= h($activity['messages_sent']) ?>"></div>
            <div class="dr-field"><label>Mail Sent</label><input type="number" id="hr_mails_sent" name="mails_sent" value="<?= h($activity['mails_sent']) ?>"></div>
            <div class="dr-field"><label>Total Calls</label><input type="number" id="hr_total_calls" readonly value="<?= h($totalCallsNow) ?>"></div>
            <div class="dr-field"><label>Forum Posting</label><input type="number" id="hr_forum_posting" name="forum_posting" value="<?= h($activity['forum_posting']) ?>"></div>
          </div></div>
          <div class="dr-block"><div class="dr-block-head">Registration</div><div class="dr-block-body">
            <div class="dr-field"><label>Promotions</label><input type="number" id="hr_promotions" name="promotions" value="<?= h($activity['promotions']) ?>"></div>
            <div class="dr-field"><label>Reference</label><input type="number" id="hr_reference_count" name="reference_count" value="<?= h($activity['reference_count']) ?>"></div>
            <div class="dr-field"><label>DB Calls</label><input type="number" id="hr_db_calls" name="db_calls" value="<?= h($activity['db_calls']) ?>"></div>
            <div class="dr-field"><label>Total</label><input type="number" id="hr_registration_total" readonly value="<?= h($regTotalNow) ?>"></div>
          </div></div>
          <div class="dr-block"><div class="dr-block-head">Contents</div><div class="dr-block-body">
            <div class="dr-field"><label>Billing</label><input type="number" step="0.01" id="hr_billing" name="billing" value="<?= h($activity['billing']) ?>"></div>
            <div class="dr-field"><label>Fresh Collection</label><input type="number" step="0.01" id="hr_fresh_collection" name="fresh_collection" value="<?= h($activity['fresh_collection']) ?>"></div>
            <div class="dr-field"><label>Old Collection</label><input type="number" step="0.01" id="hr_old_collection" name="old_collection" value="<?= h($activity['old_collection']) ?>"></div>
            <div class="dr-field"><label>Total Collection</label><input type="number" step="0.01" id="hr_total_collection" readonly value="<?= h($totalCollectionNow) ?>"></div>
            <div class="dr-field"><label>Registration</label><input type="number" id="hr_registration_total_dup" readonly value="<?= h($regTotalNow) ?>"></div>
            <div class="dr-field"><label>Walkins</label><input type="number" id="hr_walkins" name="walkins" value="<?= h($activity['walkins']) ?>"></div>
            <div class="dr-field"><label>Conversion Ratio</label><input type="text" id="hr_conversion_ratio" readonly value="<?= h($convNow) ?>%"></div>
          </div></div>
        </div>
        <div class="dr-step-nav"><span></span><button type="button" class="dr-btn dr-btn-primary" data-next="2">Next</button></div>
      </div>

      <div class="dr-step" data-step="2">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead><tr><th>From</th><th>To</th><th>Particulars</th><th>Activities Undergone</th><th>Action</th></tr></thead>
            <tbody id="hrHourBody">
              <?php foreach(($sections['hourly'] ?? []) as $r): ?>
                <tr>
                  <td><input type="time" class="hr-hour-time-from" value="<?= h((string)($r['time_from'] ?? '09:30')) ?>"></td>
                  <td><input type="time" class="hr-hour-time-to" value="<?= h((string)($r['time_to'] ?? '10:30')) ?>"></td>
                  <td><input class="hr-hour-particulars" value="<?= h((string)($r['particulars'] ?? '')) ?>"></td>
                  <td><textarea class="hr-hour-activities"><?= h((string)($r['activities_undergone'] ?? '')) ?></textarea></td>
                  <td><button type="button" class="dr-mini-btn del js-del-hour-row">Delete</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrHourRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="1">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="3">Next</button></div>
      </div>

      <div class="dr-step" data-step="3">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead>
              <tr>
                <th>S.No</th><th>Name</th><th>College</th><th>Department</th><th>Students</th><th>Platform</th><th>Topic</th><th>Mode</th><th>Duration</th><th>Start</th><th>Finish</th><th>Mini Project</th><th>Action</th>
              </tr>
            </thead>
            <tbody id="hrInternBody">
              <?php foreach(($sections['internship'] ?? []) as $idx => $r): ?>
                <tr>
                  <td><input class="hr-intern-serial_no" readonly value="<?= h((string)($idx + 1)) ?>"></td>
                  <td><input class="hr-intern-staff_name" value="<?= h((string)($r['staff_name'] ?? '')) ?>"></td>
                  <td><input class="hr-intern-college_name" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                  <td><input class="hr-intern-department" value="<?= h((string)($r['department'] ?? '')) ?>"></td>
                  <td><input type="number" class="hr-intern-student_count" value="<?= h((string)($r['student_count'] ?? '0')) ?>"></td>
                  <td><input class="hr-intern-platform" value="<?= h((string)($r['platform'] ?? '')) ?>"></td>
                  <td><input class="hr-intern-topic" value="<?= h((string)($r['topic'] ?? '')) ?>"></td>
                  <td><input class="hr-intern-mode_type" value="<?= h((string)($r['mode_type'] ?? '')) ?>"></td>
                  <td><input class="hr-intern-duration_text" value="<?= h((string)($r['duration_text'] ?? '')) ?>"></td>
                  <td><input type="date" class="hr-intern-start_date" value="<?= h((string)($r['start_date'] ?? '')) ?>"></td>
                  <td><input type="date" class="hr-intern-finish_date" value="<?= h((string)($r['finish_date'] ?? '')) ?>"></td>
                  <td><input class="hr-intern-mini_project" value="<?= h((string)($r['mini_project'] ?? '')) ?>"></td>
                  <td><button type="button" class="dr-mini-btn del js-del-intern-row">Delete</button></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrInternRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="2">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="4">Next</button></div>
      </div>

      <div class="dr-step" data-step="4">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead><tr><th>Candidate Name</th><th>Company Name</th><th>Interview Date</th><th>Interview Status</th><th>Remark</th><th>Action</th></tr></thead>
            <tbody id="hrInterviewBody">
              <?php foreach(($sections['interview'] ?? []) as $r): ?>
              <tr>
                <td><input class="hr-intv-candidate_name" value="<?= h((string)($r['candidate_name'] ?? '')) ?>"></td>
                <td><input class="hr-intv-company_name" value="<?= h((string)($r['company_name'] ?? '')) ?>"></td>
                <td><input type="date" class="hr-intv-interview_date" value="<?= h((string)($r['interview_date'] ?? '')) ?>"></td>
                <td><input class="hr-intv-interview_status" value="<?= h((string)($r['interview_status'] ?? '')) ?>"></td>
                <td><textarea class="hr-intv-remark"><?= h((string)($r['remark'] ?? '')) ?></textarea></td>
                <td><button type="button" class="dr-mini-btn del js-del-intv-row">Delete</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrInterviewRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="3">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="5">Next</button></div>
      </div>

      <div class="dr-step" data-step="5">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead><tr><th>Date</th><th>Company Name</th><th>POC Name</th><th>Contact No</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
            <tbody id="hrPlacementBody">
              <?php foreach(($sections['placement'] ?? []) as $r): ?>
              <tr>
                <td><input type="date" class="hr-place-entry_date" value="<?= h((string)($r['entry_date'] ?? $reportDate)) ?>"></td>
                <td><input class="hr-place-company_name" value="<?= h((string)($r['company_name'] ?? '')) ?>"></td>
                <td><input class="hr-place-poc_name" value="<?= h((string)($r['poc_name'] ?? '')) ?>"></td>
                <td><input class="hr-place-contact_no" value="<?= h((string)($r['contact_no'] ?? '')) ?>"></td>
                <td><input class="hr-place-status_text" value="<?= h((string)($r['status_text'] ?? '')) ?>"></td>
                <td><textarea class="hr-place-remarks"><?= h((string)($r['remarks'] ?? '')) ?></textarea></td>
                <td><button type="button" class="dr-mini-btn del js-del-placement-row">Delete</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrPlacementRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="4">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="6">Next</button></div>
      </div>

      <div class="dr-step" data-step="6">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead><tr><th>S.No</th><th>Client Company</th><th>POC</th><th>Contact No</th><th>Email</th><th>Followup Date</th><th>Followup Report</th><th>Action</th></tr></thead>
            <tbody id="hrOldClientBody">
              <?php foreach(($sections['old_client'] ?? []) as $idx => $r): ?>
              <tr>
                <td><input class="hr-old-serial_no" readonly value="<?= h((string)($idx + 1)) ?>"></td>
                <td><input class="hr-old-client_company" value="<?= h((string)($r['client_company'] ?? '')) ?>"></td>
                <td><input class="hr-old-poc" value="<?= h((string)($r['poc'] ?? '')) ?>"></td>
                <td><input class="hr-old-contact_no" value="<?= h((string)($r['contact_no'] ?? '')) ?>"></td>
                <td><input class="hr-old-email_id" value="<?= h((string)($r['email_id'] ?? '')) ?>"></td>
                <td><input type="date" class="hr-old-followup_date" value="<?= h((string)($r['followup_date'] ?? $reportDate)) ?>"></td>
                <td><textarea class="hr-old-followup_report"><?= h((string)($r['followup_report'] ?? '')) ?></textarea></td>
                <td><button type="button" class="dr-mini-btn del js-del-old-row">Delete</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrOldClientRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="5">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="7">Next</button></div>
      </div>

      <div class="dr-step" data-step="7">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead><tr><th>Company Name</th><th>Address</th><th>City</th><th>HR Name</th><th>Contact Number</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="hrNewClientBody">
              <?php foreach(($sections['new_client'] ?? []) as $r): ?>
              <tr>
                <td><input class="hr-new-company_name" value="<?= h((string)($r['company_name'] ?? '')) ?>"></td>
                <td><textarea class="hr-new-address"><?= h((string)($r['address'] ?? '')) ?></textarea></td>
                <td><input class="hr-new-city" value="<?= h((string)($r['city'] ?? '')) ?>"></td>
                <td><input class="hr-new-hr_name" value="<?= h((string)($r['hr_name'] ?? '')) ?>"></td>
                <td><input class="hr-new-contact_number" value="<?= h((string)($r['contact_number'] ?? '')) ?>"></td>
                <td><input class="hr-new-status_text" value="<?= h((string)($r['status_text'] ?? '')) ?>"></td>
                <td><button type="button" class="dr-mini-btn del js-del-new-row">Delete</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrNewClientRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="6">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="8">Next</button></div>
      </div>

      <div class="dr-step" data-step="8">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead><tr><th>S.No</th><th>Contact Name</th><th>Contact No</th><th>College Name</th><th>Topic</th><th>Days</th><th>Resource Person</th><th>Requirement</th><th>Status</th><th>Action</th></tr></thead>
            <tbody id="hrCollegeDataBody">
              <?php foreach(($sections['college_data'] ?? []) as $idx => $r): ?>
              <tr>
                <td><input class="hr-cd-serial_no" readonly value="<?= h((string)($idx + 1)) ?>"></td>
                <td><input class="hr-cd-contact_name" value="<?= h((string)($r['contact_name'] ?? '')) ?>"></td>
                <td><input class="hr-cd-contact_no" value="<?= h((string)($r['contact_no'] ?? '')) ?>"></td>
                <td><input class="hr-cd-college_name" value="<?= h((string)($r['college_name'] ?? '')) ?>"></td>
                <td><input class="hr-cd-topic" value="<?= h((string)($r['topic'] ?? '')) ?>"></td>
                <td><input class="hr-cd-days_text" value="<?= h((string)($r['days_text'] ?? '')) ?>"></td>
                <td><input class="hr-cd-resource_person" value="<?= h((string)($r['resource_person'] ?? '')) ?>"></td>
                <td><textarea class="hr-cd-requirement"><?= h((string)($r['requirement'] ?? '')) ?></textarea></td>
                <td><input class="hr-cd-status_text" value="<?= h((string)($r['status_text'] ?? '')) ?>"></td>
                <td><button type="button" class="dr-mini-btn del js-del-cd-row">Delete</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrCollegeDataRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="7">Back</button><button type="button" class="dr-btn dr-btn-primary" data-next="9">Next</button></div>
      </div>

      <div class="dr-step" data-step="9">
        <div class="dr-table-wrap">
          <table class="dr-table">
            <thead><tr><th>Name</th><th>Position</th><th>Mail ID</th><th>Contact Number</th><th>Report</th><th>College</th><th>Action</th></tr></thead>
            <tbody id="hrCollegeFollowBody">
              <?php foreach(($sections['college_followup'] ?? []) as $r): ?>
              <tr>
                <td><input class="hr-cf-name" value="<?= h((string)($r['name'] ?? '')) ?>"></td>
                <td><input class="hr-cf-position" value="<?= h((string)($r['position'] ?? '')) ?>"></td>
                <td><input class="hr-cf-mail_id" value="<?= h((string)($r['mail_id'] ?? '')) ?>"></td>
                <td><input class="hr-cf-contact_number" value="<?= h((string)($r['contact_number'] ?? '')) ?>"></td>
                <td><textarea class="hr-cf-report_text"><?= h((string)($r['report_text'] ?? '')) ?></textarea></td>
                <td><input class="hr-cf-college" value="<?= h((string)($r['college'] ?? '')) ?>"></td>
                <td><button type="button" class="dr-mini-btn del js-del-cf-row">Delete</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:8px"><button type="button" class="dr-mini-btn add" id="addHrCollegeFollowRow">+ Add Row</button></div>
        <div class="dr-step-nav"><button type="button" class="dr-btn dr-btn-muted" data-prev="8">Back</button><button type="submit" class="dr-btn dr-btn-success">Save All Sections</button></div>
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
    drAjaxSwap('index.php?page=dailyreports/entry&report_date=' + encodeURIComponent(dt));
  });
  const tabs=[...document.querySelectorAll('.dr-tab')],steps=[...document.querySelectorAll('.dr-step')];
  function show(n){tabs.forEach(t=>t.classList.toggle('active',+t.dataset.step===n));steps.forEach(s=>s.classList.toggle('active',+s.dataset.step===n));window.scrollTo({top:0,behavior:'smooth'});}
  tabs.forEach(t=>t.addEventListener('click',()=>show(+t.dataset.step)));
  document.querySelectorAll('[data-next]').forEach(b=>b.addEventListener('click',()=>show(+b.dataset.next)));
  document.querySelectorAll('[data-prev]').forEach(b=>b.addEventListener('click',()=>show(+b.dataset.prev)));
  const n=id=>{const el=document.getElementById(id); return el?parseFloat(el.value||0):0;};
  const setVal=(id,val,dec)=>{const el=document.getElementById(id); if(!el) return; el.value=dec!=null?Number(val||0).toFixed(dec):String(Math.round(val||0));};
  function hrCalcActivity(){
    const totalCalls = n('hr_fresh_calls') + n('hr_follow_calls') + n('hr_messages_sent') + n('hr_mails_sent');
    const regTotal = n('hr_promotions') + n('hr_reference_count') + n('hr_db_calls');
    const totalCollection = n('hr_fresh_collection') + n('hr_old_collection');
    const conversion = totalCalls > 0 ? (regTotal / totalCalls) * 100 : 0;
    setVal('hr_total_calls', totalCalls);
    setVal('hr_registration_total', regTotal);
    setVal('hr_registration_total_dup', regTotal);
    setVal('hr_total_collection', totalCollection, 2);
    const c = document.getElementById('hr_conversion_ratio');
    if (c) c.value = Number(conversion).toFixed(2) + '%';
  }
  ['hr_fresh_calls','hr_follow_calls','hr_messages_sent','hr_mails_sent','hr_promotions','hr_reference_count','hr_db_calls','hr_fresh_collection','hr_old_collection'].forEach(function(id){
    const el=document.getElementById(id); if(el){ el.addEventListener('input', hrCalcActivity); el.addEventListener('change', hrCalcActivity); }
  });
  hrCalcActivity();
  function addOneHour(hhmm){
    const m = /^(\d{2}):(\d{2})$/.exec((hhmm || '').trim());
    if (!m) return null;
    let h = parseInt(m[1], 10);
    const mm = m[2];
    h = (h + 1) % 24;
    return String(h).padStart(2, '0') + ':' + mm;
  }
  function getNextHourlySlot(){
    const rows = document.querySelectorAll('#hrHourBody tr');
    if (!rows.length) return { from: '09:30', to: '10:30' };
    const last = rows[rows.length - 1];
    const lastFrom = (last.querySelector('.hr-hour-time-from')?.value || '').trim();
    const lastTo = (last.querySelector('.hr-hour-time-to')?.value || '').trim();
    const nextFrom = /^\d{2}:\d{2}$/.test(lastTo) ? lastTo : (addOneHour(lastFrom) || '09:30');
    const nextTo = addOneHour(nextFrom) || '10:30';
    return { from: nextFrom, to: nextTo };
  }
  document.getElementById('addHrHourRow')?.addEventListener('click', function(){
    const body = document.getElementById('hrHourBody');
    if (!body) return;
    const slot = getNextHourlySlot();
    const tr = document.createElement('tr');
    tr.innerHTML = '<td><input type=\"time\" class=\"hr-hour-time-from\" value=\"'+slot.from+'\"></td><td><input type=\"time\" class=\"hr-hour-time-to\" value=\"'+slot.to+'\"></td><td><input class=\"hr-hour-particulars\"></td><td><textarea class=\"hr-hour-activities\"></textarea></td><td><button type=\"button\" class=\"dr-mini-btn del js-del-hour-row\">Delete</button></td>';
    body.appendChild(tr);
  });
  root.addEventListener('click', function(e){
    if (!e.target.classList.contains('js-del-hour-row')) return;
    const tr = e.target.closest('tr');
    if (tr) tr.remove();
  });
  function serializeHourlyRows(){
    const rows = [];
    let validCount = 0;
    document.querySelectorAll('#hrHourBody tr').forEach(function(tr){
      const one = {
        time_from: (tr.querySelector('.hr-hour-time-from')?.value || '').trim(),
        time_to: (tr.querySelector('.hr-hour-time-to')?.value || '').trim(),
        particulars: (tr.querySelector('.hr-hour-particulars')?.value || '').trim(),
        activities_undergone: (tr.querySelector('.hr-hour-activities')?.value || '').trim()
      };
      if (one.time_from || one.time_to || one.particulars || one.activities_undergone) rows.push(one);
      if (one.time_from && one.time_to && one.particulars) validCount++;
    });
    const hrHour = document.getElementById('hrHour');
    if (hrHour) hrHour.value = JSON.stringify(rows);
    return validCount;
  }
  function addInternshipRow(data){
    const body = document.getElementById('hrInternBody');
    if (!body) return;
    const d = data || {};
    const tr = document.createElement('tr');
    tr.innerHTML = '<td><input class="hr-intern-serial_no" readonly value=""></td>'
      + '<td><input class="hr-intern-staff_name" value="'+(d.staff_name||'')+'"></td>'
      + '<td><input class="hr-intern-college_name" value="'+(d.college_name||'')+'"></td>'
      + '<td><input class="hr-intern-department" value="'+(d.department||'')+'"></td>'
      + '<td><input type="number" class="hr-intern-student_count" value="'+(d.student_count||'0')+'"></td>'
      + '<td><input class="hr-intern-platform" value="'+(d.platform||'')+'"></td>'
      + '<td><input class="hr-intern-topic" value="'+(d.topic||'')+'"></td>'
      + '<td><input class="hr-intern-mode_type" value="'+(d.mode_type||'')+'"></td>'
      + '<td><input class="hr-intern-duration_text" value="'+(d.duration_text||'')+'"></td>'
      + '<td><input type="date" class="hr-intern-start_date" value="'+(d.start_date||'')+'"></td>'
      + '<td><input type="date" class="hr-intern-finish_date" value="'+(d.finish_date||'')+'"></td>'
      + '<td><input class="hr-intern-mini_project" value="'+(d.mini_project||'')+'"></td>'
      + '<td><button type="button" class="dr-mini-btn del js-del-intern-row">Delete</button></td>';
    body.appendChild(tr);
    renumberInternRows();
  }
  function renumberInternRows(){
    document.querySelectorAll('#hrInternBody tr').forEach(function(tr,idx){
      const s = tr.querySelector('.hr-intern-serial_no');
      if (s) s.value = String(idx + 1);
    });
  }
  function renumberRows(bodySel, serialClass){
    document.querySelectorAll(bodySel + ' tr').forEach(function(tr, idx){
      const s = tr.querySelector(serialClass);
      if (s) s.value = String(idx + 1);
    });
  }
  document.getElementById('addHrInternRow')?.addEventListener('click', function(){ addInternshipRow(); });
  const form=document.getElementById('hrDailyForm');
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
    input.type = 'text';
    input.placeholder = cfg.placeholder || 'Search...';
    input.style.cssText = 'max-width:360px;';
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
      const q = (input.value||'').trim();
      if(!q){ if(window.Swal) Swal.fire({icon:'warning',title:'Search Required',text:'Type search text first.'}); return; }
      try{
        const url='index.php?page=dailyreports/entry&ajax=hr_lookup&section='+encodeURIComponent(cfg.section)+'&q='+encodeURIComponent(q);
        const res=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data=await res.json();
        const rows=Array.isArray(data.rows)?data.rows:[];
        if(!rows.length){ if(window.Swal) Swal.fire({icon:'info',title:'No Match',text:'No previous records found.'}); return; }
        let picked=rows[0];
        if(rows.length>1 && window.Swal){
          const opts={}; rows.forEach((r,i)=>{ opts[String(i)] = cfg.optionLabel(r); });
          const pick = await Swal.fire({title:'Select Record',input:'select',inputOptions:opts,inputValue:'0',showCancelButton:true,confirmButtonColor:'#e91e63'});
          if(!pick.isConfirmed) return;
          picked=rows[parseInt(pick.value||'0',10)] || rows[0];
        }
        const tr=ensureTargetRow();
        if(!tr) return;
        cfg.fillRow(tr,picked);
        if(window.Swal) Swal.fire({icon:'success',title:'Loaded',text:'Previous data loaded. Update and save.'});
      }catch(err){
        if(window.Swal) Swal.fire({icon:'error',title:'Error',text:'Search failed. Try again.'});
      }
    });
  }
  enableEnterNavigation(form);
  enableTablePaste(form);
  attachStatusAutoFill({
    tbodyId:'hrInterviewBody', addBtnId:'addHrInterviewRow', section:'interview',
    placeholder:'Search old interview (candidate/company/status)',
    optionLabel:r => (r.candidate_name||'-')+' | '+(r.company_name||'-')+' | '+(r.interview_status||'-')+' | '+(r.report_date||'-'),
    isRowEmpty:tr => !(tr.querySelector('.hr-intv-candidate_name')?.value||'').trim() && !(tr.querySelector('.hr-intv-company_name')?.value||'').trim(),
    fillRow:function(tr,r){ tr.querySelector('.hr-intv-candidate_name').value=r.candidate_name||''; tr.querySelector('.hr-intv-company_name').value=r.company_name||''; tr.querySelector('.hr-intv-interview_date').value=r.interview_date||''; tr.querySelector('.hr-intv-interview_status').value=r.interview_status||''; tr.querySelector('.hr-intv-remark').value=r.remark||''; }
  });
  attachStatusAutoFill({
    tbodyId:'hrPlacementBody', addBtnId:'addHrPlacementRow', section:'placement',
    placeholder:'Search old placement (company/poc/contact/status)',
    optionLabel:r => (r.company_name||'-')+' | '+(r.poc_name||'-')+' | '+(r.contact_no||'-')+' | '+(r.report_date||'-'),
    isRowEmpty:tr => !(tr.querySelector('.hr-place-company_name')?.value||'').trim() && !(tr.querySelector('.hr-place-contact_no')?.value||'').trim(),
    fillRow:function(tr,r){ tr.querySelector('.hr-place-entry_date').value=r.entry_date||'<?= h($reportDate) ?>'; tr.querySelector('.hr-place-company_name').value=r.company_name||''; tr.querySelector('.hr-place-poc_name').value=r.poc_name||''; tr.querySelector('.hr-place-contact_no').value=r.contact_no||''; tr.querySelector('.hr-place-status_text').value=r.status_text||''; tr.querySelector('.hr-place-remarks').value=r.remarks||''; }
  });
  attachStatusAutoFill({
    tbodyId:'hrOldClientBody', addBtnId:'addHrOldClientRow', section:'old_client',
    placeholder:'Search old client followup (company/poc/contact)',
    optionLabel:r => (r.client_company||'-')+' | '+(r.poc||'-')+' | '+(r.contact_no||'-')+' | '+(r.report_date||'-'),
    isRowEmpty:tr => !(tr.querySelector('.hr-old-client_company')?.value||'').trim() && !(tr.querySelector('.hr-old-contact_no')?.value||'').trim(),
    fillRow:function(tr,r){ tr.querySelector('.hr-old-client_company').value=r.client_company||''; tr.querySelector('.hr-old-poc').value=r.poc||''; tr.querySelector('.hr-old-contact_no').value=r.contact_no||''; tr.querySelector('.hr-old-email_id').value=r.email_id||''; tr.querySelector('.hr-old-followup_date').value=r.followup_date||'<?= h($reportDate) ?>'; tr.querySelector('.hr-old-followup_report').value=r.followup_report||''; }
  });
  attachStatusAutoFill({
    tbodyId:'hrNewClientBody', addBtnId:'addHrNewClientRow', section:'new_client',
    placeholder:'Search old new-client status (company/hr/contact/status)',
    optionLabel:r => (r.company_name||'-')+' | '+(r.hr_name||'-')+' | '+(r.contact_number||'-')+' | '+(r.report_date||'-'),
    isRowEmpty:tr => !(tr.querySelector('.hr-new-company_name')?.value||'').trim() && !(tr.querySelector('.hr-new-contact_number')?.value||'').trim(),
    fillRow:function(tr,r){ tr.querySelector('.hr-new-company_name').value=r.company_name||''; tr.querySelector('.hr-new-address').value=r.address||''; tr.querySelector('.hr-new-city').value=r.city||''; tr.querySelector('.hr-new-hr_name').value=r.hr_name||''; tr.querySelector('.hr-new-contact_number').value=r.contact_number||''; tr.querySelector('.hr-new-status_text').value=r.status_text||''; }
  });
  attachStatusAutoFill({
    tbodyId:'hrCollegeDataBody', addBtnId:'addHrCollegeDataRow', section:'college_data',
    placeholder:'Search old college data status (contact/college/status)',
    optionLabel:r => (r.contact_name||'-')+' | '+(r.college_name||'-')+' | '+(r.contact_no||'-')+' | '+(r.report_date||'-'),
    isRowEmpty:tr => !(tr.querySelector('.hr-cd-contact_name')?.value||'').trim() && !(tr.querySelector('.hr-cd-college_name')?.value||'').trim(),
    fillRow:function(tr,r){ tr.querySelector('.hr-cd-contact_name').value=r.contact_name||''; tr.querySelector('.hr-cd-contact_no').value=r.contact_no||''; tr.querySelector('.hr-cd-college_name').value=r.college_name||''; tr.querySelector('.hr-cd-topic').value=r.topic||''; tr.querySelector('.hr-cd-days_text').value=r.days_text||''; tr.querySelector('.hr-cd-resource_person').value=r.resource_person||''; tr.querySelector('.hr-cd-requirement').value=r.requirement||''; tr.querySelector('.hr-cd-status_text').value=r.status_text||''; }
  });
  renumberInternRows();
  root.addEventListener('click', function(e){
    if (e.target.classList.contains('js-del-intern-row')) {
      const tr = e.target.closest('tr');
      if (tr) tr.remove();
      renumberInternRows();
    }
    if (e.target.classList.contains('js-del-intv-row')) e.target.closest('tr')?.remove();
    if (e.target.classList.contains('js-del-placement-row')) e.target.closest('tr')?.remove();
    if (e.target.classList.contains('js-del-old-row')) {
      e.target.closest('tr')?.remove();
      renumberRows('#hrOldClientBody','.hr-old-serial_no');
    }
    if (e.target.classList.contains('js-del-new-row')) e.target.closest('tr')?.remove();
    if (e.target.classList.contains('js-del-cd-row')) {
      e.target.closest('tr')?.remove();
      renumberRows('#hrCollegeDataBody','.hr-cd-serial_no');
    }
    if (e.target.classList.contains('js-del-cf-row')) e.target.closest('tr')?.remove();
  });
  function serializeInternshipRows(){
    const rows = [];
    document.querySelectorAll('#hrInternBody tr').forEach(function(tr, idx){
      const one = {
        serial_no: String(idx + 1),
        staff_name: (tr.querySelector('.hr-intern-staff_name')?.value || '').trim(),
        college_name: (tr.querySelector('.hr-intern-college_name')?.value || '').trim(),
        department: (tr.querySelector('.hr-intern-department')?.value || '').trim(),
        student_count: (tr.querySelector('.hr-intern-student_count')?.value || '0').trim(),
        platform: (tr.querySelector('.hr-intern-platform')?.value || '').trim(),
        topic: (tr.querySelector('.hr-intern-topic')?.value || '').trim(),
        mode_type: (tr.querySelector('.hr-intern-mode_type')?.value || '').trim(),
        duration_text: (tr.querySelector('.hr-intern-duration_text')?.value || '').trim(),
        start_date: (tr.querySelector('.hr-intern-start_date')?.value || '').trim(),
        finish_date: (tr.querySelector('.hr-intern-finish_date')?.value || '').trim(),
        mini_project: (tr.querySelector('.hr-intern-mini_project')?.value || '').trim(),
        topic_1: ''
      };
      const hasAny = one.staff_name || one.college_name || one.department || (one.student_count && one.student_count !== '0') || one.platform || one.topic || one.mode_type || one.duration_text || one.start_date || one.finish_date || one.mini_project;
      if (hasAny) rows.push(one);
    });
    const hrIntern = document.getElementById('hrIntern');
    if (hrIntern) hrIntern.value = JSON.stringify(rows);
  }
  document.getElementById('addHrInterviewRow')?.addEventListener('click', function(){
    const body=document.getElementById('hrInterviewBody'); if(!body) return;
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input class="hr-intv-candidate_name"></td><td><input class="hr-intv-company_name"></td><td><input type="date" class="hr-intv-interview_date"></td><td><input class="hr-intv-interview_status"></td><td><textarea class="hr-intv-remark"></textarea></td><td><button type="button" class="dr-mini-btn del js-del-intv-row">Delete</button></td>';
    body.appendChild(tr);
  });
  document.getElementById('addHrPlacementRow')?.addEventListener('click', function(){
    const body=document.getElementById('hrPlacementBody'); if(!body) return;
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input type="date" class="hr-place-entry_date" value="<?= h($reportDate) ?>"></td><td><input class="hr-place-company_name"></td><td><input class="hr-place-poc_name"></td><td><input class="hr-place-contact_no"></td><td><input class="hr-place-status_text"></td><td><textarea class="hr-place-remarks"></textarea></td><td><button type="button" class="dr-mini-btn del js-del-placement-row">Delete</button></td>';
    body.appendChild(tr);
  });
  document.getElementById('addHrOldClientRow')?.addEventListener('click', function(){
    const body=document.getElementById('hrOldClientBody'); if(!body) return;
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input class="hr-old-serial_no" readonly value=""></td><td><input class="hr-old-client_company"></td><td><input class="hr-old-poc"></td><td><input class="hr-old-contact_no"></td><td><input class="hr-old-email_id"></td><td><input type="date" class="hr-old-followup_date" value="<?= h($reportDate) ?>"></td><td><textarea class="hr-old-followup_report"></textarea></td><td><button type="button" class="dr-mini-btn del js-del-old-row">Delete</button></td>';
    body.appendChild(tr);
    renumberRows('#hrOldClientBody','.hr-old-serial_no');
  });
  document.getElementById('addHrNewClientRow')?.addEventListener('click', function(){
    const body=document.getElementById('hrNewClientBody'); if(!body) return;
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input class="hr-new-company_name"></td><td><textarea class="hr-new-address"></textarea></td><td><input class="hr-new-city"></td><td><input class="hr-new-hr_name"></td><td><input class="hr-new-contact_number"></td><td><input class="hr-new-status_text"></td><td><button type="button" class="dr-mini-btn del js-del-new-row">Delete</button></td>';
    body.appendChild(tr);
  });
  document.getElementById('addHrCollegeDataRow')?.addEventListener('click', function(){
    const body=document.getElementById('hrCollegeDataBody'); if(!body) return;
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input class="hr-cd-serial_no" readonly value=""></td><td><input class="hr-cd-contact_name"></td><td><input class="hr-cd-contact_no"></td><td><input class="hr-cd-college_name"></td><td><input class="hr-cd-topic"></td><td><input class="hr-cd-days_text"></td><td><input class="hr-cd-resource_person"></td><td><textarea class="hr-cd-requirement"></textarea></td><td><input class="hr-cd-status_text"></td><td><button type="button" class="dr-mini-btn del js-del-cd-row">Delete</button></td>';
    body.appendChild(tr);
    renumberRows('#hrCollegeDataBody','.hr-cd-serial_no');
  });
  document.getElementById('addHrCollegeFollowRow')?.addEventListener('click', function(){
    const body=document.getElementById('hrCollegeFollowBody'); if(!body) return;
    const tr=document.createElement('tr');
    tr.innerHTML='<td><input class="hr-cf-name"></td><td><input class="hr-cf-position"></td><td><input class="hr-cf-mail_id"></td><td><input class="hr-cf-contact_number"></td><td><textarea class="hr-cf-report_text"></textarea></td><td><input class="hr-cf-college"></td><td><button type="button" class="dr-mini-btn del js-del-cf-row">Delete</button></td>';
    body.appendChild(tr);
  });
  renumberRows('#hrOldClientBody','.hr-old-serial_no');
  renumberRows('#hrCollegeDataBody','.hr-cd-serial_no');
  function serializeInterviewRows(){
    const rows=[]; document.querySelectorAll('#hrInterviewBody tr').forEach(function(tr){
      const one={candidate_name:(tr.querySelector('.hr-intv-candidate_name')?.value||'').trim(),company_name:(tr.querySelector('.hr-intv-company_name')?.value||'').trim(),interview_date:(tr.querySelector('.hr-intv-interview_date')?.value||'').trim(),interview_status:(tr.querySelector('.hr-intv-interview_status')?.value||'').trim(),remark:(tr.querySelector('.hr-intv-remark')?.value||'').trim()};
      if(one.candidate_name||one.company_name||one.interview_date||one.interview_status||one.remark) rows.push(one);
    }); const el=document.getElementById('hrIntv'); if(el) el.value=JSON.stringify(rows);
  }
  function serializePlacementRows(){
    const rows=[]; document.querySelectorAll('#hrPlacementBody tr').forEach(function(tr){
      const one={entry_date:(tr.querySelector('.hr-place-entry_date')?.value||'').trim(),company_name:(tr.querySelector('.hr-place-company_name')?.value||'').trim(),poc_name:(tr.querySelector('.hr-place-poc_name')?.value||'').trim(),contact_no:(tr.querySelector('.hr-place-contact_no')?.value||'').trim(),status_text:(tr.querySelector('.hr-place-status_text')?.value||'').trim(),remarks:(tr.querySelector('.hr-place-remarks')?.value||'').trim()};
      if(one.entry_date||one.company_name||one.poc_name||one.contact_no||one.status_text||one.remarks) rows.push(one);
    }); const el=document.getElementById('hrPlacement'); if(el) el.value=JSON.stringify(rows);
  }
  function serializeOldClientRows(){
    const rows=[]; document.querySelectorAll('#hrOldClientBody tr').forEach(function(tr,idx){
      const one={serial_no:String(idx+1),client_company:(tr.querySelector('.hr-old-client_company')?.value||'').trim(),poc:(tr.querySelector('.hr-old-poc')?.value||'').trim(),contact_no:(tr.querySelector('.hr-old-contact_no')?.value||'').trim(),email_id:(tr.querySelector('.hr-old-email_id')?.value||'').trim(),followup_date:(tr.querySelector('.hr-old-followup_date')?.value||'').trim(),followup_report:(tr.querySelector('.hr-old-followup_report')?.value||'').trim()};
      if(one.client_company||one.poc||one.contact_no||one.email_id||one.followup_date||one.followup_report) rows.push(one);
    }); const el=document.getElementById('hrOld'); if(el) el.value=JSON.stringify(rows);
  }
  function serializeNewClientRows(){
    const rows=[]; document.querySelectorAll('#hrNewClientBody tr').forEach(function(tr){
      const one={company_name:(tr.querySelector('.hr-new-company_name')?.value||'').trim(),address:(tr.querySelector('.hr-new-address')?.value||'').trim(),city:(tr.querySelector('.hr-new-city')?.value||'').trim(),hr_name:(tr.querySelector('.hr-new-hr_name')?.value||'').trim(),contact_number:(tr.querySelector('.hr-new-contact_number')?.value||'').trim(),status_text:(tr.querySelector('.hr-new-status_text')?.value||'').trim()};
      if(one.company_name||one.address||one.city||one.hr_name||one.contact_number||one.status_text) rows.push(one);
    }); const el=document.getElementById('hrNew'); if(el) el.value=JSON.stringify(rows);
  }
  function serializeCollegeDataRows(){
    const rows=[]; document.querySelectorAll('#hrCollegeDataBody tr').forEach(function(tr,idx){
      const one={serial_no:String(idx+1),contact_name:(tr.querySelector('.hr-cd-contact_name')?.value||'').trim(),contact_no:(tr.querySelector('.hr-cd-contact_no')?.value||'').trim(),college_name:(tr.querySelector('.hr-cd-college_name')?.value||'').trim(),topic:(tr.querySelector('.hr-cd-topic')?.value||'').trim(),days_text:(tr.querySelector('.hr-cd-days_text')?.value||'').trim(),resource_person:(tr.querySelector('.hr-cd-resource_person')?.value||'').trim(),requirement:(tr.querySelector('.hr-cd-requirement')?.value||'').trim(),status_text:(tr.querySelector('.hr-cd-status_text')?.value||'').trim()};
      if(one.contact_name||one.contact_no||one.college_name||one.topic||one.days_text||one.resource_person||one.requirement||one.status_text) rows.push(one);
    }); const el=document.getElementById('hrCd'); if(el) el.value=JSON.stringify(rows);
  }
  function serializeCollegeFollowRows(){
    const rows=[]; document.querySelectorAll('#hrCollegeFollowBody tr').forEach(function(tr){
      const one={name:(tr.querySelector('.hr-cf-name')?.value||'').trim(),position:(tr.querySelector('.hr-cf-position')?.value||'').trim(),mail_id:(tr.querySelector('.hr-cf-mail_id')?.value||'').trim(),contact_number:(tr.querySelector('.hr-cf-contact_number')?.value||'').trim(),report_text:(tr.querySelector('.hr-cf-report_text')?.value||'').trim(),college:(tr.querySelector('.hr-cf-college')?.value||'').trim()};
      if(one.name||one.position||one.mail_id||one.contact_number||one.report_text||one.college) rows.push(one);
    }); const el=document.getElementById('hrCf'); if(el) el.value=JSON.stringify(rows);
  }
  form?.addEventListener('submit',function(e){
    e.preventDefault();
    const hourlyValidCount = serializeHourlyRows();
    if (hourlyValidCount === 0) {
      show(2);
      if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Hourly Report Required', text: 'Please fill at least one hourly row (From, To, Particulars).' });
      } else {
        alert('Hourly Report is mandatory. Please fill at least one hourly row (From, To, Particulars).');
      }
      return;
    }
    serializeInternshipRows();
    serializeInterviewRows();
    serializePlacementRows();
    serializeOldClientRows();
    serializeNewClientRows();
    serializeCollegeDataRows();
    serializeCollegeFollowRows();
    if(typeof Swal!=='undefined'){
      Swal.fire({icon:'question',title:'Save HR Daily Report?',text:'This will save all sections to database.',showCancelButton:true,confirmButtonColor:'#e91e63',cancelButtonColor:'#6b7280',confirmButtonText:'Yes, Save All'}).then(r=>{
        if(!r.isConfirmed) return;
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }
        Swal.fire({title:'Saving report...',allowOutsideClick:false,didOpen:()=>Swal.showLoading()});
        form.submit();
      });
    }
    else form.submit();
  });
  const drSuccess = <?= json_encode($drSuccessMessage) ?>;
  const drError = <?= json_encode($drErrorMessage) ?>;
  if (typeof Swal !== 'undefined' && drSuccess) {
    Swal.fire({icon:'success',title:'Success',text:drSuccess,confirmButtonColor:'#e91e63'});
  } else if (typeof Swal !== 'undefined' && drError) {
    Swal.fire({icon:'error',title:'Error',text:drError,confirmButtonColor:'#e91e63'});
  }
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
})();
</script>
