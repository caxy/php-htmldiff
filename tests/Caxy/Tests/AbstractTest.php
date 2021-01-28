<?php

namespace Caxy\Tests;

use PHPUnit\Framework\TestCase;

abstract class AbstractTest extends TestCase
{
    protected function stripExtraWhitespaceAndNewLines(string $text)
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
