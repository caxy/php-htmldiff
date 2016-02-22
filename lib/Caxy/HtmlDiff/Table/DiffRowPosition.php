<?php

namespace Caxy\HtmlDiff\Table;

class DiffRowPosition
{
    protected $indexInOld;

    protected $indexInNew;

    protected $columnInOld;

    protected $columnInNew;

    /**
     * DiffRowPosition constructor.
     * @param $indexInOld
     * @param $indexInNew
     * @param $columnInOld
     * @param $columnInNew
     */
    public function __construct($indexInOld = 0, $indexInNew = 0, $columnInOld = 0, $columnInNew = 0)
    {
        $this->indexInOld = $indexInOld;
        $this->indexInNew = $indexInNew;
        $this->columnInOld = $columnInOld;
        $this->columnInNew = $columnInNew;
    }

    /**
     * @return int
     */
    public function getIndexInOld()
    {
        return $this->indexInOld;
    }

    /**
     * @param int $indexInOld
     * @return DiffRowPosition
     */
    public function setIndexInOld($indexInOld)
    {
        $this->indexInOld = $indexInOld;
        return $this;
    }

    /**
     * @return int
     */
    public function getIndexInNew()
    {
        return $this->indexInNew;
    }

    /**
     * @param int $indexInNew
     * @return DiffRowPosition
     */
    public function setIndexInNew($indexInNew)
    {
        $this->indexInNew = $indexInNew;
        return $this;
    }

    /**
     * @return int
     */
    public function getColumnInOld()
    {
        return $this->columnInOld;
    }

    /**
     * @param int $columnInOld
     * @return DiffRowPosition
     */
    public function setColumnInOld($columnInOld)
    {
        $this->columnInOld = $columnInOld;
        return $this;
    }

    /**
     * @return int
     */
    public function getColumnInNew()
    {
        return $this->columnInNew;
    }

    /**
     * @param int $columnInNew
     * @return DiffRowPosition
     */
    public function setColumnInNew($columnInNew)
    {
        $this->columnInNew = $columnInNew;
        return $this;
    }

    public function incrementColumnInNew($increment = 1)
    {
        $this->columnInNew += $increment;

        return $this->columnInNew;
    }

    public function incrementColumnInOld($increment = 1)
    {
        $this->columnInOld += $increment;

        return $this->columnInOld;
    }

    public function incrementIndexInNew($increment = 1)
    {
        $this->indexInNew += $increment;

        return $this->indexInNew;
    }

    public function incrementIndexInOld($increment = 1)
    {
        $this->indexInOld += $increment;

        return $this->indexInOld;
    }

    public function incrementIndex($type, $increment = 1)
    {
        if ($type === 'new') {
            return $this->incrementIndexInNew($increment);
        }

        return $this->incrementIndexInOld($increment);
    }

    public function incrementColumn($type, $increment = 1)
    {
        if ($type === 'new') {
            return $this->incrementColumnInNew($increment);
        }

        return $this->incrementColumnInOld($increment);
    }

    public function isColumnLessThanOther($type)
    {
        if ($type === 'new') {
            return $this->getColumnInNew() < $this->getColumnInOld();
        }

        return $this->getColumnInOld() < $this->getColumnInNew();
    }

    public function getColumn($type)
    {
        if ($type === 'new') {
            return $this->getColumnInNew();
        }

        return $this->getColumnInOld();
    }

    public function getIndex($type)
    {
        if ($type === 'new') {
            return $this->getIndexInNew();
        }

        return $this->getIndexInOld();
    }

    public function areColumnsEqual()
    {
        return $this->getColumnInOld() === $this->getColumnInNew();
    }

    public function getLesserColumnType()
    {
        if ($this->isColumnLessThanOther('new')) {
            return 'new';
        } elseif ($this->isColumnLessThanOther('old')) {
            return 'old';
        }

        return null;
    }
}