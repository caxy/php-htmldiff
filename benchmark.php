<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use Caxy\HtmlDiff\HtmlDiff;
use MongoDB\BSON\ObjectID;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/vendor/autoload.php';

$mongodb = new Client();
$collection = $mongodb->tracker->diffs;

echo "\nHtmlDiff Benchmark";
$diffs = $collection->find([]);
$totalCount = $collection->count([]);

echo "\nBenchmarking with ".$totalCount." diffs.";
$elapsedTime = 0;
$runs = [];

$indexPad = strlen((string) $totalCount);
$secPad = 6;
$padding = strlen(sprintf('%s Elapsed: %s s', '', '')) + $indexPad + $secPad;

echo "\nProgress: ".str_pad('', $padding, ' ', STR_PAD_RIGHT);

foreach ($diffs as $index => $diff) {
    $time = processDiff($diff);
    $runs[] = $time;
    $elapsedTime += $time;
    echo "\033[{$padding}D";
    echo sprintf('%s Elapsed: %s s', str_pad($index, $indexPad, ' ', STR_PAD_RIGHT), str_pad((int) $elapsedTime, $secPad, ' ', STR_PAD_LEFT));
}

echo "\nElapsed Time: ".$elapsedTime.'s';
echo "\nAverage: ".(array_sum($runs) / count($runs)).'s';

exit();

function processDiff(Diff $diff)
{
    $oldText = $diff->getOldContent();
    $newText = $diff->getNewContent();

    $start = microtime(true);
    $htmldiff = new HtmlDiff($oldText, $newText, 'UTF-8', array());
    $htmldiff->build();
    $finish = microtime(true);

    return $finish - $start;
}

