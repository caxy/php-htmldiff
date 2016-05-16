<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\ListDiffLines;
use MongoDB\BSON\ObjectID;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/vendor/autoload.php';

$oldText = '<ul><li>first moved to last</li><li>test</li><li>not anywhere to be found</li><li>how about another</li><li>second one</li></ul>';
$newText = '<ul><li>test</li><li>how about another</li></li><li>another</li><li>second one</li><li>first moved to last</li></ul>';

$htmldiff = new ListDiffLines($oldText, $newText, 'UTF-8', array());
$htmldiff->build();
