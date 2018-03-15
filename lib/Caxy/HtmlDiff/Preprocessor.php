<?php

namespace Caxy\HtmlDiff;

class Preprocessor
{
    public static function diffCommonPrefix($old, $new)
    {
        // Quick check for common null cases.
        if (mb_strlen($old) == 0 || mb_strlen($new) == 0 || mb_substr($old, 0, 1) != mb_substr($new, 0, 1)) {
            return 0;
        }

        // Binary Search
        $pointerMin = 0;
        $pointerMax = min(mb_strlen($old), mb_strlen($new));
        $pointerMid = $pointerMax;
        $pointerStart = 0;
        while ($pointerMin < $pointerMid) {
            $cmp = substr_compare(
                $old,
                mb_substr($new, $pointerStart, $pointerMid - $pointerStart),
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

    public static function diffCommonSuffix($old, $new)
    {
        // Quick check for common null cases.
        if (mb_strlen($old) == 0 || mb_strlen($new) == 0 || mb_substr($old, mb_strlen($old) - 1, 1) != mb_substr($new, mb_strlen($new) - 1, 1)) {
            return 0;
        }

        // Binary Search
        $pointerMin = 0;
        $pointerMax = min(mb_strlen($old), mb_strlen($new));
        $pointerMid = $pointerMax;
        $pointerEnd = 0;
        $oldLen = mb_strlen($old);
        $newLen = mb_strlen($new);
        while ($pointerMin < $pointerMid) {
            if (mb_substr($old, $oldLen - $pointerMid, $pointerMid - $pointerEnd) == mb_substr($new, $newLen - $pointerMid, $pointerMid - $pointerEnd)) {
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
