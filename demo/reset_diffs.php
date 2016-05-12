<?php

use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

$mongodb = new Client();
$collection = $mongodb->tracker->diffs;
$result = $collection->drop();

var_dump($result);

exit();

