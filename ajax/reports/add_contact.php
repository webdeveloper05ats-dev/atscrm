<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

$name = $_POST['name'] ?? '';

if (!$name) {
    echo json_encode(['status'=>'error','message'=>'Name required']);
    exit;
}

$stmt = $pdo->prepare("
INSERT INTO contacts_master (name)
VALUES (?)
");

$stmt->execute([$name]);

echo json_encode([
    'status'=>'success',
    'id'=>$pdo->lastInsertId()
]);