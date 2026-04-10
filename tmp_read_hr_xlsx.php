<?php
require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
$path = 'c:\\Users\\atsph\\Downloads\\Daily Report-July 12.xlsx';
$wb = IOFactory::load($path);
$result = [];
foreach ($wb->getWorksheetIterator() as $ws) {
    $title = $ws->getTitle();
    $maxRow = min($ws->getHighestDataRow(), 30);
    $maxCol = min(Coordinate::columnIndexFromString($ws->getHighestDataColumn()), 40);
    $rows = [];
    for ($r=1; $r<=$maxRow; $r++) {
        $line = [];
        $has = false;
        for ($c=1; $c<=$maxCol; $c++) {
            $ref = Coordinate::stringFromColumnIndex($c) . $r;
            $val = trim((string)$ws->getCell($ref)->getFormattedValue());
            $line[] = $val;
            if ($val !== '') $has = true;
        }
        if ($has) $rows[] = $line;
    }
    $result[$title] = array_slice($rows, 0, 15);
}
echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
