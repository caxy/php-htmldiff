<?php

namespace Caxy\Tests;

abstract class AbstractTest extends \PHPUnit_Framework_TestCase
{
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