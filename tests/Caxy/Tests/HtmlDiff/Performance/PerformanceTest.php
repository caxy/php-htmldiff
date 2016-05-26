<?php

namespace Caxy\Tests\HtmlDiff\Performance;

use Caxy\HtmlDiff\HtmlDiff;

class PerformanceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group performance
     */
    public function testParagraphPerformance()
    {
        $diff = new HtmlDiff(
            file_get_contents(__DIR__ . '/../../../../fixtures/Performance/paragraphs.html'),
            file_get_contents(__DIR__ . '/../../../../fixtures/Performance/paragraphs_changed.html'),
            'UTF-8', array()
        );

        $diff->build();
    }
}
