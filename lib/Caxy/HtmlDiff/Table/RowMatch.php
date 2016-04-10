<?php

namespace Caxy\HtmlDiff\Table;

/**
 * Class RowMatch.
 */
class RowMatch
{
    /**
     * @var int
     */
    protected $startInNew;

    /**
     * @var int
     */
    protected $startInOld;

    /**
     * @var int
     */
    protected $endInNew;

    /**
     * @var int
     */
    protected $endInOld;

    /**
     * @var float|null
     */
    protected $percentage;

    /**
     * RowMatch constructor.
     *
     * @param int        $startInNew
     * @param int        $startInOld
     * @param int        $endInNew
     * @param int        $endInOld
     * @param float|null $percentage
     */
    public function __construct($startInNew = 0, $startInOld = 0, $endInNew = 0, $endInOld = 0, $percentage = null)
    {
        $this->startInNew = $startInNew;
        $this->startInOld = $startInOld;
        $this->endInNew = $endInNew;
        $this->endInOld = $endInOld;
        $this->percentage = $percentage;
    }

    /**
     * @return int
     */
    public function getStartInNew()
    {
        return $this->startInNew;
    }

    /**
     * @param int $startInNew
     *
     * @return RowMatch
     */
    public function setStartInNew($startInNew)
    {
        $this->startInNew = $startInNew;

        return $this;
    }

    /**
     * @return int
     */
    public function getStartInOld()
    {
        return $this->startInOld;
    }

    /**
     * @param int $startInOld
     *
     * @return RowMatch
     */
    public function setStartInOld($startInOld)
    {
        $this->startInOld = $startInOld;

        return $this;
    }

    /**
     * @return int
     */
    public function getEndInNew()
    {
        return $this->endInNew;
    }

    /**
     * @param int $endInNew
     *
     * @return RowMatch
     */
    public function setEndInNew($endInNew)
    {
        $this->endInNew = $endInNew;

        return $this;
    }

    /**
     * @return int
     */
    public function getEndInOld()
    {
        return $this->endInOld;
    }

    /**
     * @param int $endInOld
     *
     * @return RowMatch
     */
    public function setEndInOld($endInOld)
    {
        $this->endInOld = $endInOld;

        return $this;
    }
}
