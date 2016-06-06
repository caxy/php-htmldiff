<?php

namespace Caxy\Tests\HtmlDiff\Functional;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\Tests\AbstractTest;
use Caxy\Tests\HtmlDiff\HtmlFileIterator;

class HtmlDiffFunctionalTest extends AbstractTest
{
    /**
     * @dataProvider diffContentProvider
     *
     * @param $oldText
     * @param $newText
     * @param $expected
     */
    public function testHtmlDiff($oldText, $newText, $expected)
    {
        $diff = new HtmlDiff(trim($oldText), trim($newText), 'UTF-8', array());
        $output = $diff->build();

        static::assertEquals($this->stripExtraWhitespaceAndNewLines($expected), $this->stripExtraWhitespaceAndNewLines($output));
    }

    public function diffContentProvider()
    {
        return new HtmlFileIterator(__DIR__.'/../../../../fixtures/HtmlDiff');
    }
}
