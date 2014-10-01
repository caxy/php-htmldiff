<?php

use Caxy\HtmlDiff\HtmlDiff;

require __DIR__.'/../lib/Caxy/HtmlDiff/AbstractDiff.php';
require __DIR__.'/../lib/Caxy/HtmlDiff/HtmlDiff.php';
require __DIR__.'/../lib/Caxy/HtmlDiff/TableDiff.php';
require __DIR__.'/../lib/Caxy/HtmlDiff/Match.php';
require __DIR__.'/../lib/Caxy/HtmlDiff/Operation.php';

$input = file_get_contents('php://input');

ini_set('display_errors', 1);
error_reporting(E_ERROR);

if ($input) {
    $data = json_decode($input, true);
    $diff = new HtmlDiff($data['oldText'], $data['newText'], 'UTF-8', array());
    $diff->build();
    
    header('Content-Type: application/json');
    echo json_encode(array('diff' => $diff->getDifference()));
} else {
    header('Content-Type: text/html');
    echo file_get_contents('demo.html');
}
