<?php

/**
 * Displays the variable (string or array) in a formatted way
 *
 * @param mix $var
 * @return null
 */
return function ($var) {
    if (\is_string($var)) {
        echo $var . '<br />';
    } else if (\is_array($var)) {
        echo '<pre style="text-align: left;">';
        print_r($var);
        echo '</pre>';
    } else {
        // Do nothing
    }
};
