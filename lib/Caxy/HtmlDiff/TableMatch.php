<?php

namespace Caxy\HtmlDiff;

class TableMatch
{
    public $startInOld;
    public $startInNew;
    public $endInOld;
    public $endInNew;

    public function __construct($startInOld, $startInNew, $endInOld, $endInNew)
    {
        $this->startInOld = $startInOld;
        $this->startInNew = $startInNew;
        $this->endInOld = $endInOld;
        $this->endInNew = $endInNew;
    }
}
