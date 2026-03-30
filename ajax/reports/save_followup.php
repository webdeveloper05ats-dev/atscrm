<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

$reportId = (int)($_POST['report_id'] ?? 0);
$userId   = (int)$_SESSION['user_id'];

$refIds   = $_POST['ref_id'] ?? [];
$status   = $_POST['status'] ?? [];
$remarks  = $_POST['remarks'] ?? [];
$dates    = $_POST['follow_date'] ?? [];

if (!$reportId) {
    echo json_encode(['status'=>'error','message'=>'Invalid report']);
    exit;
}

// Verify report ownership
try {
    $ownerCheck = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND user_id = ? LIMIT 1");
    $ownerCheck->execute([$reportId, $userId]);
    if (!$ownerCheck->fetchColumn()) {
        echo json_encode(['status'=>'error','message'=>'Access denied. You can only modify your own reports.']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['status'=>'error','message'=>'Ownership check failed.']);
    exit;
}

for ($i = 0; $i < count($refIds); $i++) {

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