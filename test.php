<?php

use Caxy\HtmlDiff\Demo\Model\Diff;
use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\ListDiffLines;
use Caxy\HtmlDiff\Preprocessor;
use MongoDB\BSON\ObjectID;
use MongoDB\Client;

ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__.'/vendor/autoload.php';


$a = <<<EOD
<strong>Mortar preparation</strong>. The mortar mix shall be proportioned as required by the construction specifications. The pointing mortar shall be prepared by first thoroughly mixing all ingredients dry and then mixing again, adding only enough water to produce a damp unworkable mix that retains its form when pressed into a ball. The mortar shall be kept in a damp condition for not less than one hour and not more than 1<sup>1</sup>/ <sub>2</sub> hours for pre-hydration; then sufficient water shall be added to bring it to a workable consistency for pointing, which is somewhat drier than conventional masonry mortar. Use mortar within one and two and one-half hours from its initial mixing.
EOD;

$b = <<<EOD
<strong>Mortar preparation</strong>. The mortar mix shall be proportioned as required by the construction specifications and manufacturer's <em>approved</em> instructions.
EOD;

$a = trim(strip_tags($a));
$b = trim(strip_tags($b));

$prefix = Preprocessor::diffCommonPrefix($a, $b);
$suffix = Preprocessor::diffCommonSuffix($a, $b);

$len = min(strlen($a), strlen($b));
$remaining = $len - ($prefix + $suffix);

$percentRemaining = $remaining / $len;

$percentage = $len / max(strlen($a), strlen($b));

echo sprintf(
    "\nPrefix: %d\nSuffix: %d\nLength: %d\nRemaining: %d\nPercent Remaining: %f\nPercentage: %f\n",
    $prefix,
    $suffix,
    $len,
    $remaining,
    $percentRemaining,
    $percentage
);
exit();


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
