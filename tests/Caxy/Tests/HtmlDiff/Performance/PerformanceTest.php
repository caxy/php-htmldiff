<?php

namespace Caxy\Tests\HtmlDiff\Performance;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\Tests\AbstractTest;

class PerformanceTest extends AbstractTest
{
    /**
     * @group performance
     */
    public function testParagraphPerformance()
    {
        $fixturesPath = __DIR__ . '/../../../../fixtures/Performance/';

        $expected = file_get_contents($fixturesPath . 'paragraphs_expected.html');

        $diff = new HtmlDiff(
            file_get_contents($fixturesPath . 'paragraphs.html'),
            file_get_contents($fixturesPath . 'paragraphs_changed.html'),
            'UTF-8', array()
        );

        $output = $diff->build();

        $this->assertSame($this->stripExtraWhitespaceAndNewLines($output), $this->stripExtraWhitespaceAndNewLines($expected));
    }
}
