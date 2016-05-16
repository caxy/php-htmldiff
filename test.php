<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\ListDiffLines;
use MongoDB\BSON\ObjectID;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/vendor/autoload.php';

$diffs = [
    [
        [
            'first moved to last',
            'test',
            'not anywhere to be found',
            'how about another',
            'second one',
        ],
        [
            'test',
            'how about another',
            'another',
            'second one',
            'first moved to last',
        ],
    ],
    [
        ['a', 'b', 'c', 'd', 'e', 'f', 'g'],
        ['w', 'a', 'b', 'x', 'y', 'z', 'e'],
    ]
];

foreach ($diffs as $index => $diff) {
    $texts = [];

    foreach ($diff as $items) {
        $texts[] = sprintf('<ul><li>%s</li></ul>', implode('</li><li>', $items));
    }

    echo "\n\nDiffing $index\n";
    $htmldiff = new ListDiffLines($texts[0], $texts[1]);
    echo "\n".$htmldiff->build();
}
