<?php

namespace Caxy\HtmlDiff\Table;

class TableMatch extends AbstractTableElement
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

    public function getStartInOld()
    {
        return $this->startInOld;
    }

    public function getStartInNew()
    {
        return $this->startInNew;
    }

    public function getEndInOld()
    {
        return $this->endInOld;
    }

    public function getEndInNew()
    {
        return $this->endInNew;
    }
}
