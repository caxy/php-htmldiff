<?php

$requestBody = file_get_contents('php://input');

$requestJson = json_decode($requestBody, true);

if (empty($requestJson['id']) || empty($requestJson['status'])) {
    throw new \Exception('id or status are required.');
}

$jsonFile = __DIR__.'/data/trackerData.json';

$trackerStorage = json_decode(file_get_contents($jsonFile), true);

$diffId = $requestJson['id'];
$status = $requestJson['status'];
$hash = $requestJson['hash'];

if (array_key_exists($diffId, $trackerStorage)) {
    $trackerData = $trackerStorage[$diffId];
} else {
    $trackerData = array('id' => $diffId, 'status' => null);
}

$trackerData['prevStatus'] = $trackerData['status'];
$trackerData['status'] = $status;
$trackerData['hash'] = $hash;

$trackerStorage[$diffId] = $trackerData;

if (false === file_put_contents($jsonFile, json_encode($trackerStorage))) {
    throw new \Exception("Unable to save to file: $jsonFile");
}
