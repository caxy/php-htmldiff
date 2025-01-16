<?php

namespace Caxy\HtmlDiff\Table;

/**
 * Class TableCell.
 */
class TableCell extends AbstractTableElement
{
    /**
     * @var TableRow
     */
    protected $row;

    /**
     * @return TableRow
     */
    public function getRow()
    {
        return $this->row;
    }

    /**
     * @param TableRow|null $row
     *
     * @return $this
     */
    public function setRow(?TableRow $row = null)
    {
        $this->row = $row;

        if (null !== $row && !in_array($this, $row->getCells())) {
            $row->addCell($this);
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getColspan()
    {
        return (int) $this->getAttribute('colspan') ?: 1;
    }

    /**
     * @return int
     */
    public function getRowspan()
    {
        return (int) $this->getAttribute('rowspan') ?: 1;
    }
}
