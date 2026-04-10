<?php
require __DIR__ . '/config/database.php';
$tables = [
  'dailyreport_master',
  'dailyreport_frontoffice_activity',
  'dailyreport_frontoffice_registration_rows',
  'dailyreport_frontoffice_planner_rows',
  'dailyreport_frontoffice_hourly_rows',
  'dailyreport_frontoffice_college_followup_rows',
  'dailyreport_frontoffice_college_followup_status',
  'dailyreport_frontoffice_database_followup_rows',
  'dailyreport_frontoffice_database_followup_status',
  'reports','report_activity','report_hourly','report_registrations'
];
foreach ($tables as $t) {
  echo "\n=== {$t} ===\n";
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
  $st->execute([$t]);
  if ((int)$st->fetchColumn() === 0) { echo "(missing)\n"; continue; }
  $cols = $pdo->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cols as $c) {
    echo $c['Field'] . ' | ' . $c['Type'] . ' | ' . $c['Null'] . ' | ' . ($c['Key'] ?: '-') . ' | ' . ($c['Default']===null?'NULL':$c['Default']) . "\n";
  }
}
?>
