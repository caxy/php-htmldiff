<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$jsonFile = __DIR__.'/listdiffs.json';
$demoStorage = json_decode(file_get_contents($jsonFile), true);

$trackerDataFile = __DIR__.'/data/trackerData.json';
$trackerDataJson = json_decode(file_get_contents($trackerDataFile), true);

header('Content-Type: application/json');

$statusCounts = [];

foreach ($demoStorage as $demoIndex => $diff) {
    if (!array_key_exists($diff['id'], $trackerDataJson)) {
        $diff['trackerData'] = array(
            'id' => $diff['id'],
            'hash' => null,
            'status' => null,
            'prevStatus' => null
        );
        $diff['demoIndex'] = $demoIndex;
        continue;
    }

    $trackerData = $trackerDataJson[$diff['id']];

    $status = !empty($trackerData['status']) ? $trackerData['status'] : 'none';

    if (!array_key_exists($status, $statusCounts)) {
        $statusCounts[$status] = 0;
    }

    $statusCounts[$status]++;
}

$output = json_encode($statusCounts);

if (false !== $output) {
    echo $output;
} else {
    throw new \Exception('Failed to encode to JSON: '.json_last_error_msg());
}

exit();

