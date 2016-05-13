<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$mongodb = new Client();
$collection = $mongodb->tracker->diffs;

$diffs = $collection->find(['$or' => [
    ['$where' => 'this.oldContent == this.newContent'],
    ['oldContent' => null],
    ['newContent' => null],
    ['oldContent' => ''],
    ['newContent' => ''],
]]);

$count = 0;
foreach ($diffs as $diff) {
    $count++;
    $collection->deleteOne(['_id' => $diff->getId()]);
}

echo "<h1>Deleted $count diffs";

exit();

