<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

$response = ['status'=>'error','message'=>'Something went wrong'];

try {

    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Session expired");
    }

    $reportId = (int)($_POST['report_id'] ?? 0);
    $userId   = (int)$_SESSION['user_id'];

    if (!$reportId) {
        throw new Exception("Invalid report");
    }

    // Verify report ownership
    $ownerCheck = $pdo->prepare("SELECT id FROM reports WHERE id = ? AND user_id = ? LIMIT 1");
    $ownerCheck->execute([$reportId, $userId]);
    if (!$ownerCheck->fetchColumn()) {
        throw new Exception("Access denied. You can only modify your own reports.");
    }

    $fields = [
        'fresh_calls','follow_calls','msg_sent','mail_sent','total_calls',
        'promo','reference','db_calls','total_reg',
        'billing','fresh_collection','old_collection','total_collection',
        'registrations','walkins','conversion_ratio'
    ];

    $values = [];

    foreach ($fields as $f) {
        $values[$f] = $_POST[$f] ?? 0;
    }

    $check = $pdo->prepare("SELECT id FROM report_activity WHERE report_id=?");
    $check->execute([$reportId]);
    $exists = $check->fetchColumn();

    if ($exists) {

        $stmt = $pdo->prepare("UPDATE report_activity SET
            fresh_calls=?, follow_calls=?, msg_sent=?, mail_sent=?, total_calls=?,
            promo=?, reference=?, db_calls=?, total_reg=?,
            billing=?, fresh_collection=?, old_collection=?, total_collection=?,
            registrations=?, walkins=?, conversion_ratio=?
            WHERE report_id=?");

        $stmt->execute([
            $values['fresh_calls'], $values['follow_calls'], $values['msg_sent'], $values['mail_sent'], $values['total_calls'],
            $values['promo'], $values['reference'], $values['db_calls'], $values['total_reg'],
            $values['billing'], $values['fresh_collection'], $values['old_collection'], $values['total_collection'],
            $values['registrations'], $values['walkins'], $values['conversion_ratio'],
            $reportId
        ]);

    } else {

        $stmt = $pdo->prepare("INSERT INTO report_activity (
            report_id,
            fresh_calls, follow_calls, msg_sent, mail_sent, total_calls,
            promo, reference, db_calls, total_reg,
            billing, fresh_collection, old_collection, total_collection,
            registrations, walkins, conversion_ratio
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->execute([
            $reportId,
            $values['fresh_calls'], $values['follow_calls'], $values['msg_sent'], $values['mail_sent'], $values['total_calls'],
            $values['promo'], $values['reference'], $values['db_calls'], $values['total_reg'],
            $values['billing'], $values['fresh_collection'], $values['old_collection'], $values['total_collection'],
            $values['registrations'], $values['walkins'], $values['conversion_ratio']
        ]);
    }

    $response = [
        'status'=>'success',
        'message'=>'Activity saved successfully'
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);