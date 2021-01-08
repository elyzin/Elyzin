<?php

/**
 * Substring
 *
 * @param string $str
 * @param int $start
 * @param int $length
 * @return string
 */
return function ($str, $start, $length = null) {
    static $exists = null;
    if ($exists === null) {
        $exists = \function_exists('mb_substr');
    }
    // Type validation:
    if (!\is_string($str)) {
        throw new \Exception(
            "Helper Substr() expects a string"
        );
    }

    if ($exists) {
        // mb_substr($str, 0, NULL, '8bit') returns an empty string on PHP
        // 5.3, so we have to find the length ourselves.
        if (!isset($length)) {
            if ($start >= 0) {
                $length = Helper::strlen($str) - $start;
            } else {
                $length = -$start;
            }
        }

        return \mb_substr($str, $start, $length, '8bit');
    }

    // Unlike mb_substr(), substr() doesn't accept NULL for length
    if (isset($length)) {
        return \substr($str, $start, $length);
    } else {
        return \substr($str, $start);
    }
};
