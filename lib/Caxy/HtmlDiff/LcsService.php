<?php

namespace Caxy\HtmlDiff;

use Caxy\HtmlDiff\Strategy\EqualMatchStrategy;
use Caxy\HtmlDiff\Strategy\MatchStrategyInterface;

class LcsService
{
    /**
     * @var MatchStrategyInterface
     */
    protected $matchStrategy;

    /**
     * LcsService constructor.
     *
     * @param MatchStrategyInterface $matchStrategy
     */
    public function __construct(MatchStrategyInterface $matchStrategy = null)
    {
        if (null === $matchStrategy) {
            $matchStrategy = new EqualMatchStrategy();
        }

        $this->matchStrategy = $matchStrategy;
    }

    /**
     * @param array $a
     * @param array $b
     *
     * @return array
     */
    public function longestCommonSubsequence(array $a, array $b)
    {
        $c = array();

        $m = count($a);
        $n = count($b);

        for ($i = 0; $i <= $m; $i++) {
            $c[$i][0] = 0;
        }

        for ($j = 0; $j <= $n; $j++) {
            $c[0][$j] = 0;
        }

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($this->matchStrategy->isMatch($a[$i - 1], $b[$j - 1])) {
                    $c[$i][$j] = 1 + (isset($c[$i - 1][$j - 1]) ? $c[$i - 1][$j - 1] : 0);
                } else {
                    $c[$i][$j] = max(
                        isset($c[$i][$j - 1]) ? $c[$i][$j - 1] : 0,
                        isset($c[$i - 1][$j]) ? $c[$i - 1][$j] : 0
                    );
                }
            }
        }

        $lcs = array_pad(array(), $m + 1, 0);
        $this->compileMatches($c, $a, $b, $m, $n, $lcs);

        return $lcs;
    }

    /**
     * @param $c
     * @param $a
     * @param $b
     * @param $i
     * @param $j
     * @param $matches
     */
    protected function compileMatches($c, $a, $b, $i, $j, &$matches)
    {
        if ($i > 0 && $j > 0 && $this->matchStrategy->isMatch($a[$i - 1], $b[$j - 1])) {
            $this->compileMatches($c, $a, $b, $i - 1, $j - 1, $matches);
            $matches[$i] = $j;
        } elseif ($j > 0 && ($i === 0 || $c[$i][$j - 1] >= $c[$i - 1][$j])) {
            $this->compileMatches($c, $a, $b, $i, $j - 1, $matches);
        } elseif ($i > 0 && ($j === 0 || $c[$i][$j - 1] < $c[$i - 1][$j])) {
            $this->compileMatches($c, $a, $b, $i - 1, $j, $matches);
        }
    }
}
