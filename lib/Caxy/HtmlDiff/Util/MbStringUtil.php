<?php

namespace Caxy\HtmlDiff\Util;

/**
 * Using multi-byte functions have a huge performance impact on the diff algorithm.
 *
 * Therefor we added this wrapper around common string function that only uses mb_* functions if they
 * are necessary for the data-set we are processing.
 */
class MbStringUtil
{
    /**
     * @var bool
     */
    protected $mbRequired;

    public function __construct($oldText, $newText)
    {
        $this->mbRequired =
            strlen($oldText) !== mb_strlen($oldText) ||
            strlen($newText) !== mb_strlen($newText);

        if (true === $this->mbRequired) {
            mb_substitute_character(0x20);
        }
    }

    public function strlen($string)
    {
        if (true === $this->mbRequired) {
            return mb_strlen($string);
        }

        return strlen($string);
    }

    public function strpos($haystack, $needle, $offset = 0)
    {
        if (true === $this->mbRequired) {
            return mb_strpos($haystack, $needle, $offset);
        }

        return strpos($haystack, $needle, $offset);
    }

    public function stripos($haystack, $needle, $offset = 0)
    {
        if (true === $this->mbRequired) {
            return mb_stripos($haystack, $needle, $offset);
        }

        return stripos($haystack, $needle, $offset);
    }

    public function substr($string, $start, $length = null)
    {
        if (true === $this->mbRequired) {
            return mb_substr($string, $start, $length);
        }

        return substr($string, $start, $length);
    }
}
