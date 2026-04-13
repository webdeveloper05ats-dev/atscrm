<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
$branchId = (int)($_SESSION['branch_id'] ?? 1);
$userId = (int)($_SESSION['user_id'] ?? 1);

function q($pdo,$sql,$p){ $st=$pdo->prepare($sql); $st->execute($p); return $st->fetchColumn(); }

try {
  $c1 = q($pdo, "SELECT COUNT(*) FROM enquiry_followups f WHERE f.branch_id=:b AND LOWER(TRIM(COALESCE(f.status,'pending')))='pending'", [':b'=>$branchId]);
  $c2 = q($pdo, "SELECT COUNT(*) FROM enquiry_followups f WHERE f.branch_id=:b AND LOWER(TRIM(COALESCE(f.status,'pending')))='pending' AND DATE(f.followup_date) < CURDATE()", [':b'=>$branchId]);
  $c3 = q($pdo, "SELECT COUNT(*) FROM enquiry_followups f WHERE f.branch_id=:b AND LOWER(TRIM(COALESCE(f.status,'pending')))='pending' AND DATE(f.followup_date)=CURDATE() AND f.followup_time IS NOT NULL AND TIME(f.followup_time) BETWEEN CURTIME() AND ADDTIME(CURTIME(),'02:00:00')", [':b'=>$branchId]);
  echo "branch=$branchId user=$userId pending=$c1 missed=$c2 due_soon=$c3\n";
} catch (Throwable $e) {
  echo 'ERR: ' . $e->getMessage() . "\n";
}
?>
