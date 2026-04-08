<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/core/helper.php';
$tables = ['dailyreport_master','dailyreport_frontoffice_activity'];
foreach($tables as $t){
  $a = function_exists('crmTableExists') ? (crmTableExists($pdo,$t)?'YES':'NO') : 'NOFUNC';
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
  $st->execute([$t]);
  $b = ((int)$st->fetchColumn() > 0) ? 'YES' : 'NO';
  echo $t . ' crmTableExists=' . $a . ' infoSchema=' . $b . PHP_EOL;
}
?>
