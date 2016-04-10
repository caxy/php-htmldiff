<?php

namespace Caxy\HtmlDiff\Table;

/**
 * Class TableMatch.
 */
class TableMatch
{
    /**
     * @var int
     */
    public $startInOld;
    /**
     * @var int
     */
    public $startInNew;
    /**
     * @var int
     */
    public $endInOld;
    /**
     * @var int
     */
    public $endInNew;

    /**
     * TableMatch constructor.
     *
     * @param int $startInOld
     * @param int $startInNew
     * @param int $endInOld
     * @param int $endInNew
     */
    public function __construct($startInOld, $startInNew, $endInOld, $endInNew)
    {
        $this->startInOld = $startInOld;
        $this->startInNew = $startInNew;
        $this->endInOld = $endInOld;
        $this->endInNew = $endInNew;
    }

    /**
     * @return int
     */
    public function getStartInOld()
    {
        return $this->startInOld;
    }

    /**
     * @return int
     */
    public function getStartInNew()
    {
        return $this->startInNew;
    }

    /**
     * @return int
     */
    public function getEndInOld()
    {
        return $this->endInOld;
    }

    /**
     * @return int
     */
    public function getEndInNew()
    {
        return $this->endInNew;
    }
}
