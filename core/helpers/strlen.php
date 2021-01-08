<?php

/**
 * Calculate the length of a string
 *
 * @param string $str
 * @return int
 */
return function ($str) {
    if (!\is_string($str)) {
        throw new InvalidArgumentException(
            "ourStrlen() expects a string"
        );
    }

    $exists = \function_exists('mb_strlen');
    if ($exists) {
        $length = \mb_strlen($str, '8bit');
        if ($length === false) {
            throw new CannotPerformOperationException();
        }
        return $length;
    } else {
        return \strlen($str);
    }
};
