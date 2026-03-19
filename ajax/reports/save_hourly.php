<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

try {

    $reportId = (int)($_POST['report_id'] ?? 0);

    if (!$reportId) throw new Exception("Invalid report");

    // DELETE OLD
    $pdo->prepare("DELETE FROM report_hourly WHERE report_id=?")->execute([$reportId]);

    $times = $_POST['time_slot'] ?? [];

    foreach ($times as $i => $t) {

        if (!$t) continue;

        $stmt = $pdo->prepare("
            INSERT INTO report_hourly
            (report_id,time_slot,particulars,activities)
            VALUES (?,?,?,?)
        ");

        $stmt->execute([
            $reportId,
            $t,
            $_POST['particulars'][$i] ?? '',
            $_POST['activities'][$i] ?? ''
        ]);
    }

    echo json_encode([
        'status'=>'success',
        'message'=>'Hourly report saved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'=>'error',
        'message'=>$e->getMessage()
    ]);
}