<?php

$requestBody = file_get_contents('php://input');

$requestJson = json_decode($requestBody, true);

if (empty($requestJson['old']) && empty($requestJson['new'])) {
    throw new \Exception('Old text or new text is required.');
}

$jsonFile = __DIR__.'/demos.json';

$demoStorage = json_decode(file_get_contents($jsonFile), true);

if (empty($requestJson['name'])) {
    $requestJson['name'] = 'DEMO '.count($demoStorage);
}

$oldText = $requestJson['old'];
$newText = $requestJson['new'];
$name = $requestJson['name'];
$legislativeOverride = !empty($requestJson['legislativeOverride']) ? $requestJson['legislativeOverride'] : null;

$existingDemoIndex = null;
foreach ($demoStorage as $index => $demo) {
    if ($demo['name'] === $name) {
        $existingDemoIndex = $index;
        break;
    }
}

if ($existingDemoIndex !== null) {
    $demoStorage[$existingDemoIndex]['old'] = $oldText;
    $demoStorage[$existingDemoIndex]['new'] = $newText;
} else {
    $demoStorage[] = array(
        'name' => $name,
        'old'  => $oldText,
        'new'  => $newText,
        'legislativeOverride' => $legislativeOverride,
    );
}

if (false === file_put_contents($jsonFile, json_encode($demoStorage))) {
    throw new \Exception("Unable to save to file: $jsonFile");
}
