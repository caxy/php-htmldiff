<?php

use Caxy\HtmlDiff\HtmlDiff;

ini_set('display_errors', 1);
error_reporting(E_ERROR);

$classes = array(
    'Caxy/HtmlDiff/AbstractDiff',
    'Caxy/HtmlDiff/HtmlDiff',
    'Caxy/HtmlDiff/Table/TableDiff',
    'Caxy/HtmlDiff/Table/AbstractTableElement',
    'Caxy/HtmlDiff/Table/Table',
    'Caxy/HtmlDiff/Table/TableRow',
    'Caxy/HtmlDiff/Table/TableCell',
    'Caxy/HtmlDiff/Table/TablePosition',
    'Caxy/HtmlDiff/Table/TableMatch',
    'Caxy/HtmlDiff/Match',
    'Caxy/HtmlDiff/Operation',
);

foreach ($classes as $class) {
    require __DIR__.'/../lib/'.$class.'.php';
}

$input = file_get_contents('php://input');

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
