<?php

namespace Caxy\HtmlDiff\Strategy;

class EqualMatchStrategy implements MatchStrategyInterface
{
    /**
     * @param string $a
     * @param string $b
     *
     * @return bool
     */
    public function isMatch($a, $b)
    {
        return $a === $b;
    }
}
