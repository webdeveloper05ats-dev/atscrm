<?php
require __DIR__ . '/config/database.php';
try {
    echo 'DB=' . $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
    $st = $pdo->query("SHOW TABLES LIKE 'dailyreport_%'");
    $rows = $st->fetchAll(PDO::FETCH_NUM);
    if (!$rows) {
        echo "NO_DAILYREPORT_TABLES" . PHP_EOL;
    } else {
        foreach ($rows as $r) {
            echo $r[0] . PHP_EOL;
        }
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
}
?>
