<?php

use Caxy\HtmlDiff\HtmlDiff;
use ForceUTF8\Encoding;

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

//    $oldText = $data['oldText'];
//    $newText = $data['newText'];

    $oldText = Encoding::toUTF8($data['oldText']);
    $newText = Encoding::toUTF8($data['newText']);
    $useTableDiffing = isset($data['tableDiffing']) ? $data['tableDiffing'] : true;

    $diff = new HtmlDiff($oldText, $newText, 'UTF-8', array());
    if (array_key_exists('matchThreshold', $data)) {
        $diff->setMatchThreshold($data['matchThreshold']);
    }
    $diff->setUseTableDiffing($useTableDiffing);
    addDebugOutput($diff->build());

    header('Content-Type: application/json');
    echo json_encode(array('diff' => $diff->getDifference(), 'debug' => $debugOutput));
} else {
    header('Content-Type: text/html');
    echo file_get_contents('demo.html');
}
