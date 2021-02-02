<?php

namespace Caxy\HtmlDiff;

/**
 * A (string) block of text that is the same between the two provided versions.
 */
class MatchingBlock implements \Countable
{
    public $startInOld;
    public $startInNew;
    public $size;

    public function __construct(int $startInOld, int $startInNew, int $size)
    {
        $this->startInOld = $startInOld;
        $this->startInNew = $startInNew;
        $this->size       = $size;
    }

    public function endInOld() : int
    {
        return ($this->startInOld + $this->size);
    }

    public function endInNew() : int
    {
        return ($this->startInNew + $this->size);
    }

    public function count() : int
    {
        return (int)$this->size;
    }
}
