<?php

$requestBody = file_get_contents('php://input');

$requestJson = json_decode($requestBody, true);

if (empty($requestJson['index'])) {
    throw new \Exception('index is required.');
}

$jsonFile = __DIR__.'/tablediffs.json';

$demoStorage = json_decode(file_get_contents($jsonFile), true);

if (!array_key_exists($requestJson['index'], $demoStorage)) {
    throw new \Exception('index not found.');
}

$targetDemo = $demoStorage[$requestJson['index']];

header('Content-Type: application/json');
echo json_encode($targetDemo);
