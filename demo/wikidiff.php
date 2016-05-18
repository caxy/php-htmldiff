<?php

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\ListDiffLines;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/../vendor/autoload.php';

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

echo '<link rel="stylesheet" href="codes.css" />';

foreach ($diffs as $index => $diff) {
    $texts = [];

    foreach ($diff as $items) {
        $texts[] = sprintf('<ul><li>%s</li></ul>', implode('</li><li>', $items));
    }

    echo "<h2>Diffing $index</h2>";
//    echo html_diff($texts[0], $texts[1], true);
    $htmldiff = new ListDiffLines($texts[0], $texts[1]);
    echo $htmldiff->build();
}
