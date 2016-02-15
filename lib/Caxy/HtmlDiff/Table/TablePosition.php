<?php

namespace Caxy\HtmlDiff\Table;

class TablePosition extends AbstractTableElement
{
    public $row;
    public $cell;

    public function __construct($row, $cell)
    {
        $this->row = $row;
        $this->cell = $cell;
    }

    public function getRow()
    {
        return $this->row;
    }

    public function getCell()
    {
        return $this->cell;
    }

    public function __toString()
    {
        return $this->row.':'.$this->cell;
    }

    public static function compare($a, $b)
    {
        if ($a->getRow() == $b->getRow()) {
            return $a->getCell() - $b->getCell();
        }

        return $a->getRow() - $b->getRow();
    }
}
