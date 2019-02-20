<?php

namespace Caxy\HtmlDiff;

class Preprocessor
{
    public static function diffCommonPrefix($old, $new, $stringUtil)
    {
        // Quick check for common null cases.
        if ($stringUtil->strlen($old) == 0 || $stringUtil->strlen($new) == 0 || $stringUtil->substr($old, 0, 1) != $stringUtil->substr($new, 0, 1)) {
            return 0;
        }

        // Binary Search
        $pointerMin = 0;
        $pointerMax = min($stringUtil->strlen($old), $stringUtil->strlen($new));
        $pointerMid = $pointerMax;
        $pointerStart = 0;
        while ($pointerMin < $pointerMid) {
            $cmp = substr_compare(
                $old,
                $stringUtil->substr($new, $pointerStart, $pointerMid - $pointerStart),
                $pointerStart,
                $pointerMid - $pointerStart
            );
            if (0 === $cmp) {
                $pointerMin = $pointerMid;
                $pointerStart = $pointerMin;
            } else {
                $pointerMax = $pointerMid;
            }
            $pointerMid = floor(($pointerMax - $pointerMin) / 2 + $pointerMin);
        }
        return $pointerMid;
    }

    public static function diffCommonSuffix($old, $new, $stringUtil)
    {
        // Quick check for common null cases.
        if ($stringUtil->strlen($old) == 0 || $stringUtil->strlen($new) == 0 || $stringUtil->substr($old, $stringUtil->strlen($old) - 1, 1) != $stringUtil->substr($new, $stringUtil->strlen($new) - 1, 1)) {
            return 0;
        }

        // Binary Search
        $pointerMin = 0;
        $pointerMax = min($stringUtil->strlen($old), $stringUtil->strlen($new));
        $pointerMid = $pointerMax;
        $pointerEnd = 0;
        $oldLen = $stringUtil->strlen($old);
        $newLen = $stringUtil->strlen($new);
        while ($pointerMin < $pointerMid) {
            if ($stringUtil->substr($old, $oldLen - $pointerMid, $pointerMid - $pointerEnd) == $stringUtil->substr($new, $newLen - $pointerMid, $pointerMid - $pointerEnd)) {
                $pointerMin = $pointerMid;
                $pointerEnd = $pointerMin;
            } else {
                $pointerMax = $pointerMid;
            }
            $pointerMid = floor(($pointerMax - $pointerMin) / 2 + $pointerMin);
        }
        return $pointerMid;
    }
}
