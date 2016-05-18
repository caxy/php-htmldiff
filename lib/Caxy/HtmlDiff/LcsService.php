<?php

namespace Caxy\HtmlDiff;

class LcsService
{
    /**
     * @var null|callable
     */
    protected $comparator;

    /**
     * LcsService constructor.
     *
     * @param null|callable $comparator
     */
    public function __construct($comparator = null)
    {
        if (null === $comparator) {
            $comparator = function ($a, $b) {
                return $a === $b;
            };
        }

        $this->comparator = $comparator;
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

        $comparator = $this->comparator;

        for ($i = 0; $i <= $m; $i++) {
            $c[$i][0] = 0;
        }

        for ($j = 0; $j <= $n; $j++) {
            $c[0][$j] = 0;
        }


        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($comparator($a[$i - 1], $b[$j - 1])) {
                    $c[$i][$j] = 1 + (isset($c[$i - 1][$j - 1]) ? $c[$i - 1][$j - 1] : 0);
                } else {
                    $c[$i][$j] = max(
                        isset($c[$i][$j - 1]) ? $c[$i][$j - 1] : 0,
                        isset($c[$i - 1][$j]) ? $c[$i - 1][$j] : 0
                    );
                }
            }
        }

        $lcs = array_pad([], $m + 1, 0);
        $this->compileMatches($c, $a, $b, $m, $n, $comparator, $lcs);

        return $lcs;
    }

    protected function compileMatches($c, $a, $b, $i, $j, $comparator, &$matches)
    {
        if ($i > 0 && $j > 0 && $comparator($a[$i - 1], $b[$j - 1])) {
            $this->compileMatches($c, $a, $b, $i - 1, $j - 1, $comparator, $matches);
            $matches[$i] = $j;
        } elseif ($j > 0 && ($i === 0 || $c[$i][$j - 1] >= $c[$i - 1][$j])) {
            $this->compileMatches($c, $a, $b, $i, $j - 1, $comparator, $matches);
        } elseif ($i > 0 && ($j === 0 || $c[$i][$j - 1] < $c[$i - 1][$j])) {
            $this->compileMatches($c, $a, $b, $i - 1, $j, $comparator, $matches);
        }
    }
}
