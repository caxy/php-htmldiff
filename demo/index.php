<?php

use Caxy\HtmlDiff\HtmlDiff;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$debugOutput = array();

function addDebugOutput($value, $key = 'general')
{
    global $debugOutput;

    if (!is_string($value)) {
        $value = var_export($value, true);
    }

    if (!array_key_exists($key, $debugOutput)) {
        $debugOutput[$key] = array();
    }

    $debugOutput[$key][] = $value;
}

$input = file_get_contents('php://input');

if ($input) {
    $data = json_decode($input, true);
    $diff = new HtmlDiff($data['oldText'], $data['newText'], 'UTF-8', array());
    if (array_key_exists('matchThreshold', $data)) {
        $diff->setMatchThreshold($data['matchThreshold']);
    }
    $diff->build();

    header('Content-Type: application/json');
    echo json_encode(array('diff' => $diff->getDifference(), 'debug' => $debugOutput));
} else {
    header('Content-Type: text/html');
    echo file_get_contents('demo.html');
}
