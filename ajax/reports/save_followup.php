<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

$reportId = $_POST['report_id'] ?? 0;

$refIds   = $_POST['ref_id'] ?? [];
$status   = $_POST['status'] ?? [];
$remarks  = $_POST['remarks'] ?? [];
$dates    = $_POST['follow_date'] ?? [];

if(!$reportId){
    echo json_encode(['status'=>'error','message'=>'Invalid report']);
    exit;
}

for($i=0;$i<count($refIds);$i++){

    $stmt = $pdo->prepare("
        INSERT INTO report_followups
        (report_id, ref_id, status, remarks, follow_date)
        VALUES (?,?,?,?,?)
    ");

    $stmt->execute([
        $reportId,
        $refIds[$i],
        $status[$i] ?? '',
        $remarks[$i] ?? '',
        $dates[$i] ?? null
    ]);
}

echo json_encode([
    'status'=>'success',
    'message'=>'Follow-ups saved successfully'
]);