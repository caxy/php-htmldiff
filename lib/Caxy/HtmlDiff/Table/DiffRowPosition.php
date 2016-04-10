<?php

namespace Caxy\HtmlDiff\Table;

/**
 * Class DiffRowPosition.
 */
class DiffRowPosition
{
    /**
     * @var int
     */
    protected $indexInOld;

    /**
     * @var int
     */
    protected $indexInNew;

    /**
     * @var int
     */
    protected $columnInOld;

    /**
     * @var int
     */
    protected $columnInNew;

    /**
     * DiffRowPosition constructor.
     *
     * @param int $indexInOld
     * @param int $indexInNew
     * @param int $columnInOld
     * @param int $columnInNew
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
     *
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
     *
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
     *
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
     *
     * @return DiffRowPosition
     */
    public function setColumnInNew($columnInNew)
    {
        $this->columnInNew = $columnInNew;

        return $this;
    }

    /**
     * @param int $increment
     *
     * @return int
     */
    public function incrementColumnInNew($increment = 1)
    {
        $this->columnInNew += $increment;

        return $this->columnInNew;
    }

    /**
     * @param int $increment
     *
     * @return int
     */
    public function incrementColumnInOld($increment = 1)
    {
        $this->columnInOld += $increment;

        return $this->columnInOld;
    }

    /**
     * @param int $increment
     *
     * @return int
     */
    public function incrementIndexInNew($increment = 1)
    {
        $this->indexInNew += $increment;

        return $this->indexInNew;
    }

    /**
     * @param int $increment
     *
     * @return int
     */
    public function incrementIndexInOld($increment = 1)
    {
        $this->indexInOld += $increment;

        return $this->indexInOld;
    }

    /**
     * @param string $type
     * @param int    $increment
     *
     * @return int
     */
    public function incrementIndex($type, $increment = 1)
    {
        if ($type === 'new') {
            return $this->incrementIndexInNew($increment);
        }

        return $this->incrementIndexInOld($increment);
    }

    /**
     * @param string $type
     * @param int    $increment
     *
     * @return int
     */
    public function incrementColumn($type, $increment = 1)
    {
        if ($type === 'new') {
            return $this->incrementColumnInNew($increment);
        }

        return $this->incrementColumnInOld($increment);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isColumnLessThanOther($type)
    {
        if ($type === 'new') {
            return $this->getColumnInNew() < $this->getColumnInOld();
        }

        return $this->getColumnInOld() < $this->getColumnInNew();
    }

    /**
     * @param string $type
     *
     * @return int
     */
    public function getColumn($type)
    {
        if ($type === 'new') {
            return $this->getColumnInNew();
        }

        return $this->getColumnInOld();
    }

    /**
     * @param string $type
     *
     * @return int
     */
    public function getIndex($type)
    {
        if ($type === 'new') {
            return $this->getIndexInNew();
        }

        return $this->getIndexInOld();
    }

    /**
     * @return bool
     */
    public function areColumnsEqual()
    {
        return $this->getColumnInOld() === $this->getColumnInNew();
    }

    /**
     * @return null|string
     */
    public function getLesserColumnType()
    {
        if ($this->isColumnLessThanOther('new')) {
            return 'new';
        } elseif ($this->isColumnLessThanOther('old')) {
            return 'old';
        }

        return;
    }
}
