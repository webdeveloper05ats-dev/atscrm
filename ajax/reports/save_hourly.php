<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

try {

    $reportId = (int)($_POST['report_id'] ?? 0);
    $userId   = (int)$_SESSION['user_id'];

    if (!$reportId) throw new Exception("Invalid report");

    // Verify report ownership
    $ownerCheck = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND user_id = ? LIMIT 1");
    $ownerCheck->execute([$reportId, $userId]);
    if (!$ownerCheck->fetchColumn()) {
        throw new Exception("Access denied. You can only modify your own reports.");
    }

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