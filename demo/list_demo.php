<?php

use Caxy\HtmlDiff\ListDiffNew;

ini_set('display_errors', 1);
error_reporting(E_ERROR);

$classes = array(
    'Caxy/HtmlDiff/AbstractDiff',
    'Caxy/HtmlDiff/Match',
    'Caxy/HtmlDiff/Operation',
    'Caxy/HtmlDiff/ListDiffNew',
);

foreach ($classes as $class) {
    require __DIR__.'/../lib/'.$class.'.php';
}

$testCases = array(
    array(
        'old' => <<<EOD
<ul class="class">
    <li class="li-class">list item 1</li>
    <li>List Item 2</li>
    <li>List Item 3</li>
</ul>
EOD
        ,
        'new' => <<<EOD
<ul class="class">
    <li class="li-class">list item 2</li>
    <li>List Item 3</li>
    <li>List Item 4</li>
</ul>
EOD
        ,
    ),
    array(
        'old' => <<<EOD
<ol>
    <li>Modified list item</li>
    <li>Last list item</li>
</ol>
EOD
        ,
        'new' => <<<EOD
<ol>
    <li>New first list item</li>
    <li>New second list item</li>
    <li>Modified list item new text</li>
    <li>New fourth list item</li>
    <li>Last list item</li>
</ol>
EOD
    ),
);

foreach ($testCases as $index => $testCase) {
    $listDiff = new ListDiffNew($testCase['old'], $testCase['new']);

    $listDiff->build();
    echo sprintf('<h3>Test Case %d</h3><p>%s</p>', $index, $listDiff->getDifference());
}
