<?php
require_once '../../config/app.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

$stmt = $pdo->prepare("
    SELECT id, name
    FROM contacts_master
    WHERE name LIKE ?
    LIMIT 10
");

$stmt->execute(["%$q%"]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));