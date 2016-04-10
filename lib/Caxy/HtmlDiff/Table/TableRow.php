<?php

namespace Caxy\HtmlDiff\Table;

/**
 * Class TableRow.
 */
class TableRow extends AbstractTableElement
{
    /**
     * @var Table
     */
    protected $table;

    /**
     * @var TableCell[]
     */
    protected $cells = array();

    /**
     * @return Table
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param Table|null $table
     *
     * @return $this
     */
    public function setTable(Table $table = null)
    {
        $this->table = $table;

        if ($table && !in_array($this, $table->getRows())) {
            $table->addRow($this);
        }

        return $this;
    }

    /**
     * @return TableCell[]
     */
    public function getCells()
    {
        return $this->cells;
    }

    /**
     * @param TableCell $cell
     *
     * @return $this
     */
    public function addCell(TableCell $cell)
    {
        $this->cells[] = $cell;

        if (!$cell->getRow()) {
            $cell->setRow($this);
        }

        return $this;
    }

    /**
     * @param TableCell $cell
     */
    public function removeCell(TableCell $cell)
    {
        $key = array_search($cell, $this->cells, true);

        if ($key !== false) {
            unset($this->cells[$key]);
            if ($cell->getRow()) {
                $cell->setRow(null);
            }
        }
    }

    /**
     * @param int $index
     *
     * @return TableCell|null
     */
    public function getCell($index)
    {
        return isset($this->cells[$index]) ? $this->cells[$index] : null;
    }

    /**
     * @param TableCell[] $cells
     * @param null|int    $position
     */
    public function insertCells($cells, $position = null)
    {
        if ($position === null) {
            $this->cells = array_merge($this->cells, $cells);
        } else {
            array_splice($this->cells, $position, 0, $cells);
        }
    }
}
