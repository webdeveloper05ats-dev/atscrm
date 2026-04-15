<?php
require __DIR__ . '/config/app.php';
require __DIR__ . '/config/database.php';
try {
    $st = $pdo->query("SELECT DISTINCT COALESCE(table_name, '') AS table_name FROM audit_logs ORDER BY table_name");
    foreach (($st->fetchAll(PDO::FETCH_COLUMN) ?: []) as $m) {
        echo ($m === '' ? '<blank>' : $m), PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
}
