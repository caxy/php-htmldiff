<?php

namespace Caxy\HtmlDiff;

class Candidate
{
    protected $a;

    protected $b;

    /**
     * @var null|Candidate
     */
    protected $previous;

    public function __construct($a, $b, Candidate $previous = null)
    {
        $this->a = $a;
        $this->b = $b;
        $this->previous = $previous;
    }

    /**
     * @return mixed
     */
    public function getA()
    {
        return $this->a;
    }

    /**
     * @param mixed $a
     *
     * @return Candidate
     */
    public function setA($a)
    {
        $this->a = $a;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getB()
    {
        return $this->b;
    }

    /**
     * @param mixed $b
     *
     * @return Candidate
     */
    public function setB($b)
    {
        $this->b = $b;

        return $this;
    }

    /**
     * @return Candidate|null
     */
    public function getPrevious()
    {
        return $this->previous;
    }

    /**
     * @param Candidate|null $previous
     *
     * @return Candidate
     */
    public function setPrevious($previous)
    {
        $this->previous = $previous;

        return $this;
    }


}
