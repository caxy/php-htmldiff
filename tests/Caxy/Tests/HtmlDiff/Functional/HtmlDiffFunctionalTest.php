<?php

namespace Caxy\Tests\HtmlDiff\Functional;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\HtmlDiff\HtmlDiffConfig;
use Caxy\Tests\AbstractTest;
use Caxy\Tests\HtmlDiff\HtmlFileIterator;

class HtmlDiffFunctionalTest extends AbstractTest
{
    /**
     * @dataProvider diffContentProvider
     *
     * @param array<string, int|bool|string> $options
     */
    public function testHtmlDiff(string $oldText, string $newText, string $expected, array $options)
    {
        $diff = new HtmlDiff(trim($oldText), trim($newText), 'UTF-8', []);

        foreach ($options as $option => $value) {
            $diff->getConfig()->{$option}($value);
        }

        $output = $diff->build();

        if (isset($options['setKeepNewLines']) === false || $options['setKeepNewLines'] === false) {
            $output   = $this->stripExtraWhitespaceAndNewLines($output);
            $expected = $this->stripExtraWhitespaceAndNewLines($expected);
        }

        static::assertEquals(trim($expected), trim($output));
    }

    public function diffContentProvider()
    {
        return new HtmlFileIterator(__DIR__.'/../../../../fixtures/HtmlDiff');
    }
}
