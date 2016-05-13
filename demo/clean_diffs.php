<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$mongodb = new Client();
$collection = $mongodb->tracker->diffs;

$diffs = $collection->find(['$or' => [
    ['oldContent' => null],
    ['newContent' => null],
    ['oldContent' => ''],
    ['newContent' => ''],
]]);

foreach ($diffs as $diff) {
    if (strlen($diff->getOldContent()) === 0 || strlen($diff->getNewContent()) === 0) {
        echo "\nDeleted: ".$diff->getId();
        $collection->deleteOne(['_id' => $diff->getId()]);
    }
}

exit();

