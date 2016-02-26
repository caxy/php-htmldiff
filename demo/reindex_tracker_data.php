<?php

use Caxy\HtmlDiff\HtmlDiff;

ini_set('display_errors', 1);
error_reporting(E_ALL);

set_time_limit(0);
ob_implicit_flush(true);
ob_end_flush();

require __DIR__.'/../vendor/autoload.php';

header('Content-Type: text/event-stream');

$jsonFile = __DIR__.'/tablediffs.json';
$demoStorage = json_decode(file_get_contents($jsonFile), true);

$trackerDataFile = __DIR__.'/data/trackerData.json';
$trackerDataJson = json_decode(file_get_contents($trackerDataFile), true);

$demoCount = count($demoStorage);

$currentPercentage = 0;
$displayPercentage = 0;
$step = (1 / $demoCount) * 100;

foreach ($demoStorage as $index => &$tableDiff) {
    $oldText = $tableDiff['old'];
    $newText = $tableDiff['new'];

    $diff = new HtmlDiff($oldText, $newText, 'UTF-8', array());
    $diffOutput = \ForceUTF8\Encoding::toUTF8($diff->build());

    $diffHash = md5($diffOutput);

    if (array_key_exists($tableDiff['id'], $trackerDataJson)) {
        $trackerData = $trackerDataJson[$tableDiff['id']];
        // check if diff changed
        if ($diffHash !== $trackerData['hash']) {
            if ($trackerData['status'] !== null) {
                if ($trackerData['prevStatus'] !== 'hashChanged') {
                    $trackerData['prevStatus'] = $trackerData['status'];
                }
                $trackerData['status'] = 'hashChanged';
            }

            $trackerData['hash'] = $diffHash;
        }
    } else {
        $trackerData = array(
            'id' => $tableDiff['id'],
            'hash' => $diffHash,
            'status' => null,
            'prevStatus' => null
        );
    }

    $trackerDataJson[$tableDiff['id']] = $trackerData;

    $currentPercentage += $step;

    if (floor($currentPercentage) > $displayPercentage) {
        $displayPercentage = floor($currentPercentage);
        echo sprintf("data: %d\n\n", $displayPercentage);
    }
}

$jsonOutput = json_encode($trackerDataJson);

if (false === $jsonOutput || false === file_put_contents($trackerDataFile, $jsonOutput)) {
    throw new \Exception('Encoding to JSON failed: '.json_last_error_msg());
}

echo "event: finish\ndata: done\n\n";
