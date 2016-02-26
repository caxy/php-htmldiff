<?php

use Caxy\HtmlDiff\HtmlDiff;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$requestBody = file_get_contents('php://input');

$requestJson = json_decode($requestBody, true);

if (empty($requestJson['index']) && empty($requestJson['page'])) {
    throw new \Exception('index or page are required.');
}

$jsonFile = __DIR__.'/tablediffs.json';
$demoStorage = json_decode(file_get_contents($jsonFile), true);

if (isset($requestJson['index'])) {
    header('Content-Type: application/json');

    if (!array_key_exists($requestJson['index'], $demoStorage)) {
        throw new \Exception('index not found.');
    }

    $targetDemo = $demoStorage[$requestJson['index']];

    echo json_encode($targetDemo);
    exit();
}

if (isset($requestJson['page'])) {
    header('Content-Type: application/json');

    $trackerDataFile = __DIR__.'/data/trackerData.json';

    $trackerDataJson = json_decode(file_get_contents($trackerDataFile), true);

    $page = $requestJson['page'];
    $size = isset($requestJson['size']) ? $requestJson['size'] : 20;

    if ($page < 0) {
        $page = 1;
    }

    $offset = ($page - 1) * $size;

    $tableDiffsToShow = array();

    foreach ($demoStorage as $demoIndex => $tableDiff) {
        if (!array_key_exists($tableDiff['id'], $trackerDataJson)) {
            $tableDiff['trackerData'] = array(
                'id' => $tableDiff['id'],
                'hash' => null,
                'status' => null,
                'prevStatus' => null
            );
            $tableDiff['demoIndex'] = $demoIndex;
            $tableDiffsToShow[] = $tableDiff;
            continue;
        }

        $trackerData = $trackerDataJson[$tableDiff['id']];

        if (empty($trackerData['status']) || $trackerData['status'] === 'hashChanged') {
            $tableDiff['trackerData'] = $trackerData;
            $tableDiff['demoIndex'] = $demoIndex;
            $tableDiffsToShow[] = $tableDiff;
        }
    }

    $tableDiffs = array_slice($tableDiffsToShow, $offset, $size);

    foreach ($tableDiffs as $index => &$tableDiff) {
        $oldText = $tableDiff['old'];
        $newText = $tableDiff['new'];

        $diff = new HtmlDiff($oldText, $newText, 'UTF-8', array());
        $tableDiff['diff'] = $diff->build();

        $diffHash = md5($tableDiff['diff']);

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

        $tableDiff['trackerData'] = $trackerData;

        $trackerDataJson[$tableDiff['id']] = $trackerData;
    }

    $output = json_encode($tableDiffs);

    if (false !== $output) {
        echo $output;
    } else {
        throw new \Exception('Failed to encode to JSON: '.json_last_error_msg());
    }

    exit();
}
