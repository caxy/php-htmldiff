<?php

namespace Caxy\Tests\HtmlDiff\Performance;

use Caxy\HtmlDiff\HtmlDiff;
use Caxy\Tests\AbstractTest;

class PerformanceTest extends AbstractTest
{
    private const FIXTURE_PATH = __DIR__ . '/../../../../fixtures/Performance/';

    /**
     * @group performance
     */
    public function testParagraphPerformance()
    {
        $expected = file_get_contents(self::FIXTURE_PATH . 'paragraphs_expected.html');

        $diff = new HtmlDiff(
            file_get_contents(self::FIXTURE_PATH . 'paragraphs.html'),
            file_get_contents(self::FIXTURE_PATH . 'paragraphs_changed.html'),
            'UTF-8', array()
        );

        $output = $diff->build();

        self::assertSame(
            $this->stripExtraWhitespaceAndNewLines($expected),
            $this->stripExtraWhitespaceAndNewLines($output)
        );
    }
}
