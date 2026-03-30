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
    $pdo->prepare("DELETE FROM report_registrations WHERE report_id=?")->execute([$reportId]);

    $names = $_POST['name'] ?? [];

    foreach ($names as $i => $name) {

        if (!$name) continue;

        $stmt = $pdo->prepare("
            INSERT INTO report_registrations
            (report_id,name,department,contact_no,college,date_of_reg,course,billing,collection,balance,payment_mode)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $reportId,
            $name,
            $_POST['department'][$i] ?? '',
            $_POST['contact_no'][$i] ?? '',
            $_POST['college'][$i] ?? '',
            $_POST['date_of_reg'][$i] ?? null,
            $_POST['course'][$i] ?? '',
            $_POST['billing'][$i] ?? 0,
            $_POST['collection'][$i] ?? 0,
            $_POST['balance'][$i] ?? 0,
            $_POST['payment_mode'][$i] ?? ''
        ]);
    }

    echo json_encode([
        'status'=>'success',
        'message'=>'Registration saved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}