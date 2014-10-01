<?php

namespace Caxy\HtmlDiff;

class TablePosition
{
    public $row;
    public $cell;

    public function __construct($row, $cell)
    {
        $this->row = $row;
        $this->cell = $cell;
    }
}
