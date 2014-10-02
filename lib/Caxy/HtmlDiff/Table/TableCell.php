<?php

namespace Caxy\HtmlDiff\Table;

class TableCell extends AbstractTableElement
{
    protected $row;

    public function getRow()
    {
        return $this->row;
    }

    public function setRow(TableRow $row = null)
    {
        $this->row = $row;

        if (!in_array($this, $row->getCells())) {
            $row->addCell($this);
        }

        return $this;
    }
}
