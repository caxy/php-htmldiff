<?php

namespace Caxy\HtmlDiff\Table;

class TableRow extends AbstractTableElement
{
    /**
     * @var Table
     */
    protected $table;

    /**
     * @var array
     */
    protected $cells = array();
    
    public function getTable()
    {
        return $this->table;
    }

    public function setTable(Table $table = null)
    {
        $this->table = $table;

        if (!in_array($this, $table->getRows())) {
            $table->addRow($this);
        }

        return $this;
    }

    public function getCells()
    {
        return $this->cells;
    }

    public function addCell(TableCell $cell)
    {
        $this->cells[] = $cell;

        if (!$cell->getRow()) {
            $cell->setRow($this);
        }

        return $this;
    }

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
     * @param $index
     *
     * @return TableCell|null
     */
    public function getCell($index)
    {
        return isset($this->cells[$index]) ? $this->cells[$index] : null;
    }

    /**
     * @param array $cells
     * @param null  $position
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
