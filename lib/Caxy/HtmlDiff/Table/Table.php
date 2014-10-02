<?php

namespace Caxy\HtmlDiff\Table;

class Table extends AbstractTableElement
{
    protected $rows = array();

    protected $domNode;

    public function getRows()
    {
        return $this->rows;
    }

    public function addRow(TableRow $row)
    {
        $this->rows[] = $row;

        if (!$row->getTable()) {
            $row->setTable($this);
        }
    }

    public function removeRow(TableRow $row)
    {
        $key = array_search($row, $this->rows, true);

        if ($key !== false) {
            unset($this->rows[$key]);
            if ($row->getTable()) {
                $row->setTable(null);
            }
        }
    }

    public function getRow($index)
    {
        return isset($this->rows[$index]) ? $this->rows[$index] : null;
    }

    public function insertRows($rows, $position = null)
    {
        if ($position === null) {
            $this->rows = array_merge($this->rows, $rows);
        } else {
            array_splice($this->rows, $position, 0, $rows);
        }
    }

    public function getCellByPosition(TablePosition $position)
    {
        $row = $this->getRow($position->getRow());

        return $row ? $row->getCell($position->getCell()) : null;
    }

    public function getPositionBefore(TablePosition $position, $offset = 1)
    {
        if ($position->getCell() > ($offset - 1)) {
            $newRow = $position->getRow();
            $newCell = $position->getCell() - $offset;
        } elseif ($position->getRow() > 0) {
            $cellsToMove = $offset;
            $newRow = $position->getRow();
            $newCell = $position->getCell();

            while ($cellsToMove > 0 && $newRow >= 0) {
                if ($cellsToMove > $newCell) {
                    $newRow--;
                    if ($newRow < 0) {
                        return null;
                    }
                    
                    $cellsToMove = $cellsToMove - ($newCell + 1);
                    $cellCount = count($this->getRow($newRow)->getCells());
                    $newCell = $cellCount - 1;
                } else {
                    $newCell = $newCell - $cellsToMove;
                    $cellsToMove -= $newCell;
                }
            }
        } else {
            return null;
        }

        if ($newRow >= 0 && $newCell >= 0) {
            return new TablePosition($newRow, $newCell);
        }

        return null;
    }

    public function getPositionAfter(TablePosition $position, $offset = 1)
    {
        $cellsToMove = $offset;
        $newRow = $position->getRow();
        $newCell = $position->getCell();

        while ($cellsToMove > 0 && $newRow < count($this->rows)) {
            $cellCount = count($this->getRow($newRow)->getCells());

            $cellsLeft = $cellCount - $newCell - 1;

            if ($cellsToMove > $cellsLeft) {
                $newRow++;
                $cellsToMove -= $cellsLeft - 1;
                $newCell = 0;
            } else {
                $newCell = $newCell + $cellsToMove;
                $cellsToMove -= $cellsLeft;
            }
        }

        if ($newRow >= 0 && $newCell >= 0) {
            return new TablePosition($newRow, $newCell);
        }

        return null;
    }
}
