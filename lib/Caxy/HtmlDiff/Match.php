<?php

namespace Caxy\HtmlDiff;

class Match
{
    public $startInOld;
    public $startInNew;
    public $size;

    public function __construct($startInOld, $startInNew, $size)
    {
        $this->startInOld = $startInOld;
        $this->startInNew = $startInNew;
        $this->size = $size;
    }

    public function endInOld()
    {
        return $this->startInOld + $this->size;
    }

    public function endInNew()
    {
        return $this->startInNew + $this->size;
    }
}
