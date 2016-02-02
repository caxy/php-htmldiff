<?php

namespace Caxy\Tests\HtmlDiff\Functional;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\Tests\HtmlDiff\HtmlFileIterator;

class HtmlDiffFunctionalTest extends \PHPUnit_Framework_TestCase
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

    protected function stripExtraWhitespaceAndNewLines($text)
    {
        return trim(
            preg_replace(
                '/>\s+</',
                '><',
                preg_replace('/\s+/S', " ", preg_replace("/[\n\r]/", '', $text))
            )
        );
    }
}
