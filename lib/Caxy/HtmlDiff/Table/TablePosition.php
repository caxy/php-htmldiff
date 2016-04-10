<?php

namespace Caxy\HtmlDiff\Table;

/**
 * Class TablePosition.
 */
class TablePosition
{
    /**
     * @var int
     */
    public $row;
    /**
     * @var int
     */
    public $cell;

    /**
     * TablePosition constructor.
     *
     * @param int $row
     * @param int $cell
     */
    public function __construct($row, $cell)
    {
        $this->row = $row;
        $this->cell = $cell;
    }

    /**
     * @return int
     */
    public function getRow()
    {
        return $this->row;
    }

    /**
     * @return int
     */
    public function getCell()
    {
        return $this->cell;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->row.':'.$this->cell;
    }

    /**
     * @param TablePosition $a
     * @param TablePosition $b
     *
     * @return int
     */
    public static function compare($a, $b)
    {
        if ($a->getRow() == $b->getRow()) {
            return $a->getCell() - $b->getCell();
        }

        return $a->getRow() - $b->getRow();
    }
}
