<?php

namespace Caxy\HtmlDiff\Table;

/**
 * Class Table.
 */
class Table extends AbstractTableElement
{
    /**
     * @var TableRow[]
     */
    protected $rows = array();

    /**
     * @return TableRow[]
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param TableRow $row
     */
    public function addRow(TableRow $row)
    {
        $this->rows[] = $row;

        if (!$row->getTable()) {
            $row->setTable($this);
        }
    }

    /**
     * @param TableRow $row
     */
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

    /**
     * @param int $index
     *
     * @return null|TableRow
     */
    public function getRow($index)
    {
        return isset($this->rows[$index]) ? $this->rows[$index] : null;
    }

    /**
     * @param TableRow[] $rows
     * @param null|int   $position
     */
    public function insertRows($rows, $position = null)
    {
        if ($position === null) {
            $this->rows = array_merge($this->rows, $rows);
        } else {
            array_splice($this->rows, $position, 0, $rows);
        }
    }

    /**
     * @param TablePosition $position
     *
     * @return null|TableCell
     */
    public function getCellByPosition(TablePosition $position)
    {
        $row = $this->getRow($position->getRow());

        return $row ? $row->getCell($position->getCell()) : null;
    }

    /**
     * @param TablePosition $position
     * @param int           $offset
     *
     * @return TablePosition|null
     */
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
                    --$newRow;
                    if ($newRow < 0) {
                        return;
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
            return;
        }

        if ($newRow >= 0 && $newCell >= 0) {
            return new TablePosition($newRow, $newCell);
        }

        return;
    }

    /**
     * @param TablePosition $position
     * @param int           $offset
     *
     * @return TablePosition|null
     */
    public function getPositionAfter(TablePosition $position, $offset = 1)
    {
        $cellsToMove = $offset;
        $newRow = $position->getRow();
        $newCell = $position->getCell();

        while ($cellsToMove > 0 && $newRow < count($this->rows)) {
            $cellCount = count($this->getRow($newRow)->getCells());

            $cellsLeft = $cellCount - $newCell - 1;

            if ($cellsToMove > $cellsLeft) {
                ++$newRow;
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

        return;
    }
}
