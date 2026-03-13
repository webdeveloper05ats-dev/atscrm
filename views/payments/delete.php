<?php

if (!defined('APP_NAME')) {
die("Unauthorized access.");
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
DELETE FROM registration_payments 
WHERE registration_id = ?
");

$stmt->execute([$id]);

setFlash('success','Payment deleted');

redirect('index.php?page=payments');

?>