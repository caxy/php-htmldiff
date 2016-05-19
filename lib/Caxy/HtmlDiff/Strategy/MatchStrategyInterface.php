<?php

namespace Caxy\HtmlDiff\Strategy;

interface MatchStrategyInterface
{
    /**
     * @param string $a
     * @param string $b
     *
     * @return bool
     */
    public function isMatch($a, $b);
}
