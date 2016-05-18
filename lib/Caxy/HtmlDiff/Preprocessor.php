<?php

namespace Caxy\HtmlDiff;

class Preprocessor
{
    public static function diffCommonPrefix($old, $new)
    {
        // Quick check for common null cases.
        if (strlen($old) == 0 || strlen($new) == 0 || substr($old, 0, 1) != substr($new, 0, 1)) {
            return 0;
        }

        // Binary Search
        $pointerMin = 0;
        $pointerMax = min(strlen($old), strlen($new));
        $pointerMid = $pointerMax;
        $pointerStart = 0;
        while ($pointerMin < $pointerMid) {
            $cmp = substr_compare(
                $old,
                substr($new, $pointerStart, $pointerMid - $pointerStart),
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
        if (strlen($old) == 0 || strlen($new) == 0 || substr($old, strlen($old) - 1, 1) != substr($new, strlen($new) - 1, 1)) {
            return 0;
        }

        // Binary Search
        $pointerMin = 0;
        $pointerMax = min(strlen($old), strlen($new));
        $pointerMid = $pointerMax;
        $pointerEnd = 0;
        $oldLen = strlen($old);
        $newLen = strlen($new);
        while ($pointerMin < $pointerMid) {
            if (substr($old, $oldLen - $pointerMid, $pointerMid - $pointerEnd) == substr($new, $newLen - $pointerMid, $pointerMid - $pointerEnd)) {
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
