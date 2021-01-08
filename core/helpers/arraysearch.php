<?php

return function ($needle, $haystack)
{
    foreach ($haystack as $key => $value) {
        if ($key === $needle) {
            return $value;
        } elseif (is_array($value)) {
            $result = self::$functions[pathinfo(basename(__FILE__), PATHINFO_FILENAME)]($needle, $value);
            if ($result !== NULL){
                return $result;
            }
        }
    }
    return NULL;
};